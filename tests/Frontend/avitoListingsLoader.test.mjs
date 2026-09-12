import test from 'node:test'
import assert from 'node:assert/strict'
import { loadAllAvitoListings } from '../../resources/js/Services/avitoListingsLoader.js'

const rows = (from, count) => Array.from({ length: count }, (_, index) => ({ id: from + index, title: `Объявление ${from + index}` }))
const response = (items, meta = {}, remote = null) => ({ data: { items, meta, remote } })
const noWait = async () => {}
const rateLimit = (retryAfter) => Object.assign(new Error('Слишком много запросов'), {
    response: { status: 429, headers: retryAfter === undefined ? {} : { 'retry-after': retryAfter } },
})

test('loads 250 listings over all pages, retaining filters and progressive snapshots', async () => {
    const calls = []
    const delays = []
    const snapshots = []
    const params = { account_id: 77, statuses: ['active'], page: 8, per_page: 20 }
    const result = await loadAllAvitoListings({
        params,
        request: async (url, config) => {
            calls.push({ url, config })
            params.statuses.push('removed')
            const page = config.params.page
            return response(rows((page - 1) * 100 + 1, page === 3 ? 50 : 100), { page, per_page: 100 }, { duration_ms: page })
        },
        wait: async (delay) => delays.push(delay),
        onPage: (snapshot) => snapshots.push(snapshot),
    })

    assert.equal(result.items.length, 250)
    assert.equal(result.pagesLoaded, 3)
    assert.deepEqual(result.meta, { page: 3, per_page: 100 })
    assert.deepEqual(result.remote, { duration_ms: 3 })
    assert.deepEqual(snapshots.map((snapshot) => snapshot.items.length), [100, 200, 250])
    assert.deepEqual(calls.map(({ config }) => config.params.page), [1, 2, 3])
    assert.ok(calls.every(({ url, config }) => url === '/api/avito/listings' && config.params.per_page === 100 && config.params.account_id === 77))
    assert.ok(calls.every(({ config }) => config.params.statuses.join() === 'active'))
    assert.deepEqual(delays, [2500, 2500])
})

test('an exact multiple of 100 reads the final empty page when totals are absent', async () => {
    const pages = []
    const result = await loadAllAvitoListings({
        wait: noWait,
        request: async (_url, { params }) => {
            pages.push(params.page)
            return response(params.page <= 2 ? rows((params.page - 1) * 100 + 1, 100) : [], [])
        },
    })
    assert.equal(result.items.length, 200)
    assert.deepEqual(pages, [1, 2, 3])
    assert.equal(result.pagesLoaded, 3)
})

test('empty accounts complete without any second request or delay', async () => {
    const result = await loadAllAvitoListings({
        request: async () => response([]),
        wait: async () => assert.fail('No second page should be needed'),
    })
    assert.deepEqual(result.items, [])
    assert.equal(result.pagesLoaded, 1)
})

for (const field of ['last_page', 'total_pages', 'pages']) {
    test(`honors ${field} and does not request beyond the known last page`, async () => {
        let calls = 0
        const result = await loadAllAvitoListings({
            wait: noWait,
            request: async (_url, { params }) => {
                calls++
                return response(rows((params.page - 1) * 100 + 1, 100), { [field]: 2 })
            },
        })
        assert.equal(calls, 2)
        assert.equal(result.items.length, 200)
    })
}

test('explicit has_more continues beyond short pages and stops even on a full final page', async () => {
    const result = await loadAllAvitoListings({
        wait: noWait,
        request: async (_url, { params }) => params.page === 1
            ? response(rows(1, 20), { has_more: true })
            : response(rows(21, 100), { has_more: false }),
    })
    assert.equal(result.items.length, 120)
    assert.equal(result.pagesLoaded, 2)
})

test('deduplicates overlapping pages by stable ID while retaining the latest row', async () => {
    const result = await loadAllAvitoListings({
        wait: noWait,
        request: async (_url, { params }) => params.page === 1
            ? response(rows(1, 100))
            : response([{ id: '100', title: 'Обновлено' }, ...rows(101, 50)]),
    })
    assert.equal(result.items.length, 150)
    assert.equal(result.items[99].title, 'Обновлено')
})

test('a repeated nonempty page fails explicitly and retains only accepted progress', async () => {
    let calls = 0
    const progress = []
    await assert.rejects(loadAllAvitoListings({
        wait: noWait,
        request: async () => { calls++; return response(rows(1, 100)) },
        onPage: ({ items }) => progress.push(items.length),
    }), { code: 'AVITO_LISTINGS_INCOMPLETE' })
    assert.equal(calls, 2)
    assert.deepEqual(progress, [100])
})

test('an empty page with explicit remaining pages fails instead of claiming completion', async () => {
    await assert.rejects(loadAllAvitoListings({
        wait: noWait,
        request: async () => response([], { last_page: 5 }),
    }), { code: 'AVITO_LISTINGS_INCOMPLETE' })
})

