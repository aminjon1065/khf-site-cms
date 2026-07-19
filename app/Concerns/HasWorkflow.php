<?php

namespace App\Concerns;

use App\Enums\ContentStatus;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds workflow-transition history and status accessors to a content model.
 * The consuming model must cast its `status` column to {@see ContentStatus}.
 *
 * @property ContentStatus $status
 */
trait HasWorkflow
{
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
}
