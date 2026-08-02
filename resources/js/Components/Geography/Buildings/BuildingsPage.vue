<script setup>
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { route } from 'ziggy-js'

const buildings = ref([])
const cities = ref([])
const buildingTypes = ref([])
const loading = ref(false)
const referencesLoading = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const dialog = ref(false)
const selectedBuilding = ref(null)
const notice = ref('')
const error = ref('')
const validationErrors = ref({})

const filters = reactive({
    search: '',
    city_id: null,
    building_type_id: null,
})

const form = reactive({
    city_id: null,
    building_type_id: null,
    address: '',
    postcode: '',
})

const headers = [
    { title: 'Карта', key: 'map', align: 'center', sortable: false, width: 72 },
    { title: 'Город / регион', key: 'city', sortable: true, minWidth: 190 },
    { title: 'Адрес', key: 'address', sortable: true, minWidth: 260 },
    { title: 'Индекс', key: 'postcode', sortable: true, width: 112 },
    { title: 'Тип', key: 'building_type', sortable: false, minWidth: 150 },
    { title: 'Units', key: 'units', sortable: false, minWidth: 220 },
    { title: '', key: 'actions', align: 'end', sortable: false, width: 104 },
]

function normalized(value) {
    return String(value ?? '').trim().toLocaleLowerCase('ru-RU')
}

function cityTitle(city) {
    const region = city?.region?.name
        || city?.region_name
        || (typeof city?.region === 'string' ? city.region : '')

    return [city?.name, region].filter(Boolean).join(', ')
}

const filteredBuildings = computed(() => {
    const search = normalized(filters.search)

    return buildings.value.filter((building) => {
        if (filters.city_id && Number(building.city_id) !== Number(filters.city_id)) {
            return false
        }

        if (filters.building_type_id
            && Number(building.building_type_id) !== Number(filters.building_type_id)) {
            return false
        }

        if (!search) {
            return true
        }

        const haystack = [
            building.address,
            building.postcode,
            building.city?.name,
            building.city?.region?.name,
            building.city?.region?.country?.name,
            building.building_type?.name,
            ...(building.units || []).map((unit) => unit.name),
        ].map(normalized).join(' ')

        return haystack.includes(search)
    })
})

const summary = computed(() => ({
    total: buildings.value.length,
    filtered: filteredBuildings.value.length,
    cities: new Set(buildings.value.map((building) => building.city_id).filter(Boolean)).size,
    withUnits: buildings.value.filter((building) => building.units?.length).length,
}))

function clearMessages() {
    notice.value = ''
    error.value = ''
    validationErrors.value = {}
}

function resetForm(building = null) {
    form.city_id = building?.city_id ?? building?.city?.id ?? null
    form.building_type_id = building?.building_type_id ?? building?.building_type?.id ?? null
    form.address = building?.address ?? ''
    form.postcode = building?.postcode ?? ''
}

function openCreate() {
    clearMessages()
    selectedBuilding.value = null
    resetForm()
    dialog.value = true
}

function openEdit(building) {
    clearMessages()
    selectedBuilding.value = building
    resetForm(building)
    dialog.value = true
}

async function loadBuildings() {
    loading.value = true
    error.value = ''

    try {
        const response = await axios.get(route('buildings.index'))
        buildings.value = Array.isArray(response.data)
            ? response.data
            : response.data?.data ?? []
    } catch (requestError) {
        console.error(requestError)
        error.value = requestError.response?.data?.message || 'Не удалось загрузить здания.'
    } finally {
        loading.value = false
    }
}

async function loadReferences() {
    referencesLoading.value = true

    try {
        const [citiesResponse, typesResponse] = await Promise.all([
            axios.get(route('cities.index'), {
                params: {
                    itemsPerPage: -1,
                    sortBy: [{ key: 'name', order: 'asc' }],
                },
            }),
            axios.get(route('building-types.index')),
        ])

        cities.value = citiesResponse.data?.items
            ?? citiesResponse.data?.data
            ?? citiesResponse.data?.cities
            ?? []
        buildingTypes.value = Array.isArray(typesResponse.data)
            ? typesResponse.data
            : typesResponse.data?.data ?? []
    } catch (requestError) {
        console.error(requestError)
        error.value = requestError.response?.data?.message || 'Не удалось загрузить справочники городов и типов зданий.'
    } finally {
        referencesLoading.value = false
    }
}

