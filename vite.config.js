import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'
import inertia from '@inertiajs/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
    // MDI uses private-use Unicode code points. Keeping them escaped prevents
    // embedded browsers from rendering their UTF-8 bytes as mojibake.
    esbuild: {
        charset: 'ascii',
    },

    build: {
        cssMinify: 'esbuild',
    },

    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),

        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),

        vuetify({
            autoImport: true,
        }),

        inertia({
            ssr: false,
        }),
    ],

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },

    ssr: {
        noExternal: true,
    },
})
