import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PrimeVue from 'primevue/config';
import "primeicons/primeicons.css";
import Aura from '@primeuix/themes/aura';
// import Material from '@primeuix/themes/material';
// import Lara from '@primeuix/themes/lara';
// import Nora from '@primeuix/themes/nora';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: Aura
                    // preset: Material
                    // preset: Lara
                    // preset: Nora
                }
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
