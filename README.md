# Favicon Generator for Statamic

[![Latest Version on Packagist](https://img.shields.io/packagist/v/westwalltech/favicon-generator.svg?style=flat-square)](https://packagist.org/packages/westwalltech/favicon-generator)
[![License](https://img.shields.io/packagist/l/westwalltech/favicon-generator.svg?style=flat-square)](LICENSE.md)

A modern favicon generator addon for Statamic 6 that creates all essential favicons from a single source image. Uses the modern 5-file approach instead of generating 20+ outdated icon files.

## Features

- **Single Source Upload** - Upload one high-res image (512×512+ PNG or SVG recommended)
- **Modern Favicon Set** - Generates only the 5 files modern browsers need
- **SVG Favicon with Dark Mode** - Automatic dark mode support via CSS media queries
- **PWA Manifest** - Valid `site.webmanifest` with customizable app name, colors
- **Live Preview** - See all generated favicons in context (browser tabs, iOS, Android)
- **Antlers Tag** - Simple `{{ favicon }}` tag for your templates
- **No External APIs** - All processing happens locally with Intervention Image

## Generated Files

| File | Size | Purpose |
|------|------|---------|
| `favicon.ico` | 32×32 | Legacy browsers |
| `favicon.svg` | Scalable | Modern browsers, supports dark mode |
| `apple-touch-icon.png` | 180×180 | iOS home screen |
| `icon-192.png` | 192×192 | Android/PWA |
| `icon-512.png` | 512×512 | PWA splash screens |
| `site.webmanifest` | — | PWA manifest file |

## Installation

```bash
composer require westwalltech/favicon-generator
```

Or for local development, add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "addons/westwalltech/favicon-generator"
        }
    ],
    "require": {
        "westwalltech/favicon-generator": "*"
    }
}
```

Then run:

```bash
composer update westwalltech/favicon-generator
```

## Usage

### Control Panel

1. Navigate to **Utilities → Favicon Generator** in the Statamic control panel
2. Select a source image (PNG or SVG, minimum 512×512 recommended)
3. Set your app name, theme color, and background color
4. Click **Generate Favicons**
5. Preview the results in various contexts

### Antlers Tag

Add the favicon tags to your layout's `<head>` section:

```antlers
{{ favicon }}
```

This outputs:

```html
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#4f46e5">
```

### Additional Tags

```antlers
{{# Just the manifest link #}}
{{ favicon:manifest }}

{{# Just the theme-color meta tag #}}
{{ favicon:theme_color }}

{{# Get the theme color value for inline styles #}}
<style>
  :root { --theme-color: {{ favicon:color }}; }
</style>

{{# Check if a file exists #}}
{{ if {favicon:exists file="favicon.svg"} }}
  SVG favicon is available!
{{ /if }}
```

## Configuration

Publish the configuration file:

```bash
php please vendor:publish --tag=favicon-generator-config
```

### Options

```php
return [
    // Where to save generated files
    'output_path' => public_path(),

    // Default Statamic asset container
    'asset_container' => 'assets',

    // Default theme color
    'default_theme_color' => '#4f46e5',

    // Default background color for PWA splash
    'default_background_color' => '#ffffff',

    // Generate .ico file for legacy browsers
    'generate_ico' => true,

    // Generate SVG with dark mode support
    'generate_svg' => true,

    // Invert colors for dark mode (vs brightness adjustment)
    'dark_mode_invert' => true,
];
```

## Requirements

- Statamic 6.0+
- PHP 8.5+
- Laravel 12.0+
- Intervention Image (installed automatically)

## Image Requirements

- **Minimum size**: 512×512 pixels
- **Recommended formats**: PNG (with transparency) or SVG
- **Best practice**: Use a square image with your logo centered

## Dark Mode Support

The generated SVG favicon includes a CSS media query that adjusts colors when the user's system is in dark mode:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <style>
    @media (prefers-color-scheme: dark) {
      .favicon-image { filter: invert(1) hue-rotate(180deg); }
    }
  </style>
  <image class="favicon-image" href="data:image/png;base64,..." />
</svg>
```

You can customize or disable this behavior in the configuration.

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
