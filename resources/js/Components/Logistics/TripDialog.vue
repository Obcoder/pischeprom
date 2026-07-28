<script setup>
import { computed, inject, onBeforeUnmount, reactive, ref, watch } from 'vue'
import TripExpensesPanel from './TripExpensesPanel.vue'

const props = defineProps({ modelValue: Boolean, trip: { type: Object, default: null } })
const emit = defineEmits(['update:modelValue', 'saved'])
const api = inject('logisticsApi')
const permissions = inject('logisticsPermissions')
const tab = ref('general')
const current = ref(null)
const vehicles = ref([])
const cities = ref([])
const entities = ref([])
const users = ref([])
const routes = ref([])
const routeRun = ref(null)
const formRef = ref(null)
const form = reactive(emptyForm())
let pollTimer = null
let referenceTimer = null

const statuses = [
    { title: 'Черновик', value: 'draft' }, { title: 'Запланирован', value: 'planned' },
    { title: 'В пути', value: 'in_progress' }, { title: 'Завершён', value: 'completed' },
    { title: 'Отменён', value: 'cancelled' },
]
const temperatures = [
    { title: 'Без режима', value: null }, { title: 'Обычный', value: 'ambient' },
    { title: 'Охлаждённый', value: 'chilled' }, { title: 'Замороженный', value: 'frozen' },
    { title: 'Свой диапазон', value: 'custom' },
]
const operations = [
    { title: 'Не указано', value: null }, { title: 'Погрузка', value: 'loading' },
    { title: 'Разгрузка', value: 'unloading' }, { title: 'Погрузка и разгрузка', value: 'loading_unloading' },
    { title: 'Техническая', value: 'technical' },
]
const selectedVehicle = computed(() => {
    if (!form.vehicle_id) return null
    return vehicles.value.find((item) => item.id === form.vehicle_id)
        || (current.value?.vehicle_id === form.vehicle_id ? current.value.vehicle : null)
})
const vehicleWarning = computed(() => selectedVehicle.value && !selectedVehicle.value.is_available_for_planning && ['planned', 'in_progress'].includes(form.status))
const canManageExpenses = computed(() => Boolean(permissions.value?.expenses_manage))
const canManageTrip = computed(() => Boolean(permissions.value?.trips_manage)
    && (current.value?.status !== 'completed' || Boolean(permissions.value?.technical_view)))
const loadingTrip = computed(() => Boolean(props.trip?.id && api.isPending(`trip-show-${props.trip.id}`)))

