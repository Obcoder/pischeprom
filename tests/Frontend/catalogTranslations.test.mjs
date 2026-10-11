import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { catalogTranslationFields, catalogTranslationForm, emptyCatalogTranslationForm } from '../../resources/js/Pages/Helpers/catalogLanguages.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const source = (overrides = {}) => ({ id: 42, name: 'Рыба', kind: 'category', translations: { ru: 'Рыба', en: 'Fish', pt: 'Peixe' }, ...overrides })
const flush = async () => { await Promise.resolve(); await Vue.nextTick() }
function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogTranslations.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'catalog-translations' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'catalog-translations', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    const props = Vue.reactive({ node: { id: 42, kind: 'category' }, active: false, disabled: false, ...overrides })
    let exposed
    const environment = {
        ...Vue, catalogTranslationFields, catalogTranslationForm, emptyCatalogTranslationForm,
        axios: Object.fromEntries(['get', 'patch'].map(method => [method, (url, body, config) => new Promise((resolve, reject) => requests.push({
            method, url, body, config: method === 'get' ? body : config, resolve: data => resolve({ data: { data } }), reject,
        }))])),
    }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose: value => { exposed = value }, emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    async function load(value = source()) {
        props.active = true
        await Vue.nextTick()
        requests.at(-1).resolve(value)
        await flush()
    }
    return { props, api, requests, emitted, exposed, load, scope, render: templateRenderer(template, api, props) }
}

test('all catalogue levels use ISO language fields and the common lazy translation endpoint', async t => {
    const h = harness(t)
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.ready.value, false)
    assert.deepEqual(Object.keys(h.exposed).sort(), ['refresh', 'reset', 'syncName'])
    await h.load()
    assert.equal(h.requests[0].url, '/api/catalog/nodes/42/translations')
    assert.equal(h.api.ready.value, true)
    assert.equal(h.api.form.en, 'Fish')
    assert.equal(h.api.form.ru, 'Рыба')
    assert.equal(h.api.filledLanguages.value, 3)
    assert.equal(catalogTranslationFields.length, 19)
    assert.deepEqual(catalogTranslationFields.map(field => field.key).sort(), ['ar', 'de', 'en', 'es', 'fa', 'fr', 'he', 'hi', 'id', 'it', 'ja', 'ko', 'nl', 'pt', 'ru', 'tr', 'ur', 'vi', 'zh'])
    assert.equal(catalogTranslationFields[0].key, 'ru')
    const edit = findVNode(h.render(), node => node.props?.['prepend-icon'] === 'mdi-pencil-outline')
    edit.props.onClick()
    const field = findVNode(h.render(), node => node.type === 'v-text-field' && node.props.label.includes('(EN)'))
    assert.equal(field.props.modelValue, 'Fish')
    assert.equal(field.props.dir, 'auto')
    assert.equal(field.props.maxlength, '255')
    field.props['onUpdate:modelValue']('Seafood')
    assert.equal(h.api.form.en, 'Seafood')
    assert.deepEqual(h.emitted.at(-1), ['state', { dirty: true, busy: false, ready: true, error: '' }])
    for (const [index, kind] of ['product', 'section', 'group', 'good'].entries()) {
        h.props.node = { id: 50 + index, kind }
        h.requests.at(-1).resolve(source({ id: 50 + index, kind }))
        await flush()
        assert.equal(h.api.record.value.kind, kind)
        assert.equal(h.api.form.en, 'Fish')
        assert.equal(h.api.dirty.value, false)
    }
})

test('saving sends only edited ISO locales, trims names, clears optional names and emits the complete node', async t => {
    const h = harness(t); await h.load()
    h.api.edit()
    h.api.form.en = ' Seafood '
    h.api.form.pt = ''
    h.api.form.tr = 'Balık'
    const save = h.api.saveTranslations()
    const request = h.requests.at(-1)
    assert.equal(request.method, 'patch')
    assert.equal(request.url, '/api/catalog/nodes/42/translations')
    assert.deepEqual(request.body, { translations: { en: 'Seafood', pt: null, tr: 'Balık' } })
    assert.equal(h.api.busy.value, true)
    assert.equal(findVNode(h.render(), node => node.type === 'v-text-field').props.disabled, true)
    const confirmed = source({ translations: { ru: 'Рыба', en: 'Seafood', tr: 'Balık' }, slug: 'fish', level_id: 4 })
    request.resolve(confirmed); await save
    assert.equal(h.api.form.en, 'Seafood')
    assert.equal(h.api.form.pt, '')
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.editing.value, false)
    assert.equal(h.api.busy.value, false)
    assert.deepEqual(h.emitted.find(event => event[0] === 'saved'), ['saved', confirmed])
})

