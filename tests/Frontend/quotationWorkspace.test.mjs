import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

const filename = 'resources/js/Components/Quotations/QuotationWorkspace.vue'
const { descriptor, errors: parseErrors } = parse(readFileSync(filename, 'utf8'), { filename })
assert.deepEqual(parseErrors, [])
const compiled = compileScript(descriptor, { id: 'quotation-workspace-test' })
const template = compileTemplate({
    source: descriptor.template.content, filename, id: 'quotation-workspace-test',
    compilerOptions: { bindingMetadata: compiled.bindings },
})
assert.deepEqual(template.errors, [])

const dictionaries = () => ({
    currencies: [{ id: 1, code: 'RUB', name: 'Российский рубль' }, { id: 2, code: 'EUR', name: 'Евро' }],
    measures: [{ id: 3, name: 'кг' }, { id: 4, name: 'уп.' }],
    units: [{ id: 10, name: 'Лето-К' }],
})
const quotation = (overrides = {}) => ({
    id: 25, good_id: 66, unit_id: 10, price: '2580.0000', currency_id: 1, measure_id: 3, denominator: '10.0000',
    quoted_at: '2026-01-15', valid_until: null, price_includes_vat: true, vat_rate: '10.0000',
    minimum_quantity: '20.0000', lead_time_days: 2, notes: 'Самовывоз',
    unit: { id: 10, name: 'Лето-К' }, good: { id: 66, name: 'Манго слайсы' },
    currency: { id: 1, code: 'RUB' }, measure: { id: 3, name: 'кг' }, ...overrides,
})

function harness(t, overrides = {}) {
    const requests = [], emitted = [], timers = new Map()
    let timerId = 0
    function request(method, url, payload) {
        let resolve, reject
        const pending = new Promise((success, failure) => { resolve = success; reject = failure })
        requests.push({ method, url, payload, resolve: data => resolve({ data }), reject })
        return pending
    }
    const environment = {
        ...Vue,
        axios: Object.fromEntries(['get', 'post', 'put', 'delete'].map(method => [method, (url, payload) => request(method, url, payload)])),
        setTimeout: callback => { timers.set(++timerId, callback); return timerId },
        clearTimeout: id => timers.delete(id),
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ good: { id: 66, name: 'Манго слайсы', measure_id: 3 }, unit: null, quotations: [], dict: dictionaries(), ...overrides })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return {
        api, props, requests, emitted, scope, render: templateRenderer(template, api, props),
        runTimers() { const callbacks = [...timers.values()]; timers.clear(); return callbacks.map(callback => callback()) },
    }
}

function newDraft(api, values = {}) {
    api.open()
    Object.assign(api.form, { unit_id: 10, price: '2580', denominator: '10', ...values })
}

const settle = async () => { await new Promise(resolve => setImmediate(resolve)); await Vue.nextTick() }

test('new quotation uses the product measure and RUB; legacy missing currency requires an explicit choice', async t => {
    const { api, requests } = harness(t)
    api.open()
    assert.equal(api.form.good_id, 66)
    assert.equal(api.form.currency_id, 1)
    assert.equal(api.form.measure_id, 3)
    assert.equal(api.dirty.value, false)
    api.open(quotation({ currency_id: null, currency: null }))
    await Vue.nextTick()
    assert.equal(api.form.currency_id, null)
    await api.save()
    assert.ok(api.errors.value.currency_id.length)
    assert.equal(requests.length, 0)
    assert.equal(api.dialog.value, true)
    assert.equal(api.status(quotation({ currency_id: null })).key, 'incomplete')
})

test('missing required fields and invalid amounts keep the editor open without a request', async t => {
    const { api, requests } = harness(t)
    newDraft(api, { unit_id: null, currency_id: null, measure_id: null, price: '-1', denominator: '0' })
    await api.save()
    for (const field of ['unit_id', 'currency_id', 'measure_id', 'price', 'denominator']) assert.ok(api.errors.value[field], field)
    assert.equal(requests.length, 0)
    assert.equal(api.dialog.value, true)
})

