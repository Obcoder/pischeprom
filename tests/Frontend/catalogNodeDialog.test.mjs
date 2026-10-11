import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { catalogAvatarSources } from '../../resources/js/Components/Catalog/catalogAvatarSources.js'
import { descendantIds } from '../../resources/js/Components/Catalog/tree.js'
import { goodTradeCodeFields, goodTradeCodeValues } from '../../resources/js/utils/goodTradeCodes.js'
import { goodRecordTabs, productRecordTabs, catalogRecordTabs, normalizeRecordTab } from '../../resources/js/Components/Catalog/recordTabs.js'
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
goodMetadata.measures = [{ id: 1, name: 'кг' }, { id: 2, name: 'коробка' }]
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
        ...Vue, catalogAvatarSources, CatalogNodeAvatar: 'CatalogNodeAvatar', descendantIds, goodTradeCodeFields, goodTradeCodeValues, goodRecordTabs, productRecordTabs, catalogRecordTabs, normalizeRecordTab, CatalogGoodWarehouse: 'CatalogGoodWarehouse', CatalogProductOperations: 'CatalogProductOperations', CatalogGoodOverview: 'CatalogGoodOverview', GoodTradeCodeFields: 'GoodTradeCodeFields',
        CatalogTranslations: 'CatalogTranslations', CatalogGoodSeo: 'CatalogGoodSeo', CatalogGoodOperations: 'CatalogGoodOperations', CatalogRecordTabs: 'CatalogRecordTabs', CatalogLandingEditor: 'CatalogLandingEditor', CatalogSeoPreview: 'CatalogSeoPreview', CatalogBranchSlots: 'CatalogBranchSlots', _mergeModels: Vue.mergeModels,
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

for (const [entityType, levelId] of [['custom', 3], ['custom', 2], ['custom', null], ['category', 1], ['product', 2]]) {
    test(`avatar can be edited and cleared for ${entityType} at level ${levelId}`, async t => {
        const original = sourceNode({ entity_type: entityType, level_id: levelId, image: '/storage/original.png' })
        const h = harness(t, { node: original })
        const field = findVNode(h.render(), node => node.props?.label === 'Аватар: URL / CDN')
        assert.ok(field)
        updateModel(field, 'https://cdn.example.test/new.png')
        assert.equal(h.api.avatarPreviewSource.value, 'https://cdn.example.test/new.png')
        assert.equal(h.api.cardDirty.value, true)
        const clear = findVNode(h.render(), node => node.props?.onClick === h.api.clearAvatar)
        assert.ok(clear)
        clear.props.onClick()
        assert.equal(h.api.form.image, '')
        assert.equal(h.api.avatarPreviewSource.value, undefined)
        const pending = h.api.save()
        assert.equal(h.requests[0].data.image, '')
        h.requests[0].resolve({ ...original, image: null, thumbnail_url: null })
        await pending
        assert.equal(h.api.cardDirty.value, false)
    })
}

test('avatar upload previews the chosen file and releases the preview when cleared', async t => {
    const created = t.mock.method(URL, 'createObjectURL', () => 'blob:catalog-preview')
    const revoked = t.mock.method(URL, 'revokeObjectURL', () => {})
    const h = harness(t, { node: sourceNode({ entity_type: 'category', image: '/storage/old.png' }) })
    h.api.imageFile.value = new Blob(['image'], { type: 'image/png' })
    await Vue.nextTick()
    assert.equal(created.mock.callCount(), 1)
    assert.equal(h.api.avatarPreviewSource.value, 'blob:catalog-preview')
    assert.equal(findVNode(h.render(), node => node.props?.label === 'Аватар: URL / CDN').props.disabled, true)
    h.api.clearAvatar()
    await Vue.nextTick()
    assert.equal(revoked.mock.calls[0].arguments[0], 'blob:catalog-preview')
    assert.equal(h.api.filePreview.value, '')
    assert.equal(h.api.imageFile.value, null)
})

test('category SVG avatar can be selected, previewed and saved through the image upload', async t => {
    const created = t.mock.method(URL, 'createObjectURL', () => 'blob:catalog-svg-preview')
    const revoked = t.mock.method(URL, 'revokeObjectURL', () => {})
    const original = sourceNode({ entity_type: 'category', image: '/storage/old.png' })
    const h = harness(t, { node: original })
    const input = findVNode(h.render(), node => node.props?.label === 'Загрузить аватар')
    assert.ok(input.props.accept.split(',').includes('image/svg+xml'))
    assert.ok(input.props.accept.split(',').includes('.svg'))
    assert.match(input.props.hint, /SVG до 5 МБ/)
    const content = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>'
    const file = new File([content], 'category.svg', { type: 'image/svg+xml' })
    updateModel(input, [file])
    await Vue.nextTick()
    assert.equal(created.mock.calls[0].arguments[0], file)
    assert.equal(h.api.avatarPreviewSource.value, 'blob:catalog-svg-preview')
    const pending = h.api.save()
    h.requests[0].resolve(original)
    await h.ready()
    const upload = h.requests[1]
    assert.equal(upload.method, 'post')
    assert.equal(upload.url, '/api/catalog/nodes/7/image')
    assert.ok(upload.data instanceof FormData)
    assert.equal(upload.data.get('image').name, 'category.svg')
    assert.equal(upload.data.get('image').type, 'image/svg+xml')
    assert.equal(await upload.data.get('image').text(), content)
    const saved = { ...original, image: '/storage/catalog-images/category.svg' }
    upload.resolve(saved)
    await pending
    await Vue.nextTick()
    assert.equal(h.api.imageFile.value, null)
    assert.equal(h.props.modelValue, false)
    assert.equal(revoked.mock.calls[0].arguments[0], 'blob:catalog-svg-preview')
    assert.deepEqual(h.emitted.find(event => event[0] === 'saved'), ['saved', saved])
})

