<?php

namespace Overlook\Foundation\Analytics;

/**
 * The tag that loads the studio's analytics beacon. The beacon reads its key
 * off its own tag and reports to the address it was loaded from.
 */
class BeaconMarkup
{
    public static function for(string $key, string $source): string
    {
        $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $source = htmlspecialchars($source, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <script async src="{$source}" data-overlook-analytics="{$key}"></script>
        HTML;
    }
}
