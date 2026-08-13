<?php

namespace Overlook\Foundation\Review;

/**
 * Builds the markup that loads the client review widget.
 *
 * Two tags, and the order between them is load-bearing. BugHerd reads its
 * settings from a global the moment its script runs, so the configuration has
 * to be defined first. The loader is async — it may execute at any point after
 * the parser reaches it — while an inline script always runs during parse, so
 * writing the configuration inline above the loader is what guarantees it is
 * there in time. Swap them and the prefill works only when the network is slow.
 */
class WidgetMarkup
{
    /**
     * Where the widget is loaded from.
     *
     * The studio's `feedback:verify-widget` looks for this address in the page
     * it fetches, which is the only check that the two halves of this
     * arrangement still agree. Changing it means changing
     * BugHerdVisualFeedbackProvider::widgetScriptUrl() to match.
     */
    public const string SCRIPT_URL = 'https://www.bugherd.com/sidebarv2.js';

    /**
     * The fragment key the reviewer's address arrives under.
     *
     * Named once on each side — here, and in BugHerdVisualFeedbackProvider,
     * which builds the review link. Renaming it breaks prefill silently, since
     * a fragment nobody reads looks exactly like a reviewer who was never sent
     * one.
     */
    public const string REPORTER_FRAGMENT_KEY = 'ovr';

    public static function for(string $projectKey): string
    {
        /** Attribute context: the key is the studio's, but it is still escaped. */
        $key = htmlspecialchars($projectKey, ENT_QUOTES, 'UTF-8');

        $fragmentKey = json_encode(self::REPORTER_FRAGMENT_KEY, JSON_THROW_ON_ERROR);
        $scriptUrl = self::SCRIPT_URL;

        return <<<HTML
        <script>(function () {
            var key = {$fragmentKey};
            var config = window.BugHerdConfig = window.BugHerdConfig || {};
            var hash = window.location.hash.slice(1);

            if (!hash) {
                return;
            }

            var email = null;
            var kept = [];

            hash.split('&').forEach(function (part) {
                var separator = part.indexOf('=');

                if (separator > 0 && part.slice(0, separator) === key) {
                    email = decodeURIComponent(part.slice(separator + 1));

                    return;
                }

                kept.push(part);
            });

            if (email === null) {
                return;
            }

            if (email) {
                config.reporter = config.reporter || {};
                config.reporter.email = email;
            }

            /**
             * Taken back out of the address bar once it has been read, so the
             * reviewer's address does not sit in a history entry or get handed
             * to anything the page links to next.
             */
            window.history.replaceState(
                null,
                '',
                window.location.pathname + window.location.search + (kept.length ? '#' + kept.join('&') : '')
            );
        })();</script>
        <script async src="{$scriptUrl}?apikey={$key}"></script>
        HTML;
    }
}
