<?php

namespace App\Http\Requests\HomeBlock;

use Illuminate\Foundation\Http\FormRequest;

class HomeBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'blocks' => ['array'],
            'blocks.*.id' => ['required', 'integer', 'exists:home_blocks,id'],
            'blocks.*.enabled' => ['boolean'],
            'blocks.*.title' => ['array'],
            'blocks.*.title.ru' => ['nullable', 'string', 'max:255'],
            'blocks.*.title.tg' => ['nullable', 'string', 'max:255'],
            'blocks.*.title.en' => ['nullable', 'string', 'max:255'],
            'blocks.*.limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
