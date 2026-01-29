<?php

namespace WestWallTech\FaviconGenerator\Tags;

use Statamic\Facades\YAML;
use Statamic\Tags\Tags;

class Favicon extends Tags
{
    protected static $handle = 'favicon';

    public function index(): string
    {
        $themeColor = $this->getThemeColor();
        $html = [];

        if ($this->fileExists('favicon.ico')) {
            $html[] = '<link rel="icon" href="/favicon.ico" sizes="32x32">';
        }

        if ($this->fileExists('favicon.svg')) {
            $html[] = '<link rel="icon" href="/favicon.svg" type="image/svg+xml">';
        }

        if ($this->fileExists('apple-touch-icon.png')) {
            $html[] = '<link rel="apple-touch-icon" href="/apple-touch-icon.png">';
        }

        if ($this->fileExists('site.webmanifest')) {
            $html[] = '<link rel="manifest" href="/site.webmanifest">';
        }

        $html[] = '<meta name="theme-color" content="'.$themeColor.'">';

        return implode("\n", $html);
    }

    public function manifest(): string
    {
        if (! $this->fileExists('site.webmanifest')) {
            return '';
        }

        return '<link rel="manifest" href="/site.webmanifest">';
    }

    public function themeColor(): string
    {
        return '<meta name="theme-color" content="'.$this->getThemeColor().'">';
    }

    public function color(): string
    {
        return $this->getThemeColor();
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
