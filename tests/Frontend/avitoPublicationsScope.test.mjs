import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(overrides = {}) {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Avito/AvitoPublications.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'avito-publication-scope' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'avito-publication-scope', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], mounted = [], disposal = []
    const request = (method, url, options) => new Promise((resolve, reject) => requests.push({ method, url, options, resolve: data => resolve({ data }), reject }))
    const env = {
        ...Vue,
        onMounted: callback => mounted.push(callback),
        onBeforeUnmount: callback => disposal.push(callback),
        console: { error() {} },
        axios: Object.fromEntries(['get', 'post', 'put'].map(method => [method, (url, options) => request(method, url, options)])),
    }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(env)
    const props = Vue.reactive({ enabled: true, mutationsEnabled: true, workspace: { ready: true, authorization_ready: true, account_id: 321, connection_id: 7 }, goodId: 42, goodName: 'Изюм', linkedListings: [], ...overrides })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    api.applyWorkspace()
    return { api, props, requests, emitted, render: templateRenderer(template, api, props), mount: () => mounted.forEach(callback => callback()), dispose() { disposal.forEach(callback => callback()); scope.stop() } }
}

const flush = async () => { await Vue.nextTick(); await Vue.nextTick(); await Vue.nextTick() }
const draft = (id = 18, goodId = 42) => ({ id, good_id: goodId, good_name: 'Изюм', status: 'draft', draft: { selected_fields: ['title', 'price'] }, good: { prices: [], media: [] } })
const list = (items = []) => ({ items, meta: { page: 1, last_page: 1, total: items.length }, status_counts: { draft: items.length }, feed: null })

test('embedded publications load only the card Good and render a compact light workspace', async t => {
    const h = harness(); t.after(h.dispose)
    const loading = h.api.loadList(true)
    assert.equal(h.requests[0].options.params.good_id, 42)
    assert.equal(h.requests[0].options.params.account_id, 321)
    h.requests[0].resolve(list([draft()])); await flush()
    assert.equal(h.requests[1].url, '/api/avito/publications/18')
    h.requests[1].resolve({ publication: draft() }); await loading
    assert.equal(h.api.publication.value.good_id, 42)
    assert.ok(findVNode(h.render(), node => hasClass(node, 'publisher--embedded')))
    assert.ok(findVNode(h.render(), node => hasClass(node, 'publication-flow')))
})

test('creating from a card locks the Good and stores only a draft without remote publication', async t => {
    const h = harness(); t.after(h.dispose)
    await h.api.openCreate()
    assert.equal(h.api.createGoodId.value, 42)
    assert.equal(h.requests.length, 0, 'The card does not search other goods')
    h.api.createGoodId.value = 99
    const create = h.api.createPublication()
    assert.deepEqual(h.requests[0].options, { account_id: 321, connection_id: 7, good_id: 42 })
    assert.equal(h.requests[0].url, '/api/avito/publications')
    await h.api.createPublication()
    assert.equal(h.requests.length, 1, 'Repeated clicks cannot create another draft')
    h.requests[0].resolve({ publication: draft() }); await flush()
    h.requests[1].resolve(list([draft()])); await flush()
    h.requests[2].resolve({ publication: draft() }); await flush()
    h.requests[3].resolve({ publication: draft() }); await create
    assert.equal(h.api.publication.value.id, 18)
    assert.equal(h.api.createDialog.value, false)
    assert.equal(h.requests.filter(item => item.method === 'post').length, 1)
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
})

test('a linked listing prevents a duplicate draft only in its matching account and product', async t => {
    const h = harness({ linkedListings: [{ avito_account_id: 321, avito_item_id: 9001, good_id: 42 }] }); t.after(h.dispose)
    assert.equal(h.api.canCreate.value, false)
    await h.api.openCreate(); await h.api.createPublication()
    assert.equal(h.api.createDialog.value, false)
    assert.equal(h.requests.length, 0)
    h.props.linkedListings = [{ avito_account_id: 322, avito_item_id: 9001, good_id: 42 }]
    assert.equal(h.api.canCreate.value, true)
    h.props.linkedListings = [{ avito_account_id: 321, avito_item_id: 9001, good_id: 43 }]
    assert.equal(h.api.canCreate.value, true)
})

test('switching Good rejects stale list and detail responses before they can alter the card', async t => {
    const h = harness(); t.after(h.dispose)
    h.mount()
    const originalList = h.requests[0]
    h.props.goodId = 43; await flush()
    const nextList = h.requests[1]
    assert.equal(nextList.options.params.good_id, 43)
    nextList.resolve(list([draft(19, 43)])); await flush()
    const nextDetail = h.requests[2]
    originalList.resolve(list([draft()])); await flush()
    assert.equal(h.requests.length, 3)
    h.props.goodId = 44; await flush()
    h.requests[3].resolve(list()); await flush()
    nextDetail.resolve({ publication: draft(19, 43) }); await flush()
    assert.equal(h.api.publication.value, null)
    assert.deepEqual(h.api.items.value, [])
    assert.equal(h.api.loading.value, false)
})

