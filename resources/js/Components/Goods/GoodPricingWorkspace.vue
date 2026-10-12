<script setup>
import { computed, onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({ goodId: { type: [Number, String], required: true }, active: { type: Boolean, default: true } })
const emit = defineEmits(['changed', 'state'])
const data = ref(null), rows = ref([]), tab = ref('prices'), loading = ref(false), error = ref('')
const history = ref(null), historyScope = ref(''), historyLoading = ref(false), historyError = ref('')
const entitySearch = ref(''), entities = ref(null), entityError = ref(''), entityLoading = ref(false), choosingEntity = ref(false)
const access = ref(null), accessError = ref(''), accessBusy = ref(false)
let generation = 0, sequence = 0, historyVersion = 0, entityVersion = 0, accessVersion = 0
const dirty = computed(() => rows.value.some(row => row.dirty))
const busy = computed(() => rows.value.some(row => row.busy) || accessBusy.value)
const base = () => `/api/goods/${props.goodId}/pricing`
const money = value => value === null || value === undefined ? '—' : Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 4 })
const date = value => value ? new Date(value).toLocaleString('ru-RU') : '—'
const message = failure => Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось выполнить действие. Повторите попытку.'
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
        rounding_step: input.rounding_step ?? 0, calculation_id: input.calculation_id || null,
        active: saved.active ?? true, valid_from: saved.valid_from || '', valid_to: saved.valid_to || '', comment: saved.comment || '',
        result: saved.result || (saved.id ? { price_net: saved.price_net, price_gross: saved.price_gross } : null),
        dirty: !saved.id, busy: false, error: '', expanded: false, deleting: false, version: 0, saved }
}
function payload(row) {
    const keys = ['id', 'revision', 'scope', 'target_id', 'method', 'amount', 'amount_includes_vat', 'source_key', 'source_includes_vat', 'source_vat_rate', 'vat_rate', 'parameter', 'currency_id', 'rounding_step', 'calculation_id', 'active', 'valid_from', 'valid_to', 'comment']
    return { ...Object.fromEntries(keys.map(key => [key, row[key] === '' ? null : row[key]])), measurement: data.value.measurement }
}
function touch(row) { row.dirty = true; row.result = null; row.error = ''; row.version++ }
function selectType(row) {
    const type = data.value.types.find(item => item.id === Number(row.target_id))
    if (type) { row.target_name = type.name; row.currency_id = type.currency_id || row.currency_id; row.rounding_step = type.rounding_step || 0 }
    touch(row)
}
function selectSource(row) {
    const source = data.value.sources.find(item => item.key === row.source_key)
    if (source?.currency_id) row.currency_id = source.currency_id
    touch(row)
}
function reset() { rows.value = (data.value?.rows || []).map(draft) }
async function load() {
    const version = ++generation, url = base()
    loading.value = true; error.value = ''
    try {
        const response = await axios.get(url)
        if (version !== generation) return
        data.value = response.data; reset(); history.value = null
    } catch (failure) { if (version === generation) error.value = message(failure) }
    finally { if (version === generation) loading.value = false }
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
            Object.assign(row, draft(response.data)); history.value = null; emit('changed')
        } else row.result = response.data
    } catch (failure) { if (version === generation) row.error = message(failure) }
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
function useCalculation(calculation) {
    add(); Object.assign(rows.value.at(-1), { method: 'saved', calculation_id: calculation.id, currency_id: calculation.currency_id, vat_rate: calculation.vat_rate ?? data.value.vat_rate })
}
const sourceLabel = source => `${source.kind === 'purchase' ? 'Закупка' : 'Предложение'} · ${source.supplier || 'Без поставщика'} · ${money(source.price)} ${source.currency_code || ''} / ${money(source.denominator)} ${source.unit_label || '?'} · ${String(source.date || '').slice(0, 10)}`
function snapshotPrice(snapshot) { return snapshot ? `${money(snapshot.price_gross ?? snapshot.price_net)} ${snapshot.currency_code || ''} / ${snapshot.measurement?.unit_label || '?'}` : '—' }
function audience(row) {
    if (row.scope === 'entity') return 'Только личный кабинет Entity'
    const type = data.value.types.find(item => item.id === Number(row.target_id))
    return type?.is_public ? 'Общедоступная' : 'Для зарегистрированных'
}
watch([dirty, busy], ([dirty, busy]) => emit('state', { dirty, busy }), { immediate: true })
watch(() => props.goodId, () => { generation++; data.value = null; rows.value = []; history.value = null; access.value = null; choosingEntity.value = false; tab.value = 'prices'; if (props.active) load() }, { immediate: true })
watch(() => props.active, active => { if (active && !data.value && !loading.value) load() })
watch(tab, value => { if (value === 'history' && !history.value) loadHistory() })
onScopeDispose(() => { generation++; historyVersion++; entityVersion++; accessVersion++ })
defineExpose({ reset, refresh: () => dirty.value ? undefined : load() })
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
                <div class="pricing-toolbar"><div class="button-group"><button @click="add()">＋ Вид цены</button><button @click="choosingEntity = !choosingEntity; choosingEntity && searchEntities()">＋ Цена для Entity</button></div><span class="muted">{{ rows.length }} цен · {{ dirty ? 'Есть несохранённые изменения' : 'Изменения сохранены' }}</span></div>
                <section v-if="choosingEntity" class="inset" aria-label="Выбор Entity">
                    <form class="pricing-toolbar" @submit.prevent="searchEntities()"><label>Entity <input v-model="entitySearch" placeholder="Название или ИНН" maxlength="150"></label><button :disabled="entityLoading">Найти</button><button type="button" @click="choosingEntity = false">Закрыть</button></form>
                    <p v-if="entityError" class="price-error" role="alert">{{ entityError }}</p>
                    <p v-if="entityLoading" role="status">Поиск…</p>
                    <table v-else-if="entities" class="price-table"><thead><tr><th>Организация</th><th>ИНН</th><th></th></tr></thead><tbody><tr v-for="entity in entities.data" :key="entity.id"><td>{{ entity.name }}</td><td>{{ entity.INN || '—' }}</td><td><button :disabled="rows.some(row => row.scope === 'entity' && row.target_id === entity.id)" @click="add('entity', entity)">Выбрать</button></td></tr><tr v-if="!entities.data.length"><td colspan="3">Организации не найдены</td></tr></tbody></table>
                    <div v-if="entities?.last_page > 1" class="pricing-toolbar"><button :disabled="entityLoading || entities.current_page <= 1" @click="searchEntities(entities.current_page - 1)">Назад</button><span>{{ entities.current_page }} / {{ entities.last_page }}</span><button :disabled="entityLoading || entities.current_page >= entities.last_page" @click="searchEntities(entities.current_page + 1)">Далее</button></div>
                </section>
                <div class="table-scroll"><table class="price-table editor-table"><thead><tr><th>Назначение</th><th>Источник / способ</th><th>Расчёт</th><th>НДС</th><th class="numeric">Без НДС</th><th class="numeric">С НДС / {{ data.measurement.unit_label || 'ед.' }}</th><th>Действие цены</th><th></th></tr></thead>
                    <tbody v-for="row in rows" :key="row.key" :class="{ modified: row.dirty }">
                        <tr>
                            <td class="target-cell"><template v-if="row.scope === 'entity'"><strong>{{ row.target_name }}</strong><button class="price-link" @click="openAccess(row)">Доступ к цене ↗</button></template><select v-else v-model="row.target_id" :disabled="!!row.id || row.busy" aria-label="Вид цены" @change="selectType(row)"><option :value="null" disabled>Выберите вид цены</option><option v-for="type in data.types" :key="type.id" :value="type.id" :disabled="!type.is_active || rows.some(other => other !== row && other.scope === 'common' && other.target_id === type.id)">{{ type.name }}{{ !type.is_active ? ' (неактивен)' : '' }}</option></select><small :class="{ confidential: row.scope === 'entity' }">{{ audience(row) }}</small></td>
                            <td class="source-cell"><select v-model="row.method" :disabled="row.busy" aria-label="Способ расчёта" @change="touch(row)"><option v-for="(label, value) in methods" :key="value" :value="value">{{ label }}</option></select><select v-if="!['manual', 'saved'].includes(row.method)" v-model="row.source_key" :disabled="row.busy" aria-label="Источник себестоимости" @change="selectSource(row)"><option value="" disabled>Закупка или предложение</option><option v-for="source in data.sources" :key="source.key" :value="source.key">{{ sourceLabel(source) }}</option></select><select v-if="row.method === 'saved'" v-model="row.calculation_id" :disabled="row.busy" aria-label="Сохранённый расчёт" @change="touch(row)"><option :value="null" disabled>Выберите расчёт</option><option v-for="calculation in data.calculations" :key="calculation.id" :value="calculation.id">{{ calculation.name || `Расчёт №${calculation.id}` }}</option></select></td>
                            <td><input v-if="row.method !== 'saved'" v-model="row[row.method === 'manual' ? 'amount' : 'parameter']" :disabled="row.busy" type="number" min="0" step="0.0001" :aria-label="row.method === 'manual' ? 'Цена' : 'Параметр расчёта'" class="number-input" @input="touch(row)"><label v-if="row.method === 'manual'" class="check"><input v-model="row.amount_includes_vat" :disabled="row.busy" type="checkbox" @change="touch(row)">С НДС</label><small v-else-if="row.method === 'absolute'">{{ data.currencies.find(item => item.id === Number(row.currency_id))?.code }} / {{ data.measurement.unit_label }}</small><small v-else>{{ row.method === 'saved' ? 'Из архива' : '%' }}</small></td>
                            <td><div class="inline"><input v-model="row.vat_rate" :disabled="row.busy" type="number" min="0" max="100" step="0.0001" aria-label="НДС, %" class="vat-input" @input="touch(row)"><span>%</span></div></td>
                            <td class="numeric">{{ money(row.result?.price_net) }}<small v-if="row.result?.cost_net != null">Себест. {{ money(row.result.cost_net) }}</small></td>
                            <td class="numeric"><strong>{{ money(row.result?.price_gross) }}</strong><small>{{ data.currencies.find(item => item.id === Number(row.currency_id))?.code }}</small><small v-if="row.result?.margin_percent != null">Маржа {{ money(row.result.margin_percent) }}%</small></td>
                            <td><label class="check"><input v-model="row.active" :disabled="row.busy" type="checkbox" @change="touch(row)">{{ row.active ? 'Активна' : 'Отключена' }}</label><small>{{ row.valid_from || 'Без начала' }} → {{ row.valid_to || 'Без окончания' }}</small><button class="price-link" :aria-expanded="row.expanded" @click="row.expanded = !row.expanded">{{ row.expanded ? 'Скрыть параметры' : 'Параметры' }}</button></td>
                            <td class="actions"><div class="button-group"><button :disabled="row.busy" @click="run(row)">Рассчитать</button><button class="primary" :disabled="row.busy || !row.dirty" @click="run(row, true)">{{ row.busy ? '…' : 'Сохранить' }}</button></div><div class="button-group"><button v-if="row.dirty && row.id" :disabled="row.busy" @click="Object.assign(row, draft(row.saved))">Отменить</button><button v-if="!row.deleting" :disabled="row.busy" @click="row.id ? row.deleting = true : remove(row)">Удалить</button><template v-else><button class="danger" :disabled="row.busy" @click="remove(row)">Удалить цену?</button><button :disabled="row.busy" @click="row.deleting = false">Нет</button></template></div></td>
                        </tr>
                        <tr v-if="row.error"><td colspan="8" class="price-error" role="alert">{{ row.error }}</td></tr>
                        <tr v-if="row.expanded"><td colspan="8"><div class="parameters"><label>Валюта<select v-model="row.currency_id" :disabled="row.busy" @change="touch(row)"><option v-for="currency in data.currencies" :key="currency.id" :value="currency.id">{{ currency.code }}</option></select></label><label>Округление вверх<input v-model="row.rounding_step" :disabled="row.busy" type="number" min="0" step="0.0001" @input="touch(row)"></label><template v-if="!['manual', 'saved'].includes(row.method)"><label class="check"><input v-model="row.source_includes_vat" :disabled="row.busy" type="checkbox" @change="touch(row)">Источник с НДС</label><label>НДС источника, %<input v-model="row.source_vat_rate" :disabled="row.busy" type="number" min="0" max="100" step="0.0001" @input="touch(row)"></label></template><label>Действует с<input v-model="row.valid_from" :disabled="row.busy" type="date" @change="touch(row)"></label><label>По<input v-model="row.valid_to" :disabled="row.busy" type="date" @change="touch(row)"></label><label class="comment">Комментарий сотрудника<input v-model="row.comment" :disabled="row.busy" maxlength="2000" @input="touch(row)"></label></div></td></tr>
                    </tbody><tbody v-if="!rows.length"><tr><td colspan="8" class="empty">Цены ещё не назначены. Добавьте вид цены или индивидуальную цену Entity.</td></tr></tbody>
                </table></div>
                <section v-if="access" class="inset"><div class="pricing-toolbar"><strong>Доступ · {{ access.name }}</strong><button :disabled="accessBusy" @click="access = null; accessVersion++">Закрыть</button></div><p class="muted">Разрешайте доступ только подтверждённым представителям организации. Совпадение ИНН само по себе не открывает закрытые цены.</p><p v-if="accessError" class="price-error" role="alert">{{ accessError }}</p><p v-if="access.loading">Загрузка…</p><div v-else class="table-scroll"><table class="price-table"><thead><tr><th>Пользователь</th><th>Email</th><th>Доступ ко всем индивидуальным ценам Entity</th><th></th></tr></thead><tbody><tr v-for="user in access.users" :key="user.id"><td>{{ user.name }}</td><td>{{ user.email }}</td><td>{{ user.owner ? 'Создатель организации' : user.allowed ? 'Разрешён сотрудником' : 'Закрыт' }}</td><td><button v-if="!user.owner" :disabled="accessBusy" @click="grant(user)">{{ user.allowed ? 'Отозвать' : 'Разрешить' }}</button></td></tr><tr v-if="!access.users.length"><td colspan="4">К Entity пока не привязаны активные пользователи.</td></tr></tbody></table></div></section>
            </template>
            <template v-else-if="tab === 'history'">
                <div class="pricing-toolbar"><label>История <select v-model="historyScope" @change="loadHistory()"><option value="">Все цены</option><option value="common">Виды цен</option><option value="entity">Индивидуальные</option></select></label><span class="muted">Начальный снимок фиксирует цены на момент включения истории.</span></div>
                <p v-if="historyError" class="price-error" role="alert">{{ historyError }} <button @click="loadHistory()">Повторить</button></p><p v-if="historyLoading" class="empty" role="status">Загрузка истории…</p>
                <div v-else class="table-scroll"><table class="price-table history-table"><thead><tr><th>Дата / сотрудник</th><th>Событие</th><th>Назначение</th><th>Было · с НДС</th><th>Стало · с НДС</th><th>Подробности</th></tr></thead><tbody><tr v-for="entry in history?.data || []" :key="entry.id"><td>{{ date(entry.created_at) }}<small>{{ entry.actor_name || 'Система' }}</small></td><td>{{ events[entry.event] || entry.event }}</td><td>{{ (entry.after || entry.before)?.target_name }}<small>{{ entry.scope === 'entity' ? 'Только личный кабинет' : 'Вид цены' }}</small></td><td>{{ snapshotPrice(entry.before) }}</td><td>{{ snapshotPrice(entry.after) }}</td><td><details><summary>Изменения</summary><div v-for="(snapshot, key) in { before: entry.before, after: entry.after }" :key="key" class="snapshot"><strong>{{ key === 'before' ? 'Было' : 'Стало' }}</strong><template v-if="snapshot"><p>Без НДС: {{ money(snapshot.price_net) }} · НДС: {{ money(snapshot.vat_rate) }}%</p><p>{{ (snapshot.is_active ?? snapshot.is_published) ? 'Активна' : 'Отключена' }} · {{ snapshot.valid_from || 'Без начала' }} → {{ snapshot.valid_to || 'Без окончания' }}</p><p v-if="snapshot.pricing_input">{{ methods[snapshot.pricing_input.method] }} · {{ money(snapshot.pricing_input.parameter ?? snapshot.pricing_input.amount) }} · округление {{ money(snapshot.pricing_input.rounding_step) }}</p><p v-if="snapshot.pricing_result?.source">Источник: {{ snapshot.pricing_result.source.supplier || snapshot.pricing_result.source.name || snapshot.pricing_result.source.kind }} #{{ snapshot.pricing_result.source.id }}</p><p v-if="snapshot.manual_comment">{{ snapshot.manual_comment }}</p></template><span v-else> —</span></div></details></td></tr><tr v-if="!history?.data?.length"><td colspan="6" class="empty">История пока пуста.</td></tr></tbody></table></div>
                <div v-if="history" class="pricing-toolbar"><span class="muted">{{ history.total }} записей</span><div class="button-group"><button :disabled="historyLoading || history.current_page <= 1" @click="loadHistory(history.current_page - 1)">Назад</button><span>{{ history.current_page }} / {{ history.last_page }}</span><button :disabled="historyLoading || history.current_page >= history.last_page" @click="loadHistory(history.current_page + 1)">Далее</button></div></div>
            </template>
            <template v-else><p class="archive-note">Ранее сохранённые расчёты за кг. При назначении цена пересчитывается на единицу товара; новый расчёт сохраняется вместе с ценой и её историей.</p><div class="table-scroll"><table class="price-table"><thead><tr><th>Расчёт</th><th>Дата</th><th>Источник</th><th class="numeric">Без НДС / кг</th><th class="numeric">С НДС / кг</th><th>Валюта</th><th></th></tr></thead><tbody><tr v-for="calculation in data.calculations" :key="calculation.id"><td>{{ calculation.name || `Расчёт №${calculation.id}` }}<small>{{ calculation.comment }}</small></td><td>{{ date(calculation.created_at) }}</td><td>{{ calculation.source_type || '—' }}</td><td class="numeric">{{ money(calculation.sale_net_per_kg) }}</td><td class="numeric">{{ money(calculation.sale_gross_per_kg) }}</td><td>{{ calculation.currency?.code }}</td><td><button @click="useCalculation(calculation)">Назначить</button></td></tr><tr v-if="!data.calculations.length"><td colspan="7" class="empty">Ранее сохранённых расчётов нет.</td></tr></tbody></table></div></template>
        </template>
    </section>
