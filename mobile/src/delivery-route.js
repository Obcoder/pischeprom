// Yandex JS API guarantees routes with at most 10 reference points.
// https://yandex.ru/dev/jsapi-v2-1/doc/ru/v2-1/dg/concepts/router/multiRouter
export const ROUTE_LIMITS = Object.freeze({ exactStops: 8, maximumStops: 50, candidates: 3, concurrency: 2, referencePoints: 10, requests: 160 })

function ensureActive(signal) {
    if (signal?.aborted) throw new DOMException('Route cancelled', 'AbortError')
}

function validCoordinates(value) {
    return Array.isArray(value) && value.length === 2 && value.every(Number.isFinite)
        && Math.abs(value[0]) <= 90 && Math.abs(value[1]) <= 180
}

function sameCoordinates(left, right) { return left[0] === right[0] && left[1] === right[1] }

export function exactRoadOrder(matrix, returnToStart = false) {
    const count = matrix.length - 1
    if (count < 1 || count > ROUTE_LIMITS.exactStops || matrix.some(row => row.length !== matrix.length
        || row.some(value => !Number.isFinite(value) || value < 0))) throw new Error('Некорректные дорожные расстояния.')
    const states = 1 << count
    const costs = Array.from({ length: states }, () => new Float64Array(count).fill(Infinity))
    const previous = Array.from({ length: states }, () => new Int8Array(count).fill(-1))
    for (let last = 0; last < count; last++) costs[1 << last][last] = matrix[0][last + 1]
    for (let mask = 1; mask < states; mask++) {
        for (let last = 0; last < count; last++) {
            if (!(mask & (1 << last))) continue
            for (let next = 0; next < count; next++) {
                if (mask & (1 << next)) continue
                const nextMask = mask | (1 << next)
                const cost = costs[mask][last] + matrix[last + 1][next + 1]
                if (cost < costs[nextMask][next]) {
                    costs[nextMask][next] = cost
                    previous[nextMask][next] = last
                }
            }
        }
    }
    let last = 0
    const full = states - 1
    const total = index => costs[full][index] + (returnToStart ? matrix[index + 1][0] : 0)
    for (let index = 1; index < count; index++) if (total(index) < total(last)) last = index
    const seconds = total(last)
    const indexes = []
    for (let mask = full; mask;) {
        indexes.push(last)
        const next = previous[mask][last]
        mask ^= 1 << last
        last = next
    }
    return { indexes: indexes.reverse(), seconds }
}

// Geographic distance only limits the candidate set for larger days. Actual
// road travel time, never a straight-line estimate, selects each next stop.
function candidateDistance(left, right) {
    const radians = Math.PI / 180
    const latitude = (left[0] + right[0]) * radians / 2
    return ((left[0] - right[0]) * radians) ** 2
        + ((left[1] - right[1]) * radians * Math.cos(latitude)) ** 2
}

export async function planDeliveryRoute(origin, stops, roadTime, {
    returnToStart = false, signal, onProgress = () => {},
} = {}) {
    ensureActive(signal)
    if (!validCoordinates(origin) || !stops.length || stops.some(stop => !validCoordinates(stop.coordinates))) {
        throw new Error('Для маршрута нужны точные координаты старта и каждого адреса доставки.')
    }
    if (stops.length > ROUTE_LIMITS.maximumStops) throw new Error(`В одном маршруте может быть до ${ROUTE_LIMITS.maximumStops} адресов.`)
    const points = [origin, ...stops.map(stop => stop.coordinates)]
    const cache = new Map()
    let requests = 0
    let completed = 0
    const estimatedRequests = stops.length <= ROUTE_LIMITS.exactStops
        ? stops.length ** 2 + (returnToStart ? stops.length : 0)
        : stops.length * ROUTE_LIMITS.candidates + (returnToStart ? 1 : 0)
    const time = async (from, to) => {
        ensureActive(signal)
        if (sameCoordinates(points[from], points[to])) return 0
        const key = `${from}:${to}`
        if (!cache.has(key)) {
            if (++requests > ROUTE_LIMITS.requests) throw new Error('Достигнут предел расчёта маршрута. Уменьшите число адресов.')
            cache.set(key, Promise.resolve().then(() => roadTime(points[from], points[to], signal)))
        }
        const seconds = await cache.get(key)
        ensureActive(signal)
        if (!Number.isFinite(seconds) || seconds < 0) throw new Error('Яндекс не вернул время проезда по одной из дорог. Маршрут не построен.')
        completed++
        onProgress({ completed, total: estimatedRequests })
        return seconds
    }
    let indexes
    let seconds = 0
    const exact = stops.length <= ROUTE_LIMITS.exactStops
    if (exact) {
        const matrix = Array.from({ length: points.length }, () => Array(points.length).fill(0))
        const jobs = []
        for (let from = 0; from < points.length; from++) {
            for (let to = returnToStart ? 0 : 1; to < points.length; to++) if (from !== to) jobs.push([from, to])
        }
        let cursor = 0
        let failed = false
        await Promise.all(Array.from({ length: Math.min(ROUTE_LIMITS.concurrency, jobs.length) }, async () => {
            try {
                while (!failed && cursor < jobs.length) {
                    ensureActive(signal)
                    const [from, to] = jobs[cursor++]
                    matrix[from][to] = await time(from, to)
                }
            } catch (error) { failed = true; throw error }
        }))
        ;({ indexes, seconds } = exactRoadOrder(matrix, returnToStart))
    } else {
        indexes = []
        const remaining = new Set(stops.map((_, index) => index + 1))
        let from = 0
        while (remaining.size) {
            ensureActive(signal)
            const candidates = [...remaining].sort((left, right) => candidateDistance(points[from], points[left])
                - candidateDistance(points[from], points[right])).slice(0, ROUTE_LIMITS.candidates)
            let best = null
            // Sequential requests also stay within the global concurrency limit.
            for (const to of candidates) {
                const duration = await time(from, to)
                if (!best || duration < best.duration) best = { to, duration }
            }
            indexes.push(best.to - 1)
            remaining.delete(best.to)
            seconds += best.duration
            from = best.to
        }
        if (returnToStart) seconds += await time(from, 0)
    }
    ensureActive(signal)
    return { stops: indexes.map(index => stops[index]), seconds, exact, requests }
}

