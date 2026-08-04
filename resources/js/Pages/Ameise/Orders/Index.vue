<script setup>
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import { useHead } from '@unhead/vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'

import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

defineProps({
    permissions: {
        type: Object,
        default: () => ({
            view: false,
            create: false,
            edit: false,
            delete: false,
        }),
    },
})

const orders = ref([])
const loading = ref(false)
const errorMessage = ref('')
const options = reactive({
    statuses: [],
    entities: [],
    buildings: [],
    goods: [],
})
const meta = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
})
const filters = reactive({
    search: '',
    status_id: null,
    entity_id: null,
    building_id: null,
    good_id: null,
    date_from: '',
    date_to: '',
    total_from: '',
    total_to: '',
    sort_by: 'submitted_at',
    sort_direction: 'desc',
    page: 1,
    per_page: 25,
})

const headers = [
    { title: 'Заказ', key: 'number' },
    { title: 'Статус', key: 'status' },
    { title: 'Entity', key: 'entity' },
    { title: 'Товары', key: 'items_count' },
    { title: 'Логистика', key: null },
    { title: 'Сумма', key: 'total_amount' },
    { title: 'Создан', key: 'submitted_at' },
]

const hasActiveFilters = computed(() => [
    filters.search,
    filters.status_id,
    filters.entity_id,
    filters.building_id,
    filters.good_id,
    filters.date_from,
    filters.date_to,
    filters.total_from,
    filters.total_to,
].some((value) => value !== null && value !== ''))

const sortIcon = computed(() => filters.sort_direction === 'asc'
    ? 'mdi-chevron-up'
    : 'mdi-chevron-down')

useHead({
    title: 'Ameise — заказы',
})

async function fetchOptions() {
    try {
        const { data } = await axios.get('/api/orders/options')
        options.statuses = data.statuses || []
        options.entities = data.entities || []
        options.buildings = data.buildings || []
        options.goods = data.goods || []
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Не удалось загрузить справочники заказов.'
    }
}

async function fetchOrders() {
    loading.value = true
    errorMessage.value = ''

    try {
        const { data } = await axios.get('/api/orders', {
            params: cleanParams(filters),
        })

        orders.value = data.data || []
        Object.assign(meta, data.meta || {})
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Не удалось загрузить заказы.'
    } finally {
        loading.value = false
    }
}

function cleanParams(source) {
    return Object.fromEntries(
        Object.entries(source).filter(([, value]) => value !== '' && value !== null),
    )
}

function applyFilters() {
    filters.page = 1
    fetchOrders()
}

function resetFilters() {
    Object.assign(filters, {
        search: '',
        status_id: null,
        entity_id: null,
        building_id: null,
        good_id: null,
        date_from: '',
        date_to: '',
        total_from: '',
        total_to: '',
        sort_by: 'submitted_at',
        sort_direction: 'desc',
        page: 1,
        per_page: 25,
    })
    fetchOrders()
}

function toggleSort(key) {
    if (!key) {
        return
    }

    if (filters.sort_by === key) {
        filters.sort_direction = filters.sort_direction === 'asc' ? 'desc' : 'asc'
    } else {
        filters.sort_by = key
        filters.sort_direction = key === 'number' || key === 'entity' || key === 'status'
            ? 'asc'
            : 'desc'
    }

    filters.page = 1
    fetchOrders()
}

function goToPage(page) {
    const target = Math.min(Math.max(page, 1), meta.last_page || 1)

    if (target === meta.current_page) {
        return
    }

    filters.page = target
    fetchOrders()
}

function orderUrl(order) {
    try {
        return route('Ameise.orders.show', order.id)
    } catch (error) {
        return `/Ameise/orders/${order.id}`
    }
}

function goodUrl(good) {
    if (!good?.id) {
        return '#'
    }

    try {
        return route('Ameise.good.show', good.id)
    } catch (error) {
        return `/Ameise/goods/${good.id}`
    }
}

