<script setup>
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import SupplierOrderComposer from '@/Components/Unit/Mail/SupplierOrderComposer.vue'

const props = defineProps({ unitId: { type: [Number, String], default: null }, unitName: { type: String, default: '' }, unitFiles: { type: Array, default: () => [] } })
const rows = ref([]), loading = ref(false), loadError = ref(''), operationError = ref(''), notice = ref('')
const search = ref(''), status = ref(''), supplierFilter = ref(null)
const meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const composerOpen = ref(false), selectedDocument = ref(null), composerKey = ref(0)
const pickerOpen = ref(false), pickerPurpose = ref('create'), supplierSearch = ref(''), supplierOptions = ref([]), supplierLoading = ref(false), supplierError = ref('')
const confirmation = ref(null), busyId = ref(null)
let listGeneration = 0, pickerGeneration = 0, mutationGeneration = 0, disposed = false
let listController = null, pickerController = null, searchTimer = null, pickerTimer = null
const statuses = [
    { value: 'draft', label: 'Черновик' }, { value: 'sending', label: 'Отправляется' },
    { value: 'sent', label: 'Отправлен' }, { value: 'confirmed', label: 'Подтверждён' },
    { value: 'closed', label: 'Закрыт' }, { value: 'cancelled', label: 'Отменён' },
    { value: 'send_unknown', label: 'Результат отправки неизвестен' },
]
const unitFilterId = computed(() => Number(props.unitId) || supplierFilter.value?.id || null)
const range = computed(() => meta.total ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} из ${meta.total}` : '0 заказов')
const message = failure => Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось выполнить действие. Повторите попытку.'
const statusLabel = row => row.status_label || statuses.find(item => item.value === row.status)?.label || row.status
const date = (value, withTime = false) => {
    if (!value) return '—'
    const parsed = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00` : value)
    return Number.isNaN(parsed.getTime()) ? '—' : parsed.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}) })
}

async function load(page = meta.current_page) {
    clearTimeout(searchTimer)
    listController?.abort()
    const version = ++listGeneration
    listController = new AbortController()
    loading.value = true; loadError.value = ''
    try {
        const { data } = await axios.get('/api/supplier-orders', { signal: listController.signal, params: { page, per_page: meta.per_page, search: search.value.trim() || undefined, status: status.value || undefined, unit_id: unitFilterId.value || undefined } })
        if (disposed || version !== listGeneration) return
        if (Number(data.current_page) > Number(data.last_page || 1)) { await load(Number(data.last_page) || 1); return }
        rows.value = data.data || []
        Object.assign(meta, { current_page: Number(data.current_page) || 1, last_page: Number(data.last_page) || 1, total: Number(data.total) || 0, per_page: Number(data.per_page) || 25 })
    } catch (failure) { if (!disposed && version === listGeneration && failure.code !== 'ERR_CANCELED') loadError.value = message(failure) }
    finally { if (!disposed && version === listGeneration) loading.value = false }
}
function scheduleSearch() {
    listController?.abort(); listGeneration++; clearTimeout(searchTimer)
    confirmation.value = null; loading.value = true
    searchTimer = setTimeout(() => load(1), 300)
}
function refresh() { confirmation.value = null; return load() }
function openDocument(row = null, unit = null) {
    selectedDocument.value = { id: row?.id || null, unit_id: row?.unit_id || unit?.id || Number(props.unitId), unit_name: row?.unit?.name || unit?.name || props.unitName, unit_files: [...props.unitFiles] }
    composerKey.value++; composerOpen.value = true
}
function create() { if (props.unitId) openDocument(); else openPicker('create') }
function closeComposer() { composerOpen.value = false; selectedDocument.value = null }
function saved(document) { notice.value = `Заказ ${document.number || ''} сохранён.`; load() }
function sent(document) { notice.value = `Заказ ${document.number || ''} отправлен.`; load() }

