import test from 'node:test'
import assert from 'node:assert/strict'
import { ApiError, createApi, normalizeApiUrl } from '../src/api.js'

const baseUrl = 'https://warehouse.example/api/mobile/v1'
const response = (status, data) => ({ status, text: async () => data === undefined ? '' : JSON.stringify(data) })

test('login accepts only the fixed HTTPS API endpoint without credentials or query', () => {
    assert.equal(normalizeApiUrl(`${baseUrl}/`), baseUrl)
    for (const invalid of [undefined, '', 'http://warehouse.example/api/mobile/v1',
        'https://user:secret@warehouse.example/api/mobile/v1', `${baseUrl}?redirect=elsewhere`,
        `${baseUrl}#fragment`, 'https://warehouse.example/admin']) {
        assert.throws(() => normalizeApiUrl(invalid))
    }
})

test('browser requests use bearer auth without cookies, caching, redirects or credentials in URL', async () => {
    const calls = []
    const api = createApi({ baseUrl, fetchImpl: async (...args) => {
        calls.push(args)
        return response(200, { data: [] })
    } })
    api.setToken('secret-token')
    await api.orders({ search: '№ 12 & Покупатель', page: 2, filter: 'ready' })
    const [url, options] = calls[0]
    assert.equal(new URL(url).origin, 'https://warehouse.example')
    assert.equal(new URL(url).searchParams.get('search'), '№ 12 & Покупатель')
    assert.equal(new URL(url).searchParams.has('token'), false)
    assert.equal(options.headers.Authorization, 'Bearer secret-token')
    assert.equal(options.credentials, 'omit')
    assert.equal(options.redirect, 'error')
    assert.equal(options.cache, 'no-store')
    await assert.rejects(() => api.request('//untrusted.example/path'), /Invalid API path/)
    await assert.rejects(() => api.request('/../auth/login'), /Invalid API path/)
})

test('login never sends a previous session token and failed login does not clear another session', async () => {
    let expired = 0
    const api = createApi({ baseUrl, onUnauthorized: () => expired++, fetchImpl: async (_, options) => {
        assert.equal(options.headers.Authorization, undefined)
        assert.equal(JSON.parse(options.body).recovery_code, 'recovery-code')
        return response(401, { message: 'Не удалось войти' })
    } })
    api.setToken('old-token')
    await assert.rejects(() => api.login({ email: 'user@example.test', password: 'password', recovery_code: 'recovery-code' }), error => error.status === 401)
    assert.equal(expired, 0)
})

test('missing login endpoint explains that the server needs an update in both transports', async t => {
    for (const transport of ['browser', 'native']) {
        for (const status of [404, 405]) {
            await t.test(`${transport} ${status}`, async () => {
                const body = { message: 'The route api/mobile/v1/auth/login could not be found.' }
                const api = createApi({ baseUrl, ...(transport === 'native'
                    ? { nativeRequest: async () => ({ status, data: JSON.stringify(body) }) }
                    : { fetchImpl: async () => response(status, body) }) })
                await assert.rejects(() => api.login({ email: 'user@example.test', password: 'password' }), error => {
                    assert.ok(error instanceof ApiError)
                    assert.equal(error.status, status)
                    assert.equal(error.message, 'На сервере не настроен вход в мобильное приложение. Обратитесь к администратору для обновления серверной части.')
                    assert.equal(error.uncertain, false)
                    return true
                })
            })
        }
    }
})

test('missing order keeps the server message rather than suggesting a server update', async () => {
    const api = createApi({ baseUrl, fetchImpl: async () => response(404, { message: 'Заказ не найден.' }) })
    await assert.rejects(() => api.order(9), error => {
        assert.equal(error.status, 404)
        assert.equal(error.message, 'Заказ не найден.')
        return true
    })
})

test('expired bearer token is cleared and authentication callback fires once', async () => {
    let expired = 0
    const headers = []
    const api = createApi({ baseUrl, onUnauthorized: () => expired++, fetchImpl: async (_, options) => {
        headers.push(options.headers)
        return response(401, { message: 'Unauthenticated.' })
    } })
    api.setToken('expired')
    await assert.rejects(() => api.me(), error => error.status === 401)
    await assert.rejects(() => api.me(), error => error.status === 401)
    assert.equal(expired, 1)
    assert.equal(headers[0].Authorization, 'Bearer expired')
    assert.equal(headers[1].Authorization, undefined)
})

test('an older request cannot invalidate a newly authenticated session', async () => {
    let resolveResponse
    let expired = 0
    const api = createApi({ baseUrl, onUnauthorized: () => expired++, fetchImpl: () => new Promise(resolve => { resolveResponse = resolve }) })
    api.setToken('old')
    const pending = api.me()
    api.setToken('new')
    resolveResponse(response(401, {}))
    await assert.rejects(() => pending)
    assert.equal(expired, 0)
})

test('validation errors preserve status, field errors and a useful message', async () => {
    const api = createApi({ baseUrl, fetchImpl: async () => response(422, {
        message: 'The given data was invalid.', errors: { 'items.0.quantity': ['Отгрузите полное количество.'] },
    }) })
    await assert.rejects(() => api.prepare(9, { items: [] }), error => {
        assert.equal(error.status, 422)
        assert.equal(error.message, 'Отгрузите полное количество.')
        assert.equal(error.uncertain, false)
        return true
    })
})

test('network failures and malformed successful responses remain uncertain', async () => {
    for (const fetchImpl of [async () => { throw new TypeError('Network error') }, async () => ({ status: 200, text: async () => '<html>proxy</html>' })]) {
        const api = createApi({ baseUrl, fetchImpl })
        await assert.rejects(() => api.ship(3, {}), error => error instanceof ApiError && error.uncertain)
    }
})

test('request timeout aborts browser transport and reports an uncertain result', async () => {
    const api = createApi({ baseUrl, timeoutMs: 5, fetchImpl: (_, options) => new Promise((_, reject) => {
        options.signal.addEventListener('abort', () => reject(new Error('Aborted')), { once: true })
    }) })
    await assert.rejects(() => api.ship(3, {}), error => error.status === 0 && error.uncertain)
})

test('native transport sends JSON and forbids redirects to another server', async () => {
    let options
    const api = createApi({ baseUrl, nativeRequest: async value => {
        options = value
        return { status: 200, data: JSON.stringify({ data: { id: 7 } }) }
    } })
    api.setToken('native-token')
    const payload = { version: 'version-1', request_id: 'request-id' }
    const result = await api.ship(7, payload)
    assert.equal(options.url, `${baseUrl}/orders/7/ship`)
    assert.equal(options.headers.Authorization, 'Bearer native-token')
    assert.equal(options.disableRedirects, true)
    assert.deepEqual(options.data, payload)
    assert.equal(result.data.id, 7)
})

test('token revocation accepts an empty 204 response', async () => {
    const api = createApi({ baseUrl, fetchImpl: async () => response(204) })
    assert.equal(await api.logout(), null)
})
