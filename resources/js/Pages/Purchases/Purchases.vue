<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { usePurchases } from '@/Composables/usePurchases.js'
import { usePurchaseForm } from '@/Composables/usePurchaseForm.js'

const PER_PAGE = 100

const {
    items,
    loading,
    pagination,
    fetchPurchases,
    fetchPurchase,
    createPurchase,
    updatePurchase,
    deletePurchase,
} = usePurchases()

const {
    form,
    isEdit,
    resetForm,
    fillForm,
    payload,
    recalcItem,
    recalcAmount,
    emptyItem,
} = usePurchaseForm()

const page = ref(1)
const dialog = ref(false)
const detailsDialog = ref(false)
const detailsLoading = ref(false)
const selectedPurchase = ref(null)
const saving = ref(false)
const filtersMenu = ref(false)
const entities = ref([])
const units = ref([])
const goodsOptions = ref([])
const measures = ref([])
const currencies = ref([])
const serverErrors = ref({})
const errorMessage = ref('')

const filters = reactive({
    search: '',
    date_from: '',
    date_to: '',
    amount_from: '',
    amount_to: '',
    entity_ids: [],
    unit_ids: [],
    good_ids: [],
    measure_ids: [],
    currency_ids: [],
})

const goodsById = computed(() => new Map(goodsOptions.value.map((good) => [Number(good.id), good])))
const measuresById = computed(() => new Map(measures.value.map((measure) => [Number(measure.id), measure])))
const currenciesById = computed(() => new Map(currencies.value.map((currency) => [Number(currency.id), currency])))
const totalItems = computed(() => pagination.value.total || 0)
const currentPage = computed(() => pagination.value.current_page || 1)
const lastPage = computed(() => pagination.value.last_page || 1)
const rangeStart = computed(() => totalItems.value ? ((currentPage.value - 1) * PER_PAGE) + 1 : 0)
const rangeEnd = computed(() => Math.min(totalItems.value, currentPage.value * PER_PAGE))
const pageNumbers = computed(() => Array.from({ length: lastPage.value }, (_, index) => index + 1))
const selectedItems = computed(() => selectedPurchase.value?.items || [])
const canSubmit = computed(() => {
    return Boolean(
        form.date
        && form.entity_id
        && form.items.some((item) => item.good_id && Number(item.quantity || 0) > 0)
    )
})

const activeFilterCount = computed(() => {
    return Object.entries(filters).reduce((count, [, value]) => {
        if (Array.isArray(value)) {
            return count + (value.length ? 1 : 0)
        }

        return count + (String(value || '').trim() ? 1 : 0)
    }, 0)
})

function extractItems(response) {
    if (Array.isArray(response?.data)) return response.data
    if (Array.isArray(response?.data?.data)) return response.data.data
    return []
}

function cleanParams(targetPage = page.value) {
    const params = {
        page: targetPage,
        per_page: PER_PAGE,
    }

    Object.entries(filters).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            if (value.length) params[key] = value
            return
        }

        const normalized = String(value || '').trim()
        if (normalized) params[key] = normalized
    })

    return params
}

async function loadPurchases(targetPage = page.value) {
    page.value = targetPage
    errorMessage.value = ''

    try {
        await fetchPurchases(cleanParams(targetPage))
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось загрузить закупки'
        console.error('fetch purchases error:', error?.response?.data || error)
    }
}

async function loadDictionaries() {
    const [entitiesRes, unitsRes, goodsRes, measuresRes, currenciesRes] = await Promise.all([
        axios.get('/api/entities', { params: { itemsPerPage: 5000 } }),
        axios.get('/api/units'),
        axios.get('/api/goods', { params: { per_page: 9999 } }),
        axios.get('/api/measures'),
        axios.get('/api/currencies'),
    ])

    entities.value = extractItems(entitiesRes)
    units.value = extractItems(unitsRes)
    goodsOptions.value = extractItems(goodsRes)
    measures.value = extractItems(measuresRes)
    currencies.value = extractItems(currenciesRes)
}

