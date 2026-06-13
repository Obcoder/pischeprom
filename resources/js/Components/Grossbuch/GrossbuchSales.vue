<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { route } from 'ziggy-js'

const rows = ref([])
const totalItems = ref(0)
const months = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const detailsDialog = ref(false)
const selectedSale = ref(null)
const errorMessage = ref('')

const entities = ref([])
const goods = ref([])
const measures = ref([])

const options = reactive({
    page: 1,
    itemsPerPage: 200,
    sortBy: [{ key: 'date', order: 'desc' }],
})

const filters = reactive({
    month: null,
    date_from: '',
    date_to: '',
})

const saleForm = reactive({
    date: new Date().toISOString().slice(0, 10),
    entity_id: null,
    manualTotal: false,
    total: null,
    goods: [],
})

const headers = [
    { title: 'Дата', key: 'date', sortable: true, width: '92px' },
    { title: 'Entity / Unit / адрес', key: 'entity', sortable: true },
    { title: 'Сумма', key: 'total', sortable: true, width: '132px', align: 'end' },
    { title: 'Предыдущая', key: 'previous_sale', sortable: false, width: '150px' },
]

const groupBy = [{ key: 'month', order: 'desc' }]

const goodsById = computed(() => new Map(goods.value.map((good) => [Number(good.id), good])))
const measuresById = computed(() => new Map(measures.value.map((measure) => [Number(measure.id), measure])))

const saleLinesTotal = computed(() => {
    return saleForm.goods.reduce((sum, line) => sum + toNumber(line.total), 0)
})

const effectiveSaleTotal = computed(() => {
    return saleForm.manualTotal ? toNumber(saleForm.total) : saleLinesTotal.value
})

const canSubmitSale = computed(() => {
    const hasBase = Boolean(saleForm.date && saleForm.entity_id)
    const hasManualTotal = saleForm.manualTotal && nullableNumber(saleForm.total) !== null
    const hasLine = saleForm.goods.some((line) => {
        const filledValues = [
            nullableNumber(line.quantity),
            nullableNumber(line.price),
            nullableNumber(line.total),
        ].filter((value) => value !== null).length

        return Boolean(line.good_id && line.measure_id && filledValues >= 2)
    })

    return hasBase && (hasManualTotal || hasLine)
})

function toNumber(value, fallback = 0) {
    const number = Number(String(value ?? '').replace(',', '.'))
    return Number.isFinite(number) ? number : fallback
}

function nullableNumber(value) {
    if (value === null || value === '') return null
    const number = Number(String(value).replace(',', '.'))
    return Number.isFinite(number) ? number : null
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(toNumber(value))
}

function formatDate(value) {
    if (!value) return '-'
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
    }).format(new Date(value))
}

function monthLabel(value) {
    if (!value) return 'Без месяца'
    const [year, month] = String(value).split('-')
    return `${month}.${year}`
}

function entityUnits(entity) {
    return entity?.units || []
}

function entityBuildings(entity) {
    return entity?.buildings || []
}

function saleGoods(item) {
    return item?.goods || []
}

function goodTitle(good) {
    return good?.name || `Good #${good?.id}`
}

function entityHref(entity) {
    return entity?.id ? route('Ameise.entity.show', entity.id) : null
}

function unitHref(unit) {
    return unit?.id ? route('web.unit.show', unit.id) : null
}

function goodHref(good) {
    return good?.id ? route('Ameise.good.show', good.id) : null
}

function measureTitle(id) {
    return measuresById.value.get(Number(id))?.name || '—'
}

function lineGood(line) {
    return goodsById.value.get(Number(line.good_id)) || null
}

function goodVatText(good) {
    if (!good?.vat_rate) return 'НДС —'
    return `${good.vat_rate.title || 'НДС'} ${good.vat_rate.rate}%`
}

