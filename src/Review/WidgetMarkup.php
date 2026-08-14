<?php

namespace Overlook\Foundation\Review;

/**
 * Builds the markup that loads the client review widget.
 *
 * One tag, and it used to be two. The widget this replaced read its settings
 * from a global the moment its script ran, so a second inline script had to
 * define that configuration first and the order between them was load-bearing.
 * The studio's own widget reads everything off its own tag — the key from a data
 * attribute, its endpoint and second bundle from its own src — so there is
 * nothing to define ahead of it and no ordering to get wrong.
 *
 * Neither value is hardcoded here. The address arrives from the provisioner
 * alongside the key, which is the difference between one widget address shared
 * by every site on the internet and one served by whichever installation of the
 * studio's application built this site. That also removes the constant this
 * class used to hold and the studio had to keep in step with its own code.
 */
class WidgetMarkup
{
    /**
     * @param  string  $key  Names the environment a report is filed against. Public by
     *                       necessity — it is printed here, in the source of a page anyone
     *                       can read — and guards nothing on its own.
     * @param  string  $source  Where the loader is served from. The widget derives its
     *                          ingest endpoint and its second bundle from this, so it is
     *                          the only address that has to be right.
     */
    public static function for(string $key, string $source): string
    {
        /** Attribute context: both are the studio's own values, and both are still escaped. */
        $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $source = htmlspecialchars($source, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <script async src="{$source}" data-overlook-key="{$key}"></script>
        HTML;
    }
}
