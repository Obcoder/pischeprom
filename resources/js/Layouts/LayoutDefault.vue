<template>
    <header>
        <nav class="flex flex-row bg-red-800 text-yellow-50">
            <div>
                <img src="logo_пищепром-сервер_1200х1207.jpg">
            </div>
            <div>
                <Link href="/">ПИЩЕПРОМ-СЕРВЕР</Link>
            </div>
            <div>
                <Link href="/glycerol/">Глицерины</Link>
            </div>
        </nav>
    </header>

    <main>
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
