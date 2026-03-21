<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
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

const quickLinks = [
    {
        label: 'Рыба',
        href: '/Seaprom',
    },
    {
        label: 'Овощи',
        href: route('categories.show', 30),
    },
    {
        label: 'Бакалея',
        href: '/grocery',
    },
]

const mainContacts = [
    {
        label: '+7-965-016-0001',
        href: 'tel:+79650160001',
    },
    {
        label: 'office@180022.ru',
        href: 'mailto:office@180022.ru',
    },
]
</script>

<template>
    <header class="app-header">
        <div class="app-header__top">
            <v-container class="py-0">
                <div class="app-header__inner">
                    <!-- Левая зона -->
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

                    <!-- Центр -->
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

                    <!-- Правая зона -->
                    <div class="app-header__right">
                        <template v-if="user">
                            <div class="app-header__user">
                                <v-avatar
                                    color="white"
                                    size="42"
                                >
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
                                    >
                                        Кабинет
                                    </v-btn>
                                </Link>
                            </div>
                        </template>

                        <template v-else>
                            <div class="app-header__guest">
                                <Link :href="route('login')">
                                    <v-btn
                                        color="white"
                                        variant="text"
                                    >
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

        <!-- Дополнительная нижняя полоса -->
        <div class="app-header__bottom">
            <v-container class="py-0">
                <div class="app-header__bottom-inner">
                    <div class="app-header__catalog-link">
                        <Link :href="route('goods')">Все товары</Link>
                    </div>

                    <div class="app-header__categories">
                        <v-menu>
                            <template #activator="{ props: menuProps }">
                                <v-btn
                                    v-bind="menuProps"
                                    color="primary"
                                    variant="tonal"
                                    rounded="xl"
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
                                        :href="route('Categories', category.id)"
                                        class="text-decoration-none text-high-emphasis"
                                    >
                                        {{ category.name }}
                                    </Link>
                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </div>
                </div>
            </v-container>
        </div>
    </header>
</template>

<style scoped>
.app-header {
    position: relative;
    z-index: 20;
}

.app-header__top {
    background: linear-gradient(90deg, #5c0000 0%, #800000 45%, #6d0000 100%);
    color: #fff7ed;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.12);
}

.app-header__inner {
    min-height: 80px;
    display: grid;
    grid-template-columns: 1fr 220px 1fr;
    align-items: center;
    gap: 24px;
    position: relative;
}

.app-header__left {
    display: flex;
    flex-direction: column;
    gap: 9px;
    align-items: flex-start;
}

.app-header__contacts {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 12px;
}

.app-header__contact {
    color: #ffedd5;
    text-decoration: none;
    font-size: 0.95rem;
    opacity: 0.95;
    transition: opacity 0.2s ease;
}

.app-header__contact:hover {
    opacity: 1;
}

.app-header__quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.app-header__quick-link {
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 14px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    transition: all 0.2s ease;
}

.app-header__quick-link:hover {
    background: rgba(255, 255, 255, 0.16);
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
    width: 140px;
    height: 140px;
    margin-top: 22px;
    margin-bottom: -22px;
    border-radius: 50%;
    background: #fffaf5;
    box-shadow:
        0 10px 30px rgba(0, 0, 0, 0.16),
        inset 0 0 0 8px rgba(128, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.app-header__logo {
    width:65%;
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
    gap: 10px;
}

.app-header__register-btn {
    color: #7f1d1d !important;
    font-weight: 700;
}

.app-header__user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.app-header__user-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    max-width: 220px;
}

.app-header__user-name {
    font-weight: 700;
    line-height: 1.1;
}

.app-header__user-email {
    font-size: 0.85rem;
    color: #fed7aa;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 220px;
}

.app-header__bottom {
    background: #fffaf8;
    border-bottom: 1px solid rgba(128, 0, 0, 0.12);
}

.app-header__bottom-inner {
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.app-header__catalog-link a {
    color: #7f1d1d;
    text-decoration: none;
    font-weight: 700;
}

@media (max-width: 1264px) {
    .app-header__inner {
        grid-template-columns: 1fr 180px 1fr;
    }

    .app-header__logo-shell {
        width: 150px;
        height: 150px;
    }
}

@media (max-width: 960px) {
    .app-header__inner {
        grid-template-columns: 1fr;
        padding-top: 18px;
        padding-bottom: 18px;
        text-align: center;
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
        width: 136px;
        height: 136px;
        margin-top: 0;
        margin-bottom: 0;
    }

    .app-header__bottom-inner {
        flex-direction: column;
        justify-content: center;
        padding: 12px 0;
    }
}

@media (max-width: 600px) {
    .app-header__contacts,
    .app-header__quick-links,
    .app-header__guest,
    .app-header__user {
        justify-content: center;
    }

    .app-header__contact,
    .app-header__quick-link {
        font-size: 0.9rem;
    }

    .app-header__user {
        flex-direction: column;
    }
}
</style>