test('parent choices retain avatars and hierarchy icons without admitting terminal goods', t => {
    const parent = { id: 5, name: 'Категория', entity_type: 'category', image: '/storage/category.png', thumbnail_url: '/storage/category-thumb.png' }
    const h = harness(t, { node: sourceNode({ entity_type: 'custom' }), nodes: [parent, sourceNode()] })
    const picker = findVNode(h.render(), node => node.props?.label === 'Расположение в каталоге')
    const option = h.api.parentOptions.value.find(node => node.id === 5)
    assert.equal(option.thumbnail_url, parent.thumbnail_url)
    assert.equal(h.api.parentOptions.value.some(node => node.entity_type === 'good'), false)
    const item = picker.children.item({ props: { title: option.name }, item: { raw: option } })[0]
    const prepend = item.children.prepend()
    assert.ok(findVNode(prepend, node => node.type === 'v-icon' && node.props.icon === 'mdi-folder-outline'))
    assert.equal(findVNode(prepend, node => node.type === 'CatalogNodeAvatar').props.node.image, parent.image)
    const selected = picker.children.selection({ item: { raw: option, title: option.name } })
    assert.equal(findVNode(selected, node => node.type === 'CatalogNodeAvatar').props.node.image, parent.image)
})

test('existing entity kind is editable, resets, and changes operational tabs only after a successful save', async t => {
    const original = sourceNode({ entity_type: 'product' })
    const h = harness(t, { node: original, initialTab: 'translations' })
    const selector = () => findVNode(h.render(), node => node.props?.label === 'Вид записи')
    assert.equal(selector().props.modelValue, 'product')
    updateModel(selector(), 'category')
    assert.equal(h.api.entityTypeChanged.value, true)
    assert.equal(h.api.isProduct.value, true)
    assert.equal(h.api.dirty.value, true)
    h.api.requestReset()
    h.api.confirmReset()
    assert.equal(selector().props.modelValue, 'product')
    assert.equal(h.api.dirty.value, false)
    updateModel(selector(), 'category')
    const pending = h.api.save('overview')
    assert.equal(h.requests[0].data.entity_type, 'category')
    assert.equal(Object.hasOwn(h.requests[0].data, 'entity_id'), false)
    h.requests[0].resolve({ ...original, entity_type: 'category', entity_id: 81 })
    await pending
    assert.equal(h.api.record.value.id, original.id)
    assert.equal(h.api.record.value.entity_id, 81)
    assert.equal(h.api.isProduct.value, false)
    assert.equal(h.api.productVisited.value, false)
    assert.equal(h.api.activeTab.value, 'overview')
    assert.equal(h.api.dirty.value, false)
})

test('conversion to a good fetches the good overview only after the server creates the new entity', async t => {
    const original = sourceNode({ entity_type: 'custom', entity_id: null })
    const h = harness(t, { node: original })
    updateModel(findVNode(h.render(), node => node.props?.label === 'Вид записи'), 'good')
    assert.equal(h.api.isGood.value, false)
    assert.equal(h.reads.length, 0)
    const pending = h.api.save('overview')
    assert.equal(h.requests[0].data.entity_type, 'good')
    assert.equal(Object.hasOwn(h.requests[0].data, 'good'), false)
    h.requests[0].resolve({ ...original, entity_type: 'good', entity_id: 42 })
    await pending
    assert.equal(h.api.isGood.value, true)
    assert.equal(h.api.goodReady.value, true)
    assert.equal(h.api.dirty.value, false)
    assert.ok(h.reads.some(read => read.url === '/api/catalog/nodes/7/overview'))
})

