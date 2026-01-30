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
use WestWallTech\FaviconGenerator\Support\SvgGenerator;

class FaviconController extends Controller
{
    private const GENERATED_FILES = [
        'favicon.ico',
        'favicon.svg',
        'favicon-96x96.png',
        'apple-touch-icon.png',
        'icon-192.png',
        'icon-512.png',
        'icon-192-maskable.png',
        'icon-512-maskable.png',
        'site.webmanifest',
    ];

    private const FILE_METADATA = [
        ['name' => 'favicon.ico', 'dimensions' => '16x16, 32x32, 48x48', 'type' => 'image'],
        ['name' => 'favicon.svg', 'dimensions' => 'Scalable', 'type' => 'image'],
        ['name' => 'favicon-96x96.png', 'dimensions' => '96x96', 'type' => 'image'],
        ['name' => 'apple-touch-icon.png', 'dimensions' => '180x180', 'type' => 'image'],
        ['name' => 'icon-192.png', 'dimensions' => '192x192', 'type' => 'image'],
        ['name' => 'icon-512.png', 'dimensions' => '512x512', 'type' => 'image'],
        ['name' => 'icon-192-maskable.png', 'dimensions' => '192x192 (maskable)', 'type' => 'image'],
        ['name' => 'icon-512-maskable.png', 'dimensions' => '512x512 (maskable)', 'type' => 'image'],
        ['name' => 'site.webmanifest', 'dimensions' => null, 'type' => 'json'],
    ];

    private const VALIDATION_RULES = [
        'theme_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'app_name' => 'required|string|max:255',
        'app_short_name' => 'nullable|string|max:12',
        'dark_mode_style' => 'nullable|string|in:invert,lighten,custom,none',
        'dark_mode_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'icon_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'dark_mode_icon_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'use_custom_icon_color' => 'nullable|boolean',
        'icon_padding' => 'nullable|integer|min:0|max:40',
        'png_background' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'png_dark_background' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'png_transparent' => 'nullable|boolean',
        // Source type fields
        'source_type' => 'nullable|string|in:asset,emoji,text',
        'source_emoji' => 'nullable|string|max:8',
        'source_text' => 'nullable|string|max:4',
        'text_font' => 'nullable|string|in:system-ui,sans-serif,serif,monospace',
        'text_weight' => 'nullable|string|in:normal,medium,bold',
        'text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        'text_background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
    ];

    public function index(): Response
    {
        $settings = $this->getSettings();
        $canProcessSvg = GenerateFavicons::canProcessSvg();

        return Inertia::render('favicon-generator::FaviconGenerator', [
            'settings' => $settings,
            'generatedFiles' => $this->getGeneratedFilesMetadata(),
            'assetContainers' => $this->getAssetContainers(),
            'assetFieldConfig' => $this->getAssetFieldConfig(),
            'assetFieldMeta' => $this->getAssetFieldMeta($settings['source_asset'] ?? null),
            'canProcessSvg' => $canProcessSvg,
        ]);
    }

    public function generate(Request $request, GenerateFavicons $action, SvgGenerator $svgGenerator): JsonResponse|RedirectResponse
    {
        $sourceType = $request->input('source_type', 'asset');

        // Build validation rules based on source type
        $rules = match ($sourceType) {
            'emoji' => [
                'source_emoji' => 'required|string|max:8',
                'source_asset' => 'nullable|string',
                ...self::VALIDATION_RULES,
            ],
            'text' => [
                'source_text' => 'required|string|max:4|min:1',
                'source_asset' => 'nullable|string',
                ...self::VALIDATION_RULES,
            ],
            default => [
                'source_asset' => 'required|string',
                ...self::VALIDATION_RULES,
            ],
        };

        $validated = $request->validate($rules);
        $tempSvgPath = null;

        try {
            // Determine source path based on source type
            if ($sourceType === 'emoji') {
                $emoji = $validated['source_emoji'];
                $svgContent = $svgGenerator->generateFromEmoji($emoji, [
                    'emoji_background' => 'transparent',
                ]);
                $tempSvgPath = sys_get_temp_dir().'/favicon-emoji-'.uniqid().'.svg';
                file_put_contents($tempSvgPath, $svgContent);
                $sourcePath = $tempSvgPath;
            } elseif ($sourceType === 'text') {
                $text = $validated['source_text'];
                $svgContent = $svgGenerator->generateFromText($text, [
                    'text_background_color' => $validated['text_background_color'] ?? $validated['theme_color'],
                    'text_color' => $validated['text_color'] ?? '#ffffff',
                    'text_font' => $validated['text_font'] ?? 'system-ui',
                    'text_weight' => $validated['text_weight'] ?? 'bold',
                ]);
                $tempSvgPath = sys_get_temp_dir().'/favicon-text-'.uniqid().'.svg';
                file_put_contents($tempSvgPath, $svgContent);
                $sourcePath = $tempSvgPath;
            } else {
                // Asset source type
                $asset = Asset::find($validated['source_asset']);

                if (! $asset) {
                    if ($request->wantsJson()) {
                        return response()->json(['error' => 'Source image not found'], 422);
                    }

                    return back()->withErrors(['source_asset' => 'Source image not found']);
                }

                $extension = strtolower($asset->extension());
                $canProcessSvg = GenerateFavicons::canProcessSvg();
                $allowedExtensions = ['png', 'jpg', 'jpeg'];

                if ($canProcessSvg) {
                    $allowedExtensions[] = 'svg';
                }

                if (! in_array($extension, $allowedExtensions)) {
                    $formats = $canProcessSvg ? 'PNG, JPG, or SVG' : 'PNG or JPG';

                    if ($request->wantsJson()) {
                        return response()->json(['error' => "Source image must be {$formats} format"], 422);
                    }

                    return back()->withErrors(['source_asset' => "Source image must be {$formats} format"]);
                }

                // For raster images, check minimum dimensions
                if ($extension !== 'svg') {
                    $dimensions = $asset->dimensions();
                    if (! $dimensions || $dimensions[0] < 512 || $dimensions[1] < 512) {
                        if ($request->wantsJson()) {
                            return response()->json(['error' => 'Image must be at least 512x512 pixels'], 422);
                        }

                        return back()->withErrors(['source_asset' => 'Image must be at least 512x512 pixels']);
                    }
                }

                $sourcePath = $asset->resolvedPath();
            }

            $generationOptions = $this->buildGenerationOptions($validated);
            $generationOptions['source_type'] = $sourceType;

            if ($sourceType === 'emoji') {
                $generationOptions['source_emoji'] = $validated['source_emoji'];
            } elseif ($sourceType === 'text') {
                $generationOptions['source_text'] = $validated['source_text'];
            }

            $action(
                sourcePath: $sourcePath,
                options: $generationOptions
            );

            $this->saveSettingsToFile([
                ...$validated,
                'source_type' => $sourceType,
                'generated_at' => now()->toIso8601String(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Favicons generated successfully!']);
            }

            return back()->with('success', 'Favicons generated successfully!');
        } finally {
            // Clean up temp file
            if ($tempSvgPath && file_exists($tempSvgPath)) {
                unlink($tempSvgPath);
            }
        }
    }

    public function preview(): JsonResponse
    {
        return response()->json([
            'files' => $this->getGeneratedFilesMetadata(),
            'settings' => $this->getSettings(),
        ]);
    }

    public function saveSettings(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'source_asset' => 'nullable|string',
            ...self::VALIDATION_RULES,
        ]);

        // Get existing settings to preserve generated_at
        $existingSettings = $this->getSettings();

        $this->saveSettingsToFile([
            ...$validated,
            'generated_at' => $existingSettings['generated_at'] ?? null,
        ]);

        // Update manifest if it exists (so theme_color, app_name etc. are updated without regenerating images)
        $this->updateManifestIfExists($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Settings saved']);
        }

        return back()->with('success', 'Settings saved');
    }

