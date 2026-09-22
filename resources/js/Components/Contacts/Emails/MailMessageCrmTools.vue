<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'

const props = defineProps({
    message: { type: Object, default: null },
    disabled: Boolean,
    defaultEntityId: { type: Number, default: null },
    defaultUnitId: { type: Number, default: null },
})
const emit = defineEmits(['changed', 'busy', 'notice', 'note', 'lead'])
const dialog = ref(false)
const action = ref('phone')
const mode = ref('new')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const notice = ref('')
const crm = ref({ candidates: {}, linked: {} })
const options = ref({ building_types: [] })
const entities = ref([])
const units = ref([])
const cities = ref([])
const entitySearch = ref('')
const unitSearch = ref('')
const citySearch = ref('')
const searching = reactive({ entities: false, units: false, cities: false })
const form = reactive({})
const researchRecords = ref([])
const researchAvailability = ref({})
const selectedResearchId = ref(null)
const researchUnits = ref([])
const selectedResearchUnitId = ref(null)
const researchLoading = ref(false)
const savingResearchToUnit = ref(false)
const requests = new Map()
const searchTimers = new Map()
let revision = 0

const actions = [
    { key: 'phone', title: 'Телефон', icon: 'mdi-phone-plus-outline', hint: 'Извлечь и сохранить телефон из письма' },
    { key: 'entity', title: 'Entity', icon: 'mdi-domain-plus', hint: 'Создать или привязать Entity' },
    { key: 'unit', title: 'Unit', icon: 'mdi-office-building-plus-outline', hint: 'Создать или привязать Unit' },
    { key: 'email', title: 'Связать email', icon: 'mdi-email-plus-outline', hint: 'Привязать email к Entity или Unit' },
    { key: 'website', title: 'Сайт', icon: 'mdi-web-plus', hint: 'Добавить сайт в Unit' },
    { key: 'building', title: 'Адрес', icon: 'mdi-map-marker-plus-outline', hint: 'Создать адрес из текста письма' },
    { key: 'ai-website', title: 'AI · товары', icon: 'mdi-robot-outline', hint: 'Найти товары на сайте по нажатию' },
    { key: 'ai-company', title: 'Юрлицо', icon: 'mdi-text-box-search-outline', hint: 'Найти юридическое лицо по ИНН или названию' },
]
const actionTitle = computed(() => ({ phone: 'Телефон из письма', entity: 'Entity из письма', unit: 'Unit из письма', email: 'Связи email', website: 'Сайт подразделения', building: 'Адрес из письма', 'ai-website': 'Товары на сайте', 'ai-company': 'Поиск юридического лица' })[action.value])
const candidates = computed(() => crm.value.candidates || {})
const linked = computed(() => crm.value.linked || {})
const sender = computed(() => props.message?.from_address || '')
const researchKind = computed(() => action.value === 'ai-company' ? 'company' : 'website')
const isResearch = computed(() => action.value.startsWith('ai-'))
const researchHistory = computed(() => researchRecords.value.filter((record) => record.kind === researchKind.value))
const selectedResearch = computed(() => researchHistory.value.find((record) => record.id === selectedResearchId.value) || researchHistory.value[0] || null)
const selectedResearchUnit = computed(() => researchUnits.value.find((unit) => Number(unit.id) === Number(selectedResearchUnitId.value)) || null)
const savedResearchUnits = computed(() => selectedResearch.value?.saved_units || [])
const researchSavedToSelectedUnit = computed(() => Boolean(selectedResearchUnit.value && savedResearchUnits.value.some((unit) => Number(unit.id) === Number(selectedResearchUnit.value.id))))
const canSaveResearchToUnit = computed(() => action.value === 'ai-website' && !props.disabled && !saving.value && !loading.value && !researchLoading.value
    && Boolean(selectedResearch.value?.id && selectedResearchUnit.value))
const availability = computed(() => researchAvailability.value[researchKind.value])
const showEntityTarget = computed(() => ['phone', 'email', 'building', 'unit'].includes(action.value))
const showUnitTarget = computed(() => ['phone', 'email', 'building', 'website', 'entity'].includes(action.value))
const entityOptions = computed(() => mergeOptions(linked.value.entities, entities.value))
const unitOptions = computed(() => mergeOptions(linked.value.units, units.value))
const ready = computed(() => {
    if (props.disabled || saving.value || loading.value || !props.message?.id) return false
    if (isResearch.value) return Boolean(!researchLoading.value && availability.value?.available && String(form.query || '').trim())
    if (action.value === 'phone') return Boolean(String(form.number || '').trim())
    if (action.value === 'email') return Boolean(String(form.email_address || '').trim() && (form.entity_id || form.unit_id))
    if (action.value === 'website') return Boolean(form.unit_id && String(form.address || '').trim())
    if (action.value === 'building') return Boolean(form.city_id && (form.entity_id || form.unit_id) && String(form.address || '').trim())
    return mode.value === 'existing'
        ? Boolean(action.value === 'entity' ? form.entity_id : form.unit_id)
        : Boolean(String(form.name || '').trim())
})

