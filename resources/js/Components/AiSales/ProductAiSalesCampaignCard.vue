<script setup>
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'
import FindBuyersLauncher from '@/Components/AiSales/FindBuyersLauncher.vue'
import { normalizeReviewItems, reviewBadgeCount } from '@/Components/AiSales/reviewProjection.js'

const props = defineProps({
    productId: {
        type: [Number, String],
        required: true,
    },
})

const loading = ref(false)
const error = ref('')
const campaigns = ref([])
const jobs = ref([])
const reviewItems = ref([])

const productCampaigns = computed(() => campaigns.value.filter(campaign =>
    (campaign.products || []).some(product => Number(product.id) === Number(props.productId) && product.role !== 'exclude'),
))

const productJobs = computed(() => jobs.value.filter(job =>
    (job.job?.products || []).some(product => Number(product.id) === Number(props.productId) && product.role !== 'exclude'),
))

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
    research: productJobs.value.reduce((total, job) => total + Number(job.counts?.research?.total || 0), 0),
    candidates: candidates.value.length,
    reviews: reviewCount.value,
}))

const campaignUrl = computed(() => currentCampaign.value
    ? `/Ameise/ai-sales?tab=campaigns&campaign=${encodeURIComponent(currentCampaign.value.id)}`
    : '/Ameise/ai-sales?tab=campaigns')
const reviewUrl = computed(() => `/Ameise/ai-sales?tab=review&product=${encodeURIComponent(props.productId)}#candidate-review`)

async function load() {
    if (!props.productId) return
    loading.value = true
    error.value = ''
    try {
        const [campaignResponse, dashboardResponse] = await Promise.all([
            axios.get('/api/ai-sales/campaigns'),
            axios.get('/api/ai-sales/find-buyers/dashboard?limit=50'),
        ])
        campaigns.value = campaignResponse.data.data || []
        jobs.value = dashboardResponse.data.data?.jobs || []

        const queues = await Promise.all(productCampaigns.value.map(async campaign => {
            const response = await axios.get(`/api/ai-sales/campaigns/${encodeURIComponent(campaign.id)}/review-queue?limit=100`)
            return response.data.data || []
        }))
        reviewItems.value = queues.flat()
    } catch (requestError) {
        error.value = [401, 403].includes(requestError?.response?.status)
            ? 'AI Sales недоступен текущему пользователю.'
            : 'Не удалось загрузить безопасную AI Sales projection.'
    } finally {
        loading.value = false
    }
}

watch(() => props.productId, load)
onMounted(load)
</script>

<template>
    <v-card variant="outlined" color="deep-purple" class="product-ai-sales-card">
        <v-card-title class="d-flex align-center ga-2 flex-wrap">
            <v-icon icon="mdi-account-search-outline" />
            <span>AI-поиск покупателей</span>
            <v-chip size="small" color="deep-purple" variant="tonal">Campaign workflow</v-chip>
            <v-spacer />
            <v-btn icon="mdi-refresh" size="small" variant="text" :loading="loading" aria-label="Обновить AI Sales" @click="load" />
        </v-card-title>

        <v-card-text>
            <v-alert v-if="error" type="warning" variant="tonal" density="compact" class="mb-3">
                {{ error }}
            </v-alert>
            <div class="text-body-2 text-medium-emphasis mb-3">
                Отдельный Product-first workflow: Campaign → Search Results → public research → Candidate → human review.
                Открытие блока выполняет только защищённые read projections.
            </div>

            <div class="d-flex ga-2 flex-wrap mb-4" data-testid="product-ai-sales-counters">
                <v-chip size="small" variant="outlined">campaigns: {{ counters.campaigns }}</v-chip>
                <v-chip size="small" variant="outlined">results: {{ counters.results }}</v-chip>
                <v-chip size="small" variant="outlined">research: {{ counters.research }}</v-chip>
                <v-chip size="small" variant="outlined">candidates: {{ counters.candidates }}</v-chip>
                <v-chip size="small" color="warning" variant="tonal">review items: {{ counters.reviews }}</v-chip>
            </div>

            <div class="d-flex align-center ga-2 flex-wrap">
                <FindBuyersLauncher source-type="product" :source-id="productId" />
                <v-btn :href="campaignUrl" variant="tonal" prepend-icon="mdi-target-account">
                    Открыть текущую кампанию
                </v-btn>
                <v-btn :href="reviewUrl" color="warning" variant="tonal" prepend-icon="mdi-clipboard-check-outline">
                    На проверке: {{ reviewCount }}
                </v-btn>
            </div>

            <div v-if="currentCampaign" class="text-caption text-medium-emphasis mt-3">
                {{ currentCampaign.status }} · {{ currentCampaign.automation_mode }} ·
                {{ currentCampaign.latest_run?.safe_error_code || currentCampaign.safe_status_summary || 'review projection' }}
            </div>

            <div v-if="candidates.length" class="d-flex ga-2 flex-wrap mt-3">
                <v-btn
                    v-for="candidate in candidates"
                    :key="candidate.id"
                    :href="`/Ameise/ai-sales?tab=review&candidate=${encodeURIComponent(candidate.id)}#candidate-review`"
                    size="small"
                    variant="text"
                >
                    Candidate: {{ candidate.status }}
                </v-btn>
            </div>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.product-ai-sales-card {
    border-width: 2px;
}
</style>
