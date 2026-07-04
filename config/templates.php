<?php

return [

    /*
    | Filesystem location of template folders. Each subfolder is one template
    | and must contain a `template.json` manifest.
    */
    'path' => resource_path('templates'),

    /*
    | Where each template's `assets/` directory is published to (under public/).
    | Final URL: /{public_dir}/{key}/...
    */
    'public_dir' => 'templates',

    /*
    | Blade view namespace the templates are registered under, so a template's
    | entry view is resolvable as `templates::{key}.index`.
    */
    'view_namespace' => 'templates',

    /*
    | Registry cache. Set ttl to null to cache forever (bust via templates:sync).
    */
    'cache_key' => 'templates.registry',
    'cache_ttl' => env('TEMPLATE_CACHE_TTL', 3600),

];
