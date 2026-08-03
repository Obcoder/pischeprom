<script setup>
import axios from 'axios'
import { computed, inject, onActivated, onBeforeUnmount, onDeactivated, onMounted, reactive, ref, watch } from 'vue'
import LogisticsMap from './LogisticsMap.vue'

const api = inject('logisticsApi')
const mapRef = ref(null)
const config = ref(null)
const cities = ref(emptyCollection())
const entities = ref(emptyCollection())
const routes = ref(emptyCollection())
const stops = ref(emptyCollection())
const trips = ref([])
const tripOptions = ref([])
const vehicles = ref([])
const cityOptions = ref([])
const selectedTripIds = ref([])
const loadingLayers = ref(false)
const loadingRoutes = ref(false)
const error = ref('')
const meta = ref(null)
const viewport = reactive({ bbox: [19, 41, -169, 82], zoom: 2.3 })
const filters = reactive({ date_from: '', date_to: '', status: [], vehicle_id: null, city_id: null })
const layers = reactive({ cities: true, entities: false, trips: true })
let layerController = null
let routeController = null
let loadTimer = null
let cityReferenceTimer = null
let vehicleReferenceTimer = null
let cityReferenceSequence = 0
let vehicleReferenceSequence = 0

const statuses = [
    { title: 'Черновик', value: 'draft' }, { title: 'Запланирован', value: 'planned' },
    { title: 'В пути', value: 'in_progress' }, { title: 'Завершён', value: 'completed' },
    { title: 'Отменён', value: 'cancelled' },
]
const nothingVisible = computed(() => !cities.value.features.length && !entities.value.features.length && !trips.value.length)
const selectedTrips = computed(() => selectedTripIds.value.map((id) => tripOptions.value.find((trip) => trip.id === id)).filter(Boolean))
const missingEntityCoordinateCount = computed(() => {
    const value = meta.value?.missing_coordinates?.entities
    return value == null || Number.isNaN(Number(value)) ? null : Number(value)
})
const entityLayerIsEmpty = computed(() => layers.entities && !loadingLayers.value && !entities.value.features.length)

function emptyCollection() { return { type: 'FeatureCollection', features: [] } }
function formatTrip(item) { return `${item.number}${item.route_summary ? ` · ${item.route_summary}` : ''}` }

async function initialize() {
    try {
        const response = await axios.get('/api/logistics/map/config')
        config.value = response.data?.data || response.data
        await Promise.allSettled([loadReferences(), loadFeatures()])
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Не удалось загрузить конфигурацию карты.'
    }
}

async function loadReferences() {
    await Promise.allSettled([loadVehicleOptions(), loadCityOptions()])
}

function replaceOptionsKeepingSelected(listRef, additions, selectedId) {
    const options = new Map()
    const selected = listRef.value.find((item) => String(item.id) === String(selectedId))
    if (selected) options.set(selected.id, selected)
    additions.filter(Boolean).forEach((item) => options.set(item.id, item))
    listRef.value = [...options.values()]
}

async function loadVehicleOptions(search = '') {
    const sequence = ++vehicleReferenceSequence
    const response = await api.request(`map-vehicles-${sequence}`, {
        method: 'get', url: '/api/logistics/vehicles', params: { search, per_page: 100 },
    })
    if (sequence !== vehicleReferenceSequence) return
    replaceOptionsKeepingSelected(vehicles, response?.data || [], filters.vehicle_id)
}

async function loadCityOptions(search = '') {
    const sequence = ++cityReferenceSequence
    const response = await api.request(`map-cities-reference-${sequence}`, {
        method: 'get', url: '/api/logistics/references/cities', params: { search, limit: 75 },
    })
    if (sequence !== cityReferenceSequence) return
    replaceOptionsKeepingSelected(cityOptions, response?.data || [], filters.city_id)
}

function delayedVehicleReference(search) {
    clearTimeout(vehicleReferenceTimer)
    vehicleReferenceTimer = setTimeout(() => loadVehicleOptions(search || '').catch(() => {}), 250)
}

function delayedCityReference(search) {
    clearTimeout(cityReferenceTimer)
    cityReferenceTimer = setTimeout(() => loadCityOptions(search || '').catch(() => {}), 250)
}

