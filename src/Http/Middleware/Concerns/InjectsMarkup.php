<?php

namespace Overlook\Foundation\Http\Middleware\Concerns;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Puts a tag just before an HTML page's closing body tag.
 *
 * Shared by the review widget and the analytics beacon, which differ only in
 * which environment they belong on and what they inject.
 */
trait InjectsMarkup
{
    protected function injectBeforeBodyClose(Response $response, string $markup): Response
    {
        /** Neither holds a body this can rewrite without buffering the whole thing. */
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            return $response;
        }

        /**
         * The last closing body tag, not the first. A page that shows escaped
         * markup — a docs page, a mail preview, anything rendering a snippet —
         * contains an earlier one that is not the end of the document, and
         * str_replace would put the markup inside it.
         */
        $position = strripos($content, '</body>');

        if ($position === false) {
            return $response;
        }

        $content = substr($content, 0, $position).$markup.substr($content, $position);

        /**
         * What the response was rendered from, which setContent overwrites with
         * the string it is given. Nothing serving the page cares, but every
         * assertion about it does: assertViewIs, assertViewHas and Inertia's
         * assertInertia all read the view back off the response, and against a
         * string they report the page is not a view at all. Left alone, this
         * middleware fails the test suite of every application it is installed
         * in — for a difference the browser never sees.
         */
        $original = $response->original;

        $response->setContent($content);

        $response->original = $original;

        /**
         * setContent leaves any Content-Length the response already declared,
         * which is now short by exactly the markup — and a browser handed a
         * truncated document renders a blank page.
         */
        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) strlen($content));
        }

        return $response;
    }
}
