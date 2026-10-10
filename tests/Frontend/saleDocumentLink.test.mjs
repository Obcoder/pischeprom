import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

function harness(t, search = '') {
    const filename = 'resources/js/Pages/Ameise/Sales.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'sale-document-link' })
    assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename, id: 'sale-document-link', compilerOptions: { bindingMetadata: compiled.bindings } }).errors, [])
    const requests = [], writes = [], mounted = [], unmounted = []
    const environment = {
        ...Vue,
        VerwalterLayout: {},
        useDate: () => ({ format: value => value }),
        format: value => value,
        route: (name, id) => `${name}/${id ?? ''}`,
        saleRequestId: () => 'unique-request-id',
        buildingApartmentLabel: () => '', selectedBuildingApartments: () => ({}),
        ApartmentSelector: {}, ProspectingReviewPanel: {}, RealtimeStatus: {},
        onMounted: callback => mounted.push(callback),
        onBeforeUnmount: callback => unmounted.push(callback),
        window: { location: { search } },
        useRealtimeResource: options => ({ state: Vue.reactive(options.initialValue), signal: new AbortController().signal, refreshFailed: Vue.ref(false) }),
        useForm: initial => Vue.reactive({ ...initial, errors: {}, processing: false, post: (...args) => writes.push(args), reset() {} }),
        axios: { isCancel: () => false, get: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve: data => resolve({ data }), reject })) },
    }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup({}, { expose() {} }))
    t.after(() => { unmounted.forEach(callback => callback()); scope.stop() })
    return { api, requests, writes, mounted }
}
const flush = async () => { await Promise.resolve(); await Vue.nextTick(); await Promise.resolve() }

test('warehouse sale link loads and selects the document without opening or submitting an editing form', async t => {
    const h = harness(t, '?sale_id=42')
    h.mounted.forEach(callback => callback())
    for (const request of h.requests) request.resolve([])
    await flush()
    const linked = h.requests.at(-1)
    assert.equal(linked.url, 'sales.show/42')
    assert.equal(h.api.loadingSale.value, true)
    linked.resolve({ id: 42, entity: { name: 'Покупатель' }, goods: [{ id: 7 }], total: 100 }); await flush()
    assert.equal(h.api.sale.value.id, 42)
    assert.equal(h.api.selectedSaleId.value, 42)
    assert.equal(h.api.tab.value, 'sales')
    assert.equal(h.api.showFormAttachGood.value, false)
    assert.equal(h.api.formAttachGood.sale_id, null)
    assert.equal(h.api.loadingSale.value, false)
    assert.equal(h.writes.length, 0)
})

test('invalid or missing document IDs do not request a sale or perform writes', async t => {
    const h = harness(t)
    await h.api.openLinkedSale('')
    assert.equal(h.api.loadError.value, '')
    for (const id of ['', '0', '-1', '2.5', '1e2', '../../other', '9007199254740992']) {
        await h.api.openLinkedSale(`?sale_id=${encodeURIComponent(id)}`)
        assert.match(h.api.loadError.value, /Некорректный номер продажи/)
    }
    assert.equal(h.requests.length, 0)
    assert.equal(h.writes.length, 0)
})

test('missing linked documents clear selection and show a readable error', async t => {
    const h = harness(t)
    const opening = h.api.openLinkedSale('?sale_id=42')
    h.requests.at(-1).reject({ response: { status: 404 } }); await opening
    assert.equal(h.api.sale.value, null)
    assert.equal(h.api.selectedSaleId.value, null)
    assert.equal(h.api.showFormAttachGood.value, false)
    assert.equal(h.api.loadingSale.value, false)
    assert.match(h.api.loadError.value, /Продажа удалена/)
})

test('background list updates retain the selected document and stale requests cannot replace it', async t => {
    const h = harness(t)
    const first = h.api.openLinkedSale('?sale_id=42')
    const stale = h.requests.at(-1)
    const second = h.api.showSale(55)
    h.requests.at(-1).resolve({ id: 55, goods: [] }); await second
    stale.resolve({ id: 42, goods: [] }); await first
    assert.equal(h.api.sale.value.id, 55)
    const refresh = h.api.indexSales({ background: true })
    h.requests.at(-1).resolve([{ id: 55 }]); await flush()
    assert.equal(h.requests.at(-1).url, 'sales.show/55')
    h.requests.at(-1).resolve({ id: 55, goods: [{ id: 7 }] }); await refresh
    assert.equal(h.api.sale.value.goods[0].id, 7)
    assert.equal(h.writes.length, 0)
})
