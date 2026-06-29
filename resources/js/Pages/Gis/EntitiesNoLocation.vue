<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted, reactive, ref } from 'vue'

import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { useGisApi } from '@/Composables/gis/useGisApi'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    providers: {
        type: Array,
        default: () => [],
    },
    defaultProvider: {
        type: String,
        default: '2gis',
    },
})

const api = useGisApi()
const entities = ref([])
const geocodeResults = reactive({})
const loading = ref(false)
const geocodingId = ref(null)
const savingId = ref(null)
const errorMessage = ref('')
const successMessage = ref('')
const manualDialog = ref(false)
const selectedProvider = ref(props.defaultProvider)
const filters = reactive({
    search: '',
    type: '',
    status: '',
    city: '',
    manager_id: '',
})

const manualForm = reactive({
    entity_id: null,
    name: '',
    address_text: '',
    lat: '',
    lon: '',
    precision_level: 'exact',
})

function responseMessage(error, fallback) {
    return error.response?.data?.error?.message
        || error.response?.data?.message
        || fallback
}

async function loadEntities() {
    loading.value = true
    errorMessage.value = ''

    try {
        const { items } = await api.getEntitiesWithoutLocation({ ...filters })
        entities.value = items || []
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось загрузить entities без координат.')
    } finally {
        loading.value = false
    }
}

async function geocode(entity) {
    geocodingId.value = entity.entity_id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        const response = await api.geocodeEntity(entity.entity_id, {
            provider: selectedProvider.value,
            address: entity.address,
        })

        geocodeResults[entity.entity_id] = response.items || []

        if (!geocodeResults[entity.entity_id].length) {
            errorMessage.value = 'Провайдер не вернул варианты геокодирования.'
        }
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось выполнить геокодирование.')
    } finally {
        geocodingId.value = null
    }
}

async function saveVariant(entity, variant) {
    savingId.value = entity.entity_id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await api.saveLocation(entity.entity_id, {
            address_text: variant.address || entity.address,
            lat: variant.lat,
            lon: variant.lon,
            precision_level: variant.precision_level || 'unknown',
        })

        successMessage.value = `Координаты для ${entity.name} сохранены.`
        delete geocodeResults[entity.entity_id]
        await loadEntities()
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось сохранить координаты.')
    } finally {
        savingId.value = null
    }
}

function openManualDialog(entity) {
    manualForm.entity_id = entity.entity_id
    manualForm.name = entity.name
    manualForm.address_text = entity.address || ''
    manualForm.lat = ''
    manualForm.lon = ''
    manualForm.precision_level = 'exact'
    manualDialog.value = true
}

async function saveManual() {
    savingId.value = manualForm.entity_id
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await api.saveLocation(manualForm.entity_id, {
            address_text: manualForm.address_text,
            lat: manualForm.lat,
            lon: manualForm.lon,
            precision_level: manualForm.precision_level,
        })

        successMessage.value = `Координаты для ${manualForm.name} сохранены вручную.`
        manualDialog.value = false
        await loadEntities()
    } catch (error) {
        console.error(error)
        errorMessage.value = responseMessage(error, 'Не удалось сохранить координаты.')
    } finally {
        savingId.value = null
    }
}

onMounted(loadEntities)
</script>

