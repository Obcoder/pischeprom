<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { useGisApi } from '@/Composables/gis/useGisApi'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    provider: {
        type: String,
        required: true,
    },
    providerLabel: {
        type: String,
        required: true,
    },
    mapApiKey: {
        type: String,
        default: '',
    },
    mapScriptUrl: {
        type: String,
        default: '',
    },
    apiKeyConfigured: {
        type: Boolean,
        default: false,
    },
    defaultCenter: {
        type: Object,
        default: () => ({ lat: 55.751244, lon: 37.618423 }),
    },
    defaultZoom: {
        type: Number,
        default: 5,
    },
})

const api = useGisApi()

const mapContainer = ref(null)
const map = ref(null)
const routeOverlay = ref(null)
const popup = ref(null)
const markers = ref([])
const entities = ref([])
const selectedEntity = ref(null)
const routePoints = ref([])
const routePreview = ref(null)
const draftName = ref('')
const loading = ref(false)
const mapLoading = ref(false)
const savingLocation = ref(false)
const routeLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const filters = reactive({
    search: '',
    type: '',
    status: '',
    city: '',
    manager_id: '',
})

const manualForm = reactive({
    address_text: '',
    lat: '',
    lon: '',
    precision_level: 'exact',
})

const pageTitle = computed(() => `GIS CRM: ${props.providerLabel}`)
const providerForRoute = computed(() => props.provider)

function responseMessage(error, fallback) {
    return error.response?.data?.error?.message
        || error.response?.data?.message
        || fallback
}

function scriptId() {
    return `gis-map-sdk-${props.provider}`
}

function providerScriptUrl() {
    if (props.provider === 'yandex') {
        const separator = props.mapScriptUrl.includes('?') ? '&' : '?'
        return `${props.mapScriptUrl}${separator}apikey=${encodeURIComponent(props.mapApiKey)}&lang=ru_RU`
    }

    return props.mapScriptUrl
}

function loadScript(src, id) {
    if (typeof window === 'undefined') {
        return Promise.resolve()
    }

    const existing = document.getElementById(id)

    if (existing) {
        return Promise.resolve(existing)
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.id = id
        script.src = src
        script.async = true
        script.onload = () => resolve(script)
        script.onerror = () => reject(new Error('Не удалось загрузить SDK карты.'))
        document.head.appendChild(script)
    })
}

async function loadEntities() {
    loading.value = true
    errorMessage.value = ''

    try {
        const { items } = await api.getEntities({ ...filters })
        entities.value = items || []
        await nextTick()
        renderMarkers()
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось загрузить entities для карты.')
    } finally {
        loading.value = false
    }
}

async function initMap() {
    if (!props.apiKeyConfigured) {
        errorMessage.value = `API-ключ для ${props.providerLabel} не задан. Карта не будет загружена.`
        return
    }

    if (!props.mapScriptUrl) {
        errorMessage.value = `URL SDK для ${props.providerLabel} не настроен.`
        return
    }

    mapLoading.value = true

    try {
        await loadScript(providerScriptUrl(), scriptId())

        if (props.provider === 'yandex') {
            await initYandexMap()
        } else {
            initTwoGisMap()
        }

        renderMarkers()
    } catch (error) {
        console.error(error)
        errorMessage.value = error.message || `Не удалось инициализировать ${props.providerLabel}.`
    } finally {
        mapLoading.value = false
    }
}

function initYandexMap() {
    return new Promise((resolve, reject) => {
        if (!window.ymaps) {
            reject(new Error('SDK Яндекс Карт не найден после загрузки.'))
            return
        }

        window.ymaps.ready(() => {
            map.value = new window.ymaps.Map(mapContainer.value, {
                center: [props.defaultCenter.lat, props.defaultCenter.lon],
                zoom: props.defaultZoom,
                controls: ['zoomControl', 'typeSelector', 'fullscreenControl'],
            })

            map.value.events.add('click', (event) => {
                const coords = event.get('coords')
                applyClickedCoordinates(coords[0], coords[1])
            })

            resolve()
        })
    })
}

