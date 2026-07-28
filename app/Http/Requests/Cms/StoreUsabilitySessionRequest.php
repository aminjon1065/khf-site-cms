<?php

namespace App\Http\Requests\Cms;

use App\Support\UsabilityStudy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsabilitySessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.view') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $taskKeys = implode(',', UsabilityStudy::taskKeys());

        return [
            'participant_code' => [
                'required',
                'string',
                'max:24',
                'regex:/^[A-Z0-9-]{2,24}$/',
                Rule::unique('usability_sessions', 'participant_code'),
            ],
            'role' => ['required', Rule::in([
                'editor',
                'regional_editor',
                'alert_operator',
                'chief_editor',
                'translator',
            ])],
            'experience_level' => ['required', Rule::in(['none', 'basic', 'experienced'])],
            'tasks' => ['required', "array:{$taskKeys}", 'size:'.count(UsabilityStudy::TASKS)],
            'tasks.*' => ['required', 'array:completed,assisted,duration_seconds,irreversible_error'],
            'tasks.*.completed' => ['required', 'boolean'],
            'tasks.*.assisted' => ['required', 'boolean'],
            'tasks.*.duration_seconds' => ['required', 'integer', 'min:1', 'max:7200'],
            'tasks.*.irreversible_error' => ['required', 'boolean'],
            'sus_responses' => ['required', 'array', 'list', 'size:10'],
            'sus_responses.*' => ['required', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'started_at' => ['required', 'date', 'before_or_equal:completed_at'],
            'completed_at' => ['required', 'date', 'after_or_equal:started_at'],
        ];
    }
}
