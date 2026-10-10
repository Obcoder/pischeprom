<script setup>
import axios from 'axios'
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import { descendantIds } from './tree.js'
import { goodTradeCodeFields, goodTradeCodeValues } from '../../utils/goodTradeCodes.js'
import CatalogGoodOverview from './CatalogGoodOverview.vue'
import CatalogGoodSeo from './CatalogGoodSeo.vue'
import CatalogGoodOperations from './CatalogGoodOperations.vue'
import CatalogRecordTabs from './CatalogRecordTabs.vue'
import CatalogLandingEditor from './CatalogLandingEditor.vue'
import GoodTradeCodeFields from '../Goods/GoodTradeCodeFields.vue'
import { goodRecordTabs } from './recordTabs.js'

const open = defineModel({ type: Boolean, default: false })
const props = defineProps({
    node: { type: Object, default: null },
    levels: { type: Array, default: () => [] },
    nodes: { type: Array, default: () => [] },
    initialParentId: { type: Number, default: null },
    initialLevelId: { type: Number, default: null },
    initialEntityType: { type: String, default: 'custom' },
    initialTab: { type: String, default: 'overview' },
})
const emit = defineEmits(['saved', 'deleted', 'changed', 'schema'])
const form = reactive({ properties: {} })
const record = ref(null)
const saving = ref(false)
const error = ref('')
const errors = ref({})
const imageFile = ref(null)
const baseline = ref('')
const discardOpen = ref(false)
const deleteOpen = ref(false)
const previousLevelId = ref(null)
const goodForm = reactive({})
const goodOverview = ref(null)
const goodOptions = ref({})
const goodLoading = ref(false)
const goodReady = ref(false)
const goodError = ref('')
const goodBaseline = ref('{}')
const resetOpen = ref(false)
const activeTab = ref('overview')
const seoVisited = ref(false)
const operationsVisited = ref(false)
const landingVisited = ref(false)
const landingEditor = ref(null)
const landingState = ref({ dirty: false, busy: false })
const landingDraftMessage = 'В лендинге есть несохранённые изменения. Сохраните черновик во вкладке «Лендинг» или сбросьте изменения перед сохранением карточки.'
const seoEditor = ref(null)
const operations = ref(null)
const seoState = ref({ dirty: false, busy: false, ready: false, error: '' })
const operationsState = ref({ dirty: false, busy: false })
const operationsDraftMessage = 'В рабочем разделе есть несохранённые изменения. Сохраните их кнопкой этого раздела или сбросьте перед сохранением карточки.'
const partialErrorPhase = ref(null)
let overviewVersion = 0
let overviewController = null
let inferredProductId = null
let productsEdited = false
let propertyDrafts = {}
let propertyBaselines = {}
let schemaSnapshot = {}
const entityTypes = [
    { title: 'Произвольный объект', value: 'custom' }, { title: 'Категория', value: 'category' },
    { title: 'Продукт', value: 'product' }, { title: 'Товар', value: 'good' },
]
const currentLevel = computed(() => props.levels.find(level => level.id === form.level_id))
const levelOptions = computed(() => [{ id: null, name: 'Без уровня' }, ...props.levels])
const isGood = computed(() => form.entity_type === 'good')
const busy = computed(() => saving.value || seoState.value.busy || operationsState.value.busy || landingState.value.busy)
const mainTab = computed(() => activeTab.value === 'overview')
const operationsTab = computed(() => isGood.value && !['overview', 'seo', 'landing'].includes(activeTab.value))
const recordTabs = computed(() => isGood.value ? goodRecordTabs : goodRecordTabs.filter(tab => ['overview', 'landing'].includes(tab.id)))
const seoGood = computed(() => ({
    ...goodOverview.value, ...goodTradeCodeValues(goodForm),
    id: record.value?.entity_id, name: form.name, slug: form.slug,
    description: form.description, is_published: form.is_published, ava_image: form.image,
    ava_thumb: goodOverview.value?.ava_thumb || form.image,
    denominator: goodForm.denominator ?? null,
    country_id: goodForm.country_id ?? null,
    country: (goodOptions.value.countries || []).find(country => String(country.id) === String(goodForm.country_id)) || null,
    vat_rate_id: goodForm.vat_rate_id ?? null,
    vat_rate: (goodOptions.value.vat_rates || []).find(rate => String(rate.id) === String(goodForm.vat_rate_id)) || null,
    products: (goodOptions.value.products || []).filter(product => (goodForm.products || []).some(id => String(id) === String(product.id))),
    level_name: currentLevel.value?.name || null,
    seo_properties: (currentLevel.value?.fields || [])
        .filter(field => field.is_public && ['string', 'number', 'boolean'].includes(typeof form.properties?.[field.key]) && form.properties[field.key] !== '')
        .map(field => ({ name: field.label || field.key, value: form.properties[field.key] })),
}))
const cardDirty = computed(() => JSON.stringify(form) !== baseline.value || Boolean(selectedFile.value)
    || (isGood.value && (JSON.stringify(goodForm) !== goodBaseline.value || seoState.value.dirty || operationsState.value.dirty)))
