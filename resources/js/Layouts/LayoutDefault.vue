<script setup>
import AppHeader from '@/Components/Layout/AppHeader.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
})

const footerLinks = [
    'Home',
    'About Us',
    'Team',
    'Services',
    'Blog',
    'Contact Us',
]
</script>

<template>
    <div class="layout-shell">
        <AppHeader :categories="categories" />

        <v-main class="layout-main">
            <slot />
        </v-main>

        <v-footer
            class="layout-footer text-yellow-50"
            color="#800000"
        >
            <v-container>
                <v-row class="py-4" justify="center">
                    <v-col cols="12" md="4" class="text-center">
                        <div class="text-subtitle-1 font-weight-bold mb-2">
                            Телефон
                        </div>
                        <div>+7-965-016-0001</div>
                    </v-col>

                    <v-col cols="12" md="4" class="text-center">
                        <div class="d-flex flex-wrap justify-center ga-2">
                            <v-btn
                                v-for="link in footerLinks"
                                :key="link"
                                color="white"
                                rounded="xl"
                                variant="text"
                                size="small"
                            >
                                {{ link }}
                            </v-btn>
                        </div>
                    </v-col>

                    <v-col cols="12" md="4" class="text-center">
                        <div>
                            2022 - {{ new Date().getFullYear() }} —
                            <strong>ООО "Пищепром-сервер"</strong>
                        </div>
                    </v-col>
                </v-row>
            </v-container>
        </v-footer>
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
}
</style>
