<script setup>
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { logo } from '@/Pages/Helpers/consts.js'

defineOptions({
    layout: VerwalterLayout,
})

const warehouses = ref([])
const stockRows = ref([])
const movements = ref([])
const commodities = ref([])
const measures = ref([])
const loading = ref(false)
const savingWarehouse = ref(false)
const savingMovement = ref(false)

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
        return '-'
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

async function loadAll() {
    loading.value = true

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
    } catch (error) {
        console.error('load warehouses error:', error)
    } finally {
        loading.value = false
    }
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

function editWarehouse(warehouse) {
    warehouseForm.id = warehouse.id
    warehouseForm.name = warehouse.name || ''
    warehouseForm.code = warehouse.code || ''
    warehouseForm.address = warehouse.address || ''
    warehouseForm.description = warehouse.description || ''
    warehouseForm.is_active = warehouse.is_active ?? true
    warehouseForm.sort_order = warehouse.sort_order ?? 500
}

async function saveWarehouse() {
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
        if (warehouseForm.id) {
            await axios.patch(route('warehouses.update', warehouseForm.id), payload)
        } else {
            await axios.post(route('warehouses.store'), payload)
        }

        resetWarehouseForm()
        await loadAll()
    } catch (error) {
        console.error('save warehouse error:', error)
    } finally {
        savingWarehouse.value = false
    }
}

async function deleteWarehouse(warehouse) {
    if (!confirm(`Удалить склад "${warehouse.name}"?`)) {
        return
    }

    try {
        await axios.delete(route('warehouses.destroy', warehouse.id))
        await loadAll()
    } catch (error) {
        console.error('delete warehouse error:', error)
    }
}

function resetMovementForm() {
    movementForm.id = null
    movementForm.warehouse_id = defaultWarehouseId()
    movementForm.commodity_id = null
    movementForm.measure_id = null
    movementForm.type = 'receipt'
    movementForm.quantity = 1
    movementForm.unit_price = 0
    movementForm.moved_at = today()
    movementForm.note = ''
}

function editMovement(movement) {
    if (movement.source_type) {
        return
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
}

async function saveMovement() {
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

        resetMovementForm()
        await loadAll()
    } catch (error) {
        console.error('save stock movement error:', error)
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
        console.error('delete movement error:', error)
    }
}

onMounted(() => loadAll())
</script>