function mergeOptions(...lists) {
    return [...new Map(lists.flatMap((list) => list || []).filter((item) => item?.id).map((item) => [Number(item.id), item])).values()]
}

function searchLabel(item) {
    return item?.label || [item?.name, item?.INN ? `ИНН ${item.INN}` : ''].filter(Boolean).join(' · ')
}

function cancelRequests() {
    revision++
    for (const request of requests.values()) request.abort()
    requests.clear()
    for (const timer of searchTimers.values()) clearTimeout(timer)
    searchTimers.clear()
    loading.value = false
    researchLoading.value = false
    saving.value = false
    savingResearchToUnit.value = false
    emit('busy', false)
}

async function getResource(key, url, params = {}) {
    requests.get(key)?.abort()
    const request = new AbortController()
    const currentRevision = revision
    requests.set(key, request)
    try {
        const { data } = await axios.get(url, { params, signal: request.signal })
        return !request.signal.aborted && currentRevision === revision ? data : null
    } catch (exception) {
        if (!request.signal.aborted && currentRevision === revision) throw exception
        return null
    } finally {
        if (requests.get(key) === request) requests.delete(key)
    }
}

async function loadContext() {
    const id = props.message?.id
    if (!id) return
    const currentRevision = revision
    loading.value = true
    try {
        const [data, dictionaries] = await Promise.all([
            getResource('crm', `/api/mail-messages/${id}/crm`),
            getResource('options', '/api/mail-crm/options'),
        ])
        if (!data || currentRevision !== revision) return
        crm.value = data
        if (dictionaries) options.value = dictionaries
        if (!form.entity_id && data.linked?.entities?.length === 1) form.entity_id = data.linked.entities[0].id
        if (!form.unit_id && data.linked?.units?.length === 1) form.unit_id = data.linked.units[0].id
        if (!form.number) form.number = data.candidates?.phones?.[0] || ''
        if (!form.address) form.address = action.value === 'building' ? data.candidates?.addresses?.[0] || '' : data.candidates?.websites?.[0] || ''
        if (!form.query) form.query = action.value === 'ai-company'
            ? data.candidates?.tax_ids?.[0] || data.candidates?.companies?.[0] || props.message?.from_name || ''
            : data.candidates?.websites?.[0] || ''
    } catch (exception) {
        if (currentRevision === revision) error.value = exception?.response?.data?.message || 'Не удалось загрузить данные письма для CRM.'
    } finally {
        if (currentRevision === revision) loading.value = false
    }
}

async function searchTargets(kind, search) {
    if (!dialog.value) return
    const currentRevision = revision
    searching[kind] = true
    try {
        const data = await getResource(kind, `/api/mail-crm/${kind}`, { search: search || undefined })
        if (!data) return
        const target = { entities, units, cities }[kind]
        target.value = data.items || []
    } catch (exception) {
        if (currentRevision === revision) error.value = exception?.response?.data?.message || 'Не удалось загрузить список для привязки.'
    } finally {
        if (currentRevision === revision && !requests.has(kind)) searching[kind] = false
    }
}

for (const [kind, search] of [['entities', entitySearch], ['units', unitSearch], ['cities', citySearch]]) {
    watch(search, (value) => {
        clearTimeout(searchTimers.get(kind))
        requests.get(kind)?.abort()
        searchTimers.set(kind, setTimeout(() => searchTargets(kind, value), 260))
    })
}

async function loadResearch() {
    const currentRevision = revision
    researchLoading.value = true
    try {
        const data = await getResource('research', `/api/mail-messages/${props.message.id}/research`)
        if (!data) return
        researchRecords.value = data.data || []
        researchAvailability.value = data.availability || {}
        researchUnits.value = mergeOptions(data.linked_units)
        const validSelection = researchUnits.value.find((unit) => Number(unit.id) === Number(selectedResearchUnitId.value))
        const defaultUnit = researchUnits.value.find((unit) => Number(unit.id) === Number(props.defaultUnitId))
        selectedResearchUnitId.value = validSelection?.id || defaultUnit?.id || (researchUnits.value.length === 1 ? researchUnits.value[0].id : null)
    } catch (exception) {
        if (currentRevision === revision) error.value = exception?.response?.data?.message || 'Не удалось загрузить результаты исследования.'
    } finally {
        if (currentRevision === revision && !requests.has('research')) researchLoading.value = false
    }
}

