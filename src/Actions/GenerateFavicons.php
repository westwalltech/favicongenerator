<?php

namespace WestWallTech\FaviconGenerator\Actions;

use Illuminate\Support\Facades\Log;
use Imagick;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use WestWallTech\FaviconGenerator\Support\SvgGenerator;

class GenerateFavicons
{
    protected bool $hasImagick;

    private const PNG_SIZES = [
        ['name' => 'favicon-96x96.png', 'size' => 96],
        ['name' => 'apple-touch-icon.png', 'size' => 180],
        ['name' => 'icon-192.png', 'size' => 192],
        ['name' => 'icon-512.png', 'size' => 512],
    ];

    private const MASKABLE_SIZES = [
        ['name' => 'icon-192-maskable.png', 'size' => 192],
        ['name' => 'icon-512-maskable.png', 'size' => 512],
    ];

    private const ICO_SIZES = [16, 32, 48];

    public function __construct(
        protected SvgGenerator $svgGenerator
    ) {
        $this->hasImagick = extension_loaded('imagick');
    }

    /**
     * Check if Imagick is available for SVG processing.
     */
    public static function canProcessSvg(): bool
    {
        if (! extension_loaded('imagick')) {
            return false;
        }

        try {
            $imagick = new Imagick();
            $formats = $imagick->queryFormats('SVG');

            return ! empty($formats);
        } catch (\Exception $e) {
            Log::error('[FaviconGenerator] canProcessSvg: Failed to check Imagick SVG support', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array<string>
     */
    public function __invoke(string $sourcePath, array $options): array
    {
        $outputPath = config('favicon-generator.output_path', public_path());
        $isSvgSource = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'svg';
        $sourceType = $options['source_type'] ?? 'asset';

        // For emoji/text sources, we need special handling
        if ($sourceType === 'emoji' || $sourceType === 'text') {
            return $this->generateFromEmojiOrText($sourcePath, $outputPath, $options);
        }

        // Normalize source path - download remote files to temp location if needed
        $localSourcePath = $this->normalizeSourcePath($sourcePath);
        $tempSourceFile = $localSourcePath !== $sourcePath ? $localSourcePath : null;

        try {
            // Load image - use Imagick for SVG, Intervention for raster
            if ($isSvgSource && $this->hasImagick) {
                $image = $this->loadSvgWithImagick($localSourcePath, $options);
            } else {
                $image = Image::read($localSourcePath);
            }

            $generatedFiles = [];

            // Generate SVG favicon
            $generatedFiles[] = $this->generateSvgFavicon($localSourcePath, $outputPath, $options, $isSvgSource);

            // Generate PNG files
            foreach (self::PNG_SIZES as $spec) {
                $generatedFiles[] = $this->generatePng($image, $spec['size'], $outputPath.'/'.$spec['name'], $options);
            }

            // Generate maskable icons for Android adaptive icons (with extra safe zone padding)
            foreach (self::MASKABLE_SIZES as $spec) {
                $generatedFiles[] = $this->generateMaskableIcon($image, $spec['size'], $outputPath.'/'.$spec['name'], $options);
            }

            $generatedFiles[] = $this->generateIcoFile($image, $outputPath, $options);
            $generatedFiles[] = $this->generateWebManifest($outputPath, $options);

            return $generatedFiles;
        } finally {
            // Clean up temp file if we downloaded from remote
            if ($tempSourceFile && file_exists($tempSourceFile)) {
                unlink($tempSourceFile);
            }
        }
    }

    /**
     * Generate favicons from emoji or text source.
     */
    protected function generateFromEmojiOrText(string $svgSourcePath, string $outputPath, array $options): array
    {
        $generatedFiles = [];
        $sourceType = $options['source_type'];
        $isEmoji = $sourceType === 'emoji';

        // For emoji, try to fetch Twemoji SVG for consistent appearance across all formats
        $twemojiSvg = null;
        $baseImage = null;

        if ($isEmoji) {
            $emoji = $options['source_emoji'] ?? '';
            $twemojiSvg = $this->fetchTwemojiSvg($emoji);

            if ($twemojiSvg) {
                // Use Twemoji SVG for favicon.svg
                $generatedFiles[] = $this->generateTwemojiSvgFavicon($twemojiSvg, $outputPath, $options);
                // Create base image from Twemoji
                $baseImage = $this->createEmojiImageFromSvg($twemojiSvg, $options, 512);
            } else {
                // Fallback to text-based SVG
                $generatedFiles[] = $this->generateSvgFavicon($svgSourcePath, $outputPath, $options, true);
                $baseImage = $this->createEmojiImage($options, 512);
            }
        } else {
            // Text mode - use the text-based SVG
            $generatedFiles[] = $this->generateSvgFavicon($svgSourcePath, $outputPath, $options, true);
            $baseImage = $this->createTextImage($options, 512);
        }

        // Generate PNG files
        foreach (self::PNG_SIZES as $spec) {
            $generatedFiles[] = $this->generatePng($baseImage, $spec['size'], $outputPath.'/'.$spec['name'], $options);
        }

        // Generate maskable icons
        foreach (self::MASKABLE_SIZES as $spec) {
            $generatedFiles[] = $this->generateMaskableIcon($baseImage, $spec['size'], $outputPath.'/'.$spec['name'], $options);
        }

        $generatedFiles[] = $this->generateIcoFile($baseImage, $outputPath, $options);
        $generatedFiles[] = $this->generateWebManifest($outputPath, $options);

        return $generatedFiles;
    }

    /**
     * Create an image from emoji using Twemoji.
     */
    protected function createEmojiImage(array $options, int $size): ImageInterface
    {
        $emoji = $options['source_emoji'] ?? '';
        $bgColor = ($options['png_transparent'] ?? true) ? 'transparent' : ($options['png_background'] ?? '#ffffff');
        $isTransparent = $bgColor === 'transparent';

        // Try to fetch emoji from Twemoji
        $emojiImage = $this->fetchTwemojiImage($emoji);

        if ($emojiImage) {
            // Successfully got Twemoji image
            $baseImage = $emojiImage;
        } else {
            // Fallback: create a simple colored square as placeholder
            $baseImage = $this->createFallbackEmojiImage($size, $options);
        }

        // Apply background if not transparent
        if (! $isTransparent && $this->hasImagick) {
            $baseImage = $this->applyBackgroundToImage($baseImage, $size, $bgColor);
        }

        // Apply padding if specified
        $padding = $options['icon_padding'] ?? 0;
        if ($padding > 0) {
            $baseImage = $this->applyPaddingToInterventionImage($baseImage, $size, $padding, $bgColor);
        }

        return $baseImage;
    }

    /**
     * Fetch emoji image from Twemoji CDN.
     */
    protected function fetchTwemojiImage(string $emoji): ?ImageInterface
    {
        // Convert emoji to Twemoji codepoint format
        $codepoints = [];
        $length = mb_strlen($emoji);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($emoji, $i, 1);
            $ord = mb_ord($char);

            // Skip variation selectors (FE0E, FE0F)
            if ($ord === 0xFE0E || $ord === 0xFE0F) {
                continue;
            }

            $codepoints[] = dechex($ord);
        }

        if (empty($codepoints)) {
            return null;
        }

        $codepointStr = implode('-', $codepoints);

        // Try to fetch SVG from Twemoji CDN
        $svgUrl = "https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg/{$codepointStr}.svg";

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'FaviconGenerator/1.0',
                ],
            ]);

