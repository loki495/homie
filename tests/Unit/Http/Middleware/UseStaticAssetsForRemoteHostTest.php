<?php

declare(strict_types=1);

use App\Http\Middleware\UseStaticAssetsForRemoteHost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

it('uses built assets for a configured remote hostname', function () {
    config()->set('app.static_asset_hosts', ['dashboard.example.com']);
    config()->set('session.secure', false);
    Vite::useHotFile(public_path('hot'));

    $request = Request::create('https://dashboard.example.com/');
    $response = (new UseStaticAssetsForRemoteHost)->handle(
        $request,
        fn (): Response => new Response(json_encode([
            'hot_file' => Vite::hotFile(),
            'secure_cookie' => config('session.secure'),
        ], JSON_THROW_ON_ERROR)),
    );

    expect(json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'hot_file' => public_path('hot-static'),
            'secure_cookie' => true,
        ])
        ->and(Vite::hotFile())->toBe(public_path('hot'))
        ->and(config('session.secure'))->toBeFalse();
});

it('keeps the Vite hot file for the local development hostname', function () {
    config()->set('app.static_asset_hosts', ['dashboard.example.com']);
    config()->set('session.secure', false);
    Vite::useHotFile(public_path('hot'));

    $request = Request::create('http://homie.dev.local.test/');
    $response = (new UseStaticAssetsForRemoteHost)->handle(
        $request,
        fn (): Response => new Response(json_encode([
            'hot_file' => Vite::hotFile(),
            'secure_cookie' => config('session.secure'),
        ], JSON_THROW_ON_ERROR)),
    );

    expect(json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'hot_file' => public_path('hot'),
            'secure_cookie' => false,
        ])
        ->and(Vite::hotFile())->toBe(public_path('hot'))
        ->and(config('session.secure'))->toBeFalse();
});
