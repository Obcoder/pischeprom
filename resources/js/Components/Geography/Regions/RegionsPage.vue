<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

const regions = ref([])
const countries = ref([])
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const notice = ref('')
const error = ref('')
const selectedRegion = ref(null)

const geoItems = ref([])
const geoLoading = ref(false)
const geoSyncing = ref(false)
const geoSearch = ref('')
const geoWarning = ref('')
const selectedGeoItems = ref([])
let geoSearchTimer = null

const filters = reactive({
    search: '',
    country_id: null,
    direct: null,
})

const form = reactive({
    yandex_direct_region_ids: [],
    use_for_yandex_direct: false,
})

const directOptions = [
    { title: 'Direct active', value: 'active' },
    { title: 'Mapped', value: 'mapped' },
    { title: 'Empty', value: 'empty' },
]

const headers = [
    { title: 'ID', key: 'id', width: 64 },
    { title: 'Регион', key: 'name', minWidth: 220 },
    { title: 'Страна', key: 'country.name', minWidth: 160 },
    { title: 'Города', key: 'cities_count', align: 'end', width: 84 },
    { title: 'Площадь', key: 'area', align: 'end', width: 100 },
    { title: 'Direct', key: 'use_for_yandex_direct', width: 96 },
    { title: 'Yandex GeoRegions', key: 'yandex_direct_region_ids', minWidth: 300 },
    { title: 'Статус', key: 'mapping_status', width: 112 },
    { title: '', key: 'actions', sortable: false, width: 72 },
]

const summary = computed(() => {
    const mapped = regions.value.filter((region) => regionIds(region).length > 0).length
    const active = regions.value.filter((region) => region.use_for_yandex_direct && regionIds(region).length > 0).length
    const yandexIds = new Set(regions.value.flatMap((region) => region.use_for_yandex_direct ? regionIds(region) : []))

    return {
        total: regions.value.length,
        mapped,
        active,
        yandexIds: yandexIds.size,
    }
})

const availableGeoItems = computed(() => {
    const selectedIds = Array.isArray(form.yandex_direct_region_ids) ? form.yandex_direct_region_ids : []
    const selectedItems = [
        ...selectedGeoItems.value,
        ...selectedIds.map((id) => ({ external_region_id: Number(id), name: null, parent_names: [] })),
    ]

    return mergeGeoItems([...selectedItems, ...geoItems.value])
})

function regionIds(region) {
    return Array.isArray(region?.yandex_direct_region_ids)
        ? region.yandex_direct_region_ids.map((id) => Number(id)).filter(Boolean)
        : []
}

function regionGeoRegions(region) {
    return Array.isArray(region?.yandex_direct_geo_regions)
        ? region.yandex_direct_geo_regions.map((item) => normalizeGeoRegion(item)).filter(Boolean)
        : []
}

function regionGeoChips(region) {
    const geoMap = new Map(regionGeoRegions(region).map((item) => [item.external_region_id, item]))

    return regionIds(region).map((id) => geoMap.get(id) || normalizeGeoRegion({ external_region_id: id }))
}

function normalizeGeoRegion(item) {
    const externalId = Number(item?.external_region_id ?? item?.GeoRegionId)

    if (!Number.isInteger(externalId) || externalId <= 0) {
        return null
    }

    const parentNames = Array.isArray(item?.parent_names)
        ? item.parent_names
        : Array.isArray(item?.ParentGeoRegionNames?.Items)
            ? item.ParentGeoRegionNames.Items
            : []
    const name = item?.name ?? item?.GeoRegionName ?? null
    const path = item?.path || [...parentNames, name].filter(Boolean).join(' / ')

    return {
        ...item,
        external_region_id: externalId,
        parent_id: Number(item?.parent_id ?? item?.ParentId) || null,
        name,
        parent_names: parentNames,
        path,
        title: geoTitle({ external_region_id: externalId, name, parent_names: parentNames }),
    }
}

function geoTitle(item) {
    const id = Number(item?.external_region_id ?? item?.GeoRegionId)
    const name = item?.name ?? item?.GeoRegionName ?? `GeoRegion ${id}`
    const parentNames = Array.isArray(item?.parent_names) ? item.parent_names : []
    const parent = parentNames.length ? ` · ${parentNames.join(' / ')}` : ''

    return `${name || 'GeoRegion'}${parent} · #${id}`
}

function mergeGeoItems(items) {
    const map = new Map()

    items.map((item) => normalizeGeoRegion(item)).filter(Boolean).forEach((item) => {
        const existing = map.get(item.external_region_id)
        map.set(item.external_region_id, {
            ...existing,
            ...item,
            name: item.name || existing?.name || null,
            parent_names: item.parent_names?.length ? item.parent_names : existing?.parent_names || [],
            title: geoTitle({
                external_region_id: item.external_region_id,
                name: item.name || existing?.name,
                parent_names: item.parent_names?.length ? item.parent_names : existing?.parent_names || [],
            }),
        })
    })

    return [...map.values()]
}

