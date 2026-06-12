<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { useDate } from 'vuetify'
import axios from 'axios'

import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
    dict: {
        type: Object,
        default: () => ({}),
    },
    goodsLoading: {
        type: Boolean,
        default: false,
    },
    searchGoods: {
        type: Function,
        required: true,
    },
})

const emit = defineEmits(['refresh'])

const date = useDate()
const tab = ref('consumptions')
const showConsumptionForm = ref(false)
const savingConsumption = ref(false)
const consumptionErrors = ref({})
const dialogQuotation = ref(false)
const editingQuotation = ref(null)
const goodsSearch = ref('')
const savingQuotation = ref(false)
const deletingQuotationId = ref(null)
const quotationErrors = ref({})

const formConsumption = reactive({
    unit_id: props.unit.id,
    product_id: null,
    quantity: null,
    measure_id: null,
})

const quotationForm = reactive({
    good_id: null,
    price: null,
    currency_id: null,
    measure_id: null,
    denominator: 1,
})

const consumptions = computed(() => props.unit?.consumptions || [])
const quotations = computed(() => props.unit?.quotations || [])

const consumptionHeaders = [
    { title: 'Product', key: 'product', sortable: false },
    { title: 'Quantity', key: 'quantity', sortable: false, width: 132, align: 'end' },
    { title: 'Measure', key: 'measure', sortable: false, width: 112 },
    { title: 'Requests', key: 'requests', sortable: false, width: 96, align: 'end' },
    { title: 'Created', key: 'created_at', sortable: false, width: 116 },
]

const currencies = computed(() => (props.dict.currencies || []).map((currency) => ({
    ...currency,
    title: [currency.code, currency.name].filter(Boolean).join(' - '),
})))

const tradeStats = computed(() => [
    { label: 'consumptions', value: consumptions.value.length },
    { label: 'quotations', value: quotations.value.length },
    { label: 'requests', value: consumptions.value.reduce((sum, item) => sum + requestsCount(item), 0) },
])

const quoteStats = computed(() => {
    const uniqueGoods = new Set(quotations.value.map((item) => item.good_id || item.good?.id).filter(Boolean))
    const currencySet = new Set(quotations.value.map((item) => item.currency?.code || item.currency?.name).filter(Boolean))

    return [
        { label: 'quotes', value: quotations.value.length },
        { label: 'goods', value: uniqueGoods.size },
        { label: 'currencies', value: currencySet.size || '-' },
    ]
})

const debouncedSearchGoods = useDebounceFn(async (value) => {
    await props.searchGoods(value || '')
}, 350)

watch(goodsSearch, (value) => {
    debouncedSearchGoods(value)
})

watch(() => props.unit?.id, (unitId) => {
    formConsumption.unit_id = unitId
})

function formatNumber(value, digits = 2) {
    const number = Number(value)

    if (!Number.isFinite(number)) {
        return '-'
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: digits,
    }).format(number)
}

function formatDate(value) {
    if (!value) {
        return '-'
    }

    return date.format(value, 'keyboardDate')
}

function productName(consumption) {
    return consumption.product?.rus
        || consumption.product?.name
        || consumption.product?.eng
        || `Product #${consumption.product_id}`
}

function productHref(product) {
    if (!product?.id) {
        return '#'
    }

    try {
        return route('product.show', product.id)
    } catch (error) {
        return `/Ameise/product/${product.id}`
    }
}

function requestsCount(consumption) {
    return Number(consumption.product?.search_requests_count || consumption.product?.search_requests?.length || 0)
}

function requestTone(consumption) {
    return requestsCount(consumption) > 0 ? 'is-hot' : 'is-muted'
}

function quantityTone(consumption) {
    const quantity = Number(consumption.quantity || 0)

    if (quantity >= 1000) {
        return 'is-high'
    }

    if (quantity > 0) {
        return 'is-positive'
    }

    return 'is-muted'
}

function consumptionShare(consumption) {
    const max = Math.max(...consumptions.value.map((item) => Number(item.quantity || 0)), 0)
    const quantity = Number(consumption.quantity || 0)

    if (!max || !quantity) {
        return 0
    }

    return Math.max(8, Math.min(100, Math.round((quantity / max) * 100)))
}

