import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as tradeCodes from '../../resources/js/utils/goodTradeCodes.js'
import { descendantIds } from '../../resources/js/Components/Catalog/tree.js'
import { goodRecordTabs } from '../../resources/js/Components/Catalog/recordTabs.js'

const root = new URL('../../', import.meta.url)

function componentSource(filename, environment) {
    const { descriptor, errors } = parse(readFileSync(new URL(filename, root), 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'good-vat-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'good-vat-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const source = compiled.content.replace(/^import\s+[\s\S]*?\s+from\s+['"].*?['"];?$/gm, '').replace('export default', 'return')
    return new Function('env', `with(env){${source}}`)(environment)
}

function harness() {
    const requests = []
    const availabilityRequests = []
    const emitted = []
    const disposals = []
    const scope = Vue.effectScope()
    const props = Vue.reactive({
        active: true,
        disabled: false,
        draft: { id: 42, name: 'Печень трески', description: 'Консервы', country_id: 1, products: [9, 3], vat_rate_id: 4, ...tradeCodes.goodTradeCodeValues(), tn_ved_code: '1604199700' },
        vatRates: [{ id: 4, rate: 22 }, { id: 5, rate: 10 }],
    })
    function defer(queue, url, body, options) {
        let resolve, reject
        const promise = new Promise((success, failure) => { resolve = success; reject = failure })
        queue.push({ url, body, options, resolve: data => resolve({ data }), reject })
        return promise
    }
    const environment = {
        ...Vue, ...tradeCodes,
        onBeforeUnmount: callback => disposals.push(callback),
        axios: {
            get: (url, options) => defer(availabilityRequests, url, null, options),
            post: (url, body, options) => defer(requests, url, body, options),
        },
    }
    const component = componentSource('resources/js/Components/Goods/GoodVatCheck.vue', environment)
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    return {
        api, props, requests, availabilityRequests, emitted,
        async ready(data = { available: true }) {
            availabilityRequests.at(-1).resolve(data)
            await Vue.nextTick()
        },
        dispose() {
            disposals.forEach(callback => callback())
            scope.stop()
        },
    }
}

function suggestion(extra = {}) {
    return {
        status: 'suggestion', rate: 10, vat_rate_id: 5,
        rationale: 'Нужно сверить код товара с применимым перечнем.',
        missing_information: [], sources: [{ title: 'ФНС', url: 'https://www.nalog.gov.ru/' }],
        ...extra,
    }
}

test('VAT check uses unsaved fields and applies a known rate only after the explicit action', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    h.props.draft.hs_code = '030111'
    h.api.operation.value = 'import'
    const pending = h.api.checkVat()
    assert.equal(h.requests[0].url, '/api/goods/vat-check')
    assert.deepEqual(h.requests[0].body, {
        good_id: 42, name: 'Печень трески', description: 'Консервы', country_id: 1,
        product_ids: [3, 9], vat_rate_id: 4, operation: 'import',
        ...tradeCodes.goodTradeCodeValues(), hs_code: '030111', tn_ved_code: '1604199700',
    })
    await h.api.checkVat()
    assert.equal(h.requests.length, 1, 'duplicate requests are blocked')
    h.requests[0].resolve(suggestion({ tn_ved_code: '1000000000' }))
    await pending
    assert.deepEqual(h.emitted, [])
    assert.equal(h.props.draft.vat_rate_id, 4)
    assert.equal(h.props.draft.tn_ved_code, '1604199700')
    h.api.applyRate()
    assert.deepEqual(h.emitted, [['apply', 5]])
})

for (const [field, change] of [
    ['name', 'Другая печень'], ['description', 'Другой состав'], ['country_id', 2],
    ['products', [17]], ['vat_rate_id', 5], ['tn_ved_code', '1604200000'],
]) {
    test(`changing ${field} cancels stale VAT results even when the original value is restored`, async t => {
        const h = harness()
        t.after(h.dispose)
        await h.ready()
        const pending = h.api.checkVat()
        const old = h.props.draft[field]
        h.props.draft[field] = change
        h.props.draft[field] = old
        assert.equal(h.requests[0].options.signal.aborted, true)
        const next = h.api.checkVat()
        h.requests[0].resolve(suggestion())
        await pending
        assert.equal(h.api.result.value, null)
        assert.equal(h.api.loading.value, true)
        h.requests[1].resolve(suggestion())
        await next
        assert.equal(h.api.result.value.rate, 10)
    })
}

test('operation changes, closing, saving and unmounting invalidate requests and results', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    let pending = h.api.checkVat()
    h.api.operation.value = 'export'
    h.requests[0].reject({ response: { data: { message: 'Старый ответ' } } })
    await pending
    assert.equal(h.api.error.value, '')
    pending = h.api.checkVat()
    h.props.active = false
    assert.equal(h.requests[1].options.signal.aborted, true)
    h.props.active = true
    h.requests[1].resolve(suggestion())
    await pending
    assert.equal(h.api.result.value, null)
    pending = h.api.checkVat()
    h.props.disabled = true
    h.requests[2].resolve(suggestion())
    await pending
    assert.equal(h.api.result.value, null)
    h.props.disabled = false
    pending = h.api.checkVat()
    h.dispose()
    assert.equal(h.requests[3].options.signal.aborted, true)
    h.requests[3].resolve(suggestion())
    await pending
    assert.deepEqual(h.emitted, [])
})

test('availability requests abort on close and retry on reopening without blocking the new request', async t => {
    const h = harness()
    t.after(h.dispose)
    h.props.active = false
    assert.equal(h.availabilityRequests[0].options.signal.aborted, true)
    h.props.active = true
    assert.equal(h.availabilityRequests.length, 2)
    h.availabilityRequests[0].resolve({ available: false, message: 'Old setting' })
    await Vue.nextTick()
    assert.equal(h.api.availability.value, null)
    assert.equal(h.api.checkingAvailability.value, true)
    await h.ready({ available: false, message: 'AI отключен' })
    await h.api.checkVat()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.availability.value.message, 'AI отключен')
})

test('unknown, mismatched and incomplete suggestions cannot emit a rate change', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    for (const result of [
        suggestion({ vat_rate_id: 99 }), suggestion({ rate: 22 }),
        suggestion({ rate: null }), suggestion({ status: 'needs_information' }),
    ]) {
        const pending = h.api.checkVat()
        h.requests.at(-1).resolve(result)
        await pending
        h.api.applyRate()
    }
    assert.deepEqual(h.emitted, [])
    const pending = h.api.checkVat()
    h.requests.at(-1).resolve(suggestion({ sources: [{ title: 'Unsafe', url: 'javascript:alert(1)' }, { title: 'ФНС', url: 'https://www.nalog.gov.ru/' }] }))
    await pending
    assert.deepEqual(h.api.sourceLinks.value, [{ title: 'ФНС', url: 'https://www.nalog.gov.ru/' }])
    h.props.draft.vat_rate_id = 5
    h.api.applyRate()
    assert.deepEqual(h.emitted, [], 'manual rate changes invalidate the displayed recommendation')
})