async function fetchMeta() {
    const [entitiesRes, goodsRes, measuresRes] = await Promise.all([
        axios.get('/api/entities', {
            params: {
                itemsPerPage: 1000,
                sortBy: 'name',
                sortDesc: false,
            },
        }),
        axios.get('/api/goods', {
            params: {
                per_page: 1000,
                sort_by: 'name',
            },
        }),
        axios.get('/api/measures'),
    ])

    entities.value = entitiesRes.data.data || entitiesRes.data || []
    goods.value = goodsRes.data.data || goodsRes.data || []
    measures.value = measuresRes.data.data || measuresRes.data || []
}

async function fetchSales() {
    loading.value = true
    errorMessage.value = ''

    try {
        const sort = options.sortBy?.[0] || { key: 'date', order: 'desc' }
        const { data } = await axios.get('/api/sales', {
            params: {
                server: 1,
                page: options.page,
                itemsPerPage: options.itemsPerPage,
                sortBy: sort.key,
                sortDesc: sort.order === 'desc',
                month: filters.month,
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
            },
        })

        rows.value = data.data || []
        totalItems.value = data.meta?.total || 0
        months.value = data.meta?.months || []
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить продажи'
        console.error('fetchSales error:', error?.response?.data || error)
    } finally {
        loading.value = false
    }
}

function handleOptionsUpdate(nextOptions) {
    options.page = nextOptions.page
    options.itemsPerPage = 200
    options.sortBy = nextOptions.sortBy?.length ? nextOptions.sortBy : [{ key: 'date', order: 'desc' }]
    fetchSales()
}

function applyMonth(month) {
    filters.month = filters.month === month ? null : month
    options.page = 1
    fetchSales()
}

function applyDateRange() {
    options.page = 1
    fetchSales()
}

function resetFilters() {
    filters.month = null
    filters.date_from = ''
    filters.date_to = ''
    options.page = 1
    fetchSales()
}

function resetForm() {
    saleForm.date = new Date().toISOString().slice(0, 10)
    saleForm.entity_id = null
    saleForm.manualTotal = false
    saleForm.total = null
    saleForm.goods = [makeLine()]
}

function openCreate() {
    resetForm()
    dialog.value = true
}

function openSaleDetails(item) {
    selectedSale.value = item
    detailsDialog.value = true
}

function makeLine() {
    return {
        good_id: null,
        measure_id: null,
        quantity: null,
        price: null,
        total: null,
    }
}

function addLine() {
    saleForm.goods.push(makeLine())
}

function removeLine(index) {
    saleForm.goods.splice(index, 1)
    if (!saleForm.goods.length) addLine()
}

function handleGoodSelected(line) {
    const good = lineGood(line)
    if (!line.measure_id && measures.value.length) {
        line.measure_id = measures.value[0].id
    }
    return good
}

function recalcLine(line, changed) {
    const quantity = nullableNumber(line.quantity)
    const price = nullableNumber(line.price)
    const total = nullableNumber(line.total)

    if (changed === 'total') {
        if (quantity && quantity > 0) line.price = round(total / quantity, 4)
        else if (price && price > 0) line.quantity = round(total / price, 4)
    }

    if (changed === 'quantity') {
        if (total !== null && quantity && quantity > 0) line.price = round(total / quantity, 4)
        else if (price !== null) line.total = round(quantity * price, 2)
    }

    if (changed === 'price') {
        if (total !== null && price && price > 0) line.quantity = round(total / price, 4)
        else if (quantity !== null) line.total = round(quantity * price, 2)
    }
}

function round(value, precision) {
    const number = Number(value)
    if (!Number.isFinite(number)) return null
    const power = 10 ** precision
    return Math.round(number * power) / power
}

