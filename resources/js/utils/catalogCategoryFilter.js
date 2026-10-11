const categoryKey = value => value == null || value === '' ? '' : String(value)
const normalizeSearch = value => String(value ?? '').normalize('NFKC').toLocaleLowerCase('ru-RU').replace(/ё/g, 'е').trim()

export function catalogCategoryIndex(nodes = []) {
    const byId = new Map(nodes.map(node => [categoryKey(node.id), node]))
    const children = new Map()
    for (const node of byId.values()) {
        const parent = categoryKey(node.parent_id)
        const key = byId.has(parent) && parent !== categoryKey(node.id) ? parent : ''
        if (!children.has(key)) children.set(key, [])
        children.get(key).push(node)
    }
    return { byId, children }
}

export function catalogCategoryAncestors(index, id) {
    const ancestors = new Set()
    let node = index.byId.get(categoryKey(id))
    const visited = new Set([categoryKey(id)])
    while (node) {
        const key = categoryKey(node.parent_id)
        if (!index.byId.has(key) || visited.has(key)) break
        ancestors.add(key)
        visited.add(key)
        node = index.byId.get(key)
    }
    return ancestors
}

export function catalogCategorySearch(index, query) {
    const words = normalizeSearch(query).split(/\s+/).filter(Boolean)
    if (!words.length) return null
    const matches = new Set()
    const visible = new Set()
    for (const [key, node] of index.byId) {
        const name = normalizeSearch(node.name)
        if (!words.every(word => name.includes(word))) continue
        matches.add(key)
        visible.add(key)
        for (const ancestor of catalogCategoryAncestors(index, key)) visible.add(ancestor)
    }
    return { matches, visible }
}

export function catalogCategoryRows(index, expanded, search = null) {
    const rows = []
    const visited = new Set()
    function visit(parent, depth) {
        for (const node of index.children.get(parent) || []) {
            const key = categoryKey(node.id)
            if (visited.has(key) || (search && !search.visible.has(key))) continue
            visited.add(key)
            const hasChildren = (index.children.get(key) || []).some(child => !search || search.visible.has(categoryKey(child.id)))
            rows.push({ node, key, depth, hasChildren, matched: Boolean(search?.matches.has(key)) })
            if (expanded.has(key)) visit(key, depth + 1)
        }
    }
    visit('', 0)
    return rows
}