async function refresh() {
    clearMessages()
    await Promise.all([loadBuildings(), loadReferences()])
}

async function saveBuilding() {
    clearMessages()

    if (!form.city_id || !form.address.trim()) {
        validationErrors.value = {
            city_id: !form.city_id ? ['Выберите населённый пункт.'] : [],
            address: !form.address.trim() ? ['Укажите адрес.'] : [],
        }
        return
    }

    saving.value = true

    const payload = {
        city_id: form.city_id,
        building_type_id: form.building_type_id || null,
        address: form.address.trim(),
        postcode: form.postcode?.trim() || null,
    }

    try {
        if (selectedBuilding.value?.id) {
            await axios.patch(route('buildings.update', selectedBuilding.value.id), payload)
            notice.value = 'Здание обновлено.'
        } else {
            await axios.post(route('buildings.store'), payload)
            notice.value = 'Здание добавлено.'
        }

        dialog.value = false
        await loadBuildings()
    } catch (requestError) {
        console.error(requestError)
        validationErrors.value = requestError.response?.data?.errors || {}
        error.value = requestError.response?.data?.message || 'Не удалось сохранить здание.'
    } finally {
        saving.value = false
    }
}

async function deleteBuilding(building) {
    if (!window.confirm(`Удалить здание «${building.address}»?`)) {
        return
    }

    clearMessages()
    deletingId.value = building.id

    try {
        await axios.delete(route('buildings.destroy', building.id))
        notice.value = 'Здание удалено.'
        await loadBuildings()
    } catch (requestError) {
        console.error(requestError)
        error.value = requestError.response?.data?.message || 'Не удалось удалить здание.'
    } finally {
        deletingId.value = null
    }
}

onMounted(refresh)
</script>

