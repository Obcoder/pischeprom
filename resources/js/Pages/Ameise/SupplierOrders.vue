<script setup>
import { useHead } from '@unhead/vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import SupplierOrdersRegister from '@/Components/SupplierOrders/SupplierOrdersRegister.vue'

defineOptions({ layout: VerwalterLayout })
useHead({ title: 'Ameise — заказы поставщикам' })
</script>

<template>
    <main class="supplier-orders-page"><h1>Заказы поставщикам</h1><SupplierOrdersRegister /></main>
</template>

<style scoped>
.supplier-orders-page { padding: 16px; min-width: 0; width: 100%; align-self: stretch; }
.supplier-orders-page h1 { font-size: 20px; font-weight: 600; color: #47394f; margin: 0 0 14px; }
@media (max-width: 600px) { .supplier-orders-page { padding: 10px; } }
</style>
