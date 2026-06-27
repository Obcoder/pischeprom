import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

const ssrClientWidth = Number(import.meta.env.VITE_SSR_CLIENT_WIDTH || 1280)
const ssrClientHeight = Number(import.meta.env.VITE_SSR_CLIENT_HEIGHT || 800)

export function createAppVuetify() {
    return createVuetify({
        locale: {
            locale: 'ru',
            fallback: 'ru',
        },

        icons: {
            defaultSet: 'mdi',
            aliases,
            sets: {
                mdi,
            },
        },

        ssr: {
            clientWidth: ssrClientWidth,
            clientHeight: ssrClientHeight,
        },

        components,
        directives,
    })
}