<template>
    <v-theme-provider theme="light">
        <section class="buildings-page">
            <header class="buildings-page__header">
                <div>
                    <div class="buildings-page__eyebrow">Логистика / Buildings</div>
                    <h2>Здания и адреса</h2>
                    <p>Адресный справочник городов, Units и логистических точек.</p>
                </div>

                <div class="buildings-page__actions">
                    <v-btn
                        color="green-darken-2"
                        variant="tonal"
                        prepend-icon="mdi-refresh"
                        :loading="loading || referencesLoading"
                        @click="refresh"
                    >
                        Обновить
                    </v-btn>
                    <v-btn
                        color="green-darken-2"
                        variant="flat"
                        prepend-icon="mdi-office-building-plus-outline"
                        @click="openCreate"
                    >
                        Добавить здание
                    </v-btn>
                </div>
            </header>

            <v-alert v-if="notice" type="success" variant="tonal" density="compact" closable @click:close="notice = ''">
                {{ notice }}
            </v-alert>
            <v-alert v-if="error" type="error" variant="tonal" density="compact" closable @click:close="error = ''">
                {{ error }}
            </v-alert>

            <div class="buildings-summary">
                <div><span>Всего</span><strong>{{ summary.total }}</strong></div>
                <div><span>В выборке</span><strong>{{ summary.filtered }}</strong></div>
                <div><span>Городов</span><strong>{{ summary.cities }}</strong></div>
                <div><span>Связаны с Units</span><strong>{{ summary.withUnits }}</strong></div>
            </div>

            <div class="buildings-filters">
                <v-text-field
                    v-model="filters.search"
                    label="Адрес, индекс, город, Unit"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
                <v-autocomplete
                    v-model="filters.city_id"
                    :items="cities"
                    :item-title="cityTitle"
                    item-value="id"
                    label="Город"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                    :loading="referencesLoading"
                />
                <v-select
                    v-model="filters.building_type_id"
                    :items="buildingTypes"
                    item-title="name"
                    item-value="id"
                    label="Тип здания"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                    :loading="referencesLoading"
                />
                <v-btn
                    variant="text"
                    color="green-darken-3"
                    prepend-icon="mdi-filter-remove-outline"
                    @click="Object.assign(filters, { search: '', city_id: null, building_type_id: null })"
                >
                    Сбросить
                </v-btn>
            </div>

            <v-data-table
                :headers="headers"
                :items="filteredBuildings"
                :loading="loading"
                :items-per-page="100"
                :items-per-page-options="[25, 50, 100, 200, { value: -1, title: 'Все' }]"
                item-value="id"
                fixed-header
                height="min(68vh, 780px)"
                density="compact"
                hover
                class="buildings-table"
            >
                <template #item.map="{ item }">
                    <a
                        v-if="item.city?.yandexmapsgeo"
                        :href="item.city.yandexmapsgeo"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="building-map-link"
                        title="Открыть город на Яндекс Картах"
                    >
                        <v-icon icon="mdi-map-marker-outline" size="17" />
                    </a>
                    <span v-else class="buildings-muted">—</span>
                </template>

                <template #item.city="{ item }">
                    <Link v-if="item.city?.id" :href="route('city.show', item.city.id)" class="building-city-link">
                        {{ item.city.name }}
                    </Link>
                    <strong v-else>—</strong>
                    <small>{{ item.city?.region?.name || 'Регион не указан' }}</small>
                </template>

                <template #item.address="{ item }">
                    <strong class="building-address">{{ item.address }}</strong>
                </template>

                <template #item.postcode="{ item }">
                    <span class="building-postcode">{{ item.postcode || '—' }}</span>
                </template>

                <template #item.building_type="{ item }">
                    <v-chip v-if="item.building_type?.id" size="x-small" color="blue-grey" variant="tonal">
                        {{ item.building_type.name }}
                    </v-chip>
                    <span v-else class="buildings-muted">не указан</span>
                </template>

                <template #item.units="{ item }">
                    <div v-if="item.units?.length" class="building-units">
                        <v-chip v-for="unit in item.units" :key="unit.id" size="x-small" color="green-darken-2" variant="tonal">
                            {{ unit.name }}
                        </v-chip>
                    </div>
                    <span v-else class="buildings-muted">нет связей</span>
                </template>

                <template #item.actions="{ item }">
                    <div class="building-row-actions">
                        <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" title="Редактировать" @click="openEdit(item)" />
                        <v-btn
                            icon="mdi-delete-outline"
                            size="x-small"
                            variant="text"
                            color="red"
                            title="Удалить"
                            :loading="deletingId === item.id"
                            @click="deleteBuilding(item)"
                        />
                    </div>
                </template>

                <template #no-data>
                    <div class="buildings-empty">Здания по выбранным фильтрам не найдены.</div>
                </template>
            </v-data-table>

            <v-dialog v-model="dialog" max-width="760" persistent>
                <v-card class="building-dialog">
                    <v-card-title class="building-dialog__title">
                        <div>
                            <span>{{ selectedBuilding ? 'Редактирование здания' : 'Новое здание' }}</span>
                            <small v-if="selectedBuilding">#{{ selectedBuilding.id }}</small>
                        </div>
                        <v-btn icon="mdi-close" variant="text" :disabled="saving" @click="dialog = false" />
                    </v-card-title>

                    <v-divider />

                    <v-card-text>
                        <v-form @submit.prevent="saveBuilding">
                            <div class="building-form-grid">
                                <v-autocomplete
                                    v-model="form.city_id"
                                    :items="cities"
                                    :item-title="cityTitle"
                                    item-value="id"
                                    label="Населённый пункт *"
                                    variant="outlined"
                                    density="comfortable"
                                    :loading="referencesLoading"
                                    :error-messages="validationErrors.city_id"
                                    class="building-form-grid__wide"
                                />
                                <v-text-field
                                    v-model="form.address"
                                    label="Адрес *"
                                    placeholder="ул. Ленина, 10"
                                    variant="outlined"
                                    density="comfortable"
                                    :error-messages="validationErrors.address"
                                    class="building-form-grid__wide"
                                />
                                <v-text-field
                                    v-model="form.postcode"
                                    label="Почтовый индекс"
                                    variant="outlined"
                                    density="comfortable"
                                    :error-messages="validationErrors.postcode"
                                />
                                <v-select
                                    v-model="form.building_type_id"
                                    :items="buildingTypes"
                                    item-title="name"
                                    item-value="id"
                                    label="Тип здания"
                                    variant="outlined"
                                    density="comfortable"
                                    clearable
                                    :loading="referencesLoading"
                                    :error-messages="validationErrors.building_type_id"
                                />
                            </div>
                        </v-form>
                    </v-card-text>

                    <v-divider />

                    <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" :disabled="saving" @click="dialog = false">Отмена</v-btn>
                        <v-btn color="green-darken-2" variant="flat" prepend-icon="mdi-content-save-outline" :loading="saving" @click="saveBuilding">
                            Сохранить
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </section>
    </v-theme-provider>
