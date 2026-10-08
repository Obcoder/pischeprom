import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { goodTradeCodeValues } from '../../resources/js/utils/goodTradeCodes.js'
import { safeGalleryUrl } from '../../resources/js/Components/Catalog/gallery.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t) {
    const filename = 'resources/js/Components/Catalog/CatalogGoodOverview.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'CatalogGoodOverview' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'CatalogGoodOverview', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)({ ...Vue, safeGalleryUrl, GoodVatCheck: 'GoodVatCheck', GoodTradeCodeFields: 'GoodTradeCodeFields' })
    const props = Vue.reactive({
        modelValue: { denominator: 25, vat_rate_id: 1, country_id: 2, products: [3], fields: [4], ...goodTradeCodeValues(), hs_code: '030111' },
        overview: { id: 42, created_at: '2026-10-01T12:00:00Z', updated_at: '2026-10-07T12:00:00Z', ava_image: '/full.jpg', ava_thumb: '/thumb.jpg', counts: { prices: 5, sales: 4, purchases: 3, media: 2 } },
        options: { products: [{ id: 3, rus: 'Форель', category_id: 1 }], categories: [{ id: 1, name: 'Рыба' }], fields: [{ id: 4, title: 'Охлаждённое' }], countries: [{ id: 2, name: 'Россия', flag: '/ru.svg' }], vat_rates: [{ id: 1, title: 'Льготная', rate: 10 }] },
        context: { name: 'Филе без кожи', description: 'Несохранённый состав' },
        disabled: false, active: true, errors: {}, editUrl: '/ameise/good/42',
    })
    const emitted = []
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => { emitted.push(event); if (event[0] === 'update:modelValue') props.modelValue = event[1] } }))
    t.after(() => scope.stop())
    return { api, props, emitted, render: templateRenderer(template, api, props) }
}

test('goods overview keeps the VAT assistant connected to unsaved catalog data and preserves trade codes', t => {
    const h = harness(t)
    const vat = findVNode(h.render(), node => node.type === 'GoodVatCheck')
    assert.equal(vat.props.draft.id, 42)
    assert.equal(vat.props.draft.name, 'Филе без кожи')
    assert.equal(vat.props.draft.description, 'Несохранённый состав')
    assert.deepEqual(vat.props.draft.product_ids, [3])
    assert.equal(vat.props.draft.hs_code, '030111')
    vat.props.onApply(2)
    assert.equal(h.props.modelValue.vat_rate_id, 2)
    assert.equal(findVNode(h.render(), node => node.type === 'GoodTradeCodeFields'), null)
    assert.equal(h.props.modelValue.hs_code, '030111')
    assert.equal(h.props.modelValue.denominator, 25)
    h.props.disabled = true
    vat.props.onApply(1)
    assert.equal(h.props.modelValue.vat_rate_id, 2)
})

test('products include category context, fields retain labels and saved counters link to the matching detailed tabs', t => {
    const h = harness(t)
    const products = findVNode(h.render(), node => node.type === 'v-autocomplete' && node.props.label === 'Связанные продукты')
    assert.equal(products.props.multiple, '')
    assert.equal(products.props.items[0].category_name, 'Рыба')
    products.props['onUpdate:modelValue']([3, 5])
    assert.deepEqual(h.props.modelValue.products, [3, 5])
    assert.deepEqual(h.api.draft.value.product_ids, [3, 5])
    const fields = findVNode(h.render(), node => node.type === 'v-autocomplete' && node.props.label === 'Подборки')
    assert.equal(fields.props.items[0].label, 'Охлаждённое')
    fields.props['onUpdate:modelValue']([])
    assert.deepEqual(h.props.modelValue.fields, [])
    assert.deepEqual(h.api.stats.value.map(stat => stat.count), [5, 4, 3, 2])
    assert.equal(h.api.statUrl('purchases'), '/ameise/good/42?tab=quotations')
    assert.ok(findVNode(h.render(), node => hasClass(node, 'catalog-good-overview__stat') && node.props.href === '/ameise/good/42?tab=media'))
    h.props.inlineNavigation = true
    let prevented = false
    h.api.openStat({ preventDefault() { prevented = true } }, 'purchases')
    assert.equal(prevented, true)
    assert.deepEqual(h.emitted.at(-1), ['navigate', 'quotations'])
    assert.equal(findVNode(h.render(), node => hasClass(node, 'catalog-good-overview__stat') && node.props.href === '/ameise/good/42?tab=media').props.target, undefined)
    const country = findVNode(h.render(), node => node.type === 'v-autocomplete' && node.props.label === 'Страна происхождения')
    assert.equal(country.props.items[0].flag, '/ru.svg')
})

test('both saved avatar URLs can be copied and unavailable clipboard produces a visible message', async t => {
    const h = harness(t)
    assert.ok(findVNode(h.render(), node => node.props?.['aria-label'] === 'Скопировать оригинал'))
    assert.ok(findVNode(h.render(), node => node.props?.['aria-label'] === 'Скопировать миниатюру'))
    assert.equal(findVNode(h.render(), node => node.props?.href === '/full.jpg').props.target, '_blank')
    const descriptor = Object.getOwnPropertyDescriptor(globalThis, 'navigator')
    const copied = []
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard: { async writeText(value) { copied.push(value) } } } })
    t.after(() => { if (descriptor) Object.defineProperty(globalThis, 'navigator', descriptor); else delete globalThis.navigator })
    await h.api.copy('/full.jpg', 'Оригинал')
    await h.api.copy('/thumb.jpg', 'Миниатюра')
    assert.deepEqual(copied, ['/full.jpg', '/thumb.jpg'])
    assert.match(h.api.clipboardMessage.value, /скопирована/)
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {} })
    await h.api.copy('/thumb.jpg', 'Миниатюра')
    assert.equal(h.api.clipboardMessage.value, 'Не удалось скопировать ссылку')
})