async function submitSale() {
    saving.value = true
    errorMessage.value = ''

    try {
        await axios.post('/api/sales', {
            date: saleForm.date,
            entity_id: saleForm.entity_id,
            total: saleForm.manualTotal ? effectiveSaleTotal.value : null,
            goods: saleForm.goods
                .filter((line) => line.good_id)
                .map((line) => ({
                    good_id: line.good_id,
                    measure_id: line.measure_id,
                    quantity: nullableNumber(line.quantity),
                    price: nullableNumber(line.price),
                    total: nullableNumber(line.total),
                })),
        })

        dialog.value = false
        await fetchSales()
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || Object.values(error?.response?.data?.errors || {})?.flat()?.[0] || 'Не удалось сохранить продажу'
        console.error('submit sale error:', error?.response?.data || error)
    } finally {
        saving.value = false
    }
}

onMounted(async () => {
    await fetchMeta()
    resetForm()
    await fetchSales()
})
</script>

<template>
    <section class="sales-board">
        <div class="sales-board__main">
            <div class="sales-toolbar">
                <div>
                    <div class="sales-toolbar__eyebrow">Grossbuch / продажи</div>
                    <h2>Продажи</h2>
                </div>

                <div class="sales-toolbar__actions">
                    <v-chip size="small" color="deep-purple" variant="flat">
                        {{ totalItems }} записей
                    </v-chip>

                    <v-btn
                        color="deep-purple"
                        variant="flat"
                        density="compact"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        Продать
                    </v-btn>
                </div>
            </div>

            <v-alert
                v-if="errorMessage"
                type="error"
                variant="tonal"
                density="compact"
                class="mb-2"
            >
                {{ errorMessage }}
            </v-alert>

            <v-data-table-server
                :headers="headers"
                :items="rows"
                :items-length="totalItems"
                :loading="loading"
                :page="options.page"
                :items-per-page="200"
                :sort-by="options.sortBy"
                :group-by="groupBy"
                fixed-header
                density="compact"
                class="sales-grid"
                item-value="id"
                height="calc(100vh - 260px)"
                @update:options="handleOptionsUpdate"
            >
                <template #group-header="{ item, columns, toggleGroup, isGroupOpen }">
                    <tr class="sales-grid__month-row">
                        <td :colspan="columns.length">
                            <button type="button" @click="toggleGroup(item)">
                                <v-icon :icon="isGroupOpen(item) ? 'mdi-chevron-down' : 'mdi-chevron-right'" size="15" />
                                {{ monthLabel(item.value) }}
                            </button>
                        </td>
                    </tr>
                </template>

                <template #item.date="{ item }">
                    <span class="sales-date">{{ formatDate(item.date) }}</span>
                </template>

                <template #item.entity="{ item }">
                    <div class="sales-party">
                        <a v-if="entityHref(item.entity)" :href="entityHref(item.entity)" class="sales-party__entity">
                            {{ item.entity?.name || '—' }}
                        </a>
                        <span v-else class="sales-party__entity">—</span>

                        <div class="sales-party__units">
                            <a
                                v-for="unit in entityUnits(item.entity).slice(0, 2)"
                                :key="unit.id"
                                :href="unitHref(unit)"
                            >
                                {{ unit.name }}
                            </a>
                            <span v-if="entityUnits(item.entity).length > 2">+{{ entityUnits(item.entity).length - 2 }}</span>
                        </div>

                        <div class="sales-party__address">
                            <span v-for="building in entityBuildings(item.entity).slice(0, 1)" :key="building.id">
                                {{ building.city?.name ? `${building.city.name}, ` : '' }}{{ building.address }}
                            </span>
                        </div>
                    </div>
                </template>

                <template #item.total="{ item }">
                    <button type="button" class="sales-money-button" @click="openSaleDetails(item)">
                        <strong>{{ formatMoney(item.total) }}</strong>
                        <span>{{ saleGoods(item).length }} поз.</span>
                    </button>
                </template>

                <template #item.previous_sale="{ item }">
                    <div v-if="item.previous_sale" class="sales-prev">
                        <strong>{{ formatMoney(item.previous_sale.total) }}</strong>
                        <span>{{ item.previous_sale.days }} дн.</span>
                    </div>
                    <span v-else class="sales-first">Первая продажа</span>
                </template>
            </v-data-table-server>
        </div>

        <aside class="sales-filter">
            <div class="sales-filter__title">Период</div>

            <div class="sales-filter__range">
                <v-text-field
                    v-model="filters.date_from"
                    label="с"
                    type="date"
                    variant="solo-filled"
                    density="compact"
                    hide-details
                />
                <v-text-field
                    v-model="filters.date_to"
                    label="по"
                    type="date"
                    variant="solo-filled"
                    density="compact"
                    hide-details
                />
                <v-btn color="deep-purple" density="compact" variant="flat" @click="applyDateRange">
                    Применить
                </v-btn>
                <v-btn density="compact" variant="text" @click="resetFilters">
                    Сброс
                </v-btn>
            </div>

            <div class="sales-filter__title mt-3">Месяцы</div>
            <div class="sales-month-list">
                <button
                    v-for="month in months"
                    :key="month.value"
                    type="button"
                    :class="['sales-month', { 'sales-month--active': filters.month === month.value }]"
                    @click="applyMonth(month.value)"
                >
                    <span>{{ month.label }}</span>
                    <b>{{ month.count }}</b>
                    <small>{{ formatMoney(month.total) }}</small>
                </button>
            </div>
        </aside>

        <v-dialog v-model="detailsDialog" max-width="980" scrollable class="sale-details-dialog">
            <v-card class="sale-details">
                <v-card-title class="sale-details__title">
                    <div>
                        <span>Детали продажи</span>
                        <strong>{{ formatMoney(selectedSale?.total) }}</strong>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="detailsDialog = false" />
                </v-card-title>

                <v-card-text v-if="selectedSale" class="sale-details__body">
                    <div class="sale-details__summary">
                        <div>
                            <small>Дата</small>
                            <strong>{{ formatDate(selectedSale.date) }}</strong>
                        </div>
                        <div>
                            <small>Entity</small>
                            <a v-if="entityHref(selectedSale.entity)" :href="entityHref(selectedSale.entity)">
                                {{ selectedSale.entity?.name }}
                            </a>
                            <strong v-else>—</strong>
                        </div>
                        <div>
                            <small>Предыдущая</small>
                            <strong v-if="selectedSale.previous_sale">
                                {{ formatMoney(selectedSale.previous_sale.total) }} / {{ selectedSale.previous_sale.days }} дн.
                            </strong>
                            <strong v-else>Первая продажа</strong>
                        </div>
                    </div>

                    <div class="sale-details__units">
                        <a
                            v-for="unit in entityUnits(selectedSale.entity)"
                            :key="unit.id"
                            :href="unitHref(unit)"
                        >
                            {{ unit.name }}
                        </a>
                        <span v-for="building in entityBuildings(selectedSale.entity)" :key="building.id">
                            {{ building.city?.name ? `${building.city.name}, ` : '' }}{{ building.address }}
                        </span>
                    </div>

                    <div class="sale-details__grid">
                        <div class="sale-details__head">
                            <span>Good</span>
                            <span>НДС</span>
                            <span>Тарность</span>
                            <span>Кол-во</span>
                            <span>Ед.</span>
                            <span>Цена</span>
                            <span>Сумма</span>
                        </div>

                        <div
                            v-for="good in saleGoods(selectedSale)"
                            :key="good.id"
                            class="sale-details__row"
                        >
                            <a :href="goodHref(good)">{{ good.name }}</a>
                            <span>{{ goodVatText(good) }}</span>
                            <span>{{ good.denominator || '—' }}</span>
                            <strong>{{ formatMoney(good.pivot.quantity) }}</strong>
                            <span>{{ measureTitle(good.pivot.measure_id) }}</span>
                            <strong>{{ formatMoney(good.pivot.price) }}</strong>
                            <strong>{{ formatMoney(good.pivot.total) }}</strong>
                        </div>

                        <div v-if="!saleGoods(selectedSale).length" class="sale-details__empty">
                            Товары не прикреплены к продаже
                        </div>
                    </div>
                </v-card-text>
            </v-card>
        </v-dialog>

        <v-dialog v-model="dialog" max-width="1260" scrollable>
            <v-card class="sale-dialog">
                <v-card-title class="sale-dialog__title">
                    <div>
                        <span>Новая продажа</span>
                        <strong>{{ formatMoney(effectiveSaleTotal) }}</strong>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="dialog = false" />
                </v-card-title>

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="saleForm.date"
                                label="Дата продажи"
                                type="date"
                                variant="solo-filled"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="saleForm.entity_id"
                                :items="entities"
                                item-title="name"
                                item-value="id"
                                label="Entity"
                                variant="solo-filled"
                                density="compact"
                                clearable
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-switch
                                v-model="saleForm.manualTotal"
                                label="Сумма вручную"
                                color="deep-purple"
                                density="compact"
                                hide-details
                            />
                            <v-text-field
                                v-if="saleForm.manualTotal"
                                v-model="saleForm.total"
                                label="Сумма продажи"
                                type="number"
                                variant="solo-filled"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                    </v-row>

                    <div class="sale-lines">
                        <div class="sale-lines__head">
                            <span>Good</span>
                            <span>НДС</span>
                            <span>Тарность</span>
                            <span>Кол-во</span>
                            <span>Ед.</span>
                            <span>Цена</span>
                            <span>Сумма</span>
                            <span></span>
                        </div>

                        <div
                            v-for="(line, index) in saleForm.goods"
                            :key="index"
                            class="sale-line"
                        >
                            <v-autocomplete
                                v-model="line.good_id"
                                :items="goods"
                                :item-title="goodTitle"
                                item-value="id"
                                label="Good"
                                variant="solo-filled"
                                density="compact"
                                hide-details
                                @update:model-value="handleGoodSelected(line)"
                            />

                            <span class="sale-line__meta sale-line__vat">{{ goodVatText(lineGood(line)) }}</span>
                            <span class="sale-line__meta">{{ lineGood(line)?.denominator || '—' }}</span>

                            <v-text-field
                                v-model="line.quantity"
                                type="number"
                                variant="solo-filled"
                                density="compact"
                                hide-details
                                @update:model-value="recalcLine(line, 'quantity')"
                            />

                            <v-select
                                v-model="line.measure_id"
                                :items="measures"
                                item-title="name"
                                item-value="id"
                                variant="solo-filled"
                                density="compact"
                                hide-details
                            />

                            <v-text-field
                                v-model="line.price"
                                type="number"
                                variant="solo-filled"
                                density="compact"
                                hide-details
                                @update:model-value="recalcLine(line, 'price')"
                            />

                            <v-text-field
                                v-model="line.total"
                                type="number"
                                variant="solo-filled"
                                density="compact"
                                hide-details
                                @update:model-value="recalcLine(line, 'total')"
                            />

                            <v-btn
                                icon="mdi-delete-outline"
                                size="small"
                                variant="text"
                                color="red-lighten-2"
                                @click="removeLine(index)"
                            />
                        </div>
                    </div>

                    <div class="sale-dialog__footer-line">
                        <v-btn color="red-darken-4" variant="flat" density="compact" prepend-icon="mdi-plus" @click="addLine">
                            Товар
                        </v-btn>
                        <span>Итого по товарам: <strong>{{ formatMoney(saleLinesTotal) }}</strong></span>
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Отмена</v-btn>
                    <v-btn color="deep-purple" variant="flat" :loading="saving" :disabled="!canSubmitSale" @click="submitSale">
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.sales-board {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 178px;
    gap: 10px;
    height: calc(100vh - 150px);
    overflow: hidden;
}

