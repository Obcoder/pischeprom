<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
    warehouses: {
        type: Array,
        default: () => [],
    },
    measures: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['stats-change'])

const stockRows = ref([])
const movements = ref([])
const goods = ref([])
const alerts = ref([])
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const formError = ref('')
const movementFormOpen = ref(false)
const stockToolsMenu = ref(false)

const movementTypes = [
    { value: 'receipt', title: 'Приход' },
    { value: 'write_off', title: 'Списание' },
    { value: 'adjustment', title: 'Корректировка' },
]

const stockBalanceOptions = [
    { value: 'all', title: 'Все остатки' },
    { value: 'available', title: 'В наличии (> 0)' },
    { value: 'shortage', title: 'Дефицит (< 0)' },
]

const stockSortOptions = [
    { value: 'good_name', title: 'Название товара' },
    { value: 'warehouse_name', title: 'Название склада' },
    { value: 'quantity', title: 'Остаток' },
    { value: 'stock_value', title: 'Стоимость' },
    { value: 'last_moved_at', title: 'Последнее движение' },
]

const form = reactive({
    id: null,
    warehouse_id: null,
    good_id: null,
    measure_id: null,
    type: 'receipt',
    quantity: 1,
    unit_price: 0,
    moved_at: today(),
    note: '',
})

const stockFilters = reactive({
    search: '',
    warehouse_id: null,
    measure_key: null,
    balance: 'all',
    sort_by: 'good_name',
    sort_direction: 'asc',
})

const stats = computed(() => {
    const availableGoods = new Set(
        stockRows.value
            .filter((row) => numeric(row.quantity) > 0)
            .map((row) => row.good_id)
    )

    return {
        available: availableGoods.size,
        positions: stockRows.value.length,
        activeAlerts: alerts.value.filter((alert) => alert.status === 'active').length,
    }
})

const stockWarehouseOptions = computed(() => {
    const options = new Map()

    stockRows.value.forEach((row) => {
        const id = Number(row.warehouse_id)

        if (!Number.isFinite(id) || options.has(id)) {
            return
        }

        options.set(id, {
            id,
            name: row.warehouse?.name || `Склад #${id}`,
        })
    })

    return [...options.values()].sort((left, right) => (
        compareText(left.name, right.name)
    ))
})

const stockMeasureOptions = computed(() => {
    const options = new Map()

    stockRows.value.forEach((row) => {
        const key = row.measure_id === null || row.measure_id === undefined
            ? 'none'
            : String(row.measure_id)

        if (options.has(key)) {
            return
        }

        options.set(key, {
            key,
            name: row.measure?.name || 'Без единицы',
        })
    })

    return [...options.values()].sort((left, right) => (
        compareText(left.name, right.name)
    ))
})

const activeStockFilterCount = computed(() => [
    normalizeSearch(stockFilters.search),
    stockFilters.warehouse_id,
    stockFilters.measure_key,
    stockFilters.balance !== 'all' ? stockFilters.balance : null,
].filter((value) => value !== null && value !== undefined && value !== '').length)

const stockToolsChanged = computed(() => (
    activeStockFilterCount.value > 0
    || stockFilters.sort_by !== 'good_name'
    || stockFilters.sort_direction !== 'asc'
))

const visibleStockRows = computed(() => {
    const search = normalizeSearch(stockFilters.search)
    const rows = stockRows.value.filter((row) => {
        if (
            stockFilters.warehouse_id !== null
            && stockFilters.warehouse_id !== undefined
            && Number(row.warehouse_id) !== Number(stockFilters.warehouse_id)
        ) {
            return false
        }

        const measureKey = row.measure_id === null || row.measure_id === undefined
            ? 'none'
            : String(row.measure_id)

        if (
            stockFilters.measure_key !== null
            && stockFilters.measure_key !== undefined
            && measureKey !== stockFilters.measure_key
        ) {
            return false
        }

        const quantity = numeric(row.quantity)

        if (stockFilters.balance === 'available' && quantity <= 0) {
            return false
        }

        if (stockFilters.balance === 'shortage' && quantity >= 0) {
            return false
        }

        if (!search) {
            return true
        }

        return normalizeSearch([
            row.good?.name,
            row.warehouse?.name,
            row.measure?.name,
            row.good_id,
        ].filter(Boolean).join(' ')).includes(search)
    })

    return rows.sort((left, right) => {
        const direction = stockFilters.sort_direction === 'desc' ? -1 : 1
        const primaryResult = compareStockRows(left, right, stockFilters.sort_by)

        if (primaryResult !== 0) {
            return primaryResult * direction
        }

        let result = compareText(left.good?.name, right.good?.name)

        if (result === 0) {
            result = numeric(left.good_id) - numeric(right.good_id)
        }

        return result
    })
})

