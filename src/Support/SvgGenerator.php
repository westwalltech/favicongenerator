<?php

namespace WestWallTech\FaviconGenerator\Support;

class SvgGenerator
{
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

    public function addDarkModeSupport(string $svgContent): string
    {
        $darkModeRule = '@media (prefers-color-scheme: dark) { svg { filter: brightness(1.2) contrast(1.1); } }';

        if (preg_match('/<style[^>]*>.*?<\/style>/s', $svgContent)) {
            return preg_replace(
                '/(<style[^>]*>)(.*?)(<\/style>)/s',
                '$1$2 '.$darkModeRule.' $3',
                $svgContent
            );
        }

        return preg_replace(
            '/(<svg[^>]*>)/i',
            '$1<style>'.$darkModeRule.'</style>',
            $svgContent,
            1
        );
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
        if ($brightnessScale !== 1.0) {
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
}
