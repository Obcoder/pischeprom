<script setup>
import axios from 'axios'
import { computed, onScopeDispose, ref, watch } from 'vue'
import FindBuyersLauncher from '@/Components/AiSales/FindBuyersLauncher.vue'
import { normalizeReviewItems, reviewBadgeCount } from '@/Components/AiSales/reviewProjection.js'

const props = defineProps({
    productId: { type: [Number, String], required: true },
    active: { type: Boolean, default: true },
})

const loading = ref(false)
const loaded = ref(false)
const error = ref('')
const reviewError = ref('')
const campaigns = ref([])
const jobs = ref([])
const reviewItems = ref([])
const reviewLoaded = ref(false)
const belongsToProduct = products => (products || []).some(product =>
    Number(product.id) === Number(props.productId) && product.role !== 'exclude')
const productCampaigns = computed(() => campaigns.value.filter(campaign => belongsToProduct(campaign.products)))
const productJobs = computed(() => jobs.value.filter(job => belongsToProduct(job.job?.products)))
const candidates = computed(() => {
    const byId = new Map()
    productJobs.value.forEach(job => (job.candidates || []).forEach(candidate => byId.set(candidate.id, candidate)))
    return Array.from(byId.values())
})
const currentCampaign = computed(() => productCampaigns.value[0] || null)
const normalizedReviewItems = computed(() => normalizeReviewItems(reviewItems.value))
const reviewCount = computed(() => reviewBadgeCount(normalizedReviewItems.value, candidates.value))
const counters = computed(() => ({
    campaigns: productCampaigns.value.length,
    results: productJobs.value.reduce((total, job) => total + Number(job.counts?.results?.total || 0), 0),
    research: productJobs.value.reduce((total, job) => total + Number(job.counts?.research?.completed || 0), 0),
    candidates: candidates.value.length,
    reviews: reviewCount.value,
}))
const metrics = computed(() => [
    { key: 'campaigns', label: 'Кампании', value: counters.value.campaigns },
    { key: 'results', label: 'Найдено страниц', value: counters.value.results },
    { key: 'research', label: 'Изучено страниц', value: counters.value.research },
    { key: 'candidates', label: 'Кандидаты', value: counters.value.candidates },
    { key: 'reviews', label: 'На проверке', value: reviewLoaded.value ? counters.value.reviews : null },
])
const campaignUrl = computed(() => currentCampaign.value
    ? `/Ameise/ai-sales?tab=campaigns&campaign=${encodeURIComponent(currentCampaign.value.id)}`
    : '/Ameise/ai-sales?tab=campaigns')
const reviewUrl = computed(() => `/Ameise/ai-sales?tab=review&product=${encodeURIComponent(props.productId)}#candidate-review`)
const candidateHeaders = [
    { title: 'Компания', key: 'company', sortable: false },
    { title: 'Статус', key: 'status', sortable: false },
    { title: '', key: 'action', sortable: false, align: 'end', width: 110 },
]
const statuses = {
    draft: 'Черновик', review_required: 'На проверке', approved: 'Одобрена', scheduled: 'Запланирована',
    running: 'Поиск идёт', paused: 'Приостановлена', blocked: 'Требует внимания', completed: 'Завершена',
    cancelled: 'Отменена', archived: 'В архиве', pending_resolution: 'Ожидает проверки',
    exact_existing_unit: 'Найдена в базе', probable_existing_review: 'Проверить совпадение',
    new_unit_review: 'Новая компания', new_unit_created: 'Добавлена в базу', rejected: 'Отклонена',
    existing_unit_enriched: 'Данные компании обновлены', expired: 'Срок проверки истёк', anonymized: 'Данные обезличены',
}
function statusLabel(status) { return statuses[status] || 'Ожидает обработки' }
function statusColor(status) {
    if (['blocked', 'failed'].includes(status)) return 'error'
    if (['review_required', 'probable_existing_review', 'new_unit_review', 'pending_resolution'].includes(status)) return 'warning'
    if (['completed', 'new_unit_created', 'existing_unit_enriched', 'approved', 'exact_existing_unit'].includes(status)) return 'success'
    return 'primary'
}
function formatDate(value) {
    if (!value) return 'Ещё не запускалась'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? 'Дата неизвестна' : new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' }).format(date)
}
function candidateUrl(candidate) {
    return `/Ameise/ai-sales?tab=review&candidate=${encodeURIComponent(candidate.id)}#candidate-review`
}

