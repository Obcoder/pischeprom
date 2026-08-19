<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'

const visible = ref(false)
const loading = ref(false)
const error = ref('')
const campaigns = ref([])
const products = ref([])
const selected = ref(null)
const metrics = ref(null)
const reviewQueue = ref([])
const dialog = ref(false)

const defaultLimits = () => ({
    max_active_runs: 1, max_runs_per_day: 1, max_runs_per_month: 4,
    max_search_requests_per_run: 3, max_search_requests_per_day: 3, max_search_requests_per_month: 12,
    max_research_pages_per_run: 3, max_candidates_per_run: 10,
    max_units_per_run: 1, max_units_per_day: 1, max_units_per_month: 4,
    max_drafts_per_run: 1, max_drafts_per_day: 1, max_drafts_per_month: 4,
    max_requests_per_run: 10, max_requests_per_day: 10, max_requests_per_month: 40,
    max_tokens_per_run: 4000, max_tokens_per_day: 4000, max_tokens_per_month: 16000,
    max_cost_rub_per_run: 10, max_cost_rub_per_day: 10, max_cost_rub_per_month: 40,
})
const form = reactive({
    safe_name: '', safe_objective: '', primary_product_id: null,
    additional_product_ids: [], excluded_product_ids: [],
    automation_mode: 'manual', auto_unit_approved: false, auto_draft_approved: false,
    schedule_cadence: 'manual', schedule_timezone: 'Europe/Moscow', next_run_at: null,
    criteria: { segments: [], industries: [], categories: [], max_domains: 10, max_page_fetch_attempts: 3 },
    limits: defaultLimits(),
})

const sections = computed(() => ({
    scheduled: campaigns.value.filter(item => item.status === 'scheduled').length,
    running: campaigns.value.filter(item => item.status === 'running').length,
    review: campaigns.value.filter(item => item.status === 'review_required').length + reviewQueue.value.length,
    blocked: campaigns.value.filter(item => item.status === 'blocked').length,
    drafts: metrics.value?.drafts || 0,
}))

