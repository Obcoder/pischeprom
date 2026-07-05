<script setup>
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const shifts = ref([])
const loading = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const editingId = ref(null)
let filterTimer = null

const filters = reactive({
    date_from: '',
    date_to: '',
    sort_by: 'date',
    sort_desc: true,
})

const meta = reactive({
    total_revenue: 0,
    total_orders: 0,
    count: 0,
})

const newShift = reactive({
    date: today(),
    orders_count: 0,
    revenue_amount: 0,
})

const editShift = reactive({
    date: '',
    orders_count: 0,
    revenue_amount: 0,
})

const sortIcon = computed(() => (filters.sort_desc ? 'mdi-arrow-down' : 'mdi-arrow-up'))

function today() {
    return new Date().toISOString().slice(0, 10)
}

function numeric(value) {
    const number = Number(value)

    return Number.isFinite(number) ? number : 0
}

function requestPayload(source) {
    return {
        date: source.date,
        orders_count: Math.max(0, Math.trunc(numeric(source.orders_count))),
        revenue_amount: numeric(source.revenue_amount),
    }
}

function formatInteger(value) {
    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: 0,
    }).format(Math.trunc(numeric(value)))
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(numeric(value))
}

function formatDate(value) {
    if (!value) {
        return '-'
    }

    const parsed = new Date(`${String(value).slice(0, 10)}T00:00:00`)

    return Number.isNaN(parsed.getTime())
        ? value
        : new Intl.DateTimeFormat('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
        }).format(parsed)
}

function resetNewShift() {
    newShift.date = today()
    newShift.orders_count = 0
    newShift.revenue_amount = 0
}

function startEdit(shift) {
    editingId.value = shift.id
    editShift.date = shift.date
    editShift.orders_count = shift.orders_count || 0
    editShift.revenue_amount = shift.revenue_amount
}

function cancelEdit() {
    editingId.value = null
    editShift.date = ''
    editShift.orders_count = 0
    editShift.revenue_amount = 0
}

function toggleSort(column) {
    if (filters.sort_by === column) {
        filters.sort_desc = !filters.sort_desc
    } else {
        filters.sort_by = column
        filters.sort_desc = true
    }

    loadShifts()
}

async function loadShifts() {
    loading.value = true

    try {
        const { data } = await axios.get('/api/taxi-shifts', {
            params: {
                date_from: filters.date_from || undefined,
                date_to: filters.date_to || undefined,
                sort_by: filters.sort_by,
                sort_desc: filters.sort_desc,
            },
        })

        shifts.value = data.data || []
        meta.total_revenue = data.meta?.total_revenue || 0
        meta.total_orders = data.meta?.total_orders || 0
        meta.count = data.meta?.count || shifts.value.length
    } catch (error) {
        console.error(error)
    } finally {
        loading.value = false
    }
}