async function storeConsumption() {
    savingConsumption.value = true
    consumptionErrors.value = {}

    try {
        await axios.post(route('api.consumption.store'), {
            unit_id: props.unit.id,
            product_id: formConsumption.product_id,
            quantity: formConsumption.quantity,
            measure_id: formConsumption.measure_id,
        })

        formConsumption.unit_id = props.unit.id
        formConsumption.product_id = null
        formConsumption.quantity = null
        formConsumption.measure_id = null
        showConsumptionForm.value = false
        emit('refresh')
    } catch (error) {
        consumptionErrors.value = error.response?.data?.errors || {}
        console.error('Ошибка сохранения consumption:', error)
    } finally {
        savingConsumption.value = false
    }
}

function resetQuotationForm(quotation = null) {
    editingQuotation.value = quotation
    quotationErrors.value = {}
    quotationForm.good_id = quotation?.good_id || quotation?.good?.id || null
    quotationForm.price = quotation?.price ?? null
    quotationForm.currency_id = quotation?.currency_id || quotation?.currency?.id || null
    quotationForm.measure_id = quotation?.measure_id || quotation?.measure?.id || null
    quotationForm.denominator = quotation?.denominator || 1
    goodsSearch.value = quotation?.good?.name || ''
}

async function openCreateQuotation() {
    resetQuotationForm()
    dialogQuotation.value = true

    if (!props.dict.goods?.length) {
        await props.searchGoods('')
    }
}

async function openEditQuotation(quotation) {
    resetQuotationForm(quotation)
    dialogQuotation.value = true

    if (!props.dict.goods?.length) {
        await props.searchGoods(quotation?.good?.name || '')
    }
}

function quotationPayload() {
    return {
        unit_id: props.unit.id,
        good_id: quotationForm.good_id,
        price: quotationForm.price,
        currency_id: quotationForm.currency_id || null,
        measure_id: quotationForm.measure_id || null,
        denominator: quotationForm.denominator || 1,
    }
}

async function saveQuotation() {
    savingQuotation.value = true
    quotationErrors.value = {}

    try {
        if (editingQuotation.value?.id) {
            await axios.put(`/api/quotations/${editingQuotation.value.id}`, quotationPayload())
        } else {
            await axios.post('/api/quotations', quotationPayload())
        }

        dialogQuotation.value = false
        resetQuotationForm()
        emit('refresh')
    } catch (error) {
        quotationErrors.value = error.response?.data?.errors || {}
        console.error('Ошибка сохранения quotation:', error)
    } finally {
        savingQuotation.value = false
    }
}

async function deleteQuotation(quotation) {
    if (!quotation?.id || !window.confirm(`Удалить quotation для "${quotation.good?.name || 'good'}"?`)) {
        return
    }

    deletingQuotationId.value = quotation.id

    try {
        await axios.delete(`/api/quotations/${quotation.id}`)
        emit('refresh')
    } catch (error) {
        console.error('Ошибка удаления quotation:', error)
    } finally {
        deletingQuotationId.value = null
    }
}

function currencyLabel(quotation) {
    return quotation?.currency?.code || quotation?.currency?.name || 'RUB'
}

function measureLabel(quotation) {
    return quotation?.measure?.name || quotation?.measure?.title || 'unit'
}

function priceLine(quotation) {
    const denominator = Number(quotation?.denominator || 1)
    const denominatorLabel = denominator === 1 ? '' : ` / ${formatNumber(denominator, 4)}`

    return `${formatNumber(quotation?.price)} ${currencyLabel(quotation)}${denominatorLabel} ${measureLabel(quotation)}`
}

function goodHref(good) {
    if (!good?.id) {
        return '#'
    }

    try {
        return route('Ameise.good.show', good.id)
    } catch (error) {
        return `/Ameise/good/${good.id}`
    }
}
</script>