function openOrder(order) {
    router.visit(orderUrl(order))
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    return Number.isNaN(date.getTime())
        ? '—'
        : new Intl.DateTimeFormat('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date)
}

function formatMoney(value, currency = 'RUB') {
    const amount = Number(value)

    if (!Number.isFinite(amount)) {
        return '—'
    }

    return `${amount.toLocaleString('ru-RU', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })} ${currency === 'RUB' ? '₽' : currency}`
}

function statusStyle(order) {
    return {
        '--status-color': order.status?.color || '#64748b',
    }
}

function buildingsLabel(order) {
    const buildings = order.buildings || []

    if (!buildings.length) {
        return 'Не задано'
    }

    return buildings.map((building) => building.address).join(' · ')
}

const debouncedSearch = useDebounceFn(() => {
    filters.page = 1
    fetchOrders()
}, 350)

watch(() => filters.search, debouncedSearch)
watch(() => filters.per_page, applyFilters)

onMounted(async () => {
    await Promise.all([
        fetchOptions(),
        fetchOrders(),
    ])
})
</script>

<template>
    <main class="orders-page">
        <header class="orders-page__header">
            <div>
                <div class="orders-page__eyebrow">Control panel</div>
                <h1>Заказы</h1>
                <p>{{ meta.total }} записей · состав, статусы и логистика</p>
            </div>

            <Link
                v-if="permissions.create"
                :href="route('Ameise.orders.create')"
                class="orders-page__create"
            >
                <v-icon icon="mdi-plus" size="17" />
                Новый заказ
            </Link>
        </header>

        <section class="orders-filters" aria-label="Фильтры заказов">
            <label class="orders-filter orders-filter--search">
                <span>Поиск</span>
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Номер, Entity, товар, адрес"
                >
            </label>

            <label class="orders-filter">
                <span>Статус</span>
                <select v-model="filters.status_id">
                    <option :value="null">Все</option>
                    <option v-for="status in options.statuses" :key="status.id" :value="status.id">
                        {{ status.name }}
                    </option>
                </select>
            </label>

            <label class="orders-filter">
                <span>Entity</span>
                <select v-model="filters.entity_id">
                    <option :value="null">Все</option>
                    <option v-for="entity in options.entities" :key="entity.id" :value="entity.id">
                        {{ entity.name }}
                    </option>
                </select>
            </label>

            <label class="orders-filter">
                <span>Building</span>
                <select v-model="filters.building_id">
                    <option :value="null">Все</option>
                    <option v-for="building in options.buildings" :key="building.id" :value="building.id">
                        {{ building.address }}
                    </option>
                </select>
            </label>

            <label class="orders-filter">
                <span>Good</span>
                <select v-model="filters.good_id">
                    <option :value="null">Все</option>
                    <option v-for="good in options.goods" :key="good.id" :value="good.id">
                        {{ good.name }}
                    </option>
                </select>
            </label>

            <label class="orders-filter">
                <span>С даты</span>
                <input v-model="filters.date_from" type="date">
            </label>

            <label class="orders-filter">
                <span>По дату</span>
                <input v-model="filters.date_to" type="date">
            </label>

            <label class="orders-filter">
                <span>Сумма от</span>
                <input v-model="filters.total_from" type="number" min="0" step="0.01">
            </label>

            <label class="orders-filter">
                <span>Сумма до</span>
                <input v-model="filters.total_to" type="number" min="0" step="0.01">
            </label>

            <div class="orders-filters__actions">
                <button type="button" class="is-primary" @click="applyFilters">
                    Применить
                </button>
                <button v-if="hasActiveFilters" type="button" @click="resetFilters">
                    Сбросить
                </button>
            </div>
        </section>

        <v-alert
            v-if="errorMessage"
            type="error"
            density="compact"
            variant="tonal"
            class="mb-3"
        >
            {{ errorMessage }}
        </v-alert>

        <section class="orders-ledger" :class="{ 'is-loading': loading }">
            <v-progress-linear v-if="loading" indeterminate color="#7f1d1d" height="2" />

            <div class="orders-ledger__scroll">
                <table>
                    <thead>
                        <tr>
                            <th v-for="header in headers" :key="header.title">
                                <button
                                    v-if="header.key"
                                    type="button"
                                    @click="toggleSort(header.key)"
                                >
                                    {{ header.title }}
                                    <v-icon
                                        v-if="filters.sort_by === header.key"
                                        :icon="sortIcon"
                                        size="14"
                                    />
                                </button>
                                <span v-else>{{ header.title }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody v-if="orders.length">
                        <tr
                            v-for="order in orders"
                            :key="order.id"
                            tabindex="0"
                            @click="openOrder(order)"
                            @keydown.enter="openOrder(order)"
                        >
                            <td>
                                <strong class="orders-ledger__number">{{ order.number }}</strong>
                                <small>#{{ order.id }}</small>
                            </td>
                            <td>
                                <span class="orders-ledger__status" :style="statusStyle(order)">
                                    {{ order.status?.name || '—' }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ order.entity?.name || 'Без Entity' }}</strong>
                                <small v-if="order.entity?.INN">ИНН {{ order.entity.INN }}</small>
                            </td>
                            <td>
                                <div class="orders-ledger__goods">
                                    <Link
                                        v-for="item in (order.items || []).slice(0, 2)"
                                        :key="item.id"
                                        :href="goodUrl(item.good)"
                                        @click.stop
                                    >
                                        {{ item.good_name }} × {{ item.quantity }}
                                    </Link>
                                    <small v-if="(order.items || []).length > 2">
                                        + ещё {{ order.items.length - 2 }}
                                    </small>
                                </div>
                            </td>
                            <td>
                                <span class="orders-ledger__building" :title="buildingsLabel(order)">
                                    {{ buildingsLabel(order) }}
                                </span>
                            </td>
                            <td class="orders-ledger__money">
                                {{ formatMoney(order.total_amount, order.currency_code) }}
                            </td>
                            <td class="orders-ledger__date">
                                {{ formatDate(order.submitted_at) }}
                            </td>
                        </tr>
                    </tbody>

                    <tbody v-else-if="!loading">
                        <tr>
                            <td colspan="7" class="orders-ledger__empty">
                                Заказы по выбранным условиям не найдены.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="orders-pagination">
                <label>
                    <span>Строк</span>
                    <select v-model="filters.per_page">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </label>

                <div>
                    <button
                        type="button"
                        :disabled="meta.current_page <= 1"
                        @click="goToPage(meta.current_page - 1)"
                    >
                        <v-icon icon="mdi-chevron-left" size="17" />
                    </button>
                    <span>{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button
                        type="button"
                        :disabled="meta.current_page >= meta.last_page"
                        @click="goToPage(meta.current_page + 1)"
                    >
                        <v-icon icon="mdi-chevron-right" size="17" />
                    </button>
                </div>
            </footer>
        </section>
    </main>
</template>

<style scoped>
.orders-page {
    width: 100%;
    min-height: calc(100vh - 48px);
    padding: 18px;
    background: #f4f5f7;
    color: #252a31;
}

.orders-page__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 14px;
}

.orders-page__eyebrow {
    color: #8f1111;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.orders-page h1 {
    margin: 2px 0 0;
    font-size: 25px;
    font-weight: 950;
    letter-spacing: -0.04em;
}

.orders-page__header p {
    margin: 3px 0 0;
    color: #747d87;
    font-size: 12px;
}

.orders-page__create {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 13px;
    border-radius: 7px;
    background: #7f1d1d;
    color: #fff;
    font-size: 12px;
    font-weight: 900;
    text-decoration: none;
}

.orders-filters {
    display: grid;
    grid-template-columns: minmax(220px, 1.6fr) repeat(4, minmax(130px, 1fr));
    gap: 8px;
    margin-bottom: 12px;
    padding: 12px;
    border: 1px solid #d9dde2;
    border-radius: 8px;
    background: #fff;
}

.orders-filter {
    display: grid;
    gap: 4px;
}

.orders-filter span,
.orders-pagination label span {
    color: #747d87;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.orders-filter input,
.orders-filter select,
.orders-pagination select {
    min-width: 0;
    height: 32px;
    padding: 0 8px;
    border: 1px solid #cfd4da;
    border-radius: 5px;
    background: #fff;
    color: #2c3239;
    font-size: 11px;
}

.orders-filters__actions {
    display: flex;
    align-items: end;
    gap: 6px;
}

.orders-filters__actions button {
    height: 32px;
    padding: 0 10px;
    border: 1px solid #cfd4da;
    border-radius: 5px;
    background: #fff;
    color: #434a52;
    font-size: 10px;
    font-weight: 900;
}

.orders-filters__actions .is-primary {
    border-color: #7f1d1d;
    background: #7f1d1d;
    color: #fff;
}

.orders-ledger {
    overflow: hidden;
    border: 1px solid #d3d8de;
    border-radius: 8px;
    background: #fff;
}

.orders-ledger__scroll {
    overflow: auto;
}

.orders-ledger table {
    width: 100%;
    min-width: 1080px;
    border-collapse: collapse;
    table-layout: fixed;
}

.orders-ledger th,
.orders-ledger td {
    padding: 8px 10px;
    border-right: 1px solid #e2e5e9;
    border-bottom: 1px solid #dfe3e7;
    text-align: left;
    vertical-align: middle;
}

.orders-ledger th:last-child,
.orders-ledger td:last-child {
    border-right: 0;
}

.orders-ledger th {
    height: 34px;
    background: #eef0f2;
    color: #626b76;
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.orders-ledger th button {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    color: inherit;
    font: inherit;
    letter-spacing: inherit;
    text-transform: inherit;
}

.orders-ledger tbody tr {
    cursor: pointer;
}

.orders-ledger tbody tr:hover,
.orders-ledger tbody tr:focus {
    outline: none;
    background: #fff8f2;
}

.orders-ledger td {
    height: 54px;
    font-size: 11px;
}

.orders-ledger td:nth-child(1) { width: 145px; }
.orders-ledger td:nth-child(2) { width: 125px; }
.orders-ledger td:nth-child(3) { width: 190px; }
.orders-ledger td:nth-child(4) { width: 260px; }
.orders-ledger td:nth-child(5) { width: 230px; }
.orders-ledger td:nth-child(6) { width: 125px; }
.orders-ledger td:nth-child(7) { width: 120px; }

.orders-ledger td > strong,
.orders-ledger td > small {
    display: block;
}

.orders-ledger td > small,
.orders-ledger__goods small {
    margin-top: 2px;
    color: #8a929c;
    font-size: 9px;
}

.orders-ledger__number,
.orders-ledger__money,
.orders-ledger__date {
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
}

.orders-ledger__status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 850;
}

.orders-ledger__status::before {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--status-color);
    content: "";
}

.orders-ledger__goods {
    display: grid;
    gap: 2px;
}

.orders-ledger__goods a {
    overflow: hidden;
    color: #7f1d1d;
    font-weight: 800;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.orders-ledger__goods a:hover {
    text-decoration: underline;
}

.orders-ledger__building {
    display: -webkit-box;
    overflow: hidden;
    color: #5e6771;
    line-height: 1.35;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.orders-ledger__money {
    color: #185c4b;
    font-weight: 900;
}

.orders-ledger__date {
    color: #68717b;
    font-size: 10px;
}

.orders-ledger__empty {
    height: 120px !important;
    color: #7a838d;
    text-align: center !important;
}

.orders-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 48px;
    padding: 7px 10px;
}

.orders-pagination label,
.orders-pagination div {
    display: flex;
    align-items: center;
    gap: 7px;
}

.orders-pagination select {
    width: 70px;
}

.orders-pagination button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 1px solid #ccd2d8;
    border-radius: 5px;
    color: #38414a;
}

.orders-pagination button:disabled {
    opacity: 0.35;
}

.orders-pagination div span {
    min-width: 64px;
    color: #626b76;
    font-family: "JetBrains Mono", monospace;
    font-size: 10px;
    text-align: center;
}

@media (max-width: 1100px) {
    .orders-filters {
        grid-template-columns: repeat(3, minmax(140px, 1fr));
    }

    .orders-filter--search {
        grid-column: span 2;
    }
}

@media (max-width: 700px) {
    .orders-page {
        padding: 10px;
    }

    .orders-page__header {
        align-items: flex-start;
        flex-direction: column;
    }

    .orders-filters {
        grid-template-columns: 1fr;
    }

    .orders-filter--search {
        grid-column: auto;
    }
}
</style>