test('price basis and the price per unit preserve four-digit precision, including decimal commas', t => {
    const { api } = harness(t)
    newDraft(api, { price: '258,1234', denominator: '2,5' })
    assert.equal(api.payload().price, 258.1234)
    assert.equal(api.payload().denominator, 2.5)
    assert.equal(api.unitPrice.value, 258.1234 / 2.5)
    assert.equal(api.money(api.unitPrice.value), (258.1234 / 2.5).toLocaleString('ru-RU', { maximumFractionDigits: 4 }))
    assert.equal(api.form.price, '258,1234', 'preview must not round or rewrite the source price')
    assert.equal(api.form.denominator, '2,5')
})

test('VAT payload preserves unknown, included zero and excluded VAT as distinct conditions', t => {
    const { api } = harness(t)
    newDraft(api)
    assert.equal(api.payload().price_includes_vat, null)
    assert.equal(api.payload().vat_rate, null)
    assert.equal(api.grossUnitPrice.value, null)
    Object.assign(api.form, { vat_mode: 'included', vat_rate: '0' })
    assert.equal(api.payload().price_includes_vat, true)
    assert.equal(api.payload().vat_rate, 0)
    assert.equal(api.grossUnitPrice.value, 258)
    Object.assign(api.form, { vat_mode: 'excluded', vat_rate: '10' })
    assert.equal(api.payload().price_includes_vat, false)
    assert.equal(api.payload().vat_rate, 10)
    assert.ok(Math.abs(api.grossUnitPrice.value - 283.8) < 0.000001)
    api.form.vat_mode = 'unknown'
    assert.equal(api.payload().vat_rate, null, 'a stale rate must not accompany an unknown tax mode')
    api.open(quotation({ price_includes_vat: false, vat_rate: 0 }))
    assert.equal(api.form.vat_mode, 'excluded')
    assert.equal(api.payload().vat_rate, 0)
})

test('specified VAT requires a finite rate in the inclusive range from zero to one hundred', async t => {
    const { api, requests } = harness(t)
    newDraft(api, { vat_mode: 'excluded' })
    for (const rate of ['', '-1', '101', 'not-a-rate']) {
        api.form.vat_rate = rate
        const pending = api.save()
        requests.at(-1)?.reject(new Error('Invalid tax input must not be submitted'))
        await pending
        assert.ok(api.errors.value.vat_rate, rate || 'blank')
    }
    assert.equal(requests.length, 0)
})

test('save submits all commercial terms, ignores a double click and emits the persisted quotation', async t => {
    const { api, requests, emitted } = harness(t)
    newDraft(api, {
        price: '2580,1234', quoted_at: '2026-01-15', valid_until: '2026-12-31', vat_mode: 'included', vat_rate: '10',
        minimum_quantity: '20,5', lead_time_days: '2', notes: '  Самовывоз после оплаты  ',
    })
    await Vue.nextTick()
    assert.equal(api.dirty.value, true)
    const first = api.save(), second = api.save()
    api.close()
    await Vue.nextTick()
    assert.equal(api.dialog.value, true, 'an in-flight save cannot be dismissed')
    assert.equal(api.busy.value, true)
    assert.equal(requests.length, 1)
    assert.equal(requests[0].method, 'post')
    assert.equal(requests[0].url, '/api/quotations')
    assert.deepEqual(requests[0].payload, {
        good_id: 66, unit_id: 10, price: 2580.1234, currency_id: 1, measure_id: 3, denominator: 10,
        quoted_at: '2026-01-15', valid_until: '2026-12-31', price_includes_vat: true, vat_rate: 10,
        minimum_quantity: 20.5, lead_time_days: 2, notes: 'Самовывоз после оплаты',
    })
    assert.ok(emitted.some(([name, state]) => name === 'state' && state.dirty && state.busy))
    const saved = quotation({ id: 88, price: '2580.1234' })
    requests[0].resolve({ data: saved })
    await Promise.all([first, second])
    assert.deepEqual(emitted.filter(([name]) => name === 'changed'), [['changed', saved]])
    assert.equal(api.dialog.value, false)
    assert.equal(api.saving.value, false)
    assert.equal(api.dirty.value, false)
    assert.ok(api.notice.value)
})

