import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { usePurchaseForm } from '../../resources/js/Composables/usePurchaseForm.js'

function harness(search, { fail = false } = {}) {
    const filename = 'resources/js/Pages/Purchases/Purchases.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'purchase-link' })
    let mount
    const reads = [], filters = [], writes = []
    const env = {
        ...Vue, onMounted: callback => { mount = callback }, onBeforeUnmount() {},
        route: name => name, window: { location: { search } }, usePurchaseForm,
        useEntityApi: () => ({}), useEntityForm: () => ({ form: Vue.reactive({}) }),
        usePurchases: () => ({
            resource: { signal: new AbortController().signal }, items: Vue.ref([]), loading: Vue.ref(false), pagination: Vue.ref({}),
            async fetchPurchases(params) { filters.push(params); return true },
            async fetchPurchase(id) {
                reads.push(id)
                if (fail) throw { response: { data: { message: 'Закупка не найдена' } } }
                return { id, date: '2026-10-10', entity: { id: 4 }, items: [{ good_id: 42, quantity: 5, measure_id: 3, price: 20, total: 100 }] }
            },
            createPurchase: value => writes.push(value), updatePurchase: value => writes.push(value), deletePurchase: value => writes.push(value),
        }),
        axios: { get: async url => ({ data: url === '/api/goods' ? [{ id: 42, measure_id: 3 }] : [] }), isCancel: () => false },
    }
    const script = compiled.content.replace(/^import (.+?) from ['"].*['"];?$/gm, (_, imports) => {
        const names = imports.startsWith('{') ? imports.slice(1, -1).split(',').map(name => name.trim()) : [imports]
        for (const name of names) if (!(name in env)) env[name] = {}
        return ''
    }).replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(env)
    const api = component.setup({}, { expose() {}, emit() {} })
    return { api, reads, filters, writes, mount: () => mount() }
}

test('a warehouse purchase link opens its saved document without writing', async () => {
    const h = harness('?purchase_id=81')
    await h.mount()
    assert.deepEqual(h.reads, [81])
    assert.equal(h.api.dialog.value, true)
    assert.equal(h.api.form.id, 81)
    assert.equal(h.api.form.items[0].measure_id, 3)
    assert.deepEqual(h.writes, [])
})

test('new purchase from a good preselects its configured unit and filters the journal', async () => {
    const h = harness('?purchase_good_id=42')
    await h.mount()
    assert.equal(h.api.dialog.value, true)
    assert.equal(h.api.form.id, null)
    assert.equal(h.api.form.items[0].good_id, 42)
    assert.equal(h.api.form.items[0].measure_id, 3)
    assert.deepEqual(h.filters[0].good_ids, [42])
    assert.deepEqual(h.writes, [])
})

test('missing purchase and invalid ids leave no accidental editable document', async () => {
    const h = harness('?purchase_id=404', { fail: true })
    await h.mount()
    assert.equal(h.api.dialog.value, false)
    assert.equal(h.api.errorMessage.value, 'Закупка не найдена')
    const invalid = harness('?purchase_id=-1&purchase_good_id=bogus')
    await invalid.mount()
    assert.equal(invalid.api.dialog.value, false)
    assert.deepEqual(invalid.reads, [])
})
