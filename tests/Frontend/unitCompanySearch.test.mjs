import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t) {
    const filename = 'resources/js/Components/Unit/UnitCompanySearchDialog.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'unit-company-search-test' })
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 'unit-company-search-test',
        compilerOptions: { bindingMetadata: compiled.bindings },
    })
    assert.deepEqual(template.errors, [])
    const requests = [], disposal = [], emitted = []
    const environment = {
        ...Vue,
        Link: {},
        route(name, id) {
            return {
                'web.units.company-search': '/web/units/company-search',
                'Ameise.entity.show': `/Ameise/entity/${id}`,
                'web.unit.show': `/Ameise/unit/${id}`,
            }[name]
        },
        onBeforeUnmount: callback => disposal.push(callback),
        axios: {
            post(url, payload, options) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, payload, options, resolve: data => resolve({ data }), reject })
                return promise
            },
            isCancel: exception => exception?.code === 'ERR_CANCELED',
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: true, industries: [] })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => { disposal.forEach(callback => callback()); scope.stop() })
    function validForm() {
        Object.assign(api.form, { query: '  молоко  ', okved: ['10.51'], status: ['ACTIVE'], type: null, region_code: '' })
    }
    return { api, props, requests, emitted, validForm, render: templateRenderer(template, api, props) }
}

test('search only runs on submission and sends codes rather than local industry IDs', async t => {
    const { api, requests, validForm } = harness(t)
    validForm()
    api.form.okved = ['10.51', '10.51', { code: '46.38', id: 99 }]
    api.form.region_code = '77'
    api.form.type = 'LEGAL'
    assert.equal(requests.length, 0)
    const pending = api.searchCompanies()
    assert.deepEqual(requests[0].payload, {
        query: 'молоко', okved: ['10.51', '46.38'], status: ['ACTIVE'], region_code: '77', type: 'LEGAL', count: 20,
    })
    assert.equal(requests[0].url, '/web/units/company-search')
    requests[0].resolve({ data: [], meta: { exhaustive: false } })
    await pending
    assert.equal(api.searched.value, true)
    assert.equal(api.loading.value, false)
})

test('invalid query, incomplete codes and invalid regions never call DaData', async t => {
    const { api, requests, validForm } = harness(t)
    await api.searchCompanies()
    assert.ok(api.fieldErrors('query').length)
    assert.ok(api.fieldErrors('okved').length)
    validForm()
    api.form.okved = ['10.5.1']
    api.form.region_code = '00'
    await api.searchCompanies()
    assert.ok(api.fieldErrors('okved').length)
    assert.ok(api.fieldErrors('region_code').length)
    api.form.okved = Array.from({ length: 11 }, (_, index) => String(10 + index))
    await api.searchCompanies()
    assert.ok(api.fieldErrors('okved').length)
    assert.equal(requests.length, 0)
})

test('a pending combobox code is included and empty status means all statuses', async t => {
    const { api, requests, validForm } = harness(t)
    validForm()
    api.form.okved = []
    api.codeSearch.value = '10.51.11'
    api.form.status = []
    const pending = api.searchCompanies()
    assert.deepEqual(requests[0].payload.okved, ['10.51.11'])
    assert.deepEqual(requests[0].payload.status, [])
    requests[0].resolve({ data: [] })
    await pending
})

test('editing criteria aborts a pending request and its late results are ignored', async t => {
    const { api, requests, validForm } = harness(t)
    validForm()
    const pending = api.searchCompanies()
    api.form.query = 'сыр'
    assert.equal(requests[0].options.signal.aborted, true)
    requests[0].resolve({ data: [{ entity: { INN: '0000000000' } }] })
    await pending
    assert.deepEqual(api.results.value, [])
    assert.equal(api.searched.value, false)
    assert.equal(api.loading.value, false)
})

test('closing cancels immediately and a late error cannot overwrite a reopened search', async t => {
    const { api, props, requests, emitted, validForm } = harness(t)
    validForm()
    const first = api.searchCompanies()
    api.updateDialog(false)
    assert.deepEqual(emitted, [['update:modelValue', false]])
    assert.equal(requests[0].options.signal.aborted, true)
    props.modelValue = false
    await Vue.nextTick()
    props.modelValue = true
    await Vue.nextTick()
    const second = api.searchCompanies()
    requests[0].reject({ response: { status: 503, data: { message: 'Old error' } } })
    await first
    assert.equal(api.error.value, '')
    assert.equal(api.loading.value, true)
    requests[1].resolve({ data: [] })
    await second
    assert.equal(api.loading.value, false)
})

test('server validation maps indexed codes to the field and unavailable service can be retried', async t => {
    const { api, requests, validForm } = harness(t)
    validForm()
    const first = api.searchCompanies()
    requests[0].reject({ response: { status: 422, data: { errors: { 'okved.0': ['Неверный код ОКВЭД.'] } } } })
    await first
    assert.deepEqual(api.fieldErrors('okved'), ['Неверный код ОКВЭД.'])
    const second = api.searchCompanies()
    requests[1].reject({ response: { status: 503, data: { message: 'Настройте ключ DaData.' } } })
    await second
    assert.equal(api.error.value, 'Настройте ключ DaData.')
    assert.equal(api.loading.value, false)
    const third = api.searchCompanies()
    requests[2].resolve({ data: [] })
    await third
    assert.equal(api.error.value, '')
    assert.equal(api.searched.value, true)
})

test('existing Entity and Unit links point to the saved company records', async t => {
    const { api, requests, validForm, render } = harness(t)
    validForm()
    const pending = api.searchCompanies()
    requests[0].resolve({ data: [{
        entity: { name: 'Молочный завод', INN: '7700000000', status: 'ACTIVE', okved: '10.51' },
        existing_entities: [{ id: 7, name: 'Молочный завод', units: [{ id: 9, name: 'Завод' }] }],
    }] })
    await pending
    const entityLink = findVNode(render(), node => node.type === api.Link && node.props.href === '/Ameise/entity/7')
    const unitLink = findVNode(render(), node => node.type === api.Link && node.props.href === '/Ameise/unit/9')
    assert.ok(entityLink)
    assert.ok(unitLink)
})
