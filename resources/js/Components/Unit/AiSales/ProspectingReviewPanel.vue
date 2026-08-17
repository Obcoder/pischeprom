<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import FindBuyersDashboard from '@/Components/AiSales/FindBuyersDashboard.vue'

const tab = ref('jobs')
const loading = ref(false)
const error = ref('')
const jobs = ref([])
const candidates = ref([])
const products = ref([])
const goods = ref([])
const searchByJob = reactive({})
const searchBusy = ref('')
const searchError = ref('')
const dialog = ref(false)
const form = reactive({
    purpose: 'buyer_discovery',
    safe_objective: '',
    primary_product_id: null,
    originating_good_ids: [],
    criteria: { segments: [] },
})

const reviewCandidates = computed(() => candidates.value.filter((item) => [
    'exact_existing_unit', 'probable_existing_review', 'new_unit_review',
].includes(item.status)))
const exceptions = computed(() => candidates.value.filter((item) => [
    'rejected', 'expired', 'anonymized',
].includes(item.status)))
const resolvedUnits = computed(() => candidates.value.filter((item) => item.resolved_unit))
const discoveryJobs = computed(() => jobs.value.filter((item) => item.status === 'approved'))

async function load() {
    loading.value = true
    error.value = ''
    try {
        const [jobResponse, candidateResponse, productResponse] = await Promise.all([
            axios.get('/api/ai-sales/prospecting/jobs?per_page=50'),
            axios.get('/api/ai-sales/prospecting/candidates?per_page=50'),
            axios.get('/api/ai-sales/prospecting/catalog/products?limit=50'),
        ])
        jobs.value = jobResponse.data.data || []
        candidates.value = candidateResponse.data.data || []
        products.value = productResponse.data.data || []
    } catch (requestError) {
        error.value = requestError.response?.status === 404
            ? 'Stage 08 выключен feature flag.'
            : (requestError.response?.data?.message || 'Не удалось загрузить безопасный review queue.')
    } finally {
        loading.value = false
    }
}

async function createJob() {
    await axios.post('/api/ai-sales/prospecting/jobs', form)
    dialog.value = false
    form.safe_objective = ''
    form.primary_product_id = null
    form.originating_good_ids = []
    await load()
}

watch(() => form.primary_product_id, async (productId) => {
    form.originating_good_ids = []
    goods.value = []
    if (!productId) return
    const response = await axios.get(`/api/ai-sales/prospecting/catalog/products/${productId}/goods?limit=50`)
    goods.value = response.data.data || []
})

async function jobAction(job, action) {
    await axios.post(`/api/ai-sales/prospecting/jobs/${job.id}/${action}`, {})
    await load()
}

async function evaluate(candidate) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/evaluate`, {})
    await load()
}

async function resolveExisting(candidate, unitId) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/resolve-existing`, { unit_id: unitId })
    await load()
}