function initTwoGisMap() {
    if (window.mapgl) {
        map.value = new window.mapgl.Map(mapContainer.value, {
            center: [props.defaultCenter.lon, props.defaultCenter.lat],
            zoom: props.defaultZoom,
            key: props.mapApiKey,
        })

        map.value.on('click', (event) => {
            const lngLat = event.lngLat || event.point || []
            const lon = Array.isArray(lngLat) ? lngLat[0] : lngLat.lng
            const lat = Array.isArray(lngLat) ? lngLat[1] : lngLat.lat
            applyClickedCoordinates(lat, lon)
        })

        return
    }

    if (window.DG) {
        window.DG.then(() => {
            map.value = window.DG.map(mapContainer.value, {
                center: [props.defaultCenter.lat, props.defaultCenter.lon],
                zoom: props.defaultZoom,
            })

            map.value.on('click', (event) => {
                applyClickedCoordinates(event.latlng.lat, event.latlng.lng)
            })
        })

        return
    }

    throw new Error('SDK 2ГИС не найден после загрузки.')
}

function renderMarkers() {
    if (!map.value) {
        return
    }

    clearMarkers()

    if (props.provider === 'yandex') {
        renderYandexMarkers()
    } else {
        renderTwoGisMarkers()
    }

    drawRouteGeometry()
}

function renderYandexMarkers() {
    map.value.geoObjects.removeAll()

    entities.value.forEach((entity) => {
        if (!entity.lat || !entity.lon) {
            return
        }

        const placemark = new window.ymaps.Placemark(
            [entity.lat, entity.lon],
            {
                balloonContentHeader: entity.name,
                balloonContentBody: popupHtml(entity),
                hintContent: entity.name,
            },
            {
                preset: entity.is_confirmed ? 'islands#darkGreenDotIcon' : 'islands#orangeDotIcon',
            },
        )

        placemark.events.add('click', () => selectEntity(entity))
        map.value.geoObjects.add(placemark)
        markers.value.push(placemark)
    })
}

function renderTwoGisMarkers() {
    entities.value.forEach((entity) => {
        if (!entity.lat || !entity.lon) {
            return
        }

        if (window.mapgl) {
            const marker = new window.mapgl.Marker(map.value, {
                coordinates: [Number(entity.lon), Number(entity.lat)],
            })

            marker.on('click', () => {
                selectEntity(entity)
                openTwoGisPopup(entity)
            })

            markers.value.push(marker)
            return
        }

        if (window.DG && map.value) {
            const marker = window.DG.marker([entity.lat, entity.lon])
                .addTo(map.value)
                .bindPopup(popupHtml(entity))
                .on('click', () => selectEntity(entity))

            markers.value.push(marker)
        }
    })
}

function openTwoGisPopup(entity) {
    if (!window.mapgl?.Popup) {
        return
    }

    try {
        if (popup.value?.destroy) {
            popup.value.destroy()
        }

        popup.value = new window.mapgl.Popup(map.value, {
            coordinates: [Number(entity.lon), Number(entity.lat)],
            html: popupHtml(entity),
        })
    } catch (error) {
        console.warn('2GIS popup is not available in current SDK mode.', error)
    }
}

function popupHtml(entity) {
    return `
        <div style="max-width: 260px">
            <strong>${escapeHtml(entity.name || 'Entity')}</strong><br>
            <span>${escapeHtml(entity.type || 'тип не задан')}</span><br>
            <small>${escapeHtml(entity.address || 'адрес не задан')}</small>
        </div>
    `
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}

function clearMarkers() {
    markers.value.forEach((marker) => {
        if (marker?.destroy) {
            marker.destroy()
            return
        }

        if (marker?.remove) {
            marker.remove()
        }
    })

    markers.value = []

    if (props.provider === 'yandex' && map.value?.geoObjects) {
        map.value.geoObjects.removeAll()
    }
}

function selectEntity(entity) {
    selectedEntity.value = entity
    manualForm.address_text = entity.address || ''
    manualForm.lat = entity.lat ?? ''
    manualForm.lon = entity.lon ?? ''
    manualForm.precision_level = entity.is_confirmed ? 'exact' : 'unknown'

    if (map.value) {
        if (props.provider === 'yandex') {
            map.value.setCenter([Number(entity.lat), Number(entity.lon)], Math.max(map.value.getZoom(), 11))
        } else if (window.mapgl && map.value.setCenter) {
            map.value.setCenter([Number(entity.lon), Number(entity.lat)])
            map.value.setZoom(Math.max(map.value.getZoom?.() || 11, 11))
        } else if (map.value.setView) {
            map.value.setView([Number(entity.lat), Number(entity.lon)], 11)
        }
    }
}

