<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    launchContext: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['update:modelValue', 'context-updated'])
const step = ref(1)
const busy = ref(false)
const error = ref('')
const savedJob = ref(null)
const plannedQueries = ref([])
const progress = ref(null)
const catalogProducts = ref([])
const geographyOptions = reactive({ countries: [], regions: [], cities: [] })
const context = ref(props.launchContext)

const freshIdempotencyKey = () => (
    globalThis.crypto?.randomUUID?.()
    || '00000000-0000-4000-8000-'.concat(Date.now().toString().padStart(12, '0').slice(-12))
)

const form = reactive({
    selected_product_id: null,
    additional_product_ids: [],
    excluded_product_ids: [],
    originating_good_id: null,
    industry_ids: [],
    included_category_ids: [],
    excluded_category_ids: [],
    company_activity_codes: [],
    company_type_code: null,
    country_id: null,
    region_id: null,
    city_id: null,
    limits: {
        max_queries: 10,
        max_results_per_query: 10,
        max_domains: 5,
        max_page_fetch_attempts: 0,
        max_candidates: 25,
    },
    idempotency_key: freshIdempotencyKey(),
})

const open = computed({
    get: () => props.modelValue,
    set: value => emit('update:modelValue', value),
})
const primaryProduct = computed(() => context.value?.primary_product)
const needsProductSelection = computed(() => context.value?.eligibility?.reason_code === 'product_selection_required')
const canContinueProduct = computed(() => Boolean(primaryProduct.value?.id))
const liveDisabled = computed(() => context.value?.runtime?.live_execution_allowed === false)
const disclosure = computed(() => context.value?.disclosure_preview || {})
const productItems = computed(() => catalogProducts.value.filter(item => item.id !== primaryProduct.value?.id))

watch(() => props.launchContext, value => {
    context.value = value
    initializeFromContext()
}, { deep: true })

watch(open, async value => {
    if (!value) return
    initializeFromContext()
    await Promise.all([loadProductCatalog(), loadGeography()])
})

watch(() => form.country_id, async () => {
    form.region_id = null
    form.city_id = null
    await loadGeography()
})

watch(() => form.region_id, async () => {
    form.city_id = null
    await loadGeography()
})

function initializeFromContext() {
    const current = context.value
    form.selected_product_id = current?.primary_product?.id || null
    if (current?.source?.type === 'good') {
        form.originating_good_id = current.source.id
    }
    geographyOptions.countries = current?.geography?.countries || []
    geographyOptions.regions = current?.geography?.regions || []
    geographyOptions.cities = current?.geography?.cities || []
}

async function selectGoodProduct(productId) {
    form.selected_product_id = productId
    if (!productId || context.value?.source?.type !== 'good') return
    busy.value = true
    error.value = ''
    try {
        const { data } = await axios.get('/api/ai-sales/find-buyers/launch-context', {
            params: {
                source_type: 'good',
                source_id: context.value.source.id,
                selected_product_id: productId,
            },
        })
        context.value = data.data
        emit('context-updated', data.data)
    } catch (requestError) {
        form.selected_product_id = null
        error.value = safeError(requestError, 'Выбранный Product не прошёл серверную проверку связи с Good.')
    } finally {
        busy.value = false
    }
}

async function loadProductCatalog() {
    try {
        const { data } = await axios.get('/api/ai-sales/prospecting/catalog/products?limit=50')
        catalogProducts.value = data.data || []
    } catch (requestError) {
        error.value = safeError(requestError, 'Не удалось загрузить разрешённый Product catalogue.')
    }
}

async function loadGeography() {
    try {
        const { data } = await axios.get('/api/ai-sales/find-buyers/geography', {
            params: {
                country_id: form.country_id || undefined,
                region_id: form.region_id || undefined,
            },
        })
        geographyOptions.countries = data.data?.countries || geographyOptions.countries
        geographyOptions.regions = data.data?.regions || []
        geographyOptions.cities = data.data?.cities || []
    } catch (requestError) {
        error.value = safeError(requestError, 'География отклонена серверной проверкой иерархии.')
    }
}

function payload() {
    return {
        source_type: context.value.source.type,
        source_id: context.value.source.id,
        selected_product_id: form.selected_product_id,
        idempotency_key: form.idempotency_key,
        additional_product_ids: form.additional_product_ids,
        excluded_product_ids: form.excluded_product_ids,
        originating_good_id: form.originating_good_id,
        industry_ids: form.industry_ids,
        included_category_ids: form.included_category_ids,
        excluded_category_ids: form.excluded_category_ids,
        company_activity_codes: form.company_activity_codes,
        company_type_code: form.company_type_code,
        country_id: form.country_id,
        region_id: form.region_id,
        city_id: form.city_id,
        limits: { ...form.limits },
    }
}

