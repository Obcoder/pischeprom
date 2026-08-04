<script setup>
import { useHead } from '@unhead/vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import CommodityShowPage from '@/Components/Dictionaries/Commodities/CommodityShowPage.vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    commodityId: {
        type: Number,
        required: true,
    },
})

useHead({
    title: `Commodity #${props.commodityId}`,
})
</script>

<template>
    <CommodityShowPage :commodity-id="commodityId" />
</template>
