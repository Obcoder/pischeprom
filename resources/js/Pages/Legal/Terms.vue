<script setup>
import { Head } from '@inertiajs/vue3'
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})
</script>

<template>
    <Head title="Пользовательское соглашение" />

    <v-container class="py-10">
        <h1 class="text-h4 font-weight-bold mb-6">
            Пользовательское соглашение
        </h1>

        <p>
            Условия использования сайта и сервиса.
        </p>
    </v-container>
</template>
