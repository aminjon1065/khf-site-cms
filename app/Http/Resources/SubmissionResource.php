<?php

namespace App\Http\Resources;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin list DTO for a citizen submission ("Обращения граждан").
 *
 * @mixin Submission
 */
class SubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'name' => $this->name,
            'email' => $this->email,
            'topic' => $this->topic,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