function reloadData() {
    loadContext()
    if (isResearch.value) loadResearch()
}

function resetForm() {
    for (const key of Object.keys(form)) delete form[key]
    Object.assign(form, {
        entity_id: props.defaultEntityId || null, unit_id: props.defaultUnitId || null,
        name: props.message?.from_name || '', full_name: '', INN: '', KPP: '', OGRN: '', legal_address: '',
        email_address: sender.value, number: '', address: '', city_id: null, building_type_id: null, postcode: '',
        is_customer: false, is_supplier: false, query: '', dadata_raw: null,
        country_id: null, entity_classification_id: null,
    })
}

function openAction(key) {
    if (props.disabled || saving.value || !props.message?.id) return
    cancelRequests()
    action.value = key
    mode.value = 'new'
    error.value = ''
    notice.value = ''
    selectedResearchId.value = null
    selectedResearchUnitId.value = null
    researchUnits.value = []
    resetForm()
    dialog.value = true
    loadContext()
    if (showEntityTarget.value || key === 'entity') searchTargets('entities', '')
    if (showUnitTarget.value || key === 'unit') searchTargets('units', '')
    if (key === 'building') searchTargets('cities', '')
    if (isResearch.value) loadResearch()
}

function payload() {
    const target = { entity_id: form.entity_id || null, unit_id: form.unit_id || null }
    if (action.value === 'phone') return { ...target, number: String(form.number ?? '').trim() }
    if (action.value === 'email') return { ...target, address: String(form.email_address ?? '').trim() }
    if (action.value === 'website') return { unit_id: target.unit_id, address: String(form.address ?? '').trim() }
    if (action.value === 'building') return { ...target, address: String(form.address ?? '').trim(), city_id: form.city_id, building_type_id: form.building_type_id || null, postcode: String(form.postcode ?? '').trim() || null }
    const contact = { email_address: String(form.email_address ?? '').trim() || sender.value }
    if (action.value === 'entity') {
        if (mode.value === 'existing') return { ...contact, ...target }
        return { ...contact, unit_id: target.unit_id, name: String(form.name ?? '').trim(), full_name: String(form.full_name ?? '').trim() || null, INN: String(form.INN ?? '').trim() || null, KPP: String(form.KPP ?? '').trim() || null, OGRN: String(form.OGRN ?? '').trim() || null, legal_address: String(form.legal_address ?? '').trim() || null, country_id: form.country_id, entity_classification_id: form.entity_classification_id, ...(form.dadata_raw ? { dadata_raw: form.dadata_raw } : {}) }
    }
    return mode.value === 'existing'
        ? { ...contact, ...target }
        : { ...contact, entity_id: target.entity_id, name: String(form.name ?? '').trim(), is_customer: form.is_customer, is_supplier: form.is_supplier }
}

async function submit() {
    if (!ready.value) return
    const id = props.message.id
    const currentRevision = revision
    const currentAction = action.value
    saving.value = true
    emit('busy', true)
    error.value = ''
    notice.value = ''
    try {
        if (isResearch.value) {
            const kind = researchKind.value
            const body = kind === 'website'
                ? { url: String(form.query ?? '').trim(), ...(selectedResearchUnit.value ? { unit_id: selectedResearchUnit.value.id } : {}) }
                : { query: String(form.query ?? '').trim() }
            const { data } = await axios.post(`/api/mail-messages/${id}/research/${kind}`, body, { timeout: 65000 })
            if (currentRevision !== revision || Number(id) !== Number(props.message?.id)) return
            researchRecords.value = [data.data, ...researchRecords.value.filter((record) => record.id !== data.data?.id)]
            selectedResearchId.value = data.data?.id
            notice.value = data.saved_to_unit
                ? `Результат сохранён в письме и в Unit «${data.saved_to_unit.name}».`
                : data.cached ? 'Показан сохранённый результат.' : 'Результат исследования сохранён в письме.'
        } else {
            const resource = { phone: 'telephones', email: 'emails', entity: 'entities', unit: 'units', website: 'websites', building: 'buildings' }[currentAction]
            const { data } = await axios.post(`/api/mail-messages/${id}/crm/${resource}`, payload())
            if (currentRevision !== revision || Number(id) !== Number(props.message?.id)) return
            if (data.crm) crm.value = data.crm
            notice.value = data.created ? 'Создано и сохранено в CRM.' : 'Связь сохранена. Использована существующая запись.'
            emit('changed', data.crm)
            if (currentAction === 'entity') { form.entity_id = data.record?.id; mode.value = 'existing' }
            if (currentAction === 'unit') { form.unit_id = data.record?.id; mode.value = 'existing' }
        }
        emit('notice', { type: 'success', text: notice.value })
    } catch (exception) {
        if (currentRevision !== revision) return
        error.value = Object.values(exception?.response?.data?.errors || {}).flat()[0] || exception?.response?.data?.message || 'Не удалось выполнить действие. Попробуйте ещё раз.'
    } finally {
        if (currentRevision === revision) { saving.value = false; emit('busy', false) }
    }
}