function emptyStop() { return { city_id: null, operation_type: null, address: '', latitude: null, longitude: null, planned_arrival_at: '', planned_departure_at: '', actual_arrival_at: '', actual_departure_at: '', cargo_weight_change_kg: null, notes: '' } }
function emptyForm() {
    return {
        number: '', status: 'draft', vehicle_id: null, carrier_entity_id: null, responsible_user_id: null,
        planned_departure_at: '', planned_arrival_at: '', actual_departure_at: '', actual_arrival_at: '',
        cargo_description: '', cargo_weight_kg: null, cargo_volume_m3: null, pallet_count: null,
        temperature_mode: null, temperature_min_c: null, temperature_max_c: null,
        actual_distance_km: null, odometer_start_km: null, odometer_end_km: null,
        routing_profile: 'truck', notes: '', acknowledge_vehicle_warning: false,
        stops: [emptyStop(), emptyStop()],
    }
}
function toLocal(value) {
    if (!value) return ''
    const date = new Date(value)
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    return local.toISOString().slice(0, 16)
}
function nullable(value) { return value === '' ? null : value }
function kilometersToMeters(value) {
    if (value === '' || value == null) return null
    const kilometers = Number(value)
    return Number.isFinite(kilometers) ? Math.round(kilometers * 1000) : null
}
function formatKm(value) { return value == null ? '—' : `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(value / 1000)} км` }
function formatDuration(value) { if (value == null) return '—'; const minutes = Math.round(value / 60); return `${Math.floor(minutes / 60)} ч ${minutes % 60} мин` }
function formatDate(value) { return value ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—' }
function routeStatusColor(status) { return ({ calculated: 'green', stale: 'orange', no_route: 'red', failed: 'red', pending: 'blue' })[status] || 'grey' }

function merge(listRef, additions) {
    const map = new Map(listRef.value.map((item) => [item.id, item]))
    additions.filter(Boolean).forEach((item) => map.set(item.id, item))
    listRef.value = [...map.values()]
}
function hydrate(trip) {
    current.value = trip
    const blank = emptyForm()
    for (const key of Object.keys(blank)) {
        if (key === 'stops') continue
        form[key] = ['planned_departure_at', 'planned_arrival_at', 'actual_departure_at', 'actual_arrival_at'].includes(key)
            ? toLocal(trip?.[key])
            : (trip?.[key] ?? blank[key])
    }
    form.number = trip?.number || ''
    form.actual_distance_km = trip?.actual_distance_km
        ?? (trip?.actual_distance_m == null ? null : trip.actual_distance_m / 1000)
    form.acknowledge_vehicle_warning = false
    form.stops = trip?.stops?.length
        ? trip.stops.map((stop) => ({
            ...emptyStop(), city_id: stop.city_id, operation_type: stop.operation_type, address: stop.address || '',
            latitude: stop.latitude, longitude: stop.longitude, planned_arrival_at: toLocal(stop.planned_arrival_at),
            planned_departure_at: toLocal(stop.planned_departure_at), actual_arrival_at: toLocal(stop.actual_arrival_at),
            actual_departure_at: toLocal(stop.actual_departure_at), cargo_weight_change_kg: stop.cargo_weight_change_kg, notes: stop.notes || '',
        }))
        : [emptyStop(), emptyStop()]
    merge(vehicles, [trip?.vehicle])
    merge(entities, [trip?.carrier])
    merge(users, [trip?.responsible])
    merge(cities, trip?.stops?.map((stop) => ({ id: stop.city_id, name: stop.city?.name, label: [stop.city?.name, stop.city?.region].filter(Boolean).join(', ') })) || [])
}

async function loadReferences(type, search = '') {
    try {
        const response = await api.request(`trip-ref-${type}-${search}`, { method: 'get', url: `/api/logistics/references/${type}`, params: { search, limit: 75 } }, { error: 'Не удалось загрузить справочник.' })
        merge(({ cities, entities, users })[type], response?.data || [])
    } catch { /* global snackbar */ }
}
function delayedReference(type, search) {
    clearTimeout(referenceTimer)
    referenceTimer = setTimeout(() => loadReferences(type, search || ''), 250)
}
async function loadVehicles() {
    try {
        const response = await api.request('trip-vehicles', { method: 'get', url: '/api/logistics/vehicles', params: { per_page: 100 } }, { error: 'Не удалось загрузить автомобили.' })
        merge(vehicles, response?.data || [])
    } catch { /* global snackbar */ }
}
async function loadTrip() {
    if (!props.trip?.id) { hydrate(null); return }
    try {
        const response = await api.request(`trip-show-${props.trip.id}`, { method: 'get', url: `/api/logistics/trips/${props.trip.id}` }, { error: 'Не удалось открыть рейс.' })
        hydrate(response?.data || response)
        await loadRoutes()
    } catch { /* global snackbar */ }
}
async function loadRoutes() {
    if (!current.value?.id && !props.trip?.id) return
    const id = current.value?.id || props.trip.id
    try {
        const response = await api.request(`trip-routes-${id}`, { method: 'get', url: `/api/logistics/trips/${id}/routes` }, { error: 'Не удалось загрузить историю маршрута.' })
        routes.value = response?.data || []
    } catch { /* global snackbar */ }
}

function addStop() { form.stops.push(emptyStop()) }
function removeStop(index) { if (form.stops.length > 2) form.stops.splice(index, 1) }
function moveStop(index, direction) {
    const target = direction === 'up' ? index - 1 : index + 1
    if (target < 0 || target >= form.stops.length) return
    const [item] = form.stops.splice(index, 1)
    form.stops.splice(target, 0, item)
}

function payload() {
    return {
        number: form.number || null, status: form.status, vehicle_id: form.vehicle_id,
        carrier_entity_id: form.carrier_entity_id, responsible_user_id: form.responsible_user_id,
        planned_departure_at: nullable(form.planned_departure_at), planned_arrival_at: nullable(form.planned_arrival_at),
        actual_departure_at: nullable(form.actual_departure_at), actual_arrival_at: nullable(form.actual_arrival_at),
        cargo_description: form.cargo_description || null, cargo_weight_kg: form.cargo_weight_kg,
        cargo_volume_m3: form.cargo_volume_m3, pallet_count: form.pallet_count,
        temperature_mode: form.temperature_mode, temperature_min_c: form.temperature_min_c,
        temperature_max_c: form.temperature_max_c, actual_distance_m: kilometersToMeters(form.actual_distance_km),
        odometer_start_km: form.odometer_start_km, odometer_end_km: form.odometer_end_km,
        routing_profile: form.routing_profile, notes: form.notes || null,
        acknowledge_vehicle_warning: form.acknowledge_vehicle_warning,
        stops: form.stops.map((stop) => ({
            city_id: stop.city_id, operation_type: stop.operation_type, address: stop.address || null,
            latitude: stop.latitude, longitude: stop.longitude,
            planned_arrival_at: nullable(stop.planned_arrival_at), planned_departure_at: nullable(stop.planned_departure_at),
            actual_arrival_at: nullable(stop.actual_arrival_at), actual_departure_at: nullable(stop.actual_departure_at),
            cargo_weight_change_kg: stop.cargo_weight_change_kg, notes: stop.notes || null,
        })),
    }
}

async function save(close = true) {
    const validation = await formRef.value?.validate()
    if (validation && !validation.valid) { tab.value = 'general'; return null }
    const editing = Boolean(current.value?.id || props.trip?.id)
    const id = current.value?.id || props.trip?.id
    try {
        const response = await api.request('trip-save', {
            method: editing ? 'put' : 'post', url: editing ? `/api/logistics/trips/${id}` : '/api/logistics/trips', data: payload(),
        }, { success: editing ? 'Рейс обновлён.' : 'Рейс создан.' })
        const saved = response?.data || response
        hydrate(saved)
        emit('saved', saved)
        if (close) emit('update:modelValue', false)
        return saved
    } catch { return null }
}

async function calculateRoute(force = false) {
    const trip = await save(false)
    if (!trip?.id) return
    try {
        const response = await api.request('route-calculate', { method: 'post', url: `/api/logistics/trips/${trip.id}/routes/calculate`, data: { force } }, { success: 'Расчёт маршрута поставлен в очередь.' })
        routeRun.value = response?.data || response
        tab.value = 'routes'
        startPolling()
    } catch { /* global snackbar */ }
}
function startPolling() {
    stopPolling()
    if (!routeRun.value?.id) return
    pollTimer = setInterval(pollRun, 2000)
    pollRun()
}
async function pollRun() {
    if (!routeRun.value?.id) return
    try {
        const response = await api.request(`route-run-${routeRun.value.id}`, { method: 'get', url: `/api/logistics/routing-runs/${routeRun.value.id}` }, { error: 'Не удалось получить прогресс маршрута.' })
        routeRun.value = response?.data || response
        if (['completed', 'partial', 'failed', 'cancelled'].includes(routeRun.value.status)) {
            stopPolling()
            await loadTrip()
            emit('saved', current.value)
        }
    } catch { stopPolling() }
}
function stopPolling() { if (pollTimer) clearInterval(pollTimer); pollTimer = null }

watch(() => props.modelValue, async (open) => {
    if (!open) { stopPolling(); return }
    tab.value = 'general'; routeRun.value = null; routes.value = []
    await Promise.allSettled([loadTrip(), loadVehicles(), loadReferences('cities'), loadReferences('entities'), loadReferences('users')])
})
onBeforeUnmount(stopPolling)
</script>

<template>
    <v-dialog :model-value="modelValue" fullscreen scrollable persistent @update:model-value="emit('update:modelValue', $event)">
        <v-card>
            <v-toolbar color="green-darken-3" density="comfortable">
                <v-btn icon="mdi-close" @click="emit('update:modelValue', false)" />
                <v-toolbar-title>{{ current?.number || (trip?.id ? trip.number : 'Новый рейс') }}</v-toolbar-title>
                <v-spacer />
                <v-chip v-if="current?.status" variant="tonal" class="mr-3">{{ statuses.find(x => x.value === current.status)?.title }}</v-chip>
                <v-btn v-if="canManageTrip" variant="flat" color="white" :loading="api.isPending('trip-save')" :disabled="loadingTrip" @click="save(true)">Сохранить</v-btn>
            </v-toolbar>

            <v-tabs v-model="tab" color="green-darken-2" show-arrows>
                <v-tab value="general">Рейс и груз</v-tab>
                <v-tab value="stops">Остановки ({{ form.stops.length }})</v-tab>
                <v-tab value="expenses" :disabled="!current?.id">Расходы</v-tab>
                <v-tab value="routes" :disabled="!current?.id">Маршрут и история</v-tab>
            </v-tabs>
            <v-divider />

            <v-card-text class="pa-0 bg-grey-lighten-4">
                <v-form ref="formRef" :disabled="loadingTrip || (!canManageTrip && !(tab === 'expenses' && canManageExpenses))">
                    <v-window v-model="tab" class="trip-dialog-window">
                        <v-window-item value="general">
                            <v-container fluid class="pa-5">
                                <v-card max-width="1260" class="mx-auto" variant="outlined">
                                    <v-card-text><v-row dense>
                                        <v-col cols="12" md="3"><v-text-field v-model="form.number" label="Номер (авто, если пусто)" :error-messages="api.firstError('number')" /></v-col>
                                        <v-col cols="12" md="3"><v-select v-model="form.status" :items="statuses" label="Статус *" /></v-col>
                                        <v-col cols="12" md="6"><v-autocomplete v-model="form.vehicle_id" :items="vehicles" :item-title="item => `${item.name} · ${item.registration_number}`" item-value="id" label="Автомобиль" clearable :error-messages="api.firstError('vehicle_id')" /></v-col>
                                        <v-col v-if="vehicleWarning" cols="12"><v-alert type="warning" variant="tonal"><div>Автомобиль в ремонте или неактивен.</div><v-checkbox v-model="form.acknowledge_vehicle_warning" label="Явно подтверждаю назначение" hide-details /></v-alert></v-col>
                                        <v-col cols="12" md="6"><v-autocomplete v-model="form.carrier_entity_id" :items="entities" item-title="name" item-value="id" label="Перевозчик" clearable @update:search="delayedReference('entities', $event)" /></v-col>
                                        <v-col cols="12" md="6"><v-autocomplete v-model="form.responsible_user_id" :items="users" item-title="name" item-value="id" label="Ответственный" clearable @update:search="delayedReference('users', $event)" /></v-col>
                                        <v-col cols="12" md="3"><v-text-field v-model="form.planned_departure_at" type="datetime-local" label="План: отправление" /></v-col>
                                        <v-col cols="12" md="3"><v-text-field v-model="form.planned_arrival_at" type="datetime-local" label="План: прибытие" :error-messages="api.firstError('planned_arrival_at')" /></v-col>
                                        <v-col cols="12" md="3"><v-text-field v-model="form.actual_departure_at" type="datetime-local" label="Факт: отправление" /></v-col>
                                        <v-col cols="12" md="3"><v-text-field v-model="form.actual_arrival_at" type="datetime-local" label="Факт: прибытие" :error-messages="api.firstError('actual_arrival_at')" /></v-col>
                                        <v-col cols="12"><v-divider class="my-2" /></v-col>
                                        <v-col cols="12" md="6"><v-textarea v-model="form.cargo_description" label="Описание груза" rows="2" /></v-col>
                                        <v-col cols="6" md="2"><v-text-field v-model.number="form.cargo_weight_kg" type="number" min="0" label="Вес, кг" :error-messages="api.firstError('cargo_weight_kg')" /></v-col>
                                        <v-col cols="6" md="2"><v-text-field v-model.number="form.cargo_volume_m3" type="number" min="0" step="0.001" label="Объём, м³" /></v-col>
                                        <v-col cols="6" md="2"><v-text-field v-model.number="form.pallet_count" type="number" min="0" label="Паллет" /></v-col>
                                        <v-col cols="6" md="3"><v-select v-model="form.temperature_mode" :items="temperatures" label="Температурный режим" /></v-col>
                                        <v-col cols="6" md="2"><v-text-field v-model.number="form.temperature_min_c" type="number" label="Мин., °C" /></v-col>
                                        <v-col cols="6" md="2"><v-text-field v-model.number="form.temperature_max_c" type="number" label="Макс., °C" :error-messages="api.firstError('temperature_max_c')" /></v-col>
                                        <v-col cols="12"><v-divider class="my-2" /></v-col>
                                        <v-col cols="6" md="3"><v-text-field v-model.number="form.odometer_start_km" type="number" min="0" step="0.1" label="Одометр старт, км" /></v-col>
                                        <v-col cols="6" md="3"><v-text-field v-model.number="form.odometer_end_km" type="number" min="0" step="0.1" label="Одометр финиш, км" :error-messages="api.firstError('odometer_end_km')" /></v-col>
                                        <v-col cols="6" md="3"><v-text-field v-model.number="form.actual_distance_km" type="number" min="0" step="0.1" label="Фактический пробег, км" hint="Будет пересчитан из одометра, если заполнены оба значения" persistent-hint :error-messages="api.firstError('actual_distance_m')" /></v-col>
                                        <v-col cols="6" md="3"><v-select v-model="form.routing_profile" :items="[{title:'Грузовой',value:'truck'},{title:'Легковой',value:'auto'}]" label="Routing-профиль" /></v-col>
                                        <v-col cols="12"><v-textarea v-model="form.notes" label="Примечание" rows="2" /></v-col>
                                    </v-row></v-card-text>
                                </v-card>
                            </v-container>
                        </v-window-item>

                        <v-window-item value="stops">
                            <v-container fluid class="pa-5"><v-card max-width="1260" class="mx-auto" variant="outlined">
                                <v-card-title class="d-flex justify-space-between align-center"><span>Последовательность остановок</span><v-btn v-if="canManageTrip" color="green-darken-2" variant="tonal" prepend-icon="mdi-plus" @click="addStop">Добавить точку</v-btn></v-card-title>
                                <v-card-text>
                                    <v-alert type="info" variant="tonal" density="compact" class="mb-4">Координаты конкретного адреса приоритетны. Если они пусты, сервер использует только проверенную логистическую точку города и сохранит её snapshot.</v-alert>
                                    <v-card v-for="(stop, index) in form.stops" :key="index" variant="tonal" class="mb-3">
                                        <v-card-text><v-row dense align="center">
                                            <v-col cols="12" md="1"><div class="text-h6">{{ index + 1 }}</div><div class="text-caption">{{ index === 0 ? 'Начало' : index === form.stops.length - 1 ? 'Финиш' : 'Промежуточная' }}</div></v-col>
                                            <v-col cols="12" md="4"><v-autocomplete v-model="stop.city_id" :items="cities" item-title="label" item-value="id" label="Город *" :rules="[v => !!v || 'Выберите город']" :error-messages="api.firstError(`stops.${index}.city_id`)" @update:search="delayedReference('cities', $event)" /></v-col>
                                            <v-col cols="12" md="3"><v-select v-model="stop.operation_type" :items="operations" label="Операция" /></v-col>
                                            <v-col cols="12" md="4"><v-text-field v-model="stop.address" label="Адрес / площадка" /></v-col>
                                            <v-col cols="6" md="2"><v-text-field v-model.number="stop.latitude" type="number" step="0.0000001" label="Широта" /></v-col>
                                            <v-col cols="6" md="2"><v-text-field v-model.number="stop.longitude" type="number" step="0.0000001" label="Долгота" /></v-col>
                                            <v-col cols="6" md="2"><v-text-field v-model="stop.planned_arrival_at" type="datetime-local" label="План прибытия" /></v-col>
                                            <v-col cols="6" md="2"><v-text-field v-model="stop.planned_departure_at" type="datetime-local" label="План отправления" /></v-col>
                                            <v-col cols="6" md="2"><v-text-field v-model.number="stop.cargo_weight_change_kg" type="number" label="Изменение груза, кг" /></v-col>
                                            <v-col cols="6" md="2" class="text-right">
                                                <template v-if="canManageTrip">
                                                    <v-btn icon="mdi-arrow-up" size="small" variant="text" :disabled="index === 0" @click="moveStop(index, 'up')" />
                                                    <v-btn icon="mdi-arrow-down" size="small" variant="text" :disabled="index === form.stops.length - 1" @click="moveStop(index, 'down')" />
                                                    <v-btn icon="mdi-delete-outline" size="small" variant="text" color="red" :disabled="form.stops.length <= 2" @click="removeStop(index)" />
                                                </template>
                                            </v-col>
                                        </v-row></v-card-text>
                                    </v-card>
                                    <div v-if="api.firstError('stops')" class="text-error text-caption">{{ api.firstError('stops') }}</div>
                                </v-card-text>
                            </v-card></v-container>
                        </v-window-item>

                        <v-window-item value="expenses">
                            <v-container v-if="current?.id" fluid class="pa-5"><v-card max-width="1260" class="mx-auto" variant="outlined"><v-card-text><TripExpensesPanel :trip="current" :can-manage="canManageExpenses" @changed="emit('saved', current)" /></v-card-text></v-card></v-container>
                        </v-window-item>

                        <v-window-item value="routes">
                            <v-container v-if="current?.id" fluid class="pa-5"><v-card max-width="1260" class="mx-auto" variant="outlined">
                                <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2"><span>Плановый автомобильный маршрут</span><div class="d-flex ga-2"><v-btn variant="outlined" prepend-icon="mdi-refresh" @click="loadRoutes">История</v-btn><v-btn v-if="canManageTrip" color="green-darken-2" variant="flat" prepend-icon="mdi-routes" :loading="api.isPending('route-calculate')" @click="calculateRoute(Boolean(current.current_route))">{{ current.current_route ? 'Пересчитать' : 'Рассчитать' }}</v-btn></div></v-card-title>
                                <v-card-text>
                                    <v-alert v-if="routeRun" :type="routeRun.status === 'failed' || routeRun.status === 'partial' ? 'error' : routeRun.status === 'completed' ? 'success' : 'info'" variant="tonal" class="mb-4">
                                        Запуск {{ routeRun.id }} · {{ routeRun.status }} · {{ routeRun.progress_percent }}%
                                        <v-progress-linear :model-value="routeRun.progress_percent" class="mt-2" />
                                        <div v-if="routeRun.last_error" class="mt-1">{{ routeRun.last_error }}</div>
                                    </v-alert>
                                    <v-row v-if="current.current_route" dense class="mb-4">
                                        <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Расстояние</div><strong>{{ formatKm(current.current_route.distance_m) }}</strong></v-card-text></v-card></v-col>
                                        <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Ориентировочно</div><strong>{{ formatDuration(current.current_route.duration_s) }}</strong></v-card-text></v-card></v-col>
                                        <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Провайдер</div><strong>{{ current.current_route.provider }}</strong></v-card-text></v-card></v-col>
                                        <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">OSM-граф</div><strong>{{ current.current_route.osm_data_version || 'не задан' }}</strong></v-card-text></v-card></v-col>
                                    </v-row>
                                    <v-table density="compact" class="border rounded"><thead><tr><th>Версия</th><th>Статус</th><th>Расстояние</th><th>Время</th><th>Engine / OSM</th><th>Дата</th></tr></thead><tbody>
                                        <tr v-for="route in routes" :key="route.id"><td>#{{ route.id }} <v-chip v-if="route.is_current" size="x-small" color="green">текущая</v-chip></td><td><v-chip :color="routeStatusColor(route.status)" variant="tonal" size="x-small">{{ route.status }}</v-chip><div v-if="route.routing_options?.error_message" class="text-caption text-error mt-1">{{ route.routing_options.error_message }}</div></td><td>{{ formatKm(route.distance_m) }}</td><td>{{ formatDuration(route.duration_s) }}</td><td>{{ route.routing_engine_version || '—' }} / {{ route.osm_data_version || '—' }}</td><td>{{ formatDate(route.calculated_at) }}</td></tr>
                                        <tr v-if="!routes.length"><td colspan="6" class="logistics-empty">Маршрут ещё не рассчитывался.</td></tr>
                                    </tbody></v-table>
                                </v-card-text>
                            </v-card></v-container>
                        </v-window-item>
                    </v-window>
                </v-form>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.trip-dialog-window { min-height: calc(100vh - 120px); }
</style>
