const keyOf = value => value === null || value === undefined ? null : String(value)
const truthy = value => value === true || value === 1 || value === '1'
const compare = (a, b) => (Number(a.sort_order || 0) - Number(b.sort_order || 0))
    || String(a.name || '').localeCompare(String(b.name || ''), 'ru')
    || String(a.id).localeCompare(String(b.id), 'en', { numeric: true })

/**
 * Project a classification forest without assuming that every branch uses the
 * same levels or that level order equals physical tree depth. All selections
 * are optional. Missing parents and cycles are repaired in this read-only view.
 *
 * domainId identifies a domain NODE; null means all, 'unassigned' means no domain.
 * selections uses level IDs as keys and tab node IDs as values.
 * Levels shown as tabs or trees remain classifiers even when empty. List levels
 * contain terminal objects; objects with children stay navigable. Without a
 * known level, category/product sources remain classifiers and other leaves are
 * terminal objects. Assigning a level takes precedence over the source type.
 *
 * Counts: total = all terminal objects; domain = objects in the selected domain;
 * scoped = objects after domain/tab/branch selections; matched
 * and list = right-hand items after search/publication. published/featured count
 * scoped objects before filters. branches counts the full projected sidebar;
 * domainNodesCount counts all nodes in the selected domain before selections.
 *
 * Traversals and subtree counts are linear; sibling ordering costs O(n log n).
 * Breadcrumb generation is proportional to the size of the returned paths.
 */
