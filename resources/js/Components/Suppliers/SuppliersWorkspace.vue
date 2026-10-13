<script setup>
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import SupplierDetailDrawer from './SupplierDetailDrawer.vue'
import SupplierProfileEditor from './SupplierProfileEditor.vue'

const props = defineProps({ initialUnitId: { type: [Number, String], default: null } })
const rows = ref([]), summary = ref(null), period = ref(null), options = ref({ owners: [], statuses: [], priorities: [] })
const loading = ref(false), optionsLoading = ref(false), optionsReady = ref(false), exporting = ref(false), error = ref(''), optionsError = ref(''), exportError = ref(''), notice = ref('')
const filters = reactive({ search: '', scope: 'watched', status: '', owner_user_id: '', priority: '', attention: 'all', date_from: '', date_to: '', sort: 'name', direction: 'asc' })
const preset = ref('90'), meta = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 25 })
const detailUnitId = ref(null), detailOpen = ref(false), detailRef = ref(null), editorOpen = ref(false), editing = ref(null), editorKey = ref(0)
let disposed = false, listVersion = 0, optionsVersion = 0, exportVersion = 0, searchTimer = null, listController = null
const scopes = { watched: 'На контроле', all: 'Все добавленные', archived: 'Архив' }
const attentionOptions = { all: 'Все состояния', review_due: 'Пора проверить', delivery_overdue: 'Просрочена поставка', uncertain_send: 'Уточнить отправку', no_orders: 'Без заказов за период' }
const message = failure => Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось загрузить данные. Повторите попытку.'
const date = value => {
    if (!value) return '—'
    const result = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00+03:00` : value)
    return Number.isNaN(result.getTime()) ? '—' : result.toLocaleDateString('ru-RU', { timeZone: 'Europe/Moscow' })
}
const quantity = value => value == null ? '—' : Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
function amount(value) {
    if (value == null) return '—'
    const [integer, fraction = ''] = String(value).split('.')
    const decimals = fraction.slice(0, 4).replace(/0+$/, '')
    return `${integer.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')}${decimals ? `,${decimals}` : ''}`
}
const currency = value => !value || ['unknown', 'UNKNOWN', 'null', 'NONE'].includes(value) ? 'Валюта не указана' : value
const range = computed(() => meta.total ? `${(meta.current_page - 1) * meta.per_page + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} из ${meta.total}` : '0 поставщиков')
const hasFilters = computed(() => filters.search || filters.status || filters.owner_user_id || filters.priority || filters.attention !== 'all')
const showEmptyOnboarding = computed(() => !loading.value && !error.value && !rows.value.length && !hasFilters.value)
const profileStatus = profile => options.value.statuses.find(item => item.value === profile.status)?.label || ({ active: 'Активный', potential: 'Потенциальный', paused: 'Приостановлен', archived: 'Архив' })[profile.status] || profile.status
const priority = profile => options.value.priorities.find(item => item.value === profile.priority)?.label || ({ low: 'Низкий', normal: 'Обычный', high: 'Высокий', critical: 'Критический' })[profile.priority] || profile.priority
const reviewLabel = profile => ({ off: 'Контроль приостановлен', unscheduled: 'Дата не назначена', overdue: 'Проверка просрочена', today: 'Проверить сегодня', scheduled: 'Запланирована' })[profile.review_state] || 'Дата не назначена'
function params(page = null) {
    const result = { scope: filters.scope, search: filters.search.trim() || undefined, status: filters.status || undefined, owner_user_id: filters.owner_user_id || undefined, priority: filters.priority || undefined, attention: filters.attention, sort: filters.sort, direction: filters.direction, date_from: filters.date_from || undefined, date_to: filters.date_to || undefined }
    if (page != null) Object.assign(result, { page, per_page: meta.per_page })
    return result
}
async function loadOptions() {
    const version = ++optionsVersion; optionsLoading.value = true; optionsError.value = ''
    try {
        const response = await axios.get('/api/suppliers/options')
        if (disposed || version !== optionsVersion) return
        options.value = response.data; optionsReady.value = true
        if (!filters.date_from) filters.date_from = response.data.default_date_from || ''
        if (!filters.date_to) filters.date_to = response.data.default_date_to || response.data.today || ''
    } catch (failure) { if (!disposed && version === optionsVersion) optionsError.value = message(failure) }
    finally { if (!disposed && version === optionsVersion) optionsLoading.value = false }
}
async function initialize() {
    await loadOptions()
    if (disposed) return
    await load(1)
    if (props.initialUnitId && !disposed) openDetail({ unit: { id: Number(props.initialUnitId) } })
}
async function load(page = meta.current_page) {
    clearTimeout(searchTimer); listController?.abort()
    const version = ++listVersion
    listController = new AbortController(); loading.value = true; error.value = ''
    try {
        const response = await axios.get('/api/suppliers', { params: params(page), signal: listController.signal })
        if (disposed || version !== listVersion) return
        const data = response.data
        if (data.meta?.current_page > (data.meta?.last_page || 1)) { await load(data.meta.last_page || 1); return }
        rows.value = data.data || []; summary.value = data.summary || null; period.value = data.period || null
        Object.assign(meta, data.meta || { current_page: 1, last_page: 1, total: rows.value.length })
        if (!filters.date_from) filters.date_from = data.period?.date_from || ''
        if (!filters.date_to) filters.date_to = data.period?.date_to || ''
    } catch (failure) { if (!disposed && version === listVersion && failure.code !== 'ERR_CANCELED') error.value = message(failure) }
    finally { if (!disposed && version === listVersion) loading.value = false }
}
function scheduleSearch() {
    clearTimeout(searchTimer); listController?.abort(); listVersion++; loading.value = true
    searchTimer = setTimeout(() => load(1), 300)
}
function applyFilters() { return load(1) }
function selectScope(value) { filters.scope = value; filters.status = ''; load(1) }
function selectPreset(value) {
    preset.value = value
    if (value === 'custom') return
    const today = options.value.today || period.value?.date_to
    if (!today) return
    const start = new Date(`${today}T12:00:00Z`); start.setUTCDate(start.getUTCDate() - (Number(value) - 1))
    filters.date_from = start.toISOString().slice(0, 10); filters.date_to = today; load(1)
}
function setCustomPeriod() { preset.value = 'custom'; load(1) }
function clearFilters() { filters.search = ''; filters.status = ''; filters.owner_user_id = ''; filters.priority = ''; filters.attention = 'all'; load(1) }
function setAttention(value) { filters.attention = value; load(1) }
function openDetail(row) { detailUnitId.value = row.unit.id; detailOpen.value = true }
function openEditor(value = null) { editing.value = value || { unit: null, profile: null }; editorKey.value++; editorOpen.value = true }
async function saved(value) {
    editorOpen.value = false; notice.value = `${value.unit?.name || 'Поставщик'}: профиль сохранён.`
    if (detailOpen.value && Number(detailUnitId.value) === Number(value.unit?.id)) detailRef.value?.refresh()
    await load()
}
function detailChanged() { load() }
async function exportCsv() {
    if (exporting.value) return
    const version = ++exportVersion; exporting.value = true; exportError.value = ''
    try {
        const response = await axios.get('/api/suppliers/export', { params: params(), responseType: 'blob' })
        if (disposed || version !== exportVersion) return
        const url = URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8' })), link = document.createElement('a')
        link.href = url; link.download = `suppliers-${filters.date_from || 'all'}-${filters.date_to || 'today'}.csv`; document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url)
        notice.value = 'CSV сформирован по текущим фильтрам.'
    } catch (failure) {
        if (disposed || version !== exportVersion) return
        if (failure.response?.data instanceof Blob) { try { exportError.value = message({ response: { data: JSON.parse(await failure.response.data.text()) } }) } catch { exportError.value = 'Не удалось выгрузить CSV. Уточните фильтры и повторите попытку.' } }
        else exportError.value = message(failure)
    } finally { if (!disposed && version === exportVersion) exporting.value = false }
}
watch(() => filters.search, scheduleSearch)
watch(() => props.initialUnitId, value => { if (value) openDetail({ unit: { id: Number(value) } }) })
onScopeDispose(() => { disposed = true; listVersion++; optionsVersion++; exportVersion++; listController?.abort(); clearTimeout(searchTimer) })
initialize()
</script>