function setNotice(message) {
    notice.value = message
    error.value = ''
    window.setTimeout(() => { notice.value = '' }, 2400)
}

function setError(message) {
    error.value = message
    notice.value = ''
}

async function loadCountries() {
    const { data } = await axios.get(route('countries.index'))
    countries.value = Array.isArray(data) ? data : []
}

async function loadRegions() {
    loading.value = true
    try {
        const { data } = await axios.get(route('regions.index'), {
            params: Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null && value !== '')),
        })
        regions.value = Array.isArray(data) ? data : []
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось загрузить регионы.')
    } finally {
        loading.value = false
    }
}

async function loadYandexGeoRegions(search = geoSearch.value, remote = true) {
    const query = String(search || '').trim()

    if (query === '') {
        geoItems.value = []
        return
    }

    if (remote && query.length > 0 && query.length < 2 && !/^\d+$/.test(query)) {
        return
    }

    geoLoading.value = true
    geoWarning.value = ''
    try {
        const { data } = await axios.get('/api/marketing/direct/geo-regions', {
            params: {
                search: query,
                limit: 50,
                remote: remote ? 1 : 0,
            },
        })
        geoItems.value = mergeGeoItems(data.items || [])
        if (data.warning) {
            geoWarning.value = data.warning
        }
    } catch (e) {
        geoWarning.value = e.response?.data?.message || 'Не удалось получить справочник GeoRegions.'
    } finally {
        geoLoading.value = false
    }
}

async function resolveYandexGeoRegions(ids) {
    const normalizedIds = [...new Set((ids || []).map((id) => Number(id)).filter(Boolean))]

    if (!normalizedIds.length) {
        return
    }

    try {
        const { data } = await axios.post('/api/marketing/direct/geo-regions/resolve', {
            ids: normalizedIds,
            remote: 1,
        })
        selectedGeoItems.value = mergeGeoItems([...selectedGeoItems.value, ...(data.items || [])])
    } catch (e) {
        geoWarning.value = e.response?.data?.message || 'Не удалось уточнить названия выбранных GeoRegions.'
    }
}

async function syncYandexGeoRegions() {
    geoSyncing.value = true
    geoWarning.value = ''
    try {
        const { data } = await axios.post('/api/marketing/direct/geo-regions/sync')
        if (String(geoSearch.value || '').trim()) {
            await loadYandexGeoRegions(geoSearch.value, false)
        }
        setNotice(`Справочник GeoRegions обновлён: ${data.stored} записей, всего в кэше ${data.total}.`)
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось синхронизировать справочник GeoRegions.')
    } finally {
        geoSyncing.value = false
    }
}

async function openEdit(region) {
    selectedRegion.value = region
    form.yandex_direct_region_ids = regionIds(region)
    form.use_for_yandex_direct = Boolean(region.use_for_yandex_direct)
    selectedGeoItems.value = regionGeoRegions(region)
    geoItems.value = []
    geoSearch.value = region.name || ''
    dialog.value = true

    await resolveYandexGeoRegions(form.yandex_direct_region_ids)
    await loadYandexGeoRegions(region.name || '', true)
}

async function saveRegion() {
    if (!selectedRegion.value) return

    const selectedIds = Array.isArray(form.yandex_direct_region_ids) ? form.yandex_direct_region_ids : []
    const ids = [...new Set(selectedIds.map((id) => Number(id)).filter(Boolean))]

    if (form.use_for_yandex_direct && !ids.length) {
        setError('Для active-региона нужно выбрать хотя бы один GeoRegion Яндекса.')
        return
    }

    saving.value = true
    try {
        await axios.put(route('regions.update', selectedRegion.value.id), {
            yandex_direct_region_ids: ids,
            use_for_yandex_direct: form.use_for_yandex_direct,
        })
        dialog.value = false
        await loadRegions()
        setNotice('Yandex Direct mapping сохранён.')
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось сохранить mapping региона.')
    } finally {
        saving.value = false
    }
}

async function toggleDirect(region) {
    const ids = regionIds(region)
    if (!ids.length && !region.use_for_yandex_direct) {
        await openEdit(region)
        setError('Сначала выберите GeoRegion из справочника Яндекса.')
        return
    }

    try {
        await axios.put(route('regions.update', region.id), {
            yandex_direct_region_ids: ids,
            use_for_yandex_direct: !region.use_for_yandex_direct,
        })
        await loadRegions()
    } catch (e) {
        setError(e.response?.data?.message || 'Не удалось переключить регион.')
    }
}

