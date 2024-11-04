<template>
    <Head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </Head>

    <v-layout class="rounded rounded-md">

        <v-navigation-drawer>
            <v-list>
                <v-list-item>
                    <Link href="/">
                        <img :src="logo" class="h-12 w-auto sm:h-16 md:h-20 shrink-0 rounded">
                    </Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('goods')">Все товары</Link>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar color="#800000"
        >
            <template v-slot:prepend>
                <v-app-bar-nav-icon></v-app-bar-nav-icon>
            </template>
            <v-app-bar-title>
                <Link href="/">ПИЩЕПРОМ-СЕРВЕР</Link>
                <v-chip>Рыба</v-chip>
            </v-app-bar-title>

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
                        <v-list-item-title>{{ item.name }}</v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-menu>
        </v-app-bar>

        <v-main class="d-flex align-center justify-center" style="min-height: 300px;">
            <slot />
        </v-main>
        <footer class="inset-x-0 bottom-0 h-16 bg-red-800 text-yellow-50">
            <h3>Телефон</h3>
            <p>+7-905-753-26-48</p>
        </footer>
    </v-layout>
    <v-footer class="inset-x-0 bottom-0 h-16 text-yellow-50"
              color="#800000"
    >
        <v-row justify="center" no-gutters>
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
                {{ new Date().getFullYear() }} — <strong>ООО "Пищепром-сервер"</strong>
            </v-col>
        </v-row>
    </v-footer>
</template>

<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { logo } from "@/Pages/Helpers/consts.js";

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
];

</script>
