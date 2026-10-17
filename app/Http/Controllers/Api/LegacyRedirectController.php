<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Workflowable;
use App\Http\Controllers\Controller;
use App\Models\LegacyRedirect;
use App\Support\PublicSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where an address of the previous sites leads now: the site asks this when
 * someone opens an old link (`/node/6112` on khf.tj, or kchs.tj forwarded to
 * `/ru/node/…`) and answers with a permanent redirect.
 */
class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:2048'],
        ]);

        $source = LegacyRedirect::normalize($validated['source']);
        $redirect = $source !== null
            ? LegacyRedirect::query()->with('redirectable')->where('source', $source)->first()
            : null;
        $material = $redirect?->redirectable;

        // The old page's language first; if that version is not on the site,
        // any published one — a material in another language beats a 404.
        $path = $material instanceof Model && $material instanceof Workflowable
            ? PublicSite::localizedPathFor($material, $redirect->locale)
                ?? PublicSite::localizedPathFor($material)
            : null;

        if ($path === null) {
            return response()->json(['message' => 'No current page for this address.'], 404);
        }

        return response()->json(['data' => ['path' => $path]]);
    }
}
