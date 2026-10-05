import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as tradeCodes from '../../resources/js/utils/goodTradeCodes.js'

function harness() {
    const filename = 'resources/js/Components/Goods/GoodTradeCodesRegistry.vue'
    const { descriptor, errors } = parse(readFileSync(new URL(`../../${filename}`, import.meta.url), 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'registry-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'registry-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const source = compiled.content.replace(/^import\s+[\s\S]*?\s+from\s+['"].*?['"];?$/gm, '').replace('export default', 'return')
    const requests = [], disposals = [], emitted = []
    const props = Vue.reactive({ codes: { hs_code: ' 0101.21 ', okpd2_code: '01.43.10.110', tn_ved_code: null, name: 'Private product description' }, active: true, disabled: false })
    const component = new Function('env', `with(env){${source}}`)({
        ...Vue, ...tradeCodes,
        onBeforeUnmount: callback => disposals.push(callback),
        axios: { post(url, body, options) {
            let resolve, reject
            const promise = new Promise((success, failure) => { resolve = success; reject = failure })
            requests.push({ url, body, options, resolve: data => resolve({ data }), reject })
            return promise
        } },
    })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    return { api, props, requests, emitted, dispose() { disposals.forEach(callback => callback()); scope.stop() } }
}

function response(request) {
    return { results: Object.entries(request.body.codes).map(([field, code]) => ({
        field, code, status: field === 'hs_code' ? 'found' : 'not_supported',
        title: field === 'hs_code' ? 'Horses; live, pure-bred breeding animals' : null,
        message: '', source_name: 'UN Comtrade', source_url: 'https://comtradeapi.un.org/files/v1/app/reference/H6.json',
        version: 'HS 2022', checked_at: '2026-10-06T00:00:00Z',
    })), scope: 'Наличие кода не подтверждает применимость к товару.' }
}

test('registry check is explicit, sends only nonempty known codes and never changes the Good', async t => {
    const h = harness(); t.after(h.dispose)
    assert.equal(h.requests.length, 0)
    const original = JSON.stringify(h.props.codes)
    const pending = h.api.verifyCodes()
    assert.equal(h.requests[0].url, '/api/goods/trade-codes/verify')
    assert.deepEqual(h.requests[0].body, { codes: { hs_code: '0101.21', okpd2_code: '01.43.10.110' } })
    await h.api.verifyCodes()
    assert.equal(h.requests.length, 1)
    h.requests[0].resolve(response(h.requests[0])); await pending
    assert.equal(h.api.rows.value.find(row => row.field === 'hs_code').presentation.label, 'Код найден')
    assert.equal(h.api.rows.value.find(row => row.field === 'okpd2_code').presentation.label, 'Ручная сверка')
    assert.equal(JSON.stringify(h.props.codes), original)
    assert.deepEqual(h.emitted, [])
})

for (const change of ['code', 'active', 'disabled', 'dispose']) {
    test(`registry results are discarded after ${change} changes even if cancellation arrives too late`, async t => {
        const h = harness(); t.after(h.dispose)
        const pending = h.api.verifyCodes()
        if (change === 'code') { h.props.codes.hs_code = '160420'; h.props.codes.hs_code = ' 0101.21 ' }
        else if (change === 'active') { h.props.active = false; h.props.active = true }
        else if (change === 'disabled') { h.props.disabled = true; h.props.disabled = false }
        else h.dispose()
        assert.equal(h.requests[0].options.signal.aborted, true)
        h.requests[0].resolve(response(h.requests[0])); await pending
        assert.equal(h.api.result.value, null)
        assert.equal(h.api.loading.value, false)
    })
}

test('old failures cannot overwrite a new registry request', async t => {
    const h = harness(); t.after(h.dispose)
    const old = h.api.verifyCodes()
    h.props.codes.hs_code = '160420'
    const current = h.api.verifyCodes()
    h.requests[0].reject(new Error('Old failure')); await old
    assert.equal(h.api.error.value, '')
    assert.equal(h.api.loading.value, true)
    h.requests[1].resolve(response(h.requests[1])); await current
    assert.equal(h.api.rows.value.find(row => row.field === 'hs_code').code, '160420')
})

test('empty or inactive forms do not make registry requests', async t => {
    const h = harness(); t.after(h.dispose)
    h.props.active = false
    await h.api.verifyCodes()
    h.props.active = true
    h.props.codes = { hs_code: ' ', name: 'Ignored' }
    assert.equal(h.api.hasCodes.value, false)
    await h.api.verifyCodes()
    assert.equal(h.requests.length, 0)
})

for (const scenario of ['missing', 'duplicate', 'unknown', 'status', 'empty title']) {
    test(`malformed ${scenario} registry responses do not render success`, async t => {
        const h = harness(); t.after(h.dispose)
        const pending = h.api.verifyCodes()
        const data = response(h.requests[0])
        if (scenario === 'missing') data.results.pop()
        if (scenario === 'duplicate') data.results[1] = data.results[0]
        if (scenario === 'unknown') data.results[0].field = 'name'
        if (scenario === 'status') data.results[0].status = 'verified'
        if (scenario === 'empty title') data.results.find(row => row.field === 'hs_code').title = ''
        h.requests[0].resolve(data); await pending
        assert.equal(h.api.result.value, null)
        assert.match(h.api.error.value, /результат сверки/)
    })
}

test('errors allow retry and unsafe links are not rendered', async t => {
    const h = harness(); t.after(h.dispose)
    const first = h.api.verifyCodes()
    h.requests[0].reject({ response: { data: { message: 'Слишком много запросов.' } } }); await first
    assert.equal(h.api.error.value, 'Слишком много запросов.')
    const retry = h.api.verifyCodes()
    const data = response(h.requests[1]); data.results[0].source_url = 'javascript:alert(1)'
    h.requests[1].resolve(data); await retry
    assert.equal(h.api.error.value, '')
    assert.equal(h.api.rows.value[0].sourceUrl, null)
})
