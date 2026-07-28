<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWebVitalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $configured = (string) config('services.frontend.rum_secret');
        $provided = (string) $this->header('X-RUM-Key');

        return $configured !== '' && hash_equals($configured, $provided);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::in(['LCP', 'INP', 'CLS'])],
            'value' => ['required', 'numeric', 'min:0', 'max:120000'],
            'id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'path' => ['required', 'string', 'max:500', 'starts_with:/'],
            'locale' => ['required', Rule::in(['ru', 'tj', 'en'])],
            'device' => ['required', Rule::in(['mobile', 'tablet', 'desktop'])],
            'navigation_type' => [
                'required',
                Rule::in(['navigate', 'reload', 'prerender', 'back-forward', 'back-forward-cache', 'restore']),
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->string('name')->toString() === 'CLS' && $this->float('value') > 10) {
                    $validator->errors()->add('value', 'CLS must not exceed 10.');
                }
            },
        ];
    }
}