test('editing updates the same quotation and retains its currency, basis and optional terms', async t => {
    const { api, requests, emitted } = harness(t)
    api.open(quotation({ currency_id: 2, currency: { id: 2, code: 'EUR' } }))
    assert.equal(api.form.currency_id, 2)
    api.form.price = '26.4321'
    const pending = api.save()
    assert.equal(requests[0].method, 'put')
    assert.equal(requests[0].url, '/api/quotations/25')
    assert.equal(requests[0].payload.currency_id, 2)
    assert.equal(requests[0].payload.denominator, 10)
    assert.equal(requests[0].payload.minimum_quantity, 20)
    assert.equal(requests[0].payload.notes, 'Самовывоз')
    const saved = quotation({ price: '26.4321', currency_id: 2 })
    requests[0].resolve(saved)
    await pending
    assert.deepEqual(emitted.filter(([name]) => name === 'changed'), [['changed', saved]])
})

test('editing a legacy quotation does not turn the record creation timestamp into its commercial date', async t => {
    const { api, requests } = harness(t)
    api.open(quotation({ quoted_at: null, created_at: '2026-10-15T13:45:00.000000Z' }))
    assert.equal(api.form.quoted_at, '')
    assert.equal(api.dirty.value, false)
    api.form.notes = 'Уточнены условия поставки'
    const pending = api.save()
    assert.equal(requests[0].payload.quoted_at, null)
    assert.equal(requests[0].method, 'put')
    requests[0].resolve({ data: quotation({ quoted_at: null, notes: 'Уточнены условия поставки' }) })
    await pending
    assert.equal(api.dialog.value, false)
})

test('server validation keeps every draft field and shows the field errors for correction', async t => {
    const { api, requests, emitted } = harness(t)
    newDraft(api, { notes: 'Не терять условия', valid_until: '2000-01-01', currency_id: 2 })
    const snapshot = structuredClone(Vue.toRaw(api.form))
    const pending = api.save()
    requests[0].reject({ response: { status: 422, data: { errors: {
        valid_until: ['Окончание должно быть после даты предложения.'], currency_id: ['Валюта недоступна.'],
    } } } })
    await pending
    assert.deepEqual(Vue.toRaw(api.form), snapshot)
    assert.equal(api.dialog.value, true)
    assert.equal(api.saving.value, false)
    assert.ok(api.error.value)
    assert.deepEqual(api.errors.value.currency_id, ['Валюта недоступна.'])
    assert.equal(emitted.filter(([name]) => name === 'changed').length, 0)
})

test('network failure is retryable and cannot erase the draft', async t => {
    const { api, requests } = harness(t)
    newDraft(api, { notes: 'Срок поставки согласовать' })
    const first = api.save()
    requests[0].reject(new Error('offline'))
    await first
    assert.ok(api.error.value)
    assert.equal(api.form.notes, 'Срок поставки согласовать')
    const retry = api.save()
    assert.equal(requests.length, 2)
    assert.equal(api.error.value, '')
    requests[1].resolve({ data: quotation() })
    await retry
    assert.equal(api.dialog.value, false)
})

test('supplier context fixes the Unit while selecting a product supplies its measure', async t => {
    const { api, requests } = harness(t, { good: null, unit: { id: 10, name: 'Лето-К' }, dict: { ...dictionaries(), goods: [{ id: 70, name: 'Мука', measure_id: 4 }] } })
    api.open()
    assert.equal(api.form.unit_id, 10)
    assert.equal(api.form.good_id, null)
    api.form.good_id = 70
    api.selectedGood(70)
    assert.equal(api.form.measure_id, 4)
    api.form.price = '800'
    const pending = api.save()
    assert.equal(requests[0].payload.good_id, 70)
    assert.equal(requests[0].payload.unit_id, 10)
    assert.equal(requests[0].payload.measure_id, 4)
    requests[0].resolve({ data: quotation({ good_id: 70, measure_id: 4 }) })
    await pending
})

test('deleting is idempotent while pending and a failure leaves the row available with a useful message', async t => {
    const row = quotation()
    const { api, props, requests, emitted } = harness(t, { quotations: [row] })
    api.removing.value = row
    const first = api.remove(), second = api.remove()
    assert.equal(requests.length, 1)
    assert.equal(requests[0].method, 'delete')
    assert.equal(requests[0].url, '/api/quotations/25')
    requests[0].reject({ response: { status: 409, data: { message: 'Предложение используется в заказе.' } } })
    await Promise.all([first, second])
    assert.equal(api.tableError.value, 'Предложение используется в заказе.')
    assert.equal(api.deleting.value, false)
    assert.equal(props.quotations.length, 1)
    assert.equal(emitted.filter(([name]) => name === 'changed').length, 0)
    api.removing.value = row
    const retry = api.remove()
    requests[1].resolve({})
    await retry
    assert.deepEqual(emitted.filter(([name]) => name === 'changed'), [['changed', null]])
    assert.equal(api.removing.value, null)
    assert.equal(api.tableError.value, '')
})

