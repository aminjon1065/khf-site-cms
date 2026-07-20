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
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите ваше имя.',
            'email.required' => 'Укажите электронную почту.',
            'email.email' => 'Некорректный адрес электронной почты.',
            'message.required' => 'Введите текст обращения.',
            'message.min' => 'Текст обращения слишком короткий.',
            'consent.accepted' => 'Подтвердите согласие на обработку персональных данных.',
        ];
    }
}
