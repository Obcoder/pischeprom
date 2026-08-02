<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { loadMapRuntime } from './mapRuntime.js'

const props = defineProps({
    config: { type: Object, default: null },
    cities: { type: Object, default: () => ({ type: 'FeatureCollection', features: [] }) },
    entities: { type: Object, default: () => ({ type: 'FeatureCollection', features: [] }) },
    routes: { type: Object, default: () => ({ type: 'FeatureCollection', features: [] }) },
    stops: { type: Object, default: () => ({ type: 'FeatureCollection', features: [] }) },
    showCities: { type: Boolean, default: true },
    showEntities: { type: Boolean, default: false },
    showRoutes: { type: Boolean, default: true },
    loading: Boolean,
    active: { type: Boolean, default: true },
    height: { type: String, default: '620px' },
})
const emit = defineEmits(['ready', 'viewport-change', 'error'])
const container = ref(null)
const initializing = ref(false)
const mapError = ref('')
let map = null
let maplibregl = null
let resizeObserver = null
let stopMarkers = []
let fallbackApplied = false
let interactionsBound = false
let readyEmitted = false

const sourceIds = ['logistics-cities', 'logistics-entities', 'logistics-routes', 'logistics-stops']

const emptyCollection = () => ({ type: 'FeatureCollection', features: [] })

function fallbackStyle() {
    return {
        version: 8,
        sources: {},
        layers: [{ id: 'background', type: 'background', paint: { 'background-color': '#eef2ed' } }],
    }
}

async function initialize() {
    if (map || initializing.value || !container.value || !props.active || !props.config) return
    initializing.value = true
    mapError.value = ''

    try {
        const runtime = await loadMapRuntime()
        maplibregl = runtime.maplibregl
        if (!container.value || !props.active) return

        map = new maplibregl.Map({
            container: container.value,
            style: props.config.enabled ? props.config.style_url : fallbackStyle(),
            center: props.config.default_center || [94, 66],
            zoom: Number(props.config.default_zoom || 2.3),
            minZoom: 1.5,
            maxZoom: 18,
            renderWorldCopies: false,
            attributionControl: false,
        })
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right')
        map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right')
        map.on('error', handleMapError)
        map.on('style.load', handleStyleLoad)
        map.on('moveend', emitViewport)

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => map?.resize())
            resizeObserver.observe(container.value)
        }
    } catch (error) {
        const message = webGlMessage(error)
        mapError.value = message
        emit('error', message)
    } finally {
        initializing.value = false
    }
}

function webGlMessage(error) {
    const message = String(error?.message || '')
    return /webgl/i.test(message)
        ? 'WebGL недоступен. Табличные данные остаются доступны.'
        : 'Не удалось инициализировать карту. Табличные данные остаются доступны.'
}

function handleMapError(event) {
    const message = event?.error?.message || 'Ошибка загрузки базовой карты.'
    if (/style|source|tile|pmtiles|glyph|sprite|webgl/i.test(message)) {
        mapError.value = 'Базовая карта временно недоступна; прикладные точки и маршруты могут отображаться без подложки.'
        emit('error', mapError.value)

        let overlaysReady = false
        try {
            overlaysReady = Boolean(map?.getSource(sourceIds[0]))
        } catch {
            overlaysReady = false
        }
        if (map && !overlaysReady && !fallbackApplied) {
            fallbackApplied = true
            map.setStyle(fallbackStyle())
        }
    }
}

function handleStyleLoad() {
    addGeoJsonSources()
    if (!interactionsBound) {
        bindInteractions()
        interactionsBound = true
    }
    updateAllData()
    emitViewport()
    if (!readyEmitted) {
        readyEmitted = true
        emit('ready')
    }
}

