// Favicon Generator Addon - Statamic v6

// Control Panel Component
import FaviconGenerator from './components/FaviconGenerator.vue';

// Register Inertia page for Control Panel navigation (Statamic v6)
Statamic.booting(() => {
    Statamic.$inertia.register('favicon-generator::FaviconGenerator', FaviconGenerator);
});
