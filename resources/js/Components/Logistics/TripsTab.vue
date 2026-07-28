<script setup>
import { computed, inject, onActivated, onMounted, reactive, ref, watch } from 'vue'
import TripDialog from './TripDialog.vue'

const api = inject('logisticsApi')
const permissions = inject('logisticsPermissions')
const items = ref([])
const total = ref(0)
const loaded = ref(false)
const dialog = ref(false)
const selected = ref(null)
const archiveTarget = ref(null)
const vehicles = ref([])
const cities = ref([])
const entities = ref([])
const table = reactive({ page: 1, itemsPerPage: 25, sortBy: [{ key: 'planned_departure_at', order: 'desc' }] })
const filters = reactive({ search: '', status: [], vehicle_id: null, city_id: null, carrier_entity_id: null, date_from: '', date_to: '', has_route: null, expenses_without_check: false })
let timer = null
let referenceTimer = null

const statuses = [
    { title: 'Черновик', value: 'draft' }, { title: 'Запланирован', value: 'planned' },
    { title: 'В пути', value: 'in_progress' }, { title: 'Завершён', value: 'completed' },
    { title: 'Отменён', value: 'cancelled' },
]
const routeFilters = [{ title: 'С маршрутом', value: '1' }, { title: 'Без маршрута', value: '0' }]
const headers = [
    { title: 'Номер / статус', key: 'number' }, { title: 'Отправление', key: 'planned_departure_at' },
    { title: 'Маршрут', key: 'route_summary', sortable: false }, { title: 'Авто', key: 'vehicle', sortable: false },
    { title: 'Вес', key: 'cargo_weight_kg', align: 'end' }, { title: 'План', key: 'planned_distance_m', align: 'end' },
    { title: 'Факт', key: 'actual_distance_m', align: 'end' }, { title: 'Расходы', key: 'expenses', align: 'end', sortable: false },
    { title: 'Стоимость/км', key: 'cost_per_km', align: 'end', sortable: false }, { title: 'Ответственный', key: 'responsible', sortable: false },
    { title: '', key: 'actions', align: 'end', sortable: false },
]
const canManage = computed(() => Boolean(permissions.value?.trips_manage))

function statusTitle(value) { return statuses.find((item) => item.value === value)?.title || value }
function statusColor(value) { return ({ draft: 'grey', planned: 'blue', in_progress: 'orange', completed: 'green', cancelled: 'red' })[value] || 'grey' }
function formatDate(value) { return value ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—' }
function formatKm(value) { return value == null ? '—' : `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(value / 1000)} км` }
function formatKg(value) { return value == null ? '—' : `${new Intl.NumberFormat('ru-RU').format(value)} кг` }
function money(value, currency = 'RUB') { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number(value || 0)) }
function totals(metrics) { const entries = Object.entries(metrics?.totals_by_currency || {}); return entries.length ? entries.map(([c, v]) => money(v, c)).join(' · ') : '—' }
function costKm(metrics) { return metrics?.cost_per_km == null ? '—' : `${money(metrics.cost_per_km, metrics.primary_currency)}${metrics.distance_basis === 'planned' ? ' (план)' : ''}` }
function merge(listRef, additions) { const map = new Map(listRef.value.map((x) => [x.id, x])); additions.filter(Boolean).forEach((x) => map.set(x.id, x)); listRef.value = [...map.values()] }
function openCreate() { selected.value = null; dialog.value = true }
function openTrip(item) { selected.value = item; dialog.value = true }

async function load(options = null) {
    if (options?.page) Object.assign(table, options)
    const sort = table.sortBy?.[0] || { key: 'planned_departure_at', order: 'desc' }
    try {
        const response = await api.request('trips-load', { method: 'get', url: '/api/logistics/trips', params: {
            page: table.page, per_page: table.itemsPerPage, sort_by: sort.key, sort_direction: sort.order,
            search: filters.search || undefined, status: filters.status?.length ? filters.status : undefined,
            vehicle_id: filters.vehicle_id || undefined, city_id: filters.city_id || undefined,
            carrier_entity_id: filters.carrier_entity_id || undefined, date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined, has_route: filters.has_route ?? undefined,
            expenses_without_check: filters.expenses_without_check ? 1 : 0,
        } }, { error: 'Не удалось загрузить рейсы.' })
        items.value = response?.data || []
        total.value = response?.meta?.total || 0
        loaded.value = true
    } catch { /* global snackbar */ }
}

async function loadVehicles() {
    try { const response = await api.request('trip-filter-vehicles', { method: 'get', url: '/api/logistics/vehicles', params: { per_page: 100 } }); merge(vehicles, response?.data || []) } catch { /* global */ }
}
async function loadReference(type, search = '') {
    try { const response = await api.request(`trip-filter-${type}-${search}`, { method: 'get', url: `/api/logistics/references/${type}`, params: { search, limit: 75 } }); merge(type === 'cities' ? cities : entities, response?.data || []) } catch { /* global */ }
}
function delayedReference(type, search) { clearTimeout(referenceTimer); referenceTimer = setTimeout(() => loadReference(type, search || ''), 250) }

async function archive() {
    if (!archiveTarget.value) return
    try {
        await api.request('trip-archive', { method: 'delete', url: `/api/logistics/trips/${archiveTarget.value.id}` }, { success: 'Рейс перемещён в архив; чеки сохранены.' })
        archiveTarget.value = null
        await load()
    } catch { /* global snackbar */ }
}