watch(geoSearch, (value) => {
    if (!dialog.value) {
        return
    }

    window.clearTimeout(geoSearchTimer)
    geoSearchTimer = window.setTimeout(() => loadYandexGeoRegions(value, true), 320)
})

onMounted(async () => {
    await Promise.all([loadCountries(), loadRegions()])
})
</script>

<template>
    <section class="regions-page">
        <div class="regions-topline">
            <div>
                <div class="regions-eyebrow">Yandex Direct geo mapping</div>
                <h2>Regions</h2>
            </div>
            <div class="regions-summary">
                <div><span>Total</span><strong>{{ summary.total }}</strong></div>
                <div><span>Mapped</span><strong>{{ summary.mapped }}</strong></div>
                <div><span>Active</span><strong>{{ summary.active }}</strong></div>
                <div><span>Yandex IDs</span><strong>{{ summary.yandexIds }}</strong></div>
            </div>
        </div>

        <v-alert v-if="notice" type="success" variant="tonal" density="compact" class="mb-2">{{ notice }}</v-alert>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-2">{{ error }}</v-alert>

        <div class="regions-toolbar">
            <v-text-field
                v-model="filters.search"
                label="Search"
                density="compact"
                hide-details
                clearable
                @keyup.enter="loadRegions"
            />
            <v-select
                v-model="filters.country_id"
                :items="countries"
                item-title="name"
                item-value="id"
                label="Country"
                density="compact"
                hide-details
                clearable
            />
            <v-select
                v-model="filters.direct"
                :items="directOptions"
                label="Direct"
                density="compact"
                hide-details
                clearable
            />
            <v-btn color="teal-darken-4" size="small" variant="flat" @click="loadRegions">Filter</v-btn>
            <v-btn
                color="teal-darken-3"
                size="small"
                variant="tonal"
                :loading="geoSyncing"
                prepend-icon="mdi-cloud-sync"
                @click="syncYandexGeoRegions"
            >
                Sync Yandex Geo
            </v-btn>
        </div>

        <v-data-table
            :headers="headers"
            :items="regions"
            :loading="loading"
            density="compact"
            fixed-header
            height="calc(100vh - 282px)"
            items-per-page="200"
            class="regions-table"
            hover
        >
            <template #item.name="{ item }">
                <strong class="region-name">{{ item.name }}</strong>
            </template>
            <template #item.area="{ item }">
                {{ item.area ? Number(item.area).toLocaleString('ru-RU') : '-' }}
            </template>
            <template #item.use_for_yandex_direct="{ item }">
                <v-switch
                    :model-value="item.use_for_yandex_direct"
                    color="teal-darken-3"
                    density="compact"
                    hide-details
                    inset
                    @click.stop="toggleDirect(item)"
                />
            </template>
            <template #item.yandex_direct_region_ids="{ item }">
                <div class="region-ids">
                    <v-chip
                        v-for="geo in regionGeoChips(item)"
                        :key="geo.external_region_id"
                        size="x-small"
                        color="teal-darken-3"
                        variant="tonal"
                        class="geo-chip"
                    >
                        <strong>#{{ geo.external_region_id }}</strong>
                        <span>{{ geo.name || 'not cached' }}</span>
                    </v-chip>
                    <span v-if="!regionIds(item).length" class="muted">not mapped</span>
                </div>
            </template>
            <template #item.mapping_status="{ item }">
                <v-chip
                    size="x-small"
                    :color="item.use_for_yandex_direct && regionIds(item).length ? 'teal-darken-3' : regionIds(item).length ? 'blue-grey' : 'orange'"
                    variant="flat"
                >
                    {{ item.use_for_yandex_direct && regionIds(item).length ? 'active' : regionIds(item).length ? 'mapped' : 'empty' }}
                </v-chip>
            </template>
            <template #item.actions="{ item }">
                <v-btn icon="mdi-pencil" size="x-small" variant="text" color="teal-darken-4" @click="openEdit(item)" />
            </template>
        </v-data-table>

        <v-dialog v-model="dialog" max-width="820">
            <v-card class="region-dialog">
                <v-card-title>
                    Yandex Direct GeoRegions: {{ selectedRegion?.name }}
                </v-card-title>
                <v-card-text>
                    <v-alert type="info" density="compact" variant="tonal" class="mb-3">
                        Ищите регион по названию или ID в справочнике Яндекс.Директа. В БД сохраняются именно GeoRegionId Яндекса, не внутренние ID таблицы regions.
                    </v-alert>
                    <v-alert v-if="geoWarning" type="warning" density="compact" variant="tonal" class="mb-3">
                        {{ geoWarning }}
                    </v-alert>
                    <v-switch
                        v-model="form.use_for_yandex_direct"
                        label="Использовать регион в FULL AUTO DIRECT"
                        color="teal-darken-3"
                        density="compact"
                        hide-details
                        inset
                        class="mb-3"
                    />
                    <v-autocomplete
                        v-model="form.yandex_direct_region_ids"
                        v-model:search="geoSearch"
                        :items="availableGeoItems"
                        :loading="geoLoading"
                        item-title="title"
                        item-value="external_region_id"
                        label="Поиск GeoRegions Яндекс.Директа"
                        density="compact"
                        variant="outlined"
                        multiple
                        chips
                        closable-chips
                        clearable
                        no-filter
                        hide-details
                        class="geo-autocomplete"
                    >
                        <template #item="{ props, item }">
                            <v-list-item v-bind="props">
                                <template #title>
                                    <div class="geo-option-title">
                                        <strong>#{{ item.raw.external_region_id }}</strong>
                                        <span>{{ item.raw.name || 'GeoRegion' }}</span>
                                    </div>
                                </template>
                                <template #subtitle>
                                    {{ item.raw.parent_names?.length ? item.raw.parent_names.join(' / ') : 'Yandex Direct GeoRegion' }}
                                </template>
                            </v-list-item>
                        </template>
                    </v-autocomplete>
                    <div class="geo-dialog-actions">
                        <v-btn
                            color="teal-darken-3"
                            size="small"
                            variant="tonal"
                            :loading="geoSyncing"
                            prepend-icon="mdi-cloud-sync"
                            @click="syncYandexGeoRegions"
                        >
                            Полная синхронизация справочника
                        </v-btn>
                        <span class="muted">Для обычной работы достаточно поиска: найденные регионы кэшируются автоматически.</span>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Close</v-btn>
                    <v-btn color="teal-darken-4" variant="flat" :loading="saving" @click="saveRegion">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.regions-page {
    min-height: calc(100vh - 150px);
    padding: 8px;
    border: 1px solid rgba(0, 105, 92, 0.14);
    border-radius: 12px;
    background: linear-gradient(135deg, #ecfffb 0%, #ffffff 42%, #eefaf8 100%);
    color: #073b37;
}

.regions-topline,
.regions-toolbar,
.region-ids,
.geo-dialog-actions,
.geo-option-title {
    display: flex;
    align-items: center;
    gap: 6px;
}

.regions-topline {
    justify-content: space-between;
    margin-bottom: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(0, 105, 92, 0.14);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.8);
}