const dirty = computed(() => cardDirty.value || landingState.value.dirty)
const canSave = computed(() => !busy.value && (!isGood.value || goodReady.value))
const goodErrors = computed(() => Object.fromEntries(Object.entries(errors.value).filter(([key]) => key.startsWith('good.')).map(([key, value]) => [key.slice(5), value])))
const selectedFile = computed(() => Array.isArray(imageFile.value) ? imageFile.value[0] : imageFile.value)
const blockedParents = computed(() => record.value ? descendantIds(props.nodes, record.value.id) : new Set())
const byId = computed(() => new Map(props.nodes.map(node => [node.id, node])))
const pathFor = id => {
    const result = []
    const visited = new Set()
    let item = byId.value.get(id)
    while (item && !visited.has(item.id)) {
        result.unshift(item.name)
        visited.add(item.id)
        item = byId.value.get(item.parent_id)
    }
    return result.join(' / ')
}
const parentOptions = computed(() => [{ id: null, name: 'Корень каталога' }, ...props.nodes
    .filter(node => !blockedParents.value.has(node.id))
    .map(node => ({ id: node.id, name: pathFor(node.id) }))
    .sort((a, b) => a.name.localeCompare(b.name, 'ru'))])
const parentPath = computed(() => pathFor(form.parent_id))
const fieldErrors = key => errors.value[key] || []
const clone = value => JSON.parse(JSON.stringify(value && !Array.isArray(value) ? value : {}))
const draftKey = id => id == null ? 'unclassified' : String(id)
const definitions = () => Object.fromEntries(props.levels.map(level => [String(level.id),
    (level.fields || []).map(field => ({ id: field.id, key: field.key, type: field.type })),
]))
const own = (value, key) => Object.prototype.hasOwnProperty.call(value, key)
const same = (first, second) => JSON.stringify(first) === JSON.stringify(second)
function booleanDefaults(values, fields) {
    for (const field of fields) if (field.type === 'boolean' && values[field.key] == null) values[field.key] = false
    return values
}

