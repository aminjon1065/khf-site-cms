<?php

namespace App\Http\Controllers\Cms;

use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Services\WorkflowService;
use App\Support\ContentTitle;
use App\Support\EditorialContent;
use App\Support\PublicSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bulk actions of the content lists, as in WordPress: move to the trash, send
 * for approval, publish. Each material goes through the checks of its single
 * action — the policy, the workflow and the publication checklist. What can't
 * be done is skipped with the reason, the rest is done.
 */
class EditorialBulkController extends Controller
{
    /**
     * A list page shows at most 100 rows.
     */
    private const MAX_ITEMS = 100;

    /**
     * Per action: the module permission it needs at all, the policy ability
     * checked on each material, and how the result is reported.
     *
     * @var array<string, array{permission: string, ability: string, done: string}>
     */
    private const ACTIONS = [
        'trash' => ['permission' => 'delete', 'ability' => 'delete', 'done' => 'Перемещено в корзину'],
        'submit' => ['permission' => 'edit', 'ability' => 'update', 'done' => 'Отправлено на согласование'],
        'publish' => ['permission' => 'publish', 'ability' => 'publish', 'done' => 'Опубликовано'],
    ];

    public function __construct(
        private readonly EditorialContent $content,
        private readonly WorkflowService $workflow,
    ) {}

    public function __invoke(Request $request, string $contentType): JsonResponse
    {
        abort_unless(in_array($contentType, $this->content->types(), true), 404);

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(array_keys(self::ACTIONS))],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'ids.*' => ['required', 'integer', 'distinct'],
        ], [
            'ids.required' => 'Выберите материалы.',
            'ids.max' => 'За раз можно обработать не больше '.self::MAX_ITEMS.' материалов.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $action = self::ACTIONS[$validated['action']];
        abort_unless($user->can("{$contentType}.{$action['permission']}"), 403);

        /** @var list<int> $ids */
        $ids = array_map('intval', $validated['ids']);
        $materials = $this->content->modelClass($contentType)::query()
            ->whereKey($ids)
            ->get()
            ->keyBy(fn (Model $material): int => (int) $material->getKey());
        $done = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $material = $materials->get($id);

            // Out of the user's reach is the same as missing: the reply must
            // not reveal the titles of other regions' materials.
            if (! $material instanceof Workflowable || $user->cannot('view', $material)) {
                $skipped[] = ['id' => $id, 'title' => "№ {$id}", 'reason' => 'Не найден — возможно, его уже удалили.'];

                continue;
            }

            $reason = $user->cannot($action['ability'], $material)
                ? 'Нет прав на этот материал.'
                : match ($validated['action']) {
                    'trash' => $this->trash($material),
                    'submit' => $this->submit($material, $user),
                    default => $this->publish($material, $user),
                };

            if ($reason === null) {
                $done++;

                continue;
            }

            $title = ContentTitle::of($material);
            $skipped[] = ['id' => $id, 'title' => $title !== '' ? $title : '— без заголовка —', 'reason' => $reason];
        }

        return response()->json([
            'done' => $done,
            'skipped' => $skipped,
            'message' => $done === count($ids)
                ? "{$action['done']}: {$done}."
                : "{$action['done']}: {$done} из ".count($ids).'.',
        ]);
    }

    private function trash(Model&Workflowable $material): ?string
    {
        if ($material instanceof Page && PublicSite::isSystemPage($material->slug)) {
            return 'Эту страницу выводит раздел сайта: её можно только снять с публикации.';
        }

        $material->delete();

        return null;
    }

    private function submit(Model&Workflowable $material, User $user): ?string
    {
        $status = $material->getWorkflowStatus();

        if (in_array($status, [ContentStatus::Review, ContentStatus::TranslationCheck, ContentStatus::Approved], true)) {
            return 'Уже на согласовании.';
        }

        if ($status === ContentStatus::Scheduled) {
            return 'Уже запланирован к публикации.';
        }

        if ($status->isPublic()) {
            return 'Уже на сайте.';
        }

        if (! $this->workflow->canTransition($status, ContentStatus::Review)) {
            return "Статус «{$status->label()}»: сначала верните материал в черновики.";
        }

        return $this->transition($material, ContentStatus::Review, $user);
    }

    private function publish(Model&Workflowable $material, User $user): ?string
    {
        $status = $material->getWorkflowStatus();

        if ($status->isPublic()) {
            return 'Уже на сайте.';
        }

        if (! $this->workflow->canTransition($status, ContentStatus::Published)) {
            return "Статус «{$status->label()}»: из списка не опубликовать, откройте материал.";
        }

        return $this->transition($material, ContentStatus::Published, $user);
    }

    /**
     * The transition of the single action. A check it fails (the publication
     * checklist) becomes the reason the material is skipped.
     */
    private function transition(Model&Workflowable $material, ContentStatus $to, User $user): ?string
    {
        try {
            $this->workflow->transition($material, $to, $user);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return is_string($message) ? $message : 'Материал не прошёл проверку.';
        }

        return null;
    }
}
