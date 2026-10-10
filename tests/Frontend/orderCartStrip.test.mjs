import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { measurementForGood, quantityWeight } from '../../resources/js/utils/goodMeasurement.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const projectRoot = new URL('../../', import.meta.url)
const cartKey = 'pps-order-cart-v2'
const initialItems = [
    { good_id: 1, name: 'Мука', quantity: 2, price_gross: 120, denominator: 5, measurement: { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 } },
    { good_id: 2, name: 'Сахар', quantity: 1, price_gross: 90, denominator: 1, measurement: { measure_id: 2, unit_label: 'кор.', kilograms_per_unit: 10 } },
]

const stripImports = source => source.replace(/^import .+? from ['"].*['"];?$/gm, '')

function cartModule(environment) {
    const source = stripImports(readFileSync(new URL('resources/js/Composables/useOrderCart.js', projectRoot), 'utf8'))
        .replace('export function useOrderCart', 'function useOrderCart')
    return new Function('env', `with(env){${source};return useOrderCart}`)(environment)
}

function cartHarness(user = null, storage = new Map([[cartKey, JSON.stringify(initialItems)]]), { mounted = true } = {}) {
    const visits = []
    const requests = []
    const mountedCallbacks = []
    const storageReads = []
    const environment = {
        ...Vue, measurementForGood, quantityWeight,
        onMounted: callback => mountedCallbacks.push(callback),
        logo: '/logo.png',
        window: {
            localStorage: {
                getItem: key => {
                    storageReads.push(key)
                    return storage.get(key) ?? null
                },
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

    environment.useOrderCart = cartModule(environment)

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
    const mount = () => scope.run(() => mountedCallbacks.splice(0).forEach(callback => callback()))
    if (mounted) mount()

    return {
        api, storage, visits, requests, storageReads, mount,
        sharedCart: () => scope.run(environment.useOrderCart),
        render: templateRenderer(template, api),
        dispose: () => scope.stop(),
    }
}

function clearButton(harness) {
    return findVNode(harness.render(), node => node.type === 'button' && hasClass(node, 'order-cart-strip__clear'))
}

test('the first render stays empty for hydration and shared consumers restore storage after mounting', async t => {
    const harness = cartHarness(null, undefined, { mounted: false })
    t.after(harness.dispose)
    const shared = harness.sharedCart()
    const firstRender = harness.render()

    assert.deepEqual(harness.storageReads, [])
    assert.deepEqual(harness.api.items.value, [])
    assert.equal(shared.itemsCount.value, 0)
    assert.ok(findVNode(firstRender, node => hasClass(node, 'order-cart-strip--empty')))
    assert.ok(findVNode(firstRender, node => hasClass(node, 'order-cart-strip__placeholder')))
    assert.equal(clearButton(harness), null)
    assert.equal(JSON.parse(harness.storage.get(cartKey)).length, 2)

    harness.mount()
    await Vue.nextTick()

    assert.deepEqual(harness.storageReads, [cartKey])
    assert.equal(harness.api.items.value, shared.items.value)
    assert.equal(harness.api.items.value.length, 2)
    assert.equal(shared.itemsCount.value, 2)
    assert.ok(findVNode(harness.render(), node => hasClass(node, 'order-cart-strip__rail')))
    assert.equal(findVNode(harness.render(), node => hasClass(node, 'order-cart-strip__placeholder')), null)
    assert.equal(findVNode(harness.render(), node => hasClass(node, 'order-cart-strip--empty')), null)
    assert.ok(clearButton(harness))

    clearButton(harness).props.onClick()
    await Vue.nextTick()
    assert.equal(shared.itemsCount.value, 0)
    assert.equal(harness.storage.get(cartKey), '[]')
})

test('cart persistence survives unmounting the first consumer and changing layouts', async t => {
    const storage = new Map([[cartKey, JSON.stringify(initialItems)]])
    const useOrderCart = cartModule({
        ...Vue, measurementForGood, quantityWeight,
        logo: '/logo.png',
        window: {
            localStorage: {
                getItem: key => storage.get(key) ?? null,
                setItem: (key, value) => storage.set(key, value),
            },
            addEventListener: () => {},
        },
    })
    // Use real component mount/unmount hooks and watcher scopes; the host tree
    // needs only a static element to exercise the shared composable lifecycle.
    const renderer = Vue.createRenderer({
        createElement: type => ({ type, children: [] }),
        createText: text => ({ text }),
        createComment: text => ({ text }),
        insert: (node, parent) => { node.parent = parent; parent.children.push(node) },
        remove: node => node.parent.children.splice(node.parent.children.indexOf(node), 1),
        setText: (node, text) => { node.text = text },
        setElementText: (node, text) => { node.text = text },
        parentNode: node => node.parent,
        nextSibling: () => null,
        patchProp: () => {},
    })
    function mountConsumer() {
        let cart
        const app = renderer.createApp({
            setup() {
                cart = useOrderCart()
                return () => Vue.h('div')
            },
        })
        app.mount({ children: [] })
        return { app, cart }
    }

    const first = mountConsumer()
    await Vue.nextTick()
    assert.equal(first.cart.items.value.length, 2)
    first.app.unmount()

    const next = mountConsumer()
    t.after(() => next.app.unmount())
    assert.equal(next.cart.items.value, first.cart.items.value)
    next.cart.clearCart()
    await Vue.nextTick()
    assert.deepEqual(next.cart.items.value, [])
    assert.equal(storage.get(cartKey), '[]')
})

for (const [actor, user] of [['guest', null], ['customer', { id: 7 }]]) {
    test(`${actor} can clear every product and the cart remains empty after reloading`, async t => {
        const harness = cartHarness(user)
        t.after(harness.dispose)
        assert.equal(harness.api.itemsCount.value, 2)
        assert.equal(harness.api.totalAmount.value, 330)
        assert.equal(harness.api.totalWeight.value, 12)

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
    assert.deepEqual(harness.requests[0].body.items, [{ good_id: 1, quantity: 2, measure_id: 1, measurement: initialItems[0].measurement, pricing_context: 'catalog' }, { good_id: 2, quantity: 1, measure_id: 2, measurement: initialItems[1].measurement, pricing_context: 'catalog' }])
    assert.equal(harness.api.submitting.value, true)
    for (const label of ['Уменьшить количество', 'Увеличить количество', 'Убрать из корзины']) {
        assert.equal(findVNode(harness.render(), node => node.props?.['aria-label'] === label).props.disabled, true)
    }

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


test('old package carts are not silently interpreted as explicit units', t => {
    const harness = cartHarness(null, new Map([['pps-order-cart-v1', JSON.stringify(initialItems)]]))
    t.after(harness.dispose)
    assert.deepEqual(harness.api.items.value, [])
})

test('cart preserves decimal kg quantities and never multiplies them by packaging', t => {
    const harness = cartHarness()
    t.after(harness.dispose)
    const cart = harness.sharedCart()
    cart.removeItem(2)
    cart.setQuantity(1, 10.125)
    assert.equal(cart.items.value[0].quantity, 10.125)
    assert.equal(cart.totalWeight.value, 10.125)
    assert.equal(cart.totalAmount.value, 1215)
    assert.equal(harness.api.weight(0.000001), '0,000001 кг')
    const input = findVNode(harness.render(), node => node.props?.['aria-label'] === 'Количество, кг')
    assert.equal(input.props.step, '0.001')
})

test('cart requires an explicit measure and retains its snapshot when the product unit changes', t => {
    const harness = cartHarness()
    t.after(harness.dispose)
    const cart = harness.sharedCart()
    assert.equal(cart.addGood({ id: 3, name: 'Без единицы', denominator: 10 }), false)
    assert.match(cart.cartError.value, /не задана/)
    assert.equal(cart.addGood({ id: 1, name: 'Мука', measurement: { measure_id: 2, unit_label: 'кор.', kilograms_per_unit: 10 } }), false)
    assert.match(cart.cartError.value, /изменилась/)
    assert.equal(cart.items.value[0].measurement.unit_label, 'кг')
    assert.equal(cart.items.value[0].quantity, 2)
})

test('cart shows unknown total mass if any configured unit has no kg conversion', t => {
    const harness = cartHarness()
    t.after(harness.dispose)
    const cart = harness.sharedCart()
    cart.addGood({ id: 3, name: 'Штука', measurement: { measure_id: 3, unit_label: 'шт.', kilograms_per_unit: null }, denominator: 10 })
    assert.equal(cart.totalWeight.value, null)
})

test('checkout displays a stale measurement error without changing quantities or snapshots', async t => {
    const harness = cartHarness({ id: 7, phone: '+70000000000', delivery_address: 'Москва' })
    t.after(harness.dispose)
    harness.api.openCheckout()
    harness.api.form.preferred_delivery_time = 'Завтра'
    harness.api.submitOrder()
    harness.requests[0].reject({ response: { data: {
        message: 'The given data was invalid.',
        errors: { 'items.0.measurement': ['Единица товара изменилась. Обновите корзину.'] },
    } } })
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(harness.api.errorMessage.value, 'Единица товара изменилась. Обновите корзину.')
    assert.deepEqual(harness.api.items.value[0].measurement, initialItems[0].measurement)
    assert.equal(harness.api.items.value[0].quantity, 2)
})

test('a product public offer adds its chosen decimal quantity and survives login and reloading', async t => {
    const harness = cartHarness(null, new Map())
    t.after(harness.dispose)
    const cart = harness.sharedCart()
    const good = { id: 42, name: 'Арахис', slug: 'arahis', denominator: 25, ava_thumb: '/arahis.jpg' }
    const offer = { price: 240, currency_code: 'RUB', measurement: initialItems[0].measurement }

    assert.equal(cart.addGood(good, 2.125, offer), true)
    assert.equal(cart.addGood(good, 1.5, offer), true)
    assert.equal(cart.items.value.length, 1)
    assert.equal(cart.items.value[0].quantity, 3.625)
    assert.equal(cart.items.value[0].price_gross, 240)
    assert.equal(cart.items.value[0].pricing_context, 'public')
    assert.equal(cart.items.value[0].image_url, '/arahis.jpg')
    assert.equal(cart.totalAmount.value, 870)
    assert.equal(cart.totalWeight.value, 3.625)
    await Vue.nextTick()

    harness.api.openCheckout()
    assert.deepEqual(harness.visits, ['/login'])
    assert.equal(harness.requests.length, 0)
    const signedIn = cartHarness({ id: 7, phone: '+79991234567', delivery_address: 'Москва' }, harness.storage)
    t.after(signedIn.dispose)
    assert.equal(signedIn.api.items.value[0].pricing_context, 'public')
    signedIn.api.openCheckout()
    signedIn.api.form.preferred_delivery_time = 'Завтра с 10 до 14'
    signedIn.api.submitOrder()
    assert.deepEqual(signedIn.requests[0].body.items, [{
        good_id: 42, quantity: 3.625, measure_id: 1,
        measurement: initialItems[0].measurement, pricing_context: 'public',
    }])
    assert.equal(signedIn.api.items.value.length, 1)
    signedIn.requests[0].resolve({ order: { id: 10, number: 'PP-10' }, redirect: '/dashboard' })
    await new Promise(resolve => setImmediate(resolve))
    assert.deepEqual(signedIn.api.items.value, [])
    assert.equal(signedIn.storage.get(cartKey), '[]')
    assert.equal(signedIn.api.checkoutOpen.value, false)
    assert.equal(signedIn.api.successMessage.value, 'Заказ PP-10 создан.')
    assert.deepEqual(signedIn.visits, ['/dashboard'])
})

test('a public offer with no price stays by request instead of using a private fallback', t => {
    const harness = cartHarness(null, new Map())
    t.after(harness.dispose)
    const cart = harness.sharedCart()
    assert.equal(cart.addGood({
        id: 42, name: 'Арахис', measurement: initialItems[0].measurement,
        price_type_values: [{ price_gross: 75, price_type: { code: 'partner' } }],
    }, 2, { price: null, currency_code: 'RUB', measurement: initialItems[0].measurement }), true)
    assert.equal(cart.items.value[0].price_gross, null)
    assert.equal(cart.items.value[0].quantity, 2)
    assert.equal(harness.api.money(cart.items.value[0].price_gross), 'по запросу')
})

test('adding above the cart quantity limit fails without silently changing the requested amount', t => {
    const harness = cartHarness(null, new Map())
    t.after(harness.dispose)
    const cart = harness.sharedCart()
    const good = { id: 42, name: 'Арахис', measurement: initialItems[0].measurement }
    assert.equal(cart.addGood(good, 9998), true)
    assert.equal(cart.addGood(good, 2), false)
    assert.equal(cart.items.value[0].quantity, 9998)
    assert.match(cart.cartError.value, /не более 9999/)
    assert.equal(cart.addGood(good, 1), true)
    assert.equal(cart.items.value[0].quantity, 9999)
    assert.equal(cart.cartError.value, '')
    assert.equal(cart.addGood(good, 1), false)
    assert.equal(cart.items.value[0].quantity, 9999)
    assert.equal(cart.addGood({ ...good, id: 43 }, 10000), false)
    assert.equal(cart.addGood({ ...good, id: 43 }, 0), false)
    assert.equal(cart.items.value.length, 1)
    cart.setQuantity(good.id, 9998.999)
    assert.equal(cart.addGood(good, 0.001), true)
    assert.equal(cart.items.value[0].quantity, 9999)
})