<template>
    <v-container fluid class="warehouses-page pa-0">
        <Head title="Склады" />

        <div class="warehouses-shell">
            <header class="warehouse-toolbar">
                <div class="warehouse-toolbar__title">
                    <span>Склады</span>
                    <strong>{{ stats.warehouses }}</strong>
                </div>

                <div class="warehouse-kpis">
                    <div>
                        <span>Остатки</span>
                        <strong>{{ stats.rows }}</strong>
                    </div>
                    <div>
                        <span>Стоимость</span>
                        <strong>{{ formatMoney(stats.value) }}</strong>
                    </div>
                </div>

                <v-btn
                    icon="mdi-refresh"
                    variant="text"
                    density="compact"
                    :loading="loading"
                    title="Обновить"
                    @click="loadAll"
                />
            </header>

            <main class="warehouse-layout">
                <section class="warehouse-panel">
                    <div class="panel-title">
                        <span>Склад</span>
                        <v-btn
                            v-if="warehouseForm.id"
                            icon="mdi-close"
                            variant="text"
                            density="compact"
                            title="Сбросить"
                            @click="resetWarehouseForm"
                        />
                    </div>

                    <div class="warehouse-form">
                        <v-text-field v-model="warehouseForm.name" label="Название" variant="outlined" density="compact" hide-details />
                        <v-text-field v-model="warehouseForm.code" label="Код" variant="outlined" density="compact" hide-details />
                        <v-text-field v-model="warehouseForm.address" label="Адрес" variant="outlined" density="compact" hide-details />
                        <v-text-field v-model="warehouseForm.sort_order" label="Порядок" type="number" variant="outlined" density="compact" hide-details />
                        <v-switch v-model="warehouseForm.is_active" label="Active" color="#17845f" density="compact" hide-details />
                        <v-textarea v-model="warehouseForm.description" label="Описание" rows="2" variant="outlined" density="compact" hide-details />
                        <v-btn
                            :prepend-icon="warehouseForm.id ? 'mdi-content-save-outline' : 'mdi-plus'"
                            :text="warehouseForm.id ? 'Сохранить' : 'Добавить'"
                            color="#176b55"
                            variant="flat"
                            density="compact"
                            :loading="savingWarehouse"
                            @click="saveWarehouse"
                        />
                    </div>

                    <div class="warehouse-list">
                        <button
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            type="button"
                            class="warehouse-list-item"
                            @click="editWarehouse(warehouse)"
                        >
                            <span>
                                <strong>{{ warehouse.name }}</strong>
                                <small>{{ warehouse.code || `#${warehouse.id}` }}</small>
                            </span>
                            <v-icon :icon="warehouse.is_active ? 'mdi-check-circle-outline' : 'mdi-circle-outline'" size="18" />
                        </button>
                    </div>
                </section>

                <section class="stock-panel">
                    <div class="movement-form">
                        <v-select
                            v-model="movementForm.type"
                            :items="movementTypes"
                            item-title="title"
                            item-value="value"
                            label="Операция"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                        />
                        <v-select
                            v-model="movementForm.warehouse_id"
                            :items="warehouses"
                            item-title="name"
                            item-value="id"
                            label="Склад"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                        />
                        <v-autocomplete
                            v-model="movementForm.commodity_id"
                            :items="commodities"
                            item-title="name"
                            item-value="id"
                            label="Commodity"
                            variant="solo-filled"
                            density="compact"
                            hide-details
                        />
                        <v-text-field v-model="movementForm.quantity" label="Кол-во" type="number" variant="solo-filled" density="compact" hide-details />
                        <v-select
                            v-model="movementForm.measure_id"
                            :items="measures"
                            item-title="name"
                            item-value="id"
                            label="Мера"
                            variant="solo-filled"
                            density="compact"
                            clearable
                            hide-details
                        />
                        <v-text-field v-model="movementForm.unit_price" label="Цена" type="number" variant="solo-filled" density="compact" hide-details />
                        <v-text-field v-model="movementForm.moved_at" label="Дата" type="date" variant="solo-filled" density="compact" hide-details />
                        <v-text-field v-model="movementForm.note" label="Заметка" variant="solo-filled" density="compact" hide-details />
                        <div class="movement-actions">
                            <v-btn
                                v-if="movementForm.id"
                                icon="mdi-close"
                                variant="text"
                                density="compact"
                                title="Сбросить"
                                @click="resetMovementForm"
                            />
                            <v-btn
                                :prepend-icon="movementForm.id ? 'mdi-content-save-outline' : 'mdi-plus'"
                                :text="movementForm.id ? 'Сохранить' : 'Движение'"
                                color="#176b55"
                                variant="flat"
                                density="compact"
                                :loading="savingMovement"
                                @click="saveMovement"
                            />
                        </div>
                    </div>

                    <div class="stock-table-wrap">
                        <table class="stock-table">
                            <thead>
                                <tr>
                                    <th>Склад</th>
                                    <th>Commodity</th>
                                    <th>Наличие</th>
                                    <th>Стоимость</th>
                                    <th>Последнее</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!stockRows.length">
                                    <td colspan="5" class="state-cell">Остатков пока нет.</td>
                                </tr>
                                <tr v-for="row in stockRows" :key="`${row.warehouse_id}-${row.commodity_id}-${row.measure_id || 'n'}`">
                                    <td>{{ row.warehouse?.name || '-' }}</td>
                                    <td class="stock-commodity">
                                        <v-avatar size="30" rounded="lg">
                                            <v-img :src="row.commodity?.ava_url || logo" cover />
                                        </v-avatar>
                                        <a v-if="commodityHref(row)" :href="commodityHref(row)">{{ row.commodity?.name || `#${row.commodity_id}` }}</a>
                                        <span v-else>{{ row.commodity?.name || `#${row.commodity_id}` }}</span>
                                    </td>
                                    <td class="qty-cell">{{ formatQty(row.quantity) }} <span>{{ row.measure?.name || '' }}</span></td>
                                    <td class="money-cell">{{ formatMoney(row.stock_value) }}</td>
                                    <td>{{ formatDate(row.last_moved_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="stock-table-wrap">
                        <table class="stock-table movement-table">
                            <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Операция</th>
                                    <th>Склад</th>
                                    <th>Commodity</th>
                                    <th>Кол-во</th>
                                    <th>Цена</th>
                                    <th>Источник</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!movements.length">
                                    <td colspan="8" class="state-cell">Движений пока нет.</td>
                                </tr>
                                <tr v-for="movement in movements" :key="movement.id">
                                    <td>{{ formatDate(movement.moved_at) }}</td>
                                    <td>
                                        <span class="movement-type" :class="`movement-type--${movement.type}`">
                                            {{ movementTypeTitle(movement.type) }}
                                        </span>
                                    </td>
                                    <td>{{ movement.warehouse?.name || '-' }}</td>
                                    <td>{{ movement.commodity?.name || `#${movement.commodity_id}` }}</td>
                                    <td class="qty-cell">{{ formatQty(movement.quantity_delta) }} <span>{{ movement.measure?.name || '' }}</span></td>
                                    <td class="money-cell">{{ formatMoney(movement.unit_price) }}</td>
                                    <td>{{ movement.source_type ? movement.note || `${movement.source_type} #${movement.source_id}` : movement.note || '-' }}</td>
                                    <td>
                                        <div class="row-actions">
                                            <v-btn
                                                icon="mdi-pencil-outline"
                                                size="small"
                                                variant="text"
                                                :disabled="Boolean(movement.source_type)"
                                                title="Редактировать"
                                                @click="editMovement(movement)"
                                            />
                                            <v-btn
                                                icon="mdi-delete-outline"
                                                size="small"
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
            </main>
        </div>
    </v-container>
</template>

<style scoped>
.warehouses-page {
    align-self: stretch;
    width: 100%;
    min-height: calc(100vh - 48px);
    background: #f3f0e8;
    color: #201a14;
}

.warehouses-shell {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
}

.warehouse-toolbar {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) auto auto;
    gap: 12px;
    align-items: center;
    padding: 8px 10px;
    border: 1px solid #b8ad92;
    background: #fffaf0;
}

.warehouse-toolbar__title {
    display: flex;
    align-items: baseline;
    gap: 8px;
    font-size: 22px;
    font-weight: 900;
}

.warehouse-toolbar__title strong,
.warehouse-kpis strong {
    color: #176b55;
}

.warehouse-kpis {
    display: grid;
    grid-template-columns: repeat(2, minmax(100px, 1fr));
    gap: 1px;
    overflow: hidden;
    border: 1px solid #c8bea4;
}

.warehouse-kpis div {
    display: flex;
    flex-direction: column;
    padding: 4px 8px;
    background: #e7eddc;
}

.warehouse-kpis span {
    color: #6b6252;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

.warehouse-layout {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr);
    gap: 10px;
}

.warehouse-panel,
.stock-panel {
    min-width: 0;
}

.warehouse-panel {
    padding: 8px;
    border: 1px solid #b8ad92;
    background: #fffaf0;
}

.panel-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    font-weight: 900;
}

.warehouse-form,
.movement-form {
    display: grid;
    gap: 8px;
}

.warehouse-list {
    display: grid;
    gap: 4px;
    margin-top: 10px;
}

.warehouse-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #d5cab1;
    background: #ffffff;
    color: inherit;
    text-align: left;
}

