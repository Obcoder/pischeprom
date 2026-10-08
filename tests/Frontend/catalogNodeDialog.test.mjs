import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { descendantIds } from '../../resources/js/Components/Catalog/tree.js'
import { goodTradeCodeFields, goodTradeCodeValues } from '../../resources/js/utils/goodTradeCodes.js'
import { goodRecordTabs } from '../../resources/js/Components/Catalog/recordTabs.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const levels = [
    { id: 1, name: 'Виды', entity_type: 'category', display_mode: 'tabs', fields: [{ id: 11, key: 'origin', label: 'Происхождение', type: 'text' }] },
    { id: 2, name: 'Сорта', entity_type: 'custom', display_mode: 'tree', fields: [{ id: 21, key: 'grade', label: 'Сорт', type: 'text' }, { id: 22, key: 'weight', label: 'Вес', type: 'number' }] },
    { id: 3, name: 'Домены', entity_type: 'custom', is_domain: true, display_mode: 'tabs', fields: [] },
]
const sourceNode = (changes = {}) => ({
    id: 7, entity_type: 'good', entity_id: 42, level_id: 1, parent_id: 5, name: 'Мука',
    properties: { origin: 'Россия' }, properties_by_level: { 2: { grade: 'Высший', weight: 25 } },
    ...changes,
})

