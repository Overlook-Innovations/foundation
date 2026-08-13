<?php

use Illuminate\Support\Facades\Route;
use Overlook\Foundation\Review\WidgetMarkup;

function serving(string $body, array $headers = ['Content-Type' => 'text/html']): void
{
    Route::middleware('web')->get('/page', fn () => response($body, 200, $headers));
}

function connected(string $key = 'proj-key'): void
{
    config(['foundation.review.key' => $key]);
}

it('injects the widget before the closing body tag', function () {
    connected();
    serving('<html><body><h1>Hi</h1></body></html>');

    $body = $this->get('/page')->getContent();

    expect($body)->toContain('sidebarv2.js?apikey=proj-key')
        ->and($body)->toContain('</script></body>');
});

it('defines the configuration before the loader runs', function () {
    connected();
    serving('<html><body></body></html>');

    $body = $this->get('/page')->getContent();

    expect(strpos($body, 'BugHerdConfig'))->toBeLessThan(strpos($body, 'sidebarv2.js'));
});

/** A page that renders escaped markup carries an earlier closing tag. */
it('injects at the last closing body tag, not the first', function () {
    connected();
    serving('<html><body><code>&lt;/body&gt;</code></body></html>');

    $body = $this->get('/page')->getContent();

    expect(substr_count($body, 'sidebarv2.js'))->toBe(1)
        ->and($body)->toEndWith('</body></html>');
});

it('does nothing when the site is not connected to a review project', function () {
    config(['foundation.review.key' => null]);
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('bugherd.com');
});

it('does nothing when review is switched off', function () {
    connected();
    config(['foundation.review.enabled' => false]);
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('bugherd.com');
});

it('leaves a json response alone', function () {
    connected();
    Route::middleware('web')->get('/page', fn () => response()->json(['body' => '</body>']));

    expect($this->get('/page')->getContent())->not->toContain('bugherd.com');
});

it('leaves a document with no closing body tag alone', function () {
    connected();
    serving('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    expect($this->get('/page')->getContent())->not->toContain('bugherd.com');
});

/** A reviewer who hits an error is exactly who needs to be able to report it. */
it('injects into an error response', function () {
    connected();
    Route::middleware('web')->get('/page', fn () => response('<html><body>Oops</body></html>', 500, [
        'Content-Type' => 'text/html',
    ]));

    expect($this->get('/page')->getContent())->toContain('sidebarv2.js?apikey=proj-key');
});

it('corrects a declared content length so the document is not truncated', function () {
    connected();
    $page = '<html><body></body></html>';
    serving($page, ['Content-Type' => 'text/html', 'Content-Length' => (string) strlen($page)]);

    $response = $this->get('/page');

    expect((int) $response->headers->get('Content-Length'))->toBe(strlen($response->getContent()));
});

it('escapes the project key rather than trusting it into an attribute', function () {
    connected('a" onload="alert(1)');
    serving('<html><body></body></html>');

    expect($this->get('/page')->getContent())->not->toContain('onload="alert(1)"');
});

it('names the same fragment key the studio builds review links with', function () {
    expect(WidgetMarkup::REPORTER_FRAGMENT_KEY)->toBe('ovr');
});
