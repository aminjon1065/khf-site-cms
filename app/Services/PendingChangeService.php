<?php

namespace App\Services;

use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Jobs\RevalidateFrontend;
use App\Models\Activity;
use App\Models\PendingChange;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Support\ContentTitle;
use App\Support\FrontendRevalidation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * «Изменения на согласовании». A material that is on the site (or scheduled
 * to be) keeps its live version while someone without the publish permission
 * edits it: the edit is captured as a proposal and an approver applies or
 * rejects it (owner decision, 2026-09-23). The proposal holds the raw column
 * values the editor's form would have written plus replaced relation id
 * lists, so applying it is exactly the save that was held back.
 */
class PendingChangeService
{
    /**
     * Statuses whose content is on the public site or will get there without
     * another decision.
     *
     * @var list<ContentStatus>
     */
    private const LIVE_STATUSES = [
        ContentStatus::Published,
        ContentStatus::Updated,
        ContentStatus::Completed,
        ContentStatus::Scheduled,
    ];

    /**
     * Workflow and bookkeeping columns a proposal never carries.
     *
     * @var list<string>
     */
    private const PROTECTED_COLUMNS = [
        'id', 'status', 'published_at', 'scheduled_at', 'author_id', 'approver_id',
        'created_at', 'updated_at', 'deleted_at', 'views_count',
    ];

    /**
     * Human names of the columns shown in the approver's comparison.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'title' => 'Заголовок',
        'name' => 'Название',
        'internal_title' => 'Внутреннее название',
        'summary' => 'Лид',
        'body' => 'Текст',
        'key_point' => 'Главное за 10 секунд',
        'sections' => 'Шаги инструкции',
        'instructions' => 'Что делать',
        'contacts' => 'Контакты',
        'seo' => 'Как выглядит в поиске',
        'seo_title' => 'Заголовок для поиска',
        'seo_description' => 'Описание для поиска',
        'slug' => 'Адрес ссылки',
        'category_id' => 'Рубрика',
        'is_pinned' => 'Закрепить вверху ленты',
        'show_on_home' => 'Показывать на главной',
        'cover_alt' => 'Описание обложки',
        'cover_caption' => 'Подпись к обложке',
        'hazard_type' => 'Тип опасности',
        'severity' => 'Уровень опасности',
        'kind' => 'Тип объявления',
        'org' => 'Подразделение',
        'deadline' => 'Срок подачи заявок',
        'doc_type' => 'Тип документа',
        'number' => 'Номер',
        'doc_date' => 'Дата документа',
        'section' => 'Раздел',
        'tags' => 'Метки',
        'regions' => 'Регионы',
        'districts' => 'Районы',
        'relatedInstructions' => 'Связанные инструкции',
    ];

    public function __construct(private readonly WorkflowService $workflow) {}

    /**
     * Whether this user's save must become a proposal instead of changing
     * the live material.
     */
    public function required(Model&Workflowable $subject, ?User $user): bool
    {
        return in_array($subject->getWorkflowStatus(), self::LIVE_STATUSES, true)
            && ! ($user !== null && $user->can('publish', $subject));
    }

    /**
     * Capture what `$fill` would change on the material as a proposal; the
     * material itself is left untouched.
     *
     * @param  callable(): void  $fill  Applies the submitted form to $subject without saving it
     * @param  array<string, list<int>>  $relations  Relation method => submitted id list
     *
     * @throws ValidationException
     */
    public function propose(Model&Workflowable $subject, User $author, callable $fill, array $relations = []): PendingChange
    {
        $baseUpdatedAt = $subject->getAttribute('updated_at');

        $fill();
        $changes = array_filter(
            Arr::except($subject->getDirty(), self::PROTECTED_COLUMNS),
            fn (mixed $value, string $column): bool => ! $this->sameValue($subject->getRawOriginal($column), $value),
            ARRAY_FILTER_USE_BOTH,
        );
        $subject->refresh();

        $relations = $this->changedRelations($subject, $relations);

        if ($changes === [] && $relations === []) {
            throw ValidationException::withMessages([
                'changes' => 'Изменений нет — отправлять на согласование нечего.',
            ]);
        }

        $change = DB::transaction(function () use ($subject, $author, $changes, $relations, $baseUpdatedAt): PendingChange {
            PendingChange::query()
                ->whereMorphedTo('changeable', $subject)
                ->pending()
                ->update(['status' => PendingChange::SUPERSEDED]);

            return PendingChange::query()->create([
                'changeable_type' => $subject->getMorphClass(),
                'changeable_id' => $subject->getKey(),
                'user_id' => $author->id,
                'changes' => $changes,
                'relations' => $relations === [] ? null : $relations,
                'base_updated_at' => $baseUpdatedAt instanceof CarbonInterface ? $baseUpdatedAt : null,
                'status' => PendingChange::PENDING,
            ]);
        });

        $this->log($subject, $author, 'Изменения опубликованного материала отправлены на согласование');

        foreach ($this->workflow->approversFor($subject) as $approver) {
            if ($approver->is($author)) {
                continue;
            }

            $approver->notify(new WorkflowNotification(
                $subject,
                'Изменения ждут согласования',
                "«{$this->title($subject)}»: {$author->name} предлагает изменения опубликованного материала.",
                'warn',
                route('approvals', ['change' => $change->id], false),
            ));
        }

        return $change;
    }