function normalizeSearchText(value) {
    return String(value ?? '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim()
}

function entitySearchFilter(value, query, item) {
    const raw = item?.raw || item || {}
    const haystack = normalizeSearchText([
        value,
        raw.name,
        raw.full_name,
        raw.INN,
        raw.KPP,
        raw.OGRN,
        entityUnitsText(raw, 12),
    ].filter(Boolean).join(' '))
    const tokens = normalizeSearchText(query).split(' ').filter(Boolean)

    return tokens.every((token) => haystack.includes(token)) ? 0 : -1
}

function entityTitle(entity) {
    return [entity?.name, entityUnitsText(entity, 2)].filter(Boolean).join(' | ')
}

function unitTitle(unit) {
    return unit?.name || `Unit #${unit?.id}`
}

function goodTitle(good) {
    return good?.name || `Good #${good?.id}`
}

function toNumber(value, fallback = 0) {
    const number = Number(String(value ?? '').replace(',', '.'))
    return Number.isFinite(number) ? number : fallback
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(toNumber(value))
}

function formatDate(value, long = false) {
    if (!value) return '-'

    const date = /^\d{4}-\d{2}-\d{2}/.test(String(value))
        ? new Date(...String(value).slice(0, 10).split('-').map((part, index) => index === 1 ? Number(part) - 1 : Number(part)))
        : new Date(value)

    return new Intl.DateTimeFormat('ru-RU', long
        ? { day: 'numeric', month: 'long', year: 'numeric' }
        : { day: '2-digit', month: 'short', year: 'numeric' }
    ).format(date)
}

function entityUnits(entity) {
    return entity?.units || []
}

function entityUnitsText(entity, limit = 3) {
    const names = entityUnits(entity).map((unit) => unit?.name).filter(Boolean)
    if (!names.length) return ''

    const extra = names.length > limit ? ` +${names.length - limit}` : ''
    return `${names.slice(0, limit).join(' · ')}${extra}`
}

function entityHref(entity) {
    return entity?.id ? route('Ameise.entity.show', entity.id) : null
}

function unitHref(unit) {
    return unit?.id ? route('web.unit.show', unit.id) : null
}

function goodHref(good) {
    const id = good?.id || good?.good_id
    return id ? route('Ameise.good.show', id) : null
}

function measureTitle(id) {
    return measuresById.value.get(Number(id))?.name || '-'
}

function currencyTitle(id) {
    const currency = currenciesById.value.get(Number(id))
    return currency?.code || currency?.name || ''
}

function itemGood(item) {
    return item?.good || goodsById.value.get(Number(item?.good_id)) || null
}

function goodThumbnail(good) {
    return good?.thumbnail_url || good?.ava_thumb || good?.ava_image || ''
}

function selectedGood(row) {
    return goodsById.value.get(Number(row.good_id)) || null
}

function linePrice(item) {
    const currency = currencyTitle(item.currency_id)
    return [formatMoney(item.price), currency].filter(Boolean).join(' ')
}

function lineTotal(item) {
    const currency = currencyTitle(item.currency_id)
    return [formatMoney(item.total), currency].filter(Boolean).join(' ')
}

function applyFilters() {
    filtersMenu.value = false
    loadPurchases(1)
}

function resetFilters() {
    filters.search = ''
    filters.date_from = ''
    filters.date_to = ''
    filters.amount_from = ''
    filters.amount_to = ''
    filters.entity_ids = []
    filters.unit_ids = []
    filters.good_ids = []
    filters.measure_ids = []
    filters.currency_ids = []
    loadPurchases(1)
}

function goToPage(nextPage) {
    if (nextPage < 1 || nextPage > lastPage.value || nextPage === currentPage.value) {
        return
    }

    loadPurchases(nextPage)
}

function openCreate() {
    resetForm()
    form.date = new Date().toISOString().slice(0, 10)
    serverErrors.value = {}
    dialog.value = true
}

async function openEdit(id) {
    const purchase = await fetchPurchase(id)
    resetForm()
    fillForm(purchase)
    recalcAmount()
    serverErrors.value = {}
    dialog.value = true
}

async function openDetails(purchase) {
    selectedPurchase.value = purchase
    detailsDialog.value = true
    detailsLoading.value = true

    try {
        selectedPurchase.value = await fetchPurchase(purchase.id)
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'Не удалось открыть карточку закупки'
        console.error('fetch purchase details error:', error?.response?.data || error)
    } finally {
        detailsLoading.value = false
    }
}

function closeDialog() {
    dialog.value = false
    resetForm()
    serverErrors.value = {}
}

function addItemRow() {
    form.items.push(emptyItem())
}

function removeItemRow(index) {
    form.items.splice(index, 1)

    if (!form.items.length) {
        form.items.push(emptyItem())
    }

    recalcAmount()
}

function onItemChanged(itemRow) {
    recalcItem(itemRow)
    recalcAmount()
}

async function submit() {
    saving.value = true
    serverErrors.value = {}
    errorMessage.value = ''

    try {
        recalcAmount()

        if (isEdit.value) {
            await updatePurchase(form.id, payload.value)
        } else {
            await createPurchase(payload.value)
            page.value = 1
        }

        closeDialog()
        await loadPurchases(page.value)
    } catch (error) {
        if (error.response?.status === 422) {
            serverErrors.value = error.response.data.errors || {}
        } else {
            errorMessage.value = error?.response?.data?.message || 'Не удалось сохранить закупку'
            console.error('submit purchase error:', error?.response?.data || error)
        }
    } finally {
        saving.value = false
    }
}

async function remove(id) {
    if (!window.confirm(`Удалить закупку #${id}?`)) {
        return
    }

    await deletePurchase(id)
    await loadPurchases(page.value)
}

onMounted(async () => {
    await Promise.all([
        loadPurchases(1),
        loadDictionaries(),
    ])
})
</script>

<template>
    <v-theme-provider theme="light">
        <section class="purchases-board">
            <div class="purchases-toolbar">
                <div class="purchases-toolbar__title">
                    <span>Grossbuch / закупки</span>
                    <h2>Закупки</h2>
                </div>

                <div class="purchases-toolbar__meta">
                    <span class="purchases-counter">{{ rangeStart }}-{{ rangeEnd }} / {{ totalItems }}</span>

                    <v-menu
                        v-model="filtersMenu"
                        :close-on-content-click="false"
                        location="bottom end"
                        width="720"
                    >
                        <template #activator="{ props }">
                            <v-btn
                                v-bind="props"
                                variant="outlined"
                                density="compact"
                                size="small"
                                prepend-icon="mdi-filter-variant"
                            >
                                Фильтры
                                <span v-if="activeFilterCount" class="purchase-filter-count">{{ activeFilterCount }}</span>
                            </v-btn>
                        </template>

                        <div class="purchase-filter-panel">
                            <div class="purchase-filter-panel__head">
                                <strong>Фильтры и поиск</strong>
                                <v-btn icon="mdi-close" variant="text" size="x-small" @click="filtersMenu = false" />
                            </div>

                            <div class="purchase-filter-grid">
                                <v-text-field
                                    v-model="filters.search"
                                    label="Поиск по всем параметрам"
                                    prepend-inner-icon="mdi-magnify"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                    clearable
                                    class="purchase-filter-grid__wide"
                                    @keyup.enter="applyFilters"
                                />

                                <v-text-field
                                    v-model="filters.date_from"
                                    label="Дата с"
                                    type="date"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                />

                                <v-text-field
                                    v-model="filters.date_to"
                                    label="Дата по"
                                    type="date"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                />

                                <v-text-field
                                    v-model="filters.amount_from"
                                    label="Сумма от"
                                    type="number"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                />

                                <v-text-field
                                    v-model="filters.amount_to"
                                    label="Сумма до"
                                    type="number"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                />

                                <v-autocomplete
                                    v-model="filters.entity_ids"
                                    :items="entities"
                                    :item-title="entityTitle"
                                    :custom-filter="entitySearchFilter"
                                    item-value="id"
                                    label="Контрагенты"
                                    variant="solo-filled"
                                    density="compact"
                                    multiple
                                    chips
                                    closable-chips
                                    hide-details
                                    clearable
                                    class="purchase-filter-grid__wide"
                                />

                                <v-autocomplete
                                    v-model="filters.unit_ids"
                                    :items="units"
                                    :item-title="unitTitle"
                                    item-value="id"
                                    label="Units"
                                    variant="solo-filled"
                                    density="compact"
                                    multiple
                                    chips
                                    closable-chips
                                    hide-details
                                    clearable
                                    class="purchase-filter-grid__wide"
                                />

                                <v-autocomplete
                                    v-model="filters.good_ids"
                                    :items="goodsOptions"
                                    :item-title="goodTitle"
                                    item-value="id"
                                    label="Товары"
                                    variant="solo-filled"
                                    density="compact"
                                    multiple
                                    chips
                                    closable-chips
                                    hide-details
                                    clearable
                                    class="purchase-filter-grid__wide"
                                />

                                <v-autocomplete
                                    v-model="filters.measure_ids"
                                    :items="measures"
                                    item-title="name"
                                    item-value="id"
                                    label="Ед. изм."
                                    variant="solo-filled"
                                    density="compact"
                                    multiple
                                    chips
                                    closable-chips
                                    hide-details
                                    clearable
                                />

                                <v-autocomplete
                                    v-model="filters.currency_ids"
                                    :items="currencies"
                                    item-title="name"
                                    item-value="id"
                                    label="Валюты"
                                    variant="solo-filled"
                                    density="compact"
                                    multiple
                                    chips
                                    closable-chips
                                    hide-details
                                    clearable
                                />
                            </div>

                            <div class="purchase-filter-panel__actions">
                                <v-btn variant="text" density="compact" @click="resetFilters">Сброс</v-btn>
                                <v-btn color="green-darken-2" variant="flat" density="compact" @click="applyFilters">Применить</v-btn>
                            </div>
                        </div>
                    </v-menu>

                    <v-btn
                        color="green-darken-2"
                        variant="flat"
                        density="compact"
                        size="small"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        Закупка
                    </v-btn>
                </div>
            </div>

            <div class="purchase-alert-slot">
                <v-alert
                    v-if="errorMessage"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="purchase-alert"
                >
                    {{ errorMessage }}
                </v-alert>
            </div>

            <div class="purchase-table-wrap">
                <table class="purchase-grid">
                    <thead>
                        <tr>
                            <th class="purchase-grid__id">ID</th>
                            <th class="purchase-grid__date">Дата</th>
                            <th>Контрагент</th>
                            <th class="purchase-grid__unit">Unit</th>
                            <th class="purchase-grid__amount">Сумма</th>
                            <th class="purchase-grid__actions">Действия</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="loading">
                            <td colspan="6" class="purchase-grid__state">Загрузка</td>
                        </tr>

                        <tr v-else-if="!items.length">
                            <td colspan="6" class="purchase-grid__state">Нет записей</td>
                        </tr>

                        <template v-else>
                            <tr v-for="purchase in items" :key="purchase.id">
                                <td class="purchase-grid__id">
                                    <button type="button" class="purchase-id-link" @click="openDetails(purchase)">
                                        {{ purchase.id }}
                                    </button>
                                </td>

                                <td class="purchase-grid__date">
                                    {{ formatDate(purchase.date) }}
                                </td>

                                <td>
                                    <div class="purchase-entity-cell">
                                        <Link
                                            v-if="entityHref(purchase.entity)"
                                            :href="entityHref(purchase.entity)"
                                            class="purchase-entity-cell__name"
                                        >
                                            {{ purchase.entity?.name || '-' }}
                                        </Link>
                                        <span v-else class="purchase-entity-cell__name">-</span>
                                        <span v-if="purchase.entity?.INN" class="purchase-entity-cell__meta">ИНН {{ purchase.entity.INN }}</span>
                                    </div>
                                </td>

                                <td class="purchase-grid__unit">
                                    <div v-if="entityUnits(purchase.entity).length" class="purchase-unit-list">
                                        <Link
                                            v-for="unit in entityUnits(purchase.entity).slice(0, 2)"
                                            :key="unit.id"
                                            :href="unitHref(unit)"
                                        >
                                            {{ unit.name }}
                                        </Link>
                                        <span v-if="entityUnits(purchase.entity).length > 2">
                                            +{{ entityUnits(purchase.entity).length - 2 }}
                                        </span>
                                    </div>
                                    <span v-else class="purchase-muted">-</span>
                                </td>

                                <td class="purchase-grid__amount">
                                    <button type="button" class="purchase-amount-button" @click="openDetails(purchase)">
                                        {{ formatMoney(purchase.amount) }}
                                    </button>
                                </td>

                                <td class="purchase-grid__actions">
                                    <div class="purchase-actions">
                                        <v-tooltip text="Карточка">
                                            <template #activator="{ props }">
                                                <v-btn
                                                    v-bind="props"
                                                    icon="mdi-eye-outline"
                                                    size="x-small"
                                                    density="compact"
                                                    variant="text"
                                                    color="blue-darken-2"
                                                    @click="openDetails(purchase)"
                                                />
                                            </template>
                                        </v-tooltip>

                                        <v-tooltip text="Изменить">
                                            <template #activator="{ props }">
                                                <v-btn
                                                    v-bind="props"
                                                    icon="mdi-pencil-outline"
                                                    size="x-small"
                                                    density="compact"
                                                    variant="text"
                                                    color="green-darken-3"
                                                    @click="openEdit(purchase.id)"
                                                />
                                            </template>
                                        </v-tooltip>

                                        <v-tooltip text="Удалить">
                                            <template #activator="{ props }">
                                                <v-btn
                                                    v-bind="props"
                                                    icon="mdi-delete-outline"
                                                    size="x-small"
                                                    density="compact"
                                                    variant="text"
                                                    color="red-darken-3"
                                                    @click="remove(purchase.id)"
                                                />
                                            </template>
                                        </v-tooltip>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="purchase-pages">
                <button
                    type="button"
                    class="purchase-page"
                    :disabled="currentPage <= 1"
                    @click="goToPage(currentPage - 1)"
                >
                    ‹
                </button>

                <div class="purchase-page-list">
                    <button
                        v-for="number in pageNumbers"
                        :key="number"
                        type="button"
                        :class="['purchase-page', { 'purchase-page--active': number === currentPage }]"
                        @click="goToPage(number)"
                    >
                        {{ number }}
                    </button>
                </div>

                <button
                    type="button"
                    class="purchase-page"
                    :disabled="currentPage >= lastPage"
                    @click="goToPage(currentPage + 1)"
                >
                    ›
                </button>
            </div>

            <v-dialog v-model="detailsDialog" max-width="1120" scrollable class="purchase-details-dialog">
                <v-card class="purchase-details">
                    <v-card-title class="purchase-details__title">
                        <div>
                            <span>Карточка закупки #{{ selectedPurchase?.id }}</span>
                            <strong>{{ formatMoney(selectedPurchase?.amount) }}</strong>
                        </div>
                        <v-btn icon="mdi-close" variant="text" @click="detailsDialog = false" />
                    </v-card-title>

                    <v-card-text class="purchase-details__body">
                        <v-progress-linear v-if="detailsLoading" indeterminate color="green-darken-2" />

                        <div v-if="selectedPurchase" class="purchase-details__summary">
                            <div>
                                <small>Дата</small>
                                <strong>{{ formatDate(selectedPurchase.date, true) }}</strong>
                            </div>

                            <div>
                                <small>Контрагент</small>
                                <Link v-if="entityHref(selectedPurchase.entity)" :href="entityHref(selectedPurchase.entity)">
                                    {{ selectedPurchase.entity?.name }}
                                </Link>
                                <strong v-else>-</strong>
                            </div>

                            <div>
                                <small>Unit</small>
                                <span v-if="entityUnits(selectedPurchase.entity).length" class="purchase-details__units">
                                    <Link
                                        v-for="unit in entityUnits(selectedPurchase.entity)"
                                        :key="unit.id"
                                        :href="unitHref(unit)"
                                    >
                                        {{ unit.name }}
                                    </Link>
                                </span>
                                <strong v-else>-</strong>
                            </div>
                        </div>

                        <div class="purchase-lines">
                            <div class="purchase-lines__head">
                                <span></span>
                                <span>Товар</span>
                                <span>Кол-во</span>
                                <span>Ед.</span>
                                <span>Цена</span>
                                <span>Валюта</span>
                                <span>Сумма</span>
                            </div>

                            <div
                                v-for="item in selectedItems"
                                :key="item.pivot_id || item.good_id"
                                class="purchase-lines__row"
                            >
                                <span class="purchase-thumb-cell">
                                    <img
                                        v-if="goodThumbnail(itemGood(item))"
                                        :src="goodThumbnail(itemGood(item))"
                                        :alt="itemGood(item)?.name || item.good_name || 'Товар'"
                                        class="purchase-thumb"
                                    >
                                    <span v-else class="purchase-thumb purchase-thumb--empty">
                                        <v-icon icon="mdi-image-outline" size="16" />
                                    </span>
                                </span>

                                <Link v-if="goodHref(itemGood(item) || item)" :href="goodHref(itemGood(item) || item)">
                                    {{ itemGood(item)?.name || item.good_name || '-' }}
                                </Link>
                                <span v-else>{{ item.good_name || '-' }}</span>

                                <strong>{{ formatMoney(item.quantity) }}</strong>
                                <span>{{ measureTitle(item.measure_id) }}</span>
                                <strong>{{ linePrice(item) }}</strong>
                                <span>{{ currencyTitle(item.currency_id) || '-' }}</span>
                                <strong>{{ lineTotal(item) }}</strong>
                            </div>

                            <div v-if="!selectedItems.length" class="purchase-lines__empty">
                                Позиции не прикреплены к закупке
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-dialog>

            <v-dialog
                v-model="dialog"
                width="calc(100vw - 24px)"
                max-width="1400"
                scrollable
                class="purchase-form-dialog"
            >
                <v-card class="purchase-form">
                    <v-card-title class="purchase-form__title">
                        <div>
                            <span>{{ isEdit ? 'Редактирование закупки' : 'Создание закупки' }}</span>
                            <strong>{{ formatMoney(form.amount) }}</strong>
                        </div>
                        <v-btn icon="mdi-close" variant="text" @click="closeDialog" />
                    </v-card-title>

                    <v-card-text class="purchase-form__body">
                        <v-row dense>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="form.date"
                                    type="date"
                                    label="Дата"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="serverErrors.date"
                                />
                            </v-col>

                            <v-col cols="12" md="7">
                                <v-autocomplete
                                    v-model="form.entity_id"
                                    :items="entities"
                                    :item-title="entityTitle"
                                    :custom-filter="entitySearchFilter"
                                    item-value="id"
                                    label="Контрагент"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="serverErrors.entity_id"
                                />
                            </v-col>

                            <v-col cols="12" md="2">
                                <v-text-field
                                    :model-value="formatMoney(form.amount)"
                                    label="Итого"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details
                                    readonly
                                />
                            </v-col>
                        </v-row>

                        <div class="purchase-form-lines">
                            <div class="purchase-form-lines__head">
                                <span></span>
                                <span>Товар</span>
                                <span>Кол-во</span>
                                <span>Ед.</span>
                                <span>Цена</span>
                                <span>Валюта</span>
                                <span>Сумма</span>
                                <span></span>
                            </div>

                            <div
                                v-for="(itemRow, index) in form.items"
                                :key="index"
                                class="purchase-form-line"
                            >
                                <span class="purchase-form-line__thumb">
                                    <img
                                        v-if="goodThumbnail(selectedGood(itemRow))"
                                        :src="goodThumbnail(selectedGood(itemRow))"
                                        :alt="selectedGood(itemRow)?.name || 'Товар'"
                                    >
                                    <v-icon v-else icon="mdi-image-outline" size="16" />
                                </span>

                                <v-autocomplete
                                    v-model="itemRow.good_id"
                                    :items="goodsOptions"
                                    :item-title="goodTitle"
                                    item-value="id"
                                    placeholder="Товар"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="serverErrors[`items.${index}.good_id`]"
                                />

                                <v-text-field
                                    v-model="itemRow.quantity"
                                    type="number"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="serverErrors[`items.${index}.quantity`]"
                                    @update:model-value="onItemChanged(itemRow)"
                                />

                                <v-select
                                    v-model="itemRow.measure_id"
                                    :items="measures"
                                    item-title="name"
                                    item-value="id"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="serverErrors[`items.${index}.measure_id`]"
                                />

                                <v-text-field
                                    v-model="itemRow.price"
                                    type="number"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="serverErrors[`items.${index}.price`]"
                                    @update:model-value="onItemChanged(itemRow)"
                                />

                                <v-select
                                    v-model="itemRow.currency_id"
                                    :items="currencies"
                                    :item-title="(currency) => currency.code || currency.name"
                                    item-value="id"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="serverErrors[`items.${index}.currency_id`]"
                                />

                                <v-text-field
                                    :model-value="formatMoney(itemRow.total)"
                                    variant="solo-filled"
                                    density="compact"
                                    hide-details="auto"
                                    readonly
                                    :error-messages="serverErrors[`items.${index}.total`]"
                                />

                                <v-btn
                                    icon="mdi-delete-outline"
                                    size="small"
                                    density="compact"
                                    variant="text"
                                    color="red-darken-3"
                                    @click="removeItemRow(index)"
                                />
                            </div>
                        </div>

                        <div class="purchase-form__line-actions">
                            <v-btn color="green-darken-2" variant="flat" density="compact" prepend-icon="mdi-plus" @click="addItemRow">
                                Позиция
                            </v-btn>
                            <span>Итого: <strong>{{ formatMoney(form.amount) }}</strong></span>
                        </div>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" @click="closeDialog">Отмена</v-btn>
                        <v-btn color="green-darken-2" variant="flat" :loading="saving" :disabled="!canSubmit" @click="submit">
                            Сохранить
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </section>
    </v-theme-provider>
</template>

<style scoped>
.purchases-board {
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr) auto;
    height: calc(100vh - 150px);
    min-height: 520px;
    overflow: hidden;
    color: #202622;
    background: #f3f4f0;
    font-size: 12px;
}

