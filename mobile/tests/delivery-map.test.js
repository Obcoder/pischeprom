import test from 'node:test'
import assert from 'node:assert/strict'
import { exactGeocodeCoordinates, groupDeliveryAddresses, loadDeliveryOrders, loadYandexMaps, resolveDeliveryGroups, yandexScriptUrl } from '../src/delivery-map.js'

const address = full_address => ({ full_address })
const geocode = (precision = 'exact', coordinates = [55.75, 37.61]) => ({ geoObjects: { get: () => ({
    properties: { get: name => { assert.equal(name, 'metaDataProperty.GeocoderMetaData'); return { precision } } },
    geometry: { getCoordinates: () => coordinates },
}) } })
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done }); return { promise, resolve } }

test('delivery map fetches every page using the same filters, and deduplicates orders', async () => {
    const calls = []
    const progress = []
    const orders = await loadDeliveryOrders(async query => {
        calls.push(query)
        return { data: [{ id: query.page }, { id: 99 }], meta: { current_page: query.page, last_page: 3, total: 4 } }
    }, { search: 'Номер 32', filter: 'ready' }, { onProgress: value => progress.push(value) })
    assert.deepEqual(orders.map(order => order.id), [1, 99, 2, 3])
    assert.equal(calls.length, 3)
    assert.ok(calls.every(query => query.per_page === 100 && query.search === 'Номер 32' && query.filter === 'ready'))
    assert.deepEqual(progress.at(-1), { loaded: 4, total: 4 })
})

test('a cancelled old map request never publishes progress or fetches its next page', async () => {
    const controller = new AbortController()
    const response = deferred()
    let calls = 0
    let progress = 0
    const pending = loadDeliveryOrders(() => { calls++; return response.promise }, {}, { signal: controller.signal, onProgress: () => progress++ })
    controller.abort()
    response.resolve({ data: [{ id: 1 }], meta: { current_page: 1, last_page: 3, total: 3 } })
    await assert.rejects(pending, error => error.name === 'AbortError')
    assert.equal(calls, 1)
    assert.equal(progress, 0)
})

test('every map page carries the selected delivery day or the explicit unscheduled filter', async () => {
    for (const query of [{ delivery_date: '2026-09-22' }, { delivery_unscheduled: true }]) {
        const calls = []
        await loadDeliveryOrders(async value => {
            calls.push(value)
            return { data: [{ id: value.page }], meta: { current_page: value.page, last_page: 2, total: 2 } }
        }, query)
        assert.equal(calls.length, 2)
        assert.ok(calls.every(value => query.delivery_date ? value.delivery_date === '2026-09-22' : value.delivery_unscheduled === 1))
    }
})

test('invalid pagination and failed pages cannot silently produce an incomplete map', async () => {
    await assert.rejects(loadDeliveryOrders(async () => ({ data: [], meta: { current_page: 2, last_page: 2, total: 1 } }), {}), /полный список/)
    await assert.rejects(loadDeliveryOrders(async query => {
        if (query.page === 2) throw new Error('Page unavailable')
        return { data: [{ id: 1 }], meta: { current_page: 1, last_page: 2, total: 2 } }
    }, {}), /Page unavailable/)
})

test('equal delivery addresses are geocoded once and all orders at the address remain accessible', () => {
    const grouped = groupDeliveryAddresses([
        { id: 1, delivery_addresses: [address(' Москва,  Тверская 1 '), address('Москва, Тверская 1')] },
        { id: 2, delivery_addresses: [address('москва, тверская 1')] },
        { id: 3, delivery_addresses: [] },
        { id: 4, delivery_addresses: [address('   ')] },
    ])
    assert.equal(grouped.groups.length, 1)
    assert.deepEqual(grouped.groups[0].orders.map(order => order.id), [1, 2])
    assert.deepEqual(grouped.missing.map(entry => entry.order.id), [3, 4])
})

test('only exact house coordinates become delivery pins; approximations and invalid coordinates are rejected', () => {
    assert.deepEqual(exactGeocodeCoordinates(geocode()), [55.75, 37.61])
    assert.deepEqual(exactGeocodeCoordinates(geocode('number')), [55.75, 37.61])
    for (const precision of ['near', 'range', 'street', 'other', undefined]) assert.equal(exactGeocodeCoordinates(geocode(precision === undefined ? null : precision)), null)
    for (const coordinates of [[NaN, 1], [91, 1], [1, 181], ['55', '37'], [1]]) assert.equal(exactGeocodeCoordinates(geocode('exact', coordinates)), null)
    assert.equal(exactGeocodeCoordinates({ geoObjects: { get: () => null } }), null)
})

