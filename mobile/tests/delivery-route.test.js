import test from 'node:test'
import assert from 'node:assert/strict'
import { exactRoadOrder, fastestYandexRoute, planDeliveryRoute, requestYandexRoute, ROUTE_LIMITS, splitRoutePoints, yandexRouteUrl } from '../src/delivery-route.js'

function permutations(values) {
    return values.length ? values.flatMap((value, index) => permutations(values.filter((_, item) => item !== index)).map(tail => [value, ...tail])) : [[]]
}

test('exact optimization matches exhaustive search on directed roads with and without returning to the depot', () => {
    for (const count of [2, 3, 5]) {
        const matrix = Array.from({ length: count + 1 }, (_, from) => Array.from({ length: count + 1 }, (_, to) => from === to ? 0 : ((from * 73 + to * 29 + from * to * 11) % 97) + 1))
        for (const roundtrip of [false, true]) {
            const candidates = permutations(Array.from({ length: count }, (_, index) => index + 1))
            const cost = indexes => indexes.reduce((total, to, index) => total + matrix[index ? indexes[index - 1] : 0][to], 0)
                + (roundtrip ? matrix[indexes.at(-1)][0] : 0)
            const result = exactRoadOrder(matrix, roundtrip)
            assert.equal(result.seconds, Math.min(...candidates.map(cost)))
            assert.equal(cost(result.indexes.map(index => index + 1)), result.seconds)
            assert.equal(new Set(result.indexes).size, count)
        }
    }
})

test('exact planning uses road time in each direction, includes every stop, and bounds concurrency', async () => {
    const costs = [[0, 2, 8, 5], [90, 0, 40, 2], [1, 10, 0, 4], [20, 9, 3, 0]]
    const stops = [1, 2, 3].map(id => ({ id, coordinates: [id, 0] }))
    let active = 0
    let maximum = 0
    const calls = []
    const plan = await planDeliveryRoute([0, 0], stops, async (from, to) => {
        active++; maximum = Math.max(maximum, active)
        calls.push([from[0], to[0]])
        await new Promise(resolve => setTimeout(resolve, 1))
        active--
        return costs[from[0]][to[0]]
    }, { returnToStart: true })
    assert.deepEqual(plan.stops.map(stop => stop.id), [1, 3, 2])
    assert.equal(plan.seconds, 8)
    assert.equal(plan.exact, true)
    assert.equal(calls.length, 12)
    assert.equal(maximum, ROUTE_LIMITS.concurrency)
})

test('larger days use bounded road queries and honestly report approximate ordering, including all 50 stops', async () => {
    const stops = Array.from({ length: ROUTE_LIMITS.maximumStops }, (_, index) => ({ id: index + 1, coordinates: [index + 1, 0] }))
    let calls = 0
    const plan = await planDeliveryRoute([0, 0], stops, async (from, to) => {
        calls++
        // The geographically third candidate is faster than the closest one.
        if (from[0] === 0 && to[0] === 3) return 1
        return 100 + Math.abs(from[0] - to[0])
    }, { returnToStart: true })
    assert.equal(plan.exact, false)
    assert.equal(plan.stops[0].id, 3)
    assert.equal(plan.stops.length, 50)
    assert.equal(new Set(plan.stops.map(stop => stop.id)).size, 50)
    assert.ok(calls <= 151 && calls <= ROUTE_LIMITS.requests)
})

test('invalid or excessive stops and a pre-cancelled plan make no provider calls', async () => {
    let calls = 0
    const road = () => { calls++; return 10 }
    await assert.rejects(planDeliveryRoute([0, 0], [], road))
    await assert.rejects(planDeliveryRoute([91, 0], [{ coordinates: [1, 0] }], road))
    await assert.rejects(planDeliveryRoute([0, 0], [{ coordinates: ['1', 0] }], road))
    await assert.rejects(planDeliveryRoute([0, 0], Array.from({ length: 51 }, () => ({ coordinates: [1, 0] })), road), /до 50/)
    const controller = new AbortController()
    controller.abort()
    await assert.rejects(planDeliveryRoute([0, 0], [{ coordinates: [1, 0] }], road, { signal: controller.signal }), error => error.name === 'AbortError')
    assert.equal(calls, 0)
})

