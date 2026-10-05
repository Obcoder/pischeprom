import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as tradeCodes from '../../resources/js/utils/goodTradeCodes.js'

const root = new URL('../../', import.meta.url)

function componentSource(filename, environment) {
    const { descriptor, errors } = parse(readFileSync(new URL(filename, root), 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'good-code-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'good-code-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const source = compiled.content.replace(/^import\s+[\s\S]*?\s+from\s+['"].*?['"];?$/gm, '').replace('export default', 'return')
    return new Function('env', `with(env){${source}}`)(environment)
}

function harness({ onApply } = {}) {
    const requests = []
    const availabilityRequests = []
    const emitted = []
    const disposals = []
    const scope = Vue.effectScope()
    const props = Vue.reactive({
        active: true,
        disabled: false,
        draft: { id: 42, name: '  Печень трески  ', description: 'Консервы', country_id: 1, products: [{ id: 9 }, { id: 3 }], ...tradeCodes.goodTradeCodeValues(), tn_ved_code: '1604199700' },
    })
    function defer(queue, url, body, options) {
        let resolve, reject
        const promise = new Promise((success, failure) => { resolve = success; reject = failure })
        queue.push({ url, body, options, resolve: data => resolve({ data }), reject })
        return promise
    }
    const component = componentSource('resources/js/Components/Goods/GoodTradeCodesRecommend.vue', {
        ...Vue, ...tradeCodes,
        onBeforeUnmount: callback => disposals.push(callback),
        axios: {
            get: (url, options) => defer(availabilityRequests, url, null, options),
            post: (url, body, options) => defer(requests, url, body, options),
        },
    })
    const api = scope.run(() => component.setup(props, { expose() {}, emit(...event) {
        emitted.push(event)
        if (event[0] === 'apply') onApply?.(event[1], props.draft)
    } }))
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

function recommendation(field, overrides = {}) {
    const values = { hs_code: '160420', tn_ved_code: '1604200000', okpd2_code: '10.20.25.110', cn_code: '16042090' }
    return {
        field, value: values[field] || '12345678', status: 'suggestion', rationale: 'Проверьте состав и описание продукции.',
        missing_information: [], sources: [{ id: 'reference', title: 'Классификатор', url: 'https://example.com/classifier' }],
        ...overrides,
    }
}

function response(request, overrides = {}) {
    return { recommendations: request.body.requested_fields.map(field => recommendation(field)), advisory: true, scope: 'Предварительная классификация.', checked_at: '2026-10-06', ...overrides }
}

test('recommendations use unsaved draft data and one explicit action applies the entire selected patch', async t => {
    const h = harness({ onApply: (patch, draft) => Object.assign(draft, patch) })
    t.after(h.dispose)
    await h.ready()
    const pending = h.api.recommendCodes()
    assert.equal(h.requests[0].url, '/api/goods/trade-codes/recommend')
    assert.deepEqual(h.requests[0].body, {
        good_id: 42, name: 'Печень трески', description: 'Консервы', country_id: 1, product_ids: [3, 9],
        requested_fields: ['tn_ved_code', 'okpd2_code', 'hs_code'],
        ...tradeCodes.goodTradeCodeValues(), tn_ved_code: '1604199700',
    })
    await h.api.recommendCodes()
    assert.equal(h.requests.length, 1, 'duplicate requests are blocked')
    h.requests[0].resolve(response(h.requests[0]))
    await pending
    assert.deepEqual(h.emitted, [])
    assert.deepEqual(h.api.selectedFields.value, [], 'replacements require deliberate selection')
    assert.equal(h.props.draft.tn_ved_code, '1604199700')
    assert.equal(h.api.recommendations.value[0].current, '1604199700')
    h.api.applySelected()
    assert.deepEqual(h.emitted, [])
    h.api.selectedFields.value = ['tn_ved_code', 'hs_code']
    h.api.applySelected()
    assert.deepEqual(h.emitted, [['apply', { tn_ved_code: '1604200000', hs_code: '160420' }]])
    assert.equal(h.props.draft.tn_ved_code, '1604200000')
    assert.equal(h.props.draft.hs_code, '160420')
    assert.equal(h.props.draft.okpd2_code, null, 'unchecked suggestions stay unchanged')
    assert.equal(h.api.result.value, null)
    assert.equal(h.requests.length, 1, 'applying recommendations never saves the Good')
})

test('all eleven classifiers can be requested but GTIN suggestions and unrequested fields cannot be applied', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    h.api.requestedFields.value = [...tradeCodes.goodTradeCodeFields.map(field => field.key), 'name']
    const pending = h.api.recommendCodes()
    assert.equal(h.requests[0].body.requested_fields.length, 11)
    assert.equal(h.requests[0].body.requested_fields.includes('name'), false)
    h.requests[0].resolve(response(h.requests[0]))
    await pending
    h.api.selectedFields.value = ['gtin', 'name', 'cn_code']
    h.api.applySelected()
    assert.deepEqual(h.emitted, [['apply', { cn_code: '16042090' }]])
})

test('uncertain, inapplicable and already stored codes cannot be applied', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    const pending = h.api.recommendCodes()
    h.requests[0].resolve(response(h.requests[0], { recommendations: [
        recommendation('tn_ved_code', { value: h.props.draft.tn_ved_code }),
        recommendation('hs_code', { status: 'needs_information', value: '160420', missing_information: ['Уточните состав'] }),
        recommendation('okpd2_code', { status: 'not_applicable', value: null }),
    ] }))
    await pending
    h.api.selectedFields.value = ['tn_ved_code', 'hs_code', 'okpd2_code']
    assert.equal(h.api.selectedCount.value, 0)
    h.api.applySelected()
    assert.deepEqual(h.emitted, [])
})

for (const [field, value] of [
    ['id', 45], ['name', 'Другая продукция'], ['description', 'Другой состав'],
    ['country_id', 2], ['products', [17]], ['tn_ved_code', '1604200000'],
]) {
    test(`editing ${field} invalidates even a response arriving after the original value is restored`, async t => {
        const h = harness()
        t.after(h.dispose)
        await h.ready()
        const stale = h.api.recommendCodes()
        const original = h.props.draft[field]
        h.props.draft[field] = value
        h.props.draft[field] = original
        assert.equal(h.requests[0].options.signal.aborted, true)
        const current = h.api.recommendCodes()
        h.requests[0].resolve(response(h.requests[0]))
        await stale
        assert.equal(h.api.result.value, null)
        assert.equal(h.api.loading.value, true)
        h.requests[1].resolve(response(h.requests[1]))
        await current
        assert.equal(h.api.result.value.recommendations.length, 3)
    })
}

test('editing the requested classifiers cancels the old request and discards a previous result', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    const stale = h.api.recommendCodes()
    h.api.requestedFields.value = ['hs_code']
    h.requests[0].reject({ response: { data: { message: 'Old failure' } } })
    await stale
    assert.equal(h.api.error.value, '')
    const current = h.api.recommendCodes()
    assert.deepEqual(h.requests[1].body.requested_fields, ['hs_code'])
    h.requests[1].resolve(response(h.requests[1]))
    await current
    h.api.selectedFields.value = ['hs_code']
    h.api.requestedFields.value = ['cn_code']
    h.api.applySelected()
    assert.equal(h.api.result.value, null)
    assert.deepEqual(h.emitted, [])
})

test('manual edits after a completed recommendation prevent applying stale replacements', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    const pending = h.api.recommendCodes()
    h.requests[0].resolve(response(h.requests[0]))
    await pending
    h.api.selectedFields.value = ['tn_ved_code', 'hs_code']
    h.props.draft.tn_ved_code = '1604199800'
    h.api.applySelected()
    assert.equal(h.props.draft.tn_ved_code, '1604199800')
    assert.equal(h.props.draft.hs_code, null)
    assert.deepEqual(h.emitted, [])
})

test('closing, saving and unmounting cancel requests and prevent late results from changing state', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    let pending = h.api.recommendCodes()
    h.props.active = false
    assert.equal(h.requests[0].options.signal.aborted, true)
    h.props.active = true
    h.requests[0].resolve(response(h.requests[0]))
    await pending
    assert.equal(h.api.result.value, null)
    pending = h.api.recommendCodes()
    h.props.disabled = true
    assert.equal(h.requests[1].options.signal.aborted, true)
    h.requests[1].resolve(response(h.requests[1]))
    await pending
    assert.equal(h.api.result.value, null)
    h.props.disabled = false
    pending = h.api.recommendCodes()
    h.dispose()
    assert.equal(h.requests[2].options.signal.aborted, true)
    h.requests[2].resolve(response(h.requests[2]))
    await pending
    assert.deepEqual(h.emitted, [])
    assert.equal(h.api.result.value, null)
})