.sales-board__main {
    min-width: 0;
    overflow: hidden;
}

.sales-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 48px;
    padding: 8px 10px;
    border: 1px solid rgba(102, 72, 180, 0.28);
    border-radius: 4px 4px 0 0;
    background: linear-gradient(180deg, #f9f7ff 0%, #ece6ff 100%);
    color: #32205f;
}

.sales-toolbar h2 {
    margin: 0;
    font-size: 18px;
    line-height: 1;
}

.sales-toolbar__eyebrow {
    margin-bottom: 3px;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(50, 32, 95, 0.56);
}

.sales-toolbar__actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.sales-grid {
    --sales-purple: #5b3bb0;
    border: 1px solid rgba(91, 59, 176, 0.34);
    border-top: 0;
    font-size: 11px;
}

.sales-grid :deep(.v-table__wrapper) {
    scrollbar-width: thin;
}

.sales-grid :deep(thead th) {
    height: 28px !important;
    background: var(--sales-purple) !important;
    color: #ffffff !important;
    border-right: 1px solid rgba(255, 255, 255, 0.28);
    font-size: 11px;
    font-weight: 900 !important;
}

.sales-grid :deep(tbody td) {
    height: 30px !important;
    padding: 2px 6px !important;
    border-right: 1px solid #ddd5f5;
    border-bottom: 1px solid #ddd5f5;
    background: #fff;
}