function updatePayload() {
    const value = payload()
    delete value.source_type
    delete value.source_id
    delete value.idempotency_key
    return value
}

async function persistDraft() {
    busy.value = true
    error.value = ''
    try {
        const response = savedJob.value
            ? await axios.patch(`/api/ai-sales/find-buyers/drafts/${savedJob.value.id}`, updatePayload())
            : await axios.post('/api/ai-sales/find-buyers/drafts', payload())
        savedJob.value = response.data.data
        plannedQueries.value = []
        await loadProgress()
        return savedJob.value
    } catch (requestError) {
        error.value = safeError(requestError, 'Черновик не сохранён: серверная policy отклонила данные.')
        return null
    } finally {
        busy.value = false
    }
}

async function buildPlan() {
    const job = await persistDraft()
    if (!job) return
    busy.value = true
    error.value = ''
    try {
        const { data } = await axios.post(`/api/ai-sales/find-buyers/drafts/${job.id}/plan`, {})
        plannedQueries.value = data.data || []
        await loadProgress()
    } catch (requestError) {
        error.value = safeError(requestError, 'Code-owned query plan не сформирован.')
    } finally {
        busy.value = false
    }
}

async function submitForReview() {
    if (!savedJob.value || !plannedQueries.value.length) return
    busy.value = true
    error.value = ''
    try {
        const { data } = await axios.post(`/api/ai-sales/find-buyers/drafts/${savedJob.value.id}/submit`, {})
        savedJob.value = data.data
        await loadProgress()
    } catch (requestError) {
        error.value = safeError(requestError, 'План не отправлен на human review.')
    } finally {
        busy.value = false
    }
}

async function cancelJob() {
    if (!savedJob.value) {
        open.value = false
        return
    }
    busy.value = true
    try {
        const { data } = await axios.post(`/api/ai-sales/find-buyers/jobs/${savedJob.value.id}/cancel`, {})
        savedJob.value = data.data
        await loadProgress()
    } catch (requestError) {
        error.value = safeError(requestError, 'Задание не отменено.')
    } finally {
        busy.value = false
    }
}

async function loadProgress() {
    if (!savedJob.value) return
    const { data } = await axios.get(`/api/ai-sales/find-buyers/jobs/${savedJob.value.id}/progress`)
    progress.value = data.data
}

function safeError(requestError, fallback) {
    const validation = requestError?.response?.data?.errors
    if (validation) return Object.values(validation).flat().join(' ')
    return requestError?.response?.data?.message || fallback
}
</script>

