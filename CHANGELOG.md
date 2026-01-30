# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] - 2026-01-30

### Fixed

- Live preview now reliably shows when selecting an asset (added dedicated API endpoint)

## [1.1.1] - 2026-01-30

### Fixed

- Live preview not showing when selecting an asset for the first time

## [1.1.0] - 2026-01-30

### Added

- **Maskable icons** for Android adaptive icons (icon-192-maskable.png, icon-512-maskable.png)
- **Enhanced PWA manifest** with `start_url`, `scope`, `orientation`, and maskable icon entries
- **Cache busting** support via `{{ favicon cache_bust="true" }}` parameter
- **Microsoft meta tags** in favicon output (`msapplication-TileColor`, `msapplication-config`)
- **`{{ favicon:microsoft }}`** Antlers tag for Microsoft-specific meta tags
- **favicon-96x96.png** for high-DPI browser tabs
- **Custom icon colors** - Set specific colors for light and dark modes on SVG icons
- **Icon padding** - Add padding around icons (0-40%)
- **Background colors** - Set light/dark mode backgrounds or use transparency
- **Manifest auto-update** - Settings changes update the manifest without regenerating images
- Comprehensive test suite for Favicon tag (13 new tests)

### Changed

- Manifest now includes 4 icons (regular + maskable) instead of 2
- Improved code organization with extracted constants and helper methods
- Reduced frontend bundle size through code deduplication

### Fixed

- Missing `png_dark_background` in generation options
- Duplicate validation rules consolidated into constants
- SVG XSS protection via sanitization

## [1.0.0] - 2026-01-29

### Added

- Initial release
- Control panel utility for generating favicons from a single source image
- Multi-size ICO file generation (16x16, 32x32, 48x48)
- SVG favicon with dark mode support via CSS media queries
  - Invert mode for automatic color inversion
  - Lighten mode for brightness adjustment
  - Custom color mode for monochrome icons
- Apple Touch Icon generation (180x180)
- PWA icon generation (192x192, 512x512)
- Web manifest (site.webmanifest) generation with app name and theme colors
- Live preview panels for browser tabs, iOS, and Android
- Antlers `{{ favicon }}` tag for template integration
- Configuration file for customizing output path and defaults
- Pest PHP 4 test suite