function openPicker(purpose) {
    pickerPurpose.value = purpose; supplierSearch.value = ''; supplierOptions.value = []; supplierError.value = ''; pickerOpen.value = true
    loadUnits()
}
async function loadUnits() {
    clearTimeout(pickerTimer); pickerController?.abort()
    const version = ++pickerGeneration
    pickerController = new AbortController(); supplierLoading.value = true; supplierError.value = ''
    try {
        const { data } = await axios.get('/api/supplier-order-units', { signal: pickerController.signal, params: { search: supplierSearch.value.trim() || undefined } })
        if (!disposed && version === pickerGeneration && pickerOpen.value) supplierOptions.value = data || []
    } catch (failure) { if (!disposed && version === pickerGeneration && failure.code !== 'ERR_CANCELED') supplierError.value = message(failure) }
    finally { if (!disposed && version === pickerGeneration) supplierLoading.value = false }
}
function chooseUnit(unit) {
    if (pickerPurpose.value === 'create') openDocument(null, unit)
    else supplierFilter.value = { id: unit.id, name: unit.name }
    pickerOpen.value = false
}
function ask(row, action) { confirmation.value = { id: row.id, number: row.number, revision: row.revision, action } }
async function mutate(row, action) {
    if (busyId.value !== null) return
    const version = ++mutationGeneration
    busyId.value = row.id; operationError.value = ''; notice.value = ''
    try {
        if (action === 'delete') await axios.delete(`/api/supplier-orders/${row.id}`, { data: { revision: row.revision } })
        else await axios.post(`/api/supplier-orders/${row.id}/status`, { revision: row.revision, status: action })
        if (disposed || version !== mutationGeneration) return
        confirmation.value = null
        notice.value = action === 'delete' ? `Черновик ${row.number} удалён.` : `Статус заказа ${row.number} изменён.`
        await load()
    } catch (failure) { if (!disposed && version === mutationGeneration) operationError.value = message(failure) }
    finally { if (!disposed && version === mutationGeneration) busyId.value = null }
}

watch(search, scheduleSearch)
watch([status, unitFilterId], () => { confirmation.value = null; rows.value = []; load(1) }, { immediate: true })
watch(supplierSearch, () => {
    if (!pickerOpen.value) return
    pickerController?.abort(); pickerGeneration++; clearTimeout(pickerTimer); supplierLoading.value = true
    pickerTimer = setTimeout(loadUnits, 250)
}, { flush: 'sync' })
watch(pickerOpen, open => { if (!open) { pickerController?.abort(); pickerGeneration++; clearTimeout(pickerTimer); supplierLoading.value = false } })
onScopeDispose(() => {
    disposed = true; listGeneration++; pickerGeneration++; mutationGeneration++
    listController?.abort(); pickerController?.abort(); clearTimeout(searchTimer); clearTimeout(pickerTimer)
})
</script>

