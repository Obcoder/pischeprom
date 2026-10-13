<script setup>
import { useHead } from '@unhead/vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import SuppliersWorkspace from '@/Components/Suppliers/SuppliersWorkspace.vue'

defineOptions({ layout: VerwalterLayout })
defineProps({ initialUnitId: { type: [Number, String], default: null } })
useHead({ title: 'Ameise — поставщики' })
</script>

<template><main class="suppliers-page"><SuppliersWorkspace :initial-unit-id="initialUnitId" /></main></template>

<style scoped>
.suppliers-page { padding: 20px; min-width: 0; width: 100%; align-self: stretch; background: #faf8fc; }@media(max-width:650px) { .suppliers-page { padding: 11px; } }
</style>
