<template>
    <header>
        <nav class="flex flex-row bg-red-800 text-yellow-50">
            <div class="mx-3">
                <img src="logo_пищепром-сервер_1200х1207.jpg">
            </div>
            <div class="mx-3">
                <Link href="/">ПИЩЕПРОМ-СЕРВЕР</Link>
            </div>
            <div class="mx-3">
                <Link href="/glycerol/">Глицерины</Link>
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
</script>