<template>
    <section class="supplier-register" aria-label="Заказы поставщикам">
        <header class="supplier-register__heading">
            <div><strong>{{ unitName ? `Заказы · ${unitName}` : 'Заказы поставщикам' }}</strong><span>Приход оформляется отдельно в закупках.</span></div>
            <button type="button" class="primary" @click="create">+ Новый заказ</button>
        </header>
        <div class="supplier-register__filters">
            <label class="search"><span class="sr-only">Поиск по номеру, поставщику или товару</span><input v-model="search" type="search" maxlength="150" placeholder="Номер, поставщик или товар" /></label>
            <label><span class="sr-only">Статус заказа</span><select v-model="status"><option value="">Все статусы</option><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
            <div v-if="!unitId" class="supplier-register__supplier-filter"><button type="button" @click="openPicker('filter')">{{ supplierFilter?.name || 'Все поставщики' }} <span aria-hidden="true">⌄</span></button><button v-if="supplierFilter" type="button" aria-label="Сбросить поставщика" @click="supplierFilter = null">×</button></div>
            <button type="button" :disabled="loading" @click="refresh">Обновить</button>
            <span class="supplier-register__count" aria-live="polite">{{ loading ? 'Загрузка…' : range }}</span>
        </div>
        <div v-if="loadError" role="alert" class="supplier-register__error">{{ loadError }} <button type="button" @click="load()">Повторить загрузку</button></div>
        <div v-if="operationError" role="alert" class="supplier-register__error">{{ operationError }} <button type="button" @click="refresh">Обновить реестр</button><button type="button" aria-label="Скрыть сообщение об ошибке" @click="operationError = ''">×</button></div>
        <div v-if="notice" role="status" class="supplier-register__notice">{{ notice }} <button type="button" aria-label="Скрыть уведомление" @click="notice = ''">×</button></div>
        <div class="supplier-register__scroll" :aria-busy="loading">
            <table class="supplier-register__table" :class="{ 'is-loading': loading }">
                <thead><tr><th>Заказ / создан</th><th v-if="!unitId">Поставщик</th><th>Склад / доставка</th><th>Статус</th><th class="numeric">Позиций</th><th>Отправлен</th><th><span class="sr-only">Действия</span></th></tr></thead>
                <tbody>
                    <template v-for="row in rows" :key="row.id">
                        <tr>
                            <td><button type="button" class="text-link" :disabled="busyId !== null || loading" @click="openDocument(row)">{{ row.number }}</button><small>{{ date(row.created_at, true) }}</small></td>
                            <td v-if="!unitId"><Link v-if="row.unit" :href="`/Ameise/unit/${row.unit.id}`" class="unit-link">{{ row.unit.name }}</Link><span v-else>—</span></td>
                            <td><span>{{ row.warehouse?.name || 'Склад не указан' }}</span><small>{{ row.delivery_date ? `Доставка ${date(row.delivery_date)}` : 'Дата доставки не задана' }}</small></td>
                            <td><span class="supplier-status" :class="`supplier-status--${row.status}`">{{ statusLabel(row) }}</span></td>
                            <td class="numeric">{{ row.items_count ?? row.items?.length ?? 0 }}</td>
                            <td class="date-cell">{{ date(row.sent_at, true) }}</td>
                            <td class="supplier-register__actions">
                                <button type="button" :disabled="busyId !== null || loading" @click="openDocument(row)">{{ row.status === 'draft' ? 'Открыть / изменить' : 'Открыть' }}</button>
                                <button v-if="row.status === 'sent'" type="button" :disabled="busyId !== null || loading" @click="mutate(row, 'confirmed')">Подтвердить</button>
                                <button v-if="row.status === 'confirmed'" type="button" :disabled="busyId !== null || loading" @click="mutate(row, 'closed')">Закрыть заказ</button>
                                <button v-if="['draft', 'sent', 'confirmed'].includes(row.status)" type="button" :disabled="busyId !== null || loading" @click="ask(row, 'cancelled')">Отменить</button>
                                <button v-if="row.status === 'draft'" type="button" class="danger" :disabled="busyId !== null || loading" @click="ask(row, 'delete')">Удалить</button>
                            </td>
                        </tr>
                        <tr v-if="confirmation?.id === row.id" class="supplier-register__confirmation"><td :colspan="unitId ? 6 : 7">
                            <span>{{ confirmation.action === 'delete' ? 'Удалить черновик' : 'Отменить заказ' }} {{ confirmation.number }}?</span>
                            <button type="button" class="danger" :disabled="busyId !== null" @click="mutate(confirmation, confirmation.action)">{{ busyId === row.id ? 'Сохранение…' : 'Подтвердить' }}</button>
                            <button type="button" :disabled="busyId !== null" @click="confirmation = null">Оставить</button>
                        </td></tr>
                    </template>
                    <tr v-if="!rows.length"><td :colspan="unitId ? 6 : 7" class="supplier-register__empty">{{ loading ? 'Загрузка заказов…' : loadError ? 'Не удалось загрузить заказы.' : 'Заказов по выбранным условиям нет.' }}</td></tr>
                </tbody>
            </table>
        </div>
        <footer class="supplier-register__pagination"><span>{{ range }}</span><div><button type="button" :disabled="loading || meta.current_page <= 1" @click="load(meta.current_page - 1)">← Назад</button><span>{{ meta.current_page }} / {{ meta.last_page }}</span><button type="button" :disabled="loading || meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Далее →</button></div></footer>

        <v-dialog v-model="pickerOpen" max-width="520">
            <section class="supplier-picker">
                <header><strong>{{ pickerPurpose === 'create' ? 'Выберите поставщика для нового заказа' : 'Фильтр по поставщику' }}</strong><button type="button" aria-label="Закрыть выбор поставщика" @click="pickerOpen = false">×</button></header>
                <label><span class="sr-only">Название поставщика</span><input v-model="supplierSearch" type="search" maxlength="120" placeholder="Найти организацию" autofocus /></label>
                <p v-if="supplierError" role="alert" class="supplier-register__error">{{ supplierError }} <button type="button" @click="loadUnits">Повторить</button></p>
                <p v-if="supplierLoading" role="status">Поиск…</p>
                <div v-else class="supplier-picker__list"><button v-for="unit in supplierOptions" :key="unit.id" type="button" @click="chooseUnit(unit)">{{ unit.name }} <span>→</span></button><p v-if="!supplierOptions.length && !supplierError">Поставщики не найдены.</p></div>
            </section>
        </v-dialog>
        <v-dialog v-model="composerOpen" persistent width="96vw" max-width="1800" class="supplier-order-dialog">
            <SupplierOrderComposer v-if="composerOpen && selectedDocument" :key="composerKey" :unit-id="selectedDocument.unit_id" :order-id="selectedDocument.id" :unit-files="selectedDocument.unit_files" @close="closeComposer" @saved="saved" @sent="sent" />
        </v-dialog>
    </section>
