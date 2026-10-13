import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'
const flush = () => new Promise(resolve => setImmediate(resolve))
const options = { today: '2026-10-13', default_date_from: '2026-07-16', default_date_to: '2026-10-13', owners: [{ id: 3, name: 'Сотрудник' }], statuses: [{ value: 'active', label: 'Активный' }, { value: 'archived', label: 'Архив' }], priorities: [{ value: 'normal', label: 'Обычный' }] }
const profile = { exists: true, unit_id: 6, revision: 4, status: 'active', priority: 'normal', is_watched: true, owner_user_id: 3, owner: { id: 3, name: 'Сотрудник' }, review_interval_days: 30, next_review_on: '2026-10-15', notes: 'Согласовать срок', review_state: 'scheduled' }
const row = { unit: { id: 6, name: 'Поставщик' }, profile, entities: [{ id: 1, name: 'Компания', shared: true }], sources: [{ kind: 'manual', label: 'Добавлен вручную' }], metrics: { orders: { count: 3, totals_by_currency: { RUB: '123456789012345.6789', USD: '32.2500' }, unpriced_items_count: 1, per_30_days: 1, distinct_goods_count: 2, last_at_all_time: '2026-10-10T12:00:00Z', days_since_last: 3 }, purchases: { count: 1, totals_by_currency: { RUB: '150.0000' }, per_30_days: 0.3333 }, shared_purchases: { count: 2 }, monitoring: { open_orders_count: 2, overdue_delivery_count: 1, uncertain_send_count: 1 } } }
const dataset = (rows = []) => ({ data: structuredClone(rows), meta: { current_page: 1, last_page: 1, total: rows.length, per_page: 25 }, summary: { suppliers_count: rows.length, watched_count: rows.length, review_due_count: 0, overdue_delivery_suppliers_count: rows.length, uncertain_send_suppliers_count: rows.length }, period: { date_from: '2026-07-16', date_to: '2026-10-13', days: 90, timezone: 'Europe/Moscow' } })
function harness(t, name = 'SuppliersWorkspace', initial = {}) {
    const filename = `resources/js/Components/Suppliers/${name}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename }); assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: name }), template = compileTemplate({ source: descriptor.template.content, filename, id: name, compilerOptions: { bindingMetadata: compiled.bindings } }); assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], downloads = [], revoked = []
    const props = Vue.reactive({ initialUnitId: null, unit: null, profile: null, options, creating: false, ...initial })
    const environment = { ...Vue, Link: {}, SupplierDetailDrawer: {}, SupplierProfileEditor: {}, URL: { createObjectURL: () => 'blob:test', revokeObjectURL: value => revoked.push(value) }, document: { createElement: () => ({ click() { downloads.push({ href: this.href, filename: this.download }) }, remove() {} }), body: { appendChild() {} } }, axios: Object.fromEntries(['get', 'post', 'patch', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])) }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return'), component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope(), state = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    return { props, state, requests, emitted, downloads, revoked, render: templateRenderer(template, state, props) }
}
async function ready(t, rows = [], initial = {}) { const h = harness(t, 'SuppliersWorkspace', initial); h.requests[0].resolve(options); await flush(); h.requests.at(-1).resolve(dataset(rows)); await flush(); return h }

test('registry starts with manually selected watched suppliers and reading an empty registry never creates profiles', async t => {
    const h = await ready(t)
    assert.deepEqual(h.requests.map(item => item.url), ['/api/suppliers/options', '/api/suppliers'])
    assert.equal(h.requests[1].body.params.scope, 'watched'); assert.equal(h.requests[1].body.params.date_from, '2026-07-16')
    assert.equal(h.state.showEmptyOnboarding.value, true)
    h.state.openEditor(); assert.equal(h.state.editorOpen.value, true); assert.equal(h.requests.length, 2)
    assert.ok(h.requests.every(item => item.method === 'get'))
})

test('Unit deep links open details without automatically registering that Unit', async t => {
    const h = await ready(t, [], { initialUnitId: 91 })
    assert.equal(h.state.detailOpen.value, true); assert.equal(h.state.detailUnitId.value, 91)
    assert.ok(h.requests.every(item => item.method === 'get'))
})

test('filters use server scopes, keep whole-result summary, and reject outdated list responses', async t => {
    const h = await ready(t, [row])
    h.state.selectScope('all'); const old = h.requests.at(-1)
    h.state.filters.owner_user_id = 3; h.state.setAttention('review_due'); const current = h.requests.at(-1)
    assert.equal(old.body.signal.aborted, true)
    assert.equal(current.body.params.scope, 'all'); assert.equal(current.body.params.owner_user_id, 3); assert.equal(current.body.params.attention, 'review_due')
    current.resolve({ ...dataset([row]), summary: { suppliers_count: 125, watched_count: 99, review_due_count: 125 }, meta: { total: 125, current_page: 1, last_page: 5, per_page: 25 } }); await flush()
    old.resolve(dataset([])); await flush()
    assert.equal(h.state.rows.value.length, 1); assert.equal(h.state.summary.value.suppliers_count, 125)
    assert.equal(h.state.range.value, '1–25 из 125')
})

test('date presets use inclusive Moscow server dates and custom dates are sent unchanged', async t => {
    const h = await ready(t)
    h.state.selectPreset('30'); assert.equal(h.requests.at(-1).body.params.date_from, '2026-09-14'); assert.equal(h.requests.at(-1).body.params.date_to, '2026-10-13')
    h.requests.at(-1).resolve(dataset()); await flush()
    h.state.selectPreset('365'); assert.equal(h.requests.at(-1).body.params.date_from, '2025-10-14')
    h.requests.at(-1).resolve(dataset()); await flush()
    h.state.filters.date_from = '2026-10-01'; h.state.filters.date_to = '2026-10-07'; h.state.setCustomPeriod()
    assert.equal(h.state.preset.value, 'custom'); assert.equal(h.requests.at(-1).body.params.date_from, '2026-10-01')
    h.requests.at(-1).resolve(dataset()); await flush()
})

test('large decimal sums remain exact and currencies, purchases, and shared companies remain distinguishable', async t => {
    const h = await ready(t, [row])
    assert.equal(h.state.amount('123456789012345.6789'), '123\u00a0456\u00a0789\u00a0012\u00a0345,6789')
    assert.equal(h.state.amount('32.2500'), '32,25'); assert.equal(h.state.currency('UNKNOWN'), 'Валюта не указана')
    assert.equal(h.state.rows.value[0].metrics.orders.totals_by_currency.USD, '32.2500')
    assert.equal(h.state.rows.value[0].metrics.purchases.totals_by_currency.RUB, '150.0000')
    assert.ok(findVNode(h.render(), node => node.type === 'span' && node.props?.class === 'shared-entity'))
})

test('CSV export applies all current filters without exporting only the visible page', async t => {
    const h = await ready(t, [row])
    Object.assign(h.state.filters, { scope: 'all', priority: 'high', owner_user_id: 3, attention: 'no_orders' })
    h.state.meta.current_page = 3
    const pending = h.state.exportCsv(), request = h.requests.at(-1)
    assert.equal(request.url, '/api/suppliers/export'); assert.equal(request.body.responseType, 'blob'); assert.equal(request.body.params.page, undefined)
    assert.equal(request.body.params.priority, 'high'); assert.equal(request.body.params.attention, 'no_orders')
    request.resolve(new Blob(['Поставщик,Заказано RUB\nТест,123.0000'])); await pending
    assert.equal(h.downloads.length, 1); assert.equal(h.downloads[0].filename, 'suppliers-2026-07-16-2026-10-13.csv'); assert.deepEqual(h.revoked, ['blob:test'])
})

test('profile changes refresh the list and the open drawer while preserving selected period', async t => {
    const h = await ready(t, [row]); h.state.openDetail(row); h.state.openEditor(row)
    let refreshed = 0; h.state.detailRef.value = { refresh() { refreshed++ } }
    const pending = h.state.saved({ unit: row.unit, profile: { ...profile, revision: 5 } })
    assert.equal(h.state.editorOpen.value, false); assert.equal(refreshed, 1); assert.equal(h.requests.at(-1).body.params.date_to, '2026-10-13')
    h.requests.at(-1).resolve(dataset([row])); await pending
})

test('adding an absent profile uses explicit watched defaults and one stable idempotency key', async t => {
    const h = harness(t, 'SupplierProfileEditor', { unit: { id: 7, name: 'Выбранный Unit' }, profile: { exists: false, unit_id: 7, revision: 0, is_watched: false }, creating: true })
    assert.equal(h.state.form.is_watched, true); assert.equal(h.state.form.next_review_on, options.today); assert.equal(h.requests.length, 0)
    const pending = h.state.save(), request = h.requests.at(-1); h.state.save()
    assert.equal(h.requests.length, 1); assert.equal(request.url, '/api/suppliers'); assert.equal(request.body.unit_id, 7); assert.equal(request.body.name, undefined)
    assert.equal(request.body.is_watched, true); assert.equal(request.body.revision, 0)
    const key = request.body.create_key
    request.reject({ response: { status: 503, data: { message: 'Повторите позже' } } }); await pending
    const retry = h.state.save(); assert.equal(h.requests.at(-1).body.create_key, key)
    h.requests.at(-1).resolve({ unit: { id: 7, name: 'Выбранный Unit' }, profile: { ...profile, unit_id: 7, revision: 1 } }); await retry
    assert.equal(h.emitted.at(-1)[0], 'saved')
})

test('editing an existing profile preserves the draft on a revision conflict and guards closing', async t => {
    const h = harness(t, 'SupplierProfileEditor', { unit: row.unit, profile: structuredClone(profile) })
    h.state.form.notes = 'Новая договорённость'
    const pending = h.state.save(), request = h.requests.at(-1)
    assert.equal(request.method, 'patch'); assert.equal(request.url, '/api/suppliers/6/profile'); assert.equal(request.body.revision, 4)
    request.reject({ response: { status: 409, data: { message: 'Профиль изменён другим сотрудником' } } }); await pending
    assert.equal(h.state.form.notes, 'Новая договорённость'); assert.equal(h.state.form.revision, 4); assert.equal(h.state.dirty.value, true)
    h.state.requestClose(); assert.equal(h.state.confirmingClose.value, true); assert.ok(!h.emitted.some(([name]) => name === 'close'))
})

test('new supplier creation uses only the explicit name and keeps organization search read-only', async t => {
    const h = harness(t, 'SupplierProfileEditor', { creating: true })
    assert.equal(h.requests[0].url, '/api/supplier-order-units'); h.requests[0].resolve([{ id: 2, name: 'Существующий' }]); await flush()
    h.state.mode.value = 'new'; h.state.newName.value = '  Новый поставщик  '
    const pending = h.state.save(), request = h.requests.at(-1)
    assert.equal(request.body.name, 'Новый поставщик'); assert.equal(request.body.unit_id, undefined)
    request.resolve({ unit: { id: 8, name: 'Новый поставщик' }, profile: { ...profile, unit_id: 8, revision: 1 } }); await pending
    assert.equal(h.emitted.at(-1)[1].unit.id, 8)
})

test('an older picker response cannot replace a more recent search result', async t => {
    const h = harness(t, 'SupplierProfileEditor', { creating: true }), old = h.requests[0]
    const pending = h.state.loadUnits(), latest = h.requests.at(-1)
    assert.equal(old.body.signal.aborted, true)
    latest.resolve([{ id: 9, name: 'Найденный поставщик' }]); await pending
    old.resolve([{ id: 2, name: 'Старый результат' }]); await flush()
    assert.equal(h.state.units.value[0].id, 9)
    h.state.chooseUnit(h.state.units.value[0]); assert.equal(h.state.selectedUnit.value.id, 9)
})
