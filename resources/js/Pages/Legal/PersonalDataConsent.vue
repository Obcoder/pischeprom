<script setup>
import { Head } from '@inertiajs/vue3'
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})
</script>

<template>
    <Head title="Согласие на обработку персональных данных" />

    <v-container class="py-10">
        <h1 class="text-h4 font-weight-bold mb-6">
            Согласие на обработку персональных данных
        </h1>

        <p>
            Текст согласия на обработку персональных данных.
        </p>
    </v-container>
</template>
