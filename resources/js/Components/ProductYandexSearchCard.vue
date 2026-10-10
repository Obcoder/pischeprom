<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { searchErrorMessage, safeSearchUrl } from './productYandexSearch.js'

const props = defineProps({
    productId: { type: Number, required: true },
    productName: { type: String, default: '' },
    active: { type: Boolean, default: true },
})

const headers = [
    { title: '№', key: 'position', width: 52 },
    { title: 'Сайт / страница', key: 'title', width: '40%', sortable: false },
    { title: 'Описание', key: 'snippet', sortable: false },
]
const defaultQuery = () => props.productName ? `${props.productName} купить` : ''
const query = ref(defaultQuery())
const maxResults = ref(100)
const request = ref(null)
const results = ref([])
const filter = ref('')
const itemsPerPage = ref(20)
const isSubmitting = ref(false)
const isLoading = ref(false)
const error = ref('')
const queryDirty = ref(false)
const expanded = ref(new Set())
let timer = null
let generation = 0
let controller = null
let disposed = false

const pending = computed(() => ['queued', 'processing'].includes(request.value?.status))
const domainCount = computed(() => new Set(results.value.map(item => item.domain).filter(Boolean)).size)
const filteredResults = computed(() => {
    const needle = filter.value?.trim().toLocaleLowerCase('ru')
    return needle ? results.value.filter(item => [item.title, item.domain, item.url, item.snippet]
        .some(value => String(value || '').toLocaleLowerCase('ru').includes(needle))) : results.value
})
const status = computed(() => ({
    queued: { label: 'В очереди', color: 'info' },
    processing: { label: 'Поиск…', color: 'info' },
    done: { label: 'Готово', color: 'success' },
    failed: { label: 'Ошибка поиска', color: 'error' },
}[request.value?.status] || { label: 'Ещё не запускался', color: 'default' }))
const failure = computed(() => request.value?.status === 'failed'
    ? (request.value.error_code && request.value.error_message) || searchErrorMessage(request.value.error_code || request.value.error_message) : '')
const requestedAt = computed(() => request.value?.searched_at || request.value?.finished_at || request.value?.started_at || request.value?.created_at)
const canSubmit = computed(() => props.active && !isSubmitting.value && !isLoading.value && !pending.value && query.value.trim().length > 0 && query.value.trim().length <= 255)

function formatDate(value) {
    if (!value) return ''
    const date = new Date(value.replace(' ', 'T'))
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(date)
}

function stopPolling() {
    if (timer !== null) clearTimeout(timer)
    timer = null
}

function invalidate() {
    generation++
    controller?.abort()
    controller = null
    stopPolling()
    isLoading.value = false
    isSubmitting.value = false
}

function httpError(cause, action) {
    const code = cause?.response?.status
    if ([401, 403, 419].includes(code)) return 'Нет доступа к поиску. Проверьте авторизацию и обновите страницу.'
    if (code === 422) return 'Проверьте запрос: от 1 до 255 символов, количество результатов — от 10 до 100.'
    if (code === 429) return 'Слишком много запросов. Подождите немного и обновите статус.'
    return action === 'submit'
        ? 'Не удалось подтвердить запуск поиска. Обновите статус перед повторной попыткой.'
        : 'Не удалось обновить выдачу. Проверьте соединение и нажмите «Обновить».'
}

function applyResponse(data) {
    request.value = data.request || null
    results.value = data.results || []
    expanded.value = new Set()
    if (request.value?.query && !queryDirty.value) query.value = request.value.query
}

function schedulePoll() {
    stopPolling()
    if (!disposed && props.active && pending.value) timer = setTimeout(() => loadResults(false), 3000)
}

async function loadResults(latest = true) {
    if (!props.active || disposed || !props.productId) return
    stopPolling()
    const token = ++generation
    controller?.abort()
    controller = new AbortController()
    isLoading.value = true
    error.value = ''
    const endpoint = latest || !request.value?.id ? 'latest' : request.value.id
    try {
        const { data } = await axios.get(`/api/products/${props.productId}/yandex-search/${endpoint}`, { signal: controller.signal, timeout: 20000 })
        if (token !== generation) return
        applyResponse(data)
        schedulePoll()
    } catch (cause) {
        if (token !== generation || cause?.code === 'ERR_CANCELED') return
        error.value = httpError(cause, 'load')
    } finally {
        if (token === generation) isLoading.value = false
    }
}

