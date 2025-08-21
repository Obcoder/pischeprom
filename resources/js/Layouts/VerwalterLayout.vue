<script setup>
import {Link} from "@inertiajs/vue3";
import {route} from "ziggy-js";
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
                    <Link :href="route('Ameise.checks')">Закупка</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.contactsCentre')">Contacts centre</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise/avito')">Avito</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('ameise.telegrambot')"
                          color="blue-darken-1">Telegram</Link>
                </v-list-item>
                <v-list-item>
                    <Link :href="route('Ameise.yandex')">
                        Yandex
                    </Link>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar :elevation="2"
                   rounded
                   density="compact"
                   scroll-behavior="fade-image elevate"
                   image="https://picsum.photos/1920/1080?random"
        >
            <template v-slot:prepend>
                <v-app-bar-nav-icon>
                    <Link :href="route('Ameise')">
                        <v-icon icon="mdi-halloween"
                                size="large"
                        ></v-icon>
                    </Link>
                </v-app-bar-nav-icon>
            </template>
            <v-app-bar-title>
                <Link :href="route('Ameise.großbuch')">
                    <v-icon icon="mdi-access-point"
                            size="x-small"
                    ></v-icon>
                    <span class="ml-1 text-xs">Großbuch</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.fluxmonitor')">
                    <span>M</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('ameise.workboard')">
                    <span>WorkBoard</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.botany')">
                    <span>Botany</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.perfume')">
                    <v-icon icon="mdi-scent"
                            size="small"
                            class="mx-2"></v-icon>
                    <span>Perfume</span>
                </Link>
            </v-app-bar-title>

            <template v-slot:append>
                <v-btn icon="mdi-heart"></v-btn>

                <v-btn icon="mdi-magnify"></v-btn>

                <v-btn icon="mdi-dots-vertical"></v-btn>

                <v-icon icon="mdi-barn"
                        size="x-small"></v-icon>
            </template>
        </v-app-bar>

        <v-main class="d-flex align-center justify-center" style="min-height: 300px;">
            <slot />
        </v-main>
    </v-layout>
</template>