function addGeoJsonSources() {
    if (!map || map.getSource(sourceIds[0])) return

    map.addSource('logistics-cities', { type: 'geojson', data: props.cities, cluster: true, clusterMaxZoom: 9, clusterRadius: 48 })
    map.addLayer({
        id: 'logistics-city-clusters', type: 'circle', source: 'logistics-cities', filter: ['has', 'point_count'],
        paint: { 'circle-color': '#2e7d32', 'circle-radius': ['step', ['get', 'point_count'], 14, 25, 19, 100, 25], 'circle-stroke-width': 2, 'circle-stroke-color': '#fff' },
    })
    map.addLayer({
        id: 'logistics-city-points', type: 'circle', source: 'logistics-cities', filter: ['!', ['has', 'point_count']],
        paint: { 'circle-color': '#2e7d32', 'circle-radius': 6, 'circle-stroke-width': 2, 'circle-stroke-color': '#fff' },
    })

    map.addSource('logistics-entities', { type: 'geojson', data: props.entities, cluster: true, clusterMaxZoom: 11, clusterRadius: 46 })
    map.addLayer({
        id: 'logistics-entity-clusters', type: 'circle', source: 'logistics-entities', filter: ['has', 'point_count'],
        paint: { 'circle-color': '#6a1b9a', 'circle-radius': ['step', ['get', 'point_count'], 13, 25, 18, 100, 24], 'circle-stroke-width': 2, 'circle-stroke-color': '#fff' },
    })
    map.addLayer({
        id: 'logistics-entity-points', type: 'circle', source: 'logistics-entities', filter: ['!', ['has', 'point_count']],
        paint: { 'circle-color': '#8e24aa', 'circle-radius': 5.5, 'circle-stroke-width': 1.5, 'circle-stroke-color': '#fff' },
    })

    map.addSource('logistics-routes', { type: 'geojson', data: props.routes })
    map.addLayer({
        id: 'logistics-historical-routes', type: 'line', source: 'logistics-routes',
        filter: ['==', ['get', 'is_current'], false],
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': '#7b1fa2', 'line-width': 4, 'line-opacity': 0.78, 'line-dasharray': [2, 1.5] },
    })
    map.addLayer({
        id: 'logistics-current-routes', type: 'line', source: 'logistics-routes',
        filter: ['!=', ['get', 'is_current'], false],
        layout: { 'line-cap': 'round', 'line-join': 'round' },
        paint: { 'line-color': '#087f5b', 'line-width': 5, 'line-opacity': 0.9 },
    })
    map.addSource('logistics-stops', { type: 'geojson', data: props.stops })
    applyVisibility()
}

function bindInteractions() {
    for (const [layer, source] of [
        ['logistics-city-clusters', 'logistics-cities'],
        ['logistics-entity-clusters', 'logistics-entities'],
    ]) {
        map.on('click', layer, async (event) => {
            const clusterId = event.features?.[0]?.properties?.cluster_id
            if (clusterId == null) return
            const zoom = await map.getSource(source).getClusterExpansionZoom(clusterId)
            map.easeTo({ center: event.features[0].geometry.coordinates, zoom })
        })
        map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer' })
        map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = '' })
    }

    for (const layer of ['logistics-city-points', 'logistics-entity-points']) {
        map.on('click', layer, (event) => showFeaturePopup(event.features?.[0]))
        map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer' })
        map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = '' })
    }
}

function showFeaturePopup(feature) {
    if (!feature || !maplibregl) return
    const properties = feature.properties || {}
    const title = escapeHtml(properties.name || properties.city || 'Точка')
    const detail = escapeHtml(properties.address || properties.region || '')
    new maplibregl.Popup({ closeButton: true, maxWidth: '320px' })
        .setLngLat(feature.geometry.coordinates)
        .setHTML(`<strong>${title}</strong>${detail ? `<div>${detail}</div>` : ''}`)
        .addTo(map)
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character])
}

function updateAllData() {
    setSourceData('logistics-cities', props.cities)
    setSourceData('logistics-entities', props.entities)
    setSourceData('logistics-routes', props.routes)
    setSourceData('logistics-stops', props.stops)
    updateStopMarkers()
    applyVisibility()
}

function setSourceData(id, data) {
    map?.getSource(id)?.setData(data || emptyCollection())
}

function clearStopMarkers() {
    stopMarkers.forEach((marker) => marker.remove())
    stopMarkers = []
}

