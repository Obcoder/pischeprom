import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const flush = () => new Promise(resolve => setImmediate(resolve))
const row = (overrides = {}) => ({ id: 1, number: 'СО-001', unit_id: 42, unit: { id: 42, name: 'Поставщик' }, status: 'draft', revision: 3, items_count: 2, created_at: '2026-10-13T10:00:00Z', ...overrides })
const page = (data = [row()], overrides = {}) => ({ data, current_page: 1, last_page: 1, total: data.length, per_page: 25, ...overrides })

function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/SupplierOrders/SupplierOrdersRegister.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename }); assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'supplier-register' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'supplier-register', compilerOptions: { bindingMetadata: script.bindings } }); assert.deepEqual(template.errors, [])
    const requests = [], props = Vue.reactive({ unitId: null, unitName: '', unitFiles: [], ...overrides })
    const environment = { ...Vue, Link: 'Link', SupplierOrderComposer: 'SupplierOrderComposer', axios: Object.fromEntries(['get', 'post', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])) }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment), scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit() {} }))
    t.after(() => scope.stop())
    return { api, props, requests, scope, render: templateRenderer(template, api, props) }
}

test('registry discards superseded unit, filter, page and search responses', async t => {
    const h = harness(t, { unitId: 42 }); const oldUnit = h.requests[0]
    assert.equal(oldUnit.body.params.unit_id, 42)
    h.props.unitId = 55; await Vue.nextTick(); const current = h.requests.at(-1)
    assert.equal(oldUnit.body.signal.aborted, true)
    current.resolve(page([row({ id: 55, unit_id: 55 })])); await flush()
    oldUnit.resolve(page([row({ id: 42 })])); await flush()
    assert.equal(h.api.rows.value[0].id, 55)
    h.api.status.value = 'sent'; await Vue.nextTick(); const filterRequest = h.requests.at(-1)
    const nextPage = h.api.load(2), latest = h.requests.at(-1)
    assert.equal(latest.body.params.status, 'sent'); assert.equal(latest.body.params.page, 2)
    latest.resolve(page([row({ id: 99 })], { current_page: 2, last_page: 2, total: 26 })); await nextPage
    filterRequest.resolve(page([row({ id: 7 })])); await flush()
    assert.equal(h.api.rows.value[0].id, 99); assert.equal(h.api.meta.current_page, 2)
    const pending = h.api.load(); const obsolete = h.requests.at(-1)
    h.api.search.value = 'Морковь'; await Vue.nextTick()
    obsolete.resolve(page([row({ id: 404 })])); await pending
    assert.equal(h.api.rows.value[0].id, 99)
    const search = h.api.load(1); const request = h.requests.at(-1)
    assert.equal(request.body.params.search, 'Морковь')
    request.resolve(page([])); await search
    assert.deepEqual(h.api.rows.value, [])
})

test('global creation chooses a supplier and delegates close guarding to the persistent composer', async t => {
    const h = harness(t); h.requests[0].resolve(page()); await flush()
    h.api.create(); const oldOptions = h.requests.at(-1)
    assert.equal(oldOptions.url, '/api/supplier-order-units'); assert.equal(h.api.composerOpen.value, false)
    h.api.supplierSearch.value = 'Новый'; const search = h.api.loadUnits(), selected = h.requests.at(-1)
    selected.resolve([{ id: 8, name: 'Новый поставщик' }]); await search
    oldOptions.resolve([{ id: 9, name: 'Старые результаты' }]); await flush()
    assert.equal(h.api.supplierOptions.value[0].id, 8)
    h.api.chooseUnit(h.api.supplierOptions.value[0]); await Vue.nextTick()
    assert.equal(h.api.pickerOpen.value, false); assert.equal(h.api.composerOpen.value, true)
    const tree = h.render(), dialog = findVNode(tree, node => node.type === 'v-dialog' && node.props.persistent !== undefined)
    assert.equal(dialog.props.width, '96vw'); assert.equal(dialog.props['max-width'], '1800')
    const composer = findVNode(tree, node => node.type === 'SupplierOrderComposer')
    assert.equal(composer.props['unit-id'], 8); assert.equal(composer.props['order-id'], null)
    const key = h.api.composerKey.value
    composer.props.onSaved({ id: 19, number: 'СО-019' }); h.requests.at(-1).resolve(page([row({ id: 19 })])); await flush()
    assert.equal(h.api.composerOpen.value, true); assert.equal(h.api.composerKey.value, key, 'Saving must not remount a composer with its own draft/attachment state')
    composer.props.onClose(); assert.equal(h.api.composerOpen.value, false)
})