.warehouse-list-item strong,
.warehouse-list-item small {
    display: block;
}

.warehouse-list-item small {
    color: #6b6252;
    font-size: 11px;
}

.movement-form {
    grid-template-columns: 120px 150px minmax(220px, 1fr) 86px 120px 96px 142px minmax(160px, 1fr) auto;
    align-items: end;
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #b8ad92;
    background: #f7f0df;
}

.movement-actions,
.row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 4px;
}

.stock-table-wrap {
    width: 100%;
    margin-bottom: 10px;
    overflow: auto;
    border: 1px solid #b8ad92;
    background: #ffffff;
}

.stock-table {
    width: 100%;
    min-width: 960px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 12px;
}

.stock-table th,
.stock-table td {
    overflow: hidden;
    padding: 4px 6px;
    border: 1px solid #d8cfb8;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stock-table thead th {
    background: #d9d9d9;
    color: #1f1b16;
    font-weight: 900;
    text-align: left;
}

.stock-table tbody tr:nth-child(odd) {
    background: #fff4cc;
}

.stock-table tbody tr:nth-child(even) {
    background: #ffffff;
}

.stock-table tbody tr:hover {
    background: #e8f1df;
}

.stock-commodity {
    display: flex;
    gap: 8px;
    align-items: center;
}

.stock-commodity a {
    color: #1b4c8f;
    font-weight: 900;
    text-decoration: none;
}

.stock-commodity a:hover {
    text-decoration: underline;
}

.qty-cell {
    color: #234b75;
    font-weight: 900;
    text-align: right;
}

.qty-cell span {
    color: #6b6252;
    font-size: 11px;
}

.money-cell {
    background: #ecf8e7;
    color: #10913d;
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

.state-cell {
    padding: 14px !important;
    color: #756b59;
    text-align: center;
}

@media (max-width: 1200px) {
    .warehouse-layout,
    .warehouse-toolbar {
        grid-template-columns: 1fr;
    }

    .movement-form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
