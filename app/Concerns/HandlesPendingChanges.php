<?php

namespace App\Concerns;

use App\Contracts\Workflowable;
use App\Jobs\RevalidateFrontend;
use App\Models\User;
use App\Services\PendingChangeService;
use App\Support\FrontendRevalidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Editorial controllers: saving a live material without the publish
 * permission becomes a proposal («изменения на согласовании»), and a live
 * material saved by someone who may publish refreshes the public site.
 */
trait HandlesPendingChanges
{
    /**
     * Save the form as a proposal when this user can't change the live
     * material; null means "save normally".
     *
     * @param  callable(): void  $fill  Applies the form to $subject without saving it
     * @param  array<string, list<int>>  $relations  Relation method => submitted id list
     * @param  list<string>  $mediaInputs  Form keys that upload, pick or remove media
     *
     * @throws ValidationException
     */
    protected function proposeInsteadOfSaving(
        Model&Workflowable $subject,
        Request $request,
        callable $fill,
        array $relations,
        array $mediaInputs,
        string $editUrl,
    ): ?RedirectResponse {
        $service = app(PendingChangeService::class);
        $user = $request->user();

        if (! $user instanceof User || ! $service->required($subject, $user)) {
            return null;
        }

        $service->assertNoMediaChanges($request, $mediaInputs);
        $service->propose($subject, $user, $fill, $relations);

        return redirect($editUrl)->with('success', 'Изменения отправлены на согласование. На сайте пока прежняя версия.');
    }

    /**
     * Editor props about proposals. Call it before building the form payload:
     * for the proposal's author the material is filled with what they
     * proposed.
     *
     * @return array{pending_change: array{id: int, author: string|null, created_at: string|null, is_mine: bool}|null, changes_need_approval: bool}
     */
    protected function pendingChangeProps(Model&Workflowable $subject, ?User $user): array
    {
        $service = app(PendingChangeService::class);
        $change = $service->activeFor($subject);
        $isMine = $change !== null && $user !== null && $change->user_id === $user->id;

        if ($change !== null && $isMine) {
            $service->preview($subject, $change);
        }

        return [
            'pending_change' => $change === null ? null : [
                'id' => $change->id,
                'author' => $change->author?->name,
                'created_at' => $change->created_at?->isoFormat('D MMMM, HH:mm'),
                'is_mine' => $isMine,
            ],
            'changes_need_approval' => $service->required($subject, $user),
        ];
    }

    /**
     * A live material was saved directly: refresh its pages on the site now
     * instead of waiting for the ISR timer.
     */
    protected function refreshSiteIfLive(Model&Workflowable $subject): void
    {
        if (! $subject->getWorkflowStatus()->isPublic()) {
            return;
        }

        $payload = FrontendRevalidation::forContent($subject, 'updated');

        if ($payload !== null) {
            RevalidateFrontend::forPayload($payload);
        }
    }
}
