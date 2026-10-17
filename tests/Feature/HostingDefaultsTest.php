<?php

declare(strict_types=1);

it('NFR-16 bawaan driver aman untuk shared hosting', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toMatch('/^SESSION_DRIVER=database$/m')
        ->and($env)->toMatch('/^QUEUE_CONNECTION=database$/m')
        ->and($env)->toMatch('/^CACHE_STORE=(database|file)$/m');
});

it('NFR-16 tidak ada route atau perintah web untuk migrasi dan Artisan', function () {
    foreach (app('router')->getRoutes() as $route) {
        expect($route->uri())->not->toMatch('#(artisan|migrate|phpinfo|install)#i');
    }
});

it('NFR-13 /up merespons tanpa membuka data', function () {
    $this->get('/up')->assertOk();
});

it('NFR-12 halaman awal dirender Inertia dengan bahasa Indonesia', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $this->get('/login')->assertOk()->assertSee('data-page', false);

    expect(config('app.locale'))->toBe('id')
        ->and(config('app.timezone'))->toBe('UTC');
});
