<script setup>
import { computed, reactive, watch } from 'vue'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    mode: {
        type: String,
        default: 'create',
    },
    city: {
        type: Object,
        default: null,
    },
    regions: {
        type: Array,
        default: () => [],
    },
    loadingRegions: Boolean,
})

const emit = defineEmits(['save'])

const emptyForm = () => ({
    name: null,
    region_id: null,
    wiki: null,
    yandexmapsgeo: null,
    twogis: null,
    latitude: null,
    longitude: null,
    populations: [],
})

const form = reactive(emptyForm())

const title = computed(() => {
    return props.mode === 'create'
        ? 'Добавить город'
        : `Редактировать город: ${props.city?.name ?? ''}`
})

function resetForm() {
    Object.assign(form, emptyForm())

    if (props.city) {
        form.name = props.city.name
        form.region_id = props.city.region_id
        form.wiki = props.city.wiki
        form.yandexmapsgeo = props.city.yandexmapsgeo
        form.twogis = props.city.twogis
        form.latitude = props.city.latitude
        form.longitude = props.city.longitude

        form.populations = (props.city.populations ?? []).map(item => ({
            year: item.year,
            population: item.population,
        }))
    }
}

function addPopulationRow() {
    form.populations.push({
        year: new Date().getFullYear(),
        population: null,
    })
}

function removePopulationRow(index) {
    form.populations.splice(index, 1)
}

function submit() {
    emit('save', {
        name: form.name,
        region_id: form.region_id,
        wiki: form.wiki,
        yandexmapsgeo: form.yandexmapsgeo,
        twogis: form.twogis,
        latitude: form.latitude,
        longitude: form.longitude,
        populations: form.populations
            .filter(item => item.year && item.population !== null && item.population !== '')
            .map(item => ({
                year: Number(item.year),
                population: Number(item.population),
            })),
    })
}

watch(
    () => model.value,
    value => {
        if (value) {
            resetForm()
        }
    }
)

watch(
    () => props.city,
    () => {
        if (model.value) {
            resetForm()
        }
    }
)
</script>

<template>
    <v-dialog
        v-model="model"
        width="980"
        scrollable
    >
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>{{ title }}</span>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="model = false"
                />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-form @submit.prevent="submit">
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.name"
                                label="Название города"
                                variant="solo"
                                density="comfortable"
                                color="teal"
                                class="font-UnderdogRegular"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.region_id"
                                :items="regions"
                                :loading="loadingRegions"
                                item-title="name"
                                item-value="id"
                                label="Регион"
                                variant="filled"
                                density="comfortable"
                                color="purple"
                            >
                                <template #item="{ props: itemProps, item }">
                                    <v-list-item
                                        v-bind="itemProps"
                                        :subtitle="item.raw.country?.name"
                                    />
                                </template>
                            </v-autocomplete>
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col>
                            <v-text-field
                                v-model="form.wiki"
                                label="Wikipedia"
                                placeholder="https://ru.wikipedia.org/wiki/Москва"
                                variant="outlined"
                                density="comfortable"
                                color="blue"
                            />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col>
                            <v-text-field
                                v-model="form.yandexmapsgeo"
                                label="Yandex Maps Geo"
                                variant="solo"
                                density="comfortable"
                            />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.latitude"
                                label="Широта"
                                variant="outlined"
                                density="comfortable"
                                type="number"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-text-field
                                v-model="form.longitude"
                                label="Долгота"
                                variant="outlined"
                                density="comfortable"
                                type="number"
                            />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col>
                            <v-text-field
                                v-model="form.twogis"
                                label="2GIS"
                                variant="solo"
                                density="compact"
                            />
                        </v-col>
                    </v-row>

                    <v-divider class="my-4" />

                    <div class="d-flex justify-space-between align-center mb-2">
                        <div>
                            <div class="text-subtitle-2">
                                Население по годам
                            </div>
                            <div class="text-caption text-grey">
                                Можно добавить сразу несколько значений: год — численность.
                            </div>
                        </div>

                        <v-btn
                            text="+ год"
                            color="teal"
                            variant="tonal"
                            density="compact"
                            @click="addPopulationRow"
                        />
                    </div>

                    <v-row
                        v-for="(population, index) in form.populations"
                        :key="index"
                        dense
                    >
                        <v-col cols="5">
                            <v-text-field
                                v-model="population.year"
                                label="Год"
                                type="number"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="6">
                            <v-text-field
                                v-model="population.population"
                                label="Население"
                                type="number"
                                variant="outlined"
                                density="compact"
                                hide-details
                            />
                        </v-col>

                        <v-col cols="1" class="d-flex align-center">
                            <v-btn
                                icon="mdi-close"
                                size="x-small"
                                color="red"
                                variant="text"
                                @click="removePopulationRow(index)"
                            />
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>

            <v-divider />

            <v-card-actions>
                <v-spacer />

                <v-btn
                    text="Отмена"
                    variant="text"
                    @click="model = false"
                />

                <v-btn
                    text="Сохранить"
                    color="teal"
                    variant="elevated"
                    @click="submit"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
