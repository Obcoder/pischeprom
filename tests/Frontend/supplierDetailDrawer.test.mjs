import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const flush = () => new Promise(resolve => setImmediate(resolve))
const profile = (overrides = {}) => ({ exists: true, unit_id: 42, revision: 4, status: 'active', priority: 'normal', is_watched: true, owner: { id: 8, name: 'Анна' }, review_interval_days: 30, next_review_on: '2026-10-20', last_reviewed_at: null, notes: '', review_state: 'scheduled', ...overrides })
const detail = (id = 42) => ({ unit: { id, name: `Поставщик ${id}` }, profile: profile({ unit_id: id }), contacts: { emails: [{ address: 'supplier@example.test' }], telephones: [{ number: '+7 (123) 456-78-90' }], uris: [{ uri: 'https://example.test' }, { uri: 'javascript:alert(1)' }] }, entities: [{ id: 3, name: 'Общее юрлицо', shared: true }], sources: [], pipelines: [], capabilities: { can_send: true } })
const metrics = () => ({ count: 2, totals_by_currency: { RUB: '1000.0000', USD: '30.2500' }, unpriced_items_count: 1, distinct_goods_count: 2, per_30_days: .67, average_interval_days: 12, last_at_all_time: '2026-10-10T10:00:00Z', days_since_last: 3 })
const analytics = (overrides = {}) => ({ period: { date_from: '2026-07-16', date_to: '2026-10-13', days: 90, timezone: 'Europe/Moscow' }, summary: { orders: metrics(), purchases: metrics(), monitoring: { open_orders_count: 3, overdue_delivery_count: 1 }, shared_purchases: { count: 1, totals_by_currency: { RUB: '12.0000' }, entity_ids: [3] } }, monthly: [{ month: '2026-09', orders_count: 2, purchases_count: 1, order_totals_by_currency: { RUB: '1000.0000', USD: '10.0000' }, purchase_totals_by_currency: { RUB: '500.0000', UNKNOWN: '25.0000' } }, { month: '2026-10', orders_count: 0, purchases_count: 0, order_totals_by_currency: {}, purchase_totals_by_currency: {} }], goods: { data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 25 } }, purchases: { data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 25 } }, warnings: ['Приходы не привязаны к конкретным заказам.'], ...overrides })
const events = (data = []) => ({ data, current_page: 1, last_page: 1, total: data.length })
function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/Suppliers/SupplierDetailDrawer.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename }); assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'supplier-detail' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'supplier-detail', compilerOptions: { bindingMetadata: script.bindings } }); assert.deepEqual(template.errors, [])
    const props = Vue.reactive({ unitId: 42, dateFrom: '2026-07-16', dateTo: '2026-10-13', ...overrides }), requests = [], emitted = [], exposed = {}
    const environment = { ...Vue, Link: 'Link', SupplierOrdersRegister: 'SupplierOrdersRegister', SupplierOrderComposer: 'SupplierOrderComposer', axios: Object.fromEntries(['get', 'post'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])) }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment), scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose(value) { Object.assign(exposed, value) }, emit(...args) { emitted.push(args) } }))
    t.after(() => scope.stop())
    return { api, props, requests, emitted, exposed, scope, render: templateRenderer(template, api, props) }
}
async function initialize(h) {
    h.requests[0].resolve(detail(h.props.unitId)); h.requests[1].resolve(analytics()); h.requests[2].resolve(events()); await flush()
}
function text(node) { if (typeof node === 'string') return node; if (!node || typeof node !== 'object') return ''; return (Array.isArray(node) ? node : Array.isArray(node.children) ? node.children : typeof node.children === 'string' ? [node.children] : typeof node.children?.default === 'function' ? node.children.default() : []).map(text).join(' ') }

test('loads the explicit supplier and keeps order and purchase money separate by currency', async t => {
    const h = harness(t); assert.deepEqual(h.requests.map(r => r.url), ['/api/suppliers/42', '/api/suppliers/42/analytics', '/api/suppliers/42/events'])
    assert.deepEqual(h.requests[1].body.params, { date_from: '2026-07-16', date_to: '2026-10-13', goods_page: 1, purchases_page: 1, goods_search: undefined, goods_source: 'all', per_page: 25 })
    await initialize(h)
    const rendered = text(h.render()); assert.match(rendered, /Размещённые заказы/); assert.match(rendered, /Приходы по контрагенту/); assert.match(rendered, /не включены в сумму поставщика/)
    assert.deepEqual(h.api.moneyLines({ RUB: '12345678901234567890.1234', USD: '3.2000', UNKNOWN: '9.0000' }), ['12\u00a0345\u00a0678\u00a0901\u00a0234\u00a0567\u00a0890,1234 RUB', '3,2 USD', '9 Валюта не указана'])
    assert.equal(h.api.number('12.000006'), '12,000006')
    assert.equal(h.api.date('2026-10-12T22:30:00Z'), '13.10.2026')
    assert.equal(typeof h.exposed.refresh, 'function')
})

