<script setup>
import { Head } from '@inertiajs/vue3'
import Entities from '@/Components/Dictionaries/Entities/Entities.vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})
</script>

<template>
    <Head title="Entities" />

    <v-theme-provider theme="dark">
        <main class="ameise-directory-page" aria-label="Entities">
            <Entities />
        </main>
    </v-theme-provider>
</template>

<style scoped>
.ameise-directory-page {
    align-self: stretch;
    width: 100%;
    height: calc(100dvh - 58px);
    min-width: 0;
    min-height: 0;
    padding: 8px;
    overflow: hidden;
    background: #10141d;
}
</style>
