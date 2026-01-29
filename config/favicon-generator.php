<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assets Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the asset container and folder where source images can be
    | selected from. This is required for the asset picker to work.
    |
    */

    'assets' => [
        'container' => env('FAVICON_GENERATOR_ASSETS_CONTAINER', 'assets'),
        'folder' => env('FAVICON_GENERATOR_ASSETS_FOLDER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | The public directory where generated favicon files will be saved.
    | By default, files are saved to the public root directory.
    |
    */

    'output_path' => public_path(),

    /*
    |--------------------------------------------------------------------------
    | Settings Storage
    |--------------------------------------------------------------------------
    |
    | Path where favicon generator settings will be stored (relative to base_path).
    |
    */

    'settings_path' => 'content/favicon-generator.yaml',

    /*
    |--------------------------------------------------------------------------
    | Default Colors
    |--------------------------------------------------------------------------
    |
    | Default theme and background colors for generated favicons.
    |
    */

    'default_theme_color' => '#4f46e5',
    'default_background_color' => '#ffffff',

];
