<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter.js'

const props = defineProps({ unit: { type: Object, required: true } })
const { formatPhone } = usePhoneFormatter()
const calls = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref('')
const page = ref(1)
const lastPage = ref(1)
const perPage = 12
let request = null
let sequence = 0

function formatDate(value) {
    if (!value || Number.isNaN(new Date(value).getTime())) return '—'
    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit',
    }).format(new Date(value))
}

function formatSeconds(value) {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) return '—'
    const seconds = Math.max(0, Math.floor(Number(value)))
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

function directionLabel(direction) {
    return { in: '↓ Вх', out: '↑ Исх', missed: 'Проп', unknown: '—' }[direction] || direction || '—'
}

function statusLabel(status) {
    return {
        success: 'Успешно', completed: 'Завершён', released: 'Завершён', ringing: 'Звонит',
        missed: 'Пропущен', cancelled: 'Отменён', busy: 'Занято', clicked: 'Клик',
    }[status] || status || '—'
}

async function fetchCalls(nextPage = page.value) {
    if (!props.unit?.id) return
    request?.abort()
    const controller = new AbortController()
    request = controller
    const currentSequence = ++sequence
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get('/api/phone-calls', {
            signal: controller.signal,
            params: { unit_id: props.unit.id, per_page: perPage, page: nextPage, hide_unresolved: true },
        })
        if (currentSequence !== sequence) return
        calls.value = data.data || []
        total.value = data.total || 0
        page.value = data.current_page || nextPage
        lastPage.value = data.last_page || 1
    } catch (failure) {
        if (!controller.signal.aborted && currentSequence === sequence) {
            error.value = failure.response?.data?.message || 'Не удалось загрузить звонки.'
        }
    } finally {
        if (currentSequence === sequence) loading.value = false
    }
}

function recordingHref(call) {
    try {
        const url = new URL(call.recording_url)
        return ['http:', 'https:'].includes(url.protocol) ? url.href : null
    } catch {
        return null
    }
}

watch(() => props.unit.id, () => { calls.value = []; page.value = 1; fetchCalls(1) }, { immediate: true })
onBeforeUnmount(() => { request?.abort(); sequence++ })
defineExpose({ refresh: () => fetchCalls(1) })
</script>

<template>
    <BaseSectionCard title="Звонки" icon="mdi-phone-in-talk-outline" header-color="default" compact class="unit-calls-card">
        <template #actions>
            <span class="unit-calls-total">{{ total }}</span>
            <v-btn icon="mdi-refresh" size="x-small" variant="text" :loading="loading" aria-label="Обновить звонки" title="Обновить звонки" @click="fetchCalls()" />
        </template>
        <div v-if="error" class="unit-calls-error" role="alert">{{ error }}</div>
        <div v-if="calls.length" class="unit-calls-list" :aria-busy="loading">
            <article v-for="call in calls" :key="call.id" class="unit-call">
                <div class="unit-call-top">
                    <span class="unit-call-direction" :class="{ 'is-missed': call.direction === 'missed' || call.status === 'missed' }">{{ directionLabel(call.direction) }}</span>
                    <time>{{ formatDate(call.started_at || call.created_at) }}</time>
                </div>
                <div class="unit-call-contact">
                    <a v-if="call.client_phone" :href="`tel:+${String(call.client_phone).replace(/\D/g, '')}`">{{ formatPhone(call.client_phone) }}</a>
                    <span v-else>Номер не определён</span>
                    <span v-if="call.entity?.name" class="unit-call-owner" :title="call.entity.name">{{ call.entity.name }}</span>
                </div>
                <div class="unit-call-meta">
                    <span>{{ statusLabel(call.status) }}</span>
                    <span>{{ formatSeconds(call.duration_seconds) }}</span>
                    <a v-if="recordingHref(call)" :href="recordingHref(call)" target="_blank" rel="noopener noreferrer">Запись</a>
                </div>
            </article>
        </div>
        <div v-else-if="!error" class="unit-calls-empty">{{ loading ? 'Загрузка звонков…' : 'Звонков пока нет' }}</div>
        <nav v-if="lastPage > 1" class="unit-calls-pagination" aria-label="Страницы звонков">
            <v-btn icon="mdi-chevron-left" size="x-small" variant="text" :disabled="loading || page <= 1" aria-label="Предыдущие звонки" @click="fetchCalls(page - 1)" />
            <span>{{ page }} / {{ lastPage }}</span>
            <v-btn icon="mdi-chevron-right" size="x-small" variant="text" :disabled="loading || page >= lastPage" aria-label="Следующие звонки" @click="fetchCalls(page + 1)" />
        </nav>
    </BaseSectionCard>
</template>

<style scoped>
.unit-calls-card { border: 1px solid #d6d3d9 !important; border-radius: 0 !important; box-shadow: none !important; color: #252329; }
.unit-calls-card :deep(.base-section-card__header) { padding: 5px 10px; min-height: 42px; border-bottom: 1px solid #e4e2e6; }
.unit-calls-card :deep(.base-section-card__title) { font-size: 12px; }
.unit-calls-card :deep(.base-section-card__body) { padding: 0 10px; }
.unit-calls-total { color: #79737e; font-size: 11px; font-variant-numeric: tabular-nums; }
.unit-calls-list { max-height: 388px; overflow-y: auto; }
.unit-call { padding: 9px 0; }
.unit-call + .unit-call { border-top: 1px solid #e4e2e6; }
.unit-call-top, .unit-call-meta { display: flex; align-items: center; gap: 8px; }
.unit-call-top { justify-content: space-between; }
.unit-call-direction { color: #382447; font-size: 10px; }
.unit-call-direction.is-missed { color: #6b2032; font-weight: 700; }
.unit-call-top time { color: #79737e; font-size: 10px; font-variant-numeric: tabular-nums; white-space: nowrap; }
.unit-call-contact { display: flex; flex-direction: column; gap: 2px; margin-top: 4px; font-size: 12px; font-weight: 600; }
.unit-call-contact a { color: #252329; text-decoration: none; }
.unit-call-owner { color: #79737e; font-size: 10px; font-weight: 400; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.unit-call-meta { margin-top: 4px; color: #79737e; font-size: 10px; }
.unit-call-meta a { margin-left: auto; color: #382447; text-decoration: underline; }
.unit-calls-empty { padding: 22px 0; color: #79737e; font-size: 12px; text-align: center; }
.unit-calls-pagination { display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #e4e2e6; padding: 6px 0; color: #79737e; font-size: 11px; }
.unit-calls-error { padding: 8px; margin: 8px 0; border: 1px solid #b98a94; color: #6b2032; font-size: 12px; }
</style>
