<?php

namespace App\Http\Requests\Editorial;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEditorialAutosaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content_type' => ['required', Rule::in(['news', 'pages', 'projects', 'instructions', 'announcements', 'documents'])],
            'content_id' => ['nullable', 'integer', 'min:1'],
            'draft_key' => ['required', 'uuid'],
            'data' => ['required', 'array'],
            'base_version' => ['nullable', 'date'],
            'revision_cursor' => ['nullable', 'integer', 'min:1'],
            'opened_at' => ['required', 'date'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}
