<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import CampaignDraftForm from '@/Components/AiSales/CampaignDraftForm.vue'

const props = defineProps({
    initialCampaignId: {
        type: String,
        default: null,
    },
})

const visible = ref(false)
const loading = ref(false)
const error = ref('')
const campaigns = ref([])
const campaignLimitProfile = ref({})
const selected = ref(null)
const metrics = ref(null)
const reviewQueue = ref([])
const dialog = ref(false)
const editingCampaign = ref(null)
const funnelFilter = ref('all')
const reviewActionLoading = ref('')
const queryPlanRebuildLoading = ref('')
const queryPlanEditors = ref({})

const sections = computed(() => ({
    scheduled: campaigns.value.filter(item => item.status === 'scheduled').length,
    running: campaigns.value.filter(item => item.status === 'running').length,
    review: campaigns.value.filter(item => item.status === 'review_required').length + reviewQueue.value.length,
    blocked: campaigns.value.filter(item => item.status === 'blocked').length,
    drafts: metrics.value?.drafts || 0,
}))
const funnel = computed(() => {
    const classifications = metrics.value?.research?.classifications || {}
    return [
        { code: 'potential_buyer', label: 'Потенциальные покупатели', count: classifications.potential_buyer || 0 },
        { code: 'possible_buyer', label: 'Возможные покупатели', count: classifications.possible_buyer || 0 },
        { code: 'supplier_or_competitor', label: 'Отклонённые поставщики', count: classifications.supplier_or_competitor || 0 },
        { code: 'marketplace_directory', label: 'Маркетплейсы/справочники', count: (classifications.marketplace || 0) + (classifications.directory || 0) },
        { code: 'unknown', label: 'Нужна проверка', count: classifications.unknown || 0 },
    ]
})
const filteredDomains = computed(() => {
    const rows = metrics.value?.research?.domain_breakdown || []
    if (funnelFilter.value === 'all') return rows
    if (funnelFilter.value === 'marketplace_directory') {
        return rows.filter(item => ['marketplace', 'directory'].includes(item.classification))
    }

    return rows.filter(item => item.classification === funnelFilter.value)
})

async function load() {
    loading.value = true
    error.value = ''
    try {
        const campaignResponse = await axios.get('/api/ai-sales/campaigns')
        campaigns.value = campaignResponse.data.data || []
        campaignLimitProfile.value = campaignResponse.data.meta?.draft_limits || {}
        visible.value = true
        const requested = props.initialCampaignId
            ? campaigns.value.find(item => item.id === props.initialCampaignId)
            : null
        if (requested || selected.value) {
            await open(requested || campaigns.value.find(item => item.id === selected.value.id) || campaigns.value[0])
        }
    } catch (requestError) {
        if ([401, 403, 404].includes(requestError?.response?.status)) visible.value = false
        else {
            visible.value = true
            error.value = requestError?.response?.data?.message || 'Не удалось загрузить Campaign dashboard.'
        }
    } finally {
        loading.value = false
    }
}

function newCampaign() {
    editingCampaign.value = null
    dialog.value = true
}

function editCampaign(campaign) {
    editingCampaign.value = campaign
    dialog.value = true
}

async function action(campaign, actionName) {
    error.value = ''
    try {
        const payload = actionName === 'manual-run' ? { idempotency_token: crypto.randomUUID() } : {}
        await axios.post(`/api/ai-sales/campaigns/${campaign.id}/${actionName}`, payload)
        await load()
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Действие заблокировано campaign policy.'
    }
}

async function open(campaign) {
    if (!campaign) return
    selected.value = campaign
    const [metricsResponse, queueResponse] = await Promise.all([
        axios.get(`/api/ai-sales/campaigns/${campaign.id}/progress`),
        axios.get(`/api/ai-sales/campaigns/${campaign.id}/review-queue?limit=100`),
    ])
    metrics.value = metricsResponse.data.data
    reviewQueue.value = queueResponse.data.data || []
    initializeQueryPlanEditors()
}

async function approveQueryPlan(item) {
    const jobId = item?.search_job_public_id
    const queryCount = Number(item?.safe_evidence?.query_count || 0)
    if (!jobId || queryCount < 1) return
    if (!window.confirm(`Одобрить server-owned план из ${queryCount} запросов и продолжить текущий Campaign run?`)) return
    reviewActionLoading.value = jobId
    error.value = ''
    try {
        await axios.post(`/api/ai-sales/prospecting/jobs/${encodeURIComponent(jobId)}/search-plan/approve`, {})
        await load()
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Не удалось одобрить query plan.'
    } finally {
        reviewActionLoading.value = ''
    }
}