test('geocoding concurrency is bounded, with failures and approximations reported instead of fabricated pins', async () => {
    const groups = Array.from({ length: 6 }, (_, id) => ({ address: address(String(id)), orders: [{ id }] }))
    let active = 0
    let maximum = 0
    const results = []
    await resolveDeliveryGroups(groups, async value => {
        active++; maximum = Math.max(maximum, active)
        await new Promise(resolve => setTimeout(resolve, 2))
        active--
        if (value === '1') throw new Error('Provider failure')
        return geocode(value === '2' ? 'street' : 'exact')
    }, { onResult: result => results.push(result), concurrency: 2 })
    assert.equal(maximum, 2)
    assert.equal(results.length, 6)
    assert.equal(results.filter(result => result.coordinates).length, 4)
    assert.equal(results.at(-1).completed, 6)
    assert.ok(results.filter(result => !result.coordinates).every(result => result.reason))
})

test('late geocoder callbacks after leaving or changing filters cannot update a new map', async () => {
    const controller = new AbortController()
    const response = deferred()
    let published = 0
    let calls = 0
    const groups = Array.from({ length: 4 }, (_, id) => ({ address: address(String(id)), orders: [{ id }] }))
    const pending = resolveDeliveryGroups(groups, () => { calls++; return response.promise }, {
        signal: controller.signal, concurrency: 2, onResult: () => published++,
    })
    await Promise.resolve()
    controller.abort()
    await assert.rejects(pending, error => error.name === 'AbortError')
    response.resolve(geocode())
    await Promise.resolve()
    assert.equal(published, 0)
    assert.equal(calls, 2)
})

test('a stuck geocoder times out and keeps its order in the unresolved list', async () => {
    const results = []
    await resolveDeliveryGroups([{ address: address('Москва'), orders: [{ id: 1 }] }], () => new Promise(() => {}), {
        timeoutMs: 3, onResult: result => results.push(result),
    })
    assert.equal(results.length, 1)
    assert.equal(results[0].coordinates, null)
    assert.match(results[0].reason, /не ответили/)
})

test('the SDK accepts only trusted HTTPS Yandex script endpoints and never receives the application bearer token', () => {
    for (const host of ['api-maps.yandex.ru', 'enterprise.api-maps.yandex.ru']) {
        const url = yandexScriptUrl({ configured: true, api_key: 'public-key', script_url: `https://${host}/2.1/`, token: 'private-app-token' })
        assert.equal(url.searchParams.get('apikey'), 'public-key')
        assert.equal(url.searchParams.get('lang'), 'ru_RU')
        assert.equal(url.href.includes('private-app-token'), false)
    }
    for (const script_url of ['http://api-maps.yandex.ru/2.1/', 'https://api-maps.yandex.ru.evil.example/2.1/', 'https://api-maps.yandex.ru@evil.example/2.1/', 'https://api-maps.yandex.ru/2.1/?callback=anything', 'javascript:alert(1)']) {
        assert.throws(() => yandexScriptUrl({ configured: true, api_key: 'key', script_url }))
    }
    assert.throws(() => yandexScriptUrl({ configured: false }))
})

test('changing the configured Yandex key reloads the SDK, while a late old failure cannot discard the new connection', async context => {
    const oldWindow = globalThis.window
    const oldDocument = globalThis.document
    context.after(() => { globalThis.window = oldWindow; globalThis.document = oldDocument })
    const scripts = []
    globalThis.window = {}
    globalThis.document = { createElement: () => ({ remove() {} }), head: { appendChild: script => scripts.push(script) } }
    const configuration = api_key => ({ configured: true, script_url: 'https://api-maps.yandex.ru/2.1/', api_key })
    const sdk = { Map() {}, Placemark() {}, Clusterer() {}, geocode() {} }
    const first = loadYandexMaps(configuration('first-public-key'))
    assert.equal(loadYandexMaps(configuration('first-public-key')), first)
    window[new URL(scripts[0].src).searchParams.get('onload')](sdk)
    assert.equal(await first, sdk)
    const old = loadYandexMaps(configuration('old-public-key'))
    const current = loadYandexMaps(configuration('current-public-key'))
    window[new URL(scripts[1].src).searchParams.get('onerror')]()
    await assert.rejects(old, /Не удалось подключиться/)
    window[new URL(scripts[2].src).searchParams.get('onload')](sdk)
    assert.equal(await current, sdk)
    assert.equal(loadYandexMaps(configuration('current-public-key')), current)
    assert.equal(scripts.length, 3)
})