export function splitRoutePoints(points) {
    if (points.some(point => !validCoordinates(point))) throw new Error('Некорректные координаты маршрута.')
    const distinct = points.filter((point, index) => !index || !sameCoordinates(point, points[index - 1]))
    const chunks = []
    for (let index = 0; index < distinct.length - 1; index += ROUTE_LIMITS.referencePoints - 1) {
        chunks.push(distinct.slice(index, index + ROUTE_LIMITS.referencePoints))
    }
    return chunks
}

export function yandexRouteUrl(points) {
    if (points.length < 2 || points.length > ROUTE_LIMITS.referencePoints || points.some(point => !validCoordinates(point))) return null
    const url = new URL('https://yandex.ru/maps/')
    url.searchParams.set('mode', 'routes')
    url.searchParams.set('rtt', 'auto')
    url.searchParams.set('rtext', points.map(point => point.join(',')).join('~'))
    return url.href
}

export function disposeYandexRoute(route) {
    route?.getMap?.()?.geoObjects.remove(route)
    route?.model?.destroy?.()
}

export function requestYandexRoute(maps, points, { signal, timeoutMs = 20000 } = {}) {
    ensureActive(signal)
    if (typeof maps?.route !== 'function' || points.length < 2 || points.length > ROUTE_LIMITS.referencePoints
        || points.some(point => !validCoordinates(point))) return Promise.reject(new Error('Яндекс Маршрутизация недоступна.'))
    return new Promise((resolve, reject) => {
        let settled = false
        let timer
        const finish = (error, route) => {
            if (settled) { if (route) disposeYandexRoute(route); return }
            settled = true
            clearTimeout(timer)
            signal?.removeEventListener('abort', cancel)
            if (error) reject(error)
            else resolve(route)
        }
        const cancel = () => finish(new DOMException('Route cancelled', 'AbortError'))
        signal?.addEventListener('abort', cancel, { once: true })
        timer = setTimeout(() => finish(new Error('Яндекс не ответил при расчёте дороги. Повторите расчёт.')), timeoutMs)
        Promise.resolve().then(() => {
            ensureActive(signal)
            return maps.route(points, {
                multiRoute: true, routingMode: 'auto', avoidTrafficJams: true,
                reverseGeocoding: false, searchCoordOrder: 'latlong',
            })
        }).then(route => finish(null, route), () => finish(new Error('Не удалось построить автомобильный маршрут. Проверьте подключение Яндекс Карт и доступность дорог.')))
    })
}

export function fastestYandexRoute(multiroute, expectedPointCount) {
    let best = null
    multiroute?.getRoutes?.()?.each(route => {
        const properties = route.properties
        const seconds = properties.get('durationInTraffic')?.value ?? properties.get('duration')?.value
        const metres = properties.get('distance')?.value
        if (expectedPointCount && route.getPaths?.()?.getLength?.() !== expectedPointCount - 1) return
        if (properties.get('blocked') === true || !Number.isFinite(seconds) || seconds < 0 || !Number.isFinite(metres) || metres < 0) return
        if (!best || seconds < best.seconds) best = { route, seconds, metres }
    })
    if (!best) throw new Error('Для одного из адресов нет доступного автомобильного маршрута. Проверьте адреса.')
    multiroute.setActiveRoute?.(best.route)
    return best
}
