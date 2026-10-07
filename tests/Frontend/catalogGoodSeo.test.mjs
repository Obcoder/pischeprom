import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { safeGalleryUrl } from '../../resources/js/Components/Catalog/gallery.js'
import { normalizeSemanticCoreRows, parseSemanticCoreTable, semanticCorePhrases } from '../../resources/js/Components/Catalog/semanticCore.js'
import { buildGoodSeoPrompt } from '../../resources/js/Components/Catalog/seoPrompt.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const seo = (changes = {}) => ({
    id: 8, good_id: 42, meta_title: 'Филе форели оптом', meta_description: 'Свежая форель.', h1: 'Филе форели',
    slug_override: 'trout-fillet', canonical_url: 'https://example.test/g/trout-fillet', robots: 'index,follow',
    og_title: 'Форель', og_description: 'Свежее филе', og_image: '/storage/full.jpg',
    twitter_title: 'Филе', twitter_description: 'Охлаждённое', twitter_image: '/storage/small.jpg',
    short_seo_text: 'Свежая рыба', seo_text: 'Филе для ресторанов.',
    semantic_core: ['форель оптом', 'филе'], keywords: ['форель'], search_queries: ['купить форель'],
    structured_data: { '@type': 'Product', name: 'Форель' }, focus_keyword: 'филе форели', breadcrumbs_title: 'Филе',
    is_active: true, include_in_sitemap: true, include_in_yandex_feed: false,
    yandex_direct_title_1: 'Филе форели', yandex_direct_title_2: 'Оптом', yandex_direct_text: 'Поставка форели.', utm_template: 'utm_source=yandex',
    availability_status: 'in_stock', min_order: '10 кг', delivery_note: 'Доставим', payment_note: 'Безналичная оплата',
    faq: [{ question: 'Есть доставка?', answer: 'Да | каждый день' }], ai_generation: { available: true }, ...changes,
})
function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogGoodSeo.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'CatalogGoodSeo' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'CatalogGoodSeo', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], exposed = {}
    const environment = {
        ...Vue, safeGalleryUrl, normalizeSemanticCoreRows, parseSemanticCoreTable, semanticCorePhrases, buildGoodSeoPrompt,
        usePublicGoodUrl: () => ({ goodPublicUrl: good => `https://example.test/g/${good.slug || good.id}` }),
        axios: Object.fromEntries(['get', 'put', 'post'].map(method => [method, (url, body, options) => {
            let resolve, reject
            const promise = new Promise((success, failure) => { resolve = success; reject = failure })
            requests.push({ method, url, body: method === 'get' ? null : body, options: method === 'get' ? body : options,
                resolve: data => resolve({ data }), reject })
            return promise
        }])),
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ good: { id: 42, name: 'Форель филе', slug: 'trout', description: 'Охлаждённое филе', is_published: true, ava_thumb: '/thumb.jpg' }, active: true, disabled: false, ...overrides })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose: value => Object.assign(exposed, value), emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    return { api, exposed, props, requests, emitted, scope, descriptor, render: templateRenderer(template, api, props),
        async ready(data = seo(), ads = { data: [] }) {
            requests.find(request => request.method === 'get' && request.url.endsWith('/seo')).resolve(data)
            requests.find(request => request.url === '/api/marketing/direct/goods').resolve(ads)
            await settle()
        } }
}
async function settle() { await Promise.resolve(); await Vue.nextTick() }
function updateInput(h, id, value) {
    const input = findVNode(h.render(), node => node.props?.id === id)
    assert.ok(input, `Input ${id} exists on the thematic page`)
    const handlers = input.props['onUpdate:modelValue']
    if (!handlers && input.props.onInput) return input.props.onInput({ target: { value } })
    for (const handler of Array.isArray(handlers) ? handlers : [handlers]) handler(value)
}
function mockClipboard(t, clipboard) {
    const previous = Object.getOwnPropertyDescriptor(globalThis, 'navigator')
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard } })
    t.after(() => previous ? Object.defineProperty(globalThis, 'navigator', previous) : delete globalThis.navigator)
}

