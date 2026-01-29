<?php

namespace WestWallTech\FaviconGenerator;

use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;
use WestWallTech\FaviconGenerator\Tags\Favicon;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        Favicon::class,
    ];

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $vite = [
        'input' => [
            'resources/js/addon.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function bootAddon(): void
    {
        $this->publishes([
            __DIR__.'/../config/favicon-generator.php' => config_path('favicon-generator.php'),
        ], 'favicon-generator-config');

        $this->extendNav();
    }

    protected function extendNav(): void
    {
        Nav::extend(function ($nav) {
            $nav->create('Favicon Generator')
                ->section('Tools')
                ->icon('ai-sparks')
                ->route('favicon-generator.index');
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/favicon-generator.php', 'favicon-generator');
    }
}
