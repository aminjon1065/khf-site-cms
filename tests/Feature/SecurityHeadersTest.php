<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function securityHeadersEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

/**
 * Заголовки безопасности CMS. До появления `SecurityHeaders` панель и API не
 * отдавали ни одного из них — при том что именно здесь живут сессии редакторов
 * и неопубликованные материалы.
 */
it('sends the baseline security headers on the editorial panel', function () {
    $response = actingAs(securityHeadersEditor())->get('/dashboard');

    $response->assertSuccessful()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('sends them on the public API too', function () {
    get('/api/v1/health')
        ->assertSuccessful()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('sends them on error responses, not only successful ones', function () {
    // Ответ 404 рисуется тем же стеком; заголовок, который есть только на 200,
    // защищает ровно ту страницу, которую и так никто не подделывает.
    get('/api/v1/news/this-slug-does-not-exist')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');

    // И на запросе, который не совпал вообще ни с одним маршрутом: middleware
    // групп `web`/`api` для него не выполняется, поэтому заголовки повешены
    // глобально — иначе весь 404 хоста оставался бы голым.
    get('/definitely-not-a-route')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('offers HSTS only over TLS', function () {
    // По http заголовок всё равно игнорируется браузером, а вот запереть
    // разработческий http-хост на два года — ошибка, которую тяжело откатить.
    get('http://localhost/api/v1/health')
        ->assertSuccessful()
        ->assertHeaderMissing('Strict-Transport-Security');

    $secure = get('https://localhost/api/v1/health');
    $secure->assertSuccessful();

    expect($secure->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=63072000')
        ->toContain('includeSubDomains')
        // preload объявляет апекс-домен за всю зону; со стороны CMS это была бы
        // заявка от чужого имени.
        ->not->toContain('preload');
});