function applyClickedCoordinates(lat, lon) {
    if (!selectedEntity.value || !Number.isFinite(Number(lat)) || !Number.isFinite(Number(lon))) {
        return
    }

    manualForm.lat = Number(lat).toFixed(7)
    manualForm.lon = Number(lon).toFixed(7)
}

async function saveSelectedLocation() {
    if (!selectedEntity.value) {
        return
    }

    savingLocation.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const response = await api.saveLocation(selectedEntity.value.entity_id, { ...manualForm })
        successMessage.value = response.message || 'Координаты сохранены.'
        selectedEntity.value = response.item
        await loadEntities()
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось сохранить координаты.')
    } finally {
        savingLocation.value = false
    }
}

function addSelectedToRoute() {
    if (!selectedEntity.value) {
        return
    }

    if (routePoints.value.some((point) => point.entity_id === selectedEntity.value.entity_id)) {
        return
    }

    routePoints.value.push({
        entity_id: selectedEntity.value.entity_id,
        title: selectedEntity.value.name,
        address_text: selectedEntity.value.address,
        lat: selectedEntity.value.lat,
        lon: selectedEntity.value.lon,
        point_type: 'entity',
    })
}

function removeRoutePoint(index) {
    routePoints.value.splice(index, 1)
    routePreview.value = null
    drawRouteGeometry()
}

async function previewSelectedRoute() {
    routeLoading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        routePreview.value = await api.previewRoute({
            provider: providerForRoute.value,
            transport_mode: 'car',
            points: routePoints.value,
        })

        drawRouteGeometry()
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось построить маршрут.')
    } finally {
        routeLoading.value = false
    }
}

async function saveDraft() {
    routeLoading.value = true
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const response = await api.saveRouteDraft({
            name: draftName.value || `Маршрут ${new Date().toLocaleString('ru-RU')}`,
            provider: providerForRoute.value,
            transport_mode: 'car',
            points: routePoints.value,
        })

        successMessage.value = response.message || 'Черновик маршрута сохранён.'
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось сохранить черновик маршрута.')
    } finally {
        routeLoading.value = false
    }
}

function drawRouteGeometry() {
    if (!map.value) {
        return
    }

    clearRouteOverlay()

    const coordinates = routePreview.value?.route_geometry_json?.coordinates || []

    if (coordinates.length < 2) {
        return
    }

    if (props.provider === 'yandex' && window.ymaps) {
        routeOverlay.value = new window.ymaps.Polyline(
            coordinates.map(([lon, lat]) => [lat, lon]),
            {},
            {
                strokeColor: '#b91c1c',
                strokeWidth: 4,
                strokeOpacity: 0.85,
            },
        )
        map.value.geoObjects.add(routeOverlay.value)
        return
    }

    if (window.mapgl?.Polyline) {
        routeOverlay.value = new window.mapgl.Polyline(map.value, {
            coordinates,
            width: 4,
            color: '#b91c1c',
        })
    }
}

function clearRouteOverlay() {
    if (routeOverlay.value?.destroy) {
        routeOverlay.value.destroy()
    } else if (routeOverlay.value && props.provider === 'yandex' && map.value?.geoObjects) {
        map.value.geoObjects.remove(routeOverlay.value)
    }

    routeOverlay.value = null
}

function formatDistance(value) {
    if (!value && value !== 0) {
        return '-'
    }

    return value >= 1000 ? `${(value / 1000).toFixed(1)} км` : `${value} м`
}

function formatDuration(value) {
    if (!value && value !== 0) {
        return '-'
    }

    const minutes = Math.round(value / 60)
    return minutes >= 60 ? `${Math.floor(minutes / 60)} ч ${minutes % 60} мин` : `${minutes} мин`
}

watch(() => props.provider, () => {
    routePoints.value = []
    routePreview.value = null
})

onMounted(async () => {
    await loadEntities()
    await initMap()
})

onBeforeUnmount(() => {
    clearRouteOverlay()
    clearMarkers()

    if (popup.value?.destroy) {
        popup.value.destroy()
    }

    if (map.value?.destroy) {
        map.value.destroy()
    }
})
</script>