async function createUnit(candidate) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/create-unit`, {})
    await load()
}

async function reject(candidate) {
    await axios.post(`/api/ai-sales/prospecting/candidates/${candidate.id}/reject`, { reason_code: 'irrelevant' })
    await load()
}

async function loadSearch(job) {
    searchBusy.value = `load:${job.id}`
    searchError.value = ''
    try {
        const { data } = await axios.get(`/api/ai-sales/prospecting/jobs/${job.id}/search`)
        searchByJob[job.id] = data.data
    } catch (requestError) {
        searchError.value = requestError.response?.data?.message || 'Не удалось загрузить discovery evidence.'
    } finally {
        searchBusy.value = ''
    }
}

async function searchAction(job, action) {
    searchBusy.value = `${action}:${job.id}`
    searchError.value = ''
    try {
        await axios.post(`/api/ai-sales/prospecting/jobs/${job.id}/${action}`, {})
        await loadSearch(job)
        await load()
    } catch (requestError) {
        searchError.value = requestError.response?.data?.message || 'Search action заблокирован policy.'
    } finally {
        searchBusy.value = ''
    }
}

async function resultAction(job, result, action) {
    searchBusy.value = `${action}:${result.id}`
    searchError.value = ''
    try {
        await axios.post(`/api/ai-sales/prospecting/search-results/${result.id}/${action}`, {})
        await loadSearch(job)
        if (action === 'ingest-candidate') await load()
    } catch (requestError) {
        searchError.value = requestError.response?.data?.message || 'Evidence action заблокирован policy.'
    } finally {
        searchBusy.value = ''
    }
}

onMounted(load)
</script>

<template>
    <div id="prospecting-review">
    <FindBuyersDashboard />
    <v-card variant="outlined" class="prospecting-review">
        <v-card-title class="d-flex align-center ga-3">
            <span>AI-поиск покупателей</span>
            <v-chip size="small" color="amber">Stage 09 · Product-first discovery</v-chip>
            <v-spacer />
            <v-btn size="small" variant="tonal" :disabled="loading" @click="load">Обновить</v-btn>
            <v-btn size="small" color="primary" @click="dialog = true">Новое задание</v-btn>
        </v-card-title>
        <v-alert v-if="error" type="info" variant="tonal" class="ma-3">{{ error }}</v-alert>
        <v-alert type="warning" variant="tonal" density="compact" class="mx-3">
            Live discovery остаётся default-off. Запросы, provider/profile и URL задаются только сервером; auto Unit/Entity и email отсутствуют. Stage 10 scoring показывается только как read-only projection.
        </v-alert>
        <v-tabs v-model="tab" class="mt-2">
            <v-tab value="jobs">Задания</v-tab>
            <v-tab value="discovery">Поиск и evidence</v-tab>
            <v-tab value="candidates">Кандидаты</v-tab>
            <v-tab value="units">Units</v-tab>
            <v-tab value="review">На проверке</v-tab>
            <v-tab value="exceptions">Исключения</v-tab>
        </v-tabs>
        <v-tabs-window v-model="tab">
            <v-tabs-window-item value="jobs">
                <v-list lines="two">
                    <v-list-item v-for="job in jobs" :key="job.id" :title="job.safe_objective" :subtitle="`${job.purpose} · ${job.lane} · ${job.status}`">
                        <div class="text-caption">Products: {{ job.products?.filter(item => item.role !== 'exclude').map(item => item.name).join(', ') || 'не выбраны' }}</div>
                        <div v-if="job.originating_goods?.length" class="text-caption text-medium-emphasis">Optional Goods: {{ job.originating_goods.map(item => item.name).join(', ') }}</div>
                        <v-alert v-if="!['mapped', 'not_applicable'].includes(job.product_mapping_state)" density="compact" type="warning" variant="tonal">Требуется проверка Good→Product: {{ job.product_mapping_reason_code }}</v-alert>
                        <template #append>
                            <v-btn v-if="job.status === 'draft'" size="small" variant="text" @click="jobAction(job, 'submit')">На проверку</v-btn>
                            <v-btn v-if="job.status === 'review_required'" size="small" variant="text" color="success" @click="jobAction(job, 'approve')">Одобрить</v-btn>
                            <v-btn v-if="['draft', 'review_required', 'approved'].includes(job.status)" size="small" variant="text" color="error" @click="jobAction(job, 'cancel')">Отменить</v-btn>
                            <v-btn v-if="['approved', 'cancelled'].includes(job.status)" size="small" variant="text" @click="jobAction(job, 'archive')">В архив</v-btn>
                        </template>
                    </v-list-item>
                    <v-list-item v-if="!jobs.length" title="Заданий нет" />
                </v-list>
            </v-tabs-window-item>
            <v-tabs-window-item value="discovery">
                <v-alert v-if="searchError" type="warning" variant="tonal" class="ma-3">{{ searchError }}</v-alert>
                <v-card v-for="job in discoveryJobs" :key="job.id" variant="outlined" class="ma-3">
                    <v-card-title class="d-flex align-center ga-2 flex-wrap">
                        <span>{{ job.safe_objective }}</span>
                        <v-chip size="x-small">{{ job.purpose }}</v-chip>
                        <v-spacer />
                        <v-btn size="small" variant="text" :loading="searchBusy === `load:${job.id}`" @click="loadSearch(job)">Evidence</v-btn>
                        <v-btn
                            size="small"
                            variant="tonal"
                            :disabled="!job.search_discovery?.query_planning_enabled"
                            :loading="searchBusy === `search-plan:${job.id}`"
                            @click="searchAction(job, 'search-plan')"
                        >Сформировать план</v-btn>
                        <v-btn
                            size="small"
                            color="success"
                            variant="tonal"
                            :disabled="!searchByJob[job.id]?.queries?.some(item => item.plan_status === 'review_required')"
                            :loading="searchBusy === `search-plan/approve:${job.id}`"
                            @click="searchAction(job, 'search-plan/approve')"
                        >Одобрить план</v-btn>
                        <v-btn
                            v-if="!job.find_buyers?.wizard_version"
                            size="small"
                            color="primary"
                            :disabled="!job.execution_available || !searchByJob[job.id]?.queries?.some(item => item.plan_status === 'approved')"
                            :loading="searchBusy === `search-execute:${job.id}`"
                            @click="searchAction(job, 'search-execute')"
                        >Выполнить</v-btn>
                    </v-card-title>
                    <v-card-text v-if="searchByJob[job.id]">
                        <v-alert density="compact" variant="tonal" type="info" class="mb-3">
                            Provider: existing_yandex · profile: prospecting_b2b_discovery · retries/failovers: 0/0 · auto-ingestion: off
                        </v-alert>
                        <div class="text-subtitle-2 mb-1">Code-owned query plan</div>
                        <v-list density="compact" class="mb-3">
                            <v-list-item
                                v-for="queryItem in searchByJob[job.id].queries"
                                :key="queryItem.id"
                                :title="queryItem.query"
                                :subtitle="`${queryItem.template_code} · ${queryItem.plan_status} · results: ${queryItem.result_count}`"
                            />
                        </v-list>
                        <div class="text-caption mb-3">
                            Budget: {{ searchByJob[job.id].budgets.max_search_requests_per_job }} requests/job,
                            {{ searchByJob[job.id].budgets.max_results_per_query }} results/query.
                        </div>
                        <div class="text-subtitle-2 mb-1">Domains, evidence и research</div>
                        <v-card v-for="result in searchByJob[job.id].results" :key="result.id" variant="flat" class="border mb-2 pa-2">
                            <div class="d-flex align-center ga-2 flex-wrap">
                                <a :href="result.url" target="_blank" rel="noopener noreferrer">{{ result.title || result.domain }}</a>
                                <v-chip size="x-small">{{ result.domain }}</v-chip>
                                <v-chip v-if="result.duplicate" size="x-small" color="grey">duplicate</v-chip>
                                <v-chip size="x-small" color="orange">untrusted · authority none</v-chip>
                                <v-spacer />
                                <v-btn
                                    size="x-small"
                                    variant="text"
                                    :disabled="result.duplicate || !job.search_discovery?.page_fetch_enabled || !!result.fetch"
                                    @click="resultAction(job, result, 'fetch')"
                                >Fetch</v-btn>
                                <v-btn
                                    size="x-small"
                                    variant="text"
                                    :disabled="result.fetch?.status !== 'completed' || !job.search_discovery?.public_research_enabled || !!result.research"
                                    @click="resultAction(job, result, 'research')"
                                >Fake research</v-btn>
                                <v-btn
                                    size="x-small"
                                    color="success"
                                    variant="text"
                                    :disabled="result.fetch?.status !== 'completed' || !!result.candidate_id"
                                    @click="resultAction(job, result, 'ingest-candidate')"
                                >В Candidate review</v-btn>
                            </div>
                            <div class="text-caption">{{ result.snippet }}</div>
                            <div v-if="result.fetch" class="text-caption">Fetch: {{ result.fetch.status }} · robots: {{ result.fetch.robots_status || '—' }} · contacts: {{ result.fetch.channel_count }}</div>
                            <div v-if="result.research" class="text-caption">Research: {{ result.research.status }} · {{ result.research.summary || result.research.error_code }}</div>
                            <v-alert v-if="result.fetch?.error_code || result.research?.error_code" density="compact" type="warning" variant="tonal" class="mt-1">
                                {{ result.fetch?.error_code || result.research?.error_code }}
                            </v-alert>
                        </v-card>
                        <div v-if="!searchByJob[job.id].results.length" class="text-medium-emphasis">Результатов пока нет.</div>
                    </v-card-text>
                </v-card>
                <v-list-item v-if="!discoveryJobs.length" title="Нет одобренных Product-first заданий" />
            </v-tabs-window-item>
            <v-tabs-window-item value="candidates">
                <v-list lines="three">
                    <v-list-item v-for="candidate in candidates" :key="candidate.id" :title="candidate.working_name" :subtitle="`${candidate.domain || 'домен неизвестен'} · ${candidate.location || 'география неизвестна'} · ${candidate.status}`">
                        <template #append><v-btn size="small" variant="text" @click="evaluate(candidate)">Проверить</v-btn></template>
                    </v-list-item>
                </v-list>
            </v-tabs-window-item>
            <v-tabs-window-item value="units">
                <v-list><v-list-item v-for="candidate in resolvedUnits" :key="candidate.id" :title="candidate.resolved_unit.name" :subtitle="`Unit #${candidate.resolved_unit.id}; Candidate ${candidate.id}`" /></v-list>
            </v-tabs-window-item>
            <v-tabs-window-item value="review">
                <v-card v-for="candidate in reviewCandidates" :key="candidate.id" variant="flat" class="ma-3 pa-2 border">
                    <v-card-title>{{ candidate.working_name }}</v-card-title>
                    <v-card-subtitle>{{ candidate.relevance_summary || 'Обоснование не задано' }}</v-card-subtitle>
                    <v-card-text>
                        <div class="font-weight-medium mb-1">Product scope</div>
                        <div v-for="product in candidate.products" :key="product.id" class="mb-1">
                            {{ product.name }} · {{ product.status }} · {{ product.safe_rationale }}
                        </div>
                        <div v-if="candidate.originating_goods?.length" class="mt-2">
                            Optional Good offers: {{ candidate.originating_goods.map(item => item.name).join(', ') }}
                        </div>
                        <v-alert v-if="!['mapped', 'not_applicable'].includes(candidate.product_mapping_state)" density="compact" type="warning" variant="tonal">Product mapping требует review; автоматическая догадка запрещена.</v-alert>
                        <div v-for="source in candidate.sources" :key="source.evidence_hash">{{ source.title || source.domain || source.reference }}</div>
                        <div v-for="match in candidate.unit_matches" :key="`${match.unit.id}-${match.signal_code}`">
                            {{ match.unit.name || `Unit #${match.unit.id}` }} · {{ match.signal_code }}
                            <v-btn size="x-small" variant="text" @click="resolveExisting(candidate, match.unit.id)">Связать с Unit</v-btn>
                        </div>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn v-if="candidate.status === 'new_unit_review'" color="success" variant="tonal" @click="createUnit(candidate)">Создать рабочее Unit</v-btn>
                        <v-btn color="error" variant="text" @click="reject(candidate)">Отклонить</v-btn>
                    </v-card-actions>
                </v-card>
            </v-tabs-window-item>
            <v-tabs-window-item value="exceptions">
                <v-list><v-list-item v-for="candidate in exceptions" :key="candidate.id" :title="candidate.working_name" :subtitle="candidate.resolution_reason_code || candidate.status" /></v-list>
            </v-tabs-window-item>
        </v-tabs-window>

        <v-dialog v-model="dialog" max-width="680">
            <v-card>
                <v-card-title>Черновик prospecting job</v-card-title>
                <v-card-text>
                    <v-select v-model="form.purpose" label="Назначение" :items="[
                        { title: 'Покупатели', value: 'buyer_discovery' },
                        { title: 'Поставщики', value: 'supplier_discovery' },
                    ]" />
                    <v-textarea v-model="form.safe_objective" label="Безопасная цель" maxlength="512" counter />
                    <v-autocomplete
                        v-model="form.primary_product_id"
                        label="Основной Product"
                        :items="products"
                        item-title="name"
                        item-value="id"
                        clearable
                    />
                    <v-select
                        v-model="form.originating_good_ids"
                        label="Конкретные Goods для будущего предложения (необязательно)"
                        :items="goods"
                        item-title="name"
                        item-value="id"
                        multiple
                        chips
                        :disabled="!form.primary_product_id"
                    />
                </v-card-text>
                <v-card-actions><v-spacer /><v-btn @click="dialog = false">Отмена</v-btn><v-btn color="primary" :disabled="!form.safe_objective || !form.primary_product_id" @click="createJob">Создать Product-first черновик</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
    </div>
</template>
