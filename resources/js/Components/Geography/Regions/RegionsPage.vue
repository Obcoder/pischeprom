<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
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

const filters = reactive({
    search: '',
    country_id: null,
    direct: null,
})

const form = reactive({
    yandex_direct_region_ids_text: '',
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
    { title: 'Yandex RegionIds', key: 'yandex_direct_region_ids', minWidth: 210 },
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

function regionIds(region) {
    return Array.isArray(region?.yandex_direct_region_ids)
        ? region.yandex_direct_region_ids.map((id) => Number(id)).filter(Boolean)
        : []
}

function idsToText(ids) {
    return (ids || []).join('\n')
}

function textToIds(text) {
    return String(text || '')
        .split(/[\s,;]+/)
        .map((item) => Number(item.trim()))
        .filter((item) => Number.isInteger(item) && item > 0)
        .filter((item, index, list) => list.indexOf(item) === index)
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

function openEdit(region) {
    selectedRegion.value = region
    form.yandex_direct_region_ids_text = idsToText(regionIds(region))
    form.use_for_yandex_direct = Boolean(region.use_for_yandex_direct)
    dialog.value = true
}

async function saveRegion() {
    if (!selectedRegion.value) return

    saving.value = true
    try {
        await axios.put(route('regions.update', selectedRegion.value.id), {
            yandex_direct_region_ids: textToIds(form.yandex_direct_region_ids_text),
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
        openEdit(region)
        setError('Сначала укажите Yandex RegionIds.')
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
                        v-for="id in regionIds(item)"
                        :key="id"
                        size="x-small"
                        color="teal-darken-3"
                        variant="tonal"
                    >
                        {{ id }}
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

        <v-dialog v-model="dialog" max-width="620">
            <v-card class="region-dialog">
                <v-card-title>
                    Yandex Direct mapping: {{ selectedRegion?.name }}
                </v-card-title>
                <v-card-text>
                    <v-alert type="info" density="compact" variant="tonal" class="mb-3">
                        Вводите именно Yandex Direct RegionIds, не внутренние ID из таблицы regions. Можно несколько ID через пробел, запятую или с новой строки.
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
                    <v-textarea
                        v-model="form.yandex_direct_region_ids_text"
                        label="Yandex RegionIds"
                        density="compact"
                        variant="outlined"
                        rows="6"
                        hint="Например: 2 для Санкт-Петербурга, 213 для Москвы, если эти ID соответствуют справочнику Яндекса."
                        persistent-hint
                    />
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
.region-ids {
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
    max-width: 260px;
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