<template>
    <Head :title="pageTitle" />

    <div class="gis-page">
        <section class="gis-page__hero">
            <div>
                <p class="gis-page__eyebrow">GIS CRM MVP</p>
                <h1>{{ providerLabel }}</h1>
                <p>
                    Одинаковые данные `entities`, координаты из `entity_locations`, фильтры и черновой маршрут.
                </p>
            </div>

            <div class="gis-page__links">
                <v-btn href="/Ameise/gis" variant="outlined" color="red-darken-4">
                    Управление
                </v-btn>
                <v-btn href="/Ameise/gis/2gis" :variant="provider === '2gis' ? 'flat' : 'tonal'" color="red-darken-4">
                    2ГИС
                </v-btn>
                <v-btn href="/Ameise/gis/yandex" :variant="provider === 'yandex' ? 'flat' : 'tonal'" color="red-darken-4">
                    Яндекс
                </v-btn>
                <v-btn href="/Ameise/gis/entities/no-location" variant="outlined" color="red-darken-4">
                    Без координат
                </v-btn>
            </div>
        </section>

        <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
            {{ errorMessage }}
        </v-alert>
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4">
            {{ successMessage }}
        </v-alert>

        <section class="gis-filters">
            <v-text-field v-model="filters.search" label="Поиск" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.type" label="Тип/classification" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.status" label="Статус" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.city" label="Город" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.manager_id" label="Manager ID" density="compact" variant="outlined" hide-details />
            <v-btn color="red-darken-4" :loading="loading" @click="loadEntities">
                Применить
            </v-btn>
        </section>

        <section class="gis-workspace">
            <div class="gis-map-shell">
                <div ref="mapContainer" class="gis-map" />
                <div v-if="mapLoading" class="gis-map__loading">
                    Загрузка карты...
                </div>
                <div v-if="!apiKeyConfigured" class="gis-map__empty">
                    Добавьте API-ключ {{ providerLabel }} в env, чтобы включить карту.
                </div>
            </div>

            <aside class="gis-panel">
                <div class="gis-panel__section">
                    <h2>Точки на карте</h2>
                    <div class="gis-panel__meta">{{ entities.length }} entities с координатами</div>

                    <div class="gis-entity-list">
                        <button
                            v-for="entity in entities"
                            :key="entity.entity_id"
                            type="button"
                            class="gis-entity-card"
                            :class="{ 'gis-entity-card--active': selectedEntity?.entity_id === entity.entity_id }"
                            @click="selectEntity(entity)"
                        >
                            <strong>{{ entity.name }}</strong>
                            <span>{{ entity.type || 'Тип не задан' }}</span>
                            <small>{{ entity.address || 'Адрес не задан' }}</small>
                        </button>
                    </div>
                </div>

                <div v-if="selectedEntity" class="gis-panel__section">
                    <h2>{{ selectedEntity.name }}</h2>
                    <p class="gis-panel__muted">{{ selectedEntity.address || 'Адрес не задан' }}</p>

                    <div class="gis-manual-grid">
                        <v-text-field v-model="manualForm.address_text" label="Адрес" density="compact" variant="outlined" hide-details />
                        <v-text-field v-model="manualForm.lat" label="Lat" density="compact" variant="outlined" hide-details />
                        <v-text-field v-model="manualForm.lon" label="Lon" density="compact" variant="outlined" hide-details />
                        <v-select
                            v-model="manualForm.precision_level"
                            :items="['exact', 'building', 'street', 'city', 'unknown']"
                            label="Точность"
                            density="compact"
                            variant="outlined"
                            hide-details
                        />
                    </div>

                    <div class="gis-panel__actions">
                        <v-btn color="red-darken-4" :loading="savingLocation" @click="saveSelectedLocation">
                            Сохранить координаты
                        </v-btn>
                        <v-btn variant="tonal" color="red-darken-4" @click="addSelectedToRoute">
                            В маршрут
                        </v-btn>
                    </div>

                    <p class="gis-panel__hint">
                        Можно кликнуть по карте: координаты выбранной entity подставятся в поля Lat/Lon.
                    </p>
                </div>

                <div class="gis-panel__section">
                    <h2>Черновик маршрута</h2>
                    <v-text-field v-model="draftName" label="Название черновика" density="compact" variant="outlined" hide-details class="mb-3" />

                    <div v-if="routePoints.length" class="gis-route-list">
                        <div v-for="(point, index) in routePoints" :key="`${point.entity_id || point.title}-${index}`" class="gis-route-point">
                            <span>{{ index + 1 }}. {{ point.title }}</span>
                            <button type="button" @click="removeRoutePoint(index)">убрать</button>
                        </div>
                    </div>
                    <p v-else class="gis-panel__muted">Выберите entity на карте и добавьте её в маршрут.</p>

                    <div class="gis-panel__actions">
                        <v-btn color="red-darken-4" :disabled="routePoints.length < 2" :loading="routeLoading" @click="previewSelectedRoute">
                            Preview
                        </v-btn>
                        <v-btn variant="tonal" color="red-darken-4" :disabled="routePoints.length < 2" :loading="routeLoading" @click="saveDraft">
                            Сохранить
                        </v-btn>
                    </div>

                    <div v-if="routePreview" class="gis-route-summary">
                        <strong>{{ formatDistance(routePreview.distance_m) }}</strong>
                        <span>{{ formatDuration(routePreview.duration_sec) }}</span>
                        <small>{{ routePreview.provider_response_summary?.message }}</small>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</template>

