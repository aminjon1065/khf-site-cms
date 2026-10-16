<?php

namespace App\Http\Requests\Submission;

use Illuminate\Foundation\Http\FormRequest;

/**
 * «Проверить статус обращения» on the site: the appeal number and the check
 * code given with it.
 */
class SubmissionStatusRequest extends FormRequest
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
            'tracking_number' => ['required', 'string', 'max:40'],
            'access_code' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return match (app()->getLocale()) {
            'tg' => [
                'tracking_number.required' => 'Рақами муроҷиатро ворид кунед.',
                'access_code.required' => 'Рамзи санҷишро ворид кунед.',
            ],
            'en' => [
                'tracking_number.required' => 'Enter the appeal number.',
                'access_code.required' => 'Enter the check code.',
            ],
            default => [
                'tracking_number.required' => 'Укажите номер обращения.',
                'access_code.required' => 'Укажите код проверки.',
            ],
        };
    }
}