.purchases-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 46px;
    padding: 7px 8px;
    border: 1px solid #c7d0c8;
    border-bottom: 0;
    background: linear-gradient(180deg, #f9faf7 0%, #e2e7df 100%);
}

.purchases-toolbar__title {
    display: grid;
    gap: 2px;
}

.purchases-toolbar__title span {
    color: #68736b;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.purchases-toolbar h2 {
    margin: 0;
    color: #1f3026;
    font-size: 18px;
    line-height: 1;
}

.purchases-toolbar__meta {
    display: flex;
    align-items: center;
    gap: 7px;
}

.purchases-counter {
    padding: 3px 6px;
    border: 1px solid #bac5bc;
    background: #fff;
    color: #26332b;
    font-family: "Courier New", monospace;
    font-size: 11px;
    font-weight: 700;
}

.purchase-filter-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 15px;
    height: 15px;
    margin-left: 5px;
    padding: 0 3px;
    border-radius: 2px;
    background: #1f7a4a;
    color: #fff;
    font-size: 9px;
    line-height: 1;
}

.purchase-filter-panel {
    padding: 8px;
    border: 1px solid #9fb2a3;
    background: #f7f8f4;
    box-shadow: 0 12px 28px rgba(28, 42, 31, 0.22);
}

.purchase-filter-panel__head,
.purchase-filter-panel__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.purchase-filter-panel__head {
    margin-bottom: 8px;
    color: #1f3026;
    font-size: 13px;
}

