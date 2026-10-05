<script setup>
import { buildingApartmentLabel } from '@/utils/buildingApartments'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import { useHead } from '@unhead/vue'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import OrderStatusesDialog from '@/Components/Orders/OrderStatusesDialog.vue'
import OrderDetailsDialog from '@/Components/Orders/OrderDetailsDialog.vue'

defineOptions({ layout: VerwalterLayout })
const props = defineProps({
    permissions: {
        type: Object,
        default: () => ({ view: false, create: false, edit: false, delete: false }),
    },
})

const orders = ref([])
const loading = ref(false)
const errorMessage = ref('')
const optionsError = ref('')
const filterMenuOpen = ref(false)
const statusesOpen = ref(false)
const orderDetailsOpen = ref(false)
const selectedOrderId = ref(null)
const orderToDelete = ref(null)
const deleting = ref(false)
const deleteError = ref('')
const ledgerScroll = ref(null)
const options = reactive({ statuses: [], entities: [], buildings: [], goods: [] })
const meta = reactive({ current_page: 1, last_page: 1, per_page: 100, total: 0 })
const emptyFilters = () => ({
    status_id: null, entity_id: null, building_id: null, good_id: null,
    date_from: '', date_to: '', delivery_date: '', delivery_unscheduled: false,
    total_from: '', total_to: '',
})
const filters = reactive({
    search: '', ...emptyFilters(), sort_by: 'submitted_at', sort_direction: 'desc', page: 1, per_page: 100,
})
const draftFilters = reactive(emptyFilters())
let requestController = null
let disposed = false

