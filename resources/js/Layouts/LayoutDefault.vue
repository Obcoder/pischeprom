<script setup>
import { Link } from '@inertiajs/vue3'
import { logo } from "@/Pages/Helpers/consts.js"
import { onMounted, ref } from "vue"
import axios from "axios"
import { route } from "ziggy-js"

import AppHeader from '@/Components/Layout/AppHeader.vue';

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

const links = [
    'Home',
    'About Us',
    'Team',
    'Services',
    'Blog',
    'Contact Us',
]

const drawer = ref(true)
const products = ref([])

function indexProducts() {
    axios.get(route('products.index'))
        .then(function (response) {
            products.value = response.data
        })
        .catch(function (error) {
            console.log(error)
        })
}

onMounted(() => {
    indexProducts()
})
</script>

<template>
    <v-layout class="rounded-md">
        <v-navigation-drawer
            v-model="drawer"
            :location="$vuetify.display.mobile ? 'bottom' : undefined"
        >
            <v-list>
                <v-list-item>
                    <Link href="/">
                        <img
                            :src="logo"
                            class="h-12 w-auto sm:h-16 md:h-20 shrink-0 rounded"
                            alt="ПИЩЕПРОМ-СЕРВЕР"
                        >
                    </Link>
                </v-list-item>

                <v-list-item>
                    <Link :href="route('goods')">Все товары</Link>
                </v-list-item>

                <v-list-item>
                    <v-list>
                        <v-list-item
                            v-for="product in products"
                            :key="product.id"
                        >
                            {{ product.rus }}
                        </v-list-item>
                    </v-list>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <div class="layout-shell w-100">
            <AppHeader
                :categories="categories"
            />

            <v-main
                class="layout-main"
                style="min-height: 300px;"
            >
                <slot />
            </v-main>

            <v-footer
                class="inset-x-0 bottom-0 h-16 text-yellow-50"
                color="#800000"
            >
                <v-row justify="center" no-gutters>
                    <v-col class="text-center mt-4" cols="12">
                        <v-card title="Телефон">
                            <v-card-text>
                                <v-sheet>
                                    +7-965-016-0001
                                </v-sheet>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-btn
                        v-for="link in links"
                        :key="link"
                        class="mx-2"
                        color="white"
                        rounded="xl"
                        variant="text"
                    >
                        {{ link }}
                    </v-btn>

                    <v-col class="text-center mt-4" cols="12">
                        2022 - {{ new Date().getFullYear() }} —
                        <strong>ООО "Пищепром-сервер"</strong>
                    </v-col>
                </v-row>
            </v-footer>
        </div>
    </v-layout>
</template>

<style scoped>
.layout-shell {
    width: 100%;
    min-height: 100vh;
    background: #f9fafb;
}

.layout-main {
    display: block;
}
</style>