test('tabs and refresh preserve drafts while updating unedited names and reset uses the new baseline', async t => {
    const h = harness(t); await h.load()
    h.api.edit(); h.api.form.en = 'Draft'
    h.props.active = false; await Vue.nextTick()
    h.props.active = true; await Vue.nextTick()
    assert.equal(h.requests.length, 1)
    assert.equal(h.api.editing.value, true)
    const refresh = h.exposed.refresh()
    h.requests.at(-1).resolve(source({ name: 'Морская рыба', translations: { ru: 'Морская рыба', en: 'Server edit', de: 'Fisch' } }))
    await refresh
    assert.equal(h.api.form.en, 'Draft')
    assert.equal(h.api.form.ru, 'Морская рыба')
    assert.equal(h.api.form.de, 'Fisch')
    assert.equal(h.api.dirty.value, true)
    const save = h.api.saveTranslations()
    assert.deepEqual(h.requests.at(-1).body, { translations: { en: 'Draft' } })
    h.requests.at(-1).reject({ response: { data: { message: 'Ошибка сохранения', errors: { 'translations.en': ['Название занято'] } } } })
    await save
    assert.deepEqual(h.api.fieldErrors.value.en, ['Название занято'])
    assert.equal(h.api.form.en, 'Draft')
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.error.value, 'Ошибка сохранения')
    h.exposed.reset()
    assert.equal(h.api.form.en, 'Server edit')
    assert.equal(h.api.form.ru, 'Морская рыба')
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.error.value, '')
})

test('local validation requires Russian and limits all names to 255 characters', async t => {
    const h = harness(t); await h.load()
    h.api.form.ru = ' '
    h.api.form.en = 'x'.repeat(256)
    await h.api.saveTranslations()
    assert.ok(h.api.fieldErrors.value.ru.length)
    assert.ok(h.api.fieldErrors.value.en.length)
    assert.equal(h.requests.length, 1)
    assert.equal(h.api.saving.value, false)
    h.api.form.ru = 'Рыба'
    h.api.form.en = 'F'.repeat(255)
    const save = h.api.saveTranslations()
    h.requests.at(-1).resolve(source({ translations: { ru: 'Рыба', en: 'F'.repeat(255) } })); await save
    assert.equal(h.api.dirty.value, false)
})

test('card renames synchronize clean Russian and retain independent Russian and other language drafts', async t => {
    const h = harness(t); await h.load()
    h.props.active = false; await Vue.nextTick()
    h.api.form.en = 'Draft'
    h.exposed.syncName('Новое название')
    assert.equal(h.api.form.ru, 'Новое название')
    assert.equal(h.api.record.value.name, 'Новое название')
    assert.equal(h.api.record.value.translations.ru, 'Новое название')
    assert.equal(h.api.baseline.value.ru, 'Новое название')
    assert.equal(h.api.form.en, 'Draft')
    const save = h.api.saveTranslations()
    assert.deepEqual(h.requests.at(-1).body, { translations: { en: 'Draft' } })
    h.requests.at(-1).resolve(source({ name: 'Новое название', translations: { ru: 'Новое название', en: 'Draft' } })); await save
    h.api.form.ru = 'Мой черновик'
    h.exposed.syncName('Подтверждённое название')
    assert.equal(h.api.form.ru, 'Мой черновик')
    assert.equal(h.api.baseline.value.ru, 'Подтверждённое название')
    h.exposed.reset()
    assert.equal(h.api.form.ru, 'Подтверждённое название')
    assert.equal(h.api.dirty.value, false)
})

test('node changes abort old loads and saves and their responses cannot affect a later request', async t => {
    const h = harness(t, { active: true })
    const first = h.requests[0]
    h.props.node = { id: 87 }
    assert.equal(first.config.signal.aborted, true)
    h.requests.at(-1).resolve(source({ id: 87, name: 'Мясо', translations: { ru: 'Мясо', en: 'Meat' } })); await flush()
    first.resolve(source()); await flush()
    assert.equal(h.api.record.value.id, 87)
    h.api.form.en = 'Draft 87'
    const oldSave = h.api.saveTranslations()
    const obsolete = h.requests.at(-1)
    h.props.node = { id: 88 }
    assert.equal(obsolete.config.signal.aborted, true)
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.saving.value, false)
    h.requests.at(-1).resolve(source({ id: 88 })); await flush()
    h.api.form.en = 'Draft 88'
    const newSave = h.api.saveTranslations()
    const current = h.requests.at(-1)
    obsolete.resolve(source({ id: 87 })); await oldSave
    assert.equal(h.api.saving.value, true)
    assert.equal(h.api.record.value.id, 88)
    assert.equal(h.emitted.filter(event => event[0] === 'saved').length, 0)
    current.resolve(source({ id: 88, translations: { ru: 'Рыба', en: 'Draft 88' } })); await newSave
    assert.equal(h.api.form.en, 'Draft 88')
    assert.equal(h.api.dirty.value, false)
})