.regions-eyebrow {
    color: rgba(0, 77, 64, 0.58);
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.regions-topline h2 {
    margin: 0;
    color: #004d40;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 24px;
    font-weight: 900;
    letter-spacing: -0.04em;
}

.regions-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(76px, 1fr));
    gap: 6px;
    min-width: 360px;
}

.regions-summary div {
    padding: 4px 7px;
    border: 1px solid rgba(0, 105, 92, 0.14);
    border-radius: 8px;
    background: #e0f2f1;
}

.regions-summary span,
.regions-summary strong {
    display: block;
}

.regions-summary span {
    color: rgba(0, 77, 64, 0.62);
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
}

.regions-summary strong {
    color: #004d40;
    font-size: 15px;
    font-weight: 900;
    text-align: right;
}

.regions-toolbar {
    flex-wrap: wrap;
    margin-bottom: 6px;
}

.regions-toolbar :deep(.v-input) {
    max-width: 230px;
}

.regions-table {
    border: 1px solid rgba(0, 105, 92, 0.14);
    font-size: 11px;
}

.regions-table :deep(th) {
    height: 28px !important;
    background: #00796b !important;
    color: #fff !important;
    font-size: 11px;
    font-weight: 900 !important;
    white-space: nowrap;
}

.regions-table :deep(td) {
    height: 30px !important;
    padding: 2px 6px !important;
    border-bottom: 1px solid #d7f1ed !important;
    white-space: nowrap;
}

.regions-table :deep(tr:hover td) {
    background: #eefaf8 !important;
}

.region-name {
    color: #00695c;
    font-size: 12px;
}

.region-ids {
    flex-wrap: wrap;
    max-width: 420px;
}

.geo-chip {
    max-width: 190px;
}

.geo-chip :deep(.v-chip__content) {
    gap: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.geo-option-title {
    min-width: 0;
    color: #004d40;
}

.geo-option-title span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.geo-autocomplete :deep(.v-field__input) {
    min-height: 36px;
    padding-top: 2px;
    padding-bottom: 2px;
}

.geo-dialog-actions {
    justify-content: space-between;
    flex-wrap: wrap;
    margin-top: 10px;
}

.muted {
    color: rgba(0, 77, 64, 0.5);
    font-size: 11px;
}

.region-dialog {
    border-radius: 12px;
}

@media (max-width: 900px) {
    .regions-topline {
        align-items: stretch;
        flex-direction: column;
    }

    .regions-summary {
        min-width: 0;
        grid-template-columns: repeat(2, minmax(76px, 1fr));
    }
}
</style>
