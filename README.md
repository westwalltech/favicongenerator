# Favicon Generator for Statamic

[![Latest Version on Packagist](https://img.shields.io/packagist/v/westwalltech/favicon-generator.svg?style=flat-square)](https://packagist.org/packages/westwalltech/favicon-generator)
[![License](https://img.shields.io/packagist/l/westwalltech/favicon-generator.svg?style=flat-square)](LICENSE.md)

A modern favicon generator addon for Statamic 6 that creates all essential favicons from a single source image. Uses the modern approach with maskable icons for PWA support.

## Features

- **Single Source Upload** - Upload one high-res image (512×512+ PNG or SVG recommended)
- **Modern Favicon Set** - Generates all files modern browsers and PWAs need
- **Maskable Icons** - Android adaptive icon support with safe zone padding
- **SVG Favicon with Dark Mode** - Automatic dark mode support via CSS media queries
- **Custom Icon Colors** - Override icon colors for light and dark modes
- **Icon Padding** - Add padding around your icon
- **Background Colors** - Set light/dark background colors or use transparency
- **PWA Manifest** - Complete `site.webmanifest` with app name, colors, and orientation
- **Cache Busting** - Optional version query parameters for cache invalidation
- **Live Preview** - See all generated favicons in context
- **Antlers Tag** - Simple `{{ favicon }}` tag with cache busting support
- **No External APIs** - All processing happens locally with Intervention Image

## Generated Files

| File | Size | Purpose |
|------|------|---------|
| `favicon.ico` | 16×16, 32×32, 48×48 | Legacy browsers |
| `favicon.svg` | Scalable | Modern browsers, supports dark mode |
| `favicon-96x96.png` | 96×96 | High-DPI browser tabs |
| `apple-touch-icon.png` | 180×180 | iOS home screen |
| `icon-192.png` | 192×192 | Android/PWA |
| `icon-512.png` | 512×512 | PWA splash screens |
| `icon-192-maskable.png` | 192×192 | Android adaptive icons |
| `icon-512-maskable.png` | 512×512 | Android adaptive icons |
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

1. Navigate to **Tools → Favicon Generator** in the Statamic control panel
2. Select a source image (PNG or SVG, minimum 512×512 recommended)
3. Set your app name, theme color, and background color
4. Optionally customize icon colors, padding, and backgrounds
5. Click **Generate Favicons**
6. Preview the results and copy the Antlers tag

### Antlers Tag

Add the favicon tags to your layout's `<head>` section:

```antlers
{{ favicon }}
```

This outputs:

```html
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#4f46e5">
<meta name="application-name" content="Your App">
<meta name="apple-mobile-web-app-title" content="Your App">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="msapplication-TileColor" content="#4f46e5">
<meta name="msapplication-config" content="none">
```

### Cache Busting

To add version query parameters for cache invalidation:

```antlers
{{ favicon cache_bust="true" }}
```

This appends `?v=1705312200` (timestamp) to all asset URLs.

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

{{# Microsoft meta tags only #}}
{{ favicon:microsoft }}

{{# Apple meta tags only #}}
{{ favicon:apple_meta }}

{{# Get the app name #}}
{{ favicon:app_name }}

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
    // Where to save generated files (default: public folder)
    'output_path' => public_path(),

    // Path to settings YAML file
    'settings_path' => 'content/favicon-settings.yaml',

    // Asset container for source images
    'assets' => [
        'container' => env('FAVICON_GENERATOR_ASSETS_CONTAINER', 'assets'),
        'folder' => env('FAVICON_GENERATOR_ASSETS_FOLDER', '/'),
    ],

    // Default theme color
    'default_theme_color' => '#4f46e5',

    // Default background color for PWA splash
    'default_background_color' => '#ffffff',
];
```

## Icon Customization

### Dark Mode Styles

Choose how your SVG favicon adapts to dark mode:

- **Invert** - Inverts colors with hue rotation (best for colorful logos)
- **Lighten** - Increases brightness and contrast
- **Custom Color** - Apply a specific color filter
- **None** - No dark mode changes

### Custom Icon Colors

For monochrome SVG icons, you can set specific colors for light and dark modes:

1. Enable "Use Custom Icon Color"
2. Set the light mode color (e.g., `#000000`)
3. Set the dark mode color (e.g., `#ffffff`)

### Icon Padding

Add padding around your icon (0-40%). Useful for icons that extend to the edges.

### Background Colors

Choose between:
- **Transparent** - PNG icons with transparency
- **Solid colors** - Set separate light and dark mode backgrounds

## Requirements

- Statamic 6.0+
- PHP 8.2+
- Laravel 12.0+
- Intervention Image (installed automatically)

### Optional

- **Imagick PHP extension** - For higher quality PNG generation and SVG processing

## Image Requirements

- **Minimum size**: 512×512 pixels
- **Recommended formats**: PNG (with transparency) or SVG
- **Best practice**: Use a square image with your logo centered
- **For maskable icons**: Keep important content within the center 80% (safe zone)

## Maskable Icons

Maskable icons are used by Android for adaptive icons. They include a 10% safe zone padding to ensure your icon looks good when masked into different shapes (circles, squircles, etc.).

The generator automatically creates maskable versions with the correct padding.

## Dark Mode Support

The generated SVG favicon includes CSS media queries for dark mode:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <style>
    @media (prefers-color-scheme: dark) {
      svg { filter: invert(1) hue-rotate(180deg); }
    }
  </style>
  <!-- icon content -->
</svg>
```

## PWA Manifest

The generated `site.webmanifest` includes:

```json
{
  "name": "Your App Name",
  "short_name": "App",
  "start_url": "/",
  "scope": "/",
  "display": "standalone",
  "orientation": "any",
  "theme_color": "#4f46e5",
  "background_color": "#ffffff",
  "icons": [
    { "src": "/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icon-192-maskable.png", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },
    { "src": "/icon-512-maskable.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ]
}
```

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
