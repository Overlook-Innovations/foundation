<?php

use Illuminate\Support\Facades\Route;

const BEACON_SOURCE = 'https://studio.example.com/analytics/v1/beacon.js';

function beaconPage(): void
{
    Route::middleware('web')->get('/page', fn () => response('<html><body><h1>Hi</h1></body></html>', 200, ['Content-Type' => 'text/html']));
}

/**
 * The inverse of the widget: on the live site and only there, so the studio's
 * own looks at a preview are never counted as a client's visitors.
 */
it('puts the beacon on a live site and nowhere else', function () {
    config(['foundation.analytics.key' => '7.signature', 'foundation.analytics.url' => BEACON_SOURCE]);
    beaconPage();

    expect($this->get('/page')->getContent())->not->toContain('data-overlook-analytics');

    app()->detectEnvironment(fn () => 'production');

    $body = $this->get('/page')->getContent();

    expect($body)->toContain('src="'.BEACON_SOURCE.'"')
        ->and($body)->toContain('data-overlook-analytics="7.signature"')
        ->and($body)->toContain('</script></body>');
});

it('injects nothing on a live site the studio has not switched on', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['foundation.analytics.key' => null, 'foundation.analytics.url' => null]);
    beaconPage();

    expect($this->get('/page')->getContent())->not->toContain('<script');
});
