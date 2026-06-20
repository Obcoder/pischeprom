<script setup>
import { computed, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'
import axios from 'axios'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const fields = ref([])
const selectedFieldId = ref(null)
const board = ref(null)
const loading = ref(false)
const saving = ref(false)
const producerSearch = ref('')
const consumerSearch = ref('')
const draggingProducer = ref(null)
const activeConsumerId = ref(null)
const snackbar = ref({ show: false, text: '', color: 'success' })

const statusItems = [
    { title: 'Черновик', value: 'draft' },
    { title: 'Контакт', value: 'contacted' },
    { title: 'КП отправлено', value: 'offered' },
    { title: 'Сделка', value: 'won' },
    { title: 'Отказ', value: 'rejected' },
]

const selectedField = computed(() => board.value?.field || fields.value.find(field => field.id === selectedFieldId.value))
const producers = computed(() => board.value?.producers || [])
const consumers = computed(() => board.value?.consumers || [])
const matches = computed(() => board.value?.matches || [])

const filteredProducers = computed(() => filterUnits(producers.value, producerSearch.value))
const filteredConsumers = computed(() => filterUnits(consumers.value, consumerSearch.value))

const matchedPairsCount = computed(() => matches.value.length)
const unmatchedConsumersCount = computed(() => consumers.value.filter(consumer => !consumerMatches(consumer.id).length).length)

useHead({
    title: 'Field Board - Ameise',
    meta: [
        {
            name: 'description',
            content: 'Сопоставление производителей и потребителей внутри поля Ameise',
        },
    ],
})

function notify(text, color = 'success') {
    snackbar.value = { show: true, text, color }
}

function normalizeField(field) {
    return {
        ...field,
        title: field.title || field.name || `Field #${field.id}`,
        name: field.name || field.title || `Field #${field.id}`,
    }
}

function unitTitle(unit) {
    return unit?.name || `Unit #${unit?.id || '-'}`
}

function shortProducts(products, limit = 4) {
    return (products || []).slice(0, limit)
}

function filterUnits(items, search) {
    const query = String(search || '').trim().toLowerCase()

    if (!query) {
        return items
    }

    return items.filter(unit => {
        const haystack = [
            unit.id,
            unit.name,
            unit.site,
            ...(unit.cities || []).map(city => city.name),
            ...(unit.products || []).map(product => product.title),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return haystack.includes(query)
    })
}

function consumerMatches(consumerId) {
    return matches.value.filter(match => Number(match.consumer_unit_id) === Number(consumerId))
}

function producerMatches(producerId) {
    return matches.value.filter(match => Number(match.producer_unit_id) === Number(producerId))
}

function isProducerMatchedToConsumer(producerId, consumerId) {
    return matches.value.some(match =>
        Number(match.producer_unit_id) === Number(producerId)
        && Number(match.consumer_unit_id) === Number(consumerId)
    )
}

function statusLabel(status) {
    return statusItems.find(item => item.value === status)?.title || status || '-'
}

function statusColor(status) {
    return {
        draft: 'grey',
        contacted: 'blue',
        offered: 'orange',
        won: 'success',
        rejected: 'error',
    }[status] || 'grey'
}

function formatUnitProducts(unit) {
    const products = shortProducts(unit.products, 3)
    const extra = Math.max((unit.products_count || 0) - products.length, 0)

    return {
        products,
        extra,
    }
}

function normalizeUrl(url) {
    if (!url) return null
    return /^https?:\/\//i.test(url) ? url : `https://${url}`
}

async function fetchFields() {
    const { data } = await axios.get('/api/fields')
    fields.value = (Array.isArray(data) ? data : []).map(normalizeField)

    if (!selectedFieldId.value && fields.value.length) {
        selectedFieldId.value = fields.value[0].id
    }
}

async function fetchBoard() {
    if (!selectedFieldId.value) {
        board.value = null
        return
    }

    loading.value = true

    try {
        const { data } = await axios.get(`/api/fields/${selectedFieldId.value}/board`)
        board.value = data
    } catch (error) {
        console.error(error)
        notify('Не удалось загрузить field board.', 'error')
    } finally {
        loading.value = false
    }
}

async function selectField(fieldId) {
    selectedFieldId.value = fieldId
    await fetchBoard()
}

function onProducerDragStart(producer, event) {
    draggingProducer.value = producer
    event.dataTransfer.effectAllowed = 'copy'
    event.dataTransfer.setData('text/plain', String(producer.id))
}

function onProducerDragEnd() {
    draggingProducer.value = null
    activeConsumerId.value = null
}

function onConsumerDragEnter(consumer) {
    activeConsumerId.value = consumer.id
}

function onConsumerDragLeave(consumer) {
    if (activeConsumerId.value === consumer.id) {
        activeConsumerId.value = null
    }
}

async function onConsumerDrop(consumer, event) {
    event.preventDefault()
    const producerId = draggingProducer.value?.id || Number(event.dataTransfer.getData('text/plain'))
    activeConsumerId.value = null

    if (!producerId) {
        return
    }

    if (isProducerMatchedToConsumer(producerId, consumer.id)) {
        notify('Эта связь уже создана.', 'warning')
        return
    }

    await createMatch(producerId, consumer.id)
}

async function createMatch(producerId, consumerId) {
    if (!selectedFieldId.value) {
        return
    }

    saving.value = true

    try {
        const { data } = await axios.post(`/api/fields/${selectedFieldId.value}/matches`, {
            producer_unit_id: producerId,
            consumer_unit_id: consumerId,
        })

        const match = data.data || data
        board.value.matches = [match, ...(board.value.matches || [])]
        notify('Связь producer → consumer создана.')
    } catch (error) {
        console.error(error)
        notify(error.response?.data?.message || 'Не удалось создать связь.', 'error')
    } finally {
        saving.value = false
        draggingProducer.value = null
    }
}

async function updateMatchStatus(match, status) {
    if (!selectedFieldId.value || !match?.id) {
        return
    }

    try {
        const { data } = await axios.patch(`/api/fields/${selectedFieldId.value}/matches/${match.id}`, { status })
        const updated = data.data || data
        const index = board.value.matches.findIndex(item => item.id === updated.id)

        if (index !== -1) {
            board.value.matches.splice(index, 1, updated)
        }
    } catch (error) {
        console.error(error)
        notify('Не удалось обновить статус связи.', 'error')
    }
}

async function deleteMatch(match) {
    if (!selectedFieldId.value || !match?.id) {
        return
    }

    try {
        await axios.delete(`/api/fields/${selectedFieldId.value}/matches/${match.id}`)
        board.value.matches = board.value.matches.filter(item => item.id !== match.id)
        notify('Связь удалена.')
    } catch (error) {
        console.error(error)
        notify('Не удалось удалить связь.', 'error')
    }
}

onMounted(async () => {
    try {
        await fetchFields()
        await fetchBoard()
    } catch (error) {
        console.error(error)
        notify('Не удалось загрузить fields.', 'error')
    }
})
</script>

<template>
    <v-container fluid class="field-board pa-2 pa-md-3">
        <div class="field-board__shell">
            <header class="field-board__header">
                <div class="field-board__title">
                    <div class="field-board__eyebrow">Ameise Field Matching</div>
                    <h1>{{ selectedField?.title || 'Field board' }}</h1>
                    <p>Сопоставление производителей и покупателей внутри одного товарного поля.</p>
                </div>

                <div class="field-board__controls">
                    <v-select
                        v-model="selectedFieldId"
                        :items="fields"
                        item-title="title"
                        item-value="id"
                        label="Field"
                        variant="outlined"
                        density="compact"
                        hide-details
                        class="field-board__select"
                        @update:model-value="selectField"
                    />

                    <v-btn
                        color="primary"
                        variant="tonal"
                        prepend-icon="mdi-refresh"
                        :loading="loading"
                        @click="fetchBoard"
                    >
                        Refresh
                    </v-btn>
                </div>
            </header>

            <section class="field-board__stats">
                <div class="field-stat">
                    <span>Producers</span>
                    <strong>{{ producers.length }}</strong>
                </div>
                <div class="field-stat">
                    <span>Consumers</span>
                    <strong>{{ consumers.length }}</strong>
                </div>
                <div class="field-stat field-stat--accent">
                    <span>Matches</span>
                    <strong>{{ matchedPairsCount }}</strong>
                </div>
                <div class="field-stat">
                    <span>Unmatched consumers</span>
                    <strong>{{ unmatchedConsumersCount }}</strong>
                </div>
            </section>

            <v-progress-linear v-if="loading" indeterminate color="primary" height="2" />

            <v-alert
                v-if="!loading && selectedFieldId && !producers.length && !consumers.length"
                type="info"
                variant="tonal"
                density="compact"
                class="my-2"
            >
                В этом поле пока нет Units с производством или потреблением. Добавьте Units в Field и заполните Manufactures/Consumptions.
            </v-alert>

            <main class="field-board__grid">
                <section class="field-panel field-panel--producers">
                    <div class="field-panel__head">
                        <div>
                            <div class="field-panel__label">01 / creators</div>
                            <h2>Производители</h2>
                        </div>
                        <v-text-field
                            v-model="producerSearch"
                            prepend-inner-icon="mdi-magnify"
                            label="Поиск"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                            class="field-panel__search"
                        />
                    </div>

                    <div class="field-list">
                        <article
                            v-for="producer in filteredProducers"
                            :key="producer.id"
                            class="field-unit field-unit--producer"
                            draggable="true"
                            @dragstart="onProducerDragStart(producer, $event)"
                            @dragend="onProducerDragEnd"
                        >
                            <div class="field-unit__top">
                                <Link :href="route('web.unit.show', producer.id)" class="field-unit__name">
                                    {{ unitTitle(producer) }}
                                </Link>
                                <v-chip size="x-small" color="primary" variant="tonal">
                                    {{ producer.products_count }} products
                                </v-chip>
                            </div>

                            <div class="field-unit__meta">
                                <span>#{{ producer.id }}</span>
                                <span>{{ producerMatches(producer.id).length }} links</span>
                                <a
                                    v-if="producer.site"
                                    :href="normalizeUrl(producer.site)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    @click.stop
                                >site</a>
                            </div>

                            <div class="field-tags">
                                <v-chip
                                    v-for="product in formatUnitProducts(producer).products"
                                    :key="product.id"
                                    size="x-small"
                                    variant="outlined"
                                >
                                    {{ product.title }}
                                </v-chip>
                                <v-chip v-if="formatUnitProducts(producer).extra" size="x-small" variant="tonal">
                                    +{{ formatUnitProducts(producer).extra }}
                                </v-chip>
                            </div>
                        </article>

                        <div v-if="!filteredProducers.length" class="field-empty">
                            Производители не найдены.
                        </div>
                    </div>
                </section>

                <section class="field-panel field-panel--consumers">
                    <div class="field-panel__head">
                        <div>
                            <div class="field-panel__label">02 / buyers</div>
                            <h2>Потребители</h2>
                        </div>
                        <v-text-field
                            v-model="consumerSearch"
                            prepend-inner-icon="mdi-magnify"
                            label="Поиск"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                            class="field-panel__search"
                        />
                    </div>

                    <div class="field-list field-list--consumers">
                        <article
                            v-for="consumer in filteredConsumers"
                            :key="consumer.id"
                            class="field-consumer"
                            :class="{ 'field-consumer--active': activeConsumerId === consumer.id }"
                            @dragenter.prevent="onConsumerDragEnter(consumer)"
                            @dragover.prevent
                            @dragleave="onConsumerDragLeave(consumer)"
                            @drop="onConsumerDrop(consumer, $event)"
                        >
                            <div class="field-consumer__main">
                                <div class="field-unit__top">
                                    <Link :href="route('web.unit.show', consumer.id)" class="field-unit__name">
                                        {{ unitTitle(consumer) }}
                                    </Link>
                                    <v-chip size="x-small" color="success" variant="tonal">
                                        {{ consumer.products_count }} needs
                                    </v-chip>
                                </div>

                                <div class="field-unit__meta">
                                    <span>#{{ consumer.id }}</span>
                                    <span>{{ consumerMatches(consumer.id).length }} producers</span>
                                    <a
                                        v-if="consumer.site"
                                        :href="normalizeUrl(consumer.site)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        @click.stop
                                    >site</a>
                                </div>

                                <div class="field-tags">
                                    <v-chip
                                        v-for="product in formatUnitProducts(consumer).products"
                                        :key="product.id"
                                        size="x-small"
                                        variant="outlined"
                                    >
                                        {{ product.title }}
                                    </v-chip>
                                    <v-chip v-if="formatUnitProducts(consumer).extra" size="x-small" variant="tonal">
                                        +{{ formatUnitProducts(consumer).extra }}
                                    </v-chip>
                                </div>
                            </div>

                            <div class="field-consumer__drop">
                                <div v-if="!consumerMatches(consumer.id).length" class="field-drop-hint">
                                    Перетащите производителя сюда
                                </div>

                                <div v-else class="field-match-list">
                                    <div
                                        v-for="match in consumerMatches(consumer.id)"
                                        :key="match.id"
                                        class="field-match"
                                    >
                                        <div class="field-match__body">
                                            <strong>{{ match.producer?.name || `Unit #${match.producer_unit_id}` }}</strong>
                                            <small v-if="match.shared_products?.length">
                                                {{ match.shared_products.map(product => product.title).join(', ') }}
                                            </small>
                                            <small v-else>нет пересечения по продуктам</small>
                                        </div>

                                        <v-select
                                            :model-value="match.status"
                                            :items="statusItems"
                                            item-title="title"
                                            item-value="value"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                            class="field-match__status"
                                            :color="statusColor(match.status)"
                                            @update:model-value="value => updateMatchStatus(match, value)"
                                        />

                                        <v-btn
                                            icon="mdi-close"
                                            size="x-small"
                                            variant="text"
                                            density="compact"
                                            @click="deleteMatch(match)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </article>

                        <div v-if="!filteredConsumers.length" class="field-empty">
                            Потребители не найдены.
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="2600">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<style scoped>
.field-board {
    height: calc(100vh - 48px);
    overflow: hidden;
    background:
        radial-gradient(circle at 18% 0, rgba(30, 64, 175, 0.10), transparent 28%),
        linear-gradient(135deg, #f7fafc 0%, #eef2f7 100%);
}

.field-board__shell {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    border: 1px solid rgba(15, 23, 42, 0.12);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.72);
    overflow: hidden;
}

.field-board__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.10);
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.92));
    color: #f8fafc;
}

