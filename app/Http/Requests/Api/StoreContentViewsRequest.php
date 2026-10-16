<?php

namespace App\Http\Requests\Api;

use App\Services\ContentViewRecorder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of page views from the public site. The site's server sums them
 * for half a minute and sends them with the same key as Web Vitals: a reader
 * never talks to the CMS, and a spike of readers during an emergency is one
 * request per half minute here, not one per reader.
 */
class StoreContentViewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configured = (string) config('services.frontend.rum_secret');
        $provided = (string) $this->header('X-RUM-Key');

        return $configured !== '' && hash_equals($configured, $provided);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'views' => ['required', 'array', 'min:1', 'max:500'],
            'views.*.type' => ['required', Rule::in(ContentViewRecorder::TYPES)],
            'views.*.slug' => ['required', 'string', 'max:180'],
            'views.*.locale' => ['required', Rule::in(['ru', 'tj', 'en'])],
            'views.*.count' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
