<template>
    <Head title="Favicon Generator" />

    <div class="p-6 space-y-6 max-w-6xl">
        <!-- Header -->
        <Header title="Favicon Generator" icon="ai-sparks">
            <template #actions>
                <Button
                    v-if="hasFiles"
                    @click="clearFiles"
                    :loading="clearing"
                    variant="danger"
                    text="Remove All Files"
                />
            </template>
        </Header>

        <!-- Flash Messages -->
        <div v-if="$page.props.flash?.success" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <p class="text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
        </div>

        <!-- Form Errors -->
        <div v-if="hasErrors" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <ul class="list-disc list-inside text-red-800 dark:text-red-200">
                <li v-for="message in flattenedErrors" :key="message">{{ message }}</li>
            </ul>
        </div>

        <!-- Settings Panel -->
        <Panel>
            <PanelHeader>
                <Heading text="Settings" />
            </PanelHeader>
            <Card>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Source Image (left column) -->
                    <div class="space-y-4">
                        <div>
                            <Label>Source Image</Label>
                            <Description class="mb-3">
                                {{ canProcessSvg ? 'SVG, PNG, or JPG' : 'PNG or JPG' }}, minimum 512x512px for raster images
                            </Description>

                            <!-- Asset Field using Statamic PublishContainer -->
                            <PublishContainer
                                v-if="isAssetsField"
                                :model-value="assetFieldValues"
                                @update:model-value="onAssetFieldUpdate"
                                @update:meta="onAssetMetaUpdate"
                                :meta="assetFieldMetaData"
                                :blueprint="emptyBlueprint"
                                :track-dirty-state="false"
                            >
                                <PublishFieldsProvider :fields="assetFields">
                                    <PublishFields />
                                </PublishFieldsProvider>
                            </PublishContainer>

                            <!-- Error message if container not configured -->
                            <div
                                v-else-if="assetFieldConfig?.type === 'html'"
                                v-html="assetFieldConfig.html"
                                class="mt-2"
                            />
                        </div>
                    </div>

                    <!-- App Details (right column) -->
                    <div class="space-y-4">
                        <div>
                            <Label>App Name</Label>
                            <Input
                                v-model="form.app_name"
                                type="text"
                                placeholder="My Awesome Website"
                            />
                        </div>

                        <div>
                            <Label>Short Name</Label>
                            <Input
                                v-model="form.app_short_name"
                                type="text"
                                placeholder="MyApp"
                                maxlength="12"
                            />
                            <Description>Max 12 characters, shown under home screen icon</Description>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Theme Color</Label>
                                <div class="flex gap-2">
                                    <input
                                        type="color"
                                        v-model="form.theme_color"
                                        class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                    />
                                    <Input
                                        v-model="form.theme_color"
                                        type="text"
                                        class="flex-1 font-mono text-sm"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label>Background Color</Label>
                                <div class="flex gap-2">
                                    <input
                                        type="color"
                                        v-model="form.background_color"
                                        class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                    />
                                    <Input
                                        v-model="form.background_color"
                                        type="text"
                                        class="flex-1 font-mono text-sm"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Dark Mode Settings -->
                        <div class="pt-4 border-t dark:border-dark-600">
                            <Label>SVG Dark Mode Behavior</Label>
                            <Description class="mb-2">How should the SVG favicon appear in dark mode?</Description>
                            <Select
                                v-model="form.dark_mode_style"
                                :options="darkModeOptions"
                                class="w-full"
                            />
                        </div>

                        <div v-if="form.dark_mode_style === 'custom'" class="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Dark Mode Icon Color</Label>
                                <div class="flex gap-2">
                                    <input
                                        type="color"
                                        v-model="form.dark_mode_color"
                                        class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                    />
                                    <Input
                                        v-model="form.dark_mode_color"
                                        type="text"
                                        class="flex-1 font-mono text-sm"
                                    />
                                </div>
                                <Description>Works best with monochrome icons</Description>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Icon Customization Section -->
            <Card class="mt-4">
                <div class="flex items-center justify-between mb-4">
                    <Heading text="Icon Customization" :level="3" />
                </div>

                <!-- Live Preview -->
                <div v-if="sourceAssetUrl" class="mb-6 p-4 bg-gray-50 dark:bg-dark-700 rounded-lg">
                    <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">Live Preview</p>
                    <IconCustomizationPreview
                        :svg-url="sourceAssetUrl"
                        :icon-color="form.icon_color"
                        :dark-mode-icon-color="form.dark_mode_icon_color"
                        :use-custom-icon-color="form.use_custom_icon_color"
                        :icon-padding="form.icon_padding"
                        :png-background="form.png_background"
                        :png-dark-background="form.png_dark_background"
                        :png-transparent="form.png_transparent"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Icon Color -->
                    <div>
                        <Label>{{ form.use_custom_icon_color ? 'Icon Color (Light Mode)' : 'Icon Color' }}</Label>
                        <Description class="mb-2">Override fill color for monochrome SVGs</Description>
                        <div class="flex gap-2">
                            <input
                                type="color"
                                v-model="form.icon_color"
                                class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                :disabled="!form.use_custom_icon_color"
                            />
                            <Input
                                v-model="form.icon_color"
                                type="text"
                                class="flex-1 font-mono text-sm"
                                :disabled="!form.use_custom_icon_color"
                            />
                        </div>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.use_custom_icon_color"
                                class="rounded border-gray-300 dark:border-dark-600"
                            />
                            <span class="text-sm text-gray-600 dark:text-dark-200">Use custom color</span>
                        </label>
                        <!-- Dark Mode Icon Color -->
                        <div v-if="form.use_custom_icon_color" class="mt-4 pt-4 border-t dark:border-dark-600">
                            <Label>Icon Color (Dark Mode)</Label>
                            <Description class="mb-2">Color used in SVG favicon for dark mode</Description>
                            <div class="flex gap-2">
                                <input
                                    type="color"
                                    v-model="form.dark_mode_icon_color"
                                    class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                />
                                <Input
                                    v-model="form.dark_mode_icon_color"
                                    type="text"
                                    class="flex-1 font-mono text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Padding -->
                    <div>
                        <Label>Icon Padding</Label>
                        <Description class="mb-2">Space around the icon ({{ form.icon_padding }}%)</Description>
                        <div class="flex items-center gap-3">
                            <input
                                type="range"
                                v-model.number="form.icon_padding"
                                min="0"
                                max="40"
                                step="5"
                                class="flex-1 h-2 bg-gray-200 dark:bg-dark-600 rounded-lg appearance-none cursor-pointer"
                            />
                            <Input
                                v-model.number="form.icon_padding"
                                type="number"
                                min="0"
                                max="40"
                                class="w-20 font-mono text-sm text-center"
                            />
                        </div>
                    </div>

                    <!-- PNG Background -->
                    <div>
                        <Label>{{ form.use_custom_icon_color && !form.png_transparent ? 'PNG Background (Light)' : 'PNG Background' }}</Label>
                        <Description class="mb-2">Background color for PNG icons</Description>
                        <div class="flex gap-2">
                            <input
                                type="color"
                                v-model="form.png_background"
                                class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                :disabled="form.png_transparent"
                            />
                            <Input
                                v-model="form.png_background"
                                type="text"
                                class="flex-1 font-mono text-sm"
                                :disabled="form.png_transparent"
                            />
                        </div>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="form.png_transparent"
                                class="rounded border-gray-300 dark:border-dark-600"
                            />
                            <span class="text-sm text-gray-600 dark:text-dark-200">Transparent background</span>
                        </label>
                        <!-- Dark Mode PNG Background -->
                        <div v-if="form.use_custom_icon_color && !form.png_transparent" class="mt-4 pt-4 border-t dark:border-dark-600">
                            <Label>PNG Background (Dark)</Label>
                            <Description class="mb-2">Background for dark mode preview</Description>
                            <div class="flex gap-2">
                                <input
                                    type="color"
                                    v-model="form.png_dark_background"
                                    class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                />
                                <Input
                                    v-model="form.png_dark_background"
                                    type="text"
                                    class="flex-1 font-mono text-sm"
                                />
                            </div>
                            <p class="text-xs text-gray-400 dark:text-dark-400 mt-2">
                                Note: PNGs are static files. Dark mode preview shows how icons appear on dark backgrounds.
                            </p>
                        </div>
                    </div>
                </div>
            </Card>
            <PanelFooter class="flex gap-2">
                <Button
                    @click="save"
                    :loading="saving"
                    text="Save Settings"
                />
                <Button
                    @click="generate"
                    :loading="generating"
                    :disabled="!form.source_asset"
                    variant="primary"
                    text="Generate Favicons"
                />
            </PanelFooter>
        </Panel>

        <!-- Generated Files Section -->
        <template v-if="hasFiles">
            <!-- Generated Files Table -->
            <Panel>
                <PanelHeader>
                    <Heading text="Generated Files" />
                </PanelHeader>
                <Card inset class="overflow-hidden">
                    <FileListTable :files="generatedFiles" />
                </Card>
            </Panel>

            <!-- HTML Output -->
            <Panel>
                <PanelHeader>
                    <Heading text="HTML Output" />
                </PanelHeader>
                <Card inset>
                    <HtmlOutputPreview :theme-color="form.theme_color" :app-name="form.app_name" :generated-at="settings.generated_at" />
                </Card>
            </Panel>
        </template>
    </div>