let version = 0
let controller = null
function cancelLoad() {
    version++
    controller?.abort()
    controller = null
    loading.value = false
}
async function load() {
    cancelLoad()
    if (!props.productId || !props.active) return
    const requestVersion = version
    const productId = props.productId
    controller = new AbortController()
    const options = { signal: controller.signal }
    loading.value = true
    error.value = ''
    reviewError.value = ''
    reviewLoaded.value = false
    const isCurrent = () => requestVersion === version && String(productId) === String(props.productId)
    try {
        const [campaignResponse, dashboardResponse] = await Promise.all([
            axios.get('/api/ai-sales/campaigns', options),
            axios.get('/api/ai-sales/find-buyers/dashboard?limit=50', options),
        ])
        if (!isCurrent()) return
        campaigns.value = campaignResponse.data.data || []
        jobs.value = dashboardResponse.data.data?.jobs || []
        loaded.value = true
        const queues = await Promise.allSettled(productCampaigns.value.map(async campaign => {
            const response = await axios.get(`/api/ai-sales/campaigns/${encodeURIComponent(campaign.id)}/review-queue?limit=100`, options)
            return response.data.data || []
        }))
        if (!isCurrent()) return
        reviewItems.value = queues.filter(queue => queue.status === 'fulfilled').flatMap(queue => queue.value)
        reviewLoaded.value = queues.every(queue => queue.status === 'fulfilled')
        if (!reviewLoaded.value) reviewError.value = 'Очередь проверки загружена не полностью. Обновите данные или откройте проверку.'
    } catch (requestError) {
        if (!isCurrent()) return
        error.value = [401, 403].includes(requestError?.response?.status)
            ? 'Нет доступа к AI-поиску покупателей.'
            : 'Не удалось загрузить поиск покупателей. Попробуйте обновить данные.'
    } finally {
        if (isCurrent()) { loading.value = false; controller = null }
    }
}
watch(() => props.productId, () => {
    cancelLoad()
    campaigns.value = []
    jobs.value = []
    reviewItems.value = []
    loaded.value = false
    reviewLoaded.value = false
    error.value = ''
    reviewError.value = ''
}, { flush: 'sync' })
watch([() => props.productId, () => props.active], load, { immediate: true, flush: 'sync' })
onScopeDispose(cancelLoad)
</script>

