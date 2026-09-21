export class ApiError extends Error {
    constructor(message, { status = 0, errors = {}, code = null } = {}) {
        super(message)
        this.name = 'ApiError'
        this.status = status
        this.errors = errors
        this.code = code
    }

    get uncertain() {
        return this.status === 0 || this.status >= 500 || [408, 425, 429].includes(this.status)
    }
}

export function normalizeApiUrl(value) {
    let url
    try {
        url = new URL(value)
    } catch {
        throw new Error('В сборке не указан адрес сервера. Настройте VITE_MOBILE_API_URL и соберите приложение заново.')
    }
    if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash
        || !/\/api\/mobile\/v1\/?$/.test(url.pathname)) {
        throw new Error('Адрес сервера должен иметь вид https://ваш-сервер/api/mobile/v1.')
    }
    return url.href.replace(/\/$/, '')
}

export function createApi({ baseUrl, fetchImpl = globalThis.fetch, nativeRequest = null, onUnauthorized = () => {}, timeoutMs = 30000 }) {
    const base = normalizeApiUrl(baseUrl)
    let token = null

    async function request(path, { method = 'GET', data, query, authenticated = true } = {}) {
        if (!/^\/[a-z0-9/_-]*$/i.test(path) || path.startsWith('//')) {
            throw new Error('Invalid API path')
        }
        const url = new URL(`${base}${path}`)
        for (const [key, value] of Object.entries(query || {})) {
            if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value))
        }
        const requestToken = authenticated ? token : null
        const headers = { Accept: 'application/json' }
        if (data !== undefined) headers['Content-Type'] = 'application/json'
        if (requestToken) headers.Authorization = `Bearer ${requestToken}`

        let status
        let body
        let timer
        try {
            if (nativeRequest) {
                const response = await nativeRequest({
                    url: url.href, method, headers,
                    ...(data !== undefined ? { data } : {}),
                    connectTimeout: timeoutMs, readTimeout: timeoutMs,
                    disableRedirects: true,
                    responseType: 'json',
                })
                status = response.status
                body = response.data
                if (typeof body === 'string' && body) {
                    try { body = JSON.parse(body) } catch { body = null }
                }
            } else {
                const controller = new AbortController()
                timer = setTimeout(() => controller.abort(), timeoutMs)
                const response = await fetchImpl(url.href, {
                    method, headers, credentials: 'omit', cache: 'no-store', redirect: 'error',
                    signal: controller.signal,
                    ...(data !== undefined ? { body: JSON.stringify(data) } : {}),
                })
                status = response.status
                const text = await response.text()
                try { body = text ? JSON.parse(text) : null } catch { body = null }
            }
        } catch {
            throw new ApiError('Не удалось получить ответ сервера. Проверьте подключение и повторите запрос.')
        } finally {
            clearTimeout(timer)
        }

        if (status === 401 && requestToken && requestToken === token) {
            token = null
            onUnauthorized()
        }
        if (status < 200 || status >= 300) {
            if (path === '/auth/login' && [404, 405].includes(status)) {
                throw new ApiError('На сервере не настроен вход в мобильное приложение. Обратитесь к администратору для обновления серверной части.', { status })
            }
            const errors = body?.errors && typeof body.errors === 'object' ? body.errors : {}
            const detail = Object.values(errors).flat().find(value => typeof value === 'string')
            throw new ApiError(detail || body?.message || (status === 401
                ? 'Войдите в приложение заново.' : 'Не удалось выполнить запрос.'), {
                status, errors, code: body?.code,
            })
        }
        if (status !== 204 && (!body || typeof body !== 'object')) {
            throw new ApiError('Сервер вернул некорректный ответ. Обновите данные перед следующей операцией.')
        }
        return body
    }

    return {
        request,
        setToken(value) { token = value },
        clearToken() { token = null },
        login(data) { return request('/auth/login', { method: 'POST', data, authenticated: false }) },
        me() { return request('/auth/me') },
        logout() { return request('/auth/token', { method: 'DELETE' }) },
        orders(query) { return request('/orders', { query }) },
        deliveryMapConfig() { return request('/delivery-map/config') },
        deliveryMapOrders(query) { return request('/delivery-map/orders', { query }) },
        order(id) { return request(`/orders/${encodeURIComponent(id)}`) },
        setDeliveryDate(id, data) { return request(`/orders/${encodeURIComponent(id)}/delivery-date`, { method: 'PATCH', data }) },
        prepare(id, data) { return request(`/orders/${encodeURIComponent(id)}/prepare`, { method: 'PATCH', data }) },
        ship(id, data) { return request(`/orders/${encodeURIComponent(id)}/ship`, { method: 'POST', data }) },
    }
}