function nearestProductId() {
    let node = byId.value.get(form.parent_id)
    const seen = new Set()
    while (node && !seen.has(node.id)) {
        if (node.entity_type === 'product') return node.entity_id
        seen.add(node.id)
        node = byId.value.get(node.parent_id)
    }
    return null
}
function defaultGood(source = {}) {
    return {
        incoming_code: source.incoming_code ?? null,
        measure_id: source.measure_id ?? source.measurement?.measure_id ?? null,
        unit_weight_kg: source.unit_weight_kg ?? null,
        existing_price_basis: 'selected_unit',
        denominator: source.denominator ?? null, country_id: source.country_id ?? source.country?.id ?? null,
        vat_rate_id: source.vat_rate_id ?? source.vat_rate?.id ?? null,
        products: (source.products || []).map(product => typeof product === 'object' ? product.id : product),
        fields: (source.fields || []).map(field => typeof field === 'object' ? field.id : field),
        ...goodTradeCodeValues(source), remove_ava: false,
        avatar_source_url: /^https?:\/\//i.test(source.ava_image || '') ? source.ava_image : '',
        avatar_thumb_source_url: /^https?:\/\//i.test(source.ava_thumb || '') ? source.ava_thumb : '',
    }
}
function cancelGoodOverview() {
    overviewVersion++
    overviewController?.abort()
    overviewController = null
    goodLoading.value = false
}
async function loadGoodOverview() {
    cancelGoodOverview()
    goodReady.value = false; goodError.value = ''; goodOverview.value = null; goodOptions.value = {}
    inferredProductId = null; productsEdited = false
    Object.assign(goodForm, defaultGood())
    goodBaseline.value = JSON.stringify(goodForm)
    if (!open.value || !isGood.value) return
    const version = overviewVersion
    const current = new AbortController()
    overviewController = current
    goodLoading.value = true
    const nodeId = record.value?.id
    try {
        const [metadata, overview] = await Promise.all([
            axios.get('/api/goods', { params: { view: 'filters' }, signal: current.signal }),
            nodeId ? axios.get(`/api/catalog/nodes/${nodeId}/overview`, { signal: current.signal }) : Promise.resolve(null),
        ])
        if (version !== overviewVersion || !open.value || !isGood.value) return
        const source = overview?.data?.data
        const options = metadata.data?.data || metadata.data
        if (!options || !['products', 'categories', 'countries', 'fields', 'vat_rates'].every(key => Array.isArray(options[key]))
            || (nodeId && (!source || String(source.id) !== String(record.value.entity_id)))) throw new Error('incomplete overview')
        goodOverview.value = source || null
        goodOptions.value = options
        Object.assign(goodForm, defaultGood(source || {}))
        if (!nodeId) {
            goodForm.measure_id = (options.measures || []).find(measure => ['кг', 'kg'].includes(String(measure.name).trim().toLowerCase()))?.id ?? null
            inferredProductId = nearestProductId()
            goodForm.products = inferredProductId ? [inferredProductId] : []
        }
        goodBaseline.value = JSON.stringify(goodForm)
        goodReady.value = true
    } catch (failure) {
        if (version !== overviewVersion || !open.value) return
        goodError.value = failure.response?.data?.message || 'Не удалось загрузить данные товара. Повторите загрузку перед сохранением.'
    } finally {
        if (version === overviewVersion) { goodLoading.value = false; overviewController = null }
    }
}
function goodPayload() {
    const previous = JSON.parse(goodBaseline.value)
    const payload = {}
    for (const key of ['incoming_code', 'measure_id', 'unit_weight_kg', 'denominator', 'country_id', 'vat_rate_id', ...goodTradeCodeFields.map(field => field.key)]) {
        if (!record.value || !same(goodForm[key], previous[key])) payload[key] = goodForm[key] === '' ? null : goodForm[key]
    }
    if (record.value && payload.measure_id && !goodOverview.value?.measure_id && !goodOverview.value?.measurement?.measure_id && goodOverview.value?.counts?.prices > 0) {
        payload.existing_price_basis = goodForm.existing_price_basis || 'selected_unit'
    }
    for (const key of ['products', 'fields']) {
        const values = [...new Set(goodForm[key] || [])]
        const original = [...(previous[key] || [])]
        const compareIds = list => list.map(String).sort().join(',')
        if (!record.value || compareIds(values) !== compareIds(original)) payload[key] = values
    }
    if (!selectedFile.value) {
        if (goodForm.remove_ava) payload.remove_ava = true
        else for (const key of ['avatar_source_url', 'avatar_thumb_source_url']) {
            if (goodForm[key] !== previous[key]) payload[key] = goodForm[key] || null
        }
    }
    return payload
}
function updateGoodAvatar(value) {
    goodForm.avatar_source_url = value || ''
    form.image = value || ''
    const previous = JSON.parse(goodBaseline.value)
    if (goodForm.avatar_thumb_source_url === previous.avatar_thumb_source_url) goodForm.avatar_thumb_source_url = ''
}
function removeGoodAvatar(value) {
    goodForm.remove_ava = value
    if (value) imageFile.value = null
    form.image = value ? '' : goodForm.avatar_source_url || goodOverview.value?.ava_image || ''
}
function changeParent() {
    if (!isGood.value || record.value) return
    const productId = nearestProductId()
    const previousDefault = inferredProductId ? [inferredProductId] : []
    if (!productsEdited && same(goodForm.products, previousDefault)) goodForm.products = productId ? [productId] : []
    else if (productId && !goodForm.products.includes(productId)) goodForm.products.push(productId)
    inferredProductId = productId
}
function updateGoodForm(values) {
    if (!same(goodForm.products, values.products)) productsEdited = true
    Object.assign(goodForm, values)
}
function requestReset() {
    if (busy.value) return
    if (dirty.value) resetOpen.value = true
    else resetCurrent()
}
function resetCurrent() {
    const tab = activeTab.value
    seoEditor.value?.reset()
    operations.value?.reset()
    landingEditor.value?.reset()
    reset(record.value || props.node, false)
    activeTab.value = tab
}
function confirmReset() { resetOpen.value = false; resetCurrent() }
function selectTab(tab) {
    if (!recordTabs.value.some(item => item.id === tab)) return
    activeTab.value = tab
    if (tab === 'seo') seoVisited.value = true
    else if (tab === 'landing') landingVisited.value = true
    else if (tab !== 'overview') operationsVisited.value = true
}
function updateLandingState(state) {
    landingState.value = state
    if (!state.dirty && error.value === landingDraftMessage) error.value = ''
}
function childSaved() {
    if (partialErrorPhase.value === 'seo' && !seoState.value.dirty) { error.value = ''; partialErrorPhase.value = null }
    if (record.value) emit('changed', record.value)
}
function updateOperationsState(state) {
    operationsState.value = state
    if (!state.dirty && error.value === operationsDraftMessage) error.value = ''
}
function operationsChanged(good) {
    // Media actions update the shared avatar without discarding another tab's draft.
    if (good && String(good.id) === String(record.value?.entity_id)) {
        if (own(good, 'ava_image') || own(good, 'ava_thumb')) record.value = { ...record.value, image: good.ava_image || good.ava_thumb || '' }
        const clean = JSON.parse(baseline.value)
        const extras = JSON.parse(goodBaseline.value)
        if (form.image === clean.image && !selectedFile.value && !goodForm.remove_ava
            && goodForm.avatar_source_url === extras.avatar_source_url && goodForm.avatar_thumb_source_url === extras.avatar_thumb_source_url) {
            form.image = good.ava_image || good.ava_thumb || ''
            clean.image = form.image
            baseline.value = JSON.stringify(clean)
            const refreshed = defaultGood(good)
            for (const key of ['avatar_source_url', 'avatar_thumb_source_url']) { goodForm[key] = refreshed[key]; extras[key] = refreshed[key] }
            goodBaseline.value = JSON.stringify(extras)
        }
        goodOverview.value = { ...goodOverview.value, ava_image: good.ava_image, ava_thumb: good.ava_thumb,
            updated_at: good.updated_at, counts: {
                prices: (good.price_type_values || good.priceTypeValues || []).length,
                purchases: (good.purchases || []).length, quotations: (good.quotations || []).length,
                sales: (good.sales || []).length, media: (good.media || []).length,
            } }
    }
    childSaved()
}

function reset(node = props.node, resetTabs = true) {
    record.value = node
    Object.keys(form).forEach(key => delete form[key])
    Object.assign(form, {
        level_id: node ? node.level_id ?? null : props.initialLevelId,
        entity_type: node ? node.entity_type || 'custom' : props.initialEntityType || 'custom',
        parent_id: node ? node.parent_id ?? null : props.initialParentId,
        name: node?.name || '', slug: node?.slug || '', image: node?.image || '',
        description: node?.description || '', h1: node?.h1 || '', meta_title: node?.meta_title || '',
        meta_description: node?.meta_description || '',
        is_published: node?.is_published ?? false, is_featured: node?.is_featured ?? false,
        sort_order: node?.sort_order ?? 0, properties: clone(node?.properties),
    })
    propertyDrafts = clone(node?.properties_by_level)
    previousLevelId.value = form.level_id
    fillBooleanDefaults()
    propertyBaselines = { ...clone(propertyDrafts), [draftKey(form.level_id)]: clone(form.properties) }
    schemaSnapshot = definitions()
    if (!node && currentLevel.value?.is_domain) form.parent_id = null
    error.value = ''; errors.value = {}; imageFile.value = null
    partialErrorPhase.value = null
    discardOpen.value = false; deleteOpen.value = false; resetOpen.value = false
    baseline.value = JSON.stringify(form)
    if (resetTabs) {
        seoVisited.value = false; operationsVisited.value = false; landingVisited.value = false
        landingState.value = { dirty: false, busy: false }
        seoState.value = { dirty: false, busy: false, ready: false, error: '' }
        operationsState.value = { dirty: false, busy: false }
        selectTab(recordTabs.value.some(tab => tab.id === props.initialTab) ? props.initialTab : 'overview')
    }
    loadGoodOverview()
}
watch(open, value => { if (value) reset(); else cancelGoodOverview() }, { immediate: true, flush: 'sync' })
watch(() => props.node?.id, (id, previous) => { if (open.value && id !== previous) reset() }, { flush: 'sync' })
watch([definitions, () => props.nodes], reconcileProperties)
watch(selectedFile, value => { if (value && isGood.value) goodForm.remove_ava = false })
onScopeDispose(cancelGoodOverview)