test('current errors preserve manual data and the check can be retried', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    const snapshot = JSON.stringify(h.props.draft)
    let pending = h.api.checkVat()
    h.requests[0].reject({ response: { data: { message: 'AI временно недоступен' } } })
    await pending
    assert.equal(h.api.error.value, 'AI временно недоступен')
    assert.equal(h.api.canCheck.value, true)
    assert.equal(JSON.stringify(h.props.draft), snapshot)
    pending = h.api.checkVat()
    h.requests[1].resolve({ status: 'suggestion' })
    await pending
    assert.match(h.api.error.value, /неполный ответ/)
    assert.equal(h.api.result.value, null)
})

test('trade code fields preserve leading zeroes, unrelated fields and explicit nullable values', () => {
    const props = Vue.reactive({ modelValue: { name: 'Товар', hs_code: '030111' }, errors: {}, disabled: false, readonly: false, context: null, active: true })
    const emitted = []
    const scope = Vue.effectScope()
    const component = componentSource('resources/js/Components/Goods/GoodTradeCodeFields.vue', { ...Vue, ...tradeCodes, GoodTradeCodesRecommend: {}, GoodTradeCodesRegistry: {} })
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    api.updateField('tn_ved_code', ' 0301110000 ')
    assert.deepEqual(emitted[0], ['update:modelValue', { name: 'Товар', hs_code: '030111', tn_ved_code: '0301110000' }])
    api.updateField('hs_code', '')
    assert.equal(emitted[1][1].hs_code, null)
    assert.equal(tradeCodes.goodTradeCodeValues({ hs_code: '030111' }).hs_code, '030111')
    assert.equal(Object.keys(tradeCodes.goodTradeCodeValues()).length, 11)
    scope.stop()
})

