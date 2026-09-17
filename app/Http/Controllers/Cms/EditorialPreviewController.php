<?php

namespace App\Http\Controllers\Cms;

use App\Contracts\Workflowable;
use App\Http\Controllers\Controller;
use App\Services\PublicationChecklist;
use App\Support\ContentTitle;
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

    /**
     * Shows one language version exactly as the public site would: without a
     * title in that language the material is not published there, so nothing
     * from another language is substituted.
     */
    public function __invoke(
        Request $request,
        string $contentType,
        int $contentId,
    ): Response {
        $model = $this->content->resolve($contentType, $contentId);
        abort_unless($model instanceof Workflowable, 404);
        $this->authorize('view', $model);

        $requested = $request->string('locale')->toString();
        $locale = in_array($requested, ['tg', 'ru', 'en'], true)
            ? $requested
            : ContentTitle::firstLocale($model) ?? 'ru';
        $title = ContentTitle::in($model, $locale);
        $snapshot = $this->content->snapshot($model);
        $bodyMap = $snapshot['body'] ?? $snapshot['summary'] ?? [];
        $image = null;

        if ($model instanceof HasMedia) {
            $image = $model->getMedia('cover')->first()?->getUrl()
                ?? $model->getMedia('image')->first()?->getUrl();
        }
        $response = Inertia::render('editorial/preview', [
            'preview' => [
                'locale' => $locale,
                'title' => $title,
                'body' => $title === '' ? '' : $this->localizedValue($bodyMap, $locale),
                'image' => $image,
                'available' => $title !== '',
                'title_word' => ContentTitle::field($model) === 'name' ? 'названия' : 'заголовка',
                'checklist' => $this->checklist->inspect($model),
            ],
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }

    private function localizedValue(mixed $values, string $locale): string
    {
        return is_array($values) ? trim((string) ($values[$locale] ?? '')) : '';
    }
}
