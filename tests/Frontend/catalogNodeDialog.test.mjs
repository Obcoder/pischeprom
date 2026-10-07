import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { descendantIds } from '../../resources/js/Components/Catalog/tree.js'
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

function harness(t, initialProps = {}, componentName = 'CatalogNodeDialog') {
    const filename = `resources/js/Components/Catalog/${componentName}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: componentName })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: componentName, compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const emitted = []
    const requests = []
    const environment = {
        ...Vue, descendantIds, _mergeModels: Vue.mergeModels,
        // Bridge defineModel to the parent, while running the real component setup
        // and event bindings without a browser or a mounted Vuetify application.
        _useModel: (props, name) => Vue.computed({
            get: () => props[name],
            set: value => { emitted.push([`update:${name}`, value]); props[name] = value },
        }),
        axios: Object.fromEntries(['post', 'patch', 'delete'].map(method => [method, (url, data) => {
            let resolve, reject
            const promise = new Promise((success, failure) => { resolve = success; reject = failure })
            requests.push({ method, url, data, resolve: data => resolve({ data: { data } }), reject })
            return promise
        }])),
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: true, node: null, nodes: [], levels, initialParentId: null, initialLevelId: null, initialEntityType: 'custom', ...initialProps })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { api, props, requests, emitted, render: templateRenderer(template, api, props) }
}
function updateModel(vnode, value) {
    assert.ok(vnode, 'Expected input is rendered')
    const handler = vnode.props['onUpdate:modelValue']
    for (const callback of Array.isArray(handler) ? handler : [handler]) callback(value)
}
function chooseLevel(h, id) {
    updateModel(findVNode(h.render(), node => node.type === 'v-select' && node.props.label === 'Уровень классификации'), id)
}

test('existing goods can use any level, restore archived properties, or remove classification without changing their source', async t => {
    const h = harness(t, { node: sourceNode() })
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

test('dismissal guards dirty form data and busy writes; validation failures keep the form open and retryable', async t => {
    const h = harness(t, { node: sourceNode() })
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
