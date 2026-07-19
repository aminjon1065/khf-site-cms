<?php

namespace App\Contracts;

use App\Enums\ContentStatus;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A content model that moves through the editorial workflow.
 */
interface Workflowable
{
    /**
     * @return MorphMany<WorkflowTransition, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function transitions(): MorphMany;

    public function getWorkflowStatus(): ContentStatus;

    public function setWorkflowStatus(ContentStatus $status): void;
}
