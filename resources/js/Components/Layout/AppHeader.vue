<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { logo } from '@/Pages/Helpers/consts.js'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)

const search = ref('')
const isCompact = ref(false)

const quickLinks = [
    { label: 'Рыба', href: route('category.show', 25) },
    { label: 'Овощи', href: route('category.show', 30) },
    { label: 'Бакалея', href: route('category.show', 31) },
]

const mainContacts = [
    { label: '+7-965-016-00-01', href: 'tel:+79650160001' },
    { label: 'office@180022.ru', href: 'mailto:office@180022.ru' },
]

function submitSearch() {
    router.get(route('goods.published'), {
        search: search.value?.trim() || '',
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function handleScroll() {
    isCompact.value = window.scrollY > 24
}

onMounted(() => {
    handleScroll()
    window.addEventListener('scroll', handleScroll, { passive: true })
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
    <header :class="['app-header', { 'app-header--compact': isCompact }]">
        <div class="app-header__top">
            <v-container class="py-0">
                <div class="app-header__top-inner">
                    <div class="app-header__contacts">
                        <a
                            v-for="contact in mainContacts"
                            :key="contact.href"
                            :href="contact.href"
                            class="app-header__contact"
                        >
                            {{ contact.label }}
                        </a>
                    </div>

                    <div class="app-header__auth">
                        <template v-if="user">
                            <div class="app-header__user">
                                <v-avatar color="white" size="30">
                                    <span class="text-red-darken-4 font-weight-bold">
                                        {{ user.name?.[0] || 'U' }}
                                    </span>
                                </v-avatar>

                                <div class="app-header__user-meta">
                                    <div class="app-header__user-name">
                                        {{ user.name }}
                                    </div>
                                </div>

                                <Link :href="route('dashboard')">
                                    <v-btn
                                        color="white"
                                        variant="outlined"
                                        rounded="xl"
                                        size="small"
                                    >
                                        Кабинет
                                    </v-btn>
                                </Link>
                            </div>
                        </template>

                        <template v-else>
                            <div class="app-header__guest">
                                <Link :href="route('login')">
                                    <v-btn color="white" variant="text" size="small">
                                        Войти
                                    </v-btn>
                                </Link>

                                <Link
                                    v-if="page.props.canRegister"
                                    :href="route('register')"
                                >
                                    <v-btn
                                        color="white"
                                        variant="flat"
                                        rounded="xl"
                                        size="small"
                                        class="app-header__register-btn"
                                    >
                                        Регистрация
                                    </v-btn>
                                </Link>
                            </div>
                        </template>
                    </div>
                </div>
            </v-container>
        </div>

        <div class="app-header__main">
            <v-container class="py-0">
                <div class="app-header__main-inner">
                    <div class="app-header__brand">
                        <Link href="/" class="app-header__logo-link">
                            <div class="app-header__logo-shell">
                                <img
                                    :src="logo"
                                    alt="ПИЩЕПРОМ-СЕРВЕР"
                                    class="app-header__logo"
                                >
                            </div>
                        </Link>

                        <div class="app-header__brand-text">
                            <Link href="/" class="app-header__brand-title">
                                ПИЩЕПРОМ-СЕРВЕР
                            </Link>
                            <div class="app-header__brand-subtitle">
                                Маркетплейс для пищевой промышленности
                            </div>
                        </div>
                    </div>

                    <div class="app-header__nav">
                        <Link :href="route('goods.published')" class="app-header__catalog-link">
                            Все товары
                        </Link>

                        <v-menu>
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

                            <v-list min-width="260">
                                <v-list-item
                                    v-for="category in categories"
                                    :key="category.id"
                                >
                                    <Link
                                        :href="route('category.show', category.id)"
                                        class="text-decoration-none text-high-emphasis"
                                    >
                                        {{ category.name }}
                                    </Link>
                                </v-list-item>
                            </v-list>
                        </v-menu>

                        <nav class="app-header__quick-links">
                            <Link
                                v-for="item in quickLinks"
                                :key="item.label"
                                :href="item.href"
                                class="app-header__quick-link"
                            >
                                {{ item.label }}
                            </Link>
                        </nav>
                    </div>

                    <div class="app-header__search">
                        <v-text-field
                            v-model="search"
                            placeholder="Поиск по товарам"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                            clearable
                            rounded="xl"
                            flat
                            bg-color="white"
                            prepend-inner-icon="mdi-magnify"
                            @keyup.enter="submitSearch"
                        />

                        <v-btn
                            color="#800000"
                            rounded="xl"
                            class="app-header__search-btn"
                            @click="submitSearch"
                        >
                            Найти
                        </v-btn>
                    </div>
                </div>
            </v-container>
        </div>
    </header>
</template>

<style scoped>
.app-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #fff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    transition: box-shadow 0.2s ease;
}

.app-header__top {
    background: linear-gradient(90deg, #6b0000 0%, #800000 50%, #6b0000 100%);
    color: #fff7ed;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.app-header__top-inner {
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.app-header__contacts {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
}

.app-header__contact {
    color: #ffedd5;
    text-decoration: none;
    font-size: 0.82rem;
    line-height: 1.2;
    opacity: 0.95;
}

.app-header__contact:hover {
    opacity: 1;
}

.app-header__auth {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.app-header__guest {
    display: flex;
    align-items: center;
    gap: 8px;
}

.app-header__register-btn {
    color: #7f1d1d !important;
    font-weight: 700;
}

.app-header__user {
    display: flex;
    align-items: center;
    gap: 8px;
}

.app-header__user-meta {
    display: flex;
    align-items: center;
}

.app-header__user-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: #fff;
}

.app-header__main {
    background: #fffaf8;
}

.app-header__main-inner {
    min-height: 72px;
    display: grid;
    grid-template-columns: auto 1fr minmax(280px, 420px);
    align-items: center;
    gap: 20px;
    padding: 10px 0;
}

.app-header__brand {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.app-header__logo-link {
    text-decoration: none;
    flex-shrink: 0;
}

.app-header__logo-shell {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid rgba(128, 0, 0, 0.12);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.app-header__logo {
    width: 78%;
    height: 78%;
    object-fit: contain;
    display: block;
}

.app-header__brand-text {
    min-width: 0;
}

.app-header__brand-title {
    display: inline-block;
    color: #7f1d1d;
    text-decoration: none;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.15;
}

.app-header__brand-subtitle {
    font-size: 0.8rem;
    color: #7c6f6a;
    line-height: 1.2;
    margin-top: 2px;
}

.app-header__nav {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.app-header__catalog-link {
    color: #7f1d1d;
    text-decoration: none;
    font-weight: 700;
    white-space: nowrap;
}

.app-header__quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.app-header__quick-link {
    color: #7f1d1d;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.84rem;
    padding: 5px 10px;
    border: 1px solid rgba(128, 0, 0, 0.14);
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.04);
    transition: all 0.2s ease;
}

.app-header__quick-link:hover {
    background: rgba(128, 0, 0, 0.08);
}

.app-header__search {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: center;
}

.app-header__search-btn {
    height: 40px;
    min-width: 98px;
    font-weight: 700;
}

/* compact */
.app-header--compact {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
}

.app-header--compact .app-header__top-inner {
    min-height: 34px;
}

.app-header--compact .app-header__main-inner {
    min-height: 64px;
    padding: 8px 0;
}

.app-header--compact .app-header__logo-shell {
    width: 48px;
    height: 48px;
    border-radius: 14px;
}

.app-header--compact .app-header__brand-title {
    font-size: 0.95rem;
}

.app-header--compact .app-header__brand-subtitle {
    font-size: 0.76rem;
}

@media (max-width: 1264px) {
    .app-header__main-inner {
        grid-template-columns: auto 1fr;
    }

    .app-header__search {
        grid-column: 1 / -1;
    }
}

@media (max-width: 960px) {
    .app-header {
        position: relative;
    }

    .app-header__top-inner {
        flex-direction: column;
        justify-content: center;
        padding: 8px 0;
    }

    .app-header__contacts,
    .app-header__auth {
        justify-content: center;
    }

    .app-header__main-inner {
        grid-template-columns: 1fr;
        gap: 14px;
        padding: 12px 0;
    }

    .app-header__brand {
        justify-content: center;
        text-align: center;
    }

    .app-header__nav {
        justify-content: center;
    }
}

@media (max-width: 600px) {
    .app-header__contacts,
    .app-header__quick-links,
    .app-header__guest,
    .app-header__user {
        justify-content: center;
        flex-wrap: wrap;
    }

    .app-header__user {
        flex-direction: column;
    }

    .app-header__search {
        grid-template-columns: 1fr;
    }

    .app-header__search-btn {
        width: 100%;
    }

    .app-header__brand {
        flex-direction: column;
    }
}
</style>
