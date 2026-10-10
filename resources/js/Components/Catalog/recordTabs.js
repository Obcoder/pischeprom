export const goodRecordTabs = [
    { id: 'overview', label: 'Основное', icon: 'mdi-package-variant-closed' },
    { id: 'seo', label: 'SEO', icon: 'mdi-magnify' },
    { id: 'warehouse', label: 'Склад', icon: 'mdi-warehouse' },
    { id: 'market', label: 'Маркет', icon: 'mdi-storefront-outline' },
    { id: 'prices', label: 'Цены', icon: 'mdi-tag-outline' },
    { id: 'price-types', label: 'Типы цен', icon: 'mdi-format-list-bulleted-type' },
    { id: 'collections', label: 'Подборки', icon: 'mdi-folder-multiple-outline' },
    { id: 'media', label: 'Медиа', icon: 'mdi-image-multiple-outline' },
    { id: 'sales', label: 'Продажи', icon: 'mdi-cart-outline' },
    { id: 'landing', label: 'Лендинг', icon: 'mdi-web' },
]

export const catalogRecordTabs = goodRecordTabs.filter(tab => ['overview', 'landing'].includes(tab.id))

export const productRecordTabs = [
    { id: 'overview', label: 'Основное', icon: 'mdi-shape-outline' },
    { id: 'translations', label: 'Переводы', icon: 'mdi-translate' },
    { id: 'manufacturers', label: 'Производители', icon: 'mdi-factory' },
    { id: 'components', label: 'Компоненты', icon: 'mdi-puzzle-outline' },
    { id: 'goods', label: 'Товары', icon: 'mdi-package-variant-closed' },
    { id: 'units', label: 'Единицы', icon: 'mdi-format-list-bulleted' },
    { id: 'consumers', label: 'Потребители', icon: 'mdi-account-group-outline' },
    { id: 'sales', label: 'Продажи', icon: 'mdi-cart-outline' },
    { id: 'landing', label: 'Лендинг', icon: 'mdi-web' },
]

export function normalizeRecordTab(id) {
    return ({ quotations: 'market', purchases: 'warehouse', recommendations: 'sales' })[id] || id
}

export function normalizeTabOrder(value, tabs = goodRecordTabs) {
    const allowed = tabs.map(tab => tab.id)
    return [...new Set([...(Array.isArray(value) ? value : []).map(normalizeRecordTab).filter(id => allowed.includes(id)), ...allowed])]
}

export function moveTab(order, from, to) {
    if (from === to || !order.includes(from) || !order.includes(to)) return [...order]
    const result = order.filter(id => id !== from)
    result.splice(order.indexOf(to), 0, from)
    return result
}