test('the scoped editor refuses a publication belonging to another Good', async t => {
    const h = harness(); t.after(h.dispose)
    const loading = h.api.loadPublication(18)
    h.requests[0].resolve({ publication: draft(18, 43) }); await loading
    assert.equal(h.api.publication.value, null)
    assert.match(h.api.inlineError.value, /другому товару/)
})

test('unmount discards a pending response and global publication search remains unscoped', async t => {
    const h = harness({ goodId: null }); t.after(h.dispose)
    h.api.search.value = null
    const loading = h.api.loadList()
    assert.equal(h.requests[0].options.params.good_id, undefined)
    assert.equal(h.requests[0].options.params.search, undefined)
    h.dispose()
    h.requests[0].resolve(list([draft()])); await loading
    assert.deepEqual(h.api.items.value, [])
    assert.equal(h.requests.length, 1)
})

test('report synchronization announces the changed link without creating a new publication', async t => {
    const h = harness(); t.after(h.dispose)
    h.api.hydrate({ publication: { ...draft(), current_revision: { id: 2 } } })
    const sync = h.api.syncPublication()
    assert.equal(h.requests[0].url, '/api/avito/publications/18/sync')
    h.requests[0].resolve({ publication: { ...draft(), avito_item_id: 9001 }, avito_item_id: 9001 }); await sync
    assert.equal(h.api.publication.value.avito_item_id, 9001)
    assert.ok(h.emitted.some(event => event[0] === 'changed'))
})

test('the host receives dirty and busy state, and reset restores the last saved draft', async t => {
    const h = harness(); t.after(h.dispose)
    h.api.hydrate({ publication: draft() }); await flush()
    assert.equal(h.api.dirty.value, false)
    h.api.editor.title_override = 'Новая редакция'
    await flush()
    assert.deepEqual(h.emitted.filter(event => event[0] === 'state').at(-1)[1], { dirty: true, busy: false })
    h.api.reset(); await flush()
    assert.equal(h.api.editor.title_override, '')
    assert.equal(h.api.dirty.value, false)
    h.api.editor.title_override = 'Сохраняемая редакция'
    const save = h.api.saveDraft()
    await flush()
    assert.equal(h.api.busy.value, true)
    h.requests[0].resolve({ publication: { ...draft(), draft: { title_override: 'Сохраняемая редакция' } } }); await save; await flush()
    assert.equal(h.api.editor.title_override, 'Сохраняемая редакция')
    assert.deepEqual(h.emitted.filter(event => event[0] === 'state').at(-1)[1], { dirty: false, busy: false })
})

const measuredDraft = (kilogramsPerUnit = 1) => ({
    ...draft(),
    draft: { selected_fields: ['title', 'price'], price_value_id: 7, price_basis: { mode: 'unit' } },
    good: {
        measurement: { measure_id: kilogramsPerUnit === 1 ? 1 : 2, unit_label: kilogramsPerUnit === 1 ? 'кг' : 'коробка', kilograms_per_unit: kilogramsPerUnit },
        denominator: 10,
        prices: [{ id: 7, name: 'Розничная', amount: 233.14, currency_code: 'RUB' }],
        avito_price_basis: {
            unit: { mode: 'unit', quantity: 1, label: kilogramsPerUnit === 1 ? 'кг' : 'коробка', available: true },
            package: { mode: 'package', quantity: 10 / kilogramsPerUnit, label: 'упаковка', available: true },
        },
    },
})

test('publication price offers base unit, package and custom quantity with rounding only after multiplication', t => {
    const h = harness(); t.after(h.dispose)
    h.api.hydrate({ publication: measuredDraft() })
    assert.equal(h.api.priceCalculation.value.total, 233)
    h.api.changePriceBasis('package')
    assert.equal(h.api.priceCalculation.value.basis.quantity, 10)
    assert.equal(h.api.priceCalculation.value.total, 2331)
    assert.deepEqual(h.api.editorPayload().price_basis, { mode: 'package' })
    h.api.changePriceBasis('custom')
    h.api.changeCustomBasis('quantity', '2.5')
    h.api.changeCustomBasis('label', 'пакет')
    assert.equal(h.api.priceCalculation.value.total, 583)
    assert.deepEqual(h.api.editorPayload().price_basis, { mode: 'custom', quantity: '2.5', label: 'пакет' })
    assert.ok(findVNode(h.render(), node => hasClass(node, 'price-basis-calculation')))
    h.api.hydrate({ publication: measuredDraft(10) })
    h.api.changePriceBasis('package')
    assert.equal(h.api.priceCalculation.value.basis.quantity, 1, 'A Good accounted as a 10 kg box is not multiplied by 10 again')
    assert.equal(h.api.priceCalculation.value.total, 233)
})

