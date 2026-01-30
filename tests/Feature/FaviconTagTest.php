<?php

use Statamic\Facades\YAML;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use WestWallTech\FaviconGenerator\Tags\Favicon;

beforeEach(function () {
    // Create test favicon files
    file_put_contents($this->testOutputPath.'/favicon.ico', 'test');
    file_put_contents($this->testOutputPath.'/favicon.svg', 'test');
    file_put_contents($this->testOutputPath.'/favicon-96x96.png', 'test');
    file_put_contents($this->testOutputPath.'/apple-touch-icon.png', 'test');
    file_put_contents($this->testOutputPath.'/site.webmanifest', 'test');

    // Create settings file in the test output path
    // Since base_path() + settings_path is used, we create in storage/app for tests
    $settingsDir = base_path('storage/app');
    if (! is_dir($settingsDir)) {
        mkdir($settingsDir, 0755, true);
    }
    $this->settingsPath = $settingsDir.'/favicon-test-settings.yaml';
    config(['favicon-generator.settings_path' => 'storage/app/favicon-test-settings.yaml']);
    file_put_contents($this->settingsPath, YAML::dump([
        'theme_color' => '#4f46e5',
        'app_name' => 'Test App',
        'generated_at' => '2024-01-15T10:30:00+00:00',
    ]));
});

afterEach(function () {
    // Clean up settings file
    if (isset($this->settingsPath) && file_exists($this->settingsPath)) {
        unlink($this->settingsPath);
    }
});

function createTag(array $params = []): Favicon
{
    $tag = app(Favicon::class);
    $context = new Context();
    $tag->setContext($context);
    $tag->setParameters(Parameters::make($params, $context));

    return $tag;
}

it('outputs all favicon tags', function () {
    $output = createTag()->index();

    expect($output)
        ->toContain('<link rel="icon" href="/favicon.ico"')
        ->toContain('<link rel="icon" href="/favicon.svg"')
        ->toContain('<link rel="icon" type="image/png" href="/favicon-96x96.png"')
        ->toContain('<link rel="apple-touch-icon" href="/apple-touch-icon.png"')
        ->toContain('<link rel="manifest" href="/site.webmanifest"')
        ->toContain('<meta name="theme-color" content="#4f46e5">')
        ->toContain('<meta name="application-name" content="Test App">')
        ->toContain('<meta name="apple-mobile-web-app-capable" content="yes">')
        ->toContain('<meta name="msapplication-TileColor" content="#4f46e5">');
});

it('outputs microsoft meta tags', function () {
    $output = createTag()->microsoft();

    expect($output)
        ->toContain('<meta name="msapplication-TileColor" content="#4f46e5">')
        ->toContain('<meta name="msapplication-config" content="none">');
});

it('outputs theme color meta tag', function () {
    $output = createTag()->themeColor();

    expect($output)->toBe('<meta name="theme-color" content="#4f46e5">');
});

it('outputs just the color value', function () {
    $output = createTag()->color();

    expect($output)->toBe('#4f46e5');
});

it('outputs manifest link', function () {
    $output = createTag()->manifest();

    expect($output)->toContain('<link rel="manifest" href="/site.webmanifest">');
});

it('adds cache busting when enabled', function () {
    $output = createTag(['cache_bust' => 'true'])->index();

    expect($output)
        ->toContain('favicon.ico?v=')
        ->toContain('favicon.svg?v=')
        ->toContain('site.webmanifest?v=');
});

it('does not add cache busting when disabled', function () {
    $output = createTag(['cache_bust' => 'false'])->index();

    expect($output)->not->toContain('?v=');
});

it('outputs apple meta tags', function () {
    $output = createTag()->appleMeta();

    expect($output)
        ->toContain('<meta name="apple-mobile-web-app-title" content="Test App">')
        ->toContain('<meta name="apple-mobile-web-app-capable" content="yes">')
        ->toContain('<meta name="apple-mobile-web-app-status-bar-style" content="default">');
});

it('returns app name', function () {
    $output = createTag()->appName();

    expect($output)->toBe('Test App');
});

it('skips missing files', function () {
    // Remove some files
    unlink($this->testOutputPath.'/favicon.svg');
    unlink($this->testOutputPath.'/favicon-96x96.png');

    $output = createTag()->index();

    expect($output)
        ->toContain('<link rel="icon" href="/favicon.ico"')
        ->not->toContain('favicon.svg')
        ->not->toContain('favicon-96x96.png');
});

it('uses default theme color when settings missing', function () {
    // Remove settings file
    if (file_exists($this->settingsPath)) {
        unlink($this->settingsPath);
    }

    $output = createTag()->color();

    expect($output)->toBe('#4f46e5'); // default from config
});

it('checks if file exists', function () {
    $tag = createTag(['file' => 'favicon.ico']);
    expect($tag->exists())->toBeTrue();

    $tag = createTag(['file' => 'nonexistent.png']);
    expect($tag->exists())->toBeFalse();
});

it('adds cache busting to manifest link', function () {
    $output = createTag(['cache_bust' => 'true'])->manifest();

    expect($output)->toContain('site.webmanifest?v=');
});
