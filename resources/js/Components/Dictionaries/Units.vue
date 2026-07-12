<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { Link } from '@inertiajs/vue3'
import MaxContactButton from '@/Components/Max/MaxContactButton.vue'

const units = ref([])
const fields = ref([])
const labels = ref([])
const cities = ref([])
const industries = ref([])
const buildings = ref([])
const emails = ref([])
const telephones = ref([])
const uris = ref([])

const searchUnits = ref('')
const selectedFieldIDs = ref([])
const selectedLabelsIDs = ref([])
const selectedCityIDs = ref([])
const selectedIndustryIDs = ref([])
const showFormUnit = ref(false)
const showFilters = ref(false)
const unitSaving = ref(false)
const unitError = ref('')

const formUri = reactive({ address: '' })
const dialogFormUri = ref(false)

const headerUnits = [
    { key: 'cities', title: 'Города', align: 'start', width: '13%' },
    { key: 'name', title: 'name' },
    { key: 'telephones', title: 'MAX / Тел.', align: 'start', width: '13%' },
    { key: 'industries', title: 'ОКВЭД', align: 'start', width: '10%' },
    { key: 'sales_count', title: 'Sales', align: 'end', width: '7%' },
    { key: 'labels', title: 'labels', align: 'start', sortable: true },
    { key: 'uris', title: 'Uris' },
    { key: 'fields', title: 'Fields', align: 'start', sortable: true, width: '16%' },
]

const defaultUnitForm = () => ({
    name: null,
    is_customer: false,
    is_supplier: false,
    buildings: [],
    cities: [],
    emails: [],
    fields: [],
    industries: [],
    labels: [],
    telephones: [],
    uris: [],
})

const formUnit = reactive(defaultUnitForm())

const activeFilterCount = computed(() => (
    selectedFieldIDs.value.length
    + selectedLabelsIDs.value.length
    + selectedCityIDs.value.length
    + selectedIndustryIDs.value.length
    + (searchUnits.value ? 1 : 0)
))

const filteredUnits = computed(() => {
    let filtered = units.value

    if (searchUnits.value) {
        const search = searchUnits.value.toLowerCase()
        filtered = filtered.filter(item => String(item.name || '').toLowerCase().includes(search))
    }

    if (selectedLabelsIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            const ids = unit.labels?.map(label => label.id) || []
            return selectedLabelsIDs.value.some(id => ids.includes(id))
        })
    }

    if (selectedFieldIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            const ids = unit.fields?.map(field => field.id) || []
            return selectedFieldIDs.value.some(id => ids.includes(id))
        })
    }

    if (selectedCityIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            const ids = unit.cities?.map(city => city.id) || []
            return selectedCityIDs.value.some(id => ids.includes(id))
        })
    }

    if (selectedIndustryIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            const ids = unit.industries?.map(industry => industry.id) || []
            return selectedIndustryIDs.value.some(id => ids.includes(id))
        })
    }

    return filtered
})

async function indexUnits() {
    try {
        const { data } = await axios.get(route('units.index'), {
            params: { search: searchUnits.value || undefined },
        })
        units.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        console.error(e)
        units.value = []
    }
}

async function loadDictionary(url, target, params = {}) {
    try {
        const { data } = await axios.get(url, { params })
        target.value = Array.isArray(data) ? data : (data.data || data.cities || [])
    } catch (e) {
        console.error(e)
        target.value = []
    }
}

async function loadDictionaries() {
    await Promise.all([
        loadDictionary(route('fields.index'), fields),
        loadDictionary(route('labels.index'), labels),
        loadDictionary(route('cities.index'), cities, { per_page: 500 }),
        loadDictionary('/api/industries', industries, { per_page: 500 }),
        loadDictionary('/api/buildings', buildings, { per_page: 500 }),
        loadDictionary('/api/emails', emails, { per_page: 500 }),
        loadDictionary('/api/telephones', telephones, { per_page: 500 }),
        loadDictionary('/api/uris', uris, { per_page: 500 }),
    ])
}

function resetUnitForm() {
    Object.assign(formUnit, defaultUnitForm())
}

function openUnitForm() {
    unitError.value = ''
    showFormUnit.value = true
}