test('unit-scoped creation skips supplier lookup and preserves modal context across a unit change', async t => {
    const files = [{ path: 'units/42/a.pdf' }]
    const h = harness(t, { unitId: 42, unitName: 'Сорок два', unitFiles: files }); h.requests[0].resolve(page()); await flush()
    h.api.create()
    assert.equal(h.requests.length, 1)
    assert.equal(h.api.selectedDocument.value.unit_id, 42)
    h.props.unitId = 55; h.props.unitFiles = [{ path: 'units/55/b.pdf' }]; await Vue.nextTick()
    assert.equal(h.api.selectedDocument.value.unit_id, 42)
    assert.deepEqual(h.api.selectedDocument.value.unit_files, files)
    assert.equal(h.api.composerOpen.value, true)
    h.requests.at(-1).resolve(page([])); await flush()
})

test('destructive actions need inline confirmation, use revisions and preserve conflicts for correction', async t => {
    const h = harness(t); h.requests[0].resolve(page()); await flush()
    h.api.ask(h.api.rows.value[0], 'delete')
    assert.equal(h.requests.length, 1, 'Opening confirmation must not delete')
    const deletion = h.api.mutate(h.api.confirmation.value, 'delete'), request = h.requests.at(-1)
    assert.equal(request.method, 'delete'); assert.deepEqual(request.body.data, { revision: 3 })
    await h.api.mutate(h.api.confirmation.value, 'delete'); assert.equal(h.requests.length, 2, 'A repeated click cannot dispatch another mutation')
    request.reject({ response: { status: 409, data: { message: 'Заказ уже изменён' } } }); await deletion
    assert.equal(h.api.confirmation.value.id, 1); assert.match(h.api.operationError.value, /уже изменён/)
    const refresh = h.api.refresh(); h.requests.at(-1).resolve(page([row({ revision: 4 })])); await refresh
    assert.equal(h.api.confirmation.value, null); assert.match(h.api.operationError.value, /уже изменён/)
    h.api.ask(h.api.rows.value[0], 'cancelled'); const cancellation = h.api.mutate(h.api.confirmation.value, 'cancelled')
    assert.equal(h.requests.at(-1).url, '/api/supplier-orders/1/status')
    assert.deepEqual(h.requests.at(-1).body, { status: 'cancelled', revision: 4 })
    h.requests.at(-1).resolve(row({ status: 'cancelled', revision: 5 })); await flush()
    h.requests.at(-1).resolve(page([row({ status: 'cancelled', revision: 5 })])); await cancellation
    assert.equal(h.api.confirmation.value, null); assert.equal(h.api.busyId.value, null); assert.equal(h.api.operationError.value, '')
})

test('filter picker scopes registry and empty last pages return to a valid page', async t => {
    const h = harness(t); h.requests[0].resolve(page()); await flush()
    h.api.openPicker('filter'); h.requests.at(-1).resolve([{ id: 77, name: 'Поставщик 77' }]); await flush()
    h.api.chooseUnit({ id: 77, name: 'Поставщик 77' }); await Vue.nextTick()
    assert.equal(h.api.composerOpen.value, false); assert.equal(h.requests.at(-1).body.params.unit_id, 77)
    h.requests.at(-1).resolve(page([])); await flush()
    const load = h.api.load(2); h.requests.at(-1).resolve(page([], { current_page: 2, last_page: 1, total: 25 })); await flush()
    assert.equal(h.requests.at(-1).body.params.page, 1)
    h.requests.at(-1).resolve(page([row()])); await load
    assert.equal(h.api.meta.current_page, 1)
    const pending = h.api.load(), request = h.requests.at(-1)
    h.scope.stop(); assert.equal(request.body.signal.aborted, true)
    request.reject({ response: { data: { message: 'Late failure' } } }); await pending
    assert.equal(h.api.loadError.value, '')
})
