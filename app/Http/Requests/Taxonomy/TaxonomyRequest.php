<?php

namespace App\Http\Requests\Taxonomy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaxonomyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('taxonomy.edit');
    }

    /**
     * A term named only in Tajik/English (empty Russian) would be silently
     * dropped on save; surface it as an error instead of losing the input.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['categories', 'tags'] as $group) {
                    /** @var array<int, array<string, mixed>> $rows */
                    $rows = (array) $this->input($group, []);

                    foreach ($rows as $i => $row) {
                        $name = is_array($row['name'] ?? null) ? $row['name'] : [];
                        $ru = trim((string) ($name['ru'] ?? ''));
                        $other = trim((string) ($name['tg'] ?? '')).trim((string) ($name['en'] ?? ''));

                        if ($ru === '' && $other !== '') {
                            $validator->errors()->add(
                                "{$group}.{$i}.name.ru",
                                'Укажите название на русском языке.',
                            );
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'categories' => ['array'],
            'categories.*.id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('type', 'news'),
            ],
            'categories.*.name' => ['array'],
            'categories.*.name.ru' => ['nullable', 'string', 'max:255'],
            'categories.*.name.tg' => ['nullable', 'string', 'max:255'],
            'categories.*.name.en' => ['nullable', 'string', 'max:255'],
            'categories.*.slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'categories.*.sort' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'tags' => ['array'],
            'tags.*.id' => ['nullable', 'integer', 'exists:tags,id'],
            'tags.*.name' => ['array'],
            'tags.*.name.ru' => ['nullable', 'string', 'max:255'],
            'tags.*.name.tg' => ['nullable', 'string', 'max:255'],
            'tags.*.name.en' => ['nullable', 'string', 'max:255'],
            'tags.*.slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
        ];
    }
}
