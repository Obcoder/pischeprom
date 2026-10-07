import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { buildCatalogView } from '../../resources/js/Components/Catalog/presentation.js'

// API ordering may differ from toolbar ordering when levels share sort_order.
const levels = [
    { id: 1, name: 'Домены', display_mode: 'tabs', is_domain: true, sort_order: 0 },
    { id: 2, name: 'Б — сорт', display_mode: 'tabs', is_domain: false, sort_order: 1 },
    { id: 3, name: 'А — категория', display_mode: 'tabs', is_domain: false, sort_order: 1 },
    { id: 4, name: 'Группа', display_mode: 'tree', is_domain: false, sort_order: 2 },
    { id: 5, name: 'Объект', display_mode: 'list', is_domain: false, sort_order: 3 },
]
const nodes = [
    { id: 1, parent_id: null, level_id: 1, name: 'Продукция' },
    { id: 2, parent_id: null, level_id: 1, name: 'Пустой домен' },
    { id: 10, parent_id: 1, level_id: 3, name: 'Категория' },
    { id: 30, parent_id: 10, level_id: 4, name: 'Группа над сортом' },
    { id: 20, parent_id: 30, level_id: 2, name: 'Сорт' },
    { id: 40, parent_id: 20, level_id: 5, name: 'Конечный объект' },
]

function harness(t) {
    const filename = 'resources/js/Components/Catalog/CatalogWorkspace.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'catalog-workspace-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'catalog-workspace-test', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    let payload = { nodes: structuredClone(nodes), levels: structuredClone(levels) }
    const requests = []
    const environment = {
        ...Vue, buildCatalogView, onMounted() {},
        CatalogToolbar: {}, CatalogSchemaDialog: {}, CatalogNodeDialog: {},
        axios: { async get(url) { requests.push(url); return { data: structuredClone(payload) } } },
    }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup({}, { expose() {} }))
    t.after(() => scope.stop())
    return { state, requests, setPayload(value) { payload = value } }
}

test('lower toolbar changes preserve preceding selections in rendered order when API levels tie', async t => {
    const { state } = harness(t)
    await state.load()
    state.selectDomain(1)
    state.selectTab(3, 10)
    state.selectTab(2, 20)
    assert.deepEqual(state.selections.value, { 3: 10, 2: 20 })
    state.selectTab(2, null)
    assert.deepEqual(state.selections.value, { 3: 10 })
    state.selectTab(2, 20)
    state.selectTab(3, null)
    assert.deepEqual(state.selections.value, {})
})

test('toolbar add uses the preceding rendered classifier and leaves source type independent', async t => {
    const { state } = harness(t)
    await state.load()
    state.selectDomain(1)
    state.selectTab(3, 10)
    const row = state.view.value.tabRows.find(item => item.levelId === 2)
    state.createForTab(row)
    assert.equal(state.editorOpen.value, true)
    assert.equal(state.editorNode.value, null)
    assert.equal(state.editorContext.parentId, 10)
    assert.equal(state.editorContext.levelId, 2)
    assert.equal(state.editorContext.entityType, 'custom')
})

test('creating from a selected ancestor branch keeps the actual deeper tab scope used by the table', async t => {
    const { state } = harness(t)
    await state.load()
    state.selectDomain(1)
    state.selectTab(3, 10)
    state.selectTab(2, 20)
    state.selectBranch(state.nodes.value.find(node => node.id === 30))
    assert.deepEqual(state.tableItems.value.map(node => node.id), [40])
    assert.equal(state.contextNode.value.id, 20)
    assert.equal(state.currentTitle.value, 'Сорт')
    state.create()
    assert.equal(state.editorContext.parentId, 20)
})