test('unit and period changes ignore stale responses and disposal cancels all reads', async t => {
    const h = harness(t), old = [...h.requests]
    h.props.unitId = 55; await Vue.nextTick(); const current = h.requests.slice(-3)
    current[0].resolve(detail(55)); current[1].resolve(analytics()); current[2].resolve(events([{ id: 55, type: 'note', body: 'Новая запись' }])); await flush()
    old[0].resolve(detail(42)); old[1].resolve(analytics({ warnings: ['Устарело'] })); old[2].resolve(events()); await flush()
    assert.equal(h.api.detail.value.unit.id, 55); assert.equal(h.api.events.value.data[0].id, 55); assert.notDeepEqual(h.api.analytics.value.warnings, ['Устарело'])
    const pending = h.api.loadAnalytics(), priorPeriod = h.requests.at(-1)
    h.props.dateFrom = '2026-10-01'; await Vue.nextTick(); const newPeriod = h.requests.at(-1)
    assert.equal(priorPeriod.body.signal.aborted, true); assert.equal(newPeriod.body.params.date_from, '2026-10-01')
    newPeriod.resolve(analytics({ warnings: ['Актуально'] })); await flush(); priorPeriod.resolve(analytics({ warnings: ['Старый период'] })); await pending
    assert.deepEqual(h.api.analytics.value.warnings, ['Актуально'])
    const late = h.api.loadDetail(), lateRequest = h.requests.at(-1); h.scope.stop(); assert.equal(lateRequest.body.signal.aborted, true)
    lateRequest.reject({ response: { data: { message: 'Поздняя ошибка' } } }); await late; assert.equal(h.api.detailError.value, '')
})

test('goods search and pagination query the server with preserved historic unit rows', async t => {
    const h = harness(t); await initialize(h)
    const oldLoad = h.api.loadAnalytics(), stale = h.requests.at(-1)
    h.api.goodsSearch.value = 'Мука'; await Vue.nextTick(); assert.equal(stale.body.signal.aborted, true)
    stale.resolve(analytics({ warnings: ['Старая выборка'] })); await oldLoad; assert.notDeepEqual(h.api.analytics.value.warnings, ['Старая выборка'])
    h.api.goodsSource.value = 'purchases'; await Vue.nextTick(); const requested = h.requests.at(-1)
    assert.equal(requested.body.params.goods_search, 'Мука'); assert.equal(requested.body.params.goods_source, 'purchases')
    requested.resolve(analytics({ goods: { data: [{ key: 'kg', name: 'Мука', source: 'purchases', unit_label: 'коробка', kilograms_per_unit: '10.000000', quantity: '12.000006', documents_count: 2, totals_by_currency: { RUB: '10.0000' } }, { key: 'bag', name: 'Мука', source: 'purchases', unit_label: 'коробка', kilograms_per_unit: '25.000000', quantity: '2.000000', documents_count: 1, totals_by_currency: {} }], meta: { current_page: 1, last_page: 2, total: 26 } } })); await flush()
    h.api.tab.value = 'goods'; const rendered = text(h.render()); assert.match(rendered, /10 кг\/ед./); assert.match(rendered, /25 кг\/ед./); assert.match(rendered, /12,000006/)
    h.api.goodsPage.value = 3; const paging = h.api.loadAnalytics(), outdated = h.requests.at(-1)
    outdated.resolve(analytics({ goods: { data: [], meta: { current_page: 3, last_page: 2, total: 26 } } })); await flush()
    assert.equal(h.requests.at(-1).body.params.goods_page, 2)
    h.requests.at(-1).resolve(analytics({ goods: { data: [], meta: { current_page: 2, last_page: 2, total: 26 } } })); await paging; assert.equal(h.api.goodsPage.value, 2)
})

