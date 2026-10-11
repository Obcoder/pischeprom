import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as categories from '../../resources/js/utils/catalogCategoryFilter.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const nodes = [
    { id: 10, parent_id: null, name: 'Рыба', goods_count: 24 },
    { id: 11, parent_id: 10, name: 'Охлаждённая рыба', goods_count: 12 },
    { id: 12, parent_id: 11, name: 'Сёмга', goods_count: 5 },
    { id: 13, parent_id: 11, name: 'Карп', goods_count: 7 },
    { id: 20, parent_id: null, name: 'Зерновое сырьё', goods_count: 6 },
]

function harness(t, extra = {}) {
    const filename = 'resources/js/Components/Goods/PublicCatalogCategoryFilter.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'catalog-category-filter' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'catalog-category-filter', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const emitted = []
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)({ ...Vue, ...categories, CatalogNodeAvatar: 'CatalogNodeAvatar' })
    const props = Vue.reactive({ nodes, selectedId: '', ...extra })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { props, api, emitted, render: templateRenderer(template, api, props, { $emit: (...args) => emitted.push(args) }) }
}

test('category filter starts with compact root rows and expands branches without choosing a filter', t => {
    const h = harness(t)
    assert.deepEqual(h.api.rows.value.map(row => row.node.id), [10, 20])
    const expand = findVNode(h.render(), vnode => vnode.props?.['aria-label'] === 'Раскрыть категорию Рыба')
    assert.equal(expand.props['aria-expanded'], false)
    expand.props.onClick()
    assert.deepEqual(h.api.rows.value.map(row => row.node.id), [10, 11, 20])
    assert.equal(h.emitted.length, 0)
    const selected = findVNode(h.render(), vnode => hasClass(vnode, 'catalog-category-filter__select') && vnode.props.title === 'Охлаждённая рыба')
    selected.props.onClick()
    assert.deepEqual(h.emitted, [['select', 11]])
    const collapse = findVNode(h.render(), vnode => vnode.props?.['aria-label'] === 'Свернуть категорию Рыба')
    collapse.props.onClick()
    assert.deepEqual(h.api.rows.value.map(row => row.node.id), [10, 20])
})

test('selected category ancestors are expanded for server render and restored after navigation', async t => {
    const h = harness(t, { selectedId: '12' })
    assert.deepEqual(h.api.rows.value.map(row => row.node.id), [10, 11, 12, 13, 20])
    const selected = findVNode(h.render(), vnode => hasClass(vnode, 'catalog-category-filter__select') && vnode.props.title === 'Сёмга')
    assert.equal(selected.props['aria-pressed'], true)
    h.api.toggle('10')
    h.props.selectedId = 13
    await Vue.nextTick()
    assert.ok(h.api.expanded.value.has('10'))
    assert.equal(h.api.selectedName.value, 'Карп')
    assert.equal(h.emitted.length, 0)
    const clear = findVNode(h.render(), vnode => hasClass(vnode, 'catalog-category-filter__all'))
    assert.equal(clear.props['aria-pressed'], false)
    clear.props.onClick()
    assert.deepEqual(h.emitted, [['select', '']])
})

test('category search ignores Russian case and ё, reveals ancestors, and leaves product filters untouched', async t => {
    const h = harness(t)
    const input = findVNode(h.render(), vnode => vnode.props?.['aria-label'] === 'Найти категорию')
    input.props['onUpdate:modelValue']('  СЕМГА  ')
    await Vue.nextTick()
    assert.deepEqual(h.api.rows.value.map(row => row.node.id), [10, 11, 12])
    assert.equal(h.api.search.value.matches.size, 1)
    assert.deepEqual(h.api.rows.value.filter(row => row.matched).map(row => row.node.name), ['Сёмга'])
    assert.equal(h.emitted.length, 0)
    const clear = findVNode(h.render(), vnode => vnode.props?.['aria-label'] === 'Очистить поиск категорий')
    clear.props.onClick()
    await Vue.nextTick()
    assert.equal(h.api.query.value, '')
    assert.equal(h.api.search.value, null)
    assert.ok(h.api.rows.value.some(row => row.node.id === 20))
})

