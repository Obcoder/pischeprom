<script setup>
import { computed, onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'
import PriceConstructionChain from './PriceConstructionChain.vue'
import GoodAvitoPriceDialog from './GoodAvitoPriceDialog.vue'
import { avitoPriceBlockReason } from '@/Services/goodAvito'

const props = defineProps({ goodId: { type: [Number, String], required: true }, active: { type: Boolean, default: true } })
const emit = defineEmits(['changed', 'state', 'request-market', 'request-avito'])
const avitoPrice = ref(null), avitoOpen = ref(false), avitoBusy = ref(false)
const data = ref(null), rows = ref([]), tab = ref('prices'), loading = ref(false), error = ref('')
const history = ref(null), historyScope = ref(''), historyLoading = ref(false), historyError = ref('')
const entitySearch = ref(''), entities = ref(null), entityError = ref(''), entityLoading = ref(false), choosingEntity = ref(false)
const access = ref(null), accessError = ref(''), accessBusy = ref(false)
const libraryOpen = ref(false), libraryDraft = ref(null), libraryBusy = ref(false), libraryError = ref(''), notice = ref('')
const archiveDetail = ref(null)
const libraryMeasurementChanged = computed(() => {
    const saved = libraryDraft.value?.measurement, current = data.value?.measurement
    if (!saved || !current) return false
    const factor = value => value == null ? null : Number(value)
    return Number(saved.measure_id) !== Number(current.measure_id) || saved.unit_label !== current.unit_label || factor(saved.kilograms_per_unit) !== factor(current.kilograms_per_unit)
})
let generation = 0, sequence = 0, historyVersion = 0, entityVersion = 0, accessVersion = 0, sourcesVersion = 0
const dirty = computed(() => rows.value.some(row => row.dirty) || Boolean(libraryDraft.value?.dirty))
const busy = computed(() => rows.value.some(row => row.busy) || accessBusy.value || libraryBusy.value || avitoBusy.value)
const base = () => `/api/goods/${props.goodId}/pricing`
const money = value => value === null || value === undefined ? '—' : Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 4 })
const date = value => value ? new Date(value).toLocaleString('ru-RU') : '—'
const message = failure => Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось выполнить действие. Повторите попытку.'
const clone = value => JSON.parse(JSON.stringify(value))
const methods = { manual: 'Вручную', markup: 'Наценка, %', margin: 'Маржа, %', absolute: 'Надбавка', saved: 'Сохранённый расчёт' }
const events = { baseline: 'Начальный снимок', created: 'Назначена', updated: 'Изменена', deleted: 'Удалена', unit_changed: 'Изменена единица' }
function draft(saved = {}) {
    const input = saved.input || {}
    return { key: ++sequence, id: saved.id || null, revision: saved.revision || 0, scope: saved.scope || 'common',
        target_id: saved.target_id || null, target_name: saved.target_name || '',
        method: input.method || 'manual', amount: input.amount ?? saved.price_gross ?? null,
        amount_includes_vat: input.amount_includes_vat ?? true, source_key: input.source_key || '',
        source_includes_vat: input.source_includes_vat ?? true, source_vat_rate: input.source_vat_rate ?? data.value?.vat_rate ?? 20,
        vat_rate: saved.vat_rate ?? data.value?.vat_rate ?? 20, parameter: input.parameter ?? 20,
        currency_id: saved.currency_id || data.value?.currencies.find(item => item.code === 'RUB')?.id || data.value?.currencies[0]?.id,
        rounding_step: input.rounding_step ?? 0, calculation_id: input.calculation_id || null, saved_calculation_id: input.saved_calculation_id || null,
        purchase_amount: input.purchase_amount ?? null, elements: clone(input.elements || []), measurement: clone(saved.measurement || data.value?.measurement || {}), calculation_name: '', success: '',
        active: saved.active ?? true, valid_from: saved.valid_from || '', valid_to: saved.valid_to || '', comment: saved.comment || '',
        result: saved.result || (saved.id ? { price_net: saved.price_net, price_gross: saved.price_gross } : null),
        dirty: !saved.id, busy: false, error: '', expanded: false, deleting: false, version: 0, saved }
}
function payload(row) {
    const keys = ['id', 'revision', 'scope', 'target_id', 'method', 'amount', 'amount_includes_vat', 'source_key', 'source_includes_vat', 'source_vat_rate', 'vat_rate', 'parameter', 'currency_id', 'rounding_step', 'calculation_id', 'saved_calculation_id', 'purchase_amount', 'elements', 'calculation_name', 'active', 'valid_from', 'valid_to', 'comment']
    return { ...Object.fromEntries(keys.map(key => [key, row[key] === '' ? null : row[key]])), measurement: row.measurement || data.value.measurement }
}
function touch(row) { row.dirty = true; row.result = null; row.error = ''; row.success = ''; row.version++ }
function selectType(row) {
    const type = data.value.types.find(item => item.id === Number(row.target_id))
    if (type) { row.target_name = type.name; row.currency_id = type.currency_id || row.currency_id; if (row.method !== 'manual') row.rounding_step = type.rounding_step || 0 }
    touch(row)
}
function selectSource(row) {
    const source = data.value.sources.find(item => item.key === row.source_key)
    if (source?.price_includes_vat != null && source?.vat_rate != null) {
        row.source_includes_vat = source.price_includes_vat
        row.source_vat_rate = source.vat_rate
    }
    touch(row)
}
function reset() { rows.value = (data.value?.rows || []).map(draft); libraryDraft.value = null }
async function load() {
    const version = ++generation, url = base()
    sourcesVersion++
    loading.value = true; error.value = ''
    try {
        const response = await axios.get(url)
        if (version !== generation) return
        data.value = { ...response.data, elements: response.data.elements || [], saved_calculations: response.data.saved_calculations || [] }; reset(); history.value = null
    } catch (failure) { if (version === generation) error.value = message(failure) }
    finally { if (version === generation) loading.value = false }
}
async function refreshSources() {
    if (!data.value) return
    const version = generation, requestVersion = ++sourcesVersion
    try {
        const response = await axios.get(base())
        if (version !== generation || requestVersion !== sourcesVersion) return
        const previous = data.value.sources
        data.value.sources = response.data.sources
        data.value.partners = response.data.partners
        data.value.currencies = response.data.currencies
        for (const row of rows.value) {
            if (!row.dirty || !row.source_key || ['manual', 'saved'].includes(row.method)) continue
            const before = previous.find(item => item.key === row.source_key)
            const after = data.value.sources.find(item => item.key === row.source_key)
            if (JSON.stringify(before) !== JSON.stringify(after)) touch(row)
        }
    } catch (failure) { if (version === generation && requestVersion === sourcesVersion) notice.value = `Не удалось обновить источники: ${message(failure)}` }
}
async function run(row, save = false) {
    if (row.busy) return
    const version = generation, editVersion = row.version
    row.busy = true; row.error = ''
    try {
        const response = save ? await axios.put(base(), payload(row)) : await axios.post(`${base()}/preview`, payload(row))
        if (version !== generation || editVersion !== row.version) return
        if (save) {
            const stored = data.value.rows.findIndex(item => item.id === response.data.id && item.scope === response.data.scope)
            if (stored < 0) data.value.rows.push(response.data); else data.value.rows[stored] = response.data
            recordArchive(response.data.saved_calculation)
            Object.assign(row, draft(response.data)); row.success = 'Цена назначена. Расчёт добавлен в сохранённые.'; history.value = null; emit('changed')
        } else row.result = response.data
    } catch (failure) { if (version === generation && editVersion === row.version) row.error = message(failure) }
    finally { row.busy = false }
}
async function remove(row) {
    if (!row.id) { rows.value = rows.value.filter(item => item.key !== row.key); return }
    const version = generation
    row.busy = true; row.error = ''
    try {
        await axios.delete(`${base()}/${row.scope}/${row.id}`, { data: { revision: row.revision } })
        if (version !== generation) return
        data.value.rows = data.value.rows.filter(item => item.id !== row.id || item.scope !== row.scope)
        rows.value = rows.value.filter(item => item.key !== row.key); history.value = null; emit('changed')
    } catch (failure) { if (version === generation) row.error = message(failure) }
    finally { row.busy = false; row.deleting = false }
}
function add(scope = 'common', entity = null) {
    rows.value.push(draft({ scope, target_id: entity?.id, target_name: entity?.name }))
    choosingEntity.value = false; tab.value = 'prices'
}
async function loadHistory(page = 1) {
    const version = ++historyVersion, good = generation
    historyLoading.value = true; historyError.value = ''
    try {
        const response = await axios.get(`${base()}/history`, { params: { scope: historyScope.value || undefined, page } })
        if (version === historyVersion && good === generation) history.value = response.data
    } catch (failure) { if (version === historyVersion && good === generation) historyError.value = message(failure) }
    finally { if (version === historyVersion) historyLoading.value = false }
}
async function searchEntities(page = 1) {
    const version = ++entityVersion, good = generation
    entityLoading.value = true; entityError.value = ''
    try {
        const response = await axios.get('/api/pricing/entities', { params: { search: entitySearch.value, page } })
        if (version === entityVersion && good === generation) entities.value = response.data
    } catch (failure) { if (version === entityVersion && good === generation) entityError.value = message(failure) }
    finally { if (version === entityVersion) entityLoading.value = false }
}
async function openAccess(row) {
    const version = ++accessVersion, good = generation
    access.value = { entity: row.target_id, name: row.target_name, users: [], loading: true }; accessError.value = ''
    try {
        const response = await axios.get(`/api/pricing/entities/${row.target_id}/access`)
        if (version === accessVersion && good === generation && access.value) access.value.users = response.data
    } catch (failure) { if (version === accessVersion && good === generation) accessError.value = message(failure) }
    finally { if (version === accessVersion && access.value) access.value.loading = false }
}
async function grant(user) {
    if (accessBusy.value) return
    const current = access.value, version = generation
    accessBusy.value = true; accessError.value = ''
    try {
        const response = await axios.put(`/api/pricing/entities/${current.entity}/access/${user.id}`, { allowed: !user.allowed })
        if (version === generation && access.value === current) current.users = response.data
    } catch (failure) { if (version === generation) accessError.value = message(failure) }
    finally { accessBusy.value = false }
}
function recordArchive(calculation) {
    if (!calculation) return
    const saved = data.value.saved_calculations || (data.value.saved_calculations = [])
    if (!saved.some(item => item.id === calculation.id)) saved.unshift(calculation)
}
async function saveCalculation(row) {
    if (row.busy) return
    const version = generation, editVersion = row.version
    row.busy = true; row.error = ''; row.success = ''
    try {
        const response = await axios.post(`${base()}/calculations`, payload(row))
        if (version !== generation || editVersion !== row.version) return
        recordArchive(response.data); row.result = response.data.result; if (!row.target_id) row.dirty = false; row.success = 'Расчёт сохранён. Откройте вкладку «Сохранённые расчёты».'
    } catch (failure) { if (version === generation && editVersion === row.version) row.error = message(failure) }
    finally { row.busy = false }
}
const archive = computed(() => [
    ...(data.value?.saved_calculations || []).map(item => ({ ...item, archive_source: 'workspace' })),
    ...(data.value?.calculations || []).map(item => ({ ...item, archive_source: 'legacy', scope: 'common', measurement: { unit_label: 'кг', kilograms_per_unit: 1 }, price_net: item.sale_net_per_kg, price_gross: item.sale_gross_per_kg, currency_code: item.currency?.code })),
].sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || ''))))
function availableCalculations(row) {
    return archive.value.filter(item => item.scope !== 'entity' || (row.scope === 'entity' && Number(row.target_id) === Number(item.entity_id)))
}
function useCalculation(calculation, repeat = false) {
    const entity = calculation.scope === 'entity'
    const type = !entity && data.value.types.find(item => item.name === calculation.target_name)
    const existing = (entity || type) && rows.value.find(item => item.scope === (entity ? 'entity' : 'common') && Number(item.target_id) === Number(entity ? calculation.entity_id : type?.id))
    if (existing?.dirty) { notice.value = 'У этой цены есть несохранённые изменения. Сначала сохраните или отмените их, затем примените расчёт.'; tab.value = 'prices'; return }
    if (!existing) add(entity ? 'entity' : 'common', entity ? { id: calculation.entity_id, name: calculation.target_name } : null)
    const row = existing || rows.value.at(-1)
    tab.value = 'prices'; touch(row)
    row.elements = []; row.rounding_step = 0
    Object.assign(row, { method: 'saved', currency_id: calculation.currency_id, vat_rate: calculation.result?.vat_rate ?? calculation.vat_rate ?? data.value.vat_rate, calculation_id: calculation.archive_source === 'legacy' ? calculation.id : null, saved_calculation_id: calculation.archive_source === 'workspace' ? calculation.id : null })
    if (!repeat) row.success = 'Снимок выбран. Проверьте результат и нажмите «Сохранить цену» для назначения.'
    if (repeat && calculation.archive_source === 'workspace') {
        const input = clone(calculation.input || {})
        const permitted = ['method', 'amount', 'amount_includes_vat', 'source_key', 'purchase_amount', 'source_includes_vat', 'source_vat_rate', 'vat_rate', 'parameter', 'rounding_step', 'calculation_id', 'saved_calculation_id', 'elements']
        for (const key of permitted) if (input[key] !== undefined) row[key] = input[key]
        row.measurement = clone(calculation.measurement || data.value.measurement)
        row.success = 'Создана копия. При расчёте закупка берётся из актуального источника; дополнительные элементы сохранены как снимок.'
    }
}
function editElement(element = null) {
    libraryDraft.value = element ? { ...clone(element), dirty: false } : { id: null, revision: 0, name: '', kind: 'fixed', value: 0, percentage_base: 'running_cost', includes_vat: false, vat_rate: 0, currency_id: data.value.currencies.find(item => item.code === 'RUB')?.id || data.value.currencies[0]?.id, measurement: clone(data.value.measurement), dirty: false }
    libraryError.value = ''; libraryOpen.value = true
}
function rebaseLibraryMeasurement() {
    if (!libraryDraft.value || !data.value) return
    libraryDraft.value.measurement = clone(data.value.measurement)
    libraryDraft.value.dirty = true; libraryError.value = ''
}
async function saveElement() {
    if (libraryBusy.value || !libraryDraft.value) return
    if (libraryDraft.value.kind === 'fixed' && libraryMeasurementChanged.value) { libraryError.value = 'Единица или масса товара изменилась. Проверьте сумму и подтвердите её для текущей единицы.'; return }
    const current = libraryDraft.value, version = generation
    libraryBusy.value = true; libraryError.value = ''
    const { dirty, deleting, id, ...body } = current
    if (body.kind === 'percent') body.measurement = clone(data.value.measurement)
    try {
        const response = id ? await axios.patch(`${base()}/elements/${id}`, body) : await axios.post(`${base()}/elements`, body)
        if (version !== generation) return
        const index = data.value.elements.findIndex(item => item.id === response.data.id)
        if (index < 0) data.value.elements.push(response.data); else data.value.elements[index] = response.data
        libraryDraft.value = null; notice.value = 'Элемент сохранён в библиотеке. Копии в расчётах сохраняют свои значения.'
    } catch (failure) { if (version === generation) libraryError.value = message(failure) }
    finally { libraryBusy.value = false }
}
async function removeElement(element) {
    if (libraryBusy.value) return
    const version = generation
    libraryBusy.value = true; libraryError.value = ''
    try {
        await axios.delete(`${base()}/elements/${element.id}`, { data: { revision: element.revision } })
        if (version !== generation) return
        data.value.elements = data.value.elements.filter(item => item.id !== element.id)
        if (libraryDraft.value?.id === element.id) libraryDraft.value = null
        notice.value = 'Элемент удалён из библиотеки. Снимки в расчётах сохранены.'
    } catch (failure) { if (version === generation) libraryError.value = message(failure) }
    finally { libraryBusy.value = false }
}
const currencyCode = row => data.value?.currencies.find(item => item.id === Number(row.currency_id))?.code || ''
function openAvito(row) {
    if (avitoPriceBlockReason(row, data.value)) return
    avitoPrice.value = row.id; avitoOpen.value = true
}