async function runSearch() {
    if (!canSubmit.value) return
    stopPolling()
    const token = ++generation
    controller?.abort()
    controller = new AbortController()
    isSubmitting.value = true
    error.value = ''
    try {
        const { data } = await axios.post(`/api/products/${props.productId}/yandex-search`, {
            query: query.value.trim(), max_results: maxResults.value,
        }, { signal: controller.signal, timeout: 60000 })
        if (token !== generation) return
        request.value = { id: data.request_id, status: data.status, query: data.query, results_count: 0, error_message: null }
        query.value = data.query
        queryDirty.value = false
        results.value = []
        filter.value = ''
        isSubmitting.value = false
        await loadResults(false)
    } catch (cause) {
        if (token !== generation || cause?.code === 'ERR_CANCELED') return
        const failed = cause?.response?.data
        if (failed?.request_id && failed.status === 'failed' && /^yandex_[a-z0-9_]+$/.test(failed.error_code || '')) {
            request.value = { id: failed.request_id, status: 'failed', query: query.value.trim(), error_code: failed.error_code }
            results.value = []
        } else {
            error.value = httpError(cause, 'submit')
        }
    } finally {
        if (token === generation) isSubmitting.value = false
    }
}

function toggleSnippet(id) {
    const next = new Set(expanded.value)
    next.has(id) ? next.delete(id) : next.add(id)
    expanded.value = next
}

watch(() => [props.productId, props.active], ([id, active], previous) => {
    invalidate()
    if (!previous || id !== previous[0]) {
        query.value = defaultQuery()
        queryDirty.value = false
        request.value = null
        results.value = []
        filter.value = ''
        expanded.value = new Set()
        error.value = ''
    }
    if (active) loadResults()
}, { immediate: true, flush: 'sync' })

watch(() => props.productName, () => {
    if (!queryDirty.value && !request.value) query.value = defaultQuery()
})

onBeforeUnmount(() => { disposed = true; invalidate() })
</script>

<template>
    <section class="yandex-search" aria-label="Выдача Яндекса">
        <div class="yandex-search__heading">
            <div class="d-flex align-center ga-2 flex-wrap">
                <v-icon icon="mdi-magnify" size="20" color="primary" />
                <h3>Выдача Яндекса</h3>
                <v-chip :color="status.color" size="x-small" variant="tonal" role="status">{{ status.label }}</v-chip>
            </div>
            <v-btn size="small" variant="text" prepend-icon="mdi-refresh" :loading="isLoading" :disabled="isSubmitting" @click="loadResults()">Обновить</v-btn>
        </div>

        <form class="yandex-search__form" @submit.prevent="runSearch">
            <v-text-field v-model="query" label="Поисковый запрос" placeholder="Название продукта, отрасль или компания" density="compact" variant="outlined" hide-details maxlength="255" :disabled="isSubmitting || pending" @update:model-value="queryDirty = true" />
            <v-select v-model="maxResults" :items="[10, 20, 30, 50, 100]" label="До результатов" density="compact" variant="outlined" hide-details :disabled="isSubmitting || pending" />
            <v-btn type="submit" color="primary" variant="flat" height="40" prepend-icon="mdi-magnify" :loading="isSubmitting" :disabled="!canSubmit">Получить выдачу</v-btn>
        </form>

        <div class="yandex-search__summary" aria-live="polite">
            <span><strong>{{ results.length }}</strong> результатов</span>
            <span><strong>{{ domainCount }}</strong> сайтов</span>
            <span v-if="requestedAt">{{ formatDate(requestedAt) }}</span>
            <span v-if="request?.query" class="yandex-search__last-query" :title="request.query">Запрос: {{ request.query }}</span>
        </div>

        <v-alert v-if="error || failure" type="error" variant="tonal" density="compact" class="yandex-search__alert" role="alert">
            {{ error || failure }}
            <details v-if="failure && request" class="yandex-search__details">
                <summary>Технические сведения</summary>
                Запрос № {{ request.id }} · {{ request.error_code || request.error_message || 'yandex_product_search_failed_safely' }}
            </details>
        </v-alert>
        <div v-if="pending && !error" class="yandex-search__progress" role="status">
            <v-progress-circular indeterminate size="14" width="2" color="primary" />
            {{ request.status === 'queued' ? 'Ожидаем запуска. Статус обновляется автоматически.' : 'Собираем страницы выдачи. Результаты появятся после завершения.' }}
        </div>

        <template v-if="results.length">
            <v-text-field v-model="filter" class="yandex-search__filter" label="Фильтр по сайту, заголовку или описанию" prepend-inner-icon="mdi-filter-outline" density="compact" variant="outlined" hide-details clearable />
            <v-data-table v-model:items-per-page="itemsPerPage" :headers="headers" :items="filteredResults" item-value="id" density="compact" :items-per-page-options="[10, 20, 50, 100]" :hide-default-footer="filteredResults.length <= itemsPerPage" :mobile="false" items-per-page-text="На странице" no-data-text="Совпадений нет. Измените фильтр." class="yandex-search__table">
                <template #item.title="{ item }">
                    <div class="yandex-search__result">
                        <a v-if="safeSearchUrl(item.url)" :href="safeSearchUrl(item.url)" target="_blank" rel="noopener noreferrer" class="yandex-search__link">{{ item.title || item.domain || item.url }}<v-icon icon="mdi-open-in-new" size="12" class="ml-1" /></a>
                        <span v-else>{{ item.title || item.domain || 'Страница' }}</span>
                        <div class="yandex-search__domain" :title="item.url">{{ item.domain || item.url }}</div>
                    </div>
                </template>
                <template #item.snippet="{ item }">
                    <div class="yandex-search__snippet" :class="{ 'yandex-search__snippet--expanded': expanded.has(item.id) }">{{ item.snippet || 'Описание отсутствует' }}</div>
                    <button v-if="item.snippet" type="button" class="yandex-search__expand" :aria-expanded="expanded.has(item.id)" @click="toggleSnippet(item.id)">{{ expanded.has(item.id) ? 'Свернуть' : 'Подробнее' }}</button>
                </template>
            </v-data-table>
        </template>
        <div v-else-if="!pending && !isLoading && !failure && !error" class="yandex-search__empty">
            <v-icon icon="mdi-text-search" size="28" />
            <span>{{ request?.status === 'done' ? 'По этому запросу ничего не найдено. Попробуйте другую формулировку.' : 'Укажите запрос, чтобы найти сайты и компании в Яндексе.' }}</span>
        </div>
    </section>
