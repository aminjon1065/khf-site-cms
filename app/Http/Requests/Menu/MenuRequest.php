<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
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
            'items' => ['array'],
            'items.main' => ['array'],
            'items.footer' => ['array'],
            'items.*.*.id' => ['nullable', 'integer'],
            'items.*.*.url' => ['nullable', 'string', 'max:255'],
            'items.*.*.enabled' => ['boolean'],
            'items.*.*.label' => ['array'],
            'items.*.*.label.ru' => ['nullable', 'string', 'max:255'],
            'items.*.*.label.tg' => ['nullable', 'string', 'max:255'],
            'items.*.*.label.en' => ['nullable', 'string', 'max:255'],
        ];
    }
}
