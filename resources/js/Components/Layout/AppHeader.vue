<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import CitySelector from '@/Components/Location/CitySelector.vue'
import { useAppRoute } from '@/Composables/useAppRoute'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})

const inertiaPage = usePage()

const {
    route,
} = useAppRoute()

const drawer = ref(false)
const categoryMenu = ref(false)
const accountMenu = ref(false)
const isCompact = ref(false)

const siteName = 'ПИЩЕПРОМ-СЕРВЕР'
const siteSubtitle = 'Маркетплейс для пищевой промышленности'


//   L O G O
const logoUrl = 'https://storage.yandexcloud.net/pps/images/logo_%D0%BF%D0%B8%D1%89%D0%B5%D0%BF%D1%80%D0%BE%D0%BC-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80_1200%D1%851207.jpg'

const logoFailed = ref(false)

const showLogoImage = computed(() => {
    return Boolean(logoUrl) && !logoFailed.value
})

function markLogoFailed() {
    logoFailed.value = true
}



const contacts = [
    {
        label: '+7-965-016-00-01',
        href: 'tel:+79650160001',
        icon: 'mdi-phone',
    },
    {
        label: 'office@180022.ru',
        href: 'mailto:office@180022.ru',
        icon: 'mdi-email-outline',
    },
]

const quickLinkFallbacks = [
    { label: 'Рыба', id: 25 },
    { label: 'Овощи', id: 30 },
    { label: 'Бакалея', id: 31 },
]

const quickLinks = computed(() => {
    return quickLinkFallbacks.map((item) => {
        const category = availableCategories.value.find((candidate) => {
            return String(candidate.name || '')
                .toLowerCase()
                .includes(item.label.toLowerCase())
        })

        return {
            label: item.label,
            href: route('category.show', category?.slug || category?.id || item.id),
        }
    })
})

const user = computed(() => {
    return inertiaPage.props.auth?.user ?? null
})

const canLogin = computed(() => {
    return Boolean(inertiaPage.props.canLogin)
})

const canRegister = computed(() => {
    return Boolean(inertiaPage.props.canRegister)
})

const currentCity = computed(() => {
    return inertiaPage.props.location?.city ?? null
})

const availableCategories = computed(() => {
    return Array.isArray(props.categories)
        ? props.categories.filter((category) => category?.id && category?.name)
        : []
})

const visibleCategories = computed(() => {
    return availableCategories.value.slice(0, 18)
})

const hasCategories = computed(() => {
    return visibleCategories.value.length > 0
})

const homeUrl = computed(() => {
    return route('home')
})

const goodsIndexUrl = computed(() => {
    return route('public.goods.index')
})

const profileUrl = computed(() => {
    if (hasRoute('customer.profile.edit')) {
        return route('customer.profile.edit')
    }

    if (hasRoute('profile.show')) {
        return route('profile.show')
    }

    if (hasRoute('dashboard')) {
        return route('dashboard')
    }

    return '/dashboard'
})

const loginUrl = computed(() => {
    return hasRoute('login')
        ? route('login')
        : '/login'
})

const registerUrl = computed(() => {
    return hasRoute('register')
        ? route('register')
        : '/register'
})

function hasRoute(name) {
    const ziggy = inertiaPage.props.ziggy || globalThis.Ziggy || {}

    return Boolean(ziggy.routes?.[name])
}

function categoryUrl(category) {
    return route('category.show', category.slug || category.id)
}

function handleScroll() {
    if (typeof window === 'undefined') {
        return
    }

    isCompact.value = window.scrollY > 24
}

function closeDrawer() {
    drawer.value = false
}

function logout() {
    if (!hasRoute('logout')) {
        return
    }

    accountMenu.value = false
    drawer.value = false

    router.post(
        route('logout'),
        {},
        {
            preserveScroll: true,
        },
    )
}

