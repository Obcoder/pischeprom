import { measurementForGood } from './goodMeasurement.js'

export const catalogDefaults = Object.freeze({ search: '', country_id: '', field_id: '', node_id: '', availability: 'all', sort: 'name', per_page: 24, view: 'cards', show_filters: true, price_min: '', price_max: '' })

export function normalizeCatalogFilters(values = {}) {
    const result = { ...catalogDefaults, ...values }
    result.view = result.view === 'tree' ? 'tree' : 'cards'
    result.show_filters = ![false, 0, '0', 'false'].includes(result.show_filters)
    result.per_page = Math.min(100, Math.max(10, Math.trunc(Number(result.per_page) || 24)))
    for (const key of ['search', 'country_id', 'field_id', 'node_id', 'price_min', 'price_max']) result[key] = result[key] ?? ''
    return result
}

export function catalogQuery(filters, page = 1) {
    const normalized = normalizeCatalogFilters(filters)
    return Object.fromEntries(Object.entries({ ...normalized, show_filters: normalized.show_filters ? 1 : 0, page })
        .filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

export function catalogPages(current, last) {
    const visible = new Set([1, last, current - 1, current, current + 1].filter(value => value >= 1 && value <= last))
    const result = []
    for (const page of [...visible].sort((a, b) => a - b)) {
        if (result.length && page - result[result.length - 1] > 1) result.push('…')
        result.push(page)
    }
    return result
}

export function publicCatalogOffer(good = {}) {
    const offer = good.public_purchase || {}
    // Only server-authorized public offers can enter the customer cart.
    return { ...offer, price: Number(offer.price) > 0 ? Number(offer.price) : null, currency_code: offer.currency_code || 'RUB', measurement: offer.measurement || measurementForGood(good) }
}

export function catalogMoney(good) {
    const offer = publicCatalogOffer(good)
    return offer.price ? `${offer.price.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${offer.currency_code === 'RUB' ? '₽' : offer.currency_code}` : 'Цена по запросу'
}

export function catalogImage(good) {
    return good.ava_thumb || good.ava_image || (good.published_media || []).find(item => item.type === 'image')?.thumb_url || (good.published_media || []).find(item => item.type === 'image')?.url || ''
}

export function catalogStatus(good) {
    const status = good.availability?.status || (good.stock_availability?.is_in_stock === true ? 'in_stock' : good.stock_availability?.is_in_stock === false ? 'out_of_stock' : 'on_request')
    return { value: status, label: ({ in_stock: 'В наличии', out_of_stock: 'Ожидаем поступление', preorder: 'Под заказ', on_request: 'Наличие уточним' })[status] || 'Наличие уточним' }
}

export function catalogTreeRows(nodes, goods, expanded) {
    const ids = new Set(nodes.map(node => String(node.id)))
    const children = new Map()
    const grouped = new Map()
    const keyFor = parent => ids.has(String(parent)) ? String(parent) : ''
    for (const node of nodes) {
        const key = keyFor(node.parent_id)
        if (!children.has(key)) children.set(key, [])
        children.get(key).push(node)
    }
    for (const good of goods) {
        const key = keyFor(good.catalog_node_id)
        if (!grouped.has(key)) grouped.set(key, [])
        grouped.get(key).push(good)
    }
    const rows = []
    const visited = new Set()
    function visit(parent, depth) {
        for (const node of children.get(parent) || []) {
            const key = String(node.id)
            if (visited.has(key)) continue
            visited.add(key)
            const expandable = Boolean(children.get(key)?.length || grouped.get(key)?.length)
            rows.push({ type: 'node', key: `node-${key}`, node, depth, expandable })
            if (expanded.has(key)) visit(key, depth + 1)
        }
        for (const good of grouped.get(parent) || []) rows.push({ type: 'good', key: `good-${good.id}`, good, depth })
    }
    visit('', 0)
    return rows
}

export function expandProductBranches(nodes, goods) {
    const parents = new Map(nodes.map(node => [String(node.id), node.parent_id]))
    const expanded = new Set()
    for (const good of goods) {
        let key = String(good.catalog_node_id)
        const path = new Set()
        while (parents.has(key) && !path.has(key)) {
            path.add(key)
            expanded.add(key)
            key = String(parents.get(key))
        }
    }
    return expanded
}
