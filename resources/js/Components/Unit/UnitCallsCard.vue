<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
})

const calls = ref([])
const total = ref(0)
const loading = ref(false)
const page = ref(1)
const perPage = 12

const missedCount = computed(() => {
    return calls.value.filter((call) => call.direction === 'missed' || call.status === 'missed').length
})

function parseDate(value) {
    if (!value) {
        return null
    }

    const parsed = new Date(value)

    return Number.isNaN(parsed.getTime()) ? null : parsed
}

function formatDate(value) {
    const parsed = parseDate(value)

    if (!parsed) {
        return '-'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed)
}

function formatSeconds(value) {
    if (value === null || value === undefined) {
        return '-'
    }

    const totalSeconds = Number(value)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60

    return `${minutes}:${String(seconds).padStart(2, '0')}`
}

function formatPhone(value) {
    return value ? `+${value}` : 'Номер не найден'
}

function directionLabel(direction) {
    return {
        in: 'Вх',
        out: 'Исх',
        missed: 'Проп',
        unknown: '?',
    }[direction] || direction || '-'
}

function directionColor(direction) {
    return {
        in: 'teal',
        out: 'blue',
        missed: 'red',
        unknown: 'grey',
    }[direction] || 'grey'
}

function statusLabel(status) {
    return {
        success: 'Успешно',
        completed: 'Завершён',
        released: 'Завершён',
        ringing: 'Звонит',
        missed: 'Пропущен',
        cancelled: 'Отменён',
        busy: 'Занято',
        clicked: 'Клик',
    }[status] || status || '-'
}

async function fetchCalls(nextPage = page.value) {
    if (!props.unit?.id) {
        calls.value = []
        total.value = 0
        return
    }

    loading.value = true

    try {
        const { data } = await axios.get('/api/phone-calls', {
            params: {
                unit_id: props.unit.id,
                per_page: perPage,
                page: nextPage,
                hide_unresolved: true,
            },
        })

        calls.value = data.data || []
        total.value = data.total || 0
        page.value = data.current_page || nextPage
    } catch (error) {
        console.error('Не удалось загрузить звонки Unit:', error)
        calls.value = []
        total.value = 0
    } finally {
        loading.value = false
    }
}

function openRecording(call) {
    if (!call.recording_url) {
        return
    }

    window.open(call.recording_url, '_blank', 'noopener,noreferrer')
}

onMounted(() => fetchCalls())
</script>

<template>
    <BaseSectionCard
        title="Звонки"
        icon="mdi-phone-in-talk-outline"
        compact
    >
        <template #actions>
            <div class="unit-calls__summary">
                <span>{{ total }}</span>
                <small v-if="missedCount">missed {{ missedCount }}</small>
                <v-btn
                    icon="mdi-refresh"
                    size="x-small"
                    variant="text"
                    :loading="loading"
                    @click="fetchCalls()"
                />
            </div>
        </template>

        <div v-if="calls.length" class="unit-calls__list">
            <article
                v-for="call in calls"
                :key="call.id"
                class="unit-calls__item"
            >
                <div class="unit-calls__top">
                    <v-chip
                        size="x-small"
                        :color="directionColor(call.direction)"
                        variant="tonal"
                    >
                        {{ directionLabel(call.direction) }}
                    </v-chip>
                    <time>{{ formatDate(call.started_at || call.created_at) }}</time>
                </div>

                <div class="unit-calls__phone">
                    {{ formatPhone(call.client_phone) }}
                </div>

                <div class="unit-calls__meta">
                    <span>{{ statusLabel(call.status) }}</span>
                    <span>{{ formatSeconds(call.duration_seconds) }}</span>
                    <button
                        v-if="call.recording_url"
                        type="button"
                        @click="openRecording(call)"
                    >
                        запись
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="unit-calls__empty">
            {{ loading ? 'Загрузка звонков...' : 'Звонков по Unit нет' }}
        </div>
    </BaseSectionCard>
</template>

<style scoped>
.unit-calls__summary {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #5f0f24;
}

.unit-calls__summary span {
    font-weight: 900;
}

.unit-calls__summary small {
    color: #b4233f;
    font-size: 0.68rem;
    font-weight: 800;
}

.unit-calls__list {
    display: grid;
    gap: 6px;
    max-height: 410px;
    overflow: auto;
    padding-right: 2px;
}

.unit-calls__item {
    padding: 8px 9px;
    border: 1px solid rgba(128, 0, 32, 0.1);
    border-radius: 12px;
    background: linear-gradient(180deg, #fff, #fffafa);
}

.unit-calls__top,
.unit-calls__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.unit-calls__top time {
    color: #81737a;
    font-size: 0.68rem;
    white-space: nowrap;
}

.unit-calls__phone {
    margin-top: 6px;
    color: #271d21;
    font-size: 0.86rem;
    font-weight: 800;
}

.unit-calls__meta {
    margin-top: 4px;
    color: #7b6d73;
    font-size: 0.7rem;
}

.unit-calls__meta button {
    color: #2454a6;
    font-weight: 800;
    text-decoration: underline;
}

.unit-calls__empty {
    padding: 18px 10px;
    border: 1px dashed rgba(128, 0, 32, 0.18);
    border-radius: 12px;
    color: #887a80;
    font-size: 0.78rem;
    text-align: center;
}
</style>
