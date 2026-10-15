<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'
import UnitManufacturesCard from '@/Components/Unit/UnitManufacturesCard.vue'
import QuotationWorkspace from '@/Components/Quotations/QuotationWorkspace.vue'

const props = defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
    goodsLoading: { type: Boolean, default: false },
    searchGoods: { type: Function, required: true },
})
const emit = defineEmits(['refresh'])
const tab = ref('consumptions')
const showConsumptionForm = ref(false)
const savingConsumption = ref(false)
const consumptionErrors = ref({})
const categoryFilter = ref(null)
const feedback = ref('')
const formConsumption = reactive({ product_id: null, quantity: null, measure_id: null })
const consumptions = computed(() => props.unit?.consumptions || [])
const consumptionCategories = computed(() => [...new Map(consumptions.value
    .filter((item) => item.product?.category?.id)
    .map((item) => [item.product.category.id, item.product.category])).values()])
const filteredConsumptions = computed(() => consumptions.value.filter((item) => !categoryFilter.value || item.product?.category?.id === categoryFilter.value))
const products = computed(() => (props.dict.products || []).map((product) => ({ ...product, title: productName(product) })))
const consumptionHeaders = [
    { title: 'Продукт', key: 'product', sortable: false },
    { title: 'Категория', key: 'category', sortable: false },
    { title: 'Объём', key: 'quantity', sortable: false, align: 'end' },
    { title: 'Ед.', key: 'measure', sortable: false },
]
watch(() => props.unit.id, () => {
    showConsumptionForm.value = false
    categoryFilter.value = null
    feedback.value = ''
    Object.assign(formConsumption, { product_id: null, quantity: null, measure_id: null })
})
function formatNumber(value, digits = 2) {
    if (value === null || value === undefined || value === '') return '—'
    const number = Number(value)
    return Number.isFinite(number) ? new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(number) : '—'
}
function productName(product) { return product?.rus || product?.name || product?.eng || `Product #${product?.id ?? '—'}` }
async function storeConsumption() {
    if (savingConsumption.value) return
    savingConsumption.value = true
    consumptionErrors.value = {}
    feedback.value = ''
    try {
        await axios.post(route('api.consumption.store'), { unit_id: props.unit.id, ...formConsumption })
        Object.assign(formConsumption, { product_id: null, quantity: null, measure_id: null })
        showConsumptionForm.value = false
        emit('refresh')
    } catch (error) {
        consumptionErrors.value = error.response?.data?.errors || {}
        feedback.value = error.response?.data?.message || 'Не удалось добавить потребление.'
    } finally { savingConsumption.value = false }
}
</script>

