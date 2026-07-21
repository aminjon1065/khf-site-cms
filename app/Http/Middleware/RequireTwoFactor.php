<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasTwoFactorEnabled() || ! $this->isRequiredFor($user)) {
            return $next($request);
        }

        return redirect()->route('security.edit')->with(
            'warning',
            'Для вашей роли обязательна двухфакторная аутентификация. Настройте её, чтобы продолжить работу.',
        );
    }

    private function isRequiredFor(User $user): bool
    {
        $settings = Setting::query()
            ->where('group', 'security')
            ->whereIn('key', ['require_2fa', 'require_2fa_from'])
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value]);

        if (! (bool) $settings->get('require_2fa', false)) {
            return false;
        }

        $requiredFrom = $settings->get('require_2fa_from');
        if (is_string($requiredFrom) && Carbon::parse($requiredFrom)->isFuture()) {
            return false;
        }

        return $user->hasAnyRole([
            RoleName::Superadmin->value,
            RoleName::Admin->value,
            RoleName::ChiefEditor->value,
            RoleName::AlertOperator->value,
            RoleName::Approver->value,
        ]);
    }
}