function initializeQueryPlanEditors() {
    const editors = {}
    reviewQueue.value.filter(item => item.category === 'query_plan_review').forEach(item => {
        const evidence = item.safe_evidence || {}
        const preferences = evidence.preferences || {}
        editors[item.search_job_public_id] = {
            target_query_count: Number(preferences.target_query_count || evidence.query_count || 1),
            buyer_archetypes: [...(preferences.buyer_archetypes || [])],
            intents: [...(preferences.intents || [])],
        }
    })
    queryPlanEditors.value = editors
}

function planEditor(item) {
    return queryPlanEditors.value[item.search_job_public_id]
}

function normalizedPlanSelection(value) {
    return [...(value || [])].map(String).sort()
}

function planEditorIsDirty(item) {
    const editor = planEditor(item)
    const preferences = item.safe_evidence?.preferences || {}
    if (!editor) return false

    return Number(editor.target_query_count) !== Number(preferences.target_query_count || item.safe_evidence?.query_count || 1)
        || JSON.stringify(normalizedPlanSelection(editor.buyer_archetypes)) !== JSON.stringify(normalizedPlanSelection(preferences.buyer_archetypes))
        || JSON.stringify(normalizedPlanSelection(editor.intents)) !== JSON.stringify(normalizedPlanSelection(preferences.intents))
}

function planCombinationCount(item) {
    const editor = planEditor(item)
    if (!editor) return 0

    return Number(item.safe_evidence?.product_count || 1)
        * editor.buyer_archetypes.length
        * editor.intents.length
}

async function rebuildQueryPlan(item) {
    const jobId = item?.search_job_public_id
    const editor = planEditor(item)
    if (!jobId || !editor) return
    queryPlanRebuildLoading.value = jobId
    error.value = ''
    try {
        await axios.post(`/api/ai-sales/prospecting/jobs/${encodeURIComponent(jobId)}/search-plan/rebuild`, {
            target_query_count: Number(editor.target_query_count),
            buyer_archetypes: editor.buyer_archetypes,
            intents: editor.intents,
        })
        await load()
    } catch (requestError) {
        const errors = requestError?.response?.data?.errors
        error.value = errors
            ? Object.values(errors).flat().join(' ')
            : requestError?.response?.data?.message || 'Не удалось безопасно пересобрать query plan.'
    } finally {
        queryPlanRebuildLoading.value = ''
    }
}

onMounted(load)
</script>

