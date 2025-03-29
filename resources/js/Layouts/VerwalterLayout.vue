<script setup>
import {Link} from "@inertiajs/vue3";
</script>

<template>
    <v-layout class="rounded rounded-md">
        <v-navigation-drawer>
            <v-list>
                <v-list-item>
                    <Link :href="'https://пищепром-сервер.рф/'">
                        Пищепром-Сервер
                    </Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.cities')">Города</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.entities')">Entities</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.units')">Units</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.products')">Products</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.goods')">Goods</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.commodities')">Commodities</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.geography')">Geography</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('ameise.telegrambot')"
                          color="blue-darken-1">Telegram</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.sales')">
                        Продажи
                    </Link>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar :elevation="2"
                   rounded
                   scroll-behavior="fade-image elevate"
                   image="https://picsum.photos/1920/1080?random"
        >
            <template v-slot:prepend>
                <v-app-bar-nav-icon></v-app-bar-nav-icon>
            </template>

            <v-app-bar-title>
                <Link :href="route('verwalter')">
                    <span>Großbuch</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.fluxmonitor')">
                    <span>M</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.botany')">
                    <span>Botany</span>
                </Link>
            </v-app-bar-title>

            <template v-slot:append>
                <v-btn icon="mdi-heart"></v-btn>

                <v-btn icon="mdi-magnify"></v-btn>

                <v-btn icon="mdi-dots-vertical"></v-btn>

            </template>
        </v-app-bar>

        <v-main class="d-flex align-center justify-center" style="min-height: 300px;">
            <slot />
        </v-main>
    </v-layout>
</template>
