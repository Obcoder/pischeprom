<script setup>
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import CityHeroCard from '@/Components/Geography/Cities/Show/CityHeroCard.vue'
import CityStatsCards from '@/Components/Geography/Cities/Show/CityStatsCards.vue'
import CityWikipediaCard from '@/Components/Geography/Cities/Show/CityWikipediaCard.vue'
import CityPopulationTimeline from '@/Components/Geography/Cities/Show/CityPopulationTimeline.vue'
import CityRegionCard from '@/Components/Geography/Cities/Show/CityRegionCard.vue'
import CityBuildingsCard from '@/Components/Geography/Cities/Show/CityBuildingsCard.vue'
import CityEntitiesCard from '@/Components/Geography/Cities/Show/CityEntitiesCard.vue'
import CityUnitsCard from '@/Components/Geography/Cities/Show/CityUnitsCard.vue'
import CityYandexMapCard from '@/Components/Geography/Cities/Show/CityYandexMapCard.vue'

import CityFormDialog from '@/Components/Geography/Cities/CityFormDialog.vue'
import CityPopulationDialog from '@/Components/Geography/Cities/CityPopulationDialog.vue'

import { useCityShow } from '@/Composables/Geography/useCityShow.js'
import { useRegions } from '@/Composables/Geography/useRegions.js'

const props = defineProps({
    cityId: {
        type: [Number, String],
        required: true,
    },
    initialCity: {
        type: Object,
        default: null,
    },
})

const {
    city,
    loading,
    latestPopulation,
    latestPopulationYear,
    fetchCity,
    updateCity,
} = useCityShow(props.cityId, props.initialCity)

const {
    regions,
    loadingRegions,
    fetchRegions,
} = useRegions()

const formDialog = ref(false)
const populationDialog = ref(false)

const snackbar = ref({
    show: false,
    text: '',
    color: 'success',
})

function openEditDialog() {
    formDialog.value = true
}

function openPopulationDialog() {
    populationDialog.value = true
}

async function handleSaveCity(payload) {
    try {
        await updateCity(payload)
        await fetchCity()

        formDialog.value = false

        snackbar.value = {
            show: true,
            text: 'Город обновлён',
            color: 'success',
        }
    } catch (error) {
        console.error(error)

        snackbar.value = {
            show: true,
            text: 'Ошибка обновления города',
            color: 'error',
        }
    }
}

async function handlePopulationSaved() {
    await fetchCity()

    snackbar.value = {
        show: true,
        text: 'Данные по населению обновлены',
        color: 'success',
    }
}

onMounted(async () => {
    await Promise.all([
        fetchRegions(),
        fetchCity(props.cityId),
    ])
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <div class="mb-3">
                    <button
                        type="button"
                        class="text-teal-lighten-3 hover:text-white text-sm"
                        @click="window.history.back()"
                    >
                        ← Grossbuch / Geography / Cities
                    </button>
                </div>

                <v-skeleton-loader
                    v-if="loading && !city"
                    type="card, article, table"
                />

                <template v-else-if="city">
                    <CityHeroCard
                        :city="city"
                        :latest-population="latestPopulation"
                        :latest-population-year="latestPopulationYear"
                        @edit="openEditDialog"
                        @population="openPopulationDialog"
                    />

                    <CityStatsCards
                        :city="city"
                        class="mt-3"
                    />

                    <v-row class="mt-1">
                        <v-col cols="12" lg="5">
                            <CityWikipediaCard :city="city" />
                        </v-col>

                        <v-col cols="12" lg="4">
                            <CityYandexMapCard :city="city" />
                        </v-col>

                        <v-col cols="12" lg="3">
                            <CityPopulationTimeline
                                :city="city"
                                @edit="openPopulationDialog"
                            />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col cols="12">
                            <CityRegionCard :city="city" />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col cols="12" lg="4">
                            <CityBuildingsCard :city="city" />
                        </v-col>

                        <v-col cols="12" lg="4">
                            <CityEntitiesCard :city="city" />
                        </v-col>

                        <v-col cols="12" lg="4">
                            <CityUnitsCard :city="city" />
                        </v-col>
                    </v-row>

                    <CityFormDialog
                        v-model="formDialog"
                        mode="edit"
                        :city="city"
                        :regions="regions"
                        :loading-regions="loadingRegions"
                        @save="handleSaveCity"
                    />

                    <CityPopulationDialog
                        v-model="populationDialog"
                        :city="city"
                        @saved="handlePopulationSaved"
                    />
                </template>

                <v-alert
                    v-else
                    type="warning"
                    variant="tonal"
                >
                    Город не найден.
                </v-alert>
            </v-col>
        </v-row>

        <v-snackbar
            v-model="snackbar.show"
            :color="snackbar.color"
            :timeout="2500"
        >
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>
