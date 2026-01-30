<?php

namespace WestWallTech\FaviconGenerator\Tests;

use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\ServiceProvider as ImageServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use WestWallTech\FaviconGenerator\ServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected string $testOutputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testOutputPath = sys_get_temp_dir().'/favicon-generator-test-'.uniqid();
        mkdir($this->testOutputPath, 0755, true);

        config(['favicon-generator.output_path' => $this->testOutputPath]);

        // Configure filesystem disk for Statamic YAML
        config(['filesystems.disks.standard' => [
            'driver' => 'local',
            'root' => base_path(),
        ]]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testOutputPath)) {
            File::deleteDirectory($this->testOutputPath);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ImageServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function createTestImage(): string
    {
        $image = imagecreatetruecolor(512, 512);
        $blue = imagecolorallocate($image, 79, 70, 229);
        imagefill($image, 0, 0, $blue);

        $path = $this->testOutputPath.'/source.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    protected function createTestSvg(): string
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
  <rect fill="currentColor" width="100" height="100"/>
</svg>
SVG;

        $path = $this->testOutputPath.'/source.svg';
        file_put_contents($path, $svg);

        return $path;
    }
}
