function ensureActive(signal) {
    if (signal?.aborted) throw new DOMException('Map request cancelled', 'AbortError')
}

export async function loadDeliveryOrders(fetchPage, query, { signal, onProgress = () => {} } = {}) {
    const orders = new Map()
    let lastPage = 1
    for (let page = 1; page <= lastPage; page++) {
        ensureActive(signal)
        const response = await fetchPage({ search: query.search, filter: query.filter, page, per_page: 100 })
        ensureActive(signal)
        const meta = response?.meta
        if (!Array.isArray(response?.data) || meta?.current_page !== page
            || !Number.isInteger(meta?.last_page) || meta.last_page < 1
            || !Number.isInteger(meta?.total) || meta.total < 0) {
            throw new Error('Не удалось загрузить полный список доставок. Повторите обновление.')
        }
        // Freeze the page count for this refresh so incoming orders cannot prolong it indefinitely.
        if (page === 1) lastPage = meta.last_page
        for (const order of response.data) {
            if (!Number.isInteger(order?.id) || order.id < 1) throw new Error('Сервер вернул некорректный заказ.')
            orders.set(order.id, order)
        }
        onProgress({ loaded: orders.size, total: meta.total })
    }
    return [...orders.values()]
}

export function groupDeliveryAddresses(orders) {
    const groups = new Map()
    const missing = []
    for (const order of orders) {
        const addresses = (order.delivery_addresses || []).filter(address => typeof address.full_address === 'string' && address.full_address.trim())
        if (!addresses.length) missing.push({ order, reason: 'Адрес доставки не указан.' })
        for (const address of addresses) {
            const key = address.full_address.trim().replace(/\s+/g, ' ').toLocaleLowerCase('ru-RU')
            if (!groups.has(key)) groups.set(key, { key, address, orders: [] })
            const group = groups.get(key)
            if (!group.orders.some(item => item.id === order.id)) group.orders.push(order)
        }
    }
    return { groups: [...groups.values()], missing }
}

export function exactGeocodeCoordinates(result) {
    const object = result?.geoObjects?.get(0)
    const metadata = object?.properties?.get('metaDataProperty.GeocoderMetaData')
    const coordinates = object?.geometry?.getCoordinates()
    if (!['exact', 'number'].includes(metadata?.precision)) return null
    if (!Array.isArray(coordinates) || coordinates.length !== 2
        || !coordinates.every(Number.isFinite) || Math.abs(coordinates[0]) > 90 || Math.abs(coordinates[1]) > 180) return null
    return coordinates
}

async function timedGeocode(geocode, address, timeoutMs, signal) {
    ensureActive(signal)
    let timer
    let cancel
    try {
        return await Promise.race([
            Promise.resolve().then(() => geocode(address)),
            new Promise((_, reject) => {
                timer = setTimeout(() => reject(new Error('Geocoding timed out')), timeoutMs)
                cancel = () => reject(new DOMException('Map request cancelled', 'AbortError'))
                signal?.addEventListener('abort', cancel, { once: true })
            }),
        ])
    } finally {
        clearTimeout(timer)
        signal?.removeEventListener('abort', cancel)
    }
}

export async function resolveDeliveryGroups(groups, geocode, {
    signal, onResult = () => {}, concurrency = 2, timeoutMs = 12000,
} = {}) {
    let cursor = 0
    let completed = 0
    async function worker() {
        while (cursor < groups.length) {
            ensureActive(signal)
            const group = groups[cursor++]
            let coordinates = null
            let reason = 'Не удалось точно определить дом. Проверьте адрес в заказе.'
            try {
                coordinates = exactGeocodeCoordinates(await timedGeocode(geocode, group.address.full_address, timeoutMs, signal))
            } catch (error) {
                if (error.name === 'AbortError') throw error
                reason = 'Яндекс Карты не ответили. Повторите обновление.'
            }
            ensureActive(signal)
            completed++
            onResult({ group, coordinates, reason: coordinates ? null : reason, completed, total: groups.length })
        }
    }
    await Promise.all(Array.from({ length: Math.min(groups.length, Math.max(1, Math.min(4, concurrency))) }, worker))
}

export function yandexScriptUrl(config) {
    if (!config?.configured || typeof config.api_key !== 'string' || !config.api_key.trim()
        || !/^https:\/\/(?:api-maps|enterprise\.api-maps)\.yandex\.ru\/2\.1\/?$/.test(config.script_url || '')) {
        throw new Error('Карта доставок пока не подключена.')
    }
    const url = new URL(config.script_url)
    url.searchParams.set('apikey', config.api_key)
    url.searchParams.set('lang', 'ru_RU')
    url.searchParams.set('coordorder', 'latlong')
    return url
}

let sdkPromise = null
let sdkSequence = 0

export function loadYandexMaps(config) {
    const url = yandexScriptUrl(config)
    if (sdkPromise) return sdkPromise
    const sequence = ++sdkSequence
    const namespace = `pischepromDeliveryMaps${sequence}`
    const loaded = `${namespace}Loaded`
    const failed = `${namespace}Failed`
    url.searchParams.set('ns', namespace)
    url.searchParams.set('onload', loaded)
    url.searchParams.set('onerror', failed)
    sdkPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.async = true
        script.src = url.href
        let settled = false
        let timer
        const finish = (maps) => {
            if (settled) return
            settled = true
            clearTimeout(timer)
            script.onerror = null
            // Late SDK callbacks after a timed-out request are harmless.
            window[loaded] = () => {}
            window[failed] = () => {}
            if (maps?.Map && maps?.Placemark && maps?.Clusterer && maps?.geocode) resolve(maps)
            else {
                script.remove()
                delete window[namespace]
                sdkPromise = null
                reject(new Error('Не удалось подключиться к Яндекс Картам. Проверьте интернет и повторите обновление.'))
            }
        }
        window[loaded] = finish
        window[failed] = () => finish(null)
        script.onerror = () => finish(null)
        timer = setTimeout(() => finish(null), 20000)
        document.head.appendChild(script)
    })
    return sdkPromise
}