test('empty category search gives feedback while keeping the selected category visible as context', async t => {
    const h = harness(t, { selectedId: 12 })
    h.api.query.value = 'нет такой категории'
    await Vue.nextTick()
    assert.deepEqual(h.api.rows.value, [])
    assert.equal(findVNode(h.render(), vnode => hasClass(vnode, 'catalog-category-filter__empty')).children, 'Категории не найдены')
    assert.ok(findVNode(h.render(), vnode => hasClass(vnode, 'catalog-category-filter__current')))
    assert.equal(h.props.selectedId, 12)
    h.api.query.value = 'сырье ЗЕРНОВОЕ'
    await Vue.nextTick()
    assert.deepEqual(h.api.rows.value.map(row => row.node.id), [20])
})

test('category counts come from all catalog goods rather than currently loaded products', t => {
    const h = harness(t, { selectedId: 12 })
    const fish = findVNode(h.render(), vnode => hasClass(vnode, 'catalog-category-filter__select') && vnode.props.title === 'Рыба')
    const count = findVNode(fish, vnode => hasClass(vnode, 'catalog-category-filter__count'))
    assert.equal(count.children, '24')
    assert.equal(count.props['aria-label'], 'Товаров: 24')
})

test('updated site nodes discard obsolete expanded branches and accept category roots with unavailable parents', async t => {
    const h = harness(t, { selectedId: 12 })
    h.props.nodes = [{ id: 30, parent_id: 999, name: 'Ингредиенты', goods_count: 8 }, { id: 31, parent_id: 30, name: 'Мука', goods_count: 8 }]
    h.props.selectedId = '31'
    await Vue.nextTick()
    assert.deepEqual([...h.api.expanded.value], ['30'])
    assert.deepEqual(h.api.rows.value.map(row => row.node.id), [30, 31])
    assert.equal(h.api.selectedName.value, 'Мука')
})

test('malformed ancestor cycles do not loop when revealing a selected category', () => {
    const index = categories.catalogCategoryIndex([{ id: 1, parent_id: 2, name: 'A' }, { id: 2, parent_id: 1, name: 'B' }])
    assert.deepEqual([...categories.catalogCategoryAncestors(index, 1)], ['2'])
})

test('selected row scrolls only its panel on mount and selection changes, preserving manual scroll on count refresh', async t => {
    const h = harness(t, { selectedId: 12 })
    const selected = { offsetTop: 300, offsetHeight: 30 }
    const panel = { scrollTop: 0, clientHeight: 224, querySelector: () => selected }
    h.api.list.value = panel
    await Vue.nextTick()
    assert.equal(h.api.list.value.scrollTop, 106)
    h.api.list.value.scrollTop = 70
    h.props.nodes = nodes.map(node => ({ ...node, goods_count: node.goods_count + 1 }))
    await Vue.nextTick()
    assert.equal(h.api.list.value.scrollTop, 70, 'count refresh preserves the customer’s scroll position')
    selected.offsetTop = 10
    h.props.selectedId = 20
    await Vue.nextTick()
    assert.equal(h.api.list.value.scrollTop, 10)
})

test('category avatars belong to selection buttons while expansion and selection keep separate behavior', t => {
    const category = { id: 41, name: 'Мука', image: '/catalog/flour.png', thumbnail_url: '/catalog/flour-thumb.png' }
    const h = harness(t, { nodes: [category, { id: 42, parent_id: 41, name: 'Пшеничная' }] })
    const button = findVNode(h.render(), node => hasClass(node, 'catalog-category-filter__select'))
    const avatar = findVNode(button, node => node.type === 'CatalogNodeAvatar')
    assert.equal(avatar.props.node, h.props.nodes[0])
    assert.equal(avatar.props.size, 22)
    assert.ok(findVNode(h.render(), node => hasClass(node, 'catalog-category-filter__expand')))
    button.props.onClick()
    assert.deepEqual(h.emitted, [['select', 41]])
})
