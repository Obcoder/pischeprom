import axios from 'axios'

const PAGE_SIZE = 100
// Avito coreItems allows 25 requests per minute, including subsequent pages.
const PAGE_DELAY_MS = 2500
const MAX_RATE_LIMIT_RETRIES = 3
const MAX_RETRY_DELAY_MS = 5 * 60 * 1000

function assertNotAborted(signal) {
    if (!signal?.aborted) return
    const error = new Error('Загрузка объявлений отменена.')
    error.name = 'AbortError'
    error.code = 'ERR_CANCELED'
    throw error
}

function waitFor(milliseconds, signal) {
    assertNotAborted(signal)
    return new Promise((resolve, reject) => {
        const finish = () => {
            signal?.removeEventListener('abort', cancel)
            resolve()
        }
        const timer = setTimeout(finish, milliseconds)
        const cancel = () => {
            clearTimeout(timer)
            signal.removeEventListener('abort', cancel)
            try { assertNotAborted(signal) } catch (error) { reject(error) }
        }
        signal?.addEventListener('abort', cancel, { once: true })
    })
}

function loadError(message, code = 'AVITO_LISTINGS_INCOMPLETE') {
    const error = new Error(message)
    error.code = code
    return error
}

function retryDelay(error, now) {
    const headers = error.response?.headers
    const value = headers?.get?.('retry-after')
        ?? headers?.['retry-after']
        ?? headers?.['Retry-After']
        ?? error.response?.data?.retry_after
    const seconds = value === undefined || value === null || value === '' ? NaN : Number(value)
    const delay = Number.isFinite(seconds)
        ? seconds * 1000
        : Date.parse(value) - now()

    // Do not retry sooner than Avito asks. An exceptional delay is surfaced to
    // the caller instead of silently retrying too early or waiting indefinitely.
    if (Number.isFinite(delay) && delay > MAX_RETRY_DELAY_MS) throw error
    return Math.max(PAGE_DELAY_MS, Number.isFinite(delay) ? delay : 60_000)
}

function parsePage(response) {
    const data = response?.data
    if (!data || typeof data !== 'object' || !Array.isArray(data.items)) {
        throw loadError('Avito вернул некорректный список объявлений. Загрузка не завершена.', 'AVITO_LISTINGS_INVALID_RESPONSE')
    }

    const entries = data.items.map((item) => {
        const validId = typeof item?.id === 'string'
            ? item.id.trim() !== ''
            : typeof item?.id === 'number' && Number.isSafeInteger(item.id)
        if (!item || typeof item !== 'object' || Array.isArray(item) || !validId) {
            throw loadError('Avito вернул объявление без корректного ID. Загрузка не завершена.', 'AVITO_LISTINGS_INVALID_RESPONSE')
        }
        return [String(item.id).trim(), item]
    })
    const meta = data.meta ?? {}
    if (typeof meta !== 'object' || (Array.isArray(meta) && meta.length > 0)) {
        throw loadError('Avito вернул некорректную информацию о страницах. Загрузка не завершена.', 'AVITO_LISTINGS_INVALID_RESPONSE')
    }

    return { entries, meta: Array.isArray(meta) ? {} : meta, remote: data.remote ?? null }
}

function nextPageExpected(meta, page, count) {
    const finalPage = [meta.last_page, meta.total_pages, meta.pages]
        .filter((value) => value !== undefined && value !== null && value !== '')
        .map(Number)
        .find((value) => Number.isInteger(value) && value >= 0)
    const hasMore = [true, 1, '1', 'true'].includes(meta.has_more)
        ? true
        : [false, 0, '0', 'false'].includes(meta.has_more) ? false : null

    if (finalPage !== undefined) return page < finalPage
    if (hasMore !== null) return hasMore
    return count >= PAGE_SIZE
}

/**
 * Read every matching listing; the API's 100-item limit applies to each page.
 * onPage receives independent array snapshots so callers can keep partial
 * results visible when a later page fails. request/wait/now are injectable for
 * deterministic tests; wait receives (milliseconds, AbortSignal).
 */
export async function loadAllAvitoListings({
    params = {},
    signal,
    onPage,
    request = (url, config) => axios.get(url, config),
    wait = waitFor,
    now = Date.now,
} = {}) {
    const allItems = new Map()
    // Freeze filters for the complete traversal, including array-valued status.
    const filters = Object.fromEntries(Object.entries(params)
        .map(([key, value]) => [key, Array.isArray(value) ? [...value] : value]))
    let snapshot = { items: [], meta: {}, remote: null, pagesLoaded: 0 }

    for (let page = 1; ; page++) {
        assertNotAborted(signal)
        if (page > 1) {
            await wait(PAGE_DELAY_MS, signal)
            assertNotAborted(signal)
        }

        let response
        for (let retries = 0; ; retries++) {
            try {
                response = await request('/api/avito/listings', {
                    params: { ...filters, page, per_page: PAGE_SIZE },
                    signal,
                })
                break
            } catch (error) {
                assertNotAborted(signal)
                if (Number(error.response?.status) !== 429 || retries >= MAX_RATE_LIMIT_RETRIES) throw error
                await wait(retryDelay(error, now), signal)
                assertNotAborted(signal)
            }
        }

        // Some transports can still resolve a response after cancellation.
        assertNotAborted(signal)
        const { entries, meta, remote } = parsePage(response)
        const previousCount = allItems.size
        for (const [id, item] of entries) allItems.set(id, item)
        if (entries.length > 0 && allItems.size === previousCount) {
            throw loadError('Avito повторил уже загруженную страницу. Загружены не все объявления; повторите загрузку.')
        }

        const hasNext = nextPageExpected(meta, page, entries.length)
        if (entries.length === 0 && hasNext) {
            throw loadError('Avito вернул пустую страницу до завершения списка. Загружены не все объявления; повторите загрузку.')
        }

        snapshot = { items: [...allItems.values()], meta, remote, pagesLoaded: page }
        await onPage?.(snapshot)
        assertNotAborted(signal)
        if (!hasNext) return snapshot
    }
}
