<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b dark:border-dark-600 text-left bg-gray-50 dark:bg-dark-700">
                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-dark-200">Preview</th>
                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-dark-200">File</th>
                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-dark-200">Dimensions</th>
                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-dark-200">Size</th>
                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-dark-200">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="file in files"
                    :key="file.name"
                    class="border-b dark:border-dark-600 hover:bg-gray-50 dark:hover:bg-dark-750"
                >
                    <td class="px-4 py-3">
                        <div
                            class="w-10 h-10 rounded flex items-center justify-center overflow-hidden border border-gray-200 dark:border-dark-600"
                            :style="previewBackgroundStyle"
                        >
                            <img
                                v-if="file.type === 'image'"
                                :src="file.url + '?v=' + file.modified"
                                class="max-w-full max-h-full object-contain"
                                :alt="file.name"
                            />
                            <svg v-else class="w-5 h-5 text-gray-400 dark:text-dark-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <code class="text-xs bg-gray-100 dark:bg-dark-600 px-2 py-1 rounded text-gray-700 dark:text-dark-200">
                            {{ file.name }}
                        </code>
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-dark-300">
                        {{ file.dimensions || '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-dark-300">
                        {{ file.sizeFormatted || formatSize(file.size) }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2">
                            <button
                                @click="viewFile(file.url)"
                                class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 text-xs font-medium transition-colors"
                            >
                                View
                            </button>
                            <button
                                @click="downloadFile(file.url, file.name)"
                                class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 text-xs font-medium transition-colors"
                            >
                                Download
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
function formatSize(bytes) {
    if (!bytes) {
        return '\u2014';
    }
    if (bytes < 1024) {
        return bytes + ' B';
    }
    return (bytes / 1024).toFixed(1) + ' KB';
}

export default {
    props: {
        files: { type: Array, required: true },
    },

    data() {
        return {
            isDarkMode: false,
        };
    },

    computed: {
        previewBackgroundStyle() {
            // Use checkerboard pattern for transparency indication
            return {
                background: 'repeating-conic-gradient(#e5e5e5 0% 25%, #f5f5f5 0% 50%) 50% / 8px 8px',
            };
        },
    },

    methods: {
        formatSize,

        viewFile(url) {
            window.open(url, '_blank');
        },

        downloadFile(url, filename) {
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
        },
    },
};
</script>
