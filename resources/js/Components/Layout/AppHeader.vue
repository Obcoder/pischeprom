<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import CitySelector from '@/Components/Location/CitySelector.vue'
import OrderCartStrip from '@/Components/Orders/OrderCartStrip.vue'
import CatalogNodeAvatar from '@/Components/Catalog/CatalogNodeAvatar.vue'
import { useAppRoute } from '@/Composables/useAppRoute'
import { publicCategoryUrl } from '@/utils/publicCategoryUrl.js'

const props = defineProps({ categories: { type: Array, default: () => [] } })
const page = usePage()
const { route } = useAppRoute()
const accountMenu = ref(false)
const categoryDropdown = ref(null)
const landingDropdown = ref(null)
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
const availableLandings = computed(() => (Array.isArray(page.props.headerLandings) ? page.props.headerLandings : [])
    .filter(landing => landing?.id && landing?.title && landing?.url))
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
function closeCategories() {
    if (categoryDropdown.value) categoryDropdown.value.open = false
}
function closeLandings() {
    if (landingDropdown.value) landingDropdown.value.open = false
}
function dismissDropdowns(event) {
    if (!categoryDropdown.value?.contains(event.target)) closeCategories()
    if (!landingDropdown.value?.contains(event.target)) closeLandings()
}
function escapeCategories() {
    if (!categoryDropdown.value?.open) return
    closeCategories()
    categoryDropdown.value.querySelector('summary')?.focus()
}
function escapeLandings() {
    if (!landingDropdown.value?.open) return
    closeLandings()
    landingDropdown.value.querySelector('summary')?.focus()
}
watch(() => page.url, () => { closeCategories(); closeLandings() })
onMounted(() => document.addEventListener('click', dismissDropdowns))
onBeforeUnmount(() => document.removeEventListener('click', dismissDropdowns))
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
                        <span class="app-header__brand-text"><strong>ПИЩЕПРОМ-СЕРВЕР</strong><small>Маркетплейс пищевой промышленности</small></span>
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
                    <div class="app-header__catalog">
                        <Link :href="catalogUrl" class="app-header__nav-link" :class="{ 'app-header__nav-link--active': isCurrent(catalogUrl), 'app-header__catalog-link--split': availableCategories.length }" :aria-current="isCurrent(catalogUrl) ? 'page' : undefined"><v-icon icon="mdi-view-grid-outline" size="18" />Каталог</Link>
                        <details v-if="availableCategories.length" ref="categoryDropdown" class="app-header__category-dropdown" @keydown.esc.stop.prevent="escapeCategories">
                            <summary class="app-header__category-toggle" aria-label="Категории каталога" title="Категории каталога"><v-icon icon="mdi-chevron-down" size="19" /></summary>
                            <div class="app-header__category-menu" aria-label="Категории каталога">
                                <Link v-for="category in availableCategories" :key="category.id" :href="categoryUrl(category)" class="app-header__category-link" :class="{ 'app-header__category-link--active': isCurrent(categoryUrl(category)) }" :aria-current="isCurrent(categoryUrl(category)) ? 'page' : undefined" @click="closeCategories">
                                    <CatalogNodeAvatar :node="category" :size="24" /><span>{{ category.name }}</span>
                                </Link>
                            </div>
                        </details>
                    </div>
                    <details ref="landingDropdown" class="app-header__landing-dropdown" @keydown.esc.stop.prevent="escapeLandings">
                        <summary class="app-header__landing-toggle"><span>Полезные страницы</span><v-icon icon="mdi-chevron-down" size="18" /></summary>
                        <div class="app-header__landing-menu" aria-label="Полезные страницы">
                            <Link v-for="landing in availableLandings" :key="landing.id" :href="landing.url" class="app-header__landing-link" :class="{ 'app-header__landing-link--active': isCurrent(landing.url) }" :aria-current="isCurrent(landing.url) ? 'page' : undefined" @click="closeLandings">{{ landing.title }}</Link>
                            <p v-if="!availableLandings.length" class="app-header__landing-empty">Страницы скоро появятся</p>
                        </div>
                    </details>
                </nav>
            </v-container>
        </div>

        <div id="product-cart" class="app-header__cart-row">
            <v-container class="app-header__container"><OrderCartStrip /></v-container>
        </div>
    </header>
</template>