<template>
    <v-dialog v-model="open" max-width="980" persistent scrollable>
        <v-card>
            <v-card-title class="d-flex align-center ga-3 flex-wrap">
                <v-icon color="deep-purple">mdi-robot-outline</v-icon>
                <span>Найти покупателей</span>
                <v-chip size="small" color="deep-purple" variant="tonal">Product-first · Stage 11</v-chip>
                <v-spacer />
                <v-chip size="small" color="warning" variant="tonal">Live execution выключен</v-chip>
            </v-card-title>

            <v-divider />
            <v-stepper v-model="step" alt-labels flat>
                <v-stepper-header>
                    <v-stepper-item :value="1" title="Что ищем" />
                    <v-divider />
                    <v-stepper-item :value="2" title="Кого ищем" />
                    <v-divider />
                    <v-stepper-item :value="3" title="География" />
                    <v-divider />
                    <v-stepper-item :value="4" title="Лимиты" />
                    <v-divider />
                    <v-stepper-item :value="5" title="Данные" />
                    <v-divider />
                    <v-stepper-item :value="6" title="Проверка" />
                </v-stepper-header>

                <v-stepper-window>
                    <v-stepper-window-item :value="1">
                        <v-alert type="info" variant="tonal" class="mb-4">
                            Product задаёт смысл поиска. Good — только необязательное исходное коммерческое предложение и не меняет query semantics.
                        </v-alert>
                        <div class="d-flex ga-2 flex-wrap mb-3">
                            <v-chip size="small" variant="outlined">Доступные Goods: {{ context.summary_counts?.available_goods || 0 }}</v-chip>
                            <v-chip size="small" variant="outlined">Активные задания: {{ context.summary_counts?.active_jobs || 0 }}</v-chip>
                        </div>
                        <v-select
                            v-if="needsProductSelection"
                            :model-value="form.selected_product_id"
                            :items="context.product_options"
                            item-title="name"
                            item-value="id"
                            label="Выберите один связанный Product"
                            :loading="busy"
                            @update:model-value="selectGoodProduct"
                        />
                        <v-text-field
                            v-else
                            :model-value="primaryProduct?.name"
                            label="Основной Product"
                            readonly
                        />
                        <v-autocomplete
                            v-model="form.additional_product_ids"
                            :items="productItems"
                            item-title="name"
                            item-value="id"
                            label="Дополнительные Products"
                            multiple chips clearable
                        />
                        <v-autocomplete
                            v-model="form.excluded_product_ids"
                            :items="productItems"
                            item-title="name"
                            item-value="id"
                            label="Исключённые Products"
                            multiple chips clearable
                        />
                        <v-select
                            v-if="context.source.type === 'product'"
                            v-model="form.originating_good_id"
                            :items="context.offer_options"
                            item-title="name"
                            item-value="id"
                            label="Good для будущего предложения (необязательно)"
                            clearable
                        />
                        <v-alert v-else-if="context.originating_good" type="info" variant="tonal" density="compact">
                            Исходный Good: {{ context.originating_good.name }}. Связь с выбранным Product будет повторно проверена сервером.
                        </v-alert>
                    </v-stepper-window-item>

                    <v-stepper-window-item :value="2">
                        <v-alert type="info" variant="tonal" class="mb-4">
                            Назначение фиксировано: buyer_discovery → sales → prospective_customer → potential_need.
                        </v-alert>
                        <v-autocomplete
                            v-model="form.industry_ids"
                            :items="context.criteria.industries"
                            item-title="name"
                            item-value="id"
                            label="Отрасли / ОКВЭД"
                            multiple chips clearable
                        />
                        <v-select
                            v-model="form.company_activity_codes"
                            :items="context.criteria.activities"
                            item-title="label"
                            item-value="code"
                            label="Виды деятельности"
                            multiple chips clearable
                        />
                        <v-select
                            v-model="form.company_type_code"
                            :items="context.criteria.company_types"
                            item-title="label"
                            item-value="code"
                            label="Тип компании"
                            clearable
                        />
                        <v-autocomplete
                            v-model="form.included_category_ids"
                            :items="context.criteria.categories"
                            item-title="name"
                            item-value="id"
                            label="Включённые категории"
                            multiple chips clearable
                        />
                        <v-autocomplete
                            v-model="form.excluded_category_ids"
                            :items="context.criteria.categories"
                            item-title="name"
                            item-value="id"
                            label="Исключённые категории"
                            multiple chips clearable
                        />
                        <div class="text-caption text-medium-emphasis">Свободного AI prompt здесь нет.</div>
                    </v-stepper-window-item>

                    <v-stepper-window-item :value="3">
                        <v-select
                            v-model="form.country_id"
                            :items="geographyOptions.countries"
                            item-title="name"
                            item-value="id"
                            label="Страна"
                            clearable
                        />
                        <v-select
                            v-model="form.region_id"
                            :items="geographyOptions.regions"
                            item-title="name"
                            item-value="id"
                            label="Регион"
                            :disabled="!form.country_id"
                            clearable
                        />
                        <v-select
                            v-model="form.city_id"
                            :items="geographyOptions.cities"
                            item-title="name"
                            item-value="id"
                            label="Город"
                            :disabled="!form.region_id"
                            clearable
                        />
                        <div class="text-caption text-medium-emphasis">Иерархия country → region → city проверяется сервером; browser не задаёт Yandex region enum.</div>
                    </v-stepper-window-item>

                    <v-stepper-window-item :value="4">
                        <v-row>
                            <v-col cols="12" md="6"><v-slider v-model="form.limits.max_queries" min="1" :max="context.criteria.limits.max_queries" step="1" thumb-label label="Запросы" /></v-col>
                            <v-col cols="12" md="6"><v-slider v-model="form.limits.max_results_per_query" min="1" :max="context.criteria.limits.max_results_per_query" step="1" thumb-label label="Результаты / запрос" /></v-col>
                            <v-col cols="12" md="6"><v-slider v-model="form.limits.max_domains" min="1" :max="context.criteria.limits.max_domains" step="1" thumb-label label="Домены" /></v-col>
                            <v-col cols="12" md="6"><v-slider v-model="form.limits.max_page_fetch_attempts" min="0" :max="context.criteria.limits.max_page_fetch_attempts" step="1" thumb-label label="Page fetch attempts" /></v-col>
                            <v-col cols="12" md="6"><v-slider v-model="form.limits.max_candidates" min="1" :max="context.criteria.limits.max_candidates" step="1" thumb-label label="Candidates" /></v-col>
                        </v-row>
                        <v-alert type="warning" variant="tonal">Budget Stage 11: 0 RUB · retries 0 · failovers 0 · HTTP 0.</v-alert>
                    </v-stepper-window-item>

                    <v-stepper-window-item :value="5">
                        <v-alert type="info" variant="tonal" class="mb-3">
                            Preview вычислен из code-owned Stage 03 classification registry и deterministic disclosure policy.
                        </v-alert>
                        <div class="text-subtitle-2 mb-2">Разрешённые поля</div>
                        <v-chip
                            v-for="field in disclosure.allowed_fields"
                            :key="field.field"
                            size="small" color="success" variant="tonal" class="mr-2 mb-2"
                        >{{ field.field }} · {{ field.classification }}</v-chip>
                        <div class="text-subtitle-2 mt-3 mb-2">Всегда блокируется</div>
                        <v-list density="compact">
                            <v-list-item
                                v-for="item in disclosure.blocked_classes"
                                :key="item.code"
                                :title="item.code"
                                :subtitle="`${item.classification} · ${item.reason_code}`"
                                prepend-icon="mdi-shield-lock-outline"
                            />
                        </v-list>
                        <div class="text-caption">Policy hash: {{ disclosure.policy_hash }}</div>
                    </v-stepper-window-item>

                    <v-stepper-window-item :value="6">
                        <v-list density="compact">
                            <v-list-item title="Product scope" :subtitle="primaryProduct?.name || 'не выбран'" />
                            <v-list-item title="Good offer" :subtitle="context.originating_good?.name || context.offer_options?.find(item => item.id === form.originating_good_id)?.name || 'не выбран'" />
                            <v-list-item title="Purpose / lane / role" subtitle="buyer_discovery · sales · prospective_customer" />
                            <v-list-item title="Query plan" :subtitle="plannedQueries.length ? `${plannedQueries.length} запросов · review_required` : 'ещё не сформирован'" />
                            <v-list-item title="Live runtime" :subtitle="liveDisabled ? 'выключен; действие execute отсутствует' : 'заблокировано policy'" />
                        </v-list>
                        <v-alert v-if="savedJob" type="success" variant="tonal" class="mt-3">
                            Job {{ savedJob.id }} · {{ progress?.stage || savedJob.status }}
                        </v-alert>
                        <v-list v-if="plannedQueries.length" density="compact" class="mt-3">
                            <v-list-item
                                v-for="queryItem in plannedQueries"
                                :key="queryItem.id"
                                :title="queryItem.query"
                                :subtitle="`${queryItem.template_code} · ${queryItem.plan_status}`"
                            />
                        </v-list>
                    </v-stepper-window-item>
                </v-stepper-window>
            </v-stepper>

            <v-alert v-if="error" type="error" variant="tonal" class="mx-4 mb-3">{{ error }}</v-alert>

            <v-divider />
            <v-card-actions class="flex-wrap">
                <v-btn :disabled="step <= 1" @click="step -= 1">Назад</v-btn>
                <v-btn v-if="step < 6" color="primary" :disabled="step === 1 && !canContinueProduct" @click="step += 1">Далее</v-btn>
                <v-spacer />
                <v-btn :disabled="busy" @click="cancelJob">{{ savedJob ? 'Отменить задание' : 'Отмена' }}</v-btn>
                <v-btn v-if="step === 6" variant="tonal" color="primary" :loading="busy" :disabled="!canContinueProduct || savedJob?.status === 'review_required'" @click="persistDraft">Сохранить черновик</v-btn>
                <v-btn v-if="step === 6" variant="tonal" color="deep-purple" :loading="busy" :disabled="!canContinueProduct || savedJob?.status === 'review_required'" @click="buildPlan">Сформировать план запросов</v-btn>
                <v-btn v-if="step === 6" color="success" :loading="busy" :disabled="!plannedQueries.length || savedJob?.status === 'review_required'" @click="submitForReview">Отправить на проверку</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
