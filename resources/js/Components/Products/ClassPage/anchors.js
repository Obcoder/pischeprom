// The caller supplies its own page root, so identical anchors elsewhere stay untouched.
export function revealClassAnchor(root, hash) {
    if (!root || !hash || hash === '#') return null
    let id
    try { id = decodeURIComponent(hash.slice(1)) } catch { return null }
    const target = [...root.querySelectorAll('[id]')].find(element => element.id === id)
    if (!target) return null
    for (let ancestor = target; ancestor && root.contains(ancestor); ancestor = ancestor.parentElement) {
        if (ancestor.tagName === 'DETAILS') ancestor.open = true
    }
    return target
}

export function currentClassSection(sections, offset) {
    let current = sections[0]
    for (const section of sections) if (section.getBoundingClientRect().top <= offset) current = section
    return current?.id || ''
}