.field-board__eyebrow,
.field-panel__label {
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.field-board__eyebrow {
    color: #93c5fd;
}

.field-board__title h1 {
    margin: 2px 0 0;
    font-size: 24px;
    font-weight: 950;
    letter-spacing: -0.05em;
    line-height: 1;
}

.field-board__title p {
    margin: 4px 0 0;
    color: rgba(248, 250, 252, 0.68);
    font-size: 12px;
}

.field-board__controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.field-board__select {
    width: 280px;
}

.field-board__select :deep(.v-field) {
    min-height: 42px;
    border-radius: 10px;
    background: rgba(15, 23, 42, 0.62);
    color: #f8fafc;
    box-shadow: inset 0 0 0 1px rgba(147, 197, 253, 0.22);
}

.field-board__select :deep(.v-field:hover) {
    background: rgba(15, 23, 42, 0.76);
    box-shadow: inset 0 0 0 1px rgba(147, 197, 253, 0.42);
}

.field-board__select :deep(.v-field--focused) {
    background: rgba(15, 23, 42, 0.88);
    box-shadow: inset 0 0 0 1px rgba(96, 165, 250, 0.72);
}

.field-board__select :deep(.v-field__outline) {
    --v-field-border-opacity: 0;
}

.field-board__select :deep(.v-label) {
    color: #bfdbfe;
    font-size: 12px;
    font-weight: 800;
    opacity: 1;
}

.field-board__select :deep(.v-field__input) {
    min-height: 42px;
    padding-top: 8px;
    padding-bottom: 6px;
    color: #ffffff;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 0.01em;
}

.field-board__select :deep(.v-select__selection-text) {
    color: #ffffff;
}

.field-board__select :deep(.v-field__append-inner),
.field-board__select :deep(.v-icon) {
    color: #60a5fa;
    opacity: 1;
}

.field-board__stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1px;
    background: rgba(15, 23, 42, 0.10);
    border-bottom: 1px solid rgba(15, 23, 42, 0.10);
}

