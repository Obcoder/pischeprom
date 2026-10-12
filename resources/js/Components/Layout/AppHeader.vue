<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import CitySelector from '@/Components/Location/CitySelector.vue'
import OrderCartStrip from '@/Components/Orders/OrderCartStrip.vue'
import CatalogNodeAvatar from '@/Components/Catalog/CatalogNodeAvatar.vue'
import { useAppRoute } from '@/Composables/useAppRoute'
import { publicCategoryUrl } from '@/utils/publicCategoryUrl.js'

const props = defineProps({ categories: { type: Array, default: () => [] } })
const page = usePage()
const { route } = useAppRoute()
const accountMenu = ref(false)
const logoFailed = ref(false)
const logoUrl = 'https://storage.yandexcloud.net/pps/images/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg'
const contacts = [
    { label: '+7-965-016-00-01', href: 'tel:+79650160001', icon: 'mdi-phone-outline' },
    { label: 'office@180022.ru', href: 'mailto:office@180022.ru', icon: 'mdi-email-outline' },
]
const user = computed(() => page.props.auth?.user ?? null)
const canLogin = computed(() => Boolean(page.props.canLogin))
const canRegister = computed(() => Boolean(page.props.canRegister))
const availableCategories = computed(() => props.categories.filter(category => category?.id && category?.name))
const homeUrl = computed(() => route('home'))
const catalogUrl = computed(() => route('public.goods.index'))
const loginUrl = computed(() => hasRoute('login') ? route('login') : '/login')
const registerUrl = computed(() => hasRoute('register') ? route('register') : '/register')
const profileUrl = computed(() => {
    for (const name of ['customer.profile.edit', 'profile.show', 'dashboard']) {
        if (hasRoute(name)) return route(name)
    }
    return '/dashboard'
})
function hasRoute(name) { return Boolean((page.props.ziggy || globalThis.Ziggy || {}).routes?.[name]) }
function categoryUrl(category) {
    return publicCategoryUrl(category, page.props.publicCategoryUrls, route('category.show', category.slug || category.id))
}
function pathOf(url) {
    try { return new URL(url, 'https://store.example').pathname.replace(/\/$/, '') || '/' }
    catch { return '/' }
}
function isCurrent(url) { return pathOf(page.url || '/') === pathOf(url) }
function logout() {
    if (!hasRoute('logout')) return
    accountMenu.value = false
    router.post(route('logout'), {}, { preserveScroll: true })
}
</script>

<template>
    <header class="app-header">
        <div class="app-header__top">
            <v-container class="app-header__container">
                <div class="app-header__top-inner">
                    <Link :href="homeUrl" class="app-header__brand" aria-label="Пищепром-сервер — главная">
                        <span class="app-header__logo">
                            <img v-if="!logoFailed" :src="logoUrl" alt="" width="40" height="40" decoding="async" @error="logoFailed = true">
                            <span v-else>ПС</span>
                        </span>
                        <span class="app-header__brand-text"><strong>ПИЩЕПРОМ-СЕРВЕР</strong><small>Маркетплейс для пищевой промышленности</small></span>
                    </Link>
                    <div class="app-header__contacts" aria-label="Контакты">
                        <a v-for="contact in contacts" :key="contact.href" :href="contact.href" class="app-header__contact">
                            <v-icon :icon="contact.icon" size="16" /><span>{{ contact.label }}</span>
                        </a>
                    </div>
                    <CitySelector compact class="app-header__city" />
                    <div class="app-header__account">
                        <v-menu v-if="user" v-model="accountMenu" location="bottom end">
                            <template #activator="{ props: menuProps }">
                                <button v-bind="menuProps" type="button" class="app-header__user" aria-label="Меню личного кабинета">
                                    <v-icon icon="mdi-account-circle-outline" size="23" />
                                    <span>{{ user.name || 'Кабинет' }}</span><v-icon icon="mdi-chevron-down" size="16" />
                                </button>
                            </template>
                            <v-list density="compact" rounded="lg" min-width="200">
                                <Link :href="profileUrl" class="app-header__profile" @click="accountMenu = false"><v-list-item prepend-icon="mdi-account-outline" title="Кабинет" /></Link>
                                <v-list-item prepend-icon="mdi-logout" title="Выйти" @click="logout" />
                            </v-list>
                        </v-menu>
                        <template v-else>
                            <Link v-if="canLogin" :href="loginUrl" class="app-header__login">Войти</Link>
                            <Link v-if="canRegister" :href="registerUrl" class="app-header__register">Регистрация</Link>
                        </template>
                    </div>
                </div>
            </v-container>
        </div>

        <div class="app-header__navigation">
            <v-container class="app-header__container">
                <nav class="app-header__nav" aria-label="Разделы магазина">
                    <Link :href="homeUrl" class="app-header__nav-link" :class="{ 'app-header__nav-link--active': isCurrent(homeUrl) }" :aria-current="isCurrent(homeUrl) ? 'page' : undefined"><v-icon icon="mdi-home-outline" size="19" />Начало</Link>
                    <Link :href="catalogUrl" class="app-header__nav-link" :class="{ 'app-header__nav-link--active': isCurrent(catalogUrl) }" :aria-current="isCurrent(catalogUrl) ? 'page' : undefined"><v-icon icon="mdi-view-grid-outline" size="18" />Каталог</Link>
                    <Link v-for="category in availableCategories" :key="category.id" :href="categoryUrl(category)" class="app-header__nav-link app-header__quick-link" :class="{ 'app-header__nav-link--active': isCurrent(categoryUrl(category)) }" :aria-current="isCurrent(categoryUrl(category)) ? 'page' : undefined">
                        <CatalogNodeAvatar :node="category" :size="20" /><span>{{ category.name }}</span>
                    </Link>
                </nav>
            </v-container>
        </div>

        <div id="product-cart" class="app-header__cart-row">
            <v-container class="app-header__container"><OrderCartStrip /></v-container>
        </div>
    </header>
