<script setup>
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

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
const failedThumbnails = ref(new Set())

let searchTimer = null
let abortController = null

const currentCity = computed(() => {
    return inertiaPage.props.location?.city ?? null
})

const currentCityName = computed(() => {
    return currentCity.value?.name || 'Санкт-Петербург'
})

const currentCityRegion = computed(() => {
    return currentCity.value?.region || currentCity.value?.region_name || ''
})

const currentCityThumbnail = computed(() => {
    return currentCity.value?.wiki_thumbnail || ''
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

const normalizedSearch = computed(() => {
    return String(search.value ?? '').trim()
})

const normalizedCities = computed(() => {
    return cities.value
        .filter((city) => city?.id && city?.name)
        .map((city) => ({
            id: city.id,
            name: city.name,
            region: city.region || city.region_name || city.subject || '',
            label: city.label || makeCityLabel(city),
            wiki_thumbnail: city.wiki_thumbnail || city.thumbnail || city.image || '',
        }))
})

const filteredCities = computed(() => {
    const query = normalizedSearch.value.toLowerCase()

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

const showInitialHint = computed(() => {
    return !loading.value
        && !errorMessage.value
        && !filteredCities.value.length
        && !normalizedSearch.value
})

const showEmptySearch = computed(() => {
    return !loading.value
        && !errorMessage.value
        && !filteredCities.value.length
        && Boolean(normalizedSearch.value)
})

function makeCityLabel(city) {
    const name = city?.name || ''

    const region = city?.region
        || city?.region_name
        || city?.subject
        || ''

    return region
        ? `${name}, ${region}`
        : name
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

    if (Array.isArray(payload?.items)) {
        return payload.items
    }

    return []
}

function cancelPendingSearch() {
    if (searchTimer) {
        clearTimeout(searchTimer)
        searchTimer = null
    }

    if (abortController) {
        abortController.abort()
        abortController = null
    }
}

async function loadCities(query = '') {
    cancelPendingSearch()

    loading.value = true
    errorMessage.value = ''

    abortController = new AbortController()

    try {
        const response = await axios.get(citiesUrl.value, {
            params: {
                search: query,
                q: query,
                term: query,
            },
            signal: abortController.signal,
        })

        cities.value = normalizeCitiesResponse(response.data)
    } catch (error) {
        if (error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED') {
            return
        }

        console.error('[CitySelector] Cities loading error:', error)

        errorMessage.value = 'Не удалось загрузить список городов.'
        cities.value = []
    } finally {
        loading.value = false
        abortController = null
    }
}

function scheduleCitiesSearch() {
    if (!dialog.value) {
        return
    }

    if (searchTimer) {
        clearTimeout(searchTimer)
    }

    searchTimer = setTimeout(() => {
        loadCities(normalizedSearch.value)
    }, 300)
}

async function openDialog() {
    dialog.value = true
    errorMessage.value = ''

    await loadCities(normalizedSearch.value)
}

function closeDialog() {
    if (saving.value) {
        return
    }

    dialog.value = false
    search.value = ''
    errorMessage.value = ''
    cancelPendingSearch()
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

function cityInitials(city) {
    const name = String(city?.name || '').trim()

    if (!name) {
        return 'Г'
    }

    const words = name
        .split(/\s+/)
        .filter(Boolean)

    if (words.length >= 2) {
        return `${words[0][0]}${words[1][0]}`.toUpperCase()
    }

    return name.slice(0, 2).toUpperCase()
}

function hasCityThumbnail(city) {
    return Boolean(city?.wiki_thumbnail)
        && !failedThumbnails.value.has(city.id)
}

function markThumbnailFailed(city) {
    if (!city?.id) {
        return
    }

    const next = new Set(failedThumbnails.value)

    next.add(city.id)

    failedThumbnails.value = next
}

watch(search, () => {
    scheduleCitiesSearch()
})

onBeforeUnmount(() => {
    cancelPendingSearch()
})
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
            <v-avatar
                v-if="currentCityThumbnail && !compact"
                size="22"
                class="city-selector__button-avatar"
            >
                <img
                    :src="currentCityThumbnail"
                    :alt="currentCityName"
                    loading="lazy"
                >
            </v-avatar>

            <v-icon
                v-else
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
            max-width="620"
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
                        placeholder="Начните вводить город: Москва, Казань, Набережные Челны..."
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        rounded="lg"
                        clearable
                        autofocus
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
                            Ищем города...
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
                            class="city-selector__city-item"
                            @click="selectCity(city)"
                        >
                            <template #prepend>
                                <v-avatar
                                    size="46"
                                    class="city-selector__avatar"
                                >
                                    <img
                                        v-if="hasCityThumbnail(city)"
                                        :src="city.wiki_thumbnail"
                                        :alt="city.name"
                                        loading="lazy"
                                        @error="markThumbnailFailed(city)"
                                    >

                                    <span
                                        v-else
                                        class="city-selector__avatar-fallback"
                                    >
                                        {{ cityInitials(city) }}
                                    </span>
                                </v-avatar>
                            </template>

                            <v-list-item-title class="city-selector__city-title">
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
                        v-else-if="showInitialHint"
                        class="city-selector__empty"
                    >
                        Начните вводить название города.
                    </div>

                    <div
                        v-else-if="showEmptySearch"
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

.city-selector__button-avatar {
    background: rgba(255, 255, 255, 0.18);
    flex: 0 0 auto;
}

.city-selector__button-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.city-selector__icon {
    opacity: 0.9;
    flex: 0 0 auto;
}

.city-selector__text {
    display: inline-block;
    max-width: 260px;
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
    max-height: 460px;
    overflow: auto;
}

.city-selector__city-item {
    min-height: 62px;
}

.city-selector__avatar {
    background: rgba(128, 0, 0, 0.08);
    color: #7f1d1d;
    font-weight: 800;
    overflow: hidden;
}

.city-selector__avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.city-selector__avatar-fallback {
    display: grid;
    place-items: center;
    width: 100%;
    height: 100%;
    background:
        linear-gradient(
            135deg,
            rgba(128, 0, 0, 0.12),
            rgba(128, 0, 0, 0.04)
        );
    color: #7f1d1d;
    font-size: 0.88rem;
    font-weight: 900;
}

.city-selector__city-title {
    font-weight: 700;
}

@media (max-width: 600px) {
    .city-selector__text {
        max-width: 180px;
    }

    .city-selector__avatar {
        width: 40px;
        height: 40px;
    }
}
</style>