test('conversion to a good keeps generic SEO and avatar edits from the same draft', async t => {
    const original = sourceNode({ entity_type: 'custom', entity_id: null, h1: 'Старый H1', meta_title: 'Старый Title', image: '/storage/old.png' })
    const h = harness(t, { node: original })
    const draft = { h1: '  Новый H1  ', meta_title: 'Новый Title', meta_description: 'Новое описание поиска', image: 'https://cdn.example.test/new.png' }
    Object.assign(h.api.form, draft)
    updateModel(findVNode(h.render(), node => node.props?.label === 'Вид записи'), 'good')
    const pending = h.api.save()
    for (const [key, value] of Object.entries(draft)) assert.equal(h.requests[0].data[key], value.trim())
    assert.equal(Object.hasOwn(h.requests[0].data, 'good'), false)
    h.requests[0].resolve({ ...original, ...draft, h1: null, entity_type: 'good', entity_id: 42 })
    await pending
    assert.equal(h.api.form.image, draft.image)
    assert.equal(h.api.dirty.value, false)
})

test('conversion from a good leaves current SEO to the server instead of sending hidden stale fields', async t => {
    const original = sourceNode({ h1: null, meta_title: 'Старый Title', meta_description: 'Старое описание' })
    const h = harness(t, { node: original })
    await h.ready()
    updateModel(findVNode(h.render(), node => node.props?.label === 'Вид записи'), 'custom')
    const pending = h.api.save('overview')
    assert.equal(h.requests[0].data.entity_type, 'custom')
    for (const key of ['h1', 'meta_title', 'meta_description', 'good']) assert.equal(Object.hasOwn(h.requests[0].data, key), false)
    const currentSeo = { h1: 'Актуальный H1', meta_title: 'Актуальный Title', meta_description: 'Актуальное описание' }
    h.requests[0].resolve({ ...original, ...currentSeo, entity_type: null, entity_id: null })
    await pending
    for (const [key, value] of Object.entries(currentSeo)) assert.equal(h.api.form[key], value)
    assert.equal(h.api.form.entity_type, 'custom')
    assert.equal(h.api.dirty.value, false)
})

test('failed conversion preserves draft and source identity and displays the server error beside the kind', async t => {
    const original = sourceNode({ entity_type: 'product' })
    const h = harness(t, { node: original })
    updateModel(findVNode(h.render(), node => node.props?.label === 'Вид записи'), 'good')
    const pending = h.api.save()
    h.requests[0].reject({ response: { data: { errors: { entity_type: ['Продукт связан с операциями.'] } } } })
    await pending
    const selector = findVNode(h.render(), node => node.props?.label === 'Вид записи')
    assert.equal(selector.props.modelValue, 'good')
    assert.deepEqual(selector.props['error-messages'], ['Продукт связан с операциями.'])
    assert.equal(h.api.record.value.entity_type, 'product')
    assert.equal(h.api.record.value.entity_id, original.entity_id)
    assert.equal(h.api.isProduct.value, true)
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.reads.length, 0)
})

test('a pending conversion can be reverted without discarding dirty good details or SEO', async t => {
    for (const seo of [false, true]) {
        const h = harness(t, { node: sourceNode() })
        await h.ready()
        const selector = () => findVNode(h.render(), node => node.props?.label === 'Вид записи')
        updateModel(selector(), 'custom')
        if (seo) h.api.seoState.value.dirty = true
        else h.api.goodForm.denominator = 88
        await h.api.save()
        assert.equal(h.requests.length, 0)
        assert.match(h.api.error.value, /Сначала сохраните или сбросьте/)
        assert.equal(selector().props.disabled, false)
        h.api.saving.value = true
        assert.equal(selector().props.disabled, true)
        h.api.saving.value = false
        updateModel(selector(), 'good')
        assert.equal(h.api.entityTypeChanged.value, false)
        assert.equal(selector().props.disabled, true)
        assert.equal(h.api.dirty.value, true)
        if (seo) assert.equal(h.api.seoState.value.dirty, true)
        else {
            const pending = h.api.save()
            assert.equal(h.requests[0].data.good.denominator, 88)
            assert.equal(Object.hasOwn(h.requests[0].data, 'entity_type'), false)
            h.requests[0].resolve(sourceNode())
            await pending
        }
    }
})

test('a good avatar URL edited after choosing conversion is retained when returning to the good kind', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    const selector = () => findVNode(h.render(), node => node.props?.label === 'Вид записи')
    updateModel(selector(), 'custom')
    h.api.updateGoodAvatar('https://cdn.example.test/new-good.png')
    await h.api.save()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.form.image, 'https://cdn.example.test/new-good.png')
    assert.equal(selector().props.disabled, false)
    updateModel(selector(), 'good')
    const pending = h.api.save()
    assert.equal(h.requests[0].data.good.avatar_source_url, 'https://cdn.example.test/new-good.png')
    assert.equal(Object.hasOwn(h.requests[0].data, 'image'), false)
    h.requests[0].resolve(sourceNode({ image: 'https://cdn.example.test/new-good.png' }))
    await pending
})

