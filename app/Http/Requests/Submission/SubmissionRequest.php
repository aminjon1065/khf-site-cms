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
            // A phone number or an email: one is enough to answer the
            // citizen. The error sits on the phone, the first of the two.
            'phone' => ['nullable', 'required_without:email', 'string', 'max:50', 'regex:/^\+?[0-9\s()\-]{7,25}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'topic' => ['nullable', 'string', 'max:255'],
            // The site knows regions by code; region_id stays for old clients.
            'region' => ['nullable', 'string', 'exists:regions,code'],
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
                'phone.required_without' => 'Телефон ё почтаи электрониро нишон диҳед.',
                'phone.regex' => 'Рақами телефонро бо рақамҳо нависед, масалан +992 93 123 45 67.',
                'email.email' => 'Суроғаи почтаи электронӣ нодуруст аст.',
                'message.required' => 'Матни муроҷиатро ворид кунед.',
                'message.min' => 'Матни муроҷиат хеле кӯтоҳ аст.',
                'region.exists' => 'Минтақаи интихобшуда ёфт нашуд.',
                'region_id.exists' => 'Минтақаи интихобшуда ёфт нашуд.',
                'consent.accepted' => 'Розигиро ба коркарди маълумоти шахсӣ тасдиқ кунед.',
            ],
            'en' => [
                'name.required' => 'Enter your name.',
                'phone.required_without' => 'Enter a phone number or an email address.',
                'phone.regex' => 'Enter the phone number in digits, e.g. +992 93 123 45 67.',
                'email.email' => 'Enter a valid email address.',
                'message.required' => 'Enter your message.',
                'message.min' => 'The message is too short.',
                'region.exists' => 'The selected region was not found.',
                'region_id.exists' => 'The selected region was not found.',
                'consent.accepted' => 'Confirm your consent to personal data processing.',
            ],
            default => [
                'name.required' => 'Укажите ваше имя.',
                'phone.required_without' => 'Укажите телефон или электронную почту.',
                'phone.regex' => 'Укажите номер телефона цифрами, например +992 93 123 45 67.',
                'email.email' => 'Некорректный адрес электронной почты.',
                'message.required' => 'Введите текст обращения.',
                'message.min' => 'Текст обращения слишком короткий.',
                'region.exists' => 'Выбранный регион не найден.',
                'region_id.exists' => 'Выбранный регион не найден.',
                'consent.accepted' => 'Подтвердите согласие на обработку персональных данных.',
            ],
        };
    }
}
