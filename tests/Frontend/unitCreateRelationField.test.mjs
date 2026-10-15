import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { contactKey } from '../../resources/js/Composables/unitCommunications.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, kind = 'entities', selected = []) {
    const filename = 'resources/js/Components/Unit/UnitCreateRelationField.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'unit-create-relation-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'unit-create-relation-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], disposal = [], emitted = [], timers = new Map()
    let timerId = 0
    const environment = {
        ...Vue, contactKey, VAutocomplete: {}, VCombobox: {},
        onBeforeUnmount: callback => disposal.push(callback),
        setTimeout(callback) { const id = ++timerId; timers.set(id, callback); return id },
        clearTimeout(id) { timers.delete(id) },
        axios: {
            get(url, options) {
                let resolve, reject
                const promise = new Promise((yes, no) => { resolve = yes; reject = no })
                requests.push({ url, options, resolve: data => resolve({ data }), reject })
                return promise
            },
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: selected, kind, label: kind, hint: '', errorMessages: [], disabled: false })
    const scope = Vue.effectScope()
    const exposed = {}
    const api = scope.run(() => component.setup(props, {
        expose: methods => Object.assign(exposed, methods),
        emit: (event, value) => { emitted.push([event, value]); if (event === 'update:modelValue') props.modelValue = value },
    }))
    const dispose = () => { disposal.splice(0).forEach(callback => callback()); scope.stop() }
    t.after(dispose)
    const flushTimers = () => { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()) }
    return { api, props, exposed, requests, emitted, dispose, flushTimers, render: templateRenderer(template, api, props) }
}

test('relation fields load lazily with each dictionary’s actual pagination contract', async t => {
    const cases = [
        ['entities', '/api/entities', { itemsPerPage: 30 }],
        ['emails', '/api/emails', { itemsPerPage: 30 }],
        ['cities', '/api/cities', { itemsPerPage: 30 }],
        ['telephones', '/api/telephones', { per_page: 30 }],
        ['industries', '/api/industries', { per_page: 30 }],
        ['fields', '/api/fields', { per_page: 30 }],
        ['buildings', '/api/buildings', { per_page: 30, structure: 1 }],
        ['classifications', '/api/entities-classification', null],
    ]
    for (const [kind, url, pagination] of cases) {
        const { api, requests } = harness(t, kind)
        assert.equal(requests.length, 0)
        api.focus(true)
        assert.equal(requests.length, 1)
        assert.equal(requests[0].url, url)
        assert.deepEqual(requests[0].options.params, pagination ? { search: undefined, page: 1, ...pagination } : {})
        requests[0].resolve([])
        await Vue.nextTick()
    }
})

test('later pages append results and keep previously selected complete records', async t => {
    const selection = { id: 99, name: 'Выбранная компания', INN: '1234567890', extra: { keep: true } }
    const { api, requests, props, render } = harness(t, 'entities', [selection])
    const first = api.loadOptions()
    requests[0].resolve({ data: [{ id: 1, name: 'Первая' }], meta: { current_page: 1, last_page: 2, total: 31, per_page: 30 } })
    await first
    assert.equal(api.hasMore.value, true)
    const control = findVNode(render(), node => node.type === api.VAutocomplete)
    const more = findVNode(control.children['append-item'](), node => node.type === 'v-btn')
    assert.ok(more)
    const next = api.loadMore()
    assert.equal(requests[1].options.params.page, 2)
    requests[1].resolve({ data: [{ id: 1, name: 'Первая' }, { id: 31, name: 'Следующая' }], meta: { last_page: 2 } })
    await next
    assert.deepEqual(api.items.value.map(item => item.id), [99, 1, 31])
    assert.deepEqual(props.modelValue[0].extra, { keep: true })
    assert.equal(api.hasMore.value, false)
    const query = api.loadOptions(1)
    requests[2].resolve({ data: [{ id: 4, name: 'Другой результат' }], meta: { last_page: 1 } })
    await query
    assert.deepEqual(api.items.value.map(item => item.id), [99, 4])
})

test('city response totals expose more choices beyond the first page', async t => {
    const { api, requests } = harness(t, 'cities')
    const pending = api.loadOptions()
    requests[0].resolve({ data: [{ id: 5, name: 'Город', region: 'Область', label: 'Город, Область' }], total: 50, page: 1, itemsPerPage: 30 })
    await pending
    assert.equal(api.hasMore.value, true)
    assert.equal(api.title(api.options.value[0]), 'Город, Область')
    const next = api.loadMore()
    requests[1].resolve({ data: [{ id: 6, name: 'Второй город' }], total: 50, page: 2, itemsPerPage: 30 })
    await next
    assert.equal(api.hasMore.value, false)
})

test('remote search is debounced, aborts old requests and ignores their late results', async t => {
    const { api, requests, flushTimers } = harness(t)
    api.focus(true)
    api.changeSearch('мол')
    assert.equal(requests[0].options.signal.aborted, true)
    api.changeSearch('молоко')
    assert.equal(requests.length, 1)
    flushTimers()
    assert.equal(requests.length, 2)
    assert.equal(requests[1].options.params.search, 'молоко')
    requests[1].resolve({ data: [{ id: 4, name: 'Найдено' }], meta: { last_page: 1 } })
    await Vue.nextTick()
    requests[0].resolve({ data: [{ id: 1, name: 'Устарело' }], meta: { last_page: 3 } })
    await Vue.nextTick()
    assert.deepEqual(api.options.value.map(item => item.id), [4])
    assert.equal(api.hasMore.value, false)
})