async function loadFeatures() {
    if (!config.value) return
    layerController?.abort()
    layerController = new AbortController()
    const currentController = layerController
    const requestedLayers = [layers.cities ? 'cities' : null, layers.trips ? 'trips' : null, layers.entities ? 'entities' : null].filter(Boolean)
    if (!requestedLayers.length) {
        cities.value = emptyCollection()
        entities.value = emptyCollection()
        trips.value = []
        meta.value = null
        loadingLayers.value = false
        return
    }
    loadingLayers.value = true
    error.value = ''

    try {
        const response = await axios.get('/api/logistics/map/features', {
            signal: currentController.signal,
            params: {
                bbox: viewport.bbox.join(','), zoom: viewport.zoom, layers: requestedLayers,
                status: filters.status.length ? filters.status : undefined,
                vehicle_id: filters.vehicle_id || undefined, city_id: filters.city_id || undefined,
                date_from: filters.date_from || undefined, date_to: filters.date_to || undefined,
            },
        })
        const data = response.data?.data || response.data
        cities.value = data.cities || emptyCollection()
        entities.value = data.entities || emptyCollection()
        trips.value = data.trips || []
        meta.value = data.meta || null
        mergeTripOptions(trips.value)
    } catch (requestError) {
        if (requestError?.code !== 'ERR_CANCELED') {
            error.value = requestError?.response?.data?.message || 'Не удалось обновить слои карты.'
        }
    } finally {
        if (layerController === currentController) loadingLayers.value = false
    }
}

function mergeTripOptions(items) {
    const selected = new Set(selectedTripIds.value.map(String))
    const options = new Map(tripOptions.value
        .filter((trip) => selected.has(String(trip.id)))
        .map((trip) => [trip.id, trip]))
    items.forEach((trip) => options.set(trip.id, trip))
    tripOptions.value = [...options.values()]
}

async function loadSelectedRoutes() {
    routeController?.abort()
    routeController = new AbortController()
    const currentController = routeController
    routes.value = emptyCollection()
    stops.value = emptyCollection()
    if (!selectedTripIds.value.length) {
        loadingRoutes.value = false
        return
    }
    loadingRoutes.value = true

    try {
        const results = await Promise.allSettled(selectedTripIds.value.map((tripId) => axios.get(
            `/api/logistics/trips/${tripId}/map`,
            { signal: currentController.signal },
        )))
        const routeFeatures = []
        const stopFeatures = []
        const failures = []
        results.forEach((result, index) => {
            if (result.status !== 'fulfilled') {
                if (result.reason?.code !== 'ERR_CANCELED') failures.push(selectedTripIds.value[index])
                return
            }
            const data = result.value.data?.data || result.value.data
            if (data.route_feature) routeFeatures.push(data.route_feature)
            stopFeatures.push(...(data.stops?.features || []))
        })
        routes.value = { type: 'FeatureCollection', features: routeFeatures }
        stops.value = { type: 'FeatureCollection', features: stopFeatures }
        if (failures.length) error.value = `Не удалось загрузить геометрию рейсов: ${failures.join(', ')}.`
    } finally {
        if (routeController === currentController) loadingRoutes.value = false
    }
}

function viewportChanged(value) {
    viewport.bbox = value.bbox
    viewport.zoom = Number(value.zoom.toFixed(2))
    scheduleFeatures(180)
}

function scheduleFeatures(delay = 300) {
    clearTimeout(loadTimer)
    loadTimer = setTimeout(loadFeatures, delay)
}

function showRussia() { mapRef.value?.showRussia() }
function showSelection() { mapRef.value?.fitData() }
function setMapError(value) { if (!error.value) error.value = value }

watch(filters, () => scheduleFeatures(), { deep: true })
watch(() => [layers.cities, layers.entities, layers.trips], () => scheduleFeatures(50))
watch(selectedTripIds, (value, previous = []) => {
    if (value.length > 20) {
        selectedTripIds.value = previous.slice(0, 20)
        error.value = 'Одновременно можно показать не более 20 рейсов.'
        return
    }
    loadSelectedRoutes()
}, { deep: true })
onMounted(initialize)
onActivated(() => mapRef.value?.resize())
onDeactivated(() => {
    layerController?.abort()
    routeController?.abort()
    clearTimeout(loadTimer)
    clearTimeout(cityReferenceTimer)
    clearTimeout(vehicleReferenceTimer)
})
onBeforeUnmount(() => {
    layerController?.abort()
    routeController?.abort()
    clearTimeout(loadTimer)
    clearTimeout(cityReferenceTimer)
    clearTimeout(vehicleReferenceTimer)
})
</script>