export function buildCatalogView(nodes = [], levels = [], options = {}) {
    const input = Array.isArray(nodes) ? nodes : []
    const levelList = (Array.isArray(levels) ? levels : []).slice().sort(compare)
    const levelById = new Map(levelList.map(level => [keyOf(level.id), level]))
    const byId = new Map(input.filter(node => keyOf(node?.id) !== null).map(node => [keyOf(node.id), node]))
    const parent = new Map()
    const mode = new Map()
    const isDomain = new Set()

    for (const [id, node] of byId) {
        const parentId = keyOf(node.parent_id)
        parent.set(id, byId.has(parentId) ? parentId : null)
        const level = levelById.get(keyOf(node.level_id))
        mode.set(id, level?.display_mode || 'tree')
        if (truthy(level?.is_domain)) isDomain.add(id)
    }

    // Each parent edge is visited once. Cutting one edge in every cycle retains
    // all its records as an ordinary, reachable branch instead of dropping it.
    const complete = new Set()
    for (const id of byId.keys()) {
        if (complete.has(id)) continue
        const path = new Set()
        let current = id
        while (current !== null && !complete.has(current)) {
            if (path.has(current)) { parent.set(current, null); break }
            path.add(current)
            current = parent.get(current) ?? null
        }
        for (const visited of path) complete.add(visited)
    }

    const children = new Map([[null, []]])
    for (const id of byId.keys()) {
        const parentId = parent.get(id)
        if (!children.has(parentId)) children.set(parentId, [])
        children.get(parentId).push(id)
    }
    for (const siblings of children.values()) siblings.sort((a, b) => compare(byId.get(a), byId.get(b)))

    const order = []
    const start = new Map()
    const end = new Map()
    const domain = new Map()
    const ownSearchMatch = new Map()
    const searchMatch = new Map()
    const query = String(options.search ?? '').trim().toLocaleLowerCase('ru')
    const publication = ['published', 'draft', 'featured'].includes(options.publication) ? options.publication : 'all'
    const filtering = Boolean(query) || publication !== 'all'
    const publicationMatches = node => publication === 'all'
        || (publication === 'featured' ? truthy(node.is_featured)
            : publication === 'published' ? truthy(node.is_published) : !truthy(node.is_published))
    const stack = (children.get(null) || []).slice().reverse().map(id => ({ id, closing: false }))
    while (stack.length) {
        const { id, closing } = stack.pop()
        if (closing) { end.set(id, order.length); continue }
        const node = byId.get(id)
        const parentId = parent.get(id)
        const matches = !query || [node.name, node.slug, node.entity_id]
            .filter(value => value !== null && value !== undefined)
            .join(' ').toLocaleLowerCase('ru').includes(query)
        ownSearchMatch.set(id, matches)
        searchMatch.set(id, matches || Boolean(searchMatch.get(parentId)))
        domain.set(id, isDomain.has(id) ? id : (domain.get(parentId) ?? null))
        start.set(id, order.length)
        order.push(id)
        stack.push({ id, closing: true })
        const descendants = children.get(id) || []
        for (let index = descendants.length - 1; index >= 0; index--) stack.push({ id: descendants[index], closing: false })
    }

    const contains = (ancestor, descendant) => ancestor === null
        || (start.get(ancestor) <= start.get(descendant) && start.get(descendant) < end.get(ancestor))
    const compatible = (first, second) => first === null || second === null || contains(first, second) || contains(second, first)
    const narrower = (first, second) => first === null || contains(first, second) ? second : first
    const terminal = new Set(order.filter(id => {
        if (isDomain.has(id) || children.get(id)?.length) return false
        const node = byId.get(id)
        return levelById.has(keyOf(node.level_id))
            ? mode.get(id) === 'list'
            : !['category', 'product'].includes(node.entity_type)
    }))

    let selectedDomain = options.domainId === 'unassigned' ? 'unassigned' : keyOf(options.domainId)
    if (selectedDomain !== null && selectedDomain !== 'unassigned' && !isDomain.has(selectedDomain)) selectedDomain = null
    const inDomain = id => selectedDomain === null || domain.get(id) === (selectedDomain === 'unassigned' ? null : selectedDomain)
    const terminalCounts = new Map()
    const domainNodeCounts = new Map()
    const matchedDomainCounts = new Map()
    const scopedPrefix = [0]
    const matchedPrefix = [0]
    const publishedPrefix = [0]
    const featuredPrefix = [0]
    const levelNodes = new Map()
    let domainNodesCount = 0
    for (const id of order) {
        const node = byId.get(id)
        const domainId = domain.get(id)
        domainNodeCounts.set(domainId, (domainNodeCounts.get(domainId) || 0) + 1)
        const leaf = terminal.has(id)
        const matched = leaf && searchMatch.get(id) && publicationMatches(node)
        if (leaf) terminalCounts.set(domainId, (terminalCounts.get(domainId) || 0) + 1)
        if (matched) matchedDomainCounts.set(domainId, (matchedDomainCounts.get(domainId) || 0) + 1)
        const scoped = inDomain(id)
        if (scoped) domainNodesCount++
        scopedPrefix.push(scopedPrefix.at(-1) + Number(scoped && leaf))
        matchedPrefix.push(matchedPrefix.at(-1) + Number(scoped && matched))
        publishedPrefix.push(publishedPrefix.at(-1) + Number(scoped && leaf && truthy(node.is_published)))
        featuredPrefix.push(featuredPrefix.at(-1) + Number(scoped && leaf && truthy(node.is_featured)))
        const levelId = keyOf(node.level_id)
        if (!levelNodes.has(levelId)) levelNodes.set(levelId, [])
        levelNodes.get(levelId).push(id)
    }
    const countRange = (prefix, root = null) => root === null ? prefix.at(-1) : prefix[end.get(root)] - prefix[start.get(root)]
    const countWithin = (prefix, node, scope) => compatible(node, scope) ? countRange(prefix, narrower(scope, node)) : 0
    const raw = id => byId.get(id)
    const domainTabs = [{ id: null, name: 'Все', count: terminal.size, selected: selectedDomain === null }]
    for (const id of [...isDomain].filter(id => mode.get(id) === 'tabs').sort((a, b) => compare(raw(a), raw(b)))) {
        domainTabs.push({ ...raw(id), count: terminalCounts.get(id) || 0, matchedCount: matchedDomainCounts.get(id) || 0, selected: selectedDomain === id })
    }
    if (domainNodeCounts.get(null)) {
        domainTabs.push({ id: 'unassigned', name: 'Без домена', count: terminalCounts.get(null) || 0, selected: selectedDomain === 'unassigned' })
    }

    let tabScope = null
    let clearFollowingSelections = false
    const selections = {}
    const tabRows = []
    for (const level of levelList) {
        if (truthy(level.is_domain) || level.display_mode !== 'tabs') continue
        const levelId = keyOf(level.id)
        const candidates = (levelNodes.get(levelId) || [])
            .filter(id => !terminal.has(id) && inDomain(id) && compatible(tabScope, id))
            .sort((a, b) => compare(raw(a), raw(b)))
        const requested = keyOf(options.selections?.[level.id])
        const selected = !clearFollowingSelections && candidates.includes(requested) ? requested : null
        if (requested !== null && selected === null) clearFollowingSelections = true
        selections[level.id] = selected === null ? null : raw(selected).id
        if (candidates.length) {
            tabRows.push({
                levelId: level.id, name: level.name, selectedId: selections[level.id],
                items: candidates.map(id => ({
                    ...raw(id), selected: id === selected,
                    hasChildren: Boolean(children.get(id)?.length),
                    count: countWithin(scopedPrefix, id, tabScope),
                    matchedCount: countWithin(matchedPrefix, id, tabScope),
                })),
            })
        }
        if (selected !== null) tabScope = narrower(tabScope, selected)
    }

    let branch = clearFollowingSelections ? null : keyOf(options.selectedBranchId)
    if (!byId.has(branch) || terminal.has(branch) || !inDomain(branch) || !compatible(tabScope, branch)) branch = null
    const itemScope = branch === null ? tabScope : narrower(tabScope, branch)
    const expanded = new Set([...(options.expanded || [])].map(keyOf))
    const projected = new Set()
    for (const id of order) {
        const navigable = !terminal.has(id) && mode.get(id) !== 'tabs'
        if (!navigable || !inDomain(id) || !compatible(tabScope, id)) continue
        if (filtering && !countWithin(matchedPrefix, id, tabScope) && !(ownSearchMatch.get(id) && publicationMatches(raw(id)))) continue
        projected.add(id)
    }

    const projectedChildren = new Map([[null, []]])
    const projectedParent = new Map()
    for (const id of order) {
        const ancestor = projectedParent.get(parent.get(id)) ?? null
        projectedParent.set(id, projected.has(id) ? id : ancestor)
        if (!projected.has(id)) continue
        if (!projectedChildren.has(ancestor)) projectedChildren.set(ancestor, [])
        projectedChildren.get(ancestor).push(id)
    }
    for (const siblings of projectedChildren.values()) siblings.sort((a, b) => compare(raw(a), raw(b)))
    const treeRows = []
    const treeStack = (projectedChildren.get(null) || []).slice().reverse().map(id => ({ id, depth: 0 }))
    while (treeStack.length) {
        const { id, depth } = treeStack.pop()
        const descendants = projectedChildren.get(id) || []
        const open = filtering || expanded.has(id)
            || (tabScope !== null && id !== tabScope && contains(id, tabScope))
            || (branch !== null && id !== branch && contains(id, branch))
        treeRows.push({
            ...raw(id), depth, expanded: open, selected: id === branch,
            hasChildren: Boolean(children.get(id)?.length), childCount: children.get(id)?.length || 0,
            hasTreeChildren: descendants.length > 0, treeChildCount: descendants.length,
            descendantCount: end.get(id) - start.get(id) - 1,
            count: countWithin(scopedPrefix, id, tabScope),
            matchedCount: countWithin(matchedPrefix, id, tabScope),
            context: filtering && !(ownSearchMatch.get(id) && publicationMatches(raw(id))),
        })
        if (open) for (let index = descendants.length - 1; index >= 0; index--) treeStack.push({ id: descendants[index], depth: depth + 1 })
    }

    const items = order
        .filter(id => terminal.has(id) && inDomain(id) && contains(itemScope, id) && searchMatch.get(id) && publicationMatches(raw(id)))
        .map(id => {
            const ancestors = []
            let ancestor = parent.get(id)
            while (ancestor !== null) {
                const node = raw(ancestor)
                ancestors.push({ id: node.id, name: node.name, level_id: node.level_id ?? null })
                ancestor = parent.get(ancestor)
            }
            ancestors.reverse()
            return {
                ...raw(id), hasChildren: false, childCount: 0, descendantCount: 0,
                level_name: levelById.get(keyOf(raw(id).level_id))?.name || 'Без уровня',
                ancestors, path_label: ancestors.map(node => node.name).join(' / '),
            }
        })
        .sort(compare)

    return {
        domainTabs,
        domainId: selectedDomain === null || selectedDomain === 'unassigned' ? selectedDomain : raw(selectedDomain).id,
        selections,
        tabRows,
        treeRows,
        items,
        selectedBranchId: branch === null ? null : raw(branch).id,
        scopeNodeId: itemScope !== null ? raw(itemScope).id
            : selectedDomain !== null && selectedDomain !== 'unassigned' ? raw(selectedDomain).id : null,
        counts: {
            total: terminal.size,
            domain: countRange(scopedPrefix),
            scoped: countRange(scopedPrefix, itemScope),
            matched: items.length,
            list: items.length,
            branches: projected.size,
            published: countRange(publishedPrefix, itemScope),
            featured: countRange(featuredPrefix, itemScope),
            domainNodesCount,
        },
    }
}