test('delayed dictionary loading defaults only new currency and preserves user changes as dirty', async t => {
    const { api, requests } = harness(t, { dict: { ...dictionaries(), currencies: [] } })
    api.open()
    assert.equal(requests[0].url, '/api/currencies')
    Object.assign(api.form, { price: '100', notes: 'Введено до загрузки валют' })
    requests[0].resolve({ data: dictionaries().currencies })
    await settle()
    assert.equal(api.form.currency_id, 1)
    assert.equal(api.dirty.value, true)
    assert.equal(api.form.notes, 'Введено до загрузки валют')
})

test('delayed currency lookup never fills in an unknown currency on an existing quotation', async t => {
    const { api, requests } = harness(t, { dict: { ...dictionaries(), currencies: [] } })
    api.open(quotation({ currency_id: null, currency: null }))
    requests[0].resolve(dictionaries().currencies)
    await settle()
    assert.equal(api.form.currency_id, null)
    assert.equal(api.dirty.value, false)
})

test('out-of-order goods search responses cannot replace the newest results', async t => {
    const { api, requests, runTimers } = harness(t, { good: null, unit: { id: 10, name: 'Лето-К' } })
    api.open()
    api.searchGoods('ман')
    const [first] = runTimers()
    api.searchGoods('манго')
    const [second] = runTimers()
    assert.equal(requests[0].payload.signal.aborted, true)
    assert.equal(requests[1].payload.params.search, 'манго')
    const latest = [{ id: 66, name: 'Манго слайсы' }]
    requests[1].resolve({ data: latest })
    await second
    requests[0].resolve({ data: [{ id: 1, name: 'Манка' }] })
    await first
    assert.deepEqual(Vue.toRaw(api.lookupGoods.value), latest)
    assert.equal(api.goodsLoading.value, false)
})

test('changing Unit invalidates goods lookups and clears search state from the previous context', async t => {
    const { api, props, requests, runTimers } = harness(t, { good: null, unit: { id: 10, name: 'Лето-К' } })
    api.open()
    api.searchGoods('манго')
    const [search] = runTimers()
    props.unit = { id: 11, name: 'Другой поставщик' }
    await Vue.nextTick()
    requests[0].resolve({ data: [{ id: 66, name: 'Манго слайсы' }] })
    await search
    assert.deepEqual(api.lookupGoods.value, [])
    assert.equal(api.goodSearch.value, '')
    assert.equal(api.goodsLoading.value, false)
    api.open()
    assert.equal(api.form.unit_id, 11)
})

test('a late save cannot close or overwrite the form of another product', async t => {
    const { api, props, requests, emitted } = harness(t)
    newDraft(api)
    const pending = api.save()
    props.good = { id: 67, name: 'Другой товар', measure_id: 4 }
    await Vue.nextTick()
    assert.equal(api.saving.value, false)
    api.open()
    api.form.price = '700'
    requests[0].resolve({ data: quotation() })
    await pending
    assert.equal(api.dialog.value, true)
    assert.equal(api.form.good_id, 67)
    assert.equal(api.form.price, '700')
    assert.equal(api.notice.value, '')
    assert.equal(emitted.filter(([name]) => name === 'changed').length, 0)
})

test('refreshing the same product preserves its draft, pending save and resulting success notice', async t => {
    const { api, props, requests, emitted } = harness(t)
    newDraft(api, { price: '777.1234', notes: 'Несохранённые условия' })
    const draft = structuredClone(Vue.toRaw(api.form))
    props.good = { id: 66, name: 'Манго слайсы, обновлённое название', measure_id: 3 }
    props.quotations = [quotation()]
    await Vue.nextTick()
    assert.equal(api.dialog.value, true)
    assert.deepEqual(Vue.toRaw(api.form), draft)
    assert.equal(api.dirty.value, true)
    const pending = api.save()
    props.good = { ...props.good, updated_at: '2026-10-15T14:00:00Z' }
    await Vue.nextTick()
    assert.equal(api.saving.value, true)
    requests[0].resolve({ data: quotation({ price: '777.1234' }) })
    await pending
    assert.equal(emitted.filter(([name]) => name === 'changed').length, 1)
    const notice = api.notice.value
    assert.ok(notice)
    props.good = { ...props.good, quotations_count: 2 }
    await Vue.nextTick()
    assert.equal(api.notice.value, notice)
})