function updateStopMarkers() {
    clearStopMarkers()
    if (!map || !maplibregl || !props.showRoutes) return

    for (const feature of props.stops?.features || []) {
        if (feature?.geometry?.type !== 'Point') continue
        const element = document.createElement('button')
        element.type = 'button'
        element.className = 'logistics-map-stop'
        element.textContent = feature.properties?.sequence_label || String(feature.properties?.sequence || '')
        element.setAttribute('aria-label', `Остановка ${element.textContent}`)
        const popupLines = [
            `<strong>${escapeHtml(feature.properties?.city || 'Остановка')}</strong>`,
            feature.properties?.address ? `<div>${escapeHtml(feature.properties.address)}</div>` : '',
            feature.properties?.stop_type ? `<div>Тип: ${escapeHtml(stopTypeLabel(feature.properties.stop_type))}</div>` : '',
            feature.properties?.operation_type ? `<div>Операция: ${escapeHtml(operationTypeLabel(feature.properties.operation_type))}</div>` : '',
            stopTimeLine('План', feature.properties?.planned_arrival_at, feature.properties?.planned_departure_at),
            stopTimeLine('Факт', feature.properties?.actual_arrival_at, feature.properties?.actual_departure_at),
        ].filter(Boolean).join('')
        const marker = new maplibregl.Marker({ element, anchor: 'center' })
            .setLngLat(feature.geometry.coordinates)
            .setPopup(new maplibregl.Popup({ offset: 18, maxWidth: '340px' }).setHTML(popupLines))
            .addTo(map)
        stopMarkers.push(marker)
    }
}

function stopTypeLabel(value) {
    return ({ origin: 'начало', waypoint: 'промежуточная', destination: 'финиш' })[value] || value
}

function operationTypeLabel(value) {
    return ({
        loading: 'погрузка', unloading: 'разгрузка', loading_unloading: 'погрузка и разгрузка', technical: 'техническая',
    })[value] || value
}

function stopTimeLine(label, arrival, departure) {
    if (!arrival && !departure) return ''
    const parts = [
        arrival ? `прибытие ${formatMapDate(arrival)}` : null,
        departure ? `отправление ${formatMapDate(departure)}` : null,
    ].filter(Boolean).join(', ')
    return `<div>${escapeHtml(label)}: ${escapeHtml(parts)}</div>`
}

function formatMapDate(value) {
    const date = new Date(value)
    return Number.isNaN(date.getTime())
        ? String(value)
        : new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' }).format(date)
}

function applyVisibility() {
    setLayersVisibility(['logistics-city-clusters', 'logistics-city-points'], props.showCities)
    setLayersVisibility(['logistics-entity-clusters', 'logistics-entity-points'], props.showEntities)
    setLayersVisibility(['logistics-current-routes', 'logistics-historical-routes'], props.showRoutes)
}

function setLayersVisibility(ids, visible) {
    for (const id of ids) {
        if (map?.getLayer(id)) map.setLayoutProperty(id, 'visibility', visible ? 'visible' : 'none')
    }
}

function emitViewport() {
    if (!map) return
    const bounds = map.getBounds()
    emit('viewport-change', {
        bbox: [normalizeLongitude(bounds.getWest()), bounds.getSouth(), normalizeLongitude(bounds.getEast()), bounds.getNorth()],
        zoom: map.getZoom(),
    })
}

function normalizeLongitude(value) {
    let longitude = Number(value)
    while (longitude > 180) longitude -= 360
    while (longitude < -180) longitude += 360
    return Number(longitude.toFixed(7))
}

function showRussia() {
    map?.easeTo({ center: props.config?.default_center || [94, 66], zoom: Number(props.config?.default_zoom || 2.3), duration: 650 })
}

function fitData() {
    if (!map) return
    const coordinates = []
    collectCoordinates(props.routes, coordinates)
    collectCoordinates(props.stops, coordinates)
    if (!coordinates.length) return
    const prepared = datelineAwareCoordinates(coordinates)
    const bounds = prepared.reduce((value, coordinate) => value.extend(coordinate), new maplibregl.LngLatBounds(prepared[0], prepared[0]))
    map.fitBounds(bounds, { padding: { top: 55, right: 55, bottom: 55, left: 55 }, maxZoom: 13, duration: 650 })
}

