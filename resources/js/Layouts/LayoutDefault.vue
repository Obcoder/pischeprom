<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

import AppHeader from '@/Components/Layout/AppHeader.vue'

const inertiaPage = usePage()

const categories = computed(() => {
    const props = inertiaPage.props || {}

    const possibleCategories = [
        props.categories,
        props.publicCategories,
        props.headerCategories,
        props.navigation?.categories,
    ]

    return possibleCategories.find((items) => Array.isArray(items)) || []
})

const footerLinks = [
    {
        label: 'Политика конфиденциальности',
        href: '/privacy-policy',
    },
    {
        label: 'Пользовательское соглашение',
        href: '/terms',
    },
    {
        label: 'Согласие на обработку ПД',
        href: '/personal-data-consent',
    },
]

const currentYear = new Date().getFullYear()
</script>

<template>
    <div class="layout-shell">
        <AppHeader :categories="categories" />

        <main class="layout-main">
            <slot />
        </main>

        <footer class="layout-footer">
            <v-container>
                <v-row class="py-6" justify="center">
                    <v-col cols="12" md="4" class="text-center">
                        <div class="text-subtitle-1 font-weight-bold mb-2">
                            Телефон
                        </div>

                        <div>
                            +7-965-016-0001
                        </div>
                    </v-col>

                    <v-col cols="12" md="4" class="text-center">
                        <div class="d-flex flex-wrap justify-center ga-2">
                            <v-btn
                                v-for="link in footerLinks"
                                :key="link.href"
                                :href="link.href"
                                color="white"
                                rounded="xl"
                                variant="text"
                                size="small"
                            >
                                {{ link.label }}
                            </v-btn>
                        </div>
                    </v-col>

                    <v-col cols="12" md="4" class="text-center">
                        <div>
                            2022 - {{ currentYear }} —
                            <strong>ООО "Пищепром-сервер"</strong>
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </footer>
    </div>
</template>

<style scoped>
.layout-shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f9fafb;
}

.layout-main {
    flex: 1 1 auto;
    display: block;
}

.layout-footer {
    margin-top: auto;
    background: #800000;
    color: #fef2f2;
}
</style>
