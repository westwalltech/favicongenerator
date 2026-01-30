<template>
    <div class="p-6 space-y-6">
        <!-- Cache Busting Toggle -->
        <div class="flex items-center justify-between pb-4 border-b dark:border-dark-600">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-dark-200">Cache Busting</label>
                <p class="text-xs text-gray-500 dark:text-dark-300">Add version query parameter to URLs</p>
            </div>
            <Switch v-model="useCacheBusting" />
        </div>

        <!-- Antlers Tag -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-gray-700 dark:text-dark-200">Antlers Tag</label>
                <button
                    @click="copyAntlers"
                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium flex items-center gap-1"
                >
                    <svg v-if="copiedAntlers" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    {{ copiedAntlers ? 'Copied!' : 'Copy' }}
                </button>
            </div>
            <p class="text-xs text-gray-500 dark:text-dark-300 mb-2">
                Add this to your layout's <code class="bg-gray-100 dark:bg-dark-700 px-1 rounded">&lt;head&gt;</code> section:
            </p>
            <pre class="bg-gray-100 dark:bg-dark-700 rounded-lg p-4 text-sm font-mono overflow-x-auto text-gray-800 dark:text-dark-200">{{ antlersTag }}</pre>
            <p class="text-xs text-gray-500 dark:text-dark-300 mt-2">
                For cache busting, use: <code class="bg-gray-100 dark:bg-dark-700 px-1 rounded">{{ antlersTagWithCache }}</code>
            </p>
        </div>

        <!-- Full HTML Output -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-gray-700 dark:text-dark-200">Generated HTML</label>
                <button
                    @click="copyHtml"
                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium flex items-center gap-1"
                >
                    <svg v-if="copiedHtml" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    {{ copiedHtml ? 'Copied!' : 'Copy HTML' }}
                </button>
            </div>
            <p class="text-xs text-gray-500 dark:text-dark-300 mb-2">
                This is the HTML that <code class="bg-gray-100 dark:bg-dark-700 px-1 rounded" v-text="antlersTag"></code> will output:
            </p>
            <pre class="bg-gray-100 dark:bg-dark-700 rounded-lg p-4 text-sm font-mono overflow-x-auto whitespace-pre-wrap text-gray-800 dark:text-dark-200">{{ htmlOutput }}</pre>
        </div>

        <!-- Additional Tags Info -->
        <div class="border-t dark:border-dark-600 pt-4">
            <p class="text-xs text-gray-500 dark:text-dark-300">
                <strong>Additional Antlers tags available:</strong>
            </p>
            <ul class="text-xs text-gray-500 dark:text-dark-300 mt-2 space-y-1 list-disc list-inside">
                <li><code class="bg-gray-100 dark:bg-dark-700 px-1 rounded" v-text="tags.manifest"></code> — Only the manifest link tag</li>
                <li><code class="bg-gray-100 dark:bg-dark-700 px-1 rounded" v-text="tags.themeColor"></code> — Only the theme-color meta tag</li>
                <li><code class="bg-gray-100 dark:bg-dark-700 px-1 rounded" v-text="tags.color"></code> — Just the theme color value (e.g., for inline styles)</li>
                <li><code class="bg-gray-100 dark:bg-dark-700 px-1 rounded" v-text="tags.microsoft"></code> — Microsoft tile meta tags</li>
            </ul>
        </div>
    </div>
</template>

<script>
import { Switch } from '@statamic/cms/ui';

const ANTLERS_TAG = '{{ favicon }}';
const ANTLERS_TAG_CACHE = '{{ favicon cache_bust="true" }}';
const ADDITIONAL_TAGS = {
    manifest: '{{ favicon:manifest }}',
    themeColor: '{{ favicon:theme_color }}',
    color: '{{ favicon:color }}',
    microsoft: '{{ favicon:microsoft }}',
};
const COPY_RESET_DELAY = 2000;

export default {
    components: { Switch },

    props: {
        themeColor: { type: String, required: true },
        appName: { type: String, default: '' },
        generatedAt: { type: [String, Number], default: null },
    },

    data() {
        return {
            copiedAntlers: false,
            copiedHtml: false,
            useCacheBusting: false,
            antlersTag: ANTLERS_TAG,
            antlersTagWithCache: ANTLERS_TAG_CACHE,
            tags: ADDITIONAL_TAGS,
        };
    },

    computed: {
        cacheVersion() {
            if (!this.useCacheBusting) return '';
            // Use generatedAt timestamp or current time
            const version = this.generatedAt || Date.now();
            return `?v=${version}`;
        },

        htmlOutput() {
            const v = this.cacheVersion;
            const lines = [
                '<!-- Favicon -->',
                `<link rel="icon" href="/favicon.ico${v}" sizes="32x32">`,
                `<link rel="icon" href="/favicon.svg${v}" type="image/svg+xml">`,
                `<link rel="icon" type="image/png" href="/favicon-96x96.png${v}" sizes="96x96">`,
                '',
                '<!-- Apple Touch Icon -->',
                `<link rel="apple-touch-icon" href="/apple-touch-icon.png${v}">`,
                '',
                '<!-- Web App Manifest -->',
                `<link rel="manifest" href="/site.webmanifest${v}">`,
                '',
                '<!-- Theme Color -->',
                `<meta name="theme-color" content="${this.themeColor}">`,
            ];

            // App name tags
            if (this.appName) {
                lines.push('');
                lines.push('<!-- App Name -->');
                lines.push(`<meta name="application-name" content="${this.escapeHtml(this.appName)}">`);
                lines.push(`<meta name="apple-mobile-web-app-title" content="${this.escapeHtml(this.appName)}">`);
            }

            // Apple-specific tags
            lines.push('');
            lines.push('<!-- Apple Web App -->');
            lines.push('<meta name="apple-mobile-web-app-capable" content="yes">');
            lines.push('<meta name="apple-mobile-web-app-status-bar-style" content="default">');

            // Microsoft tags
            lines.push('');
            lines.push('<!-- Microsoft -->');
            lines.push(`<meta name="msapplication-TileColor" content="${this.themeColor}">`);
            lines.push('<meta name="msapplication-config" content="none">');

            return lines.join('\n');
        },
    },

    methods: {
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        async copyToClipboard(text, flagName) {
            try {
                await navigator.clipboard.writeText(text);
                this[flagName] = true;
                setTimeout(() => { this[flagName] = false; }, COPY_RESET_DELAY);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        },

        copyAntlers() {
            this.copyToClipboard(this.antlersTag, 'copiedAntlers');
        },

        copyHtml() {
            this.copyToClipboard(this.htmlOutput, 'copiedHtml');
        },
    },
};
</script>