<template>
    <BaseSectionCard
        title="Trade"
        icon="mdi-view-dashboard-outline"
        compact
        body-class="unit-trade-tabs"
    >
        <template #actions>
            <div class="unit-trade-tabs__stats">
                <span v-for="stat in tradeStats" :key="stat.label">
                    <strong>{{ stat.value }}</strong> {{ stat.label }}
                </span>
            </div>
        </template>

        <v-tabs
            v-model="tab"
            color="#5f0f24"
            density="compact"
            height="34"
            class="unit-trade-tabs__tabs"
        >
            <v-tab value="consumptions">
                Потребление
            </v-tab>
            <v-tab value="quotations">
                Quotations
            </v-tab>
        </v-tabs>

        <v-window v-model="tab" class="unit-trade-tabs__window">
            <v-window-item value="consumptions">
                <div class="unit-trade-tabs__toolbar">
                    <div class="unit-trade-tabs__caption">
                        Продукты, которые unit потенциально закупает
                    </div>

                    <button type="button" class="unit-trade-tabs__action" @click="showConsumptionForm = !showConsumptionForm">
                        <v-icon icon="mdi-plus" size="14" />
                        <span>consumption</span>
                    </button>
                </div>

                <v-expand-transition>
                    <div v-if="showConsumptionForm" class="unit-trade-tabs__form">
                        <v-autocomplete
                            v-model="formConsumption.product_id"
                            :items="dict.products || []"
                            item-title="rus"
                            item-value="id"
                            label="Product"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            :error-messages="consumptionErrors.product_id || []"
                        />

                        <v-text-field
                            v-model="formConsumption.quantity"
                            label="Quantity"
                            type="number"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            :error-messages="consumptionErrors.quantity || []"
                        />

                        <v-select
                            v-model="formConsumption.measure_id"
                            :items="dict.measures || []"
                            item-title="name"
                            item-value="id"
                            label="Measure"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            :error-messages="consumptionErrors.measure_id || []"
                        />

                        <v-btn
                            color="#5f0f24"
                            size="small"
                            :disabled="!formConsumption.product_id || !formConsumption.quantity"
                            :loading="savingConsumption"
                            @click="storeConsumption"
                        >
                            Save
                        </v-btn>
                    </div>
                </v-expand-transition>

                <v-data-table
                    :items="consumptions"
                    :headers="consumptionHeaders"
                    density="compact"
                    items-per-page="12"
                    fixed-header
                    height="330"
                    class="unit-consumption-table"
                >
                    <template #item.product="{ item }">
                        <div class="unit-consumption-table__product">
                            <Link
                                v-if="item.product?.id"
                                :href="productHref(item.product)"
                                class="unit-consumption-table__link"
                            >
                                {{ productName(item) }}
                            </Link>
                            <span v-else>{{ productName(item) }}</span>
                            <small v-if="item.product?.category?.name">{{ item.product.category.name }}</small>
                        </div>
                    </template>

                    <template #item.quantity="{ item }">
                        <div class="unit-consumption-table__metric">
                            <strong :class="quantityTone(item)">{{ formatNumber(item.quantity) }}</strong>
                            <span class="unit-consumption-table__bar">
                                <span :style="{ width: consumptionShare(item) + '%' }" />
                            </span>
                        </div>
                    </template>

                    <template #item.measure="{ item }">
                        <span class="unit-consumption-table__muted">{{ item.measure?.name || '-' }}</span>
                    </template>

                    <template #item.requests="{ item }">
                        <span class="unit-consumption-table__requests" :class="requestTone(item)">
                            {{ requestsCount(item) }}
                        </span>
                    </template>

                    <template #item.created_at="{ item }">
                        <span class="unit-consumption-table__date">{{ formatDate(item.created_at) }}</span>
                    </template>

                    <template #no-data>
                        <div class="unit-trade-tabs__empty">
                            Нет данных о потенциальном потреблении.
                        </div>
                    </template>
                </v-data-table>
            </v-window-item>

            <v-window-item value="quotations">
                <div class="unit-trade-tabs__toolbar">
                    <div class="unit-quote-stats">
                        <span v-for="stat in quoteStats" :key="stat.label">
                            <strong>{{ stat.value }}</strong>
                            {{ stat.label }}
                        </span>
                    </div>

                    <button type="button" class="unit-trade-tabs__action" @click="openCreateQuotation">
                        <v-icon icon="mdi-plus" size="14" />
                        <span>price</span>
                    </button>
                </div>

                <div v-if="quotations.length" class="unit-quotations-list">
                    <article
                        v-for="quotation in quotations"
                        :key="quotation.id"
                        class="unit-quotations-list__item"
                    >
                        <div class="unit-quotations-list__main">
                            <Link
                                v-if="quotation.good"
                                :href="goodHref(quotation.good)"
                                class="unit-quotations-list__good"
                            >
                                {{ quotation.good.name }}
                            </Link>
                            <span v-else class="unit-quotations-list__good">Good #{{ quotation.good_id }}</span>
                            <small>{{ measureLabel(quotation) }}</small>
                        </div>

                        <div class="unit-quotations-list__price">
                            {{ priceLine(quotation) }}
                        </div>

                        <div class="unit-quotations-list__controls">
                            <button type="button" @click="openEditQuotation(quotation)">Edit</button>
                            <button
                                type="button"
                                class="is-danger"
                                :disabled="deletingQuotationId === quotation.id"
                                @click="deleteQuotation(quotation)"
                            >
                                Delete
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else class="unit-trade-tabs__empty">
                    Нет прайс-позиций. Добавьте товары, которые поставляет эта компания.
                </div>
            </v-window-item>
        </v-window>

        <v-dialog v-model="dialogQuotation" max-width="920">
            <v-card rounded="xl">
                <v-card-title>
                    {{ editingQuotation ? 'Edit quotation' : 'New quotation' }}
                </v-card-title>

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="quotationForm.good_id"
                                v-model:search="goodsSearch"
                                :items="dict.goods || []"
                                item-title="name"
                                item-value="id"
                                label="Good"
                                variant="outlined"
                                density="compact"
                                clearable
                                :loading="goodsLoading"
                                no-filter
                                :error-messages="quotationErrors.good_id || []"
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="quotationForm.price"
                                label="Price"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="compact"
                                clearable
                                :error-messages="quotationErrors.price || []"
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-select
                                v-model="quotationForm.currency_id"
                                :items="currencies"
                                item-title="title"
                                item-value="id"
                                label="Currency"
                                variant="outlined"
                                density="compact"
                                clearable
                                :error-messages="quotationErrors.currency_id || []"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="quotationForm.measure_id"
                                :items="dict.measures || []"
                                item-title="name"
                                item-value="id"
                                label="Measure"
                                variant="outlined"
                                density="compact"
                                clearable
                                :error-messages="quotationErrors.measure_id || []"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="quotationForm.denominator"
                                label="Denominator"
                                type="number"
                                step="0.0001"
                                min="0.0001"
                                variant="outlined"
                                density="compact"
                                :error-messages="quotationErrors.denominator || []"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogQuotation = false">
                        Cancel
                    </v-btn>
                    <v-btn
                        color="#5f0f24"
                        :disabled="!quotationForm.good_id || quotationForm.price === null || quotationForm.price === ''"
                        :loading="savingQuotation"
                        @click="saveQuotation"
                    >
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </BaseSectionCard>
</template>