test('availability checks restart after closing, and stale availability cannot disable a reopened form', async t => {
    const h = harness()
    t.after(h.dispose)
    assert.equal(h.availabilityRequests[0].url, '/api/goods/trade-codes/availability')
    h.props.active = false
    h.props.active = true
    assert.equal(h.availabilityRequests[0].options.signal.aborted, true)
    h.availabilityRequests[0].resolve({ available: false })
    await Vue.nextTick()
    assert.equal(h.api.availability.value, null)
    assert.equal(h.api.checkingAvailability.value, true)
    await h.ready({ available: false, message: 'AI не настроен' })
    await h.api.recommendCodes()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.availability.value.message, 'AI не настроен')
})

test('empty names, overly long names, closed forms and empty classifier selections do not call AI', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    for (const name of ['', '   ', 'я'.repeat(256)]) {
        h.props.draft.name = name
        await h.api.recommendCodes()
    }
    h.props.draft.name = 'Товар'
    h.api.requestedFields.value = []
    await h.api.recommendCodes()
    h.api.requestedFields.value = ['hs_code']
    h.props.active = false
    await h.api.recommendCodes()
    assert.equal(h.requests.length, 0)
})

test('incomplete, duplicate, extra and malformed AI responses cannot partially populate the form', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    const mutations = [
        data => data.recommendations.pop(),
        data => { data.recommendations[1] = data.recommendations[0] },
        data => { data.recommendations[1].field = 'name' },
        data => { data.recommendations[1].value = null },
        data => { data.recommendations[1].status = 'approved' },
        data => { data.recommendations[1].rationale = null },
        data => { data.recommendations[1].missing_information = 'Not an array' },
    ]
    for (const mutate of mutations) {
        const pending = h.api.recommendCodes()
        const request = h.requests.at(-1)
        const data = response(request)
        mutate(data)
        request.resolve(data)
        await pending
        assert.equal(h.api.result.value, null)
        assert.match(h.api.error.value, /неполный ответ/)
        h.api.applySelected()
    }
    assert.deepEqual(h.emitted, [])
    assert.equal(h.props.draft.tn_ved_code, '1604199700')
})

