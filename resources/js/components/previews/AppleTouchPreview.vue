<template>
    <div class="p-6">
        <div class="grid grid-cols-2 gap-8 max-w-lg mx-auto">
            <!-- Light iOS -->
            <div class="text-center">
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">iOS Light</p>
                <div
                    class="inline-block p-6 rounded-3xl"
                    style="background: linear-gradient(180deg, #f5f5f7 0%, #e8e8ed 100%);"
                >
                    <div class="flex flex-col items-center gap-1">
                        <img
                            :src="iconUrl"
                            class="w-[60px] h-[60px] rounded-[13px]"
                            alt="Apple Touch Icon"
                        />
                        <span class="text-[11px] text-gray-800 mt-1 max-w-[75px] truncate font-medium">
                            {{ appName }}
                        </span>
                    </div>
                </div>
            </div>
            <!-- Dark iOS -->
            <div class="text-center">
                <p class="text-sm font-medium text-gray-600 dark:text-dark-200 mb-3">iOS Dark</p>
                <div
                    class="inline-block p-6 rounded-3xl"
                    style="background: linear-gradient(180deg, #1c1c1e 0%, #000000 100%);"
                >
                    <div class="flex flex-col items-center gap-1">
                        <img
                            :src="iconUrl"
                            class="w-[60px] h-[60px] rounded-[13px]"
                            :style="darkModeFilterStyle"
                            alt="Apple Touch Icon"
                        />
                        <span class="text-[11px] text-gray-300 mt-1 max-w-[75px] truncate font-medium">
                            {{ appName }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-dark-300 text-center mt-4">
            Apple Touch Icon (180×180) — shown when users add your site to their iOS home screen.
        </p>
    </div>
</template>

<script>
function hexToHsl(hexColor) {
    const hex = hexColor.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16) / 255;
    const g = parseInt(hex.substr(2, 2), 16) / 255;
    const b = parseInt(hex.substr(4, 2), 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;

    if (max === min) {
        return { h: 0, s: 0, l: Math.round(l * 100) };
    }

    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    let h;
    if (max === r) {
        h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
    } else if (max === g) {
        h = ((b - r) / d + 2) / 6;
    } else {
        h = ((r - g) / d + 4) / 6;
    }

    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100),
    };
}

function buildColorFilter(hsl) {
    const filters = ['invert(1)', 'sepia(1)', `hue-rotate(${hsl.h}deg)`];

    if (hsl.s > 0) {
        filters.push(`saturate(${(hsl.s / 30) * 100}%)`);
    } else {
        filters.push('saturate(0)');
    }

    const brightnessScale = hsl.l / 50;
    if (brightnessScale !== 1) {
        filters.push(`brightness(${brightnessScale})`);
    }

    return filters.join(' ');
}

export default {
    props: {
        iconUrl: { type: String, required: true },
        appName: { type: String, required: true },
        darkModeStyle: { type: String, default: 'invert' },
        darkModeColor: { type: String, default: '#ffffff' },
    },

    computed: {
        darkModeFilterStyle() {
            switch (this.darkModeStyle) {
                case 'invert':
                    return { filter: 'invert(1) hue-rotate(180deg)' };
                case 'lighten':
                    return { filter: 'brightness(1.5) contrast(1.1)' };
                case 'custom':
                    return { filter: buildColorFilter(hexToHsl(this.darkModeColor)) };
                default:
                    return {};
            }
        },
    },
};
</script>
