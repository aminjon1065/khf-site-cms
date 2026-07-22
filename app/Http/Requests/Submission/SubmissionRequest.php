<?php

namespace App\Http\Requests\Submission;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public citizen-submission form. The `website` field is a honeypot handled in
 * the controller (not validated here) so bots are not tipped off.
 */
class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'topic' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return match (app()->getLocale()) {
            'tg' => [
                'name.required' => 'Ному насабро ворид кунед.',
                'email.required' => 'Почтаи электрониро ворид кунед.',
                'email.email' => 'Суроғаи почтаи электронӣ нодуруст аст.',
                'message.required' => 'Матни муроҷиатро ворид кунед.',
                'message.min' => 'Матни муроҷиат хеле кӯтоҳ аст.',
                'region_id.exists' => 'Минтақаи интихобшуда ёфт нашуд.',
                'consent.accepted' => 'Розигиро ба коркарди маълумоти шахсӣ тасдиқ кунед.',
            ],
            'en' => [
                'name.required' => 'Enter your name.',
                'email.required' => 'Enter your email address.',
                'email.email' => 'Enter a valid email address.',
                'message.required' => 'Enter your message.',
                'message.min' => 'The message is too short.',
                'region_id.exists' => 'The selected region was not found.',
                'consent.accepted' => 'Confirm your consent to personal data processing.',
            ],
            default => [
                'name.required' => 'Укажите ваше имя.',
                'email.required' => 'Укажите электронную почту.',
                'email.email' => 'Некорректный адрес электронной почты.',
                'message.required' => 'Введите текст обращения.',
                'message.min' => 'Текст обращения слишком короткий.',
                'region_id.exists' => 'Выбранный регион не найден.',
                'consent.accepted' => 'Подтвердите согласие на обработку персональных данных.',
            ],
        };
    }
}
