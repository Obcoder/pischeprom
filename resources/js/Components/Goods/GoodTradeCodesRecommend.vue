<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { goodTradeCodeFields, goodTradeCodeValues } from '@/utils/goodTradeCodes.js'
import GoodTradeCodesRegistry from '@/Components/Goods/GoodTradeCodesRegistry.vue'

const props = defineProps({
    draft: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
})
const emit = defineEmits(['apply'])
const requestedFields = ref(goodTradeCodeFields.filter(field => field.primary).map(field => field.key))
const selectedFields = ref([])
const availability = ref(null)
const checkingAvailability = ref(false)
const loading = ref(false)
const error = ref('')
const result = ref(null)
const clarificationAnswers = ref([])
const additionalContext = ref('')
const currentQuestionKeys = ref([])
const resultAnswerContext = ref(null)
const emptyAnswerContext = JSON.stringify({ answers: [], additional_context: '' })
const lastSuccessfulAnswerContext = ref(emptyAnswerContext)
let controller = null
let availabilityController = null
let requestVersion = 0
let disposed = false
let mergingResponseQuestions = false

const productPayload = computed(() => {
    const selectedProducts = props.draft.product_ids ?? props.draft.products
    const products = Array.isArray(selectedProducts) ? selectedProducts : []
    return {
        ...(props.draft.id ? { good_id: props.draft.id } : {}),
        name: String(props.draft.name || '').trim(),
        description: props.draft.description || null,
        country_id: props.draft.country_id || null,
        product_ids: products.map(product => typeof product === 'object' ? product?.id : product)
            .filter(Boolean).sort((a, b) => Number(a) - Number(b)),
        ...goodTradeCodeValues(props.draft),
    }
})
const requestedClassifiers = computed(() => goodTradeCodeFields.filter(field => requestedFields.value.includes(field.key)).map(field => field.key))
const answeredClarifications = computed(() => clarificationAnswers.value.filter(item => item.answer.trim()).map(item => ({
    fields: item.fields,
    question: item.question,
    answer: item.answer.trim(),
})))
const answerContext = computed(() => JSON.stringify({
    answers: clarificationAnswers.value.filter(item => item.answer.trim())
        .map(item => ({ key: item.key, fields: [...item.fields].sort(), answer: item.answer.trim() })).sort((a, b) => a.key.localeCompare(b.key)),
    additional_context: additionalContext.value.trim(),
}))
const payload = computed(() => ({
    ...productPayload.value,
    requested_fields: requestedClassifiers.value,
    ...(answeredClarifications.value.length ? { clarifications: answeredClarifications.value } : {}),
    ...(additionalContext.value.trim() ? { additional_context: additionalContext.value.trim() } : {}),
}))
const context = computed(() => JSON.stringify(payload.value))
const currentQuestions = computed(() => currentQuestionKeys.value.map(key => clarificationAnswers.value.find(item => item.key === key)).filter(Boolean))
const answeredHistory = computed(() => clarificationAnswers.value.filter(item => (item.wasAnswered || item.answer.trim()) && !currentQuestionKeys.value.includes(item.key)))
const clarificationError = computed(() => {
    if (answeredClarifications.value.length > 40) return 'Оставьте не более 40 ответов в одном подборе.'
    if (answeredClarifications.value.some(item => Array.from(item.answer).length > 2000)) return 'Сократите каждый ответ до 2000 символов.'
    if (answeredClarifications.value.reduce((total, item) => total + Array.from(item.answer).length, 0) > 16000) return 'Общий объём ответов не должен превышать 16 000 символов.'
    if (Array.from(additionalContext.value.trim()).length > 4000) return 'Сократите дополнительные сведения до 4000 символов.'
    return ''
})
const canRecommend = computed(() => props.active && !props.disabled && !loading.value && !checkingAvailability.value
    && availability.value?.available !== false && payload.value.name.length > 0 && payload.value.name.length <= 255
    && payload.value.requested_fields.length > 0 && !clarificationError.value)
const canRefine = computed(() => canRecommend.value && answerContext.value !== lastSuccessfulAnswerContext.value
    && (answerContext.value !== emptyAnswerContext || lastSuccessfulAnswerContext.value !== emptyAnswerContext))
