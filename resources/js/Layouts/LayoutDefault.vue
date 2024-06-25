<template>
    <header>
        <nav class="flex items-center bg-red-800 text-yellow-50 sm:max-w-full">
            <div class="mx-3">
                <Link href="/">
                    <img :src="logo" class="h-12 w-auto sm:h-16 md:h-20 shrink-0 rounded">
                </Link>
            </div>
            <div class="mx-3 sm:hidden">
                <Link href="/">ПИЩЕПРОМ-СЕРВЕР</Link>
            </div>
            <div v-for="category in categories" class="mx-3 p-3 h-full">
                <div>
                    {{category.name}}
                </div>
            </div>
            <div class="mx-3 p-3 h-full">
                <Link :href="route('goods')">Все товары</Link>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-4">
        <slot />
    </main>

    <footer class="absolute inset-x-0 bottom-0 h-16 bg-red-800 text-yellow-50">
        <h3>Телефон</h3>
        <p>+7-905-753-26-48</p>
    </footer>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import { logo } from "@/Pages/Helpers/consts.js";

const props = defineProps({
    categories: Object,
})
</script>