async function createShift() {
    if (!newShift.date || saving.value) {
        return
    }

    saving.value = true

    try {
        await axios.post('/api/taxi-shifts', requestPayload(newShift))
        resetNewShift()
        await loadShifts()
    } catch (error) {
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function updateShift(shift) {
    if (!editingId.value || saving.value) {
        return
    }

    saving.value = true

    try {
        await axios.patch(`/api/taxi-shifts/${shift.id}`, requestPayload(editShift))
        cancelEdit()
        await loadShifts()
    } catch (error) {
        console.error(error)
    } finally {
        saving.value = false
    }
}

async function deleteShift(shift) {
    if (!window.confirm(`Удалить смену #${shift.id}?`)) {
        return
    }

    deletingId.value = shift.id

    try {
        await axios.delete(`/api/taxi-shifts/${shift.id}`)
        if (editingId.value === shift.id) {
            cancelEdit()
        }
        await loadShifts()
    } catch (error) {
        console.error(error)
    } finally {
        deletingId.value = null
    }
}

watch(
    () => [filters.date_from, filters.date_to],
    () => {
        clearTimeout(filterTimer)
        filterTimer = window.setTimeout(() => loadShifts(), 250)
    }
)

onMounted(() => {
    loadShifts()
})
</script>

<template>
    <Head title="Такси: смены" />

    <main class="taxi-shifts-page">
        <section class="taxi-toolbar">
            <div>
                <h1>Смены такси</h1>
                <p>{{ meta.count }} смен · {{ formatInteger(meta.total_orders) }} заказов · {{ formatMoney(meta.total_revenue) }}</p>
            </div>

            <div class="taxi-filters">
                <label>
                    <span>с</span>
                    <input v-model="filters.date_from" type="date">
                </label>
                <label>
                    <span>по</span>
                    <input v-model="filters.date_to" type="date">
                </label>
                <button type="button" title="Обновить" @click="loadShifts">
                    <v-icon icon="mdi-refresh" size="14" />
                </button>
            </div>
        </section>

        <section class="taxi-sheet-wrap" aria-label="Таблица смен такси">
            <table class="taxi-sheet">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th>
                            <button type="button" class="sort-button" @click="toggleSort('date')">
                                Дата
                                <v-icon v-if="filters.sort_by === 'date'" :icon="sortIcon" size="12" />
                            </button>
                        </th>
                        <th class="orders-cell">
                            <button type="button" class="sort-button" @click="toggleSort('orders_count')">
                                Заказы
                                <v-icon v-if="filters.sort_by === 'orders_count'" :icon="sortIcon" size="12" />
                            </button>
                        </th>
                        <th>
                            <button type="button" class="sort-button" @click="toggleSort('revenue_amount')">
                                Выручка
                                <v-icon v-if="filters.sort_by === 'revenue_amount'" :icon="sortIcon" size="12" />
                            </button>
                        </th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="new-row">
                        <td>new</td>
                        <td>
                            <input v-model="newShift.date" type="date">
                        </td>
                        <td class="orders-cell">
                            <input v-model="newShift.orders_count" type="number" min="0" step="1" inputmode="numeric">
                        </td>
                        <td>
                            <input v-model="newShift.revenue_amount" type="number" min="0" step="0.01" inputmode="decimal">
                        </td>
                        <td class="actions">
                            <button type="button" title="Добавить смену" :disabled="saving" @click="createShift">
                                <v-icon icon="mdi-plus" size="14" />
                            </button>
                        </td>
                    </tr>

                    <tr v-if="loading">
                        <td colspan="5" class="state-cell">Загрузка...</td>
                    </tr>

                    <tr v-else-if="!shifts.length">
                        <td colspan="5" class="state-cell">Смен пока нет.</td>
                    </tr>

                    <template v-else>
                        <tr
                            v-for="shift in shifts"
                            :key="shift.id"
                            :class="{ editing: editingId === shift.id }"
                        >
                            <td class="col-id">{{ shift.id }}</td>
                            <td>
                                <input v-if="editingId === shift.id" v-model="editShift.date" type="date">
                                <span v-else>{{ formatDate(shift.date) }}</span>
                            </td>
                            <td class="orders-cell">
                                <input
                                    v-if="editingId === shift.id"
                                    v-model="editShift.orders_count"
                                    type="number"
                                    min="0"
                                    step="1"
                                    inputmode="numeric"
                                >
                                <span v-else>{{ formatInteger(shift.orders_count) }}</span>
                            </td>
                            <td class="money-cell">
                                <input
                                    v-if="editingId === shift.id"
                                    v-model="editShift.revenue_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    inputmode="decimal"
                                >
                                <span v-else>{{ formatMoney(shift.revenue_amount) }}</span>
                            </td>
                            <td class="actions">
                                <template v-if="editingId === shift.id">
                                    <button type="button" title="Сохранить" :disabled="saving" @click="updateShift(shift)">
                                        <v-icon icon="mdi-content-save" size="14" />
                                    </button>
                                    <button type="button" title="Отменить" :disabled="saving" @click="cancelEdit">
                                        <v-icon icon="mdi-close" size="14" />
                                    </button>
                                </template>
                                <template v-else>
                                    <button type="button" title="Редактировать" @click="startEdit(shift)">
                                        <v-icon icon="mdi-pencil" size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Удалить"
                                        :disabled="deletingId === shift.id"
                                        @click="deleteShift(shift)"
                                    >
                                        <v-icon icon="mdi-trash-can-outline" size="14" />
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Итого</td>
                        <td class="orders-cell">{{ formatInteger(meta.total_orders) }}</td>
                        <td class="money-cell">{{ formatMoney(meta.total_revenue) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </section>
    </main>
</template>

<style scoped>
.taxi-shifts-page {
    min-height: 100vh;
    background: #f1c400;
    color: #000;
    padding: 6px;
}

.taxi-toolbar {
    align-items: end;
    background: #ffd900;
    border: 1px solid #000;
    color: #000;
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
    padding: 4px 6px;
}

.taxi-toolbar h1 {
    color: #000;
    font-size: 15px;
    font-weight: 900;
    line-height: 1;
    margin: 0;
}

.taxi-toolbar p {
    color: #000;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    margin: 3px 0 0;
}

.taxi-filters {
    align-items: center;
    display: flex;
    gap: 4px;
}

.taxi-filters label {
    align-items: center;
    color: #000;
    display: flex;
    gap: 3px;
    font-size: 10px;
    font-weight: 800;
}

.taxi-sheet-wrap {
    border: 1px solid #000;
    margin: 0 14px;
    overflow: auto;
}

.taxi-sheet {
    background: #ffe44a;
    border-collapse: collapse;
    color: #000;
    font-size: 11px;
    line-height: 1.1;
    min-width: 620px;
    table-layout: fixed;
    width: 100%;
}

.taxi-sheet th,
.taxi-sheet td {
    border: 1px solid #000;
    color: #000;
    height: 22px;
    padding: 1px 4px;
    vertical-align: middle;
}

.taxi-sheet th {
    background: #e0ad00;
    font-size: 10px;
    font-weight: 950;
    text-align: left;
}

.taxi-sheet tfoot td {
    background: #f4c400;
    font-weight: 950;
}

.taxi-sheet .new-row td,
.taxi-sheet tr.editing td {
    background: #fff06c;
}

.col-id {
    width: 46px;
}

.col-actions {
    width: 64px;
}

.orders-cell {
    text-align: right;
    width: 86px;
}

.money-cell {
    text-align: right;
}

.state-cell {
    font-size: 10px;
    font-weight: 800;
    text-align: center;
}

.sort-button,
.taxi-sheet button,
.taxi-filters button {
    align-items: center;
    background: transparent;
    border: 0;
    color: #000;
    cursor: pointer;
    display: inline-flex;
    font: inherit;
    font-weight: 950;
    gap: 2px;
    justify-content: center;
    min-height: 16px;
    padding: 0;
}

.actions {
    text-align: center;
    white-space: nowrap;
}

.actions button,
.taxi-filters button {
    border: 1px solid #000;
    height: 18px;
    margin: 0 1px;
    width: 20px;
}

.taxi-sheet button:disabled,
.taxi-filters button:disabled {
    cursor: default;
    opacity: 0.45;
}

input {
    background: #fff7a8;
    border: 0;
    color: #000;
    font: inherit;
    font-weight: 800;
    height: 18px;
    outline: 1px solid #000;
    padding: 0 3px;
    width: 100%;
}

input[type="date"] {
    min-width: 112px;
}

input[type="number"] {
    text-align: right;
}

@media (max-width: 720px) {
    .taxi-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .taxi-filters {
        flex-wrap: wrap;
    }

    .taxi-sheet-wrap {
        margin: 0 6px;
    }
}
</style>