</template>

<style scoped>
.yandex-search { color: #302a38; min-width: 0; }
.yandex-search__heading { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 12px; }
.yandex-search h3 { font-size: 15px; font-weight: 600; }
.yandex-search__form { display: grid; grid-template-columns: minmax(180px, 1fr) 150px auto; gap: 10px; align-items: start; }
.yandex-search__form :deep(.v-field), .yandex-search__filter :deep(.v-field) { font-size: 13px; }
.yandex-search__form :deep(.v-btn), .yandex-search__heading :deep(.v-btn) { text-transform: none; letter-spacing: 0; }
.yandex-search__summary { display: flex; gap: 8px 18px; flex-wrap: wrap; margin: 10px 0; color: #736b7c; font-size: 12px; align-items: baseline; }
.yandex-search__summary strong { color: #352345; font-weight: 600; }
.yandex-search__last-query { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.yandex-search__alert { margin: 10px 0; font-size: 13px; }
.yandex-search__details { font-size: 11px; margin-top: 4px; overflow-wrap: anywhere; }
.yandex-search__details summary { cursor: pointer; }
.yandex-search__progress { display: flex; align-items: center; gap: 8px; margin: 12px 0; color: #736b7c; font-size: 12px; }
.yandex-search__filter { max-width: 520px; margin: 14px 0 8px; }
.yandex-search__table { border-top: 1px solid #ebe6ef; background: transparent; font-size: 13px; }
.yandex-search__table :deep(th) { font-size: 11px; color: #736b7c; background: #faf8fc; }
.yandex-search__table :deep(td) { vertical-align: top; padding: 8px 12px !important; }
.yandex-search__result { overflow-wrap: anywhere; }
.yandex-search__link { text-decoration: none; font-weight: 500; color: #4e3976; line-height: 1.4; }
.yandex-search__link:hover { text-decoration: underline; }
.yandex-search__domain { margin-top: 3px; color: #81798a; font-size: 11px; overflow-wrap: anywhere; }
.yandex-search__snippet { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; line-height: 1.5; color: #605768; overflow-wrap: anywhere; }
.yandex-search__snippet--expanded { display: block; }
.yandex-search__expand { color: #67518e; font-size: 11px; margin-top: 3px; }
.yandex-search__empty { display: flex; gap: 10px; align-items: center; padding: 24px 12px; background: #faf8fc; border: 1px dashed #e3dce9; border-radius: 6px; font-size: 13px; color: #81798a; }
@media (max-width: 680px) {
    .yandex-search__form { grid-template-columns: minmax(0, 1fr) auto; }
    .yandex-search__form > :first-child { grid-column: 1 / -1; }
    .yandex-search__table :deep(td) { padding: 8px 6px !important; }
}
</style>
