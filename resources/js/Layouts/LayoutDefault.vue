<template>
    <header>
        <nav class="bg-red-800">
            <div class="text-yellow-50">
                <Link href="/">ПИЩЕПРОМ-СЕРВЕР</Link>
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
