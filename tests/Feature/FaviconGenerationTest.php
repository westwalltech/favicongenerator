<?php

use WestWallTech\FaviconGenerator\Actions\GenerateFavicons;
use WestWallTech\FaviconGenerator\Support\SvgGenerator;

it('generates all required files', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
    ]);

    expect($this->testOutputPath.'/favicon.ico')->toBeFile();
    expect($this->testOutputPath.'/favicon.svg')->toBeFile();
    expect($this->testOutputPath.'/favicon-96x96.png')->toBeFile();
    expect($this->testOutputPath.'/apple-touch-icon.png')->toBeFile();
    expect($this->testOutputPath.'/icon-192.png')->toBeFile();
    expect($this->testOutputPath.'/icon-512.png')->toBeFile();
    expect($this->testOutputPath.'/site.webmanifest')->toBeFile();
});

it('generates maskable icons', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
    ]);

    expect($this->testOutputPath.'/icon-192-maskable.png')->toBeFile();
    expect($this->testOutputPath.'/icon-512-maskable.png')->toBeFile();
});

it('generates manifest with correct data', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#ff5500',
        'background_color' => '#000000',
        'app_name' => 'My Test Website',
        'app_short_name' => 'MyTest',
    ]);

    $manifest = json_decode(file_get_contents($this->testOutputPath.'/site.webmanifest'), true);

    expect($manifest)
        ->name->toBe('My Test Website')
        ->short_name->toBe('MyTest')
        ->theme_color->toBe('#ff5500')
        ->background_color->toBe('#000000')
        ->display->toBe('standalone')
        ->start_url->toBe('/')
        ->scope->toBe('/')
        ->icons->toHaveCount(4);
});

it('generates manifest with maskable icons', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
    ]);

    $manifest = json_decode(file_get_contents($this->testOutputPath.'/site.webmanifest'), true);

    $maskableIcons = array_filter($manifest['icons'], fn ($icon) => ($icon['purpose'] ?? null) === 'maskable');

    expect($maskableIcons)->toHaveCount(2);
});

it('generates svg with dark mode media query', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
    ]);

    $svgContent = file_get_contents($this->testOutputPath.'/favicon.svg');

    expect($svgContent)
        ->toContain('prefers-color-scheme: dark')
        ->toContain('<style>');
});

it('generates valid ico file with multiple sizes', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
    ]);

    $icoPath = $this->testOutputPath.'/favicon.ico';
    $icoData = file_get_contents($icoPath);

    // ICO header: reserved (2 bytes = 0), type (2 bytes = 1 for icon), count (2 bytes)
    $header = unpack('vreserved/vtype/vcount', $icoData);

    expect($header['reserved'])->toBe(0);
    expect($header['type'])->toBe(1); // 1 = icon, 2 = cursor
    expect($header['count'])->toBe(3); // 16x16, 32x32, 48x48
});

it('applies icon padding when specified', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
        'icon_padding' => 10,
    ]);

    // Files should still be generated with padding
    expect($this->testOutputPath.'/icon-512.png')->toBeFile();
    expect($this->testOutputPath.'/icon-192.png')->toBeFile();
});

it('uses custom icon colors when specified with svg source', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick extension required for SVG processing');
    }

    $sourcePath = $this->createTestSvg();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
        'use_custom_icon_color' => true,
        'icon_color' => '#ff0000',
        'dark_mode_icon_color' => '#00ff00',
    ]);

    $svgContent = file_get_contents($this->testOutputPath.'/favicon.svg');

    expect($svgContent)->toContain('--icon-color');
});

it('handles transparent png background', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
        'png_transparent' => true,
    ]);

    expect($this->testOutputPath.'/icon-512.png')->toBeFile();
});

it('handles solid png background', function () {
    $sourcePath = $this->createTestImage();

    $action = new GenerateFavicons(new SvgGenerator);
    $action($sourcePath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
        'png_transparent' => false,
        'png_background' => '#ff0000',
        'png_dark_background' => '#00ff00',
    ]);

    expect($this->testOutputPath.'/icon-512.png')->toBeFile();
});

