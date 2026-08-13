<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client review
    |--------------------------------------------------------------------------
    |
    | The visual feedback widget a client annotates the site with. The studio's
    | provisioner creates a review project, gets a key back, and writes it into
    | this application's environment; this package turns that key into the tag
    | that loads the widget. Neither half is any use without the other.
    |
    | Absent on a production site, which is never connected to a review project.
    | The middleware treats that as the normal case and does nothing.
    |
    | Read here rather than in the middleware on purpose. A site that has run
    | config:cache does not load its .env at all, so an env() call at request
    | time would return null and the widget would vanish in production while
    | continuing to work everywhere it was tested. Reading it in a config file
    | bakes the resolved value into the cache instead.
    |
    | The variable name is a contract with the provisioner, which builds it from
    | BUGHERD_INJECTION_ENV_VAR on its side. Renaming it in one place without
    | the other silently stops the widget appearing.
    |
    */

    'review' => [
        'key' => env('BUGHERD_PROJECT_KEY'),

        /** An escape hatch for a site that must be looked at without the widget. */
        'enabled' => env('FOUNDATION_REVIEW_ENABLED', true),
    ],

];
