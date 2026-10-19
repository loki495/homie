<?php

declare(strict_types=1);

use App\Http\Middleware\FrameAncestors;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

function frameAncestorsHeader(): string
{
    $response = (new FrameAncestors)->handle(
        Request::create('/'),
        fn (): Response => new Response('ok'),
    );

    return (string) $response->headers->get('Content-Security-Policy');
}

it('defaults to the app\'s own origin', function () {
    expect(frameAncestorsHeader())->toBe("frame-ancestors 'self'");
});

it('allows a configured embedding origin', function () {
    config()->set('homie.frame_ancestors', "'self' https://ha.example.com");

    expect(frameAncestorsHeader())->toBe("frame-ancestors 'self' https://ha.example.com");
});

it('falls back to self for an empty value', function (mixed $value) {
    config()->set('homie.frame_ancestors', $value);

    expect(frameAncestorsHeader())->toBe("frame-ancestors 'self'");
})->with(['', '   ', null]);

it('falls back to self for a malformed value instead of sending it', function (string $value) {
    config()->set('homie.frame_ancestors', $value);

    expect(frameAncestorsHeader())->toBe("frame-ancestors 'self'");
})->with([
    'directive injection' => "https://ha.example.com; script-src 'unsafe-inline'",
    'comma' => 'https://a.example.com,https://b.example.com',
    'bare word' => 'example.com',
]);

it('sends the header on a real response', function () {
    $this->get('/login')->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
});
