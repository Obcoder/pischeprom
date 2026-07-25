<script setup>
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import GoodStockPanel from '@/Components/Warehouses/GoodStockPanel.vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { logo } from '@/Pages/Helpers/consts.js'

defineOptions({
    layout: VerwalterLayout,
})

// Goods use a separate stock ledger; the remaining tabs represent commodity warehouses.
const warehouses = ref([])
const stockRows = ref([])
const movements = ref([])
const commodities = ref([])
const measures = ref([])
const loading = ref(false)
const savingWarehouse = ref(false)
const savingMovement = ref(false)
const activeTab = ref('goods')
const goodsPanel = ref(null)
const warehouseDialog = ref(false)
const movementFormOpen = ref(false)
const loadError = ref('')
const warehouseError = ref('')
const movementError = ref('')
const goodsSummary = reactive({
    rows: 0,
    value: 0,
})

const movementTypes = [
    { value: 'receipt', title: 'Приход', icon: 'mdi-tray-arrow-down' },
    { value: 'write_off', title: 'Списание', icon: 'mdi-tray-arrow-up' },
    { value: 'adjustment', title: 'Корректировка', icon: 'mdi-tune-variant' },
]

const warehouseForm = reactive({
    id: null,
    name: '',
    code: '',
    address: '',
    description: '',
    is_active: true,
    sort_order: 500,
})

const movementForm = reactive({
    id: null,
    warehouse_id: null,
    commodity_id: null,
    measure_id: null,
    type: 'receipt',
    quantity: 1,
    unit_price: 0,
    moved_at: today(),
    note: '',
})

const stats = computed(() => ({
    warehouses: warehouses.value.length,
    rows: stockRows.value.length,
    value: stockRows.value.reduce((sum, row) => sum + numeric(row.stock_value), 0),
}))

const activeWarehouse = computed(() => {
    if (activeTab.value === 'goods') {
        return null
    }

    const warehouseId = Number(String(activeTab.value).replace('warehouse-', ''))

    return warehouses.value.find((warehouse) => Number(warehouse.id) === warehouseId) || null
})