async function saveResearchToUnit() {
    if (!canSaveResearchToUnit.value) return
    const id = props.message.id
    const researchId = selectedResearch.value.id
    const unitId = selectedResearchUnit.value.id
    const currentRevision = revision
    saving.value = true
    savingResearchToUnit.value = true
    emit('busy', true)
    error.value = ''
    notice.value = ''
    try {
        const { data } = await axios.post(`/api/mail-messages/${id}/research/${researchId}/unit`, { unit_id: unitId })
        if (currentRevision !== revision || Number(id) !== Number(props.message?.id)) return
        researchRecords.value = researchRecords.value.map((record) => Number(record.id) === Number(data.data.id) ? data.data : record)
        notice.value = `Результат сохранён в Unit «${data.saved_to_unit.name}».`
        emit('notice', { type: 'success', text: notice.value })
    } catch (exception) {
        if (currentRevision !== revision) return
        error.value = Object.values(exception?.response?.data?.errors || {}).flat()[0] || exception?.response?.data?.message || 'Не удалось сохранить результат в Unit. Попробуйте ещё раз.'
    } finally {
        if (currentRevision === revision) { saving.value = false; savingResearchToUnit.value = false; emit('busy', false) }
    }
}

function useCompany(company) {
    const entity = company.entity || {}
    action.value = 'entity'
    mode.value = 'new'
    error.value = ''
    notice.value = 'Реквизиты подставлены. Проверьте их и нажмите «Создать и привязать».'
    Object.assign(form, {
        entity_id: null, name: entity.name || '', full_name: entity.full_name || '', INN: entity.INN || '',
        KPP: entity.KPP || '', OGRN: entity.OGRN || '', legal_address: entity.legal_address || '',
        country_id: entity.country_id || null, entity_classification_id: entity.entity_classification_id || null,
        dadata_raw: company.raw || null,
    })
    searchTargets('entities', '')
    searchTargets('units', '')
}

function safeLink(value) {
    try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.href : null } catch { return null }
}

function unitResearchLink(unit) {
    const url = String(unit?.url || '')
    if (url.startsWith('/') && !url.startsWith('//')) return url
    return safeLink(url) || `/Ameise/unit/${Number(unit.id)}?section=overview#website-research`
}

watch(() => props.message?.id, () => {
    cancelRequests()
    dialog.value = false
    crm.value = { candidates: {}, linked: {} }
    researchRecords.value = []
    researchAvailability.value = {}
    researchUnits.value = []
    selectedResearchUnitId.value = null
})
watch(dialog, (open) => { if (!open) cancelRequests() })
onBeforeUnmount(cancelRequests)
</script>