    protected function updateManifestIfExists(array $settings): void
    {
        $outputPath = config('favicon-generator.output_path', public_path());
        $manifestPath = $outputPath.'/site.webmanifest';

        if (! file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (! $manifest) {
            return;
        }

        // Update manifest fields from settings
        $manifest['name'] = $settings['app_name'];
        $manifest['short_name'] = $settings['app_short_name'] ?? substr($settings['app_name'], 0, 12);
        $manifest['theme_color'] = $settings['theme_color'];
        $manifest['background_color'] = $settings['background_color'];

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function buildGenerationOptions(array $validated): array
    {
        return [
            'theme_color' => $validated['theme_color'],
            'background_color' => $validated['background_color'],
            'app_name' => $validated['app_name'],
            'app_short_name' => $validated['app_short_name'] ?? substr($validated['app_name'], 0, 12),
            'dark_mode_style' => $validated['dark_mode_style'] ?? 'invert',
            'dark_mode_color' => $validated['dark_mode_color'] ?? '#ffffff',
            'icon_color' => $validated['icon_color'] ?? '#000000',
            'dark_mode_icon_color' => $validated['dark_mode_icon_color'] ?? '#ffffff',
            'use_custom_icon_color' => $validated['use_custom_icon_color'] ?? false,
            'icon_padding' => $validated['icon_padding'] ?? 0,
            'png_background' => $validated['png_background'] ?? '#ffffff',
            'png_dark_background' => $validated['png_dark_background'] ?? '#1a1a1a',
            'png_transparent' => $validated['png_transparent'] ?? true,
            // Text mode options
            'text_font' => $validated['text_font'] ?? 'system-ui',
            'text_weight' => $validated['text_weight'] ?? 'bold',
            'text_color' => $validated['text_color'] ?? '#ffffff',
            'text_background_color' => $validated['text_background_color'] ?? $validated['theme_color'],
        ];
    }

    public function clear(Request $request): JsonResponse|RedirectResponse
    {
        $outputPath = config('favicon-generator.output_path', public_path());

        foreach (self::GENERATED_FILES as $file) {
            $path = $outputPath.'/'.$file;
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Generated files removed']);
        }

        return back()->with('success', 'Generated files removed');
    }

    public function getAssetUrl(Request $request): JsonResponse
    {
        $assetId = $request->get('asset');

        if (! $assetId) {
            return response()->json(['url' => null]);
        }

        $asset = Asset::find($assetId);

        if (! $asset) {
            return response()->json(['url' => null]);
        }

        return response()->json(['url' => $asset->url()]);
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

        $defaults = [
            'source_type' => 'asset',
            'source_asset' => null,
            'source_emoji' => '',
            'source_text' => '',
            'theme_color' => config('favicon-generator.default_theme_color', '#4f46e5'),
            'background_color' => config('favicon-generator.default_background_color', '#ffffff'),
            'app_name' => config('app.name', ''),
            'app_short_name' => '',
            'text_font' => 'system-ui',
            'text_weight' => 'bold',
            'text_color' => '#ffffff',
            'text_background_color' => config('favicon-generator.default_theme_color', '#4f46e5'),
            'generated_at' => null,
        ];

        if (! file_exists($path)) {
            return $defaults;
        }

        $settings = YAML::file($path)->parse();

        // Merge with defaults to ensure all keys exist
        return array_merge($defaults, $settings);
    }

    protected function saveSettingsToFile(array $settings): void
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
