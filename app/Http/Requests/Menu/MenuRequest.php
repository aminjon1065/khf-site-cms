<?php

namespace App\Http\Requests\Menu;

use App\Rules\SafePublicUrl;
use Illuminate\Contracts\Validation\Validator;
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
        $item = [
            'id' => ['nullable', 'integer'],
            'url' => ['nullable', 'string', 'max:255', new SafePublicUrl],
            'enabled' => ['boolean'],
            'label' => ['array'],
            'label.ru' => ['required', 'string', 'max:255'],
            'label.tg' => ['required', 'string', 'max:255'],
            'label.en' => ['nullable', 'string', 'max:255'],
        ];

        return [
            'items' => ['array'],
            'items.main' => ['array'],
            'items.footer' => ['array'],
            'items.*.*' => ['array'],
            'items.*.*.id' => $item['id'],
            'items.*.*.url' => $item['url'],
            'items.*.*.enabled' => $item['enabled'],
            'items.*.*.label' => $item['label'],
            'items.*.*.label.ru' => $item['label.ru'],
            'items.*.*.label.tg' => $item['label.tg'],
            'items.*.*.label.en' => $item['label.en'],
            'items.*.*.children' => ['array'],
            'items.*.*.children.*' => ['array'],
            'items.*.*.children.*.id' => $item['id'],
            'items.*.*.children.*.url' => $item['url'],
            'items.*.*.children.*.enabled' => $item['enabled'],
            'items.*.*.children.*.label' => $item['label'],
            'items.*.*.children.*.label.ru' => $item['label.ru'],
            'items.*.*.children.*.label.tg' => $item['label.tg'],
            'items.*.*.children.*.label.en' => $item['label.en'],
            'items.*.*.children.*.children' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.*.children.*.children.prohibited' => 'Вложенность меню ограничена одним уровнем.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['main', 'footer'] as $location) {
                    /** @var array<int, mixed> $rows */
                    $rows = $this->input("items.{$location}", []);
                    $seen = [];

                    foreach (array_values($rows) as $index => $row) {
                        if (! is_array($row)) {
                            continue;
                        }

                        $this->trackId($seen, $row['id'] ?? null, "items.{$location}.{$index}.id", $validator);

                        /** @var array<int, mixed> $children */
                        $children = is_array($row['children'] ?? null) ? $row['children'] : [];

                        foreach (array_values($children) as $childIndex => $child) {
                            if (! is_array($child)) {
                                continue;
                            }

                            $this->trackId(
                                $seen,
                                $child['id'] ?? null,
                                "items.{$location}.{$index}.children.{$childIndex}.id",
                                $validator,
                            );
                        }
                    }
                }
            },
        ];
    }

    /**
     * @param  array<int, true>  $seen
     */
    private function trackId(array &$seen, mixed $id, string $attribute, Validator $validator): void
    {
        if ($id === null || $id === '' || ! is_numeric($id)) {
            return;
        }

        $key = (int) $id;

        if (isset($seen[$key])) {
            $validator->errors()->add($attribute, 'Один пункт меню нельзя указать дважды.');

            return;
        }

        $seen[$key] = true;
    }
}
