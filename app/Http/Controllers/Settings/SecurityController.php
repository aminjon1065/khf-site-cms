<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();
        $user = $request->user();
        $hasTwoFactorSecret = $user->two_factor_secret !== null;

        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'twoFactor' => [
                'enabled' => $user->hasTwoFactorEnabled(),
                'pending' => $hasTwoFactorSecret && ! $user->hasTwoFactorEnabled(),
                'qr_code_svg' => $hasTwoFactorSecret ? $user->twoFactorQrCodeSvg() : null,
                'recovery_codes' => $user->hasTwoFactorEnabled() ? $user->recoveryCodes() : [],
            ],
        ];

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