<template>
    <div class="mail-crm-tools" role="toolbar" aria-label="Инструменты CRM для письма">
        <button v-for="item in message?.direction === 'incoming' ? actions : []" :key="item.key" type="button" :title="item.hint" :aria-label="item.hint" :disabled="disabled || saving" @click="openAction(item.key)"><v-icon :icon="item.icon" size="15" /><span>{{ item.title }}</span></button>
        <span class="mail-crm-tools__divider" />
        <button type="button" title="Сохранить важное из письма" :disabled="disabled || saving" @click="emit('note')"><v-icon icon="mdi-note-plus-outline" size="15" /><span>Заметка</span><small v-if="message?.notes?.length">{{ message.notes.length }}</small></button>
        <button type="button" :title="message?.leads?.length ? 'Открыть существующий лид письма' : 'Создать лид из письма'" :disabled="disabled || saving" @click="emit('lead')"><v-icon :icon="message?.leads?.length ? 'mdi-account-check-outline' : 'mdi-account-plus-outline'" size="15" /><span>{{ message?.leads?.length ? `Лид #${message.leads[0].id}` : 'Лид' }}</span></button>
    </div>

    <v-dialog v-model="dialog" max-width="660" :persistent="saving" scrollable>
        <v-card theme="dark" class="mail-crm-dialog">
            <header class="mail-crm-dialog__header"><div><h3>{{ actionTitle }}</h3><p>{{ sender }}</p></div><v-btn icon="mdi-close" size="small" variant="text" aria-label="Закрыть инструменты CRM" :disabled="saving" @click="dialog = false" /></header>
            <v-progress-linear v-if="loading || saving || researchLoading" indeterminate height="2" color="cyan" />
            <v-card-text class="mail-crm-dialog__body">
                <v-alert v-if="error" type="error" density="compact" variant="tonal" class="mail-crm-dialog__alert">{{ error }} <button v-if="!saving" type="button" @click="reloadData">Обновить данные</button></v-alert>
                <v-alert v-if="notice" type="success" density="compact" variant="tonal" class="mail-crm-dialog__alert">{{ notice }}</v-alert>

                <template v-if="isResearch">
                    <v-combobox v-model="form.query" :items="researchKind === 'website' ? candidates.websites || [] : [...(candidates.tax_ids || []), ...(candidates.companies || [])]" :label="researchKind === 'website' ? 'Сайт для поиска товаров' : 'ИНН или название юридического лица'" :return-object="false" density="compact" variant="outlined" hide-details :disabled="saving" />
                    <p class="mail-crm-dialog__hint">{{ researchKind === 'website' ? 'По кнопке будут исследованы публичные страницы сайта. Результат с источниками сохранится в письме.' : 'Найдите реквизиты и выберите юридическое лицо для заполнения карточки Entity.' }}</p>
                    <template v-if="researchKind === 'website'">
                        <v-select v-if="researchUnits.length" v-model="selectedResearchUnitId" :items="researchUnits" item-value="id" item-title="name" label="Сохранить исследование в Unit" density="compact" variant="outlined" hide-details clearable :disabled="saving || researchLoading" />
                        <p v-if="selectedResearchUnit" class="mail-crm-dialog__hint">По кнопке «Исследовать сайт» результат также сохранится в Unit «{{ selectedResearchUnit.name }}».</p>
                        <p v-else-if="!researchLoading && !researchUnits.length" class="mail-crm-dialog__hint">Чтобы сохранить результат в Unit, сначала привяжите подразделение к этому письму. <button type="button" class="mail-research-link-button" :disabled="saving" @click="openAction('unit')">Привязать Unit</button></p>
                        <p v-else-if="!researchLoading" class="mail-crm-dialog__hint">Выберите связанный Unit, чтобы сохранить в нём результат. Без выбора результат останется в письме.</p>
                    </template>
                    <p v-if="availability && !availability.available" class="mail-crm-dialog__warning">{{ availability.message || 'Исследование сейчас недоступно.' }}</p>
                    <v-select v-if="researchHistory.length > 1" v-model="selectedResearchId" :items="researchHistory" item-value="id" item-title="query" label="Сохранённые результаты" density="compact" variant="outlined" hide-details :disabled="saving" />
                    <section v-if="selectedResearch" class="mail-research-result">
                        <template v-if="researchKind === 'website'">
                            <div class="mail-research-unit-actions">
                                <v-btn size="small" variant="tonal" color="teal" :loading="savingResearchToUnit" :disabled="!canSaveResearchToUnit" @click="saveResearchToUnit">{{ researchSavedToSelectedUnit ? 'Обновить в Unit' : 'Сохранить результат в Unit' }}</v-btn>
                                <span class="mail-crm-dialog__hint">Готовый результат, без повторного исследования.</span>
                            </div>
                            <nav v-if="savedResearchUnits.length" class="mail-research-saved-units" aria-label="Unit с сохранённым исследованием">
                                <span>Сохранено в:</span><a v-for="unit in savedResearchUnits" :key="unit.id" :href="unitResearchLink(unit)" target="_blank" rel="noopener noreferrer"><v-icon icon="mdi-office-building-marker-outline" size="13" />{{ unit.name }}<v-icon icon="mdi-open-in-new" size="11" /></a>
                            </nav>
                            <p v-if="selectedResearch.result?.summary">{{ selectedResearch.result.summary }}</p>
                            <div v-for="(product, index) in selectedResearch.result?.products || []" :key="index" class="mail-research-product"><strong>{{ product.name }}</strong><span v-if="product.description">{{ product.description }}</span><small v-if="product.evidence">{{ product.evidence }}</small><a v-if="safeLink(product.source_url)" :href="safeLink(product.source_url)" target="_blank" rel="noopener noreferrer">Источник <v-icon icon="mdi-open-in-new" size="11" /></a></div>
                            <p v-if="!selectedResearch.result?.products?.length" class="mail-crm-dialog__hint">Товары в сохранённом результате не найдены.</p>
                            <div v-if="selectedResearch.result?.pages?.length" class="mail-research-sources"><span>Исследованные страницы</span><a v-for="(source, index) in selectedResearch.result.pages" :key="index" :href="safeLink(source.url) || undefined" target="_blank" rel="noopener noreferrer">{{ source.title || source.url }}</a></div>
                            <p v-if="selectedResearch.result?.partial" class="mail-crm-dialog__hint">Исследована часть публичных страниц сайта.</p>
                            <p v-for="(warning, index) in selectedResearch.result?.warnings || []" :key="index" class="mail-crm-dialog__warning">{{ warning }}</p>
                        </template>
                        <template v-else>
                            <p class="mail-crm-dialog__hint">Источник реквизитов: DaData · {{ selectedResearch.query }}</p>
                            <article v-for="(company, index) in selectedResearch.result?.companies || []" :key="index" class="mail-research-company"><strong>{{ company.entity?.name || company.entity?.full_name }}</strong><span>{{ company.entity?.full_name }}</span><dl><div><dt>ИНН / КПП</dt><dd>{{ company.entity?.INN || '—' }} / {{ company.entity?.KPP || '—' }}</dd></div><div><dt>ОГРН</dt><dd>{{ company.entity?.OGRN || '—' }}</dd></div><div><dt>Адрес</dt><dd>{{ company.entity?.legal_address || '—' }}</dd></div><div v-if="company.entity?.director_name"><dt>Руководитель</dt><dd>{{ company.entity.director_name }}</dd></div><div v-if="company.entity?.status"><dt>Статус</dt><dd>{{ company.entity.status }}</dd></div></dl><v-btn size="small" variant="tonal" color="teal" :disabled="saving" @click="useCompany(company)">Заполнить Entity</v-btn></article>
                            <p v-if="!selectedResearch.result?.companies?.length" class="mail-crm-dialog__hint">Юридические лица по этому запросу не найдены.</p>
                        </template>
                    </section>
                </template>

                <template v-else>
                    <div v-if="['entity', 'unit'].includes(action)" class="mail-crm-mode"><button type="button" :class="{ active: mode === 'new' }" :disabled="saving" @click="mode = 'new'">Создать {{ action === 'entity' ? 'Entity' : 'Unit' }}</button><button type="button" :class="{ active: mode === 'existing' }" :disabled="saving" @click="mode = 'existing'">Привязать существующую</button></div>
                    <template v-if="['entity', 'unit'].includes(action) && mode === 'new'">
                        <v-combobox v-model="form.name" :items="candidates.companies || []" label="Название" :return-object="false" density="compact" variant="outlined" hide-details :disabled="saving" />
                        <template v-if="action === 'entity'">
                            <v-text-field v-model="form.full_name" label="Полное наименование" density="compact" variant="outlined" hide-details :disabled="saving" />
                            <div class="mail-crm-form-row"><v-combobox v-model="form.INN" :items="candidates.tax_ids || []" label="ИНН" :return-object="false" density="compact" variant="outlined" hide-details :disabled="saving" /><v-text-field v-model="form.KPP" label="КПП" density="compact" variant="outlined" hide-details :disabled="saving" /><v-text-field v-model="form.OGRN" label="ОГРН" density="compact" variant="outlined" hide-details :disabled="saving" /></div>
                            <v-text-field v-model="form.legal_address" label="Юридический адрес" density="compact" variant="outlined" hide-details :disabled="saving" />
                        </template>
                        <div v-else class="mail-crm-role-row"><v-checkbox v-model="form.is_customer" label="Покупатель" density="compact" hide-details :disabled="saving" /><v-checkbox v-model="form.is_supplier" label="Поставщик" density="compact" hide-details :disabled="saving" /></div>
                    </template>
                    <v-autocomplete v-if="action === 'entity' && mode === 'existing'" v-model="form.entity_id" v-model:search="entitySearch" :items="entityOptions" item-value="id" :item-title="searchLabel" label="Найти Entity" no-filter :loading="searching.entities" density="compact" variant="outlined" hide-details clearable :disabled="saving" no-data-text="Entity не найдены" />
                    <v-autocomplete v-if="action === 'unit' && mode === 'existing'" v-model="form.unit_id" v-model:search="unitSearch" :items="unitOptions" item-value="id" :item-title="searchLabel" label="Найти Unit" no-filter :loading="searching.units" density="compact" variant="outlined" hide-details clearable :disabled="saving" no-data-text="Unit не найдены" />
                    <v-combobox v-if="action === 'phone'" v-model="form.number" :items="candidates.phones || []" label="Телефон из письма или вручную" :return-object="false" density="compact" variant="outlined" hide-details :disabled="saving" />
                    <v-combobox v-if="['email', 'entity', 'unit'].includes(action)" v-model="form.email_address" :items="candidates.emails || []" label="Email для привязки" :return-object="false" density="compact" variant="outlined" hide-details :disabled="saving" />
                    <v-combobox v-if="action === 'website'" v-model="form.address" :items="candidates.websites || []" label="Адрес сайта" :return-object="false" density="compact" variant="outlined" hide-details :disabled="saving" />
                    <template v-if="action === 'building'">
                        <v-combobox v-model="form.address" :items="candidates.addresses || []" label="Адрес из письма — проверьте перед сохранением" :return-object="false" density="compact" variant="outlined" hide-details :disabled="saving" />
                        <v-autocomplete v-model="form.city_id" v-model:search="citySearch" :items="cities" item-value="id" :item-title="searchLabel" label="Город" no-filter :loading="searching.cities" density="compact" variant="outlined" hide-details clearable :disabled="saving" no-data-text="Города не найдены" />
                        <div class="mail-crm-form-row"><v-select v-model="form.building_type_id" :items="options.building_types || []" item-value="id" :item-title="searchLabel" label="Тип адреса" density="compact" variant="outlined" hide-details clearable :disabled="saving" /><v-text-field v-model="form.postcode" label="Почтовый индекс" density="compact" variant="outlined" hide-details :disabled="saving" /></div>
                    </template>
                    <div v-if="showEntityTarget || showUnitTarget" class="mail-crm-form-row">
                        <v-autocomplete v-if="showEntityTarget" v-model="form.entity_id" v-model:search="entitySearch" :items="entityOptions" item-value="id" :item-title="searchLabel" label="Привязать к Entity" no-filter :loading="searching.entities" density="compact" variant="outlined" hide-details clearable :disabled="saving" no-data-text="Entity не найдены" />
                        <v-autocomplete v-if="showUnitTarget" v-model="form.unit_id" v-model:search="unitSearch" :items="unitOptions" item-value="id" :item-title="searchLabel" :label="action === 'website' ? 'Unit для сайта (обязательно)' : 'Привязать к Unit'" no-filter :loading="searching.units" density="compact" variant="outlined" hide-details clearable :disabled="saving" no-data-text="Unit не найдены" />
                    </div>
                    <p class="mail-crm-dialog__hint">{{ action === 'building' ? 'Выберите Entity или Unit, к которому относится адрес.' : action === 'phone' ? 'Можно сохранить телефон отдельно или сразу связать его с выбранной Entity и Unit.' : ['entity', 'unit'].includes(action) ? 'Email отправителя будет связан с выбранной карточкой. Существующие записи будут использованы повторно.' : 'Проверьте данные и выберите карточку для привязки.' }}</p>
                </template>
            </v-card-text>
            <v-card-actions class="mail-crm-dialog__footer"><v-btn size="small" variant="text" :disabled="saving" @click="dialog = false">Закрыть</v-btn><v-spacer /><v-btn size="small" color="teal-lighten-2" variant="tonal" :loading="saving && !savingResearchToUnit" :disabled="!ready" @click="submit">{{ isResearch ? (researchKind === 'website' ? 'Исследовать сайт' : 'Найти реквизиты') : ['entity', 'unit'].includes(action) && mode === 'new' ? 'Создать и привязать' : 'Сохранить' }}</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.mail-crm-tools { display: flex; align-items: center; gap: 4px; min-width: 0; margin-top: 6px; overflow-x: auto; scrollbar-width: thin; }