function snapshotPrice(snapshot) { return snapshot ? `${money(snapshot.price_gross ?? snapshot.price_net)} ${snapshot.currency_code || ''} / ${snapshot.measurement?.unit_label || '?'}` : '—' }
function audience(row) {
    if (row.scope === 'entity') return 'Только личный кабинет Entity'
    const type = data.value.types.find(item => item.id === Number(row.target_id))
    return type?.is_public ? 'Общедоступная' : 'Для зарегистрированных'
}
watch([dirty, busy], ([dirty, busy]) => emit('state', { dirty, busy }), { immediate: true })
watch(() => props.goodId, () => { generation++; data.value = null; rows.value = []; history.value = null; access.value = null; libraryOpen.value = false; libraryDraft.value = null; notice.value = ''; archiveDetail.value = null; choosingEntity.value = false; tab.value = 'prices'; avitoOpen.value = false; avitoPrice.value = null; avitoBusy.value = false; if (props.active) load() }, { immediate: true })
watch(() => props.active, active => { if (active && !data.value && !loading.value) load() })
watch(tab, value => { if (value === 'history' && !history.value) loadHistory() })
onScopeDispose(() => { generation++; historyVersion++; entityVersion++; accessVersion++ })
defineExpose({ reset, refresh: () => dirty.value ? undefined : load(), refreshSources })
</script>