            $svgContent = @file_get_contents($svgUrl, false, $context);

            if ($svgContent === false) {
                return null;
            }

            // Convert SVG to PNG using Imagick
            if ($this->hasImagick) {
                return $this->convertSvgToImage($svgContent, 512);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('[FaviconGenerator] fetchTwemojiImage: Failed to fetch Twemoji image', [
                'emoji' => $emoji,
                'url' => $svgUrl ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch emoji SVG content from Twemoji CDN.
     */
    protected function fetchTwemojiSvg(string $emoji): ?string
    {
        $codepoints = [];
        $length = mb_strlen($emoji);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($emoji, $i, 1);
            $ord = mb_ord($char);

            // Skip variation selectors (FE0E, FE0F)
            if ($ord === 0xFE0E || $ord === 0xFE0F) {
                continue;
            }

            $codepoints[] = dechex($ord);
        }

        if (empty($codepoints)) {
            return null;
        }

        $codepointStr = implode('-', $codepoints);
        $svgUrl = "https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg/{$codepointStr}.svg";

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'FaviconGenerator/1.0',
                ],
            ]);

            $svgContent = @file_get_contents($svgUrl, false, $context);

            return $svgContent !== false ? $svgContent : null;
        } catch (\Exception $e) {
            Log::error('[FaviconGenerator] fetchTwemojiSvg: Failed to fetch Twemoji SVG', [
                'emoji' => $emoji,
                'url' => $svgUrl ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate favicon.svg from Twemoji SVG content.
     */
    protected function generateTwemojiSvgFavicon(string $twemojiSvg, string $outputPath, array $options): string
    {
        $svgPath = $outputPath.'/favicon.svg';

        $pngTransparent = ($options['png_transparent'] ?? true);
        $padding = $options['icon_padding'] ?? 0;

        // Extract viewBox dimensions
        if (preg_match('/viewBox=["\']([^"\']+)["\']/', $twemojiSvg, $matches)) {
            $viewBox = array_map('floatval', preg_split('/[\s,]+/', trim($matches[1])));
            $width = $viewBox[2] ?? 36;
            $height = $viewBox[3] ?? 36;
        } else {
            $width = 36;
            $height = 36;
        }

        $css = '';
        $bgRect = '';

        // Add background if not transparent
        if (! $pngTransparent) {
            $bgColor = $options['png_background'] ?? '#ffffff';
            $darkBgColor = $options['png_dark_background'] ?? '#1a1a1a';
            $css = ".favicon-bg { fill: {$bgColor}; } @media (prefers-color-scheme: dark) { .favicon-bg { fill: {$darkBgColor}; } }";
            $bgRect = "<rect class=\"favicon-bg\" x=\"0\" y=\"0\" width=\"{$width}\" height=\"{$height}\"/>";
        }

        // Apply padding by wrapping content in a scaled/translated group
        if ($padding > 0) {
            $paddingFraction = $padding / 100;
            $scale = 1 - ($paddingFraction * 2);
            $translateX = $width * $paddingFraction;
            $translateY = $height * $paddingFraction;

            // Extract the inner content (everything after <svg...>)
            if (preg_match('/(<svg[^>]*>)(.*?)(<\/svg>)/is', $twemojiSvg, $contentMatches)) {
                $svgOpen = $contentMatches[1];
                $innerContent = $contentMatches[2];
                $svgClose = $contentMatches[3];

                $groupOpen = "<g transform=\"translate({$translateX},{$translateY}) scale({$scale})\">";
                $groupClose = '</g>';

                $styleTag = $css ? "<style>{$css}</style>" : '';
                $twemojiSvg = $svgOpen.$styleTag.$bgRect.$groupOpen.$innerContent.$groupClose.$svgClose;
            }
        } elseif ($css || $bgRect) {
            // No padding but need to add style/background
            $twemojiSvg = preg_replace(
                '/(<svg[^>]*>)/i',
                '$1<style>'.$css.'</style>'.$bgRect,
                $twemojiSvg,
                1
            );
        }

        file_put_contents($svgPath, $twemojiSvg);

        return $svgPath;
    }

    /**
     * Create base image from Twemoji SVG content.
     */
    protected function createEmojiImageFromSvg(string $svgContent, array $options, int $size): ImageInterface
    {
        $bgColor = ($options['png_transparent'] ?? true) ? 'transparent' : ($options['png_background'] ?? '#ffffff');
        $isTransparent = $bgColor === 'transparent';

        // Convert SVG to image
        $baseImage = $this->convertSvgToImage($svgContent, $size);

        if (! $baseImage) {
            // Fallback to placeholder
            return $this->createFallbackEmojiImage($size, $options);
        }

        // Apply background if not transparent
        if (! $isTransparent && $this->hasImagick) {
            $baseImage = $this->applyBackgroundToImage($baseImage, $size, $bgColor);
        }

        // Apply padding if specified
        $padding = $options['icon_padding'] ?? 0;
        if ($padding > 0) {
            $baseImage = $this->applyPaddingToInterventionImage($baseImage, $size, $padding, $bgColor);
        }

        return $baseImage;
    }

    /**
     * Convert SVG content to Intervention Image.
     */
    protected function convertSvgToImage(string $svgContent, int $size): ?ImageInterface
    {
        try {
            $imagick = new Imagick();
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            $imagick->setResolution(600, 600);
            $imagick->readImageBlob($svgContent);

            // Ensure RGBA colorspace (not grayscale)
            $imagick->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $imagick->setImageType(Imagick::IMGTYPE_TRUECOLORALPHA);
            $imagick->setImageFormat('png32');

            // Resize to desired size
            $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);

            $tempPath = sys_get_temp_dir().'/twemoji-'.uniqid().'.png';
            $imagick->writeImage($tempPath);
            $imagick->destroy();

            $image = Image::read($tempPath);
            unlink($tempPath);

            return $image;
        } catch (\Exception $e) {
            Log::error('[FaviconGenerator] convertSvgToImage: Failed to convert SVG to image', [
                'size' => $size,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a fallback image when emoji can't be rendered.
     */
    protected function createFallbackEmojiImage(int $size, array $options): ImageInterface
    {
        $bgColor = ($options['png_transparent'] ?? true) ? 'transparent' : ($options['png_background'] ?? '#ffffff');
        $themeColor = $options['theme_color'] ?? '#4f46e5';

        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);

        if ($bgColor === 'transparent') {
            $bg = imagecolorallocatealpha($image, 255, 255, 255, 127);
        } else {
            $rgb = $this->hexToRgb($bgColor);
            $bg = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        }
        imagefill($image, 0, 0, $bg);

        // Draw a colored circle as placeholder
        $themeRgb = $this->hexToRgb($themeColor);
        $circleColor = imagecolorallocate($image, $themeRgb['r'], $themeRgb['g'], $themeRgb['b']);

        $centerX = $size / 2;
        $centerY = $size / 2;
        $radius = $size * 0.35;

        imagefilledellipse($image, (int) $centerX, (int) $centerY, (int) ($radius * 2), (int) ($radius * 2), $circleColor);

        $tempPath = sys_get_temp_dir().'/emoji-fallback-'.uniqid().'.png';
        imagepng($image, $tempPath);
        imagedestroy($image);

        $interventionImage = Image::read($tempPath);
        unlink($tempPath);

        return $interventionImage;
    }

    /**
     * Apply background color to an image.
     */
    protected function applyBackgroundToImage(ImageInterface $image, int $size, string $bgColor): ImageInterface
    {
        $tempPath = sys_get_temp_dir().'/favicon-temp-'.uniqid().'.png';
        $image->toPng()->save($tempPath);

        $imagick = new Imagick($tempPath);

        $canvas = new Imagick();
        $canvas->newImage($size, $size, new \ImagickPixel($bgColor));
        $canvas->setImageFormat('png32');

        // Resize source to fit
        $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);

        // Composite the emoji on top
        $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, 0, 0);

        $canvas->writeImage($tempPath);
        $imagick->destroy();
        $canvas->destroy();

        $result = Image::read($tempPath);
        unlink($tempPath);

        return $result;
    }

    /**
     * Apply padding to an Intervention Image.
     */
    protected function applyPaddingToInterventionImage(ImageInterface $image, int $size, int $paddingPercent, string $bgColor): ImageInterface
    {
        $paddingFraction = $paddingPercent / 100;
        $innerSize = (int) round($size * (1 - $paddingFraction * 2));
        $offset = (int) (($size - $innerSize) / 2);

        $tempPath = sys_get_temp_dir().'/favicon-pad-'.uniqid().'.png';
        $image->toPng()->save($tempPath);

        $imagick = new Imagick($tempPath);
        $imagick->resizeImage($innerSize, $innerSize, Imagick::FILTER_LANCZOS, 1);

        $canvas = new Imagick();
        $bgPixel = $bgColor === 'transparent' ? 'transparent' : $bgColor;
        $canvas->newImage($size, $size, new \ImagickPixel($bgPixel));
        $canvas->setImageFormat('png32');

        $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, $offset, $offset);

        $canvas->writeImage($tempPath);
        $imagick->destroy();
        $canvas->destroy();

        $result = Image::read($tempPath);
        unlink($tempPath);

        return $result;
    }

    /**
     * Create an image with text using GD library.
     */
    protected function createTextImage(array $options, int $size): ImageInterface
    {
        $sourceType = $options['source_type'];
        $isEmoji = $sourceType === 'emoji';
        $content = $isEmoji ? ($options['source_emoji'] ?? '') : ($options['source_text'] ?? '');

        // Determine colors
        if ($isEmoji) {
            $bgColor = ($options['png_transparent'] ?? true) ? 'transparent' : ($options['png_background'] ?? '#ffffff');
            $textColor = '#000000';
        } else {
            $bgColor = $options['text_background_color'] ?? $options['theme_color'] ?? '#4f46e5';
            $textColor = $options['text_color'] ?? '#ffffff';
        }

        // Parse colors
        $bgRgb = $this->hexToRgb($bgColor === 'transparent' ? '#ffffff' : $bgColor);
        $textRgb = $this->hexToRgb($textColor);
        $isTransparent = $bgColor === 'transparent';

        // Create GD image
        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);

        if ($isTransparent) {
            $bg = imagecolorallocatealpha($image, 255, 255, 255, 127);
        } else {
            $bg = imagecolorallocate($image, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
        }
        imagefill($image, 0, 0, $bg);

        $textColorAlloc = imagecolorallocate($image, $textRgb['r'], $textRgb['g'], $textRgb['b']);

        // Find a font
        $fontPath = $this->findTtfFont($isEmoji, $options['text_font'] ?? 'system-ui', $options['text_weight'] ?? 'bold');

        if ($fontPath && ! $isEmoji) {
            // Use TrueType font for text
            $charCount = mb_strlen($content);
            $fontSize = match (true) {
                $charCount === 1 => $size * 0.55,
                $charCount === 2 => $size * 0.42,
                $charCount === 3 => $size * 0.32,
                default => $size * 0.25,
            };

            // Calculate text bounding box for centering
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $content);
            $textWidth = abs($bbox[4] - $bbox[0]);
            $textHeight = abs($bbox[5] - $bbox[1]);

            $x = ($size - $textWidth) / 2;
            $y = ($size + $textHeight) / 2;

            imagettftext($image, $fontSize, 0, (int) $x, (int) $y, $textColorAlloc, $fontPath, $content);
        } else {
            // Fallback: use built-in GD font (limited but works everywhere)
            // GD's built-in fonts don't support emoji well, so for emoji we'll create a colored square
            if ($isEmoji) {
                // For emoji, create a distinctive colored image since GD can't render emoji
                // The SVG will still have the emoji, this is just for PNG fallback
                $emojiColor = imagecolorallocate($image, 255, 200, 100);
                imagefilledrectangle($image, $size / 4, $size / 4, $size * 3 / 4, $size * 3 / 4, $emojiColor);
            } else {
                // Use the largest built-in font
                $font = 5;
                $fontWidth = imagefontwidth($font);
                $fontHeight = imagefontheight($font);
                $textWidth = strlen($content) * $fontWidth;

                $x = ($size - $textWidth) / 2;
                $y = ($size - $fontHeight) / 2;

                imagestring($image, $font, (int) $x, (int) $y, $content, $textColorAlloc);
            }
        }

        // Apply padding if specified
        $padding = $options['icon_padding'] ?? 0;
        if ($padding > 0) {
            $image = $this->applyPaddingToGdImage($image, $size, $padding, $bgColor);
        }

        // Save to temp file and convert to Intervention Image
        $tempPath = sys_get_temp_dir().'/favicon-text-img-'.uniqid().'.png';
        imagepng($image, $tempPath);
        imagedestroy($image);

        $interventionImage = Image::read($tempPath);
        unlink($tempPath);

        return $interventionImage;
    }

    /**
     * Convert hex color to RGB array.
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Apply padding to a GD image.
     */
    protected function applyPaddingToGdImage($image, int $size, int $paddingPercent, string $bgColor)
    {
        $paddingFraction = $paddingPercent / 100;
        $innerSize = (int) round($size * (1 - $paddingFraction * 2));
        $offset = (int) (($size - $innerSize) / 2);

        // Create new canvas
        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, true);

        $bgRgb = $this->hexToRgb($bgColor === 'transparent' ? '#ffffff' : $bgColor);
        if ($bgColor === 'transparent') {
            $bg = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        } else {
            $bg = imagecolorallocate($canvas, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
        }
        imagefill($canvas, 0, 0, $bg);

        // Resize original and copy to canvas
        $resized = imagecreatetruecolor($innerSize, $innerSize);
        imagesavealpha($resized, true);
        imagealphablending($resized, false);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $innerSize, $innerSize, $size, $size);

        imagealphablending($canvas, true);
        imagecopy($canvas, $resized, $offset, $offset, 0, 0, $innerSize, $innerSize);

        imagedestroy($image);
        imagedestroy($resized);

        return $canvas;
    }

    /**
     * Find a TrueType font file.
     */
    protected function findTtfFont(bool $isEmoji, string $fontFamily, string $fontWeight): ?string
    {
        // Common font directories
        $fontDirs = [
            '/System/Library/Fonts',
            '/System/Library/Fonts/Supplemental',
            '/Library/Fonts',
            '/usr/share/fonts/truetype',
            '/usr/share/fonts',
            '/usr/local/share/fonts',
        ];

        // For emoji, we can't really use TTF fonts with GD, so return null
        if ($isEmoji) {
            return null;
        }

        // Text mode - look for appropriate fonts (only .ttf, not .ttc)
        $fontPatterns = match ($fontFamily) {
            'serif' => ['Times*.ttf', 'Georgia*.ttf', 'DejaVuSerif*.ttf', 'LiberationSerif*.ttf'],
            'monospace' => ['Courier*.ttf', 'DejaVuSansMono*.ttf', 'LiberationMono*.ttf'],
            default => ['Arial*.ttf', 'Helvetica*.ttf', 'DejaVuSans.ttf', 'DejaVuSans-Bold.ttf', 'LiberationSans*.ttf', 'Roboto*.ttf'],
        };

        // Prefer bold variants if weight is bold
        if ($fontWeight === 'bold') {
            $boldPatterns = ['*Bold*.ttf', '*-Bold.ttf'];
            $fontPatterns = array_merge($boldPatterns, $fontPatterns);
        }

        foreach ($fontDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($fontPatterns as $pattern) {
                $matches = glob($dir.'/'.$pattern);
                if (! empty($matches)) {
                    // Filter to only .ttf files (not .ttc)
                    foreach ($matches as $match) {
                        if (strtolower(pathinfo($match, PATHINFO_EXTENSION)) === 'ttf') {
                            return $match;
                        }
                    }
                }

                // Also check subdirectories
                $matches = glob($dir.'/*/'.$pattern);
                if (! empty($matches)) {
                    foreach ($matches as $match) {
                        if (strtolower(pathinfo($match, PATHINFO_EXTENSION)) === 'ttf') {
                            return $match;
                        }
                    }
                }
            }
        }

        // Final fallback - find any .ttf file
        foreach ($fontDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $matches = glob($dir.'/*.ttf');
            if (! empty($matches)) {
                return $matches[0];
            }

            $matches = glob($dir.'/*/*.ttf');
            if (! empty($matches)) {
                return $matches[0];
            }
        }

        return null;
    }

    /**
     * Load SVG file using Imagick and convert to Intervention Image.
     */
    protected function loadSvgWithImagick(string $svgPath, array $options = []): ImageInterface
    {
        // Read SVG content from local file or remote URL
        $svgContent = $this->getFileContents($svgPath);
        $svgContent = $this->prepareSvgForRasterization($svgContent, $options);

        // Write prepared SVG to temp file
        $tempSvgPath = sys_get_temp_dir().'/favicon-svg-prepared-'.uniqid().'.svg';
        file_put_contents($tempSvgPath, $svgContent);

        try {
            $imagick = new Imagick();

            // Set high resolution before reading for better quality (600 DPI for crisp edges)
            $imagick->setResolution(600, 600);

            // Set background to transparent
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));

            $imagick->readImage($tempSvgPath);

            // Ensure RGBA colorspace (not grayscale)
            $imagick->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $imagick->setImageType(Imagick::IMGTYPE_TRUECOLORALPHA);

            // Convert to PNG format with alpha channel
            $imagick->setImageFormat('png32');
            $imagick->setImageCompressionQuality(100);

            // Ensure minimum size of 1024x1024 for better quality when scaling down
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $minSize = 1024;

            if ($width < $minSize || $height < $minSize) {
                $scale = max($minSize / $width, $minSize / $height);
                $newWidth = (int) ceil($width * $scale);
                $newHeight = (int) ceil($height * $scale);
                $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
            }

            // Create temp file and return Intervention Image
            $tempPngPath = sys_get_temp_dir().'/favicon-svg-'.uniqid().'.png';
            $imagick->writeImage($tempPngPath);
            $imagick->destroy();

            $image = Image::read($tempPngPath);
            unlink($tempPngPath);

            return $image;
        } finally {
            if (file_exists($tempSvgPath)) {
                unlink($tempSvgPath);
            }
        }
    }

    /**
     * Prepare SVG content for rasterization by replacing CSS variables and currentColor.
     */
    protected function prepareSvgForRasterization(string $svgContent, array $options = []): string
    {
        // Determine the icon color to use
        $iconColor = '#000000'; // Default to black
        if (! empty($options['use_custom_icon_color']) && ! empty($options['icon_color'])) {
            $iconColor = $options['icon_color'];
        }

        // Replace currentColor with the specified color
        $svgContent = str_replace('currentColor', $iconColor, $svgContent);

        // Also replace common fill patterns if using custom color
        if (! empty($options['use_custom_icon_color']) && ! empty($options['icon_color'])) {
            // Replace fill="#000000" or fill="#000" with the custom color
            $svgContent = preg_replace('/fill=["\']#0{3,6}["\']/', 'fill="'.$iconColor.'"', $svgContent);
        }

        // Remove dark mode media queries that shouldn't affect rasterization
        $svgContent = preg_replace(
            '/@media\s*\(\s*prefers-color-scheme:\s*dark\s*\)\s*\{[^}]*\}/i',
            '',
            $svgContent
        );

        // Add viewBox if missing to prevent edge clipping
        $svgContent = $this->ensureViewBox($svgContent);

        return $svgContent;
    }

    /**
     * Ensure SVG has a viewBox attribute to prevent edge clipping.
     * Adds slight padding to prevent anti-aliasing artifacts at edges.
     */
    protected function ensureViewBox(string $svgContent): string
    {
        // Check if viewBox already exists
        if (preg_match('/viewBox\s*=\s*["\'][^"\']+["\']/', $svgContent)) {
            return $svgContent;
        }

        // Extract width and height from SVG
        $width = 100;
        $height = 100;

        if (preg_match('/\bwidth\s*=\s*["\']?(\d+(?:\.\d+)?)["\']?/', $svgContent, $wMatch)) {
            $width = (float) $wMatch[1];
        }
        if (preg_match('/\bheight\s*=\s*["\']?(\d+(?:\.\d+)?)["\']?/', $svgContent, $hMatch)) {
            $height = (float) $hMatch[1];
        }

        // Add small padding (1%) to prevent edge clipping from anti-aliasing
        $padding = max($width, $height) * 0.01;
        $viewBoxX = -$padding;
        $viewBoxY = -$padding;
        $viewBoxWidth = $width + ($padding * 2);
        $viewBoxHeight = $height + ($padding * 2);

        // Insert viewBox after the opening svg tag
        $svgContent = preg_replace(
            '/(<svg\s)/i',
            sprintf('$1viewBox="%.2f %.2f %.2f %.2f" ', $viewBoxX, $viewBoxY, $viewBoxWidth, $viewBoxHeight),
            $svgContent,
            1
        );

        return $svgContent;
    }

    /**
     * Generate a PNG file with high quality.
     */
    protected function generatePng(ImageInterface $image, int $size, string $outputPath, array $options = []): string
    {
        if ($this->hasImagick) {
            $this->createHighQualityPng($image, $size, $outputPath, $options);
        } else {
            $resized = clone $image;
            // Use contain mode to fit entire icon without cropping
            $resized->contain($size, $size);
            $resized->toPng()->save($outputPath);
        }

        return $outputPath;
    }

    /**
     * Create a high-quality PNG using Imagick with Lanczos filter.
     */
    protected function createHighQualityPng(ImageInterface $sourceImage, int $size, string $outputPath, array $options = []): void
    {
        $padding = $options['icon_padding'] ?? 0;
        $useTransparent = $options['png_transparent'] ?? true;
        $bgColor = $useTransparent ? 'transparent' : ($options['png_background'] ?? '#ffffff');

        $this->createPngWithImagick($sourceImage, $size, $outputPath, $padding, $bgColor);
    }

    /**
     * Create a PNG using Imagick with specified padding and background.
     */
    protected function createPngWithImagick(
        ImageInterface $sourceImage,
        int $size,
        string $outputPath,
        int $paddingPercent,
        string $backgroundColor
    ): void {
        $tempSourcePath = sys_get_temp_dir().'/source_image_'.uniqid().'.png';
        $sourceImage->toPng()->save($tempSourcePath);

        try {
            $imagick = new Imagick($tempSourcePath);
            $imagick->setImageCompressionQuality(100);
            $imagick->setOption('png:compression-level', '9');
            $imagick->setInterlaceScheme(Imagick::INTERLACE_NO);

            $sourceWidth = $imagick->getImageWidth();
            $sourceHeight = $imagick->getImageHeight();

            // Calculate icon size with padding
            $paddingFraction = $paddingPercent / 100;
            $iconSize = (int) round($size * (1 - $paddingFraction * 2));
            $iconSize = max($iconSize, 1);

            // Resize with high quality Lanczos filter (contain mode - fit entire icon)
            $ratio = min($iconSize / $sourceWidth, $iconSize / $sourceHeight);
            $newWidth = (int) round($sourceWidth * $ratio);
            $newHeight = (int) round($sourceHeight * $ratio);
            $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
            $imagick->setImagePage($newWidth, $newHeight, 0, 0);

            // Create canvas with background
            $canvas = new Imagick();
            $canvas->newImage($size, $size, new \ImagickPixel($backgroundColor));
            $canvas->setImageFormat('png32');

            // Center the resized icon on canvas
            $offsetX = (int) (($size - $newWidth) / 2);
            $offsetY = (int) (($size - $newHeight) / 2);
            $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, $offsetX, $offsetY);

            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (! file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $canvas->writeImage($outputPath);
            $canvas->destroy();
            $imagick->destroy();
        } finally {
            if (file_exists($tempSourcePath)) {
                unlink($tempSourcePath);
            }
        }
    }

    protected function generateIcoFile(ImageInterface $image, string $outputPath, array $options = []): string
    {
        $pngImages = [];

        foreach (self::ICO_SIZES as $size) {
            if ($this->hasImagick) {
                $tempPath = sys_get_temp_dir().'/ico_temp_'.$size.'_'.uniqid().'.png';
                $this->createHighQualityPng($image, $size, $tempPath, $options);
                $pngImages[] = [
                    'size' => $size,
                    'data' => file_get_contents($tempPath),
                ];
                unlink($tempPath);
            } else {
                $resized = clone $image;
                $resized->cover($size, $size);
                $pngImages[] = [
                    'size' => $size,
                    'data' => (string) $resized->toPng(),
                ];
            }
        }

        $icoPath = $outputPath.'/favicon.ico';
        file_put_contents($icoPath, $this->buildIcoFile($pngImages));

        return $icoPath;
    }

    protected function buildIcoFile(array $pngImages): string
    {
        $imageCount = count($pngImages);
        $headerSize = 6;
        $directoryEntrySize = 16;
        $dataOffset = $headerSize + ($directoryEntrySize * $imageCount);

        // ICO header: reserved (2), type (2, 1=icon), count (2)
        $ico = pack('vvv', 0, 1, $imageCount);

        // Build directory entries and collect image data
        $imageData = '';
        foreach ($pngImages as $png) {
            $size = $png['size'];
            $data = $png['data'];
            $dataLength = strlen($data);

            // Width/height: 0 means 256
            $width = $size >= 256 ? 0 : $size;
            $height = $size >= 256 ? 0 : $size;

            // Directory entry: width, height, colors, reserved, planes, bits, size, offset
            $ico .= pack('CCCCvvVV',
                $width,
                $height,
                0,              // color palette count (0 for PNG)
                0,              // reserved
                1,              // color planes
                32,             // bits per pixel
                $dataLength,
                $dataOffset
            );

            $dataOffset += $dataLength;
            $imageData .= $data;
        }

        return $ico.$imageData;
    }

    protected function generateSvgFavicon(string $sourcePath, string $outputPath, array $options, bool $isSvgSource): string
    {
        $svgPath = $outputPath.'/favicon.svg';

        if ($isSvgSource) {
            // Source is SVG - copy and apply customizations
            $svgContent = $this->getFileContents($sourcePath);

            // Check if custom icon colors are enabled
            $useCustomColor = ! empty($options['use_custom_icon_color']);

            if ($useCustomColor) {
                // Apply custom light and dark mode icon colors
                $lightColor = $options['icon_color'] ?? '#000000';
                $darkColor = $options['dark_mode_icon_color'] ?? '#ffffff';
                $svgContent = $this->svgGenerator->applyCustomIconColors($svgContent, $lightColor, $darkColor, $options);
            } else {
                // Use the standard dark mode behavior
                $darkModeStyle = $options['dark_mode_style'] ?? 'invert';

                if ($darkModeStyle !== 'none') {
                    $svgContent = $this->svgGenerator->injectDarkModeIntoSvg(
                        $svgContent,
                        $darkModeStyle,
                        $options['dark_mode_color'] ?? '#ffffff',
                        $options
                    );
                } elseif (! ($options['png_transparent'] ?? true)) {
                    // No dark mode style but has background - still need to add background
                    $svgContent = $this->svgGenerator->addBackgroundToSvg($svgContent, $options);
                }
            }

            file_put_contents($svgPath, $svgContent);
        } else {
            // Source is raster - embed as base64 in SVG wrapper
            $svg = $this->svgGenerator->generate(
                $sourcePath,
                $options['dark_mode_style'] ?? 'invert',
                $options['dark_mode_color'] ?? '#ffffff'
            );
            file_put_contents($svgPath, $svg);
        }

        return $svgPath;
    }

    /**
     * Generate a maskable icon with safe zone padding for Android adaptive icons.
     * Maskable icons need 10% padding on each side (icon in inner 80%).
     */
    protected function generateMaskableIcon(ImageInterface $image, int $size, string $outputPath, array $options = []): string
    {
        if ($this->hasImagick) {
            $this->createMaskableIconWithImagick($image, $size, $outputPath, $options);
        } else {
            // Fallback: create with extra padding using Intervention
            $maskableOptions = $options;
            $existingPadding = $options['icon_padding'] ?? 0;
            // Add 10% safe zone padding on top of existing padding
            $maskableOptions['icon_padding'] = min(40, $existingPadding + 10);
            $this->generatePng($image, $size, $outputPath, $maskableOptions);
        }

        return $outputPath;
    }

    /**
     * Create maskable icon using Imagick with proper safe zone.
     */
    protected function createMaskableIconWithImagick(ImageInterface $sourceImage, int $size, string $outputPath, array $options = []): void
    {
        // Maskable icons need 10% safe zone + any user padding
        $userPadding = $options['icon_padding'] ?? 0;
        $totalPadding = min(40, $userPadding + 10);

        // Maskable icons always need a solid background
        $bgColor = $options['png_background'] ?? '#ffffff';

        $this->createPngWithImagick($sourceImage, $size, $outputPath, $totalPadding, $bgColor);
    }

    protected function generateWebManifest(string $outputPath, array $options): string
    {
        $manifest = [
            'name' => $options['app_name'],
            'short_name' => $options['app_short_name'] ?? substr($options['app_name'], 0, 12),
            'description' => $options['app_description'] ?? '',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => $options['theme_color'],
            'background_color' => $options['background_color'],
            'icons' => [
                [
                    'src' => '/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => '/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
                [
                    'src' => '/icon-192-maskable.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/icon-512-maskable.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        // Remove empty description
        if (empty($manifest['description'])) {
            unset($manifest['description']);
        }

        $manifestPath = $outputPath.'/site.webmanifest';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $manifestPath;
    }

    /**
     * Normalize source path - download remote files to temp location if needed.
     * Handles URLs, Statamic asset paths, and local file paths.
     */
    protected function normalizeSourcePath(string $sourcePath): string
    {
        // If it's already a URL, download to temp file
        if ($this->isUrl($sourcePath)) {
            return $this->downloadToTemp($sourcePath);
        }

        // If the file exists locally, use it directly
        if (file_exists($sourcePath)) {
            return $sourcePath;
        }

        // Try to resolve as a Statamic asset path
        $resolvedUrl = $this->resolveAssetUrl($sourcePath);
        if ($resolvedUrl) {
            return $this->downloadToTemp($resolvedUrl);
        }

        // Return as-is and let it fail with a descriptive error
        return $sourcePath;
    }

    /**
     * Get file contents from local path or URL.
     */
    protected function getFileContents(string $path): string
    {
        // If it's a URL, fetch with context
        if ($this->isUrl($path)) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'FaviconGenerator/1.0',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $content = file_get_contents($path, false, $context);

            if ($content === false) {
                throw new \RuntimeException("Failed to fetch file from URL: {$path}");
            }

            return $content;
        }

        // Try local file
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        // Try to resolve as asset URL and fetch
        $resolvedUrl = $this->resolveAssetUrl($path);
        if ($resolvedUrl) {
            return $this->getFileContents($resolvedUrl);
        }

        throw new \RuntimeException("File not found: {$path}");
    }

    /**
     * Check if a path is a URL.
     */
    protected function isUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    /**
     * Download a URL to a temp file and return the temp path.
     */
    protected function downloadToTemp(string $url): string
    {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'tmp';
        $tempPath = sys_get_temp_dir().'/favicon-source-'.uniqid().'.'.$extension;

        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'FaviconGenerator/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = file_get_contents($url, false, $context);

        if ($content === false) {
            throw new \RuntimeException("Failed to download file from: {$url}");
        }

        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * Try to resolve a path as a Statamic asset and return its URL.
     */
    protected function resolveAssetUrl(string $path): ?string
    {
        // Check if Statamic Asset facade is available
        if (! class_exists(\Statamic\Facades\Asset::class)) {
            return null;
        }

        try {
            // If path already has container:: prefix, try it directly
            if (str_contains($path, '::')) {
                $asset = \Statamic\Facades\Asset::find($path);
                if ($asset) {
                    return $asset->absoluteUrl();
                }

                return null;
            }

            // Parse path to extract potential container from first segment
            // e.g., "media/favicons/favicon.svg" -> container: "media", path: "favicons/favicon.svg"
            $segments = explode('/', $path, 2);

            if (count($segments) === 2) {
                $potentialContainer = $segments[0];
                $assetPath = $segments[1];

                // Try with first segment as container
                $asset = \Statamic\Facades\Asset::find("{$potentialContainer}::{$assetPath}");
                if ($asset) {
                    return $asset->absoluteUrl();
                }
            }

            // Fallback: try common container prefixes with full path
            $containers = ['assets', 'media', 'files'];
            foreach ($containers as $container) {
                $asset = \Statamic\Facades\Asset::find("{$container}::{$path}");
                if ($asset) {
                    return $asset->absoluteUrl();
                }
            }
        } catch (\Exception $e) {
            Log::error('[FaviconGenerator] resolveAssetUrl: Failed to resolve asset URL', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