</template>

<script>
import { Head } from '@statamic/cms/inertia';
import {
    Header,
    Heading,
    Description,
    Label,
    Panel,
    PanelHeader,
    PanelFooter,
    Card,
    Button,
    Input,
    Select,
    PublishContainer,
    PublishFieldsProvider,
    PublishFields,
} from '@statamic/cms/ui';
import FileListTable from './previews/FileListTable.vue';
import HtmlOutputPreview from './previews/HtmlOutputPreview.vue';
import IconCustomizationPreview from './previews/IconCustomizationPreview.vue';

const DARK_MODE_OPTIONS = [
    { value: 'invert', label: 'Invert colors' },
    { value: 'lighten', label: 'Lighten (increase brightness)' },
    { value: 'custom', label: 'Custom color (monochrome icons)' },
    { value: 'none', label: 'No change' },
];

const EMPTY_BLUEPRINT = { tabs: [] };

export default {
    components: {
        Head,
        Header,
        Heading,
        Description,
        Label,
        Panel,
        PanelHeader,
        PanelFooter,
        Card,
        Button,
        Input,
        Select,
        PublishContainer,
        PublishFieldsProvider,
        PublishFields,
        FileListTable,
        HtmlOutputPreview,
        IconCustomizationPreview,
    },

    props: {
        settings: { type: Object, default: () => ({}) },
        generatedFiles: { type: Array, default: () => [] },
        assetContainers: { type: Array, default: () => [] },
        assetFieldConfig: { type: Object, default: null },
        assetFieldMeta: { type: Object, default: null },
        canProcessSvg: { type: Boolean, default: false },
    },

    data() {
        return {
            form: {
                source_asset: this.settings.source_asset ?? null,
                theme_color: this.settings.theme_color ?? '#4f46e5',
                background_color: this.settings.background_color ?? '#ffffff',
                app_name: this.settings.app_name ?? '',
                app_short_name: this.settings.app_short_name ?? '',
                dark_mode_style: this.settings.dark_mode_style ?? 'invert',
                dark_mode_color: this.settings.dark_mode_color ?? '#ffffff',
                // Icon customization
                icon_color: this.settings.icon_color ?? '#000000',
                dark_mode_icon_color: this.settings.dark_mode_icon_color ?? '#ffffff',
                use_custom_icon_color: this.settings.use_custom_icon_color ?? false,
                icon_padding: this.settings.icon_padding ?? 0,
                png_background: this.settings.png_background ?? '#ffffff',
                png_dark_background: this.settings.png_dark_background ?? '#1a1a1a',
                png_transparent: this.settings.png_transparent ?? true,
            },
            darkModeOptions: DARK_MODE_OPTIONS,
            emptyBlueprint: EMPTY_BLUEPRINT,
            generating: false,
            saving: false,
            clearing: false,
            errors: {},
            selectedAssetUrl: null,
        };
    },

    watch: {
        'form.source_asset': {
            immediate: true,
            async handler(assetId, oldAssetId) {
                if (!assetId) {
                    this.selectedAssetUrl = null;
                    return;
                }
                // On initial load, check if we have URL from meta
                if (!oldAssetId && this.assetFieldMeta?.data?.[0]?.url) {
                    this.selectedAssetUrl = this.assetFieldMeta.data[0].url;
                    return;
                }
                // Fetch asset URL from our endpoint
                await this.fetchAssetUrl(assetId);
            },
        },
    },

    computed: {
        hasFiles() {
            return this.generatedFiles.length > 0;
        },

        hasErrors() {
            return this.errors && (
                typeof this.errors === 'string' ||
                Object.keys(this.errors).length > 0
            );
        },

        flattenedErrors() {
            if (!this.errors) return [];
            // Handle string error (e.g., { error: "message" })
            if (typeof this.errors === 'string') {
                return [this.errors];
            }
            // Handle Laravel validation errors (e.g., { field: ["message"] })
            const messages = [];
            for (const [field, fieldErrors] of Object.entries(this.errors)) {
                if (Array.isArray(fieldErrors)) {
                    messages.push(...fieldErrors);
                } else if (typeof fieldErrors === 'string') {
                    messages.push(fieldErrors);
                }
            }
            return messages;
        },

        cacheKey() {
            return this.generatedFiles[0]?.modified || Date.now();
        },

        isAssetsField() {
            return this.assetFieldConfig?.type === 'assets';
        },

        assetFields() {
            if (!this.isAssetsField) {
                return [];
            }
            return [{
                handle: 'source_asset',
                type: 'assets',
                display: 'Source Image',
                hide_display: true,
                ...this.assetFieldConfig,
            }];
        },

        assetFieldValues() {
            const value = this.form.source_asset;
            return { source_asset: value ? [value] : [] };
        },

        assetFieldMetaData() {
            return { source_asset: this.assetFieldMeta || {} };
        },

        sourceAssetUrl() {
            // Use the selected asset URL if available
            if (this.selectedAssetUrl) {
                return this.selectedAssetUrl;
            }
            // Fallback: get URL from asset meta
            if (this.assetFieldMeta?.data?.[0]?.url) {
                return this.assetFieldMeta.data[0].url;
            }
            // If we have generated files, use the favicon.svg as preview source
            if (this.hasFiles) {
                return `/favicon.svg?v=${this.cacheKey}`;
            }
            return null;
        },
    },

    methods: {
        onAssetFieldUpdate(values) {
            const assets = values.source_asset || [];
            this.form.source_asset = assets[0] || null;
        },

        onAssetMetaUpdate(meta) {
            // Extract URL from the updated meta data
            const assetData = meta?.source_asset?.data?.[0];
            if (assetData?.url) {
                this.selectedAssetUrl = assetData.url;
            }
        },

        async fetchAssetUrl(assetId) {
            try {
                const response = await this.$axios.get('/cp/favicon-generator/asset-url', {
                    params: { asset: assetId }
                });
                this.selectedAssetUrl = response.data.url;
            } catch (error) {
                console.error('Failed to fetch asset URL:', error);
                this.selectedAssetUrl = null;
            }
        },

        async save() {
            this.saving = true;
            this.errors = {};

            try {
                await this.$axios.post('/cp/favicon-generator/save', this.form, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                this.$toast.success('Settings saved');
            } catch (error) {
                if (error.response?.status === 422) {
                    const data = error.response.data;
                    this.errors = data.errors || (data.error ? { error: data.error } : {});
                } else {
                    this.$toast.error('Failed to save settings');
                }
            } finally {
                this.saving = false;
            }
        },

        async generate() {
            this.generating = true;
            this.errors = {};

            try {
                await this.$axios.post('/cp/favicon-generator/generate', this.form, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                window.location.reload();
            } catch (error) {
                if (error.response?.status === 422) {
                    const data = error.response.data;
                    // Handle both { errors: {...} } and { error: "string" } formats
                    this.errors = data.errors || (data.error ? { error: data.error } : {});
                } else {
                    this.$toast.error('Failed to generate favicons');
                }
            } finally {
                this.generating = false;
            }
        },

        async clearFiles() {
            if (!confirm('Are you sure you want to remove all generated favicon files?')) {
                return;
            }

            this.clearing = true;

            try {
                await this.$axios.post('/cp/favicon-generator/clear');
                window.location.reload();
            } catch (error) {
                this.$toast.error('Failed to remove files');
            } finally {
                this.clearing = false;
            }
        },
    },
};
</script>
