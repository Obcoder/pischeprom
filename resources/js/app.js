import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'

import './bootstrap'
import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'
import { createHead } from '@vueuse/head'

// Vuetify
import { createVuetify } from 'vuetify'
import VuetifyInertiaLink from 'vuetify-inertia-link'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

const head = createHead()

const vuetify = createVuetify({
    locale: {
        locale: 'ru',
    },

    icons: {
        defaultSet: 'mdi',
        aliases,
        sets: {
            mdi,
        },
    },

    components,
    directives,
})

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
    title: (title) => `${title}`,

    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue')
    ),

    setup({ el, App, props, plugin }) {
        return createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify)
            .use(VuetifyInertiaLink)
            .use(head)
            .mount(el)
    },

    progress: {
        color: '#4B5563',
    },
})