watch(filters, () => { clearTimeout(timer); timer = setTimeout(() => { table.page = 1; load() }, 300) }, { deep: true })
onMounted(() => { load(); loadVehicles(); loadReference('cities'); loadReference('entities') })
onActivated(() => { if (!loaded.value) load() })
</script>

<template>
    <section>
        <div class="logistics-toolbar">
            <div class="logistics-toolbar__filters">
                <v-text-field v-model="filters.search" label="Номер, груз, авто" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" hide-details clearable style="min-width: 220px" />
                <v-select v-model="filters.status" :items="statuses" label="Статус" multiple chips closable-chips density="compact" variant="outlined" hide-details clearable style="min-width: 220px" />
                <v-autocomplete v-model="filters.vehicle_id" :items="vehicles" :item-title="item => `${item.name} · ${item.registration_number}`" item-value="id" label="Авто" density="compact" variant="outlined" hide-details clearable style="min-width: 210px" />
                <v-autocomplete v-model="filters.city_id" :items="cities" item-title="label" item-value="id" label="Город" density="compact" variant="outlined" hide-details clearable style="min-width: 200px" @update:search="delayedReference('cities', $event)" />
                <v-autocomplete v-model="filters.carrier_entity_id" :items="entities" item-title="name" item-value="id" label="Перевозчик" density="compact" variant="outlined" hide-details clearable style="min-width: 200px" @update:search="delayedReference('entities', $event)" />
                <v-text-field v-model="filters.date_from" type="date" label="С" density="compact" variant="outlined" hide-details style="max-width: 165px" />
                <v-text-field v-model="filters.date_to" type="date" label="По" density="compact" variant="outlined" hide-details style="max-width: 165px" />
                <v-select v-model="filters.has_route" :items="routeFilters" label="Маршрут" density="compact" variant="outlined" hide-details clearable style="max-width: 180px" />
                <v-checkbox v-model="filters.expenses_without_check" label="Есть расходы без чека" density="compact" hide-details />
            </div>
            <div class="logistics-toolbar__actions">
                <v-btn variant="outlined" icon="mdi-refresh" :loading="api.isPending('trips-load')" @click="load" />
                <v-btn v-if="canManage" color="green-darken-2" variant="flat" prepend-icon="mdi-plus" @click="openCreate">Новый рейс</v-btn>
            </div>
        </div>

        <v-card variant="outlined">
            <v-data-table-server v-model:page="table.page" v-model:items-per-page="table.itemsPerPage" v-model:sort-by="table.sortBy"
                :headers="headers" :items="items" :items-length="total" :loading="api.isPending('trips-load')" item-value="id" @update:options="load" @click:row="(_, row) => openTrip(row.item)">
                <template #item.number="{ item }"><strong>{{ item.number }}</strong><div><v-chip :color="statusColor(item.status)" variant="tonal" size="x-small">{{ statusTitle(item.status) }}</v-chip></div></template>
                <template #item.planned_departure_at="{ item }">{{ formatDate(item.planned_departure_at) }}</template>
                <template #item.route_summary="{ item }"><span class="text-no-wrap">{{ item.route_summary || '—' }}</span><div class="text-caption">{{ item.stops_count || 0 }} ост.</div></template>
                <template #item.vehicle="{ item }">{{ item.vehicle?.registration_number || '—' }}<div class="text-caption">{{ item.vehicle?.name }}</div></template>
                <template #item.cargo_weight_kg="{ item }">{{ formatKg(item.cargo_weight_kg) }}</template>
                <template #item.planned_distance_m="{ item }">{{ formatKm(item.planned_distance_m) }}</template>
                <template #item.actual_distance_m="{ item }">{{ formatKm(item.actual_distance_m) }}</template>
                <template #item.expenses="{ item }">{{ totals(item.metrics) }}<div class="text-caption">{{ item.expenses_count || 0 }} поз.</div></template>
                <template #item.cost_per_km="{ item }">{{ costKm(item.metrics) }}</template>
                <template #item.responsible="{ item }">{{ item.responsible?.name || '—' }}</template>
                <template #item.actions="{ item }"><v-btn icon="mdi-open-in-new" size="small" variant="text" @click.stop="openTrip(item)" /><v-btn v-if="canManage && item.status !== 'completed'" icon="mdi-archive-outline" size="small" variant="text" color="orange" @click.stop="archiveTarget = item" /></template>
                <template #no-data><div class="logistics-empty">Рейсы по выбранным фильтрам не найдены.</div></template>
            </v-data-table-server>
        </v-card>

        <TripDialog v-model="dialog" :trip="selected" @saved="load" />
        <v-dialog :model-value="Boolean(archiveTarget)" max-width="470" @update:model-value="!$event && (archiveTarget = null)">
            <v-card title="Архивировать рейс?"><v-card-text>Рейс {{ archiveTarget?.number }} будет скрыт из рабочего списка. Связанные Checks не удаляются.</v-card-text><v-card-actions class="justify-end"><v-btn @click="archiveTarget = null">Отмена</v-btn><v-btn color="orange-darken-2" variant="flat" :loading="api.isPending('trip-archive')" @click="archive">В архив</v-btn></v-card-actions></v-card>
        </v-dialog>
    </section>
</template>