test('review conflicts retain notes and date, require refresh, and submit the new revision', async t => {
    const h = harness(t); await initialize(h)
    h.api.note.value = 'Проверили цены'; h.api.nextReview.value = '2026-11-01'; const save = h.api.saveNote(), request = h.requests.at(-1)
    assert.equal(request.url, '/api/suppliers/42/reviews'); assert.deepEqual(request.body, { revision: 4, note: 'Проверили цены', next_review_on: '2026-11-01' })
    await h.api.saveNote(); assert.equal(h.requests.at(-1), request, 'Repeated click must not duplicate a review')
    request.reject({ response: { status: 409, data: { message: 'Профиль уже изменён' } } }); await save
    assert.equal(h.api.conflict.value, true); assert.equal(h.api.note.value, 'Проверили цены'); assert.equal(h.api.nextReview.value, '2026-11-01')
    await h.api.saveNote(); assert.equal(h.requests.at(-1), request, 'A conflicting revision cannot be resent before review')
    const refresh = h.exposed.refresh(), batch = h.requests.slice(-3)
    batch[0].resolve({ ...detail(), profile: profile({ revision: 8 }) }); batch[1].resolve(analytics()); batch[2].resolve(events()); await refresh
    assert.equal(h.api.note.value, 'Проверили цены'); assert.equal(h.api.conflict.value, false)
    const retry = h.api.saveNote(), next = h.requests.at(-1); assert.equal(next.body.revision, 8); next.resolve(profile({ revision: 9, next_review_on: '2026-11-01' })); await retry
    assert.equal(h.api.note.value, ''); assert.equal(h.api.nextReview.value, ''); assert.equal(h.api.profile.value.revision, 9); assert.ok(h.emitted.some(e => e[0] === 'changed'))
    assert.equal(h.requests.at(-1).url, '/api/suppliers/42/events'); h.requests.at(-1).resolve(events())
})

test('note action leaves scheduling to the server and close guards unsaved forms', async t => {
    const h = harness(t); await initialize(h)
    h.api.note.value = 'Договорились созвониться'; h.api.action.value = 'note'; h.api.nextReview.value = '2026-11-01'
    h.api.requestClose(); assert.equal(h.api.closeConfirmation.value, true); assert.deepEqual(h.emitted, [])
    const tree = h.render(), confirmation = findVNode(tree, node => hasClass(node, 'close-confirmation')); assert.ok(confirmation)
    const save = h.api.saveNote(), request = h.requests.at(-1); assert.equal(request.url, '/api/suppliers/42/notes'); assert.deepEqual(request.body, { revision: 4, note: 'Договорились созвониться' })
    h.api.requestClose(); assert.deepEqual(h.emitted, [])
    request.resolve(profile({ revision: 5 })); await save; h.requests.at(-1).resolve(events()); h.api.requestClose(); assert.ok(h.emitted.some(e => e[0] === 'close'))
})

test('monthly chart selects exact values for only the chosen currency and includes zero months', async t => {
    const h = harness(t); await initialize(h)
    assert.deepEqual(h.api.chartCurrencies.value, ['RUB', 'UNKNOWN', 'USD']); assert.equal(h.api.selectedMonthData.value.month, '2026-10')
    h.api.chartMetric.value = 'USD'; assert.equal(h.api.chartAmount(h.api.monthly.value[0], 'orders'), '10 USD'); assert.equal(h.api.chartAmount(h.api.monthly.value[0], 'purchases'), '0 USD')
    const tree = h.render(), september = findVNode(tree, node => node.type === 'button' && node.props?.['aria-label']?.includes('10 USD'))
    assert.ok(september); september.props.onClick(); assert.equal(h.api.selectedMonthData.value.month, '2026-09')
    assert.equal(h.api.chartMax.value, 10); assert.equal(h.api.barHeight(h.api.monthly.value[0], 'orders'), '100%')
    h.api.chartMetric.value = 'count'; assert.equal(h.api.chartAmount(h.api.monthly.value[0], 'orders'), '2 док.')
})

test('contacts render safe protocols and journal changes remain readable', async t => {
    const h = harness(t); await initialize(h)
    assert.equal(h.api.safeWebUrl('javascript:alert(1)'), null); assert.equal(h.api.safeWebUrl('data:text/html,test'), null)
    assert.equal(h.api.emailUrl('mail@example.test?bcc=evil@example.test'), null)
    assert.equal(h.api.emailUrl('a?subject=test@example.test'), 'mailto:a%3Fsubject%3Dtest@example.test')
    assert.equal(h.api.phoneUrl('+7 (123) 456-78-90'), 'tel:+71234567890')
    assert.equal(findVNode(h.render(), node => node.type === 'a' && node.props?.href === 'javascript:alert(1)'), null)
    const event = { id: 1, type: 'updated', actor: { name: 'Анна' }, created_at: '2026-10-13T10:00:00Z', changes: { status: { before: 'active', after: 'paused' }, owner_user_id: { before: 5, after: 8 }, owner_name: { before: 'Иван', after: 'Анна' }, is_watched: { before: false, after: true } } }
    assert.deepEqual(h.api.eventChanges(event).map(item => item.after), ['Приостановлен', 'Анна', 'Включён'])
    h.api.events.value = events([event]); h.api.tab.value = 'journal'; const rendered = text(h.render()); assert.match(rendered, /Приостановлен/); assert.match(rendered, /Иван →/); assert.doesNotMatch(rendered, /owner_user_id/)
})