function unpack(response) {
    return response?.data?.data || response?.data || []
}

function today() {
    return new Date().toISOString().slice(0, 10)
}

function numeric(value) {
    const number = Number(value)

    return Number.isFinite(number) ? number : 0
}

function normalizeSearch(value) {
    return String(value || '').trim().toLocaleLowerCase('ru-RU')
}

function compareText(left, right) {
    return String(left || '').localeCompare(String(right || ''), 'ru-RU', {
        numeric: true,
        sensitivity: 'base',
    })
}

function compareStockRows(left, right, sortBy) {
    if (sortBy === 'quantity' || sortBy === 'stock_value') {
        return numeric(left[sortBy]) - numeric(right[sortBy])
    }

    if (sortBy === 'warehouse_name') {
        return compareText(left.warehouse?.name, right.warehouse?.name)
    }

    if (sortBy === 'last_moved_at') {
        return compareText(left.last_moved_at, right.last_moved_at)
    }

    return compareText(left.good?.name, right.good?.name)
}

function resetStockTools() {
    stockFilters.search = ''
    stockFilters.warehouse_id = null
    stockFilters.measure_key = null
    stockFilters.balance = 'all'
    stockFilters.sort_by = 'good_name'
    stockFilters.sort_direction = 'asc'
}

function formatQty(value) {
    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: 3,
    }).format(numeric(value))
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(numeric(value))
}

function formatDate(value, withTime = false) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return String(value)
    }

    return new Intl.DateTimeFormat('ru-RU', withTime
        ? { dateStyle: 'short', timeStyle: 'short' }
        : { dateStyle: 'short' }
    ).format(date)
}

function movementTypeTitle(type) {
    return movementTypes.find((item) => item.value === type)?.title || type
}

function alertStatusTitle(status) {
    return {
        pending: 'Ожидает запуска MAX',
        active: 'Ждёт поступления',
        notified: 'Отправлено',
        cancelled: 'Отменено',
        failed: 'Ошибка',
        expired: 'Истекло',
    }[status] || status
}

function alertStatusColor(status) {
    return {
        pending: 'warning',
        active: 'info',
        notified: 'success',
        cancelled: 'default',
        failed: 'error',
        expired: 'default',
    }[status] || 'default'
}

function errorMessage(error, fallback) {
    const errors = error?.response?.data?.errors

    if (errors && typeof errors === 'object') {
        const first = Object.values(errors).flat()[0]

        if (first) {
            return first
        }
    }

    return error?.response?.data?.message || fallback
}

function defaultWarehouseId() {
    return props.warehouses.find((warehouse) => warehouse.code === 'goods')?.id
        || props.warehouses.find((warehouse) => warehouse.is_active)?.id
        || props.warehouses[0]?.id
        || null
}

function goodHref(good) {
    return good?.slug
        ? route('public.goods.show', { good: good.slug })
        : null
}

async function loadAll() {
    loading.value = true
    loadError.value = ''

    try {
        const [
            stockResponse,
            movementsResponse,
            goodsResponse,
            alertsResponse,
        ] = await Promise.all([
            axios.get(route('good-warehouse-stock.index')),
            axios.get(route('good-stock-movements.index'), { params: { limit: 250 } }),
            axios.get(route('goods.index'), {
                params: {
                    per_page: 9999,
                    sort_by: 'name',
                    sort_desc: false,
                },
            }),
            axios.get(route('good-stock-alerts.index'), { params: { limit: 250 } }),
        ])

        stockRows.value = unpack(stockResponse)
        movements.value = unpack(movementsResponse)
        goods.value = unpack(goodsResponse)
        alerts.value = unpack(alertsResponse)
        emit('stats-change', {
            rows: stockRows.value.length,
            value: stockRows.value.reduce(
                (sum, row) => sum + numeric(row.stock_value),
                0
            ),
        })
    } catch (error) {
        loadError.value = errorMessage(
            error,
            'Не удалось загрузить отдельный склад товаров.'
        )
    } finally {
        loading.value = false
    }
}

