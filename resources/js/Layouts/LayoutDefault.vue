<script setup>
import {Link} from "@inertiajs/vue3";
import { logo } from "@/Pages/Helpers/consts.js";
import {onMounted, ref} from "vue";
import axios from "axios";
import {route} from "ziggy-js";
const props = defineProps({
    categories: Object,
})

const links = [
    'Home',
    'About Us',
    'Team',
    'Services',
    'Blog',
    'Contact Us',
]

let drawer = ref(true);
let products = ref()

function indexProducts(){
    axios.get(route('products.index')).then(function (response) {
        products.value = response.data;
    }).catch(function (error) {
            console.log(error)
    })
}

onMounted(()=>{
    indexProducts()
})
</script>
<template>
    <v-layout class="rounded rounded-md">
        <v-navigation-drawer v-model="drawer"
                             :location="$vuetify.display.mobile ? 'bottom' : undefined"
        >
            <v-list>
                <v-list-item>
                    <Link href="/">
                        <img :src="logo" class="h-12 w-auto sm:h-16 md:h-20 shrink-0 rounded" alt="ПИЩЕПРОМ-СЕРВЕР">
                    </Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('goods')">Все товары</Link>
                </v-list-item>
                <v-list-item>
                    <v-list>
                        <v-list-item v-for="product in products">
                            {{product.rus}}
                        </v-list-item>
                    </v-list>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar color="#800000"
        >
            <template v-slot:prepend>
                <v-app-bar-nav-icon variant="text"
                                    @click.stop="drawer = !drawer"
                ></v-app-bar-nav-icon>
            </template>
            <v-app-bar-title>
                <Link href="/">ПИЩЕПРОМ-СЕРВЕР</Link>
            </v-app-bar-title>

            <v-btn>Пищевое сырьё</v-btn>
            <v-btn>Пищевые ингредиенты</v-btn>
            <v-btn>Пищевые добавки</v-btn>

            <v-menu
                КАТЕГОРИИ
            >
                <template v-slot:activator="{ props }">
                    <v-btn
                        color="primary"
                        v-bind="props"
                    >
                        КАТЕГОРИИ
                    </v-btn>
                </template>

                <v-list>
                    <v-list-item
                        v-for="(item, index) in categories"
                        :key="index"
                    >
                        <v-list-item-title>{{item.name}}</v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-menu>

            <Link href="/Seaprom">Рыба</Link>
        </v-app-bar>

        <v-main class="d-flex align-center justify-center" style="min-height: 300px;">
            <slot />
        </v-main>
    </v-layout>

    <v-footer class="inset-x-0 bottom-0 h-16 text-yellow-50"
              color="#800000"
    >
        <v-row justify="center" no-gutters>
            <v-col class="text-center mt-4" cols="12"
                >
                <v-card title="Телефон">
                    <v-card-text>
                        <v-sheet>
                            +7-905-753-26-48
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
                2022 - {{ new Date().getFullYear() }} — <strong>ООО "Пищепром-сервер"</strong>
            </v-col>
        </v-row>
    </v-footer>

</template>
