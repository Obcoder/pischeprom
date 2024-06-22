<template>
    <header>
        <nav class="flex items-center bg-red-800 text-yellow-50">
            <div class="mx-3">
                <Link href="/">
                    <img :src="logo" class="h-12 w-auto sm:h-16 md:h-20 shrink-0 rounded">
                </Link>
            </div>
            <div class="mx-3 p-3 h-full">
                <Link :href="route('glycerol')">Глицерины</Link>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-4">
        <slot />
    </main>

    <footer class="bg-red-800 text-yellow-50">
        <h3>Телефон</h3>
        <p>+7-905-753-26-48</p>
    </footer>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import {logo} from "@/Pages/Helpers/consts.js";
</script>