.mail-crm-tools > button { display: inline-flex; align-items: center; flex: 0 0 auto; gap: 4px; height: 25px; padding: 0 7px; color: #c2d5ed; border: 1px solid #51719850; border-radius: 5px; background: #1c32465c; font-size: 10px; white-space: nowrap; }
.mail-crm-tools > button:hover:not(:disabled) { color: #e2f7ff; border-color: #7bb7dc; background: #1d496b66; }
.mail-crm-tools > button:disabled { opacity: .4; }
.mail-crm-tools > button:focus-visible { outline: 2px solid #7dd3fc; outline-offset: -2px; }
.mail-crm-tools small { font-size: 9px; color: #5eead4; }
.mail-crm-tools__divider { flex: 0 0 1px; height: 17px; background: #58728c66; margin-inline: 2px; }
.mail-crm-dialog { border: 1px solid #354964; border-radius: 12px; background: #101d30; color: #d2def0; }
.mail-crm-dialog__header { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px 8px; border-bottom: 1px solid #334660; }
.mail-crm-dialog__header h3 { font-size: 15px; color: #c5e4fc; font-weight: 650; }
.mail-crm-dialog__header p { margin-top: 3px; font-size: 11px; color: #8da7c4; }
.mail-crm-dialog__body { display: flex; flex-direction: column; gap: 12px; padding: 16px !important; }
.mail-crm-dialog__body :deep(.v-field) { font-size: 12px; }
.mail-crm-dialog__body :deep(.v-label) { font-size: 12px; }
.mail-crm-dialog__alert { flex: 0 0 auto; font-size: 12px; }
.mail-crm-dialog__alert button { text-decoration: underline; margin-left: 8px; }
.mail-crm-dialog__hint { color: #93a7c1; font-size: 11px; line-height: 1.5; }
.mail-crm-dialog__warning { color: #f3bf70; font-size: 11px; line-height: 1.5; }
.mail-crm-dialog__footer { flex: 0 0 auto; padding: 8px 12px; border-top: 1px solid #334660; }
.mail-crm-dialog__footer :deep(.v-btn) { font-size: 11px; letter-spacing: 0; text-transform: none; }
.mail-crm-form-row { display: flex; gap: 10px; min-width: 0; }
.mail-crm-form-row > * { flex: 1 1 0; min-width: 0; }
.mail-crm-mode { display: flex; gap: 4px; padding: 3px; border: 1px solid #354964; border-radius: 7px; }
.mail-crm-mode button { flex: 1; padding: 6px; border-radius: 5px; font-size: 11px; color: #8fa7c4; }
.mail-crm-mode button.active { color: #b9efe7; background: #134844; }
.mail-crm-role-row { display: flex; gap: 12px; }
.mail-research-result { display: flex; flex-direction: column; gap: 10px; padding: 10px; border: 1px solid #354964; border-radius: 8px; font-size: 12px; line-height: 1.5; }
.mail-research-unit-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; }
.mail-research-unit-actions :deep(.v-btn) { font-size: 11px; letter-spacing: 0; text-transform: none; }
.mail-research-saved-units { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 8px; color: #8da7c4; font-size: 11px; }
.mail-research-saved-units a { display: inline-flex; align-items: center; gap: 4px; color: #8de1d5; }
.mail-research-link-button { color: #78cbdc; text-decoration: underline; }
.mail-research-product, .mail-research-company { display: flex; flex-direction: column; gap: 4px; padding: 9px; border: 1px solid #324760; border-radius: 6px; background: #17253a; overflow-wrap: anywhere; }
.mail-research-product strong, .mail-research-company strong { color: #d9eafa; font-size: 12px; }
.mail-research-product span, .mail-research-company span { color: #a8bdd4; font-size: 11px; }
.mail-research-product small { color: #8a9fb8; font-size: 10px; }
.mail-research-product a, .mail-research-sources a { color: #78cbdc; font-size: 11px; }
.mail-research-sources { display: flex; flex-direction: column; gap: 4px; overflow-wrap: anywhere; }
.mail-research-sources > span { color: #8099b5; font-size: 10px; }
.mail-research-company dl { display: flex; flex-direction: column; gap: 4px; font-size: 11px; }
.mail-research-company dl > div { display: grid; grid-template-columns: 100px minmax(0, 1fr); gap: 6px; }
.mail-research-company dt { color: #829ab4; }
.mail-research-company dd { color: #c4d7e9; margin: 0; }
.mail-research-company > .v-btn { align-self: flex-start; margin-top: 6px; font-size: 11px; letter-spacing: 0; }
@media (max-width: 520px) { .mail-crm-form-row { flex-wrap: wrap; }.mail-crm-form-row > * { flex-basis: 100%; }.mail-crm-dialog__body { padding: 12px !important; } }
</style>
