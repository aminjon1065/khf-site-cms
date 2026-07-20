<?php

namespace App\Http\Requests\Region;

use App\Enums\RegionType;
use App\Models\Region;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced in the controller via policies.
        return true;
    }

    /**
     * A district named only in Tajik/English (empty Russian) would be silently
     * dropped on save; surface it as an error instead of losing the input.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var array<int, array<string, mixed>> $districts */
            $districts = (array) $this->input('districts', []);

            foreach ($districts as $i => $row) {
                $name = is_array($row['name'] ?? null) ? $row['name'] : [];
                $ru = trim((string) ($name['ru'] ?? ''));
                $other = trim((string) ($name['tg'] ?? '')).trim((string) ($name['en'] ?? ''));

                if ($ru === '' && $other !== '') {
                    $validator->errors()->add(
                        "districts.{$i}.name.ru",
                        'Укажите название района на русском языке.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Region|null $region */
        $region = $this->route('region');

        return [
            'name' => ['array'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.tg' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],

            'head' => ['nullable', 'array'],
            'head.ru' => ['nullable', 'string', 'max:255'],
            'head.tg' => ['nullable', 'string', 'max:255'],
            'head.en' => ['nullable', 'string', 'max:255'],

            'address' => ['nullable', 'array'],
            'address.ru' => ['nullable', 'string', 'max:255'],
            'address.tg' => ['nullable', 'string', 'max:255'],
            'address.en' => ['nullable', 'string', 'max:255'],

            'code' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('regions', 'code')->ignore($region?->id),
            ],
            'type' => ['required', Rule::enum(RegionType::class)],
            'regional_center' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'duty_phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'districts_count' => ['required', 'integer', 'min:0', 'max:65535'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],

            // districts: [{ id?, name: { ru|tg|en } }]
            'districts' => ['nullable', 'array'],
            'districts.*.id' => ['nullable', 'integer'],
            'districts.*.name' => ['array'],
            'districts.*.name.ru' => ['nullable', 'string', 'max:255'],
            'districts.*.name.tg' => ['nullable', 'string', 'max:255'],
            'districts.*.name.en' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.ru.required' => 'Укажите название региона на русском языке.',
            'code.required' => 'Укажите код региона.',
            'code.unique' => 'Такой код региона уже используется.',
            'code.alpha_dash' => 'Код может содержать только латинские буквы, цифры и дефисы.',
            'type.required' => 'Выберите тип региона.',
            'email.email' => 'Укажите корректный адрес электронной почты.',
            'districts_count.required' => 'Укажите количество районов.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name.ru' => 'название (рус.)',
            'code' => 'код региона',
            'type' => 'тип региона',
            'districts_count' => 'количество районов',
        ];
    }
}