<template>
    <Head title="GIS CRM: entities без координат" />

    <div class="gis-missing-page">
        <section class="gis-missing-hero">
            <div>
                <p>GIS CRM MVP</p>
                <h1>Entities без координат</h1>
                <span>Геокодирование не перезаписывает координаты автоматически: вариант нужно подтвердить вручную.</span>
            </div>
            <div class="gis-missing-hero__actions">
                <v-btn href="/Ameise/gis" variant="outlined" color="red-darken-4">Управление</v-btn>
                <v-btn href="/Ameise/gis/2gis" variant="outlined" color="red-darken-4">2ГИС</v-btn>
                <v-btn href="/Ameise/gis/yandex" variant="outlined" color="red-darken-4">Яндекс</v-btn>
            </div>
        </section>

        <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">
            {{ errorMessage }}
        </v-alert>
        <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4">
            {{ successMessage }}
        </v-alert>

        <section class="gis-missing-filters">
            <v-select
                v-model="selectedProvider"
                :items="providers"
                item-title="title"
                item-value="value"
                label="Провайдер"
                density="compact"
                variant="outlined"
                hide-details
            />
            <v-text-field v-model="filters.search" label="Поиск" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.type" label="Тип/classification" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.status" label="Статус" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.city" label="Город" density="compact" variant="outlined" hide-details />
            <v-text-field v-model="filters.manager_id" label="Manager ID" density="compact" variant="outlined" hide-details />
            <v-btn color="red-darken-4" :loading="loading" @click="loadEntities">Обновить</v-btn>
        </section>

        <section class="gis-missing-list">
            <article v-for="entity in entities" :key="entity.entity_id" class="gis-missing-card">
                <div class="gis-missing-card__main">
                    <strong>{{ entity.name }}</strong>
                    <span>{{ entity.type || 'Тип не задан' }}</span>
                    <small>{{ entity.address || 'Адрес не задан' }}</small>
                </div>

                <div class="gis-missing-card__actions">
                    <v-btn
                        color="red-darken-4"
                        variant="tonal"
                        :loading="geocodingId === entity.entity_id"
                        :disabled="!entity.address"
                        @click="geocode(entity)"
                    >
                        Геокодировать
                    </v-btn>
                    <v-btn color="red-darken-4" variant="outlined" @click="openManualDialog(entity)">
                        Вручную
                    </v-btn>
                </div>

                <div v-if="geocodeResults[entity.entity_id]?.length" class="gis-geocode-results">
                    <div v-for="variant in geocodeResults[entity.entity_id]" :key="`${variant.provider_object_id}-${variant.lat}-${variant.lon}`" class="gis-geocode-result">
                        <div>
                            <strong>{{ variant.title || variant.address }}</strong>
                            <span>{{ variant.address }}</span>
                            <small>{{ variant.lat }}, {{ variant.lon }} · {{ variant.precision_level }}</small>
                        </div>
                        <v-btn
                            size="small"
                            color="red-darken-4"
                            :loading="savingId === entity.entity_id"
                            @click="saveVariant(entity, variant)"
                        >
                            Подтвердить
                        </v-btn>
                    </div>
                </div>
            </article>

            <div v-if="!loading && !entities.length" class="gis-missing-empty">
                Все найденные entities уже имеют координаты.
            </div>
        </section>

        <v-dialog v-model="manualDialog" max-width="560">
            <v-card>
                <v-card-title>Ручные координаты</v-card-title>
                <v-card-subtitle>{{ manualForm.name }}</v-card-subtitle>
                <v-card-text class="gis-manual-form">
                    <v-text-field v-model="manualForm.address_text" label="Адрес" variant="outlined" />
                    <v-text-field v-model="manualForm.lat" label="Lat" variant="outlined" />
                    <v-text-field v-model="manualForm.lon" label="Lon" variant="outlined" />
                    <v-select
                        v-model="manualForm.precision_level"
                        :items="['exact', 'building', 'street', 'city', 'unknown']"
                        label="Точность"
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="manualDialog = false">Отмена</v-btn>
                    <v-btn color="red-darken-4" :loading="savingId === manualForm.entity_id" @click="saveManual">
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<style scoped>
.gis-missing-page {
    min-height: 100vh;
    padding: 24px;
    background: radial-gradient(circle at top left, #ffedd5 0, transparent 36%), linear-gradient(135deg, #f8fafc, #fef2f2);
}

.gis-missing-hero {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: flex-end;
    margin-bottom: 18px;
}

.gis-missing-hero p {
    margin: 0 0 8px;
    color: #991b1b;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.gis-missing-hero h1 {
    margin: 0;
    color: #450a0a;
    font-size: clamp(32px, 5vw, 58px);
    font-weight: 900;
    line-height: 1;
}

.gis-missing-hero span {
    color: #7f1d1d;
}

.gis-missing-hero__actions,
.gis-missing-filters,
.gis-missing-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.gis-missing-filters {
    align-items: center;
    padding: 14px;
    margin-bottom: 16px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(127, 29, 29, 0.14);
}

.gis-missing-filters > * {
    min-width: 150px;
    flex: 1 1 150px;
}

.gis-missing-list {
    display: grid;
    gap: 12px;
}

.gis-missing-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 16px;
    padding: 16px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(127, 29, 29, 0.13);
    box-shadow: 0 16px 42px rgba(69, 10, 10, 0.07);
}

.gis-missing-card__main {
    display: grid;
    gap: 4px;
    color: #450a0a;
}

.gis-missing-card__main span,
.gis-missing-card__main small {
    color: #7f1d1d;
}

.gis-geocode-results {
    grid-column: 1 / -1;
    display: grid;
    gap: 8px;
}

.gis-geocode-result {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border-radius: 18px;
    background: #fff7ed;
    color: #450a0a;
}

.gis-geocode-result div {
    display: grid;
    gap: 3px;
}

.gis-geocode-result span,
.gis-geocode-result small {
    color: #7f1d1d;
}

.gis-missing-empty {
    padding: 32px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.85);
    color: #450a0a;
    text-align: center;
}

.gis-manual-form {
    display: grid;
    gap: 4px;
}

@media (max-width: 900px) {
    .gis-missing-hero,
    .gis-missing-card,
    .gis-geocode-result {
        grid-template-columns: 1fr;
        align-items: flex-start;
    }

    .gis-missing-card__actions {
        justify-content: flex-start;
    }
}
</style>