test('a selected good avatar file survives conversion and a failed upload can be retried', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    const image = new Blob(['draft avatar'], { type: 'image/png' })
    h.api.imageFile.value = image
    updateModel(findVNode(h.render(), node => node.props?.label === 'Вид записи'), 'custom')
    const pending = h.api.save()
    const converted = sourceNode({ entity_type: null, entity_id: null })
    h.requests[0].resolve(converted)
    await h.ready()
    assert.equal(h.requests[1].url, '/api/catalog/nodes/7/image')
    assert.equal(await h.requests[1].data.get('image').text(), 'draft avatar')
    h.requests[1].reject({ response: { data: { message: 'Повторите загрузку.' } } })
    await pending
    assert.equal(h.api.imageFile.value, image)
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.dirty.value, true)
    assert.equal(h.api.entityTypeChanged.value, false)
    const retry = h.api.save()
    assert.equal(Object.hasOwn(h.requests[2].data, 'entity_type'), false)
    h.requests[2].resolve(converted)
    await h.ready()
    assert.equal(await h.requests[3].data.get('image').text(), 'draft avatar')
    h.requests[3].resolve({ ...converted, image: '/storage/new-avatar.png' })
    await retry
    assert.equal(h.api.imageFile.value, null)
    assert.equal(h.props.modelValue, false)
})

test('catalog location excludes all goods and own descendants but keeps products and unclassified parents', t => {
    const original = sourceNode({ entity_type: 'product', parent_id: null })
    const nodes = [original,
        { id: 1, entity_type: 'category', name: 'Раздел', parent_id: null },
        { id: 2, entity_type: 'product', name: 'Продукт', parent_id: 1 },
        { id: 3, entity_type: 'good', name: 'Товар', parent_id: 2 },
        { id: 4, entity_type: null, name: 'Свой уровень', parent_id: 1 },
        { id: 8, entity_type: 'category', name: 'Потомок', parent_id: original.id },
        { id: 9, entity_type: 'custom', name: 'Вложенный потомок', parent_id: 8 },
    ]
    for (const node of [original, null]) {
        const h = harness(t, { node, nodes })
        const parent = findVNode(h.render(), vnode => vnode.props?.label === 'Расположение в каталоге')
        const ids = parent.props.items.map(item => item.id)
        for (const id of [null, 1, 2, 4]) assert.ok(ids.includes(id))
        assert.equal(ids.includes(3), false)
        if (node) for (const id of [7, 8, 9]) assert.equal(ids.includes(id), false)
    }
})

test('slot selection updates saved order and follows a changed branch', async t => {
    const original = sourceNode({ entity_type: 'product', sort_order: 0 })
    const h = harness(t, { node: original })
    const slots = () => findVNode(h.render(), node => node.type === 'CatalogBranchSlots')
    assert.equal(slots().props['parent-id'], original.parent_id)
    assert.equal(slots().props['node-id'], original.id)
    updateModel(slots(), 3)
    assert.equal(h.api.form.sort_order, 3)
    h.api.form.parent_id = null
    assert.equal(slots().props['parent-id'], null)
    const pending = h.api.save()
    assert.equal(h.requests[0].data.sort_order, 3)
    assert.equal(h.requests[0].data.parent_id, null)
    h.requests[0].resolve({ ...original, sort_order: 3, parent_id: null })
    await pending
})

test('goods use the full SEO tab regardless of their classification while other entities retain generic metadata', async t => {
    const h = harness(t, { node: sourceNode({ level_id: null, h1: 'Устаревший H1', meta_title: 'Устаревшее SEO' }), initialTab: 'seo' })
    await h.ready()
    assert.equal(h.api.activeTab.value, 'seo')
    assert.equal(h.api.seoVisited.value, true)
    assert.equal(findVNode(h.render(), node => node.props?.label === 'Заголовок · Title'), null)
    assert.equal(findVNode(h.render(), node => node.props?.label === 'SEO H1'), null)
    const saved = h.api.save()
    assert.equal(Object.hasOwn(h.requests[0].data, 'meta_title'), false)
    assert.equal(Object.hasOwn(h.requests[0].data, 'meta_description'), false)
    assert.equal(Object.hasOwn(h.requests[0].data, 'h1'), false)
    h.requests[0].resolve(sourceNode())
    await saved
    const other = harness(t, { node: sourceNode({ entity_type: 'custom', entity_id: null, meta_title: 'SEO категории' }) })
    assert.ok(findVNode(other.render(), node => node.props?.label === 'Заголовок · Title'))
})

test('SEO H1 loads for every classification level and non-good entity without making the card dirty', t => {
    for (const entity_type of ['category', 'product', 'custom']) {
        for (const level_id of [null, ...levels.map(level => level.id)]) {
            const h = harness(t, { node: sourceNode({ entity_type, level_id, h1: 'Мука оптом с доставкой' }) })
            const input = findVNode(h.render(), node => node.props?.label === 'SEO H1')
            assert.ok(input)
            assert.equal(input.props.modelValue, 'Мука оптом с доставкой')
            assert.equal(input.props.maxlength, '255')
            assert.match(input.props.hint, /заголовок лендинга или название записи/)
            assert.equal(h.api.dirty.value, false)
            assert.equal(h.reads.length, 0)
        }
    }
})