function goodCardHarness() {
    const requests = []
    const scope = Vue.effectScope()
    const good = {
        id: 42, name: 'Товар', vat_rate_id: 4, is_published: true,
        ava_image: 'https://cdn.example.com/old.webp', ava_thumb: 'https://cdn.example.com/old-small.webp',
        hs_code: '030111', tn_ved_code: '0301110000', products: [{ id: 9 }],
    }
    const node = { id: 7, entity_type: 'good', entity_id: 42, name: good.name, image: good.ava_image, is_published: true }
    const props = Vue.reactive({ modelValue: true, node, nodes: [], levels: [], initialParentId: null, initialLevelId: null, initialEntityType: 'custom' })
    const environment = {
        ...Vue, ...tradeCodes, descendantIds, goodRecordTabs, CatalogGoodOverview: {}, CatalogGoodSeo: {},
        CatalogGoodOperations: {}, CatalogRecordTabs: {}, CatalogLandingEditor: {}, GoodTradeCodeFields: {}, _mergeModels: Vue.mergeModels,
        _useModel: (source, key) => Vue.computed({ get: () => source[key], set: value => { source[key] = value } }),
        axios: {
            async patch(url, body) { requests.push({ url, body }); return { data: { data: node } } },
            async post(url, body) { requests.push({ url, body }); return { data: { data: node } } },
            async get(url) {
                return { data: url === '/api/goods'
                    ? { products: [], categories: [], fields: [], countries: [], vat_rates: [] }
                    : { data: good } }
            },
        },
    }
    const component = componentSource('resources/js/Components/Catalog/CatalogNodeDialog.vue', environment)
    const api = scope.run(() => component.setup(props, { expose() {}, emit() {} }))
    return { api, props, requests, ready: async () => { await Promise.resolve(); await Vue.nextTick() }, dispose: () => scope.stop() }
}

test('the catalog record saves changed nullable trade codes atomically while preserving unchanged codes and CDN avatars', async t => {
    const h = goodCardHarness()
    t.after(h.dispose)
    await h.ready()
    h.api.goodForm.hs_code = null
    h.api.goodForm.gtin = '00012345600012'
    await h.api.save()
    const body = h.requests[0].body
    assert.equal(h.requests[0].url, '/api/catalog/nodes/7')
    assert.deepEqual(body.good, { hs_code: null, gtin: '00012345600012' })
    assert.equal(h.api.goodForm.tn_ved_code, '0301110000')
    assert.equal(Object.hasOwn(body, 'image'), false)
})

test('changing the card CDN original clears the old thumbnail and saves only changed source fields', async t => {
    const h = goodCardHarness()
    t.after(h.dispose)
    await h.ready()
    h.api.updateGoodAvatar('https://cdn.example.com/new.webp')
    assert.equal(h.api.goodForm.avatar_thumb_source_url, '')
    await h.api.save()
    assert.equal(h.requests[0].body.good.avatar_source_url, 'https://cdn.example.com/new.webp')
    assert.equal(h.requests[0].body.good.avatar_thumb_source_url, null)
})

test('uploaded media and explicit avatar removal do not submit stale CDN URLs from the card form', async t => {
    const h = goodCardHarness()
    t.after(h.dispose)
    await h.ready()
    h.api.goodForm.avatar_source_url = 'https://cdn.example.com/new.webp'
    h.api.imageFile.value = new File(['image'], 'good.webp', { type: 'image/webp' })
    await h.api.save()
    assert.equal(Object.hasOwn(h.requests[0].body, 'good'), false)
    assert.equal(h.requests[1].url, '/api/catalog/nodes/7/image')
    assert.equal(h.requests[1].body.get('image').name, 'good.webp')
    h.props.modelValue = true
    await h.ready()
    h.api.removeGoodAvatar(true)
    h.api.goodForm.avatar_source_url = 'https://cdn.example.com/another.webp'
    await h.api.save()
    assert.deepEqual(h.requests[2].body.good, { remove_ava: true })
    assert.equal(Object.hasOwn(h.requests[2].body, 'image'), false)
})
