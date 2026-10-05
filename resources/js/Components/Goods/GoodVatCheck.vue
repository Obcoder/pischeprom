<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { goodTradeCodeValues } from '@/utils/goodTradeCodes.js'

const props = defineProps({
    draft: { type: Object, required: true },
    vatRates: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
})
const emit = defineEmits(['apply'])
const operation = ref('domestic')
const operations = [
    { title: 'Продажа в РФ', value: 'domestic' },
    { title: 'Импорт в РФ', value: 'import' },
    { title: 'Экспорт из РФ', value: 'export' },
]
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
        product_ids: products.map(product => typeof product === 'object' ? product.id : product)
            .filter(Boolean).sort((a, b) => Number(a) - Number(b)),
        vat_rate_id: props.draft.vat_rate_id || null,
        operation: operation.value,
        ...goodTradeCodeValues(props.draft),
    }
})
const context = computed(() => JSON.stringify(payload.value))
const canCheck = computed(() => props.active && !props.disabled && !loading.value
    && !checkingAvailability.value && availability.value?.available !== false
    && payload.value.name.length > 0 && payload.value.name.length <= 255)
const suggestedRate = computed(() => {
    if (result.value?.status !== 'suggestion' || result.value.vat_rate_id == null || result.value.rate == null) return null
    return props.vatRates.find(rate => String(rate.id) === String(result.value.vat_rate_id)
        && Number(rate.rate) === Number(result.value.rate)) || null
})
const sameRate = computed(() => suggestedRate.value
    && String(suggestedRate.value.id) === String(props.draft.vat_rate_id))
const sourceLinks = computed(() => (Array.isArray(result.value?.sources) ? result.value.sources : [])
    .filter(source => /^https?:\/\//i.test(source?.url || '')))

function invalidate() {
    requestVersion += 1
    controller?.abort()
    controller = null
    loading.value = false
    result.value = null
    error.value = ''
}

async function loadAvailability() {
    if (disposed || availability.value || checkingAvailability.value || !props.active) return
    checkingAvailability.value = true
    const current = new AbortController()
    availabilityController = current
    try {
        const response = await axios.get('/api/goods/vat-check/availability', { signal: current.signal })
        if (!disposed && !current.signal.aborted) availability.value = response.data
    } catch (failure) {
        if (!disposed && !current.signal.aborted) {
            error.value = failure?.response?.data?.message || 'Не удалось проверить доступность AI. Повторите проверку.'
        }
    } finally {
        if (availabilityController === current) {
            availabilityController = null
            checkingAvailability.value = false
        }
    }
}

async function checkVat() {
    if (!canCheck.value) return
    const version = ++requestVersion
    const snapshot = context.value
    const current = new AbortController()
    controller = current
    loading.value = true
    error.value = ''
    result.value = null
    try {
        const response = await axios.post('/api/goods/vat-check', JSON.parse(snapshot), { signal: current.signal })
        if (disposed || current.signal.aborted || version !== requestVersion || snapshot !== context.value) return
        if (!['suggestion', 'needs_information'].includes(response.data?.status)
            || typeof response.data?.rationale !== 'string') {
            throw new Error('AI вернул неполный ответ. Повторите проверку.')
        }
        result.value = response.data
    } catch (failure) {
        if (!disposed && !current.signal.aborted && version === requestVersion) {
            error.value = failure?.response?.data?.message || failure?.message || 'Не удалось проверить НДС. Повторите попытку.'
        }
    } finally {
        if (version === requestVersion) {
            controller = null
            loading.value = false
        }
    }
}

function applyRate() {
    if (!props.active || props.disabled || loading.value || !suggestedRate.value || sameRate.value) return
    emit('apply', suggestedRate.value.id)
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
    <div class="good-vat-check">
        <div class="good-vat-check__toolbar">
            <v-select
                v-model="operation"
                :items="operations"
                :disabled="disabled"
                label="Операция для НДС"
                density="compact"
                variant="outlined"
                hide-details
                class="good-vat-check__operation"
            />
            <v-btn
                prepend-icon="mdi-auto-fix"
                variant="tonal"
                color="deep-purple"
                size="small"
                :loading="loading || checkingAvailability"
                :disabled="!canCheck"
                @click="checkVat"
            >
                AI-проверка НДС
            </v-btn>
            <v-btn v-if="loading" size="small" variant="text" @click="invalidate">Отмена</v-btn>
        </div>
        <div class="text-caption text-medium-emphasis mt-1">Предварительная оценка · общий режим НДС РФ.</div>
        <div v-if="availability?.available === false" class="text-caption text-medium-emphasis mt-1">
            {{ availability.message || 'AI-проверка пока не настроена.' }}
        </div>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2" role="alert">
            {{ error }}
        </v-alert>
        <v-alert v-if="result" :type="result.status === 'suggestion' ? 'info' : 'warning'" variant="tonal" density="compact" class="mt-2">
            <div class="d-flex align-center flex-wrap ga-2 mb-1">
                <strong>{{ result.rate != null ? (result.status === 'suggestion' ? `Рекомендация: НДС ${result.rate}%` : `Возможная ставка: ${result.rate}% · нужны уточнения`) : 'Нужны уточнения' }}</strong>
                <v-btn v-if="suggestedRate" size="small" variant="tonal" :disabled="disabled || sameRate" @click="applyRate">
                    {{ sameRate ? 'Уже выбрана' : 'Применить ставку' }}
                </v-btn>
            </div>
            <div class="text-body-2">{{ result.rationale }}</div>
            <div v-if="result.missing_information?.length" class="text-body-2 mt-1">
                Уточните: {{ result.missing_information.join('; ') }}
            </div>
            <div v-if="result.scope" class="text-caption mt-1">{{ result.scope }}</div>
            <div v-if="result.status === 'suggestion' && !suggestedRate" class="text-caption mt-1">
                Эта ставка отсутствует в справочнике НДС; выберите подходящую ставку вручную.
            </div>
            <div v-if="sourceLinks.length" class="d-flex flex-wrap ga-2 mt-1">
                <a v-for="source in sourceLinks" :key="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="text-caption">
                    {{ source.title }}
                </a>
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
                Проверьте основание перед применением.<template v-if="result.rules_verified_at"> Справочные нормы на {{ result.rules_verified_at }}.</template>
                <template v-if="result.check_date"> Расчёт на {{ result.check_date }}.</template>
            </div>
        </v-alert>
    </div>
</template>

<style scoped>
.good-vat-check {
    margin-top: 8px;
}
.good-vat-check__toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.good-vat-check__operation {
    flex: 1 1 175px;
    max-width: 245px;
}
</style>