function resetForm() {
    form.id = null
    form.warehouse_id = defaultWarehouseId()
    form.good_id = null
    form.measure_id = null
    form.type = 'receipt'
    form.quantity = 1
    form.unit_price = 0
    form.moved_at = today()
    form.note = ''
    formError.value = ''
}

function openMovementForm() {
    resetForm()
    movementFormOpen.value = true
}

function closeMovementForm() {
    movementFormOpen.value = false
    resetForm()
}

function editMovement(movement) {
    if (movement.source_type) {
        return
    }

    form.id = movement.id
    form.warehouse_id = movement.warehouse_id
    form.good_id = movement.good_id
    form.measure_id = movement.measure_id
    form.type = movement.type
    form.quantity = movement.type === 'write_off'
        ? Math.abs(numeric(movement.quantity_delta))
        : numeric(movement.quantity_delta)
    form.unit_price = numeric(movement.unit_price)
    form.moved_at = movement.moved_at || today()
    form.note = movement.note || ''
    formError.value = ''
    movementFormOpen.value = true
}

async function saveMovement() {
    formError.value = ''

    if (!form.warehouse_id || !form.good_id || !form.moved_at || numeric(form.quantity) === 0) {
        formError.value = 'Заполните склад, товар, дату и ненулевое количество.'
        return
    }

    saving.value = true

    const payload = {
        warehouse_id: form.warehouse_id,
        good_id: form.good_id,
        measure_id: form.measure_id,
        type: form.type,
        quantity: numeric(form.quantity),
        unit_price: numeric(form.unit_price),
        moved_at: form.moved_at,
        note: form.note || null,
    }

    try {
        if (form.id) {
            await axios.patch(route('good-stock-movements.update', form.id), payload)
        } else {
            await axios.post(route('good-stock-movements.store'), payload)
        }

        resetForm()
        movementFormOpen.value = false
        await loadAll()
    } catch (error) {
        formError.value = errorMessage(error, 'Не удалось сохранить движение.')
    } finally {
        saving.value = false
    }
}

async function deleteMovement(movement) {
    if (
        movement.source_type
        || !confirm('Удалить движение отдельного склада товаров?')
    ) {
        return
    }

    try {
        await axios.delete(route('good-stock-movements.destroy', movement.id))
        await loadAll()
    } catch (error) {
        loadError.value = errorMessage(error, 'Не удалось удалить движение.')
    }
}

async function cancelAlert(alert) {
    if (!confirm(`Отменить оповещение о товаре «${alert.good?.name || alert.good_id}»?`)) {
        return
    }

    try {
        await axios.delete(route('good-stock-alerts.destroy', alert.id))
        await loadAll()
    } catch (error) {
        loadError.value = errorMessage(error, 'Не удалось отменить оповещение.')
    }
}

watch(
    () => props.warehouses,
    () => {
        if (!form.warehouse_id) {
            form.warehouse_id = defaultWarehouseId()
        }
    },
    { deep: true, immediate: true }
)

defineExpose({
    closeMovementForm,
    openMovementForm,
    reload: loadAll,
})

onMounted(loadAll)
</script>

