<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { onMounted, reactive, ref, watch } from 'vue'
import { route } from 'ziggy-js'

defineOptions({ layout: VerwalterLayout })

const loading = ref(false)
const error = ref('')
const imports = ref([])
const statuses = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const filters = reactive({
    search: '',
    source_channel: null,
    status: null,
    supplier_state: null,
    extension: null,
    applied: null,
    from: null,
    to: null,
    requires_review: false,
    has_error: false,
})
let searchTimer = null

const headers = [
    { title: 'Получен', key: 'received_at', width: 150 },
    { title: 'Источник', key: 'source_channel', width: 110 },
    { title: 'Поставщик', key: 'supplier', minWidth: 190 },
    { title: 'Файл', key: 'file', minWidth: 230 },
    { title: 'Статус', key: 'status', width: 205 },
    { title: 'Строки', key: 'counts', width: 180, sortable: false },
    { title: 'Reviewer', key: 'reviewer', width: 140 },
    { title: '', key: 'actions', width: 62, sortable: false },
]

async function load() {
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get('/api/ai/price-lists', {
            params: {
                page: page.value,
                per_page: perPage.value,
                search: filters.search || undefined,
                source_channel: filters.source_channel || undefined,
                status: filters.status || undefined,
                supplier_state: filters.supplier_state || undefined,
                extension: filters.extension || undefined,
                applied: filters.applied,
                from: filters.from || undefined,
                to: filters.to || undefined,
                requires_review: filters.requires_review || undefined,
                has_error: filters.has_error || undefined,
            },
        })
        imports.value = data.data || []
        total.value = data.total || 0
        statuses.value = data.statuses || statuses.value
    } catch (requestError) {
        console.error(requestError)
        error.value = requestError.response?.data?.message || 'Не удалось загрузить прайс-листы.'
    } finally {
        loading.value = false
    }
}

function scheduleLoad() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        page.value = 1
        load()
    }, 350)
}

function showUrl(uuid) {
    return route('Ameise.ai.price-lists.show', uuid)
}

