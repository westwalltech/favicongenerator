<template>
    <div class="p-6 space-y-6">
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
            </ul>
        </div>
    </div>
</template>

<script>
const ANTLERS_TAG = '{{ favicon }}';
const ADDITIONAL_TAGS = {
    manifest: '{{ favicon:manifest }}',
    themeColor: '{{ favicon:theme_color }}',
    color: '{{ favicon:color }}',
};
const COPY_RESET_DELAY = 2000;

export default {
    props: {
        themeColor: { type: String, required: true },
    },

    data() {
        return {
            copiedAntlers: false,
            copiedHtml: false,
            antlersTag: ANTLERS_TAG,
            tags: ADDITIONAL_TAGS,
        };
    },

    computed: {
        htmlOutput() {
            return [
                '<link rel="icon" href="/favicon.ico" sizes="32x32">',
                '<link rel="icon" href="/favicon.svg" type="image/svg+xml">',
                '<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
                '<link rel="manifest" href="/site.webmanifest">',
                `<meta name="theme-color" content="${this.themeColor}">`,
            ].join('\n');
        },
    },

    methods: {
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