.field-stat {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 7px 12px;
    background: rgba(255, 255, 255, 0.74);
}

.field-stat span {
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.field-stat strong {
    color: #0f172a;
    font-size: 18px;
    line-height: 1;
}

.field-stat--accent strong {
    color: #1d4ed8;
}

.field-board__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 1px;
    flex: 1 1 auto;
    min-height: 0;
    background: rgba(15, 23, 42, 0.10);
}

.field-panel {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    background: rgba(248, 250, 252, 0.92);
}

.field-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.field-panel__label {
    color: #2563eb;
}

.field-panel h2 {
    margin: 1px 0 0;
    color: #0f172a;
    font-size: 18px;
    font-weight: 900;
    letter-spacing: -0.04em;
    line-height: 1;
}

.field-panel__search {
    max-width: 240px;
}

.field-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 10px;
}

.field-unit,
.field-consumer {
    border: 1px solid rgba(15, 23, 42, 0.10);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.86);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
}

.field-unit {
    padding: 9px;
    cursor: grab;
}

.field-unit:active {
    cursor: grabbing;
}

.field-unit--producer:hover {
    border-color: rgba(37, 99, 235, 0.34);
    background: #ffffff;
}

.field-consumer {
    display: grid;
    grid-template-columns: minmax(230px, 0.84fr) minmax(0, 1.16fr);
    gap: 8px;
    padding: 8px;
    transition: border-color 0.16s ease, background 0.16s ease;
}