function reconcileProperties() {
    if (!open.value || !baseline.value) return
    const nextSchema = definitions()
    const remap = (values, levelKey) => {
        const previousFields = new Map((schemaSnapshot[levelKey] || []).map(field => [field.id, field]))
        const mapped = {}
        for (const field of nextSchema[levelKey] || []) {
            const oldKey = previousFields.get(field.id)?.key ?? field.key
            if (own(values, oldKey)) mapped[field.key] = values[oldKey]
            else if (own(values, field.key)) mapped[field.key] = values[field.key]
        }
        return booleanDefaults(mapped, nextSchema[levelKey] || [])
    }
    // Field IDs survive renames. Reconcile every cached level, including the
    // clean baseline, so a schema reload cannot erase a user's unsaved values.
    for (const key of Object.keys(propertyDrafts)) propertyDrafts[key] = remap(propertyDrafts[key], key)
    for (const key of Object.keys(propertyBaselines)) propertyBaselines[key] = remap(propertyBaselines[key], key)
    const activeKey = draftKey(form.level_id)
    form.properties = remap(form.properties, activeKey)
    const original = JSON.parse(baseline.value)
    original.properties = remap(original.properties, draftKey(original.level_id))
    const fresh = props.nodes.find(node => node.id === record.value?.id)
    if (fresh && fresh !== record.value) {
        const confirmed = { ...clone(fresh.properties_by_level), [draftKey(fresh.level_id)]: clone(fresh.properties) }
        for (const [key, values] of Object.entries(confirmed)) {
            const fields = nextSchema[key] || []
            const received = remap(clone(values), key)
            const current = key === activeKey ? form.properties : propertyDrafts[key] || {}
            const clean = propertyBaselines[key] || {}
            for (const field of fields) {
                if (same(current[field.key], clean[field.key])) {
                    if (own(received, field.key)) current[field.key] = received[field.key]
                    else delete current[field.key]
                }
            }
            if (key !== activeKey) propertyDrafts[key] = current
            propertyBaselines[key] = received
        }
        original.properties = clone(propertyBaselines[draftKey(original.level_id)])
        record.value = fresh
    }
    schemaSnapshot = nextSchema
    baseline.value = JSON.stringify(original)
}

function changeLevel() {
    propertyDrafts[draftKey(previousLevelId.value)] = clone(form.properties)
    form.properties = clone(propertyDrafts[draftKey(form.level_id)])
    previousLevelId.value = form.level_id
    fillBooleanDefaults()
    propertyBaselines[draftKey(form.level_id)] ??= clone(form.properties)
    if (currentLevel.value?.is_domain) form.parent_id = null
    errors.value = {}
}
function fillBooleanDefaults() {
    booleanDefaults(form.properties, currentLevel.value?.fields || [])
}
function requestClose() {
    if (busy.value) return
    if (dirty.value) discardOpen.value = true
    else open.value = false
}
function discard() { discardOpen.value = false; open.value = false }

