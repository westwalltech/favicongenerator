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

            return content
                .replace(/<svg/, `<svg ${style}`)
                .replace(/width="32"/, '')
                .replace(/height="32"/, '');
        },
    },
};
</script>