</template>

<style scoped>
.supplier-register { min-width: 0; background: #fff; color: #302d35; border: 1px solid #d6d3d9; border-radius: 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 12px; }
.supplier-register button, .supplier-picker button, .supplier-register input, .supplier-register select, .supplier-picker input { font: inherit; }
.supplier-register button, .supplier-picker button { min-height: 28px; padding: 4px 9px; border: 1px solid #d7d3dc; border-radius: 4px; background: #fff; color: #514958; cursor: pointer; line-height: 1.3; }
.supplier-register button:hover:not(:disabled), .supplier-picker button:hover:not(:disabled) { background: #f3f0f7; }
.supplier-register button:disabled { cursor: default; opacity: .48; }
.supplier-register button:focus-visible, .supplier-picker button:focus-visible, .supplier-register input:focus-visible, .supplier-register select:focus-visible, .supplier-picker input:focus-visible { outline: 2px solid #638bc2; outline-offset: 2px; }
.supplier-register button.primary { background: #506f9b; border-color: #506f9b; color: #fff; }
.supplier-register button.primary:hover { background: #415f8a; }
.supplier-register button.danger { color: #943e49; }
.supplier-register__heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 11px; border-bottom: 1px solid #e4e0e9; }
.supplier-register__heading strong { display: block; font-size: 13px; font-weight: 600; }
.supplier-register__heading span { display: block; font-size: 10px; color: #837b8a; margin-top: 2px; }
.supplier-register__filters { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; padding: 8px 10px; background: #faf9fb; }
.supplier-register input, .supplier-register select, .supplier-picker input { height: 29px; min-width: 0; width: 100%; border: 1px solid #d6d2dd; border-radius: 4px; padding: 4px 8px; background: #fff; color: inherit; }
.supplier-register select { padding-right: 26px; max-width: 240px; }
.supplier-register .search { flex: 1 1 240px; max-width: 420px; }
.supplier-register__supplier-filter { display: flex; max-width: 300px; gap: 3px; }
.supplier-register__supplier-filter > :first-child { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.supplier-register__count { margin-left: auto; color: #81798a; font-size: 10px; white-space: nowrap; }
.supplier-register__scroll { max-width: 100%; overflow: auto; }
.supplier-register__table { border-collapse: collapse; width: 100%; font-size: 11px; }
.supplier-register__table.is-loading { opacity: .6; }
.supplier-register__table th { color: #7a7283; background: #f1eff4; padding: 7px 9px; font-size: 10px; font-weight: 600; text-align: left; white-space: nowrap; border-top: 1px solid #ddd8e3; border-bottom: 1px solid #d6d1dd; }
.supplier-register__table td { padding: 6px 9px; border-bottom: 1px solid #e6e1eb; vertical-align: middle; }
.supplier-register__table tbody > tr:nth-child(even) { background: #fbfafc; }
.supplier-register__table tbody > tr:hover { background: #f4f2f8; }
.supplier-register__table small { display: block; color: #857e8d; font-size: 10px; margin-top: 3px; white-space: nowrap; }
.supplier-register__table .numeric { text-align: right; font-variant-numeric: tabular-nums; }
.supplier-register__table .date-cell { white-space: nowrap; font-variant-numeric: tabular-nums; }
.supplier-register button.text-link { border: 0; min-height: 0; background: transparent; color: #4d6590; padding: 0; text-align: left; font-weight: 600; white-space: nowrap; }
.supplier-register .unit-link { color: #52466a; text-decoration: none; min-width: 140px; display: inline-block; }
.supplier-register .unit-link:hover { text-decoration: underline; }
.supplier-register__actions { min-width: 200px; }
.supplier-register__actions button { font-size: 10px; min-height: 25px; padding: 3px 6px; margin: 2px; white-space: nowrap; }
.supplier-status { display: inline-block; border: 1px solid #ddd7e5; background: #f1eef5; color: #766784; padding: 3px 6px; border-radius: 4px; font-size: 10px; white-space: nowrap; }
.supplier-status--sent { background: #edf3fc; border-color: #ccd9eb; color: #456793; }
.supplier-status--confirmed, .supplier-status--closed { background: #edf5ef; border-color: #d1e1d6; color: #477159; }
.supplier-status--sending, .supplier-status--send_unknown { background: #fff7e9; border-color: #ebd8b6; color: #8e691f; }
.supplier-status--cancelled { background: #f8f0f1; border-color: #e9d7da; color: #936470; }
.supplier-register__table .supplier-register__empty { padding: 35px 15px; text-align: center; color: #8d8398; }
.supplier-register__confirmation td { background: #fff8ee; }
.supplier-register__confirmation button { margin-left: 8px; font-size: 11px; }
.supplier-register__error, .supplier-register__notice { padding: 8px 10px; line-height: 1.5; font-size: 11px; overflow-wrap: anywhere; }
.supplier-register__error { color: #923d48; background: #fff5f5; border-bottom: 1px solid #e6ccd0; }
.supplier-register__notice { color: #46715b; background: #f3f8f5; }
.supplier-register__error button, .supplier-register__notice button { margin-left: 8px; font-size: 10px; min-height: 22px; }
.supplier-register__pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 7px 10px; color: #83798e; font-size: 10px; }
.supplier-register__pagination > div { display: flex; gap: 9px; align-items: center; }
.supplier-register__pagination button { font-size: 10px; min-height: 25px; }
.supplier-picker { background: #fff; border: 1px solid #d5cfdf; border-radius: 7px; padding: 14px; color: #40384a; font-size: 12px; }
.supplier-picker header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
.supplier-picker header strong { font-size: 13px; }
.supplier-picker__list { display: grid; gap: 4px; max-height: 50vh; overflow: auto; margin-top: 10px; }
.supplier-picker__list button { display: flex; justify-content: space-between; text-align: left; padding: 8px; }
.supplier-picker p { margin-top: 12px; color: #84758e; }
.sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
@media (max-width: 700px) { .supplier-register__heading { align-items: flex-start; } .supplier-register__heading > button { flex-shrink: 0; } .supplier-register__filters .search { max-width: none; flex-basis: 100%; } .supplier-register__count { width: 100%; } .supplier-register__pagination { flex-wrap: wrap; } }
</style>