async function save(nextTab = null) {
    if (!canSave.value) return
    if (landingState.value.dirty) {
        error.value = landingDraftMessage
        selectTab('landing')
        return
    }
    if (operationsState.value.dirty) {
        error.value = operationsDraftMessage
        selectTab(operationsState.value.dirtyTab || 'recommendations')
        return
    }
    if (isGood.value && seoState.value.dirty && !seoEditor.value?.validate()) { selectTab('seo'); return }
    saving.value = true; error.value = ''; errors.value = {}; partialErrorPhase.value = null
    let saved = null
    let phase = 'record'
    try {
        const payload = { ...form, h1: form.h1?.trim() || null, properties: {} }
        if (record.value) delete payload.entity_type
        if (isGood.value) {
            delete payload.image
            delete payload.h1
            delete payload.meta_title
            delete payload.meta_description
            const extras = goodPayload()
            if (Object.keys(extras).length) payload.good = extras
        }
        for (const field of currentLevel.value?.fields || []) {
            const value = form.properties[field.key]
            payload.properties[field.key] = value === '' || value === undefined ? null
                : field.type === 'number' ? Number(value) : value
        }
        const { data } = record.value
            ? await axios.patch(`/api/catalog/nodes/${record.value.id}`, payload)
            : await axios.post('/api/catalog/nodes', payload)
        saved = data.data
        record.value = saved
        // Relationship edits can relocate this placement on the server. Keep
        // the confirmed parent for retries if the subsequent avatar upload fails.
        if (own(saved, 'parent_id')) form.parent_id = saved.parent_id ?? null
        if (own(saved, 'slug')) form.slug = saved.slug || ''
        if (own(saved, 'image')) form.image = saved.image || ''
        if (own(saved, 'h1')) form.h1 = saved.h1 || ''
        baseline.value = JSON.stringify(form)
        goodBaseline.value = JSON.stringify(goodForm)
        if (selectedFile.value) {
            phase = 'image'
            const body = new FormData()
            body.append('image', selectedFile.value)
            const uploaded = await axios.post(`/api/catalog/nodes/${saved.id}/image`, body)
            saved = uploaded.data.data
            imageFile.value = null
        }
        if (isGood.value && seoState.value.dirty) {
            phase = 'seo'
            if (!await seoEditor.value.save()) throw new Error(seoState.value.error || 'Не удалось сохранить SEO.')
        }
        if (operationsVisited.value) operations.value?.refresh()
        if (typeof nextTab === 'string' && recordTabs.value.some(tab => tab.id === nextTab)) {
            await loadGoodOverview()
            selectTab(nextTab)
        } else open.value = false
        emit('saved', saved)
    } catch (failure) {
        errors.value = failure.response?.data?.errors || {}
        const message = Object.values(errors.value).flat().join(' ') || failure.response?.data?.message || failure.message || 'Не удалось сохранить запись.'
        error.value = saved ? `${phase === 'seo' ? 'Основные данные сохранены, но SEO не сохранено.' : 'Запись сохранена, но аватар не загружен.'} ${message}` : message
        partialErrorPhase.value = saved ? phase : null
        if (phase === 'seo') selectTab('seo')
        else if (!saved && Object.keys(errors.value).length) selectTab('overview')
        if (saved) emit('changed', saved)
    } finally { saving.value = false }
}
async function remove() {
    if (!record.value || busy.value) return
    saving.value = true; error.value = ''
    try {
        const id = record.value.id
        await axios.delete(`/api/catalog/nodes/${id}`)
        deleteOpen.value = false; open.value = false
        emit('deleted', id)
    } catch (failure) {
        error.value = failure.response?.data?.message || 'Не удалось удалить запись.'
        deleteOpen.value = false
    } finally { saving.value = false }
}
</script>

