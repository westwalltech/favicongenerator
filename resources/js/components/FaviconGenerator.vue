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
        <div v-if="errors && Object.keys(errors).length" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <ul class="list-disc list-inside text-red-800 dark:text-red-200">
                <li v-for="(error, key) in errors" :key="key">{{ error }}</li>
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
                            <Description class="mb-3">PNG or JPG, minimum 512x512px required</Description>

                            <!-- Asset Field using Statamic PublishContainer -->
                            <PublishContainer
                                v-if="isAssetsField"
                                :model-value="assetFieldValues"
                                @update:model-value="onAssetFieldUpdate"
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
            <PanelFooter>
                <Button
                    @click="generate"
                    :loading="generating"
                    :disabled="!form.source_asset"
                    variant="primary"
                    text="Generate Favicons"
                />
            </PanelFooter>
        </Panel>

        <!-- Preview Section -->
        <template v-if="hasFiles">
            <div class="border-t dark:border-dark-600 pt-6 mt-6">
                <Heading :level="2" class="mb-6">Preview</Heading>
            </div>

            <!-- Browser Tab Preview -->
            <Panel>
                <PanelHeader>
                    <Heading text="Browser Tab Preview" />
                </PanelHeader>
                <Card>
                    <BrowserTabPreview
                        :favicon-url="previewUrls.ico"
                        :site-name="form.app_name || 'My Website'"
                    />
                </Card>
            </Panel>

            <!-- SVG Dark Mode Preview -->
            <Panel>
                <PanelHeader class="flex items-center justify-between">
                    <Heading text="SVG Favicon (with Dark Mode)" />
                    <label class="flex items-center gap-2 cursor-pointer">
                        <span class="text-sm text-gray-600 dark:text-dark-200">Preview Dark Mode</span>
                        <Switch v-model="darkModePreview" />
                    </label>
                </PanelHeader>
                <Card>
                    <SvgDarkModePreview
                        :svg-url="previewUrls.svg"
                        :dark-mode="darkModePreview"
                    />
                </Card>
            </Panel>

            <!-- iOS Preview -->
            <Panel>
                <PanelHeader>
                    <Heading text="iOS Home Screen" />
                </PanelHeader>
                <Card>
                    <AppleTouchPreview
                        :icon-url="previewUrls.apple"
                        :app-name="form.app_short_name || form.app_name || 'App'"
                        :dark-mode-style="form.dark_mode_style"
                        :dark-mode-color="form.dark_mode_color"
                    />
                </Card>
            </Panel>

            <!-- Android/PWA Preview -->
            <Panel>
                <PanelHeader>
                    <Heading text="Android / PWA" />
                </PanelHeader>
                <Card>
                    <AndroidPwaPreview
                        :icon-192-url="previewUrls.icon192"
                        :icon-512-url="previewUrls.icon512"
                        :app-name="form.app_short_name || form.app_name || 'App'"
                        :theme-color="form.theme_color"
                        :background-color="form.background_color"
                    />
                </Card>
            </Panel>

            <!-- Generated Files Table -->
            <Panel>
                <PanelHeader>
                    <Heading text="Generated Files" />
                </PanelHeader>
                <Card>
                    <FileListTable :files="generatedFiles" />
                </Card>
            </Panel>

            <!-- HTML Output -->
            <Panel>
                <PanelHeader>
                    <Heading text="HTML Output" />
                </PanelHeader>
                <Card>
                    <HtmlOutputPreview :theme-color="form.theme_color" />
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
    Switch,
    Select,
    PublishContainer,
    PublishFieldsProvider,
    PublishFields,
} from '@statamic/cms/ui';
import BrowserTabPreview from './previews/BrowserTabPreview.vue';
import SvgDarkModePreview from './previews/SvgDarkModePreview.vue';
import AppleTouchPreview from './previews/AppleTouchPreview.vue';
import AndroidPwaPreview from './previews/AndroidPwaPreview.vue';
import FileListTable from './previews/FileListTable.vue';
import HtmlOutputPreview from './previews/HtmlOutputPreview.vue';

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
        Switch,
        Select,
        PublishContainer,
        PublishFieldsProvider,
        PublishFields,
        BrowserTabPreview,
        SvgDarkModePreview,
        AppleTouchPreview,
        AndroidPwaPreview,
        FileListTable,
        HtmlOutputPreview,
    },

    props: {
        settings: { type: Object, default: () => ({}) },
        generatedFiles: { type: Array, default: () => [] },
        assetContainers: { type: Array, default: () => [] },
        assetFieldConfig: { type: Object, default: null },
        assetFieldMeta: { type: Object, default: null },
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
            },
            darkModeOptions: DARK_MODE_OPTIONS,
            emptyBlueprint: EMPTY_BLUEPRINT,
            generating: false,
            clearing: false,
            darkModePreview: false,
            errors: {},
        };
    },

    computed: {
        hasFiles() {
            return this.generatedFiles.length > 0;
        },

        cacheKey() {
            return this.generatedFiles[0]?.modified || Date.now();
        },

        previewUrls() {
            if (!this.hasFiles) {
                return {};
            }
            const ts = this.cacheKey;
            return {
                ico: `/favicon.ico?v=${ts}`,
                svg: `/favicon.svg?v=${ts}`,
                apple: `/apple-touch-icon.png?v=${ts}`,
                icon192: `/icon-192.png?v=${ts}`,
                icon512: `/icon-512.png?v=${ts}`,
            };
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
    },

    methods: {
        onAssetFieldUpdate(values) {
            const assets = values.source_asset || [];
            this.form.source_asset = assets[0] || null;
        },

        async generate() {
            this.generating = true;
            this.errors = {};

            try {
                await this.$axios.post('/cp/westwalltech/favicon-generator/generate', this.form);
                window.location.reload();
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors || {};
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
                await this.$axios.post('/cp/westwalltech/favicon-generator/clear');
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
