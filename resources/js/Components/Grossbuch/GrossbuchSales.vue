<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { route } from 'ziggy-js'
import EntityFormDialog from '@/Components/Dictionaries/Entities/EntityFormDialog.vue'
import { useEntityApi } from '@/Composables/entities/useEntityApi.js'
import { useEntityForm } from '@/Composables/entities/useEntityForm.js'

const rows = ref([])
const totalItems = ref(0)
const totalAmount = ref(0)
const months = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const entityDialog = ref(false)
const entitySaving = ref(false)
const entityMetaLoading = ref(false)
const detailsDialog = ref(false)
const detailsAddOpen = ref(false)
const detailsSaving = ref(false)
const selectedSale = ref(null)
const errorMessage = ref('')
const detailsErrorMessage = ref('')
const detailsMessage = ref('')
const dateRangeMenu = ref(false)
let salesRequestId = 0

const entities = ref([])
const goods = ref([])
const measures = ref([])
const entityMeta = ref({
    classifications: [],
    countries: [],
    cities: [],
    buildings: [],
    emails: [],
    telephones: [],
    units: [],
    chats: [],
})

const { getMeta: getEntityMeta, createOne: createEntity } = useEntityApi()
const {
    form: entityForm,
    resetForm: resetEntityForm,
    toPayload: entityPayload,
} = useEntityForm()

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

const detailsLine = reactive(makeLine())

const headers = [
    { title: 'Дата', key: 'date', sortable: true, width: '78px' },
    { title: 'Покупатель / адрес', key: 'entity', sortable: true },
    { title: 'Сумма', key: 'total', sortable: true, width: '116px', align: 'end' },
    { title: 'Предыдущая', key: 'previous_sale', sortable: false, width: '126px', align: 'end' },
]

const pageCount = computed(() => Math.max(1, Math.ceil(totalItems.value / options.itemsPerPage)))
const firstRow = computed(() => rows.value.length ? (options.page - 1) * options.itemsPerPage + 1 : 0)
const lastRow = computed(() => rows.value.length ? firstRow.value + rows.value.length - 1 : 0)
const hasFilters = computed(() => Boolean(filters.month || filters.date_from || filters.date_to))
const selectedMonthLabel = computed(() => {
    if (!filters.month) return 'Все месяцы'
    return months.value.find((month) => month.value === filters.month)?.label || filters.month.split('-').reverse().join('.')
})
const dateRangeLabel = computed(() => {
    if (filters.date_from && filters.date_to) return `${formatDate(filters.date_from)}–${formatDate(filters.date_to)}`
    if (filters.date_from) return `С ${formatDate(filters.date_from)}`
    if (filters.date_to) return `По ${formatDate(filters.date_to)}`
    return 'Период'
})
const pageAmount = computed(() => rows.value.reduce((sum, row) => sum + toNumber(row.total), 0))

const goodsById = computed(() => new Map(goods.value.map((good) => [Number(good.id), good])))
const measuresById = computed(() => new Map(measures.value.map((measure) => [Number(measure.id), measure])))
const entityOptions = computed(() => entities.value.map((entity) => ({
    ...entity,
    search_text: [
        entity?.name,
        entity?.full_name,
        entityUnitsText(entity),
        entityBuildingsText(entity),
    ].filter(Boolean).join(' '),
})))
const selectedEntityOption = computed(() => {
    return entityOptions.value.find((entity) => Number(entity.id) === Number(saleForm.entity_id)) || null
})

const saleLinesTotal = computed(() => {
    return saleForm.goods.reduce((sum, line) => sum + toNumber(line.total), 0)
})

const effectiveSaleTotal = computed(() => {
    return saleForm.manualTotal ? toNumber(saleForm.total) : saleLinesTotal.value
})
const entityDialogLoading = computed(() => entitySaving.value || entityMetaLoading.value)

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

const canAttachGood = computed(() => {
    const filledValues = [
        nullableNumber(detailsLine.quantity),
        nullableNumber(detailsLine.price),
        nullableNumber(detailsLine.total),
    ].filter((value) => value !== null).length

    return Boolean(
        selectedSale.value?.id
        && detailsLine.good_id
        && detailsLine.measure_id
        && filledValues >= 2
    )
})