<template>
    <section class="pricing" aria-label="Ценообразование товара">
        <div class="pricing-head">
            <div><h3>Ценообразование</h3><p>Расчёт и назначение за 1 {{ data?.measurement?.unit_label || 'единицу товара' }}. Все изменения сохраняются в истории.</p></div>
            <div v-if="data?.partners.length" class="partners"><table class="price-table"><caption>Поставщики и производители</caption><tbody><tr v-for="(partner, index) in data.partners" :key="index"><td>{{ partner.role }}</td><td>{{ partner.name }}</td></tr></tbody></table></div>
        </div>
        <div class="pricing-toolbar">
            <div class="segments" role="group" aria-label="Разделы цен"><button v-for="(label, key) in { prices: 'Цены', history: 'История', calculations: 'Сохранённые расчёты' }" :key="key" :aria-pressed="tab === key" @click="tab = key">{{ label }}</button></div>
            <button :disabled="busy || dirty || loading" @click="load">Обновить</button>
        </div>
        <p v-if="error" class="price-error" role="alert">{{ error }}</p>
        <p v-if="loading" class="empty" role="status">Загрузка цен…</p>
        <template v-else-if="data">
            <template v-if="tab === 'prices'">
                <div class="pricing-toolbar"><div class="button-group"><button @click="add()">＋ Расчёт / вид цены</button><button @click="choosingEntity = !choosingEntity; choosingEntity && searchEntities()">＋ Цена для Entity</button><button :aria-expanded="libraryOpen" @click="libraryOpen = !libraryOpen">Элементы цены · {{ data.elements.length }}</button></div><span class="muted">{{ rows.length }} цен · {{ dirty ? 'Есть несохранённые изменения' : 'Изменения сохранены' }}</span></div>
                <section v-if="choosingEntity" class="inset" aria-label="Выбор Entity">
                    <form class="pricing-toolbar" @submit.prevent="searchEntities()"><label>Entity <input v-model="entitySearch" placeholder="Название или ИНН" maxlength="150"></label><button :disabled="entityLoading">Найти</button><button type="button" @click="choosingEntity = false">Закрыть</button></form>
                    <p v-if="entityError" class="price-error" role="alert">{{ entityError }}</p>
                    <p v-if="entityLoading" role="status">Поиск…</p>
                    <table v-else-if="entities" class="price-table"><thead><tr><th>Организация</th><th>ИНН</th><th></th></tr></thead><tbody><tr v-for="entity in entities.data" :key="entity.id"><td>{{ entity.name }}</td><td>{{ entity.INN || '—' }}</td><td><button :disabled="rows.some(row => row.scope === 'entity' && row.target_id === entity.id)" @click="add('entity', entity)">Выбрать</button></td></tr><tr v-if="!entities.data.length"><td colspan="3">Организации не найдены</td></tr></tbody></table>
                    <div v-if="entities?.last_page > 1" class="pricing-toolbar"><button :disabled="entityLoading || entities.current_page <= 1" @click="searchEntities(entities.current_page - 1)">Назад</button><span>{{ entities.current_page }} / {{ entities.last_page }}</span><button :disabled="entityLoading || entities.current_page >= entities.last_page" @click="searchEntities(entities.current_page + 1)">Далее</button></div>
                </section>
                <section v-if="libraryOpen" class="inset library" aria-label="Библиотека элементов цены">
                    <div class="pricing-toolbar"><div><strong>Элементы цены</strong><p class="muted">Объекты библиотеки добавляются в расчёт как независимые копии. Изменения здесь не меняют сохранённые цены и цепочки.</p></div><button :disabled="libraryBusy || !!libraryDraft" @click="editElement()">＋ Создать элемент</button></div>
                    <p v-if="libraryError" class="price-error" role="alert">{{ libraryError }}</p>
                    <div class="table-scroll"><table class="price-table"><thead><tr><th>Элемент</th><th>Величина</th><th>База / единица</th><th>НДС</th><th></th></tr></thead><tbody><tr v-for="element in data.elements" :key="element.id"><td>{{ element.name }}</td><td>{{ money(element.value) }} {{ element.kind === 'percent' ? '%' : currencyCode(element) }}</td><td>{{ element.kind === 'percent' ? element.percentage_base === 'purchase_net' ? 'Закупка без НДС' : 'Сумма предыдущих затрат' : `За 1 ${element.measurement?.unit_label || '?'}` }}</td><td>{{ element.includes_vat ? `Включён, ${money(element.vat_rate)}%` : 'Без НДС' }}</td><td><div class="button-group"><button :disabled="libraryBusy || !!libraryDraft" @click="editElement(element)">Изменить</button><button v-if="!element.deleting" :disabled="libraryBusy || !!libraryDraft" @click="element.deleting = true">Удалить</button><template v-else><button class="danger" :disabled="libraryBusy" @click="removeElement(element)">Удалить из библиотеки?</button><button :disabled="libraryBusy" @click="element.deleting = false">Нет</button></template></div></td></tr><tr v-if="!data.elements.length"><td colspan="5" class="empty">Создайте элементы: логистика, хранение, реклама, дополнительные услуги.</td></tr></tbody></table></div>
                    <form v-if="libraryDraft" class="parameters library-form" @submit.prevent="saveElement" @input="libraryDraft.dirty = true" @change="libraryDraft.dirty = true"><div v-if="libraryDraft.kind === 'fixed' && libraryMeasurementChanged" class="library-unit-warning" role="status"><span>Единица или масса товара изменилась: элемент сохранён за 1 {{ libraryDraft.measurement?.unit_label }}, {{ libraryDraft.measurement?.kilograms_per_unit ?? 'неизвестно' }} кг; сейчас — 1 {{ data.measurement.unit_label }}, {{ data.measurement.kilograms_per_unit ?? 'неизвестно' }} кг. Проверьте введённую сумму: автоматического пересчёта нет.</span><button type="button" :disabled="libraryBusy" @click="rebaseLibraryMeasurement">Использовать введённую сумму за 1 {{ data.measurement.unit_label }}</button></div><label class="comment">Название<input v-model="libraryDraft.name" :disabled="libraryBusy" maxlength="120" required placeholder="Например, доставка до склада"></label><label>Формат<select v-model="libraryDraft.kind" :disabled="libraryBusy" @change="libraryDraft.kind === 'percent' && (libraryDraft.includes_vat = false)"><option value="fixed">Сумма за единицу</option><option value="percent">Процент</option></select></label><label>Величина<input v-model="libraryDraft.value" :disabled="libraryBusy" type="number" min="0" step="0.0001" required></label><label v-if="libraryDraft.kind === 'fixed'">Валюта<select v-model="libraryDraft.currency_id" :disabled="libraryBusy"><option v-for="currency in data.currencies" :key="currency.id" :value="currency.id">{{ currency.code }} / {{ libraryDraft.measurement?.unit_label }}</option></select></label><label v-else>База процента<select v-model="libraryDraft.percentage_base" :disabled="libraryBusy"><option value="purchase_net">Закупка без НДС</option><option value="running_cost">Сумма слева</option></select></label><label class="check"><input v-model="libraryDraft.includes_vat" :disabled="libraryBusy || libraryDraft.kind === 'percent'" type="checkbox">Величина с НДС</label><label>НДС, %<input v-model="libraryDraft.vat_rate" :disabled="libraryBusy || !libraryDraft.includes_vat" type="number" min="0" max="100" step="0.0001"></label><div class="button-group"><button class="primary" :disabled="libraryBusy || (libraryDraft.kind === 'fixed' && libraryMeasurementChanged)">Сохранить элемент</button><button type="button" :disabled="libraryBusy" @click="libraryDraft = null">Отменить</button></div></form>
                </section>
                <p v-if="notice" class="notice" role="status">{{ notice }}</p>
                <div class="table-scroll"><table class="price-table editor-table"><thead><tr><th>Назначение и валюта</th><th class="numeric">Итог за 1 {{ data.measurement.unit_label || 'ед.' }}</th><th>Действие цены</th><th>Расчёт / назначение</th></tr></thead>
                    <tbody v-for="row in rows" :key="row.key" :class="{ modified: row.dirty }">
                        <tr>
                            <td class="target-cell"><div class="row-target"><template v-if="row.scope === 'entity'"><strong>{{ row.target_name }}</strong><button class="price-link" @click="openAccess(row)">Доступ ↗</button></template><select v-else v-model="row.target_id" :disabled="!!row.id || row.busy" aria-label="Вид цены" @change="selectType(row)"><option :value="null">Без назначения · только расчёт</option><option v-for="type in data.types" :key="type.id" :value="type.id" :disabled="!type.is_active || rows.some(other => other !== row && other.scope === 'common' && other.target_id === type.id)">{{ type.name }}{{ !type.is_active ? ' (неактивен)' : '' }}</option></select><select v-model="row.currency_id" :disabled="row.busy" aria-label="Валюта расчёта" class="currency-select" @change="touch(row)"><option v-for="currency in data.currencies" :key="currency.id" :value="currency.id">{{ currency.code }}</option></select></div><small :class="{ confidential: row.scope === 'entity' }">{{ row.target_id ? audience(row) : 'Можно сохранить расчёт без назначения цены' }}</small></td>
                            <td class="numeric"><strong>{{ money(row.result?.price_gross) }} {{ currencyCode(row) }}</strong><small>{{ row.result ? `Без НДС: ${money(row.result.price_net)}` : 'Результат ожидает расчёта' }}</small><small v-if="row.result?.margin_percent != null">Маржа {{ money(row.result.margin_percent) }}%</small></td>
                            <td><label class="check"><input v-model="row.active" :disabled="row.busy" type="checkbox" @change="touch(row)">{{ row.active ? 'Активна' : 'Отключена' }}</label><small>{{ row.valid_from || 'Без начала' }} → {{ row.valid_to || 'Без окончания' }}</small><button class="price-link" :aria-expanded="row.expanded" @click="row.expanded = !row.expanded">{{ row.expanded ? 'Скрыть параметры' : 'Сроки и комментарий' }}</button><button v-if="row.scope === 'common' && data.types.some(type => Number(type.id) === Number(row.target_id) && type.is_public)" class="avito-button" :disabled="!!avitoPriceBlockReason(row, data)" :title="avitoPriceBlockReason(row, data) || 'Проверить и передать сохранённую цену в объявление'" @click="openAvito(row)">Авито · передать цену</button></td>
                            <td class="actions"><div class="button-group"><button :disabled="row.busy" @click="run(row)">Рассчитать</button><button :disabled="row.busy" @click="saveCalculation(row)">Сохранить расчёт</button><button class="primary" :disabled="row.busy || !row.dirty || !row.target_id" @click="run(row, true)">{{ row.busy ? '…' : 'Сохранить цену' }}</button></div><div class="button-group"><input v-model="row.calculation_name" :disabled="row.busy" maxlength="150" aria-label="Название расчёта" placeholder="Название расчёта (необязательно)" @input="row.dirty = true; row.version++; row.success = ''"><button v-if="row.dirty && row.id" :disabled="row.busy" @click="Object.assign(row, draft(row.saved))">Отменить</button><button v-if="!row.deleting" :disabled="row.busy" @click="row.id ? row.deleting = true : remove(row)">{{ row.id ? 'Удалить цену' : 'Убрать черновик' }}</button><template v-else><button class="danger" :disabled="row.busy" @click="remove(row)">Удалить цену?</button><button :disabled="row.busy" @click="row.deleting = false">Нет</button></template></div></td>
                        </tr>
                        <tr><td colspan="4" class="construction-cell"><PriceConstructionChain :row="row" :sources="data.sources" :elements="data.elements" :calculations="availableCalculations(row)" :measurement="row.measurement" :currency="currencyCode(row)" @touch="touch(row)" @source="selectSource(row)" @library="libraryOpen = true" @market="emit('request-market')" /></td></tr>
                        <tr v-if="row.error"><td colspan="4" class="price-error" role="alert">{{ row.error }}</td></tr><tr v-if="row.success"><td colspan="4" class="notice" role="status">{{ row.success }} <button class="price-link inline-link" @click="tab = 'calculations'">Сохранённые расчёты ↗</button></td></tr>
                        <tr v-if="row.expanded"><td colspan="4"><div class="parameters"><label>Действует с<input v-model="row.valid_from" :disabled="row.busy" type="date" @change="touch(row)"></label><label>По<input v-model="row.valid_to" :disabled="row.busy" type="date" @change="touch(row)"></label><label class="comment">Комментарий сотрудника<input v-model="row.comment" :disabled="row.busy" maxlength="2000" @input="touch(row)"></label></div></td></tr>
                    </tbody><tbody v-if="!rows.length"><tr><td colspan="4" class="empty">Добавьте расчёт или цену для Entity. Можно задать цену вручную или собрать её из элементов.</td></tr></tbody>
                </table></div>
                <section v-if="access" class="inset"><div class="pricing-toolbar"><strong>Доступ · {{ access.name }}</strong><button :disabled="accessBusy" @click="access = null; accessVersion++">Закрыть</button></div><p class="muted">Разрешайте доступ только подтверждённым представителям организации. Совпадение ИНН само по себе не открывает закрытые цены.</p><p v-if="accessError" class="price-error" role="alert">{{ accessError }}</p><p v-if="access.loading">Загрузка…</p><div v-else class="table-scroll"><table class="price-table"><thead><tr><th>Пользователь</th><th>Email</th><th>Доступ ко всем индивидуальным ценам Entity</th><th></th></tr></thead><tbody><tr v-for="user in access.users" :key="user.id"><td>{{ user.name }}</td><td>{{ user.email }}</td><td>{{ user.owner ? 'Создатель организации' : user.allowed ? 'Разрешён сотрудником' : 'Закрыт' }}</td><td><button v-if="!user.owner" :disabled="accessBusy" @click="grant(user)">{{ user.allowed ? 'Отозвать' : 'Разрешить' }}</button></td></tr><tr v-if="!access.users.length"><td colspan="4">К Entity пока не привязаны активные пользователи.</td></tr></tbody></table></div></section>
            </template>
            <template v-else-if="tab === 'history'">
                <div class="pricing-toolbar"><label>История <select v-model="historyScope" @change="loadHistory()"><option value="">Все цены</option><option value="common">Виды цен</option><option value="entity">Индивидуальные</option></select></label><span class="muted">Начальный снимок фиксирует цены на момент включения истории.</span></div>
                <p v-if="historyError" class="price-error" role="alert">{{ historyError }} <button @click="loadHistory()">Повторить</button></p><p v-if="historyLoading" class="empty" role="status">Загрузка истории…</p>
                <div v-else class="table-scroll"><table class="price-table history-table"><thead><tr><th>Дата / сотрудник</th><th>Событие</th><th>Назначение</th><th>Было · с НДС</th><th>Стало · с НДС</th><th>Подробности</th></tr></thead><tbody><tr v-for="entry in history?.data || []" :key="entry.id"><td>{{ date(entry.created_at) }}<small>{{ entry.actor_name || 'Система' }}</small></td><td>{{ events[entry.event] || entry.event }}</td><td>{{ (entry.after || entry.before)?.target_name }}<small>{{ entry.scope === 'entity' ? 'Только личный кабинет' : 'Вид цены' }}</small></td><td>{{ snapshotPrice(entry.before) }}</td><td>{{ snapshotPrice(entry.after) }}</td><td><details><summary>Изменения</summary><div v-for="(snapshot, key) in { before: entry.before, after: entry.after }" :key="key" class="snapshot"><strong>{{ key === 'before' ? 'Было' : 'Стало' }}</strong><template v-if="snapshot"><p>Без НДС: {{ money(snapshot.price_net) }} · НДС: {{ money(snapshot.vat_rate) }}%</p><p>{{ (snapshot.is_active ?? snapshot.is_published) ? 'Активна' : 'Отключена' }} · {{ snapshot.valid_from || 'Без начала' }} → {{ snapshot.valid_to || 'Без окончания' }}</p><p v-if="snapshot.pricing_input">{{ methods[snapshot.pricing_input.method] }} · {{ money(snapshot.pricing_input.parameter ?? snapshot.pricing_input.amount) }} · округление {{ money(snapshot.pricing_input.rounding_step) }}</p><p v-if="snapshot.pricing_result?.source">Источник: {{ snapshot.pricing_result.source.supplier || snapshot.pricing_result.source.name || snapshot.pricing_result.source.kind }} #{{ snapshot.pricing_result.source.id }}</p><p v-if="snapshot.manual_comment">{{ snapshot.manual_comment }}</p><table v-if="snapshot.pricing_result?.breakdown?.length" class="price-table"><thead><tr><th>Элемент</th><th class="numeric">Изменение</th><th class="numeric">Итог</th></tr></thead><tbody><tr v-for="(part, index) in snapshot.pricing_result.breakdown" :key="part.key || index"><td>{{ part.label }}<small>{{ part.formula }}</small></td><td class="numeric">{{ money(part.amount) }}</td><td class="numeric">{{ money(part.subtotal) }}</td></tr></tbody></table></template><span v-else> —</span></div></details></td></tr><tr v-if="!history?.data?.length"><td colspan="6" class="empty">История пока пуста.</td></tr></tbody></table></div>
                <div v-if="history" class="pricing-toolbar"><span class="muted">{{ history.total }} записей</span><div class="button-group"><button :disabled="historyLoading || history.current_page <= 1" @click="loadHistory(history.current_page - 1)">Назад</button><span>{{ history.current_page }} / {{ history.last_page }}</span><button :disabled="historyLoading || history.current_page >= history.last_page" @click="loadHistory(history.current_page + 1)">Далее</button></div></div>
            </template>
            <template v-else>
                <p class="archive-note">Каждое сохранение цены создаёт снимок расчёта. «Сохранить расчёт» добавляет снимок без назначения цены. Суммы показаны за единицу, действовавшую на момент сохранения.</p>
                <div class="table-scroll"><table class="price-table archive-table"><thead><tr><th>Расчёт / назначение</th><th>Дата</th><th>Способ / источник</th><th>Единица</th><th class="numeric">Без НДС</th><th class="numeric">С НДС</th><th>Валюта</th><th></th></tr></thead><tbody v-for="calculation in archive" :key="`${calculation.archive_source}:${calculation.id}`"><tr><td>{{ calculation.name || `Расчёт №${calculation.id}` }}<small :class="{ confidential: calculation.scope === 'entity' }">{{ calculation.scope === 'entity' ? `Только Entity · ${calculation.target_name}` : calculation.target_name || 'Без назначения' }}</small><small>{{ calculation.comment }}</small></td><td>{{ date(calculation.created_at) }}</td><td>{{ methods[calculation.input?.method] || calculation.source_type || 'Ранее сохранённый' }}<small>{{ calculation.result?.source?.supplier || calculation.result?.source?.name }}</small></td><td>1 {{ calculation.measurement?.unit_label || 'ед.' }}</td><td class="numeric">{{ money(calculation.price_net) }}</td><td class="numeric">{{ money(calculation.price_gross) }}</td><td>{{ calculation.currency_code }}</td><td><div class="button-group"><button @click="useCalculation(calculation)">Назначить результат</button><button v-if="calculation.archive_source === 'workspace'" @click="useCalculation(calculation, true)">Повторить расчёт</button><button :aria-expanded="archiveDetail === `${calculation.archive_source}:${calculation.id}`" @click="archiveDetail = archiveDetail === `${calculation.archive_source}:${calculation.id}` ? null : `${calculation.archive_source}:${calculation.id}`">Состав</button></div></td></tr><tr v-if="archiveDetail === `${calculation.archive_source}:${calculation.id}`"><td colspan="8"><div class="snapshot archive-snapshot"><p>Сохранённый снимок · {{ calculation.scope === 'entity' ? 'доступен для назначения только этому Entity' : 'общий расчёт' }}</p><p>НДС: {{ money(calculation.result?.vat_rate ?? calculation.vat_rate ?? calculation.input?.vat_rate) }}% · Округление: {{ money(calculation.input?.rounding_step) }} · Затраты: {{ money(calculation.result?.cost_net) }}</p><p v-if="calculation.input?.method === 'manual'">Вручную: {{ money(calculation.input.amount) }} {{ calculation.input.amount_includes_vat ? 'с НДС' : 'без НДС' }}</p><p v-if="calculation.input?.source_key">Источник: {{ calculation.result?.source?.supplier || calculation.input.source_key }} · НДС источника: {{ money(calculation.input.source_vat_rate) }}%</p><p v-for="(element, index) in calculation.input?.elements || []" :key="index">{{ index + 1 }}. {{ element.name }} · {{ money(element.value) }} {{ element.kind === 'percent' ? '%' : calculation.currency_code }} · {{ element.enabled === false ? 'исключён' : 'участвует' }}</p></div><table v-if="calculation.result?.breakdown?.length" class="price-table"><thead><tr><th>Элемент</th><th>Формула</th><th class="numeric">Изменение</th><th class="numeric">Результат</th></tr></thead><tbody><tr v-for="(part, index) in calculation.result.breakdown" :key="part.key || index"><td>{{ part.label }}</td><td>{{ part.formula || '—' }}</td><td class="numeric">{{ money(part.amount) }}</td><td class="numeric">{{ money(part.subtotal) }}</td></tr></tbody></table></td></tr></tbody><tbody v-if="!archive.length"><tr><td colspan="8" class="empty">Сохранённых расчётов пока нет. Создайте расчёт и нажмите «Сохранить расчёт» или «Сохранить цену».</td></tr></tbody></table></div>
            </template>
        </template>
        <GoodAvitoPriceDialog v-model="avitoOpen" :good-id="goodId" :price-value-id="avitoPrice" @state="avitoBusy = $event.busy" @applied="notice = 'Сохранённая цена передана в Авито.'; emit('changed')" @request-avito="emit('request-avito')" />
    </section>
