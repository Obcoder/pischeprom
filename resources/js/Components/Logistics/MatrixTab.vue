<script setup>
import { computed, inject, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import MatrixRoutePreviewDialog from './MatrixRoutePreviewDialog.vue'

const api = inject('logisticsApi')
const permissions = inject('logisticsPermissions')
const logisticsCities = ref([])
const cityCandidates = ref([])
const selectedIds = ref([])
const matrix = ref(null)
const profile = ref('truck')
const run = ref(null)
const citySearch = ref('')
const fullMatrixCityCount = ref(0)
const fullMatrixDialog = ref(false)
const fullMatrixRefresh = ref(false)
const citySettingsDialog = ref(false)
const citySettings = reactive({ city_id: null, name: '', routing_latitude: null, routing_longitude: null, coordinate_source: 'manual', source_reference: '', is_matrix_enabled: true, mark_verified: false })
const manualDialog = ref(false)
const previewDialog = ref(false)
const preview = reactive({ distance: null, fromCity: null, toCity: null })
const manual = reactive({ from_city_id: null, to_city_id: null, distance_km: null, duration_min: null, manual_note: '' })
let pollTimer = null
let searchTimer = null

const canManage = computed(() => Boolean(permissions.value?.matrix_manage))
const selectedCities = computed(() => selectedIds.value.map((id) => logisticsCities.value.find((city) => city.city_id === id)).filter(Boolean))
const selectedUnreadyCities = computed(() => selectedCities.value.filter((city) => !city.is_verified))
const selectedCitiesAreReady = computed(() => selectedIds.value.length >= 2 && selectedUnreadyCities.value.length === 0)
const fullMatrixPairCount = computed(() => fullMatrixCityCount.value * Math.max(0, fullMatrixCityCount.value - 1))
const citySettingsMapUrl = computed(() => {
    if (citySettings.routing_latitude === null || citySettings.routing_longitude === null) return null
    const latitude = Number(citySettings.routing_latitude)
    const longitude = Number(citySettings.routing_longitude)
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null

    return `https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=16/${latitude}/${longitude}`
})
const maxCities = 50

function statusColor(status) { return ({ calculated: 'green', manual: 'purple', pending: 'blue', stale: 'orange', no_route: 'red', failed: 'red', diagonal: 'grey' })[status] || 'grey' }
function statusTitle(status) { return ({ calculated: 'рассчитано', manual: 'ручное', pending: 'в очереди', stale: 'устарело', no_route: 'нет маршрута', failed: 'ошибка', diagonal: 'диагональ' })[status] || 'не рассчитано' }
function km(value) { return value == null ? '—' : `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 1 }).format(value / 1000)} км` }
function duration(value) { if (value == null) return '—'; const minutes = Math.round(value / 60); return minutes >= 60 ? `${Math.floor(minutes / 60)} ч ${minutes % 60} мин` : `${minutes} мин` }
function cell(fromId, toId) { return matrix.value?.cells?.[`${fromId}:${toId}`] || null }
function cellHint(item) { if (!item) return 'Значение отсутствует'; return [statusTitle(item.status), item.calculated_at ? new Date(item.calculated_at).toLocaleString('ru-RU') : null, item.osm_data_version ? `OSM ${item.osm_data_version}` : null, item.manual_note, item.error_message].filter(Boolean).join(' · ') }
function cityIsReady(cityId) { return Boolean(logisticsCities.value.find((city) => city.city_id === cityId)?.is_verified) }
function pairIsReady(fromId, toId) { return cityIsReady(fromId) && cityIsReady(toId) }
function unreadyCityNames() { return selectedUnreadyCities.value.map((city) => `«${city.name}»`).join(', ') }

async function loadCities(search = '', enabledOnly = true) {
    try {
        const response = await api.request(`matrix-cities-${enabledOnly}-${search}`, { method: 'get', url: '/api/logistics/cities', params: { search, per_page: 200, enabled_only: enabledOnly ? 1 : 0, matrix_only: enabledOnly ? 1 : 0 } }, { error: 'Не удалось загрузить города логистики.' })
        if (enabledOnly) {
            logisticsCities.value = response?.data || []
            fullMatrixCityCount.value = Number(response?.meta?.matrix_ready_total || 0)
            const availableIds = new Set(logisticsCities.value.map((city) => city.city_id))
            selectedIds.value = selectedIds.value.filter((id) => availableIds.has(id))
            if (!selectedIds.value.length) selectedIds.value = logisticsCities.value.filter((city) => city.is_verified).slice(0, 6).map((city) => city.city_id)
        } else cityCandidates.value = response?.data || []
    } catch { /* global */ }
}

async function loadMatrix() {
    if (selectedIds.value.length < 2) { matrix.value = null; return }
    try {
        const response = await api.request('matrix-fragment', { method: 'get', url: '/api/logistics/matrix', params: { city_ids: selectedIds.value, routing_profile: profile.value } }, { error: 'Не удалось загрузить фрагмент матрицы.' })
        matrix.value = response?.data || null
    } catch { /* global */ }
}

async function calculate(refresh = false) {
    if (selectedIds.value.length < 2) { api.notify('Выберите минимум два города с подтверждёнными точками маршрутизации.', 'warning'); return }
    if (selectedUnreadyCities.value.length) {
        api.notify(`Сначала подтвердите точки маршрутизации городов: ${unreadyCityNames()}.`, 'warning')
        return
    }
    try {
        const response = await api.request('matrix-calculate', { method: 'post', url: '/api/logistics/matrix/calculate', data: { city_ids: selectedIds.value, routing_profile: profile.value, refresh, missing_only: !refresh } }, { success: refresh ? 'Пересчёт выбранного фрагмента поставлен в очередь.' : 'Недостающие пары поставлены в очередь.' })
        run.value = response?.data || response
        await loadMatrix()
        startPolling()
    } catch { /* global */ }
}

async function calculateFullMatrix() {
    if (fullMatrixCityCount.value < 2) {
        api.notify('Для полной матрицы нужны минимум два города с подтверждёнными точками маршрутизации.', 'warning')
        return
    }

    try {
        const response = await api.request('matrix-calculate-full', {
            method: 'post',
            url: '/api/logistics/matrix/calculate',
            data: {
                full_matrix: true,
                routing_profile: profile.value,
                refresh: fullMatrixRefresh.value,
                missing_only: !fullMatrixRefresh.value,
            },
        }, {
            success: 'Полная матрица поставлена в очередь.',
        })
        run.value = response?.data || response
        fullMatrixDialog.value = false
        await loadMatrix()
        startPolling()
    } catch { /* global */ }
}

function startPolling() { stopPolling(); if (!run.value?.id) return; pollTimer = setInterval(pollRun, 2000); pollRun() }
async function pollRun() {
    try {
        const response = await api.request(`matrix-run-${run.value.id}`, { method: 'get', url: `/api/logistics/routing-runs/${run.value.id}` }, { error: 'Не удалось получить прогресс матрицы.' })
        run.value = response?.data || response
        await loadMatrix()
        if (['completed', 'partial', 'failed', 'cancelled'].includes(run.value.status)) stopPolling()
    } catch { stopPolling() }
}
function stopPolling() { if (pollTimer) clearInterval(pollTimer); pollTimer = null }

function openManual(fromId, toId) {
    if (!pairIsReady(fromId, toId)) {
        api.notify('Сначала подтвердите точки маршрутизации обоих городов.', 'warning')
        return
    }

    const current = cell(fromId, toId)
    Object.assign(manual, { from_city_id: fromId, to_city_id: toId, distance_km: current?.distance_m == null ? null : current.distance_m / 1000, duration_min: current?.duration_s == null ? null : Math.round(current.duration_s / 60), manual_note: current?.manual_note || '' })
    manualDialog.value = true
}
function openPreview(fromId, toId) {
    if (fromId === toId) return
    const distance = cell(fromId, toId)
    if (!distance) {
        api.notify('Для этой пары ещё нет сохранённого значения и route preview.', 'info')
        return
    }
    Object.assign(preview, {
        distance,
        fromCity: selectedCities.value.find((city) => city.city_id === fromId) || null,
        toCity: selectedCities.value.find((city) => city.city_id === toId) || null,
    })
    previewDialog.value = true
}
async function saveManual() {
    try {
        await api.request('matrix-manual', { method: 'put', url: '/api/logistics/matrix/manual', data: {
            from_city_id: manual.from_city_id, to_city_id: manual.to_city_id, routing_profile: profile.value,
            distance_m: Math.round(Number(manual.distance_km) * 1000), duration_s: manual.duration_min ? Math.round(Number(manual.duration_min) * 60) : null,
            manual_note: manual.manual_note,
        } }, { success: 'Ручное направленное расстояние сохранено.' })
        manualDialog.value = false
        await loadMatrix()
    } catch { /* validation errors are shown */ }
}

function searchCandidate(value) { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadCities(value || '', false), 250) }
function openCitySettings(city) {
    Object.assign(citySettings, {
        city_id: city.city_id, name: city.name, routing_latitude: city.routing_latitude ?? city.existing_latitude,
        routing_longitude: city.routing_longitude ?? city.existing_longitude, coordinate_source: city.coordinate_source || 'manual',
        source_reference: city.source_reference || '', is_matrix_enabled: city.is_matrix_enabled ?? true, mark_verified: false,
    })
    citySettingsDialog.value = true
}
async function saveCitySettings() {
    try {
        await api.request('matrix-city-save', { method: 'put', url: `/api/logistics/cities/${citySettings.city_id}`, data: {
            routing_latitude: citySettings.routing_latitude, routing_longitude: citySettings.routing_longitude,
            coordinate_source: citySettings.coordinate_source, source_reference: citySettings.source_reference || null,
            is_matrix_enabled: citySettings.is_matrix_enabled, mark_verified: citySettings.mark_verified,
        } }, { success: 'Точка маршрутизации города сохранена.' })
        citySettingsDialog.value = false
        await Promise.all([loadCities('', true), loadCities(citySearch.value, false)])
    } catch { /* global */ }
}

