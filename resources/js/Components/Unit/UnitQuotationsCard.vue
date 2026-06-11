<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
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

const dialogQuotation = ref(false)
const editingQuotation = ref(null)
const goodsSearch = ref('')
const saving = ref(false)
const deletingId = ref(null)
const errors = ref({})

const form = reactive({
    good_id: null,
    price: null,
    currency_id: null,
    measure_id: null,
    denominator: 1,
})

const quotations = computed(() => props.unit?.quotations || [])

const currencies = computed(() => (props.dict.currencies || []).map((currency) => ({
    ...currency,
    title: [currency.code, currency.name].filter(Boolean).join(' - '),
})))

const quoteStats = computed(() => {
    const uniqueGoods = new Set(quotations.value.map((item) => item.good_id || item.good?.id).filter(Boolean))
    const currencies = new Set(quotations.value.map((item) => item.currency?.code || item.currency?.name).filter(Boolean))

    return [
        { label: 'quotes', value: quotations.value.length },
        { label: 'goods', value: uniqueGoods.size },
        { label: 'currencies', value: currencies.size || '-' },
    ]
})

const debouncedSearchGoods = useDebounceFn(async (value) => {
    await props.searchGoods(value || '')
}, 350)

watch(goodsSearch, (value) => {
    debouncedSearchGoods(value)
})

function resetForm(quotation = null) {
    editingQuotation.value = quotation
    errors.value = {}
    form.good_id = quotation?.good_id || quotation?.good?.id || null
    form.price = quotation?.price ?? null
    form.currency_id = quotation?.currency_id || quotation?.currency?.id || null
    form.measure_id = quotation?.measure_id || quotation?.measure?.id || null
    form.denominator = quotation?.denominator || 1
    goodsSearch.value = quotation?.good?.name || ''
}

async function openCreateDialog() {
    resetForm()
    dialogQuotation.value = true

    if (!props.dict.goods?.length) {
        await props.searchGoods('')
    }
}

async function openEditDialog(quotation) {
    resetForm(quotation)
    dialogQuotation.value = true

    if (!props.dict.goods?.length) {
        await props.searchGoods(quotation?.good?.name || '')
    }
}

function quotationPayload() {
    return {
        unit_id: props.unit.id,
        good_id: form.good_id,
        price: form.price,
        currency_id: form.currency_id || null,
        measure_id: form.measure_id || null,
        denominator: form.denominator || 1,
    }
}

async function saveQuotation() {
    saving.value = true
    errors.value = {}

    try {
        if (editingQuotation.value?.id) {
            await axios.put(`/api/quotations/${editingQuotation.value.id}`, quotationPayload())
        } else {
            await axios.post('/api/quotations', quotationPayload())
        }

        dialogQuotation.value = false
        resetForm()
        emit('refresh')
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        console.error('Ошибка сохранения quotation:', error)
    } finally {
        saving.value = false
    }
}

async function deleteQuotation(quotation) {
    if (!quotation?.id || !window.confirm(`Удалить quotation для "${quotation.good?.name || 'good'}"?`)) {
        return
    }

    deletingId.value = quotation.id

    try {
        await axios.delete(`/api/quotations/${quotation.id}`)
        emit('refresh')
    } catch (error) {
        console.error('Ошибка удаления quotation:', error)
    } finally {
        deletingId.value = null
    }
}