<template>
    <v-card v-if="visible" variant="outlined" color="indigo" class="ma-3">
        <v-card-title class="d-flex align-center ga-2 flex-wrap">
            <v-icon>mdi-target-account</v-icon>
            <span>Кампании привлечения клиентов</span>
            <v-chip size="small" color="warning" variant="flat">Stage 14 · default-off</v-chip>
            <v-chip size="small" color="error" variant="outlined">dispatch/email off</v-chip>
            <v-spacer />
            <v-btn size="small" variant="text" :loading="loading" @click="load">Обновить</v-btn>
            <v-btn size="small" color="primary" @click="newCampaign">Новая кампания</v-btn>
        </v-card-title>
        <v-card-text>
            <v-alert v-if="error" type="warning" variant="tonal" class="mb-3">{{ error }}</v-alert>
            <v-alert type="info" density="compact" variant="tonal" class="mb-3">
                Workflow, Yandex profile, research stages и порядок действий принадлежат серверу. Production scheduler, live providers, Entity creation, consent и отправка писем недоступны.
            </v-alert>
            <div class="d-flex ga-2 flex-wrap mb-3">
                <v-chip v-for="(count, name) in sections" :key="name" size="small" variant="outlined">{{ name }}: {{ count }}</v-chip>
            </div>
            <v-row dense>
                <v-col cols="12" md="5">
                    <v-list density="compact" lines="three">
                        <v-list-item v-for="campaign in campaigns" :key="campaign.id" @click="open(campaign)">
                            <v-list-item-title>{{ campaign.safe_name }}</v-list-item-title>
                            <v-list-item-subtitle>
                                {{ campaign.products.find(item => item.role === 'primary')?.name }} · {{ campaign.status }} · {{ campaign.automation_mode }}
                            </v-list-item-subtitle>
                            <div class="d-flex ga-1 flex-wrap mt-1">
                                <v-btn v-if="campaign.status === 'draft'" size="x-small" variant="text" @click.stop="action(campaign, 'submit')">На проверку</v-btn>
                                <v-btn v-if="['draft', 'review_required', 'approved'].includes(campaign.status)" size="x-small" variant="text" @click.stop="editCampaign(campaign)">Редактировать</v-btn>
                                <v-btn v-if="campaign.status === 'review_required'" size="x-small" color="success" variant="text" @click.stop="action(campaign, 'approve')">Одобрить</v-btn>
                                <v-btn v-if="['approved', 'scheduled'].includes(campaign.status)" size="x-small" color="primary" variant="text" @click.stop="action(campaign, 'manual-run')">Bounded run</v-btn>
                                <v-btn v-if="['scheduled', 'running'].includes(campaign.status)" size="x-small" variant="text" @click.stop="action(campaign, 'pause')">Пауза</v-btn>
                                <v-btn v-if="campaign.status === 'paused'" size="x-small" variant="text" @click.stop="action(campaign, 'resume')">Продолжить</v-btn>
                                <v-btn v-if="!['cancelled', 'archived'].includes(campaign.status)" size="x-small" color="error" variant="text" @click.stop="action(campaign, 'cancel')">Отменить</v-btn>
                            </div>
                        </v-list-item>
                        <v-list-item v-if="!campaigns.length" title="Кампаний пока нет" />
                    </v-list>
                </v-col>
                <v-col cols="12" md="7">
                    <template v-if="selected">
                        <div class="text-subtitle-1 mb-2">{{ selected.safe_name }} — прогресс и аудит</div>
                        <div v-if="metrics" class="d-flex ga-2 flex-wrap mb-3">
                            <v-chip size="small">Запросы {{ metrics.queries.planned }}</v-chip>
                            <v-chip size="small">Результаты {{ metrics.research.results }}</v-chip>
                            <v-chip size="small">Уникальные домены {{ metrics.research.unique_domains }}</v-chip>
                            <v-chip size="small">Buyer-like {{ metrics.research.buyer_like_domains || 0 }}</v-chip>
                            <v-chip size="small">Исследованные компании {{ metrics.research.researched_companies || 0 }}</v-chip>
                            <v-chip size="small">Candidates {{ metrics.candidates.total }}</v-chip>
                            <v-chip size="small">Units {{ metrics.candidates.units_created }}</v-chip>
                            <v-chip size="small" color="warning">Поставщики отклонены {{ metrics.research.rejected_supplier || 0 }}</v-chip>
                            <v-chip size="small" color="warning">Маркетплейсы {{ metrics.research.rejected_marketplace || 0 }}</v-chip>
                            <v-chip size="small" color="error">Писем {{ metrics.usage.emails_sent }}</v-chip>
                        </div>
                        <v-btn-toggle v-if="metrics" v-model="funnelFilter" mandatory density="compact" divided class="mb-3">
                            <v-btn value="all">Все домены</v-btn>
                            <v-btn v-for="item in funnel" :key="item.code" :value="item.code">{{ item.label }} · {{ item.count }}</v-btn>
                        </v-btn-toggle>
                        <v-list v-if="metrics" density="compact" lines="two" class="mb-3">
                            <v-list-subheader>Домены в выбранной части funnel</v-list-subheader>
                            <v-list-item
                                v-for="domain in filteredDomains"
                                :key="domain.domain"
                                :subtitle="`${domain.classification} · результатов ${domain.result_count} · research ${domain.research_completed}`"
                            >
                                <template #title>
                                    <a
                                        v-if="domain.source_url"
                                        :href="domain.source_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary text-decoration-none font-weight-medium"
                                        :aria-label="`Открыть публичный сайт ${domain.domain}`"
                                        @click.stop
                                    >
                                        {{ domain.domain }}
                                        <v-icon icon="mdi-open-in-new" size="x-small" class="ml-1" />
                                    </a>
                                    <span v-else>{{ domain.domain }}</span>
                                </template>
                                <template #append>
                                    <v-chip size="x-small" variant="tonal">{{ domain.confidence }}%</v-chip>
                                </template>
                            </v-list-item>
                            <v-list-item v-if="!filteredDomains.length" title="В этой категории доменов нет" />
                        </v-list>
                        <v-list density="compact" lines="two">
                            <v-list-subheader>Единая очередь human review</v-list-subheader>
                            <v-list-item
                                v-for="item in reviewQueue"
                                :key="`${item.source_type}-${item.source_id}-${item.category}`"
                                :title="item.category === 'query_plan_review' ? `План поисковых запросов · ${item.safe_evidence?.query_count || 0}` : item.category"
                                :subtitle="item.category === 'query_plan_review' ? 'Проверьте server-owned запросы перед обращением к Yandex.' : `${item.reason_code} · ${item.next_permitted_action}`"
                            >
                                <div v-if="item.category === 'query_plan_review'" class="mt-2">
                                    <v-alert
                                        v-if="Number(item.safe_evidence?.query_count || 0) < Number(item.safe_evidence?.max_queries || 0)"
                                        type="info"
                                        density="compact"
                                        variant="tonal"
                                        class="mb-2"
                                    >
                                        Сейчас сформировано {{ item.safe_evidence?.query_count || 0 }} из доступных {{ item.safe_evidence?.max_queries || 0 }} запросов.
                                    </v-alert>
                                    <v-list density="compact" class="border rounded mb-2">
                                        <v-list-item
                                            v-for="(queryItem, index) in item.safe_evidence?.queries || []"
                                            :key="`${item.source_id}-${index}`"
                                            :title="queryItem.query"
                                            :subtitle="queryItem.template"
                                        />
                                    </v-list>
                                    <v-expansion-panels variant="accordion" class="mb-2">
                                        <v-expansion-panel>
                                            <v-expansion-panel-title>Настроить и пересобрать запросы</v-expansion-panel-title>
                                            <v-expansion-panel-text v-if="planEditor(item)">
                                                <v-alert type="info" density="compact" variant="tonal" class="mb-3">
                                                    Вы выбираете отрасли покупателей и цели поиска. Фразы сформирует сервер; Yandex при пересборке не запускается.
                                                </v-alert>
                                                <v-row dense>
                                                    <v-col cols="12" md="4">
                                                        <v-text-field
                                                            v-model.number="planEditor(item).target_query_count"
                                                            type="number"
                                                            min="1"
                                                            :max="item.safe_evidence?.max_queries || 1"
                                                            label="Количество запросов"
                                                            :hint="`Лимит этого run: ${item.safe_evidence?.max_queries || 1}`"
                                                            persistent-hint
                                                        />
                                                    </v-col>
                                                    <v-col cols="12" md="8">
                                                        <v-select
                                                            v-model="planEditor(item).buyer_archetypes"
                                                            :items="item.safe_evidence?.available_archetypes || []"
                                                            item-title="label"
                                                            item-value="code"
                                                            label="Типы потенциальных покупателей"
                                                            multiple
                                                            chips
                                                            closable-chips
                                                        />
                                                    </v-col>
                                                    <v-col cols="12">
                                                        <v-select
                                                            v-model="planEditor(item).intents"
                                                            :items="item.safe_evidence?.available_intents || []"
                                                            item-title="label"
                                                            item-value="code"
                                                            label="Цели поиска"
                                                            multiple
                                                            chips
                                                            closable-chips
                                                        />
                                                    </v-col>
                                                </v-row>
                                                <div class="text-caption text-medium-emphasis mb-2">
                                                    Выбранная матрица даёт до {{ planCombinationCount(item) }} различных комбинаций.
                                                </div>
                                                <v-btn
                                                    size="small"
                                                    color="primary"
                                                    variant="tonal"
                                                    :loading="queryPlanRebuildLoading === item.search_job_public_id"
                                                    :disabled="!planEditorIsDirty(item) || !planEditor(item).buyer_archetypes.length || !planEditor(item).intents.length || Number(planEditor(item).target_query_count) < 1 || Number(planEditor(item).target_query_count) > Number(item.safe_evidence?.max_queries || 0) || Number(planEditor(item).target_query_count) > planCombinationCount(item)"
                                                    @click="rebuildQueryPlan(item)"
                                                >Пересобрать план без запуска Yandex</v-btn>
                                            </v-expansion-panel-text>
                                        </v-expansion-panel>
                                    </v-expansion-panels>
                                    <div class="d-flex align-center ga-2 flex-wrap">
                                        <v-btn
                                            size="small"
                                            color="success"
                                            variant="tonal"
                                            :loading="reviewActionLoading === item.search_job_public_id"
                                            :disabled="!item.search_job_public_id || planEditorIsDirty(item)"
                                            @click="approveQueryPlan(item)"
                                        >Одобрить план и продолжить</v-btn>
                                        <span v-if="planEditorIsDirty(item)" class="text-caption text-warning">Сначала пересоберите изменённый план.</span>
                                    </div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!reviewQueue.length" title="Открытых review items нет" />
                        </v-list>
                    </template>
                </v-col>
            </v-row>
        </v-card-text>
    </v-card>

    <CampaignDraftForm v-model="dialog" :campaign="editingCampaign" :limit-profile="campaignLimitProfile" @saved="load" />
</template>
