import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as productLanguages from '../../resources/js/Pages/Helpers/productLanguages.js'

function deferred() {
    let resolve, reject
    const promise = new Promise((success, failure) => { resolve = success; reject = failure })
    return { promise, resolve, reject }
}

// Exercise the real form with reactive edits and HTTP responses arriving later.
function productFormHarness() {
    const requests = []
    const disposal = []
    const scope = Vue.effectScope()
    const environment = {
        ...Vue,
        ...productLanguages,
        onMounted: () => {},
        onBeforeUnmount: callback => disposal.push(callback),
        Link: {},
        route: () => '',
        axios: {
            post(url, body, options = {}) {
                const request = deferred()
                requests.push({ url, body, options, resolve: data => request.resolve({ data }), reject: request.reject })
                return request.promise
            },
        },
    }
    const filename = fileURLToPath(new URL('../../resources/js/Components/Dictionaries/Products.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'products-ai' })
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 'products-ai',
        compilerOptions: { bindingMetadata: compiled.bindings },
    })
    assert.deepEqual(template.errors, [])
    const script = compiled.content
        .replace(/^import\s+[\s\S]*?\s+from\s+['"].*?['"];?$/gm, '')
        .replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const api = scope.run(() => component.setup({}, { expose: () => {}, emit: () => {} }))
    api.openCreate()
    api.form.rus = 'Пшеница'
    return {
        api,
        requests,
        dispose() {
            disposal.forEach(callback => callback())
            scope.stop()
        },
    }
}

function translationResponse(request, extra = {}) {
    return { translations: Object.fromEntries(request.body.languages.map(key => [key, ` ${key} translation `])), ...extra }
}

test('one AI request fills all missing languages and leaves existing names editable until normal saving', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    api.form.rus = '  Пшеница  '
    api.form.category_id = 7
    api.form.eng = 'Existing wheat'
    const pending = api.translateProduct()
    assert.equal(requests.length, 1)
    assert.equal(requests[0].url, '/api/products/translate-ai')
    assert.deepEqual(requests[0].body, {
        rus: 'Пшеница',
        category_id: 7,
        languages: productLanguages.productLanguageFields.filter(field => field.key !== 'eng').map(field => field.key),
    })
    assert.equal(api.translating.value, true)
    assert.equal(api.canTranslate.value, false)
    await api.translateProduct()
    await api.createProduct()
    assert.equal(requests.length, 1, 'duplicate generation and saving are blocked while AI is working')
    requests[0].resolve(translationResponse(requests[0]))
    await pending
    assert.equal(api.form.eng, 'Existing wheat')
    assert.equal(api.form.zh, 'zh translation')
    assert.equal(api.form.he, 'he translation')
    assert.equal(api.translating.value, false)
    assert.equal(api.canTranslate.value, false, 'all translation fields are now filled')
    assert.match(api.translationMessage.value, /17/)
    assert.equal(requests.length, 1, 'generation does not save the product')
    api.form.zh = 'Manual correction'
    const saved = api.createProduct()
    assert.equal(requests[1].url, '/api/products')
    assert.equal(requests[1].body.zh, 'Manual correction')
    assert.equal(requests[1].body.eng, 'Existing wheat')
    requests[1].resolve({ id: 42, ...requests[1].body })
    await saved
    assert.equal(api.createDialog.value, false)
    assert.equal(api.products.value[0].zh, 'Manual correction')
})

test('manual edits during generation survive, including fields the user clears again', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    const pending = api.translateProduct()
    api.form.eng = 'My translation'
    api.form.zh = 'A draft'
    api.form.zh = ''
    const response = translationResponse(requests[0])
    response.translations.rus = 'Unexpected source change'
    response.translations.category_id = 99
    requests[0].resolve(response)
    await pending
    assert.equal(api.form.eng, 'My translation')
    assert.equal(api.form.zh, '')
    assert.equal(api.form.fr, 'fr translation')
    assert.equal(api.form.rus, 'Пшеница')
    assert.equal(api.form.category_id, null)
    assert.match(api.translationMessage.value, /16/)
    assert.equal(api.canTranslate.value, true)
})

