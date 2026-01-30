<?php

namespace WestWallTech\FaviconGenerator\Actions;

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
        } catch (\Exception) {
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

        // Load image - use Imagick for SVG, Intervention for raster
        if ($isSvgSource && $this->hasImagick) {
            $image = $this->loadSvgWithImagick($sourcePath, $options);
        } else {
            $image = Image::read($sourcePath);
        }

        $generatedFiles = [];

        // Generate SVG favicon
        $generatedFiles[] = $this->generateSvgFavicon($sourcePath, $outputPath, $options, $isSvgSource);

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
    }

    /**
     * Load SVG file using Imagick and convert to Intervention Image.
     */
    protected function loadSvgWithImagick(string $svgPath, array $options = []): ImageInterface
    {
        // Read SVG content and replace currentColor with the appropriate color
        $svgContent = file_get_contents($svgPath);
        $svgContent = $this->prepareSvgForRasterization($svgContent, $options);

        // Write prepared SVG to temp file
        $tempSvgPath = sys_get_temp_dir().'/favicon-svg-prepared-'.uniqid().'.svg';
        file_put_contents($tempSvgPath, $svgContent);

        try {
            $imagick = new Imagick();

            // Set high resolution before reading for better quality
            $imagick->setResolution(300, 300);

            // Set background to transparent
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));

            $imagick->readImage($tempSvgPath);

            // Convert to PNG format with alpha channel
            $imagick->setImageFormat('png32');
            $imagick->setImageCompressionQuality(100);

            // Ensure minimum size of 512x512 for quality
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $minSize = 512;

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
            $svgContent = file_get_contents($sourcePath);

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
}