test('editing and resetting SEO H1 preserves the record name and address, and saves the confirmed value', async t => {
    const original = sourceNode({ entity_type: 'product', slug: 'muka', h1: 'Мука оптом' })
    const h = harness(t, { node: original })
    const input = () => findVNode(h.render(), node => node.props?.label === 'SEO H1')
    updateModel(input(), 'Черновик H1')
    assert.equal(h.api.dirty.value, true)
    h.api.requestReset()
    assert.equal(h.api.resetOpen.value, true)
    h.api.confirmReset()
    assert.equal(input().props.modelValue, 'Мука оптом')
    assert.equal(h.api.dirty.value, false)
    updateModel(input(), '  Мука с доставкой  ')
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, true)
    h.api.discardOpen.value = false
    const pending = h.api.save('landing')
    assert.equal(h.requests[0].data.h1, 'Мука с доставкой')
    assert.equal(h.requests[0].data.name, 'Мука')
    assert.equal(h.requests[0].data.slug, 'muka')
    h.requests[0].resolve({ ...original, h1: 'Мука с доставкой' })
    await pending
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.form.h1, 'Мука с доставкой')
    assert.equal(h.api.dirty.value, false)
})

test('empty or missing SEO H1 values render blank and clearing an existing heading sends null', async t => {
    for (const h1 of [null, undefined]) {
        const h = harness(t, { node: sourceNode({ entity_type: 'category', h1 }) })
        assert.equal(findVNode(h.render(), node => node.props?.label === 'SEO H1').props.modelValue, '')
        assert.equal(h.api.dirty.value, false)
    }
    for (const cleared of ['', null, '   ']) {
        const original = sourceNode({ entity_type: 'category', h1: 'Сохранённый H1' })
        const h = harness(t, { node: original })
        updateModel(findVNode(h.render(), node => node.props?.label === 'SEO H1'), cleared)
        assert.equal(h.api.dirty.value, true)
        const pending = h.api.save()
        assert.equal(h.requests[0].data.h1, null)
        h.requests[0].resolve({ ...original, h1: null })
        await pending
        assert.equal(h.props.modelValue, false)
        assert.equal(h.api.form.h1, '')
        assert.equal(h.api.dirty.value, false)
    }
})

test('SEO H1 validation errors remain beside the field without discarding the heading draft', async t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'custom', h1: 'Исходный H1' }) })
    updateModel(findVNode(h.render(), node => node.props?.label === 'SEO H1'), 'Новый H1')
    h.api.selectTab('landing')
    const pending = h.api.save()
    h.requests[0].reject({ response: { data: { errors: { h1: ['Заголовок не должен быть длиннее 255 символов.'] } } } })
    await pending
    const input = findVNode(h.render(), node => node.props?.label === 'SEO H1')
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.activeTab.value, 'overview')
    assert.equal(input.props.modelValue, 'Новый H1')
    assert.deepEqual(input.props['error-messages'], ['Заголовок не должен быть длиннее 255 символов.'])
    assert.equal(h.api.dirty.value, true)
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

test('landing is shared by all levels and has independent dirty and busy guards', async t => {
    for (const entity_type of ['custom', 'category', 'product', 'good']) {
        const h = harness(t, { node: sourceNode({ entity_type, level_id: null }), initialTab: 'landing' })
        await h.ready()
        assert.equal(h.api.activeTab.value, 'landing')
        assert.equal(h.api.mainTab.value, false)
        assert.equal(h.api.operationsTab.value, false)
        assert.equal(h.api.operationsVisited.value, false, 'Landing never initializes commerce tools')
        assert.ok(findVNode(h.render(), node => node.type === 'CatalogLandingEditor'))
        h.api.updateLandingState({ dirty: true, busy: false })
        assert.equal(h.api.dirty.value, true)
        h.api.selectTab('overview')
        await h.api.save()
        assert.equal(h.requests.length, 0, 'Main save cannot implicitly publish or discard a landing draft')
        assert.equal(h.api.activeTab.value, 'landing')
        h.api.requestClose()
        assert.equal(h.api.discardOpen.value, true)
        h.api.discardOpen.value = false
        h.api.updateLandingState({ dirty: false, busy: true })
        h.api.requestClose()
        assert.equal(h.props.modelValue, true)
        assert.equal(h.api.discardOpen.value, false)
        assert.equal(h.api.canSave.value, false)
    }
})

test('a new catalog record can be created while staying on its landing tab', async t => {
    const h = harness(t, { initialTab: 'landing' })
    assert.equal(h.api.activeTab.value, 'landing')
    assert.equal(findVNode(h.render(), node => node.type === 'CatalogLandingEditor'), null)
    h.api.form.name = 'Новый раздел'
    const pending = h.api.save('landing')
    h.requests[0].resolve(sourceNode({ entity_type: 'custom', entity_id: null, name: 'Новый раздел' }))
    await pending
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.activeTab.value, 'landing')
    assert.ok(findVNode(h.render(), node => node.type === 'CatalogLandingEditor'))
})

