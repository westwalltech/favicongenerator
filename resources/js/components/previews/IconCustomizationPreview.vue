<template>
    <div class="p-3">
        <!-- Compact Preview Grid -->
        <div class="flex flex-wrap items-end justify-center gap-4">
            <!-- Light Mode Previews -->
            <template v-for="size in previewSizes" :key="size.label">
                <div class="text-center">
                    <div
                        class="relative mx-auto border border-gray-200 dark:border-dark-600 overflow-hidden"
                        :class="size.rounded"
                        :style="{ width: size.width, height: size.height, background: lightBackground }"
                    >
                        <div class="absolute inset-0 flex items-center justify-center" :style="paddingStyle">
                            <div class="w-full h-full" v-html="processedSvg"></div>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-dark-400 mt-1">{{ size.label }}</p>
                </div>
            </template>

            <!-- Dark Mode Previews (when custom colors enabled) -->
            <template v-if="useCustomIconColor && !pngTransparent">
                <div class="w-px h-12 bg-gray-200 dark:bg-dark-600 mx-2"></div>

                <template v-for="size in previewSizes" :key="'dark-' + size.label">
                    <div class="text-center">
                        <div
                            class="relative mx-auto border border-dark-600 overflow-hidden"
                            :class="size.rounded"
                            :style="{ width: size.width, height: size.height, background: darkBackground }"
                        >
                            <div class="absolute inset-0 flex items-center justify-center" :style="paddingStyle">
                                <div class="w-full h-full" v-html="processedDarkSvg"></div>
                            </div>
                        </div>
                        <p class="text-[10px] text-gray-500 dark:text-dark-400 mt-1">{{ size.label }}</p>
                    </div>
                </template>
            </template>

            <!-- SVG Light/Dark (when custom colors enabled) -->
            <template v-if="useCustomIconColor">
                <div class="w-px h-12 bg-gray-200 dark:bg-dark-600 mx-2"></div>

                <div class="text-center">
                    <div class="flex gap-1">
                        <div
                            class="relative border border-gray-200 dark:border-dark-600 rounded overflow-hidden flex items-center justify-center"
                            :style="{ width: '40px', height: '40px', background: lightBackground }"
                        >
                            <div class="w-6 h-6" v-html="processedSvg"></div>
                        </div>
                        <div
                            class="relative border border-dark-600 rounded overflow-hidden flex items-center justify-center"
                            :style="{ width: '40px', height: '40px', background: darkBackground }"
                        >
                            <div class="w-6 h-6" v-html="processedDarkSvg"></div>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-dark-400 mt-1">SVG</p>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
const PREVIEW_SIZES = [
    { label: '512', width: '64px', height: '64px', rounded: 'rounded-lg' },
    { label: '192', width: '48px', height: '48px', rounded: 'rounded-lg' },
    { label: '96', width: '32px', height: '32px', rounded: 'rounded-lg' },
    { label: '32', width: '20px', height: '20px', rounded: 'rounded' },
];

export default {
    props: {
        svgUrl: { type: String, default: null },
        iconColor: { type: String, default: '#000000' },
        darkModeIconColor: { type: String, default: '#ffffff' },
        useCustomIconColor: { type: Boolean, default: false },
        iconPadding: { type: Number, default: 0 },
        pngBackground: { type: String, default: '#ffffff' },
        pngDarkBackground: { type: String, default: '#1a1a1a' },
        pngTransparent: { type: Boolean, default: true },
    },

    data() {
        return {
            svgContent: '',
            previewSizes: PREVIEW_SIZES,
        };
    },

    computed: {
        lightBackground() {
            if (this.pngTransparent) {
                return 'repeating-conic-gradient(#e5e5e5 0% 25%, white 0% 50%) 50% / 8px 8px';
            }
            return this.pngBackground;
        },

        darkBackground() {
            return this.pngDarkBackground;
        },

        paddingStyle() {
            return { padding: `${this.iconPadding}%` };
        },

        processedSvg() {
            return this.processSvgWithColor(this.useCustomIconColor ? this.iconColor : '#000000');
        },

        processedDarkSvg() {
            return this.processSvgWithColor(this.darkModeIconColor);
        },
    },

    methods: {
        sanitizeSvg(content) {
            if (!content) return '';
            // Remove script tags and event handlers for security
            let sanitized = content.replace(/<script[\s\S]*?<\/script>/gi, '');
            sanitized = sanitized.replace(/\s*on\w+="[^"]*"/gi, '');
            sanitized = sanitized.replace(/\s*on\w+='[^']*'/gi, '');
            return sanitized;
        },

        processSvgWithColor(color) {
            if (!this.svgContent) {
                return '<div class="w-full h-full bg-gray-200 dark:bg-dark-600 rounded animate-pulse"></div>';
            }

            let processed = this.sanitizeSvg(this.svgContent);
            processed = processed.replace(/currentColor/g, color);

            if (this.useCustomIconColor) {
                processed = processed.replace(/fill=["']#0{3,6}["']/g, `fill="${color}"`);
            }

            if (!processed.includes('viewBox')) {
                const widthMatch = processed.match(/width="(\d+)"/);
                const heightMatch = processed.match(/height="(\d+)"/);
                if (widthMatch && heightMatch) {
                    processed = processed.replace(/<svg/, `<svg viewBox="0 0 ${widthMatch[1]} ${heightMatch[1]}"`);
                }
            }

            processed = processed.replace(/\s*width="\d+"/g, '');
            processed = processed.replace(/\s*height="\d+"/g, '');
            processed = processed.replace(/<svg/, '<svg style="width:100%;height:100%"');

            return processed;
        },
    },

    watch: {
        svgUrl: {
            immediate: true,
            async handler(url) {
                if (!url) {
                    this.svgContent = '';
                    return;
                }
                try {
                    const response = await fetch(url);
                    if (response.ok) {
                        this.svgContent = await response.text();
                    }
                } catch (error) {
                    console.error('Failed to fetch SVG:', error);
                    this.svgContent = '';
                }
            },
        },
    },
};
</script>