test('all orders are explicitly independent of period and creation retains the persistent composer', async t => {
    const h = harness(t); await initialize(h); h.api.tab.value = 'orders'
    const tree = h.render(); assert.match(text(tree), /за всё время/)
    const register = findVNode(tree, node => node.type === 'SupplierOrdersRegister'); assert.equal(register.props['unit-id'], 42); assert.equal(register.props.dateFrom, undefined)
    register.props.onChanged(); assert.equal(h.requests.at(-1).url, '/api/suppliers/42/analytics'); h.requests.at(-1).resolve(analytics())
    h.api.openComposer(); let composer = findVNode(h.render(), node => node.type === 'SupplierOrderComposer'), dialog = findVNode(h.render(), node => node.type === 'v-dialog')
    assert.equal(composer.props['unit-id'], 42); assert.notEqual(dialog.props.persistent, undefined)
    const key = h.api.composerKey.value; composer.props.onSaved(); assert.equal(h.api.composerOpen.value, true); assert.equal(h.api.composerKey.value, key)
    h.requests.at(-1).resolve(analytics()); h.api.requestClose(); assert.equal(h.emitted.some(e => e[0] === 'close'), false)
    composer.props.onClose(); assert.equal(h.api.composerOpen.value, false)
})

test('failed independent reads remain actionable without hiding the loaded profile', async t => {
    const h = harness(t); h.requests[0].resolve(detail()); h.requests[1].reject({ response: { data: { message: 'Аналитика недоступна' } } }); h.requests[2].reject({ response: { data: { message: 'Журнал недоступен' } } }); await flush()
    assert.equal(h.api.detail.value.unit.id, 42); assert.equal(h.api.analyticsError.value, 'Аналитика недоступна'); assert.equal(h.api.eventsError.value, 'Журнал недоступен')
    assert.ok(findVNode(h.render(), node => node.props?.role === 'alert'))
    const retry = h.api.loadAnalytics(); h.requests.at(-1).resolve(analytics()); await retry; assert.equal(h.api.analyticsError.value, '')
})

test('opening an unregistered unit stays read only until explicit profile addition', async t => {
    const h = harness(t); h.requests[0].resolve({ ...detail(), profile: profile({ exists: false, revision: 0, is_watched: false, review_state: 'off' }) }); h.requests[1].resolve(analytics()); h.requests[2].resolve(events()); await flush()
    const tree = h.render(); assert.match(text(tree), /Сначала добавьте поставщика в реестр вручную/)
    assert.equal(findVNode(tree, node => node.type === 'form'), null)
    h.api.note.value = 'Не создавать автоматически'; await h.api.saveNote(); assert.equal(h.requests.length, 3)
    const add = findVNode(tree, node => node.type === 'button' && text(node) === 'Добавить в реестр'); add.props.onClick()
    assert.equal(h.emitted.at(-1)[0], 'edit'); assert.equal(h.emitted.at(-1)[1].profile.exists, false)
})

test('calculation notes are collapsed guidance while shared amounts stay visible and select labels are explicit', async t => {
    const h = harness(t); await initialize(h)
    const tree = h.render(), notes = findVNode(tree, node => node.type === 'details' && hasClass(node, 'calculation-notes'))
    assert.ok(notes); assert.equal(notes.props.open, undefined); assert.match(text(notes), /Как считаются показатели/); assert.match(text(notes), /Приходы не привязаны/)
    const warning = findVNode(tree, node => hasClass(node, 'warning')); assert.match(text(warning), /Приходы общего юрлица/); assert.match(text(warning), /12 RUB/)
    assert.ok(findVNode(tree, node => node.type === 'select' && node.props?.['aria-label'] === 'Показатель'))
    assert.ok(findVNode(tree, node => node.type === 'select' && node.props?.['aria-label'] === 'Действие'))
    assert.ok(findVNode(tree, node => node.type === 'input' && node.props?.['aria-label'] === 'Следующая проверка'))
    h.api.tab.value = 'goods'; assert.ok(findVNode(h.render(), node => node.type === 'select' && node.props?.['aria-label'] === 'Источник'))
})
