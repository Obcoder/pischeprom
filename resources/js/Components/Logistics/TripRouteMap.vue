<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import LogisticsMap from './LogisticsMap.vue'

const props = defineProps({
    tripId: { type: [Number, String], required: true },
    routeId: { type: [Number, String], default: null },
    active: { type: Boolean, default: true },
    height: { type: String, default: '430px' },
})
const mapRef = ref(null)
const config = ref(null)
const data = ref(null)
const loading = ref(false)
const error = ref('')
let controller = null

const emptyCollection = () => ({ type: 'FeatureCollection', features: [] })
const routes = computed(() => data.value?.route_feature
    ? { type: 'FeatureCollection', features: [data.value.route_feature] }
    : emptyCollection())
const stops = computed(() => data.value?.stops || emptyCollection())

async function load() {
    if (!props.active || !props.tripId) return
    controller?.abort()
    controller = new AbortController()
    const currentController = controller
    loading.value = true
    error.value = ''
    const mapUrl = props.routeId
        ? `/api/logistics/trips/${props.tripId}/routes/${props.routeId}/map`
        : `/api/logistics/trips/${props.tripId}/map`

    try {
        const [configResponse, mapResponse] = await Promise.all([
            config.value
                ? Promise.resolve({ data: { data: config.value } })
                : axios.get('/api/logistics/map/config', { signal: currentController.signal }),
            axios.get(mapUrl, { signal: currentController.signal }),
        ])
        config.value = configResponse.data?.data || configResponse.data
        data.value = mapResponse.data?.data || mapResponse.data
    } catch (requestError) {
        if (requestError?.code !== 'ERR_CANCELED') {
            error.value = requestError?.response?.data?.message || 'Не удалось загрузить картографию рейса.'
        }
    } finally {
        if (controller === currentController) loading.value = false
    }
}

watch(() => [props.tripId, props.routeId, props.active], load, { immediate: true })
onBeforeUnmount(() => controller?.abort())

defineExpose({ fitData: () => mapRef.value?.fitData(), resize: () => mapRef.value?.resize() })
</script>

<template>
    <div class="trip-route-map">
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
        <v-alert v-if="data?.message" type="info" variant="tonal" density="compact" class="mb-3">{{ data.message }}</v-alert>
        <v-alert v-if="data?.notice" type="info" variant="outlined" density="compact" class="mb-3">{{ data.notice }}</v-alert>
        <v-alert v-if="data?.missing_stop_coordinates?.length" type="warning" variant="tonal" density="compact" class="mb-3">
            Без координат: {{ data.missing_stop_coordinates.map(item => `${item.sequence}. ${item.city || 'точка'}`).join(', ') }}.
        </v-alert>
        <div class="trip-route-map__toolbar">
            <div v-if="data?.route" class="text-caption text-medium-emphasis">
                Версия #{{ data.route.id }} · {{ data.route.is_current ? 'текущая' : 'историческая' }} · {{ data.route.routing_profile }} · OSM {{ data.route.osm_data_version || '—' }}
            </div>
            <v-btn size="small" variant="tonal" prepend-icon="mdi-fit-to-screen-outline" :disabled="!routes.features.length && !stops.features.length" @click="mapRef?.fitData()">
                Показать весь маршрут
            </v-btn>
        </div>
        <LogisticsMap
            v-if="config"
            ref="mapRef"
            :config="config"
            :routes="routes"
            :stops="stops"
            :loading="loading"
            :active="active"
            :height="height"
            :show-cities="false"
            @ready="mapRef?.fitData()"
        />
        <v-skeleton-loader v-else-if="loading" type="image" :height="height" />
    </div>
</template>

<style scoped>
.trip-route-map__toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
</style>