function unpack(response) {
    return response?.data?.data || response?.data || []
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

function today() {
    return new Date().toISOString().slice(0, 10)
}

function numeric(value) {
    const number = Number(value)

    return Number.isFinite(number) ? number : 0
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

function formatDate(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`)

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('ru-RU').format(date)
}

function movementTypeTitle(type) {
    if (type === 'check_purchase') {
        return 'Check'
    }

    return movementTypes.find((item) => item.value === type)?.title || type
}

function commodityHref(item) {
    const commodityId = item.commodity?.id || item.commodity_id

    return commodityId ? route('Ameise.commodity.show', commodityId) : null
}

function defaultWarehouseId() {
    return warehouses.value.find((item) => item.is_active)?.id || warehouses.value[0]?.id || null
}

function warehouseTabValue(warehouse) {
    return `warehouse-${warehouse.id}`
}

function warehouseStockRows(warehouseId) {
    return stockRows.value.filter(
        (row) => Number(row.warehouse_id) === Number(warehouseId)
    )
}

function warehouseMovements(warehouseId) {
    return movements.value.filter(
        (movement) => Number(movement.warehouse_id) === Number(warehouseId)
    )
}

function warehouseStockValue(warehouseId) {
    return warehouseStockRows(warehouseId)
        .reduce((sum, row) => sum + numeric(row.stock_value), 0)
}

function handleGoodsStats(summary) {
    goodsSummary.rows = numeric(summary?.rows)
    goodsSummary.value = numeric(summary?.value)
}

async function loadAll() {
    loading.value = true
    loadError.value = ''

    try {
        const [
            warehousesResponse,
            stockResponse,
            movementsResponse,
            commoditiesResponse,
            measuresResponse,
        ] = await Promise.all([
            axios.get(route('warehouses.index')),
            axios.get(route('warehouse-stock.index')),
            axios.get(route('stock-movements.index'), { params: { limit: 250 } }),
            axios.get(route('commodities.index'), {
                params: {
                    per_page: 500,
                    sort_by: 'name',
                    sort_desc: false,
                },
            }),
            axios.get(route('measures.index')),
        ])

        warehouses.value = unpack(warehousesResponse)
        stockRows.value = unpack(stockResponse)
        movements.value = unpack(movementsResponse)
        commodities.value = unpack(commoditiesResponse)
        measures.value = unpack(measuresResponse)

        if (!movementForm.warehouse_id) {
            movementForm.warehouse_id = defaultWarehouseId()
        }

        if (
            activeTab.value !== 'goods'
            && !warehouses.value.some(
                (warehouse) => warehouseTabValue(warehouse) === activeTab.value
            )
        ) {
            activeTab.value = 'goods'
        }
    } catch (error) {
        loadError.value = errorMessage(error, 'Не удалось загрузить данные складов.')
    } finally {
        loading.value = false
    }
}

async function refreshAll() {
    await Promise.all([
        loadAll(),
        goodsPanel.value?.reload?.(),
    ])
}

function resetWarehouseForm() {
    warehouseForm.id = null
    warehouseForm.name = ''
    warehouseForm.code = ''
    warehouseForm.address = ''
    warehouseForm.description = ''
    warehouseForm.is_active = true
    warehouseForm.sort_order = 500
}

function openCreateWarehouse() {
    resetWarehouseForm()
    warehouseError.value = ''
    warehouseDialog.value = true
}

function editWarehouse(warehouse) {
    warehouseForm.id = warehouse.id
    warehouseForm.name = warehouse.name || ''
    warehouseForm.code = warehouse.code || ''
    warehouseForm.address = warehouse.address || ''
    warehouseForm.description = warehouse.description || ''
    warehouseForm.is_active = warehouse.is_active ?? true
    warehouseForm.sort_order = warehouse.sort_order ?? 500
    warehouseError.value = ''
    warehouseDialog.value = true
}

async function saveWarehouse() {
    warehouseError.value = ''

    if (!warehouseForm.name.trim()) {
        warehouseError.value = 'Укажите название склада.'
        return
    }

    savingWarehouse.value = true

    const payload = {
        name: warehouseForm.name,
        code: warehouseForm.code || null,
        address: warehouseForm.address || null,
        description: warehouseForm.description || null,
        is_active: Boolean(warehouseForm.is_active),
        sort_order: numeric(warehouseForm.sort_order),
    }

    try {
        let response

        if (warehouseForm.id) {
            response = await axios.patch(route('warehouses.update', warehouseForm.id), payload)
        } else {
            response = await axios.post(route('warehouses.store'), payload)
        }

        const savedWarehouseId = response?.data?.data?.id || response?.data?.id

        warehouseDialog.value = false
        resetWarehouseForm()
        await loadAll()

        if (savedWarehouseId) {
            activeTab.value = `warehouse-${savedWarehouseId}`
        }
    } catch (error) {
        warehouseError.value = errorMessage(error, 'Не удалось сохранить склад.')
    } finally {
        savingWarehouse.value = false
    }
}

async function deleteWarehouse(warehouse) {
    if (!confirm(`Удалить склад «${warehouse.name}»?`)) {
        return
    }

    try {
        await axios.delete(route('warehouses.destroy', warehouse.id))

        if (activeTab.value === warehouseTabValue(warehouse)) {
            activeTab.value = 'goods'
        }

        await loadAll()
    } catch (error) {
        loadError.value = errorMessage(error, 'Не удалось удалить склад.')
    }
}

function resetMovementForm(warehouseId = activeWarehouse.value?.id || defaultWarehouseId()) {
    movementForm.id = null
    movementForm.warehouse_id = warehouseId
    movementForm.commodity_id = null
    movementForm.measure_id = null
    movementForm.type = 'receipt'
    movementForm.quantity = 1
    movementForm.unit_price = 0
    movementForm.moved_at = today()
    movementForm.note = ''
    movementError.value = ''
}

function openMovementForm() {
    if (activeTab.value === 'goods') {
        movementFormOpen.value = false
        goodsPanel.value?.openMovementForm?.()
        return
    }

    resetMovementForm(activeWarehouse.value?.id)
    movementFormOpen.value = true
}

function closeMovementForm() {
    movementFormOpen.value = false
    resetMovementForm()
}

async function editMovement(movement) {
    if (movement.source_type) {
        return
    }

    const targetTab = `warehouse-${movement.warehouse_id}`

    if (activeTab.value !== targetTab) {
        activeTab.value = targetTab
        await nextTick()
    }

    movementForm.id = movement.id
    movementForm.warehouse_id = movement.warehouse_id
    movementForm.commodity_id = movement.commodity_id
    movementForm.measure_id = movement.measure_id
    movementForm.type = movement.type
    movementForm.quantity = movement.type === 'write_off'
        ? Math.abs(numeric(movement.quantity_delta))
        : numeric(movement.quantity_delta)
    movementForm.unit_price = numeric(movement.unit_price)
    movementForm.moved_at = movement.moved_at || today()
    movementForm.note = movement.note || ''
    movementError.value = ''
    movementFormOpen.value = true
}

async function saveMovement() {
    movementError.value = ''

    if (
        !movementForm.warehouse_id
        || !movementForm.commodity_id
        || !movementForm.moved_at
        || numeric(movementForm.quantity) === 0
    ) {
        movementError.value = 'Заполните склад, commodity, дату и ненулевое количество.'
        return
    }

    savingMovement.value = true

    const payload = {
        warehouse_id: movementForm.warehouse_id,
        commodity_id: movementForm.commodity_id,
        measure_id: movementForm.measure_id,
        type: movementForm.type,
        quantity: numeric(movementForm.quantity),
        unit_price: numeric(movementForm.unit_price),
        moved_at: movementForm.moved_at,
        note: movementForm.note || null,
    }

    try {
        if (movementForm.id) {
            await axios.patch(route('stock-movements.update', movementForm.id), payload)
        } else {
            await axios.post(route('stock-movements.store'), payload)
        }

        const warehouseId = movementForm.warehouse_id

        movementFormOpen.value = false
        resetMovementForm(warehouseId)
        await loadAll()
        activeTab.value = `warehouse-${warehouseId}`
    } catch (error) {
        movementError.value = errorMessage(error, 'Не удалось сохранить движение.')
    } finally {
        savingMovement.value = false
    }
}

async function deleteMovement(movement) {
    if (movement.source_type || !confirm('Удалить движение склада?')) {
        return
    }

    try {
        await axios.delete(route('stock-movements.destroy', movement.id))
        await loadAll()
    } catch (error) {
        loadError.value = errorMessage(error, 'Не удалось удалить движение.')
    }
}

watch(activeTab, (tab) => {
    movementFormOpen.value = false

    if (tab !== 'goods') {
        goodsPanel.value?.closeMovementForm?.()
    }
})

onMounted(loadAll)
</script>

<template>
    <v-container fluid class="warehouses-page pa-0">
        <Head title="Склады" />

        <div class="warehouses-shell">
            <header class="warehouse-toolbar">
                <div class="warehouse-toolbar__identity">
                    <span class="warehouse-toolbar__icon">
                        <v-icon icon="mdi-warehouse" size="24" />
                    </span>

                    <div>
                        <span class="warehouse-toolbar__eyebrow">Управление запасами</span>
                        <h1>Склады</h1>
                    </div>
                </div>

                <div class="warehouse-kpis">
                    <div>
                        <span>Складов</span>
                        <strong>{{ stats.warehouses }}</strong>
                    </div>

                    <div>
                        <span>Остатков</span>
                        <strong>{{ stats.rows }}</strong>
                    </div>

                    <div>
                        <span>Стоимость</span>
                        <strong>{{ formatMoney(stats.value) }}</strong>
                    </div>
                </div>

                <div class="warehouse-toolbar__actions">
                    <v-btn
                        icon="mdi-domain-plus"
                        variant="tonal"
                        color="#176b55"
                        density="comfortable"
                        title="Создать склад"
                        aria-label="Создать склад"
                        @click="openCreateWarehouse"
                    />

                    <v-btn
                        prepend-icon="mdi-plus"
                        text="Движение"
                        color="#176b55"
                        variant="flat"
                        density="comfortable"
                        :disabled="!warehouses.length"
                        @click="openMovementForm"
                    />

                    <v-btn
                        icon="mdi-refresh"
                        variant="text"
                        density="comfortable"
                        :loading="loading"
                        title="Обновить все склады"
                        aria-label="Обновить все склады"
                        @click="refreshAll"
                    />
                </div>
            </header>

            <v-alert
                v-if="loadError"
                type="error"
                variant="tonal"
                density="compact"
                closable
                @click:close="loadError = ''"
            >
                {{ loadError }}
            </v-alert>

            <v-expand-transition>
                <section
                    v-if="movementFormOpen && activeWarehouse"
                    class="movement-dropdown"
                >
                    <div class="movement-dropdown__heading">
                        <div>
                            <span>{{ movementForm.id ? 'Редактирование движения' : 'Новое движение' }}</span>
                            <strong>{{ activeWarehouse.name }}</strong>
                        </div>

                        <v-btn
                            icon="mdi-close"
                            variant="text"
                            density="compact"
                            title="Закрыть форму"
                            aria-label="Закрыть форму"
                            @click="closeMovementForm"
                        />
                    </div>

                    <form class="movement-form" @submit.prevent="saveMovement">
                        <v-select
                            v-model="movementForm.type"
                            class="movement-field--operation"
                            :items="movementTypes"
                            item-title="title"
                            item-value="value"
                            label="Операция"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />

                        <v-select
                            v-model="movementForm.warehouse_id"
                            class="movement-field--warehouse"
                            :items="warehouses"
                            item-title="name"
                            item-value="id"
                            label="Склад"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />

                        <v-autocomplete
                            v-model="movementForm.commodity_id"
                            class="movement-field--commodity"
                            :items="commodities"
                            item-title="name"
                            item-value="id"
                            label="Commodity"
                            variant="outlined"
                            density="compact"
                            clearable
                            hide-details
                        />

                        <v-text-field
                            v-model.number="movementForm.quantity"
                            class="movement-field--quantity"
                            label="Количество"
                            type="number"
                            step="0.001"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />

                        <v-select
                            v-model="movementForm.measure_id"
                            class="movement-field--measure"
                            :items="measures"
                            item-title="name"
                            item-value="id"
                            label="Единица"
                            variant="outlined"
                            density="compact"
                            clearable
                            hide-details
                        />

                        <v-text-field
                            v-model.number="movementForm.unit_price"
                            class="movement-field--price"
                            label="Цена / ед."
                            type="number"
                            min="0"
                            step="0.01"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />

                        <v-text-field
                            v-model="movementForm.moved_at"
                            class="movement-field--date"
                            label="Дата"
                            type="date"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />

                        <v-text-field
                            v-model="movementForm.note"
                            class="movement-field--note"
                            label="Примечание"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />

                        <div class="movement-actions">
                            <v-btn
                                text="Отмена"
                                variant="text"
                                density="comfortable"
                                type="button"
                                @click="closeMovementForm"
                            />

                            <v-btn
                                :prepend-icon="movementForm.id ? 'mdi-content-save-outline' : 'mdi-plus'"
                                :text="movementForm.id ? 'Сохранить' : 'Добавить'"
                                color="#176b55"
                                variant="flat"
                                density="comfortable"
                                type="submit"
                                :loading="savingMovement"
                            />
                        </div>

                        <div v-if="movementError" class="movement-form__error">
                            {{ movementError }}
                        </div>
                    </form>
                </section>
            </v-expand-transition>

            <main class="warehouse-tabs">
                <v-tabs
                    v-model="activeTab"
                    class="warehouse-tabs__bar"
                    color="#176b55"
                    density="comfortable"
                    show-arrows
                >
                    <v-tab value="goods" class="warehouse-tab">
                        <v-icon icon="mdi-package-variant-closed" size="18" />
                        <span>Склад goods</span>
                        <small>{{ goodsSummary.rows }}</small>
                    </v-tab>

                    <v-tab
                        v-for="warehouse in warehouses"
                        :key="warehouse.id"
                        :value="warehouseTabValue(warehouse)"
                        class="warehouse-tab"
                    >
                        <span
                            class="warehouse-tab__status"
                            :class="{ 'warehouse-tab__status--inactive': !warehouse.is_active }"
                        />
                        <span>{{ warehouse.name }}</span>
                        <small>{{ warehouseStockRows(warehouse.id).length }}</small>
                    </v-tab>
                </v-tabs>

                <v-tabs-window v-model="activeTab" :touch="false" class="warehouse-tabs__window">
                    <v-tabs-window-item value="goods">
                        <GoodStockPanel
                            ref="goodsPanel"
                            :warehouses="warehouses"
                            :measures="measures"
                            @stats-change="handleGoodsStats"
                        />
                    </v-tabs-window-item>

                    <v-tabs-window-item
                        v-for="warehouse in warehouses"
                        :key="warehouse.id"
                        :value="warehouseTabValue(warehouse)"
                    >
                        <section class="warehouse-content">
                            <header class="warehouse-overview">
                                <div class="warehouse-overview__copy">
                                    <div class="warehouse-overview__eyebrow">
                                        <span>{{ warehouse.code || `Склад #${warehouse.id}` }}</span>
                                        <v-chip
                                            :color="warehouse.is_active ? 'success' : 'default'"
                                            size="x-small"
                                            variant="tonal"
                                        >
                                            {{ warehouse.is_active ? 'Активен' : 'Неактивен' }}
                                        </v-chip>
                                    </div>

                                    <h2>{{ warehouse.name }}</h2>

                                    <p v-if="warehouse.address">
                                        <v-icon icon="mdi-map-marker-outline" size="14" />
                                        {{ warehouse.address }}
                                    </p>

                                    <p v-if="warehouse.description" class="warehouse-overview__description">
                                        {{ warehouse.description }}
                                    </p>
                                </div>

                                <div class="warehouse-overview__side">
                                    <div class="warehouse-overview__stats">
                                        <div>
                                            <span>Позиций</span>
                                            <strong>{{ warehouseStockRows(warehouse.id).length }}</strong>
                                        </div>

                                        <div>
                                            <span>Стоимость</span>
                                            <strong>{{ formatMoney(warehouseStockValue(warehouse.id)) }}</strong>
                                        </div>
                                    </div>

                                    <div class="warehouse-overview__actions">
                                        <v-btn
                                            icon="mdi-pencil-outline"
                                            variant="text"
                                            density="compact"
                                            title="Редактировать склад"
                                            aria-label="Редактировать склад"
                                            @click="editWarehouse(warehouse)"
                                        />

                                        <v-btn
                                            icon="mdi-delete-outline"
                                            variant="text"
                                            density="compact"
                                            color="error"
                                            title="Удалить склад"
                                            aria-label="Удалить склад"
                                            @click="deleteWarehouse(warehouse)"
                                        />
                                    </div>
                                </div>
                            </header>

                            <div class="warehouse-tables">
                                <section class="warehouse-card">
                                    <div class="warehouse-card__heading">
                                        <div>
                                            <span>Текущее состояние</span>
                                            <h3>Остатки commodities</h3>
                                        </div>

                                        <strong>{{ warehouseStockRows(warehouse.id).length }}</strong>
                                    </div>

                                    <div class="stock-table-wrap">
                                        <table class="stock-table stock-table--balances">
                                            <colgroup>
                                                <col class="col-commodity">
                                                <col class="col-quantity">
                                                <col class="col-value">
                                                <col class="col-date">
                                            </colgroup>

                                            <thead>
                                                <tr>
                                                    <th>Commodity</th>
                                                    <th>Наличие</th>
                                                    <th>Стоимость</th>
                                                    <th>Последнее движение</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <tr v-if="loading && !warehouseStockRows(warehouse.id).length">
                                                    <td colspan="4" class="state-cell">Загрузка…</td>
                                                </tr>

                                                <tr v-else-if="!warehouseStockRows(warehouse.id).length">
                                                    <td colspan="4" class="state-cell">Остатков пока нет.</td>
                                                </tr>

                                                <tr
                                                    v-for="row in warehouseStockRows(warehouse.id)"
                                                    :key="`${row.warehouse_id}-${row.commodity_id}-${row.measure_id || 'n'}`"
                                                >
                                                    <td class="stock-commodity">
                                                        <v-avatar size="32" rounded="lg">
                                                            <v-img :src="row.commodity?.ava_url || logo" cover />
                                                        </v-avatar>

                                                        <a
                                                            v-if="commodityHref(row)"
                                                            :href="commodityHref(row)"
                                                        >
                                                            {{ row.commodity?.name || `#${row.commodity_id}` }}
                                                        </a>

                                                        <span v-else>
                                                            {{ row.commodity?.name || `#${row.commodity_id}` }}
                                                        </span>
                                                    </td>

                                                    <td class="qty-cell">
                                                        {{ formatQty(row.quantity) }}
                                                        <span>{{ row.measure?.name || '' }}</span>
                                                    </td>

                                                    <td class="money-cell">{{ formatMoney(row.stock_value) }}</td>
                                                    <td>{{ formatDate(row.last_moved_at) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section class="warehouse-card">
                                    <div class="warehouse-card__heading">
                                        <div>
                                            <span>История операций</span>
                                            <h3>Последние движения</h3>
                                        </div>

                                        <strong>{{ warehouseMovements(warehouse.id).length }}</strong>
                                    </div>

                                    <div class="stock-table-wrap">
                                        <table class="stock-table stock-table--movements">
                                            <colgroup>
                                                <col class="col-movement-date">
                                                <col class="col-operation">
                                                <col class="col-movement-commodity">
                                                <col class="col-movement-quantity">
                                                <col class="col-price">
                                                <col class="col-source">
                                                <col class="col-actions">
                                            </colgroup>

                                            <thead>
                                                <tr>
                                                    <th>Дата</th>
                                                    <th>Операция</th>
                                                    <th>Commodity</th>
                                                    <th>Количество</th>
                                                    <th>Цена</th>
                                                    <th>Источник / примечание</th>
                                                    <th></th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <tr v-if="loading && !warehouseMovements(warehouse.id).length">
                                                    <td colspan="7" class="state-cell">Загрузка…</td>
                                                </tr>

                                                <tr v-else-if="!warehouseMovements(warehouse.id).length">
                                                    <td colspan="7" class="state-cell">Движений пока нет.</td>
                                                </tr>

                                                <tr
                                                    v-for="movement in warehouseMovements(warehouse.id)"
                                                    :key="movement.id"
                                                >
                                                    <td>{{ formatDate(movement.moved_at) }}</td>

                                                    <td>
                                                        <span
                                                            class="movement-type"
                                                            :class="`movement-type--${movement.type}`"
                                                        >
                                                            {{ movementTypeTitle(movement.type) }}
                                                        </span>
                                                    </td>

                                                    <td class="movement-commodity">
                                                        {{ movement.commodity?.name || `#${movement.commodity_id}` }}
                                                    </td>

                                                    <td class="qty-cell">
                                                        {{ formatQty(movement.quantity_delta) }}
                                                        <span>{{ movement.measure?.name || '' }}</span>
                                                    </td>

                                                    <td class="money-cell">
                                                        {{ formatMoney(movement.unit_price) }}
                                                    </td>

                                                    <td>
                                                        {{
                                                            movement.source_type
                                                                ? movement.note || `${movement.source_type} #${movement.source_id}`
                                                                : movement.note || '—'
                                                        }}
                                                    </td>

                                                    <td>
                                                        <div class="row-actions">
                                                            <v-btn
                                                                icon="mdi-pencil-outline"
                                                                size="x-small"
                                                                variant="text"
                                                                :disabled="Boolean(movement.source_type)"
                                                                title="Редактировать"
                                                                @click="editMovement(movement)"
                                                            />

                                                            <v-btn
                                                                icon="mdi-delete-outline"
                                                                size="x-small"
                                                                variant="text"
                                                                color="error"
                                                                :disabled="Boolean(movement.source_type)"
                                                                title="Удалить"
                                                                @click="deleteMovement(movement)"
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>
                        </section>
                    </v-tabs-window-item>
                </v-tabs-window>
            </main>
        </div>

        <v-dialog v-model="warehouseDialog" max-width="680" scrollable>
            <v-card class="warehouse-dialog">
                <form @submit.prevent="saveWarehouse">
                    <v-card-title class="warehouse-dialog__title">
                        <div>
                            <span>{{ warehouseForm.id ? 'Настройки склада' : 'Новый склад' }}</span>
                            <small>
                                {{
                                    warehouseForm.id
                                        ? 'Измените параметры выбранного склада'
                                        : 'Добавьте ещё одно место хранения'
                                }}
                            </small>
                        </div>

                        <v-btn
                            icon="mdi-close"
                            variant="text"
                            density="compact"
                            type="button"
                            title="Закрыть"
                            aria-label="Закрыть"
                            @click="warehouseDialog = false"
                        />
                    </v-card-title>

                    <v-divider />

                    <v-card-text>
                        <v-alert
                            v-if="warehouseError"
                            type="error"
                            variant="tonal"
                            density="compact"
                            class="mb-4"
                        >
                            {{ warehouseError }}
                        </v-alert>

                        <div class="warehouse-dialog__grid">
                            <v-text-field
                                v-model="warehouseForm.name"
                                class="warehouse-dialog__name"
                                label="Название"
                                variant="outlined"
                                density="compact"
                                autofocus
                                hide-details
                            />

                            <v-text-field
                                v-model="warehouseForm.code"
                                label="Код"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />

                            <v-text-field
                                v-model.number="warehouseForm.sort_order"
                                label="Порядок"
                                type="number"
                                min="0"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />

                            <v-text-field
                                v-model="warehouseForm.address"
                                class="warehouse-dialog__wide"
                                label="Адрес"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />

                            <v-textarea
                                v-model="warehouseForm.description"
                                class="warehouse-dialog__wide"
                                label="Описание"
                                rows="3"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />

                            <v-switch
                                v-model="warehouseForm.is_active"
                                class="warehouse-dialog__wide"
                                label="Склад активен"
                                color="#17845f"
                                density="compact"
                                hide-details
                            />
                        </div>
                    </v-card-text>

                    <v-divider />

                    <v-card-actions class="warehouse-dialog__actions">
                        <v-btn
                            text="Отмена"
                            variant="text"
                            type="button"
                            @click="warehouseDialog = false"
                        />

                        <v-btn
                            :prepend-icon="warehouseForm.id ? 'mdi-content-save-outline' : 'mdi-plus'"
                            :text="warehouseForm.id ? 'Сохранить' : 'Создать склад'"
                            color="#176b55"
                            variant="flat"
                            type="submit"
                            :loading="savingWarehouse"
                        />
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.warehouses-page {
    align-self: stretch;
    width: 100%;
    min-height: calc(100vh - 48px);
    background:
        radial-gradient(circle at 100% 0, rgba(23, 107, 85, 0.08), transparent 28rem),
        #f3f0e8;
    color: #201a14;
}

.warehouses-shell {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
}

.warehouse-toolbar {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) auto auto;
    gap: 18px;
    align-items: center;
    padding: 12px 14px;
    border: 1px solid #b8ad92;
    border-radius: 12px;
    background: rgba(255, 250, 240, 0.96);
    box-shadow: 0 5px 18px rgba(74, 61, 35, 0.07);
}

.warehouse-toolbar__identity {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 0;
}

.warehouse-toolbar__icon {
    display: grid;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 10px;
    background: #176b55;
    color: #ffffff;
}

.warehouse-toolbar__eyebrow {
    display: block;
    color: #756b59;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    line-height: 1;
    text-transform: uppercase;
}

.warehouse-toolbar h1 {
    margin: 3px 0 0;
    font-size: 23px;
    font-weight: 900;
    line-height: 1;
}

.warehouse-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(100px, auto));
    gap: 1px;
    overflow: hidden;
    border: 1px solid #c8bea4;
    border-radius: 8px;
    background: #c8bea4;
}

.warehouse-kpis div {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 6px 10px;
    background: #edf2e6;
}

.warehouse-kpis span,
.warehouse-overview__stats span {
    color: #6b6252;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.warehouse-kpis strong {
    overflow: hidden;
    color: #176b55;
    font-size: 17px;
    line-height: 1.15;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.warehouse-toolbar__actions {
    display: flex;
    gap: 6px;
    align-items: center;
    justify-content: flex-end;
}

.movement-dropdown {
    padding: 12px;
    border: 1px solid #8aaa9c;
    border-radius: 12px;
    background: #f2fbf6;
    box-shadow: 0 8px 22px rgba(23, 107, 85, 0.09);
}

.movement-dropdown__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.movement-dropdown__heading div {
    display: flex;
    gap: 7px;
    align-items: baseline;
}

.movement-dropdown__heading span {
    color: #607067;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}

.movement-dropdown__heading strong {
    color: #176b55;
    font-size: 15px;
}

.movement-form {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 9px;
    align-items: start;
}

.movement-field--operation,
.movement-field--warehouse,
.movement-field--quantity,
.movement-field--measure,
.movement-field--price,
.movement-field--date {
    grid-column: span 2;
}

.movement-field--commodity {
    grid-column: span 4;
}

.movement-field--note {
    grid-column: span 6;
}

.movement-actions {
    display: flex;
    grid-column: span 2;
    gap: 4px;
    justify-content: flex-end;
}

.movement-form__error {
    grid-column: 1 / -1;
    color: #b42318;
    font-size: 11px;
    font-weight: 800;
}

.warehouse-tabs {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #b8ad92;
    border-radius: 12px;
    background: #fffdf8;
    box-shadow: 0 5px 18px rgba(74, 61, 35, 0.06);
}

.warehouse-tabs__bar {
    border-bottom: 1px solid #c8bea4;
    background: #ede7d9;
}

.warehouse-tab {
    min-width: 140px;
    max-width: 260px;
    padding-inline: 16px;
    text-transform: none;
}

.warehouse-tab :deep(.v-btn__content) {
    gap: 7px;
    min-width: 0;
}

.warehouse-tab span:not(.warehouse-tab__status) {
    overflow: hidden;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.warehouse-tab small {
    display: inline-grid;
    min-width: 22px;
    height: 20px;
    place-items: center;
    padding: 0 5px;
    border-radius: 10px;
    background: rgba(23, 107, 85, 0.12);
    color: #176b55;
    font-size: 10px;
    font-weight: 900;
}

.warehouse-tab__status {
    flex: 0 0 8px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #17845f;
    box-shadow: 0 0 0 3px rgba(23, 132, 95, 0.12);
}

.warehouse-tab__status--inactive {
    background: #8b8170;
    box-shadow: none;
}

.warehouse-tabs__window {
    min-width: 0;
}

.warehouse-content {
    display: grid;
    gap: 12px;
    padding: 12px;
}

.warehouse-overview {
    display: flex;
    gap: 20px;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border: 1px solid #d5cab1;
    border-radius: 10px;
    background: #fffaf0;
}

.warehouse-overview__copy {
    min-width: 0;
}

.warehouse-overview__eyebrow {
    display: flex;
    gap: 8px;
    align-items: center;
    color: #176b55;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.warehouse-overview h2 {
    margin: 3px 0 0;
    font-size: 20px;
    font-weight: 900;
    line-height: 1.15;
}

.warehouse-overview p {
    display: flex;
    gap: 4px;
    align-items: center;
    margin: 5px 0 0;
    color: #6b6252;
    font-size: 11px;
}

.warehouse-overview__description {
    max-width: 760px;
    white-space: normal;
}

.warehouse-overview__side,
.warehouse-overview__actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.warehouse-overview__stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(100px, 1fr));
    overflow: hidden;
    border: 1px solid #d5cab1;
    border-radius: 7px;
}

.warehouse-overview__stats div {
    display: flex;
    flex-direction: column;
    padding: 5px 9px;
}

.warehouse-overview__stats div + div {
    border-left: 1px solid #d5cab1;
}

.warehouse-overview__stats strong {
    color: #176b55;
    font-size: 15px;
}

.warehouse-tables {
    display: grid;
    grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.35fr);
    gap: 12px;
}

.warehouse-card {
    min-width: 0;
    padding: 10px;
    border: 1px solid #d5cab1;
    border-radius: 10px;
    background: #ffffff;
}

.warehouse-card__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.warehouse-card__heading span {
    display: block;
    color: #776d5b;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.warehouse-card__heading h3 {
    margin: 1px 0 0;
    font-size: 14px;
    font-weight: 900;
}

.warehouse-card__heading > strong {
    display: grid;
    min-width: 28px;
    height: 24px;
    place-items: center;
    padding: 0 6px;
    border-radius: 12px;
    background: #edf2e6;
    color: #176b55;
    font-size: 11px;
}

.stock-table-wrap {
    width: 100%;
    overflow: auto;
    border: 1px solid #d8cfb8;
    border-radius: 7px;
    background: #ffffff;
}

.stock-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 11px;
}

.stock-table--balances {
    min-width: 650px;
}

.stock-table--movements {
    min-width: 930px;
}

.stock-table th,
.stock-table td {
    overflow: hidden;
    padding: 5px 7px;
    border-right: 1px solid #e2dac5;
    border-bottom: 1px solid #e2dac5;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stock-table th:last-child,
.stock-table td:last-child {
    border-right: 0;
}

.stock-table tbody tr:last-child td {
    border-bottom: 0;
}

.stock-table thead th {
    background: #e1ded6;
    color: #2c2720;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.02em;
    text-align: left;
    text-transform: uppercase;
}

.stock-table tbody tr:nth-child(odd) {
    background: #fff8de;
}

.stock-table tbody tr:nth-child(even) {
    background: #ffffff;
}

.stock-table tbody tr:hover {
    background: #e8f1df;
}

.col-commodity {
    width: 46%;
}

.col-quantity {
    width: 18%;
}

.col-value {
    width: 16%;
}

.col-date {
    width: 20%;
}

.col-movement-date {
    width: 10%;
}

.col-operation {
    width: 12%;
}

.col-movement-commodity {
    width: 27%;
}

.col-movement-quantity {
    width: 13%;
}

.col-price {
    width: 11%;
}

.col-source {
    width: 22%;
}

.col-actions {
    width: 5%;
}

.stock-commodity {
    display: flex;
    gap: 8px;
    align-items: center;
}

.stock-commodity a {
    overflow: hidden;
    color: #1b4c8f;
    font-weight: 900;
    text-decoration: none;
    text-overflow: ellipsis;
}

.stock-commodity a:hover {
    text-decoration: underline;
}

.movement-commodity {
    color: #493d2f;
    font-weight: 800;
}

.qty-cell {
    color: #234b75;
    font-weight: 900;
    text-align: right;
}

.qty-cell span {
    color: #6b6252;
    font-size: 10px;
}

.money-cell {
    color: #14733f;
    font-weight: 900;
    text-align: right;
}

.movement-type {
    display: inline-flex;
    padding: 2px 6px;
    border-left: 4px solid #6b7280;
    background: #ffffff;
    font-weight: 900;
}

.movement-type--receipt,
.movement-type--check_purchase {
    border-left-color: #17845f;
}

.movement-type--write_off {
    border-left-color: #b42318;
}

.movement-type--adjustment {
    border-left-color: #7f5f00;
}

.row-actions {
    display: flex;
    justify-content: center;
}

.state-cell {
    padding: 20px !important;
    color: #756b59;
    text-align: center;
}

.warehouse-dialog {
    border-radius: 12px !important;
}

.warehouse-dialog__title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 18px 20px 14px;
}

.warehouse-dialog__title span,
.warehouse-dialog__title small {
    display: block;
}

.warehouse-dialog__title span {
    font-size: 20px;
    font-weight: 900;
}

.warehouse-dialog__title small {
    margin-top: 2px;
    color: #756b59;
    font-size: 11px;
    font-weight: 500;
}

.warehouse-dialog__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(100px, 0.35fr);
    gap: 12px;
}

