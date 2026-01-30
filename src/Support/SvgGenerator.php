<?php

namespace WestWallTech\FaviconGenerator\Support;

class SvgGenerator
{
    /**
     * Generate an SVG favicon from a raster image by embedding it as base64.
     */
    public function generate(string $imagePath, string $darkModeStyle = 'invert', string $darkModeColor = '#ffffff'): string
    {
        $mimeType = $this->getMimeType($imagePath);
        $base64 = base64_encode(file_get_contents($imagePath));
        $darkModeCSS = $this->getDarkModeStyle($darkModeStyle, $darkModeColor);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32">
  <style>
    @media (prefers-color-scheme: dark) {
      {$darkModeCSS}
    }
  </style>
  <image href="data:{$mimeType};base64,{$base64}" width="32" height="32" preserveAspectRatio="xMidYMid meet"/>
</svg>
SVG;
    }

    /**
     * Inject dark mode CSS into an existing SVG file.
     */
    public function injectDarkModeIntoSvg(string $svgContent, string $darkModeStyle = 'invert', string $darkModeColor = '#ffffff', array $options = []): string
    {
        $darkModeCSS = $this->getDarkModeStyle($darkModeStyle, $darkModeColor);
        $pngTransparent = $options['png_transparent'] ?? true;

        if (! $pngTransparent) {
            $css = $this->buildBackgroundCss($options, "@media (prefers-color-scheme: dark) { {$darkModeCSS} }");
        } else {
            $css = "@media (prefers-color-scheme: dark) { {$darkModeCSS} }";
        }

        $svgContent = $this->injectStyleElement($svgContent, $css);

        if (! $pngTransparent) {
            $svgContent = $this->addBackgroundRect($svgContent);
        }

        return $this->applyPaddingIfNeeded($svgContent, $options);
    }

    /**
     * Apply custom icon colors to an SVG for both light and dark modes.
     */
    public function applyCustomIconColors(string $svgContent, string $lightColor, string $darkColor, array $options = []): string
    {
        // Replace currentColor with a CSS variable
        $svgContent = str_replace('currentColor', 'var(--icon-color)', $svgContent);

        // Replace black fills with the CSS variable
        $svgContent = preg_replace('/fill=["\']#0{3,6}["\']/i', 'fill="var(--icon-color)"', $svgContent);

        // Build CSS with custom properties and media query
        $css = ":root { --icon-color: {$lightColor}; }";
        $css .= " @media (prefers-color-scheme: dark) { :root { --icon-color: {$darkColor}; } }";

        $pngTransparent = $options['png_transparent'] ?? true;
        if (! $pngTransparent) {
            $css .= ' '.$this->buildBackgroundCss($options);
        }

        $svgContent = $this->injectStyleElement($svgContent, $css);

        if (! $pngTransparent) {
            $svgContent = $this->addBackgroundRect($svgContent);
        }

        return $this->applyPaddingIfNeeded($svgContent, $options);
    }

    /**
     * Add only background to an SVG without dark mode icon changes.
     */
    public function addBackgroundToSvg(string $svgContent, array $options): string
    {
        $css = $this->buildBackgroundCss($options);
        $svgContent = $this->injectStyleElement($svgContent, $css);
        $svgContent = $this->addBackgroundRect($svgContent);

        return $this->applyPaddingIfNeeded($svgContent, $options);
    }

    /**
     * Add dark mode support to an SVG (legacy method for backwards compatibility).
     */
    public function addDarkModeSupport(string $svgContent): string
    {
        return $this->injectDarkModeIntoSvg($svgContent, 'lighten');
    }

    /**
     * Build CSS for background colors with dark mode support.
     */
    protected function buildBackgroundCss(array $options, string $additionalDarkCss = ''): string
    {
        $lightBg = $options['png_background'] ?? '#ffffff';
        $darkBg = $options['png_dark_background'] ?? '#1a1a1a';

        $css = ".favicon-bg { fill: {$lightBg}; }";
        $darkContent = ".favicon-bg { fill: {$darkBg}; }";

        if ($additionalDarkCss) {
            $darkContent .= ' '.$additionalDarkCss;
        }

        return $css." @media (prefers-color-scheme: dark) { {$darkContent} }";
    }

