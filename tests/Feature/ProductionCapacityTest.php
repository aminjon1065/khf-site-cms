<?php

use function Pest\Laravel\artisan;

/**
 * `ops:capacity` существует, чтобы две настройки production перестали быть
 * догадкой: сколько файлов держит OPcache и сколько памяти стоит запрос. План
 * требует считать воркеров по измеренной памяти, а не выставлять «максимум».
 */
it('measures the release and reports what the settings should be', function () {
    artisan('ops:capacity')
        ->expectsOutputToContain('PHP-файлов в релизе')
        ->expectsOutputToContain('Память: базовая загрузка / пик на запрос')
        ->assertSuccessful();
});

it('fails when OPcache is configured to hold fewer files than the release has', function () {
    // Это не косметика: при переполнении OPcache начинает вытеснять классы и
    // компилировать их заново на каждом запросе — процессор растёт, а по
    // конфигу всё «включено».
    config()->set('production.opcache.max_accelerated_files', 100);

    artisan('ops:capacity')
        ->expectsOutputToContain('ниже измеренного числа файлов')
        ->assertFailed();
});

it('fails when the configured worker count does not fit the given RAM', function () {
    config()->set('production.php_fpm_max_children', 10_000);

    artisan('ops:capacity', ['--ram' => 512])
        ->expectsOutputToContain('больше, чем помещается в 512 MB')
        ->assertFailed();
});

it('says nothing about workers when no RAM budget is given', function () {
    // Без бюджета памяти рекомендация была бы выдумкой, а не расчётом.
    config()->set('production.php_fpm_max_children', 10_000);

    artisan('ops:capacity')
        ->doesntExpectOutputToContain('pm.max_children')
        ->assertSuccessful();
});