    /**
     * Photos and files are uploaded with the form and can't wait in a
     * proposal: on a live material only someone who may publish replaces
     * them. Everything else can still be proposed without them.
     *
     * @param  list<string>  $inputs  Form keys that upload, pick or remove media
     *
     * @throws ValidationException
     */
    public function assertNoMediaChanges(Request $request, array $inputs): void
    {
        foreach ($inputs as $input) {
            $value = $request->input($input);
            $touched = $request->hasFile($input)
                || (is_array($value) && $value !== [])
                || (! is_array($value) && filled($value) && ! in_array($value, [false, 0, '0', 'false'], true));

            if ($touched) {
                throw ValidationException::withMessages([
                    'media' => 'Фото и файлы опубликованного материала меняет сотрудник с правом публикации. Остальные изменения отправьте на согласование без них.',
                ]);
            }
        }
    }

    public function apply(PendingChange $change, User $approver): void
    {
        $subject = $this->subjectOf($change);

        DB::transaction(function () use ($change, $approver, $subject): void {
            $subject->setRawAttributes(array_merge($subject->getAttributes(), $change->changes));
            $subject->save();

            foreach ($change->relations ?? [] as $relation => $ids) {
                $subject->{$relation}()->sync($ids);
            }

            $change->forceFill([
                'status' => PendingChange::APPLIED,
                'decided_by' => $approver->id,
                'decided_at' => now(),
            ])->save();
        });

        $this->log($subject, $approver, 'Изменения опубликованного материала согласованы и применены');
        $this->notifyAuthor($change, $subject, 'Ваши изменения опубликованы', "Изменения «{$this->title($subject)}» согласованы и уже на сайте.", 'ok');

        if ($subject->getWorkflowStatus()->isPublic()) {
            $payload = FrontendRevalidation::forContent($subject, 'updated');

            if ($payload !== null) {
                RevalidateFrontend::forPayload($payload);
            }
        }
    }

    public function reject(PendingChange $change, User $approver, string $comment): void
    {
        $subject = $this->subjectOf($change);

        $change->forceFill([
            'status' => PendingChange::REJECTED,
            'comment' => $comment,
            'decided_by' => $approver->id,
            'decided_at' => now(),
        ])->save();

        $this->log($subject, $approver, 'Изменения опубликованного материала отклонены', $comment);
        $this->notifyAuthor($change, $subject, 'Изменения отклонены', "Изменения «{$this->title($subject)}» не приняты. Комментарий: {$comment}", 'danger');
    }

    public function activeFor(Model $subject): ?PendingChange
    {
        return PendingChange::query()
            ->whereMorphedTo('changeable', $subject)
            ->pending()
            ->with('author')
            ->latest('id')
            ->first();
    }

    /**
     * Put the proposal into the (unsaved) material, so its author reopens the
     * editor on what they proposed rather than on the live version.
     */
    public function preview(Model $subject, PendingChange $change): void
    {
        $subject->setRawAttributes(array_merge($subject->getAttributes(), $change->changes));

        foreach ($change->relations ?? [] as $relation => $ids) {
            $subject->setRelation($relation, $subject->{$relation}()->getRelated()->newQuery()->whereKey($ids)->get());
        }
    }