test('changing filters or cancelling prevents late routing replies from publishing or fetching another road', async () => {
    const controller = new AbortController()
    let respond
    const reply = new Promise(resolve => { respond = resolve })
    let progress = 0
    let calls = 0
    const pending = planDeliveryRoute([0, 0], [1, 2, 3].map(id => ({ coordinates: [id, 0] })), () => { calls++; return reply }, {
        signal: controller.signal, onProgress: () => progress++,
    })
    await Promise.resolve()
    controller.abort()
    respond(10)
    await assert.rejects(pending, error => error.name === 'AbortError')
    assert.equal(progress, 0)
    assert.equal(calls, 2)
})

test('missing road measurements fail the whole plan instead of skipping deliveries or inventing distances', async () => {
    for (const badTime of [undefined, NaN, Infinity, -1]) {
        await assert.rejects(planDeliveryRoute([0, 0], [{ coordinates: [1, 0] }], () => badTime), /не вернул время/)
    }
    await assert.rejects(planDeliveryRoute([0, 0], [{ coordinates: [1, 0] }], () => { throw new Error('Provider offline') }), /Provider offline/)
})

test('route chunks obey the ten-point limit, preserve every leg and share their boundary stop', () => {
    const points = Array.from({ length: 52 }, (_, index) => [index, 0])
    const chunks = splitRoutePoints(points)
    assert.equal(chunks.length, 6)
    assert.ok(chunks.every(chunk => chunk.length <= 10))
    assert.deepEqual([chunks[0][0], ...chunks.flatMap(chunk => chunk.slice(1))], points)
    for (let index = 1; index < chunks.length; index++) assert.deepEqual(chunks[index - 1].at(-1), chunks[index][0])
    assert.deepEqual(splitRoutePoints([[1, 2], [1, 2]]), [])
    assert.throws(() => splitRoutePoints([[91, 0]]))
    const url = new URL(yandexRouteUrl([[55.75, 37.6], [55.9, 38.1]]))
    assert.equal(url.origin, 'https://yandex.ru')
    assert.equal(url.searchParams.get('rtext'), '55.75,37.6~55.9,38.1')
    assert.equal(url.searchParams.get('rtt'), 'auto')
    assert.equal(yandexRouteUrl(points), null)
})

test('same-address depot and delivery need no road lookup', async () => {
    const result = await planDeliveryRoute([55, 37], [{ coordinates: [55, 37] }], () => { throw new Error('Unexpected request') }, { returnToStart: true })
    assert.equal(result.seconds, 0)
    assert.equal(result.requests, 0)
    assert.equal(result.stops.length, 1)
})

const road = (seconds, metres, blocked = false) => ({ properties: { get: name => ({ durationInTraffic: { value: seconds }, distance: { value: metres }, blocked })[name] } })

test('provider selection uses traffic duration, rejects blocked roads and missing measurements', () => {
    const routes = [road(50, 100, true), road(100, 800), road(70, 1000), road(NaN, 50)]
    let selected
    const result = fastestYandexRoute({ getRoutes: () => ({ each: callback => routes.forEach(callback) }), setActiveRoute: route => { selected = route } })
    assert.equal(result.seconds, 70)
    assert.equal(result.metres, 1000)
    assert.equal(selected, routes[2])
    assert.throws(() => fastestYandexRoute({ getRoutes: () => ({ each: callback => callback(road(10, 200, true)) }) }), /нет доступного/)
    const incomplete = { ...road(10, 200), getPaths: () => ({ getLength: () => 1 }) }
    assert.throws(() => fastestYandexRoute({ getRoutes: () => ({ each: callback => callback(incomplete) }) }, 3), /нет доступного/)
})

test('cancelled and timed-out provider replies are destroyed when they arrive and never become visible routes', async () => {
    for (const cancel of [true, false]) {
        let respond
        let destroyed = 0
        const controller = new AbortController()
        const maps = { route: (points, options) => {
            assert.equal(options.avoidTrafficJams, true)
            assert.equal(options.searchCoordOrder, 'latlong')
            assert.equal(options.reverseGeocoding, false)
            return new Promise(resolve => { respond = resolve })
        } }
        const pending = requestYandexRoute(maps, [[1, 2], [3, 4]], { signal: controller.signal, timeoutMs: 5 })
        await Promise.resolve()
        if (cancel) controller.abort()
        await assert.rejects(pending, error => cancel ? error.name === 'AbortError' : /не ответил/.test(error.message))
        respond({ model: { destroy: () => destroyed++ } })
        await new Promise(resolve => setTimeout(resolve, 0))
        assert.equal(destroyed, 1)
    }
})
