<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves short-lived previews of non-public workflow media to CMS users. */
class PrivateMediaController extends Controller
{
    public function __invoke(Request $request, Media $media, string $conversion = 'original'): StreamedResponse
    {
        // Return 404 instead of revealing whether a private media id exists.
        abort_unless((bool) $request->user()?->can('media.view'), 404);

        $privateDisk = (string) config('media-library.private_disk_name', 'content_private');
        $isConversion = $conversion !== 'original';

        if ($isConversion) {
            abort_unless($media->hasGeneratedConversion($conversion), 404);
        }

        $diskName = $isConversion ? $media->conversions_disk : $media->disk;
        abort_unless($diskName === $privateDisk, 404);

        $path = $media->getPathRelativeToRoot($isConversion ? $conversion : '');
        abort_unless(Storage::disk($diskName)->exists($path), 404);

        return Storage::disk($diskName)->response($path, $media->file_name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