<template>
    <v-dialog :model-value="open" :max-width="isGood ? 1640 : 1380" :content-class="isGood ? 'catalog-node-dialog-overlay--good' : undefined" scrollable :persistent="busy" @update:model-value="value => { if (!value) requestClose() }">
        <v-card class="catalog-node-dialog" :class="{ 'catalog-node-dialog--good': isGood }">
            <v-card-title class="catalog-node-dialog__heading">
                <div class="catalog-node-dialog__title"><span>{{ isGood ? 'Карточка товара' : record ? 'Карточка записи' : 'Новая запись' }}</span><h2>{{ record?.name || 'Добавление в каталог' }}</h2></div>
                <v-chip v-if="dirty" size="small" variant="tonal" class="catalog-node-dialog__dirty">Не сохранено</v-chip>
                <v-btn icon="mdi-close" variant="text" :disabled="busy" aria-label="Закрыть карточку" @click="requestClose" />
            </v-card-title>
            <v-divider />
            <CatalogRecordTabs :model-value="activeTab" :good="isGood" @update:model-value="selectTab" />
            <v-card-text class="catalog-node-dialog__body">
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-5">{{ error }}</v-alert>
                <v-form v-show="mainTab" id="catalog-node-form" :disabled="busy" @submit.prevent="save">
                    <div class="catalog-node-dialog__columns">
                        <section class="catalog-node-dialog__section">
                            <h3><v-icon icon="mdi-file-tree-outline" size="18" /> Название и классификация</h3>
                            <v-text-field v-model="form.name" label="Название *" variant="outlined" density="compact" :error-messages="fieldErrors('name')" required maxlength="255" />
                            <v-text-field v-if="isGood" v-model="form.slug" label="Основной адрес товара" prefix="/g/" variant="outlined" density="compact" maxlength="255" :error-messages="fieldErrors('slug')" hint="Задайте вручную. SEO-адрес и Canonical формируются из него." persistent-hint class="mb-2" />
                            <v-select v-if="!record" v-model="form.entity_type" :items="entityTypes" label="Вид записи" variant="outlined" density="compact" :error-messages="fieldErrors('entity_type')" hint="Товар и продукт доступны в учёте. Для своей классификации выберите произвольный объект." persistent-hint class="mb-3" @update:model-value="loadGoodOverview" />
                            <div v-else class="catalog-node-dialog__identity"><v-chip size="small" variant="tonal">{{ entityTypes.find(type => type.value === (record.entity_type || 'custom'))?.title }}</v-chip><span v-if="record.entity_id">№ {{ record.entity_id }}</span></div>
                            <v-select v-model="form.level_id" :items="levelOptions" item-title="name" item-value="id" label="Уровень классификации" variant="outlined" density="compact" :error-messages="fieldErrors('level_id')" hint="Любой уровень можно пропустить или назначить позже." persistent-hint class="mb-3" @update:model-value="changeLevel" />
                            <v-autocomplete v-model="form.parent_id" :items="parentOptions" item-title="name" item-value="id" label="Расположение в каталоге" variant="outlined" density="compact" :disabled="currentLevel?.is_domain || saving" :hint="currentLevel?.is_domain ? 'Домены располагаются в корне каталога.' : ''" :persistent-hint="currentLevel?.is_domain" :error-messages="fieldErrors('parent_id')" @update:model-value="changeParent" />
                            <p v-if="parentPath" class="catalog-node-dialog__path">{{ parentPath }}</p>
                            <v-text-field v-model.number="form.sort_order" label="Порядок в ветке" type="number" min="0" max="1000000" variant="outlined" density="compact" :error-messages="fieldErrors('sort_order')" />
                            <GoodTradeCodeFields v-if="isGood && goodReady" class="catalog-node-dialog__trade-codes" compact :model-value="goodForm" :context="seoGood" :errors="goodErrors" :disabled="busy" :active="open && mainTab && goodReady" @update:model-value="updateGoodForm" />
                            <div class="catalog-node-dialog__publication">
                                <h3><v-icon icon="mdi-web" size="18" /> Отображение на сайте</h3>
                                <v-switch v-model="form.is_published" label="Опубликовано" color="#4d315e" density="compact" hide-details />
                                <v-switch v-model="form.is_featured" label="Показывать на витрине" color="#4d315e" density="compact" hide-details />
                                <p>Витрина показывает опубликованные записи. Скрытая родительская ветка скрывает вложенные записи.</p>
                            </div>
                        </section>
                        <section class="catalog-node-dialog__section">
                            <h3><v-icon icon="mdi-text-box-outline" size="18" /> Содержание</h3>
                            <v-textarea v-model="form.description" label="Описание" variant="outlined" density="compact" :rows="isGood ? 3 : 5" :error-messages="fieldErrors('description')" />
                            <div class="catalog-node-dialog__avatar">
                                <v-img v-if="form.image" :src="form.image" width="88" height="88" cover rounded="lg" />
                                <div v-else class="catalog-node-dialog__avatar-empty"><v-icon icon="mdi-image-outline" size="30" /></div>
                                <p>Аватар записи<small>Для каталога и витрины</small></p>
                            </div>
                            <template v-if="isGood">
                                <v-text-field :model-value="goodForm.avatar_source_url" label="Аватар: URL / CDN" type="url" variant="outlined" density="compact" clearable :disabled="!goodReady || saving || Boolean(selectedFile) || goodForm.remove_ava" :error-messages="goodErrors.avatar_source_url" @update:model-value="updateGoodAvatar" />
                                <v-text-field v-model="goodForm.avatar_thumb_source_url" label="Миниатюра: URL / CDN" type="url" variant="outlined" density="compact" clearable :disabled="!goodReady || saving || Boolean(selectedFile) || goodForm.remove_ava" :error-messages="goodErrors.avatar_thumb_source_url" />
                            </template>
                            <v-text-field v-else v-model="form.image" label="Ссылка на изображение" variant="outlined" density="compact" clearable :error-messages="fieldErrors('image')" />
                            <v-file-input v-model="imageFile" label="Загрузить аватар" accept="image/jpeg,image/png,image/webp,image/gif" variant="outlined" density="compact" :error-messages="fieldErrors('image')" hint="JPG, PNG, WebP или GIF до 5 МБ" persistent-hint :disabled="saving || (isGood && !goodReady)" />
                            <v-checkbox v-if="isGood" :model-value="goodForm.remove_ava" label="Удалить аватар" color="error" density="compact" hide-details :disabled="!goodReady || saving" :error-messages="goodErrors.remove_ava" @update:model-value="removeGoodAvatar" />
                        </section>
                        <section v-if="!isGood" class="catalog-node-dialog__section catalog-node-dialog__seo">
                            <h3><v-icon icon="mdi-magnify" size="18" /> Поиск и SEO</h3>
                            <v-text-field v-model="form.slug" label="Адрес страницы" variant="outlined" density="compact" :error-messages="fieldErrors('slug')" hint="Латинские буквы, цифры и дефисы. Пустое значение создаст адрес автоматически." persistent-hint class="mb-3" />
                            <v-text-field v-model="form.h1" label="SEO H1" variant="outlined" density="compact" maxlength="255" clearable :error-messages="fieldErrors('h1')" hint="Главный заголовок публичной страницы. Если поле пустое, используется заголовок лендинга или название записи." persistent-hint class="mb-3" />
                            <v-text-field v-model="form.meta_title" label="Заголовок · Title" variant="outlined" density="compact" maxlength="255" :error-messages="fieldErrors('meta_title')" />
                            <v-textarea v-model="form.meta_description" label="Описание · Description" variant="outlined" density="compact" rows="5" maxlength="2000" :error-messages="fieldErrors('meta_description')" />
                            <div v-if="record?.public_url || record?.edit_url" class="catalog-node-dialog__links">
                                <v-btn v-if="record?.public_url && record?.is_published" :href="record.public_url" target="_blank" rel="noopener" variant="text" prepend-icon="mdi-open-in-new" size="small">Открыть на сайте</v-btn>
                                <v-btn v-if="record?.edit_url" :href="record.edit_url" target="_blank" rel="noopener" variant="text" prepend-icon="mdi-card-text-outline" size="small">Карточка в учёте</v-btn>
                            </div>
                        </section>
                    </div>
                    <div v-if="isGood && !goodReady" class="catalog-node-dialog__overview-loading" role="status"><v-progress-circular v-if="goodLoading" indeterminate size="20" width="2" color="#806592" /><v-icon v-else icon="mdi-alert-circle-outline" size="20" /><span>{{ goodLoading ? 'Загрузка данных товара…' : goodError }}</span><v-btn v-if="!goodLoading" variant="text" size="small" @click="loadGoodOverview">Повторить</v-btn></div>
                    <CatalogGoodOverview v-if="isGood && goodReady" :model-value="goodForm" :overview="goodOverview" :options="goodOptions" :context="form" :errors="goodErrors" :disabled="saving" :active="open && mainTab && goodReady" :edit-url="record?.edit_url || ''" :inline-navigation="Boolean(record)" @update:model-value="updateGoodForm" @navigate="selectTab" />
                    <section class="catalog-node-dialog__properties">
                        <div class="catalog-node-dialog__properties-heading"><h3>Свойства<span v-if="currentLevel"> · {{ currentLevel.name }}</span></h3><v-btn variant="text" size="small" prepend-icon="mdi-tune-variant" :disabled="saving" @click="emit('schema')">Уровни и поля</v-btn></div>
                        <p v-if="!currentLevel" class="catalog-node-dialog__hint">Выберите уровень, чтобы заполнить его дополнительные свойства.</p>
                        <p v-else-if="!currentLevel.fields?.length" class="catalog-node-dialog__hint">У этого уровня пока нет дополнительных полей.</p>
                        <div v-else class="catalog-node-dialog__property-grid">
                            <template v-for="field in currentLevel.fields" :key="field.id">
                                <v-checkbox v-if="field.type === 'boolean'" v-model="form.properties[field.key]" :label="field.label + (field.required ? ' *' : '')" :error-messages="fieldErrors(`properties.${field.key}`)" density="compact" />
                                <v-textarea v-else-if="field.type === 'textarea'" v-model="form.properties[field.key]" :label="field.label + (field.required ? ' *' : '')" variant="outlined" density="compact" rows="3" :error-messages="fieldErrors(`properties.${field.key}`)" />
                                <v-select v-else-if="field.type === 'select'" v-model="form.properties[field.key]" :items="field.options || []" :label="field.label + (field.required ? ' *' : '')" variant="outlined" density="compact" clearable :error-messages="fieldErrors(`properties.${field.key}`)" />
                                <v-text-field v-else v-model="form.properties[field.key]" :label="field.label + (field.required ? ' *' : '')" :type="['number', 'date', 'url'].includes(field.type) ? field.type : 'text'" :step="field.type === 'number' ? 'any' : undefined" variant="outlined" density="compact" :error-messages="fieldErrors(`properties.${field.key}`)" />
                            </template>
                        </div>
                    </section>
                </v-form>
                <section v-if="!record && !mainTab" class="catalog-node-dialog__create-first">
                    <v-icon :icon="activeTab === 'seo' ? 'mdi-magnify' : 'mdi-package-variant-closed'" size="30" />
                    <h3>{{ isGood ? 'Сначала сохраните новый товар' : 'Сначала сохраните запись каталога' }}</h3>
                    <p>После создания здесь появятся все инструменты выбранного раздела.</p>
                    <v-btn variant="flat" color="#4d315e" :disabled="!canSave || !form.name.trim()" :loading="saving" @click="save(activeTab)">{{ isGood ? 'Создать товар и продолжить' : 'Создать запись и продолжить' }}</v-btn>
                    <v-btn variant="text" @click="selectTab('overview')">Основные данные</v-btn>
                </section>
                <CatalogGoodSeo v-if="isGood && record && seoVisited" v-show="activeTab === 'seo'" ref="seoEditor" :good="seoGood" :active="open && activeTab === 'seo'" :disabled="saving"
                    @state="seoState = $event" @saved="childSaved" />
                <CatalogGoodOperations v-if="isGood && record && operationsVisited" v-show="operationsTab" ref="operations" :good-id="record.entity_id" :active-tab="activeTab" :active="open && operationsTab"
                    @state="updateOperationsState" @request-basics="selectTab('overview')" @changed="operationsChanged" />
                <CatalogLandingEditor v-if="open && record && landingVisited" v-show="activeTab === 'landing'" ref="landingEditor" :node="record" :active="activeTab === 'landing'" :disabled="saving" :card-dirty="cardDirty"
                    @state="updateLandingState" @saved="childSaved" @navigate="selectTab" @save-card="save('landing')" />
            </v-card-text>
            <v-divider />
            <v-card-actions class="catalog-node-dialog__actions">
                <v-btn v-if="record" color="error" variant="text" prepend-icon="mdi-delete-outline" :disabled="busy" @click="deleteOpen = true">Удалить</v-btn>
                <v-spacer /><v-btn variant="text" :disabled="busy" @click="requestReset">Сбросить</v-btn><v-btn variant="text" :disabled="busy" @click="requestClose">{{ operationsTab || activeTab === 'landing' ? 'Закрыть' : 'Отмена' }}</v-btn><v-btn v-if="!operationsTab && activeTab !== 'landing'" color="#4d315e" variant="flat" :loading="saving" :disabled="!canSave" prepend-icon="mdi-check" @click="save()">Сохранить</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog v-model="discardOpen" max-width="480"><v-card title="Есть несохранённые изменения"><v-card-text>Закрыть карточку и отменить изменения?</v-card-text><v-card-actions><v-spacer /><v-btn @click="discardOpen = false">Продолжить</v-btn><v-btn color="error" @click="discard">Отменить изменения</v-btn></v-card-actions></v-card></v-dialog>
    <v-dialog v-model="resetOpen" max-width="480"><v-card title="Сбросить изменения?"><v-card-text>Поля карточки вернутся к сохранённым значениям.</v-card-text><v-card-actions><v-spacer /><v-btn @click="resetOpen = false">Продолжить редактирование</v-btn><v-btn color="error" @click="confirmReset">Сбросить</v-btn></v-card-actions></v-card></v-dialog>
    <v-dialog v-model="deleteOpen" max-width="520" :persistent="saving"><v-card title="Удалить запись?"><v-card-text>«{{ record?.name }}» будет удалена. Сначала перенесите вложенные записи. Записи со связанными операциями защищены от удаления.</v-card-text><v-card-actions><v-spacer /><v-btn :disabled="saving" @click="deleteOpen = false">Отмена</v-btn><v-btn color="error" :loading="saving" @click="remove">Удалить</v-btn></v-card-actions></v-card></v-dialog>
