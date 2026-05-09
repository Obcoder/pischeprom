<script setup>
import { useHead } from '@vueuse/head'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import CommoditiesPage from '@/Components/Dictionaries/Commodities/CommoditiesPage.vue'

defineOptions({
    layout: VerwalterLayout,
})

useHead({
    title: 'Commodities',
})
</script>

<template>
    <CommoditiesPage />
</template>