.purchase-filter-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.purchase-filter-grid__wide {
    grid-column: 1 / -1;
}

.purchase-filter-panel__actions {
    margin-top: 9px;
}

.purchase-alert {
    margin: 0;
    border-radius: 0;
}

.purchase-table-wrap {
    min-height: 0;
    overflow: auto;
    border: 1px solid #c7d0c8;
    background: #fff;
    scrollbar-width: thin;
}

.purchase-grid {
    width: 100%;
    min-width: 980px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    color: #202622;
    font-size: 12px;
}

.purchase-grid th,
.purchase-grid td {
    overflow: hidden;
    height: 27px;
    padding: 2px 6px;
    border-right: 1px solid #d8ded6;
    border-bottom: 1px solid #d8ded6;
    text-overflow: ellipsis;
    vertical-align: middle;
}

.purchase-grid th {
    position: sticky;
    top: 0;
    z-index: 2;
    height: 28px;
    background: linear-gradient(180deg, #f2f4ef 0%, #dce3da 100%);
    color: #233029;
    font-size: 11px;
    font-weight: 800;
    text-align: left;
}

.purchase-grid tbody tr:nth-child(even) td {
    background: #fbfcfa;
}

.purchase-grid tbody tr:hover td {
    background: #eaf4ec;
}

.purchase-grid__id {
    width: 58px;
    text-align: right;
}

.purchase-grid__date {
    width: 118px;
    color: #26332b;
    font-family: "Courier New", monospace;
    font-weight: 700;
}

.purchase-grid__unit {
    width: 238px;
}

.purchase-grid__amount {
    width: 148px;
    text-align: right;
}

.purchase-grid__actions {
    width: 78px;
    padding-right: 2px !important;
    padding-left: 2px !important;
    text-align: center;
}

.purchase-grid__state {
    height: 82px !important;
    color: #6b766f;
    text-align: center;
}

.purchase-id-link,
.purchase-amount-button {
    color: #1c5f86;
    font-family: "Courier New", monospace;
    font-weight: 800;
    text-decoration: none;
}

.purchase-id-link:hover,
.purchase-amount-button:hover {
    color: #0d7d47;
    text-decoration: underline;
}

.purchase-entity-cell {
    display: grid;
    gap: 1px;
    min-width: 0;
    line-height: 1.12;
}

.purchase-entity-cell__name {
    overflow: hidden;
    color: #1f3026;
    font-weight: 800;
    text-overflow: ellipsis;
    text-decoration: none;
    white-space: nowrap;
}

.purchase-entity-cell__name:hover,
.purchase-unit-list a:hover {
    color: #1c5f86;
    text-decoration: underline;
}

.purchase-entity-cell__meta {
    color: #707b73;
    font-size: 9px;
}

.purchase-unit-list {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    line-height: 1.1;
}

.purchase-unit-list a,
.purchase-unit-list span {
    max-width: 100%;
    overflow: hidden;
    padding: 1px 4px;
    border: 1px solid #cbd8cc;
    background: #f5faf4;
    color: #22623e;
    font-size: 10px;
    text-overflow: ellipsis;
    text-decoration: none;
    white-space: nowrap;
}

.purchase-muted {
    color: #879088;
}

.purchase-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
}

