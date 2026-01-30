<?php

namespace WestWallTech\FaviconGenerator\Tags;

use Statamic\Facades\YAML;
use Statamic\Tags\Tags;

class Favicon extends Tags
{
    protected static $handle = 'favicon';

    public function index(): string
    {
        $settings = $this->getSettings();
        $themeColor = $settings['theme_color'] ?? config('favicon-generator.default_theme_color', '#4f46e5');
        $appName = $settings['app_name'] ?? config('app.name', '');
        $v = $this->getCacheBuster($settings);
        $html = [];

        // Favicon links
        if ($this->fileExists('favicon.ico')) {
            $html[] = '<link rel="icon" href="/favicon.ico'.$v.'" sizes="32x32">';
        }

        if ($this->fileExists('favicon.svg')) {
            $html[] = '<link rel="icon" href="/favicon.svg'.$v.'" type="image/svg+xml">';
        }

        if ($this->fileExists('favicon-96x96.png')) {
            $html[] = '<link rel="icon" type="image/png" href="/favicon-96x96.png'.$v.'" sizes="96x96">';
        }

        if ($this->fileExists('apple-touch-icon.png')) {
            $html[] = '<link rel="apple-touch-icon" href="/apple-touch-icon.png'.$v.'">';
        }

        if ($this->fileExists('site.webmanifest')) {
            $html[] = '<link rel="manifest" href="/site.webmanifest'.$v.'">';
        }

        // Theme color
        $html[] = '<meta name="theme-color" content="'.$themeColor.'">';

        // App name
        if ($appName) {
            $html[] = '<meta name="application-name" content="'.e($appName).'">';
            $html[] = '<meta name="apple-mobile-web-app-title" content="'.e($appName).'">';
        }

        // Apple meta tags
        $html[] = '<meta name="apple-mobile-web-app-capable" content="yes">';
        $html[] = '<meta name="apple-mobile-web-app-status-bar-style" content="default">';

        // Microsoft meta tags
        $html[] = '<meta name="msapplication-TileColor" content="'.$themeColor.'">';
        $html[] = '<meta name="msapplication-config" content="none">';

        return implode("\n", $html);
    }

    public function manifest(): string
    {
        if (! $this->fileExists('site.webmanifest')) {
            return '';
        }

        $v = $this->getCacheBuster();

        return '<link rel="manifest" href="/site.webmanifest'.$v.'">';
    }

    public function themeColor(): string
    {
        return '<meta name="theme-color" content="'.$this->getThemeColor().'">';
    }

    public function color(): string
    {
        return $this->getThemeColor();
    }

    public function appName(): string
    {
        $settings = $this->getSettings();

        return $settings['app_name'] ?? config('app.name', '');
    }

    public function appleMeta(): string
    {
        $settings = $this->getSettings();
        $appName = $settings['app_name'] ?? config('app.name', '');
        $html = [];

        if ($appName) {
            $html[] = '<meta name="apple-mobile-web-app-title" content="'.e($appName).'">';
        }

        $html[] = '<meta name="apple-mobile-web-app-capable" content="yes">';
        $html[] = '<meta name="apple-mobile-web-app-status-bar-style" content="default">';

        return implode("\n", $html);
    }

    public function microsoft(): string
    {
        $themeColor = $this->getThemeColor();

        return '<meta name="msapplication-TileColor" content="'.$themeColor.'">'."\n".
               '<meta name="msapplication-config" content="none">';
    }

    public function exists(): bool
    {
        return $this->fileExists($this->params->get('file', 'favicon.ico'));
    }

    protected function getThemeColor(): string
    {
        $settings = $this->getSettings();

        return $settings['theme_color'] ?? config('favicon-generator.default_theme_color', '#4f46e5');
    }

    protected function getCacheBuster(?array $settings = null): string
    {
        if (! $this->params->bool('cache_bust', false)) {
            return '';
        }

        $settings ??= $this->getSettings();
        $version = $settings['generated_at'] ?? time();

        // Convert ISO date to timestamp if needed
        if (is_string($version) && ! is_numeric($version)) {
            $version = strtotime($version) ?: time();
        }

        return '?v='.$version;
    }

    protected function fileExists(string $filename): bool
    {
        $outputPath = config('favicon-generator.output_path', public_path());

        return file_exists($outputPath.'/'.$filename);
    }

    protected function getSettings(): array
    {
        $path = base_path(config('favicon-generator.settings_path'));

        if (! file_exists($path)) {
            return [];
        }

        return YAML::file($path)->parse();
    }
}
