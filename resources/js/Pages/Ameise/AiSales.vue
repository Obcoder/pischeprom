<script setup>
import axios from 'axios'
import { Head, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, ref } from 'vue'
import CandidateIngestionReviewPanel from '@/Components/AiSales/CandidateIngestionReviewPanel.vue'
import CandidateReviewCard from '@/Components/AiSales/CandidateReviewCard.vue'
import ClientAcquisitionCampaignDashboard from '@/Components/AiSales/ClientAcquisitionCampaignDashboard.vue'
import FindBuyersDashboard from '@/Components/AiSales/FindBuyersDashboard.vue'
import { isCandidateReview, normalizeReviewItems, reviewBadgeCount } from '@/Components/AiSales/reviewProjection.js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const page = usePage()
const allowedTabs = ['campaigns', 'review', 'candidates', 'units', 'scores', 'drafts', 'audit']

function requestedValue(name) {
    const parsed = new URL(page.url, 'https://ui.invalid')
    return parsed.searchParams.get(name)
}

const requestedTab = requestedValue('tab')
const requestedCandidateId = requestedValue('candidate')
const requestedCampaignId = requestedValue('campaign')
const requestedProductId = requestedValue('product')
const tab = ref(allowedTabs.includes(requestedTab) ? requestedTab : 'campaigns')
const loading = ref(false)
const error = ref('')
const candidates = ref([])
const campaigns = ref([])
const campaignReviewItems = ref([])
const ingestionReviews = ref({})
const candidateQualityFilter = ref('all')
const assistedResearchCategories = new Set(['public_research_review', 'candidate_ingestion_review'])

const filteredCandidates = computed(() => {
    let rows = candidates.value
    if (requestedProductId) {
        rows = rows.filter(candidate => (candidate.products || [])
            .some(product => Number(product.id) === Number(requestedProductId)))
    }
    if (candidateQualityFilter.value === 'needs_review') return rows.filter(isCandidateReview)
    if (candidateQualityFilter.value !== 'all') {
        return rows.filter(candidate => candidate.investigation?.buyer_classification?.role === candidateQualityFilter.value)
    }

    return rows
})
const candidatesForReview = computed(() => filteredCandidates.value.filter(isCandidateReview))
const resolvedCandidates = computed(() => filteredCandidates.value.filter(candidate => candidate.resolved_unit))
const normalizedReviewItems = computed(() => normalizeReviewItems(campaignReviewItems.value))
const actionableResearchItems = computed(() => normalizedReviewItems.value.filter(item =>
    assistedResearchCategories.has(item?.category) && item?.search_job_public_id && reviewFor(item),
))
const otherReviewItems = computed(() => normalizedReviewItems.value.filter(item =>
    !actionableResearchItems.value.includes(item),
))
const reviewBadge = computed(() => reviewBadgeCount(normalizedReviewItems.value, candidatesForReview.value))

function reviewFor(item) {
    return ingestionReviews.value[item?.search_job_public_id] || null
}

function campaignName(item) {
    return campaigns.value.find(campaign => campaign.id === item?.campaign_id)?.safe_name || 'Campaign'
}