<style scoped>
.app-header { position: sticky; top: 0; z-index: 20; min-width: 0; background: #f2ebdf; color: #243d32; }
.app-header__container { padding-block: 0; }
.app-header__top { background: #800000; color: #fff; }
.app-header__top-inner { display: grid; grid-template-columns: minmax(260px, 1fr) auto minmax(140px, 200px) auto; align-items: center; gap: 12px; padding-block: 6px; }
.app-header__brand { display: flex; align-items: center; gap: 9px; min-width: 0; color: #fff; text-decoration: none; }
.app-header__logo { flex: 0 0 34px; width: 34px; height: 34px; display: grid; place-items: center; overflow: hidden; border-radius: 50%; background: #fff; color: #800000; font-weight: 800; }
.app-header__logo img { display: block; width: 100%; height: 100%; object-fit: cover; }
.app-header__brand-text { min-width: 0; }
.app-header__brand-text strong { display: block; font-size: 14px; font-weight: 700; letter-spacing: .025em; line-height: 1.2; }
.app-header__brand-text small { display: block; margin-top: 3px; font-size: 10px; line-height: 1.2; color: #f2dfd3; white-space: nowrap; }
.app-header__contacts { display: flex; flex-wrap: wrap; gap: 5px 12px; align-items: center; }
.app-header__contact { display: inline-flex; align-items: center; gap: 6px; color: #fff0ed; font-size: 12px; line-height: 1.5; text-decoration: none; white-space: nowrap; }
.app-header__contact:hover { text-decoration: underline; }
.app-header__city { width: 100%; min-width: 0; }
.app-header__city :deep(.city-selector__button) { width: 100%; min-width: 0; min-height: 36px; padding: 8px 11px; border: 1px solid #ffffff60; border-radius: 2px; background: transparent; font-size: 12px; }
.app-header__city :deep(.city-selector__button:hover) { background: #ffffff10; }
.app-header__city :deep(.city-selector__button:active) { transform: none; }
.app-header__city :deep(.city-selector__text) { min-width: 0; max-width: 100%; }
.app-header__account { display: flex; justify-content: flex-end; align-items: center; gap: 6px; min-width: 0; }
.app-header__login, .app-header__register, .app-header__user { display: inline-flex; justify-content: center; align-items: center; gap: 6px; min-height: 36px; padding: 7px 11px; color: #fff; border-radius: 2px; text-decoration: none; font-size: 12px; font-weight: 600; white-space: nowrap; }
.app-header__register { border: 1px solid #ffffff80; }
.app-header__login:hover, .app-header__register:hover, .app-header__user:hover { background: #ffffff10; }
.app-header__user { max-width: 190px; cursor: pointer; background: transparent; }
.app-header__user span { overflow: hidden; text-overflow: ellipsis; }
.app-header__profile { color: inherit; text-decoration: none; }
.app-header__navigation { background: #f2ebdf; border-bottom: 1px solid #d7cdbd; }
.app-header__nav { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 0 18px; max-width: 100%; padding: 2px 0 0; }
.app-header__nav-link, .app-header__category-toggle, .app-header__landing-toggle { display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 8px 12px; border: 0; border-bottom: 2px solid transparent; border-radius: 0; background: transparent; color: #243d32; text-decoration: none; font-size: 12px; font-weight: 600; letter-spacing: .07em; line-height: 1.3; white-space: nowrap; text-transform: uppercase; transition: color .15s ease, background .15s ease; }
.app-header__nav-link:hover, .app-header__category-toggle:hover, .app-header__landing-toggle:hover { background: #243d3209; color: #82462f; }
.app-header__nav-link--active { border-bottom-color: #b66143; color: #243d32; }
.app-header__catalog { position: relative; display: flex; align-items: stretch; }
.app-header__catalog-link--split { padding-right: 9px; }
.app-header__category-toggle { height: 100%; min-width: 40px; padding-inline: 8px; list-style: none; cursor: pointer; }
.app-header__landing-dropdown { position: relative; min-width: 0; }
.app-header__landing-toggle { list-style: none; cursor: pointer; }
.app-header__category-toggle::-webkit-details-marker, .app-header__landing-toggle::-webkit-details-marker { display: none; }
.app-header__category-dropdown[open] .app-header__category-toggle > .v-icon, .app-header__landing-dropdown[open] .app-header__landing-toggle > .v-icon { transform: rotate(180deg); }
.app-header__category-menu, .app-header__landing-menu { position: absolute; z-index: 30; top: 100%; left: 0; width: 320px; max-width: calc(100vw - 28px); max-height: min(360px, 50vh); overflow-y: auto; overscroll-behavior: contain; padding: 5px 12px; border: 1px solid #b8baa9; border-radius: 2px; background: #f8f3e9; }
.app-header__landing-menu { left: auto; right: 0; }
.app-header__category-link, .app-header__landing-link { display: flex; align-items: center; gap: 10px; min-height: 44px; padding: 10px 3px; border-bottom: 1px solid #ded7c9; color: #243d32; text-decoration: none; font-size: 14px; line-height: 1.4; overflow-wrap: anywhere; }
.app-header__category-link:last-child, .app-header__landing-link:last-child { border-bottom: 0; }
.app-header__category-link span { min-width: 0; overflow-wrap: anywhere; }
.app-header__category-link:hover, .app-header__category-link--active, .app-header__landing-link:hover, .app-header__landing-link--active { background: #ebe3d4; color: #82462f; }
.app-header__category-link--active, .app-header__landing-link--active { font-weight: 600; }
.app-header__landing-empty { margin: 0; padding: 12px 3px; color: #59634f; font-size: 13px; line-height: 1.5; }
.app-header__cart-row { padding-block: 4px; background: #f2ebdf; border-bottom: 1px solid #d7cdbd; }
.app-header :is(a, button, summary):focus-visible, .app-header__city :deep(button:focus-visible) { outline: 2px solid #b66143; outline-offset: 3px; }
.app-header__top :is(a, button):focus-visible, .app-header__city :deep(button:focus-visible) { outline-color: #f2ebdf; }
@media (max-width: 1279px) and (min-width: 960px) {
    .app-header__top-inner { grid-template-columns: minmax(250px, 1fr) auto minmax(140px, 190px) auto; gap: 10px; }
    .app-header__contacts { flex-direction: column; align-items: flex-start; gap: 3px; }
}
@media (max-width: 959px) {
    .app-header { position: relative; }
    .app-header__top-inner { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: 'brand account' 'contacts city'; gap: 6px 12px; padding-block: 6px; }
    .app-header__brand { grid-area: brand; }
    .app-header__contacts { grid-area: contacts; }
    .app-header__city { grid-area: city; justify-self: end; max-width: 280px; }
    .app-header__account { grid-area: account; }
    .app-header__brand-text small { max-width: none; }
    .app-header__nav-link, .app-header__category-toggle { min-height: 40px; }
}
@media (max-width: 599px) {
    .app-header__container { padding-inline: 14px; }
    .app-header__top-inner { gap: 5px 8px; }
    .app-header__brand { display: grid; grid-template-columns: 26px minmax(0, 1fr); gap: 3px 6px; }
    .app-header__logo { width: 26px; height: 26px; }
    .app-header__brand-text { display: contents; }
    .app-header__brand-text strong { font-size: 12px; }
    .app-header__brand-text small { grid-column: 1 / -1; margin: 0; font-size: 10px; }
    .app-header__account { flex-direction: column; align-items: stretch; gap: 0; }
    .app-header__login, .app-header__register { min-height: 25px; padding: 3px 6px; font-size: 10px; }
    .app-header__contacts { flex-direction: column; align-items: flex-start; gap: 3px; }
    .app-header__contact { gap: 5px; font-size: 11px; }
    .app-header__city { width: 160px; justify-self: end; }
    .app-header__city :deep(.city-selector__button) { min-height: 30px; padding: 6px; font-size: 11px; }
    .app-header__user { max-width: 100px; padding-inline: 6px; }
    .app-header__nav { gap: 0 8px; padding-top: 1px; }
    .app-header__nav-link, .app-header__landing-toggle { padding-inline: 8px; font-size: 11px; }
    .app-header__catalog, .app-header__landing-dropdown { position: static; }
    .app-header__category-menu, .app-header__landing-menu { top: 100%; width: 100%; }
}
@media (max-width: 399px) {
    .app-header__top-inner { grid-template-columns: minmax(0, 1fr) 84px; }
    .app-header__brand-text small { font-size: 9.5px; }
    .app-header__account { justify-self: end; }
    .app-header__user { max-width: 84px; }
    .app-header__user > .v-icon:last-child { display: none; }
}
@media (prefers-reduced-motion: reduce) { .app-header__nav-link, .app-header__category-toggle, .app-header__landing-toggle { transition: none; } }
</style>
