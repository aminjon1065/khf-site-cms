<?php

namespace App\Http\Controllers\Cms;

use App\Contracts\Workflowable;
use App\Http\Controllers\Controller;
use App\Services\PublicationChecklist;
use App\Support\EditorialContent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\MediaLibrary\HasMedia;
use Symfony\Component\HttpFoundation\Response;

class EditorialPreviewController extends Controller
{
    public function __construct(
        private readonly EditorialContent $content,
        private readonly PublicationChecklist $checklist,
    ) {}

    public function __invoke(
        Request $request,
        string $contentType,
        int $contentId,
    ): Response {
        $model = $this->content->resolve($contentType, $contentId);
        abort_unless($model instanceof Workflowable, 404);
        $this->authorize('view', $model);

        $locale = in_array($request->string('locale')->toString(), ['tg', 'ru', 'en'], true)
            ? $request->string('locale')->toString()
            : 'ru';
        $snapshot = $this->content->snapshot($model);
        $titleMap = $snapshot['title'] ?? $snapshot['name'] ?? [];
        $bodyMap = $snapshot['body'] ?? $snapshot['summary'] ?? [];
        $title = $this->localizedValue($titleMap, $locale);
        $body = $this->localizedValue($bodyMap, $locale);
        $image = null;

        if ($model instanceof HasMedia) {
            $image = $model->getMedia('cover')->first()?->getUrl()
                ?? $model->getMedia('image')->first()?->getUrl();
        }
        $response = Inertia::render('editorial/preview', [
            'preview' => [
                'locale' => $locale,
                'title' => $title['value'],
                'body' => $body['value'],
                'image' => $image,
                'fallback' => $title['fallback'] || $body['fallback'],
                'checklist' => $this->checklist->inspect($model),
            ],
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    /**
     * @return array{value: string, fallback: bool}
     */
    private function localizedValue(mixed $values, string $locale): array
    {
        if (! is_array($values)) {
            return ['value' => '', 'fallback' => false];
        }

        $localized = trim((string) ($values[$locale] ?? ''));

        if ($localized !== '') {
            return ['value' => $localized, 'fallback' => false];
        }

        return [
            'value' => (string) ($values['ru'] ?? collect($values)->first() ?? ''),
            'fallback' => true,
        ];
    }
}