test('ChatGPT assignment copies current facts and drafts, announcing success only after clipboard resolves', async t => {
    let resolveCopy, copied
    mockClipboard(t, { writeText: value => { copied = value; return new Promise(resolve => { resolveCopy = resolve }) } })
    const h = harness(t)
    await h.ready()
    h.props.good.name = 'Несохранённая форель'
    h.api.form.h1 = 'Новый заголовок'
    h.api.form.semantic_core_rows = [{ group: 'Поставка', phrase: 'форель ресторанам' }]
    const count = h.requests.length
    const copy = h.api.copyChatGptPrompt()
    assert.equal(h.api.copyingPrompt.value, true)
    assert.equal(h.api.clipboardMessage.value, '')
    assert.match(copied, /Несохранённая форель/)
    assert.match(copied, /Новый заголовок/)
    assert.match(copied, /форель ресторанам/)
    resolveCopy()
    assert.equal(await copy, true)
    assert.match(h.api.clipboardMessage.value, /скопировано/)
    assert.equal(h.api.clipboardDialog.value, false)
    assert.equal(h.requests.length, count, 'Copying creates no AI, advertising or save request')
})

test('blocked clipboard offers selectable plain text and late rejection cannot reopen an inactive tab', async t => {
    let rejectCopy
    mockClipboard(t, { writeText: () => new Promise((resolve, reject) => { rejectCopy = reject }) })
    const h = harness(t)
    await h.ready()
    let focused = 0, selected = 0
    h.api.promptTextarea.value = { focus: () => focused++, select: () => selected++ }
    const copy = h.api.copyChatGptPrompt()
    rejectCopy(new Error('Denied'))
    assert.equal(await copy, false)
    assert.equal(h.api.clipboardDialog.value, true)
    assert.equal(h.api.clipboardMessage.value, '')
    assert.ok(focused > 0 && selected > 0)
    const textarea = findVNode(h.render(), node => node.type === 'textarea' && node.props?.readonly !== undefined)
    assert.equal(textarea.props.value, h.api.clipboardText.value)
    assert.equal(textarea.props.innerHTML, undefined)
    h.api.clipboardDialog.value = false
    const stale = h.api.copyChatGptPrompt()
    h.props.active = false
    rejectCopy(new Error('Denied late'))
    assert.equal(await stale, false)
    assert.equal(h.api.clipboardDialog.value, false)
    assert.equal(h.api.copyingPrompt.value, false)
})

test('canonical preserves a loaded custom address, follows explicit slug edits and automatic base addresses', async t => {
    const h = harness(t)
    await h.ready(seo({ canonical_url: 'https://example.test/custom' }))
    assert.equal(h.api.form.canonical_url, 'https://example.test/custom')
    assert.equal(h.api.dirty.value, false)
    h.props.good.slug = 'base-change'
    assert.equal(h.api.form.canonical_url, 'https://example.test/custom')
    updateInput(h, 'catalog-seo-slug', 'new-seo-slug')
    assert.equal(h.api.form.canonical_url, 'https://example.test/g/new-seo-slug')
    updateInput(h, 'catalog-seo-slug', '')
    assert.equal(h.api.form.canonical_url, 'https://example.test/g/base-change')
    h.props.good.slug = 'latest-base'
    assert.equal(h.api.form.canonical_url, 'https://example.test/g/latest-base')
    updateInput(h, 'catalog-seo-canonical', 'https://example.test/manual')
    h.props.good.slug = 'last-base'
    assert.equal(h.api.form.canonical_url, 'https://example.test/manual')
    h.api.useAutomaticCanonical()
    assert.equal(h.api.form.canonical_url, 'https://example.test/g/last-base')
    h.exposed.reset()
    assert.equal(h.api.form.canonical_url, 'https://example.test/custom')
    assert.equal(h.api.dirty.value, false)
    const blank = harness(t)
    await blank.ready(seo({ canonical_url: '', slug_override: '' }))
    assert.equal(blank.api.form.canonical_url, 'https://example.test/g/trout')
    assert.equal(blank.api.dirty.value, false)
    blank.props.good.slug = 'updated'
    assert.equal(blank.api.form.canonical_url, 'https://example.test/g/updated')
})