test('refreshing the same supplier preserves an active price-list draft', async t => {
    const { api, props } = harness(t, { good: null, unit: { id: 10, name: 'Лето-К' } })
    newDraft(api, { good_id: 70, measure_id: 3, price: '250', notes: 'Не потерять черновик' })
    const draft = structuredClone(Vue.toRaw(api.form))
    props.unit = { id: 10, name: 'Лето-К, обновлённая карточка' }
    await Vue.nextTick()
    assert.equal(api.dialog.value, true)
    assert.deepEqual(Vue.toRaw(api.form), draft)
    assert.equal(api.dirty.value, true)
})

test('exposed reset releases a pending mutation and ignores its later failure', async t => {
    const { api, requests } = harness(t)
    newDraft(api)
    const pending = api.save()
    api.reset()
    assert.equal(api.busy.value, false)
    api.open()
    api.form.price = '321'
    requests[0].reject({ response: { status: 422, data: { errors: { price: ['Старая ошибка'] } } } })
    await pending
    assert.equal(api.dialog.value, true)
    assert.equal(api.form.price, '321')
    assert.equal(api.error.value, '')
    assert.deepEqual(api.errors.value, {})
})

test('a late delete cannot emit a refresh or notice for a different product', async t => {
    const { api, props, requests, emitted } = harness(t)
    api.removing.value = quotation()
    const pending = api.remove()
    props.good = { id: 67, name: 'Другой товар' }
    await Vue.nextTick()
    requests[0].resolve({})
    await pending
    assert.equal(api.deleting.value, false)
    assert.equal(api.notice.value, '')
    assert.equal(emitted.filter(([name]) => name === 'changed').length, 0)
})

test('table sorting, filters and search reset reveal the original rows without mutating them', t => {
    const rows = [
        quotation({ id: 1, quoted_at: '2026-01-01', currency_id: null, currency: null }),
        quotation({ id: 2, quoted_at: '2026-01-02', valid_until: '2000-01-01' }),
        quotation({ id: 3, quoted_at: '2026-01-03', unit: { id: 12, name: 'Фруктовый мир' } }),
        quotation({ id: 4, quoted_at: '2999-01-01' }),
    ]
    const { api, props, render } = harness(t, { quotations: rows })
    assert.deepEqual(api.visibleRows.value.map(row => row.id), [4, 3, 2, 1])
    assert.equal(api.incompleteCount.value, 1)
    api.statusFilter.value = 'incomplete'
    assert.deepEqual(api.visibleRows.value.map(row => row.id), [1])
    api.statusFilter.value = 'all'
    api.search.value = '  фРУКтовый  '
    assert.deepEqual(api.visibleRows.value.map(row => row.id), [3])
    const clear = findVNode(render(), node => node.props?.['aria-label'] === 'Очистить поиск предложений')
    assert.ok(clear)
    clear.props.onClick()
    assert.equal(api.visibleRows.value.length, 4)
    assert.deepEqual(props.quotations.map(row => row.id), [1, 2, 3, 4])
})

test('the actual form submit binding saves and displays the normalized price-per-unit preview', async t => {
    const { api, requests, render } = harness(t)
    newDraft(api)
    const form = findVNode(render(), node => node.type === 'form')
    assert.ok(form)
    let prevented = false
    const pending = form.props.onSubmit({ preventDefault: () => { prevented = true } })
    assert.equal(prevented, true)
    assert.equal(requests.length, 1)
    const currency = findVNode(render(), node => node.type === 'v-select' && node.props.label === 'Валюта *')
    assert.ok(currency)
    assert.equal(currency.props.disabled, true)
    assert.equal(currency.props.modelValue, 1)
    requests[0].resolve({ data: quotation() })
    await pending
})

test('the supplier price list links to the product card through the existing plural goods route', t => {
    const { render } = harness(t, { good: null, unit: { id: 10, name: 'Лето-К' }, quotations: [quotation()] })
    const link = findVNode(render(), node => node.type === 'a' && node.props?.href === '/Ameise/goods/66')
    assert.ok(link)
    assert.equal(link.children, 'Манго слайсы')
})
