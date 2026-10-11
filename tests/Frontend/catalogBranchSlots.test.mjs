import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { catalogBranchSlots } from '../../resources/js/Components/Catalog/branchSlots.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const nodes = [
    { id: 1, parent_id: null, name: 'Корневой раздел', sort_order: 0 },
    { id: 2, parent_id: 1, name: 'Ананас', sort_order: 0 },
    { id: 3, parent_id: 1, name: 'Банан', sort_order: 2 },
    { id: 4, parent_id: 1, name: 'Яблоко', sort_order: 2 },
    { id: 5, parent_id: 9, name: 'Другая ветка', sort_order: 1 },
]

function harness(t, initialProps = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogBranchSlots.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'branch-slots-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'branch-slots-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)({ ...Vue, catalogBranchSlots })
    const props = Vue.reactive({ nodes, parentId: 1, nodeId: null, modelValue: 0, disabled: false, ...initialProps })
    const emitted = []
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    const render = templateRenderer(template, api, props)
    return { api, props, emitted, render, button: order => findVNode(render(), node => node.type === 'button' && node.props['data-order'] === order) }
}

test('slots describe only the current branch and exclude the edited record by normalized ID', () => {
    const branch = catalogBranchSlots(nodes, '1', '2', '0')
    assert.equal(branch.occupiedCount, 1)
    assert.equal(branch.firstFree, 0)
    assert.deepEqual(branch.selectedNames, [])
    assert.deepEqual(branch.items.find(item => item.order === 2).names, ['Банан', 'Яблоко'])
    assert.equal(branch.items.find(item => item.order === 1).names.length, 0)
    const root = catalogBranchSlots(nodes, null, null, 0)
    assert.deepEqual(root.selectedNames, ['Корневой раздел'])
})

test('reactive placement and draft order update accessible slot names and current selection', t => {
    const h = harness(t, { nodeId: 2 })
    assert.equal(h.button(0).props['aria-pressed'], true)
    assert.match(h.button(0).props['aria-label'], /свободен/)
    assert.match(h.button(2).props['aria-label'], /Банан, Яблоко/)
    h.props.parentId = 9
    assert.match(h.button(1).props['aria-label'], /Другая ветка/)
    assert.match(h.button(2).props['aria-label'], /свободен/)
    h.props.modelValue = '1'
    assert.equal(h.button(0).props['aria-pressed'], false)
    assert.equal(h.button(1).props['aria-pressed'], true)
    assert.ok(findVNode(h.render(), node => node.props?.role === 'status'))
    h.props.parentId = null
    assert.match(h.button(0).props['aria-label'], /Корневой раздел/)
    assert.equal(findVNode(h.render(), node => node.props?.role === 'status'), null)
})

test('free and occupied slot clicks emit a numeric order without silently reordering siblings', t => {
    const h = harness(t)
    h.button(1).props.onClick()
    h.button(2).props.onClick()
    assert.deepEqual(h.emitted, [['update:modelValue', 1], ['update:modelValue', 2]])
    assert.equal(h.props.modelValue, 0)
    assert.equal(h.props.nodes[2].sort_order, 2)
    h.props.disabled = true
    assert.equal(h.button(1).props.disabled, true)
    h.button(1).props.onClick()
    assert.equal(h.emitted.length, 2)
})

test('sparse million-scale orders stay bounded and retain current, occupied and first free slots', () => {
    const branch = catalogBranchSlots([
        ...nodes,
        { id: 6, parent_id: 1, name: 'Последний', sort_order: 1000000 },
    ], 1, null, 500000)
    assert.ok(branch.items.length < 40)
    assert.equal(branch.condensed, true)
    assert.equal(branch.items.find(item => item.order === 500000).selected, true)
    assert.deepEqual(branch.items.find(item => item.order === 1000000).names, ['Последний'])
    assert.deepEqual(branch.items.find(item => item.order === branch.firstFree).names, [])
    assert.ok(branch.items.filter(item => item.type === 'slot').every(item => item.order <= 1000000))
    assert.ok(branch.items.filter(item => item.type === 'gap').every(item => item.free + item.occupied === item.to - item.from + 1))
})

test('a dense branch exposes its first free slot and accurately counts collapsed occupied orders', () => {
    const denseNodes = Array.from({ length: 500 }, (_, order) => ({ id: order + 1, parent_id: 1, name: `Запись ${order}`, sort_order: order }))
    const branch = catalogBranchSlots(denseNodes, 1, null, 250)
    assert.equal(branch.firstFree, 500)
    assert.ok(branch.items.length < 40)
    assert.deepEqual(branch.items.find(item => item.order === 500).names, [])
    const visibleOccupied = branch.items.filter(item => item.type === 'slot' && item.names.length).length
    const hiddenOccupied = branch.items.filter(item => item.type === 'gap').reduce((total, item) => total + item.occupied, 0)
    assert.equal(visibleOccupied + hiddenOccupied, 500)
})

test('empty or invalid order drafts do not highlight zero or allocate unbounded slots', () => {
    for (const order of ['', null, undefined, ' ', 'oops', -1, 2.5, Infinity, 1000001]) {
        const branch = catalogBranchSlots([], null, null, order)
        assert.equal(branch.selectedOrder, null)
        assert.equal(branch.firstFree, 0)
        assert.equal(branch.items.some(item => item.selected), false)
        assert.ok(branch.items.length <= 8)
    }
    const branch = catalogBranchSlots([], null, null, 1000000)
    assert.equal(branch.items.find(item => item.order === 1000000).selected, true)
})