test('grouped rows remain distinct by group while legacy and AI fields contain unique phrases only', async t => {
    const h = harness(t)
    await h.ready(seo({ semantic_core_rows: null }))
    assert.deepEqual(h.api.form.semantic_core_rows, [{ group: '', phrase: 'форель оптом' }, { group: '', phrase: 'филе' }])
    h.api.form.semantic_core_rows = [{ group: 'Опт', phrase: 'форель оптом' }, { group: 'Ресторанам', phrase: 'ФОРЕЛЬ ОПТОМ' }, { group: 'Опт', phrase: 'форель оптом' }]
    const payload = h.api.payload()
    assert.equal(payload.semantic_core_rows.length, 2)
    assert.deepEqual(payload.semantic_core, ['форель оптом'])
    assert.deepEqual(h.api.aiContext().semantic_core, ['форель оптом'])
    const save = h.exposed.save()
    h.requests.at(-1).resolve({ ...seo(), ...payload })
    assert.equal(await save, true)
    assert.deepEqual(h.api.form.semantic_core_rows, payload.semantic_core_rows)
    h.api.addSemanticRow()
    assert.equal(h.exposed.validate(), false)
    assert.match(h.api.error.value, /строка 3/)
    h.api.form.semantic_core_rows[2].phrase = 'x'.repeat(1001)
    assert.equal(h.exposed.validate(), false)
    h.api.removeSemanticRow(2)
    assert.equal(h.exposed.validate(), true)
    h.exposed.reset()
    assert.deepEqual(h.api.form.semantic_core_rows, payload.semantic_core_rows)
    assert.equal(h.api.dirty.value, false)
})

test('unapplied table paste is guarded as a draft; preview, append, replace and reset retain independent tabs', async t => {
    const h = harness(t)
    await h.ready(seo({ semantic_core_rows: [{ group: 'Опт', phrase: 'форель оптом' }] }))
    const original = JSON.parse(JSON.stringify(h.api.form.semantic_core_rows))
    updateInput(h, 'catalog-seo-core-import', '| Группа | Поисковая фраза |\n|---|---|\n| Опт | форель оптом |\n| HoReCa | филе ресторанам |')
    assert.equal(h.api.importPreview.value.rows.length, 2)
    assert.deepEqual(h.api.form.semantic_core_rows, original, 'Preview does not mutate saved or draft rows')
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.exposed.validate(), false)
    assert.match(h.api.error.value, /Примените или очистите/)
    const count = h.requests.length
    assert.equal(await h.exposed.save(), false)
    assert.equal(h.requests.length, count)
    h.props.active = false
    assert.equal(h.api.dirty.value, true)
    assert.match(h.api.importText.value, /HoReCa/)
    h.props.active = true
    assert.equal(h.api.applySemanticImport(), true)
    assert.equal(h.api.form.semantic_core_rows.length, 2)
    assert.equal(h.api.importText.value, '')
    updateInput(h, 'catalog-seo-keywords_text', 'Новый keyword')
    updateInput(h, 'catalog-seo-faq', 'Новый вопрос | Ответ')
    for (const tab of ['core', 'keywords', 'queries', 'faq']) {
        const button = findVNode(h.render(), node => node.props?.id === `catalog-seo-tab-${tab}`)
        button.props.onClick()
        assert.equal(h.api.semanticTab.value, tab)
        assert.ok(findVNode(h.render(), node => node.props?.id === 'catalog-seo-core-import'), 'Inner tabs remain mounted')
    }
    assert.equal(h.api.form.keywords_text, 'Новый keyword')
    assert.equal(h.api.form.faq_text, 'Новый вопрос | Ответ')
    h.api.importText.value = 'Новая группа\tНовая фраза'
    h.api.importMode.value = 'replace'
    assert.equal(h.api.applySemanticImport(), true)
    assert.deepEqual(h.api.form.semantic_core_rows, [{ group: 'Новая группа', phrase: 'Новая фраза' }])
    h.api.importText.value = 'Ещё\tНесохранённая таблица'
    h.exposed.reset()
    assert.deepEqual(h.api.form.semantic_core_rows, original)
    assert.equal(h.api.importText.value, '')
    assert.equal(h.api.form.keywords_text, 'форель')
    assert.equal(h.api.dirty.value, false)
})

