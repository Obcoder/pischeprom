<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import debounce from 'lodash.debounce'

import CityFilters from '@/Components/Geography/Cities/CityFilters.vue'
import CityTable from '@/Components/Geography/Cities/CityTable.vue'
import CityFormDialog from '@/Components/Geography/Cities/CityFormDialog.vue'
import CityPopulationDialog from '@/Components/Geography/Cities/CityPopulationDialog.vue'

import { useCities } from '@/Composables/Geography/useCities.js'
import { useRegions } from '@/Composables/Geography/useRegions.js'
import { useCountries } from '@/Composables/Geography/useCountries.js'
import { useCityRelations } from '@/Composables/Geography/useCityRelations.js'

const {
    cities,
    totalItems,
    loading,
    options,
    filters,
    fetchCities,
    storeCity,
    updateCity,
    deleteCity,
    resetFilters,
} = useCities()

const {
    regions,
    loadingRegions,
    fetchRegions,
} = useRegions()

const {
    countries,
    loadingCountries,
    fetchCountries,
} = useCountries()

const {
    units,
    entities,
    loadingUnits,
    loadingEntities,
    fetchUnits,
    fetchEntities,
} = useCityRelations()

const formDialog = ref(false)
const populationDialog = ref(false)

const selectedCity = ref(null)
const mode = ref('create')

const snackbar = ref({
    show: false,
    text: '',
    color: 'success',
})

const regionsForFilter = computed(() => {
    if (!filters.country_id) {
        return regions.value
    }

    return regions.value.filter(region => region.country_id === filters.country_id)
})

function openCreateDialog() {
    selectedCity.value = null
    mode.value = 'create'
    formDialog.value = true
}

function openEditDialog(city) {
    selectedCity.value = city
    mode.value = 'edit'
    formDialog.value = true
}

function openPopulationDialog(city) {
    selectedCity.value = city
    populationDialog.value = true
}

async function handleSaveCity(payload) {
    try {
        if (mode.value === 'create') {
            await storeCity(payload)

            snackbar.value = {
                show: true,
                text: 'Город успешно добавлен',
                color: 'success',
            }
        } else {
            await updateCity(selectedCity.value.id, payload)

            snackbar.value = {
                show: true,
                text: 'Город успешно обновлён',
                color: 'success',
            }
        }

        formDialog.value = false
    } catch (error) {
        console.error(error)

        snackbar.value = {
            show: true,
            text: 'Ошибка сохранения города',
            color: 'error',
        }
    }
}

async function handleDeleteCity(city) {
    const confirmed = window.confirm(`Удалить город "${city.name}"?`)

    if (!confirmed) {
        return
    }

    try {
        await deleteCity(city.id)

        snackbar.value = {
            show: true,
            text: 'Город удалён',
            color: 'success',
        }
    } catch (error) {
        console.error(error)

        snackbar.value = {
            show: true,
            text: 'Ошибка удаления города',
            color: 'error',
        }
    }
}

function handleOptionsUpdate(newOptions) {
    const itemsPerPageChanged = newOptions.itemsPerPage !== options.value.itemsPerPage

    fetchCities({
        ...newOptions,
        page: itemsPerPageChanged ? 1 : newOptions.page,
    })
}

const debouncedFetch = debounce(() => {
    options.value.page = 1
    fetchCities()
}, 400)

watch(
    () => ({
        search: filters.search,
        country_id: filters.country_id,
        region_id: filters.region_id,
        unit_id: filters.unit_id,
        entity_id: filters.entity_id,
        has_buildings: filters.has_buildings,
    }),
    () => {
        debouncedFetch()
    },
    { deep: true }
)

function handleResetFilters() {
    resetFilters()
    fetchCities()
}

onMounted(async () => {
    await Promise.all([
        fetchRegions(),
        fetchCountries(),
        fetchUnits(),
        fetchEntities(),
    ])

    await fetchCities()
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-sheet class="pa-3 rounded border border-teal-800 bg-slate-900">
                    <div class="d-flex align-center justify-space-between mb-3">
                        <div>
                            <div class="text-h6 text-teal-lighten-3 font-ComfortaaVariableFont">
                                Cities
                            </div>
                            <div class="text-caption text-teal-darken-1">
                                География / города / население / Wikipedia
                            </div>
                        </div>

                        <v-btn
                            text="+ 🏰"
                            color="teal"
                            variant="tonal"
                            density="comfortable"
                            @click="openCreateDialog"
                        />
                    </div>

                    <CityFilters
                        v-model:filters="filters"
                        :countries="countries"
                        :regions="regionsForFilter"
                        :units="units"
                        :entities="entities"
                        :loading-countries="loadingCountries"
                        :loading-regions="loadingRegions"
                        :loading-units="loadingUnits"
                        :loading-entities="loadingEntities"
                        @reset="handleResetFilters"
                    />

                    <CityTable
                        :cities="cities"
                        :total-items="totalItems"
                        :loading="loading"
                        :options="options"
                        @update:options="handleOptionsUpdate"
                        @edit="openEditDialog"
                        @delete="handleDeleteCity"
                        @population="openPopulationDialog"
                    />
                </v-sheet>
            </v-col>
        </v-row>

        <CityFormDialog
            v-model="formDialog"
            :mode="mode"
            :city="selectedCity"
            :regions="regions"
            :loading-regions="loadingRegions"
            @save="handleSaveCity"
        />

        <CityPopulationDialog
            v-model="populationDialog"
            :city="selectedCity"
            @saved="fetchCities"
        />

        <v-snackbar
            v-model="snackbar.show"
            :color="snackbar.color"
            :timeout="2500"
        >
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>
