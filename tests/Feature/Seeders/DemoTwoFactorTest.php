<?php

use App\Models\User;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Laravel\seed;

// Demo and e2e stands: with DEMO_TWO_FACTOR_SECRET the demo accounts get a
// confirmed 2FA, each with its own secret (HMAC of the e-mail), which the
// browser tests reproduce to sign in with a code.

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

it('gives demo accounts a working 2FA derived from the demo secret', function () {
    config(['app.demo_two_factor_secret' => 'stand-secret']);
    seed(UserSeeder::class);

    $editor = User::query()->where('email', 'd.sattorov@khf.tj')->sole();
    $secret = Fortify::currentEncrypter()->decrypt($editor->two_factor_secret);
    $code = (new Google2FA)->getCurrentOtp($secret);

    expect($editor->hasTwoFactorEnabled())->toBeTrue()
        ->and(bin2hex((new Google2FA)->base32Decode($secret)))->toBe(hash_hmac('sha1', 'd.sattorov@khf.tj', 'stand-secret'))
        ->and(app(TwoFactorAuthenticationProvider::class)->verify($secret, $code))->toBeTrue();
});

it('leaves demo accounts without 2FA when no demo secret is set', function () {
    config(['app.demo_two_factor_secret' => null]);
    seed(UserSeeder::class);

    expect(User::query()->whereNotNull('two_factor_confirmed_at')->count())->toBe(0);
});