test('malformed table import cannot partially apply and markup remains literal preview text', async t => {
    const h = harness(t)
    await h.ready()
    const original = JSON.parse(JSON.stringify(h.api.form.semantic_core_rows))
    h.api.importText.value = '| Группа | Фраза |\n|---|---|\n| Опт | корректная |\n| Ошибка | |'
    assert.equal(h.api.importPreview.value.rows.length, 1)
    assert.ok(h.api.importPreview.value.errors.length)
    assert.equal(h.api.applySemanticImport(), false)
    assert.deepEqual(h.api.form.semantic_core_rows, original)
    h.api.importText.value = 'Опт\t<img src=x onerror=alert(1)>'
    const preview = findVNode(h.render(), node => hasClass(node, 'catalog-good-seo__import-preview'))
    assert.ok(findVNode(preview, node => node.type === 'td' && node.children === '<img src=x onerror=alert(1)>'))
    assert.equal(findVNode(preview, node => node.type === 'img' || node.props?.innerHTML), null)
    h.api.clearImport()
    assert.equal(h.api.dirty.value, false)
})

test('every SEO field is present in the compact tables and a single payload retains all channels and data', async t => {
    const h = harness(t)
    assert.deepEqual(h.requests.find(request => request.url === '/api/marketing/direct/goods').options.params, { good_id: 42, per_page: 1 })
    await h.ready()
    assert.equal(h.api.ready.value, true)
    assert.equal(h.api.dirty.value, false)
    const payload = h.api.payload()
    const expected = seo()
    expected.semantic_core_rows = expected.semantic_core.map(phrase => ({ group: '', phrase }))
    delete expected.id; delete expected.good_id; delete expected.ai_generation
    assert.deepEqual(payload, expected)
    assert.equal(findVNode(h.render(), node => node.type === 'v-expansion-panels'), null)
    for (const id of ['h1', 'meta_title', 'meta_description', 'slug', 'canonical', 'breadcrumbs', 'robots', 'short_seo_text', 'seo_text', 'core-import', 'keywords_text', 'search_queries_text', 'faq', 'availability', 'min-order', 'delivery', 'payment', 'jsonld', 'yandex_direct_title_1', 'yandex_direct_title_2', 'yandex_direct_text', 'utm']) {
        assert.ok(findVNode(h.render(), node => node.props?.id === `catalog-seo-${id}`), id)
    }
    assert.ok(findVNode(h.render(), node => hasClass(node, 'catalog-good-seo__metrics')))
    updateInput(h, 'catalog-seo-meta_title', 'Новое имя в поиске')
    h.props.disabled = true
    h.props.active = false
    assert.equal(h.exposed.validate(), true)
    const save = h.exposed.save()
    const request = h.requests.at(-1)
    assert.equal(request.method, 'put')
    assert.equal(request.url, '/api/goods/42/seo')
    assert.equal(request.body.meta_title, 'Новое имя в поиске')
    assert.deepEqual(request.body.structured_data, expected.structured_data)
    request.resolve(seo({ meta_title: 'Новое имя в поиске' }))
    assert.equal(await save, true)
    assert.equal(h.api.dirty.value, false)
    assert.ok(h.emitted.some(event => event[0] === 'saved' && event[1].meta_title === 'Новое имя в поиске'))
    assert.deepEqual(h.emitted.filter(event => event[0] === 'state').at(-1)[1], { dirty: false, busy: false, ready: true, error: '' })
})