<template>
    <section>
        <v-alert v-if="config && !config.enabled" type="warning" variant="tonal" class="mb-4">
            Активный проверенный PMTiles-релиз ещё не подключён. Прикладные точки доступны на резервном фоне; production-подложка включится после безопасной активации согласованного GIS-релиза.
        </v-alert>
        <v-alert v-if="error" type="error" variant="tonal" closable class="mb-4" @click:close="error = ''">{{ error }}</v-alert>

        <v-card variant="outlined" class="mb-4">
            <v-card-text>
                <div class="logistics-toolbar mb-0">
                    <div class="logistics-toolbar__filters">
                        <v-text-field v-model="filters.date_from" type="date" label="Период с" density="compact" variant="outlined" hide-details style="max-width: 165px" />
                        <v-text-field v-model="filters.date_to" type="date" label="по" density="compact" variant="outlined" hide-details style="max-width: 165px" />
                        <v-select v-model="filters.status" :items="statuses" label="Статус рейса" multiple chips closable-chips density="compact" variant="outlined" hide-details clearable style="min-width: 210px" />
                        <v-autocomplete v-model="filters.vehicle_id" :items="vehicles" :item-title="item => `${item.name} · ${item.registration_number}`" item-value="id" label="Авто" density="compact" variant="outlined" hide-details clearable style="min-width: 210px" @update:search="delayedVehicleReference($event)" />
                        <v-autocomplete v-model="filters.city_id" :items="cityOptions" item-title="label" item-value="id" label="Город" density="compact" variant="outlined" hide-details clearable style="min-width: 200px" @update:search="delayedCityReference($event)" />
                    </div>
                    <div class="logistics-toolbar__actions">
                        <v-btn variant="outlined" prepend-icon="mdi-map-outline" @click="showRussia">Вся Россия</v-btn>
                        <v-btn variant="tonal" prepend-icon="mdi-fit-to-screen-outline" :disabled="!selectedTripIds.length" @click="showSelection">Выбранный маршрут</v-btn>
                        <v-btn icon="mdi-refresh" variant="outlined" :loading="loadingLayers" @click="loadFeatures" />
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-row dense>
            <v-col cols="12" lg="9">
                <v-card variant="outlined" class="pa-2">
                    <LogisticsMap
                        v-if="config"
                        ref="mapRef"
                        :config="config"
                        :cities="cities"
                        :entities="entities"
                        :routes="routes"
                        :stops="stops"
                        :show-cities="layers.cities"
                        :show-entities="layers.entities"
                        :show-routes="layers.trips"
                        :loading="loadingLayers || loadingRoutes"
                        @viewport-change="viewportChanged"
                        @error="setMapError"
                    />
                    <v-skeleton-loader v-else type="image" height="620" />
                </v-card>
            </v-col>
            <v-col cols="12" lg="3">
                <v-card variant="outlined" class="mb-3">
                    <v-card-title>Рейсы на карте</v-card-title>
                    <v-card-text>
                        <v-autocomplete
                            v-model="selectedTripIds"
                            :items="tripOptions"
                            :item-title="formatTrip"
                            item-value="id"
                            label="Выбранные рейсы"
                            multiple chips closable-chips clearable
                            :counter="20"
                            hint="Геометрия загружается только для выбранных рейсов"
                            persistent-hint
                        />
                        <div v-if="selectedTrips.length" class="map-trip-list mt-3">
                            <div v-for="trip in selectedTrips" :key="trip.id">
                                <strong>{{ trip.number }}</strong>
                                <small>{{ trip.route_summary || 'Остановки не заданы' }}</small>
                                <v-chip size="x-small" variant="tonal">{{ trip.current_route?.status || 'без маршрута' }}</v-chip>
                            </div>
                        </div>
                        <div v-else class="text-caption text-medium-emphasis mt-3">Выберите рейс, чтобы загрузить сохранённую дорожную линию и пронумерованные остановки.</div>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mb-3">
                    <v-card-title>Слои</v-card-title>
                    <v-card-text>
                        <v-switch v-model="layers.cities" label="Города и логистические точки" color="green" hide-details />
                        <v-switch v-model="layers.trips" label="Рейсы" color="teal" hide-details />
                        <v-switch v-model="layers.entities" color="purple" hide-details :disabled="config && !config.entity_layer_available">
                            <template #label>
                                <span>Контрагенты</span>
                                <v-chip v-if="layers.entities && !loadingLayers" size="x-small" variant="tonal" color="purple" class="ml-2">
                                    на карте: {{ entities.features.length }}
                                </v-chip>
                            </template>
                        </v-switch>

                        <v-alert v-if="entityLayerIsEmpty" type="info" variant="tonal" density="compact" class="map-entity-layer-status mt-3">
                            <div class="map-entity-layer-status__content">
                                <div>
                                    <strong>Контрагенты не показаны</strong>
                                    <div v-if="missingEntityCoordinateCount">
                                        Без координат: {{ missingEntityCoordinateCount }}. На карту попадают только контрагенты с заполненными широтой и долготой.
                                    </div>
                                    <div v-else>
                                        В текущей области карты нет контрагентов с координатами.
                                    </div>
                                </div>
                                <v-btn href="/Ameise/gis/entities/no-location" color="purple-darken-2" variant="flat" size="small" prepend-icon="mdi-map-marker-plus-outline">
                                    Настроить координаты
                                </v-btn>
                            </div>
                        </v-alert>

                        <div v-else-if="layers.entities && missingEntityCoordinateCount" class="map-entity-layer-hint mt-2">
                            <span>Без координат: {{ missingEntityCoordinateCount }}</span>
                            <v-btn href="/Ameise/gis/entities/no-location" color="purple-darken-2" variant="text" size="small">
                                Настроить
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <v-card variant="outlined">
                    <v-card-title>Легенда</v-card-title>
                    <v-card-text class="map-legend">
                        <div><i class="map-legend__dot map-legend__dot--city" />Город / точка</div>
                        <div><i class="map-legend__dot map-legend__dot--entity" />Контрагент</div>
                        <div><i class="map-legend__line map-legend__line--current" />Текущий маршрут</div>
                        <div><i class="map-legend__line map-legend__line--history" />Историческая версия</div>
                        <div><i class="map-legend__stop">1</i>Остановка рейса</div>
                        <div class="text-caption text-medium-emphasis mt-3">Карта показывает сохранённый план маршрута и известные точки, а не GPS-положение автомобиля.</div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-alert v-if="nothingVisible && !loadingLayers" type="info" variant="tonal" class="mt-4">
            В текущей области и с выбранными фильтрами объекты не найдены.
        </v-alert>
        <v-alert v-if="meta?.truncated && Object.values(meta.truncated).some(Boolean)" type="info" variant="tonal" density="compact" class="mt-3">
            Часть объектов скрыта серверным лимитом. Увеличьте масштаб карты для более точной выборки.
        </v-alert>
    </section>