.sales-grid :deep(tbody tr:hover td) {
    background: #f4f0ff !important;
}

.sales-grid__month-row td {
    height: 24px !important;
    background: #eee7ff !important;
    color: #38216e;
    font-weight: 900;
}

.sales-grid__month-row button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 900;
}

.sales-date {
    color: #38216e;
    font-family: 'Courier New', monospace;
    font-weight: 900;
}

.sales-money-button {
    display: grid;
    justify-items: end;
    gap: 1px;
    width: 100%;
    padding: 1px 5px;
    border: 1px solid rgba(91, 59, 176, 0.24);
    border-radius: 3px;
    background: #f8f5ff;
    line-height: 1.05;
    text-align: right;
}

.sales-money-button:hover {
    background: #ebe3ff;
    border-color: rgba(91, 59, 176, 0.46);
}

.sales-money-button strong {
    color: #2f1678;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.sales-money-button span {
    color: #806fa5;
    font-size: 9px;
}

.sales-party {
    display: grid;
    gap: 1px;
    line-height: 1.15;
}

.sales-party__entity {
    color: #231357;
    font-weight: 900;
    text-decoration: none;
}

.sales-party__units,
.sales-party__address {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    color: #6c5b93;
    font-size: 9px;
}

.sales-party__units a {
    color: #5b3bb0;
    text-decoration: none;
}