<template>
    <v-card variant="outlined" class="product-ai-sales-card" aria-label="AI-поиск покупателей">
        <div class="product-ai-sales-card__toolbar">
            <div class="product-ai-sales-card__intro">
                <span class="text-subtitle-2 font-weight-bold">Поиск компаний для продукта</span>
                <span class="text-caption text-medium-emphasis">Кампании, найденные компании и очередь проверки</span>
            </div>
            <div class="d-flex align-center ga-1 flex-wrap">
                <FindBuyersLauncher v-if="active" source-type="product" :source-id="productId" compact />
                <v-btn :href="reviewUrl" size="small" variant="tonal" :color="reviewCount ? 'warning' : undefined" prepend-icon="mdi-clipboard-check-outline">
                    Проверить<span v-if="reviewLoaded" class="ml-1">({{ reviewCount }})</span>
                </v-btn>
                <v-btn icon="mdi-refresh" size="small" variant="text" :loading="loading" aria-label="Обновить поиск покупателей" @click="load" />
            </div>
        </div>
        <v-progress-linear v-if="loading" indeterminate height="2" color="primary" />
        <div class="product-ai-sales-card__body">
            <v-alert v-if="error || reviewError" type="warning" variant="tonal" density="compact" class="mb-2" role="alert">{{ error || reviewError }}</v-alert>
            <div class="product-ai-sales-card__metrics" data-testid="product-ai-sales-counters">
                <div v-for="metric in metrics" :key="metric.key" class="product-ai-sales-card__metric" :class="{ 'is-review': metric.key === 'reviews' && metric.value }">
                    <strong>{{ loaded && metric.value !== null ? metric.value : '—' }}</strong>
                    <span>{{ metric.label }}</span>
                </div>
            </div>
            <div v-if="currentCampaign" class="product-ai-sales-card__campaign">
                <div class="product-ai-sales-card__campaign-name">
                    <a :href="campaignUrl">{{ currentCampaign.safe_name || 'Текущая кампания' }}</a>
                    <span class="text-caption text-medium-emphasis">Последний запуск: {{ formatDate(currentCampaign.schedule?.last_run_at) }}</span>
                </div>
                <v-chip size="x-small" variant="tonal" :color="statusColor(currentCampaign.status)">{{ statusLabel(currentCampaign.status) }}</v-chip>
                <v-btn :href="campaignUrl" size="small" variant="text" append-icon="mdi-arrow-top-right">Кампания</v-btn>
                <div v-if="currentCampaign.latest_run?.safe_error_code" class="product-ai-sales-card__campaign-error text-caption text-error">Последний запуск завершился с ошибкой. Подробности доступны в кампании.</div>
            </div>
            <p v-else-if="loaded && !productJobs.length" class="text-body-2 text-medium-emphasis py-3">Чтобы найти компании для продукта, нажмите «Найти покупателей» и задайте условия.</p>
            <v-data-table
                v-if="candidates.length"
                :headers="candidateHeaders"
                :items="candidates"
                :items-per-page="6"
                :items-per-page-options="[6, 12, 24]"
                :hide-default-footer="candidates.length <= 6"
                :mobile="false"
                density="compact"
                items-per-page-text="На странице"
                class="product-ai-sales-card__table"
            >
                <template #item.company="{ item }"><a :href="candidateUrl(item)">{{ item.resolved_unit?.name || 'Компания ожидает проверки' }}</a></template>
                <template #item.status="{ item }"><v-chip size="x-small" variant="tonal" :color="statusColor(item.status)">{{ statusLabel(item.status) }}</v-chip></template>
                <template #item.action="{ item }"><v-btn :href="candidateUrl(item)" size="x-small" variant="text" append-icon="mdi-arrow-top-right">Открыть</v-btn></template>
            </v-data-table>
            <div class="product-ai-sales-card__footer">
                <span v-if="loaded" class="text-caption text-medium-emphasis">Задач по продукту: {{ productJobs.length }}<span v-if="jobs.length >= 50 || campaigns.length >= 100"> · Сводка ограничена последними загруженными кампаниями и задачами</span></span>
                <v-spacer />
                <v-btn href="/Ameise/ai-sales?tab=campaigns" size="x-small" variant="text">Все кампании <v-icon end size="14">mdi-arrow-top-right</v-icon></v-btn>
            </div>
        </div>
    </v-card>
</template>

<style scoped>
.product-ai-sales-card { min-width: 0; }
.product-ai-sales-card__toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 10px 12px; }
.product-ai-sales-card__intro, .product-ai-sales-card__campaign-name { display: flex; flex-direction: column; min-width: 0; gap: 2px; }
.product-ai-sales-card__body { padding: 0 12px 6px; }
.product-ai-sales-card__metrics { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 6px; overflow: hidden; }
.product-ai-sales-card__metric { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px; padding: 7px 10px; background: rgba(var(--v-theme-on-surface), .025); }
.product-ai-sales-card__metric + .product-ai-sales-card__metric { border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.product-ai-sales-card__metric strong { font-size: 18px; line-height: 1.25; font-variant-numeric: tabular-nums; }
.product-ai-sales-card__metric span { font-size: 11px; color: rgba(var(--v-theme-on-surface), .65); }
.product-ai-sales-card__metric.is-review { background: rgba(var(--v-theme-warning), .1); }
.product-ai-sales-card__campaign { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px 0; }
.product-ai-sales-card__campaign-name { flex: 1; font-size: 13px; overflow-wrap: anywhere; }
.product-ai-sales-card__campaign-error { flex-basis: 100%; }
.product-ai-sales-card__table { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); font-size: 12px; }
.product-ai-sales-card__table :deep(.v-data-table-footer) { min-height: 42px; padding: 4px 0; flex-wrap: wrap; }
.product-ai-sales-card__footer { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.product-ai-sales-card a { color: rgb(var(--v-theme-primary)); text-decoration: none; }
.product-ai-sales-card a:hover { text-decoration: underline; }
@media (max-width: 650px) {
    .product-ai-sales-card__metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .product-ai-sales-card__metric { padding: 6px; }
    .product-ai-sales-card__metric + .product-ai-sales-card__metric { border-left: 0; }
    .product-ai-sales-card__metric:nth-child(n+4) { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
}
</style>
