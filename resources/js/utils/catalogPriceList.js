import { publicCatalogOffer, catalogStatus } from './publicCatalog.js'
import { unitLabel } from './goodMeasurement.js'

export const priceListStorageKey = 'pps-catalog-price-list-columns-v1'
export const priceListDefaultColumns = Object.freeze({ country: true, pack: true, availability: true })

export function normalizePriceListColumns(value) {
    return Object.fromEntries(Object.entries(priceListDefaultColumns).map(([key, fallback]) => [key, typeof value?.[key] === 'boolean' ? value[key] : fallback]))
}

// Read on mount so server rendering and the first client render agree.
export function readPriceListColumns(storage) {
    try {
        const target = storage ?? (typeof window !== 'undefined' ? window.localStorage : null)
        return normalizePriceListColumns(JSON.parse(target?.getItem(priceListStorageKey) || 'null'))
    } catch {
        return { ...priceListDefaultColumns }
    }
}

export function writePriceListColumns(value, storage) {
    try {
        const target = storage ?? (typeof window !== 'undefined' ? window.localStorage : null)
        target?.setItem(priceListStorageKey, JSON.stringify(normalizePriceListColumns(value)))
    } catch {
        // Private browsing and blocked storage must not interrupt shopping.
    }
}

export function priceListQuantity(value) {
    const text = String(value ?? '').trim().replace(',', '.')
    if (!/^\d+(?:\.\d*)?$/.test(text)) return null
    const amount = Number(text)
    return Number.isFinite(amount) && amount >= 0.001 && amount <= 9999 ? Math.round(amount * 1000) / 1000 : null
}

export function priceListCanOrder(good) {
    const offer = publicCatalogOffer(good)
    return Boolean(offer.measurement?.measure_id && offer.measurement?.unit_label) && offer.can_order !== false
}

export function priceListUnit(good) {
    const measurement = publicCatalogOffer(good).measurement
    return measurement?.measure_id && measurement?.unit_label ? unitLabel(measurement) : null
}

export function priceListScope(goods, selected) {
    return selected.size ? goods.filter(good => selected.has(String(good.id))) : [...goods]
}

export function priceListTotal(good, quantity) {
    const offer = publicCatalogOffer(good)
    const amount = priceListQuantity(quantity)
    return offer.price && amount !== null && priceListCanOrder(good) ? Math.round(offer.price * amount * 100) / 100 : null
}

export function priceListPack(good) {
    return Number(good.denominator) > 0 ? `${Number(good.denominator).toLocaleString('ru-RU')} кг / уп.` : '—'
}

function spreadsheetText(value) {
    const text = String(value ?? '').replace(/[\t\r\n]+/g, ' ')
    // Excel can interpret a cell as a formula even after leading whitespace.
    return /^[\s\uFEFF]*[=+@-]/u.test(text) ? `'${text}` : text
}

export function buildPriceListExport(goods, quantities = {}, { format = 'tsv', nodes = [], from = 1, pageGoods = goods } = {}) {
    const sections = new Map(nodes.map(node => [String(node.id), node.name]))
    const positions = new Map(pageGoods.map((good, index) => [String(good.id), from + index]))
    const rows = [['№', 'Наименование', 'Раздел', 'Страна', 'Фасовка', 'Наличие', 'Цена', 'Валюта', 'Единица', 'Количество', 'Сумма']]
    for (const good of goods) {
        const offer = publicCatalogOffer(good)
        const amount = priceListCanOrder(good) ? priceListQuantity(quantities[String(good.id)] ?? 1) : null
        const total = priceListTotal(good, amount)
        const decimal = value => value === null ? '' : String(value).replace('.', ',')
        rows.push([positions.get(String(good.id)) ?? '', good.name, sections.get(String(good.catalog_node_id)) || '', good.country?.name || '',
            priceListPack(good), catalogStatus(good).label, offer.price === null ? 'Цена по запросу' : decimal(offer.price), offer.currency_code,
            priceListUnit(good) || 'Уточняется', decimal(amount), decimal(total)])
    }
    const delimiter = format === 'csv' ? ';' : '\t'
    const cell = value => format === 'csv' ? `"${spreadsheetText(value).replaceAll('"', '""')}"` : spreadsheetText(value)
    return (format === 'csv' ? '\uFEFF' : '') + rows.map(row => row.map(cell).join(delimiter)).join('\r\n')
}