    /**
     * Inject or replace CSS in an SVG's style element.
     */
    protected function injectStyleElement(string $svgContent, string $css): string
    {
        if (preg_match('/<style[^>]*>.*?<\/style>/s', $svgContent)) {
            return preg_replace(
                '/(<style[^>]*>).*?(<\/style>)/s',
                '$1'.$css.'$2',
                $svgContent
            );
        }

        return preg_replace(
            '/(<svg[^>]*>)/i',
            '$1<style>'.$css.'</style>',
            $svgContent,
            1
        );
    }

    /**
     * Apply padding if specified in options.
     */
    protected function applyPaddingIfNeeded(string $svgContent, array $options): string
    {
        $padding = $options['icon_padding'] ?? 0;

        if ($padding > 0) {
            return $this->applyPaddingToSvg($svgContent, $padding);
        }

        return $svgContent;
    }

    /**
     * Apply padding to SVG content by wrapping icon elements in a scaled/translated group.
     */
    protected function applyPaddingToSvg(string $svgContent, int $paddingPercent): string
    {
        ['width' => $width, 'height' => $height] = $this->extractDimensions($svgContent);

        // Calculate scale and translation for padding
        $paddingFraction = $paddingPercent / 100;
        $scale = 1 - ($paddingFraction * 2);
        $translateX = $width * $paddingFraction;
        $translateY = $height * $paddingFraction;

        // Add closing group tag before </svg>
        $svgContent = preg_replace('/(<\/svg>)/i', '</g>$1', $svgContent, 1);

        // Insert opening group tag after background rect, style, or svg tag
        $groupTag = '<g transform="translate('.$translateX.','.$translateY.') scale('.$scale.')">';

        if (preg_match('/<rect[^>]*class=["\']favicon-bg["\'][^>]*\/?>/i', $svgContent)) {
            return preg_replace(
                '/(<rect[^>]*class=["\']favicon-bg["\'][^>]*\/?>)/i',
                '$1'.$groupTag,
                $svgContent,
                1
            );
        }

        if (preg_match('/<\/style>/i', $svgContent)) {
            return preg_replace('/(<\/style>)/i', '$1'.$groupTag, $svgContent, 1);
        }

        return preg_replace('/(<svg[^>]*>)/i', '$1'.$groupTag, $svgContent, 1);
    }

    /**
     * Add a background rectangle to an SVG as the first child element.
     */
    protected function addBackgroundRect(string $svgContent): string
    {
        ['width' => $width, 'height' => $height] = $this->extractDimensions($svgContent);

        $bgRect = '<rect class="favicon-bg" x="0" y="0" width="'.$width.'" height="'.$height.'"/>';

        if (preg_match('/<\/style>/i', $svgContent)) {
            return preg_replace('/(<\/style>)/i', '$1'.$bgRect, $svgContent, 1);
        }

        return preg_replace('/(<svg[^>]*>)/i', '$1'.$bgRect, $svgContent, 1);
    }

    /**
     * Extract width and height dimensions from SVG content.
     *
     * @return array{width: float, height: float}
     */
    protected function extractDimensions(string $svgContent): array
    {
        if (preg_match('/viewBox=["\']([^"\']+)["\']/', $svgContent, $matches)) {
            $viewBox = array_map('floatval', preg_split('/[\s,]+/', trim($matches[1])));
            if (count($viewBox) >= 4) {
                return ['width' => $viewBox[2], 'height' => $viewBox[3]];
            }
        }

        if (preg_match('/width=["\'](\d+)["\']/', $svgContent, $wMatch) &&
            preg_match('/height=["\'](\d+)["\']/', $svgContent, $hMatch)) {
            return ['width' => (float) $wMatch[1], 'height' => (float) $hMatch[1]];
        }

        return ['width' => 100.0, 'height' => 100.0];
    }

    protected function getDarkModeStyle(string $style, string $color): string
    {
        return match ($style) {
            'invert' => 'svg { filter: invert(1) hue-rotate(180deg); }',
            'lighten' => 'svg { filter: brightness(1.5) contrast(1.1); }',
            'custom' => $this->getCustomColorStyle($color),
            'none' => '/* No dark mode changes */',
            default => 'svg { filter: invert(1) hue-rotate(180deg); }',
        };
    }