function normalizeSearchText(value) {
    return String(value ?? '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim()
}

function searchText(...values) {
    return normalizeSearchText(values.filter(Boolean).join(' '))
}

function entitySearchFilter(value, query, item) {
    const raw = item?.raw || item || {}
    const haystack = searchText(
        value,
        raw.search_text,
        raw.name,
        raw.full_name,
        entityUnitsText(raw, 12),
        entityBuildingsText(raw, 12),
    )
    const tokens = normalizeSearchText(query).split(' ').filter(Boolean)

    return tokens.every((token) => haystack.includes(token)) ? 0 : -1
}

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

function entityUnits(entity) {
    return entity?.units || []
}

function entityBuildings(entity) {
    return entity?.buildings || []
}

function entityUnitsText(entity, limit = 3) {
    const units = entityUnits(entity)
        .map((unit) => unit?.name)
        .filter(Boolean)

    if (!units.length) return ''

    const visible = units.slice(0, limit).join(' · ')
    const extra = units.length > limit ? ` +${units.length - limit}` : ''

    return `${visible}${extra}`
}

function entityBuildingsText(entity, limit = 2) {
    const buildings = entityBuildings(entity)
        .map((building) => {
            const city = building?.city?.name
            const address = building?.address

            return [city, address].filter(Boolean).join(', ')
        })
        .filter(Boolean)

    if (!buildings.length) return ''

    const visible = buildings.slice(0, limit).join(' · ')
    const extra = buildings.length > limit ? ` +${buildings.length - limit}` : ''

    return `${visible}${extra}`
}

function entityOptionTitle(entity) {
    return entity?.search_text || entity?.name || `Контрагент #${entity?.id}`
}

function saleGoods(item) {
    return item?.goods || []
}

function goodTitle(good) {
    return good?.name || `Товар #${good?.id}`
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

function normalizeEntity(entity) {
    return {
        ...entity,
        units: entity?.units || [],
        buildings: entity?.buildings || [],
        cities: entity?.cities || [],
        emails: entity?.emails || [],
        telephones: entity?.telephones || [],
        chats: entity?.chats || [],
    }
}

function upsertEntity(entity) {
    if (!entity?.id) {
        return
    }

    const normalized = normalizeEntity(entity)

    entities.value = [
        normalized,
        ...entities.value.filter((item) => Number(item.id) !== Number(normalized.id)),
    ].sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ru'))
}

function mergeEntityBuildingMeta(building) {
    if (!building?.id) {
        return
    }

    entityMeta.value.buildings = [
        building,
        ...entityMeta.value.buildings.filter((item) => Number(item.id) !== Number(building.id)),
    ].sort((a, b) => {
        const cityComparison = (a.city?.name || '').localeCompare(b.city?.name || '', 'ru')

        if (cityComparison !== 0) {
            return cityComparison
        }

        return (a.address || '').localeCompare(b.address || '', 'ru')
    })
}

async function loadEntityMeta() {
    entityMetaLoading.value = true

    try {
        entityMeta.value = await getEntityMeta()
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить справочники контрагентов'
        console.error('load entity meta error:', error?.response?.data || error)
    } finally {
        entityMetaLoading.value = false
    }
}

async function fetchMeta() {
    const [entitiesRes, goodsRes, measuresRes, entityMetaRes] = await Promise.all([
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
        getEntityMeta(),
    ])

    entities.value = (entitiesRes.data.data || entitiesRes.data || []).map(normalizeEntity)
    goods.value = goodsRes.data.data || goodsRes.data || []
    measures.value = measuresRes.data.data || measuresRes.data || []
    entityMeta.value = entityMetaRes
}

async function fetchSales() {
    const requestId = ++salesRequestId
    loading.value = true
    errorMessage.value = ''

    try {
        const sort = options.sortBy?.[0] || { key: 'date', order: 'desc' }
        const { data } = await axios.get('/api/sales', {
            params: {
                server: 1,
                page: options.page,
                itemsPerPage: options.itemsPerPage,
                sortBy: sort.key === 'entity' ? 'entity.name' : sort.key,
                sortDesc: sort.order === 'desc',
                month: filters.month,
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
            },
        })

        if (requestId !== salesRequestId) return

        rows.value = data.data || []
        totalItems.value = data.meta?.total || 0
        totalAmount.value = data.meta?.total_amount ?? 0
        months.value = data.meta?.months || []
    } catch (error) {
        if (requestId !== salesRequestId) return
        errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить продажи'
        console.error('fetchSales error:', error?.response?.data || error)
    } finally {
        if (requestId === salesRequestId) loading.value = false
    }
}

function handleOptionsUpdate(nextOptions) {
    const nextSort = nextOptions.sortBy?.length ? nextOptions.sortBy : [{ key: 'date', order: 'desc' }]
    if (nextOptions.page === options.page && JSON.stringify(nextSort) === JSON.stringify(options.sortBy)) return
    options.page = nextOptions.page
    options.sortBy = nextSort
    fetchSales()
}

function changePage(page) {
    if (page < 1 || page > pageCount.value || loading.value) return
    options.page = page
    fetchSales()
}

function changePageSize(event) {
    options.itemsPerPage = Number(event.target.value)
    options.page = 1
    fetchSales()
}

function applyMonth(month) {
    filters.month = filters.month === month ? null : month
    options.page = 1
    fetchSales()
}

function applyDateRange() {
    if (filters.date_from && filters.date_to && filters.date_from > filters.date_to) {
        errorMessage.value = 'Дата начала периода должна быть не позже даты окончания.'
        return
    }
    dateRangeMenu.value = false
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

function openEntityCreate() {
    resetEntityForm()
    entityDialog.value = true

    if (!entityMeta.value.classifications.length && !entityMetaLoading.value) {
        loadEntityMeta()
    }
}

function openSaleDetails(item) {
    selectedSale.value = item
    detailsAddOpen.value = false
    detailsErrorMessage.value = ''
    detailsMessage.value = ''
    resetDetailsLine()
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

function resetDetailsLine() {
    Object.assign(detailsLine, makeLine())
}

function toggleDetailsAdd() {
    detailsAddOpen.value = !detailsAddOpen.value
    detailsErrorMessage.value = ''
    detailsMessage.value = ''

    if (detailsAddOpen.value) {
        resetDetailsLine()
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

function handleDetailsGoodSelected() {
    detailsErrorMessage.value = ''
    detailsMessage.value = ''
    handleGoodSelected(detailsLine)
}

function recalcLine(line, changed) {
    const quantity = nullableNumber(line.quantity)
    const price = nullableNumber(line.price)
    const total = nullableNumber(line.total)

    if (changed === 'total') {
        if (total === null) return
        if (quantity !== null && quantity > 0) line.price = round(total / quantity, 4)
        else if (price !== null && price > 0) line.quantity = round(total / price, 4)
    }

    if (changed === 'quantity') {
        if (quantity === null) return
        if (price !== null) line.total = round(quantity * price, 2)
        else if (total !== null && quantity > 0) line.price = round(total / quantity, 4)
    }

    if (changed === 'price') {
        if (price === null) return
        if (quantity !== null) line.total = round(quantity * price, 2)
        else if (total !== null && price > 0) line.quantity = round(total / price, 4)
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

async function attachGoodToSale() {
    if (detailsSaving.value || !selectedSale.value?.id || !canAttachGood.value) {
        return
    }

    detailsSaving.value = true
    detailsErrorMessage.value = ''
    detailsMessage.value = ''

    try {
        const saleId = selectedSale.value.id
        const { data } = await axios.post(`/api/sales/${saleId}/goods`, {
            good_id: detailsLine.good_id,
            measure_id: detailsLine.measure_id,
            quantity: nullableNumber(detailsLine.quantity),
            price: nullableNumber(detailsLine.price),
            total: nullableNumber(detailsLine.total),
        })
        const updatedSale = data.data
        const rowIndex = rows.value.findIndex((item) => Number(item.id) === Number(saleId))

        selectedSale.value = updatedSale

        if (rowIndex >= 0) {
            rows.value.splice(rowIndex, 1, updatedSale)
        }

        resetDetailsLine()
        detailsMessage.value = 'Товар добавлен. Сумма продажи обновлена.'
        await fetchSales()
    } catch (error) {
        detailsErrorMessage.value = error?.response?.data?.message
            || Object.values(error?.response?.data?.errors || {})?.flat()?.[0]
            || 'Не удалось добавить товар в продажу'
        console.error('attach sale good error:', error?.response?.data || error)
    } finally {
        detailsSaving.value = false
    }
}

async function submitEntity() {
    entitySaving.value = true
    errorMessage.value = ''

    try {
        const saved = await createEntity(entityPayload())

        upsertEntity(saved)
        saleForm.entity_id = saved.id
        entityDialog.value = false
        resetEntityForm()
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || Object.values(error?.response?.data?.errors || {})?.flat()?.[0] || 'Не удалось сохранить контрагента'
        console.error('submit entity error:', error?.response?.data || error)
    } finally {
        entitySaving.value = false
    }
}

onMounted(async () => {
    resetForm()
    await Promise.all([
        fetchMeta().catch((error) => {
            errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить справочники'
        }),
        fetchSales(),
    ])
})
</script>

<template>
    <section class="sales-board">
        <div class="sales-board__main">
            <header class="sales-toolbar">
                <div class="sales-toolbar__summary" aria-live="polite">
                    <div class="sales-metric sales-metric--amount">
                        <span>{{ hasFilters ? 'Сумма за период' : 'Сумма продаж' }}</span>
                        <strong :title="formatMoney(totalAmount)">{{ loading ? '…' : formatMoney(totalAmount) }}</strong>
                    </div>
                    <div class="sales-metric">
                        <span>Продаж</span>
                        <strong>{{ loading ? '…' : formatMoney(totalItems) }}</strong>
                    </div>
                </div>

                <div class="sales-filters">
                    <v-menu max-height="320" location="bottom start" theme="light">
                        <template #activator="{ props }">
                            <button v-bind="props" type="button" class="sales-month-trigger" :class="{ 'is-active': filters.month }" :title="`Месяц продаж: ${selectedMonthLabel}`" :aria-label="`Месяц продаж: ${selectedMonthLabel}`">
                                <v-icon icon="mdi-calendar-month-outline" size="17" />
                                <span>{{ selectedMonthLabel }}</span>
                                <v-icon icon="mdi-chevron-down" size="15" class="sales-filter-chevron" />
                            </button>
                        </template>
                        <v-list density="compact" class="sales-month-menu" aria-label="Месяц продаж">
                            <v-list-item :active="!filters.month" color="#0f766e" @click="applyMonth(null)">
                                <v-list-item-title>Все месяцы</v-list-item-title>
                            </v-list-item>
                            <v-list-item v-for="month in months" :key="month.value" :active="filters.month === month.value" color="#0f766e" @click="applyMonth(month.value)">
                                <v-list-item-title>{{ month.label }} · {{ month.count }} продаж</v-list-item-title>
                                <v-list-item-subtitle>{{ formatMoney(month.total) }}</v-list-item-subtitle>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                    <v-menu v-model="dateRangeMenu" :close-on-content-click="false" location="bottom start" theme="light">
                        <template #activator="{ props }">
                            <button v-bind="props" type="button" class="sales-month-trigger sales-period-trigger" :class="{ 'is-active': filters.date_from || filters.date_to }" :title="`Период продаж: ${dateRangeLabel}`" aria-label="Выбрать период продаж">
                                <v-icon icon="mdi-calendar-range-outline" size="16" />
                                <span>{{ dateRangeLabel }}</span>
                                <v-icon icon="mdi-chevron-down" size="14" class="sales-filter-chevron" />
                            </button>
                        </template>
                        <form class="sales-period-form" @submit.prevent="applyDateRange">
                            <strong>Период продаж</strong>
                            <div class="sales-date-range">
                                <label class="sales-date-control">
                                    <span>С</span>
                                    <input v-model="filters.date_from" type="date" aria-label="Продажи с даты" :max="filters.date_to || undefined" />
                                </label>
                                <label class="sales-date-control">
                                    <span>По</span>
                                    <input v-model="filters.date_to" type="date" aria-label="Продажи по дату" :min="filters.date_from || undefined" />
                                </label>
                            </div>
                            <v-btn type="submit" size="small" variant="flat" color="#0f766e">Применить</v-btn>
                        </form>
                    </v-menu>
                    <v-btn v-if="hasFilters" icon="mdi-filter-remove-outline" size="x-small" variant="text" title="Сбросить фильтры продаж" aria-label="Сбросить фильтры продаж" @click="resetFilters" />
                </div>

                <div class="sales-toolbar__actions">
                    <v-btn
                        icon="mdi-refresh"
                        variant="text"
                        size="small"
                        aria-label="Обновить продажи"
                        title="Обновить продажи"
                        :loading="loading"
                        @click="fetchSales"
                    />
                    <v-btn
                        color="#0f766e"
                        variant="flat"
                        size="small"
                        prepend-icon="mdi-plus"
                        class="sales-create-button"
                        title="Новая продажа"
                        aria-label="Новая продажа"
                        @click="openCreate"
                    >
                        <span class="sales-create-button__label">Новая продажа</span>
                    </v-btn>
                </div>
            </header>

            <v-alert v-if="errorMessage" type="error" variant="tonal" density="compact" class="sales-error">
                {{ errorMessage }}
            </v-alert>

            <v-data-table-server
                :headers="headers"
                :items="rows"
                :items-length="totalItems"
                :loading="loading"
                :page="options.page"
                :items-per-page="options.itemsPerPage"
                :sort-by="options.sortBy"
                fixed-header
                hide-default-footer
                must-sort
                density="compact"
                theme="light"
                class="sales-grid"
                item-value="id"
                loading-text="Загрузка продаж…"
                @update:options="handleOptionsUpdate"
            >
                <template #item.date="{ item }">
                    <div class="sales-date">
                        <span>{{ formatDate(item.date) }}</span>
                        <small>#{{ item.id }}</small>
                    </div>
                </template>

                <template #item.entity="{ item }">
                    <div class="sales-party">
                        <a v-if="entityHref(item.entity)" :href="entityHref(item.entity)" class="sales-party__entity" :title="item.entity?.name">
                            {{ item.entity?.name || '—' }}
                        </a>
                        <span v-else class="sales-party__entity">Покупатель не указан</span>
                        <div v-if="entityUnits(item.entity).length" class="sales-party__units">
                            <a v-for="unit in entityUnits(item.entity).slice(0, 2)" :key="unit.id" :href="unitHref(unit)" :title="unit.name">{{ unit.name }}</a>
                            <span v-if="entityUnits(item.entity).length > 2">+{{ entityUnits(item.entity).length - 2 }}</span>
                        </div>
                        <div v-if="entityBuildingsText(item.entity)" class="sales-party__address" :title="entityBuildingsText(item.entity, 12)">
                            {{ entityBuildingsText(item.entity, 1) }}
                        </div>
                    </div>
                </template>

                <template #item.total="{ item }">
                    <button type="button" class="sales-money-button" :aria-label="`Детали продажи № ${item.id}, сумма ${formatMoney(item.total)}`" @click="openSaleDetails(item)">
                        <strong>{{ formatMoney(item.total) }}</strong>
                        <v-icon icon="mdi-chevron-right" size="15" />
                    </button>
                </template>

                <template #item.previous_sale="{ item }">
                    <div v-if="item.previous_sale" class="sales-prev">
                        <strong>{{ formatMoney(item.previous_sale.total) }}</strong>
                        <span>{{ formatDate(item.previous_sale.date) }} · {{ item.previous_sale.days }} дн.</span>
                    </div>
                    <span v-else class="sales-first">Первая продажа</span>
                </template>

                <template #no-data>
                    <div class="sales-empty">
                        <v-icon icon="mdi-receipt-text-outline" size="30" />
                        <strong>{{ hasFilters ? 'За этот период продаж нет' : 'Продаж пока нет' }}</strong>
                        <span>{{ hasFilters ? 'Выберите другой период или сбросьте фильтры.' : 'Добавьте первую продажу — она появится в таблице.' }}</span>
                    </div>
                </template>
            </v-data-table-server>

            <footer class="sales-footer">
                <div class="sales-footer__total">На странице <strong>{{ formatMoney(pageAmount) }}</strong></div>
                <div class="sales-pagination">
                    <label class="sales-page-size">
                        <span>По</span>
                        <select :value="options.itemsPerPage" aria-label="Продаж на странице" :disabled="loading" @change="changePageSize">
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                            <option :value="200">200</option>
                        </select>
                    </label>
                    <span class="sales-pagination__range">{{ firstRow }}–{{ lastRow }} из {{ totalItems }}</span>
                    <button type="button" :disabled="options.page <= 1 || loading" aria-label="Предыдущая страница продаж" @click="changePage(options.page - 1)"><v-icon icon="mdi-chevron-left" size="19" /></button>
                    <span class="sales-pagination__page">{{ options.page }} / {{ pageCount }}</span>
                    <button type="button" :disabled="options.page >= pageCount || loading" aria-label="Следующая страница продаж" @click="changePage(options.page + 1)"><v-icon icon="mdi-chevron-right" size="19" /></button>
                </div>
            </footer>
        </div>

        <v-dialog v-model="detailsDialog" max-width="980" scrollable class="sale-details-dialog">
            <v-card class="sale-details" theme="light">
                <v-card-title class="sale-details__title">
                    <div>
                        <span>Детали продажи</span>
                        <strong>{{ formatMoney(selectedSale?.total) }}</strong>
                    </div>

                    <div class="sale-details__title-actions">
                        <v-btn
                            size="small"
                            variant="outlined"
                            :prepend-icon="detailsAddOpen ? 'mdi-minus' : 'mdi-plus'"
                            @click="toggleDetailsAdd"
                        >
                            {{ detailsAddOpen ? 'Скрыть' : 'Товар' }}
                        </v-btn>
                        <v-btn icon="mdi-close" variant="text" size="small" aria-label="Закрыть детали продажи" @click="detailsDialog = false" />
                    </div>
                </v-card-title>

                <v-card-text v-if="selectedSale" class="sale-details__body">
                    <div class="sale-details__summary">
                        <div>
                            <small>Дата</small>
                            <strong>{{ formatDate(selectedSale.date) }}</strong>
                        </div>
                        <div>
                            <small>Покупатель</small>
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
                            <span>Товар</span>
                            <span>НДС</span>
                            <span>Тарность</span>
                            <span>Кол-во</span>
                            <span>Ед.</span>
                            <span>Цена</span>
                            <span>Сумма</span>
                        </div>

                        <div
                            v-for="good in saleGoods(selectedSale)"
                            :key="good.pivot?.id || good.id"
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

                    <v-form
                        v-if="detailsAddOpen"
                        class="sale-details__add"
                        @submit.prevent="attachGoodToSale"
                    >
                        <div class="sale-details__add-title">
                            <div>
                                <strong>Добавить товар</strong>
                                <span>Укажите любые два значения: количество, цена или сумма.</span>
                            </div>
                            <small>Сумма позиции будет добавлена к итогу продажи</small>
                        </div>

                        <v-alert
                            v-if="detailsErrorMessage"
                            type="error"
                            variant="tonal"
                            density="compact"
                        >
                            {{ detailsErrorMessage }}
                        </v-alert>

                        <v-alert
                            v-if="detailsMessage"
                            type="success"
                            variant="tonal"
                            density="compact"
                        >
                            {{ detailsMessage }}
                        </v-alert>

                        <div class="sale-details__add-grid">
                            <div class="sale-details__add-head">
                                <span>Товар</span>
                                <span>НДС</span>
                                <span>Тарность</span>
                                <span>Кол-во</span>
                                <span>Ед.</span>
                                <span>Цена</span>
                                <span>Сумма</span>
                            </div>

                            <div class="sale-details__add-line">
                                <v-autocomplete
                                    v-model="detailsLine.good_id"
                                    :items="goods"
                                    :item-title="goodTitle"
                                    item-value="id"
                                    placeholder="Выберите товар"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    clearable
                                    theme="light"
                                    @update:model-value="handleDetailsGoodSelected"
                                />

                                <span class="sale-details__add-meta">{{ goodVatText(lineGood(detailsLine)) }}</span>
                                <span class="sale-details__add-meta">{{ lineGood(detailsLine)?.denominator || '—' }}</span>

                                <v-text-field
                                    v-model="detailsLine.quantity"
                                    aria-label="Количество"
                                    type="number"
                                    min="0"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    theme="light"
                                    @update:model-value="recalcLine(detailsLine, 'quantity')"
                                />

                                <v-select
                                    v-model="detailsLine.measure_id"
                                    :items="measures"
                                    item-title="name"
                                    item-value="id"
                                    aria-label="Единица измерения"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    theme="light"
                                />

                                <v-text-field
                                    v-model="detailsLine.price"
                                    aria-label="Цена"
                                    type="number"
                                    min="0"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    theme="light"
                                    @update:model-value="recalcLine(detailsLine, 'price')"
                                />

                                <v-text-field
                                    v-model="detailsLine.total"
                                    aria-label="Сумма"
                                    type="number"
                                    min="0"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    theme="light"
                                    @update:model-value="recalcLine(detailsLine, 'total')"
                                />
                            </div>
                        </div>

                        <div class="sale-details__add-actions">
                            <span>
                                Сумма позиции:
                                <strong>{{ formatMoney(detailsLine.total) }}</strong>
                            </span>
                            <div>
                                <v-btn size="small" variant="text" @click="resetDetailsLine">
                                    Очистить
                                </v-btn>
                                <v-btn
                                    type="submit"
                                    size="small"
                                    color="#0f766e"
                                    variant="flat"
                                    prepend-icon="mdi-plus"
                                    :loading="detailsSaving"
                                    :disabled="!canAttachGood"
                                >
                                    Добавить
                                </v-btn>
                            </div>
                        </div>
                    </v-form>
                </v-card-text>
            </v-card>
        </v-dialog>

        <v-dialog
            v-model="dialog"
            width="calc(100vw - 24px)"
            max-width="1400"
            scrollable
            class="sale-create-dialog"
        >
            <v-card class="sale-dialog" theme="light">
                <v-card-title class="sale-dialog__title">
                    <div>
                        <span>Новая продажа</span>
                        <strong>{{ formatMoney(effectiveSaleTotal) }}</strong>
                    </div>

                    <div class="sale-dialog__title-actions">
                        <v-btn
                            color="#0f766e"
                            variant="outlined"
                            size="small"
                            class="sale-dialog__entity-btn"
                            :loading="entityMetaLoading"
                            @click="openEntityCreate"
                        >
                            + Покупатель
                        </v-btn>

                        <v-btn icon="mdi-close" variant="text" size="small" aria-label="Закрыть создание продажи" @click="dialog = false" />
                    </div>
                </v-card-title>

                <v-card-text class="sale-dialog__body">
                    <v-alert v-if="errorMessage" type="error" variant="tonal" density="compact" class="mb-3">
                        {{ errorMessage }}
                    </v-alert>
                    <v-row dense>
                        <v-col cols="12" md="2">
                            <div class="sale-form-field sale-form-field--date">
                                <span class="sale-form-field__label">Дата продажи</span>
                                <v-text-field
                                    v-model="saleForm.date"
                                    aria-label="Дата продажи"
                                    type="date"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                    class="sale-date-field"
                                />
                            </div>
                        </v-col>

                        <v-col cols="12" md="7">
                            <div class="sale-form-field sale-form-field--entity">
                                <span class="sale-form-field__label">Покупатель</span>
                                <v-autocomplete
                                    v-model="saleForm.entity_id"
                                    :items="entityOptions"
                                    :item-title="entityOptionTitle"
                                    :custom-filter="entitySearchFilter"
                                    item-value="id"
                                    aria-label="Покупатель"
                                    placeholder="Название, подразделение или адрес"
                                    variant="solo-filled"
                                    density="compact"
                                    clearable
                                    hide-details="auto"
                                    class="sale-entity-field"
                                    :menu-props="{ contentClass: 'sale-entity-menu' }"
                                >
                                    <template #selection="{ item }">
                                        <div class="sale-entity-selection">
                                            <strong>{{ item.raw.name }}</strong>
                                            <span>#{{ item.raw.id }}</span>
                                        </div>
                                    </template>

                                    <template #item="{ props, item }">
                                        <v-list-item
                                            v-bind="props"
                                            class="sale-entity-option"
                                        >
                                            <template #title>
                                                <div class="sale-entity-option__title">
                                                    <strong>{{ item.raw.name }}</strong>
                                                    <span>#{{ item.raw.id }}</span>
                                                </div>
                                            </template>

                                            <template #subtitle>
                                                <div class="sale-entity-option__meta">
                                                    <span v-if="entityUnitsText(item.raw)">
                                                        Подразделения: {{ entityUnitsText(item.raw) }}
                                                    </span>
                                                    <span v-if="entityBuildingsText(item.raw)">
                                                        Адреса: {{ entityBuildingsText(item.raw) }}
                                                    </span>
                                                    <span v-if="!entityUnitsText(item.raw) && !entityBuildingsText(item.raw)">
                                                        Нет подразделений и адресов
                                                    </span>
                                                </div>
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-autocomplete>
                            </div>

                            <div v-if="selectedEntityOption" class="sale-entity-context">
                                <span v-if="entityUnitsText(selectedEntityOption)" class="sale-entity-context__unit">
                                    {{ entityUnitsText(selectedEntityOption) }}
                                </span>
                                <span v-if="entityBuildingsText(selectedEntityOption)" class="sale-entity-context__building">
                                    {{ entityBuildingsText(selectedEntityOption) }}
                                </span>
                                <span v-if="!entityUnitsText(selectedEntityOption) && !entityBuildingsText(selectedEntityOption)">
                                    Нет подразделений и адресов
                                </span>
                            </div>
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-switch
                                v-model="saleForm.manualTotal"
                                label="Сумма вручную"
                                color="#0f766e"
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
                            <span>Товар</span>
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
                                placeholder="Товар"
                                variant="solo-filled"
                                density="compact"
                                hide-details
                                class="sale-good-field"
                                :menu-props="{ contentClass: 'sale-good-menu' }"
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
                        <v-btn color="#0f766e" variant="flat" density="compact" prepend-icon="mdi-plus" @click="addLine">
                            Товар
                        </v-btn>
                        <span>Итого по товарам: <strong>{{ formatMoney(saleLinesTotal) }}</strong></span>
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Отмена</v-btn>
                    <v-btn color="#0f766e" variant="flat" :loading="saving" :disabled="!canSubmitSale" @click="submitSale">
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <EntityFormDialog
            v-model="entityDialog"
            :loading="entityDialogLoading"
            :is-edit="false"
            :form="entityForm"
            :meta="entityMeta"
            @submit="submitEntity"
            @building-created="mergeEntityBuildingMeta"
        />
    </section>
</template>

<style scoped>
.sales-board {
    display: flex;
    flex: 1 1 auto;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    color: #334155;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
    -webkit-font-smoothing: antialiased;
}

.sales-board__main {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    background: #fff;
}

.sales-toolbar {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    min-height: 46px;
    padding: 4px 10px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}

.sales-toolbar__summary,
.sales-toolbar__actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sales-toolbar__summary { flex: 0 0 auto; min-width: 0; gap: 18px; }
.sales-toolbar__actions { flex: 0 0 auto; gap: 5px; margin-left: auto; }
.sales-metric { display: grid; gap: 2px; }
.sales-metric > span { color: #64748b; font-size: 11px; line-height: 1.1; white-space: nowrap; }
.sales-metric > strong { color: #334155; font-size: 17px; font-weight: 600; line-height: 1.2; }
.sales-metric--amount { min-width: 0; }
.sales-metric--amount > strong { overflow: hidden; color: #0f766e; font-size: 19px; text-overflow: ellipsis; white-space: nowrap; }

.sales-filters {
    display: flex;
    flex: 0 1 auto;
    align-items: center;
    flex-wrap: nowrap;
    min-width: 0;
    gap: 6px;
}

.sales-month-trigger,
.sales-date-control {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 30px;
    border: 1px solid #dce3eb;
    border-radius: 6px;
    background: #fff;
    color: #475569;
    font-size: 12px;
}

.sales-month-trigger { flex: 0 0 auto; padding: 0 7px; }
.sales-month-trigger.is-active { border-color: #93cfc5; background: #f0fdfa; color: #0f766e; }
.sales-period-trigger { flex: 0 1 auto; min-width: 32px; }
.sales-period-trigger > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sales-period-form { display: grid; gap: 10px; width: 310px; max-width: calc(100vw - 24px); padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #334155; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto-Regular', sans-serif; font-size: 12px; }
.sales-period-form > strong { font-size: 12px; font-weight: 600; }
.sales-period-form > .v-btn { justify-self: end; text-transform: none; letter-spacing: 0; }
.sales-date-range { display: flex; align-items: center; gap: 6px; }
.sales-date-control { flex: 1; min-width: 0; padding-left: 8px; }
.sales-date-control > span { color: #64748b; font-size: 11px; }
.sales-date-control input {
    flex: 1;
    width: 100%;
    min-width: 0;
    height: 30px;
    padding: 0 5px 0 0;
    border: 0;
    border-radius: 5px;
    outline: 0;
    background: transparent;
    color: #334155;
    font: inherit;
    font-size: 12px;
    color-scheme: light;
    box-shadow: none;
}
.sales-date-control:focus-within { border-color: #0f766e; box-shadow: 0 0 0 2px #ccfbf1; }
.sales-month-menu { min-width: 220px; }
.sales-month-menu :deep(.v-list-item-title) { font-size: 12px; }
.sales-month-menu :deep(.v-list-item-subtitle) { font-size: 11px; font-variant-numeric: tabular-nums; }
.sales-error { flex: 0 0 auto; margin: 6px 10px; font-size: 12px; max-height: 72px; overflow: auto; }
.sales-toolbar :deep(.v-btn),
.sales-filters :deep(.v-btn) { min-height: 30px; text-transform: none; letter-spacing: 0; font-size: 12px; font-weight: 500; }
.sales-toolbar :deep(.v-btn--icon) { width: 30px; height: 30px; }

.sales-grid {
    display: flex;
    flex: 1 1 0;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    font-size: 12px;
}
.sales-grid :deep(.v-table__wrapper) {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    scrollbar-width: thin;
    scrollbar-color: #bdcbd4 #f8fafc;
    overscroll-behavior: contain;
}
.sales-grid :deep(table) { min-width: 540px; table-layout: fixed; }
.sales-grid :deep(thead th) {
    height: 31px !important;
    padding: 0 8px !important;
    border-bottom: 1px solid #dce5ea !important;
    background: #f1f5f9 !important;
    color: #64748b !important;
    font-size: 11px;
    font-weight: 600 !important;
    white-space: nowrap;
}
.sales-grid :deep(tbody td) {
    height: 54px !important;
    padding: 6px 8px !important;
    border-bottom: 1px solid #edf1f5 !important;
    background: #fff;
}
.sales-grid :deep(tbody tr:nth-child(even) td) { background: #fbfcfd; }
.sales-grid :deep(tbody tr:hover td) { background: #f0fdfa !important; }
.sales-grid :deep(.v-data-table-progress th) { height: auto !important; padding: 0 !important; }
.sales-date { display: grid; gap: 4px; color: #475569; white-space: nowrap; }
.sales-date > small { color: #64748b; font-size: 10px; }
.sales-party { display: grid; gap: 3px; min-width: 0; line-height: 1.2; }
.sales-party__entity { overflow: hidden; color: #334155; font-size: 12px; font-weight: 600; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
.sales-party__entity:hover { color: #0f766e; text-decoration: underline; }
.sales-party__units { display: flex; gap: 5px; min-width: 0; overflow: hidden; font-size: 11px; white-space: nowrap; }
.sales-party__units a { overflow: hidden; color: #0f766e; text-overflow: ellipsis; text-decoration: none; }
.sales-party__units a:hover { text-decoration: underline; }
.sales-party__units > span { flex: 0 0 auto; color: #64748b; }
.sales-party__address { overflow: hidden; color: #64748b; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
.sales-money-button { display: flex; align-items: center; justify-content: flex-end; gap: 2px; width: 100%; min-height: 32px; padding: 4px 0; border-radius: 5px; color: #0f766e; text-align: right; white-space: nowrap; }
.sales-money-button strong { overflow: hidden; font-size: 12px; font-weight: 600; text-overflow: ellipsis; }
.sales-money-button :deep(.v-icon) { color: #8fbeb6; }
.sales-money-button:hover { background: #ccfbf1; }
.sales-prev { display: grid; gap: 4px; line-height: 1.2; text-align: right; white-space: nowrap; }
.sales-prev strong { color: #64748b; font-size: 12px; font-weight: 500; }
.sales-prev span { color: #64748b; font-size: 10px; }
.sales-first { display: inline-block; padding: 3px 6px; border-radius: 4px; background: #f0fdfa; color: #0f766e; font-size: 10px; white-space: nowrap; }
.sales-empty { display: grid; justify-items: center; gap: 8px; padding: 38px 16px; color: #94a3b8; }
.sales-empty strong { color: #475569; font-size: 14px; font-weight: 500; }
.sales-empty span { color: #64748b; font-size: 12px; }

.sales-footer { display: flex; flex: 0 0 auto; align-items: center; justify-content: space-between; gap: 8px; min-height: 37px; padding: 4px 9px; border-top: 1px solid #e2e8f0; background: #fff; color: #64748b; font-size: 11px; }
.sales-footer__total { display: flex; gap: 8px; white-space: nowrap; }
.sales-footer__total strong { color: #0f766e; font-weight: 600; }
.sales-pagination { display: flex; align-items: center; justify-content: flex-end; gap: 8px; white-space: nowrap; }
.sales-page-size { display: flex; align-items: center; gap: 5px; }
.sales-page-size select { width: 57px; height: 28px; padding: 0 18px 0 6px; border: 1px solid #e2e8f0; border-radius: 5px; color: #475569; font-size: 11px; }
.sales-pagination > button { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid #e2e8f0; border-radius: 5px; background: #fff; color: #475569; }
.sales-pagination > button:hover:not(:disabled) { border-color: #99d2c8; background: #f0fdfa; color: #0f766e; }
.sales-pagination > button:disabled { opacity: .35; cursor: default; }
.sales-pagination__page { min-width: 30px; text-align: center; }
.sales-board button:focus-visible,
.sales-board a:focus-visible { outline: 2px solid #0f766e; outline-offset: 2px; }

.sale-details,
.sale-dialog { border: 1px solid #dce5ea; border-radius: 12px; background: #fff; color: #334155; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto-Regular', sans-serif; font-size: 12px; font-variant-numeric: tabular-nums; -webkit-font-smoothing: antialiased; }
.sale-details__title,
.sale-dialog__title { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 52px; padding: 9px 14px; border-bottom: 1px solid #e2e8f0; background: #f0fdfa; color: #334155; }
.sale-details__title > div,
.sale-dialog__title > div { display: flex; align-items: baseline; gap: 14px; }
.sale-details__title span,
.sale-dialog__title span { font-size: 14px; font-weight: 500; }
.sale-details__title strong,
.sale-dialog__title strong { color: #0f766e; font-size: 17px; font-weight: 600; }
.sale-details__title .sale-details__title-actions,
.sale-dialog__title .sale-dialog__title-actions { display: flex; align-items: center; gap: 6px; }
.sale-details :deep(.v-btn),
.sale-dialog :deep(.v-btn) { text-transform: none; letter-spacing: 0; font-size: 12px; }
.sale-details__body { display: grid; gap: 10px; padding: 12px !important; }
.sale-details__summary { display: grid; grid-template-columns: 100px minmax(0, 1fr) 185px; gap: 8px; }
.sale-details__summary > div { display: grid; gap: 4px; padding: 7px 9px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; }
.sale-details__summary small { color: #64748b; font-size: 11px; }
.sale-details__summary strong,
.sale-details__summary a { color: #334155; font-size: 12px; font-weight: 500; text-decoration: none; }
.sale-details__summary a { color: #0f766e; }
.sale-details__units { display: flex; flex-wrap: wrap; gap: 5px; }
.sale-details__units a,
.sale-details__units span { padding: 3px 6px; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff; color: #64748b; font-size: 11px; text-decoration: none; }
.sale-details__units a { color: #0f766e; }
.sale-details__grid { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; }
.sale-details__head,
.sale-details__row { display: grid; grid-template-columns: minmax(220px, 1fr) 85px 82px 78px 65px 90px 100px; align-items: center; min-width: 720px; }
.sale-details__head { min-height: 30px; background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; white-space: nowrap; }
.sale-details__head span,
.sale-details__row > * { min-width: 0; padding: 6px 8px; }
.sale-details__row { min-height: 35px; border-top: 1px solid #edf1f5; font-size: 12px; }
.sale-details__row a { overflow: hidden; color: #0f766e; font-weight: 500; text-overflow: ellipsis; text-decoration: none; white-space: nowrap; }
.sale-details__row span { color: #64748b; }
.sale-details__row strong { color: #334155; font-weight: 500; text-align: right; }
.sale-details__empty { padding: 16px; color: #94a3b8; font-size: 12px; }
.sale-details__add { display: grid; gap: 8px; padding: 10px; border: 1px solid #cce7e0; border-radius: 6px; background: #f8fdfc; }
.sale-details__add-title { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
.sale-details__add-title > div { display: grid; gap: 3px; }
.sale-details__add-title strong { color: #0f766e; font-size: 13px; font-weight: 500; }
.sale-details__add-title span,
.sale-details__add-title small { color: #64748b; font-size: 11px; }
.sale-details__add-grid { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 5px; }
.sale-details__add-head,
.sale-details__add-line { display: grid; grid-template-columns: minmax(220px, 1fr) 85px 82px 78px 65px 90px 100px; align-items: center; min-width: 754px; }
.sale-details__add-head { min-height: 28px; background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; white-space: nowrap; }
.sale-details__add-head span { padding: 4px 6px; }
.sale-details__add-line { gap: 4px; padding: 5px; border-top: 1px solid #e2e8f0; }
.sale-details__add-line :deep(.v-field),
.sale-details__add-line :deep(.v-field__input) { min-height: 34px; font-size: 12px; }
.sale-details__add-line :deep(.v-field__input) { padding-top: 0; padding-bottom: 0; }
.sale-details__add-meta { overflow: hidden; color: #64748b; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.sale-details__add-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #64748b; font-size: 12px; }
.sale-details__add-actions > div { display: flex; align-items: center; gap: 6px; }
.sale-details__add-actions strong { color: #0f766e; font-weight: 600; }

.sale-create-dialog :deep(.v-overlay__content) { margin: 12px; max-height: calc(100dvh - 24px); }
.sale-dialog__body { padding: 12px 14px !important; }
.sale-dialog :deep(.v-card-actions) { flex-shrink: 0; min-height: 48px; padding: 8px 14px; border-top: 1px solid #e2e8f0; }
.sale-form-field { display: grid; gap: 5px; }
.sale-form-field__label { color: #64748b; font-size: 11px; line-height: 1.2; }
.sale-form-field :deep(.v-field),
.sale-good-field :deep(.v-field),
.sale-line :deep(.v-field) { min-height: 36px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; box-shadow: none; font-size: 12px; }
.sale-form-field :deep(.v-field__input),
.sale-good-field :deep(.v-field__input),
.sale-line :deep(.v-field__input) { min-height: 36px; padding-top: 0; padding-bottom: 0; font-size: 12px; }
.sale-form-field :deep(input),
.sale-good-field :deep(input),
.sale-line :deep(input),
.sale-form-field :deep(.v-select__selection-text),
.sale-good-field :deep(.v-select__selection-text),
.sale-line :deep(.v-select__selection-text) { color: #334155 !important; opacity: 1; }
.sale-form-field :deep(input::placeholder),
.sale-good-field :deep(input::placeholder),
.sale-line :deep(input::placeholder) { color: #94a3b8 !important; opacity: 1; }
.sale-form-field :deep(.v-label),
.sale-good-field :deep(.v-label),
.sale-line :deep(.v-label) { font-size: 11px; }
.sale-form-field :deep(.v-field__append-inner),
.sale-form-field :deep(.v-field__prepend-inner),
.sale-form-field :deep(.v-field__clearable),
.sale-good-field :deep(.v-field__append-inner),
.sale-good-field :deep(.v-field__prepend-inner),
.sale-good-field :deep(.v-field__clearable),
.sale-line :deep(.v-field__append-inner),
.sale-line :deep(.v-field__prepend-inner),
.sale-line :deep(.v-field__clearable) { padding-top: 6px; }
.sale-date-field :deep(input[type='date']) { color-scheme: light; line-height: 1.2; }
.sale-entity-field :deep(.v-field__input),
.sale-good-field :deep(.v-field__input) { align-items: center; }
.sale-dialog__entity-btn { min-width: 100px; font-weight: 500; }
.sale-entity-selection { display: flex; align-items: baseline; gap: 6px; min-width: 0; max-width: 100%; line-height: 1.2; }
.sale-entity-selection strong { overflow: hidden; color: #334155; font-size: 13px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
.sale-entity-selection span { flex: 0 0 auto; color: #94a3b8; font-size: 10px; }
.sale-entity-context { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; color: #64748b; font-size: 10px; line-height: 1.2; }
.sale-entity-context span { overflow: hidden; max-width: 100%; padding: 3px 5px; border: 1px solid #e2e8f0; border-radius: 4px; background: #f8fafc; text-overflow: ellipsis; white-space: nowrap; }
.sale-entity-context__unit { color: #0f766e; }
.sale-entity-option { min-height: 42px !important; }
.sale-entity-option__title { display: flex; align-items: baseline; gap: 7px; min-width: 0; line-height: 1.2; }
.sale-entity-option__title strong { overflow: hidden; color: #334155; font-size: 13px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
.sale-entity-option__title span { color: #94a3b8; font-size: 10px; }
.sale-entity-option__meta { display: grid; gap: 2px; color: #64748b; font-size: 11px; line-height: 1.2; }
.sale-entity-option__meta span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
:global(.sale-entity-menu),
:global(.sale-good-menu) { border: 1px solid #e2e8f0; background: #fff !important; box-shadow: 0 10px 30px #0f172a18 !important; }
:global(.sale-entity-menu .v-list),
:global(.sale-good-menu .v-list) { padding: 4px; background: #fff !important; }
:global(.sale-entity-menu .v-list-item),
:global(.sale-good-menu .v-list-item) { min-height: 34px; border-radius: 4px; color: #334155 !important; }
:global(.sale-good-menu .v-list-item-title) { color: #334155 !important; font-size: 12px; }
:global(.sale-entity-menu .v-list-item:hover),
:global(.sale-entity-menu .v-list-item--active),
:global(.sale-good-menu .v-list-item:hover),
:global(.sale-good-menu .v-list-item--active) { background: #f0fdfa !important; }
.sale-lines { margin-top: 12px; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; }
.sale-lines__head,
.sale-line { display: grid; grid-template-columns: minmax(280px, 2.4fr) 84px 82px 88px 78px 92px 104px 30px; gap: 4px; align-items: center; min-width: 890px; }
.sale-lines__head { padding: 7px 6px; background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; white-space: nowrap; }
.sale-line { padding: 5px 6px; border-top: 1px solid #edf1f5; }
.sale-line__meta { color: #64748b; font-size: 11px; }
.sale-line__vat { color: #0f766e; }
.sale-dialog__footer-line { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 10px; color: #64748b; font-size: 12px; }
.sale-dialog__footer-line strong { color: #0f766e; font-weight: 600; }

@container commerce-panel (max-width: 620px) {
    .sales-footer__total { display: none; }
    .sales-pagination { width: 100%; justify-content: space-between; }
    .sales-toolbar { gap: 6px; padding: 4px 8px; }
    .sales-toolbar__summary { flex: 0 1 auto; gap: 13px; }
    .sales-metric > strong { font-size: 15px; }
    .sales-metric--amount > strong { font-size: 17px; }
    .sales-metric > span { font-size: 10px; }
    .sales-toolbar__actions { gap: 2px; }
    .sales-toolbar__actions :deep(.sales-create-button) { width: 30px; min-width: 30px; padding: 0; }
    .sales-create-button__label { display: none; }
    .sales-create-button :deep(.v-btn__prepend) { margin: 0; }
    .sales-filters { gap: 5px; }
    .sales-footer { padding: 4px 8px; }
    .sales-pagination { gap: 5px; font-size: 10px; }
    .sales-page-size { gap: 4px; }
    .sales-pagination > button { width: 27px; height: 28px; }
}
@container commerce-panel (max-width: 440px) {
    .sales-toolbar__summary { flex: 1 1 auto; gap: 9px; }
    .sales-metric--amount > strong { font-size: 16px; }
    .sales-month-trigger { flex: 0 0 30px; justify-content: center; width: 30px; min-width: 30px; gap: 0; padding: 0; }
    .sales-month-trigger > span,
    .sales-month-trigger > .sales-filter-chevron { display: none; }
    .sales-filters { flex: 0 0 auto; gap: 4px; }
    .sales-error { max-height: 42px; margin: 3px 8px; }
}
@media (max-width: 600px) {
    .sale-details__title,
    .sale-dialog__title { padding: 8px 10px; gap: 6px; }
    .sale-details__title > div,
    .sale-dialog__title > div { gap: 5px; flex-wrap: wrap; }
    .sale-details__title > div:first-child,
    .sale-dialog__title > div:first-child { flex-direction: column; }
    .sale-details__summary { grid-template-columns: 1fr 1fr; }
    .sale-details__summary > div:nth-child(2) { grid-column: 1 / -1; grid-row: 1; }
    .sale-details__add-title,
    .sale-details__add-actions { align-items: stretch; flex-direction: column; }
}
</style>