</template>

<style scoped>
.app-header { position: sticky; top: 0; z-index: 20; min-width: 0; background: #fffaf8; box-shadow: 0 4px 18px #4923220b; }
.app-header__container { padding-block: 0; }
.app-header__top { background: #800000; color: #fff; }
.app-header__top-inner { display: grid; grid-template-columns: minmax(205px, 1fr) auto minmax(150px, 240px) auto; align-items: center; gap: 16px; padding-block: 10px; }
.app-header__brand { display: flex; align-items: center; gap: 9px; min-width: 0; color: #fff; text-decoration: none; }
.app-header__logo { flex: 0 0 40px; width: 40px; height: 40px; display: grid; place-items: center; overflow: hidden; border-radius: 50%; background: #fff; color: #800000; font-weight: 800; }
.app-header__logo img { display: block; width: 100%; height: 100%; object-fit: cover; }
.app-header__brand-text { min-width: 0; }
.app-header__brand-text strong { display: block; font-size: 14px; font-weight: 800; line-height: 1.2; }
.app-header__brand-text small { display: block; margin-top: 3px; max-width: 220px; font-size: 10px; line-height: 1.3; color: #f0c9c3; }
.app-header__contacts { display: flex; flex-wrap: wrap; gap: 5px 12px; align-items: center; }
.app-header__contact { display: inline-flex; align-items: center; gap: 6px; color: #fff0ed; font-size: 12px; line-height: 1.5; text-decoration: none; white-space: nowrap; }
.app-header__contact:hover { text-decoration: underline; }
.app-header__city { width: 100%; min-width: 0; }
.app-header__city :deep(.city-selector__button) { width: 100%; min-width: 0; min-height: 36px; padding: 8px 11px; font-size: 12px; }
.app-header__city :deep(.city-selector__text) { min-width: 0; max-width: 100%; }
.app-header__account { display: flex; justify-content: flex-end; align-items: center; gap: 6px; min-width: 0; }
.app-header__login, .app-header__register, .app-header__user { display: inline-flex; justify-content: center; align-items: center; gap: 6px; min-height: 36px; padding: 7px 11px; color: #fff; border-radius: 9px; text-decoration: none; font-size: 12px; font-weight: 650; white-space: nowrap; }
.app-header__register { border: 1px solid #ffffff80; background: #ffffff0d; }
.app-header__login:hover, .app-header__register:hover, .app-header__user:hover { background: #ffffff20; }
.app-header__user { max-width: 190px; cursor: pointer; background: #ffffff12; }
.app-header__user span { overflow: hidden; text-overflow: ellipsis; }
.app-header__profile { color: inherit; text-decoration: none; }
.app-header__navigation { background: linear-gradient(110deg, #f8eeeb, #fffaf6 55%, #f4e7e4); border-bottom: 1px solid #eadbd5; }
.app-header__nav { display: flex; align-items: center; gap: 10px; max-width: 100%; padding: 9px 2px 12px; overflow-x: auto; overscroll-behavior-x: contain; scrollbar-width: thin; scrollbar-color: #ccb1a8 transparent; }
.app-header__nav-link { position: relative; isolation: isolate; display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 8px 16px; border: 1px solid #fffaf7; border-bottom-color: #dfc7be; border-radius: 12px; background: linear-gradient(160deg, #ffffffed, #fff8f2a8 55%, #ecd7d066); color: #684239; box-shadow: inset 0 1px 0 #fff, inset 0 -2px 3px #cdaaa22b, 0 3px 0 #d5beb5, 0 5px 9px #63322912; backdrop-filter: blur(12px); text-decoration: none; font-size: 13px; font-weight: 650; line-height: 1.3; white-space: nowrap; transition: transform .15s ease, box-shadow .15s ease, background .15s ease; }
.app-header__nav-link::before { content: ''; position: absolute; z-index: -1; inset: 1px 12% auto; height: 40%; border-radius: 20px; background: linear-gradient(#ffffff85, #fff0); pointer-events: none; }
.app-header__nav-link:hover { transform: translateY(-1px); box-shadow: inset 0 1px 0 #fff, 0 4px 0 #d5beb5, 0 7px 13px #6332291f; }
.app-header__nav-link:active { transform: translateY(2px); box-shadow: inset 0 1px 3px #63322920, 0 1px 0 #d5beb5; }
.app-header__nav-link--active { border-color: #b45448; border-top-color: #d17d6d; background: linear-gradient(155deg, #ad3f32, #800000 70%); color: #fff; box-shadow: inset 0 1px 0 #ffffff66, inset 0 -2px 3px #50000060, 0 3px 0 #5f1511, 0 5px 10px #80000026; }
.app-header__nav-link--active:hover { box-shadow: inset 0 1px 0 #ffffff66, 0 4px 0 #5f1511, 0 7px 13px #80000030; }
.app-header__cart-row { padding-block: 7px; }
.app-header :is(a, button):focus-visible, .app-header__city :deep(button:focus-visible) { outline: 3px solid #dbaf68; outline-offset: 3px; }
@media (max-width: 1279px) and (min-width: 960px) {
    .app-header__top-inner { grid-template-columns: minmax(200px, 1fr) auto minmax(160px, 220px) auto; gap: 12px; }
    .app-header__contacts { flex-direction: column; align-items: flex-start; gap: 3px; }
}
@media (max-width: 959px) {
    .app-header { position: relative; }
    .app-header__top-inner { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); grid-template-areas: 'brand account' 'contacts city'; gap: 8px 16px; padding-block: 10px; }
    .app-header__brand { grid-area: brand; }
    .app-header__contacts { grid-area: contacts; }
    .app-header__city { grid-area: city; justify-self: end; max-width: 280px; }
    .app-header__account { grid-area: account; }
    .app-header__brand-text small { max-width: none; }
    .app-header__nav-link { min-height: 40px; }
}
@media (max-width: 599px) {
    .app-header__container { padding-inline: 14px; }
    .app-header__top-inner { gap: 7px 10px; }
    .app-header__brand { gap: 7px; }
    .app-header__logo { flex-basis: 34px; width: 34px; height: 34px; }
    .app-header__brand-text strong { font-size: 12px; }
    .app-header__brand-text small { display: none; }
    .app-header__account { gap: 3px; }
    .app-header__login, .app-header__register { padding: 6px 8px; font-size: 11px; }
    .app-header__contacts { flex-direction: column; align-items: flex-start; gap: 3px; }
    .app-header__contact { gap: 5px; font-size: 11px; }
    .app-header__city :deep(.city-selector__button) { padding: 8px; font-size: 11px; }
    .app-header__user { max-width: 145px; padding-inline: 8px; }
    .app-header__nav { gap: 8px; padding-top: 8px; }
    .app-header__nav-link { padding-inline: 13px; font-size: 12px; }
}
@media (max-width: 399px) {
    .app-header__top-inner { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
    .app-header__brand { grid-column: 1 / -1; padding-right: 96px; min-height: 52px; }
    .app-header__account { grid-column: 2; align-self: center; flex-direction: column; justify-self: end; gap: 0; }
    .app-header__login, .app-header__register { min-height: 27px; padding: 4px 7px; }
    .app-header__user { max-width: 94px; }
    .app-header__user > .v-icon:last-child { display: none; }
}
@media (prefers-reduced-motion: reduce) { .app-header__nav-link { transition: none; } }
</style>
