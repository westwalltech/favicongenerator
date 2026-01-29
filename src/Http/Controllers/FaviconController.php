<?php

namespace WestWallTech\FaviconGenerator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
use WestWallTech\FaviconGenerator\Actions\GenerateFavicons;

class FaviconController extends Controller
{
    private const GENERATED_FILES = [
        'favicon.ico',
        'favicon.svg',
        'apple-touch-icon.png',
        'icon-192.png',
        'icon-512.png',
        'site.webmanifest',
    ];

    private const FILE_METADATA = [
        ['name' => 'favicon.ico', 'dimensions' => '32x32', 'type' => 'image'],
        ['name' => 'favicon.svg', 'dimensions' => 'Scalable', 'type' => 'image'],
        ['name' => 'apple-touch-icon.png', 'dimensions' => '180x180', 'type' => 'image'],
        ['name' => 'icon-192.png', 'dimensions' => '192x192', 'type' => 'image'],
        ['name' => 'icon-512.png', 'dimensions' => '512x512', 'type' => 'image'],
        ['name' => 'site.webmanifest', 'dimensions' => null, 'type' => 'json'],
    ];

    public function index(): Response
    {
        $settings = $this->getSettings();

        return Inertia::render('favicon-generator::FaviconGenerator', [
            'settings' => $settings,
            'generatedFiles' => $this->getGeneratedFilesMetadata(),
            'assetContainers' => $this->getAssetContainers(),
            'assetFieldConfig' => $this->getAssetFieldConfig(),
            'assetFieldMeta' => $this->getAssetFieldMeta($settings['source_asset'] ?? null),
        ]);
    }

    public function generate(Request $request, GenerateFavicons $action): RedirectResponse
    {
        $validated = $request->validate([
            'source_asset' => 'required|string',
            'theme_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'app_name' => 'required|string|max:255',
            'app_short_name' => 'nullable|string|max:12',
            'dark_mode_style' => 'nullable|string|in:invert,lighten,custom,none',
            'dark_mode_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $asset = Asset::find($validated['source_asset']);

        if (! $asset) {
            return back()->withErrors(['source_asset' => 'Source image not found']);
        }

        $extension = strtolower($asset->extension());
        if (! in_array($extension, ['png', 'jpg', 'jpeg'])) {
            return back()->withErrors(['source_asset' => 'Source image must be PNG or JPG format']);
        }

        $dimensions = $asset->dimensions();
        if (! $dimensions || $dimensions[0] < 512 || $dimensions[1] < 512) {
            return back()->withErrors(['source_asset' => 'Image must be at least 512x512 pixels']);
        }

        $action(
            sourcePath: $asset->resolvedPath(),
            options: [
                'theme_color' => $validated['theme_color'],
                'background_color' => $validated['background_color'],
                'app_name' => $validated['app_name'],
                'app_short_name' => $validated['app_short_name'] ?? substr($validated['app_name'], 0, 12),
                'dark_mode_style' => $validated['dark_mode_style'] ?? 'invert',
                'dark_mode_color' => $validated['dark_mode_color'] ?? '#ffffff',
            ]
        );

        $this->saveSettings([
            ...$validated,
            'generated_at' => now()->toIso8601String(),
        ]);

        return back()->with('success', 'Favicons generated successfully!');
    }

    public function preview(): JsonResponse
    {
        return response()->json([
            'files' => $this->getGeneratedFilesMetadata(),
            'settings' => $this->getSettings(),
        ]);
    }

    public function clear(): RedirectResponse
    {
        $outputPath = config('favicon-generator.output_path', public_path());

        foreach (self::GENERATED_FILES as $file) {
            $path = $outputPath.'/'.$file;
            if (file_exists($path)) {
                unlink($path);
            }
        }

        return back()->with('success', 'Generated files removed');
    }

    protected function getAssetFieldConfig(): array
    {
        $container = config('favicon-generator.assets.container');

        if (! $container || ! AssetContainer::find($container)) {
            return [
                'type' => 'html',
                'html' => '<div class="text-sm text-red-500">Asset container not configured. Set <code>FAVICON_GENERATOR_ASSETS_CONTAINER</code> in your .env file or publish the config.</div>',
            ];
        }

        return [
            'type' => 'assets',
            'container' => $container,
            'folder' => config('favicon-generator.assets.folder'),
            'max_files' => 1,
            'mode' => 'list',
            'restrict' => false,
            'allow_uploads' => true,
        ];
    }

    protected function getAssetFieldMeta(?string $value): ?array
    {
        $config = $this->getAssetFieldConfig();

        if (($config['type'] ?? null) !== 'assets') {
            return null;
        }

        $field = new Field('source_asset', $config);
        $field->setValue($value ? [$value] : []);

        return $field->preProcess()->meta();
    }

    protected function getGeneratedFilesMetadata(): array
    {
        $outputPath = config('favicon-generator.output_path', public_path());
        $result = [];

        foreach (self::FILE_METADATA as $file) {
            $path = $outputPath.'/'.$file['name'];

            if (! file_exists($path)) {
                continue;
            }

            $size = filesize($path);
            $modified = filemtime($path);

            $result[] = [
                'name' => $file['name'],
                'path' => '/'.$file['name'],
                'url' => url($file['name']),
                'size' => $size,
                'sizeFormatted' => $this->formatFileSize($size),
                'dimensions' => $file['dimensions'],
                'type' => $file['type'],
                'modified' => $modified,
                'modifiedFormatted' => date('Y-m-d H:i:s', $modified),
            ];
        }

        return $result;
    }

    protected function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        return round($bytes / 1024, 1).' KB';
    }

    protected function getSettings(): array
    {
        $path = base_path(config('favicon-generator.settings_path'));

        if (! file_exists($path)) {
            return [
                'source_asset' => null,
                'theme_color' => config('favicon-generator.default_theme_color', '#4f46e5'),
                'background_color' => config('favicon-generator.default_background_color', '#ffffff'),
                'app_name' => config('app.name', ''),
                'app_short_name' => '',
                'generated_at' => null,
            ];
        }

        return YAML::file($path)->parse();
    }

    protected function saveSettings(array $settings): void
    {
        $path = base_path(config('favicon-generator.settings_path'));
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, YAML::dump($settings));
    }

    protected function getAssetContainers(): array
    {
        return AssetContainer::all()
            ->map(fn ($container) => [
                'handle' => $container->handle(),
                'title' => $container->title(),
            ])
            ->values()
            ->all();
    }
}
