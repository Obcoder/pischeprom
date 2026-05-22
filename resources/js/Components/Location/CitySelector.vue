<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { route } from 'ziggy-js'

const page = usePage()

const currentCity = computed(() => page.props.location?.city)

const dialog = ref(false)
const search = ref('')
const loading = ref(false)
const cities = ref([])
const selectedCity = ref(null)

let timeoutId = null

watch(search, (value) => {
    clearTimeout(timeoutId)

    if (!value || value.length < 2) {
        cities.value = []
        return
    }

    timeoutId = setTimeout(fetchCities, 250)
})

function fetchCities() {
    loading.value = true

    axios.get(route('location.cities'), {
        params: {
            q: search.value,
        },
    })
        .then((response) => {
            cities.value = response.data
        })
        .finally(() => {
            loading.value = false
        })
}

function saveCity() {
    if (!selectedCity.value?.id) {
        return
    }

    router.post(route('location.city.update'), {
        city_id: selectedCity.value.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            dialog.value = false
            selectedCity.value = null
            search.value = ''
        },
    })
}
</script>

<template>
    <div class="city-selector">
        <button
            type="button"
            class="city-selector__button"
            @click="dialog = true"
        >
            <span class="city-selector__icon">📍</span>
            <span class="city-selector__label">Доставка в</span>
            <span class="city-selector__city">
                {{ currentCity?.name || 'Санкт-Петербург' }}
            </span>
        </button>

        <v-dialog v-model="dialog" max-width="560">
            <v-card rounded="xl">
                <v-card-title class="text-h6 font-weight-bold">
                    Выберите город доставки
                </v-card-title>

                <v-card-text>
                    <p class="text-body-2 text-medium-emphasis mb-4">
                        Город нужен для расчёта доставки, логистики и будущих региональных условий.
                    </p>

                    <v-autocomplete
                        v-model="selectedCity"
                        v-model:search="search"
                        :items="cities"
                        :loading="loading"
                        item-title="label"
                        item-value="id"
                        return-object
                        hide-no-data
                        clearable
                        label="Город, село или деревня"
                        variant="outlined"
                        rounded="lg"
                    />
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />

                    <v-btn
                        variant="text"
                        rounded="xl"
                        @click="dialog = false"
                    >
                        Отмена
                    </v-btn>

                    <v-btn
                        color="#800000"
                        rounded="xl"
                        :disabled="!selectedCity"
                        @click="saveCity"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.city-selector__button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 0;
    background: rgba(255, 255, 255, 0.12);
    color: #fff7ed;
    border-radius: 999px;
    padding: 6px 11px;
    cursor: pointer;
    font-size: 0.82rem;
    line-height: 1.2;
}

.city-selector__button:hover {
    background: rgba(255, 255, 255, 0.18);
}

.city-selector__label {
    opacity: 0.85;
}

.city-selector__city {
    font-weight: 800;
}
</style>
