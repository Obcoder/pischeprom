<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import CitySelector from '@/Components/Location/CitySelector.vue'
import OrderCartStrip from '@/Components/Orders/OrderCartStrip.vue'
import { useOrderCart } from '@/Composables/useOrderCart'

const page = usePage()
const { itemsCount } = useOrderCart()
const user = computed(() => page.props.auth?.user)
const logoUrl = 'https://storage.yandexcloud.net/pps/images/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg'
</script>

<template>
    <v-app class="product-app">
        <div class="product-site">
            <div class="product-topline">Продукты для вашего бизнеса. Условия — под вашу задачу.</div>
            <header class="product-header">
                <div class="product-header__inner">
                    <Link href="/" class="product-brand" aria-label="Пищепром-сервер — главная">
                        <img :src="logoUrl" alt="Логотип Пищепром-сервер" width="56" height="56" class="product-brand__logo" loading="eager" decoding="async">
                        <span class="product-brand__text"><strong>ПИЩЕПРОМ-СЕРВЕР</strong><small>Маркетплейс для пищевой промышленности</small></span>
                    </Link>
                    <nav class="product-nav" aria-label="Основная навигация">
                        <Link href="/g" class="product-catalog"><v-icon icon="mdi-view-grid-outline" size="20" /><span class="product-catalog__desktop">Каталог товаров</span><span class="product-catalog__mobile">Каталог</span></Link>
                        <CitySelector compact class="product-location" />
                        <Link :href="user ? '/dashboard' : '/login'" class="product-account" :aria-label="user ? 'Личный кабинет' : 'Войти в личный кабинет'"><v-icon icon="mdi-account-outline" size="23" /><span>{{ user ? 'Кабинет' : 'Войти' }}</span></Link>
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
.product-app { background: #fffaf8; color: #2d201d; font-family: Arial, sans-serif; }
.product-site { min-height: 100vh; min-width: 0; display: flex; flex-direction: column; }
.product-site main { flex: 1; min-width: 0; }
.product-topline { background: #640000; color: #fff3ed; text-align: center; font-size: 12px; padding: 9px 18px; letter-spacing: .025em; }
.product-header { background: #800000; border-bottom: 1px solid rgba(128, 0, 0, .14); }
.product-header__inner { max-width: 1320px; padding: 20px 32px; margin: auto; display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(150px, 230px) auto; align-items: center; gap: 24px; }
.product-brand { display: flex; min-width: 0; gap: 13px; align-items: center; text-decoration: none; color: #fff; }
.product-brand__logo { display: block; width: 56px; height: 56px; flex: 0 0 56px; border-radius: 12px; object-fit: contain; background: #fff; }
.product-brand__text { min-width: 0; }
.product-brand strong { display: block; font-size: 18px; line-height: 1.2; letter-spacing: -.35px; white-space: nowrap; }
.product-brand small { display: block; font-size: 10px; line-height: 1.4; color: #f5d8d0; margin-top: 5px; }
.product-nav { display: contents; }
.product-nav a { color: #fff; display: flex; min-height: 44px; align-items: center; justify-content: center; gap: 9px; text-decoration: none; font-size: 13px; font-weight: 600; }
.product-catalog { padding: 10px 15px; border: 1px solid rgba(255, 255, 255, .35); border-radius: 9px; white-space: nowrap; }
.product-catalog__mobile { display: none; }
.product-location { width: 100%; min-width: 0; }
.product-location :deep(.city-selector__button) { width: 100%; min-width: 0; min-height: 44px; padding: 10px 12px; border-radius: 9px; color: #fff; background: rgba(255, 255, 255, .12); font-size: 12px; text-align: left; }
.product-location :deep(.city-selector__button:hover), .product-catalog:hover, .product-account:hover { background: rgba(255, 255, 255, .2); }
.product-location :deep(.city-selector__text) { min-width: 0; max-width: 100%; }
.product-account { padding: 0 8px; border-radius: 9px; white-space: nowrap; }
.product-header__cart { max-width: 1320px; min-width: 0; margin: 0 auto; padding: 0 32px 16px; }
.product-footer { background: #800000; color: #f3d9d0; padding: 44px 32px; }
.product-footer__inner { max-width: 1256px; margin: auto; display: flex; gap: 30px; justify-content: space-between; }
.product-footer__brand { font-size: 18px; font-weight: 700; color: white; text-decoration: none; }
.product-footer p { margin: 12px 0 24px; font-size: 13px; }
.product-footer small { font-size: 11px; }
.product-footer__links { display: grid; gap: 12px; }
.product-footer__links a { font-size: 12px; color: #fff3ed; text-decoration: none; }
a:hover { text-decoration: underline; }
a:focus-visible, .product-location :deep(button:focus-visible) { outline: 3px solid #e7b67d; outline-offset: 4px; }
@media (max-width: 1050px) and (min-width: 768px) {
    .product-header__inner { padding-inline: 20px; gap: 12px; grid-template-columns: minmax(0, 1fr) auto minmax(120px, 180px) 44px; }
    .product-brand strong { font-size: 15px; }
    .product-brand__logo { width: 44px; height: 44px; flex-basis: 44px; }
    .product-brand { gap: 10px; }
    .product-brand small { font-size: 9px; }
    .product-catalog { padding-inline: 10px; font-size: 12px !important; }
    .product-account span { display: none; }
}
@media (max-width: 767px) {
    .product-footer { padding-bottom: calc(110px + env(safe-area-inset-bottom, 0px)); }
    .product-topline { display: none; }
    .product-header__inner { grid-template-columns: auto minmax(0, 1fr) 44px; gap: 12px 10px; padding: 14px 20px 12px; }
    .product-brand { grid-column: 1 / 3; grid-row: 1; }
    .product-account { grid-column: 3; grid-row: 1; width: 44px; height: 44px; padding: 0; background: rgba(255, 255, 255, .12); }
    .product-account span { display: none; }
    .product-catalog { grid-column: 1; grid-row: 2; }
    .product-location { grid-column: 2 / 4; grid-row: 2; }
    .product-location :deep(.city-selector__button) { justify-content: flex-start; }
    .product-header__cart { padding: 0 20px 12px; }
}
@media (max-width: 600px) {
    .product-header__inner { padding: 12px 14px 10px; gap: 12px 10px; }
    .product-brand { gap: 10px; }
    .product-brand__logo { width: 48px; height: 48px; flex-basis: 48px; border-radius: 10px; }
    .product-brand strong { font-size: 15px; letter-spacing: -.35px; }
    .product-brand small { max-width: 210px; font-size: 10px; margin-top: 4px; }
    .product-catalog { padding: 10px 13px; }
    .product-catalog__desktop { display: none; }
    .product-catalog__mobile { display: inline; }
    .product-location :deep(.city-selector__button) { gap: 6px; padding: 10px; font-size: 11px; }
    .product-header__cart { padding: 0 14px 10px; }
    .product-header__cart :deep(.order-cart-strip__submit) { min-height: 44px; }
    .product-footer { padding: 32px 20px calc(110px + env(safe-area-inset-bottom, 0px)); }
    .product-footer__inner { flex-direction: column; gap: 20px; }
    .product-footer__links { gap: 0; }
    .product-footer__links a { display: flex; min-height: 44px; align-items: center; line-height: 1.5; }
}
@media (max-width: 359px) {
    .product-header__inner { padding-inline: 12px; }
    .product-brand { gap: 8px; }
    .product-brand__logo { width: 44px; height: 44px; flex-basis: 44px; }
    .product-brand strong { font-size: 13px; }
    .product-brand small { font-size: 9px; }
    .product-catalog { padding-inline: 10px; gap: 6px !important; }
}
</style>
