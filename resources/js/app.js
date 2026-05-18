import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'

import './bootstrap'
import '../css/app.css'

import { createSSRApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'
import { createHead } from '@vueuse/head'

// Vuetify
import { createVuetify } from 'vuetify'
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

const appName = import.meta.env?.['VITE_APP_NAME'] || 'ПИЩЕПРОМ-СЕРВЕР'

async function bootstrap() {
    try {
        await createInertiaApp({
            title: (title) => title ? `${title}` : appName,

            resolve: (name) => {
                return resolvePageComponent(
                    `./Pages/${name}.vue`,
                    import.meta.glob('./Pages/**/*.vue')
                )
            },

            setup({ el, App, props, plugin }) {
                const app = createSSRApp({
                    render: () => h(App, props),
                })

                app.use(plugin)
                app.use(vuetify)
                app.use(head)

                const initialPage = props?.initialPage || {}
                const initialPageProps = initialPage?.props || {}
                const ziggyConfig = initialPageProps['ziggy']

                if (ziggyConfig?.location) {
                    app.use(ZiggyVue, {
                        ...ziggyConfig,
                        location: new URL(ziggyConfig.location),
                    })
                } else {
                    app.use(ZiggyVue)
                }

                return app.mount(el)
            },

            progress: {
                color: '#4B5563',
            },
        })
    } catch (error) {
        console.error('Failed to initialize Inertia app:', error)
    }
}

void bootstrap()