test('switching away preserves drafts and reset returns only to the last confirmed SEO snapshot', async t => {
    const h = harness(t)
    await h.ready()
    h.api.form.seo_text = 'Несохранённый текст'
    h.api.form.include_in_sitemap = false
    const requestCount = h.requests.length
    h.props.active = false
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.form.seo_text, 'Несохранённый текст')
    h.props.active = true
    assert.equal(h.requests.filter(request => request.url.endsWith('/seo')).length, 1)
    assert.equal(h.requests.length, requestCount + 1, 'Only advertising statistics refresh on returning')
    h.exposed.reset()
    assert.equal(h.api.form.seo_text, 'Филе для ресторанов.')
    assert.equal(h.api.form.include_in_sitemap, true)
    assert.equal(h.api.dirty.value, false)
})

test('failed and stale initial loads never mark a different good ready and remain retryable', async t => {
    const h = harness(t)
    const oldSeo = h.requests[0]
    h.props.good = { id: 43, name: 'Другой товар', slug: 'other' }
    assert.equal(oldSeo.options.signal.aborted, true)
    oldSeo.resolve(seo({ h1: 'Устаревший товар' }))
    await settle()
    assert.equal(h.api.ready.value, false)
    assert.notEqual(h.api.form.h1, 'Устаревший товар')
    const current = h.requests.find(request => request.url === '/api/goods/43/seo')
    current.reject({ response: { status: 500, data: { message: 'Временная ошибка' } } })
    await settle()
    assert.equal(h.api.error.value, 'Временная ошибка')
    assert.equal(await h.exposed.save(), false)
    assert.equal(h.requests.filter(request => request.method === 'put').length, 0)
    const retry = h.exposed.load()
    h.requests.at(-1).resolve(seo({ good_id: 43, h1: 'Другой товар' }))
    assert.equal(await retry, true)
    assert.equal(h.api.form.h1, 'Другой товар')
})

test('invalid JSON-LD and incomplete FAQ stop writes without silently dropping a user entry', async t => {
    const h = harness(t)
    await h.ready()
    const count = h.requests.length
    h.api.form.structured_data_text = '{ broken'
    assert.equal(h.exposed.validate(), false)
    assert.match(h.api.error.value, /JSON/)
    assert.equal(await h.exposed.save(), false)
    assert.equal(h.requests.length, count)
    h.api.form.structured_data_text = '42'
    assert.equal(h.exposed.validate(), false)
    h.api.form.structured_data_text = '[]'
    h.api.form.faq_text = 'Вопрос без ответа'
    assert.equal(h.exposed.validate(), false)
    assert.match(h.api.error.value, /FAQ, строка 1/)
    h.api.form.faq_text = 'Вопрос | Ответ | дополнительная часть'
    assert.equal(h.exposed.validate(), true)
    assert.deepEqual(h.api.payload().faq, [{ question: 'Вопрос', answer: 'Ответ | дополнительная часть' }])
})

test('save failures retain drafts and field errors until a successful retry', async t => {
    const h = harness(t)
    await h.ready()
    h.api.form.h1 = 'Новый H1'
    const save = h.exposed.save()
    h.requests.at(-1).reject({ response: { status: 422, data: { errors: { h1: ['Проверьте H1.'] } } } })
    assert.equal(await save, false)
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.form.h1, 'Новый H1')
    assert.equal(h.api.errorFor('h1'), 'Проверьте H1.')
    const retry = h.exposed.save()
    h.requests.at(-1).resolve(seo({ h1: 'Новый H1' }))
    assert.equal(await retry, true)
    assert.equal(h.api.dirty.value, false)
})

test('unchanged multiline FAQ and literal question delimiters survive unrelated saves and resets losslessly', async t => {
    const h = harness(t)
    const faq = [{ question: 'Свежая | замороженная?', answer: 'Первый ответ\nПродолжение ответа' }, { question: 'Доставка\nпо городам?', answer: 'Да | каждый день' }]
    await h.ready(seo({ faq }))
    h.api.form.meta_title = 'Другой заголовок'
    assert.equal(h.exposed.validate(), true)
    assert.deepEqual(h.api.payload().faq, faq)
    h.api.form.faq_text = 'Другой вопрос | ответ'
    assert.deepEqual(h.api.payload().faq, [{ question: 'Другой вопрос', answer: 'ответ' }])
    h.exposed.reset()
    assert.deepEqual(h.api.payload().faq, faq)
    h.api.form.h1 = 'Другой H1'
    const save = h.exposed.save()
    assert.deepEqual(h.requests.at(-1).body.faq, faq)
    h.requests.at(-1).resolve(seo({ faq, h1: 'Другой H1' }))
    assert.equal(await save, true)
    assert.deepEqual(h.api.payload().faq, faq)
})

