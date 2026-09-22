<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'
import UnitManufacturesCard from '@/Components/Unit/UnitManufacturesCard.vue'

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
const dialogQuotation = ref(false)
const editingQuotation = ref(null)
const goodsSearch = ref('')
const savingQuotation = ref(false)
const deletingQuotationId = ref(null)
const quotationErrors = ref({})
const feedback = ref('')
const quotationFeedback = ref('')
const formConsumption = reactive({ product_id: null, quantity: null, measure_id: null })
const quotationForm = reactive({ good_id: null, price: null, currency_id: null, measure_id: null, denominator: 1 })
const consumptions = computed(() => props.unit?.consumptions || [])
const quotations = computed(() => props.unit?.quotations || [])
const consumptionCategories = computed(() => [...new Map(consumptions.value
    .filter((item) => item.product?.category?.id)
    .map((item) => [item.product.category.id, item.product.category])).values()])
const filteredConsumptions = computed(() => consumptions.value.filter((item) => !categoryFilter.value || item.product?.category?.id === categoryFilter.value))
const products = computed(() => (props.dict.products || []).map((product) => ({ ...product, title: productName(product) })))
const goods = computed(() => [...new Map([
    ...(props.dict.goods || []),
    ...(editingQuotation.value?.good ? [editingQuotation.value.good] : []),
].map((good) => [good.id, good])).values()])
const currencies = computed(() => (props.dict.currencies || []).map((currency) => ({
    ...currency, title: [currency.code, currency.name].filter(Boolean).join(' · '),
})))
const consumptionHeaders = [
    { title: 'Продукт', key: 'product', sortable: false },
    { title: 'Категория', key: 'category', sortable: false },
    { title: 'Объём', key: 'quantity', sortable: false, align: 'end' },
    { title: 'Ед.', key: 'measure', sortable: false },
]
const debouncedSearchGoods = useDebounceFn(async (value) => {
    try { await props.searchGoods(value || '') }
    catch (error) { quotationFeedback.value = 'Не удалось найти товары. Повторите поиск.' }
}, 350)
watch(goodsSearch, (value) => { debouncedSearchGoods(value) })
watch(() => props.unit.id, () => {
    showConsumptionForm.value = false
    dialogQuotation.value = false
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
async function openQuotation(quotation = null) {
    editingQuotation.value = quotation
    quotationErrors.value = {}
    quotationFeedback.value = ''
    Object.assign(quotationForm, {
        good_id: quotation?.good_id || quotation?.good?.id || null,
        price: quotation?.price ?? null,
        currency_id: quotation?.currency_id || quotation?.currency?.id || null,
        measure_id: quotation?.measure_id || quotation?.measure?.id || null,
        denominator: quotation?.denominator || 1,
    })
    goodsSearch.value = quotation?.good?.name || ''
    dialogQuotation.value = true
    if (!props.dict.goods?.length) {
        try { await props.searchGoods(goodsSearch.value) }
        catch (error) { quotationFeedback.value = 'Не удалось загрузить товары. Повторите поиск.' }
    }
}
async function saveQuotation() {
    if (savingQuotation.value) return
    savingQuotation.value = true
    quotationErrors.value = {}
    quotationFeedback.value = ''
    try {
        const payload = { unit_id: props.unit.id, ...quotationForm }
        if (editingQuotation.value?.id) await axios.put(`/api/quotations/${editingQuotation.value.id}`, payload)
        else await axios.post('/api/quotations', payload)
        dialogQuotation.value = false
        emit('refresh')
    } catch (error) {
        quotationErrors.value = error.response?.data?.errors || {}
        quotationFeedback.value = error.response?.data?.message || 'Не удалось сохранить цену.'
    } finally { savingQuotation.value = false }
}
async function deleteQuotation(quotation) {
    if (!quotation?.id || deletingQuotationId.value) return
    if (!window.confirm(`Удалить цену для «${quotation.good?.name || 'товара'}»?`)) return
    deletingQuotationId.value = quotation.id
    feedback.value = ''
    try {
        await axios.delete(`/api/quotations/${quotation.id}`)
        emit('refresh')
    } catch (error) { feedback.value = error.response?.data?.message || 'Не удалось удалить цену.' }
    finally { deletingQuotationId.value = null }
}
function priceLine(quotation) {
    const currency = quotation.currency?.code || quotation.currency?.name || ''
    const measure = quotation.measure?.name || quotation.measure?.title || ''
    const denominator = Number(quotation.denominator || 1)
    return `${formatNumber(quotation.price)} ${currency}${measure ? ` / ${denominator === 1 ? '' : `${formatNumber(denominator, 4)} `}${measure}` : denominator !== 1 ? ` / ${formatNumber(denominator, 4)}` : ''}`.trim()
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
            <v-window-item value="quotations">
                <div class="unit-relations__toolbar"><span>Товары и цены поставщика</span><button type="button" class="unit-relations__action" @click="openQuotation()"><v-icon icon="mdi-plus" size="15" />Добавить цену</button></div>
                <div v-if="quotations.length" class="unit-relations__scroll">
                    <table class="unit-relations__table">
                        <thead><tr><th>Товар / продукт / категория</th><th class="unit-relations__number">Цена</th><th><span class="sr-only">Действия</span></th></tr></thead>
                        <tbody>
                            <tr v-for="quotation in quotations" :key="quotation.id">
                                <td>
                                    <Link v-if="quotation.good?.id" :href="route('Ameise.good.show', quotation.good.id)">{{ quotation.good.name }}</Link><span v-else>Good #{{ quotation.good_id }}</span>
                                    <div v-for="product in quotation.good?.products || []" :key="product.id" class="unit-relations__product-path"><Link :href="route('product.show', product.id)">{{ productName(product) }}</Link><span v-if="product.category?.id"> / <Link :href="route('category.show', product.category.id)">{{ product.category.name }}</Link></span></div>
                                </td>
                                <td class="unit-relations__number unit-relations__price">{{ priceLine(quotation) }}</td>
                                <td class="unit-relations__controls"><button type="button" title="Изменить цену" aria-label="Изменить цену" @click="openQuotation(quotation)"><v-icon icon="mdi-pencil-outline" size="16" /></button><button type="button" class="is-danger" title="Удалить цену" aria-label="Удалить цену" :disabled="deletingQuotationId === quotation.id" @click="deleteQuotation(quotation)"><v-icon icon="mdi-trash-can-outline" size="16" /></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="unit-relations__empty">Прайс-лист пока пуст. Добавьте товары и цены Unit.</p>
            </v-window-item>
        </v-window>
        <v-dialog v-model="dialogQuotation" max-width="640" :persistent="savingQuotation">
            <v-card class="unit-quotation-dialog" rounded="0" elevation="0" border>
                <v-card-title>{{ editingQuotation ? 'Изменить цену' : 'Добавить цену' }}</v-card-title>
                <v-card-text>
                    <p v-if="quotationFeedback" role="alert" class="unit-relations__error">{{ quotationFeedback }}</p>
                    <form id="unit-quotation-form" class="unit-quotation-dialog__form" @submit.prevent="saveQuotation">
                        <v-autocomplete v-model="quotationForm.good_id" v-model:search="goodsSearch" :items="goods" item-title="name" item-value="id" label="Товар" variant="outlined" density="compact" clearable :loading="goodsLoading" no-filter hide-details="auto" class="unit-quotation-dialog__good" :error-messages="quotationErrors.good_id || []" />
                        <v-text-field v-model="quotationForm.price" label="Цена" type="number" step="0.01" min="0" variant="outlined" density="compact" hide-details="auto" :error-messages="quotationErrors.price || []" />
                        <v-select v-model="quotationForm.currency_id" :items="currencies" item-title="title" item-value="id" label="Валюта" variant="outlined" density="compact" clearable hide-details="auto" :error-messages="quotationErrors.currency_id || []" />
                        <v-text-field v-model="quotationForm.denominator" label="За количество" type="number" step="0.0001" min="0.0001" variant="outlined" density="compact" hide-details="auto" :error-messages="quotationErrors.denominator || []" />
                        <v-select v-model="quotationForm.measure_id" :items="dict.measures || []" item-title="name" item-value="id" label="Единица" variant="outlined" density="compact" clearable hide-details="auto" :error-messages="quotationErrors.measure_id || []" />
                    </form>
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn :disabled="savingQuotation" @click="dialogQuotation = false">Отмена</v-btn><v-btn form="unit-quotation-form" type="submit" color="#352345" variant="flat" rounded="0" :disabled="!quotationForm.good_id || quotationForm.price === null || quotationForm.price === ''" :loading="savingQuotation">Сохранить</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
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
.unit-relations__consumptions a, .unit-relations__table a { color: #352345; text-decoration: none; }
.unit-relations__consumptions a:hover, .unit-relations__table a:hover { text-decoration: underline; }
.unit-relations__consumptions .unit-relations__category { color: #666; font-size: 12px; }
.unit-relations__scroll { overflow: auto; max-height: 390px; }
.unit-relations__table { width: 100%; border-collapse: collapse; font-size: 13px; }
.unit-relations__table th { position: sticky; top: 0; color: #666; background: #f5f5f5; font-size: 11px; font-weight: 500; text-align: left; }
.unit-relations__table th, .unit-relations__table td { padding: 8px 12px; border-bottom: 1px solid #e7e7e7; }
.unit-relations__table .unit-relations__number { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.unit-relations__price { color: #651c2e; }
.unit-relations__product-path { font-size: 11px; color: #777; margin-top: 2px; }
.unit-relations__product-path a { color: #666; }
.unit-relations__controls { width: 82px; white-space: nowrap; text-align: right; }
.unit-relations__controls button { width: 28px; height: 28px; color: #352345; }
.unit-relations__controls button:hover { background: #f3f2f4; }
.unit-relations__controls button:disabled { opacity: .5; }
.unit-relations__controls .is-danger { color: #651c2e; }
.unit-relations__empty { padding: 18px 12px; color: #777; font-size: 13px; margin: 0; }
.unit-relations__error { padding: 8px 12px; color: #651c2e; font-size: 13px; margin: 0; }
.unit-quotation-dialog__form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.unit-quotation-dialog__good { grid-column: 1 / -1; }
.unit-relations :deep(.v-field), .unit-quotation-dialog :deep(.v-field) { border-radius: 0; }
@media (max-width: 760px) { .unit-relations__consumption-form { grid-template-columns: 1fr 1fr; } .unit-relations__consumption-form > :first-child { grid-column: 1 / -1; } }
@media (max-width: 480px) { .unit-relations__toolbar { flex-wrap: wrap; } .unit-quotation-dialog__form { grid-template-columns: 1fr; } }
</style>