.field-consumer--active {
    border-color: rgba(37, 99, 235, 0.75);
    background: rgba(219, 234, 254, 0.84);
}

.field-consumer__main {
    min-width: 0;
}

.field-consumer__drop {
    min-height: 92px;
    min-width: 0;
    border: 1px dashed rgba(15, 23, 42, 0.16);
    border-radius: 12px;
    padding: 6px;
    background: rgba(241, 245, 249, 0.78);
}

.field-unit__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    min-width: 0;
}

.field-unit__name {
    min-width: 0;
    color: #0f172a;
    font-size: 14px;
    font-weight: 900;
    line-height: 1.16;
    text-decoration: none;
}

.field-unit__name:hover {
    color: #1d4ed8;
}

.field-unit__meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 5px;
    color: #64748b;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    font-weight: 700;
}

.field-unit__meta a {
    color: #1d4ed8;
    text-decoration: none;
}

.field-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 7px;
}

.field-drop-hint,
.field-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 76px;
    border-radius: 10px;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
}

.field-match-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field-match {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 138px 28px;
    align-items: center;
    gap: 6px;
    padding: 6px;
    border: 1px solid rgba(37, 99, 235, 0.12);
    border-radius: 10px;
    background: #ffffff;
}

.field-match__body {
    min-width: 0;
}

.field-match__body strong,
.field-match__body small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.field-match__body strong {
    color: #0f172a;
    font-size: 12px;
    line-height: 1.1;
}

.field-match__body small {
    margin-top: 2px;
    color: #64748b;
    font-size: 10px;
}

.field-match__status {
    min-width: 0;
}

@media (max-width: 1180px) {
    .field-board {
        height: auto;
        min-height: calc(100vh - 48px);
        overflow: visible;
    }

    .field-board__grid {
        grid-template-columns: 1fr;
    }

    .field-list {
        max-height: 560px;
    }
}

@media (max-width: 760px) {
    .field-board__header,
    .field-board__controls,
    .field-panel__head {
        align-items: stretch;
        flex-direction: column;
    }

    .field-board__select,
    .field-panel__search {
        width: 100%;
        max-width: none;
    }

    .field-board__stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .field-consumer,
    .field-match {
        grid-template-columns: 1fr;
    }
}
</style>