<template>
    <section class="unit-relations" aria-label="Связи с продуктами, товарами и категориями">
        <v-tabs v-model="tab" color="#352345" density="compact" height="38" class="unit-relations__tabs" show-arrows>
            <v-tab value="consumptions">Закупает / потребляет</v-tab>
            <v-tab value="manufactures">Производит</v-tab>
            <v-tab value="quotations">Quotations · прайс-лист</v-tab>
        </v-tabs>
        <p v-if="feedback" role="alert" class="unit-relations__error">{{ feedback }}</p>
        <v-window v-model="tab">
            <v-window-item value="consumptions">
                <div class="unit-relations__toolbar">
                    <span>Продукты для поставок этому Unit</span>
                    <button type="button" class="unit-relations__action" :aria-expanded="showConsumptionForm" @click="showConsumptionForm = !showConsumptionForm"><v-icon :icon="showConsumptionForm ? 'mdi-close' : 'mdi-plus'" size="15" />{{ showConsumptionForm ? 'Закрыть' : 'Добавить продукт' }}</button>
                </div>
                <form v-if="showConsumptionForm" class="unit-relations__consumption-form" @submit.prevent="storeConsumption">
                    <v-autocomplete v-model="formConsumption.product_id" :items="products" item-title="title" item-value="id" label="Продукт" variant="outlined" density="compact" hide-details="auto" :error-messages="consumptionErrors.product_id || []" />
                    <v-text-field v-model="formConsumption.quantity" label="Объём" type="number" min="0" step="any" variant="outlined" density="compact" hide-details="auto" :error-messages="consumptionErrors.quantity || []" />
                    <v-select v-model="formConsumption.measure_id" :items="dict.measures || []" item-title="name" item-value="id" label="Единица" variant="outlined" density="compact" hide-details="auto" clearable :error-messages="consumptionErrors.measure_id || []" />
                    <v-btn type="submit" color="#352345" variant="flat" rounded="0" :disabled="!formConsumption.product_id || formConsumption.quantity === null || formConsumption.quantity === ''" :loading="savingConsumption">Добавить</v-btn>
                </form>
                <v-select v-if="consumptionCategories.length > 1" v-model="categoryFilter" :items="consumptionCategories" item-title="name" item-value="id" label="Категория" variant="outlined" density="compact" hide-details clearable class="unit-relations__filter" />
                <v-data-table :items="filteredConsumptions" :headers="consumptionHeaders" density="compact" :items-per-page="12" :hide-default-footer="filteredConsumptions.length <= 12" class="unit-relations__consumptions">
                    <template #item.product="{ item }"><Link v-if="item.product?.id" :href="route('product.show', item.product.id)">{{ productName(item.product) }}</Link><span v-else>Product #{{ item.product_id }}</span></template>
                    <template #item.category="{ item }"><Link v-if="item.product?.category?.id" :href="route('category.show', item.product.category.id)" class="unit-relations__category">{{ item.product.category.name }}</Link><span v-else>—</span></template>
                    <template #item.quantity="{ item }">{{ formatNumber(item.quantity) }}</template>
                    <template #item.measure="{ item }">{{ item.measure?.name || '—' }}</template>
                    <template #no-data><p class="unit-relations__empty">Потребляемые продукты пока не добавлены.</p></template>
                </v-data-table>
            </v-window-item>
            <v-window-item value="manufactures"><UnitManufacturesCard :unit="unit" :dict="dict" @refresh="emit('refresh')" /></v-window-item>
            <v-window-item value="quotations" eager>
                <QuotationWorkspace :key="unit.id" :unit="unit" :quotations="unit.quotations || []" :dict="dict" @changed="emit('refresh')" />
            </v-window-item>
        </v-window>
    </section>
</template>

<style scoped>
.unit-relations { border: 1px solid #d9d7dc; background: #fff; color: #222; min-width: 0; }
.unit-relations__tabs { border-bottom: 1px solid #d8d6db; }
.unit-relations__tabs :deep(.v-tab) { text-transform: none; letter-spacing: 0; font-size: 12px; padding: 0 14px; }
.unit-relations__toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; color: #666; font-size: 13px; }
.unit-relations__action { display: inline-flex; align-items: center; justify-content: center; gap: 5px; min-height: 30px; padding: 5px 9px; border: 1px solid #cfcbd2; background: #fff; color: #352345; font-size: 12px; text-decoration: none; white-space: nowrap; }
.unit-relations__action:hover { background: #f3f2f4; }
.unit-relations__consumption-form { display: grid; grid-template-columns: minmax(170px, 1.8fr) minmax(90px, .7fr) minmax(90px, .7fr) auto; gap: 8px; padding: 0 12px 12px; align-items: start; }
.unit-relations__filter { max-width: 300px; margin: 0 12px 12px; }
.unit-relations__consumptions { font-size: 13px; }
.unit-relations__consumptions :deep(th) { color: #666; font-size: 11px; font-weight: 500 !important; background: #f5f5f5; }
.unit-relations__consumptions a { color: #352345; text-decoration: none; }
.unit-relations__consumptions a:hover { text-decoration: underline; }
.unit-relations__consumptions .unit-relations__category { color: #666; font-size: 12px; }
.unit-relations__empty { padding: 18px 12px; color: #777; font-size: 13px; margin: 0; }
.unit-relations__error { padding: 8px 12px; color: #651c2e; font-size: 13px; margin: 0; }
.unit-relations__consumption-form :deep(.v-field) { border-radius: 0; }
@media (max-width: 760px) { .unit-relations__consumption-form { grid-template-columns: 1fr 1fr; } .unit-relations__consumption-form > :first-child { grid-column: 1 / -1; } }
@media (max-width: 480px) { .unit-relations__toolbar { flex-wrap: wrap; } }
</style>