async function storeUnit() {
    unitError.value = ''

    if (!String(formUnit.name || '').trim()) {
        unitError.value = 'Укажите название Unit.'
        return
    }

    unitSaving.value = true

    try {
        await axios.post('/api/units', {
            name: formUnit.name,
            is_customer: formUnit.is_customer,
            is_supplier: formUnit.is_supplier,
            buildings: formUnit.buildings,
            cities: formUnit.cities,
            emails: formUnit.emails,
            fields: formUnit.fields,
            industries: formUnit.industries,
            labels: formUnit.labels,
            telephones: formUnit.telephones,
            uris: formUnit.uris,
        })

        resetUnitForm()
        showFormUnit.value = false
        await indexUnits()
    } catch (e) {
        unitError.value = e?.response?.data?.message
            || Object.values(e?.response?.data?.errors || {})?.flat()?.[0]
            || 'Не удалось сохранить Unit.'
        console.error('store unit error:', e?.response?.data || e)
    } finally {
        unitSaving.value = false
    }
}

async function storeUri() {
    if (!formUri.address) return

    try {
        await axios.post('/api/uris', { address: formUri.address })
        formUri.address = ''
        dialogFormUri.value = false
        await loadDictionary('/api/uris', uris, { per_page: 500 })
    } catch (e) {
        console.error(e)
    }
}

function clearFilters() {
    searchUnits.value = ''
    selectedFieldIDs.value = []
    selectedLabelsIDs.value = []
    selectedCityIDs.value = []
    selectedIndustryIDs.value = []
}

function toggleLabel(labelId) {
    if (selectedLabelsIDs.value.includes(labelId)) {
        selectedLabelsIDs.value = selectedLabelsIDs.value.filter(id => id !== labelId)
    } else {
        selectedLabelsIDs.value.push(labelId)
    }
}

function formatBuildingTitle(building) {
    if (!building) return ''
    return [building.address, building.city?.name].filter(Boolean).join(' / ')
}

function phoneNumber(telephone) {
    return telephone?.number ?? telephone?.telephone ?? telephone?.phone ?? ''
}

onMounted(() => {
    loadDictionaries()
    indexUnits()
})

watch(searchUnits, () => indexUnits())
</script>

