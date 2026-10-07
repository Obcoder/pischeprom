export function catalogTree(nodes, { search = '', publication = 'all', expanded = new Set() } = {}) {
    const byId = new Map(nodes.map(node => [node.id, node]))
    const children = new Map()
    const compare = (a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name, 'ru') || a.id - b.id
    for (const node of nodes) {
        const parent = byId.has(node.parent_id) ? node.parent_id : null
        if (!children.has(parent)) children.set(parent, [])
        children.get(parent).push(node)
    }
    children.forEach(items => items.sort(compare))
    const query = String(search || '').trim().toLocaleLowerCase('ru')
    const filtering = Boolean(query) || publication !== 'all'
    const matches = node => (!query || `${node.name} ${node.slug || ''} ${node.entity_id || ''}`.toLocaleLowerCase('ru').includes(query))
        && (publication === 'all' || (publication === 'published' ? node.is_published : publication === 'featured' ? node.is_featured : !node.is_published))
    const visible = new Set()
    if (filtering) {
        nodes.filter(matches).forEach(node => {
            const path = new Set()
            while (node && !path.has(node.id)) {
                path.add(node.id)
                visible.add(node.id)
                node = byId.get(node.parent_id)
            }
        })
    }
    const rows = []
    const visited = new Set()
    const walk = (parent, depth) => {
        for (const node of children.get(parent) || []) {
            if (visited.has(node.id) || (filtering && !visible.has(node.id))) continue
            visited.add(node.id)
            const descendants = children.get(node.id) || []
            rows.push({ ...node, depth, hasChildren: descendants.length > 0, childCount: descendants.length, expanded: filtering || expanded.has(node.id), context: filtering && !matches(node) })
            if (filtering || expanded.has(node.id)) walk(node.id, depth + 1)
        }
    }
    walk(null, 0)
    return rows
}

export function descendantIds(nodes, id) {
    const result = new Set([id])
    const children = new Map()
    for (const node of nodes) {
        if (!children.has(node.parent_id)) children.set(node.parent_id, [])
        children.get(node.parent_id).push(node.id)
    }
    const queue = [id]
    for (let i = 0; i < queue.length; i++) {
        for (const child of children.get(queue[i]) || []) {
            if (!result.has(child)) { result.add(child); queue.push(child) }
        }
    }
    return result
}

export function restoredCatalogTab(value) {
    if (['commodities', 'services', 'purchased'].includes(value)) return 'purchased'
    return value === 'components' ? 'components' : 'goods'
}
