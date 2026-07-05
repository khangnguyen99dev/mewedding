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
    | Base URL template assets (images/fonts/media/js) are served from. When set,
    | the renderer rewrites the template's absolute `/{public_dir}/...` references
    | to `{asset_url}/{public_dir}/...` so assets load from S3/CDN instead of the
    | app server. Leave empty to keep serving from the local public/ directory.
    | e.g. https://kanewedding.s3.ap-southeast-1.amazonaws.com  or a CloudFront URL.
    */
    'asset_url' => rtrim((string) env('TEMPLATE_ASSET_URL', 'https://kanewedding.s3.ap-southeast-1.amazonaws.com'), '/'),

    /*
    | The filesystem disk `templates:sync --s3` uploads template assets to.
    */
    's3_disk' => env('TEMPLATE_S3_DISK', 's3'),

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
