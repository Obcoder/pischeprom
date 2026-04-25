<script setup>
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    city: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['saved'])

const loading = ref(false)
const saving = ref(false)
const populations = ref([])

const form = reactive({
    id: null,
    year: new Date().getFullYear(),
    population: null,
})

const isEdit = computed(() => Boolean(form.id))

const title = computed(() => {
    return props.city
        ? `Население: ${props.city.name}`
        : 'Население города'
})

function resetForm() {
    form.id = null
    form.year = new Date().getFullYear()
    form.population = null
}

async function fetchPopulations() {
    if (!props.city?.id) {
        return
    }

    loading.value = true

    try {
        const response = await axios.get(
            route('cities.populations.index', props.city.id)
        )

        populations.value = response.data
    } catch (error) {
        console.error('City populations loading error:', error)
    } finally {
        loading.value = false
    }
}

function editPopulation(item) {
    form.id = item.id
    form.year = item.year
    form.population = item.population
}

async function savePopulation() {
    if (!props.city?.id) {
        return
    }

    saving.value = true

    const payload = {
        year: Number(form.year),
        population: Number(form.population),
    }

    try {
        if (isEdit.value) {
            await axios.patch(
                route('cities.populations.update', {
                    city: props.city.id,
                    cityPopulation: form.id,
                }),
                payload
            )
        } else {
            await axios.post(
                route('cities.populations.store', props.city.id),
                payload
            )
        }

        resetForm()
        await fetchPopulations()
        emit('saved')
    } catch (error) {
        console.error('City population saving error:', error)
    } finally {
        saving.value = false
    }
}

async function deletePopulation(item) {
    const confirmed = window.confirm(`Удалить население за ${item.year} год?`)

    if (!confirmed) {
        return
    }

    try {
        await axios.delete(
            route('cities.populations.destroy', {
                city: props.city.id,
                cityPopulation: item.id,
            })
        )

        await fetchPopulations()
        emit('saved')
    } catch (error) {
        console.error('City population deleting error:', error)
    }
}

function formatNumber(value) {
    return new Intl.NumberFormat('ru-RU').format(value)
}

watch(
    () => model.value,
    async value => {
        if (value) {
            resetForm()
            await fetchPopulations()
        }
    }
)
</script>

<template>
    <v-dialog
        v-model="model"
        width="720"
    >
        <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
                <span>{{ title }}</span>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="model = false"
                />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row dense>
                    <v-col cols="4">
                        <v-text-field
                            v-model="form.year"
                            label="Год"
                            type="number"
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>

                    <v-col cols="6">
                        <v-text-field
                            v-model="form.population"
                            label="Население"
                            type="number"
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>

                    <v-col cols="2" class="d-flex align-center">
                        <v-btn
                            :text="isEdit ? 'update' : 'add'"
                            :loading="saving"
                            color="teal"
                            variant="elevated"
                            density="compact"
                            @click="savePopulation"
                        />
                    </v-col>
                </v-row>

                <v-divider class="my-3" />

                <v-skeleton-loader
                    v-if="loading"
                    type="table"
                />

                <v-table
                    v-else
                    density="compact"
                    class="rounded border"
                >
                    <thead>
                    <tr>
                        <th>Год</th>
                        <th class="text-right">Население</th>
                        <th class="text-right"></th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="item in populations"
                        :key="item.id"
                    >
                        <td>{{ item.year }}</td>

                        <td class="text-right">
                            {{ formatNumber(item.population) }}
                        </td>

                        <td class="text-right">
                            <v-btn
                                icon="mdi-pencil"
                                size="x-small"
                                color="amber"
                                variant="text"
                                @click="editPopulation(item)"
                            />

                            <v-btn
                                icon="mdi-delete"
                                size="x-small"
                                color="red"
                                variant="text"
                                @click="deletePopulation(item)"
                            />
                        </td>
                    </tr>

                    <tr v-if="!populations.length">
                        <td
                            colspan="3"
                            class="text-center text-grey py-4"
                        >
                            Нет данных по населению
                        </td>
                    </tr>
                    </tbody>
                </v-table>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>