test('reset confirms both card and landing drafts together and preserves the selected landing tab', async t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'product' }), initialTab: 'landing' })
    h.api.form.name = 'Несохранённое имя'
    h.api.updateLandingState({ dirty: true, busy: false })
    let resets = 0
    h.api.landingEditor.value = { reset() { resets++; h.api.updateLandingState({ dirty: false, busy: false }) } }
    h.api.requestReset()
    assert.equal(h.api.resetOpen.value, true)
    assert.equal(resets, 0)
    h.api.confirmReset()
    assert.equal(resets, 1)
    assert.equal(h.api.form.name, 'Мука')
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.activeTab.value, 'landing')
    assert.equal(h.api.landingVisited.value, true)
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
    assert.equal(h.api.activeTab.value, 'market')
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
    assert.equal(h.api.goodForm.measure_id, null)
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
    assert.equal(h.api.goodForm.measure_id, 1)
    assert.equal(h.api.dirty.value, false)
    h.api.form.name = 'Новая мука'
    h.api.goodForm.denominator = 25
    h.api.goodForm.fields = [3]
    const save = h.api.save()
    assert.equal(h.requests[0].method, 'post')
    assert.equal(h.requests[0].data.good.denominator, 25)
    assert.equal(h.requests[0].data.good.measure_id, 1)
    assert.deepEqual(h.requests[0].data.good.products, [12])
    assert.deepEqual(h.requests[0].data.good.fields, [3])
    h.requests[0].resolve(sourceNode({ id: 19, parent_id: 11 }))
    await save
})

test('existing goods retain unset units until explicitly configured and save unit weight with the record', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    assert.equal(h.api.goodForm.measure_id, null)
    assert.equal(h.api.dirty.value, false)
    h.api.goodForm.measure_id = 2
    h.api.goodForm.unit_weight_kg = 10
    const save = h.api.save()
    assert.deepEqual(h.requests[0].data.good, { measure_id: 2, unit_weight_kg: 10, existing_price_basis: 'selected_unit' })
    h.requests[0].resolve(sourceNode())
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
    assert.ok(findVNode(h.render(), node => node.type === 'v-text-field' && node.props.label === 'Аватар: URL / CDN'))
    h.api.form.image = '/storage/product.png'
    const save = h.api.save()
    assert.equal(h.requests[0].data.image, '/storage/product.png')
    assert.equal(Object.hasOwn(h.requests[0].data, 'good'), false)
    h.requests[0].resolve(sourceNode({ entity_type: 'product' }))
    await save
})

test('warehouse shares the card draft, supports new goods and directs unit validation to its editor', async t => {
    const h = harness(t, { initialEntityType: 'good', initialTab: 'warehouse' })
    await h.ready()
    assert.equal(h.api.warehouseTab.value, true)
    assert.equal(h.api.operationsVisited.value, false)
    const warehouse = findVNode(h.render(), node => node.type === 'CatalogGoodWarehouse')
    assert.ok(warehouse)
    assert.equal(warehouse.props['good-id'], null)
    updateModel(warehouse, { ...h.api.goodForm, measure_id: 2, unit_weight_kg: 12 })
    h.api.form.name = 'Новый товар'
    h.api.selectTab('overview')
    h.api.selectTab('warehouse')
    assert.equal(h.api.goodForm.unit_weight_kg, 12)
    assert.equal(h.api.dirty.value, true)
    const pending = h.api.save()
    assert.equal(h.requests[0].data.good.measure_id, 2)
    h.requests[0].reject({ response: { data: { errors: { 'good.measure_id': ['Остатки в другой единице'] } } } })
    await pending
    assert.equal(h.api.activeTab.value, 'warehouse')
    assert.deepEqual(h.api.goodErrors.value.measure_id, ['Остатки в другой единице'])
    assert.equal(h.api.goodForm.unit_weight_kg, 12)
})

test('warehouse movement drafts block card save and busy mutations block close', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    h.api.updateWarehouseState({ dirty: true, busy: false })
    await h.api.save()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.activeTab.value, 'warehouse')
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, true)
    h.api.discardOpen.value = false
    h.api.updateWarehouseState({ dirty: true, busy: true })
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, false)
    h.api.updateWarehouseState({ dirty: false, busy: false })
    assert.equal(h.api.error.value, '')
})

