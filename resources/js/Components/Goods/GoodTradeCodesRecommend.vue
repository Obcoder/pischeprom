<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { goodTradeCodeFields, goodTradeCodeValues } from '@/utils/goodTradeCodes.js'

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
let controller = null
let availabilityController = null
let requestVersion = 0
let disposed = false

const payload = computed(() => {
    const selectedProducts = props.draft.product_ids ?? props.draft.products
    const products = Array.isArray(selectedProducts) ? selectedProducts : []
    return {
        ...(props.draft.id ? { good_id: props.draft.id } : {}),
        name: String(props.draft.name || '').trim(),
        description: props.draft.description || null,
        country_id: props.draft.country_id || null,
        product_ids: products.map(product => typeof product === 'object' ? product?.id : product)
            .filter(Boolean).sort((a, b) => Number(a) - Number(b)),
        requested_fields: goodTradeCodeFields.filter(field => requestedFields.value.includes(field.key)).map(field => field.key),
        ...goodTradeCodeValues(props.draft),
    }
})
const context = computed(() => JSON.stringify(payload.value))
const canRecommend = computed(() => props.active && !props.disabled && !loading.value && !checkingAvailability.value
    && availability.value?.available !== false && payload.value.name.length > 0 && payload.value.name.length <= 255
    && payload.value.requested_fields.length > 0)
const recommendations = computed(() => (result.value?.recommendations || []).map(item => ({
    ...item,
    label: goodTradeCodeFields.find(field => field.key === item.field)?.label || item.field,
    current: props.draft[item.field] || null,
    sources: (item.sources || []).filter(source => typeof source?.title === 'string' && /^https?:\/\//i.test(source?.url || '')),
})))
const applicableFields = computed(() => recommendations.value.filter(canApplyRecommendation).map(item => item.field))
const selectedCount = computed(() => applicableFields.value.filter(field => selectedFields.value.includes(field)).length)

function canApplyRecommendation(item) {
    return item.status === 'suggestion' && item.field !== 'gtin' && typeof item.value === 'string'
        && item.value.trim() !== '' && String(props.draft[item.field] || '') !== item.value
}

function invalidate() {
    requestVersion += 1
    controller?.abort()
    controller = null
    loading.value = false
    result.value = null
    selectedFields.value = []
    error.value = ''
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
    const body = JSON.parse(snapshot)
    const current = new AbortController()
    controller = current
    loading.value = true
    result.value = null
    selectedFields.value = []
    error.value = ''
    try {
        const response = await axios.post('/api/goods/trade-codes/recommend', body, { signal: current.signal })
        if (disposed || current.signal.aborted || version !== requestVersion || snapshot !== context.value) return
        if (!validResponse(response.data, body.requested_fields)) {
            throw new Error('AI вернул неполный ответ. Повторите подбор кодов.')
        }
        result.value = response.data
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

function applySelected() {
    if (disposed || !props.active || props.disabled || loading.value || !selectedCount.value) return
    // Build one patch before reactive changes invalidate the recommendation list.
    const patch = Object.fromEntries(recommendations.value
        .filter(item => selectedFields.value.includes(item.field) && canApplyRecommendation(item))
        .map(item => [item.field, item.value]))
    invalidate()
    emit('apply', patch)
}

watch(context, invalidate, { flush: 'sync' })
watch(() => props.disabled, disabled => { if (disabled) invalidate() }, { flush: 'sync' })
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
            <v-btn v-if="loading" size="small" variant="text" @click="invalidate">Отмена</v-btn>
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
                    :disabled="disabled || !canApplyRecommendation(item)"
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
                    <div v-if="item.missing_information.length" class="text-caption mt-1">Уточните: {{ item.missing_information.join('; ') }}</div>
                    <div v-if="item.sources.length" class="d-flex flex-wrap ga-2 mt-1">
                        <a v-for="source in item.sources" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="text-caption">{{ source.title }}</a>
                    </div>
                </div>
            </div>
            <div class="good-code-recommend__footer">
                <v-btn size="small" variant="tonal" color="deep-purple" :disabled="disabled || !selectedCount" @click="applySelected">
                    Применить выбранные<template v-if="selectedCount"> · {{ selectedCount }}</template>
                </v-btn>
                <span class="text-caption text-medium-emphasis">Коды попадут в форму. Сохраните товар, чтобы записать изменения.</span>
            </div>
            <div v-if="result.scope" class="text-caption text-medium-emphasis mt-1">{{ result.scope }}</div>
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
</style>