test('a manual publication override remains the final lot price and invalid quantities never produce a preview amount', t => {
    const h = harness(); t.after(h.dispose)
    h.api.hydrate({ publication: measuredDraft() })
    h.api.changePriceBasis('package')
    h.api.editor.price_override = 899.6
    assert.equal(h.api.priceCalculation.value.manual, true)
    assert.equal(h.api.priceCalculation.value.total, 900, 'A final manual price is never multiplied by package quantity')
    h.api.changePriceBasis('custom')
    h.api.changeCustomBasis('quantity', '-1')
    assert.equal(h.api.priceCalculation.value, null)
    h.api.changeCustomBasis('quantity', '3')
    h.api.changeCustomBasis('label', '')
    assert.equal(h.api.priceCalculation.value, null)
})

test('custom basis keeps its saved unit snapshot until explicitly edited and warns about changed units', t => {
    const h = harness(); t.after(h.dispose)
    const publication = measuredDraft()
    publication.draft.price_basis = { mode: 'custom', quantity: 5, label: 'мешок', measurement: { measure_id: 2, unit_label: 'коробка', kilograms_per_unit: 10 } }
    h.api.hydrate({ publication })
    assert.deepEqual(h.api.editorPayload().price_basis, publication.draft.price_basis)
    assert.match(h.api.priceBasisIssue.value, /Единица товара или фасовка изменились/)
    assert.equal(h.api.priceCalculation.value, null)
    h.api.changeCustomBasis('quantity', '5')
    assert.equal(h.api.editorPayload().price_basis.measurement, undefined)
    assert.equal(h.api.priceBasisIssue.value, '')
    assert.equal(h.api.priceCalculation.value.total, 1166)
    h.api.publication.value.good.avito_price_basis.package = { available: false, block_reason: 'Не указана масса единицы товара' }
    assert.equal(h.api.priceBasisOptions.value.find(item => item.value === 'package').props.disabled, true)
})

test('stored package and unit bases require explicit acknowledgement after measurement or package changes', t => {
    const h = harness(); t.after(h.dispose)
    const publication = measuredDraft()
    publication.draft.price_basis = { mode: 'package', quantity: 10, label: 'упаковка', package_kg: 8, measurement: { ...publication.good.measurement } }
    h.api.hydrate({ publication })
    assert.equal(h.api.priceBasisStale.value, true)
    assert.equal(h.api.priceCalculation.value, null)
    h.api.renewPriceBasis()
    assert.deepEqual(h.api.editorPayload().price_basis, { mode: 'package' })
    assert.equal(h.api.priceCalculation.value.total, 2331)
    publication.draft.price_basis = { mode: 'unit', quantity: 1, label: 'кг', measurement: { measure_id: 2, unit_label: 'коробка', kilograms_per_unit: 10 } }
    h.api.hydrate({ publication })
    assert.equal(h.api.priceBasisStale.value, true)
    h.api.renewPriceBasis()
    assert.equal(h.api.priceCalculation.value.total, 233)
    publication.draft.price_basis = { mode: 'unit', quantity: 1, label: 'кг', measurement: { ...publication.good.measurement, unit_label: 'килограмм' } }
    h.api.hydrate({ publication })
    assert.equal(h.api.priceBasisStale.value, false, 'Renaming the same unit does not change its quantity conversion')
})

function nodeText(node) {
    if (node == null || typeof node === 'boolean') return ''
    if (typeof node === 'string' || typeof node === 'number') return String(node)
    if (Array.isArray(node)) return node.map(nodeText).join('')
    return nodeText(node.children)
}

test('saving persists the server unit snapshot and published price formulas stay immutable in history', async t => {
    const h = harness(); t.after(h.dispose)
    const publication = measuredDraft()
    h.api.hydrate({ publication })
    h.api.changePriceBasis('package')
    const save = h.api.saveDraft()
    assert.deepEqual(h.requests[0].options.price_basis, { mode: 'package' })
    const basis = { mode: 'package', quantity: 10, label: 'упаковка', measurement: { ...publication.good.measurement }, package_kg: 10 }
    const details = { price_basis: basis, total_unrounded: 2331.4, avito_value: 2331, sale_unit_label: 'упаковка', formula: '233,14 RUB / кг × 10 кг = 2 331,4 RUB / упаковка', can_apply: true }
    const revision = { id: 20, version: 1, status: 'approved', payload: { price: 2331, price_basis: basis, price_details: details } }
    const saved = { ...publication, draft: { ...publication.draft, price_basis: basis }, current_revision: revision, revisions: [revision] }
    h.requests[0].resolve({ publication: saved, preview: { valid: true, payload: revision.payload, price_details: details } }); await save
    assert.deepEqual(h.api.editorPayload().price_basis, basis)
    assert.equal(h.api.dirty.value, false)
    h.api.publication.value.good.prices[0].amount = 300
    assert.equal(h.api.priceCalculation.value.total, 3000)
    const rendered = h.render()
    assert.match(nodeText(findVNode(rendered, node => hasClass(node, 'revision-price'))), /2\s331 ₽ \/ упаковка/)
    assert.equal(nodeText(findVNode(rendered, node => hasClass(node, 'revision-formula'))), details.formula)
    assert.match(nodeText(findVNode(rendered, node => hasClass(node, 'snapshot-price'))), /2\s331 ₽ \/ упаковка/)
    assert.equal(h.api.preview.value.payload.price, 2331)
})