const headers = [
    { title: 'Заказ', key: 'number' },
    { title: 'Статус', key: 'status' },
    { title: 'Контрагент', key: 'entity' },
    { title: 'Состав заказа', key: 'items_count' },
    { title: 'Сумма / вес', key: 'total_amount' },
    { title: 'Создан', key: 'submitted_at' },
    { title: 'Доставка', key: 'delivery_date' },
]
const activeFilters = computed(() => {
    const selected = (items, id, field = 'name') => items.find(item => String(item.id) === String(id))?.[field] || `#${id}`
    const labels = {
        status_id: () => selected(options.statuses, filters.status_id),
        entity_id: () => selected(options.entities, filters.entity_id),
        building_id: () => selected(options.buildings, filters.building_id, 'address'),
        good_id: () => selected(options.goods, filters.good_id),
        date_from: () => `Создан с ${formatDeliveryDate(filters.date_from)}`,
        date_to: () => `Создан по ${formatDeliveryDate(filters.date_to)}`,
        delivery_date: () => `Доставка ${formatDeliveryDate(filters.delivery_date)}`,
        delivery_unscheduled: () => 'Без даты доставки',
        total_from: () => `Сумма от ${filters.total_from}`,
        total_to: () => `Сумма до ${filters.total_to}`,
    }
    return Object.entries(labels)
        .filter(([key]) => filters[key] !== '' && filters[key] !== null && filters[key] !== false)
        .map(([key, label]) => ({ key, label: label() }))
})
const hasActiveFilters = computed(() => Boolean(filters.search || activeFilters.value.length))
const pageTotals = computed(() => {
    const totals = new Map()
    for (const order of orders.value) {
        const code = order.currency_code || 'RUB'
        totals.set(code, (totals.get(code) || 0) + (Number(order.total_amount) || 0))
    }
    return [...totals].map(([code, total]) => formatMoney(total, code)).join(' · ')
})
const pageRange = computed(() => {
    if (!meta.total) return '0 заказов'
    const start = (meta.current_page - 1) * meta.per_page + 1
    return `${start}–${Math.min(start + orders.value.length - 1, meta.total)} из ${meta.total}`
})
const sortIcon = computed(() => filters.sort_direction === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down')
useHead({ title: 'Ameise — заказы' })

async function fetchOptions() {
    optionsError.value = ''
    try {
        const { data } = await axios.get('/api/orders/options')
        if (disposed) return
        for (const key of Object.keys(options)) options[key] = data[key] || []
    } catch {
        if (!disposed) optionsError.value = 'Не удалось загрузить справочники заказов.'
    }
}

async function fetchOrders() {
    if (disposed) return
    requestController?.abort()
    const controller = new AbortController()
    requestController = controller
    loading.value = true
    errorMessage.value = ''
    try {
        const { data } = await axios.get('/api/orders', {
            params: cleanParams(filters), signal: controller.signal,
        })
        if (disposed || controller.signal.aborted) return
        orders.value = data.data || []
        Object.assign(meta, data.meta || {})
        if (meta.current_page > meta.last_page) {
            filters.page = Math.max(meta.last_page, 1)
            await fetchOrders()
            return
        }
        if (ledgerScroll.value) ledgerScroll.value.scrollTop = 0
    } catch (error) {
        if (!disposed && !controller.signal.aborted && !axios.isCancel(error)) {
            errorMessage.value = 'Не удалось загрузить заказы.'
        }
    } finally {
        if (!disposed && !controller.signal.aborted) loading.value = false
    }
}

function cleanParams(source) {
    return Object.fromEntries(Object.entries(source)
        .filter(([, value]) => value !== '' && value !== null && value !== false)
        .map(([key, value]) => [key, value === true ? 1 : value]))
}
function applyFilters() {
    Object.assign(filters, draftFilters, { page: 1 })
    filterMenuOpen.value = false
    fetchOrders()
}
function resetFilters() {
    Object.assign(filters, emptyFilters(), { search: '', page: 1 })
    Object.assign(draftFilters, emptyFilters())
    filterMenuOpen.value = false
    fetchOrders()
}
function removeFilter(key) {
    filters[key] = emptyFilters()[key]
    filters.page = 1
    fetchOrders()
}
function toggleSort(key) {
    if (filters.sort_by === key) filters.sort_direction = filters.sort_direction === 'asc' ? 'desc' : 'asc'
    else {
        filters.sort_by = key
        filters.sort_direction = ['number', 'entity', 'status'].includes(key) ? 'asc' : 'desc'
    }
    filters.page = 1
    fetchOrders()
}
function goToPage(page) {
    const target = Math.min(Math.max(page, 1), meta.last_page || 1)
    if (target === meta.current_page) return
    filters.page = target
    fetchOrders()
}
function goodUrl(good) {
    if (!good?.id) return '#'
    try { return route('Ameise.good.show', good.id) }
    catch { return `/Ameise/goods/${good.id}` }
}
function openOrder(order) {
    selectedOrderId.value = order.id
    orderDetailsOpen.value = true
}
function canDeleteOrder(order) {
    return Boolean(props.permissions.delete && order?.id && order.permissions?.delete !== false && !order.shipped_sale_id)
}
function requestDeleteOrder(order) {
    if (disposed || deleting.value || loading.value || !canDeleteOrder(order)) return
    orderToDelete.value = order
    deleteError.value = ''
}
function cancelDeleteOrder() {
    if (deleting.value) return
    orderToDelete.value = null
    deleteError.value = ''
}
async function deleteOrder() {
    const order = orderToDelete.value
    if (disposed || deleting.value || !canDeleteOrder(order)) return
    deleting.value = true
    deleteError.value = ''
    try {
        await axios.delete(`/api/orders/${order.id}`)
        if (disposed) return
        orderToDelete.value = null
        if (String(selectedOrderId.value) === String(order.id)) {
            orderDetailsOpen.value = false
            selectedOrderId.value = null
        }
        await fetchOrders()
    } catch (error) {
        if (!disposed) deleteError.value = error.response?.data?.message || 'Не удалось удалить заказ. Попробуйте ещё раз.'
    } finally {
        if (!disposed) deleting.value = false
    }
}
function formatDate(value) {
    if (!value) return '—'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit' })
}
function formatTime(value) {
    if (!value) return ''
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
function formatMoney(value, currency = 'RUB') {
    if (value == null || !Number.isFinite(Number(value))) return '—'
    return `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${currency === 'RUB' ? '₽' : currency}`
}
function formatWeight(value) {
    if (value == null || !Number.isFinite(Number(value))) return ''
    return `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 3 })} кг`
}
function formatDeliveryDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''))
    return match ? `${match[3]}.${match[2]}.${match[1]}` : 'Не назначена'
}
function chooseDeliveryDate(value) {
    draftFilters.delivery_date = value
    if (value) draftFilters.delivery_unscheduled = false
}
function toggleUnscheduled(checked) {
    draftFilters.delivery_unscheduled = checked
    if (checked) draftFilters.delivery_date = ''
}
function buildingsLabel(order) {
    return (order.buildings || []).map(building => [building.city?.name, building.address, buildingApartmentLabel(building)].filter(Boolean).join(', ')).join(' · ')
}
function statusesChanged(statuses) {
    options.statuses = statuses
    if (filters.status_id && !statuses.some(status => String(status.id) === String(filters.status_id))) {
        filters.status_id = null
        filters.page = 1
    }
    fetchOrders()
}
const debouncedSearch = useDebounceFn(() => {
    filters.page = 1
    fetchOrders()
}, 350)
watch(() => filters.search, debouncedSearch)
watch(filterMenuOpen, open => {
    if (open) for (const key of Object.keys(draftFilters)) draftFilters[key] = filters[key]
})
onMounted(() => Promise.all([fetchOptions(), fetchOrders()]))
onBeforeUnmount(() => { disposed = true; requestController?.abort() })
</script>

