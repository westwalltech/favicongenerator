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
                    <!-- Source Selection (left column) -->
                    <div class="space-y-4">
                        <div>
                            <Label>Source Type</Label>
                            <div class="flex gap-1 p-1 bg-gray-100 dark:bg-dark-700 rounded-lg mb-4">
                                <button
                                    v-for="option in sourceTypeOptions"
                                    :key="option.value"
                                    type="button"
                                    @click="form.source_type = option.value"
                                    :class="[
                                        'flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors',
                                        form.source_type === option.value
                                            ? 'bg-white dark:bg-dark-600 shadow-sm text-gray-900 dark:text-white'
                                            : 'text-gray-600 dark:text-dark-300 hover:text-gray-900 dark:hover:text-white'
                                    ]"
                                >
                                    {{ option.label }}
                                </button>
                            </div>

                            <!-- Asset Source -->
                            <div v-if="form.source_type === 'asset'">
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

                            <!-- Emoji Source -->
                            <div v-else-if="form.source_type === 'emoji'" class="space-y-4">
                                <Description class="mb-3">
                                    Select or enter an emoji to use as your favicon
                                </Description>
                                <div>
                                    <Label>Emoji</Label>
                                    <div class="flex gap-2 items-center">
                                        <Input
                                            v-model="form.source_emoji"
                                            type="text"
                                            placeholder="Select below or type"
                                            class="text-2xl text-center flex-1"
                                            maxlength="8"
                                        />
                                        <button
                                            v-if="form.source_emoji"
                                            type="button"
                                            @click="form.source_emoji = ''"
                                            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            title="Clear"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Emoji Picker -->
                                <div class="border dark:border-dark-600 rounded-lg p-3 bg-white dark:bg-dark-800">
                                    <div class="flex gap-2 mb-3 border-b dark:border-dark-600 pb-2">
                                        <button
                                            v-for="category in emojiCategories"
                                            :key="category.name"
                                            type="button"
                                            @click="selectedEmojiCategory = category.name"
                                            :class="[
                                                'px-2 py-1 text-lg rounded transition-colors',
                                                selectedEmojiCategory === category.name
                                                    ? 'bg-gray-100 dark:bg-dark-600'
                                                    : 'hover:bg-gray-50 dark:hover:bg-dark-700'
                                            ]"
                                            :title="category.label"
                                        >
                                            {{ category.icon }}
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-8 gap-1">
                                        <button
                                            v-for="emoji in currentCategoryEmojis"
                                            :key="emoji"
                                            type="button"
                                            @click="form.source_emoji = emoji"
                                            :class="[
                                                'p-2 text-2xl rounded hover:bg-gray-100 dark:hover:bg-dark-600 transition-colors',
                                                form.source_emoji === emoji ? 'bg-blue-100 dark:bg-blue-900/30 ring-2 ring-blue-500' : ''
                                            ]"
                                        >
                                            {{ emoji }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Text Source -->
                            <div v-else-if="form.source_type === 'text'" class="space-y-4">
                                <Description class="mb-3">
                                    Enter 1-4 characters to use as your favicon
                                </Description>
                                <div>
                                    <Label>Characters</Label>
                                    <Input
                                        v-model="form.source_text"
                                        type="text"
                                        placeholder="SA"
                                        class="text-xl text-center font-bold uppercase"
                                        maxlength="4"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label>Font</Label>
                                        <Select
                                            v-model="form.text_font"
                                            :options="fontOptions"
                                            class="w-full"
                                        />
                                    </div>
                                    <div>
                                        <Label>Weight</Label>
                                        <Select
                                            v-model="form.text_weight"
                                            :options="fontWeightOptions"
                                            class="w-full"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label>Background Color</Label>
                                        <div class="flex gap-2">
                                            <input
                                                type="color"
                                                v-model="form.text_background_color"
                                                class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                            />
                                            <Input
                                                v-model="form.text_background_color"
                                                type="text"
                                                class="flex-1 font-mono text-sm"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <Label>Text Color</Label>
                                        <div class="flex gap-2">
                                            <input
                                                type="color"
                                                v-model="form.text_color"
                                                class="w-12 h-10 rounded cursor-pointer border dark:border-dark-600"
                                            />
                                            <Input
                                                v-model="form.text_color"
                                                type="text"
                                                class="flex-1 font-mono text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
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

                        <!-- Dark Mode Settings (only for asset source type) -->
                        <div v-if="form.source_type === 'asset'" class="pt-4 border-t dark:border-dark-600">
                            <Label>SVG Dark Mode Behavior</Label>
                            <Description class="mb-2">How should the SVG favicon appear in dark mode?</Description>
                            <Select
                                v-model="form.dark_mode_style"
                                :options="darkModeOptions"
                                class="w-full"
                            />
                        </div>

                        <div v-if="form.source_type === 'asset' && form.dark_mode_style === 'custom'" class="grid grid-cols-2 gap-4">
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

                <!-- Live Preview for Asset source type -->
                <div v-if="form.source_type === 'asset' && sourceAssetUrl" class="mb-6 p-4 bg-gray-50 dark:bg-dark-700 rounded-lg">
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

                <!-- Live Preview for Emoji source type -->
                <div v-if="form.source_type === 'emoji' && form.source_emoji" class="mb-6 p-4 bg-gray-50 dark:bg-dark-700 rounded-lg">
                    <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">Live Preview (Twemoji)</p>
                    <div class="flex items-end gap-6">
                        <!-- Large preview -->
                        <div class="text-center">
                            <div
                                class="rounded-xl flex items-center justify-center border dark:border-dark-500 overflow-hidden shrink-0"
                                :style="{
                                    width: '128px',
                                    height: '128px',
                                    backgroundColor: form.png_transparent ? 'transparent' : form.png_background
                                }"
                            >
                                <img
                                    v-if="twemojiUrl"
                                    :src="twemojiUrl"
                                    class="transition-all duration-200"
                                    :style="{ width: `${Math.round(128 * (1 - form.icon_padding / 50) * 0.8)}px`, height: `${Math.round(128 * (1 - form.icon_padding / 50) * 0.8)}px` }"
                                    :alt="form.source_emoji"
                                />
                                <span v-else class="text-6xl">{{ form.source_emoji }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-dark-400 mt-1">512x512</p>
                        </div>
                        <!-- Medium preview -->
                        <div class="text-center">
                            <div
                                class="rounded-lg flex items-center justify-center border dark:border-dark-500 overflow-hidden shrink-0"
                                :style="{
                                    width: '48px',
                                    height: '48px',
                                    backgroundColor: form.png_transparent ? 'transparent' : form.png_background
                                }"
                            >
                                <img
                                    v-if="twemojiUrl"
                                    :src="twemojiUrl"
                                    class="transition-all duration-200"
                                    :style="{ width: `${Math.round(48 * (1 - form.icon_padding / 50) * 0.8)}px`, height: `${Math.round(48 * (1 - form.icon_padding / 50) * 0.8)}px` }"
                                    :alt="form.source_emoji"
                                />
                                <span v-else class="text-xl">{{ form.source_emoji }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-dark-400 mt-1">192x192</p>
                        </div>
                        <!-- Small preview -->
                        <div class="text-center">
                            <div
                                class="rounded flex items-center justify-center border dark:border-dark-500 overflow-hidden shrink-0"
                                :style="{
                                    width: '32px',
                                    height: '32px',
                                    backgroundColor: form.png_transparent ? 'transparent' : form.png_background
                                }"
                            >
                                <img
                                    v-if="twemojiUrl"
                                    :src="twemojiUrl"
                                    class="transition-all duration-200"
                                    :style="{ width: `${Math.round(32 * (1 - form.icon_padding / 50) * 0.8)}px`, height: `${Math.round(32 * (1 - form.icon_padding / 50) * 0.8)}px` }"
                                    :alt="form.source_emoji"
                                />
                                <span v-else class="text-sm">{{ form.source_emoji }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-dark-400 mt-1">32x32</p>
                        </div>
                    </div>
                </div>

                <!-- Live Preview for Text source type -->
                <div v-if="form.source_type === 'text' && form.source_text" class="mb-6 p-4 bg-gray-50 dark:bg-dark-700 rounded-lg">
                    <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">Live Preview</p>
                    <div class="flex items-end gap-6">
                        <!-- Large preview -->
                        <div class="text-center">
                            <div
                                class="rounded-xl flex items-center justify-center overflow-hidden shrink-0"
                                :style="{
                                    width: '128px',
                                    height: '128px',
                                    backgroundColor: form.text_background_color
                                }"
                            >
                                <span
                                    class="transition-all duration-200"
                                    :style="{
                                        color: form.text_color,
                                        fontFamily: getFontFamilyCss(form.text_font),
                                        fontWeight: form.text_weight,
                                        fontSize: getTextPreviewFontSize(form.source_text, 128, form.icon_padding)
                                    }"
                                >{{ form.source_text }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-dark-400 mt-1">512x512</p>
                        </div>
                        <!-- Medium preview -->
                        <div class="text-center">
                            <div
                                class="rounded-lg flex items-center justify-center overflow-hidden shrink-0"
                                :style="{
                                    width: '48px',
                                    height: '48px',
                                    backgroundColor: form.text_background_color
                                }"
                            >
                                <span
                                    class="transition-all duration-200"
                                    :style="{
                                        color: form.text_color,
                                        fontFamily: getFontFamilyCss(form.text_font),
                                        fontWeight: form.text_weight,
                                        fontSize: getTextPreviewFontSize(form.source_text, 48, form.icon_padding)
                                    }"
                                >{{ form.source_text }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-dark-400 mt-1">192x192</p>
                        </div>
                        <!-- Small preview -->
                        <div class="text-center">
                            <div
                                class="rounded flex items-center justify-center overflow-hidden shrink-0"
                                :style="{
                                    width: '32px',
                                    height: '32px',
                                    backgroundColor: form.text_background_color
                                }"
                            >
                                <span
                                    class="transition-all duration-200"
                                    :style="{
                                        color: form.text_color,
                                        fontFamily: getFontFamilyCss(form.text_font),
                                        fontWeight: form.text_weight,
                                        fontSize: getTextPreviewFontSize(form.source_text, 32, form.icon_padding)
                                    }"
                                >{{ form.source_text }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-dark-400 mt-1">32x32</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Icon Color (only for asset source type) -->
                    <div v-if="form.source_type === 'asset'">
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

                    <!-- Background Color -->
                    <div>
                        <Label>Background Color</Label>
                        <Description class="mb-2">Background color for generated icons</Description>
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
                    :disabled="!canGenerate"
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

const SOURCE_TYPE_OPTIONS = [
    { value: 'asset', label: 'Image' },
    { value: 'emoji', label: 'Emoji' },
    { value: 'text', label: 'Text' },
];

const FONT_OPTIONS = [
    { value: 'system-ui', label: 'System UI' },
    { value: 'sans-serif', label: 'Sans Serif' },
    { value: 'serif', label: 'Serif' },
    { value: 'monospace', label: 'Monospace' },
];

const FONT_WEIGHT_OPTIONS = [
    { value: 'normal', label: 'Normal' },
    { value: 'medium', label: 'Medium' },
    { value: 'bold', label: 'Bold' },
];

const EMOJI_CATEGORIES = [
    {
        name: 'popular',
        label: 'Popular',
        icon: '⭐',
        emojis: ['🔥', '✨', '💡', '🚀', '⚡', '💎', '🎯', '✅', '❤️', '💜', '💙', '💚', '🧡', '💛', '🖤', '🤍', '📱', '💻', '🌐', '📧', '📝', '📊', '🎨', '🎵', '📸', '🎬', '🏠', '🏢', '🛒', '💰', '🔒', '🔑'],
    },
    {
        name: 'smileys',
        label: 'Smileys',
        icon: '😀',
        emojis: ['😀', '😃', '😄', '😁', '😊', '🥰', '😍', '🤩', '😎', '🤓', '🧐', '🤔', '😏', '😌', '🥳', '🤗', '🙂', '🙃', '😇', '😈', '👻', '🤖', '👽', '💀', '🎃', '😺', '😸', '😹', '😻', '🙀', '😿', '😾'],
    },
    {
        name: 'animals',
        label: 'Animals',
        icon: '🐶',
        emojis: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🦅', '🦆', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🐛', '🦋', '🐌', '🐞', '🐢'],
    },
    {
        name: 'food',
        label: 'Food',
        icon: '🍕',
        emojis: ['🍎', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🥑', '🌶️', '🌽', '🥕', '🥦', '🧄', '🧅', '🍄', '🥜', '🍞', '🥐', '🧀', '🍕', '🍔', '🍟', '🌭', '🍿', '☕'],
    },
    {
        name: 'activities',
        label: 'Activities',
        icon: '⚽',
        emojis: ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🏓', '🏸', '🏒', '🥊', '🎿', '⛷️', '🏂', '🏋️', '🤸', '🧘', '🎮', '🎲', '🧩', '🎭', '🎨', '🎤', '🎧', '🎸', '🎹', '🥁', '🎺', '🎻'],
    },
    {
        name: 'travel',
        label: 'Travel',
        icon: '✈️',
        emojis: ['🚗', '🚕', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🏍️', '🛵', '🚲', '✈️', '🚀', '🛸', '🚁', '⛵', '🚢', '🗼', '🗽', '🏰', '🏯', '🏟️', '🎡', '🎢', '🎠', '⛱️', '🏖️'],
    },
    {
        name: 'objects',
        label: 'Objects',
        icon: '💼',
        emojis: ['⌚', '📱', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '💾', '💿', '📷', '📹', '🎥', '📞', '☎️', '📺', '📻', '🎙️', '⏰', '🔋', '💡', '🔦', '🕯️', '💵', '💳', '💎', '⚖️', '🔧', '🔨', '⚙️', '🔩', '📎', '✂️'],
    },
    {
        name: 'symbols',
        label: 'Symbols',
        icon: '💯',
        emojis: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☯️', '✡️', '🔯', '♈', '♉', '♊', '♋', '♌', '♍', '♎'],
    },
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
                source_type: this.settings.source_type ?? 'asset',
                source_asset: this.settings.source_asset ?? null,
                source_emoji: this.settings.source_emoji ?? '',
                source_text: this.settings.source_text ?? '',
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
                // Text mode options
                text_font: this.settings.text_font ?? 'system-ui',
                text_weight: this.settings.text_weight ?? 'bold',
                text_color: this.settings.text_color ?? '#ffffff',
                text_background_color: this.settings.text_background_color ?? this.settings.theme_color ?? '#4f46e5',
            },
            darkModeOptions: DARK_MODE_OPTIONS,
            sourceTypeOptions: SOURCE_TYPE_OPTIONS,
            fontOptions: FONT_OPTIONS,
            fontWeightOptions: FONT_WEIGHT_OPTIONS,
            emojiCategories: EMOJI_CATEGORIES,
            selectedEmojiCategory: 'popular',
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

        canGenerate() {
            const sourceType = this.form.source_type || 'asset';
            switch (sourceType) {
                case 'emoji':
                    return Boolean(this.form.source_emoji && String(this.form.source_emoji).trim().length > 0);
                case 'text':
                    return Boolean(this.form.source_text && String(this.form.source_text).trim().length > 0);
                case 'asset':
                default:
                    return Boolean(this.form.source_asset);
            }
        },

        currentCategoryEmojis() {
            const category = this.emojiCategories.find(c => c.name === this.selectedEmojiCategory);
            return category ? category.emojis : [];
        },

        twemojiUrl() {
            if (!this.form.source_emoji) return null;
            const codepoints = this.getEmojiCodepoints(this.form.source_emoji);
            if (!codepoints) return null;
            return `https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg/${codepoints}.svg`;
        },
    },

    methods: {
        getEmojiCodepoints(emoji) {
            if (!emoji) return null;
            const codepoints = [];
            for (const char of emoji) {
                const code = char.codePointAt(0);
                // Skip variation selectors (FE0E, FE0F)
                if (code === 0xFE0E || code === 0xFE0F) continue;
                codepoints.push(code.toString(16));
            }
            return codepoints.length > 0 ? codepoints.join('-') : null;
        },

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

        getFontFamilyCss(fontKey) {
            const fonts = {
                'system-ui': 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                'sans-serif': 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                'serif': 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif',
                'monospace': 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
            };
            return fonts[fontKey] || fonts['system-ui'];
        },

        getTextPreviewFontSize(text, containerSize, padding) {
            const charCount = text ? text.length : 0;
            const paddingFactor = 1 - (padding / 100) * 2;
            const effectiveSize = containerSize * paddingFactor;

            // Base multipliers for different character counts
            const multipliers = { 1: 0.55, 2: 0.42, 3: 0.32, 4: 0.25 };
            const multiplier = multipliers[Math.min(charCount, 4)] || multipliers[4];

            return `${Math.round(effectiveSize * multiplier)}px`;
        },
    },
};
</script>
