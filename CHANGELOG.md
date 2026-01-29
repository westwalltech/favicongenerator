# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
