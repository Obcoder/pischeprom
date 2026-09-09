<script setup>
import { useHead } from '@unhead/vue'

import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import PurchasesBoard from '@/Pages/Purchases/Purchases.vue'

defineOptions({
    layout: VerwalterLayout,
})

useHead({
    title: 'Закупки Ameise',
    meta: [
        {
            name: 'description',
            content: 'Управление закупками в Ameise',
        },
    ],
})
</script>

<template>
    <v-container fluid class="ameise-purchases-page pa-4">
        <PurchasesBoard />
    </v-container>
</template>

<style scoped>
.ameise-purchases-page {
    width: 100%;
    min-height: calc(100vh - 48px);
}
</style>