.warehouse-dialog__name,
.warehouse-dialog__wide {
    grid-column: 1 / -1;
}

.warehouse-dialog__actions {
    justify-content: flex-end;
    padding: 12px 20px;
}

@media (max-width: 1400px) {
    .warehouse-toolbar {
        grid-template-columns: minmax(200px, 1fr) auto;
    }

    .warehouse-kpis {
        grid-row: 2;
        grid-column: 1 / -1;
    }

    .warehouse-tables {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .warehouses-shell {
        padding: 8px;
    }

    .warehouse-toolbar {
        grid-template-columns: 1fr;
        gap: 12px;
        border-radius: 8px;
    }

    .warehouse-kpis {
        grid-row: auto;
        grid-column: auto;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .warehouse-toolbar__actions {
        justify-content: flex-start;
    }

    .movement-form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .movement-form > * {
        grid-column: span 1;
    }

    .movement-field--commodity,
    .movement-field--note,
    .movement-form__error {
        grid-column: 1 / -1;
    }

    .movement-actions {
        grid-column: 1 / -1;
    }

    .warehouse-overview {
        align-items: flex-start;
        flex-direction: column;
    }

    .warehouse-overview__side {
        width: 100%;
        justify-content: space-between;
    }
}

@media (max-width: 560px) {
    .warehouse-toolbar__actions {
        display: grid;
        grid-template-columns: auto 1fr auto;
    }

    .warehouse-kpis div {
        padding-inline: 6px;
    }

    .warehouse-kpis strong {
        font-size: 14px;
    }

    .movement-form {
        grid-template-columns: 1fr;
    }

    .movement-form > *,
    .movement-field--commodity,
    .movement-field--note,
    .movement-actions {
        grid-column: 1;
    }

    .warehouse-content {
        padding: 8px;
    }

    .warehouse-overview__side {
        align-items: flex-end;
    }

    .warehouse-overview__stats {
        grid-template-columns: 1fr;
    }

    .warehouse-overview__stats div + div {
        border-top: 1px solid #d5cab1;
        border-left: 0;
    }

    .warehouse-dialog__grid {
        grid-template-columns: 1fr;
    }

    .warehouse-dialog__grid > * {
        grid-column: 1;
    }
}
</style>