</template>

<style scoped>
.map-trip-list { display: grid; gap: 10px; max-height: 220px; overflow: auto; }
.map-trip-list > div { display: grid; grid-template-columns: 1fr auto; gap: 2px 7px; padding-bottom: 8px; border-bottom: 1px solid #e4e8e5; }
.map-trip-list small { grid-column: 1 / -1; color: #66736b; }
.map-entity-layer-status__content { display: grid; gap: 12px; }
.map-entity-layer-status__content > div { display: grid; gap: 3px; }
.map-entity-layer-status__content .v-btn { justify-self: start; }
.map-entity-layer-hint { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: #6f3c78; font-size: .78rem; }
.map-legend { display: grid; gap: 10px; }
.map-legend > div { display: flex; align-items: center; gap: 9px; }
.map-legend__dot { width: 12px; height: 12px; border: 2px solid #fff; border-radius: 50%; box-shadow: 0 0 0 1px #aaa; }
.map-legend__dot--city { background: #2e7d32; }
.map-legend__dot--entity { background: #8e24aa; }
.map-legend__line { width: 27px; height: 0; border-top: 4px solid; }
.map-legend__line--current { border-color: #087f5b; }
.map-legend__line--history { border-color: #7b1fa2; border-top-style: dashed; }
.map-legend__stop { display: grid; width: 23px; height: 23px; place-items: center; border-radius: 50%; background: #087f5b; color: #fff; font-size: 11px; font-style: normal; font-weight: 700; }
</style>
