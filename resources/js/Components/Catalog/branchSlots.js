const maximumOrder = 1000000
const sameId = (first, second) => String(first ?? '') === String(second ?? '')

function validOrder(value) {
    if (value == null || String(value).trim() === '') return null
    const order = Number(value)
    return Number.isInteger(order) && order >= 0 && order <= maximumOrder ? order : null
}

export function catalogBranchSlots(nodes, parentId, nodeId, draftOrder) {
    const occupants = new Map()
    for (const node of nodes) {
        if (!sameId(node.parent_id, parentId) || (nodeId != null && sameId(node.id, nodeId))) continue
        const order = validOrder(node.sort_order ?? 0)
        if (order == null) continue
        if (!occupants.has(order)) occupants.set(order, [])
        occupants.get(order).push(node.name || 'Без названия')
    }
    const occupiedOrders = [...occupants.keys()].sort((a, b) => a - b)
    for (const names of occupants.values()) names.sort((a, b) => a.localeCompare(b, 'ru'))
    const selectedOrder = validOrder(draftOrder)
    let firstFree = 0
    while (occupants.has(firstFree) && firstFree <= maximumOrder) firstFree++
    if (firstFree > maximumOrder) firstFree = null

    // Show the start, ends of the occupied range and the draft's neighbours.
    // Sparse orders can reach one million: never allocate one item per order.
    const visible = new Set()
    const add = value => { if (value != null && value >= 0 && value <= maximumOrder) visible.add(value) }
    for (let order = 0; order < 8; order++) add(order)
    if (selectedOrder != null) for (let delta = -2; delta <= 2; delta++) add(selectedOrder + delta)
    if (firstFree != null) for (let delta = -1; delta <= 1; delta++) add(firstFree + delta)
    for (const order of [...occupiedOrders.slice(0, 3), ...occupiedOrders.slice(-3)]) add(order)
    if (occupiedOrders.length) add(occupiedOrders.at(-1) + 1)

    const items = []
    let previous = -1
    for (const order of [...visible].sort((a, b) => a - b)) {
        if (order > previous + 1) {
            const from = previous + 1, to = order - 1
            const occupied = occupiedOrders.filter(value => value >= from && value <= to).length
            items.push({ type: 'gap', from, to, occupied, free: to - from + 1 - occupied })
        }
        items.push({ type: 'slot', order, names: occupants.get(order) || [], selected: order === selectedOrder })
        previous = order
    }
    return {
        items,
        occupiedCount: occupiedOrders.length,
        firstFree,
        selectedOrder,
        selectedNames: occupants.get(selectedOrder) || [],
        condensed: items.some(item => item.type === 'gap'),
    }
}