.sales-first {
    color: #7c6b9c;
    font-size: 10px;
}

.sales-prev {
    display: grid;
    gap: 1px;
    line-height: 1.05;
    text-align: right;
}

.sales-prev strong {
    color: #2f1678;
    font-family: 'Courier New', monospace;
}

.sales-prev span {
    color: #806fa5;
    font-size: 9px;
}

.sales-filter {
    min-width: 0;
    padding: 8px;
    border: 1px solid rgba(91, 59, 176, 0.28);
    background: linear-gradient(180deg, #f6f2ff 0%, #ffffff 100%);
    color: #342263;
    overflow: hidden;
}

.sales-filter__title {
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.sales-filter__range {
    display: grid;
    gap: 6px;
    margin-top: 8px;
}

.sales-month-list {
    display: grid;
    gap: 4px;
    max-height: calc(100vh - 360px);
    margin-top: 8px;
    overflow: auto;
}

.sales-month {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1px 4px;
    padding: 5px 6px;
    border: 1px solid #d9cff8;
    background: #fff;
    color: #36246c;
    text-align: left;
}

.sales-month--active {
    background: #5b3bb0;
    color: #fff;
}

.sales-month small {
    grid-column: 1 / -1;
    color: inherit;
    opacity: 0.72;
    font-family: 'Courier New', monospace;
}

.sale-details {
    border: 1px solid rgba(91, 59, 176, 0.45);
    background: #f7f4ff;
    color: #2e2058;
}

.sale-details__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 44px;
    background: linear-gradient(90deg, #4b2f9b 0%, #6f49c9 100%);
    color: #fff;
}

.sale-details__title div {
    display: flex;
    align-items: baseline;
    gap: 14px;
}

.sale-details__title span {
    font-size: 14px;
    font-weight: 900;
}

.sale-details__title strong {
    font-family: 'Courier New', monospace;
    font-size: 15px;
}

.sale-details__body {
    display: grid;
    gap: 8px;
    padding: 10px !important;
}

.sale-details__summary {
    display: grid;
    grid-template-columns: 100px minmax(0, 1fr) 190px;
    gap: 6px;
}

.sale-details__summary > div {
    display: grid;
    gap: 1px;
    padding: 5px 7px;
    border: 1px solid #d9cff8;
    background: #fff;
}

.sale-details__summary small {
    color: #7c6b9c;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.sale-details__summary strong,
.sale-details__summary a {
    color: #2f1678;
    font-size: 12px;
    font-weight: 900;
    text-decoration: none;
}

.sale-details__units {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.sale-details__units a,
.sale-details__units span {
    padding: 2px 6px;
    border: 1px solid #d9cff8;
    background: #fff;
    color: #5b3bb0;
    font-size: 10px;
    text-decoration: none;
}

.sale-details__grid {
    border: 1px solid #cfc2f3;
    background: #fff;
}

.sale-details__head,
.sale-details__row {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) 88px 70px 82px 68px 92px 102px;
    align-items: center;
}

.sale-details__head {
    min-height: 26px;
    background: #5b3bb0;
    color: #fff;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.sale-details__head span,
.sale-details__row > * {
    min-width: 0;
    padding: 3px 6px;
    border-right: 1px solid #ddd5f5;
}

.sale-details__row {
    min-height: 28px;
    border-top: 1px solid #ddd5f5;
    font-size: 11px;
}

.sale-details__row a {
    overflow: hidden;
    color: #2f1678;
    font-weight: 900;
    text-overflow: ellipsis;
    text-decoration: none;
    white-space: nowrap;
}

.sale-details__row span {
    color: #6c5b93;
}

.sale-details__row strong {
    color: #2f1678;
    font-family: 'Courier New', monospace;
    text-align: right;
}

.sale-details__empty {
    padding: 12px;
    color: #7c6b9c;
    font-size: 12px;
}

.sale-dialog {
    background: #14080a;
    color: #ffe8e8;
}

.sale-dialog__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, #26080c 0%, #4b1016 100%);
}

.sale-dialog__title div {
    display: flex;
    align-items: baseline;
    gap: 14px;
}

.sale-dialog__title strong {
    color: #ff9090;
    font-family: 'Courier New', monospace;
}

.sale-lines {
    margin-top: 10px;
    border: 1px solid rgba(255, 80, 80, 0.36);
    background: #0d0809;
}

.sale-lines__head,
.sale-line {
    display: grid;
    grid-template-columns: minmax(220px, 1.9fr) 88px 70px 90px 90px 90px 100px 34px;
    gap: 4px;
    align-items: center;
}

.sale-lines__head {
    padding: 5px 8px;
    background: #3a090e;
    color: #ffaaaa;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.sale-line {
    padding: 5px 8px;
    border-top: 1px solid rgba(255, 80, 80, 0.18);
}

.sale-line__meta {
    color: #ffd0d0;
    font-size: 11px;
    font-family: 'Courier New', monospace;
}

.sale-line__vat {
    color: #ff8b8b;
}

.sale-dialog__footer-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    color: #ffdede;
}

@media (max-width: 1180px) {
    .sales-board {
        grid-template-columns: 1fr;
        height: auto;
        overflow: visible;
    }

    .sales-filter {
        order: -1;
    }

    .sale-lines__head,
    .sale-line {
        grid-template-columns: 1fr 80px 66px 80px 82px 82px 92px 32px;
        overflow-x: auto;
    }

    .sale-details__summary,
    .sale-details__head,
    .sale-details__row {
        grid-template-columns: 1fr;
    }
}
</style>