    /**
     * What the proposal changes, field by field and language by language,
     * as plain text for the approver's comparison.
     *
     * @return list<array{field: string, label: string, before: string, after: string}>
     */
    public function diff(PendingChange $change): array
    {
        $subject = $this->subjectOf($change);
        $rows = [];

        foreach ($change->changes as $column => $after) {
            $before = $subject->getRawOriginal($column);
            $label = self::LABELS[$column] ?? $column;
            $beforeLocales = $this->localized($before);
            $afterLocales = $this->localized($after);

            if ($beforeLocales !== null || $afterLocales !== null) {
                foreach (['ru', 'tg', 'en'] as $locale) {
                    $from = $this->plain($beforeLocales[$locale] ?? '');
                    $to = $this->plain($afterLocales[$locale] ?? '');

                    if ($from !== $to) {
                        $rows[] = ['field' => $column, 'label' => $label.' ('.$this->localeLabel($locale).')', 'before' => $from, 'after' => $to];
                    }
                }

                continue;
            }

            $rows[] = ['field' => $column, 'label' => $label, 'before' => $this->plain($before), 'after' => $this->plain($after)];
        }

        foreach ($change->relations ?? [] as $relation => $ids) {
            $current = $subject->{$relation}()->pluck($subject->{$relation}()->getRelated()->getQualifiedKeyName())->all();
            $rows[] = [
                'field' => $relation,
                'label' => self::LABELS[$relation] ?? $relation,
                'before' => implode(', ', $this->relationNames($subject, $relation, $current)),
                'after' => implode(', ', $this->relationNames($subject, $relation, $ids)),
            ];
        }

        return $rows;
    }

    /**
     * Whether a column keeps its meaning: the same translations stored as
     * JSON with different key order or with empty languages dropped are not
     * a change, nor is `1` versus `true`.
     */
    private function sameValue(mixed $before, mixed $after): bool
    {
        $decodedBefore = is_string($before) ? json_decode($before, true) : null;
        $decodedAfter = is_string($after) ? json_decode($after, true) : null;

        if (is_array($decodedBefore) || is_array($decodedAfter)) {
            return $this->normalized($decodedBefore) === $this->normalized($decodedAfter);
        }

        return (string) (is_bool($before) ? (int) $before : $before) === (string) (is_bool($after) ? (int) $after : $after);
    }

    private function normalized(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value === '' ? null : $value;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $item = $this->normalized($item);

            if ($item !== null && $item !== []) {
                $clean[$key] = $item;
            }
        }

        if (! array_is_list($clean)) {
            ksort($clean);
        }

        return $clean;
    }

    /**
     * @param  array<string, list<int>>  $relations
     * @return array<string, list<int>>
     */
    private function changedRelations(Model $subject, array $relations): array
    {
        $changed = [];

        foreach ($relations as $relation => $ids) {
            $submitted = array_values(collect($ids)->map(fn (mixed $id): int => (int) $id)->unique()->sort()->all());
            $current = array_values($subject->{$relation}()
                ->pluck($subject->{$relation}()->getRelated()->getQualifiedKeyName())
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->all());

            if ($submitted !== $current) {
                $changed[$relation] = $submitted;
            }
        }

        return $changed;
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function relationNames(Model $subject, string $relation, array $ids): array
    {
        $related = $subject->{$relation}()->getRelated();

        return $related->newQuery()
            ->whereKey($ids)
            ->get()
            ->map(fn (Model $model): string => ContentTitle::of($model) ?: '#'.$model->getKey())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function localized(mixed $raw): ?array
    {
        if (! is_string($raw) || ! str_starts_with(ltrim($raw), '{')) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && array_intersect(array_keys($decoded), ['ru', 'tg', 'en']) !== [] ? $decoded : null;
    }

    private function plain(mixed $value): string
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", (string) $value)));

        return trim((string) preg_replace("/[ \t]+/", ' ', $text));
    }

    private function localeLabel(string $locale): string
    {
        return ['ru' => 'РУ', 'tg' => 'ТҶ', 'en' => 'EN'][$locale] ?? Str::upper($locale);
    }

    private function subjectOf(PendingChange $change): Model&Workflowable
    {
        $subject = $change->changeable;

        abort_unless($subject instanceof Model && $subject instanceof Workflowable, 404);

        return $subject;
    }

    private function title(Model $subject): string
    {
        return ContentTitle::of($subject) ?: (string) ($subject->getAttribute('internal_title') ?? '—');
    }

    private function notifyAuthor(PendingChange $change, Model $subject, string $title, string $message, string $tone): void
    {
        $author = $change->author;

        if ($author instanceof User) {
            $author->notify(new WorkflowNotification($subject, $title, $message, $tone));
        }
    }

    private function log(Model $subject, User $actor, string $description, ?string $comment = null): void
    {
        activity($subject->getTable())
            ->performedOn($subject)
            ->causedBy($actor)
            ->event('pending_change')
            ->withProperties(['comment' => $comment])
            ->tap(function (Activity $activity): void {
                $activity->is_critical = false;
            })
            ->log($description);
    }
}