test('a later request failure preserves earlier snapshots and is not retried without 429', async () => {
    const failure = Object.assign(new Error('Avito недоступен'), { response: { status: 503 } })
    const progress = []
    let calls = 0
    await assert.rejects(loadAllAvitoListings({
        wait: noWait,
        request: async () => { if (++calls === 1) return response(rows(1, 100)); throw failure },
        onPage: (snapshot) => progress.push(snapshot),
    }), (error) => error === failure)
    assert.equal(calls, 2)
    assert.equal(progress.length, 1)
    assert.equal(progress[0].items.length, 100)
})

test('malformed responses and rows fail rather than silently reporting an empty completed list', async () => {
    for (const payload of [null, {}, { data: {} }, response(null), response([null]), response([{ title: 'Нет ID' }]), response([{ id: '' }]), response([{ id: {} }]), response(rows(1, 1), 'bad')]) {
        await assert.rejects(loadAllAvitoListings({
            wait: noWait,
            request: async () => payload,
        }), { code: 'AVITO_LISTINGS_INVALID_RESPONSE' })
    }
})

test('an already aborted load sends no requests', async () => {
    const controller = new AbortController()
    controller.abort()
    await assert.rejects(loadAllAvitoListings({
        signal: controller.signal,
        request: async () => assert.fail('Aborted load must not send requests'),
    }), { name: 'AbortError' })
})

test('cancellation during page delay prevents the following request', async () => {
    const controller = new AbortController()
    let calls = 0
    await assert.rejects(loadAllAvitoListings({
        signal: controller.signal,
        request: async () => { calls++; return response(rows(1, 100)) },
        wait: async (_delay, signal) => { assert.equal(signal, controller.signal); controller.abort() },
    }), { code: 'ERR_CANCELED' })
    assert.equal(calls, 1)
})

test('a response resolving after cancellation never reaches onPage', async () => {
    const controller = new AbortController()
    await assert.rejects(loadAllAvitoListings({
        signal: controller.signal,
        request: async () => { controller.abort(); return response(rows(1, 100)) },
        onPage: () => assert.fail('A stale response must not replace current rows'),
    }), { name: 'AbortError' })
})

test('the default page delay is abortable without waiting for its timer', async () => {
    const controller = new AbortController()
    let calls = 0
    const pending = loadAllAvitoListings({
        signal: controller.signal,
        request: async () => { calls++; return response(rows(1, 100)) },
        onPage: () => setImmediate(() => controller.abort()),
    })
    await assert.rejects(pending, { name: 'AbortError' })
    assert.equal(calls, 1)
})

test('429 retries the same page after Retry-After and does not duplicate progress', async () => {
    const pages = []
    const delays = []
    const progress = []
    let retries = 0
    const result = await loadAllAvitoListings({
        wait: async (delay) => delays.push(delay),
        request: async (_url, { params }) => {
            pages.push(params.page)
            if (params.page === 1 && retries++ === 0) throw rateLimit('12')
            return response(rows(1, 25))
        },
        onPage: ({ items }) => progress.push(items.length),
    })
    assert.equal(result.items.length, 25)
    assert.deepEqual(pages, [1, 1])
    assert.deepEqual(delays, [12_000])
    assert.deepEqual(progress, [25])
})

test('429 honors Retry-After as a date and supports the JSON retry_after fallback', async () => {
    const now = Date.parse('2026-09-12T10:00:00Z')
    const errors = [rateLimit('Sat, 12 Sep 2026 10:00:30 GMT'), rateLimit()]
    errors[1].response.data = { retry_after: 15 }
    const delays = []
    await loadAllAvitoListings({
        now: () => now,
        wait: async (delay) => delays.push(delay),
        request: async () => { if (errors.length) throw errors.shift(); return response([]) },
    })
    assert.deepEqual(delays, [30_000, 15_000])
})

test('persistent 429 is bounded to three retries with a one-minute fallback', async () => {
    const delays = []
    let calls = 0
    const failure = rateLimit()
    await assert.rejects(loadAllAvitoListings({
        wait: async (delay) => delays.push(delay),
        request: async () => { calls++; throw failure },
    }), (error) => error === failure)
    assert.equal(calls, 4)
    assert.deepEqual(delays, [60_000, 60_000, 60_000])
})

test('an unusually long Retry-After is surfaced without an early retry or an unbounded wait', async () => {
    let calls = 0
    const failure = rateLimit('3600')
    await assert.rejects(loadAllAvitoListings({
        wait: async () => assert.fail('Do not hold a load for an hour'),
        request: async () => { calls++; throw failure },
    }), (error) => error === failure)
    assert.equal(calls, 1)
})

test('cancellation while retrying 429 prevents any further attempts', async () => {
    const controller = new AbortController()
    let calls = 0
    await assert.rejects(loadAllAvitoListings({
        signal: controller.signal,
        wait: async () => controller.abort(),
        request: async () => { calls++; throw rateLimit('10') },
    }), { name: 'AbortError' })
    assert.equal(calls, 1)
})
