import './bootstrap'
import '../css/app.css'
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'

import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import { renderToString } from '@vue/server-renderer'
import { createSSRApp, h } from 'vue'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'

import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

const appName = import.meta.env.VITE_APP_NAME || 'ПИЩЕПРОМ-СЕРВЕР'

createServer((page) =>
    createInertiaApp({
        page,

        render: renderToString,

        title: (title) => title ? `${title}` : appName,

        resolve: (name) => {
            return resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue')
            )
        },

        setup({ App, props, plugin }) {
            const app = createSSRApp({
                render: () => h(App, props),
            })

            const vuetify = createVuetify({
                components,
                directives,
            })

            app.use(plugin)
            app.use(vuetify)

            if (page.props.ziggy) {
                app.use(ZiggyVue, {
                    ...page.props.ziggy,
                    location: new URL(page.props.ziggy.location),
                })
            } else {
                app.use(ZiggyVue)
            }

            return app
        },
    })
)
