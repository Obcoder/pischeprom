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
        ...Vue,
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
    assert.equal(shared.itemsCount.value, 3)
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
        ...Vue,
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