<template>
    <main class="orders-page">
        <header class="orders-header">
            <div class="orders-header__title">
                <span class="orders-header__mark"><v-icon icon="mdi-package-variant-closed" size="21" /></span>
                <h1>Заказы</h1>
                <span class="orders-header__count" aria-label="Всего заказов">{{ meta.total }}</span>
            </div>
            <label class="orders-search">
                <v-icon icon="mdi-magnify" size="18" />
                <input v-model="filters.search" type="search" aria-label="Поиск заказов" placeholder="Номер, контрагент, товар…">
            </label>
            <div class="orders-header__actions">
                <v-menu v-model="filterMenuOpen" :close-on-content-click="false" location="bottom end" :max-width="560" :offset="8">
                    <template #activator="{ props: menuProps }">
                        <button v-bind="menuProps" type="button" class="orders-action" :class="{ 'is-active': activeFilters.length }">
                            <v-icon icon="mdi-filter-variant" size="18" />
                            <span>Фильтры</span>
                            <b v-if="activeFilters.length" class="orders-action__badge">{{ activeFilters.length }}</b>
                            <v-icon icon="mdi-chevron-down" size="14" />
                        </button>
                    </template>
                    <form class="orders-filters" role="dialog" aria-label="Фильтры заказов" @submit.prevent="applyFilters">
                        <div class="orders-filters__heading">
                            <strong>Фильтры заказов</strong>
                            <button type="button" aria-label="Закрыть фильтры" @click="filterMenuOpen = false"><v-icon icon="mdi-close" size="18" /></button>
                        </div>
                        <div class="orders-filters__grid">
                            <label class="orders-filter">
                                <span>Статус</span>
                                <select v-model="draftFilters.status_id" aria-label="Статус"><option :value="null">Все статусы</option><option v-for="status in options.statuses" :key="status.id" :value="status.id">{{ status.name }}</option></select>
                            </label>
                            <label class="orders-filter">
                                <span>Контрагент</span>
                                <select v-model="draftFilters.entity_id" aria-label="Контрагент"><option :value="null">Все контрагенты</option><option v-for="entity in options.entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option></select>
                            </label>
                            <label class="orders-filter">
                                <span>Адрес</span>
                                <select v-model="draftFilters.building_id" aria-label="Адрес"><option :value="null">Все адреса</option><option v-for="building in options.buildings" :key="building.id" :value="building.id">{{ building.address }}</option></select>
                            </label>
                            <label class="orders-filter">
                                <span>Товар</span>
                                <select v-model="draftFilters.good_id" aria-label="Товар"><option :value="null">Все товары</option><option v-for="good in options.goods" :key="good.id" :value="good.id">{{ good.name }}</option></select>
                            </label>
                            <label class="orders-filter"><span>Создан с</span><input v-model="draftFilters.date_from" type="date" :max="draftFilters.date_to || undefined"></label>
                            <label class="orders-filter"><span>Создан по</span><input v-model="draftFilters.date_to" type="date" :min="draftFilters.date_from || undefined"></label>
                            <label class="orders-filter"><span>Сумма от</span><input v-model="draftFilters.total_from" type="number" min="0" step="0.01" :max="draftFilters.total_to || undefined" placeholder="0"></label>
                            <label class="orders-filter"><span>Сумма до</span><input v-model="draftFilters.total_to" type="number" :min="draftFilters.total_from || 0" step="0.01" placeholder="Без ограничения"></label>
                            <label class="orders-filter"><span>День доставки</span><input :value="draftFilters.delivery_date" type="date" @input="chooseDeliveryDate($event.target.value)"></label>
                            <label class="orders-filter orders-filter--checkbox"><input :checked="draftFilters.delivery_unscheduled" type="checkbox" @change="toggleUnscheduled($event.target.checked)"><span>Без даты доставки</span></label>
                        </div>
                        <div class="orders-filters__actions">
                            <button type="button" class="orders-action" @click="resetFilters">Сбросить</button>
                            <button type="submit" class="orders-action orders-action--primary"><v-icon icon="mdi-check" size="16" />Применить</button>
                        </div>
                    </form>
                </v-menu>
                <button type="button" class="orders-action" title="Управление статусами заказов" @click="statusesOpen = true"><v-icon icon="mdi-tag-outline" size="17" /><span>Статусы</span></button>
                <Link v-if="permissions.create" :href="route('Ameise.orders.create')" class="orders-action orders-action--primary"><v-icon icon="mdi-plus" size="18" /><span>Новый заказ</span></Link>
            </div>
        </header>

        <div v-if="activeFilters.length" class="orders-active-filters" aria-label="Применённые фильтры">
            <button v-for="filter in activeFilters" :key="filter.key" type="button" :title="`Убрать фильтр: ${filter.label}`" @click="removeFilter(filter.key)"><span>{{ filter.label }}</span><v-icon icon="mdi-close" size="12" /></button>
            <button type="button" class="orders-active-filters__reset" @click="resetFilters">Сбросить все</button>
        </div>
        <v-alert v-if="optionsError" type="error" density="compact" variant="tonal" class="mb-2">{{ optionsError }} <button type="button" class="orders-retry" @click="fetchOptions">Повторить</button></v-alert>
        <v-alert v-if="errorMessage" type="error" density="compact" variant="tonal" class="mb-2">{{ errorMessage }} <button type="button" class="orders-retry" @click="fetchOrders">Повторить</button></v-alert>

        <section class="orders-ledger" :aria-busy="loading">
            <div class="orders-ledger__summary">
                <span><v-icon icon="mdi-format-list-bulleted" size="15" />{{ loading ? 'Загрузка…' : pageRange }}</span>
                <span v-if="orders.length && !loading" class="orders-ledger__totals">На странице <strong>{{ pageTotals }}</strong></span>
                <button type="button" :disabled="loading" title="Обновить заказы" aria-label="Обновить заказы" @click="fetchOrders"><v-icon icon="mdi-refresh" size="17" /></button>
            </div>
            <div class="orders-ledger__progress"><v-progress-linear v-if="loading" indeterminate color="#7f1d1d" height="2" /></div>
            <div ref="ledgerScroll" class="orders-ledger__scroll">
                <table aria-label="Заказы">
                    <colgroup><col class="col-number"><col class="col-status"><col class="col-entity"><col class="col-items"><col class="col-amount"><col class="col-date"><col class="col-delivery"><col v-if="permissions.delete" class="col-actions"></colgroup>
                    <thead><tr>
                        <th v-for="header in headers" :key="header.key" scope="col" :aria-sort="filters.sort_by === header.key ? (filters.sort_direction === 'asc' ? 'ascending' : 'descending') : 'none'">
                            <button type="button" @click="toggleSort(header.key)">{{ header.title }}<v-icon v-if="filters.sort_by === header.key" :icon="sortIcon" size="12" /></button>
                        </th>
                        <th v-if="permissions.delete" scope="col" aria-label="Действия" />
                    </tr></thead>
                    <tbody v-if="orders.length" :class="{ 'is-loading': loading }">
                        <tr v-for="order in orders" :key="order.id" tabindex="0" @click="openOrder(order)" @keydown.enter.self.prevent="openOrder(order)" @keydown.space.self.prevent="openOrder(order)">
                            <td><button type="button" class="orders-ledger__number" aria-haspopup="dialog" :aria-label="`Детали заказа ${order.number || `#${order.id}`}`" @click.stop="openOrder(order)">{{ order.number || `#${order.id}` }}</button><small v-if="order.internal_comment" class="orders-ledger__comment" :title="order.internal_comment"><v-icon icon="mdi-text-box-outline" size="12" />{{ order.internal_comment }}</small></td>
                            <td><span class="orders-ledger__status" :style="{ '--status-color': order.status?.color || '#64748b' }">{{ order.status?.name || '—' }}</span></td>
                            <td><strong class="orders-ledger__entity" :title="order.entity?.name">{{ order.entity?.name || 'Без контрагента' }}</strong><small v-if="order.entity?.INN">ИНН {{ order.entity.INN }}</small></td>
                            <td><div class="orders-ledger__goods">
                                <template v-for="item in (order.items || [])" :key="item.id">
                                    <Link v-if="item.good?.id" :href="goodUrl(item.good)" :title="`${item.good_name} × ${item.quantity}`" @click.stop>{{ item.good_name }} <span>× {{ item.quantity }}</span></Link>
                                    <span v-else>{{ item.good_name }} × {{ item.quantity }}</span>
                                </template>
                            </div></td>
                            <td class="orders-ledger__money"><strong>{{ formatMoney(order.total_amount, order.currency_code) }}</strong><small>{{ formatWeight(order.total_weight) }}</small></td>
                            <td class="orders-ledger__date">{{ formatDate(order.submitted_at || order.created_at) }}<small>{{ formatTime(order.submitted_at || order.created_at) }}</small></td>
                            <td><span class="orders-ledger__delivery" :class="{ 'is-unscheduled': !order.delivery_date }"><v-icon icon="mdi-truck-outline" size="13" />{{ formatDeliveryDate(order.delivery_date) }}</span><small v-if="buildingsLabel(order)" class="orders-ledger__building" :title="buildingsLabel(order)">{{ buildingsLabel(order) }}</small></td>
                            <td v-if="permissions.delete" class="orders-ledger__actions"><button v-if="canDeleteOrder(order)" type="button" class="orders-ledger__delete" :disabled="loading || deleting" :aria-label="`Удалить заказ ${order.number || `#${order.id}`}`" title="Удалить заказ" aria-haspopup="dialog" @click.stop="requestDeleteOrder(order)"><v-icon icon="mdi-delete-outline" size="17" /></button></td>
                        </tr>
                    </tbody>
                    <tbody v-else><tr><td :colspan="headers.length + (permissions.delete ? 1 : 0)" class="orders-ledger__empty">
                        <v-icon :icon="loading ? 'mdi-dots-horizontal' : 'mdi-package-variant'" size="28" />
                        <span>{{ loading ? 'Загружаем заказы…' : errorMessage ? 'Список заказов недоступен' : 'Заказы не найдены' }}</span>
                        <button v-if="hasActiveFilters && !loading" type="button" class="orders-retry" @click="resetFilters">Сбросить фильтры</button>
                    </td></tr></tbody>
                </table>
            </div>
            <footer class="orders-pagination">
                <span>100 строк на странице</span>
                <div><button type="button" :disabled="loading || meta.current_page <= 1" aria-label="Предыдущая страница" @click="goToPage(meta.current_page - 1)"><v-icon icon="mdi-chevron-left" size="17" /></button><span>{{ meta.current_page }} / {{ meta.last_page }}</span><button type="button" :disabled="loading || meta.current_page >= meta.last_page" aria-label="Следующая страница" @click="goToPage(meta.current_page + 1)"><v-icon icon="mdi-chevron-right" size="17" /></button></div>
            </footer>
        </section>
        <OrderStatusesDialog v-model="statusesOpen" :permissions="permissions" @changed="statusesChanged" />
        <OrderDetailsDialog v-model="orderDetailsOpen" :order-id="selectedOrderId" :editable="permissions.edit" :external-busy="deleting" @saved="fetchOrders">
            <template #actions="{ order, disabled }">
                <button v-if="canDeleteOrder(order)" type="button" class="orders-action orders-action--danger orders-details-delete" :disabled="disabled || loading" @click="requestDeleteOrder(order)"><v-icon icon="mdi-delete-outline" size="16" />Удалить заказ</button>
            </template>
        </OrderDetailsDialog>
        <v-dialog :model-value="Boolean(orderToDelete)" :persistent="deleting" max-width="440" aria-labelledby="orders-delete-title" @update:model-value="value => { if (!value) cancelDeleteOrder() }">
            <v-card>
                <v-card-title id="orders-delete-title" class="orders-delete-title">Удалить заказ {{ orderToDelete?.number || `#${orderToDelete?.id}` }}?</v-card-title>
                <v-card-text>
                    Заказ и его позиции будут удалены. Восстановить их будет невозможно.
                    <v-alert v-if="deleteError" type="error" density="compact" variant="tonal" class="mt-3" role="alert">{{ deleteError }}</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn :disabled="deleting" @click="cancelDeleteOrder">Отмена</v-btn>
                    <v-btn color="error" variant="flat" :loading="deleting" :disabled="deleting" @click="deleteOrder">Удалить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </main>