test('empty domains remain creation contexts and changing domain clears prior tab and branch filters', async t => {
    const { state } = harness(t)
    await state.load()
    state.selectDomain(1)
    state.selectTab(3, 10)
    state.selectBranch(state.nodes.value.find(node => node.id === 30))
    state.selectDomain(2)
    assert.deepEqual(state.selections.value, {})
    assert.equal(state.selectedBranchId.value, null)
    assert.equal(state.tableItems.value.length, 0)
    assert.equal(state.contextNode.value.id, 2)
    state.create()
    assert.equal(state.editorContext.parentId, 2)
})

test('reload normalizes selections after a selected record changes classification', async t => {
    const { state, setPayload } = harness(t)
    await state.load()
    state.selectDomain(1)
    state.selectTab(3, 10)
    state.selectTab(2, 20)
    setPayload({ levels, nodes: nodes.map(node => node.id === 20 ? { ...node, level_id: null } : node) })
    await state.load()
    assert.equal(state.view.value.selections[2], null)
    assert.ok(!state.selections.value[2], 'Toolbar must display All after its selected node leaves this level')
    assert.equal(state.selections.value[3], 10)
    setPayload({ levels, nodes: nodes.map(node => node.id === 1 ? { ...node, level_id: null } : node) })
    await state.load()
    assert.equal(state.domainId.value, null, 'Domain selector must clear when the selected node is no longer a domain')
})

test('publication status distinguishes a published object inside a hidden parent branch', async t => {
    const { state, setPayload } = harness(t)
    setPayload({ levels, nodes: nodes.map(node => ({ ...node, is_published: node.id !== 30 })) })
    await state.load()
    assert.equal(state.tableItems.value[0].status_label, 'Скрыт разделом')
    assert.equal(state.tableItems.value[0].visible, false)
    state.nodes.value.find(node => node.id === 30).is_published = true
    assert.equal(state.tableItems.value[0].status_label, 'На сайте')
    state.nodes.value.find(node => node.id === 40).is_published = false
    assert.equal(state.tableItems.value[0].status_label, 'Черновик')
})

test('trout fillet remains inside the left trout class while its goods appear in the right table', async t => {
    const { state, setPayload } = harness(t)
    const troutLevels = [
        { id: 1, name: 'Класс', display_mode: 'tree' },
        { id: 2, name: 'Продукт', entity_type: 'product', display_mode: 'tree' },
        { id: 3, name: 'Товар', entity_type: 'good', display_mode: 'list' },
    ]
    const trout = { id: 10, parent_id: null, level_id: 1, name: 'Форель' }
    const fillet = { id: 20, parent_id: 10, level_id: 2, entity_type: 'product', entity_id: 42, name: 'Форель филе' }
    const goods = [
        { id: 30, parent_id: 20, level_id: 3, entity_type: 'good', name: 'Филе охлаждённое', is_published: true },
        { id: 31, parent_id: 20, level_id: 3, entity_type: 'good', name: 'Филе замороженное', is_published: false },
    ]
    setPayload({ levels: troutLevels, nodes: [trout, fillet, ...goods] })
    await state.load()
    assert.equal(state.view.value.treeRows.find(node => node.id === 10).hasTreeChildren, true)
    state.toggle(trout)
    assert.deepEqual(state.view.value.treeRows.map(node => [node.id, node.depth]), [[10, 0], [20, 1]])
    state.selectBranch(fillet)
    assert.deepEqual(state.tableItems.value.map(node => node.id).sort(), [30, 31])
    assert.equal(state.currentTitle.value, 'Форель филе')
    assert.equal(state.tableItems.value.some(node => node.entity_type === 'product'), false)
    state.publication.value = 'published'
    assert.deepEqual(state.tableItems.value.map(node => node.id), [30])

    state.publication.value = 'all'
    setPayload({ levels: troutLevels, nodes: [trout, fillet] })
    await state.load()
    assert.deepEqual(state.view.value.treeRows.map(node => [node.id, node.depth]), [[10, 0], [20, 1]])
    assert.deepEqual(state.tableItems.value, [])
    assert.equal(state.selectedBranchId.value, 20)
    state.createGood()
    assert.equal(state.editorContext.parentId, 20)
    assert.equal(state.editorContext.entityType, 'good')
})
