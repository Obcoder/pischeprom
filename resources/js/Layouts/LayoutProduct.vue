<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import CitySelector from '@/Components/Location/CitySelector.vue'
import OrderCartStrip from '@/Components/Orders/OrderCartStrip.vue'
import { useOrderCart } from '@/Composables/useOrderCart'

const page = usePage()
const { itemsCount } = useOrderCart()
const user = computed(() => page.props.auth?.user)
</script>

<template>
    <v-app class="product-app">
        <div class="product-site">
            <div class="product-topline">Продукты для вашего бизнеса. Условия — под вашу задачу.</div>
            <header class="product-header">
                <div class="product-header__inner">
                    <Link href="/" class="product-brand" aria-label="Пищепром-сервер — главная">
                        <span class="product-brand__mark"><v-icon icon="mdi-sprout-outline" size="30" /></span>
                        <span><strong>ПИЩЕПРОМ<span>СЕРВЕР</span></strong><small>Продукты. Поставки. Партнёрство.</small></span>
                    </Link>
                    <nav class="product-nav" aria-label="Основная навигация">
                        <Link href="/g" class="product-catalog"><v-icon icon="mdi-view-grid-outline" size="18" /> Каталог товаров</Link>
                        <CitySelector compact class="product-location" />
                        <Link :href="user ? '/dashboard' : '/login'" class="product-account"><v-icon icon="mdi-account-outline" size="21" /><span>{{ user ? 'Кабинет' : 'Войти' }}</span></Link>
                    </nav>
                </div>
                <div v-if="itemsCount" class="product-header__cart"><OrderCartStrip /></div>
            </header>
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
.product-app { background: #faf9f6; color: #202c28; font-family: Arial, sans-serif; }
.product-site { min-height: 100vh; display: flex; flex-direction: column; }
.product-site main { flex: 1; }
.product-topline { background: #193e32; color: #f4f3e9; text-align: center; font-size: 12px; padding: 9px 18px; letter-spacing: .025em; }
.product-header { background: #fff; border-bottom: 1px solid #e5e7df; }
.product-header__inner { max-width: 1320px; padding: 23px 32px; margin: auto; display: flex; align-items: center; justify-content: space-between; gap: 30px; }
.product-brand { display: flex; gap: 12px; align-items: center; text-decoration: none; color: #193e32; }
.product-brand__mark { width: 45px; height: 45px; border-radius: 50%; background: #eaf0e6; display: grid; place-items: center; }
.product-brand strong { display: block; font-size: 18px; letter-spacing: -.6px; }
.product-brand strong span { font-weight: 400; margin-left: 4px; }
.product-brand small { display: block; font-size: 10px; letter-spacing: .04em; color: #6e766e; margin-top: 4px; }
.product-nav { display: flex; align-items: center; gap: 28px; }
.product-nav a { color: #233d32; display: flex; align-items: center; gap: 9px; text-decoration: none; font-size: 13px; font-weight: 600; }
.product-catalog { padding: 12px 16px; border: 1px solid #d9dfd4; border-radius: 7px; }
.product-location { max-width: 240px; }
.product-location :deep(.city-selector__button) { color: #3d5142; background: #f3f5ef; font-size: 12px; }
.product-location :deep(.city-selector__button:hover) { background: #e9eee1; }
.product-header__cart { max-width: 1256px; margin: 0 auto 12px; }
.product-footer { background: #193e32; color: #cbd5c9; padding: 44px 32px; }
.product-footer__inner { max-width: 1256px; margin: auto; display: flex; gap: 30px; justify-content: space-between; }
.product-footer__brand { font-size: 18px; font-weight: 700; color: white; text-decoration: none; }
.product-footer p { margin: 12px 0 24px; font-size: 13px; }
.product-footer small { font-size: 11px; }
.product-footer__links { display: grid; gap: 12px; }
.product-footer__links a { font-size: 12px; color: #e0e7dc; text-decoration: none; }
a:hover { text-decoration: underline; }
a:focus-visible { outline: 3px solid #d79b59; outline-offset: 4px; }
@media(max-width: 850px) { .product-location { display: none; } .product-nav { gap: 15px; } }
@media(max-width: 600px) { .product-header__inner { padding: 16px; gap: 12px; } .product-brand { gap: 7px; } .product-brand strong { font-size: 14px; } .product-brand small { font-size: 8px; } .product-brand__mark { width: 34px; height: 34px; } .product-catalog { padding: 9px !important; font-size: 0 !important; gap: 0 !important; } .product-account span { display: none; } .product-topline { font-size: 10px; } .product-footer { padding: 32px 20px 110px; } .product-footer__inner { flex-direction: column; } }
</style>