test('product tools use entity ID at every classification level and preserve unsaved translation drafts', async t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'product', level_id: null }), initialTab: 'consumers' })
    assert.equal(h.reads.length, 0)
    assert.deepEqual(h.api.recordTabs.value.map(tab => tab.id), productRecordTabs.map(tab => tab.id))
    const child = findVNode(h.render(), node => node.type === 'CatalogProductOperations')
    assert.equal(child.props['product-id'], 42)
    assert.equal(child.props['active-tab'], 'consumers')
    assert.equal(h.api.operationsVisited.value, false)
    child.props.onState({ dirty: true, busy: false, dirtyTab: 'translations' })
    h.api.selectTab('overview')
    await h.api.save()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.activeTab.value, 'translations')
    assert.equal(h.api.dirty.value, true)
    let resets = 0
    h.api.productOperations.value = { reset() { resets++; h.api.updateProductState({ dirty: false, busy: false }) } }
    h.api.confirmReset()
    assert.equal(resets, 1)
    assert.equal(h.api.dirty.value, false)
})

test('saved product translations update a clean name without overwriting another tab name draft', t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'product' }) })
    h.api.productChanged({ id: 42, rus: 'Обновлённый продукт' })
    assert.equal(h.api.form.name, 'Обновлённый продукт')
    assert.equal(h.api.record.value.name, 'Обновлённый продукт')
    assert.equal(h.api.dirty.value, false)
    h.api.form.name = 'Мой черновик'
    h.api.productChanged({ id: 42, rus: 'Сохранённый перевод' })
    assert.equal(h.api.form.name, 'Мой черновик')
    assert.equal(JSON.parse(h.api.baseline.value).name, 'Сохранённый перевод')
    assert.equal(h.api.dirty.value, true)
})

test('every entity kind and classification uses the shared node translations editor', async t => {
    for (const entity_type of ['custom', 'category', 'product', 'good']) {
        for (const level_id of [null, 1, 2, 3]) {
            const node = sourceNode({ entity_type, level_id })
            const h = harness(t, { node, initialTab: 'translations' })
            await h.ready()
            const editor = findVNode(h.render(), vnode => vnode.type === 'CatalogTranslations')
            assert.equal(editor.props.node.id, node.id)
            assert.equal(editor.props.active, true)
            assert.equal(h.api.productVisited.value, false)
            assert.equal(h.api.operationsVisited.value, false)
            assert.equal(findVNode(h.render(), vnode => vnode.type === 'CatalogProductOperations'), null)
            h.api.selectTab('overview')
            assert.equal(findVNode(h.render(), vnode => vnode.type === 'CatalogTranslations').props.active, false)
        }
    }
})

test('shared translation drafts block card writes, conversion and silent close, and reset cleanly', async t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'category' }), initialTab: 'translations' })
    const editor = findVNode(h.render(), node => node.type === 'CatalogTranslations')
    editor.props.onState({ dirty: true, busy: false, ready: true, error: '' })
    h.api.selectTab('overview')
    assert.equal(findVNode(h.render(), node => node.props?.label === 'Вид записи').props.disabled, true)
    await h.api.save()
    assert.equal(h.requests.length, 0)
    assert.equal(h.api.activeTab.value, 'translations')
    assert.match(h.api.error.value, /Переводы/)
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, true)
    h.api.discardOpen.value = false
    editor.props.onState({ dirty: true, busy: true, ready: true, error: '' })
    h.api.requestClose()
    assert.equal(h.api.discardOpen.value, false)
    let resets = 0
    h.api.translationsEditor.value = { reset() { resets++; h.api.updateTranslationsState({ dirty: false, busy: false, ready: true, error: '' }) } }
    h.api.confirmReset()
    assert.equal(resets, 1)
    assert.equal(h.api.dirty.value, false)
    assert.equal(h.api.error.value, '')
})

test('saved shared translations refresh Russian names while retaining a separate card draft', t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'custom' }), initialTab: 'translations' })
    const saved = sourceNode({ entity_type: 'custom', name: 'Новое название', translations: { ru: 'Новое название', en: 'Flour' } })
    h.api.translationsSaved(saved)
    assert.equal(h.api.form.name, saved.name)
    assert.equal(h.api.record.value.translations.en, 'Flour')
    assert.equal(h.api.dirty.value, false)
    h.api.form.name = 'Мой черновик'
    h.api.translationsSaved({ ...saved, name: 'Сохранённое название' })
    assert.equal(h.api.form.name, 'Мой черновик')
    assert.equal(JSON.parse(h.api.baseline.value).name, 'Сохранённое название')
    assert.equal(h.api.dirty.value, true)
    h.api.translationsSaved({ ...saved, id: 999, name: 'Другая запись' })
    assert.equal(h.api.record.value.name, 'Сохранённое название')
    assert.equal(h.emitted.filter(event => event[0] === 'changed').length, 2)
})