</template>

<style scoped>
.pricing { min-width: 0; color: #35333a; font: 12px/1.4 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.pricing-head { display: flex; justify-content: space-between; align-items: start; gap: 20px; padding: 10px 0; }
h3 { margin: 0 0 4px; font-size: 15px; font-weight: 600; } p { margin: 0; } .pricing-head p, .muted, .archive-note { color: #77727c; font-size: 11px; }
.partners { max-width: 330px; max-height: 90px; overflow: auto; flex-shrink: 0; } .partners .price-table { font-size: 10px; } .partners td { padding: 2px 6px; } .partners td:first-child { color: #817a87; } caption { text-align: right; font-size: 9px; color: #817a87; padding-bottom: 3px; }
.pricing-toolbar, .button-group, .inline { display: flex; align-items: center; gap: 6px; } .pricing-toolbar { justify-content: space-between; flex-wrap: wrap; padding: 7px 0; } .segments { display: flex; padding: 2px; border: 1px solid #d6d3d9; border-radius: 6px; background: #efedf1; } .segments button { border: 0; background: transparent; } .segments button[aria-pressed=true] { background: white; box-shadow: 0 1px 3px #2b233721; color: #25232a; }
button, input, select { font: inherit; } button { min-height: 26px; padding: 3px 9px; border: 1px solid #d6d3d9; border-radius: 4px; background: #fff; cursor: pointer; white-space: nowrap; } button:hover:enabled { background: #f0edf6; } button:disabled { opacity: .45; cursor: default; } button.primary { background: #4266b0; border-color: #4266b0; color: white; } button.primary:hover:enabled { background: #365b9f; } button.danger { color: #b02e35; } .price-link { display: block; border: none; background: none; color: #4266a1; padding: 2px 0; min-height: 20px; font-size: 10px; }
input:not([type=checkbox]), select { min-height: 27px; max-width: 100%; padding: 3px 6px; border: 1px solid #d7d4dc; border-radius: 4px; color: #38343f; background: #fff; } select { width: 100%; text-overflow: ellipsis; } input:disabled, select:disabled { background: #f4f3f5; color: #625b6a; } :is(button, input, select, summary):focus-visible { outline: 2px solid #739ade; outline-offset: 2px; }
.table-scroll { overflow-x: auto; border: 1px solid #d6d3d9; border-radius: 5px; } .price-table { width: 100%; border-collapse: collapse; font-size: 11px; text-align: left; } .price-table th { padding: 6px 8px; color: #77727c; font-size: 10px; font-weight: 600; background: #f2f1f4; border-bottom: 1px solid #d6d3d9; white-space: nowrap; } .price-table td { padding: 6px 8px; border-bottom: 1px solid #e7e5ea; vertical-align: middle; } .price-table tbody:nth-child(even) { background: #faf9fb; } .price-table tbody.modified { background: #f2f6ff; } .price-table small { display: block; color: #8b8492; font-size: 10px; margin-top: 3px; } .price-table small.confidential { color: #80649b; } .numeric { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; } .numeric strong { font-size: 13px; color: #303b52; } .editor-table { min-width: 1020px; table-layout: fixed; } .editor-table th:nth-child(1) { width: 170px; } .editor-table th:nth-child(2) { width: 195px; } .editor-table th:nth-child(3) { width: 105px; } .editor-table th:nth-child(4) { width: 80px; } .editor-table th:nth-child(5), .editor-table th:nth-child(6) { width: 100px; } .editor-table th:nth-child(7) { width: 160px; } .editor-table th:nth-child(8) { width: 183px; }
.source-cell select + select { margin-top: 5px; } .target-cell strong { overflow-wrap: anywhere; } .number-input { width: 88px; } .vat-input { width: 55px; } .check { display: flex; align-items: center; gap: 5px; font-size: 10px; white-space: nowrap; } input[type=checkbox] { accent-color: #5077b8; width: 13px; height: 13px; } .actions .button-group + .button-group { margin-top: 4px; } .actions button { font-size: 10px; padding: 3px 6px; }
.parameters { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; padding: 5px 0; } .parameters label { display: grid; gap: 3px; color: #736c7c; font-size: 10px; width: 125px; } .parameters .check { display: flex; align-self: center; } .parameters .comment { flex: 1; min-width: 200px; } .inset { padding: 10px; margin: 8px 0; border: 1px solid #d6d3d9; border-radius: 5px; background: #faf9fc; } .inset .pricing-toolbar label { display: flex; align-items: center; gap: 8px; flex: 1; } .inset input { flex: 1; } .empty { padding: 24px !important; text-align: center; color: #827a8b; } .price-error { color: #aa2938; padding: 8px; background: #fff0f2; } .history-table { min-width: 780px; } summary { cursor: pointer; color: #4266a1; } .snapshot { margin-top: 8px; max-width: 330px; overflow-wrap: anywhere; } .archive-note { margin: 9px 0; }
@media (max-width: 700px) { .pricing-head { flex-direction: column; gap: 8px; } .partners { max-width: 100%; } .segments { flex-wrap: wrap; } .segments button { padding-inline: 7px; } }
</style>
