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
    <footer></footer>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
</script>
