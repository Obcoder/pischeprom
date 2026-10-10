import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { goodTradeCodeValues } from '../../resources/js/utils/goodTradeCodes.js'
import { safeGalleryUrl } from '../../resources/js/Components/Catalog/gallery.js'
import { massUnitFactor } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t) {
    const filename = 'resources/js/Components/Catalog/CatalogGoodMeasurement.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'CatalogGoodMeasurement' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'CatalogGoodMeasurement', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)({ ...Vue, safeGalleryUrl, massUnitFactor, GoodVatCheck: 'GoodVatCheck', GoodTradeCodeFields: 'GoodTradeCodeFields' })
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

test('the accounting unit explains ten kilograms versus ten boxes independently of packaging', t => {
    const h = harness(t)
    h.props.options.measures = [{ id: 1, name: 'кг' }, { id: 2, name: 'коробка' }]
    assert.match(h.api.example.value, /Выберите единицу/)
    const select = findVNode(h.render(), node => node.type === 'v-select' && node.props.label === 'Единица учёта товара')
    select.props['onUpdate:modelValue'](1)
    assert.match(h.api.example.value, /10 кг = 10 кг/)
    assert.match(h.api.example.value, /Цена указывается за 1 кг/)
    h.props.modelValue.unit_weight_kg = 10
    assert.equal(h.api.unitWeight.value, 1)
    select.props['onUpdate:modelValue'](2)
    assert.equal(h.props.modelValue.unit_weight_kg, null)
    assert.equal(h.api.unitWeight.value, null)
    h.props.modelValue.unit_weight_kg = 10
    assert.match(h.api.example.value, /10 коробка = 100 кг/)
    assert.equal(h.props.modelValue.denominator, 25)
    const weight = findVNode(h.render(), node => node.type === 'v-text-field' && node.props.label === 'Масса 1 коробка, кг')
    assert.ok(weight)
    weight.props['onUpdate:modelValue']('12.5')
    assert.match(h.api.example.value, /125 кг/)
})

test('first configuration asks for the basis of existing prices and keeps price amounts by default', t => {
    const h = harness(t)
    const selector = findVNode(h.render(), node => node.type === 'v-select' && node.props.label === 'Единица ранее сохранённых цен')
    assert.ok(selector)
    assert.equal(selector.props['model-value'], 'selected_unit')
    selector.props['onUpdate:modelValue']('kg')
    assert.equal(h.props.modelValue.existing_price_basis, 'kg')
    h.props.overview.measure_id = 1
    assert.equal(findVNode(h.render(), node => node.type === 'v-select' && node.props.label === 'Единица ранее сохранённых цен'), null)
    h.props.overview.measure_id = null
    h.props.overview.counts.prices = 0
    assert.equal(findVNode(h.render(), node => node.type === 'v-select' && node.props.label === 'Единица ранее сохранённых цен'), null)
})
