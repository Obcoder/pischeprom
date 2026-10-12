<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppHeader from '@/Components/Layout/AppHeader.vue'

const page = usePage()
const categories = computed(() => page.props.headerCategories || [])
</script>

<template>
    <v-app class="product-app">
        <div class="product-site">
            <AppHeader :categories="categories" />
            <main id="main-content"><slot /></main>
            <footer class="product-footer">
                <div class="product-footer__inner">
                    <div><Link href="/" class="product-footer__brand">ПИЩЕПРОМ-СЕРВЕР</Link><p>Помогаем договориться о хорошей поставке.</p><small>© {{ new Date().getFullYear() }} ООО «Пищепром-сервер»</small></div>
                    <div class="product-footer__links"><Link href="/g">Каталог товаров</Link><a href="/privacy-policy">Политика конфиденциальности</a><a href="/personal-data-consent">Обработка персональных данных</a><a href="/terms">Пользовательское соглашение</a></div>
                </div>
            </footer>
        </div>
    </v-app>
</template>

<style scoped>
.product-app { background: #fffaf8; color: #2d201d; font-family: Arial, sans-serif; }
.product-site { min-height: 100vh; min-width: 0; display: flex; flex-direction: column; }
.product-site main { flex: 1; min-width: 0; }
.product-footer { background: #800000; color: #f3d9d0; padding: 44px 32px; }
.product-footer__inner { max-width: 1256px; margin: auto; display: flex; gap: 30px; justify-content: space-between; }
.product-footer__brand { font-size: 18px; font-weight: 700; color: white; text-decoration: none; }
.product-footer p { margin: 12px 0 24px; font-size: 13px; }
.product-footer small { font-size: 11px; }
.product-footer__links { display: grid; gap: 12px; }
.product-footer__links a { font-size: 12px; color: #fff3ed; text-decoration: none; }
a:hover { text-decoration: underline; }
a:focus-visible { outline: 3px solid #e7b67d; outline-offset: 4px; }
@media (max-width: 767px) { .product-footer { padding-bottom: calc(110px + env(safe-area-inset-bottom, 0px)); } }
@media (max-width: 600px) {
    .product-footer { padding: 32px 20px calc(110px + env(safe-area-inset-bottom, 0px)); }
    .product-footer__inner { flex-direction: column; gap: 20px; }
    .product-footer__links { gap: 0; }
    .product-footer__links a { display: flex; min-height: 44px; align-items: center; line-height: 1.5; }
}
</style>
