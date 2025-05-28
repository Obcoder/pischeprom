import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'], // Точка входа, через которую подтягивается app.css
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
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Поддержка SCSS для Vuetify
                additionalData: `@use "sass:map";`,
            },
        },
    },
    resolve: {
        alias: {
            // Для совместимости с Inertia и Vuetify
            'vue': 'vue/dist/vue.esm-bundler.js',
        },
    },
});