.purchase-pages {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 5px;
    min-height: 30px;
    padding: 4px 6px;
    border: 1px solid #c7d0c8;
    border-top: 0;
    background: #edf0e9;
}

.purchase-page-list {
    display: flex;
    gap: 3px;
    overflow-x: auto;
    scrollbar-width: thin;
}

.purchase-page {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    min-width: 18px;
    height: 18px;
    padding: 0 3px;
    border: 1px solid #9ead9f;
    background: #fff;
    color: #26332b;
    font-family: "Courier New", monospace;
    font-size: 10px;
    line-height: 1;
}

.purchase-page:hover:not(:disabled) {
    border-color: #1f7a4a;
    background: #e9f5eb;
}

.purchase-page--active {
    border-color: #1f7a4a;
    background: #1f7a4a;
    color: #fff;
}

.purchase-page:disabled {
    opacity: 0.38;
}

.purchase-details {
    background: #f7f8f4;
    color: #202622;
}

.purchase-details__title,
.purchase-form__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 44px;
    background: linear-gradient(180deg, #355944 0%, #244131 100%);
    color: #fff;
}

.purchase-details__title div,
.purchase-form__title div {
    display: flex;
    align-items: baseline;
    gap: 14px;
}

.purchase-details__title span,
.purchase-form__title span {
    font-size: 14px;
    font-weight: 800;
}

