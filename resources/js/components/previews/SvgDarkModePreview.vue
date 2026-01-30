<template>
    <div class="p-6">
        <div class="grid grid-cols-2 gap-8 max-w-lg mx-auto">
            <!-- Light mode -->
            <div class="text-center">
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">Light Mode</p>
                <div
                    :class="[
                        'inline-flex items-center justify-center w-28 h-28 rounded-xl shadow-inner border transition-all duration-300',
                        !darkMode ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-dark-900' : ''
                    ]"
                    style="background: #ffffff;"
                >
                    <div class="w-16 h-16" v-html="lightModeSvg"></div>
                </div>
            </div>
            <!-- Dark mode -->
            <div class="text-center">
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">Dark Mode</p>
                <div
                    :class="[
                        'inline-flex items-center justify-center w-28 h-28 rounded-xl shadow-inner border border-dark-600 transition-all duration-300',
                        darkMode ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-dark-900' : ''
                    ]"
                    style="background: #1a1a1a;"
                >
                    <div class="w-16 h-16" v-html="darkModeSvg"></div>
                </div>
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-dark-300 text-center mt-4">
            The SVG favicon includes a CSS media query that adjusts colors when the user's system is in dark mode.
        </p>
    </div>
</template>

<script>
const DARK_MODE_FILTER_REGEX = /@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*svg\s*\{\s*filter:\s*([^;]+);?\s*\}\s*\}/;

export default {
    props: {
        svgUrl: { type: String, required: true },
        darkMode: { type: Boolean, default: false },
    },

    data() {
        return {
            svgContent: '',
        };
    },

    computed: {
        lightModeSvg() {
            if (!this.svgContent) {
                return '';
            }
            return this.prepareSvg(this.svgContent, '');
        },

        darkModeSvg() {
            if (!this.svgContent) {
                return '';
            }
            const filterMatch = this.svgContent.match(DARK_MODE_FILTER_REGEX);
            const filterValue = filterMatch ? filterMatch[1].trim() : '';
            return this.prepareSvg(this.svgContent, filterValue);
        },
    },

    watch: {
        svgUrl: {
            immediate: true,
            async handler(url) {
                if (!url) {
                    return;
                }
                try {
                    const response = await fetch(url);
                    if (response.ok) {
                        this.svgContent = await response.text();
                    }
                } catch (error) {
                    console.error('Failed to fetch SVG:', error);
                }
            },
        },
    },

    methods: {
        prepareSvg(content, filterValue) {
            const style = filterValue
                ? `style="width:100%;height:100%;filter:${filterValue}"`
                : 'style="width:100%;height:100%"';

            // Extract width and height to create viewBox if missing
            let prepared = content;

            // Replace currentColor with black so the icon is visible in preview
            prepared = prepared.replace(/currentColor/g, '#000000');

            // Check if viewBox is missing and add it based on width/height
            if (!prepared.includes('viewBox')) {
                const widthMatch = prepared.match(/width="(\d+)"/);
                const heightMatch = prepared.match(/height="(\d+)"/);
                if (widthMatch && heightMatch) {
                    const w = widthMatch[1];
                    const h = heightMatch[1];
                    prepared = prepared.replace(/<svg/, `<svg viewBox="0 0 ${w} ${h}"`);
                }
            }

            // Remove fixed width/height so it can scale with CSS
            prepared = prepared.replace(/\s*width="\d+"/g, '');
            prepared = prepared.replace(/\s*height="\d+"/g, '');

            // Add style for sizing and optional filter
            prepared = prepared.replace(/<svg/, `<svg ${style}`);

            return prepared;
        },
    },
};
</script>
