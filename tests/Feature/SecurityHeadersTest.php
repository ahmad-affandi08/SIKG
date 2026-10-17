<?php

declare(strict_types=1);

it('NFR-04 halaman mengirim header keamanan', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'same-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('NFR-04 CSP ketat: hanya asal sendiri, tanpa eval atau inline script, tanpa framing', function () {
    $csp = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("script-src 'self'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("form-action 'self'")
        ->and($csp)->not->toContain('unsafe-eval')
        ->and($csp)->not->toMatch('/script-src[^;]*unsafe-inline/')
        ->and($csp)->not->toMatch('#https?://(?!localhost|127\.0\.0\.1|\[::1\])#');
});

it('NFR-04 HSTS dikirim hanya pada permintaan HTTPS', function () {
    $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('NFR-04 header juga ada pada respons galat dan pengalihan', function () {
    $this->get('/dashboard')->assertRedirect('/login')->assertHeader('X-Frame-Options', 'DENY');
    $this->get('/tidak-ada')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
});
