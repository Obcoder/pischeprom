import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'
import { encodeLandingEditorText, insertLandingEditorReference, landingEditorText, supportsLandingReferences } from '../../resources/js/Components/Catalog/landingEditorText.js'

const schema = JSON.parse(readFileSync('resources/landings/schema.json', 'utf8'))
const helpersSource = readFileSync('resources/js/Components/Catalog/Landing/schema.js', 'utf8').replace(/^import .*$/gm, '').replace(/^export /gm, '')
const helpers = new Function('schema', `${helpersSource}; return {blockTypes, createLandingBlock, defaultFields, heroFields, catalogFields, contactFields, sourcesFields}`)(schema)
const draft = () => ({ template: 'editorial', hero: { title: 'Скумбрия', subtitle: 'Оптом', image: '' }, catalog: { enabled: true, mode: 'selection', source_product_ids: [201], good_ids: [], inline_good_ids: [75] }, blocks: [helpers.createLandingBlock('faq')], contact: { enabled: true }, sources: { enabled: true, items: [] } })
const snapshot = (changes = {}) => ({ mode: 'catalog', exists: true, version: 4, draft_content: draft(), published_content: draft(), has_changes: false, published_at: '2026-10-10T10:00:00Z', public_url: '/p/mackerel', preview_url: '/Ameise/catalog/nodes/160/landing/preview', effective_visible: true, source_products: [{ id: 201, name: 'Скумбрия замороженная' }], selected_goods: [{ id: 75, name: 'Скумбрия 400–600' }], templates: [{ key: 'editorial', label: 'Каталог с гидом', content: draft() }], ...changes })
async function settle() { await Promise.resolve(); await Vue.nextTick(); await Promise.resolve() }
function harness(t, initial = {}, componentName = 'CatalogLandingEditor') {
    const filename = `resources/js/Components/Catalog/${componentName}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: componentName })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: componentName, compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], exposed = {}
    const environment = { ...Vue, ...helpers, encodeLandingEditorText, insertLandingEditorReference, landingEditorText, supportsLandingReferences, CatalogLandingTextField: 'CatalogLandingTextField', CatalogLandingFields: 'CatalogLandingFields', axios: Object.fromEntries(['get', 'put', 'post'].map(method => [method, (url, body, options) => {
        let resolve, reject
        const promise = new Promise((success, failure) => { resolve = success; reject = failure })
        requests.push({ method, url, body: method === 'get' ? null : body, options: method === 'get' ? body : options, resolve: data => resolve({ data }), reject })
        return promise
    }])) }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const props = Vue.reactive({ node: { id: 160, name: 'Скумбрия', public_url: '/p/mackerel' }, active: true, disabled: false, cardDirty: false, ...initial })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose: value => Object.assign(exposed, value), emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    return { api, props, requests, emitted, exposed, scope, render: templateRenderer(template, api, props), async ready(data = snapshot()) {
        requests.find(item => item.url.endsWith('/landing')).resolve({ data })
        await settle()
        requests.find(item => item.options?.params?.view === 'filters')?.resolve({ products: [{ id: 201, rus: 'Скумбрия замороженная' }, { id: 124, rus: 'Скумбрия' }] })
        await settle()
    } }
}

test('editing and saving a draft preserve its source selection and never publish implicitly', async t => {
    const h = harness(t)
    const initial = snapshot()
    await h.ready(initial)
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.sourceProducts.value.find(item => item.id === 201).name, 'Скумбрия замороженная')
    assert.equal(h.api.selectedGoods.value[0].name, 'Скумбрия 400–600')
    h.api.content.value.hero.title = 'Новый первый экран'
    assert.equal(h.api.dirty.value, true)
    assert.equal(initial.draft_content.hero.title, 'Скумбрия', 'Local editing cannot mutate the server baseline')
    const before = h.requests.length
    assert.equal(await h.api.mutate('publish'), false)
    assert.equal(h.requests.length, before)
    const pending = h.exposed.save()
    const request = h.requests.at(-1)
    assert.equal(request.method, 'put')
    assert.deepEqual(request.body.content.catalog.source_product_ids, [201])
    assert.equal(request.body.version, 4)
    assert.equal(h.api.busy.value, true)
    assert.ok(h.emitted.some(event => event[0] === 'state' && event[1].busy))
    request.resolve({ data: snapshot({ version: 5, has_changes: true, draft_content: request.body.content }) })
    assert.equal(await pending, true)
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.state.value.published_content.hero.title, 'Скумбрия')
    assert.equal(h.requests.some(item => item.url.endsWith('/publish')), false)
    h.props.cardDirty = true
    assert.equal(await h.api.mutate('publish'), false, 'Unsaved authoritative metadata must be saved first')
    h.props.cardDirty = false
    const publication = h.api.mutate('publish')
    const publishRequest = h.requests.at(-1)
    assert.equal(publishRequest.url, '/api/catalog/nodes/160/landing/publish')
    assert.deepEqual(publishRequest.body, { version: 5 })
    publishRequest.resolve({ data: snapshot({ version: 6, draft_content: request.body.content, published_content: request.body.content }) })
    assert.equal(await publication, true)
})

test('a new landing is a local draft until save and its ordered blocks remain independently editable', async t => {
    const h = harness(t)
    await h.ready(snapshot({ exists: false, version: 0, draft_content: null, published_content: null, published_at: null }))
    h.api.create()
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.hasSavedDraft.value, false)
    const first = h.api.content.value.blocks[0]
    h.api.newBlockType.value = 'table'
    h.api.addBlock()
    const second = h.api.content.value.blocks[1]
    h.api.moveBlock(1, -1)
    assert.equal(h.api.content.value.blocks[0].id, second.id)
    assert.equal(h.api.content.value.blocks[1].id, first.id)
    h.api.content.value.blocks[0].enabled = false
    h.api.removeBlock(1)
    assert.equal(h.api.content.value.blocks.length, 1)
    assert.equal(h.api.content.value.blocks[0].enabled, false)
    assert.equal(h.requests.some(item => item.method !== 'get'), false)
    h.exposed.reset()
    assert.equal(h.api.content.value, null)
    assert.equal(h.api.dirty.value, false)
})

test('virtual legacy content is already public even when its original publication date is unknown', async t => {
    const h = harness(t)
    await h.ready(snapshot({ legacy: true, version: 0, published_at: null }))
    assert.equal(h.api.published.value, true)
    assert.equal(h.api.publicationLabel.value, 'Опубликован')
    assert.equal(h.api.hasSavedDraft.value !== null, true)
    const unpublish = h.api.mutate('unpublish')
    const request = h.requests.at(-1)
    assert.equal(request.url, '/api/catalog/nodes/160/landing/unpublish')
    assert.deepEqual(request.body, { version: 0 })
    request.resolve({ data: snapshot({ legacy: false, version: 1, published_content: null, published_at: null, activated_at: '2026-10-10T10:00:00Z' }) })
    assert.equal(await unpublish, true)
    assert.equal(h.api.published.value, false)
    assert.ok(h.api.content.value, 'Deactivation retains editable content')
})

test('inactive loads abort and stale identity responses cannot replace another record or its local edits', async t => {
    const h = harness(t)
    const stale = h.requests[0]
    h.props.active = false
    await settle()
    assert.equal(stale.options.signal.aborted, true)
    stale.resolve({ data: snapshot() })
    await settle()
    assert.equal(h.api.ready.value, false)
    h.props.active = true
    await settle()
    const second = h.requests.at(-1)
    h.props.node = { id: 99, name: 'Другой раздел' }
    const current = h.requests.at(-1)
    assert.equal(second.options.signal.aborted, true)
    second.resolve({ data: snapshot() })
    current.resolve({ data: snapshot({ draft_content: { ...draft(), hero: { title: 'Другой раздел' } } }) })
    await settle()
    assert.equal(h.api.content.value.hero.title, 'Другой раздел')
    h.api.content.value.hero.title = 'Несохранённое изменение'
    h.props.active = false
    await settle()
    h.props.active = true
    await settle()
    assert.equal(h.api.content.value.hero.title, 'Несохранённое изменение')
    h.scope.stop()
    assert.ok(h.requests.filter(item => item.options?.signal).at(-1).options.signal.aborted)
})

test('version conflicts preserve local work and require an explicit recovery choice before another write', async t => {
    const h = harness(t)
    await h.ready()
    h.api.content.value.hero.title = 'Мой заголовок'
    const save = h.exposed.save()
    h.requests.at(-1).reject({ response: { status: 409 } })
    await settle()
    assert.equal(h.api.conflict.value, true)
    assert.equal(h.api.content.value.hero.title, 'Мой заголовок')
    assert.equal(h.requests.at(-1).method, 'get')
    const server = snapshot({ version: 8, draft_content: { ...draft(), hero: { title: 'Правка коллеги' } } })
    h.requests.at(-1).resolve({ data: server })
    assert.equal(await save, false)
    assert.equal(await h.exposed.save(), false)
    h.api.resolveConflict(true)
    assert.equal(h.api.state.value.version, 8)
    assert.equal(h.api.content.value.hero.title, 'Мой заголовок')
    assert.equal(h.api.dirty.value, true)
    const retry = h.exposed.save()
    const request = h.requests.at(-1)
    assert.equal(request.body.version, 8)
    assert.equal(request.body.content.hero.title, 'Мой заголовок')
    request.resolve({ data: snapshot({ version: 9, draft_content: request.body.content }) })
    assert.equal(await retry, true)
    assert.equal(h.api.dirty.value, false)
})

test('failed saves retain the draft, while choosing the current server version deliberately replaces it', async t => {
    const h = harness(t)
    await h.ready()
    h.api.content.value.hero.title = 'Правка'
    const failure = h.exposed.save()
    h.requests.at(-1).reject({ response: { status: 422, data: { errors: { 'content.hero.title': ['Заполните заголовок.'] } } } })
    assert.equal(await failure, false)
    assert.equal(h.api.content.value.hero.title, 'Правка')
    assert.equal(h.api.dirty.value, true)
    assert.match(h.api.error.value, /Заполните заголовок/)
    h.api.conflict.value = true
    h.api.conflictSnapshot.value = snapshot({ version: 22 })
    h.api.resolveConflict(false)
    assert.equal(h.api.content.value.hero.title, 'Скумбрия')
    assert.equal(h.api.state.value.version, 22)
    assert.equal(h.api.dirty.value, false)
})

test('shared good pages link to their existing controls and never offer catalog publication writes', async t => {
    const h = harness(t)
    await h.ready(snapshot({ mode: 'good', exists: false, draft_content: null, templates: [] }))
    assert.equal(h.api.isGood.value, true)
    assert.equal(h.requests.length, 1, 'Shared good page does not load a second product editor')
    assert.equal(await h.api.mutate('publish'), false)
    const seo = findVNode(h.render(), node => node.type === 'v-btn' && node.props?.['prepend-icon'] === 'mdi-magnify')
    seo.props.onClick()
    assert.deepEqual(h.emitted.at(-1), ['navigate', 'seo'])
    assert.equal(findVNode(h.render(), node => node.type === 'v-btn' && node.props?.['prepend-icon'] === 'mdi-publish'), null)
})

test('image upload updates the local field, and stale searches or uploads never mutate the next card', async t => {
    const h = harness(t)
    await h.ready()
    const file = new File(['image'], 'hero.png', { type: 'image/png' })
    const upload = h.api.uploadImage({ file, apply: url => { h.api.content.value.hero.image = url } })
    h.requests.at(-1).resolve({ url: '/storage/catalog-landings/hero.png' })
    assert.equal(await upload, true)
    assert.equal(h.api.content.value.hero.image, '/storage/catalog-landings/hero.png')
    assert.equal(h.api.dirty.value, true)
    const first = h.api.searchGoods('Скумбрия')
    const firstRequest = h.requests.at(-1)
    const second = h.api.searchGoods('Форель')
    const secondRequest = h.requests.at(-1)
    assert.equal(firstRequest.options.signal.aborted, true)
    firstRequest.resolve({ data: [{ id: 1, name: 'Скумбрия' }] })
    secondRequest.resolve({ data: [{ id: 2, name: 'Форель' }] })
    await Promise.all([first, second])
    assert.deepEqual(h.api.goodOptions.value.map(item => item.id), [2])
    let applied = false
    const stale = h.api.uploadImage({ file, apply: () => { applied = true } })
    const staleRequest = h.requests.at(-1)
    h.props.node = { id: 170, name: 'Форель' }
    staleRequest.resolve({ url: '/storage/stale.png' })
    assert.equal(await stale, false)
    assert.equal(applied, false)
})

test('recursive fields edit nested rows as structures with independent defaults and no JSON input', t => {
    const fields = helpers.blockTypes.faq.fields
    const h = harness(t, { modelValue: helpers.defaultFields(fields), fields }, 'CatalogLandingFields')
    const questions = fields.find(item => item.key === 'items')
    h.api.add(questions)
    const first = h.emitted.at(-1)[1]
    h.props.modelValue = first
    h.api.add(questions)
    const second = h.emitted.at(-1)[1]
    assert.equal(second.items.length, 2)
    assert.notEqual(second.items[0], second.items[1])
    assert.notEqual(second.items[0].id, second.items[1].id, 'New anchors are generated without asking for identifiers')
    second.items[0].question = 'Как хранить?'
    assert.equal(second.items[1].question, '')
    h.props.modelValue = second
    h.api.move(questions, 0, 1)
    assert.equal(h.emitted.at(-1)[1].items[1].question, 'Как хранить?')
    assert.equal(findVNode(h.render(), node => node.props?.label?.includes('JSON')), null)
})

test('link editing displays readable labels, preserves target and caption, and handles ambiguous or literal markers', () => {
    const value = 'Товар [[good:75|Скумбрия]] и [[#source-reg|[1]]]. Ещё [[good:80|Скумбрия]]. Повтор [[good:75|Скумбрия]]. Обычный ⟦Скумбрия⟧.'
    const display = landingEditorText(value, [{ target: '#source-reg', name: 'Технический регламент' }])
    assert.doesNotMatch(display.text, /good:75|source-reg|\[\[/)
    assert.equal(display.references.length, 3)
    assert.match(display.references.find(reference => reference.target === '#source-reg').name, /Технический регламент/)
    assert.equal(encodeLandingEditorText(display.text, display.references), value)
    assert.equal(encodeLandingEditorText(display.text + '\nДополнение', display.references), value + '\nДополнение')
    const withoutBrackets = display.text.replace(display.references[0].marker, 'Скумбрия')
    assert.match(encodeLandingEditorText(withoutBrackets, display.references), /^Товар Скумбрия и/)
    const selected = { token: '[[#source-reg|[1]]]', label: '[1]', name: 'Технический регламент', target: '#source-reg' }
    const inserted = insertLandingEditorReference('Начало и конец', 7, 8, selected)
    assert.equal(inserted.value, 'Начало [[#source-reg|[1]]] конец')
    assert.equal(inserted.cursor, 'Начало ⟦[1]⟧'.length)
    assert.equal(supportsLandingReferences('block.items.1', 'paragraphs'), true)
    assert.equal(supportsLandingReferences('sources.items.1', 'description'), false)
    assert.equal(supportsLandingReferences('hero', 'caption'), false)
})

test('named link insertion replaces the selected phrase and emits safe stored content without requiring IDs', async t => {
    const link = { token: '[[good:75|Скумбрия]]', label: 'Скумбрия', target: 'good:75', name: 'Товар: Скумбрия' }
    const h = harness(t, { modelValue: 'Выбрать рыбу', label: 'Абзац', links: [link] }, 'CatalogLandingTextField')
    h.api.selection.value = { start: 8, end: 12 }
    await h.api.insert(link.token)
    assert.deepEqual(h.emitted.at(-1), ['update:modelValue', 'Выбрать [[good:75|Скумбрия]]'])
    h.props.modelValue = h.emitted.at(-1)[1]
    assert.equal(h.api.display.value.text, 'Выбрать ⟦Скумбрия⟧')
    h.api.update('Можно ' + h.api.display.value.text)
    assert.deepEqual(h.emitted.at(-1), ['update:modelValue', 'Можно Выбрать [[good:75|Скумбрия]]'])
    const input = findVNode(h.render(), node => node.type === 'v-textarea')
    assert.equal(input.props['model-value'], 'Выбрать ⟦Скумбрия⟧')
})

test('copy and paste between text fields preserve inline references while ordinary clipboard text contains readable labels', async t => {
    const from = harness(t, { modelValue: 'Рыба [[good:75|Скумбрия]]', label: 'Абзац', links: [] }, 'CatalogLandingTextField')
    const clipboard = new Map()
    const clipboardData = { setData: (type, value) => clipboard.set(type, value), getData: type => clipboard.get(type) }
    let prevented = false
    from.api.copy({ target: { selectionStart: 5, selectionEnd: from.api.display.value.text.length }, clipboardData, preventDefault() { prevented = true } })
    assert.equal(prevented, true)
    assert.equal(clipboard.get('text/plain'), 'Скумбрия')
    assert.equal(clipboard.get('application/x-pischeprom-landing-text'), '[[good:75|Скумбрия]]')
    const to = harness(t, { modelValue: 'Выбирайте ', label: 'Абзац', links: [] }, 'CatalogLandingTextField')
    await to.api.paste({ target: { selectionStart: 10, selectionEnd: 10 }, clipboardData, preventDefault() {} })
    assert.deepEqual(to.emitted.at(-1), ['update:modelValue', 'Выбирайте [[good:75|Скумбрия]]'])
})