</template>

<style scoped>
.buildings-page {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    min-width: 0;
    gap: 14px;
    padding: 16px;
    overflow: hidden;
    border: 1px solid #d8e5dc;
    border-radius: 14px;
    background: linear-gradient(145deg, #f7fbf8 0%, #fff 42%, #f4f8f5 100%);
    color: #26312b;
}

.buildings-page__header,
.buildings-page__actions,
.building-row-actions,
.building-units,
.building-dialog__title {
    display: flex;
    align-items: center;
}

.buildings-page__header {
    justify-content: space-between;
    gap: 18px;
}

.buildings-page__header > * {
    min-width: 0;
}

.buildings-page__header h2 {
    margin: 1px 0 3px;
    color: #1f442b;
    font-size: 1.4rem;
    line-height: 1.15;
}

.buildings-page__header p {
    margin: 0;
    color: #68766d;
    font-size: .84rem;
}

.buildings-page__eyebrow {
    color: #3d7752;
    font-size: .65rem;
    font-weight: 850;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.buildings-page__actions,
.building-row-actions,
.building-units {
    flex-wrap: wrap;
    gap: 6px;
}

.buildings-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(120px, 1fr));
    gap: 8px;
}

.buildings-summary div {
    padding: 9px 11px;
    border: 1px solid #dce8df;
    border-radius: 10px;
    background: rgba(255, 255, 255, .88);
}

.buildings-summary span,
.buildings-summary strong,
.building-city-link + small {
    display: block;
}

.buildings-summary span {
    color: #718078;
    font-size: .65rem;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.buildings-summary strong {
    margin-top: 2px;
    color: #285a38;
    font-size: 1.15rem;
}

.buildings-filters {
    display: grid;
    grid-template-columns: minmax(260px, 1.6fr) minmax(220px, 1fr) minmax(180px, .8fr) auto;
    align-items: center;
    gap: 8px;
    padding: 10px;
    border: 1px solid #dce8df;
    border-radius: 10px;
    background: #f7faf8;
}

.buildings-table {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #dce8df;
    border-radius: 10px;
    background: #fff;
}

.buildings-table :deep(.v-table__wrapper) {
    overflow-x: auto;
}

.buildings-table :deep(th) {
    background: #edf5ef !important;
    color: #355541 !important;
    font-size: .72rem;
    font-weight: 850 !important;
    white-space: nowrap;
}

.building-map-link {
    display: inline-grid;
    width: 28px;
    height: 28px;
    place-items: center;
    border-radius: 50%;
    background: #e4f2e8;
    color: #2d7141;
}

.building-city-link {
    color: #256c3a;
    font-weight: 800;
    text-decoration: none;
}

.building-city-link:hover {
    text-decoration: underline;
}

.building-city-link + small,
.buildings-muted {
    color: #7b877f;
    font-size: .72rem;
}

.building-address {
    color: #293c31;
    overflow-wrap: anywhere;
}

.building-postcode {
    font-family: "JetBrains Mono", monospace;
    font-size: .78rem;
}

.buildings-empty {
    padding: 42px 20px;
    color: #708078;
    text-align: center;
}

.building-dialog__title {
    justify-content: space-between;
    gap: 12px;
}

.building-dialog__title div,
.building-dialog__title small {
    display: grid;
}

.building-dialog__title small {
    color: #79857d;
    font-size: .7rem;
}

.building-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding-top: 6px;
}

.building-form-grid__wide {
    grid-column: 1 / -1;
}

@media (max-width: 900px) {
    .buildings-page__header {
        align-items: stretch;
        flex-direction: column;
    }

    .buildings-summary,
    .buildings-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .buildings-page {
        padding: 10px;
    }

    .buildings-summary,
    .buildings-filters,
    .building-form-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .building-form-grid__wide {
        grid-column: auto;
    }

    .buildings-page__actions {
        align-items: stretch;
        flex-direction: column;
    }

    .buildings-page__actions .v-btn {
        width: 100%;
    }
}
</style>
