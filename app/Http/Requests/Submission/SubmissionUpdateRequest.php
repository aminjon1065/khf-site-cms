<?php

namespace App\Http\Requests\Submission;

use App\Enums\SubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmissionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller via policies.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SubmissionStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