<template>
    <section class="suppliers-workspace" aria-label="Реестр поставщиков">
        <header class="workspace-heading"><div><h1>Поставщики</h1><p>Заказы, закупки и регулярный контроль выбранных организаций</p></div><div class="heading-actions"><Link href="/Ameise/supplier-orders" class="button-link">Заказы поставщикам ↗</Link><button class="primary" :disabled="!optionsReady || optionsLoading" @click="openEditor()">＋ Добавить поставщика</button></div></header>
        <div class="summary-caption"><span>Показатели по выбранным фильтрам</span><span>{{ period ? `${date(period.date_from)} — ${date(period.date_to)}` : 'За выбранный период' }}</span></div>
        <div class="stats-grid">
            <div class="stat"><span>Поставщиков</span><strong>{{ summary?.suppliers_count ?? '—' }}</strong><small>{{ scopes[filters.scope] }}</small></div>
            <div class="stat"><span>На контроле</span><strong>{{ summary?.watched_count ?? '—' }}</strong><small>Из выбранных организаций</small></div>
            <button class="stat" :class="{ selected: filters.attention === 'review_due' }" @click="setAttention('review_due')"><span>Пора проверить</span><strong>{{ summary?.review_due_count ?? '—' }}</strong><small>Сегодня и просроченные</small></button>
            <button class="stat" :class="{ selected: filters.attention === 'delivery_overdue' }" @click="setAttention('delivery_overdue')"><span>Просрочены поставки</span><strong>{{ summary?.overdue_delivery_suppliers_count ?? '—' }}</strong><small>Поставщиков с просрочкой</small></button>
            <button class="stat" :class="{ selected: filters.attention === 'uncertain_send' }" @click="setAttention('uncertain_send')"><span>Уточнить отправку</span><strong>{{ summary?.uncertain_send_suppliers_count ?? '—' }}</strong><small>Неопределённый результат</small></button>
        </div>
        <div class="register-card">
            <div class="scope-period-row"><div class="segments" role="group" aria-label="Состав реестра"><button v-for="(label, value) in scopes" :key="value" :aria-pressed="filters.scope === value" @click="selectScope(value)">{{ label }}</button></div><div class="period-controls"><label>Период<select :value="preset" aria-label="Период аналитики" @change="selectPreset($event.target.value)"><option value="30">30 дней</option><option value="90">90 дней</option><option value="365">365 дней</option><option value="custom">Свой период</option></select></label><label class="date-field"><span class="sr-only">Начало периода</span><input v-model="filters.date_from" type="date" aria-label="Начало периода" @change="setCustomPeriod"></label><span>—</span><label class="date-field"><span class="sr-only">Конец периода</span><input v-model="filters.date_to" type="date" aria-label="Конец периода" @change="setCustomPeriod"></label></div></div>
            <form class="filter-row" @submit.prevent="applyFilters"><label class="search"><span class="sr-only">Поиск поставщика</span><input v-model="filters.search" type="search" maxlength="150" placeholder="Название поставщика или компании"></label><label><span class="sr-only">Ответственный</span><select v-model="filters.owner_user_id" aria-label="Ответственный" @change="applyFilters"><option value="">Все ответственные</option><option v-for="owner in options.owners" :key="owner.id" :value="owner.id">{{ owner.name }}</option></select></label><label><span class="sr-only">Статус поставщика</span><select v-model="filters.status" aria-label="Статус поставщика" @change="applyFilters"><option value="">Все статусы</option><option v-for="item in options.statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label><label><span class="sr-only">Приоритет поставщика</span><select v-model="filters.priority" aria-label="Приоритет поставщика" @change="applyFilters"><option value="">Все приоритеты</option><option v-for="item in options.priorities" :key="item.value" :value="item.value">{{ item.label }}</option></select></label><label><span class="sr-only">Требует внимания</span><select v-model="filters.attention" aria-label="Требует внимания" @change="applyFilters"><option v-for="(label, value) in attentionOptions" :key="value" :value="value">{{ label }}</option></select></label><button v-if="hasFilters" type="button" @click="clearFilters">Сбросить</button></form>
            <div class="table-toolbar"><span>{{ loading ? 'Загрузка…' : range }} · Только поставщики, добавленные сотрудниками</span><div><label>Сортировка<select v-model="filters.sort" aria-label="Сортировка поставщиков" @change="applyFilters"><option value="name">Название</option><option value="order_count">Количество заказов</option><option value="last_order">Последний заказ</option><option value="next_review">Следующая проверка</option></select></label><button :aria-label="filters.direction === 'asc' ? 'Сортировать по убыванию' : 'Сортировать по возрастанию'" @click="filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'; applyFilters()">{{ filters.direction === 'asc' ? '↑' : '↓' }}</button><button :disabled="loading" @click="load()">Обновить</button><button :disabled="exporting || loading" @click="exportCsv">{{ exporting ? 'Выгружаем…' : 'CSV ↓' }}</button></div></div>
            <p v-if="optionsError" class="error" role="alert">Настройки профиля: {{ optionsError }} <button :disabled="optionsLoading" @click="loadOptions">Повторить</button></p><p v-if="error" class="error" role="alert">{{ error }} <button @click="load()">Повторить</button></p><p v-if="exportError" class="error" role="alert">{{ exportError }}</p><p v-if="notice" class="notice" role="status">{{ notice }} <button aria-label="Скрыть уведомление" @click="notice = ''">×</button></p>
            <div v-if="showEmptyOnboarding" class="empty-register"><div class="empty-symbol" aria-hidden="true">▤</div><h2>{{ filters.scope === 'archived' ? 'Архив поставщиков пуст' : filters.scope === 'all' ? 'Добавьте первого поставщика' : 'На контроле пока нет поставщиков' }}</h2><p>{{ filters.scope === 'archived' ? 'Архивированные профили сохраняют историю работы.' : 'Выберите организации, которые хотите регулярно проверять. Их заказы и закупки появятся в аналитике после добавления.' }}</p><div><button :disabled="!optionsReady" class="primary" @click="openEditor()">＋ Добавить поставщика</button><button v-if="filters.scope === 'watched'" @click="selectScope('all')">Все добавленные</button></div></div>
            <div v-else class="table-scroll" :aria-busy="loading"><table class="supplier-table" :class="{ loading }"><thead><tr><th>Поставщик / ответственный</th><th>Статус / приоритет</th><th class="numeric">Заказы</th><th>Заказано</th><th>Закуплено</th><th>Частота за период</th><th>Последний заказ</th><th>Следующая проверка</th><th>Требует внимания</th><th></th></tr></thead><tbody><tr v-for="row in rows" :key="row.unit.id">
                <td class="supplier-identity"><button class="supplier-name" @click="openDetail(row)">{{ row.unit.name }}</button><small>{{ row.profile.owner?.name || 'Ответственный не назначен' }}</small><div class="identity-links"><Link :href="`/Ameise/unit/${row.unit.id}`">Unit #{{ row.unit.id }} ↗</Link><span v-if="row.entities.some(entity => entity.shared)" class="shared-entity" title="Общие закупки Entity учитываются отдельно">Общая компания</span></div></td>
                <td><span class="status" :class="`status-${row.profile.status}`">{{ profileStatus(row.profile) }}</span><small :class="`priority-${row.profile.priority}`">{{ priority(row.profile) }}</small><small v-if="!row.profile.is_watched">Вне регулярного контроля</small></td>
                <td class="numeric"><strong>{{ row.metrics?.orders?.count ?? 0 }}</strong><small>{{ row.metrics?.orders?.distinct_goods_count ?? 0 }} товаров</small></td>
                <td class="money-cell"><div v-for="(total, code) in row.metrics?.orders?.totals_by_currency || {}" :key="code"><strong>{{ amount(total) }}</strong> <span>{{ currency(code) }}</span></div><span v-if="!Object.keys(row.metrics?.orders?.totals_by_currency || {}).length" class="muted">—</span><small v-if="row.metrics?.orders?.unpriced_items_count" class="warning">Без цены: {{ row.metrics.orders.unpriced_items_count }} поз.</small></td>
                <td class="money-cell"><div v-for="(total, code) in row.metrics?.purchases?.totals_by_currency || {}" :key="code"><strong>{{ amount(total) }}</strong> <span>{{ currency(code) }}</span></div><small>{{ row.metrics?.purchases?.count ?? 0 }} закупок</small><small v-if="row.metrics?.purchases?.unpriced_items_count" class="warning">Без цены: {{ row.metrics.purchases.unpriced_items_count }} поз.</small><small v-if="row.metrics?.shared_purchases?.count" class="warning">＋{{ row.metrics.shared_purchases.count }} общих · отдельно</small></td>
                <td class="frequency-cell"><span :title="row.metrics?.orders?.average_interval_days == null ? 'Для среднего интервала нужны хотя бы 2 заказа' : `Средний интервал: ${quantity(row.metrics.orders.average_interval_days)} дн.`">Заказы: {{ quantity(row.metrics?.orders?.per_30_days) }} / 30 дн.</span><small :title="row.metrics?.purchases?.average_interval_days == null ? 'Для среднего интервала нужны хотя бы 2 закупки' : `Средний интервал: ${quantity(row.metrics.purchases.average_interval_days)} дн.`">Закупки: {{ quantity(row.metrics?.purchases?.per_30_days) }} / 30 дн.</small></td>
                <td class="date-cell">{{ date(row.metrics?.orders?.last_at_all_time) }}<small v-if="row.metrics?.orders?.days_since_last != null">{{ quantity(row.metrics.orders.days_since_last) }} дн. назад</small><small>За всё время</small></td>
                <td class="date-cell"><span :class="{ warning: ['overdue', 'today'].includes(row.profile.review_state) }">{{ date(row.profile.next_review_on) }}</span><small :class="{ warning: ['overdue', 'today'].includes(row.profile.review_state) }">{{ reviewLabel(row.profile) }}</small><small v-if="row.profile.is_watched">Каждые {{ row.profile.review_interval_days }} дн.</small></td>
                <td class="attention-cell"><span v-if="row.metrics?.monitoring?.overdue_delivery_count" class="alert-tag">Поставка: {{ row.metrics.monitoring.overdue_delivery_count }} проср.</span><span v-if="row.metrics?.monitoring?.uncertain_send_count" class="alert-tag">Отправка: {{ row.metrics.monitoring.uncertain_send_count }} уточнить</span><small v-if="row.metrics?.monitoring?.unknown_delivery_count">Без даты поставки: {{ row.metrics.monitoring.unknown_delivery_count }}</small><small v-if="row.metrics?.monitoring?.open_orders_count">Открытых заказов: {{ row.metrics.monitoring.open_orders_count }}</small><span v-if="!row.metrics?.monitoring?.open_orders_count && !row.metrics?.monitoring?.uncertain_send_count" class="muted">—</span></td>
                <td class="row-actions"><button @click="openDetail(row)">Открыть</button><button :disabled="!optionsReady" @click="openEditor(row)">Профиль</button></td>
            </tr><tr v-if="!rows.length"><td colspan="10" class="empty-row">{{ loading ? 'Загрузка поставщиков…' : error ? 'Реестр пока недоступен.' : 'По выбранным фильтрам поставщиков нет.' }}</td></tr></tbody></table></div>
            <footer class="register-footer"><div><p>Заказы и закупки показаны отдельно, суммы — по валютам. Общие закупки Entity не включены в личный итог поставщика.</p><p>Частота — документов на 30 дней выбранного периода. Состояние контроля и поставок — на сегодня.</p></div><div class="pagination"><label>Строк<select v-model="meta.per_page" aria-label="Строк на странице" @change="load(1)"><option :value="25">25</option><option :value="50">50</option></select></label><button :disabled="loading || meta.current_page <= 1" @click="load(meta.current_page - 1)">Назад</button><span>{{ meta.current_page }} / {{ meta.last_page }}</span><button :disabled="loading || meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Далее</button></div></footer>
        </div>
        <v-dialog v-model="detailOpen" persistent width="96vw" max-width="1800"><SupplierDetailDrawer v-if="detailOpen && detailUnitId" :key="detailUnitId" ref="detailRef" :unit-id="detailUnitId" :date-from="filters.date_from" :date-to="filters.date_to" @close="detailOpen = false" @changed="detailChanged" @edit="openEditor" /></v-dialog>
        <v-dialog v-model="editorOpen" persistent max-width="640"><SupplierProfileEditor v-if="editorOpen && editing" :key="editorKey" :unit="editing.unit" :profile="editing.profile" :options="options" :creating="!editing.profile?.exists" @close="editorOpen = false" @saved="saved" /></v-dialog>
    </section>
