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

            // Показатели ведомства: значение остаётся строкой — редактор
            // пишет «86 500» или «1 318» с разделителем разрядов, и приводить
            // это к числу значило бы терять формат, который он выбрал.
            'blocks.*.items' => ['nullable', 'array', 'max:8'],
            'blocks.*.items.*.value' => ['required', 'string', 'max:20'],
            'blocks.*.items.*.label' => ['required', 'array'],
            'blocks.*.items.*.label.ru' => ['nullable', 'string', 'max:120'],
            'blocks.*.items.*.label.tg' => ['nullable', 'string', 'max:120'],
            'blocks.*.items.*.label.en' => ['nullable', 'string', 'max:120'],
        ];
    }
}
