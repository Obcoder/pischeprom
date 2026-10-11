import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as catalog from '../../resources/js/utils/publicCatalog.js'
import * as priceList from '../../resources/js/utils/catalogPriceList.js'
import { unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const good = (id, changes = {}) => ({ id, name: `Товар ${id}`, denominator: 10, country: { name: 'Россия' }, catalog_node_id: 4,
    public_purchase: { price: 125.5, currency_code: 'RUB', can_order: true, measurement: { measure_id: 2, unit_label: 'кг', kilograms_per_unit: 1 } }, ...changes })

function harness(t, goods = [good(1), good(2)]) {
    const filename = 'resources/js/Components/Goods/PublicCatalogPriceList.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'catalogPriceList' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'catalogPriceList', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const emitted = []
    const cart = []
    const mounted = []
    const environment = { ...Vue, ...catalog, ...priceList, unitLabel, Link: 'Link', GoodStockAlertButton: 'GoodStockAlertButton',
        onMounted: handler => mounted.push(handler), usePublicGoodUrl: () => ({ goodPublicUrl: good => `/g/${good.id}` }),
        useOrderCart: () => ({ addGood: (...args) => { cart.push(args); return true }, cartError: Vue.ref('') }) }
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(environment)
    const props = Vue.reactive({ goods, nodes: [{ id: 4, name: 'Рыба' }], sort: 'name', from: 11 })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { props, api, cart, emitted, mounted, render: templateRenderer(template, api, props) }
}

test('price quantities accept comma fractions and enforce the cart limits without using package weight', () => {
    for (const [input, expected] of [['0,125', .125], [' 2.5 ', 2.5], ['0.001', .001], ['1.2346', 1.235], ['9999', 9999]]) {
        assert.equal(priceList.priceListQuantity(input), expected)
    }
    for (const invalid of ['', ' ', null, undefined, '-1', '0', '0,0001', '10000', '1,2,3', Infinity, '3kg', '=2+1']) {
        assert.equal(priceList.priceListQuantity(invalid), null)
    }
    assert.equal(priceList.priceListTotal(good(1), '0,5'), 62.75)
    assert.equal(priceList.priceListTotal(good(1, { denominator: 999 }), '0,5'), 62.75)
})

test('an unpriced public offer is orderable, while missing units and disabled offers are not', () => {
    const unpriced = good(1, { public_purchase: { ...good(1).public_purchase, price: null }, price_type_values: [{ price_gross: 9000 }] })
    assert.equal(priceList.priceListCanOrder(unpriced), true)
    assert.equal(priceList.priceListTotal(unpriced, 1), null)
    assert.equal(priceList.priceListCanOrder(good(2, { public_purchase: { price: 10, measurement: { measure_id: null } } })), false)
    assert.equal(priceList.priceListCanOrder(good(3, { public_purchase: { ...good(3).public_purchase, can_order: false } })), false)
    assert.equal(priceList.priceListCanOrder(good(4, { public_purchase: { price: 10, measurement: { measure_id: 2, unit_label: null } } })), false)
})

test('exports include only the current page or selected current rows, with public prices and actual units', () => {
    const goods = [good(10, { name: 'Рыба; "северная"' }), good(20, { public_purchase: { ...good(20).public_purchase, price: null }, price_type_values: [{ price_gross: 8777 }] })]
    assert.deepEqual(priceList.priceListScope(goods, new Set()).map(good => good.id), [10, 20])
    assert.deepEqual(priceList.priceListScope(goods, new Set(['20', '999'])).map(good => good.id), [20])
    assert.deepEqual(priceList.priceListScope(goods, new Set(['999'])), [])
    const csv = priceList.buildPriceListExport([goods[0]], { 10: '0,5' }, { format: 'csv', from: 21, pageGoods: goods, nodes: [{ id: 4, name: 'Рыба' }] })
    assert.ok(csv.startsWith('\uFEFF'))
    assert.ok(csv.includes('"21";"Рыба; ""северная""";"Рыба"'))
    assert.ok(csv.includes('"125,5";"RUB";"кг";"0,5";"62,75"'))
    const tsv = priceList.buildPriceListExport(goods, { 20: '2' })
    assert.ok(tsv.includes('Цена по запросу\tRUB\tкг\t2\t'))
    assert.ok(!tsv.includes('8777'))
    assert.equal(tsv.split('\r\n').length, 3)
})

test('spreadsheet exports neutralize formulas and keep cell and row boundaries intact', () => {
    const goods = ['=HYPERLINK("https://example.test")', ' +cmd', '\t@SUM(1)', '\r\n-2+3'].map((name, index) => good(index + 1, { name, country: { name: 'Россия\tСевер\nЗапад' } }))
    const tsv = priceList.buildPriceListExport(goods)
    const rows = tsv.split('\r\n')
    assert.equal(rows.length, 5)
    for (const row of rows.slice(1)) {
        const cells = row.split('\t')
        assert.equal(cells.length, 11)
        assert.ok(cells[1].startsWith("'"))
        assert.equal(cells[3], 'Россия Север Запад')
    }
    const csv = priceList.buildPriceListExport(goods, {}, { format: 'csv' })
    assert.ok(csv.includes('"\'=HYPERLINK(""https://example.test"")"'))
})

test('column settings work without a browser and ignore corrupted or unavailable storage', () => {
    assert.deepEqual(priceList.readPriceListColumns(), priceList.priceListDefaultColumns)
    assert.doesNotThrow(() => priceList.writePriceListColumns({ country: false }))
    assert.deepEqual(priceList.readPriceListColumns({ getItem: () => '{broken' }), priceList.priceListDefaultColumns)
    assert.deepEqual(priceList.readPriceListColumns({ getItem: () => '{"country":false,"pack":"false","unexpected":true}' }), { country: false, pack: true, availability: true })
    assert.deepEqual(priceList.readPriceListColumns({ getItem: () => { throw Error('blocked') } }), priceList.priceListDefaultColumns)
    assert.doesNotThrow(() => priceList.writePriceListColumns({}, { setItem: () => { throw Error('blocked') } }))
    const writes = []
    priceList.writePriceListColumns({ country: false, pack: true, availability: false, secret: true }, { setItem: (...args) => writes.push(args) })
    assert.deepEqual(writes, [[priceList.priceListStorageKey, '{"country":false,"pack":true,"availability":false}']])
})

test('rendered row controls add decimal quantities and always pass the authorized public offer', t => {
    const h = harness(t)
    const input = findVNode(h.render(), node => node.props?.['aria-label'] === 'Количество Товар 1, кг')
    input.props.onInput({ target: { value: '0,125' } })
    findVNode(h.render(), node => node.props?.['aria-label'] === 'Добавить Товар 1 в корзину').props.onClick()
    assert.equal(h.cart.length, 1)
    assert.equal(h.cart[0][0].id, 1)
    assert.equal(h.cart[0][1], .125)
    assert.equal(h.cart[0][2].price, 125.5)
    assert.equal(h.cart[0][2].measurement.measure_id, 2)
    assert.equal(h.emitted.at(-1)[0], 'notice')
    h.api.inquire(h.props.goods[0], 'bargain')
    assert.equal(h.emitted.at(-1)[1].quantity, .125)
    assert.equal(h.emitted.at(-1)[1].kind, 'bargain')
})

test('bulk selection rejects invalid amounts before changing the cart and retains unorderable rows', t => {
    const unavailable = good(3, { public_purchase: { price: null, measurement: { measure_id: null } } })
    const h = harness(t, [good(1), good(2), unavailable])
    findVNode(h.render(), node => node.props?.['aria-label'] === 'Выбрать все товары текущей страницы').props.onChange({ target: { checked: true } })
    assert.equal(h.api.selected.value.size, 3)
    h.api.quantityChanged('2', '0')
    h.api.addSelected()
    assert.equal(h.cart.length, 0)
    assert.equal(h.api.errors.value['2'], 'От 0,001 до 9999')
    h.api.quantityChanged('2', '2,5')
    h.api.addSelected()
    assert.deepEqual(h.cart.map(args => [args[0].id, args[1]]), [[1, 1], [2, 2.5]])
    assert.deepEqual([...h.api.selected.value], ['3'])
    assert.match(h.emitted.at(-1)[1].message, /Для 1 поз\. условия заказа/)
    assert.equal(findVNode(h.render(), node => node.props?.['aria-label'] === 'Добавить Товар 3 в корзину'), null)
    h.api.inquire(unavailable, 'email')
    assert.equal(h.emitted.at(-1)[1].kind, 'email')
})

test('priced products with unknown units use customer labels and cannot manufacture a cart order', t => {
    const missingUnit = good(3, { public_purchase: { price: 300, currency_code: 'RUB', can_order: false, measurement: { measure_id: null, unit_label: null } } })
    const h = harness(t, [missingUnit])
    const price = findVNode(h.render(), node => hasClass(node, 'price-list__price'))
    const label = findVNode(price, node => node.type === 'small')
    assert.ok(label.children.some(node => typeof node.children === 'string' && node.children.includes('Единицу уточним')))
    assert.equal(findVNode(h.render(), node => node.props?.['aria-label'] === 'Добавить Товар 3 в корзину'), null)
    h.api.selectAll(true)
    h.api.addSelected()
    h.api.addOne(missingUnit)
    h.api.inquire(missingUnit, 'order')
    assert.equal(h.cart.length, 0)
    assert.equal(h.emitted.length, 0)
    const csv = priceList.buildPriceListExport([missingUnit], {}, { format: 'csv' })
    assert.ok(csv.includes('"300";"RUB";"Уточняется";"";""'))
    assert.ok(!csv.includes('единица не задана'))
    assert.equal(priceList.priceListUnit(good(1)), 'кг')
    assert.equal(priceList.priceListUnit(missingUnit), null)
})

test('new pages and sorting clear selection and edited quantities so stale goods cannot be ordered', async t => {
    const h = harness(t)
    h.api.toggleSelection('1', true)
    h.api.quantityChanged('1', '4')
    h.props.goods = [good(7)]
    h.props.from = 21
    await Vue.nextTick()
    assert.equal(h.api.selected.value.size, 0)
    assert.deepEqual(h.api.quantities.value, { 7: '1' })
    assert.equal(h.api.rows.value[0].number, 21)
    h.api.addSelected()
    assert.equal(h.cart.length, 0)
    h.api.toggleSelection('7', true)
    h.props.sort = 'price_desc'
    await Vue.nextTick()
    assert.equal(h.api.selected.value.size, 0)
})

test('column sorting emits server sort keys and reports the active direction accessibly', t => {
    const h = harness(t)
    assert.ok(findVNode(h.render(), node => hasClass(node, 'catalog-price-list')))
    assert.equal(findVNode(h.render(), node => node.type === 'th' && hasClass(node, 'price-list__product')).props['aria-sort'], 'ascending')
    findVNode(h.render(), node => node.props?.['aria-label'] === 'Наименование: сортировать по убыванию').props.onClick()
    assert.deepEqual(h.emitted.at(-1), ['sort', 'name_desc'])
    h.props.sort = 'price_asc'
    findVNode(h.render(), node => node.props?.['aria-label'] === 'Цена: сортировать по убыванию').props.onClick()
    assert.deepEqual(h.emitted.at(-1), ['sort', 'price_desc'])
    assert.equal(h.api.ariaSort('price'), 'ascending')
})

test('selection, quantity edits and bulk cart additions clear stale copied state and fallback text', t => {
    const h = harness(t)
    const markCopied = () => { h.api.copied.value = true; h.api.copyFallback.value = 'Previously copied rows' }
    const assertCleared = () => { assert.equal(h.api.copied.value, false); assert.equal(h.api.copyFallback.value, '') }
    markCopied()
    h.api.toggleSelection('1', true)
    assertCleared()
    markCopied()
    h.api.quantityChanged('1', '2,5')
    assertCleared()
    markCopied()
    h.api.addSelected()
    assertCleared()
    assert.equal(h.api.selected.value.size, 0)
    assert.equal(h.cart[0][1], 2.5)
})

test('quantity keyboard navigation moves between editable rows without changing amounts', t => {
    const h = harness(t)
    const calls = []
    const inputs = [0, 1].map(index => ({ focus: () => calls.push(['focus', index]), select: () => calls.push(['select', index]) }))
    h.api.tableScroll.value = { querySelectorAll: () => inputs }
    let prevented = 0
    h.api.moveQuantity({ key: 'Enter', target: inputs[0], preventDefault: () => prevented++ })
    h.api.moveQuantity({ key: 'ArrowUp', target: inputs[1], preventDefault: () => prevented++ })
    assert.equal(prevented, 2)
    assert.deepEqual(calls, [['focus', 1], ['select', 1], ['focus', 0], ['select', 0]])
    assert.deepEqual(h.api.quantities.value, { 1: '1', 2: '1' })
})