function exportCsv() {
    if (selectedIds.value.length < 2) return
    const params = new URLSearchParams({ routing_profile: profile.value })
    selectedIds.value.forEach((id) => params.append('city_ids[]', String(id)))
    window.location.assign(`/api/logistics/matrix/export?${params.toString()}`)
}

watch(selectedIds, (ids) => {
    if (ids.length > maxCities) {
        selectedIds.value = ids.slice(0, maxCities)
        api.notify(`За один раз можно выбрать не более ${maxCities} городов.`, 'warning')
        return
    }
    clearTimeout(searchTimer)
    searchTimer = setTimeout(loadMatrix, 250)
}, { deep: true })
watch(profile, () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadMatrix, 250) })
onMounted(() => { loadCities('', true); if (canManage.value) loadCities('', false) })
onBeforeUnmount(stopPolling)
</script>

<template>
    <section>
        <v-card variant="outlined" class="mb-4">
            <v-card-text>
                <div class="logistics-toolbar mb-0">
                    <div class="logistics-toolbar__filters">
                        <v-autocomplete v-model="selectedIds" :items="logisticsCities" item-title="name" item-value="city_id" label="Выбранные города" multiple chips closable-chips :counter="maxCities" density="compact" variant="outlined" hide-details style="min-width: min(100%, 480px)">
                            <template #item="{ props: itemProps, item }">
                                <v-list-item
                                    v-bind="itemProps"
                                    :subtitle="item.raw.is_verified ? 'Готов к расчёту' : 'Требуется подтвердить точку маршрутизации'"
                                >
                                    <template #prepend>
                                        <v-icon
                                            :color="item.raw.is_verified ? 'green' : 'orange'"
                                            :icon="item.raw.is_verified ? 'mdi-map-marker-check' : 'mdi-map-marker-alert'"
                                        />
                                    </template>
                                </v-list-item>
                            </template>
                            <template #chip="{ props: chipProps, item }">
                                <v-chip
                                    v-bind="chipProps"
                                    :color="item.raw.is_verified ? undefined : 'orange'"
                                    :prepend-icon="item.raw.is_verified ? undefined : 'mdi-alert-circle-outline'"
                                    :text="item.raw.name"
                                    variant="tonal"
                                />
                            </template>
                        </v-autocomplete>
                        <v-select v-model="profile" :items="[{title:'Грузовой truck',value:'truck'},{title:'Легковой auto',value:'auto'}]" label="Профиль" density="compact" variant="outlined" hide-details style="max-width: 210px" />
                    </div>
                    <div class="logistics-toolbar__actions">
                        <v-btn variant="outlined" prepend-icon="mdi-download" :disabled="selectedIds.length < 2" @click="exportCsv">CSV</v-btn>
                        <v-btn v-if="canManage" variant="outlined" color="deep-purple" prepend-icon="mdi-grid-large" :disabled="fullMatrixCityCount < 2" @click="fullMatrixDialog = true">Полная матрица</v-btn>
                        <v-btn v-if="canManage" variant="tonal" color="green-darken-2" :disabled="!selectedCitiesAreReady" :loading="api.isPending('matrix-calculate')" @click="calculate(false)">Только отсутствующие</v-btn>
                        <v-btn v-if="canManage" variant="flat" color="green-darken-2" :disabled="!selectedCitiesAreReady" :loading="api.isPending('matrix-calculate')" @click="calculate(true)">Пересчитать</v-btn>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-alert
            v-if="selectedUnreadyCities.length"
            type="warning"
            variant="tonal"
            title="Сначала подтвердите точки маршрутизации"
            class="mb-4"
        >
            <p>
                Для {{ unreadyCityNames() }} координаты ещё не подтверждены, поэтому автоматический расчёт не запускается.
            </p>
            <p class="mt-2">
                Точка маршрутизации — координаты на доступной автомобильной дороге или въезде в город,
                от которых Valhalla строит маршрут. Проверка защищает от старта из реки, парка или условного центра вне дороги.
            </p>
            <div v-if="canManage" class="matrix-alert-actions">
                <v-btn
                    v-for="city in selectedUnreadyCities"
                    :key="city.city_id"
                    size="small"
                    variant="outlined"
                    color="orange-darken-3"
                    prepend-icon="mdi-map-marker-edit-outline"
                    @click="openCitySettings(city)"
                >
                    Настроить {{ city.name }}
                </v-btn>
            </div>
        </v-alert>

        <v-alert v-if="run" :type="run.status === 'failed' || run.status === 'partial' ? 'error' : run.status === 'completed' ? 'success' : 'info'" variant="tonal" class="mb-4">
            Расчёт {{ run.status }}: {{ run.completed_pairs }} готово, {{ run.failed_pairs }} ошибок из {{ run.total_pairs }}.
            <v-progress-linear :model-value="run.progress_percent" class="mt-2" />
            <div v-if="run.last_error" class="mt-1">{{ run.last_error }}</div>
        </v-alert>

        <v-card variant="outlined" class="matrix-card">
            <div v-if="selectedIds.length < 2" class="logistics-empty">Выберите от двух до {{ maxCities }} городов для просмотра фрагмента или запустите полную матрицу отдельной кнопкой.</div>
            <div v-else class="matrix-scroll">
                <table class="matrix-table">
                    <thead><tr><th class="matrix-table__corner">Откуда ↓ / Куда →</th><th v-for="city in selectedCities" :key="city.city_id">{{ city.name }}</th></tr></thead>
                    <tbody><tr v-for="from in selectedCities" :key="from.city_id"><th>{{ from.name }}</th><td v-for="to in selectedCities" :key="to.city_id" :class="`matrix-cell matrix-cell--${cell(from.city_id,to.city_id)?.status || 'missing'}`" :title="cellHint(cell(from.city_id,to.city_id))" @click="openPreview(from.city_id,to.city_id)">
                        <template v-if="cell(from.city_id,to.city_id)"><strong>{{ km(cell(from.city_id,to.city_id).distance_m) }}</strong><small>{{ duration(cell(from.city_id,to.city_id).duration_s) }}</small><div class="d-flex align-center justify-center ga-1"><v-chip :color="statusColor(cell(from.city_id,to.city_id).status)" size="x-small" variant="tonal">{{ statusTitle(cell(from.city_id,to.city_id).status) }}</v-chip><v-btn v-if="from.city_id !== to.city_id" icon="mdi-map-search-outline" variant="text" size="x-small" @click.stop="openPreview(from.city_id,to.city_id)" /><v-btn v-if="canManage && pairIsReady(from.city_id,to.city_id) && from.city_id !== to.city_id" icon="mdi-pencil-outline" variant="text" size="x-small" @click.stop="openManual(from.city_id,to.city_id)" /></div></template>
                        <template v-else><span>—</span><small>не рассчитано</small><v-btn v-if="canManage && pairIsReady(from.city_id,to.city_id)" icon="mdi-pencil-outline" variant="text" size="x-small" @click.stop="openManual(from.city_id,to.city_id)" /></template>
                    </td></tr></tbody>
                </table>
            </div>
            <v-card-text class="text-caption text-medium-emphasis">Щелчок по рассчитанной направленной ячейке открывает картографический preview; карандаш — ручное значение. A → B и B → A хранятся раздельно; диагональ 0 не записывается в БД.</v-card-text>
        </v-card>

        <v-dialog v-model="fullMatrixDialog" max-width="620" persistent>
            <v-card title="Рассчитать полную матрицу">
                <v-card-text>
                    <v-alert type="warning" variant="tonal" class="mb-4">
                        Будут обработаны все {{ fullMatrixCityCount.toLocaleString('ru-RU') }} включённых городов с подтверждёнными точками маршрутизации:
                        {{ fullMatrixPairCount.toLocaleString('ru-RU') }} направленных пар. Операция выполняется батчами в очереди и может занять продолжительное время.
                    </v-alert>
                    <v-checkbox
                        v-model="fullMatrixRefresh"
                        label="Пересчитать существующие автоматические значения"
                        hint="Ручные значения сохранятся в любом случае"
                        persistent-hint
                        color="deep-purple"
                    />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn @click="fullMatrixDialog = false">Отмена</v-btn>
                    <v-btn color="deep-purple" variant="flat" :loading="api.isPending('matrix-calculate-full')" @click="calculateFullMatrix">
                        Поставить в очередь
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-card v-if="canManage" variant="outlined" class="mt-4">
            <v-card-title>Логистические точки городов</v-card-title>
            <v-card-text>
                <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                    <strong>Что такое точка маршрутизации?</strong>
                    Это координаты на доступной автомобильной дороге или въезде в город, которые Valhalla использует вместо условного географического центра.
                    Перед автоматическим расчётом координаты нужно просмотреть и подтвердить.
                </v-alert>
                <v-text-field v-model="citySearch" label="Найти город для включения или настройки" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" clearable @update:model-value="searchCandidate" />
                <v-list lines="two" max-height="320" class="overflow-y-auto border rounded">
                    <v-list-item v-for="city in cityCandidates" :key="city.city_id" :title="city.name" :subtitle="city.is_enabled ? `${city.is_verified ? 'точка подтверждена' : 'нужно подтвердить точку'} · ${city.is_matrix_enabled ? 'в матрице' : 'отключён'}` : 'не включён в логистику'" @click="openCitySettings(city)">
                        <template #prepend><v-icon :color="city.is_verified ? 'green' : city.has_coordinates ? 'orange' : 'red'" :icon="city.is_verified ? 'mdi-map-marker-check' : 'mdi-map-marker-alert'" /></template><template #append><v-btn icon="mdi-pencil-outline" variant="text" /></template>
                    </v-list-item>
                </v-list>
            </v-card-text>
        </v-card>

        <v-dialog v-model="manualDialog" max-width="600" persistent><v-card title="Ручное направленное расстояние"><v-card-text><v-alert type="warning" variant="tonal" density="compact" class="mb-4">Автоматическое обновление никогда не перезапишет ручное значение.</v-alert><v-row dense><v-col cols="12" md="6"><v-text-field v-model.number="manual.distance_km" type="number" min="0.001" step="0.1" label="Расстояние, км *" :error-messages="api.firstError('distance_m')" /></v-col><v-col cols="12" md="6"><v-text-field v-model.number="manual.duration_min" type="number" min="1" label="Время, минут" /></v-col><v-col cols="12"><v-textarea v-model="manual.manual_note" label="Обязательный комментарий *" :error-messages="api.firstError('manual_note')" /></v-col></v-row></v-card-text><v-card-actions class="justify-end"><v-btn @click="manualDialog=false">Отмена</v-btn><v-btn color="purple" variant="flat" :loading="api.isPending('matrix-manual')" @click="saveManual">Сохранить</v-btn></v-card-actions></v-card></v-dialog>

        <MatrixRoutePreviewDialog v-model="previewDialog" :distance="preview.distance" :from-city="preview.fromCity" :to-city="preview.toCity" />

        <v-dialog v-model="citySettingsDialog" max-width="680" persistent>
            <v-card :title="`Точка маршрутизации: ${citySettings.name}`">
                <v-card-text>
                    <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                        Укажите точку на доступной автомобильной дороге или въезде в город.
                        После визуальной проверки координат подтвердите её флажком ниже.
                    </v-alert>
                    <v-row dense>
                        <v-col cols="6">
                            <v-text-field v-model.number="citySettings.routing_latitude" type="number" step="0.0000001" label="Широта *" :error-messages="api.firstError('routing_latitude')" />
                        </v-col>
                        <v-col cols="6">
                            <v-text-field v-model.number="citySettings.routing_longitude" type="number" step="0.0000001" label="Долгота *" :error-messages="api.firstError('routing_longitude')" />
                        </v-col>
                        <v-col cols="12">
                            <v-btn
                                v-if="citySettingsMapUrl"
                                :href="citySettingsMapUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                variant="outlined"
                                prepend-icon="mdi-open-in-new"
                            >
                                Проверить точку в OpenStreetMap
                            </v-btn>
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-select v-model="citySettings.coordinate_source" :items="[{title:'Существующие',value:'existing'},{title:'Ручные',value:'manual'},{title:'Импорт',value:'import'},{title:'OSM',value:'osm'}]" label="Источник" />
                        </v-col>
                        <v-col cols="12" md="7">
                            <v-text-field v-model="citySettings.source_reference" label="Ссылка / источник" />
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-switch v-model="citySettings.is_matrix_enabled" label="Участвует в матрице" color="green" />
                        </v-col>
                        <v-col cols="12" md="7">
                            <v-checkbox v-model="citySettings.mark_verified" label="Подтверждаю точку на автомобильной дороге" color="green" />
                        </v-col>
                    </v-row>
                    <v-alert type="info" variant="tonal" density="compact">
                        Изменение координат пометит автоматически рассчитанные пары stale. Ручные пары сохранятся.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn @click="citySettingsDialog=false">Отмена</v-btn>
                    <v-btn color="green-darken-2" variant="flat" :loading="api.isPending('matrix-city-save')" @click="saveCitySettings">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.matrix-card { overflow: hidden; }
.matrix-scroll { overflow: auto; max-height: 66vh; }
.matrix-table { border-collapse: separate; border-spacing: 0; min-width: 100%; font-size: .78rem; }
.matrix-table th, .matrix-table td { min-width: 135px; padding: 10px; border-right: 1px solid #e1e7e2; border-bottom: 1px solid #e1e7e2; text-align: center; }
.matrix-table th { position: sticky; z-index: 2; background: #f1f5f2; font-weight: 700; }
.matrix-table thead th { top: 0; }
.matrix-table tbody th { left: 0; text-align: left; }
.matrix-table__corner { left: 0; z-index: 3 !important; min-width: 180px !important; }
.matrix-cell { cursor: default; }
.matrix-cell strong, .matrix-cell small { display: block; }
.matrix-cell small { margin: 2px 0 5px; color: #657169; }
.matrix-cell--manual { background: #faf3ff; }
.matrix-cell--stale { background: #fff8e8; }
.matrix-cell--failed, .matrix-cell--no_route { background: #fff1f1; }
.matrix-cell--pending { background: #eef6ff; }
.matrix-cell--diagonal { background: #f2f3f2; }
.matrix-alert-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
</style>