</template>

<style scoped>
.catalog-node-dialog { border-radius: 14px !important; }
:global(.v-dialog > .v-overlay__content.catalog-node-dialog-overlay--good) { height: calc(100vh - 24px); height: calc(100dvh - 24px); max-height: calc(100vh - 24px); max-height: calc(100dvh - 24px); margin-block: 12px; }
:global(.v-dialog > .v-overlay__content.catalog-node-dialog-overlay--good > .catalog-node-dialog--good) { height: 100%; min-height: 0; overflow: hidden; }
.catalog-node-dialog--good > :not(.catalog-node-dialog__body) { flex: 0 0 auto; }
.catalog-node-dialog__heading { display: flex; align-items: center; gap: 16px; padding: 18px 24px; white-space: normal; }
.catalog-node-dialog__title { flex: 1; min-width: 0; }
.catalog-node-dialog__title > span { font-size: 11px; font-weight: 500; color: #8c8294; }
.catalog-node-dialog__title h2 { font-size: 19px; line-height: 1.5; }
.catalog-node-dialog__body { padding: 24px !important; background: #fdfcfe; }
.catalog-node-dialog--good .catalog-node-dialog__body { flex: 1 1 0; min-height: 0; overflow-y: auto; padding: 18px 22px !important; }
.catalog-node-dialog--good .catalog-node-dialog__columns { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
.catalog-node-dialog--good h3 { margin-bottom: 14px; }
.catalog-node-dialog--good .catalog-node-dialog__avatar { margin-bottom: 12px; }
.catalog-node-dialog__overview-loading { display: flex; align-items: center; gap: 10px; margin-top: 18px; padding: 12px 16px; background: #f5f0f8; border-radius: 8px; font-size: 12px; color: #806592; }
.catalog-node-dialog__create-first { display: flex; min-height: 320px; align-items: center; justify-content: center; flex-direction: column; gap: 14px; color: #8c8294; font-size: 13px; text-align: center; }
.catalog-node-dialog__create-first h3 { margin: 0; }
.catalog-node-dialog__columns { display: grid; grid-template-columns: 1.1fr 1fr 1fr; gap: 24px; }
.catalog-node-dialog__section { min-width: 0; }
.catalog-node-dialog h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 20px; font-size: 13px; font-weight: 650; color: #4d4058; }
.catalog-node-dialog__identity { display: flex; align-items: center; gap: 10px; margin: 0 0 18px; color: #8c8294; font-size: 12px; }
.catalog-node-dialog__path { margin: -12px 0 16px; color: #8c8294; font-size: 11px; overflow-wrap: anywhere; }
.catalog-node-dialog__trade-codes { margin-bottom: 14px; }
.catalog-node-dialog__publication { border: 1px solid #e6e0eb; border-radius: 10px; background: #f8f5fb; padding: 16px; }
.catalog-node-dialog__publication h3 { margin-bottom: 0; }
.catalog-node-dialog__publication p { font-size: 11px; line-height: 1.6; color: #8c8294; margin-top: 8px; }
.catalog-node-dialog__avatar { display: flex; align-items: center; gap: 14px; margin: 0 0 18px; }
.catalog-node-dialog__avatar :deep(.v-img) { flex: 0 0 88px; }
.catalog-node-dialog__avatar-empty { display: grid; place-items: center; width: 88px; height: 88px; border-radius: 10px; color: #ada2b7; background: #f0ebf5; }
.catalog-node-dialog__avatar p { font-size: 12px; font-weight: 550; }
.catalog-node-dialog__avatar small { display: block; margin-top: 6px; color: #8c8294; font-size: 11px; font-weight: 400; }
.catalog-node-dialog__links { display: flex; align-items: flex-start; flex-direction: column; gap: 6px; }
.catalog-node-dialog__properties { margin-top: 26px; padding-top: 18px; border-top: 1px solid #e6e0eb; }
.catalog-node-dialog__properties-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
.catalog-node-dialog__properties-heading h3 { margin: 0; display: block; }
.catalog-node-dialog__properties-heading h3 span { color: #8c8294; font-weight: 400; }
.catalog-node-dialog__property-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 24px; }
.catalog-node-dialog__hint { color: #8c8294; font-size: 12px; }
.catalog-node-dialog__actions { padding: 12px 24px; }
@media (max-width: 1050px) { .catalog-node-dialog__columns { grid-template-columns: repeat(2, minmax(0, 1fr)); } .catalog-node-dialog__seo { grid-column: 1 / -1; } .catalog-node-dialog__property-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) {
    :global(.v-dialog > .v-overlay__content.catalog-node-dialog-overlay--good) { height: calc(100vh - 16px); height: calc(100dvh - 16px); max-height: calc(100vh - 16px); max-height: calc(100dvh - 16px); margin-block: 8px; }
    .catalog-node-dialog__columns, .catalog-node-dialog--good .catalog-node-dialog__columns, .catalog-node-dialog__property-grid { grid-template-columns: 1fr; }
    .catalog-node-dialog__body { padding: 18px !important; }
    .catalog-node-dialog__heading { padding: 14px 18px; }
    .catalog-node-dialog__dirty { display: none; }
    .catalog-node-dialog__actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 8px; padding: 10px 12px; }
    .catalog-node-dialog__actions :deep(.v-spacer) { display: none; }
    .catalog-node-dialog__actions :deep(.v-btn) { width: 100%; min-width: 0; margin: 0 !important; padding-inline: 8px; font-size: 11px; letter-spacing: .02em; }
    .catalog-node-dialog__actions :deep(.v-btn__content) { white-space: normal; overflow-wrap: anywhere; }
}
</style>
