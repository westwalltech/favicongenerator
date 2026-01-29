<template>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Light Mode Browser -->
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3 text-center">Light Mode</p>
                <div class="border rounded-lg overflow-hidden shadow-sm max-w-sm mx-auto bg-white">
                    <!-- Browser chrome -->
                    <div class="bg-gray-100 px-3 py-2 border-b flex items-center gap-2">
                        <div class="flex gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-red-400"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                            <div class="w-3 h-3 rounded-full bg-green-400"></div>
                        </div>
                    </div>
                    <!-- Tab bar -->
                    <div class="bg-gray-200 px-2 pt-2">
                        <div class="inline-flex items-center gap-2 bg-white px-3 py-1.5 rounded-t-lg text-sm max-w-[200px]">
                            <img :src="faviconUrl" class="w-4 h-4 flex-shrink-0" alt="Favicon" />
                            <span class="truncate text-gray-800">{{ siteName }}</span>
                            <span class="text-gray-400 text-xs flex-shrink-0">×</span>
                        </div>
                    </div>
                    <!-- Address bar -->
                    <div class="bg-white px-3 py-2 border-b">
                        <div class="bg-gray-100 rounded px-3 py-1 text-sm text-gray-500">
                            https://example.com
                        </div>
                    </div>
                    <!-- Content area -->
                    <div class="h-20 bg-gray-50"></div>
                </div>
            </div>

            <!-- Dark Mode Browser -->
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3 text-center">Dark Mode</p>
                <div class="border border-dark-600 rounded-lg overflow-hidden shadow-sm max-w-sm mx-auto bg-dark-900">
                    <!-- Browser chrome -->
                    <div class="bg-dark-800 px-3 py-2 border-b border-dark-600 flex items-center gap-2">
                        <div class="flex gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        </div>
                    </div>
                    <!-- Tab bar -->
                    <div class="bg-dark-700 px-2 pt-2">
                        <div class="inline-flex items-center gap-2 bg-dark-800 px-3 py-1.5 rounded-t-lg text-sm max-w-[200px]">
                            <img :src="faviconUrl" class="w-4 h-4 flex-shrink-0" alt="Favicon" />
                            <span class="truncate text-gray-200">{{ siteName }}</span>
                            <span class="text-gray-500 text-xs flex-shrink-0">×</span>
                        </div>
                    </div>
                    <!-- Address bar -->
                    <div class="bg-dark-800 px-3 py-2 border-b border-dark-600">
                        <div class="bg-dark-700 rounded px-3 py-1 text-sm text-gray-400">
                            https://example.com
                        </div>
                    </div>
                    <!-- Content area -->
                    <div class="h-20 bg-dark-900"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        faviconUrl: { type: String, required: true },
        siteName: { type: String, required: true },
    },
};
</script>
