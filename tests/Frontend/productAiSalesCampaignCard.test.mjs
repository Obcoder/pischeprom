import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { normalizeReviewItems, reviewBadgeCount } from '../../resources/js/Components/AiSales/reviewProjection.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const flush = async () => { for (let i = 0; i < 6; i++) await Promise.resolve(); await Vue.nextTick() }
function harness(t, overrides = {}) {
    const filename = 'resources/js/Components/AiSales/ProductAiSalesCampaignCard.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'ai-card' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'ai-card', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = []
    const environment = {
        ...Vue, normalizeReviewItems, reviewBadgeCount, FindBuyersLauncher: 'FindBuyersLauncher',
        axios: { get: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve: data => resolve({ data }), reject })) },
    }
    const code = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const props = Vue.reactive({ productId: 42, active: true, ...overrides })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { props, api, requests, render: templateRenderer(template, api, props) }
}
const product = (id = 42, role = 'primary') => ({ id, role })
const campaign = (id = 'campaign-1', products = [product()]) => ({ id, products, status: 'running', safe_name: 'Покупатели рыбы' })
const job = (id, candidates, products = [product()]) => ({ job: { id, products }, candidates, counts: { results: { total: 4 }, research: { total: 3, completed: 2 } } })

test('AI summary filters the product, deduplicates candidates and links to the real review queue', async t => {
    const h = harness(t)
    h.requests[0].resolve({ data: [campaign(), campaign('other', [product(87)]), campaign('excluded', [product(42, 'exclude')])] })
    const candidate = { id: 'candidate-1', status: 'new_unit_review', resolved_unit: null }
    h.requests[1].resolve({ data: { jobs: [job('one', [candidate]), job('two', [candidate]), job('other', [{ id: 'not-product' }], [product(87)])] } })
    await flush()
    assert.equal(h.requests.length, 3)
    assert.equal(h.requests[2].url, '/api/ai-sales/campaigns/campaign-1/review-queue?limit=100')
    h.requests[2].resolve({ data: [{ campaign_id: 'campaign-1', source_type: 'candidate', source_id: 'candidate-1', category: 'new_unit_review' }] })
    await flush()
    assert.deepEqual(h.api.counters.value, { campaigns: 1, results: 8, research: 4, candidates: 1, reviews: 1 })
    assert.equal(h.api.statusLabel('new_unit_review'), 'Новая компания')
    assert.equal(h.api.reviewLoaded.value, true)
    assert.equal(h.api.loading.value, false)
    const table = findVNode(h.render(), node => node.type === 'v-data-table')
    assert.equal(table.props.items.length, 1)
    assert.equal(h.api.candidateUrl(candidate), '/Ameise/ai-sales?tab=review&candidate=candidate-1#candidate-review')
    assert.equal(findVNode(h.render(), node => node.type === 'FindBuyersLauncher').props.compact, '')
})

test('a failed queue does not erase loaded campaigns and candidates or claim a complete review count', async t => {
    const h = harness(t)
    h.requests[0].resolve({ data: [campaign()] })
    h.requests[1].resolve({ data: { jobs: [job('one', [{ id: 'candidate-1', status: 'new_unit_review' }])] } })
    await flush()
    h.requests[2].reject({ response: { status: 503 } })
    await flush()
    assert.equal(h.api.loaded.value, true)
    assert.equal(h.api.candidates.value.length, 1)
    assert.match(h.api.reviewError.value, /не полностью/)
    assert.equal(h.api.reviewLoaded.value, false)
    assert.equal(h.api.metrics.value.find(metric => metric.key === 'reviews').value, null)
    assert.equal(h.api.loading.value, false)
})

test('hidden cards cancel requests and reject late responses from a previous product', async t => {
    const h = harness(t, { active: false })
    assert.equal(h.requests.length, 0)
    h.props.active = true
    const old = h.requests.slice()
    h.props.productId = 87
    assert.equal(old[0].options.signal.aborted, true)
    old[0].resolve({ data: [campaign()] })
    old[1].resolve({ data: { jobs: [job('old', [])] } })
    await flush()
    assert.equal(h.api.loaded.value, false)
    h.requests[2].resolve({ data: [campaign('current', [product(87)])] })
    h.requests[3].resolve({ data: { jobs: [] } })
    await flush()
    h.requests[4].resolve({ data: [] })
    await flush()
    assert.equal(h.api.currentCampaign.value.id, 'current')
    const refresh = h.api.load()
    const pending = h.requests.slice(-2)
    h.props.active = false
    assert.equal(pending[0].options.signal.aborted, true)
    assert.equal(h.api.loading.value, false)
    pending[0].reject({ response: { status: 500 } })
    pending[1].reject({ response: { status: 500 } })
    await refresh
    assert.equal(h.api.error.value, '')
    assert.equal(h.api.currentCampaign.value.id, 'current')
})