function formatDate(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

function formatBytes(value) {
    const bytes = Number(value || 0)
    if (bytes < 1024) return `${bytes} Б`
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} КБ`
    return `${(bytes / 1024 ** 2).toFixed(1)} МБ`
}

function statusColor(status) {
    if (status === 'applied') return 'green'
    if (['failed', 'quarantined', 'unsupported_format'].includes(status)) return 'red'
    if (['review_required', 'supplier_unresolved', 'awaiting_classification'].includes(status)) return 'amber-darken-2'
    if (['applying', 'extracting', 'ocr', 'normalizing', 'matching', 'validating', 'queued'].includes(status)) return 'blue'
    return 'grey'
}

watch(() => [filters.source_channel, filters.status, filters.supplier_state, filters.extension, filters.applied, filters.from, filters.to, filters.requires_review, filters.has_error], () => {
    page.value = 1
    load()
})
watch(() => filters.search, scheduleLoad)
watch([page, perPage], load)
onMounted(load)
</script>

<template>
    <Head title="AI · Прайс-листы" />

    <main class="price-lists-page">
        <header class="price-lists-hero">
            <div>
                <div class="price-lists-eyebrow">Ameise · AI</div>
                <h1>Прайс-листы поставщиков</h1>
                <p>Входящие файлы из Почты и MAX: распознавание, проверка и контролируемый импорт закупочных цен.</p>
            </div>
            <v-btn icon="mdi-refresh" variant="tonal" color="white" :loading="loading" title="Обновить" @click="load" />
        </header>

        <section class="price-lists-panel">
            <div class="price-lists-filters">
                <v-text-field
                    v-model="filters.search"
                    label="Файл, поставщик, email или сообщение"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
                <v-select
                    v-model="filters.source_channel"
                    :items="[{ title: 'Почта', value: 'email' }, { title: 'MAX', value: 'max' }]"
                    label="Канал"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
                <v-select
                    v-model="filters.status"
                    :items="statuses"
                    item-title="title"
                    item-value="value"
                    label="Статус"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
                <v-select
                    v-model="filters.supplier_state"
                    :items="[{ title: 'Определён', value: 'resolved' }, { title: 'Не определён', value: 'unresolved' }]"
                    label="Поставщик"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
                <v-select
                    v-model="filters.extension"
                    :items="['xlsx', 'xls', 'csv', 'tsv', 'docx', 'pdf', 'jpg', 'png', 'tiff']"
                    label="Формат"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
                <v-select
                    v-model="filters.applied"
                    :items="[{ title: 'Применён', value: true }, { title: 'Не применён', value: false }]"
                    label="Применение"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                />
                <v-text-field v-model="filters.from" type="date" label="Получен с" variant="outlined" density="compact" hide-details clearable />
                <v-text-field v-model="filters.to" type="date" label="Получен по" variant="outlined" density="compact" hide-details clearable />
                <v-checkbox v-model="filters.requires_review" label="Нужна проверка" color="amber" density="compact" hide-details />
                <v-checkbox v-model="filters.has_error" label="С ошибками" color="red" density="compact" hide-details />
            </div>

            <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-4">{{ error }}</v-alert>

            <v-data-table-server
                v-model:page="page"
                v-model:items-per-page="perPage"
                :headers="headers"
                :items="imports"
                :items-length="total"
                :loading="loading"
                item-value="uuid"
                class="price-lists-table"
                hover
                @click:row="(_, row) => $inertia.visit(showUrl(row.item.uuid))"
            >
                <template #item.received_at="{ item }">
                    <span class="text-no-wrap">{{ formatDate(item.received_at) }}</span>
                </template>
                <template #item.source_channel="{ item }">
                    <v-chip :prepend-icon="item.source_channel === 'email' ? 'mdi-email-outline' : 'mdi-message-text-outline'" size="small" variant="tonal">
                        {{ item.source_label }}
                    </v-chip>
                </template>
                <template #item.supplier="{ item }">
                    <strong>{{ item.supplier?.name || 'Не определён' }}</strong>
                    <div v-if="item.supplier?.INN" class="cell-muted">ИНН {{ item.supplier.INN }}</div>
                </template>
                <template #item.file="{ item }">
                    <div class="file-cell" :title="item.file.original_name">
                        <strong>{{ item.file.name }}</strong>
                        <span>{{ (item.file.extension || '—').toUpperCase() }} · {{ formatBytes(item.file.size_bytes) }}</span>
                    </div>
                </template>
                <template #item.status="{ item }">
                    <v-chip :color="statusColor(item.status)" size="small" variant="tonal">{{ item.status_label }}</v-chip>
                    <v-progress-linear v-if="item.progress < 100 && !['failed', 'awaiting_classification'].includes(item.status)" :model-value="item.progress" height="3" rounded class="mt-2" />
                </template>
                <template #item.counts="{ item }">
                    <div class="count-row">
                        <span title="Всего">Σ {{ item.counts.total }}</span>
                        <span class="count-exact" title="Точные">✓ {{ item.counts.exact }}</span>
                        <span class="count-probable" title="Вероятные">≈ {{ item.counts.probable }}</span>
                        <span title="Без совпадения">+ {{ item.counts.unmatched }}</span>
                    </div>
                </template>
                <template #item.reviewer="{ item }">{{ item.reviewer?.name || '—' }}</template>
                <template #item.actions="{ item }">
                    <Link :href="showUrl(item.uuid)" @click.stop>
                        <v-btn icon="mdi-open-in-new" variant="text" size="small" title="Открыть" />
                    </Link>
                </template>
                <template #no-data>
                    <div class="empty-state">
                        <v-icon icon="mdi-file-search-outline" size="34" />
                        <strong>Прайс-листы пока не поступали</strong>
                        <span>Подходящие вложения из входящей Почты и MAX появятся здесь автоматически.</span>
                    </div>
                </template>
            </v-data-table-server>
        </section>
    </main>
</template>

<style scoped>
.price-lists-page { min-height: calc(100vh - 58px); padding: 22px; background: radial-gradient(circle at 8% 0%, #394a5b 0, #17212b 42%, #10171e 100%); color: #f7fafc; }
.price-lists-hero { max-width: 1500px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
.price-lists-eyebrow { color: #9bc4d8; font-size: .75rem; letter-spacing: .18em; text-transform: uppercase; font-weight: 800; }
.price-lists-hero h1 { margin: 4px 0 5px; font-size: clamp(1.55rem, 3vw, 2.35rem); line-height: 1.08; }
.price-lists-hero p { margin: 0; color: #c8d4de; max-width: 820px; }
.price-lists-panel { max-width: 1500px; margin: 0 auto; overflow: hidden; border: 1px solid rgba(255,255,255,.13); border-radius: 16px; background: #f7f9fb; color: #1b2732; box-shadow: 0 18px 60px rgba(0,0,0,.28); }
.price-lists-filters { padding: 14px; display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 10px; align-items: center; border-bottom: 1px solid #dce3e8; background: #fff; }
.price-lists-filters > :first-child { grid-column: span 4; }.price-lists-filters > :not(:first-child) { grid-column: span 2; }
.price-lists-table :deep(tbody tr) { cursor: pointer; }
.file-cell { max-width: 310px; display: flex; flex-direction: column; }
.file-cell strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-cell span, .cell-muted { color: #6c7b88; font-size: .76rem; }
.count-row { display: flex; gap: 9px; font-variant-numeric: tabular-nums; font-size: .82rem; }
.count-exact { color: #187a4a; }.count-probable { color: #9a6500; }
.empty-state { min-height: 230px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px; color: #6c7b88; }
@media (max-width: 1050px) { .price-lists-filters { grid-template-columns: 1fr 1fr; }.price-lists-filters > :first-child { grid-column: 1 / -1; }.price-lists-filters > :not(:first-child) { grid-column: auto; } }
@media (max-width: 640px) { .price-lists-page { padding: 14px 8px; }.price-lists-filters { grid-template-columns: 1fr; }.price-lists-filters > :first-child, .price-lists-filters > :not(:first-child) { grid-column: auto; } }
</style>