<style scoped>
:deep(.unit-trade-tabs) {
    padding: 0;
}

.unit-trade-tabs__stats,
.unit-quote-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    color: #7c6f67;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.unit-trade-tabs__stats strong,
.unit-quote-stats strong {
    color: #35231f;
    font-size: 0.74rem;
}

.unit-trade-tabs__tabs {
    border-bottom: 1px solid rgba(95, 15, 36, 0.12);
    padding: 0 10px;
}

.unit-trade-tabs__window {
    padding: 8px 10px 10px;
}

.unit-trade-tabs__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}

.unit-trade-tabs__caption {
    color: #7c6f67;
    font-size: 0.72rem;
    font-weight: 750;
}

.unit-trade-tabs__action {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid rgba(95, 15, 36, 0.16);
    border-radius: 999px;
    background: #5f0f24;
    color: #fff7ee;
    cursor: pointer;
    font-size: 0.66rem;
    font-weight: 900;
    letter-spacing: 0.07em;
    line-height: 1;
    padding: 6px 9px;
    text-transform: uppercase;
}

.unit-trade-tabs__form {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 120px 150px auto;
    gap: 7px;
    align-items: center;
    margin-bottom: 8px;
    padding: 8px;
    border: 1px solid rgba(95, 15, 36, 0.12);
    border-radius: 8px;
    background: #fffaf6;
}

