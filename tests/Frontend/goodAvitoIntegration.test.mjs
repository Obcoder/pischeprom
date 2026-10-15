import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'
import * as goodAvito from '../../resources/js/Services/goodAvito.js'

const flush = () => new Promise(resolve => setImmediate(resolve))

function harness(t, name, initial) {
    const filename = `resources/js/Components/Goods/${name}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: name })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: name, compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    const props = Vue.reactive(initial)
    const env = {
        ...Vue,
        onBeforeUnmount: callback => Vue.onScopeDispose(callback),
        ...goodAvito,
        debounce: callback => Object.assign(callback, { cancel() {} }),
        GoodAvitoPriceDialog: { name: 'GoodAvitoPriceDialog' },
        GoodAvitoPublications: { name: 'GoodAvitoPublications' },
        AvitoPublications: { name: 'AvitoPublications' },
        PriceConstructionChain: { name: 'PriceConstructionChain' },
        axios: Object.fromEntries(['get', 'put', 'post', 'patch', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => {
            requests.push({ method, url, body, resolve: data => resolve({ data }), reject })
        })])),
    }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(env)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...event) => emitted.push(event) }))
    t.after(() => scope.stop())
    return { props, api, requests, emitted, render: templateRenderer(template, api, props) }
}

const measurement = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
const basisOptions = {
    unit: { mode: 'unit', quantity: 1, label: 'кг', available: true },
    package: { mode: 'package', quantity: 10, label: 'упаковка', package_kg: 10, available: true },
    custom: { mode: 'custom', available: true },
}
const publicPrice = { id: 7, revision: 3, name: 'Розничная', code: 'retail', amount: 233.2, currency_code: 'RUB', is_public: true }
const link = { id: 15, avito_account_id: 120, avito_item_id: 501, good_id: 42, history: [] }
const context = (goodId = 42) => ({ good_id: goodId, good: { id: goodId, name: 'Изюм', denominator: 10, prices: [publicPrice], measurement, avito_price_basis: structuredClone(basisOptions) }, measurement, links: [{ ...link, good_id: goodId }], publications: [] })
const status = { enabled: true, configured: true, mutations_enabled: true, connections: 1, active_connections: 1 }
const preview = () => ({ source_fingerprint: 'approved-price-revision-3', selected_fields: ['price'], price: { price_value_id: 7, revision: 3, good_value: 233.2, avito_value: 233, currency_code: 'RUB', unit_label: 'кг', measurement, can_apply: true }, warnings: ['Avito принимает целую цену: 233,2 RUB будет округлено до 233 RUB.'] })

test('the catalog Avito badge identifies linked goods and exposes the number of listings', async t => {
    const h = harness(t, 'GoodAvitoBadge', { count: 0 })
    assert.equal(findVNode(h.render(), node => node.type === 'span' && node.props?.['aria-label']), null)
    h.props.count = 2
    await Vue.nextTick()
    const badge = findVNode(h.render(), node => node.type === 'span' && node.props?.['aria-label'])
    assert.equal(badge.props['aria-label'], 'Авито: связанных объявлений 2')
    assert.equal(badge.props.title, 'Связанных объявлений Авито: 2')
})

test('the legacy listing editor sends the reviewed price fingerprint and shows the sale quantity', async t => {
    const h = harness(t, '../Avito/AvitoListingGoodLink', { item: { id: 501, title: 'Изюм', price: 100 }, accountId: 120, authConnectionId: null, mutationsEnabled: true })
    h.requests[0].resolve({ link: { ...link, good: { ...context().good, media: [] } }, history: [] })
    await flush()
    h.api.selectedFields.value = ['price']
    const preparing = h.api.prepareTransfer()
    const prepared = { ...preview(), price: { ...preview().price, avito_value: 2332, sale_unit_label: 'упаковка', price_basis: { mode: 'package', quantity: 10, label: 'упаковка', measurement }, formula: '233,2 RUB / кг × 10 кг = 2 332 RUB / упаковка' } }
    h.requests.at(-1).resolve({ preview: prepared })
    await preparing
    h.api.confirmed.value = true
    assert.ok(h.api.canApplyPrice.value)
    const confirmation = findVNode(h.render(), node => node.type === 'v-checkbox' && node.props?.label?.includes('Подтверждаю'))
    assert.match(confirmation.props.label, /упаковка \(10 кг\)/)
    const fingerprint = h.api.preview.value.source_fingerprint
    delete h.api.preview.value.source_fingerprint
    const count = h.requests.length
    await h.api.applyGoodPrice()
    assert.equal(h.requests.length, count, 'A legacy panel cannot publish an unreviewed price without its fingerprint')
    h.api.preview.value.source_fingerprint = fingerprint
    const applying = h.api.applyGoodPrice()
    const request = h.requests.at(-1)
    assert.equal(request.url, '/api/avito/listings/501/good-transfer/apply')
    assert.equal(request.body.expected_fingerprint, fingerprint)
    assert.equal(request.body.price_value_id, 7)
    assert.equal('price' in request.body, false, 'The authoritative source price must stay on the server')
    request.reject({ response: { status: 409, data: { message: 'Объём объявления изменился. Повторите предпросмотр.' } } })
    await applying
    assert.equal(h.api.preview.value, null)
    assert.equal(h.api.confirmed.value, false)
    assert.match(h.api.localError.value, /Объём объявления изменился/)
})

function dialog(t, props = {}) {
    return harness(t, 'GoodAvitoPriceDialog', { modelValue: true, goodId: 42, priceValueId: 7, linkId: 15, ...props })
}
async function resolveContext(h, good = context(), connection = status, requests = h.requests) {
    requests.find(request => request.url === `/api/avito/goods/${good.good_id}`).resolve(good)
    requests.find(request => request.url === '/api/avito/status').resolve(connection)
    await flush()
}
async function prepareDialog(h, data = preview()) {
    const pending = h.api.prepare()
    h.requests.at(-1).resolve({ preview: data })
    await pending
    return data
}

test('price transfer requires a server preview and unit confirmation, then sends price identity and fingerprint only', async t => {
    const h = dialog(t)
    await resolveContext(h)
    assert.equal(h.api.selectedLink.value, 15)
    assert.equal(h.api.selectedPrice.value, 7)
    const reads = h.requests.length
    await h.api.apply()
    assert.equal(h.requests.length, reads, 'Opening a dialog cannot change a public listing')
    const pending = h.api.prepare(), request = h.requests.at(-1)
    assert.equal(request.url, '/api/avito/goods/42/links/15/preview')
    assert.deepEqual(request.body, { fields: ['price'], price_value_id: 7, price_basis: { mode: 'unit' } })
    request.resolve({ preview: preview() })
    await pending
    assert.equal(h.api.unit.value, 'кг')
    assert.equal(h.api.canApply.value, false)
    await h.api.apply()
    assert.equal(h.requests.length, reads + 1)
    h.api.confirmed.value = true
    const apply = h.api.apply(), mutation = h.requests.at(-1)
    assert.equal(mutation.url, '/api/avito/goods/42/links/15/apply')
    assert.deepEqual(mutation.body, { fields: ['price'], price_value_id: 7, price_basis: { mode: 'unit' }, expected_fingerprint: 'approved-price-revision-3', confirmed: true })
    await h.api.apply()
    assert.equal(h.requests.length, reads + 2, 'Double-clicking cannot submit duplicate price changes')
    mutation.resolve({ transfer: { price: { avito_value: 233 } } })
    await apply
    assert.equal(h.api.confirmed.value, false)
    assert.equal(h.api.preview.value, null)
    assert.match(h.api.notice.value, /233 RUB.*передана/)
    assert.equal(h.emitted.filter(([name]) => name === 'applied').length, 1)
})

test('switching selected price or listing discards an old preview, including an in-flight response', async t => {
    const h = dialog(t)
    const data = context()
    data.good.prices.push({ ...publicPrice, id: 8, amount: 250 })
    data.links.push({ ...link, id: 16, avito_item_id: 502 })
    await resolveContext(h, data)
    const pending = h.api.prepare(), old = h.requests.at(-1)
    h.api.selectedPrice.value = 8
    await Vue.nextTick()
    old.resolve({ preview: preview() })
    await pending
    assert.equal(h.api.preview.value, null)
    assert.equal(h.api.canApply.value, false)
    await prepareDialog(h, { ...preview(), price: { ...preview().price, price_value_id: 8 } })
    h.api.confirmed.value = true
    h.api.selectedLink.value = 16
    await Vue.nextTick()
    assert.equal(h.api.preview.value, null)
    assert.equal(h.api.confirmed.value, false)
    const next = h.api.prepare(), request = h.requests.at(-1)
    assert.equal(request.url, '/api/avito/goods/42/links/16/preview')
    assert.equal(request.body.price_value_id, 8)
    request.resolve({ preview: preview() })
    await next
})

test('opening a different Good ignores late context, errors and mutation success from the old Good', async t => {
    const h = dialog(t), initial = [...h.requests]
    h.props.goodId = 88
    await Vue.nextTick()
    const current = h.requests.slice(initial.length)
    await resolveContext(h, context(88), status, current)
    initial.find(request => request.url.endsWith('/42')).reject({ response: { data: { message: 'Старая ошибка' } } })
    initial.find(request => request.url.endsWith('/status')).resolve(status)
    await flush()
    assert.equal(h.api.context.value.good.id, 88)
    assert.equal(h.api.error.value, '')
    await prepareDialog(h)
    h.api.confirmed.value = true
    const applying = h.api.apply(), oldApply = h.requests.at(-1)
    h.props.goodId = 99
    await Vue.nextTick()
    await resolveContext(h, context(99), status, h.requests.slice(-2))
    oldApply.resolve({ transfer: { price: { avito_value: 233 } } })
    await applying
    assert.equal(h.api.context.value.good.id, 99)
    assert.equal(h.api.notice.value, '')
    assert.equal(h.emitted.some(([name]) => name === 'applied'), false)
})

test('a failed or conflicting transfer requires a fresh preview and never reports success', async t => {
    const h = dialog(t)
    await resolveContext(h)
    await prepareDialog(h)
    h.api.confirmed.value = true
    const applying = h.api.apply()
    h.requests.at(-1).reject({ response: { status: 409, data: { errors: { price: ['Сохранённая цена изменилась.'] } } } })
    await applying
    assert.match(h.api.error.value, /Сохранённая цена изменилась/)
    assert.equal(h.api.preview.value, null)
    assert.equal(h.api.confirmed.value, false)
    assert.equal(h.api.canApply.value, false)
    assert.equal(h.api.notice.value, '')
    assert.equal(h.emitted.some(([name]) => name === 'applied'), false)
})

test('disabled mutations, absent links, and blocked previews cannot publish a price', async t => {
    const h = dialog(t)
    await resolveContext(h, context(), { ...status, mutations_enabled: false })
    assert.equal(h.api.canPreview.value, false)
    const reads = h.requests.length
    await h.api.prepare()
    assert.equal(h.requests.length, reads)
    h.api.status.value.mutations_enabled = true
    await prepareDialog(h, { ...preview(), price: { ...preview().price, can_apply: false, block_reason: 'Не задана единица.' } })
    h.api.confirmed.value = true
    assert.equal(h.api.canApply.value, false)
    await h.api.apply()
    assert.equal(h.requests.length, reads + 1)
})

test('a price dialog stays local while closed and routes an unlinked Good to Avito management', async t => {
    const h = dialog(t, { modelValue: false })
    assert.equal(h.requests.length, 0)
    h.props.modelValue = true
    await Vue.nextTick()
    await resolveContext(h, { ...context(), links: [] })
    assert.equal(Boolean(h.api.canPreview.value), false)
    const action = findVNode(h.render(), node => node.type === 'button' && String(node.children).includes('Открыть вкладку'))
    assert.ok(action)
    action.props.onClick()
    assert.ok(h.emitted.some(([name, value]) => name === 'update:modelValue' && value === false))
    assert.ok(h.emitted.some(([name]) => name === 'request-avito'))
    assert.equal(h.requests.length, 2, 'Navigation to management must not write a public listing')
})

test('only saved, public RUB prices with an explicit unit can start an Avito transfer', () => {
    const data = { types: [{ id: 2, is_public: true, is_active: true }, { id: 3, is_public: false, is_active: true }], currencies: [{ id: 1, code: 'RUB' }, { id: 2, code: 'USD' }], measurement }
    const saved = { id: 7, scope: 'common', target_id: 2, active: true, dirty: false, busy: false, currency_id: 1, measurement, saved: { price_gross: 233.2 } }
    assert.equal(goodAvito.avitoPriceBlockReason(saved, data), '')
    for (const variation of [
        { scope: 'entity' }, { target_id: 3 }, { id: null }, { dirty: true }, { busy: true },
        { active: false }, { currency_id: 2 }, { saved: { price_gross: null } },
        { saved: { price_gross: 0 } }, { saved: { price_gross: -10 } }, { measurement: {} },
    ]) {
        assert.ok(goodAvito.avitoPriceBlockReason({ ...saved, ...variation }, data), `Unsafe price must be blocked: ${JSON.stringify(variation)}`)
    }
})

test('public Avito links reject unrelated hosts and unsafe protocols', () => {
    assert.equal(goodAvito.avitoListingUrl('https://www.avito.ru/item_501'), 'https://www.avito.ru/item_501')
    assert.equal(goodAvito.avitoListingUrl('https://avito.ru/item_501'), 'https://avito.ru/item_501')
    for (const value of ['javascript:alert(1)', 'https://avito.ru.evil.example/item', 'https://evilavito.ru/item', 'http://avito.ru/item', '/relative']) {
        assert.equal(goodAvito.avitoListingUrl(value), null)
    }
})

test('explicit missing price and listing IDs never silently fall back to different records', async t => {
    const h = dialog(t, { priceValueId: 999, linkId: 888 })
    await resolveContext(h)
    assert.equal(h.api.selectedPrice.value, null)
    assert.equal(h.api.selectedLink.value, null)
    assert.equal(Boolean(h.api.canPreview.value), false)
    assert.ok(h.api.error.value)
})

const basisPreview = (mode, quantity, label) => ({
    ...preview(), source_fingerprint: `price-7-${mode}-${quantity}-${label}`,
    price: { ...preview().price, price_basis: { mode, quantity, label, measurement }, sale_unit_label: label, total_unrounded: Number((233.2 * quantity).toFixed(4)), avito_value: Math.round(233.2 * quantity), formula: `233,2 RUB × ${quantity} кг = ${Number((233.2 * quantity).toFixed(4))} RUB / ${label}` },
})
const vnodeText = node => typeof node === 'string' ? node : Array.isArray(node) ? node.map(vnodeText).join(' ') : node && vnodeText(node.children) || ''

test('price basis restores each listing snapshot and switches between a saved package and custom lot', async t => {
    const h = dialog(t), data = context()
    const packageBasis = { mode: 'package', quantity: 10, label: 'упаковка', measurement, package_kg: 10 }
    const customBasis = { mode: 'custom', quantity: 6.5, label: 'коробка', measurement }
    data.links[0].price_basis = packageBasis
    data.links.push({ ...link, id: 16, avito_item_id: 502, price_basis: customBasis })
    await resolveContext(h, data)
    assert.equal(h.api.basisMode.value, 'package')
    assert.deepEqual(h.api.body().price_basis, packageBasis)
    assert.equal(h.api.selectedBasis.value.quantity, 10)
    await prepareDialog(h, basisPreview('package', 10, 'упаковка'))
    h.api.confirmed.value = true
    h.api.selectedLink.value = 16
    assert.equal(h.api.preview.value, null, 'Switching listings invalidates confirmation synchronously')
    assert.equal(h.api.confirmed.value, false)
    assert.equal(h.api.basisMode.value, 'custom')
    assert.equal(Number(h.api.basisQuantity.value), 6.5)
    assert.equal(h.api.basisLabel.value, 'коробка')
    assert.deepEqual(h.api.body().price_basis, customBasis)
    h.api.basisQuantity.value = 4
    h.api.editBasis()
    assert.equal(h.api.basisSnapshot.value, null)
    assert.equal(customBasis.quantity, 6.5, 'Editing a draft must not mutate the stored link snapshot')
    h.api.selectedLink.value = 15
    assert.deepEqual(h.api.body().price_basis, packageBasis)
})

test('unit, package and custom lot use the server preview total and send basis identity without a trusted amount', async t => {
    const h = dialog(t)
    await resolveContext(h)
    for (const selection of [
        { mode: 'unit', quantity: 1, label: 'кг', body: { mode: 'unit' } },
        { mode: 'package', quantity: 10, label: 'упаковка', body: { mode: 'package' } },
        { mode: 'custom', quantity: 6.5, label: 'коробка', body: { mode: 'custom', quantity: 6.5, label: 'коробка' } },
    ]) {
        h.api.basisMode.value = selection.mode
        h.api.basisQuantity.value = selection.quantity
        h.api.basisLabel.value = selection.label
        h.api.editBasis()
        const pending = h.api.prepare(), request = h.requests.at(-1)
        assert.deepEqual(request.body.price_basis, selection.body)
        assert.equal('amount' in request.body, false)
        const result = basisPreview(selection.mode, selection.quantity, selection.label)
        request.resolve({ preview: result })
        await pending
        assert.equal(h.api.quantity.value, selection.quantity)
        assert.equal(h.api.saleUnit.value, selection.label)
        const rendered = h.render()
        const resultRow = findVNode(rendered, node => node.type === 'tr' && node.props?.class === 'total')
        assert.ok(vnodeText(resultRow).includes(goodAvito.avitoMoney(result.price.avito_value)))
        assert.match(vnodeText(resultRow), new RegExp(selection.label))
        const confirmation = findVNode(rendered, node => node.type === 'label' && node.props?.class === 'confirmation')
        assert.ok(vnodeText(confirmation).includes(goodAvito.avitoMoney(result.price.avito_value)))
        assert.ok(vnodeText(confirmation).includes(selection.label))
    }
    h.api.confirmed.value = true
    const applying = h.api.apply(), request = h.requests.at(-1)
    assert.deepEqual(request.body.price_basis, { mode: 'custom', quantity: 6.5, label: 'коробка' })
    assert.equal(request.body.expected_fingerprint, 'price-7-custom-6.5-коробка')
    assert.equal('amount' in request.body, false)
    assert.equal('avito_value' in request.body, false)
    const result = basisPreview('custom', 6.5, 'коробка')
    request.resolve({ transfer: { price: result.price }, link: { ...link, price_basis: result.price.price_basis } })
    await applying
    assert.match(h.api.notice.value, /1\s?516 RUB передана в Авито\. Объём: 1 коробка \(6,5 кг\)/)
    assert.deepEqual(h.api.basisSnapshot.value, result.price.price_basis)
})

test('basis mode, quantity and label edits revoke both the preview and its confirmation before another click', async t => {
    const h = dialog(t)
    await resolveContext(h)
    h.api.basisMode.value = 'custom'
    h.api.basisQuantity.value = 6.5
    h.api.basisLabel.value = 'коробка'
    h.api.editBasis()
    for (const edit of [
        () => { h.api.basisQuantity.value = 7 },
        () => { h.api.basisLabel.value = 'партия' },
        () => { h.api.basisMode.value = 'package' },
    ]) {
        await prepareDialog(h, basisPreview('custom', 6.5, 'коробка'))
        h.api.confirmed.value = true
        const before = h.requests.length
        edit()
        assert.equal(h.api.preview.value, null)
        assert.equal(h.api.confirmed.value, false)
        await h.api.apply()
        assert.equal(h.requests.length, before)
    }
    const pending = h.api.prepare(), old = h.requests.at(-1)
    h.api.basisMode.value = 'unit'
    h.api.editBasis()
    old.resolve({ preview: basisPreview('package', 10, 'упаковка') })
    await pending
    assert.equal(h.api.preview.value, null, 'A late package preview cannot approve a newly selected per-kg price')
    assert.equal(h.api.canApply.value, false)
})

test('unknown packaging and invalid custom quantities cannot trigger a preview, while a 10 kg package can equal one Good unit', async t => {
    const h = dialog(t), data = context()
    data.good.avito_price_basis.package = { mode: 'package', quantity: null, label: 'упаковка', available: false, block_reason: 'Фасовка не задана.' }
    await resolveContext(h, data)
    h.api.basisMode.value = 'package'
    const count = h.requests.length
    await h.api.prepare()
    assert.equal(h.requests.length, count)
    assert.match(h.api.basisError.value, /Фасовка/)
    h.api.basisMode.value = 'custom'
    for (const quantity of [0, -1, '', 'NaN']) {
        h.api.basisQuantity.value = quantity
        await h.api.prepare()
        assert.equal(h.requests.length, count)
        assert.ok(h.api.basisError.value)
    }
    h.api.basisQuantity.value = 2
    h.api.basisLabel.value = '  '
    await h.api.prepare()
    assert.equal(h.requests.length, count)
    h.api.basisQuantity.value = 0.000001
    h.api.basisLabel.value = 'проба'
    assert.match(h.api.example.value, /× 0,000001 кг/, 'A valid small sale quantity must never be displayed as zero')
    const box = { measure_id: 2, unit_label: 'кор.', kilograms_per_unit: 10 }
    h.api.context.value.measurement = box
    h.api.context.value.good.measurement = box
    h.api.context.value.good.avito_price_basis.package = { mode: 'package', quantity: 1, label: 'упаковка', package_kg: 10, available: true }
    h.api.basisMode.value = 'package'
    h.api.editBasis()
    assert.equal(h.api.selectedBasis.value.quantity, 1, 'A package of 10 kg must not multiply a price already defined per 10 kg box by ten again')
    assert.equal(h.api.basisError.value, '')
    assert.match(h.api.example.value, /× 1 кор\./)
})

test('changed product measurement requires an explicit basis acknowledgement and retains the original snapshot until then', async t => {
    const h = dialog(t), data = context()
    const oldMeasurement = { measure_id: 2, unit_label: 'кор.', kilograms_per_unit: 10 }
    const saved = { mode: 'custom', quantity: 2, label: 'партия', measurement: oldMeasurement }
    data.links[0].price_basis = saved
    await resolveContext(h, data)
    assert.equal(h.api.basisChanged.value, true)
    assert.deepEqual(h.api.body().price_basis, saved)
    const reads = h.requests.length
    await h.api.prepare()
    assert.equal(h.requests.length, reads)
    const acknowledge = findVNode(h.render(), node => node.type === 'button' && node.children === 'Использовать текущую единицу')
    assert.ok(acknowledge)
    acknowledge.props.onClick()
    assert.equal(h.api.basisChanged.value, false)
    assert.deepEqual(h.api.body().price_basis, { mode: 'custom', quantity: 2, label: 'партия' })
    assert.deepEqual(saved.measurement, oldMeasurement, 'Acknowledging a new unit does not rewrite history or silently convert quantity')
})

test('a stale Good context cannot overwrite a manually edited price basis in the current dialog', async t => {
    const h = dialog(t), oldRequests = [...h.requests]
    h.props.goodId = 88
    await Vue.nextTick()
    await resolveContext(h, context(88), status, h.requests.slice(oldRequests.length))
    h.api.basisMode.value = 'custom'
    h.api.basisQuantity.value = 3
    h.api.basisLabel.value = 'минимальная партия'
    h.api.editBasis()
    const stale = context()
    stale.links[0].price_basis = { mode: 'package', quantity: 10, label: 'упаковка', package_kg: 10, measurement }
    await resolveContext(h, stale, status, oldRequests)
    assert.equal(h.api.context.value.good.id, 88)
    assert.deepEqual(h.api.body().price_basis, { mode: 'custom', quantity: 3, label: 'минимальная партия' })
    assert.equal(h.api.basisSnapshot.value, null)
})

async function connectedTab(t, data = context(), props = {}) {
    const h = harness(t, 'GoodAvitoTab', { goodId: data.good_id, active: true, ...props })
    await resolveContext(h, data)
    h.requests.find(request => request.url === '/api/avito/listings/context').resolve({ account: { id: 120, name: 'Аккаунт поставщика' } })
    await flush()
    return h
}

test('Avito management loads on first visit and preserves local links if the provider is unavailable', async t => {
    const h = harness(t, 'GoodAvitoTab', { goodId: 42, active: false })
    assert.equal(h.requests.length, 0)
    h.props.active = true
    await Vue.nextTick()
    const data = context()
    h.requests.find(request => request.url.endsWith('/42')).resolve(data)
    h.requests.find(request => request.url.endsWith('/status')).reject({ response: { data: { message: 'Сервис временно недоступен' } } })
    await flush()
    assert.equal(h.api.links.value[0].id, 15)
    assert.match(h.api.connectionError.value, /временно недоступен/)
    assert.equal(h.api.apiReady.value, false)
    h.props.active = false
    await Vue.nextTick()
    h.props.active = true
    await Vue.nextTick()
    assert.equal(h.requests.length, 2, 'Revisiting a loaded tab does not flash or discard its context')
})

test('linking and unlinking use Good-scoped IDs and update the catalog only after a successful response', async t => {
    const h = await connectedTab(t)
    const reads = h.requests.length
    await h.api.attach('invalid')
    assert.equal(h.requests.length, reads)
    const failed = h.api.attach(502), rejected = h.requests.at(-1)
    assert.equal(rejected.url, '/api/avito/goods/42/links')
    assert.deepEqual(rejected.body, { account_id: 120, item_id: 502 })
    rejected.reject({ response: { status: 409, data: { message: 'Объявление уже связано с другим товаром.' } } })
    await failed
    assert.equal(h.api.links.value.length, 1)
    assert.equal(h.emitted.some(([name]) => name === 'changed'), false)
    const attached = h.api.attach(502)
    h.requests.at(-1).resolve({ link: { ...link, id: 16, avito_item_id: 502 }, listing: { item: { id: 502, status: 'active', title: 'Изюм новый', price: 233 } }, history: [] })
    await attached
    assert.equal(h.api.links.value.length, 2)
    assert.equal(h.api.selectedLink.value, 16)
    assert.equal(h.api.detail.value.title, 'Изюм новый')
    assert.equal(h.emitted.filter(([name]) => name === 'changed').length, 1)
    const remove = h.api.detach(), deletion = h.requests.at(-1)
    assert.equal(deletion.method, 'delete')
    assert.equal(deletion.url, '/api/avito/goods/42/links/16')
    deletion.resolve({})
    await remove
    assert.deepEqual(h.api.links.value.map(item => item.id), [15])
    assert.equal(h.api.selectedLink.value, 15)
    assert.equal(h.emitted.filter(([name]) => name === 'changed').length, 2)
})

test('old account connection and listing responses cannot replace another Good', async t => {
    const h = harness(t, 'GoodAvitoTab', { goodId: 42, active: true })
    await resolveContext(h)
    const oldConnect = h.requests.at(-1)
    h.props.goodId = 88
    await Vue.nextTick()
    await resolveContext(h, context(88), status, h.requests.slice(-2))
    const newConnect = h.requests.at(-1)
    newConnect.resolve({ account: { id: 999 } })
    await flush()
    oldConnect.resolve({ account: { id: 120 } })
    await flush()
    assert.equal(h.api.account.value.id, 999)
    const pending = h.api.refreshListing(), oldListing = h.requests.at(-1)
    h.props.goodId = 99
    await Vue.nextTick()
    await resolveContext(h, context(99), { ...status, enabled: false }, h.requests.slice(-2))
    oldListing.resolve({ item: { id: 501, title: 'Old listing', price: 50 } })
    await pending
    assert.equal(h.api.context.value.good.id, 99)
    assert.deepEqual(h.api.details.value, {})
})

test('listing pagination ignores stale page data and preserves the current page on failure', async t => {
    const h = await connectedTab(t)
    const first = h.api.loadListings(1), oldPage = h.requests.at(-1)
    const second = h.api.loadListings(2), newPage = h.requests.at(-1)
    assert.deepEqual(newPage.body.params, { account_id: 120, page: 2, per_page: 30, statuses: undefined })
    newPage.resolve({ items: [{ id: 502, title: 'Изюм' }] })
    await second
    oldPage.resolve({ items: [{ id: 501, title: 'Old page' }] })
    await first
    assert.equal(h.api.listPage.value, 2)
    assert.deepEqual(h.api.listings.value.map(item => item.id), [502])
    h.api.listingSearch.value = 'изюм'
    assert.deepEqual(h.api.filteredListings.value.map(item => item.id), [502])
    const failed = h.api.loadListings(3)
    h.requests.at(-1).reject({ response: { data: { message: 'Достигнут лимит Avito.' } } })
    await failed
    assert.equal(h.api.listPage.value, 2)
    assert.deepEqual(h.api.listings.value.map(item => item.id), [502])
    assert.match(h.api.error.value, /лимит/)
})

test('refreshing a listing never labels an older cached price as a current API value', async t => {
    const h = await connectedTab(t)
    h.api.details.value = { 15: { title: 'Изюм', price: 240 } }
    const pending = h.api.refreshListing()
    h.requests.at(-1).resolve({ item: { status: 'active', url: 'https://www.avito.ru/item_501' } })
    await pending
    assert.equal(h.api.detail.value.title, 'Изюм')
    assert.equal(h.api.detail.value.price, null)
    assert.equal(h.api.detail.value.status, 'active')
    assert.ok(h.api.detail.value.checked_at)
})

test('refreshing during connection verification replaces the old request without leaving the UI busy', async t => {
    const h = harness(t, 'GoodAvitoTab', { goodId: 42, active: true })
    await resolveContext(h)
    const oldConnect = h.requests.at(-1)
    assert.equal(h.api.connecting.value, true)
    const pending = h.api.load()
    await resolveContext(h, context(), status, h.requests.slice(-2))
    await pending
    const currentConnect = h.requests.at(-1)
    assert.notEqual(currentConnect, oldConnect)
    currentConnect.resolve({ account: { id: 120 } })
    await flush()
    oldConnect.resolve({ account: { id: 999 } })
    await flush()
    assert.equal(h.api.account.value.id, 120)
    assert.equal(h.api.connecting.value, false)
})

test('a successful price response whose link was concurrently removed refreshes the local linkage', async t => {
    const h = await connectedTab(t)
    const pending = h.api.priceApplied({ transfer: { link_changed: true }, link: null })
    assert.equal(h.requests.at(-1).url, '/api/avito/goods/42')
    h.requests.at(-1).resolve({ ...context(), links: [] })
    await pending
    assert.equal(h.api.link.value, null)
    assert.deepEqual(h.api.links.value, [])
    assert.equal(h.requests.at(-1).url, '/api/avito/goods/42', 'Do not request a removed link after successful price transmission')
})

test('preparing listing content copies only selected Good fields and invalidates an outdated preview', async t => {
    const data = context()
    data.good.media = Array.from({ length: 12 }, (_, index) => ({ id: index + 1 }))
    const h = await connectedTab(t, data)
    const pending = h.api.prepareContent(), request = h.requests.at(-1)
    assert.equal(request.url, '/api/avito/goods/42/links/15/preview')
    assert.deepEqual(request.body, { fields: ['title', 'description', 'images'], include_facts: true, media_ids: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10] })
    h.api.fields.value = ['title']
    await Vue.nextTick()
    request.resolve({ preview: { title: { good_value: 'Old material' } } })
    await pending
    assert.equal(h.api.prepared.value, null)
    const next = h.api.prepareContent(), current = h.requests.at(-1)
    assert.deepEqual(current.body, { fields: ['title'], include_facts: true, media_ids: [] })
    current.resolve({ preview: { title: { good_value: 'Изюм' } }, link, history: [{ id: 1, status: 'prepared' }] })
    await next
    assert.equal(h.api.prepared.value.title.good_value, 'Изюм')
    assert.equal(h.api.link.value.history[0].status, 'prepared')
    assert.equal(h.requests.some(request => request.url.endsWith('/apply')), false, 'Preparing content is not a remote mutation')
})

test('publication drafts remain mounted across Avito sub-tabs and old child state cannot block loading another Good', async t => {
    const h = await connectedTab(t)
    const isPublication = node => node.type?.name === 'AvitoPublications'
    assert.equal(findVNode(h.render(), isPublication), null)
    h.api.tab.value = 'publication'
    await Vue.nextTick()
    const editor = findVNode(h.render(), isPublication)
    assert.ok(editor)
    assert.equal(editor.props['good-id'], 42)
    editor.props.onState({ busy: false, dirty: true })
    await Vue.nextTick()
    assert.ok(h.emitted.some(([name, state]) => name === 'state' && state.dirty))
    h.api.tab.value = 'listings'
    await Vue.nextTick()
    assert.ok(findVNode(h.render(), isPublication), 'Switching sub-tabs must not destroy a publication draft')
    h.api.priceBusy.value = true
    h.api.publicationBusy.value = true
    await Vue.nextTick()
    const requests = h.requests.length
    h.props.goodId = 88
    await Vue.nextTick()
    assert.equal(h.api.priceBusy.value, false)
    assert.equal(h.api.publicationBusy.value, false)
    assert.equal(h.api.publicationDirty.value, false)
    assert.equal(h.requests.length, requests + 2)
    assert.equal(h.requests[requests].url, '/api/avito/goods/88')
})

test('the Prices tab exposes Avito only for a saved public price and passes its identity into the dialog', async t => {
    const h = harness(t, 'GoodPricingWorkspace', { goodId: 42, active: true })
    h.requests[0].resolve({
        rows: [{ id: 7, revision: 3, scope: 'common', target_id: 2, target_name: 'Розничная', price_gross: 233.2, price_net: 194.3333, currency_id: 1, vat_rate: 20, active: true }],
        measurement, vat_rate: 20, currencies: [{ id: 1, code: 'RUB' }],
        types: [{ id: 2, name: 'Розничная', is_active: true, is_public: true }, { id: 3, name: 'Партнёрская', is_active: true, is_public: false }], sources: [], partners: [], calculations: [],
    })
    await flush()
    const isAvitoButton = node => node.type === 'button' && node.props?.class === 'avito-button'
    const button = findVNode(h.render(), isAvitoButton)
    assert.ok(button)
    assert.equal(button.props.disabled, false)
    button.props.onClick()
    const priceDialog = findVNode(h.render(), node => node.type?.name === 'GoodAvitoPriceDialog')
    assert.equal(priceDialog.props.modelValue, true)
    assert.equal(priceDialog.props['good-id'], 42)
    assert.equal(priceDialog.props['price-value-id'], 7, 'The saved price ID, not its type or calculated amount, is passed to the dialog')
    priceDialog.props.onState({ busy: true })
    await Vue.nextTick()
    assert.equal(h.api.busy.value, true, 'The enclosing product modal must know a remote mutation is pending')
    priceDialog.props.onState({ busy: false })
    h.api.avitoOpen.value = false
    const row = h.api.rows.value[0]
    row.amount = 999
    h.api.touch(row)
    const dirtyButton = findVNode(h.render(), isAvitoButton)
    assert.equal(dirtyButton.props.disabled, true)
    assert.match(dirtyButton.props.title, /сохраните/)
    h.api.openAvito(row)
    assert.equal(h.api.avitoOpen.value, false)
    row.scope = 'entity'
    assert.equal(findVNode(h.render(), isAvitoButton), null, 'Entity-private prices have no Avito control')
    row.scope = 'common'
    row.target_id = 3
    assert.equal(findVNode(h.render(), isAvitoButton), null, 'Wholesale/partner types have no Avito control')
    priceDialog.props.onRequestAvito()
    assert.ok(h.emitted.some(([name]) => name === 'request-avito'))
    h.props.goodId = 88
    await Vue.nextTick()
    assert.equal(h.api.avitoOpen.value, false)
    assert.equal(h.api.avitoPrice.value, null)
})

