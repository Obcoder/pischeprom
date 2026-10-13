<script setup>
import { computed, onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import SupplierOrdersRegister from '@/Components/SupplierOrders/SupplierOrdersRegister.vue'
import SupplierOrderComposer from '@/Components/Unit/Mail/SupplierOrderComposer.vue'

const props = defineProps({ unitId: { type: [Number, String], required: true }, dateFrom: { type: String, default: '' }, dateTo: { type: String, default: '' } })
const emit = defineEmits(['close', 'changed', 'edit'])
const detail = ref(null), analytics = ref(null), events = ref(null), tab = ref('overview')
const detailLoading = ref(false), analyticsLoading = ref(false), eventsLoading = ref(false)
const detailError = ref(''), analyticsError = ref(''), eventsError = ref(''), operationError = ref(''), notice = ref('')
const goodsSearch = ref(''), goodsSource = ref('all'), goodsPage = ref(1), purchasesPage = ref(1), eventsPage = ref(1)
const note = ref(''), nextReview = ref(''), action = ref('review'), saving = ref(false), conflict = ref(false), closeConfirmation = ref(false)
const chartMetric = ref('count'), selectedMonth = ref(''), composerOpen = ref(false), composerUnit = ref(null), composerKey = ref(0)
let generation = 0, detailVersion = 0, analyticsVersion = 0, eventsVersion = 0, disposed = false
let detailController, analyticsController, eventsController, searchTimer
const profile = computed(() => detail.value?.profile)
const summary = computed(() => analytics.value?.summary)
const dirty = computed(() => Boolean(note.value.trim() || nextReview.value))
const tabs = [{ value: 'overview', label: 'Обзор' }, { value: 'goods', label: 'Товары' }, { value: 'orders', label: 'Все заказы' }, { value: 'purchases', label: 'Приходы' }, { value: 'journal', label: 'Журнал' }]
const statuses = { active: 'Активный', potential: 'Потенциальный', paused: 'Приостановлен', archived: 'Архив' }
const priorities = { low: 'Низкий', normal: 'Обычный', high: 'Высокий', critical: 'Критический' }
const reviewStates = { off: 'Контроль выключен', unscheduled: 'Дата проверки не задана', overdue: 'Проверка просрочена', today: 'Проверка сегодня', scheduled: 'Проверка запланирована' }
const eventTypes = { created: 'Добавлен в реестр', updated: 'Профиль изменён', reviewed: 'Проверка выполнена', note: 'Заметка' }
const changeLabels = { status: 'Статус', priority: 'Приоритет', is_watched: 'Регулярный контроль', owner_user_id: 'Ответственный', owner_name: 'Ответственный', review_interval_days: 'Интервал проверки, дней', next_review_on: 'Следующая проверка', last_reviewed_at: 'Последняя проверка', notes: 'Описание' }
const failureMessage = error => Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || 'Не удалось загрузить данные. Повторите попытку.'
const number = value => {
    if (value == null || value === '') return '—'
    const raw = String(value), match = raw.match(/^(-?)(\d+)(?:\.(\d+))?$/)
    if (!match) return '—'
    const fraction = (match[3] || '').replace(/0+$/, '')
    return `${match[1]}${match[2].replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')}${fraction ? `,${fraction}` : ''}`
}
const currencyName = code => code === 'UNKNOWN' ? 'Валюта не указана' : code
const moneyLines = totals => Object.entries(totals || {}).map(([currency, amount]) => `${number(amount)} ${currencyName(currency)}`)
const date = (value, time = false) => {
    if (!value) return '—'
    const parsed = new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T12:00:00+03:00` : value)
    return Number.isNaN(parsed.getTime()) ? '—' : parsed.toLocaleString('ru-RU', { timeZone: 'Europe/Moscow', day: '2-digit', month: '2-digit', year: 'numeric', ...(time ? { hour: '2-digit', minute: '2-digit' } : {}) })
}
const frequency = value => value == null ? '—' : Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })
const monthLabel = value => new Date(`${value}-01T12:00:00+03:00`).toLocaleDateString('ru-RU', { timeZone: 'Europe/Moscow', month: 'short', year: '2-digit' })
const safeWebUrl = value => { try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.href : null } catch { return null } }
const emailUrl = value => /^[^\s@<>:]+@[^\s@<>]+\.[^\s@<>]+$/.test(value || '') ? `mailto:${encodeURIComponent(value).replace(/%40/g, '@')}` : null
const phoneUrl = value => /^[+\d\s().-]+$/.test(value || '') && /\d/.test(value) ? `tel:${value.replace(/[^+\d]/g, '')}` : null
const chartCurrencies = computed(() => [...new Set((analytics.value?.monthly || []).flatMap(row => [...Object.keys(row.order_totals_by_currency || {}), ...Object.keys(row.purchase_totals_by_currency || {})]))].sort())
const monthly = computed(() => analytics.value?.monthly || [])
const chartValue = (row, source) => chartMetric.value === 'count' ? Number(row[`${source}_count`] || 0) : Number(row[`${source === 'orders' ? 'order' : 'purchase'}_totals_by_currency`]?.[chartMetric.value] || 0)
const chartMax = computed(() => Math.max(1, ...monthly.value.flatMap(row => [chartValue(row, 'orders'), chartValue(row, 'purchases')]).map(Math.abs)))
const barHeight = (row, source) => `${Math.abs(chartValue(row, source)) / chartMax.value * 100}%`
const selectedMonthData = computed(() => monthly.value.find(row => row.month === selectedMonth.value) || monthly.value.at(-1))
const chartAmount = (row, source) => chartMetric.value === 'count' ? `${number(row[`${source}_count`] || 0)} док.` : `${number(row[`${source === 'orders' ? 'order' : 'purchase'}_totals_by_currency`]?.[chartMetric.value] || '0')} ${currencyName(chartMetric.value)}`
function changedValue(field, value) {
    if (value == null || value === '') return 'не задано'
    if (field === 'status') return statuses[value] || value
    if (field === 'priority') return priorities[value] || value
    if (field === 'is_watched') return value ? 'Включён' : 'Выключен'
    if (field === 'next_review_on' || field === 'last_reviewed_at') return date(value, field === 'last_reviewed_at')
    return String(value)
}
function eventChanges(event) { return Object.entries(event.changes || {}).filter(([field]) => changeLabels[field] && !(field === 'owner_user_id' && event.changes.owner_name)).map(([field, values]) => ({ field, label: changeLabels[field], before: changedValue(field, values?.before), after: changedValue(field, values?.after) })) }

async function loadDetail() {
    detailController?.abort(); const version = ++detailVersion, epoch = generation; detailController = new AbortController()
    detailLoading.value = true; detailError.value = ''
    try {
        const { data } = await axios.get(`/api/suppliers/${props.unitId}`, { signal: detailController.signal })
        if (!disposed && epoch === generation && version === detailVersion) detail.value = data
    } catch (error) { if (!disposed && epoch === generation && version === detailVersion && error.code !== 'ERR_CANCELED') detailError.value = failureMessage(error) }
    finally { if (!disposed && epoch === generation && version === detailVersion) detailLoading.value = false }
}
async function loadAnalytics() {
    clearTimeout(searchTimer); analyticsController?.abort(); const version = ++analyticsVersion, epoch = generation; analyticsController = new AbortController()
    analyticsLoading.value = true; analyticsError.value = ''
    try {
        const { data } = await axios.get(`/api/suppliers/${props.unitId}/analytics`, { signal: analyticsController.signal, params: { date_from: props.dateFrom || undefined, date_to: props.dateTo || undefined, goods_page: goodsPage.value, purchases_page: purchasesPage.value, goods_search: goodsSearch.value.trim() || undefined, goods_source: goodsSource.value, per_page: 25 } })
        if (disposed || epoch !== generation || version !== analyticsVersion) return
        analytics.value = data
        const nextGoods = Math.max(1, Number(data.goods?.meta?.last_page) || 1), nextPurchases = Math.max(1, Number(data.purchases?.meta?.last_page) || 1)
        if (goodsPage.value > nextGoods || purchasesPage.value > nextPurchases) { goodsPage.value = Math.min(goodsPage.value, nextGoods); purchasesPage.value = Math.min(purchasesPage.value, nextPurchases); return loadAnalytics() }
        if (chartMetric.value !== 'count' && !chartCurrencies.value.includes(chartMetric.value)) chartMetric.value = 'count'
    } catch (error) { if (!disposed && epoch === generation && version === analyticsVersion && error.code !== 'ERR_CANCELED') analyticsError.value = failureMessage(error) }
    finally { if (!disposed && epoch === generation && version === analyticsVersion) analyticsLoading.value = false }
}
async function loadEvents(page = eventsPage.value) {
    eventsController?.abort(); const version = ++eventsVersion, epoch = generation; eventsController = new AbortController()
    eventsLoading.value = true; eventsError.value = ''
    try {
        const { data } = await axios.get(`/api/suppliers/${props.unitId}/events`, { signal: eventsController.signal, params: { page } })
        if (disposed || epoch !== generation || version !== eventsVersion) return
        if (Number(data.current_page) > Number(data.last_page || 1)) return loadEvents(Number(data.last_page) || 1)
        events.value = data; eventsPage.value = Number(data.current_page) || 1
    } catch (error) { if (!disposed && epoch === generation && version === eventsVersion && error.code !== 'ERR_CANCELED') eventsError.value = failureMessage(error) }
    finally { if (!disposed && epoch === generation && version === eventsVersion) eventsLoading.value = false }
}
async function refresh() {
    await Promise.allSettled([loadDetail(), loadAnalytics(), loadEvents()])
    if (!detailError.value) conflict.value = false
}
function requestClose() { if (saving.value || composerOpen.value) return; if (dirty.value) closeConfirmation.value = true; else emit('close') }
function discardClose() { if (!saving.value) emit('close') }
function openComposer() { composerUnit.value = detail.value.unit.id; composerKey.value++; composerOpen.value = true }
function ordersChanged() { emit('changed'); loadAnalytics() }
function orderSaved() { ordersChanged() }
async function saveNote() {
    if (saving.value || conflict.value || !profile.value?.exists || !note.value.trim()) return
    const epoch = generation, unitId = props.unitId, kind = action.value
    saving.value = true; operationError.value = ''; notice.value = ''
    try {
        const { data } = await axios.post(`/api/suppliers/${unitId}/${kind === 'review' ? 'reviews' : 'notes'}`, { revision: profile.value.revision, note: note.value.trim(), ...(kind === 'review' && nextReview.value ? { next_review_on: nextReview.value } : {}) })
        if (disposed || epoch !== generation) return
        detailVersion++; detailController?.abort(); detailLoading.value = false
        detail.value = { ...detail.value, profile: data }; note.value = ''; nextReview.value = ''; closeConfirmation.value = false
        notice.value = kind === 'review' ? 'Проверка записана. Следующая дата обновлена.' : 'Заметка добавлена.'
        emit('changed'); loadEvents(1)
    } catch (error) {
        if (disposed || epoch !== generation) return
        operationError.value = failureMessage(error); conflict.value = error.response?.status === 409
    } finally { if (!disposed && epoch === generation) saving.value = false }
}
watch(() => props.unitId, () => {
    generation++; detailVersion++; analyticsVersion++; eventsVersion++; clearTimeout(searchTimer)
    detail.value = null; analytics.value = null; events.value = null; tab.value = 'overview'; goodsSearch.value = ''; goodsSource.value = 'all'
    goodsPage.value = 1; purchasesPage.value = 1; eventsPage.value = 1; note.value = ''; nextReview.value = ''; operationError.value = ''; notice.value = ''; conflict.value = false; saving.value = false; closeConfirmation.value = false
    refresh()
}, { immediate: true })
watch([() => props.dateFrom, () => props.dateTo], () => { goodsPage.value = 1; purchasesPage.value = 1; loadAnalytics() })
watch(goodsSearch, () => { analyticsVersion++; analyticsController?.abort(); clearTimeout(searchTimer); analyticsLoading.value = true; searchTimer = setTimeout(() => { goodsPage.value = 1; loadAnalytics() }, 300) })
watch(goodsSource, () => { goodsPage.value = 1; loadAnalytics() })
onScopeDispose(() => { disposed = true; generation++; detailController?.abort(); analyticsController?.abort(); eventsController?.abort(); clearTimeout(searchTimer) })
defineExpose({ refresh, requestClose })
</script>

<template>
    <section class="supplier-detail" aria-label="Карточка поставщика">
        <header class="detail-heading">
            <div class="detail-title"><span class="eyebrow">Поставщик · {{ unitId }}</span><h2>{{ detail?.unit?.name || 'Загрузка поставщика…' }}</h2><div v-if="profile" class="badges"><span>{{ statuses[profile.status] }}</span><span>{{ priorities[profile.priority] }} приоритет</span><span :class="{ attention: ['overdue', 'today'].includes(profile.review_state) }">{{ reviewStates[profile.review_state] }}</span><span v-if="!profile.exists">Ещё не добавлен в реестр</span></div></div>
            <div class="heading-actions"><button v-if="detail" type="button" @click="emit('edit', { unit: detail.unit, profile })">{{ profile?.exists ? 'Изменить профиль' : 'Добавить в реестр' }}</button><button v-if="detail" type="button" class="primary" @click="openComposer">+ Новый заказ</button><button type="button" :disabled="saving || composerOpen" aria-label="Закрыть карточку поставщика" @click="requestClose">×</button></div>
        </header>
        <div v-if="detailError" class="message error" role="alert">{{ detailError }} <button type="button" @click="loadDetail">Повторить</button></div>
        <div v-if="detail" class="detail-context">
            <Link :href="`/Ameise/unit/${detail.unit.id}`">Открыть контрагента ↗</Link>
            <span>Ответственный: <strong>{{ profile?.owner?.name || 'не назначен' }}</strong></span>
            <span>Следующая проверка: <strong>{{ date(profile?.next_review_on) }}</strong></span>
            <span>Последняя проверка: {{ date(profile?.last_reviewed_at, true) }}</span>
            <span v-for="item in detail.contacts?.emails || []" :key="item.address"><a v-if="emailUrl(item.address)" :href="emailUrl(item.address)">{{ item.address }}</a><span v-else>{{ item.address }}</span></span>
            <span v-for="item in detail.contacts?.telephones || []" :key="item.number"><a v-if="phoneUrl(item.number)" :href="phoneUrl(item.number)">{{ item.number }}</a><span v-else>{{ item.number }}</span></span>
            <span v-for="item in detail.contacts?.uris || []" :key="item.uri"><a v-if="safeWebUrl(item.uri)" :href="safeWebUrl(item.uri)" target="_blank" rel="noopener noreferrer">{{ item.uri }}</a><span v-else>{{ item.uri }}</span></span>
        </div>
        <nav class="detail-tabs" aria-label="Разделы карточки"><button v-for="item in tabs" :key="item.value" type="button" :aria-pressed="tab === item.value" :class="{ active: tab === item.value }" @click="tab = item.value">{{ item.label }}</button></nav>
        <div class="detail-body">
            <div v-if="tab !== 'orders' && tab !== 'journal'" class="period-row"><span>Период: {{ date(analytics?.period?.date_from || dateFrom) }} — {{ date(analytics?.period?.date_to || dateTo) }} · Москва</span><button type="button" :disabled="analyticsLoading" @click="loadAnalytics">{{ analyticsLoading ? 'Загрузка…' : 'Обновить показатели' }}</button></div>
            <div v-if="analyticsError && ['overview', 'goods', 'purchases'].includes(tab)" class="message error" role="alert">{{ analyticsError }} <button type="button" @click="loadAnalytics">Повторить</button></div>
            <template v-if="tab === 'overview'">
                <div v-if="summary" class="metrics-grid" :aria-busy="analyticsLoading">
                    <article v-for="source in ['orders', 'purchases']" :key="source" class="metric-card"><h3>{{ source === 'orders' ? 'Размещённые заказы' : 'Приходы по контрагенту' }}</h3><strong class="metric-number">{{ summary[source]?.count || 0 }} <small>док.</small></strong><div class="amount-lines"><span v-for="line in moneyLines(summary[source]?.totals_by_currency)" :key="line">{{ line }}</span><span v-if="!moneyLines(summary[source]?.totals_by_currency).length">Сумма не определена</span></div><dl><div><dt>Документов за 30 дней</dt><dd>{{ frequency(summary[source]?.per_30_days) }}</dd></div><div><dt>Средний интервал</dt><dd>{{ frequency(summary[source]?.average_interval_days) }} дн.</dd></div><div><dt>Товаров за период</dt><dd>{{ summary[source]?.distinct_goods_count || 0 }}</dd></div><div><dt>Последний за всё время</dt><dd>{{ date(summary[source]?.last_at_all_time) }}</dd></div><div><dt>Дней с последнего</dt><dd>{{ summary[source]?.days_since_last ?? '—' }}</dd></div><div v-if="summary[source]?.unpriced_items_count"><dt>Строк без цены</dt><dd>{{ summary[source].unpriced_items_count }}</dd></div></dl></article>
                    <article class="metric-card"><h3>Текущий контроль</h3><p class="muted">Независимо от выбранного периода</p><dl><div><dt>Открытые заказы</dt><dd>{{ summary.monitoring?.open_orders_count || 0 }}</dd></div><div><dt>Доставка просрочена</dt><dd :class="{ attention: summary.monitoring?.overdue_delivery_count }">{{ summary.monitoring?.overdue_delivery_count || 0 }}</dd></div><div><dt>Дата доставки не задана</dt><dd>{{ summary.monitoring?.unknown_delivery_count || 0 }}</dd></div><div><dt>Отправка не подтверждена</dt><dd :class="{ attention: summary.monitoring?.uncertain_send_count }">{{ summary.monitoring?.uncertain_send_count || 0 }}</dd></div></dl><button type="button" @click="tab = 'orders'">Открыть все заказы →</button></article>
                </div>
                <div v-if="summary?.shared_purchases?.count" class="message warning"><strong>Приходы общего юрлица: {{ summary.shared_purchases.count }} док.</strong><span v-for="line in moneyLines(summary.shared_purchases.totals_by_currency)" :key="line"> · {{ line }}</span><p>Юрлицо связано с несколькими контрагентами. Эти приходы показаны отдельно и не включены в сумму поставщика.</p></div>
                <details v-if="analytics?.warnings?.length" class="calculation-notes"><summary>Как считаются показатели</summary><ul><li v-for="warning in analytics.warnings" :key="warning">{{ warning }}</li></ul></details>
                <section v-if="monthly.length" class="chart-card" aria-label="Динамика по месяцам">
                    <header><h3>По месяцам</h3><label>Показатель <select v-model="chartMetric" aria-label="Показатель"><option value="count">Число документов</option><option v-for="code in chartCurrencies" :key="code" :value="code">Суммы · {{ currencyName(code) }}</option></select></label></header>
                    <div class="chart-legend"><span class="order-dot">Заказы</span><span class="purchase-dot">Приходы</span><span>Нажмите на месяц для точных значений</span></div>
                    <div class="chart-scroll"><div class="monthly-chart"><button v-for="row in monthly" :key="row.month" type="button" class="month-column" :class="{ selected: selectedMonthData?.month === row.month }" :aria-label="`${monthLabel(row.month)}: заказы ${chartAmount(row, 'orders')}, приходы ${chartAmount(row, 'purchases')}`" :title="`${monthLabel(row.month)}: заказы ${chartAmount(row, 'orders')}, приходы ${chartAmount(row, 'purchases')}`" @click="selectedMonth = row.month"><span class="month-bars" aria-hidden="true"><span class="orders-bar" :style="{ height: barHeight(row, 'orders') }"></span><span class="purchases-bar" :style="{ height: barHeight(row, 'purchases') }"></span></span><span>{{ monthLabel(row.month) }}</span></button></div></div>
                    <p v-if="selectedMonthData" class="chart-readout" aria-live="polite"><strong>{{ monthLabel(selectedMonthData.month) }}</strong> · Заказы: {{ chartAmount(selectedMonthData, 'orders') }} · Приходы: {{ chartAmount(selectedMonthData, 'purchases') }}</p>
                </section>
                <div class="overview-lower">
                    <section v-if="profile?.exists" class="review-card"><h3>Регулярная проверка</h3><p class="muted">Интервал: {{ profile.review_interval_days }} дней. {{ profile.is_watched ? 'Поставщик на контроле.' : 'Регулярный контроль выключен.' }}</p><form @submit.prevent="saveNote"><label>Действие<select v-model="action" aria-label="Действие" :disabled="saving"><option value="review">Зафиксировать проверку</option><option value="note">Добавить заметку</option></select></label><label>Результат / комментарий<textarea v-model="note" rows="3" maxlength="10000" required :disabled="saving" placeholder="Что проверили, договорённости и следующий шаг"></textarea></label><label v-if="action === 'review'">Следующая проверка<input v-model="nextReview" type="date" aria-label="Следующая проверка" :disabled="saving" /><small>Если дату не задать, используется интервал {{ profile.review_interval_days }} дней.</small></label><div v-if="operationError" class="message error" role="alert">{{ operationError }}<button v-if="conflict" type="button" :disabled="detailLoading" @click="refresh">Обновить профиль, сохранив текст</button></div><p v-if="conflict" class="muted">Профиль изменён другим сотрудником. Обновите его и проверьте дату перед сохранением.</p><div v-if="notice" class="message success" role="status">{{ notice }}</div><button type="submit" class="primary" :disabled="saving || conflict || !note.trim()">{{ saving ? 'Сохранение…' : action === 'review' ? 'Проверка выполнена' : 'Сохранить заметку' }}</button></form></section>
                    <section v-else class="review-card"><h3>Регулярная проверка</h3><p class="muted">Сначала добавьте поставщика в реестр вручную. После этого здесь можно записывать проверки и заметки.</p><button v-if="detail" type="button" @click="emit('edit', { unit: detail.unit, profile })">Добавить в реестр</button></section>
                    <section class="profile-card"><h3>О поставщике</h3><p class="preserve-lines">{{ profile?.notes || 'Описание не заполнено.' }}</p><h4>Юридические лица</h4><ul class="plain-list"><li v-for="entity in detail?.entities || []" :key="entity.id">{{ entity.name }} <span v-if="entity.shared" class="attention">· общее для нескольких контрагентов</span></li><li v-if="!detail?.entities?.length" class="muted">Не связаны</li></ul><h4 v-if="detail?.sources?.length">Источники</h4><p>{{ (detail?.sources || []).map(item => item.label).join(' · ') }}</p><h4 v-if="detail?.pipelines?.length">Работа с поставщиком</h4><ul class="plain-list"><li v-for="(pipeline, index) in detail?.pipelines || []" :key="index">{{ pipeline.name }} · {{ pipeline.stage }}<small v-if="pipeline.next_contact_at">Следующий контакт: {{ date(pipeline.next_contact_at, true) }}</small></li></ul></section>
                </div>
            </template>
            <section v-else-if="tab === 'goods'">
                <div class="table-filters"><label class="search">Поиск товара<input v-model="goodsSearch" type="search" maxlength="150" placeholder="Название товара" /></label><label>Источник<select v-model="goodsSource" aria-label="Источник"><option value="all">Заказы и приходы</option><option value="orders">Заказы</option><option value="purchases">Приходы</option></select></label></div><p class="muted">Количество сгруппировано по единицам, сохранённым в документах. Заказы и приходы показаны отдельно.</p>
                <div class="table-scroll" :aria-busy="analyticsLoading"><table><thead><tr><th>Товар</th><th>Источник</th><th>Единица</th><th class="numeric">Количество</th><th class="numeric">Документов</th><th>Суммы по валютам</th><th>Последний</th></tr></thead><tbody><tr v-for="good in analytics?.goods?.data || []" :key="good.key"><td>{{ good.name }}</td><td>{{ good.source === 'orders' ? 'Заказы' : 'Приходы' }}</td><td>{{ good.unit_label || 'Не указана' }}<small v-if="good.kilograms_per_unit != null">{{ number(good.kilograms_per_unit) }} кг/ед.</small></td><td class="numeric">{{ number(good.quantity) }}</td><td class="numeric">{{ good.documents_count }}</td><td><span v-for="line in moneyLines(good.totals_by_currency)" :key="line" class="amount-line">{{ line }}</span><span v-if="!moneyLines(good.totals_by_currency).length">—</span><small v-if="good.unpriced_items_count">Без цены: {{ good.unpriced_items_count }} строк</small></td><td class="nowrap">{{ date(good.last_at) }}</td></tr><tr v-if="!analytics?.goods?.data?.length"><td colspan="7" class="empty">{{ analyticsLoading ? 'Загрузка товаров…' : 'Товары за выбранный период не найдены.' }}</td></tr></tbody></table></div>
                <footer class="pagination"><span>Групп товаров: {{ analytics?.goods?.meta?.total || 0 }}</span><div><button type="button" :disabled="analyticsLoading || goodsPage <= 1" @click="goodsPage--; loadAnalytics()">← Назад</button><span>{{ goodsPage }} / {{ analytics?.goods?.meta?.last_page || 1 }}</span><button type="button" :disabled="analyticsLoading || goodsPage >= (analytics?.goods?.meta?.last_page || 1)" @click="goodsPage++; loadAnalytics()">Далее →</button></div></footer>
            </section>
            <section v-else-if="tab === 'orders'"><p class="message info">Все заказы поставщика за всё время, включая черновики. Выбранный период аналитики к этому списку не применяется.</p><SupplierOrdersRegister :unit-id="unitId" :unit-name="detail?.unit?.name || ''" @changed="ordersChanged" /></section>
            <section v-else-if="tab === 'purchases'"><p class="muted">Приходы показаны по текущей связи юридического лица с контрагентом. Приход не означает оплату или исполнение конкретного заказа.</p><div class="table-scroll" :aria-busy="analyticsLoading"><table><thead><tr><th>Приход</th><th>Дата</th><th>Юридическое лицо</th><th class="numeric">Позиций</th><th>Суммы по валютам</th></tr></thead><tbody><tr v-for="purchase in analytics?.purchases?.data || []" :key="purchase.id"><td>№ {{ purchase.id }}<small v-if="purchase.shared" class="attention">Общее юрлицо · вне итогов поставщика</small></td><td class="nowrap">{{ date(purchase.date) }}</td><td>{{ purchase.entity?.name || '—' }}</td><td class="numeric">{{ purchase.items_count }}</td><td><span v-for="line in moneyLines(purchase.totals_by_currency)" :key="line" class="amount-line">{{ line }}</span><span v-if="!moneyLines(purchase.totals_by_currency).length">—</span></td></tr><tr v-if="!analytics?.purchases?.data?.length"><td colspan="5" class="empty">{{ analyticsLoading ? 'Загрузка приходов…' : 'Приходов за выбранный период нет.' }}</td></tr></tbody></table></div><footer class="pagination"><span>Приходов: {{ analytics?.purchases?.meta?.total || 0 }}</span><div><button type="button" :disabled="analyticsLoading || purchasesPage <= 1" @click="purchasesPage--; loadAnalytics()">← Назад</button><span>{{ purchasesPage }} / {{ analytics?.purchases?.meta?.last_page || 1 }}</span><button type="button" :disabled="analyticsLoading || purchasesPage >= (analytics?.purchases?.meta?.last_page || 1)" @click="purchasesPage++; loadAnalytics()">Далее →</button></div></footer></section>
            <section v-else-if="tab === 'journal'"><header class="journal-heading"><h3>История проверок и изменений</h3><button type="button" :disabled="eventsLoading" @click="loadEvents()">Обновить</button></header><div v-if="eventsError" class="message error" role="alert">{{ eventsError }} <button type="button" @click="loadEvents()">Повторить</button></div><ol class="event-list"><li v-for="event in events?.data || []" :key="event.id"><header><strong>{{ eventTypes[event.type] || 'Изменение профиля' }}</strong><span>{{ event.actor?.name || 'Сотрудник не указан' }} · {{ date(event.created_at, true) }}</span></header><p v-if="event.body" class="preserve-lines">{{ event.body }}</p><dl v-if="eventChanges(event).length" class="changes"><div v-for="change in eventChanges(event)" :key="change.field"><dt>{{ change.label }}</dt><dd>{{ change.before }} → <strong>{{ change.after }}</strong></dd></div></dl></li></ol><p v-if="!events?.data?.length" class="empty">{{ eventsLoading ? 'Загрузка журнала…' : 'В журнале пока нет записей.' }}</p><footer class="pagination"><span>Записей: {{ events?.total || 0 }}</span><div><button type="button" :disabled="eventsLoading || eventsPage <= 1" @click="loadEvents(eventsPage - 1)">← Назад</button><span>{{ eventsPage }} / {{ events?.last_page || 1 }}</span><button type="button" :disabled="eventsLoading || eventsPage >= (events?.last_page || 1)" @click="loadEvents(eventsPage + 1)">Далее →</button></div></footer></section>
        </div>
        <div v-if="closeConfirmation" class="close-confirmation" role="alert"><span>Остался несохранённый комментарий или дата проверки.</span><button type="button" @click="closeConfirmation = false">Продолжить работу</button><button type="button" @click="discardClose">Закрыть без сохранения</button></div>
        <v-dialog v-model="composerOpen" persistent width="96vw" max-width="1800"><SupplierOrderComposer v-if="composerOpen" :key="composerKey" :unit-id="composerUnit" @close="composerOpen = false" @saved="orderSaved" @sent="orderSaved" /></v-dialog>
    </section>
</template>

<style scoped>
.supplier-detail { font: 12px/1.45 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #30333a; background: #f7f8fa; border: 1px solid #d8dce3; border-radius: 10px; min-width: 0; max-height: 92dvh; display: flex; flex-direction: column; box-shadow: 0 20px 65px #1c263322; overflow: hidden; }
.supplier-detail *, .supplier-detail *::before, .supplier-detail *::after { box-sizing: border-box; }
.supplier-detail button, .supplier-detail input, .supplier-detail select, .supplier-detail textarea { font: inherit; }
.supplier-detail button { min-height: 30px; padding: 4px 10px; color: #424c60; background: #fff; border: 1px solid #cdd3dd; border-radius: 5px; cursor: pointer; }
.supplier-detail button:hover:not(:disabled) { background: #edf2f9; }
.supplier-detail button:disabled { opacity: .5; cursor: default; }
.supplier-detail button.primary { color: #fff; background: #506f9b; border-color: #506f9b; }
.supplier-detail button.primary:hover:not(:disabled) { background: #405e8a; }
.supplier-detail :is(button, a, input, select, textarea):focus-visible { outline: 2px solid #5b88be; outline-offset: 2px; }
.supplier-detail a { color: #3e6294; text-decoration: none; }
.supplier-detail a:hover { text-decoration: underline; }
.supplier-detail h2, .supplier-detail h3, .supplier-detail h4, .supplier-detail p { margin: 0; }
.supplier-detail h2 { font-size: 17px; line-height: 1.3; font-weight: 600; overflow-wrap: anywhere; }
.supplier-detail h3 { font-size: 13px; font-weight: 600; }
.supplier-detail h4 { margin-top: 14px; font-size: 12px; }
.supplier-detail small { font-size: 12px; }
.detail-heading { padding: 12px 16px; display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; background: #fff; }
.detail-title { min-width: 0; }
.eyebrow { color: #737d8c; font-size: 12px; }
.heading-actions, .badges { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.heading-actions { flex-shrink: 0; }
.badges { margin-top: 7px; }
.badges > span { border-radius: 4px; background: #eff2f6; padding: 2px 6px; }
.detail-context { display: flex; flex-wrap: wrap; gap: 7px 18px; padding: 9px 16px; background: #fff; border-top: 1px solid #edf0f3; color: #697381; overflow-wrap: anywhere; }
.detail-tabs { display: flex; flex-shrink: 0; gap: 4px; padding: 7px 16px; border-block: 1px solid #dce0e7; background: #eef0f4; overflow-x: auto; }
.detail-tabs button { border-color: transparent; background: transparent; white-space: nowrap; }
.detail-tabs button.active { background: #fff; border-color: #d2d8e2; color: #355883; box-shadow: 0 1px 2px #1c263310; }
.detail-body { overflow: auto; min-height: 200px; padding: 14px 16px 20px; }
.period-row, .journal-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.period-row { color: #697381; }
.message { padding: 9px 11px; border: 1px solid #d9e0e8; border-radius: 5px; margin-bottom: 10px; overflow-wrap: anywhere; }
.message button { margin-left: 8px; }
.message p { margin-top: 4px; }
.error { background: #fff3f3; border-color: #edcfd1; color: #98434e; }
.warning { background: #fffaee; border-color: #eadcba; color: #806635; }
.success { background: #eff8f2; border-color: #cce0d2; color: #416e50; }
.calculation-notes { margin-bottom: 12px; color: #727c8b; }
.calculation-notes summary { width: fit-content; padding: 4px 0; cursor: pointer; color: #5d6c7f; }
.calculation-notes summary:focus-visible { outline: 2px solid #5b88be; outline-offset: 2px; }
.calculation-notes ul { margin: 4px 0 0; padding-left: 20px; display: grid; gap: 5px; }
.info { background: #edf3fa; color: #4f6380; }
.attention { color: #986126; }
.metrics-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 12px; }
.metric-card, .chart-card, .review-card, .profile-card { min-width: 0; border: 1px solid #dce1e8; border-radius: 6px; padding: 12px; background: #fff; }
.metric-number { display: block; font-size: 24px; margin: 5px 0; font-variant-numeric: tabular-nums; }
.metric-number small { color: #7a8290; font-weight: 400; }
.amount-lines { min-height: 22px; display: flex; flex-wrap: wrap; gap: 4px 12px; font-weight: 600; color: #415d7e; font-variant-numeric: tabular-nums; }
.supplier-detail dl { margin: 10px 0 0; }
.supplier-detail dl > div { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 4px 0; border-bottom: 1px solid #f0f2f5; }
.supplier-detail dt { color: #747e8c; }
.supplier-detail dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
.metric-card > button { margin-top: 10px; }
.muted { color: #727c8b; }
.chart-card { margin-bottom: 12px; }
.chart-card > header { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
.chart-card label { display: flex; align-items: center; gap: 8px; }
.supplier-detail input, .supplier-detail select, .supplier-detail textarea { min-height: 30px; border: 1px solid #ccd3dd; border-radius: 4px; padding: 5px 8px; background: #fff; color: inherit; min-width: 0; max-width: 100%; }
.chart-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; color: #747e8b; }
.order-dot::before, .purchase-dot::before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 5px; background: #6485b3; }
.purchase-dot::before { background: #69a694; }
.chart-scroll { overflow-x: auto; padding-bottom: 4px; }
.monthly-chart { display: flex; min-width: min-content; gap: 4px; padding: 8px 0; }
.supplier-detail .month-column { display: flex; flex: 1 0 68px; min-width: 68px; flex-direction: column; align-items: center; gap: 6px; padding: 5px; border-color: transparent; color: #707c8d; background: #fff; }
.supplier-detail .month-column.selected { border-color: #b7c9df; background: #f3f7fc; }
.month-bars { display: flex; align-items: flex-end; justify-content: center; height: 94px; width: 100%; gap: 4px; border-bottom: 1px solid #d6deea; }
.month-bars > span { width: 17px; border-radius: 3px 3px 0 0; }
.orders-bar { background: #6485b3; }
.purchases-bar { background: #69a694; }
.chart-readout { padding-top: 8px; border-top: 1px solid #e6ebf1; font-variant-numeric: tabular-nums; }
.overview-lower { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; }
.review-card form { display: grid; gap: 10px; margin-top: 10px; }
.review-card form > label, .table-filters label { display: grid; gap: 4px; color: #687382; }
.review-card textarea { resize: vertical; width: 100%; }
.review-card small { color: #788392; }
.review-card button[type=submit] { justify-self: start; }
.preserve-lines { white-space: pre-wrap; overflow-wrap: anywhere; }
.profile-card > p { margin-top: 8px; }
.plain-list { list-style: none; padding: 0; margin: 6px 0 0; }
.plain-list > li { padding: 4px 0; overflow-wrap: anywhere; }
.plain-list small { display: block; color: #788392; }
.table-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 10px; margin-bottom: 8px; }
.table-filters .search { flex: 1 1 240px; max-width: 420px; }
.table-scroll { overflow: auto; border: 1px solid #dce1e8; border-radius: 5px; background: #fff; margin-top: 10px; }
.supplier-detail table { width: 100%; border-collapse: collapse; }
.supplier-detail th { text-align: left; font-weight: 600; color: #6f7989; background: #eff2f6; white-space: nowrap; }
.supplier-detail :is(th, td) { padding: 8px 10px; border-bottom: 1px solid #e7ebf0; vertical-align: top; }
.supplier-detail td:first-child { min-width: 160px; }
.supplier-detail td small, .amount-line { display: block; }
.supplier-detail td small { color: #778291; margin-top: 3px; }
.supplier-detail .numeric { text-align: right; font-variant-numeric: tabular-nums; }
.nowrap, .amount-line { white-space: nowrap; font-variant-numeric: tabular-nums; }
.empty { padding: 28px 12px; color: #7b8593; text-align: center; }
.pagination { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; color: #747f8f; }
.pagination > div { display: flex; align-items: center; gap: 8px; }
.event-list { list-style: none; padding: 0; margin: 0; }
.event-list > li { border: 1px solid #dce2ea; border-radius: 5px; background: #fff; padding: 12px; margin-bottom: 8px; }
.event-list header { display: flex; gap: 8px 16px; flex-wrap: wrap; justify-content: space-between; }
.event-list header span { color: #778292; }
.event-list p { margin-top: 8px; }
.changes > div { justify-content: start !important; }
.changes dt { flex: 0 0 170px; }
.changes dd { text-align: left; overflow-wrap: anywhere; white-space: pre-wrap; }
.close-confirmation { flex-shrink: 0; padding: 12px 16px; background: #fff5e5; border-top: 1px solid #ead7b7; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.close-confirmation span { margin-right: auto; }
@media (max-width: 900px) { .metrics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .metric-card:last-child { grid-column: 1 / -1; } .heading-actions { flex-shrink: 1; } }
@media (max-width: 600px) { .detail-heading { flex-direction: column; padding: 10px; gap: 10px; } .heading-actions { width: 100%; } .heading-actions button:last-child { margin-left: auto; } .detail-context, .detail-tabs { padding: 8px 10px; } .detail-body { padding: 10px; } .metrics-grid, .overview-lower { grid-template-columns: minmax(0, 1fr); } .changes > div { flex-direction: column; gap: 4px !important; } .changes dt { flex-basis: auto; } }
</style>