function formatMoney(value) {
    const number = Number(value)

    if (!Number.isFinite(number)) {
        return '-'
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(number)
}

function currencyLabel(quotation) {
    return quotation?.currency?.code || quotation?.currency?.name || 'RUB'
}

function measureLabel(quotation) {
    return quotation?.measure?.name || quotation?.measure?.title || 'unit'
}

function priceLine(quotation) {
    const denominator = Number(quotation?.denominator || 1)
    const denominatorLabel = denominator === 1 ? '' : ` / ${formatMoney(denominator)}`

    return `${formatMoney(quotation?.price)} ${currencyLabel(quotation)}${denominatorLabel} ${measureLabel(quotation)}`
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
        title="Quotations"
        icon="mdi-cash-multiple"
        compact
        body-class="unit-quotations"
    >
        <template #actions>
            <button type="button" class="unit-quotations__add" @click="openCreateDialog">
                + price
            </button>
        </template>

        <div class="unit-quotations__summary">
            <div
                v-for="stat in quoteStats"
                :key="stat.label"
                class="unit-quotations__stat"
            >
                <strong>{{ stat.value }}</strong>
                <span>{{ stat.label }}</span>
            </div>
        </div>

        <div v-if="quotations.length" class="unit-quotations__list">
            <article
                v-for="quotation in quotations"
                :key="quotation.id"
                class="unit-quotations__item"
            >
                <div class="unit-quotations__main">
                    <Link
                        v-if="quotation.good"
                        :href="goodHref(quotation.good)"
                        class="unit-quotations__good"
                    >
                        {{ quotation.good.name }}
                    </Link>

                    <span v-else class="unit-quotations__good">Good #{{ quotation.good_id }}</span>

                    <div class="unit-quotations__meta">
                        <span>{{ measureLabel(quotation) }}</span>
                        <span v-if="quotation.denominator && Number(quotation.denominator) !== 1">
                            denominator {{ formatMoney(quotation.denominator) }}
                        </span>
                    </div>
                </div>

                <div class="unit-quotations__price">
                    {{ priceLine(quotation) }}
                </div>

                <div class="unit-quotations__controls">
                    <button type="button" @click="openEditDialog(quotation)">Edit</button>
                    <button
                        type="button"
                        class="is-danger"
                        :disabled="deletingId === quotation.id"
                        @click="deleteQuotation(quotation)"
                    >
                        Delete
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="unit-quotations__empty">
            Нет прайс-позиций. Добавьте товары, которые поставляет эта компания.
        </div>

        <v-dialog v-model="dialogQuotation" max-width="920">
            <v-card rounded="xl">
                <v-card-title>
                    {{ editingQuotation ? 'Edit quotation' : 'New quotation' }}
                </v-card-title>

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.good_id"
                                v-model:search="goodsSearch"
                                :items="dict.goods || []"
                                item-title="name"
                                item-value="id"
                                label="Good"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                :loading="goodsLoading"
                                no-filter
                                :error-messages="errors.good_id || []"
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="form.price"
                                label="Price"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                :error-messages="errors.price || []"
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-select
                                v-model="form.currency_id"
                                :items="currencies"
                                item-title="title"
                                item-value="id"
                                label="Currency"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                :error-messages="errors.currency_id || []"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.measure_id"
                                :items="dict.measures || []"
                                item-title="name"
                                item-value="id"
                                label="Measure"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                :error-messages="errors.measure_id || []"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.denominator"
                                label="Denominator"
                                type="number"
                                step="0.0001"
                                min="0.0001"
                                variant="outlined"
                                density="comfortable"
                                :error-messages="errors.denominator || []"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogQuotation = false">
                        Cancel
                    </v-btn>
                    <v-btn
                        color="primary"
                        :disabled="!form.good_id || form.price === null || form.price === ''"
                        :loading="saving"
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
:deep(.unit-quotations) {
    background:
        radial-gradient(circle at 12% 8%, rgba(13, 148, 136, 0.11), transparent 30%),
        linear-gradient(180deg, #fff, #fffaf6);
}

.unit-quotations__add {
    border: 1px solid rgba(95, 15, 36, 0.18);
    border-radius: 999px;
    background: #5f0f24;
    color: #fff7ee;
    cursor: pointer;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    line-height: 1;
    padding: 7px 10px;
    text-transform: uppercase;
}

.unit-quotations__summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 7px;
    margin-bottom: 9px;
}

.unit-quotations__stat {
    padding: 7px 8px;
    border: 1px solid rgba(13, 148, 136, 0.15);
    border-radius: 13px;
    background: rgba(255, 255, 255, 0.82);
}

.unit-quotations__stat strong,
.unit-quotations__stat span {
    display: block;
}

.unit-quotations__stat strong {
    color: #0f5f56;
    font-size: 1.03rem;
    line-height: 1;
}

.unit-quotations__stat span {
    margin-top: 2px;
    color: #7c6f67;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.unit-quotations__list {
    display: grid;
    gap: 7px;
    max-height: 360px;
    overflow: auto;
    padding-right: 2px;
}

.unit-quotations__item {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(130px, 0.7fr) auto;
    gap: 8px;
    align-items: center;
    padding: 9px 10px;
    border: 1px solid rgba(95, 15, 36, 0.11);
    border-radius: 15px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 18px rgba(58, 31, 38, 0.045);
}

.unit-quotations__main {
    min-width: 0;
}

.unit-quotations__good {
    display: block;
    overflow: hidden;
    color: #35171f;
    font-size: 0.87rem;
    font-weight: 900;
    line-height: 1.18;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.unit-quotations__good:hover {
    color: #8a1631;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.unit-quotations__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 4px;
    color: #82736d;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.unit-quotations__price {
    color: #0f5f56;
    font-size: 0.88rem;
    font-weight: 950;
    text-align: right;
    white-space: nowrap;
}

.unit-quotations__controls {
    display: flex;
    gap: 5px;
}

.unit-quotations__controls button {
    border: 1px solid rgba(95, 15, 36, 0.16);
    border-radius: 999px;
    background: #fff;
    color: #5f0f24;
    cursor: pointer;
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    line-height: 1;
    padding: 6px 8px;
    text-transform: uppercase;
}

.unit-quotations__controls button:hover:not(:disabled) {
    background: rgba(95, 15, 36, 0.08);
}

.unit-quotations__controls button:disabled {
    cursor: wait;
    opacity: 0.5;
}

.unit-quotations__controls .is-danger {
    border-color: rgba(190, 18, 60, 0.2);
    color: #9f1239;
}

.unit-quotations__empty {
    padding: 12px;
    border: 1px dashed rgba(13, 148, 136, 0.22);
    border-radius: 14px;
    color: #7c6f67;
    font-size: 0.78rem;
    font-weight: 700;
}

@media (max-width: 760px) {
    .unit-quotations__item {
        grid-template-columns: 1fr;
    }

    .unit-quotations__price {
        text-align: left;
    }
}
</style>
