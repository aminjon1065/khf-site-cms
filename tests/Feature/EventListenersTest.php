<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

// Listeners in app/Listeners are discovered automatically; registering one by
// hand as well made it run twice — image metadata was read twice for every
// conversion, and a Telegram alert would go out twice.

it('runs each application listener once per event', function () {
    $duplicates = [];

    foreach (Event::getRawListeners() as $event => $listeners) {
        $classes = [];

        foreach ($listeners as $listener) {
            $class = match (true) {
                is_string($listener) => Str::before($listener, '@'),
                is_array($listener) && is_string($listener[0] ?? null) => $listener[0],
                default => null,
            };

            if (is_string($class) && str_starts_with($class, 'App\\Listeners\\')) {
                $classes[] = $class;
            }
        }

        foreach (array_count_values($classes) as $class => $count) {
            if ($count > 1) {
                $duplicates[] = "{$event} → {$class} ×{$count}";
            }
        }
    }

    expect($duplicates)->toBe([]);
});