.purchase-details__title strong,
.purchase-form__title strong {
    font-family: "Courier New", monospace;
    font-size: 15px;
}

.purchase-details__body {
    display: grid;
    gap: 9px;
    padding: 10px !important;
}

.purchase-details__summary {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr) minmax(220px, 0.7fr);
    gap: 6px;
}

.purchase-details__summary > div {
    display: grid;
    gap: 2px;
    min-width: 0;
    padding: 6px 8px;
    border: 1px solid #cbd8cc;
    background: #fff;
}

.purchase-details__summary small {
    color: #6b766f;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.purchase-details__summary strong,
.purchase-details__summary a {
    overflow: hidden;
    color: #1f3026;
    font-size: 12px;
    font-weight: 800;
    text-overflow: ellipsis;
    text-decoration: none;
    white-space: nowrap;
}

.purchase-details__units {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.purchase-details__units a {
    padding: 1px 5px;
    border: 1px solid #cbd8cc;
    background: #f5faf4;
    color: #22623e;
    font-size: 10px;
    text-decoration: none;
}

.purchase-lines {
    border: 1px solid #c7d0c8;
    background: #fff;
}

.purchase-lines__head,
.purchase-lines__row {
    display: grid;
    grid-template-columns: 48px minmax(220px, 1fr) 96px 76px 118px 74px 124px;
    align-items: center;
}

.purchase-lines__head {
    min-height: 28px;
    background: #dfe7dd;
    color: #26332b;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.purchase-lines__head span,
.purchase-lines__row > * {
    min-width: 0;
    padding: 4px 6px;
    border-right: 1px solid #d8ded6;
}

.purchase-lines__row {
    min-height: 42px;
    border-top: 1px solid #d8ded6;
    font-size: 12px;
}

.purchase-lines__row a {
    overflow: hidden;
    color: #1f3026;
    font-weight: 800;
    text-overflow: ellipsis;
    text-decoration: none;
    white-space: nowrap;
}

.purchase-lines__row strong {
    color: #202622;
    font-family: "Courier New", monospace;
    text-align: right;
}

.purchase-thumb-cell {
    display: flex;
    justify-content: center;
}

.purchase-thumb {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: 1px solid #cbd8cc;
    background: #eef2ed;
    object-fit: cover;
}

.purchase-thumb--empty {
    color: #6b766f;
}

.purchase-lines__empty {
    padding: 14px;
    color: #6b766f;
}

.purchase-form {
    min-height: calc(100vh - 42px);
    background: #f7f8f4;
    color: #202622;
}

.purchase-form-dialog :deep(.v-overlay__content) {
    margin: 12px;
    max-height: calc(100vh - 24px);
}

.purchase-form__body {
    display: grid;
    gap: 10px;
    padding: 10px 12px !important;
}

.purchase-form-lines {
    overflow-x: auto;
    border: 1px solid #c7d0c8;
    background: #fff;
}

.purchase-form-lines__head,
.purchase-form-line {
    display: grid;
    grid-template-columns: 42px minmax(280px, 1fr) 92px 126px 110px 104px 118px 42px;
    align-items: center;
    min-width: 1000px;
}

.purchase-form-lines__head {
    min-height: 28px;
    background: #dfe7dd;
    color: #26332b;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.purchase-form-lines__head span,
.purchase-form-line > * {
    min-width: 0;
    padding: 4px;
    border-right: 1px solid #d8ded6;
}

.purchase-form-line {
    min-height: 46px;
    border-top: 1px solid #d8ded6;
}

.purchase-form-line__thumb {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b766f;
}

.purchase-form-line__thumb img {
    width: 30px;
    height: 30px;
    border: 1px solid #cbd8cc;
    object-fit: cover;
}

.purchase-form__line-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.purchase-form__line-actions span {
    font-family: "Courier New", monospace;
}

.purchase-filter-panel :deep(.v-field),
.purchase-form :deep(.v-field) {
    border-radius: 3px;
    font-size: 12px;
}

.purchase-filter-panel :deep(.v-field__input),
.purchase-form :deep(.v-field__input) {
    min-height: 34px;
    padding-top: 0;
    padding-bottom: 0;
    font-size: 12px;
}

.purchase-filter-panel :deep(.v-label.v-field-label),
.purchase-form :deep(.v-label.v-field-label) {
    font-size: 11px;
}

@media (max-width: 900px) {
    .purchases-board {
        height: calc(100vh - 128px);
        min-height: 460px;
    }

    .purchases-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .purchases-toolbar__meta {
        flex-wrap: wrap;
    }

    .purchase-filter-grid {
        grid-template-columns: 1fr;
    }

    .purchase-details__summary {
        grid-template-columns: 1fr;
    }
}
</style>