test('product conversion retains the shared translations tab with the same node identity', async t => {
    const original = sourceNode({ entity_type: 'product', translations: { ru: 'Мука', en: 'Flour' } })
    const h = harness(t, { node: original, initialTab: 'translations' })
    h.api.selectTab('overview')
    updateModel(findVNode(h.render(), node => node.props?.label === 'Вид записи'), 'category')
    const pending = h.api.save('translations')
    h.requests[0].resolve({ ...original, entity_type: 'category', entity_id: 81 })
    await pending
    const editor = findVNode(h.render(), node => node.type === 'CatalogTranslations')
    assert.equal(editor.props.node.id, original.id)
    assert.equal(editor.props.node.translations.en, 'Flour')
    assert.equal(h.api.activeTab.value, 'translations')
    assert.equal(h.api.dirty.value, false)
})

test('old goods deep links resolve to their combined tabs without exposing product tools on custom records', async t => {
    for (const [initialTab, expected] of [['quotations', 'market'], ['purchases', 'warehouse'], ['recommendations', 'sales']]) {
        const h = harness(t, { node: sourceNode(), initialTab })
        await h.ready()
        assert.equal(h.api.activeTab.value, expected)
    }
    const custom = harness(t, { node: sourceNode({ entity_type: 'custom', level_id: 2 }), initialTab: 'translations' })
    assert.deepEqual(custom.api.recordTabs.value.map(tab => tab.id), ['overview', 'translations', 'landing'])
    assert.equal(custom.api.activeTab.value, 'translations')
    assert.equal(findVNode(custom.render(), node => node.type === 'CatalogProductOperations'), null)
})

test('confirmed product names reach the translations editor before a partial avatar failure', async t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'product' }), initialTab: 'translations' })
    const synced = []
    h.api.translationsEditor.value = { syncName: name => synced.push(name) }
    h.api.selectTab('overview')
    h.api.form.name = 'Новое название продукта'
    h.api.imageFile.value = new Blob(['image'], { type: 'image/png' })
    const pending = h.api.save('landing')
    h.requests[0].resolve(sourceNode({ entity_type: 'product', name: 'Новое название продукта' }))
    await h.ready()
    assert.deepEqual(synced, ['Новое название продукта'])
    assert.equal(h.requests[1].url, '/api/catalog/nodes/7/image')
    h.requests[1].reject({ response: { data: { message: 'Изображение не загружено' } } })
    await pending
    assert.equal(h.props.modelValue, true)
    assert.equal(h.api.record.value.name, 'Новое название продукта')
})

test('user tab navigation is locked during writes while validation may still select its target', async t => {
    const h = harness(t, { node: sourceNode() })
    await h.ready()
    h.api.saving.value = true
    const tabs = findVNode(h.render(), node => node.type === 'CatalogRecordTabs')
    assert.equal(tabs.props.disabled, true)
    updateModel(tabs, 'sales')
    assert.equal(h.api.activeTab.value, 'overview')
    h.api.selectTab('warehouse')
    assert.equal(h.api.activeTab.value, 'warehouse')
    h.api.saving.value = false
    updateModel(tabs, 'sales')
    assert.equal(h.api.activeTab.value, 'sales')
})


test('products open the market deep link and expose the shared tools with the entity ID', t => {
    const h = harness(t, { node: sourceNode({ entity_type: 'product', level_id: null }), initialTab: 'market' })
    assert.equal(h.api.activeTab.value, 'market')
    assert.equal(h.api.recordTabs.value.find(tab => tab.id === 'market').label, 'Маркет')
    const child = findVNode(h.render(), node => node.type === 'CatalogProductOperations')
    assert.equal(child.props['product-id'], 42)
    assert.equal(child.props['active-tab'], 'market')
    assert.equal(child.props.active, true)
})

test('domain hosts are editable only on Domain records and normalized server values survive save', async t => {
    const domain = sourceNode({ entity_type: 'custom', entity_id: null, level_id: 3, parent_id: null, domain_hosts: ['food.test'] })
    const h = harness(t, { node: domain })
    const hosts = findVNode(h.render(), node => node.type === 'v-combobox' && node.props.label === 'Интернет-адреса этого Домена')
    assert.deepEqual(hosts.props.modelValue, ['food.test'])
    updateModel(hosts, ['food.test', 'ПИЩЕПРОМ-СЕРВЕР.РФ'])
    const pending = h.api.save()
    assert.deepEqual(h.requests[0].data.domain_hosts, ['food.test', 'ПИЩЕПРОМ-СЕРВЕР.РФ'])
    h.requests[0].resolve({ ...domain, domain_hosts: ['food.test', 'xn----dtbhbbn3apgclecj7i.xn--p1ai'] })
    await pending
    assert.deepEqual(h.api.form.domain_hosts, ['food.test', 'xn----dtbhbbn3apgclecj7i.xn--p1ai'])
    const category = harness(t, { node: sourceNode({ entity_type: 'category' }) })
    assert.equal(findVNode(category.render(), node => node.type === 'v-combobox' && node.props.label === 'Интернет-адреса этого Домена'), null)
    const saving = category.api.save()
    assert.equal(Object.hasOwn(category.requests[0].data, 'domain_hosts'), false)
    category.requests[0].resolve(sourceNode({ entity_type: 'category' }))
    await saving
})