test('inactive SEO preview preserves stored search metadata while using the ordinary good address', async t => {
    const h = harness(t)
    await h.ready(seo({ is_active: false }))
    assert.equal(h.api.previewTitle.value, 'Филе форели оптом')
    assert.equal(h.api.previewDescription.value, 'Свежая форель.')
    assert.equal(h.api.previewUrl.value, 'https://example.test/g/trout')
    assert.equal(h.api.indexingLabel.value, 'SEO отключено')
})

test('AI proposals use unsaved context and require explicit insertion; leaving the tab cancels late proposals', async t => {
    const h = harness(t)
    await h.ready()
    h.api.form.focus_keyword = 'Поставка форели'
    const generate = h.api.requestAi('meta_title')
    const request = h.requests.at(-1)
    assert.equal(request.url, '/api/goods/42/seo/generate-ai')
    assert.equal(request.body.context.focus_keyword, 'Поставка форели')
    request.resolve({ field: 'meta_title', value: 'Предложенный заголовок' })
    await generate
    assert.equal(h.api.form.meta_title, 'Филе форели оптом')
    h.api.applyAi()
    assert.equal(h.api.form.meta_title, 'Предложенный заголовок')
    assert.equal(h.requests.filter(request => request.method === 'put').length, 0)
    const stale = h.api.requestAi('h1')
    const oldRequest = h.requests.at(-1)
    h.props.active = false
    assert.equal(oldRequest.options.signal.aborted, true)
    oldRequest.resolve({ field: 'h1', value: 'Устаревший результат' })
    await stale
    assert.equal(h.api.aiDialog.value, false)
    assert.equal(h.api.form.h1, 'Филе форели')
    assert.notEqual(h.api.aiValue.value, 'Устаревший результат')
})

test('generated JSON-LD becomes saved while unrelated text drafts remain dirty', async t => {
    const h = harness(t)
    await h.ready()
    h.api.form.seo_text = 'Новый несохранённый текст'
    const generation = h.api.generateJsonLd()
    assert.equal(h.requests.at(-1).url, '/api/goods/42/seo/generate-structured-data')
    h.requests.at(-1).resolve(seo({ structured_data: { '@type': 'Product', sku: '42' } }))
    await generation
    assert.equal(h.api.form.seo_text, 'Новый несохранённый текст')
    assert.deepEqual(JSON.parse(h.api.form.structured_data_text), { '@type': 'Product', sku: '42' })
    assert.equal(h.api.dirty.value, true)
    h.exposed.reset()
    assert.equal(h.api.form.seo_text, 'Филе для ресторанов.')
    assert.deepEqual(JSON.parse(h.api.form.structured_data_text), { '@type': 'Product', sku: '42' })
})

test('JSON-LD regeneration requires confirmation before replacing an unsaved custom draft', async t => {
    const h = harness(t)
    await h.ready()
    h.api.form.structured_data_text = '{"@type":"Custom","name":"Мой черновик"}'
    const previousConfirm = globalThis.confirm
    globalThis.confirm = () => false
    t.after(() => { if (previousConfirm) globalThis.confirm = previousConfirm; else delete globalThis.confirm })
    const before = h.requests.length
    await h.api.generateJsonLd()
    assert.equal(h.requests.length, before)
    assert.match(h.api.form.structured_data_text, /Мой черновик/)
    globalThis.confirm = () => true
    const generate = h.api.generateJsonLd()
    h.requests.at(-1).resolve(seo({ structured_data: { '@type': 'Product', sku: '42' } }))
    await generate
    assert.deepEqual(JSON.parse(h.api.form.structured_data_text), { '@type': 'Product', sku: '42' })
    assert.equal(h.api.dirty.value, false)
})