const resultIsCurrent = computed(() => !!result.value && resultAnswerContext.value === answerContext.value)
const recommendations = computed(() => (result.value?.recommendations || []).map(item => ({
    ...item,
    label: goodTradeCodeFields.find(field => field.key === item.field)?.label || item.field,
    current: props.draft[item.field] || null,
    sources: (item.sources || []).filter(source => typeof source?.title === 'string' && /^https?:\/\//i.test(source?.url || '')),
})))
const applicableFields = computed(() => recommendations.value.filter(canApplyRecommendation).map(item => item.field))
const recommendationCodes = computed(() => Object.fromEntries(recommendations.value.filter(item => item.value).map(item => [item.field, item.value])))
const selectedCount = computed(() => resultIsCurrent.value ? applicableFields.value.filter(field => selectedFields.value.includes(field)).length : 0)

function questionKey(question) {
    return question.trim().replace(/\s+/gu, ' ').toLocaleLowerCase('ru-RU').replace(/[?.!:;]+$/u, '').trim()
}

function fieldLabels(fields) {
    return goodTradeCodeFields.filter(field => fields.includes(field.key)).map(field => field.label).join(' · ')
}

function mergeQuestions(items) {
    const activeKeys = []
    const updated = clarificationAnswers.value.map(item => ({ ...item, fields: [...item.fields] }))
    for (const item of items) {
        for (const text of item.missing_information) {
            const question = text.trim().replace(/\s+/gu, ' ')
            const key = questionKey(question)
            if (!key) continue
            let existing = updated.find(answer => answer.key === key)
            if (!existing) {
                existing = { key, question, fields: [], answer: '' }
                updated.push(existing)
            }
            if (!existing.fields.includes(item.field)) existing.fields.push(item.field)
            if (!activeKeys.includes(key)) activeKeys.push(key)
        }
    }
    clarificationAnswers.value = updated
    currentQuestionKeys.value = activeKeys
}

function canApplyRecommendation(item) {
    return item.status === 'suggestion' && item.field !== 'gtin' && typeof item.value === 'string'
        && item.value.trim() !== '' && String(props.draft[item.field] || '') !== item.value
}

function cancelRequest() {
    requestVersion += 1
    controller?.abort()
    controller = null
    loading.value = false
    selectedFields.value = []
    error.value = ''
}

function clearResult() {
    cancelRequest()
    result.value = null
    resultAnswerContext.value = null
    currentQuestionKeys.value = []
}

function invalidate() {
    clearResult()
    clarificationAnswers.value = []
    additionalContext.value = ''
    lastSuccessfulAnswerContext.value = emptyAnswerContext
}

async function loadAvailability() {
    if (disposed || availability.value || checkingAvailability.value || !props.active) return
    checkingAvailability.value = true
    const current = new AbortController()
    availabilityController = current
    try {
        const response = await axios.get('/api/goods/trade-codes/availability', { signal: current.signal })
        if (!disposed && !current.signal.aborted) availability.value = response.data
    } catch (failure) {
        if (!disposed && !current.signal.aborted) {
            error.value = failure?.response?.data?.message || 'Не удалось проверить доступность AI. Повторите подбор.'
        }
    } finally {
        if (availabilityController === current) {
            availabilityController = null
            checkingAvailability.value = false
        }
    }
}

function validResponse(data, fields) {
    if (!Array.isArray(data?.recommendations) || data.recommendations.length !== fields.length) return false
    const seen = new Set()
    return data.recommendations.every(item => {
        if (!item || !fields.includes(item.field) || seen.has(item.field)) return false
        seen.add(item.field)
        return ['suggestion', 'needs_information', 'not_applicable'].includes(item.status)
            && typeof item.rationale === 'string'
            && (item.value === null || typeof item.value === 'string')
            && (item.status !== 'suggestion' || !!item.value?.trim())
            && Array.isArray(item.missing_information) && item.missing_information.every(value => typeof value === 'string')
            && Array.isArray(item.sources)
    })
}

async function recommendCodes() {
    if (disposed || !canRecommend.value) return
    const version = ++requestVersion
    const snapshot = context.value
    const answersSnapshot = answerContext.value
    const body = JSON.parse(snapshot)
    const current = new AbortController()
    controller = current
    loading.value = true
    selectedFields.value = []
    error.value = ''
    try {
        const response = await axios.post('/api/goods/trade-codes/recommend', body, { signal: current.signal })
        if (disposed || current.signal.aborted || version !== requestVersion || snapshot !== context.value) return
        if (!validResponse(response.data, body.requested_fields)) {
            throw new Error('AI вернул неполный ответ. Повторите подбор кодов.')
        }
        // A repeated question may gain another classifier. Keep the request snapshot so
        // its existing answer can be sent for that classifier in the next refinement.
        mergingResponseQuestions = true
        try {
            mergeQuestions(response.data.recommendations)
        } finally {
            mergingResponseQuestions = false
        }
        result.value = response.data
        resultAnswerContext.value = answersSnapshot
        lastSuccessfulAnswerContext.value = answersSnapshot
    } catch (failure) {
        if (!disposed && !current.signal.aborted && version === requestVersion) {
            error.value = failure?.response?.data?.message || failure?.message || 'Не удалось подобрать коды. Повторите попытку.'
        }
    } finally {
        if (version === requestVersion) {
            controller = null
            loading.value = false
        }
    }
}

async function refineCodes() {
    if (!canRefine.value) return
    await recommendCodes()
}

function applySelected() {
    if (disposed || !props.active || props.disabled || loading.value || !selectedCount.value) return
    // Build one patch before reactive changes invalidate the recommendation list.
    const patch = Object.fromEntries(recommendations.value
        .filter(item => selectedFields.value.includes(item.field) && canApplyRecommendation(item))
        .map(item => [item.field, item.value]))
    invalidate()
    emit('apply', patch)
}

watch(() => JSON.stringify(productPayload.value), invalidate, { flush: 'sync' })
watch(() => JSON.stringify(requestedClassifiers.value), clearResult, { flush: 'sync' })
watch(answerContext, () => {
    if (!mergingResponseQuestions) cancelRequest()
    clarificationAnswers.value.forEach(item => { if (item.answer.trim()) item.wasAnswered = true })
}, { flush: 'sync' })
watch(() => props.disabled, disabled => { if (disabled) cancelRequest() }, { flush: 'sync' })
watch(() => props.active, active => {
    invalidate()
    if (active) loadAvailability()
    else {
        availabilityController?.abort()
        availabilityController = null
        checkingAvailability.value = false
    }
}, { immediate: true, flush: 'sync' })
onBeforeUnmount(() => {
    disposed = true
    invalidate()
    availabilityController?.abort()
})
</script>

<template>
    <div class="good-code-recommend">
        <div class="good-code-recommend__toolbar">
            <v-select
                v-model="requestedFields"
                :items="goodTradeCodeFields"
                item-title="label"
                item-value="key"
                label="Коды для подбора"
                :disabled="disabled"
                multiple
                chips
                closable-chips
                density="compact"
                variant="outlined"
                hide-details
                class="good-code-recommend__classifiers"
            />
            <v-btn
                prepend-icon="mdi-auto-fix"
                variant="tonal"
                color="deep-purple"
                size="small"
                :loading="loading || checkingAvailability"
                :disabled="!canRecommend"
                @click="recommendCodes"
            >
                AI-подбор кодов
            </v-btn>
            <v-btn v-if="loading" size="small" variant="text" @click="cancelRequest">Отмена</v-btn>
        </div>
        <div class="text-caption text-medium-emphasis mt-1">
            {{ payload.name ? 'Рекомендации по описанию товара; выберите коды перед применением.' : 'Укажите название товара для подбора кодов.' }}
        </div>
        <div v-if="availability?.available === false" class="text-caption text-medium-emphasis mt-1">
            {{ availability.message || 'AI-подбор пока не настроен.' }}
        </div>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2" role="alert">{{ error }}</v-alert>
        <div v-if="result" class="good-code-recommend__results mt-2" aria-live="polite">
            <div v-for="item in recommendations" :key="item.field" class="good-code-recommend__row">
                <v-checkbox
                    v-model="selectedFields"
                    :value="item.field"
                    :aria-label="`Применить ${item.label}: ${item.value || 'нет рекомендации'}`"
                    :disabled="disabled || loading || !resultIsCurrent || !canApplyRecommendation(item)"
                    density="compact"
                    hide-details
                    class="good-code-recommend__checkbox"
                />
                <div class="good-code-recommend__details">
                    <div class="d-flex align-center flex-wrap ga-2">
                        <strong class="text-body-2">{{ item.label }}</strong>
                        <span v-if="item.current && item.value && item.current !== item.value" class="good-code-recommend__code">{{ item.current }} → {{ item.value }}</span>
                        <span v-else-if="item.value" class="good-code-recommend__code">{{ item.value }}</span>
                        <span v-if="item.status === 'needs_information'" class="text-caption text-medium-emphasis">Нужны уточнения</span>
                        <span v-else-if="item.status === 'not_applicable'" class="text-caption text-medium-emphasis">Не применимо</span>
                        <span v-else-if="item.current === item.value" class="text-caption text-medium-emphasis">Уже указан</span>
                    </div>
                    <div class="text-body-2">{{ item.rationale }}</div>
                    <div v-if="item.sources.length" class="d-flex flex-wrap ga-2 mt-1">
                        <a v-for="source in item.sources" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="text-caption">{{ source.title }}</a>
                    </div>
                </div>
            </div>
            <div class="good-code-recommend__footer">
                <v-btn size="small" variant="tonal" color="deep-purple" :disabled="disabled || loading || !selectedCount" @click="applySelected">
                    Применить выбранные<template v-if="selectedCount"> · {{ selectedCount }}</template>
                </v-btn>
                <span class="text-caption text-medium-emphasis">Коды попадут в форму. Сохраните товар, чтобы записать изменения.</span>
            </div>
            <GoodTradeCodesRegistry
                :codes="recommendationCodes"
                :active="active && resultIsCurrent"
                :disabled="disabled || loading || !resultIsCurrent"
                label="Проверить рекомендации по реестрам"
            />
            <div v-if="!resultIsCurrent" class="text-caption text-medium-emphasis mt-1">Уточнения изменены. Обновите подбор перед применением кодов.</div>
            <div v-if="result.scope" class="text-caption text-medium-emphasis mt-1">{{ result.scope }}</div>
        </div>
        <div v-if="result || currentQuestions.length || answeredHistory.length || additionalContext" class="good-code-recommend__clarifications mt-3">
            <div class="text-subtitle-2 mb-2">Уточнения для подбора</div>
            <div v-for="question in currentQuestions" :key="question.key" class="good-code-recommend__question">
                <div class="text-body-2">{{ question.question }}</div>
                <div class="text-caption text-medium-emphasis mb-1">{{ fieldLabels(question.fields) }}</div>
                <v-textarea
                    v-model="question.answer"
                    :aria-label="`Ответ: ${question.question}`"
                    placeholder="Ваш ответ"
                    :disabled="disabled"
                    maxlength="2000"
                    rows="2"
                    max-rows="4"
                    auto-grow
                    density="compact"
                    variant="outlined"
                    hide-details="auto"
                />
            </div>
            <details v-if="answeredHistory.length" class="good-code-recommend__history mb-2">
                <summary class="text-caption">Ответы предыдущих шагов · {{ answeredHistory.length }}</summary>
                <div v-for="question in answeredHistory" :key="question.key" class="good-code-recommend__question mt-2">
                    <div class="text-body-2">{{ question.question }}</div>
                    <div class="text-caption text-medium-emphasis mb-1">{{ fieldLabels(question.fields) }}</div>
                    <v-textarea
                        v-model="question.answer"
                        :aria-label="`Ответ: ${question.question}`"
                        placeholder="Ваш ответ"
                        :disabled="disabled"
                        maxlength="2000"
                        rows="2"
                        max-rows="4"
                        auto-grow
                        density="compact"
                        variant="outlined"
                        hide-details="auto"
                    />
                </div>
            </details>
            <v-textarea
                v-model="additionalContext"
                label="Дополнительные сведения"
                aria-label="Дополнительные сведения"
                :disabled="disabled"
                maxlength="4000"
                rows="2"
                max-rows="4"
                auto-grow
                density="compact"
                variant="outlined"
                hide-details="auto"
            />
            <div v-if="clarificationError" class="text-error text-caption mt-1" role="alert">{{ clarificationError }}</div>
            <div class="good-code-recommend__footer mt-2">
                <v-btn size="small" variant="tonal" color="deep-purple" prepend-icon="mdi-auto-fix" :loading="loading" :disabled="!canRefine" @click="refineCodes">
                    Уточнить подбор
                </v-btn>
                <span class="text-caption text-medium-emphasis">Ответы используются для подбора и не меняют описание товара.</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.good-code-recommend {
    margin-block: 8px 12px;
}
.good-code-recommend__toolbar,
.good-code-recommend__footer {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.good-code-recommend__classifiers {
    flex: 1 1 250px;
    min-width: 0;
    max-width: 460px;
}
.good-code-recommend__row {
    display: flex;
    align-items: flex-start;
    gap: 4px;
    padding: 8px 0;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.good-code-recommend__checkbox {
    flex: 0 0 36px;
}
.good-code-recommend__details {
    min-width: 0;
    overflow-wrap: anywhere;
}
.good-code-recommend__code {
    font-family: monospace;
    font-size: 0.85rem;
}
.good-code-recommend__footer {
    margin-top: 4px;
}
.good-code-recommend__question {
    margin-bottom: 8px;
    overflow-wrap: anywhere;
}
.good-code-recommend__history summary {
    cursor: pointer;
}
</style>
