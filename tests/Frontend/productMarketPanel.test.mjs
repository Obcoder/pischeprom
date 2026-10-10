import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/ProductMarketPanel.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'market' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'market', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const environment = { ...Vue, ProductAiSalesCampaignCard: 'ProductAiSalesCampaignCard', ProductYandexSearchCard: 'ProductYandexSearchCard' }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const props = Vue.reactive({ productId: 42, productName: 'Скумбрия', canViewAiSales: true, active: false, ...overrides })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { api, props, render: templateRenderer(template, api, props) }
}
const card = (h, type) => findVNode(h.render(), node => node.type === type)

test('market mounts services on first selection, keeps their state and deactivates hidden requests', async t => {
    const h = harness(t)
    assert.equal(card(h, 'ProductAiSalesCampaignCard'), null)
    assert.equal(card(h, 'ProductYandexSearchCard'), null)
    h.props.active = true
    assert.equal(card(h, 'ProductAiSalesCampaignCard').props.active, true)
    assert.equal(card(h, 'ProductYandexSearchCard'), null)
    findVNode(h.render(), node => node.type === 'v-tabs').props['onUpdate:modelValue']('yandex')
    assert.equal(card(h, 'ProductAiSalesCampaignCard').props.active, false)
    const yandex = card(h, 'ProductYandexSearchCard')
    assert.equal(yandex.props.active, true)
    assert.equal(yandex.props['product-id'], 42)
    assert.equal(yandex.props['product-name'], 'Скумбрия')
    h.props.active = false
    assert.equal(card(h, 'ProductAiSalesCampaignCard').props.active, false)
    assert.equal(card(h, 'ProductYandexSearchCard').props.active, false)
    h.props.active = true
    assert.equal(card(h, 'ProductYandexSearchCard').props.active, true)
})

test('users without AI permission can use Yandex without mounting AI services', t => {
    const h = harness(t, { active: true, canViewAiSales: false })
    assert.equal(h.api.tab.value, 'yandex')
    assert.equal(card(h, 'ProductAiSalesCampaignCard'), null)
    assert.equal(card(h, 'ProductYandexSearchCard').props.active, true)
    assert.equal(findVNode(h.render(), node => node.type === 'v-tab' && node.props.value === 'ai').props.disabled, true)
})

test('changing product clears visited service state and permission changes revoke AI access', async t => {
    const h = harness(t, { active: true })
    h.api.tab.value = 'yandex'
    h.props.productId = 87
    assert.equal(h.api.tab.value, 'ai')
    assert.equal(card(h, 'ProductYandexSearchCard'), null)
    assert.equal(card(h, 'ProductAiSalesCampaignCard').props['product-id'], 87)
    h.props.canViewAiSales = false
    await Vue.nextTick()
    assert.equal(card(h, 'ProductAiSalesCampaignCard'), null)
    assert.equal(card(h, 'ProductYandexSearchCard').props.active, true)
})
