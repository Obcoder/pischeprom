import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { goodRecordTabs, moveTab, normalizeTabOrder } from '../../resources/js/Components/Catalog/recordTabs.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, saved = null, failStorage = false, overrides = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogRecordTabs.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'record-tabs' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'record-tabs', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const emitted = [], stored = []
    const mounted = []
    const props = Vue.reactive({ modelValue: 'seo', good: true, ...overrides })
    let pointerTarget = null
    const env = { ...Vue, goodRecordTabs, moveTab, normalizeTabOrder, onMounted: callback => mounted.push(callback),
        document: { elementFromPoint: () => pointerTarget }, localStorage: {
            getItem() { if (failStorage) throw new Error('blocked'); return saved },
            setItem(key, value) { if (failStorage) throw new Error('blocked'); stored.push([key, value]) },
        } }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(env)
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    mounted.forEach(callback => callback())
    return { state, props, emitted, stored, render: templateRenderer(template, state, props), pointerTarget: value => { pointerTarget = value } }
}

test('stored tab order is deduplicated and new tabs are appended without losing any tools', t => {
    const h = harness(t, JSON.stringify(['media', 'seo', 'media', 'removed']))
    assert.deepEqual(h.state.order.value.slice(0, 2), ['media', 'seo'])
    assert.equal(h.state.order.value.length, goodRecordTabs.length)
    assert.deepEqual(normalizeTabOrder({ wrong: true }), goodRecordTabs.map(tab => tab.id))
    assert.equal(findVNode(h.render(), node => node.props?.role === 'tablist').props['aria-label'], 'Разделы карточки товара')
})

test('catalog records use their own two-tab order and goods retain every existing tool', async t => {
    const h = harness(t, JSON.stringify(['media', 'landing', 'overview']), false, { good: false, modelValue: 'landing' })
    assert.deepEqual(h.state.order.value, ['landing', 'overview'])
    assert.equal(findVNode(h.render(), node => node.props?.role === 'tablist').props['aria-label'], 'Разделы карточки записи')
    h.state.reorder('overview', 'landing')
    assert.equal(h.stored[0][0], 'ameise.catalog.record-tabs.v1')
    h.props.good = true
    await Vue.nextTick()
    assert.equal(h.state.order.value.length, goodRecordTabs.length)
    assert.ok(h.state.order.value.includes('landing'))
})

test('drag and drop persists the tab order without changing the selected tab', t => {
    const h = harness(t)
    const transfers = []
    h.state.dragStart({ dataTransfer: { setData: (...args) => transfers.push(args) } }, 'seo')
    h.state.drop({ preventDefault() {} }, 'media')
    assert.equal(h.state.order.value[7], 'seo')
    assert.equal(h.props.modelValue, 'seo')
    assert.equal(h.emitted.length, 0)
    assert.deepEqual(JSON.parse(h.stored.at(-1)[1]), h.state.order.value)
    assert.deepEqual(transfers, [['text/plain', 'seo']])
    assert.equal(h.state.dragging.value, null)
})

test('touch handle and keyboard reorder work when browser storage is blocked', async t => {
    const h = harness(t, null, true)
    const target = { dataset: { recordTab: 'overview' } }
    h.pointerTarget({ closest: () => target })
    h.state.row.value = { contains: item => item === target, getBoundingClientRect: () => ({ left: 0, right: 900 }), scrollLeft: 0, querySelector: () => null }
    let captured = null
    h.state.startPointer({ button: 0, pointerId: 4, preventDefault() {}, currentTarget: { setPointerCapture: id => { captured = id } } }, 'seo')
    h.state.movePointer({ pointerId: 4, clientX: 100, clientY: 20 })
    assert.equal(h.state.order.value[0], 'seo')
    assert.equal(captured, 4)
    h.state.endPointer()
    await h.state.keydown({ key: 'ArrowRight', preventDefault() {} }, 'seo', true)
    assert.equal(h.state.order.value[1], 'seo')
    assert.match(h.state.announcement.value, /позиция 2/)
    assert.equal(h.props.modelValue, 'seo')
})

test('keyboard tab navigation follows the reordered row and selects without rewriting preferences', async t => {
    const h = harness(t, JSON.stringify(['seo', 'media', 'overview']))
    const focused = []
    h.state.row.value = { querySelector: selector => ({ focus: () => focused.push(selector) }) }
    await h.state.keydown({ key: 'ArrowRight', preventDefault() {} }, 'seo')
    assert.deepEqual(h.emitted[0], ['update:modelValue', 'media'])
    assert.match(focused[0], /media/)
    assert.equal(h.stored.length, 0)
})

test('programmatic navigation reveals the selected tab in a narrow or reordered row', async t => {
    const h = harness(t, JSON.stringify(['media', 'overview', 'seo']))
    const revealed = []
    h.state.row.value = { querySelector: selector => ({ scrollIntoView: options => revealed.push({ selector, options }) }) }
    h.props.modelValue = 'prices'
    await Vue.nextTick()
    assert.ok(revealed.some(item => item.selector.includes('prices') && item.options.inline === 'nearest'))
})
