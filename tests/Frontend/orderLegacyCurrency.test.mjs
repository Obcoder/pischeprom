import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { selectedApartment, selectedBuildingApartments } from '../../resources/js/utils/buildingApartments.js'
import { lineMeasurement, quantityWeight, unitLabel } from '../../resources/js/utils/goodMeasurement.js'

function editor() {
    const filename = fileURLToPath(new URL('../../resources/js/Pages/Ameise/Orders/Show.vue', import.meta.url))
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: 'legacy-order' })
    const environment = {
        ...Vue, axios: {}, Link: {}, router: {}, route: () => '', useHead: () => {}, onMounted: () => {},
        VerwalterLayout: {}, ApartmentSelector: {}, selectedApartment, selectedBuildingApartments,
        lineMeasurement, quantityWeight, unitLabel,
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    return component.setup({ orderId: 1, permissions: { edit: true } }, { expose: () => {} })
}

test('legacy order page preserves currencies and both price and amount based lines on unrelated edits', () => {
    const api = editor()
    api.fillForm({ currency_code: 'RUB', items: [
        { id: 10, good_id: 1, quantity: 0.001, unit_price: 1.2345, line_total: 0.0012, currency_code: 'USD', pricing_basis: 'unit_price' },
        { id: 11, good_id: 2, quantity: 3, unit_price: 33.3333, line_total: 100, currency_code: 'RUB', pricing_basis: 'total' },
    ] })
    api.form.internal_comment = 'Комментарий'
    const lines = api.contentPayload().items
    assert.equal(lines[0].currency_code, 'USD')
    assert.equal(lines[0].unit_price, 1.2345)
    assert.equal(Object.hasOwn(lines[0], 'line_total'), false)
    assert.equal(lines[1].line_total, 100)
    assert.deepEqual(api.calculatedTotals.value, { USD: 0.0012, RUB: 100 })
    api.form.items[1].quantity = 4
    assert.equal(Object.hasOwn(api.contentPayload().items[1], 'line_total'), false)
    assert.equal(api.itemTotal(api.form.items[1]), 133.3332)
    api.form.items[0].unit_price = 1.234567
    assert.equal(api.itemTotal(api.form.items[0]), 0.0012)
})

test('legacy order page retains explicit null prices and the historical order currency fallback', () => {
    const api = editor()
    api.fillForm({ currency_code: 'EUR', items: [{ id: 10, good_id: 1, quantity: 1, unit_price: null, price_gross: 25 }] })
    assert.equal(api.contentPayload().items[0].unit_price, null)
    assert.equal(api.contentPayload().items[0].currency_code, 'EUR')
})
