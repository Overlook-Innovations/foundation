<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client review
    |--------------------------------------------------------------------------
    |
    | The review widget a client annotates the site with. The studio's
    | provisioner connects an environment, mints a key, and writes both the key
    | and the address the widget is served from into this application's
    | environment; this package turns them into the tag that loads it. Neither
    | half is any use without the other.
    |
    | Two variables rather than one, because the widget is served by whichever
    | installation of the studio's application provisioned this site — which a
    | deployed site has no other way to learn. There is no address to hardcode.
    |
    | Absent on a production site, which is never connected for review. The
    | middleware treats that as the normal case and does nothing, and refuses
    | outright when APP_ENV says production — see InjectReviewWidget.
    |
    | Read here rather than in the middleware on purpose. A site that has run
    | config:cache does not load its .env at all, so an env() call at request
    | time would return null and the widget would vanish in production while
    | continuing to work everywhere it was tested. Reading it in a config file
    | bakes the resolved value into the cache instead.
    |
    | Both names are a contract with the provisioner, which builds them from its
    | own OVERLOOK_REVIEW_INJECTION_ENV_VAR and _URL_ENV_VAR. Renaming one in
    | one place without the other silently stops the widget appearing.
    |
    */

    'review' => [
        'key' => env('OVERLOOK_REVIEW_KEY'),
        'url' => env('OVERLOOK_REVIEW_URL'),

        /** An escape hatch for a site that must be looked at without the widget. */
        'enabled' => env('FOUNDATION_REVIEW_ENABLED', true),
    ],

];
