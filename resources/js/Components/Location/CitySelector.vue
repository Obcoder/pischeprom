<script setup>
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import { useAppRoute } from '@/Composables/useAppRoute'

const props = defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
})

const inertiaPage = usePage()

const {
    route,
} = useAppRoute()

const dialog = ref(false)
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const cities = ref([])
const errorMessage = ref('')

const currentCity = computed(() => {
    return inertiaPage.props.location?.city ?? null
})

const currentCityName = computed(() => {
    return currentCity.value?.name || 'Санкт-Петербург'
})

const currentCityRegion = computed(() => {
    return currentCity.value?.region || ''
})

const citiesUrl = computed(() => {
    const url = route('location.cities')

    return url && url !== '#'
        ? url
        : '/location/cities'
})

const updateCityUrl = computed(() => {
    const url = route('location.city.update')

    return url && url !== '#'
        ? url
        : '/location/city'
})

const normalizedCities = computed(() => {
    return cities.value
        .filter((city) => city?.id && city?.name)
        .map((city) => ({
            id: city.id,
            name: city.name,
            region: city.region || city.region_name || '',
            label: city.label || makeCityLabel(city),
        }))
})

const filteredCities = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) {
        return normalizedCities.value
    }

    return normalizedCities.value.filter((city) => {
        return [
            city.name,
            city.region,
            city.label,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(query))
    })
})

const selectedCityId = computed(() => {
    return currentCity.value?.id ?? null
})

function makeCityLabel(city) {
    if (!city?.region) {
        return city?.name || ''
    }

    return `${city.name}, ${city.region}`
}

function normalizeCitiesResponse(payload) {
    if (Array.isArray(payload)) {
        return payload
    }

    if (Array.isArray(payload?.data)) {
        return payload.data
    }

    if (Array.isArray(payload?.cities)) {
        return payload.cities
    }

    return []
}

async function loadCities() {
    if (loading.value || cities.value.length) {
        return
    }

    loading.value = true
    errorMessage.value = ''

    try {
        const response = await axios.get(citiesUrl.value)

        cities.value = normalizeCitiesResponse(response.data)
    } catch (error) {
        console.error('[CitySelector] Cities loading error:', error)

        errorMessage.value = 'Не удалось загрузить список городов.'
        cities.value = []
    } finally {
        loading.value = false
    }
}

async function openDialog() {
    dialog.value = true

    await loadCities()
}

function closeDialog() {
    if (saving.value) {
        return
    }

    dialog.value = false
    search.value = ''
    errorMessage.value = ''
}

function selectCity(city) {
    if (!city?.id || saving.value) {
        return
    }

    saving.value = true
    errorMessage.value = ''

    router.post(
        updateCityUrl.value,
        {
            city_id: city.id,
            city: city.id,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                dialog.value = false
                search.value = ''
            },
            onError: (errors) => {
                console.error('[CitySelector] City update error:', errors)

                errorMessage.value = 'Не удалось изменить город доставки.'
            },
            onFinish: () => {
                saving.value = false
            },
        },
    )
}
</script>

<template>
    <div class="city-selector">
        <button
            type="button"
            class="city-selector__button"
            :class="{
                'city-selector__button--compact': compact,
            }"
            @click="openDialog"
        >
            <v-icon
                icon="mdi-map-marker"
                size="16"
                class="city-selector__icon"
            />

            <span class="city-selector__text">
                Доставка в
                <strong>{{ currentCityName }}</strong>
            </span>
        </button>

        <v-dialog
            v-model="dialog"
            max-width="560"
            scrollable
        >
            <v-card rounded="xl">
                <v-card-title class="city-selector__dialog-title">
                    <div>
                        <div class="text-h6 font-weight-bold">
                            Выберите город доставки
                        </div>

                        <div class="text-body-2 text-medium-emphasis">
                            Текущий город:
                            <strong>{{ currentCityName }}</strong>
                            <template v-if="currentCityRegion">
                                , {{ currentCityRegion }}
                            </template>
                        </div>
                    </div>

                    <v-btn
                        icon="mdi-close"
                        variant="text"
                        aria-label="Закрыть"
                        @click="closeDialog"
                    />
                </v-card-title>

                <v-divider />

                <v-card-text>
                    <v-alert
                        v-if="errorMessage"
                        type="error"
                        variant="tonal"
                        density="comfortable"
                        class="mb-4"
                    >
                        {{ errorMessage }}
                    </v-alert>

                    <v-text-field
                        v-model="search"
                        label="Поиск города"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        clearable
                        hide-details
                        class="mb-4"
                    />

                    <div
                        v-if="loading"
                        class="city-selector__loading"
                    >
                        <v-progress-circular
                            indeterminate
                            size="28"
                            width="3"
                            color="#800000"
                        />

                        <span>
                            Загружаем города...
                        </span>
                    </div>

                    <v-list
                        v-else-if="filteredCities.length"
                        class="city-selector__list"
                        density="compact"
                    >
                        <v-list-item
                            v-for="city in filteredCities"
                            :key="city.id"
                            :active="city.id === selectedCityId"
                            rounded="lg"
                            @click="selectCity(city)"
                        >
                            <template #prepend>
                                <v-icon
                                    :icon="city.id === selectedCityId ? 'mdi-map-marker-check' : 'mdi-map-marker-outline'"
                                    color="#800000"
                                />
                            </template>

                            <v-list-item-title>
                                {{ city.name }}
                            </v-list-item-title>

                            <v-list-item-subtitle v-if="city.region">
                                {{ city.region }}
                            </v-list-item-subtitle>

                            <template #append>
                                <v-progress-circular
                                    v-if="saving && city.id === selectedCityId"
                                    indeterminate
                                    size="18"
                                    width="2"
                                />

                                <v-icon
                                    v-else-if="city.id === selectedCityId"
                                    icon="mdi-check"
                                    color="success"
                                />
                            </template>
                        </v-list-item>
                    </v-list>

                    <div
                        v-else
                        class="city-selector__empty"
                    >
                        Город не найден.
                    </div>
                </v-card-text>

                <v-divider />

                <v-card-actions class="justify-end">
                    <v-btn
                        variant="text"
                        rounded="xl"
                        :disabled="saving"
                        @click="closeDialog"
                    >
                        Закрыть
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.city-selector {
    display: inline-flex;
    align-items: center;
}

.city-selector__button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    border: 0;
    border-radius: 999px;
    padding: 8px 14px;
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
    cursor: pointer;
    font-size: 0.88rem;
    line-height: 1.2;
    transition:
        background 0.18s ease,
        transform 0.18s ease;
}

.city-selector__button:hover {
    background: rgba(255, 255, 255, 0.24);
}

.city-selector__button:active {
    transform: translateY(1px);
}

.city-selector__button--compact {
    padding: 7px 12px;
    font-size: 0.84rem;
}

.city-selector__icon {
    opacity: 0.9;
    flex: 0 0 auto;
}

.city-selector__text {
    display: inline-block;
    max-width: 240px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.city-selector__dialog-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.city-selector__loading,
.city-selector__empty {
    min-height: 120px;
    display: grid;
    place-items: center;
    gap: 12px;
    color: #6b625d;
    text-align: center;
}

.city-selector__loading {
    grid-auto-flow: column;
    justify-content: center;
}

.city-selector__list {
    max-height: 420px;
    overflow: auto;
}

@media (max-width: 600px) {
    .city-selector__text {
        max-width: 170px;
    }
}
</style>
