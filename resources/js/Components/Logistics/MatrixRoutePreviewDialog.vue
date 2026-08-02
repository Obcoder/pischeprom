<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import LogisticsMap from './LogisticsMap.vue'

const props = defineProps({
    modelValue: Boolean,
    distance: { type: Object, default: null },
    fromCity: { type: Object, default: null },
    toCity: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue'])
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
const points = computed(() => data.value?.points || emptyCollection())

function formatKm(value) { return value == null ? '—' : `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(value / 1000)} км` }
function formatDuration(value) { if (value == null) return '—'; const minutes = Math.round(value / 60); return `${Math.floor(minutes / 60)} ч ${minutes % 60} мин` }
function formatDate(value) { return value ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—' }

async function load() {
    if (!props.modelValue || !props.distance?.id) return
    controller?.abort()
    controller = new AbortController()
    const currentController = controller
    loading.value = true
    error.value = ''
    data.value = null

    try {
        const [configResponse, previewResponse] = await Promise.all([
            config.value
                ? Promise.resolve({ data: { data: config.value } })
                : axios.get('/api/logistics/map/config', { signal: currentController.signal }),
            axios.get(`/api/logistics/matrix/${props.distance.id}/preview`, { signal: currentController.signal }),
        ])
        config.value = configResponse.data?.data || configResponse.data
        data.value = previewResponse.data?.data || previewResponse.data
    } catch (requestError) {
        if (requestError?.code !== 'ERR_CANCELED') {
            error.value = requestError?.response?.data?.message || 'Не удалось построить preview выбранной пары.'
        }
    } finally {
        if (controller === currentController) loading.value = false
    }
}

watch(() => [props.modelValue, props.distance?.id], load)
watch(() => props.modelValue, (open) => { if (!open) controller?.abort() })
onBeforeUnmount(() => controller?.abort())
</script>

<template>
    <v-dialog :model-value="modelValue" max-width="1120" scrollable @update:model-value="emit('update:modelValue', $event)">
        <v-card>
            <v-toolbar color="green-darken-3" density="comfortable">
                <v-btn icon="mdi-close" @click="emit('update:modelValue', false)" />
                <v-toolbar-title>{{ fromCity?.name || 'A' }} → {{ toCity?.name || 'B' }}</v-toolbar-title>
            </v-toolbar>
            <v-card-text>
                <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
                <v-alert v-if="data?.message" :type="data.distance?.status === 'manual' ? 'warning' : 'info'" variant="tonal" class="mb-4">{{ data.message }}</v-alert>
                <v-row v-if="data?.distance" dense class="mb-4">
                    <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Сохранено</div><strong>{{ formatKm(data.distance.distance_m) }}</strong><div class="text-caption">{{ formatDuration(data.distance.duration_s) }}</div></v-card-text></v-card></v-col>
                    <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Статус</div><strong>{{ data.distance.status }}</strong><div class="text-caption">{{ data.distance.routing_profile }}</div></v-card-text></v-card></v-col>
                    <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Engine / OSM</div><strong>{{ data.distance.routing_engine_version || '—' }}</strong><div class="text-caption">{{ data.distance.osm_data_version || '—' }}</div></v-card-text></v-card></v-col>
                    <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Дата</div><strong class="text-body-2">{{ formatDate(data.distance.calculated_at) }}</strong><div class="text-caption">{{ data.distance.provider }}</div></v-card-text></v-card></v-col>
                </v-row>
                <v-alert v-if="data?.distance?.manual_note" type="info" density="compact" variant="outlined" class="mb-4">{{ data.distance.manual_note }}</v-alert>
                <LogisticsMap
                    v-if="config"
                    ref="mapRef"
                    :config="config"
                    :routes="routes"
                    :stops="points"
                    :loading="loading"
                    height="500px"
                    :show-cities="false"
                    @ready="mapRef?.fitData()"
                />
                <v-skeleton-loader v-else-if="loading" type="image" height="500" />
            </v-card-text>
            <v-card-actions class="justify-end">
                <v-btn variant="tonal" prepend-icon="mdi-fit-to-screen-outline" :disabled="!data" @click="mapRef?.fitData()">Показать пару</v-btn>
                <v-btn @click="emit('update:modelValue', false)">Закрыть</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
