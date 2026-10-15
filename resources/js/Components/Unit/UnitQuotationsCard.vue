<script setup>
import QuotationWorkspace from '@/Components/Quotations/QuotationWorkspace.vue'

defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
    goodsLoading: { type: Boolean, default: false },
    searchGoods: { type: Function, default: null },
})
const emit = defineEmits(['refresh'])
</script>

<template>
    <QuotationWorkspace :key="unit.id" :unit="unit" :quotations="unit.quotations || []" :dict="dict" @changed="emit('refresh')" />
</template>