it('generates svg from emoji', function () {
    $svgGenerator = new SvgGenerator;
    $svg = $svgGenerator->generateFromEmoji('🔥');

    expect($svg)
        ->toContain('🔥')
        ->toContain('<svg')
        ->toContain('viewBox="0 0 100 100"')
        ->toContain('text-anchor="middle"');
});

it('generates svg from text', function () {
    $svgGenerator = new SvgGenerator;
    $svg = $svgGenerator->generateFromText('SA', [
        'text_background_color' => '#4f46e5',
        'text_color' => '#ffffff',
        'text_font' => 'system-ui',
        'text_weight' => 'bold',
    ]);

    expect($svg)
        ->toContain('SA')
        ->toContain('<svg')
        ->toContain('viewBox="0 0 100 100"')
        ->toContain('#4f46e5')
        ->toContain('#ffffff')
        ->toContain('font-weight="bold"');
});

it('adjusts font size based on text length', function () {
    $svgGenerator = new SvgGenerator;

    $svg1 = $svgGenerator->generateFromText('A');
    $svg2 = $svgGenerator->generateFromText('AB');
    $svg3 = $svgGenerator->generateFromText('ABC');
    $svg4 = $svgGenerator->generateFromText('ABCD');

    expect($svg1)->toContain('font-size="70"');
    expect($svg2)->toContain('font-size="55"');
    expect($svg3)->toContain('font-size="40"');
    expect($svg4)->toContain('font-size="32"');
});

it('generates favicons from emoji source', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick extension required for SVG processing');
    }

    $svgGenerator = new SvgGenerator;
    $svgContent = $svgGenerator->generateFromEmoji('🔥');

    $tempSvgPath = $this->testOutputPath.'/emoji-source.svg';
    file_put_contents($tempSvgPath, $svgContent);

    $action = new GenerateFavicons($svgGenerator);
    $action($tempSvgPath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
        'source_type' => 'emoji',
        'source_emoji' => '🔥',
    ]);

    expect($this->testOutputPath.'/favicon.ico')->toBeFile();
    expect($this->testOutputPath.'/favicon.svg')->toBeFile();
    expect($this->testOutputPath.'/icon-192.png')->toBeFile();
    expect($this->testOutputPath.'/icon-512.png')->toBeFile();

    // Verify the PNGs are not tiny (which would indicate failed rendering)
    expect(filesize($this->testOutputPath.'/icon-512.png'))->toBeGreaterThan(500);
});

it('generates favicons from text source', function () {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick extension required for SVG processing');
    }

    $svgGenerator = new SvgGenerator;
    $svgContent = $svgGenerator->generateFromText('SA', [
        'text_background_color' => '#4f46e5',
        'text_color' => '#ffffff',
        'text_font' => 'system-ui',
        'text_weight' => 'bold',
    ]);

    $tempSvgPath = $this->testOutputPath.'/text-source.svg';
    file_put_contents($tempSvgPath, $svgContent);

    $action = new GenerateFavicons($svgGenerator);

    $action($tempSvgPath, [
        'theme_color' => '#4f46e5',
        'background_color' => '#ffffff',
        'app_name' => 'Test App',
        'app_short_name' => 'Test',
        'source_type' => 'text',
        'source_text' => 'SA',
        'text_background_color' => '#4f46e5',
        'text_color' => '#ffffff',
        'text_font' => 'system-ui',
        'text_weight' => 'bold',
    ]);

    expect($this->testOutputPath.'/favicon.ico')->toBeFile();
    expect($this->testOutputPath.'/favicon.svg')->toBeFile();
    expect($this->testOutputPath.'/icon-192.png')->toBeFile();
    expect($this->testOutputPath.'/icon-512.png')->toBeFile();

    $svgFavicon = file_get_contents($this->testOutputPath.'/favicon.svg');
    expect($svgFavicon)->toContain('SA');

    // Verify the PNGs are not tiny (which would indicate failed rendering)
    expect(filesize($this->testOutputPath.'/icon-512.png'))->toBeGreaterThan(500);
});