test('saving aborts an older refresh and ignores its stale confirmed names', async t => {
    const h = harness(t); await h.load()
    const refresh = h.exposed.refresh()
    const obsolete = h.requests.at(-1)
    h.api.form.en = 'Saved name'
    const save = h.api.saveTranslations()
    assert.equal(obsolete.config.signal.aborted, true)
    h.requests.at(-1).resolve(source({ translations: { ru: 'Рыба', en: 'Saved name' } })); await save
    obsolete.resolve(source()); await refresh
    assert.equal(h.api.form.en, 'Saved name')
    assert.equal(h.api.record.value.translations.en, 'Saved name')
    assert.equal(h.api.dirty.value, false)
})

test('a confirmed card rename invalidates an older refresh while preserving translation drafts', async t => {
    const h = harness(t); await h.load()
    h.api.form.en = 'Draft'
    const refresh = h.exposed.refresh()
    const obsolete = h.requests.at(-1)
    h.exposed.syncName('Новое название')
    assert.equal(obsolete.config.signal.aborted, true)
    obsolete.resolve(source()); await refresh
    assert.equal(h.api.form.ru, 'Новое название')
    assert.equal(h.api.baseline.value.ru, 'Новое название')
    assert.equal(h.api.form.en, 'Draft')
    assert.equal(h.api.record.value.name, 'Новое название')
    assert.equal(h.api.loading.value, false)
})

test('a failed refresh retains confirmed data and all drafts while reporting its error', async t => {
    const h = harness(t); await h.load()
    h.api.form.en = 'Draft'
    const refresh = h.exposed.refresh({ throwOnError: true })
    const failure = new Error('Connection failed')
    h.requests.at(-1).reject(failure)
    await assert.rejects(refresh, failure)
    assert.equal(h.api.form.en, 'Draft')
    assert.equal(h.api.baseline.value.en, 'Fish')
    assert.equal(h.api.record.value.id, 42)
    assert.equal(h.api.ready.value, true)
    assert.equal(h.api.busy.value, false)
    assert.match(h.api.error.value, /Не удалось загрузить/)
})

test('disabled locks editing and submission including programmatic save attempts', async t => {
    const h = harness(t, { disabled: true }); await h.load()
    const edit = findVNode(h.render(), node => node.props?.['prepend-icon'] === 'mdi-pencil-outline')
    assert.equal(edit.props.disabled, true)
    edit.props.onClick()
    assert.equal(h.api.editing.value, false)
    h.props.disabled = false
    h.api.edit(); h.api.form.en = 'Draft'
    h.props.disabled = true
    assert.equal(findVNode(h.render(), node => node.type === 'v-text-field').props.disabled, true)
    assert.equal(findVNode(h.render(), node => node.props?.type === 'submit').props.disabled, true)
    await h.api.saveTranslations()
    assert.equal(h.requests.length, 1)
    assert.equal(h.api.form.en, 'Draft')
})

test('mismatched responses become errors and retry can recover without losing saved data or drafts', async t => {
    const h = harness(t, { active: true })
    h.requests[0].resolve(source({ id: 87 })); await flush()
    assert.equal(h.api.ready.value, false)
    assert.match(h.api.error.value, /Не удалось загрузить/)
    assert.ok(findVNode(h.render(), node => node.type === 'v-alert'))
    const retry = h.exposed.refresh()
    h.requests.at(-1).resolve(source()); await retry
    assert.equal(h.api.ready.value, true)
    assert.equal(h.api.error.value, '')
    h.api.form.en = 'Draft'
    const save = h.api.saveTranslations()
    h.requests.at(-1).resolve(source({ id: 87 })); await save
    assert.match(h.api.error.value, /Не удалось сохранить/)
    assert.equal(h.api.record.value.id, 42)
    assert.equal(h.api.form.en, 'Draft')
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.emitted.filter(event => event[0] === 'saved').length, 0)
})

test('deactivating an initial load cancels it and returning retries; disposal aborts writes', async t => {
    const h = harness(t, { active: true })
    const first = h.requests[0]
    h.props.active = false; await Vue.nextTick()
    assert.equal(first.config.signal.aborted, true)
    h.props.active = true; await Vue.nextTick()
    assert.equal(h.requests.length, 2)
    h.requests.at(-1).resolve(source()); await flush()
    first.reject(new Error('Cancelled')); await flush()
    assert.equal(h.api.error.value, '')
    h.api.form.en = 'Draft'
    const save = h.api.saveTranslations()
    const request = h.requests.at(-1)
    h.scope.stop()
    const previousEvents = h.emitted.length
    assert.equal(request.config.signal.aborted, true)
    request.resolve(source({ translations: { ru: 'Рыба', en: 'Draft' } })); await save
    assert.equal(h.emitted.length, previousEvents)
})
