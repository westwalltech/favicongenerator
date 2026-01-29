<?php

namespace WestWallTech\FaviconGenerator\Actions;

use Intervention\Image\Laravel\Facades\Image;
use WestWallTech\FaviconGenerator\Support\SvgGenerator;

class GenerateFavicons
{
    public function __construct(
        protected SvgGenerator $svgGenerator
    ) {}

    /**
     * @return array<string>
     */
    public function __invoke(string $sourcePath, array $options): array
    {
        $outputPath = config('favicon-generator.output_path', public_path());
        $image = Image::read($sourcePath);

        $generatedFiles = [
            $this->generateSvgFavicon($sourcePath, $outputPath, $options),
        ];

        $pngSizes = [
            ['name' => 'apple-touch-icon.png', 'size' => 180],
            ['name' => 'icon-192.png', 'size' => 192],
            ['name' => 'icon-512.png', 'size' => 512],
        ];

        foreach ($pngSizes as $spec) {
            $resized = clone $image;
            $resized->cover($spec['size'], $spec['size']);
            $resized->toPng()->save($outputPath.'/'.$spec['name']);
            $generatedFiles[] = $outputPath.'/'.$spec['name'];
        }

        $generatedFiles[] = $this->generateIcoFile($image, $outputPath);
        $generatedFiles[] = $this->generateWebManifest($outputPath, $options);

        return $generatedFiles;
    }

    protected function generateIcoFile($image, string $outputPath): string
    {
        $sizes = [16, 32, 48];
        $pngImages = [];

        foreach ($sizes as $size) {
            $resized = clone $image;
            $resized->cover($size, $size);
            $pngImages[] = [
                'size' => $size,
                'data' => (string) $resized->toPng(),
            ];
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

    protected function generateSvgFavicon(string $sourcePath, string $outputPath, array $options): string
    {
        $svg = $this->svgGenerator->generate(
            $sourcePath,
            $options['dark_mode_style'] ?? 'invert',
            $options['dark_mode_color'] ?? '#ffffff'
        );
        $svgPath = $outputPath.'/favicon.svg';
        file_put_contents($svgPath, $svg);

        return $svgPath;
    }

    protected function generateWebManifest(string $outputPath, array $options): string
    {
        $manifest = [
            'name' => $options['app_name'],
            'short_name' => $options['app_short_name'] ?? substr($options['app_name'], 0, 12),
            'icons' => [
                ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
            'theme_color' => $options['theme_color'],
            'background_color' => $options['background_color'],
            'display' => 'standalone',
        ];

        $manifestPath = $outputPath.'/site.webmanifest';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $manifestPath;
    }
}
