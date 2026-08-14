<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

const WIDGET_SOURCE = 'https://studio.example.com/review/v1/widget.js';

function serving(string $body, array $headers = ['Content-Type' => 'text/html']): void
{
    Route::middleware('web')->get('/page', fn () => response($body, 200, $headers));
}

function connected(string $key = 'review-key', string $source = WIDGET_SOURCE): void
{
    config([
        'foundation.review.key' => $key,
        'foundation.review.url' => $source,
    ]);
}

it('injects the widget before the closing body tag', function () {
    connected();
    serving('<html><body><h1>Hi</h1></body></html>');

    $body = $this->get('/page')->getContent();

    expect($body)->toContain('src="'.WIDGET_SOURCE.'"')
        ->and($body)->toContain('data-overlook-key="review-key"')
        ->and($body)->toContain('</script></body>');
});

/**
 * One tag, where the widget this replaced needed two in a fixed order. The
 * loader reads its key off its own tag and derives everything else from its
 * own src, so there is nothing to define ahead of it.
 */
it('injects one tag and no configuration ahead of it', function () {
    connected();
    serving('<html><body></body></html>');

    expect(substr_count($this->get('/page')->getContent(), '<script'))->toBe(1);
});

/** A page that renders escaped markup carries an earlier closing tag. */
it('injects at the last closing body tag, not the first', function () {
    connected();
    serving('<html><body><code>&lt;/body&gt;</code></body></html>');

    $body = $this->get('/page')->getContent();

    expect(substr_count($body, WIDGET_SOURCE))->toBe(1)
        ->and($body)->toEndWith('</body></html>');
});

it('does nothing when the site is not connected for review', function () {
    config(['foundation.review.key' => null, 'foundation.review.url' => null]);
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('widget.js');
});

/**
 * Both halves or neither. A tag carrying an address and no key files reports
 * nowhere; one carrying a key and no address does not load at all. Either is a
 * launcher that looks like it works.
 */
it('does nothing when only one of the two variables is set', function () {
    config(['foundation.review.key' => 'review-key', 'foundation.review.url' => null]);
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('data-overlook-key');

    config(['foundation.review.key' => null, 'foundation.review.url' => WIDGET_SOURCE]);

    expect($this->get('/page')->getContent())->not->toContain('widget.js');
});

/**
 * The last line of defence, and the one that does not depend on the studio's
 * provisioner having behaved. A live site is the client's own property; a key
 * typed into a dashboard by hand must not put a launcher in front of their
 * visitors.
 */
it('refuses to inject on a production site even when it is fully configured', function () {
    app()->detectEnvironment(fn () => 'production');
    connected();
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('widget.js');
});

it('does nothing when review is switched off', function () {
    connected();
    config(['foundation.review.enabled' => false]);
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('widget.js');
});

it('leaves a json response alone', function () {
    connected();
    Route::middleware('web')->get('/page', fn () => response()->json(['body' => '</body>']));

    expect($this->get('/page')->getContent())->not->toContain('widget.js');
});

it('leaves a document with no closing body tag alone', function () {
    connected();
    serving('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    expect($this->get('/page')->getContent())->not->toContain('widget.js');
});

/** A reviewer who hits an error is exactly who needs to be able to report it. */
it('injects into an error response', function () {
    connected();
    Route::middleware('web')->get('/page', fn () => response('<html><body>Oops</body></html>', 500, [
        'Content-Type' => 'text/html',
    ]));

    expect($this->get('/page')->getContent())->toContain('data-overlook-key="review-key"');
});

it('corrects a declared content length so the document is not truncated', function () {
    connected();
    $page = '<html><body></body></html>';
    serving($page, ['Content-Type' => 'text/html', 'Content-Length' => (string) strlen($page)]);

    $response = $this->get('/page');

    expect((int) $response->headers->get('Content-Length'))->toBe(strlen($response->getContent()));
});

it('escapes the key rather than trusting it into an attribute', function () {
    connected('a" onload="alert(1)');
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('onload="alert(1)"');
});

it('escapes the address rather than trusting it into an attribute', function () {
    connected(source: 'https://studio.example.com/w.js" onload="alert(1)');
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('onload="alert(1)"');
});

/**
 * The regression that broke four starter kits at once. setContent replaces
 * whatever the response was rendered from, and for a view-backed response —
 * which every Inertia page is — that is the view assertViewIs, assertViewHas
 * and assertInertia read back. Injecting without putting it back fails the
 * test suite of every application this package is installed in.
 */
it('leaves the rendered view on the response for assertions to read', function () {
    connected();
    View::addLocation(__DIR__.'/fixtures/views');
    Route::middleware('web')->get('/page', fn () => view('widget-page'));

    $response = $this->get('/page');

    expect($response->getContent())->toContain('data-overlook-key="review-key"');

    $response->assertViewIs('widget-page');
});
