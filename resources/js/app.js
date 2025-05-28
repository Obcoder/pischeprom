import 'vuetify/styles';
import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
//import {createMetaManager, defaultConfig} from 'vue-meta'; chatgpt: не нужно использовать одновременно createMetaManager
// и @vueuse/head, потому что это две разные библиотеки для управления <head>, и они могут конфликтовать.
// Ты подключаешь и vue-meta, и @vueuse/head — этого делать не стоит. Лучше выбрать только одну.
// ✅ Если хочешь использовать @vueuse/head (а это правильно):

// Vuetify
import { createVuetify } from 'vuetify';
import VuetifyInertiaLink from "vuetify-inertia-link";
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import '@mdi/font/css/materialdesignicons.css';
import { createHead } from '@vueuse/head';
const head = createHead()

const vuetify = createVuetify({
    locale: {
        locale: 'ru',
    },
    icons: {
        defaultSet: 'mdi', // This is already the default value - only for display purposes
    },
    components,
    directives,
})

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify)
            .use(VuetifyInertiaLink)
            .use(head)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