onMounted(() => {
    handleScroll()

    window.addEventListener('scroll', handleScroll, {
        passive: true,
    })
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return
    }

    window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
    <header
        class="app-header"
        :class="{
            'app-header--compact': isCompact,
        }"
    >
        <div class="app-header__top">
            <v-container class="py-0">
                <div class="app-header__top-inner">
                    <div class="app-header__contacts">
                        <a
                            v-for="contact in contacts"
                            :key="contact.href"
                            :href="contact.href"
                            class="app-header__contact"
                        >
                            <v-icon
                                :icon="contact.icon"
                                size="15"
                                class="app-header__contact-icon"
                            />

                            <span>
                                {{ contact.label }}
                            </span>
                        </a>
                    </div>

                    <CitySelector
                        class="app-header__city"
                        compact
                    />

                    <div class="app-header__account">
                        <template v-if="user">
                            <v-menu
                                v-model="accountMenu"
                                location="bottom end"
                                transition="scale-transition"
                            >
                                <template #activator="{ props: menuProps }">
                                    <button
                                        v-bind="menuProps"
                                        type="button"
                                        class="app-header__user"
                                    >
                                        <v-avatar size="30">
                                            <v-img
                                                v-if="user.profile_photo_url"
                                                :src="user.profile_photo_url"
                                                :alt="user.name"
                                                cover
                                            />

                                            <v-icon
                                                v-else
                                                icon="mdi-account-circle"
                                                size="30"
                                            />
                                        </v-avatar>

                                        <span class="app-header__user-name">
                                            {{ user.name }}
                                        </span>

                                        <v-icon
                                            icon="mdi-chevron-down"
                                            size="18"
                                        />
                                    </button>
                                </template>

                                <v-card
                                    min-width="220"
                                    rounded="lg"
                                >
                                    <v-list density="compact">
                                        <v-list-item
                                            :href="profileUrl"
                                            prepend-icon="mdi-account-outline"
                                            title="Кабинет"
                                        />

                                        <v-divider />

                                        <v-list-item
                                            prepend-icon="mdi-logout"
                                            title="Выйти"
                                            @click="logout"
                                        />
                                    </v-list>
                                </v-card>
                            </v-menu>
                        </template>

                        <template v-else>
                            <Link
                                v-if="canLogin"
                                :href="loginUrl"
                                class="app-header__login"
                            >
                                Войти
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="registerUrl"
                                class="app-header__register"
                            >
                                Регистрация
                            </Link>
                        </template>
                    </div>

                    <v-btn
                        class="app-header__burger"
                        icon="mdi-menu"
                        variant="text"
                        color="white"
                        aria-label="Открыть меню"
                        @click="drawer = true"
                    />
                </div>
            </v-container>
        </div>

        <div class="app-header__main">
            <v-container>
                <div class="app-header__main-inner">
                    <Link
                        :href="homeUrl"
                        class="app-header__brand"
                    >
                        <div class="app-header__logo">
                            <img
                                v-if="showLogoImage"
                                :src="logoUrl"
                                :alt="siteName"
                                width="54"
                                height="54"
                                class="app-header__logo-image"
                                loading="eager"
                                decoding="async"
                                @error="markLogoFailed"
                            >

                            <div
                                v-else
                                class="app-header__logo-fallback"
                            >
                                ПС
                            </div>
                        </div>

                        <div class="app-header__brand-text">
                            <div class="app-header__brand-title">
                                {{ siteName }}
                            </div>

                            <div class="app-header__brand-subtitle">
                                {{ siteSubtitle }}
                            </div>
                        </div>
                    </Link>

                    <nav class="app-header__nav">
                        <Link
                            :href="goodsIndexUrl"
                            class="app-header__nav-link app-header__nav-link--primary"
                        >
                            Все товары
                        </Link>

                        <v-menu
                            v-model="categoryMenu"
                            location="bottom"
                            transition="scale-transition"
                            :close-on-content-click="false"
                        >
                            <template #activator="{ props: menuProps }">
                                <v-btn
                                    v-bind="menuProps"
                                    color="#800000"
                                    variant="tonal"
                                    rounded="xl"
                                    size="small"
                                >
                                    Категории
                                </v-btn>
                            </template>

                            <v-card
                                min-width="280"
                                max-width="420"
                                rounded="xl"
                            >
                                <v-card-title class="text-subtitle-1 font-weight-bold">
                                    Категории
                                </v-card-title>

                                <v-divider />

                                <v-list
                                    v-if="hasCategories"
                                    density="compact"
                                    nav
                                >
                                    <Link
                                        v-for="category in visibleCategories"
                                        :key="category.id"
                                        :href="categoryUrl(category)"
                                        class="app-header__category-link"
                                        @click="categoryMenu = false"
                                    >
                                        <v-list-item
                                            :title="category.name"
                                            prepend-icon="mdi-shape-outline"
                                        />
                                    </Link>
                                </v-list>

                                <v-card-text
                                    v-else
                                    class="text-body-2 text-medium-emphasis"
                                >
                                    Категории скоро появятся.
                                </v-card-text>
                            </v-card>
                        </v-menu>

                        <div class="app-header__quick-links">
                            <Link
                                v-for="item in quickLinks"
                                :key="item.label"
                                :href="item.href"
                                class="app-header__quick-link"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </nav>
                </div>
            </v-container>
        </div>

        <Teleport to="body">
            <Transition name="app-header-drawer">
                <div
                    v-if="drawer"
                    class="app-header__drawer-overlay"
                    @click.self="closeDrawer"
                >
                    <aside class="app-header__drawer-panel">
                        <div class="app-header__drawer-head">
                            <div>
                                <div class="app-header__drawer-title">
                                    {{ siteName }}
                                </div>

                                <div class="app-header__drawer-subtitle">
                                    {{ currentCity?.name ? `Доставка в ${currentCity.name}` : siteSubtitle }}
                                </div>
                            </div>

                            <v-btn
                                icon="mdi-close"
                                variant="text"
                                aria-label="Закрыть меню"
                                @click="closeDrawer"
                            />
                        </div>

                        <v-divider />

                        <div class="app-header__drawer-content">
                            <Link
                                :href="homeUrl"
                                class="app-header__drawer-link"
                                @click="closeDrawer"
                            >
                                <v-icon icon="mdi-home-outline" size="20" />
                                <span>Главная</span>
                            </Link>

                            <Link
                                :href="goodsIndexUrl"
                                class="app-header__drawer-link"
                                @click="closeDrawer"
                            >
                                <v-icon icon="mdi-storefront-outline" size="20" />
                                <span>Все товары</span>
                            </Link>

                            <div
                                v-if="hasCategories"
                                class="app-header__drawer-section"
                            >
                                <div class="app-header__drawer-section-title">
                                    Категории
                                </div>

                                <Link
                                    v-for="category in visibleCategories"
                                    :key="category.id"
                                    :href="categoryUrl(category)"
                                    class="app-header__drawer-link"
                                    @click="closeDrawer"
                                >
                                    <v-icon icon="mdi-shape-outline" size="20" />
                                    <span>{{ category.name }}</span>
                                </Link>
                            </div>

                            <div class="app-header__drawer-section">
                                <CitySelector />
                            </div>

                            <div class="app-header__drawer-section">
                                <template v-if="user">
                                    <Link
                                        :href="profileUrl"
                                        class="app-header__drawer-link"
                                        @click="closeDrawer"
                                    >
                                        <v-icon icon="mdi-account-outline" size="20" />
                                        <span>Кабинет</span>
                                    </Link>

                                    <button
                                        type="button"
                                        class="app-header__drawer-link app-header__drawer-button"
                                        @click="logout"
                                    >
                                        <v-icon icon="mdi-logout" size="20" />
                                        <span>Выйти</span>
                                    </button>
                                </template>

                                <template v-else>
                                    <Link
                                        v-if="canLogin"
                                        :href="loginUrl"
                                        class="app-header__drawer-link"
                                        @click="closeDrawer"
                                    >
                                        <v-icon icon="mdi-login" size="20" />
                                        <span>Войти</span>
                                    </Link>

                                    <Link
                                        v-if="canRegister"
                                        :href="registerUrl"
                                        class="app-header__drawer-link"
                                        @click="closeDrawer"
                                    >
                                        <v-icon icon="mdi-account-plus-outline" size="20" />
                                        <span>Регистрация</span>
                                    </Link>
                                </template>
                            </div>
                        </div>
                    </aside>
                </div>
            </Transition>
        </Teleport>
    </header>
