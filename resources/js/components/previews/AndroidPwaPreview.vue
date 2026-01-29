<template>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Android Home Screen Icon -->
            <div class="text-center">
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">Android Home Screen</p>
                <p class="text-xs text-gray-500 dark:text-dark-300 mb-4">192×192 with adaptive icon masking</p>
                <div class="inline-block p-6 rounded-2xl bg-gray-100 dark:bg-dark-700">
                    <div class="flex flex-col items-center gap-2">
                        <!-- Circular mask like Android adaptive icons -->
                        <div class="w-16 h-16 rounded-full overflow-hidden shadow-lg ring-2 ring-white/20">
                            <img :src="icon192Url" class="w-full h-full object-cover" alt="Android Icon" />
                        </div>
                        <span class="text-xs text-gray-700 dark:text-dark-200 max-w-[80px] truncate font-medium">
                            {{ appName }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- PWA Splash Screen -->
            <div class="text-center">
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">PWA Splash Screen</p>
                <p class="text-xs text-gray-500 dark:text-dark-300 mb-4">512×512 for launch screens</p>
                <div
                    class="inline-flex flex-col items-center justify-center w-48 h-72 rounded-2xl shadow-lg mx-auto"
                    :style="{ backgroundColor: backgroundColor }"
                >
                    <img :src="icon512Url" class="w-24 h-24 mb-4" alt="PWA Splash" />
                    <span class="text-sm font-semibold px-4 text-center" :style="{ color: themeColor }">
                        {{ appName }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        icon192Url: { type: String, required: true },
        icon512Url: { type: String, required: true },
        appName: { type: String, required: true },
        themeColor: { type: String, required: true },
        backgroundColor: { type: String, required: true },
    },
};
</script>