    protected function getCustomColorStyle(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $hsl = $this->rgbToHsl($r, $g, $b);

        $filters = [
            'invert(1)',
            'sepia(1)',
            "hue-rotate({$hsl['h']}deg)",
        ];

        if ($hsl['s'] > 0) {
            $satScale = ($hsl['s'] / 30) * 100;
            $filters[] = "saturate({$satScale}%)";
        } else {
            $filters[] = 'saturate(0)';
        }

        $brightnessScale = $hsl['l'] / 50;
        if (abs($brightnessScale - 1.0) > 0.001) {
            $filters[] = "brightness({$brightnessScale})";
        }

        return 'svg { filter: '.implode(' ', $filters).'; }';
    }

    /**
     * @return array{h: int, s: int, l: int}
     */
    protected function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return ['h' => 0, 's' => 0, 'l' => (int) round($l * 100)];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6,
            $g => (($b - $r) / $d + 2) / 6,
            $b => (($r - $g) / $d + 4) / 6,
            default => 0,
        };

        return [
            'h' => (int) round($h * 360),
            's' => (int) round($s * 100),
            'l' => (int) round($l * 100),
        ];
    }

    protected function getMimeType(string $imagePath): string
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    /**
     * Generate an SVG from an emoji character.
     */
    public function generateFromEmoji(string $emoji, array $options = []): string
    {
        $backgroundColor = $options['emoji_background'] ?? 'transparent';
        $hasBackground = $backgroundColor !== 'transparent';

        // Emoji font stack for cross-platform support
        $fontFamily = 'Apple Color Emoji, Segoe UI Emoji, Noto Color Emoji, sans-serif';

        $bgRect = $hasBackground
            ? '<rect class="favicon-bg" x="0" y="0" width="100" height="100" fill="'.htmlspecialchars($backgroundColor).'"/>'
            : '';

        $css = '';
        if ($hasBackground && isset($options['emoji_dark_background'])) {
            $darkBg = $options['emoji_dark_background'];
            $css = '<style>.favicon-bg { fill: '.htmlspecialchars($backgroundColor).'; } @media (prefers-color-scheme: dark) { .favicon-bg { fill: '.htmlspecialchars($darkBg).'; } }</style>';
            $bgRect = '<rect class="favicon-bg" x="0" y="0" width="100" height="100"/>';
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
  {$css}{$bgRect}<text x="50" y="55" text-anchor="middle" dominant-baseline="central" font-size="80" font-family="{$fontFamily}">{$emoji}</text>
</svg>
SVG;
    }

    /**
     * Generate an SVG from text characters.
     */
    public function generateFromText(string $text, array $options = []): string
    {
        $backgroundColor = $options['text_background_color'] ?? '#4f46e5';
        $textColor = $options['text_color'] ?? '#ffffff';
        $fontFamily = $this->getFontFamily($options['text_font'] ?? 'system-ui');
        $fontWeight = $options['text_weight'] ?? 'bold';

        // Adjust font size based on character count
        $charCount = mb_strlen($text);
        $fontSize = match (true) {
            $charCount === 1 => 70,
            $charCount === 2 => 55,
            $charCount === 3 => 40,
            default => 32,
        };

        $css = $this->buildTextModeCss($backgroundColor, $textColor, $options);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
  <style>{$css}</style>
  <rect class="favicon-bg" x="0" y="0" width="100" height="100"/>
  <text class="favicon-text" x="50" y="55" text-anchor="middle" dominant-baseline="central" font-size="{$fontSize}" font-weight="{$fontWeight}" font-family="{$fontFamily}">{$text}</text>
</svg>
SVG;
    }

    /**
     * Build CSS for text mode with dark mode support.
     */
    protected function buildTextModeCss(string $backgroundColor, string $textColor, array $options): string
    {
        $darkBg = $options['text_dark_background_color'] ?? $backgroundColor;
        $darkText = $options['text_dark_color'] ?? $textColor;

        $css = ".favicon-bg { fill: {$backgroundColor}; } .favicon-text { fill: {$textColor}; }";

        if ($darkBg !== $backgroundColor || $darkText !== $textColor) {
            $css .= " @media (prefers-color-scheme: dark) { .favicon-bg { fill: {$darkBg}; } .favicon-text { fill: {$darkText}; } }";
        }

        return $css;
    }

    /**
     * Get the font family CSS value for the given font key.
     */
    protected function getFontFamily(string $fontKey): string
    {
        return match ($fontKey) {
            'sans-serif' => 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            'serif' => 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif',
            'monospace' => 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
            default => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        };
    }
}