.unit-consumption-table {
    border: 1px solid rgba(52, 48, 44, 0.14);
    border-radius: 8px;
    overflow: hidden;
}

.unit-consumption-table :deep(th) {
    height: 42px !important;
    border-bottom: 1px solid rgba(52, 48, 44, 0.26) !important;
    background: #fff !important;
    color: #34302c;
    font-family: 'Courier New', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.76rem;
    font-weight: 900;
    letter-spacing: 0;
    white-space: nowrap;
}

.unit-consumption-table :deep(td) {
    height: 42px !important;
    border-bottom: 1px solid rgba(52, 48, 44, 0.16) !important;
    color: #34302c;
    font-family: 'Courier New', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.78rem;
    vertical-align: middle;
}

.unit-consumption-table__product {
    display: grid;
    gap: 1px;
    min-width: 0;
}

.unit-consumption-table__product small,
.unit-consumption-table__muted,
.unit-consumption-table__date {
    color: #908b86;
    font-size: 0.68rem;
}

.unit-consumption-table__link {
    overflow: hidden;
    color: #3155a4;
    font-weight: 900;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-consumption-table__link:hover {
    text-decoration: underline;
    text-underline-offset: 2px;
}

.unit-consumption-table__metric {
    display: grid;
    justify-items: end;
    gap: 4px;
}

.unit-consumption-table__metric strong {
    font-size: 0.82rem;
    line-height: 1;
}

.unit-consumption-table__metric .is-high {
    color: #0f9f6e;
}

.unit-consumption-table__metric .is-positive {
    color: #34302c;
}

.unit-consumption-table__metric .is-muted {
    color: #9b9690;
}

.unit-consumption-table__bar {
    position: relative;
    width: 90px;
    height: 4px;
    overflow: hidden;
    border-radius: 999px;
    background: #e5e7eb;
}

.unit-consumption-table__bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #19a974;
}

.unit-consumption-table__requests {
    display: inline-flex;
    justify-content: flex-end;
    min-width: 36px;
    padding: 2px 5px;
    border-radius: 3px;
    font-weight: 900;
}

.unit-consumption-table__requests.is-hot {
    color: #166534;
    background: #e7f8e9;
}

.unit-consumption-table__requests.is-muted {
    color: #9b9690;
    background: #f7f7f6;
}

.unit-quotations-list {
    display: grid;
    gap: 6px;
    max-height: 330px;
    overflow: auto;
    padding-right: 2px;
}

.unit-quotations-list__item {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(150px, 0.7fr) auto;
    gap: 8px;
    align-items: center;
    padding: 8px 9px;
    border: 1px solid rgba(95, 15, 36, 0.11);
    border-radius: 8px;
    background: #fff;
}

.unit-quotations-list__main {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.unit-quotations-list__good {
    overflow: hidden;
    color: #35171f;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.18;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-quotations-list__good:hover {
    color: #8a1631;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.unit-quotations-list__main small {
    color: #82736d;
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.unit-quotations-list__price {
    color: #0f5f56;
    font-size: 0.82rem;
    font-weight: 950;
    text-align: right;
    white-space: nowrap;
}

.unit-quotations-list__controls {
    display: flex;
    gap: 5px;
}

.unit-quotations-list__controls button {
    border: 1px solid rgba(95, 15, 36, 0.16);
    border-radius: 999px;
    background: #fff;
    color: #5f0f24;
    cursor: pointer;
    font-size: 0.61rem;
    font-weight: 900;
    letter-spacing: 0.05em;
    line-height: 1;
    padding: 5px 7px;
    text-transform: uppercase;
}

.unit-quotations-list__controls .is-danger {
    border-color: rgba(190, 18, 60, 0.2);
    color: #9f1239;
}

.unit-trade-tabs__empty {
    padding: 12px;
    border: 1px dashed rgba(95, 15, 36, 0.18);
    border-radius: 8px;
    color: #7c6f67;
    font-size: 0.76rem;
    font-weight: 750;
}

@media (max-width: 880px) {
    .unit-trade-tabs__form,
    .unit-quotations-list__item {
        grid-template-columns: 1fr;
    }

    .unit-quotations-list__price {
        text-align: left;
    }

    .unit-trade-tabs__toolbar {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
