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
    router.get(route('goods'), {
        search: search.value?.trim() || '',
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function handleScroll() {
    isCompact.value = window.scrollY > 40
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
    <header
        :class="['app-header', { 'app-header--compact': isCompact }]"
    >
        <div class="app-header__top">
            <v-container class="py-0">
                <div class="app-header__inner">
                    <div class="app-header__left">
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

                    <div class="app-header__center">
                        <Link href="/" class="app-header__logo-link">
                            <div class="app-header__logo-shell">
                                <img
                                    :src="logo"
                                    alt="ПИЩЕПРОМ-СЕРВЕР"
                                    class="app-header__logo"
                                >
                            </div>
                        </Link>
                    </div>

                    <div class="app-header__right">
                        <template v-if="user">
                            <div class="app-header__user">
                                <v-avatar color="white" size="38">
                                    <span class="text-red-darken-4 font-weight-bold">
                                        {{ user.name?.[0] || 'U' }}
                                    </span>
                                </v-avatar>

                                <div class="app-header__user-meta">
                                    <div class="app-header__user-name">
                                        {{ user.name }}
                                    </div>
                                    <div class="app-header__user-email">
                                        {{ user.email }}
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

        <div class="app-header__bottom">
            <v-container class="py-0">
                <div class="app-header__bottom-inner">
                    <div class="app-header__left-bottom">
                        <Link :href="route('goods')" class="app-header__catalog-link">
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
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.app-header__top {
    background: linear-gradient(90deg, #5c0000 0%, #800000 45%, #6d0000 100%);
    color: #fff7ed;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: background 0.25s ease, box-shadow 0.25s ease;
}

.app-header__inner {
    min-height: 82px;
    display: grid;
    grid-template-columns: 1fr 160px 1fr;
    align-items: center;
    gap: 18px;
    transition: min-height 0.25s ease, gap 0.25s ease, padding 0.25s ease;
}

.app-header__left {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
    transition: gap 0.25s ease;
}

.app-header__contacts {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 12px;
    transition: gap 0.25s ease;
}

.app-header__contact {
    color: #ffedd5;
    text-decoration: none;
    font-size: 0.84rem;
    line-height: 1.2;
    opacity: 0.95;
    transition: font-size 0.25s ease, opacity 0.2s ease;
}

.app-header__contact:hover {
    opacity: 1;
}

.app-header__quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    transition: gap 0.25s ease;
}

.app-header__quick-link {
    color: white;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.88rem;
    padding: 6px 10px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.07);
    transition: all 0.25s ease;
}

.app-header__quick-link:hover {
    background: rgba(255, 255, 255, 0.14);
    transform: translateY(-1px);
}

.app-header__center {
    display: flex;
    justify-content: center;
    align-items: center;
}

.app-header__logo-link {
    text-decoration: none;
}

.app-header__logo-shell {
    width: 128px;
    height: 128px;
    margin-top: 10px;
    margin-bottom: -10px;
    border-radius: 50%;
    background: #fffaf5;
    box-shadow:
        0 8px 24px rgba(0, 0, 0, 0.14),
        inset 0 0 0 6px rgba(128, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition:
        width 0.25s ease,
        height 0.25s ease,
        margin-top 0.25s ease,
        margin-bottom 0.25s ease,
        box-shadow 0.25s ease;
}

.app-header__logo {
    width: 78%;
    height: auto;
    object-fit: contain;
    display: block;
}

.app-header__right {
    display: flex;
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
    gap: 10px;
}

.app-header__user-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    max-width: 190px;
}

.app-header__user-name {
    font-weight: 700;
    font-size: 0.95rem;
    line-height: 1.1;
}

.app-header__user-email {
    font-size: 0.8rem;
    color: #fed7aa;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 190px;
}

.app-header__bottom {
    background: #fffaf8;
    border-bottom: 1px solid rgba(128, 0, 0, 0.12);
    transition: background 0.25s ease, border-color 0.25s ease;
}

.app-header__bottom-inner {
    min-height: 52px;
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    gap: 16px;
    transition: min-height 0.25s ease, gap 0.25s ease, padding 0.25s ease;
}

.app-header__left-bottom {
    display: flex;
    align-items: center;
    gap: 12px;
}

.app-header__catalog-link {
    color: #7f1d1d;
    text-decoration: none;
    font-weight: 700;
    white-space: nowrap;
}

.app-header__search {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    align-items: center;
}

.app-header__search-btn {
    height: 40px;
    min-width: 104px;
    font-weight: 700;
}

/* compact state */
.app-header--compact {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
}

.app-header--compact .app-header__inner {
    min-height: 64px;
    gap: 14px;
}

.app-header--compact .app-header__left {
    gap: 4px;
}

.app-header--compact .app-header__contacts {
    gap: 4px 10px;
}

.app-header--compact .app-header__contact {
    font-size: 0.78rem;
}

.app-header--compact .app-header__quick-links {
    gap: 6px;
}

.app-header--compact .app-header__quick-link {
    padding: 4px 8px;
    font-size: 0.8rem;
}

.app-header--compact .app-header__logo-shell {
    width: 92px;
    height: 92px;
    margin-top: 4px;
    margin-bottom: -4px;
    box-shadow:
        0 6px 16px rgba(0, 0, 0, 0.12),
        inset 0 0 0 5px rgba(128, 0, 0, 0.08);
}

.app-header--compact .app-header__user-name {
    font-size: 0.88rem;
}

.app-header--compact .app-header__user-email {
    font-size: 0.75rem;
}

.app-header--compact .app-header__bottom-inner {
    min-height: 44px;
    gap: 12px;
}

.app-header--compact .app-header__search-btn {
    height: 36px;
    min-width: 96px;
}

@media (max-width: 1264px) {
    .app-header__inner {
        grid-template-columns: 1fr 140px 1fr;
    }

    .app-header__logo-shell {
        width: 112px;
        height: 112px;
    }

    .app-header--compact .app-header__logo-shell {
        width: 84px;
        height: 84px;
    }
}

@media (max-width: 960px) {
    .app-header {
        position: relative;
        top: auto;
    }

    .app-header__inner {
        grid-template-columns: 1fr;
        text-align: center;
        padding-top: 14px;
        padding-bottom: 14px;
    }

    .app-header__left,
    .app-header__right {
        align-items: center;
        justify-content: center;
    }

    .app-header__right {
        justify-content: center;
    }

    .app-header__user-meta {
        align-items: center;
    }

    .app-header__center {
        order: -1;
    }

    .app-header__logo-shell {
        width: 104px;
        height: 104px;
        margin-top: 0;
        margin-bottom: 0;
    }

    .app-header--compact .app-header__logo-shell {
        width: 104px;
        height: 104px;
        margin-top: 0;
        margin-bottom: 0;
    }

    .app-header__bottom-inner {
        grid-template-columns: 1fr;
        padding: 12px 0;
    }

    .app-header__left-bottom {
        justify-content: center;
        flex-wrap: wrap;
    }
}

@media (max-width: 600px) {
    .app-header__contacts,
    .app-header__quick-links,
    .app-header__guest,
    .app-header__user {
        justify-content: center;
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
}
</style>