</template>

<style scoped>
.suppliers-workspace { color: #463e50; font: 12px/1.45 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; min-width: 0; }.workspace-heading { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }.workspace-heading h1 { margin: 0 0 5px; font-size: 25px; font-weight: 600; letter-spacing: -.4px; }.workspace-heading p { margin: 0; color: #97899f; font-size: 12px; }.heading-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }.summary-caption { display: flex; align-items: center; justify-content: space-between; color: #9c8ba7; font-size: 12px; margin-bottom: 8px; gap: 8px; }.stats-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-bottom: 18px; }.stat { display: flex; flex-direction: column; justify-content: space-between; text-align: left; padding: 12px 14px !important; border: 1px solid #ddd5e7 !important; border-radius: 6px; min-width: 0; background: linear-gradient(140deg, #fff, #faf8fc); color: #776283; }.stat span, .stat small { font-size: 12px; white-space: normal; }.stat strong { font-size: 27px; line-height: 1.35; font-weight: 500; color: #574360; margin: 3px 0; }.stat small { color: #a08eac; }.stat.selected { border-color: #b59fc9 !important; background: #f1ebf8; }.register-card { border: 1px solid #dcd4e5; border-radius: 6px; background: white; overflow: hidden; }.scope-period-row, .filter-row, .table-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 9px; padding: 10px 12px; }.scope-period-row { background: #fcfafd; border-bottom: 1px solid #e9e2ef; }.segments { display: flex; gap: 3px; padding: 3px; border: 1px solid #dad1e5; border-radius: 5px; background: #eee9f4; }.segments button { border: 0; background: transparent; padding: 5px 10px; }.segments button[aria-pressed=true] { background: #fff; color: #644f75; box-shadow: 0 1px 3px #58366b15; }.period-controls { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; color: #9b8aa8; }.period-controls > label:first-child, .table-toolbar label, .pagination label { display: flex; align-items: center; gap: 6px; }.date-field { width: 133px; }.filter-row { justify-content: flex-start; padding-bottom: 7px; margin: 0; }.filter-row .search { flex: 1 1 230px; }.filter-row > label:not(.search) { flex: 0 1 auto; max-width: 230px; }.table-toolbar { color: #95839f; font-size: 12px; padding-top: 5px; padding-bottom: 9px; }.table-toolbar > div { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }.table-toolbar select { max-width: 175px; }.table-scroll { max-width: 100%; overflow: auto; }.supplier-table { width: 100%; min-width: 1330px; border-collapse: collapse; font-size: 12px; text-align: left; }.supplier-table.loading { opacity: .5; pointer-events: none; }.supplier-table th { padding: 8px 9px; background: #f0ecf5; color: #877294; font-size: 12px; font-weight: 500; white-space: nowrap; border-block: 1px solid #ded5e8; }.supplier-table td { padding: 10px 9px; border-bottom: 1px solid #ebe4f1; vertical-align: top; }.supplier-table tbody tr:nth-child(even) { background: #fcfbfe; }.supplier-table tbody tr:hover { background: #f7f3fb; }.supplier-table small { display: block; margin-top: 4px; font-size: 12px; color: #a08daf; }.supplier-table strong { font-weight: 600; }.supplier-identity { min-width: 200px; max-width: 260px; }.supplier-name { padding: 0; border: 0; background: none; text-align: left; color: #5b456c; font-size: 13px; font-weight: 600; min-height: 0; white-space: normal; overflow-wrap: anywhere; }.supplier-name:hover { text-decoration: underline; }.identity-links { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 7px; font-size: 12px; }.identity-links a { color: #9b8ca8; text-decoration: none; }.identity-links a:hover { text-decoration: underline; }.shared-entity { color: #a08055; }.status { display: inline-block; padding: 2px 6px; border: 1px solid #ded2e7; border-radius: 4px; font-size: 12px; color: #887097; background: #f4eef8; }.status-active { border-color: #d4e4d7; background: #f0f7f1; color: #68816a; }.status-archived, .status-paused { border-color: #e3dce8; background: #f5f3f7; color: #9e91a7; }.supplier-table .priority-high, .supplier-table .priority-critical { color: #ae775d; }.numeric { text-align: right; font-variant-numeric: tabular-nums; }.money-cell { font-variant-numeric: tabular-nums; min-width: 135px; }.money-cell > div { white-space: nowrap; }.money-cell > div + div { margin-top: 3px; }.money-cell > div span { color: #9a88a5; font-size: 12px; }.frequency-cell, .date-cell { white-space: nowrap; }.attention-cell { min-width: 125px; max-width: 170px; }.alert-tag { display: block; margin-bottom: 5px; padding: 2px 5px; border-radius: 3px; border: 1px solid #efdfc7; color: #ad844b; background: #fffaf0; font-size: 12px; }.warning { color: #ad844b !important; }.muted { color: #b0a0bc; }.row-actions { width: 90px; }.row-actions button { display: block; min-width: 76px; margin-bottom: 5px; padding: 4px 7px; font-size: 12px; }.empty-row { padding: 34px !important; color: #a390b0; text-align: center; }.empty-register { display: flex; flex-direction: column; align-items: center; padding: 34px 20px; text-align: center; color: #9b86aa; background: #fdfbff; }.empty-symbol { font-size: 29px; color: #ad95bd; border: 1px solid #e5ddeb; border-radius: 9px; padding: 5px 13px; background: #fff; }.empty-register h2 { font-weight: 500; font-size: 19px; color: #70597e; margin: 14px 0 7px; }.empty-register p { max-width: 600px; margin: 0 0 18px; line-height: 1.6; font-size: 12px; }.empty-register > div:last-child { display: flex; align-items: center; flex-wrap: wrap; justify-content: center; gap: 8px; }.register-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; padding: 11px 12px; background: #fcfafd; color: #9f8bac; border-top: 1px solid #e8deee; font-size: 12px; }.register-footer p { margin: 0 0 3px; }.pagination { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; }.pagination select { width: 65px; }.error, .notice { margin: 0; padding: 9px 12px; font-size: 12px; overflow-wrap: anywhere; }.error { background: #fff3f5; color: #a95162; border-block: 1px solid #efd5dc; }.notice { background: #f2f8f3; color: #62846a; border-block: 1px solid #e0ecdf; }.error button, .notice button { margin-left: 7px; font-size: 12px; min-height: 25px; }button, input, select { font: inherit; }button, .button-link { min-height: 30px; padding: 5px 10px; border: 1px solid #dcd2e5; border-radius: 4px; background: #fff; color: #82678f; cursor: pointer; text-decoration: none; white-space: nowrap; font-size: 12px; display: inline-block; box-sizing: border-box; }button:hover:enabled, .button-link:hover { background: #f1eaf8; }button:disabled { cursor: default; opacity: .5; }.primary { background: #806b93; color: white; border-color: #806b93; }.primary:hover:enabled { background: #705d84; }input, select { width: 100%; min-width: 0; min-height: 31px; border: 1px solid #ddd4e7; border-radius: 4px; padding: 4px 8px; color: #6c557c; background: #fff; font-size: 12px; box-sizing: border-box; }button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid #b095ca; outline-offset: 2px; }.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }@media(max-width:1050px) { .stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }.summary-caption { align-items: start; }.scope-period-row { align-items: start; }.register-footer { align-items: start; } }@media(max-width:620px) { .workspace-heading { margin-bottom: 14px; }.workspace-heading h1 { font-size: 23px; }.heading-actions { width: 100%; }.stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; }.stat { padding: 10px !important; }.stat strong { font-size: 24px; }.summary-caption { flex-direction: column; gap: 3px; }.segments { width: 100%; }.segments button { flex: 1; padding-inline: 5px; }.period-controls { width: 100%; }.period-controls > label:first-child { width: 100%; }.date-field { flex: 1; }.filter-row > label:not(.search) { flex: 1 1 160px; max-width: none; }.table-toolbar > span { width: 100%; }.scope-period-row, .filter-row, .table-toolbar { padding-inline: 9px; }.register-footer { padding: 10px; } }
</style>