test('API failures allow retry, keep manual codes and do not expose unsafe source links', async t => {
    const h = harness()
    t.after(h.dispose)
    await h.ready()
    const initial = JSON.stringify(h.props.draft)
    let pending = h.api.recommendCodes()
    h.requests[0].reject({ response: { data: { message: 'AI временно недоступен' } } })
    await pending
    assert.equal(h.api.error.value, 'AI временно недоступен')
    assert.equal(h.api.canRecommend.value, true)
    assert.equal(JSON.stringify(h.props.draft), initial)
    pending = h.api.recommendCodes()
    const data = response(h.requests[1])
    data.recommendations[0].sources = [{ title: 'Unsafe', url: 'javascript:alert(1)' }, { title: 'Reference', url: 'https://example.com/' }]
    h.requests[1].resolve(data)
    await pending
    assert.equal(h.api.error.value, '')
    assert.deepEqual(h.api.recommendations.value[0].sources, [{ title: 'Reference', url: 'https://example.com/' }])
})

test('the shared code editor applies a single filtered patch and uses current codes with Good card context', () => {
    const props = Vue.reactive({
        modelValue: { name: 'Товар', tn_ved_code: '0301110000', cn_code: null },
        context: { id: 42, name: 'Товар', product_ids: [7], tn_ved_code: 'Stale context' },
        active: true, disabled: false, readonly: false, errors: {},
    })
    const emitted = []
    const scope = Vue.effectScope()
    const component = componentSource('resources/js/Components/Goods/GoodTradeCodeFields.vue', { ...Vue, ...tradeCodes, GoodTradeCodesRecommend: {} })
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    assert.equal(api.recommendationDraft.value.id, 42)
    assert.deepEqual(api.recommendationDraft.value.product_ids, [7])
    assert.equal(api.recommendationDraft.value.tn_ved_code, '0301110000')
    api.applyRecommendations({ hs_code: '030111', cn_code: '03011100', name: 'Unexpected rename' })
    assert.deepEqual(emitted, [['update:modelValue', { name: 'Товар', tn_ved_code: '0301110000', hs_code: '030111', cn_code: '03011100' }]])
    assert.equal(api.expanded.value, true, 'applied optional codes become visible')
    props.active = false
    api.applyRecommendations({ hs_code: '030222' })
    props.active = true
    props.disabled = true
    api.applyRecommendations({ hs_code: '030222' })
    assert.equal(emitted.length, 1)
    scope.stop()
})