async function safeGet(url, fallback) {
    try {
        const response = await axios.get(url)
        return response.data.data ?? fallback
    } catch (requestError) {
        if ([401, 403, 404].includes(requestError?.response?.status)) return fallback
        throw requestError
    }
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const [candidateRows, campaignRows] = await Promise.all([
            safeGet('/api/ai-sales/prospecting/candidates?per_page=50', []),
            safeGet('/api/ai-sales/campaigns', []),
        ])
        candidates.value = candidateRows
        campaigns.value = campaignRows

        if (requestedCandidateId && !candidates.value.some(candidate => candidate.id === requestedCandidateId)) {
            const exact = await safeGet(
                `/api/ai-sales/prospecting/candidates/${encodeURIComponent(requestedCandidateId)}`,
                null,
            )
            if (exact) candidates.value = [exact, ...candidates.value]
        }

        const queues = await Promise.all(campaigns.value.map(campaign => safeGet(
            `/api/ai-sales/campaigns/${encodeURIComponent(campaign.id)}/review-queue?limit=100`,
            [],
        )))
        campaignReviewItems.value = queues.flat()
        const reviewJobIds = [...new Set(campaignReviewItems.value
            .filter(item => assistedResearchCategories.has(item?.category))
            .map(item => item?.search_job_public_id)
            .filter(Boolean))]
        const reviewResponses = await Promise.all(reviewJobIds.map(async jobId => {
            const data = await safeGet(
                `/api/ai-sales/prospecting/jobs/${encodeURIComponent(jobId)}/search`,
                null,
            )

            return [jobId, data?.candidate_ingestion_review || null]
        }))
        ingestionReviews.value = Object.fromEntries(reviewResponses.filter(([, review]) => review))

        await nextTick()
        if (requestedCandidateId && typeof document !== 'undefined') {
            document.getElementById(`candidate-${requestedCandidateId}`)?.scrollIntoView({ block: 'center' })
        }
    } catch {
        error.value = 'AI Sales projections безопасно недоступны. Выполнение Campaign/provider не запускалось.'
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>

<template>
    <Head title="AI Sales" />

    <v-container fluid class="ai-sales-page pa-3 pa-md-4">
        <header class="d-flex align-center ga-3 flex-wrap mb-4">
            <v-icon icon="mdi-account-search-outline" size="34" color="deep-purple" />
            <div>
                <h1 class="text-h4 font-weight-black">AI Sales</h1>
                <div class="text-body-2 text-medium-emphasis">
                    Product-first Campaigns, human review и durable Unit dossiers.
                </div>
            </div>
            <v-spacer />
            <v-chip color="success" variant="tonal">assisted · bounded</v-chip>
            <v-chip color="error" variant="outlined">auto Unit / Entity / email off</v-chip>
            <v-btn icon="mdi-refresh" variant="text" :loading="loading" aria-label="Обновить AI Sales" @click="load" />
        </header>

        <v-alert v-if="error" type="warning" variant="tonal" class="mb-3">
            {{ error }}
        </v-alert>

        <v-card variant="outlined">
            <v-tabs v-model="tab" show-arrows color="deep-purple">
                <v-tab value="campaigns">Кампании</v-tab>
                <v-tab value="review">
                    На проверке
                    <v-badge
                        :content="reviewBadge"
                        :model-value="reviewBadge > 0"
                        color="warning"
                        inline
                        class="ml-2"
                        data-testid="ai-sales-review-badge"
                    />
                </v-tab>
                <v-tab value="candidates">Кандидаты</v-tab>
                <v-tab value="units">Units</v-tab>
                <v-tab value="scores">Scores</v-tab>
                <v-tab value="drafts">Черновики</v-tab>
                <v-tab value="audit">Аудит</v-tab>
            </v-tabs>

            <v-divider />

            <v-tabs-window v-model="tab">
                <v-tabs-window-item value="campaigns">
                    <FindBuyersDashboard />
                    <ClientAcquisitionCampaignDashboard :initial-campaign-id="requestedCampaignId" />
                </v-tabs-window-item>

                <v-tabs-window-item value="review">
                    <div id="candidate-review" class="pa-3 pa-md-4 ai-sales-stack">
                        <v-alert type="info" variant="tonal" density="compact">
                            Candidate остаётся transient. Resolve/create/reject выполняются только через protected services;
                            Entity shortcut отсутствует.
                        </v-alert>

                        <CandidateReviewCard
                            v-for="candidate in candidatesForReview"
                            :key="candidate.id"
                            :candidate="candidate"
                            :highlighted="candidate.id === requestedCandidateId"
                            @updated="load"
                        />

                        <CandidateIngestionReviewPanel
                            v-for="item in actionableResearchItems"
                            :key="`${item.campaign_id}-${item.search_job_public_id}-${item.category}`"
                            :item="item"
                            :review="reviewFor(item)"
                            :campaign-name="campaignName(item)"
                            @updated="load"
                        />

                        <v-alert v-if="!candidatesForReview.length && !actionableResearchItems.length" type="info" variant="tonal">
                            Candidate review items отсутствуют.
                        </v-alert>

                        <v-card v-if="otherReviewItems.length" variant="tonal">
                            <v-card-title>Campaign Review Queue</v-card-title>
                            <v-list density="compact">
                                <v-list-item
                                    v-for="item in otherReviewItems"
                                    :key="`${item.campaign_id}-${item.source_type}-${item.source_id}-${item.category}`"
                                    :title="item.category"
                                    :subtitle="`${item.reason_code} · ${item.next_permitted_action}`"
                                />
                            </v-list>
                        </v-card>
                    </div>
                </v-tabs-window-item>

                <v-tabs-window-item value="candidates">
                    <div class="pa-3 pa-md-4 ai-sales-stack">
                        <v-btn-toggle v-model="candidateQualityFilter" mandatory density="compact" divided>
                            <v-btn value="all">Все</v-btn>
                            <v-btn value="potential_buyer">Потенциальные покупатели</v-btn>
                            <v-btn value="possible_buyer">Возможные покупатели</v-btn>
                            <v-btn value="needs_review">Нужна проверка</v-btn>
                        </v-btn-toggle>
                        <CandidateReviewCard
                            v-for="candidate in filteredCandidates"
                            :key="candidate.id"
                            :candidate="candidate"
                            :highlighted="candidate.id === requestedCandidateId"
                            @updated="load"
                        />
                        <v-alert v-if="!filteredCandidates.length" type="info" variant="tonal">
                            Candidates отсутствуют.
                        </v-alert>
                    </div>
                </v-tabs-window-item>

                <v-tabs-window-item value="units">
                    <v-list class="pa-3">
                        <v-list-item
                            v-for="candidate in resolvedCandidates"
                            :key="candidate.id"
                            :title="candidate.resolved_unit.name"
                            :subtitle="`Candidate ${candidate.status}`"
                            :href="`/Ameise/unit/${Number(candidate.resolved_unit.id)}?ai_sales=1#prospecting-dossier`"
                            prepend-icon="mdi-domain"
                        />
                        <v-list-item v-if="!resolvedCandidates.length" title="Reviewed Units пока отсутствуют" />
                    </v-list>
                </v-tabs-window-item>

                <v-tabs-window-item value="scores">
                    <v-alert type="info" variant="tonal" class="ma-4">
                        Score history открывается из reviewed Unit dossier. Computed, effective, confidence и eligibility остаются раздельными projections.
                    </v-alert>
                </v-tabs-window-item>

                <v-tabs-window-item value="drafts">
                    <v-alert type="info" variant="tonal" class="ma-4">
                        Outreach drafts остаются выключены; эта вкладка не создаёт draft и не отправляет сообщения.
                    </v-alert>
                </v-tabs-window-item>

                <v-tabs-window-item value="audit">
                    <v-alert type="info" variant="tonal" class="ma-4">
                        Audit использует существующие append-only Campaign, Candidate и Unit projections. Raw provider bodies не отображаются.
                    </v-alert>
                </v-tabs-window-item>
            </v-tabs-window>
        </v-card>
    </v-container>
</template>

<style scoped>
.ai-sales-page {
    min-height: calc(100dvh - 58px);
}

.ai-sales-stack {
    display: grid;
    gap: 16px;
}
</style>