test('Direct templates, validation and dry-run retain their separate actions and never launch real ads implicitly', async t => {
    const h = harness(t)
    await h.ready(seo({ yandex_direct_title_1: '', yandex_direct_title_2: '', yandex_direct_text: '', utm_template: '' }), { data: [{ id: 42, direct_ad_id: 99, direct_status: 'draft', stats: { clicks: 4, impressions: 100 } }] })
    assert.equal(h.api.directStats.value.clicks, 4)
    h.api.generateDirectFields()
    assert.equal(h.api.form.yandex_direct_title_1, 'Форель филе')
    assert.match(h.api.form.utm_template, /utm_source=yandex/)
    h.api.form.yandex_direct_title_1 = 'x'.repeat(57)
    assert.equal(h.api.checkDirectLimits(), false)
    assert.equal(await h.api.runDirect('dry-run'), false)
    assert.equal(h.requests.filter(request => request.method === 'post').length, 0)
    h.api.form.yandex_direct_title_1 = 'Форель филе'
    const check = h.api.runDirect('validate')
    assert.equal(h.requests.at(-1).url, '/api/marketing/direct/ads/99/validate')
    h.requests.at(-1).resolve({ ad: { status: 'valid' }, errors: {} })
    assert.equal(await check, true)
    const dryRun = h.api.runDirect('dry-run')
    assert.equal(h.requests.at(-1).method, 'put')
    h.requests.at(-1).resolve({ ...h.api.payload() })
    await settle()
    const launch = h.requests.at(-1)
    assert.equal(launch.url, '/api/marketing/direct/launch/42')
    assert.deepEqual(launch.body, { dry_run: true, budget_approved: false })
    launch.resolve({ status: 'dry_run', message: 'Проверено', warnings: ['Бюджет требует проверки.'] })
    await settle()
    h.requests.at(-1).resolve({ data: [{ id: 42, direct_ad_id: 99, direct_status: 'draft', stats: { clicks: 4 } }] })
    assert.equal(await dryRun, true)
    assert.match(h.api.directMessage.value, /Бюджет требует проверки/)
    assert.equal(h.requests.some(request => request.body?.budget_approved === true), false)
})

test('Direct draft creation and sending use explicit actions; real launch requires affirmative confirmation', async t => {
    const h = harness(t)
    await h.ready()
    const draft = h.api.runDirect('draft')
    await settle()
    assert.equal(h.requests.at(-1).url, '/api/marketing/direct/goods/42/generate-draft')
    h.requests.at(-1).resolve({ id: 17, status: 'draft' })
    await settle()
    h.requests.at(-1).resolve({ data: [{ id: 42, direct_ad_id: 17, direct_status: 'draft' }] })
    assert.equal(await draft, true)
    const send = h.api.runDirect('send')
    await settle()
    assert.equal(h.requests.at(-1).url, '/api/marketing/direct/ads/17/send')
    h.requests.at(-1).resolve({ message: 'Обработано' })
    await settle()
    h.requests.at(-1).resolve({ data: [{ id: 42, direct_ad_id: 17, direct_status: 'sent' }] })
    assert.equal(await send, true)
    const previousConfirm = globalThis.confirm
    globalThis.confirm = () => false
    t.after(() => { if (previousConfirm) globalThis.confirm = previousConfirm; else delete globalThis.confirm })
    const before = h.requests.length
    assert.equal(await h.api.runDirect('launch'), false)
    assert.equal(h.requests.length, before)
    globalThis.confirm = () => true
    const launch = h.api.runDirect('launch')
    await settle()
    assert.deepEqual(h.requests.at(-1).body, { dry_run: false, budget_approved: true })
    h.requests.at(-1).resolve({ status: 'sent' })
    await settle()
    h.requests.at(-1).resolve({ data: [{ id: 42, direct_ad_id: 17, direct_status: 'sent' }] })
    assert.equal(await launch, true)
})
