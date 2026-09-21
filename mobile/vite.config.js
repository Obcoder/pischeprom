import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vuetify from 'vite-plugin-vuetify'
import { fileURLToPath } from 'node:url'

export default defineConfig({
    root: fileURLToPath(new URL('.', import.meta.url)),
    envDir: fileURLToPath(new URL('.', import.meta.url)),
    base: './',
    plugins: [vue(), vuetify({ autoImport: true })],
    esbuild: { charset: 'ascii' },
    css: { postcss: { plugins: [] } },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        cssMinify: 'esbuild',
    },
    server: { host: '127.0.0.1', port: 5174, strictPort: true },
})
