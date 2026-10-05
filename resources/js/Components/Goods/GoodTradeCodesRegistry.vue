<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { goodTradeCodeFields } from '@/utils/goodTradeCodes.js'

const props = defineProps({
    codes: { type: Object, default: () => ({}) },
    active: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: 'Проверить введённые коды по реестрам' },
})
const loading = ref(false)
const result = ref(null)
const error = ref('')
let controller = null
let requestVersion = 0
let disposed = false
const codes = computed(() => Object.fromEntries(goodTradeCodeFields
    .filter(field => typeof props.codes[field.key] === 'string' && props.codes[field.key].trim())
    .map(field => [field.key, props.codes[field.key].trim()])))
const context = computed(() => JSON.stringify(codes.value))
const hasCodes = computed(() => Object.keys(codes.value).length > 0)
const canVerify = computed(() => props.active && !props.disabled && !loading.value && hasCodes.value)
const statuses = {
    found: { label: 'Код найден', icon: 'mdi-check-circle-outline', color: 'success' },
    not_found: { label: 'Код не найден', icon: 'mdi-alert-circle-outline', color: 'warning' },
    unavailable: { label: 'Источник недоступен', icon: 'mdi-cloud-alert-outline', color: 'grey' },
    not_supported: { label: 'Ручная сверка', icon: 'mdi-open-in-new', color: 'grey' },
}
const rows = computed(() => (result.value?.results || []).map(item => ({
    ...item,
    label: goodTradeCodeFields.find(field => field.key === item.field)?.label || item.field,
    sourceUrl: /^https:\/\//i.test(item.source_url || '') ? item.source_url : null,
    presentation: statuses[item.status],
})))

function invalidate() {
    requestVersion += 1
    controller?.abort()
    controller = null
    loading.value = false
    result.value = null
    error.value = ''
}

function validResponse(data, requested) {
    const fields = Object.keys(requested)
    if (!Array.isArray(data?.results) || data.results.length !== fields.length) return false
    const seen = new Set()
    return data.results.every(item => {
        if (!item || !fields.includes(item.field) || seen.has(item.field) || !Object.hasOwn(statuses, item.status)) return false
        seen.add(item.field)
        return typeof item.code === 'string' && !!item.code.trim() && typeof item.message === 'string'
            && (item.status !== 'found' || (typeof item.title === 'string' && !!item.title.trim()))
    })
}

async function verifyCodes() {
    if (disposed || !canVerify.value) return
    const version = ++requestVersion
    const snapshot = context.value
    const body = { codes: JSON.parse(snapshot) }
    const current = new AbortController()
    controller = current
    loading.value = true
    error.value = ''
    result.value = null
    try {
        const response = await axios.post('/api/goods/trade-codes/verify', body, { signal: current.signal })
        if (disposed || current.signal.aborted || version !== requestVersion || snapshot !== context.value) return
        if (!validResponse(response.data, body.codes)) throw new Error('Не удалось прочитать результат сверки. Повторите проверку.')
        result.value = response.data
    } catch (failure) {
        if (!disposed && !current.signal.aborted && version === requestVersion) {
            error.value = failure?.response?.data?.message || failure?.message || 'Не удалось проверить коды. Повторите попытку.'
        }
    } finally {
        if (version === requestVersion) {
            controller = null
            loading.value = false
        }
    }
}

function checkedAt(value) {
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? '' : date.toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' })
}

watch(context, invalidate, { flush: 'sync' })
watch(() => props.active, invalidate, { flush: 'sync' })
watch(() => props.disabled, disabled => { if (disabled) invalidate() }, { flush: 'sync' })
onBeforeUnmount(() => { disposed = true; invalidate() })
</script>

<template>
    <div v-if="hasCodes" class="good-code-registry">
        <div class="d-flex align-center flex-wrap ga-1">
            <v-btn
                prepend-icon="mdi-book-search-outline" size="small" variant="text" color="primary"
                :loading="loading" :disabled="!canVerify" @click="verifyCodes"
            >{{ label }}</v-btn>
            <v-btn v-if="loading" size="small" variant="text" @click="invalidate">Отмена</v-btn>
        </div>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-1" role="alert">{{ error }}</v-alert>
        <div v-if="result" class="good-code-registry__results" aria-live="polite">
            <div v-for="item in rows" :key="item.field" class="good-code-registry__row">
                <v-icon :icon="item.presentation.icon" :color="item.presentation.color" size="17" class="mt-1" />
                <div>
                    <div class="d-flex align-center flex-wrap ga-2">
                        <strong>{{ item.label }} · {{ item.code }}</strong>
                        <span :class="`text-${item.presentation.color}`">{{ item.presentation.label }}</span>
                    </div>
                    <div v-if="item.title"><span v-if="item.field === 'tn_ved_code'">Фрагмент строки: </span>{{ item.title }}</div>
                    <div v-if="item.message" class="text-medium-emphasis">{{ item.message }}</div>
                    <div class="d-flex align-center flex-wrap ga-2 text-medium-emphasis">
                        <a v-if="item.sourceUrl" :href="item.sourceUrl" target="_blank" rel="noopener noreferrer">{{ item.source_name || 'Источник' }}</a>
                        <span v-if="item.version">{{ item.version }}</span>
                        <span v-if="item.checked_at">Сверка: {{ checkedAt(item.checked_at) }}</span>
                    </div>
                </div>
            </div>
            <div class="text-caption text-medium-emphasis mt-1">{{ result.scope }}</div>
        </div>
    </div>
</template>

<style scoped>
.good-code-registry { min-width: 0; margin: 4px 0; }
.good-code-registry__results { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); border-radius: 6px; padding: 8px; }
.good-code-registry__row { display: grid; grid-template-columns: 17px minmax(0, 1fr); gap: 8px; padding: 4px 0; font-size: 0.75rem; line-height: 1.5; overflow-wrap: anywhere; }
.good-code-registry__row + .good-code-registry__row { border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.good-code-registry__row a { color: rgb(var(--v-theme-primary)); }
</style>