</template>

<style scoped>
.pricing { min-width: 0; color: #35333a; font: 12px/1.4 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.pricing button.avito-button { display: block; margin-top: 6px; padding: 4px 7px; border-color: #cdc2df; color: #725392; background: #fbf9fe; font-size: 10px; }
.pricing-head { display: flex; justify-content: space-between; align-items: start; gap: 20px; padding: 10px 0; }
h3 { margin: 0 0 4px; font-size: 15px; font-weight: 600; } p { margin: 0; } .pricing-head p, .muted, .archive-note { color: #77727c; font-size: 11px; }
.partners { max-width: 330px; max-height: 90px; overflow: auto; flex-shrink: 0; } .partners .price-table { font-size: 10px; } .partners td { padding: 2px 6px; } .partners td:first-child { color: #817a87; } caption { text-align: right; font-size: 9px; color: #817a87; padding-bottom: 3px; }
.pricing-toolbar, .button-group, .inline { display: flex; align-items: center; gap: 6px; } .pricing-toolbar { justify-content: space-between; flex-wrap: wrap; padding: 7px 0; } .segments { display: flex; padding: 2px; border: 1px solid #d6d3d9; border-radius: 6px; background: #efedf1; } .segments button { border: 0; background: transparent; } .segments button[aria-pressed=true] { background: white; box-shadow: 0 1px 3px #2b233721; color: #25232a; }
button, input, select { font: inherit; } button { min-height: 26px; padding: 3px 9px; border: 1px solid #d6d3d9; border-radius: 4px; background: #fff; cursor: pointer; white-space: nowrap; } button:hover:enabled { background: #f0edf6; } button:disabled { opacity: .45; cursor: default; } button.primary { background: #4266b0; border-color: #4266b0; color: white; } button.primary:hover:enabled { background: #365b9f; } button.danger { color: #b02e35; } .price-link { display: block; border: none; background: none; color: #4266a1; padding: 2px 0; min-height: 20px; font-size: 10px; }
input:not([type=checkbox]), select { min-height: 27px; max-width: 100%; padding: 3px 6px; border: 1px solid #d7d4dc; border-radius: 4px; color: #38343f; background: #fff; } select { width: 100%; text-overflow: ellipsis; } input:disabled, select:disabled { background: #f4f3f5; color: #625b6a; } :is(button, input, select, summary):focus-visible { outline: 2px solid #739ade; outline-offset: 2px; }
.table-scroll { overflow-x: auto; border: 1px solid #d6d3d9; border-radius: 5px; } .price-table { width: 100%; border-collapse: collapse; font-size: 11px; text-align: left; } .price-table th { padding: 6px 8px; color: #77727c; font-size: 10px; font-weight: 600; background: #f2f1f4; border-bottom: 1px solid #d6d3d9; white-space: nowrap; } .price-table td { padding: 6px 8px; border-bottom: 1px solid #e7e5ea; vertical-align: middle; } .price-table tbody:nth-child(even) { background: #faf9fb; } .price-table tbody.modified { background: #f2f6ff; } .price-table small { display: block; color: #8b8492; font-size: 10px; margin-top: 3px; } .price-table small.confidential { color: #80649b; } .numeric { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; } .numeric strong { font-size: 13px; color: #303b52; }
.source-cell select + select { margin-top: 5px; } .target-cell strong { overflow-wrap: anywhere; } .number-input { width: 88px; } .vat-input { width: 55px; } .check { display: flex; align-items: center; gap: 5px; font-size: 10px; white-space: nowrap; } input[type=checkbox] { accent-color: #5077b8; width: 13px; height: 13px; } .actions .button-group + .button-group { margin-top: 4px; } .actions button { font-size: 10px; padding: 3px 6px; }
.parameters { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; padding: 5px 0; } .parameters label { display: grid; gap: 3px; color: #736c7c; font-size: 10px; width: 125px; } .parameters .check { display: flex; align-self: center; } .parameters .comment { flex: 1; min-width: 200px; } .inset { padding: 10px; margin: 8px 0; border: 1px solid #d6d3d9; border-radius: 5px; background: #faf9fc; } .inset .pricing-toolbar label { display: flex; align-items: center; gap: 8px; flex: 1; } .inset input { flex: 1; } .empty { padding: 24px !important; text-align: center; color: #827a8b; } .price-error { color: #aa2938; padding: 8px; background: #fff0f2; } .history-table { min-width: 780px; } summary { cursor: pointer; color: #4266a1; } .snapshot { margin-top: 8px; max-width: 600px; overflow-wrap: anywhere; } .archive-note { margin: 9px 0; }
.editor-table { min-width: 920px; table-layout: fixed; }.editor-table th:nth-child(1) { width: 27%; }.editor-table th:nth-child(2) { width: 17%; }.editor-table th:nth-child(3) { width: 18%; }.editor-table th:nth-child(4) { width: 38%; }.row-target { display: flex; align-items: center; gap: 7px; }.row-target > select { flex: 1; min-width: 0; }.row-target .currency-select { flex: 0 0 65px; }.construction-cell { padding-top: 3px !important; border-bottom-color: #c5bfce !important; }.notice { padding: 6px 8px; color: #42634e; font-size: 10px; background: #f1f7f2; }.inline-link { display: inline; margin-left: 7px; }.archive-table { min-width: 980px; }.archive-snapshot { max-width: none; margin: 0 0 9px; }.archive-snapshot p + p { margin-top: 4px; }.library .empty { padding: 12px !important; }.library-unit-warning { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; flex-basis: 100%; padding: 7px; color: #875721; background: #fff7e8; border: 1px solid #ead8b6; border-radius: 4px; font-size: 10px; }.library-unit-warning span { flex: 1; min-width: 240px; }.library-form { border-top: 1px solid #ddd8e4; margin-top: 10px; padding-top: 12px; }.actions input { flex: 1; min-width: 70px; min-height: 24px; font-size: 10px; }.editor-table .actions .button-group { flex-wrap: wrap; }.library .parameters label { width: 135px; }
@media (max-width: 700px) { .pricing-head { flex-direction: column; gap: 8px; } .partners { max-width: 100%; } .segments { flex-wrap: wrap; } .segments button { padding-inline: 7px; } }
</style>