const goodMetadata = { products: [{ id: 12, rus: 'Мука пшеничная', category_id: 1 }], categories: [{ id: 1, name: 'Мука' }], fields: [{ id: 3, title: 'Бакалея' }], countries: [{ id: 1, name: 'Россия' }], vat_rates: [{ id: 2, title: 'Льготная', rate: 10 }] }
const sourceOverview = (changes = {}) => ({ id: 42, denominator: 25, country_id: 1, vat_rate_id: 2, products: [{ id: 12 }], fields: [{ id: 3 }], ...goodTradeCodeValues(), hs_code: '110100', counts: { prices: 3, sales: 5, purchases: 2, media: 8 }, ...changes })
function harness(t, initialProps = {}, componentName = 'CatalogNodeDialog', config = {}) {
    const filename = `resources/js/Components/Catalog/${componentName}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: componentName })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: componentName, compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const emitted = []
    const requests = []
    const reads = []
    const environment = {
        ...Vue, descendantIds, goodTradeCodeFields, goodTradeCodeValues, goodRecordTabs, CatalogGoodOverview: 'CatalogGoodOverview', GoodTradeCodeFields: 'GoodTradeCodeFields',
        CatalogGoodSeo: 'CatalogGoodSeo', CatalogGoodOperations: 'CatalogGoodOperations', CatalogRecordTabs: 'CatalogRecordTabs', _mergeModels: Vue.mergeModels,
        // Bridge defineModel to the parent, while running the real component setup
        // and event bindings without a browser or a mounted Vuetify application.
        _useModel: (props, name) => Vue.computed({
            get: () => props[name],
            set: value => { emitted.push([`update:${name}`, value]); props[name] = value },
        }),
        axios: { get(url, options) {
            if (!config.deferReads) {
                reads.push({ url, options })
                return Promise.resolve({ data: url === '/api/goods' ? goodMetadata : { data: sourceOverview({ id: props.node?.entity_id || 42 }) } })
            }
            let resolve, reject
            const promise = new Promise((success, failure) => { resolve = success; reject = failure })
            reads.push({ url, options, resolve: data => resolve({ data }), reject })
            return promise
        }, ...Object.fromEntries(['post', 'patch', 'delete'].map(method => [method, (url, data) => {
            let resolve, reject
            const promise = new Promise((success, failure) => { resolve = success; reject = failure })
            requests.push({ method, url, data, resolve: data => resolve({ data: { data } }), reject })
            return promise
        }])) },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: true, node: null, nodes: [], levels, initialParentId: null, initialLevelId: null, initialEntityType: 'custom', initialTab: 'overview', ...initialProps })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { api, props, requests, reads, emitted, ready: async () => { await Promise.resolve(); await Vue.nextTick() }, render: templateRenderer(template, api, props) }
}
function updateModel(vnode, value) {
    assert.ok(vnode, 'Expected input is rendered')
    const handler = vnode.props['onUpdate:modelValue']
    for (const callback of Array.isArray(handler) ? handler : [handler]) callback(value)
}
function chooseLevel(h, id) {
    updateModel(findVNode(h.render(), node => node.type === 'v-select' && node.props.label === 'Уровень классификации'), id)
}

test('goods use the full SEO tab regardless of their classification while other entities retain generic metadata', async t => {
    const h = harness(t, { node: sourceNode({ level_id: null, meta_title: 'Устаревшее SEO' }), initialTab: 'seo' })
    await h.ready()
    assert.equal(h.api.activeTab.value, 'seo')
    assert.equal(h.api.seoVisited.value, true)
    assert.equal(findVNode(h.render(), node => node.props?.label === 'Заголовок · Title'), null)
    const saved = h.api.save()
    assert.equal(Object.hasOwn(h.requests[0].data, 'meta_title'), false)
    assert.equal(Object.hasOwn(h.requests[0].data, 'meta_description'), false)
    h.requests[0].resolve(sourceNode())
    await saved
    const other = harness(t, { node: sourceNode({ entity_type: 'custom', entity_id: null, meta_title: 'SEO категории' }) })
    assert.ok(findVNode(other.render(), node => node.props?.label === 'Заголовок · Title'))
})

test('changing tabs preserves the base draft and mounts each tool lazily', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    assert.equal(h.api.seoVisited.value, false)
    assert.equal(h.api.operationsVisited.value, false)
    h.api.form.name = 'Черновик названия'
    h.api.goodForm.denominator = 12
    h.api.selectTab('seo')
    h.api.selectTab('media')
    h.api.selectTab('overview')
    assert.equal(h.api.seoVisited.value, true)
    assert.equal(h.api.operationsVisited.value, true)
    assert.equal(h.api.form.name, 'Черновик названия')
    assert.equal(h.api.goodForm.denominator, 12)
    assert.equal(h.reads.length, 2)
})

test('classification trade codes save the incoming code with leading zeroes and keep unsaved product context', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    h.api.form.name = 'Название до сохранения'
    const codes = findVNode(h.render(), node => node.type === 'GoodTradeCodeFields')
    assert.equal(codes.props.compact, '')
    assert.equal(codes.props.context.name, 'Название до сохранения')
    assert.equal(codes.props.context.id, 42)
    updateModel(codes, { ...h.api.goodForm, incoming_code: '00042-А/7', hs_code: null })
    assert.equal(h.api.dirty.value, true)
    h.api.selectTab('seo')
    h.api.selectTab('overview')
    assert.equal(h.api.goodForm.incoming_code, '00042-А/7')
    const save = h.api.save()
    assert.deepEqual(h.requests[0].data.good, { incoming_code: '00042-А/7', hs_code: null })
    h.requests[0].resolve(sourceNode())
    await save
    assert.equal(h.props.modelValue, false)
})

test('SEO validation precedes base writes and a partial SEO failure remains retryable in the same card', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    let valid = false, successful = false, attempts = 0
    h.api.seoEditor.value = { validate: () => valid, async save() {
        attempts++
        if (successful) h.api.seoState.value.dirty = false
        return successful
    } }
    h.api.seoState.value = { dirty: true, busy: false, ready: true, error: 'SEO: ошибка сервера' }
    await h.api.save()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.activeTab.value, 'seo')
    valid = true
    const first = h.api.save()
    h.requests[0].resolve(sourceNode())
    await first
    assert.equal(h.props.modelValue, true)
    assert.match(h.api.error.value, /Основные данные сохранены, но SEO не сохранено/)
    assert.equal(h.api.seoState.value.dirty, true)
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, true)
    h.api.discardOpen.value = false
    successful = true
    const retry = h.api.save()
    assert.equal(h.requests[1].method, 'patch')
    h.requests[1].resolve(sourceNode())
    await retry
    assert.equal(attempts, 2)
    assert.equal(h.props.modelValue, false)
})

test('unsaved operational drafts cannot be silently discarded by saving the base card', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    h.api.operationsState.value = { dirty: true, busy: false, dirtyTab: 'quotations' }
    await h.api.save()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.activeTab.value, 'quotations')
    assert.equal(h.props.modelValue, true)
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, true)
    h.api.updateOperationsState({ dirty: false, busy: false, dirtyTab: null })
    assert.equal(h.api.error.value, '')
})

test('media changes refresh clean avatar fields without replacing unsaved name or product data', async t => {
    const h = harness(t, { node: sourceNode({ image: '/storage/original.jpg' }) })
    await h.ready()
    h.api.form.name = 'Черновик'
    h.api.goodForm.denominator = 8
    h.api.operationsChanged({ id: 42, ava_image: '/storage/new.jpg', ava_thumb: '/storage/thumb.jpg', media: [{ id: 1 }, { id: 2 }] })
    assert.equal(h.api.form.image, '/storage/new.jpg')
    assert.equal(h.api.record.value.image, '/storage/new.jpg')
    assert.equal(h.api.form.name, 'Черновик')
    assert.equal(h.api.goodForm.denominator, 8)
    assert.equal(h.api.goodOverview.value.counts.media, 2)
    h.api.updateGoodAvatar('https://images.test/draft.jpg')
    h.api.operationsChanged({ id: 42, ava_image: '/storage/other.jpg' })
    assert.equal(h.api.form.image, 'https://images.test/draft.jpg')
})

test('existing goods can use any level, restore archived properties, or remove classification without changing their source', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    const selector = findVNode(h.render(), node => node.type === 'v-select' && node.props.label === 'Уровень классификации')
    assert.deepEqual(selector.props.items.map(level => level.id), [null, 1, 2, 3])
    assert.notEqual(selector.props.disabled, true)
    h.api.form.properties.origin = 'Казахстан'
    chooseLevel(h, 2)
    assert.deepEqual(h.api.form.properties, { grade: 'Высший', weight: 25 })
    h.api.form.properties.grade = 'Первый'
    chooseLevel(h, null)
    assert.equal(h.api.form.level_id, null)
    assert.deepEqual(h.api.form.properties, {})
    chooseLevel(h, 1)
    assert.deepEqual(h.api.form.properties, { origin: 'Казахстан' })
    chooseLevel(h, 2)
    assert.deepEqual(h.api.form.properties, { grade: 'Первый', weight: 25 })
    h.api.form.properties.weight = '12.5'
    const save = h.api.save()
    assert.equal(h.requests[0].method, 'patch')
    assert.equal(h.requests[0].url, '/api/catalog/nodes/7')
    assert.equal(h.requests[0].data.level_id, 2)
    assert.equal(Object.hasOwn(h.requests[0].data, 'entity_type'), false)
    assert.deepEqual(h.requests[0].data.properties, { grade: 'Первый', weight: 12.5 })
    const saved = sourceNode({ level_id: 2, properties: { grade: 'Первый', weight: 12.5 } })
    h.requests[0].resolve(saved)
    await save
    assert.equal(h.props.modelValue, false)
    assert.deepEqual(h.emitted.find(event => event[0] === 'saved'), ['saved', saved])
})

test('empty archived maps remain editable and new records can be saved without any level', async t => {
    const h = harness(t, { node: sourceNode({ level_id: null, properties: {}, properties_by_level: { 1: [] } }) })
    await h.ready()
    chooseLevel(h, 1)
    h.api.form.properties.origin = 'Россия'
    assert.equal(Array.isArray(h.api.form.properties), false)
    assert.equal(h.api.dirty.value, true)
    chooseLevel(h, null)
    const unclassify = h.api.save()
    assert.equal(h.requests[0].data.level_id, null)
    assert.deepEqual(h.requests[0].data.properties, {})
    h.requests[0].resolve(sourceNode({ level_id: null, properties: {} }))
    await unclassify

    const fresh = harness(t, { initialEntityType: 'good', levels: [] })
    await fresh.ready()
    fresh.api.form.name = 'Новый товар'
    const create = fresh.api.save()
    assert.equal(fresh.requests[0].method, 'post')
    assert.equal(fresh.requests[0].data.entity_type, 'good')
    assert.equal(fresh.requests[0].data.level_id, null)
    fresh.requests[0].resolve(sourceNode({ id: 8, level_id: null }))
    await create
    assert.equal(fresh.props.modelValue, false)
})

test('failed image upload preserves the created record ID and retry updates it instead of creating a duplicate', async t => {
    const h = harness(t, { initialEntityType: 'good' })
    await h.ready()
    h.api.form.name = 'Мука'
    h.api.imageFile.value = new Blob(['image'], { type: 'image/png' })
    const save = h.api.save()
    const saved = sourceNode({ id: 19, level_id: null })
    h.requests[0].resolve(saved)
    await Vue.nextTick()
    assert.equal(h.requests[1].url, '/api/catalog/nodes/19/image')
    assert.equal(h.requests[1].data.get('image').type, 'image/png')
    h.requests[1].reject({ response: { data: { message: 'Изображение слишком большое.' } } })
    await save
    assert.equal(h.api.record.value.id, 19)
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.saving.value, false)
    assert.equal(h.api.dirty.value, true)
    assert.match(h.api.error.value, /Запись сохранена, но аватар не загружен/)
    assert.equal(h.emitted.filter(event => event[0] === 'saved').length, 0)
    assert.deepEqual(h.emitted.find(event => event[0] === 'changed'), ['changed', saved])

    const retry = h.api.save()
    assert.equal(h.requests[2].method, 'patch')
    assert.equal(h.requests[2].url, '/api/catalog/nodes/19')
    h.requests[2].resolve(saved)
    await Vue.nextTick()
    const uploaded = { ...saved, image: '/storage/catalog-images/avatar.png' }
    h.requests[3].resolve(uploaded)
    await retry
    assert.equal(h.requests.filter(request => request.url === '/api/catalog/nodes').length, 1)
    assert.equal(h.api.imageFile.value, null)
    assert.equal(h.props.modelValue, false)
    assert.deepEqual(h.emitted.find(event => event[0] === 'saved'), ['saved', uploaded])
})

test('image upload retry keeps the server-confirmed parent after changing a good product relationship', async t => {
    const h = harness(t, { node: sourceNode({ parent_id: 5 }) })
    await h.ready()
    h.api.goodForm.products = [13]
    h.api.imageFile.value = new Blob(['image'], { type: 'image/png' })
    const save = h.api.save()
    assert.deepEqual(h.requests[0].data.good.products, [13])
    const relocated = sourceNode({ parent_id: 99 })
    h.requests[0].resolve(relocated)
    await h.ready()
    h.requests[1].reject({ response: { data: { message: 'Временная ошибка загрузки.' } } })
    await save
    assert.equal(h.api.form.parent_id, 99)
    assert.deepEqual(h.api.goodForm.products, [13])
    const retry = h.api.save()
    assert.equal(h.requests[2].data.parent_id, 99)
    assert.equal(Object.hasOwn(h.requests[2].data, 'good'), false)
    h.requests[2].resolve(relocated)
    await h.ready()
    h.requests[3].resolve({ ...relocated, image: '/new-image.png' })
    await retry
    assert.equal(h.props.modelValue, false)
})

test('dismissal guards dirty form data and busy writes; validation failures keep the form open and retryable', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    assert.equal(h.api.dirty.value, false)
    h.api.form.name = 'Новое название'
    updateModel(findVNode(h.render(), node => node.type === 'v-dialog'), false)
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.discardOpen.value, true)
    h.api.discardOpen.value = false
    const pending = h.api.save()
    updateModel(findVNode(h.render(), node => node.type === 'v-dialog'), false)
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.discardOpen.value, false)
    h.requests[0].reject({ response: { data: { errors: { name: ['Проверьте название.'] } } } })
    await pending
    assert.equal(h.api.form.name, 'Новое название')
    assert.equal(h.api.error.value, 'Проверьте название.')
    assert.equal(h.api.saving.value, false)
    updateModel(findVNode(h.render(), node => node.type === 'v-dialog'), false)
    assert.equal(h.api.discardOpen.value, true)
    h.api.discard()
    assert.equal(h.props.modelValue, false)
})

test('parent choices exclude the current branch and selecting the domain level moves the record to the root', t => {
    const h = harness(t, { node: sourceNode(), nodes: [
        { id: 5, parent_id: null, name: 'Родитель' }, sourceNode(),
        { id: 8, parent_id: 7, name: 'Подвид' }, { id: 9, parent_id: 8, name: 'Экземпляр' },
    ] })
    assert.deepEqual(h.api.parentOptions.value.map(node => node.id), [null, 5])
    chooseLevel(h, 3)
    assert.equal(h.api.form.parent_id, null)
    const parent = findVNode(h.render(), node => node.type === 'v-autocomplete')
    assert.equal(parent.props.disabled, true)
})

test('domain levels allow choosing hierarchical rendering and persist it without changing their business kind', async t => {
    const domain = { ...levels[2], display_mode: 'tree' }
    const h = harness(t, { levels: [domain] }, 'CatalogSchemaDialog')
    h.api.editLevel(domain)
    assert.equal(h.api.levelForm.display_mode, 'tree')
    const modes = findVNode(h.render(), node => node.type === 'v-radio-group')
    assert.equal(modes.props.disabled, false)
    const save = h.api.saveLevel()
    assert.deepEqual(h.requests[0].data, { name: 'Домены', sort_order: 0, display_mode: 'tree', is_domain: true })
    h.requests[0].resolve(domain)
    await save
    assert.equal(h.api.levelEditor.value, false)
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
})

test('schema reload remaps renamed fields by stable ID and refreshes clean values without creating unsaved edits', async t => {
    const original = sourceNode()
    const h = harness(t, { node: original, nodes: [original] })
    await h.ready()
    const renamedLevels = structuredClone(levels)
    renamedLevels[0].fields[0] = { ...renamedLevels[0].fields[0], key: 'country', type: 'textarea' }
    h.props.levels = renamedLevels
    h.props.nodes = [sourceNode({ properties: { country: 'Казахстан' } })]
    await Vue.nextTick()
    assert.deepEqual(h.api.form.properties, { country: 'Казахстан' })
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.form.entity_type, 'good')
    const save = h.api.save()
    assert.deepEqual(h.requests[0].data.properties, { country: 'Казахстан' })
    h.requests[0].resolve(sourceNode({ properties: { country: 'Казахстан' } }))
    await save
})

test('schema reload preserves unsaved current and archived-level properties while refreshing untouched values', async t => {
    const original = sourceNode()
    const h = harness(t, { node: original, nodes: [original] })
    await h.ready()
    h.api.form.properties.origin = 'Свой вариант'
    h.api.form.description = 'Несохранённое описание'
    chooseLevel(h, 2)
    h.api.form.properties.grade = 'Первый'
    chooseLevel(h, 1)
    const renamedLevels = structuredClone(levels)
    renamedLevels[0].fields[0].key = 'country'
    renamedLevels[1].fields[0].key = 'quality'
    h.props.levels = renamedLevels
    h.props.nodes = [sourceNode({ properties: { country: 'Россия' }, properties_by_level: { 2: { quality: 'Высший', weight: 30 } } })]
    await Vue.nextTick()
    assert.deepEqual(h.api.form.properties, { country: 'Свой вариант' })
    assert.equal(h.api.form.description, 'Несохранённое описание')
    assert.equal(h.api.dirty.value, true)
    chooseLevel(h, 2)
    assert.deepEqual(h.api.form.properties, { quality: 'Первый', weight: 30 })
    const save = h.api.save()
    assert.deepEqual(h.requests[0].data.properties, { quality: 'Первый', weight: 30 })
    assert.equal(h.requests[0].data.description, 'Несохранённое описание')
    h.requests[0].resolve(sourceNode({ level_id: 2, properties: { quality: 'Первый', weight: 30 } }))
    await save
})

test('consecutive field renames preserve draft and clean values even before refreshed nodes arrive', async t => {
    const original = sourceNode()
    const h = harness(t, { node: original, nodes: [original] })
    await h.ready()
    for (const key of ['country', 'country_of_origin']) {
        const renamedLevels = structuredClone(levels)
        renamedLevels[0].fields[0].key = key
        h.props.levels = renamedLevels
        await Vue.nextTick()
        assert.deepEqual(h.api.form.properties, { [key]: 'Россия' })
        assert.equal(h.api.dirty.value, false)
    }
    h.props.nodes = [sourceNode({ properties: { country_of_origin: 'Россия' } })]
    await Vue.nextTick()
    assert.deepEqual(h.api.form.properties, { country_of_origin: 'Россия' })
    assert.equal(h.api.dirty.value, false)
})

test('goods load their complete overview lazily and cannot save unresolved defaults', async t => {
    const h = harness(t, { node: sourceNode() }, 'CatalogNodeDialog', { deferReads: true })
    assert.deepEqual(h.reads.map(request => request.url), ['/api/goods', '/api/catalog/nodes/7/overview'])
    assert.deepEqual(h.reads[0].options.params, { view: 'filters' })
    assert.equal(h.api.canSave.value, false)
    await h.api.save()
    assert.equal(h.requests.length, 0)
    h.api.form.description = 'Описание изменено во время загрузки'
    h.reads[0].resolve(goodMetadata)
    await h.ready()
    assert.equal(h.api.canSave.value, false)
    h.reads[1].resolve({ data: sourceOverview() })
    await h.ready()
    assert.equal(h.api.goodReady.value, true)
    assert.equal(h.api.form.description, 'Описание изменено во время загрузки')
    assert.equal(h.api.goodForm.denominator, 25)
    assert.deepEqual(h.api.goodForm.products, [12])
    h.api.goodForm.denominator = 10
    h.api.goodForm.country_id = null
    h.api.goodForm.hs_code = null
    h.api.goodForm.fields = []
    h.api.form.parent_id = 99
    const save = h.api.save()
    assert.equal(h.requests.length, 1)
    assert.deepEqual(h.requests[0].data.good, { denominator: 10, country_id: null, hs_code: null, fields: [] })
    assert.equal(h.requests[0].data.parent_id, 99)
    assert.equal(Object.hasOwn(h.requests[0].data, 'image'), false)
    assert.equal(Object.hasOwn(h.requests[0].data.good, 'products'), false, 'An unchanged product list must not undo an explicit parent move')
    h.requests[0].resolve(sourceNode())
    await save
})

test('a failed overview stays retryable without overwriting catalog edits and late node responses are discarded', async t => {
    const h = harness(t, { node: sourceNode() }, 'CatalogNodeDialog', { deferReads: true })
    h.api.form.name = 'Несохранённое название'
    h.reads[0].resolve(goodMetadata)
    h.reads[1].reject({ response: { data: { message: 'Не удалось получить товар' } } })
    await h.ready()
    assert.equal(h.api.goodError.value, 'Не удалось получить товар')
    assert.equal(h.api.canSave.value, false)
    const retry = h.api.loadGoodOverview()
    h.reads[2].resolve(goodMetadata)
    h.reads[3].resolve({ data: sourceOverview() })
    await retry
    assert.equal(h.api.goodReady.value, true)
    assert.equal(h.api.form.name, 'Несохранённое название')
    const stale = h.api.loadGoodOverview()
    const previous = h.reads.slice(4)
    h.props.node = sourceNode({ id: 8, entity_id: 43, name: 'Другой товар' })
    assert.ok(previous.every(request => request.options.signal.aborted))
    previous[0].resolve(goodMetadata)
    previous[1].resolve({ data: sourceOverview({ denominator: 999 }) })
    await stale
    assert.equal(h.api.goodReady.value, false)
    assert.notEqual(h.api.goodForm.denominator, 999)
    h.reads[6].resolve(goodMetadata)
    h.reads[7].resolve({ data: sourceOverview({ id: 43, denominator: 3 }) })
    await h.ready()
    assert.equal(h.api.goodForm.denominator, 3)
    const pending = h.api.loadGoodOverview()
    h.props.modelValue = false
    h.reads[8].resolve(goodMetadata)
    h.reads[9].resolve({ data: sourceOverview({ id: 43, denominator: 777 }) })
    await pending
    assert.equal(h.api.goodReady.value, false)
    assert.notEqual(h.api.goodForm.denominator, 777)
})

test('good relationship edits share the catalog mutation and validation keeps all drafts open', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    const overview = findVNode(h.render(), node => node.type === 'CatalogGoodOverview')
    updateModel(overview, { ...h.api.goodForm, products: [12, 13], fields: [3, 4], vat_rate_id: null, gtin: '00012345600012' })
    h.api.form.properties.origin = 'Казахстан'
    assert.equal(h.api.dirty.value, true)
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, true)
    const save = h.api.save()
    assert.deepEqual(h.requests[0].data.good, { vat_rate_id: null, gtin: '00012345600012', products: [12, 13], fields: [3, 4] })
    assert.deepEqual(h.requests[0].data.properties, { origin: 'Казахстан' })
    h.requests[0].reject({ response: { data: { errors: { 'good.products': ['Сначала объедините размещения.'], 'good.gtin': ['Некорректный GTIN.'] } } } })
    await save
    assert.equal(h.props.modelValue, true)
    assert.deepEqual(h.api.goodErrors.value.products, ['Сначала объедините размещения.'])
    assert.deepEqual(h.api.goodForm.products, [12, 13])
    assert.equal(h.api.form.properties.origin, 'Казахстан')
})

test('new goods load only options, infer their optional parent product and save extras atomically', async t => {
    const h = harness(t, { initialEntityType: 'good', initialParentId: 11, nodes: [
        { id: 10, entity_type: 'product', entity_id: 12, name: 'Мука' },
        { id: 11, parent_id: 10, entity_type: null, name: 'Высший сорт' },
    ] })
    await h.ready()
    assert.deepEqual(h.reads.map(request => request.url), ['/api/goods'])
    assert.deepEqual(h.api.goodForm.products, [12])
    assert.equal(h.api.dirty.value, false)
    h.api.form.name = 'Новая мука'
    h.api.goodForm.denominator = 25
    h.api.goodForm.fields = [3]
    const save = h.api.save()
    assert.equal(h.requests[0].method, 'post')
    assert.equal(h.requests[0].data.good.denominator, 25)
    assert.deepEqual(h.requests[0].data.good.products, [12])
    assert.deepEqual(h.requests[0].data.good.fields, [3])
    h.requests[0].resolve(sourceNode({ id: 19, parent_id: 11 }))
    await save
})

test('new good parent changes replace inferred product defaults while retaining intentional multi-product choices', async t => {
    const h = harness(t, { initialEntityType: 'good', initialParentId: 10, nodes: [
        { id: 10, entity_type: 'product', entity_id: 12, name: 'Мука' },
        { id: 11, entity_type: 'product', entity_id: 13, name: 'Крупа' },
    ] })
    await h.ready()
    const parent = findVNode(h.render(), node => node.type === 'v-autocomplete' && node.props.label === 'Расположение в каталоге')
    updateModel(parent, 11)
    assert.deepEqual(h.api.goodForm.products, [13])
    updateModel(parent, null)
    assert.deepEqual(h.api.goodForm.products, [])
    h.api.updateGoodForm({ ...h.api.goodForm, products: [14] })
    updateModel(parent, 10)
    assert.deepEqual(h.api.goodForm.products, [14, 12])
    updateModel(parent, 11)
    assert.deepEqual(h.api.goodForm.products, [14, 12, 13])
})

test('reset confirms unsaved good data and restores saved overview without flagging freshly loaded fields dirty', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    assert.equal(h.api.dirty.value, false)
    h.api.goodForm.denominator = 99
    h.api.requestReset()
    assert.equal(h.api.resetOpen.value, true)
    assert.equal(h.api.goodForm.denominator, 99)
    h.api.confirmReset()
    await h.ready()
    assert.equal(h.api.goodForm.denominator, 25)
    assert.equal(h.api.dirty.value, false)
})

test('non-goods never load commerce defaults and retain direct image editing', async t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'product' }) })
    assert.equal(h.reads.length, 0)
    assert.ok(findVNode(h.render(), node => node.type === 'v-text-field' && node.props.label === 'Ссылка на изображение'))
    h.api.form.image = '/storage/product.png'
    const save = h.api.save()
    assert.equal(h.requests[0].data.image, '/storage/product.png')
    assert.equal(Object.hasOwn(h.requests[0].data, 'good'), false)
    h.requests[0].resolve(sourceNode({ entity_type: 'product' }))
    await save
})