<template>
    <v-container fluid>
        <div class="units-toolbar">
            <v-text-field
                v-model="searchUnits"
                label="Поиск по юнитам"
                variant="solo-inverted"
                density="compact"
                color="deep-orange-accent-3"
                hide-details
                class="units-toolbar__search border-2 rounded border-rose-950"
            />

            <v-btn
                text="+ Unit"
                variant="tonal"
                density="comfortable"
                color="deep-purple-darken-1"
                @click="openUnitForm"
            />

            <v-btn
                variant="tonal"
                density="comfortable"
                color="teal-darken-3"
                @click="showFilters = true"
            >
                Фильтры
                <v-badge v-if="activeFilterCount" :content="activeFilterCount" inline color="deep-orange" />
            </v-btn>
        </div>

        <v-data-table
            :items="filteredUnits"
            items-per-page="100"
            :headers="headerUnits"
            fixed-header
            height="888px"
            density="compact"
            class="border border-orange-800 rounded"
            hover
        >
            <template #item.cities="{ item }">
                <div
                    v-for="city in item.cities || []"
                    :key="city.id"
                    class="text-sm font-sans text-lime-600 hover:text-lime-300"
                >
                    {{ city.name }}
                </div>
            </template>

            <template #item.name="{ item }">
                <Link :href="route('web.unit.show', item.id)" class="text-sm">
                    {{ item.name }}
                </Link>
                <div class="units-flags">
                    <span v-if="item.is_customer">customer</span>
                    <span v-if="item.is_supplier">supplier</span>
                </div>
            </template>

            <template #item.telephones="{ item }">
                <div
                    v-if="item.telephones?.length"
                    class="units-max-phones"
                >
                    <span
                        v-for="telephone in item.telephones"
                        :key="telephone.id || phoneNumber(telephone)"
                    >
                        <span>{{ phoneNumber(telephone) }}</span>
                        <MaxContactButton
                            :phone="phoneNumber(telephone)"
                            :unit-id="item.id"
                            :context-title="item.name"
                            size="x-small"
                            color="#fff7ed"
                        />
                    </span>
                </div>
                <span v-else>-</span>
            </template>

            <template #item.industries="{ item }">
                <v-chip
                    v-for="industry in item.industries || []"
                    :key="industry.id"
                    size="x-small"
                    color="teal-darken-3"
                    variant="tonal"
                    class="mr-1 mb-1"
                >
                    {{ industry.code }}
                </v-chip>
            </template>

            <template #item.sales_count="{ item }">
                <span class="units-sales-count">{{ item.sales_count ?? 0 }}</span>
            </template>

            <template #item.labels="{ item }">
                <div v-for="label in item.labels || []" :key="label.id" class="text-xs font-sans text-blue-500">
                    {{ label.name }}
                </div>
            </template>

            <template #item.uris="{ item }">
                <a
                    v-for="uri in item.uris || []"
                    :key="uri.id"
                    :href="uri.address"
                    target="_blank"
                    class="text-xs inline-block mr-1 text-yellow-200"
                >
                    {{ uri.address }}
                </a>
            </template>

            <template #item.fields="{ item }">
                <div v-for="field in item.fields || []" :key="field.id" class="text-[10px] font-sans">
                    {{ field.title }}
                </div>
            </template>
        </v-data-table>

        <v-dialog v-model="showFilters" width="760">
            <v-card>
                <v-toolbar title="Фильтры Units" color="blue-grey-darken-4" density="compact" />
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="selectedFieldIDs"
                                :items="fields"
                                item-title="title"
                                item-value="id"
                                label="Фильтр по полям"
                                multiple
                                chips
                                clearable
                                closable-chips
                                variant="solo-inverted"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="selectedCityIDs"
                                :items="cities"
                                item-title="name"
                                item-value="id"
                                label="Фильтр по городам"
                                multiple
                                chips
                                clearable
                                closable-chips
                                variant="solo-inverted"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-autocomplete
                                v-model="selectedIndustryIDs"
                                :items="industries"
                                item-title="code"
                                item-value="id"
                                label="Фильтр по ОКВЭД"
                                multiple
                                chips
                                clearable
                                closable-chips
                                variant="solo-inverted"
                                density="compact"
                                hide-details
                            >
                                <template #item="{ props: itemProps, item }">
                                    <v-list-item v-bind="itemProps" :subtitle="item.raw.title" />
                                </template>
                            </v-autocomplete>
                        </v-col>
                    </v-row>

                    <div class="units-label-filter">
                        <v-btn
                            v-for="label in labels"
                            :key="label.id"
                            :color="selectedLabelsIDs.includes(label.id) ? 'cyan' : 'blue-grey-darken-2'"
                            :class="{ 'active-btn': selectedLabelsIDs.includes(label.id) }"
                            size="x-small"
                            class="ma-1"
                            @click="toggleLabel(label.id)"
                        >
                            {{ label.name }}
                            <span class="ml-1 text-[8px] bg-teal-700 rounded">{{ label.rank }}</span>
                        </v-btn>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-space-between">
                    <v-btn variant="text" color="error" @click="clearFilters">Сбросить</v-btn>
                    <v-btn variant="tonal" color="teal-darken-3" @click="showFilters = false">Применить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showFormUnit" width="909">
            <v-card>
                <v-card-title>Form Unit</v-card-title>
                <v-card-text>
                    <v-alert
                        v-if="unitError"
                        type="error"
                        variant="tonal"
                        density="compact"
                        class="mb-3"
                    >
                        {{ unitError }}
                    </v-alert>

                    <v-form @submit.prevent>
                        <v-container>
                            <v-row>
                                <v-col cols="12" md="8">
                                    <v-text-field
                                        v-model="formUnit.name"
                                        label="Name"
                                        variant="solo-filled"
                                        density="comfortable"
                                        hide-details
                                    />
                                </v-col>
                                <v-col cols="12" md="2">
                                    <v-switch v-model="formUnit.is_customer" label="Customer" color="teal" hide-details density="compact" />
                                </v-col>
                                <v-col cols="12" md="2">
                                    <v-switch v-model="formUnit.is_supplier" label="Supplier" color="teal" hide-details density="compact" />
                                </v-col>
                            </v-row>

                            <v-row>
                                <v-col cols="9">
                                    <v-autocomplete
                                        v-model="formUnit.uris"
                                        :items="uris"
                                        item-value="id"
                                        item-title="address"
                                        label="Uris selected"
                                        chips
                                        multiple
                                    />
                                </v-col>
                                <v-col cols="3">
                                    <v-btn text="+ uri" @click="dialogFormUri = true" />
                                    <v-dialog v-model="dialogFormUri" width="800">
                                        <v-card>
                                            <v-toolbar title="FORM: Uri" />
                                            <v-card-text>
                                                <v-form @submit.prevent>
                                                    <v-row>
                                                        <v-text-field v-model="formUri.address" label="Uri address" variant="outlined" />
                                                    </v-row>
                                                    <v-row>
                                                        <v-col cols="4">
                                                            <v-btn text="store" block @click="storeUri" />
                                                        </v-col>
                                                    </v-row>
                                                </v-form>
                                            </v-card-text>
                                        </v-card>
                                    </v-dialog>
                                </v-col>
                            </v-row>

                            <v-row>
                                <v-col>
                                    <v-autocomplete
                                        v-model="formUnit.cities"
                                        :items="cities"
                                        item-value="id"
                                        item-title="name"
                                        label="Cities (city_unit)"
                                        multiple
                                        chips
                                        variant="solo"
                                        density="comfortable"
                                        bg-color="teal-darken-4"
                                        hide-details
                                    />
                                </v-col>
                                <v-col>
                                    <v-autocomplete
                                        v-model="formUnit.industries"
                                        :items="industries"
                                        item-value="id"
                                        item-title="code"
                                        label="ОКВЭД"
                                        multiple
                                        chips
                                        variant="solo"
                                        density="comfortable"
                                        bg-color="teal-darken-3"
                                        hide-details
                                    >
                                        <template #item="{ props: itemProps, item }">
                                            <v-list-item v-bind="itemProps" :subtitle="item.raw.title" />
                                        </template>
                                    </v-autocomplete>
                                </v-col>
                            </v-row>

                            <v-row>
                                <v-col>
                                    <v-autocomplete
                                        v-model="formUnit.fields"
                                        :items="fields"
                                        item-value="id"
                                        item-title="title"
                                        label="Fields"
                                        placeholder="Выбери поле"
                                        variant="solo"
                                        density="comfortable"
                                        hide-details
                                        multiple
                                        chips
                                        bg-color="indigo-accent-4"
                                    />
                                </v-col>
                                <v-col>
                                    <v-autocomplete
                                        v-model="formUnit.labels"
                                        :items="labels"
                                        item-value="id"
                                        item-title="name"
                                        label="Labels"
                                        variant="solo"
                                        density="comfortable"
                                        bg-color="blue-grey-darken-4"
                                        item-color="deep-orange-darken-2"
                                        multiple
                                        chips
                                        hide-details
                                    />
                                </v-col>
                            </v-row>

                            <v-row>
                                <v-col>
                                    <v-autocomplete
                                        v-model="formUnit.buildings"
                                        :items="buildings"
                                        :item-title="formatBuildingTitle"
                                        item-value="id"
                                        label="Buildings"
                                        multiple
                                        chips
                                        variant="solo"
                                        density="comfortable"
                                        bg-color="blue-grey-darken-4"
                                    />
                                </v-col>
                                <v-col>
                                    <v-autocomplete
                                        v-model="formUnit.emails"
                                        :items="emails"
                                        item-value="id"
                                        item-title="address"
                                        multiple
                                        chips
                                        label="Emails"
                                        variant="solo-filled"
                                        density="comfortable"
                                        bg-color="deep-orange-accent-4"
                                        hide-details
                                    />
                                </v-col>
                            </v-row>

                            <v-row>
                                <v-col>
                                    <v-autocomplete
                                        v-model="formUnit.telephones"
                                        :items="telephones"
                                        item-value="id"
                                        item-title="number"
                                        multiple
                                        chips
                                        label="Telephones"
                                        variant="solo-filled"
                                        density="comfortable"
                                        bg-color="blue-grey-darken-3"
                                        hide-details
                                    />
                                </v-col>
                            </v-row>
                        </v-container>
                    </v-form>
                </v-card-text>
                <v-card-actions class="justify-start">
                    <v-btn text="Close" :disabled="unitSaving" @click="showFormUnit = false" />
                    <v-btn
                        text="Сохранить"
                        color="teal-darken-3"
                        :loading="unitSaving"
                        :disabled="unitSaving || !formUnit.name"
                        @click="storeUnit"
                    />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.units-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}

.units-toolbar__search {
    max-width: 320px;
}

.units-flags {
    display: flex;
    gap: 3px;
    margin-top: 2px;
}

.units-flags span {
    padding: 1px 5px;
    border-radius: 999px;
    background: rgba(0, 128, 128, 0.14);
    color: #00695c;
    font-size: 0.54rem;
    font-weight: 900;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.units-sales-count {
    color: #5f0f24;
    font-size: 0.8rem;
    font-weight: 950;
}

.units-max-phones {
    display: grid;
    gap: 2px;
    max-height: 48px;
    overflow: hidden;
}

.units-max-phones > span {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    color: #f8fafc;
    font-size: 0.7rem;
    font-weight: 800;
    white-space: nowrap;
}

.units-label-filter {
    display: flex;
    flex-wrap: wrap;
    margin-top: 12px;
}
</style>