</template>

<style scoped>
.app-header {
    position: sticky;
    top: 0;
    z-index: 20;
    background: #fffaf8;
    box-shadow: 0 6px 24px rgba(31, 41, 55, 0.08);
}

.app-header__top {
    background: #800000;
    color: #fff;
}

.app-header__top-inner {
    min-height: 52px;
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 18px;
}

.app-header__contacts {
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 0;
}

.app-header__contact {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #fff;
    text-decoration: none;
    font-size: 0.88rem;
    white-space: nowrap;
    opacity: 0.95;
}

.app-header__contact:hover {
    opacity: 1;
    text-decoration: underline;
}

.app-header__contact-icon {
    opacity: 0.86;
}

.app-header__city {
    justify-self: center;
}

.app-header__account {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.app-header__user {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 0;
    background: transparent;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    padding: 4px 0;
}

.app-header__user-name {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.app-header__login,
.app-header__register {
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
}

.app-header__register {
    border: 1px solid rgba(255, 255, 255, 0.75);
    padding: 7px 14px;
    border-radius: 8px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 0.75rem;
}

.app-header__burger {
    display: none;
    justify-self: end;
}

.app-header__main {
    background: rgba(255, 250, 248, 0.96);
    backdrop-filter: blur(10px);
    transition: padding 0.2s ease;
}

.app-header__main-inner {
    min-height: 84px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    transition: min-height 0.2s ease;
}

.app-header--compact .app-header__main-inner {
    min-height: 70px;
}

.app-header__brand {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    text-decoration: none;
    min-width: 0;
}

.app-header__logo {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 8px 20px rgba(128, 0, 0, 0.12);
    flex: 0 0 auto;
}

.app-header__logo-image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.app-header__logo-fallback {
    width: 54px;
    height: 54px;
    display: grid;
    place-items: center;
    background: #800000;
    color: #fff;
    font-weight: 800;
}

.app-header__brand-title {
    color: #8b1e1e;
    font-weight: 900;
    font-size: 1.02rem;
    line-height: 1.2;
    text-transform: uppercase;
}

.app-header__brand-subtitle {
    margin-top: 4px;
    color: #756c67;
    font-size: 0.88rem;
    line-height: 1.2;
}

.app-header__nav {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.app-header__nav-link,
.app-header__quick-link {
    color: #1f1f1f;
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
}

.app-header__nav-link--primary {
    color: #7f1d1d;
    font-weight: 800;
}

.app-header__quick-links {
    display: flex;
    align-items: center;
    gap: 10px;
}

.app-header__quick-link:hover,
.app-header__nav-link:hover {
    color: #800000;
}

.app-header__category-link {
    color: inherit;
    text-decoration: none;
    display: block;
}

.app-header__drawer-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    justify-content: flex-end;
    background: rgba(17, 24, 39, 0.48);
}

.app-header__drawer-panel {
    width: min(320px, 92vw);
    min-height: 100vh;
    background: #fff;
    color: #1f2937;
    box-shadow: -16px 0 40px rgba(17, 24, 39, 0.22);
    overflow: auto;
}

.app-header__drawer-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding: 18px 16px;
}

.app-header__drawer-title {
    color: #8b1e1e;
    font-weight: 900;
    text-transform: uppercase;
}

.app-header__drawer-subtitle {
    margin-top: 4px;
    color: #6b625d;
    font-size: 0.88rem;
}

.app-header__drawer-content {
    padding: 12px 0 22px;
}

.app-header__drawer-section {
    padding: 12px 0;
    border-top: 1px solid rgba(128, 0, 0, 0.08);
}

.app-header__drawer-section-title {
    padding: 0 18px 8px;
    color: #8b1e1e;
    font-weight: 800;
    font-size: 0.86rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.app-header__drawer-link {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 11px 18px;
    color: #1f2937;
    text-decoration: none;
    font-weight: 600;
}

.app-header__drawer-link:hover {
    background: rgba(128, 0, 0, 0.06);
    color: #800000;
}

.app-header__drawer-button {
    border: 0;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font: inherit;
}

.app-header-drawer-enter-active,
.app-header-drawer-leave-active {
    transition: opacity 0.18s ease;
}

.app-header-drawer-enter-from,
.app-header-drawer-leave-to {
    opacity: 0;
}

@media (max-width: 960px) {
    .app-header__top-inner {
        grid-template-columns: 1fr auto;
    }

    .app-header__contacts,
    .app-header__account,
    .app-header__nav {
        display: none;
    }

    .app-header__city {
        justify-self: start;
    }

    .app-header__burger {
        display: inline-flex;
    }

    .app-header__main-inner {
        min-height: 76px;
    }

    .app-header__brand-subtitle {
        font-size: 0.8rem;
    }
}

@media (max-width: 600px) {
    .app-header__top-inner {
        min-height: 46px;
    }

    .app-header__main-inner {
        min-height: 68px;
    }

    .app-header__logo {
        width: 46px;
        height: 46px;
        border-radius: 14px;
    }

    .app-header__logo-fallback {
        width: 46px;
        height: 46px;
    }

    .app-header__brand-title {
        font-size: 0.92rem;
    }

    .app-header__brand-subtitle {
        display: none;
    }
}
</style>