test('changing the Russian source cancels the old request even if the original source is restored', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    const oldRequest = api.translateProduct()
    api.form.rus = 'Рожь'
    api.form.rus = 'Пшеница'
    assert.equal(requests[0].options.signal.aborted, true)
    assert.equal(api.translating.value, false)
    const currentRequest = api.translateProduct()
    requests[0].resolve(translationResponse(requests[0]))
    await oldRequest
    assert.equal(api.form.eng, '')
    assert.equal(api.translating.value, true, 'an old completion cannot clear the current loading state')
    requests[1].resolve(translationResponse(requests[1]))
    await currentRequest
    assert.equal(api.form.eng, 'eng translation')
})

test('category changes discard stale AI results and retry uses the new category', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    const pending = api.translateProduct()
    api.form.category_id = 4
    assert.equal(requests[0].options.signal.aborted, true)
    requests[0].reject({ response: { data: { message: 'Old failure' } } })
    await pending
    assert.equal(api.translationError.value, '')
    const retry = api.translateProduct()
    assert.equal(requests[1].body.category_id, 4)
    requests[1].resolve(translationResponse(requests[1]))
    await retry
    assert.equal(api.form.eng, 'eng translation')
})

test('closing and reopening a dialog cannot apply a translation to the new draft', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    const pending = api.translateProduct()
    api.createDialog.value = false
    assert.equal(requests[0].options.signal.aborted, true)
    api.openCreate()
    api.form.rus = 'Пшеница'
    requests[0].resolve(translationResponse(requests[0]))
    await pending
    assert.equal(api.form.eng, '')
    assert.equal(api.translationMessage.value, '')
    assert.equal(api.translating.value, false)
})

test('unmount aborts the request and ignores late responses', async () => {
    const { api, requests, dispose } = productFormHarness()
    const pending = api.translateProduct()
    dispose()
    assert.equal(requests[0].options.signal.aborted, true)
    requests[0].resolve(translationResponse(requests[0]))
    await pending
    assert.equal(api.form.eng, '')
    assert.equal(api.translationMessage.value, '')
})

test('AI failures leave manual data intact, explain the failure and allow retry', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    api.form.eng = 'Existing name'
    const pending = api.translateProduct()
    requests[0].reject({ response: { status: 503, data: { message: 'AI временно недоступен.' } } })
    await pending
    assert.equal(api.form.eng, 'Existing name')
    assert.equal(api.form.zh, '')
    assert.equal(api.translationError.value, 'AI временно недоступен.')
    assert.equal(api.translating.value, false)
    assert.equal(api.canTranslate.value, true)
    const retry = api.translateProduct()
    assert.equal(api.translationError.value, '')
    requests[1].resolve(translationResponse(requests[1]))
    await retry
    assert.equal(api.form.zh, 'zh translation')
    assert.equal(api.form.eng, 'Existing name')
})

test('invalid source names and completed or closed forms do not call AI', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    for (const name of ['', '  ', 'я'.repeat(256)]) {
        api.form.rus = name
        assert.equal(api.canTranslate.value, false)
        await api.translateProduct()
    }
    api.form.rus = 'я'.repeat(255)
    assert.equal(api.canTranslate.value, true)
    productLanguages.productLanguageFields.forEach(field => { api.form[field.key] = 'Filled' })
    await api.translateProduct()
    api.form.eng = ''
    api.createDialog.value = false
    await api.translateProduct()
    assert.equal(requests.length, 0)
})

test('async save validation blocks duplicate saves and starting AI until validation finishes', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    const validation = deferred()
    api.formRef.value = { validate: () => validation.promise }
    const saving = api.createProduct()
    assert.equal(api.saving.value, true)
    await api.translateProduct()
    await api.createProduct()
    assert.equal(requests.length, 0)
    validation.resolve({ valid: false })
    await saving
    assert.equal(api.saving.value, false)
    assert.equal(api.canTranslate.value, true)
})

test('incomplete or malformed translation responses never partially overwrite the form', async t => {
    const { api, requests, dispose } = productFormHarness()
    t.after(dispose)
    const pending = api.translateProduct()
    const response = translationResponse(requests[0])
    response.translations.he = null
    requests[0].resolve(response)
    await pending
    assert.equal(api.form.eng, '')
    assert.equal(api.translating.value, false)
    assert.match(api.translationError.value, /Попробуйте ещё раз/)
    assert.equal(api.canTranslate.value, true)
})
