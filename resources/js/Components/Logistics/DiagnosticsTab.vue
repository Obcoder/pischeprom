<script setup>
import { inject, onActivated, onDeactivated, onMounted, ref } from 'vue'

const api = inject('logisticsApi')
const status = ref(null)
const runs = ref([])
const missingCities = ref([])
let timer = null

function formatDate(value) { return value ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'medium' }).format(new Date(value)) : '—' }
function statusColor(value) { return ({ queued: 'blue', running: 'blue', completed: 'green', partial: 'orange', failed: 'red', cancelled: 'grey' })[value] || 'grey' }

async function load() {
    const [healthResult, runsResult, citiesResult] = await Promise.allSettled([
        api.request('routing-health-ui', { method: 'get', url: '/api/logistics/routing-status', validateStatus: (code) => code < 600 }),
        api.request('routing-runs-ui', { method: 'get', url: '/api/logistics/routing-runs', params: { per_page: 25 } }, { error: 'Не удалось загрузить запуски routing.' }),
        api.request('routing-missing-cities', { method: 'get', url: '/api/logistics/cities', params: { enabled_only: 1, missing_coordinates: 1, per_page: 100 } }, { error: 'Не удалось загрузить города без координат.' }),
    ])
    if (healthResult.status === 'fulfilled') status.value = healthResult.value?.data || null
    if (runsResult.status === 'fulfilled') runs.value = runsResult.value?.data || []
    if (citiesResult.status === 'fulfilled') missingCities.value = citiesResult.value?.data || []
}
function start() { stop(); timer = setInterval(load, 10000) }
function stop() { if (timer) clearInterval(timer); timer = null }

onMounted(() => { load(); start() })
onActivated(start)
onDeactivated(stop)
</script>

<template>
    <section>
        <div class="logistics-toolbar">
            <div><h2 class="text-h6">Routing-сервис и очередь</h2><div class="text-caption text-medium-emphasis">Polling раз в 10 секунд, без WebSocket.</div></div>
            <v-btn variant="outlined" prepend-icon="mdi-refresh" :loading="api.isPending('routing-health-ui')" @click="load">Обновить</v-btn>
        </div>

        <v-alert v-if="status" :type="status.healthy ? 'success' : 'error'" variant="tonal" class="mb-4">
            <strong>{{ status.healthy ? 'Valhalla доступна' : 'Valhalla недоступна' }}</strong>
            <span class="ml-2">{{ status.message }}</span>
        </v-alert>

        <v-row v-if="status" dense>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Routing engine</div><div class="logistics-metric__value">{{ status.routing_engine_version || '—' }}</div><div class="logistics-metric__hint">{{ status.provider }} · {{ status.latency_ms ?? '—' }} ms</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Версия OSM-графа</div><div class="logistics-metric__value">{{ status.osm_data_version || 'не задана' }}</div><div class="logistics-metric__hint">обновляется отдельно от deploy Laravel</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Города</div><div class="logistics-metric__value">{{ status.matrix.enabled_cities }}</div><div class="logistics-metric__hint">проверено: {{ status.matrix.verified_cities }}</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Матрица</div><div class="logistics-metric__value">{{ status.matrix.calculated }}</div><div class="logistics-metric__hint">stale: {{ status.matrix.stale }} · ошибки: {{ status.matrix.failed }}</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Очередь</div><div class="logistics-metric__value">{{ status.queue.name }}</div><div class="logistics-metric__hint">{{ status.queue.connection }} · активно: {{ status.runs.active }}</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Последний успех матрицы</div><div class="logistics-metric__value text-body-1">{{ formatDate(status.matrix.last_success_at) }}</div><div class="logistics-metric__hint">по сохранённым значениям</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Ожидают / stale</div><div class="logistics-metric__value">{{ status.matrix.pending }} / {{ status.matrix.stale }}</div><div class="logistics-metric__hint">направленные пары</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card class="logistics-metric" variant="outlined"><v-card-text><div class="logistics-metric__label">Маршруты рейсов</div><div class="logistics-metric__value">{{ status.routes.stale }}</div><div class="logistics-metric__hint">stale · ошибок: {{ status.routes.failed }}</div></v-card-text></v-card></v-col>
        </v-row>

        <v-row dense class="mt-3">
            <v-col cols="12" lg="8"><v-card variant="outlined"><v-card-title>Последние routing runs</v-card-title><v-table density="compact"><thead><tr><th>Создан</th><th>Тип</th><th>Профиль</th><th>Прогресс</th><th>Статус</th><th>Ошибка</th></tr></thead><tbody><tr v-for="run in runs" :key="run.id"><td>{{ formatDate(run.created_at) }}<div class="text-caption">{{ run.initiator?.name || 'CLI/system' }}</div></td><td>{{ run.operation_type }}</td><td>{{ run.routing_profile }}</td><td>{{ run.completed_pairs + run.failed_pairs }} / {{ run.total_pairs }}<v-progress-linear :model-value="run.progress_percent" height="3" /></td><td><v-chip :color="statusColor(run.status)" size="x-small" variant="tonal">{{ run.status }}</v-chip></td><td class="text-caption">{{ run.last_error || '—' }}</td></tr><tr v-if="!runs.length"><td colspan="6" class="logistics-empty">Запусков пока нет.</td></tr></tbody></v-table></v-card></v-col>
            <v-col cols="12" lg="4"><v-card variant="outlined"><v-card-title>Города без routing-точки</v-card-title><v-list v-if="missingCities.length" density="compact"><v-list-item v-for="city in missingCities" :key="city.city_id" :title="city.name" :subtitle="city.region || 'регион не указан'" prepend-icon="mdi-map-marker-alert-outline" /></v-list><div v-else class="logistics-empty">У включённых городов координаты заполнены.</div></v-card></v-col>
        </v-row>
    </section>
</template>