</template>

<style scoped>
.orders-page { display: flex; flex-direction: column; box-sizing: border-box; width: 100%; height: calc(100vh - var(--v-layout-top, 58px) - var(--v-layout-bottom, 0px)); height: calc(100dvh - var(--v-layout-top, 58px) - var(--v-layout-bottom, 0px)); min-height: 0; overflow: hidden; padding: 12px 12px 24px; background: #f4f5f7; color: #252a31; }
.orders-page > :not(.orders-ledger) { flex: 0 0 auto; }
.orders-page > .v-alert { font-size: 11px; line-height: 1.4; }
.orders-header { display: flex; align-items: center; gap: 16px; margin-bottom: 10px; }
.orders-header__title, .orders-header__actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.orders-header__title { gap: 9px; }
.orders-header__mark { display: grid; place-items: center; width: 34px; height: 34px; border: 1px solid #e3cdcd; border-radius: 8px; background: #f4e9e9; color: #7f1d1d; }
.orders-header h1 { font-size: 18px; font-weight: 800; letter-spacing: -.04em; }
.orders-header__count { border-radius: 5px; padding: 2px 6px; background: #e5e8ed; color: #66707c; font-size: 11px; font-weight: 700; font-variant-numeric: tabular-nums; }
.orders-search { display: flex; align-items: center; gap: 6px; width: min(340px, 100%); min-width: 140px; height: 32px; margin-left: auto; padding: 0 8px; border: 1px solid #d7dce2; border-radius: 6px; background: #fff; color: #8b929b; }
.orders-search input { min-width: 0; width: 100%; height: 100%; padding: 0; border: 0; outline: none; box-shadow: none; background: transparent; color: #252a31; font-size: 11px; }
.orders-search:focus-within { outline: 2px solid #a66a6a; outline-offset: 1px; }
.orders-action { display: inline-flex; align-items: center; justify-content: center; gap: 5px; height: 32px; padding: 0 10px; border: 1px solid #d2d7de; border-radius: 6px; background: #fff; color: #4b5563; font-size: 11px; font-weight: 650; text-decoration: none; white-space: nowrap; }
.orders-action:hover { background: #f0f2f5; }
.orders-action.is-active { border-color: #b88e8e; color: #7f1d1d; }
.orders-action--primary, .orders-action--primary:hover { border-color: #7f1d1d; background: #7f1d1d; color: #fff; }
.orders-action--danger { border-color: #e7c5c5; color: #a22f2f; }
.orders-action--danger:hover { background: #fdf0f0; }
.orders-details-delete { margin-bottom: 12px; }
.orders-delete-title { white-space: normal; overflow-wrap: anywhere; }
.orders-action__badge { display: grid; place-items: center; min-width: 17px; height: 17px; padding: 0 3px; border-radius: 4px; background: #7f1d1d; color: white; font-size: 9px; }
.orders-filters { width: 520px; max-width: calc(100vw - 24px); overflow: auto; max-height: min(640px, calc(100dvh - 100px)); border: 1px solid #d7dce2; border-radius: 9px; background: #fff; color: #252a31; box-shadow: 0 12px 40px #202b4026; }
.orders-filters__heading { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-bottom: 1px solid #e7e9ed; font-size: 12px; }
.orders-filters__heading button { display: grid; place-items: center; width: 26px; height: 26px; color: #68717b; }
.orders-filters__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 14px; }
.orders-filter { display: grid; min-width: 0; gap: 5px; }
.orders-filter > span { color: #68717b; font-size: 11px; }
.orders-filter input, .orders-filter select { width: 100%; min-width: 0; height: 32px; padding: 0 8px; border: 1px solid #cfd4da; border-radius: 5px; background-color: #fff; color: #2c3239; font-size: 11px; }
.orders-filter select { padding-right: 28px; text-overflow: ellipsis; }
.orders-filter--checkbox { display: flex; align-items: center; gap: 7px; padding-top: 18px; }
.orders-filter--checkbox input { width: 15px; height: 15px; padding: 0; accent-color: #7f1d1d; }
.orders-filters__actions { display: flex; justify-content: space-between; gap: 8px; padding: 10px 14px; border-top: 1px solid #e7e9ed; background: #f8f9fb; }
.orders-page > .orders-active-filters { display: flex; flex: 0 1 auto; flex-wrap: wrap; gap: 5px; min-height: 0; max-height: min(96px, 20dvh); overflow: auto; margin-bottom: 8px; }
.orders-active-filters button { display: inline-flex; align-items: center; gap: 5px; max-width: 250px; padding: 3px 7px; border: 1px solid #ded4d4; border-radius: 5px; background: #fcf7f7; color: #7f1d1d; font-size: 10px; }
.orders-active-filters button span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.orders-active-filters .orders-active-filters__reset { border-color: transparent; background: transparent; color: #747d87; }
.orders-ledger { display: flex; flex: 1; flex-direction: column; min-height: 74px; overflow: hidden; border: 1px solid #d3d8de; border-radius: 8px; background: #fff; }
.orders-ledger__summary { display: flex; flex-shrink: 0; align-items: center; gap: 12px; min-height: 31px; padding: 4px 9px; color: #747d87; font-size: 10px; }
.orders-ledger__summary > span { display: inline-flex; align-items: center; gap: 6px; }
.orders-ledger__summary .orders-ledger__totals { margin-left: auto; flex-wrap: wrap; }
.orders-ledger__totals strong { color: #434d59; font-weight: 650; }
.orders-ledger__summary button { display: grid; place-items: center; margin-left: auto; width: 24px; height: 24px; }
.orders-ledger__totals + button { margin-left: 0; }
.orders-ledger__progress { flex-shrink: 0; height: 2px; }
.orders-ledger__scroll { flex: 1; min-height: 0; overflow: auto; }
.orders-ledger table { width: 100%; min-width: 930px; border-collapse: collapse; table-layout: fixed; }
.col-number { width: 12%; } .col-status { width: 10%; } .col-entity { width: 17%; } .col-items { width: 23%; } .col-amount { width: 12%; } .col-date { width: 8%; } .col-delivery { width: 14%; } .col-actions { width: 4%; }
.orders-ledger th, .orders-ledger td { padding: 6px 9px; border-bottom: 1px solid #e6e9ed; text-align: left; vertical-align: middle; }
.orders-ledger th { position: sticky; top: 0; z-index: 1; height: 29px; background: #eef0f3; color: #6c7682; font-size: 9px; font-weight: 750; letter-spacing: .035em; text-transform: uppercase; }
.orders-ledger th button { display: inline-flex; align-items: center; gap: 3px; color: inherit; font: inherit; text-transform: inherit; }
.orders-ledger tbody tr { cursor: pointer; }
.orders-ledger tbody tr:hover { background: #faf6f3; }
.orders-ledger tbody tr:focus-visible { outline: 2px solid #9e6868; outline-offset: -2px; background: #faf6f3; }
.orders-ledger tbody.is-loading { opacity: .5; }
.orders-ledger td { height: 47px; font-size: 11px; overflow-wrap: anywhere; }
.orders-ledger td > strong, .orders-ledger td > small { display: block; }
.orders-ledger td > small, .orders-ledger__goods small { margin-top: 2px; color: #87909b; font-size: 9px; }
.orders-ledger__number { color: #384556; font-size: 10px; font-weight: 750; text-decoration: none; }
.orders-ledger__number:hover { text-decoration: underline; }
.orders-ledger__number:focus-visible { outline: 2px solid #9e6868; outline-offset: 2px; }
.orders-ledger__number, .orders-ledger__money, .orders-ledger__date { font-variant-numeric: tabular-nums; }
.orders-ledger__entity, .orders-ledger__comment, .orders-ledger__building { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.orders-ledger__comment .v-icon { margin-right: 3px; }
.orders-ledger__status { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 650; }
.orders-ledger__status::before { width: 6px; height: 6px; flex-shrink: 0; border-radius: 50%; background: var(--status-color); content: ''; }
.orders-ledger__goods { display: grid; gap: 2px; font-size: 10px; }
.orders-ledger__goods a, .orders-ledger__goods > span { color: #7f1d1d; text-decoration: none; overflow-wrap: anywhere; }
.orders-ledger__goods a span { color: #7b838d; }
.orders-ledger__goods a:hover { text-decoration: underline; }
.orders-ledger__money { text-align: right !important; white-space: nowrap; }
.orders-ledger__money strong { color: #285a4b; font-size: 11px; font-weight: 750; }
.orders-ledger__date { color: #5d6875; white-space: nowrap; font-size: 10px !important; }
.orders-ledger__delivery { display: inline-flex; align-items: center; gap: 4px; color: #4b5c6e; font-size: 10px; white-space: nowrap; }
.orders-ledger__delivery.is-unscheduled { color: #9b865f; }
.orders-ledger__empty { height: 150px !important; color: #929aa4; text-align: center !important; cursor: default; }
.orders-ledger__empty > span { display: block; margin: 6px 0; }
.orders-retry { margin-left: 5px; font-size: 11px; text-decoration: underline; }
.orders-ledger__actions { padding: 6px 4px !important; text-align: center !important; }
.orders-ledger__delete { display: inline-flex; align-items: center; justify-content: center; width: 27px; height: 27px; border-radius: 5px; color: #a22f2f; }
.orders-ledger__delete:hover { background: #fce8e8; }
.orders-ledger__delete:focus-visible { outline: 2px solid #9e6868; outline-offset: 1px; }
.orders-ledger__delete:disabled { opacity: .35; }
.orders-pagination { display: flex; flex-shrink: 0; align-items: center; justify-content: space-between; gap: 12px; min-height: 38px; padding: 4px 9px; border-top: 1px solid #e6e9ed; color: #747d87; font-size: 10px; }
.orders-pagination div { display: flex; align-items: center; gap: 7px; }
.orders-pagination button { display: inline-flex; align-items: center; justify-content: center; width: 27px; height: 27px; border: 1px solid #d8dde3; border-radius: 5px; color: #526070; }
.orders-pagination button:disabled, .orders-ledger__summary button:disabled { opacity: .35; }
.orders-pagination div span { min-width: 45px; font-variant-numeric: tabular-nums; text-align: center; }
@media (max-width: 1050px) { .orders-header { flex-wrap: wrap; gap: 8px; } .orders-search { flex: 1; width: auto; } .orders-header__actions { margin-left: auto; } }
@media (max-width: 600px) { .orders-page { padding: 8px 8px 24px; } .orders-header__title { gap: 6px; } .orders-header h1 { font-size: 17px; } .orders-header__actions { width: 100%; } .orders-header__actions > .orders-action--primary { margin-left: auto; } .orders-header__mark { width: 30px; height: 30px; } .orders-search { min-width: 125px; } .orders-ledger__summary { gap: 6px; } .orders-ledger__summary .orders-ledger__totals { font-size: 9px; } }
@media (max-width: 380px) { .orders-header__actions { gap: 4px; } .orders-header__actions .orders-action { gap: 4px; padding: 0 6px; font-size: 10px; } .orders-filters__grid { grid-template-columns: 1fr; } .orders-filter--checkbox { padding-top: 0; } .orders-action { padding: 0 7px; } }
</style>