<style scoped>
.gis-page {
    min-height: 100vh;
    padding: 24px;
    background: linear-gradient(135deg, #fff7ed 0%, #fef2f2 42%, #f8fafc 100%);
}

.gis-page__hero {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    align-items: flex-end;
    margin-bottom: 18px;
}

.gis-page__hero h1 {
    margin: 0;
    font-size: clamp(32px, 5vw, 64px);
    line-height: 0.95;
    color: #450a0a;
    font-weight: 900;
}

.gis-page__hero p {
    max-width: 720px;
    color: #7f1d1d;
}

.gis-page__eyebrow {
    margin: 0 0 8px;
    color: #991b1b;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.gis-page__links,
.gis-panel__actions,
.gis-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.gis-filters {
    align-items: center;
    padding: 14px;
    margin-bottom: 16px;
    border: 1px solid rgba(127, 29, 29, 0.16);
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(14px);
}

.gis-filters > * {
    min-width: 150px;
    flex: 1 1 150px;
}

.gis-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 420px;
    gap: 16px;
}

.gis-map-shell {
    position: relative;
    overflow: hidden;
    min-height: 720px;
    border-radius: 32px;
    border: 1px solid rgba(127, 29, 29, 0.18);
    box-shadow: 0 24px 80px rgba(69, 10, 10, 0.12);
    background: #f8fafc;
}

.gis-map {
    width: 100%;
    height: 100%;
    min-height: 720px;
}

.gis-map__loading,
.gis-map__empty {
    position: absolute;
    inset: auto 24px 24px 24px;
    padding: 14px 16px;
    border-radius: 18px;
    background: rgba(69, 10, 10, 0.9);
    color: #fff7ed;
}

.gis-panel {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-height: 720px;
    overflow: auto;
}

.gis-panel__section {
    padding: 18px;
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(127, 29, 29, 0.14);
    box-shadow: 0 18px 50px rgba(69, 10, 10, 0.08);
}

.gis-panel__section h2 {
    margin: 0 0 8px;
    color: #450a0a;
    font-weight: 900;
}

.gis-panel__meta,
.gis-panel__muted,
.gis-panel__hint {
    color: #7f1d1d;
    font-size: 13px;
}

.gis-entity-list,
.gis-route-list {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.gis-entity-card {
    display: grid;
    gap: 2px;
    width: 100%;
    padding: 12px;
    border: 1px solid #fee2e2;
    border-radius: 18px;
    background: #fff;
    color: #450a0a;
    text-align: left;
    cursor: pointer;
}

.gis-entity-card--active,
.gis-entity-card:hover {
    border-color: #991b1b;
    background: #fff7ed;
}

.gis-entity-card span,
.gis-entity-card small {
    color: #7f1d1d;
}

.gis-manual-grid {
    display: grid;
    gap: 10px;
    margin: 12px 0;
}

.gis-route-point {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 0;
    border-bottom: 1px solid #fee2e2;
    color: #450a0a;
}

.gis-route-point button {
    color: #991b1b;
}

.gis-route-summary {
    display: grid;
    gap: 4px;
    margin-top: 12px;
    padding: 12px;
    border-radius: 18px;
    background: #fff7ed;
    color: #450a0a;
}

@media (max-width: 1100px) {
    .gis-page__hero {
        align-items: flex-start;
        flex-direction: column;
    }

    .gis-workspace {
        grid-template-columns: 1fr;
    }

    .gis-panel {
        max-height: none;
    }
}
</style>