async function load() {
    loading.value = true
    error.value = ''
    try {
        const [campaignResponse, productResponse] = await Promise.all([
            axios.get('/api/ai-sales/campaigns'),
            axios.get('/api/ai-sales/prospecting/catalog/products?limit=50'),
        ])
        campaigns.value = campaignResponse.data.data || []
        products.value = productResponse.data.data || []
        visible.value = true
        if (selected.value) await open(campaigns.value.find(item => item.id === selected.value.id) || campaigns.value[0])
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

async function createCampaign() {
    error.value = ''
    try {
        await axios.post('/api/ai-sales/campaigns', form)
        dialog.value = false
        Object.assign(form, {
            safe_name: '', safe_objective: '', primary_product_id: null,
            additional_product_ids: [], excluded_product_ids: [], automation_mode: 'manual',
            auto_unit_approved: false, auto_draft_approved: false, schedule_cadence: 'manual',
            schedule_timezone: 'Europe/Moscow', next_run_at: null,
            criteria: { segments: [], industries: [], categories: [], max_domains: 10, max_page_fetch_attempts: 3 },
            limits: defaultLimits(),
        })
        await load()
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Campaign draft заблокирован policy.'
    }
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
            <v-btn size="small" color="primary" @click="dialog = true">Новая кампания</v-btn>
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
                            <v-chip size="small">runs {{ metrics.runs.completed }}/{{ metrics.runs.started }}</v-chip>
                            <v-chip size="small">results {{ metrics.research.results }}</v-chip>
                            <v-chip size="small">candidates {{ metrics.candidates.total }}</v-chip>
                            <v-chip size="small">Units {{ metrics.candidates.units_created }}</v-chip>
                            <v-chip size="small">matches {{ metrics.product_matches }}</v-chip>
                            <v-chip size="small">drafts {{ metrics.drafts }}</v-chip>
                            <v-chip size="small" color="error">emails {{ metrics.usage.emails_sent }}</v-chip>
                        </div>
                        <v-list density="compact" lines="two">
                            <v-list-subheader>Единая очередь human review</v-list-subheader>
                            <v-list-item v-for="item in reviewQueue" :key="`${item.source_type}-${item.source_id}-${item.category}`" :title="item.category" :subtitle="`${item.reason_code} · ${item.next_permitted_action}`" />
                            <v-list-item v-if="!reviewQueue.length" title="Открытых review items нет" />
                        </v-list>
                    </template>
                </v-col>
            </v-row>
        </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="900">
        <v-card>
            <v-card-title>Product-first campaign draft</v-card-title>
            <v-card-text>
                <v-text-field v-model="form.safe_name" label="Безопасное название" maxlength="160" />
                <v-textarea v-model="form.safe_objective" label="Безопасная цель" maxlength="512" rows="2" />
                <v-select v-model="form.primary_product_id" :items="products" item-title="name" item-value="id" label="Primary Product" />
                <v-select v-model="form.additional_product_ids" :items="products" item-title="name" item-value="id" label="Additional Products" multiple chips />
                <v-select v-model="form.excluded_product_ids" :items="products" item-title="name" item-value="id" label="Excluded Products" multiple chips />
                <v-row dense>
                    <v-col cols="12" md="4"><v-text-field v-model.number="form.criteria.country_id" type="number" min="1" label="Country ID" /></v-col>
                    <v-col cols="12" md="4"><v-text-field v-model.number="form.criteria.region_id" type="number" min="1" label="Region ID" /></v-col>
                    <v-col cols="12" md="4"><v-text-field v-model.number="form.criteria.city_id" type="number" min="1" label="City ID" /></v-col>
                </v-row>
                <v-combobox v-model="form.criteria.segments" label="Сегменты" multiple chips :hide-no-data="false" />
                <v-select v-model="form.automation_mode" :items="['manual', 'assisted', 'autonomous_reviewed']" label="Automation mode" />
                <v-select v-model="form.schedule_cadence" :items="['manual', 'daily', 'weekly', 'monthly']" label="Cadence" />
                <v-text-field v-if="form.schedule_cadence !== 'manual'" v-model="form.next_run_at" type="datetime-local" label="Следующий bounded run" />
                <div class="text-subtitle-2 mb-2">Hard caps (frozen by approval)</div>
                <v-row dense>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_runs_per_day" type="number" min="1" label="Runs/day" /></v-col>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_search_requests_per_run" type="number" min="0" label="Search/run" /></v-col>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_research_pages_per_run" type="number" min="0" label="Pages/run" /></v-col>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_candidates_per_run" type="number" min="1" label="Candidates/run" /></v-col>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_units_per_run" type="number" min="0" label="Units/run" /></v-col>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_drafts_per_run" type="number" min="0" label="Drafts/run" /></v-col>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_tokens_per_run" type="number" min="1" label="Tokens/run" /></v-col>
                    <v-col cols="6" md="3"><v-text-field v-model.number="form.limits.max_cost_rub_per_run" type="number" min="0" step="0.01" label="RUB/run" /></v-col>
                </v-row>
                <template v-if="form.automation_mode === 'autonomous_reviewed'">
                    <v-switch v-model="form.auto_unit_approved" color="warning" label="Запросить approval: autonomous_unit_creation.v1" />
                    <v-switch v-model="form.auto_draft_approved" color="warning" label="Запросить approval: autonomous_outreach_draft.v1" />
                </template>
                <v-alert type="info" density="compact" variant="tonal" class="mb-2">
                    Disclosure preview: только sales/public allowlist; secrets, unclassified, raw correspondence, opposite lane и recipient PII для внешнего AI блокируются.
                </v-alert>
                <v-alert type="warning" density="compact" variant="tonal">
                    Auto Unit и auto draft требуют отдельного admin permission и human approval. Создание Entity, consent и dispatch запрещены.
                </v-alert>
            </v-card-text>
            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="dialog = false">Отмена</v-btn>
                <v-btn color="primary" @click="createCampaign">Сохранить draft</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
