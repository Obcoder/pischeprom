<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { VAutocomplete, VCombobox } from 'vuetify/components'
import axios from 'axios'
import { contactKey } from '@/Composables/unitCommunications.js'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    kind: { type: String, required: true },
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    errorMessages: { type: Array, default: () => [] },
    disabled: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const search = ref('')
const options = ref([])
const loading = ref(false)
const loaded = ref(false)
const loadError = ref('')
const page = ref(0)
const hasMore = ref(false)
const focused = ref(false)
const allowCreate = computed(() => ['emails', 'telephones', 'uris'].includes(props.kind))
const isLocal = computed(() => ['labels', 'classifications'].includes(props.kind))
const field = computed(() => props.kind === 'telephones' ? 'number' : 'address')
const control = computed(() => allowCreate.value ? VCombobox : VAutocomplete)
let timer = null
let request = null
let sequence = 0
let disposed = false
let failedPage = 1

function title(item) {
    if (typeof item === 'string') return item
    if (!item) return ''
    if (allowCreate.value) return item[field.value] || ''
    if (props.kind === 'buildings') return [item.address, item.city?.name].filter(Boolean).join(' · ')
    if (props.kind === 'cities') return item.label || [item.name, item.region_name || item.region?.name].filter(Boolean).join(' · ')
    if (props.kind === 'industries') return [item.code, item.title].filter(Boolean).join(' · ')
    return item.title || item.name || ''
}

function subtitle(item) {
    return props.kind === 'entities' && item?.INN ? `ИНН ${item.INN}` : undefined
}

function key(item) {
    if (allowCreate.value) {
        const value = String(typeof item === 'string' ? item : item?.[field.value] || '').trim()
        // Preserve invalid input for server-side validation instead of stripping its
        // letters or silently reusing a valid contact with the same remaining digits.
        if (props.kind === 'telephones' && value && (!/^[+\d\s().-]+$/.test(value) || !/\d/.test(value))) {
            return typeof item === 'string' ? `raw:${value}` : `record:${item.id ?? value}`
        }
        return contactKey(props.kind, value)
    }
    return item?.id == null ? '' : String(item.id)
}

function unique(items) {
    const records = new Map()
    for (let item of items || []) {
        if (typeof item === 'string') {
            if (!allowCreate.value || !item.trim()) continue
            item = item.trim()
            item = options.value.find(option => key(option) === key(item)) || item
        }
        const identity = key(item)
        if (identity && !records.has(identity)) records.set(identity, item)
    }
    return [...records.values()]
}

const items = computed(() => {
    const candidates = isLocal.value && search.value.trim()
        ? options.value.filter(item => title(item).toLocaleLowerCase('ru').includes(search.value.trim().toLocaleLowerCase('ru')))
        : options.value
    // Keep complete selected records available to Vuetify when a remote search changes its result set.
    return unique([...props.modelValue, ...candidates])
})

function select(value) {
    emit('update:modelValue', unique(value))
}

function flushPending() {
    const next = allowCreate.value && search.value.trim()
        ? unique([...props.modelValue, search.value.trim()])
        : unique(props.modelValue)
    if (allowCreate.value) {
        emit('update:modelValue', next)
        clearTimeout(timer)
        search.value = ''
    }
    return next
}

function cancelRequest() {
    clearTimeout(timer)
    request?.abort()
    request = null
    sequence++
    loading.value = false
}

function requestConfig(nextPage) {
    const params = { search: search.value.trim() || undefined, page: nextPage }
    const urls = { classifications: '/api/entities-classification' }
    if (isLocal.value) return { url: urls[props.kind] || `/api/${props.kind}`, params: {} }
    if (['cities', 'entities', 'emails'].includes(props.kind)) params.itemsPerPage = 30
    else params.per_page = 30
    if (props.kind === 'buildings') params.structure = 1
    return { url: urls[props.kind] || `/api/${props.kind}`, params }
}

