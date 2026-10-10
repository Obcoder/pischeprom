<script setup>
import { ref, watch } from 'vue'
import ProductAiSalesCampaignCard from '@/Components/AiSales/ProductAiSalesCampaignCard.vue'
import ProductYandexSearchCard from '@/Components/ProductYandexSearchCard.vue'

const props = defineProps({
    productId: { type: [Number, String], required: true },
    productName: { type: String, default: '' },
    canViewAiSales: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
})

const tab = ref(props.canViewAiSales ? 'ai' : 'yandex')
const visited = ref(new Set())

watch(() => props.productId, () => {
    tab.value = props.canViewAiSales ? 'ai' : 'yandex'
    visited.value = new Set()
}, { flush: 'sync' })
watch(() => props.canViewAiSales, allowed => {
    if (!allowed && tab.value === 'ai') tab.value = 'yandex'
})
watch([() => props.active, tab, () => props.productId], ([active, selected]) => {
    if (active) visited.value = new Set([...visited.value, selected])
}, { immediate: true, flush: 'sync' })
</script>

<template>
    <section class="product-market" aria-label="Маркет продукта">
        <v-tabs v-model="tab" color="primary" density="compact" height="38" show-arrows class="product-market__tabs" aria-label="Сервисы маркета">
            <v-tab value="ai" :disabled="!canViewAiSales" :title="canViewAiSales ? undefined : 'Нет доступа к AI-поиску покупателей'" prepend-icon="mdi-account-search-outline">AI-поиск покупателей</v-tab>
            <v-tab value="yandex" prepend-icon="mdi-magnify">Выдача Яндекса</v-tab>
        </v-tabs>
        <div class="product-market__content">
            <ProductAiSalesCampaignCard
                v-if="canViewAiSales && visited.has('ai')"
                v-show="tab === 'ai'"
                :key="`ai-${productId}`"
                :product-id="productId"
                :active="active && tab === 'ai'"
            />
            <ProductYandexSearchCard
                v-if="visited.has('yandex')"
                v-show="tab === 'yandex'"
                :key="`yandex-${productId}`"
                :product-id="Number(productId)"
                :product-name="productName"
                :active="active && tab === 'yandex'"
            />
        </div>
    </section>
</template>

<style scoped>
.product-market { min-width: 0; }
.product-market__tabs { border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.product-market__tabs :deep(.v-tab) { font-size: 12px; letter-spacing: normal; text-transform: none; }
.product-market__content { padding-top: 10px; min-width: 0; }
</style>
