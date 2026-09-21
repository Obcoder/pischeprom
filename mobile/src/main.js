import { createApp } from 'vue'
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import { ru } from 'vuetify/locale'
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import './style.css'
import App from './App.vue'

const vuetify = createVuetify({
    locale: { locale: 'ru', fallback: 'ru', messages: { ru } },
    icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
    theme: {
        defaultTheme: 'mobile',
        themes: {
            mobile: {
                dark: false,
                colors: {
                    primary: '#236254', secondary: '#173f39', background: '#f3f5f3',
                    surface: '#ffffff', error: '#a32d32', warning: '#9b6314', success: '#236254',
                },
            },
        },
    },
    defaults: {
        VBtn: { rounded: 'lg', elevation: 0, style: 'text-transform: none; letter-spacing: 0' },
        VTextField: { variant: 'outlined', color: 'primary', rounded: 'lg' },
        VSelect: { variant: 'outlined', color: 'primary', rounded: 'lg' },
        VCard: { rounded: 'xl', elevation: 0 },
        VAlert: { rounded: 'lg', variant: 'tonal' },
    },
})

createApp(App).use(vuetify).mount('#app')
