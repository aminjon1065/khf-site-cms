<?php

namespace App\Concerns;

use App\Enums\ContentStatus;
use App\Jobs\RevalidateFrontend;
use App\Models\WorkflowTransition;
use App\Support\FrontendRevalidation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds workflow-transition history and status accessors to a content model.
 * The consuming model must cast its `status` column to {@see ContentStatus}
 * and use SoftDeletes.
 *
 * @property ContentStatus $status
 */
trait HasWorkflow
{
    /**
     * Moving a live material to the trash takes it off the site and restoring
     * it brings it back: its pages are refreshed now, as after a publication
     * (WorkflowService), instead of when the ISR timer runs out.
     */
    public static function bootHasWorkflow(): void
    {
        static::deleted(function (self $material): void {
            $material->refreshSiteAfterTrash('deleted');
        });

        static::restored(function (self $material): void {
            $material->refreshSiteAfterTrash('restored');
        });
    }

    /**
     * @return MorphMany<WorkflowTransition, $this>
     */
    public function transitions(): MorphMany
    {
        return $this->morphMany(WorkflowTransition::class, 'subject')->latest('id');
    }

    public function getWorkflowStatus(): ContentStatus
    {
        return $this->status;
    }

    public function setWorkflowStatus(ContentStatus $status): void
    {
        $this->status = $status;
    }

    private function refreshSiteAfterTrash(string $event): void
    {
        if (! $this->getWorkflowStatus()->isPublic()) {
            return;
        }

        $payload = FrontendRevalidation::forContent($this, $event);

        if ($payload !== null) {
            RevalidateFrontend::forPayload($payload);
        }
    }
}
