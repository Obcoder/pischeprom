import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const projectRoot = new URL('../../', import.meta.url)
const cartKey = 'pps-order-cart-v1'
const initialItems = [
    { good_id: 1, name: 'Мука', quantity: 2, price_gross: 120, denominator: 5 },
    { good_id: 2, name: 'Сахар', quantity: 1, price_gross: 90, denominator: 1 },
]

function cartHarness(user = null, storage = new Map([[cartKey, JSON.stringify(initialItems)]])) {
    const visits = []
    const requests = []
    const environment = {
        ...Vue,
        logo: '/logo.png',
        window: {
            localStorage: {
                getItem: key => storage.get(key) ?? null,
                setItem: (key, value) => storage.set(key, value),
            },
            addEventListener: () => {},
        },
        usePage: () => ({ props: { auth: { user } } }),
        useAppRoute: () => ({ route: name => name === 'customer.orders.store' ? '/orders' : '/login' }),
        router: { visit: url => visits.push(url) },
        axios: {
            post(url, body) {
                let resolve, reject
                const promise = new Promise((success, failure) => { resolve = success; reject = failure })
                requests.push({ url, body, resolve: data => resolve({ data }), reject })
                return promise
            },
        },
        DeliveryApartmentFields: { name: 'DeliveryApartmentFields' },
    }

    const stripImports = source => source.replace(/^import .+? from ['"].*['"];?$/gm, '')
    const cartSource = stripImports(readFileSync(new URL('resources/js/Composables/useOrderCart.js', projectRoot), 'utf8'))
        .replace('export function useOrderCart', 'function useOrderCart')
    environment.useOrderCart = new Function('env', `with(env){${cartSource};return useOrderCart}`)(environment)

    const filename = fileURLToPath(new URL('resources/js/Components/Orders/OrderCartStrip.vue', projectRoot))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'order-cart-strip' })
    const template = compileTemplate({
        source: descriptor.template.content,
        filename,
        id: 'order-cart-strip',
        compilerOptions: { bindingMetadata: compiled.bindings },
    })
    assert.deepEqual(template.errors, [])
    const script = stripImports(compiled.content).replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup({}, { expose: () => {} }))

    return {
        api, storage, visits, requests,
        render: templateRenderer(template, api),
        dispose: () => scope.stop(),
    }
}

function clearButton(harness) {
    return findVNode(harness.render(), node => node.type === 'button' && hasClass(node, 'order-cart-strip__clear'))
}

for (const [actor, user] of [['guest', null], ['customer', { id: 7 }]]) {
    test(`${actor} can clear every product and the cart remains empty after reloading`, async t => {
        const harness = cartHarness(user)
        t.after(harness.dispose)
        assert.equal(harness.api.itemsCount.value, 3)
        assert.equal(harness.api.totalAmount.value, 330)
        assert.equal(harness.api.totalWeight.value, 11)

        const clear = clearButton(harness)
        assert.ok(clear)
        assert.equal(clear.props.disabled, false)
        clear.props.onClick()
        await Vue.nextTick()

        assert.deepEqual(harness.api.items.value, [])
        assert.equal(harness.api.itemsCount.value, 0)
        assert.equal(harness.api.totalAmount.value, 0)
        assert.equal(harness.api.totalWeight.value, 0)
        assert.equal(harness.storage.get(cartKey), '[]')
        assert.equal(clearButton(harness), null)
        assert.ok(findVNode(harness.render(), node => hasClass(node, 'order-cart-strip__placeholder')))
        assert.deepEqual(harness.visits, [])
        assert.equal(harness.requests.length, 0)

        const reloaded = cartHarness(user, harness.storage)
        t.after(reloaded.dispose)
        assert.deepEqual(reloaded.api.items.value, [])
        assert.equal(clearButton(reloaded), null)
    })
}

test('clearing the cart closes checkout and removes stale order feedback', t => {
    const harness = cartHarness({ id: 7, phone: '+70000000000', delivery_address: 'Москва' })
    t.after(harness.dispose)
    harness.api.openCheckout()
    harness.api.fieldErrors.value = { customer_phone: ['Некорректный телефон'] }
    harness.api.errorMessage.value = 'Ошибка заказа'
    harness.api.successMessage.value = 'Предыдущий заказ создан'
    assert.equal(harness.api.checkoutOpen.value, true)

    clearButton(harness).props.onClick()

    assert.equal(harness.api.checkoutOpen.value, false)
    assert.deepEqual(harness.api.fieldErrors.value, {})
    assert.equal(harness.api.errorMessage.value, '')
    assert.equal(harness.api.successMessage.value, '')
    assert.equal(findVNode(harness.render(), node => hasClass(node, 'order-checkout')), null)
})

test('the clear action cannot change products while an order is being submitted', async t => {
    const harness = cartHarness({ id: 7, phone: '+70000000000', delivery_address: 'Москва' })
    t.after(harness.dispose)
    harness.api.openCheckout()
    harness.api.form.preferred_delivery_time = 'Завтра с 10 до 14'
    harness.api.submitOrder()
    assert.equal(harness.requests.length, 1)
    assert.deepEqual(harness.requests[0].body.items, [{ good_id: 1, quantity: 2 }, { good_id: 2, quantity: 1 }])
    assert.equal(harness.api.submitting.value, true)

    const clear = clearButton(harness)
    assert.equal(clear.props.disabled, true)
    clear.props.onClick()
    await Vue.nextTick()
    assert.equal(harness.api.items.value.length, 2)
    assert.equal(harness.api.checkoutOpen.value, true)
    assert.equal(JSON.parse(harness.storage.get(cartKey)).length, 2)

    harness.requests[0].reject({ response: { data: { message: 'Попробуйте снова' } } })
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(harness.api.submitting.value, false)
    assert.equal(clearButton(harness).props.disabled, false)
    clearButton(harness).props.onClick()
    await Vue.nextTick()
    assert.equal(harness.storage.get(cartKey), '[]')
    assert.equal(harness.api.errorMessage.value, '')
})