test('typed contacts are flushed on submission, normalized for deduplication and matched to known records', async t => {
    const { api, props, exposed, requests, render } = harness(t, 'emails', [{ id: 8, address: 'one@example.test' }])
    const pending = api.loadOptions()
    requests[0].resolve({ data: [{ id: 9, address: 'two@example.test', name: 'Отдел закупок' }], last_page: 1 })
    await pending
    api.changeSearch('  TWO@example.test  ')
    assert.equal(exposed.flushPending()[1].id, 9)
    assert.equal(props.modelValue[1].name, 'Отдел закупок')
    api.changeSearch('one@EXAMPLE.test')
    exposed.flushPending()
    assert.equal(props.modelValue.length, 2)
    api.changeSearch('new@example.test')
    const submitted = exposed.flushPending()
    assert.deepEqual(submitted.map(item => typeof item === 'string' ? item : item.id), [8, 9, 'new@example.test'])
    assert.equal(api.search.value, '')
    const control = findVNode(render(), node => node.type === api.VCombobox)
    assert.ok(control)
    control.props['onUpdate:modelValue']([])
    assert.deepEqual(props.modelValue, [])
})

test('phone and website variants do not become repeated contacts, and dictionaries never create strings', t => {
    const phone = harness(t, 'telephones', [{ id: 1, number: '79990000000' }])
    phone.api.changeSearch('8 (999) 000-00-00')
    assert.equal(phone.exposed.flushPending().length, 1)
    const site = harness(t, 'uris', [{ id: 2, address: 'https://example.test/' }])
    site.api.changeSearch('www.example.test')
    assert.equal(site.exposed.flushPending().length, 1)
    const fields = harness(t, 'fields')
    fields.api.changeSearch('Несуществующее направление')
    assert.deepEqual(fields.exposed.flushPending(), [])
    fields.api.select(['Несуществующее направление'])
    assert.deepEqual(fields.props.modelValue, [])
})

test('invalid nonempty phone input is retained for validation and never reused as a valid contact', async t => {
    const { api, props, requests, exposed } = harness(t, 'telephones')
    const pending = api.loadOptions()
    requests[0].resolve({ data: [{ id: 7, number: '+79991234567' }], last_page: 1 })
    await pending
    api.changeSearch('  abc  ')
    assert.deepEqual(exposed.flushPending(), ['abc'])
    api.changeSearch('abc79991234567')
    assert.deepEqual(exposed.flushPending(), ['abc', 'abc79991234567'])
    assert.equal(props.modelValue.some(item => typeof item === 'object'), false)
    api.select(['abc', 'abc79991234567', 'abc'])
    assert.deepEqual(props.modelValue, ['abc', 'abc79991234567'])
    api.changeSearch('+7 (999) 123-45-67')
    assert.deepEqual(exposed.flushPending().map(item => typeof item === 'string' ? item : item.id), ['abc', 'abc79991234567', 7])
    api.changeSearch('---')
    assert.equal(exposed.flushPending().at(-1), '---')
})

test('local classifications search in the fetched dictionary without another server request', async t => {
    const { api, requests, flushTimers } = harness(t, 'classifications')
    const pending = api.loadOptions()
    requests[0].resolve([{ id: 1, name: 'Производитель' }, { id: 2, name: 'Розница' }])
    await pending
    api.changeSearch('РОЗ')
    flushTimers()
    assert.deepEqual(api.items.value.map(item => item.id), [2])
    assert.equal(requests.length, 1)
})

test('failed next page keeps selection and results, exposes retry and retries that page', async t => {
    const { api, requests, props, render } = harness(t, 'entities', [{ id: 8, name: 'Связь' }])
    const first = api.loadOptions()
    requests[0].resolve({ data: [{ id: 1, name: 'Первая' }], meta: { last_page: 2 } })
    await first
    const next = api.loadMore()
    requests[1].reject({ response: { data: { message: 'Временно недоступно.' } } })
    await next
    assert.equal(api.loadError.value, 'Временно недоступно.')
    assert.ok(findVNode(render(), node => node.props?.role === 'alert'))
    assert.deepEqual(props.modelValue.map(item => item.id), [8])
    assert.deepEqual(api.options.value.map(item => item.id), [1])
    const retry = api.retry()
    assert.equal(requests[2].options.params.page, 2)
    requests[2].resolve({ data: [{ id: 31, name: 'Следующая' }], meta: { last_page: 2 } })
    await retry
    assert.equal(api.loadError.value, '')
    assert.deepEqual(api.options.value.map(item => item.id), [1, 31])
})

test('disabling or unmounting cancels work and prevents stale results from changing the field', async t => {
    const { api, props, requests, dispose, flushTimers } = harness(t)
    api.focus(true)
    props.disabled = true
    await Vue.nextTick()
    assert.equal(requests[0].options.signal.aborted, true)
    requests[0].resolve({ data: [{ id: 1, name: 'Устарело' }] })
    await Vue.nextTick()
    assert.deepEqual(api.options.value, [])
    await api.loadOptions()
    assert.equal(requests.length, 1)
    props.disabled = false
    await Vue.nextTick()
    api.changeSearch('запрос')
    dispose()
    flushTimers()
    assert.equal(requests.length, 1)
})