<template>
    <section class="goods-stock">
        <header class="goods-stock__toolbar">
            <div>
                <div class="goods-stock__eyebrow">
                    Отдельный учёт
                </div>

                <h2>Склад товаров для покупателей</h2>

                <p>
                    Остатки и оповещения по goods. Движения commodities сюда не входят.
                </p>
            </div>

            <div class="goods-stock__kpis">
                <div>
                    <span>В наличии</span>
                    <strong>{{ stats.available }}</strong>
                </div>

                <div>
                    <span>Позиций</span>
                    <strong>{{ stats.positions }}</strong>
                </div>

                <div>
                    <span>Подписок</span>
                    <strong>{{ stats.activeAlerts }}</strong>
                </div>
            </div>

            <v-btn
                icon="mdi-refresh"
                variant="text"
                density="compact"
                :loading="loading"
                title="Обновить склад товаров"
                @click="loadAll"
            />
        </header>

        <v-alert
            v-if="loadError"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-2"
        >
            {{ loadError }}
        </v-alert>

        <v-expand-transition>
            <form
                v-if="movementFormOpen"
                class="goods-stock__form"
                @submit.prevent="saveMovement"
            >
                <div class="goods-stock__form-heading">
                    <div>
                        <span>{{ form.id ? 'Редактирование движения' : 'Новое движение' }}</span>
                        <strong>Склад goods</strong>
                    </div>

                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        density="compact"
                        type="button"
                        title="Закрыть форму"
                        aria-label="Закрыть форму"
                        @click="closeMovementForm"
                    />
                </div>

                <v-select
                    v-model="form.type"
                    :items="movementTypes"
                    item-title="title"
                    item-value="value"
                    label="Операция"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-select
                    v-model="form.warehouse_id"
                    :items="warehouses"
                    item-title="name"
                    item-value="id"
                    label="Склад"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-autocomplete
                    v-model="form.good_id"
                    :items="goods"
                    item-title="name"
                    item-value="id"
                    label="Товар (goods)"
                    density="compact"
                    variant="outlined"
                    hide-details
                    clearable
                />

                <v-select
                    v-model="form.measure_id"
                    :items="measures"
                    item-title="name"
                    item-value="id"
                    label="Ед."
                    density="compact"
                    variant="outlined"
                    hide-details
                    clearable
                />

                <v-text-field
                    v-model.number="form.quantity"
                    label="Количество"
                    type="number"
                    step="0.001"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-text-field
                    v-model.number="form.unit_price"
                    label="Цена / ед."
                    type="number"
                    min="0"
                    step="0.01"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-text-field
                    v-model="form.moved_at"
                    label="Дата"
                    type="date"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-text-field
                    v-model="form.note"
                    label="Примечание"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <div class="goods-stock__form-actions">
                    <v-btn
                        text="Отмена"
                        variant="text"
                        density="comfortable"
                        type="button"
                        @click="closeMovementForm"
                    />

                    <v-btn
                        :prepend-icon="form.id ? 'mdi-content-save-outline' : 'mdi-plus'"
                        color="success"
                        variant="flat"
                        density="comfortable"
                        type="submit"
                        :loading="saving"
                    >
                        {{ form.id ? 'Сохранить' : 'Добавить' }}
                    </v-btn>
                </div>

                <div
                    v-if="formError"
                    class="goods-stock__form-error"
                >
                    {{ formError }}
                </div>
            </form>
        </v-expand-transition>

        <div class="goods-stock__grid">
            <section class="goods-stock__card">
                <div class="goods-stock__card-heading">
                    <div class="goods-stock__card-heading-copy">
                        <h3>Текущие остатки goods</h3>
                        <span v-if="stockRows.length">
                            {{ visibleStockRows.length }} из {{ stockRows.length }}
                        </span>
                    </div>

                    <v-menu
                        v-model="stockToolsMenu"
                        :close-on-content-click="false"
                        location="bottom end"
                        offset="8"
                        width="420"
                        max-width="calc(100vw - 24px)"
                    >
                        <template #activator="{ props: menuProps }">
                            <v-btn
                                v-bind="menuProps"
                                :variant="stockToolsChanged ? 'flat' : 'tonal'"
                                color="#dcebe2"
                                size="small"
                                density="compact"
                                prepend-icon="mdi-tune-variant"
                                class="goods-stock__tools-button"
                            >
                                Сортировка и фильтры

                                <span
                                    v-if="activeStockFilterCount"
                                    class="goods-stock__filter-count"
                                >
                                    {{ activeStockFilterCount }}
                                </span>
                            </v-btn>
                        </template>

                        <v-card class="goods-stock__tools-menu">
                            <v-card-title class="goods-stock__tools-title">
                                <div>
                                    <strong>Остатки goods</strong>
                                    <span>Сортировка и фильтрация таблицы</span>
                                </div>

                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    size="x-small"
                                    title="Закрыть"
                                    @click="stockToolsMenu = false"
                                />
                            </v-card-title>

                            <v-card-text class="goods-stock__tools-body">
                                <v-text-field
                                    v-model="stockFilters.search"
                                    label="Поиск по товару"
                                    prepend-inner-icon="mdi-magnify"
                                    density="compact"
                                    variant="solo-filled"
                                    clearable
                                    hide-details
                                />

                                <div class="goods-stock__tools-grid">
                                    <v-select
                                        v-model="stockFilters.warehouse_id"
                                        :items="stockWarehouseOptions"
                                        item-title="name"
                                        item-value="id"
                                        label="Склад"
                                        density="compact"
                                        variant="solo-filled"
                                        clearable
                                        hide-details
                                    />

                                    <v-select
                                        v-model="stockFilters.measure_key"
                                        :items="stockMeasureOptions"
                                        item-title="name"
                                        item-value="key"
                                        label="Единица"
                                        density="compact"
                                        variant="solo-filled"
                                        clearable
                                        hide-details
                                    />
                                </div>

                                <v-select
                                    v-model="stockFilters.balance"
                                    :items="stockBalanceOptions"
                                    item-title="title"
                                    item-value="value"
                                    label="Состояние остатка"
                                    density="compact"
                                    variant="solo-filled"
                                    hide-details
                                />

                                <div class="goods-stock__tools-section">
                                    Сортировка
                                </div>

                                <v-select
                                    v-model="stockFilters.sort_by"
                                    :items="stockSortOptions"
                                    item-title="title"
                                    item-value="value"
                                    label="Поле"
                                    density="compact"
                                    variant="solo-filled"
                                    hide-details
                                />

                                <v-btn-toggle
                                    v-model="stockFilters.sort_direction"
                                    class="goods-stock__sort-direction"
                                    density="compact"
                                    color="#176b55"
                                    mandatory
                                    divided
                                >
                                    <v-btn
                                        value="asc"
                                        prepend-icon="mdi-sort-ascending"
                                    >
                                        По возрастанию
                                    </v-btn>

                                    <v-btn
                                        value="desc"
                                        prepend-icon="mdi-sort-descending"
                                    >
                                        По убыванию
                                    </v-btn>
                                </v-btn-toggle>
                            </v-card-text>

                            <v-card-actions class="goods-stock__tools-actions">
                                <v-btn
                                    variant="text"
                                    density="compact"
                                    :disabled="!stockToolsChanged"
                                    @click="resetStockTools"
                                >
                                    Сбросить
                                </v-btn>

                                <v-spacer />

                                <v-btn
                                    color="#176b55"
                                    variant="flat"
                                    density="compact"
                                    @click="stockToolsMenu = false"
                                >
                                    Готово
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-menu>
                </div>

                <div class="goods-stock__table-wrap">
                    <table class="goods-stock__table">
                        <colgroup>
                            <col class="goods-col-warehouse">
                            <col class="goods-col-name">
                            <col class="goods-col-quantity">
                            <col class="goods-col-value">
                            <col class="goods-col-date">
                        </colgroup>

                        <thead>
                            <tr>
                                <th>Склад</th>
                                <th>Товар</th>
                                <th>Остаток</th>
                                <th>Стоимость</th>
                                <th>Последнее движение</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-if="loading && !stockRows.length">
                                <td colspan="5" class="goods-stock__state">Загрузка…</td>
                            </tr>

                            <tr v-else-if="!stockRows.length">
                                <td colspan="5" class="goods-stock__state">Движений пока нет</td>
                            </tr>

                            <tr v-else-if="!visibleStockRows.length">
                                <td colspan="5" class="goods-stock__state">
                                    По заданным фильтрам ничего не найдено
                                </td>
                            </tr>

                            <tr
                                v-for="row in visibleStockRows"
                                :key="`${row.warehouse_id}-${row.good_id}-${row.measure_id || 0}`"
                                :class="{ 'goods-stock__row--empty': numeric(row.quantity) <= 0 }"
                            >
                                <td>{{ row.warehouse?.name || `#${row.warehouse_id}` }}</td>
                                <td>
                                    <a
                                        v-if="goodHref(row.good)"
                                        :href="goodHref(row.good)"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ row.good?.name || `#${row.good_id}` }}
                                    </a>
                                    <span v-else>{{ row.good?.name || `#${row.good_id}` }}</span>
                                </td>
                                <td class="goods-stock__qty">
                                    {{ formatQty(row.quantity) }}
                                    <small>{{ row.measure?.name || '' }}</small>
                                </td>
                                <td class="goods-stock__money">{{ formatMoney(row.stock_value) }}</td>
                                <td>{{ formatDate(row.last_moved_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="goods-stock__card">
                <h3>Оповещения MAX</h3>

                <div class="goods-stock__table-wrap">
                    <table class="goods-stock__table goods-stock__table--alerts">
                        <colgroup>
                            <col class="alerts-col-good">
                            <col class="alerts-col-status">
                            <col class="alerts-col-max">
                            <col class="alerts-col-created">
                            <col class="alerts-col-actions">
                        </colgroup>

                        <thead>
                            <tr>
                                <th>Товар</th>
                                <th>Статус</th>
                                <th>MAX</th>
                                <th>Создано</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-if="loading && !alerts.length">
                                <td colspan="5" class="goods-stock__state">Загрузка…</td>
                            </tr>

                            <tr v-else-if="!alerts.length">
                                <td colspan="5" class="goods-stock__state">Подписок пока нет</td>
                            </tr>

                            <tr v-for="alert in alerts" :key="alert.id">
                                <td>{{ alert.good?.name || `#${alert.good_id}` }}</td>
                                <td>
                                    <v-chip
                                        :color="alertStatusColor(alert.status)"
                                        size="x-small"
                                        variant="tonal"
                                    >
                                        {{ alertStatusTitle(alert.status) }}
                                    </v-chip>
                                </td>
                                <td>{{ alert.max_chat?.title || alert.max_chat?.chat_id || 'Ещё не открыт' }}</td>
                                <td>{{ formatDate(alert.created_at, true) }}</td>
                                <td>
                                    <v-btn
                                        v-if="['pending', 'active', 'failed'].includes(alert.status)"
                                        icon="mdi-bell-cancel-outline"
                                        size="x-small"
                                        variant="text"
                                        color="error"
                                        title="Отменить оповещение"
                                        @click="cancelAlert(alert)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="goods-stock__card">
            <h3>Последние движения goods</h3>

            <div class="goods-stock__table-wrap">
                <table class="goods-stock__table goods-stock__table--movements">
                    <colgroup>
                        <col class="goods-movement-col-date">
                        <col class="goods-movement-col-operation">
                        <col class="goods-movement-col-warehouse">
                        <col class="goods-movement-col-name">
                        <col class="goods-movement-col-quantity">
                        <col class="goods-movement-col-price">
                        <col class="goods-movement-col-note">
                        <col class="goods-movement-col-actions">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Операция</th>
                            <th>Склад</th>
                            <th>Товар</th>
                            <th>Количество</th>
                            <th>Цена / ед.</th>
                            <th>Примечание</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="loading && !movements.length">
                            <td colspan="8" class="goods-stock__state">Загрузка…</td>
                        </tr>

                        <tr v-else-if="!movements.length">
                            <td colspan="8" class="goods-stock__state">Движений пока нет</td>
                        </tr>

                        <tr v-for="movement in movements" :key="movement.id">
                            <td>{{ formatDate(movement.moved_at) }}</td>
                            <td>
                                <span
                                    class="goods-stock__movement-type"
                                    :class="`goods-stock__movement-type--${movement.type}`"
                                >
                                    {{ movementTypeTitle(movement.type) }}
                                </span>
                            </td>
                            <td>{{ movement.warehouse?.name || '—' }}</td>
                            <td>{{ movement.good?.name || `#${movement.good_id}` }}</td>
                            <td class="goods-stock__qty">
                                {{ formatQty(movement.quantity_delta) }}
                                <small>{{ movement.measure?.name || '' }}</small>
                            </td>
                            <td class="goods-stock__money">{{ formatMoney(movement.unit_price) }}</td>
                            <td>{{ movement.note || '—' }}</td>
                            <td>
                                <div class="goods-stock__actions">
                                    <v-btn
                                        icon="mdi-pencil-outline"
                                        size="x-small"
                                        variant="text"
                                        :disabled="Boolean(movement.source_type)"
                                        :title="movement.source_type
                                            ? 'Редактируется через Purchase'
                                            : 'Редактировать'"
                                        @click="editMovement(movement)"
                                    />
                                    <v-btn
                                        icon="mdi-delete-outline"
                                        size="x-small"
                                        variant="text"
                                        color="error"
                                        :disabled="Boolean(movement.source_type)"
                                        :title="movement.source_type
                                            ? 'Удаляется вместе с Purchase'
                                            : 'Удалить'"
                                        @click="deleteMovement(movement)"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</template>

<style scoped>
.goods-stock {
    display: grid;
    gap: 10px;
    padding: 12px;
}

.goods-stock__toolbar {
    display: grid;
    grid-template-columns: minmax(320px, 1fr) auto auto;
    gap: 16px;
    align-items: center;
    padding: 10px 12px;
    border: 1px solid #8aaa9c;
    border-radius: 10px;
    background: #f2fbf6;
}

.goods-stock__toolbar h2,
.goods-stock__toolbar p,
.goods-stock__card h3 {
    margin: 0;
}

.goods-stock__toolbar h2 {
    font-size: 20px;
    font-weight: 900;
}

.goods-stock__toolbar p {
    margin-top: 2px;
    color: #52645b;
    font-size: 11px;
}

.goods-stock__eyebrow {
    color: #176b55;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.goods-stock__kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(84px, 1fr));
    gap: 1px;
    overflow: hidden;
    border: 1px solid #9fb8aa;
}

.goods-stock__kpis div {
    display: flex;
    flex-direction: column;
    padding: 5px 8px;
    background: #ffffff;
}

.goods-stock__kpis span {
    color: #607067;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.goods-stock__kpis strong {
    color: #176b55;
    font-size: 17px;
}

.goods-stock__form {
    position: relative;
    display: grid;
    grid-template-columns: 118px 150px minmax(220px, 1fr) 92px 116px 116px 142px minmax(160px, 1fr) auto;
    gap: 8px;
    align-items: start;
    padding: 8px;
    border: 1px solid #9fb8aa;
    border-radius: 10px;
    background: #e8f3ec;
}

.goods-stock__form-heading {
    display: flex;
    grid-column: 1 / -1;
    align-items: center;
    justify-content: space-between;
}

.goods-stock__form-heading div {
    display: flex;
    gap: 7px;
    align-items: baseline;
}

.goods-stock__form-heading span {
    color: #607067;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.goods-stock__form-heading strong {
    color: #176b55;
    font-size: 14px;
}

.goods-stock__form-actions,
.goods-stock__actions {
    display: flex;
    justify-content: flex-end;
    gap: 3px;
}

.goods-stock__form-error {
    grid-column: 1 / -1;
    color: #b42318;
    font-size: 11px;
    font-weight: 800;
}

.goods-stock__grid {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
    gap: 10px;
}

.goods-stock__card {
    min-width: 0;
    padding: 8px;
    border: 1px solid #9fb8aa;
    border-radius: 10px;
    background: #fafffb;
}

.goods-stock__card h3 {
    font-size: 13px;
    font-weight: 900;
}

.goods-stock__card-heading {
    display: flex;
    min-height: 28px;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 7px;
}

.goods-stock__card-heading-copy {
    display: flex;
    min-width: 0;
    align-items: baseline;
    gap: 7px;
}

.goods-stock__card-heading-copy span {
    flex: none;
    color: #607067;
    font-size: 10px;
    font-weight: 800;
}

.goods-stock__tools-button {
    flex: none;
    color: #173b2e !important;
    font-size: 10px;
    font-weight: 900;
}

.goods-stock__filter-count {
    display: inline-flex;
    min-width: 17px;
    height: 17px;
    align-items: center;
    justify-content: center;
    margin-left: 5px;
    padding: 0 4px;
    border-radius: 9px;
    background: #176b55;
    color: #ffffff;
    font-size: 9px;
}

.goods-stock__tools-menu {
    overflow: hidden;
    border: 1px solid #8aaa9c;
    border-radius: 10px !important;
}

.goods-stock__tools-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 12px 8px;
    background: #e3f0e8;
}

.goods-stock__tools-title div {
    display: grid;
    gap: 1px;
}

.goods-stock__tools-title strong {
    color: #173b2e;
    font-size: 14px;
    font-weight: 900;
}

.goods-stock__tools-title span {
    color: #607067;
    font-size: 10px;
}

.goods-stock__tools-body {
    display: grid;
    gap: 9px;
    padding: 12px !important;
}

.goods-stock__tools-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.goods-stock__tools-section {
    margin: 2px 0 -3px;
    color: #176b55;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.goods-stock__sort-direction {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
}

.goods-stock__sort-direction :deep(.v-btn) {
    min-width: 0;
}

.goods-stock__tools-actions {
    padding: 5px 12px 10px;
    border-top: 1px solid #d5e0d9;
}

.goods-stock__table-wrap {
    width: 100%;
    overflow: auto;
    border: 1px solid #c0d0c7;
    border-radius: 7px;
    background: #ffffff;
}

.goods-stock__table {
    width: 100%;
    min-width: 680px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 11px;
}

.goods-stock__table--alerts {
    min-width: 620px;
}

.goods-stock__table--movements {
    min-width: 980px;
}

.goods-col-warehouse {
    width: 17%;
}

.goods-col-name {
    width: 37%;
}

.goods-col-quantity {
    width: 14%;
}

.goods-col-value {
    width: 14%;
}

.goods-col-date {
    width: 18%;
}

.alerts-col-good {
    width: 34%;
}

.alerts-col-status {
    width: 20%;
}

.alerts-col-max {
    width: 28%;
}

.alerts-col-created {
    width: 13%;
}

.alerts-col-actions {
    width: 5%;
}

.goods-movement-col-date {
    width: 9%;
}

.goods-movement-col-operation {
    width: 11%;
}

.goods-movement-col-warehouse {
    width: 13%;
}

.goods-movement-col-name {
    width: 28%;
}

.goods-movement-col-quantity {
    width: 12%;
}

.goods-movement-col-price {
    width: 10%;
}

.goods-movement-col-note {
    width: 13%;
}

.goods-movement-col-actions {
    width: 4%;
}

.goods-stock__table th,
.goods-stock__table td {
    overflow: hidden;
    padding: 4px 6px;
    border: 1px solid #d5e0d9;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.goods-stock__table thead th {
    background: #dcebe2;
    color: #173b2e;
    font-weight: 900;
    text-align: left;
}

.goods-stock__table tbody tr:nth-child(odd) {
    background: #f3faf5;
}

.goods-stock__table tbody tr:hover {
    background: #dff2e6;
}

.goods-stock__table a {
    color: #12604a;
    font-weight: 900;
    text-decoration: none;
}

.goods-stock__table a:hover {
    text-decoration: underline;
}

.goods-stock__row--empty {
    color: #8b5b52;
    background: #fff1ee !important;
}

.goods-stock__qty,
.goods-stock__money {
    font-weight: 900;
    text-align: right;
}

.goods-stock__qty {
    color: #234b75;
}

.goods-stock__qty small {
    color: #607067;
}

.goods-stock__money {
    color: #14733f;
}

.goods-stock__movement-type {
    display: inline-flex;
    padding: 2px 5px;
    border-left: 3px solid #6b7280;
    background: #ffffff;
    font-weight: 900;
}

.goods-stock__movement-type--receipt {
    border-left-color: #17845f;
}

.goods-stock__movement-type--write_off {
    border-left-color: #b42318;
}

.goods-stock__movement-type--adjustment {
    border-left-color: #7f5f00;
}

.goods-stock__state {
    padding: 12px !important;
    color: #647269;
    text-align: center;
}

@media (max-width: 1280px) {
    .goods-stock__toolbar,
    .goods-stock__grid {
        grid-template-columns: 1fr;
    }

    .goods-stock__form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .goods-stock__card-heading {
        align-items: flex-start;
    }

    .goods-stock__tools-button {
        width: 34px;
        min-width: 34px;
        padding: 0 !important;
        font-size: 0;
    }

    .goods-stock__filter-count {
        position: absolute;
        top: -5px;
        right: -5px;
        margin: 0;
    }

    .goods-stock__tools-grid {
        grid-template-columns: 1fr;
    }
}
</style>
