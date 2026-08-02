<?php

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

/**
 * `/health` и `/ready` дёргает кто угодно: балансировщик, CDN, мониторинг, а
 * значит и любой прохожий. План разрешает им проверять БД, хранилище и
 * очередь, но запрещает раскрывать секреты. Здесь это проверяется на реальном
 * ответе, а не на намерении.
 */
$secrets = [
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'DB_PASSWORD' => 'super-secret-database-password',
    'REDIS_PASSWORD' => 'super-secret-redis-password',
    'FRONTEND_REVALIDATION_SECRET' => 'super-secret-revalidation-token',
];

it('never returns configuration secrets', function () use ($secrets) {
    config()->set([
        'app.key' => $secrets['APP_KEY'],
        'database.connections.mysql.password' => $secrets['DB_PASSWORD'],
        'database.redis.default.password' => $secrets['REDIS_PASSWORD'],
        'services.frontend_revalidation.secret' => $secrets['FRONTEND_REVALIDATION_SECRET'],
    ]);

    foreach (['/api/v1/health', '/api/v1/ready'] as $uri) {
        $body = getJson($uri)->getContent();

        // Второй аргумент `toContain` — это ещё одна искомая строка, а не
        // подпись к проверке, поэтому сообщение сюда не передаём.
        foreach ($secrets as $value) {
            expect($body)->not->toContain($value);
        }
    }
});

it('does not disclose the software stack or its versions', function () {
    // Точная версия PHP, Laravel или строка подключения — бесплатная разведка
    // для сканера уязвимостей. `version` в ответе есть, но это версия
    // контракта API (`v1`), а не сборки.
    $health = getJson('/api/v1/health');

    $health->assertOk()->assertJsonPath('version', 'v1');

    expect($health->getContent())
        ->not->toContain(PHP_VERSION)
        ->not->toContain(app()->version())
        ->not->toContain(base_path());

    expect(getJson('/api/v1/ready')->getContent())
        ->not->toContain(PHP_VERSION)
        ->not->toContain(app()->version())
        ->not->toContain(base_path());
});

it('reports a database failure without echoing the driver message', function () {
    // Сломанное подключение — тот случай, когда в ответ чаще всего утекает
    // полный DSN вместе с паролем: драйвер кладёт его прямо в текст ошибки.
    DB::shouldReceive('select')
        ->andThrow(new RuntimeException(
            "SQLSTATE[HY000] [1045] Access denied for user 'khf'@'10.0.0.5' (using password: super-secret-database-password)",
        ));
    DB::shouldReceive('table')->andThrow(new RuntimeException('SQLSTATE[HY000] super-secret-database-password'));

    $response = getJson('/api/v1/ready');

    $response->assertStatus(503)->assertJsonPath('checks.database', false);

    expect($response->getContent())
        ->not->toContain('super-secret-database-password')
        ->not->toContain('SQLSTATE');
});

it('forbids caching health and readiness anywhere', function () {
    // Правило CDN из DEPLOYMENT: `/health` и `/ready` не кэшируются. Иначе
    // балансировщик увидит бодрый 200 из кэша от узла, который уже лежит, —
    // ровно в тот момент, когда проверка важнее всего.
    foreach (['/api/v1/health', '/api/v1/ready'] as $uri) {
        expect(getJson($uri)->headers->get('Cache-Control'))->toContain('no-store');
    }
});