function collectCoordinates(collection, target) {
    for (const feature of collection?.features || []) {
        const geometry = feature?.geometry
        if (!geometry) continue
        if (geometry.type === 'Point') target.push(geometry.coordinates)
        if (geometry.type === 'LineString') target.push(...geometry.coordinates)
    }
}

function datelineAwareCoordinates(coordinates) {
    const direct = coordinates.map(([longitude, latitude]) => [normalizeLongitude(longitude), latitude])
    const shifted = direct.map(([longitude, latitude]) => [longitude < 0 ? longitude + 360 : longitude, latitude])
    const span = (items) => Math.max(...items.map((item) => item[0])) - Math.min(...items.map((item) => item[0]))
    return span(shifted) < span(direct) ? shifted : direct
}

function resize() {
    nextTick(() => map?.resize())
}

watch(() => props.active, (active) => { if (active) { initialize(); resize() } })
watch(() => props.config, initialize, { deep: true })
watch(() => props.cities, () => setSourceData('logistics-cities', props.cities), { deep: true })
watch(() => props.entities, () => setSourceData('logistics-entities', props.entities), { deep: true })
watch(() => props.routes, () => setSourceData('logistics-routes', props.routes), { deep: true })
watch(() => props.stops, () => { setSourceData('logistics-stops', props.stops); updateStopMarkers() }, { deep: true })
watch(() => [props.showCities, props.showEntities, props.showRoutes], () => { applyVisibility(); updateStopMarkers() })

onMounted(initialize)
onBeforeUnmount(() => {
    clearStopMarkers()
    resizeObserver?.disconnect()
    resizeObserver = null
    if (map) {
        map.off('error', handleMapError)
        map.off('style.load', handleStyleLoad)
        map.off('moveend', emitViewport)
        map.remove()
        map = null
    }
})

defineExpose({ showRussia, fitData, resize })
</script>

<template>
    <div class="logistics-map-shell" :style="{ '--logistics-map-height': height }">
        <div ref="container" class="logistics-map-canvas" />
        <div v-if="initializing || loading" class="logistics-map-overlay logistics-map-overlay--loading">
            <v-progress-circular indeterminate color="green-darken-2" size="30" />
            <span>{{ initializing ? 'Инициализация карты…' : 'Обновление слоёв…' }}</span>
        </div>
        <v-alert v-if="mapError" class="logistics-map-error" type="warning" density="compact" variant="tonal" closable @click:close="mapError = ''">
            {{ mapError }}
        </v-alert>
        <span class="logistics-map-attribution">
            © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>
            · © <a href="https://openmaptiles.org/" target="_blank" rel="noopener noreferrer">OpenMapTiles</a>
        </span>
    </div>
</template>

<style>
.logistics-map-shell {
    position: relative;
    min-height: var(--logistics-map-height);
    overflow: hidden;
    border-radius: 12px;
    background: #eef2ed;
}
.logistics-map-canvas { width: 100%; height: var(--logistics-map-height); }
.logistics-map-overlay { position: absolute; inset: 0; z-index: 4; display: grid; place-content: center; justify-items: center; gap: 10px; background: rgba(247, 249, 247, .72); color: #415046; }
.logistics-map-error { position: absolute !important; z-index: 5; top: 12px; left: 12px; max-width: min(520px, calc(100% - 78px)); }
.logistics-map-attribution { position: absolute; z-index: 3; right: 7px; bottom: 4px; padding: 2px 5px; border-radius: 3px; background: rgba(255,255,255,.86); color: #3f5146; font-size: 10px; }
.logistics-map-attribution a { color: inherit; text-decoration: none; }
.logistics-map-stop { display: grid; width: 30px; height: 30px; place-items: center; padding: 0; border: 3px solid #fff; border-radius: 50%; background: #087f5b; box-shadow: 0 2px 8px rgba(17, 47, 34, .32); color: #fff; cursor: pointer; font: 750 12px/1 sans-serif; }
.maplibregl-popup-content { color: #26312b; }
@media (max-width: 700px) { .logistics-map-shell, .logistics-map-canvas { min-height: 460px; height: 460px; } }
</style>