async function loadOptions(nextPage = 1) {
    if (disposed || props.disabled) return
    cancelRequest()
    const current = sequence
    const controller = new AbortController()
    request = controller
    loading.value = true
    loadError.value = ''
    failedPage = nextPage
    const { url, params } = requestConfig(nextPage)
    try {
        const { data } = await axios.get(url, { params, signal: controller.signal })
        if (disposed || current !== sequence || controller.signal.aborted) return
        const records = Array.isArray(data) ? data : data?.data || data?.cities || []
        options.value = unique(nextPage > 1 ? [...options.value, ...records] : records)
        page.value = nextPage
        loaded.value = true
        const lastPage = Number(data?.meta?.last_page ?? data?.last_page)
        const total = Number(data?.meta?.total ?? data?.total)
        const perPage = Number(data?.meta?.per_page ?? data?.per_page ?? data?.itemsPerPage ?? 30)
        hasMore.value = Number.isFinite(lastPage) ? nextPage < lastPage
            : Number.isFinite(total) ? nextPage * perPage < total : false
    } catch (error) {
        if (!disposed && current === sequence && !controller.signal.aborted) {
            loadError.value = error.response?.data?.message || 'Не удалось загрузить варианты.'
        }
    } finally {
        if (!disposed && current === sequence) loading.value = false
    }
}

function changeSearch(value) {
    search.value = String(value || '')
    if (isLocal.value || props.disabled) return
    cancelRequest()
    hasMore.value = false
    if (focused.value || loaded.value) timer = setTimeout(() => loadOptions(1), 250)
}

function focus(value) {
    focused.value = value
    if (value && !loaded.value && !loading.value) loadOptions(1)
}

function loadMore() {
    if (!loading.value && hasMore.value) return loadOptions(page.value + 1)
}

function retry() {
    return loadOptions(failedPage)
}

watch(() => props.kind, () => {
    cancelRequest()
    options.value = []
    loaded.value = false
    loadError.value = ''
    page.value = 0
    hasMore.value = false
    search.value = ''
    if (focused.value) loadOptions(1)
})
watch(() => props.disabled, disabled => { if (disabled) cancelRequest() })
onBeforeUnmount(() => { disposed = true; cancelRequest() })
defineExpose({ flushPending })
</script>

<template>
    <div class="unit-create-relation">
        <component
            :is="control"
            :model-value="modelValue"
            :search="search"
            :items="items"
            :item-title="title"
            item-value="id"
            :label="label"
            :hint="hint"
            :persistent-hint="Boolean(hint)"
            :error-messages="errorMessages"
            :disabled="disabled"
            :loading="loading"
            :menu-props="{ maxHeight: 320 }"
            no-filter
            return-object
            multiple
            chips
            closable-chips
            hide-selected
            clearable
            variant="outlined"
            density="compact"
            hide-details="auto"
            @update:model-value="select"
            @update:search="changeSearch"
            @update:focused="focus"
        >
            <template #item="{ props: itemProps, item }">
                <v-list-item v-bind="itemProps" :subtitle="subtitle(item.raw)" />
            </template>
            <template #no-data>
                <div class="unit-create-relation__empty">
                    {{ loading ? 'Загружаем варианты…' : allowCreate && search.trim() ? 'Нажмите Enter, чтобы добавить новый контакт.' : 'Ничего не найдено. Уточните поиск.' }}
                </div>
            </template>
            <template #append-item>
                <v-btn v-if="hasMore" class="unit-create-relation__more" size="small" variant="text" block :loading="loading" :disabled="disabled || loading" @click.stop="loadMore">Показать ещё</v-btn>
            </template>
        </component>
        <div v-if="loadError" class="unit-create-relation__error" role="alert">
            <span>{{ loadError }}</span>
            <button type="button" :disabled="loading || disabled" @click="retry">Повторить</button>
        </div>
    </div>
</template>

<style scoped>
.unit-create-relation { min-width: 0; }
.unit-create-relation :deep(.v-field__input) { min-width: 0; }
.unit-create-relation :deep(.v-chip) { max-width: 100%; }
.unit-create-relation :deep(.v-chip__content) { overflow: hidden; text-overflow: ellipsis; }
.unit-create-relation__empty { padding: 12px 16px; color: rgb(var(--v-theme-on-surface)); opacity: .65; font-size: 12px; }
.unit-create-relation__more { margin: 4px 0; text-transform: none; letter-spacing: 0; }
.unit-create-relation__error { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 5px; color: rgb(var(--v-theme-error)); font-size: 12px; }
.unit-create-relation__error button { text-decoration: underline; text-underline-offset: 3px; }
.unit-create-relation__error button:disabled { opacity: .5; }
</style>
