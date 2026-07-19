<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:ru,tg'],
        ]);

        $request->session()->put('locale', $validated['locale']);

        if ($user = $request->user()) {
            $user->forceFill(['interface_locale' => $validated['locale']])->saveQuietly();
        }

        return back();
    }
}
