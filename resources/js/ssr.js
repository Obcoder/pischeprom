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

function normalizeZiggyLocation(page, ziggy) {
    if (ziggy?.location) {
        return new URL(ziggy.location)
    }

    if (ziggy?.url && page?.url) {
        return new URL(page.url, ziggy.url)
    }

    if (ziggy?.url) {
        return new URL(ziggy.url)
    }

    return new URL(page?.url || '/', 'http://localhost')
}

function makeZiggyConfig(page) {
    const ziggy = page.props?.ziggy || {}

    return {
        ...ziggy,
        defaults: ziggy.defaults || {},
        routes: ziggy.routes || {},
        location: normalizeZiggyLocation(page, ziggy),
    }
}

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

            const ziggy = makeZiggyConfig(page)

            /**
             * Важно для SSR:
             *
             * app.use(ZiggyVue, ziggy) помогает только $route / route внутри Vue-плагина.
             * Но прямой импорт `import { route } from 'ziggy-js'`
             * во время SSR не знает про Vue plugin.
             *
             * Поэтому кладём конфиг в globalThis.Ziggy.
             * Это чинит старые компоненты, где route импортирован напрямую из ziggy-js.
             */
            globalThis.Ziggy = ziggy

            app.use(plugin)
            app.use(vuetify)
            app.use(ZiggyVue, ziggy)

            return app
        },
    })
)
