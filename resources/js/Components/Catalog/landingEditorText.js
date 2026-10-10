const referencePattern = /\[\[(good:\d+|#[a-z][a-z0-9_-]*)\|(\[[^\]]*\]|[^\]]*)\]\]/gi

function markerFor(label, text, references, token) {
    const existing = references.find(item => item.token === token)
    if (existing) return existing.marker
    const caption = String(label).replaceAll('⟦', '(').replaceAll('⟧', ')') || 'Ссылка'
    let marker = `⟦${caption}⟧`, suffix = 1
    while (text.includes(marker) || references.some(item => item.marker === marker)) marker = `⟦${caption} · ${++suffix}⟧`
    return marker
}

/** References stay intact in storage while editors see readable labels, never identifiers. */
export function landingEditorText(value, links = []) {
    const original = String(value || ''), references = []
    const text = original.replace(referencePattern, (token, target, label) => {
        const marker = markerFor(label, original, references, token)
        if (!references.some(item => item.token === token)) references.push({ marker, token, label, target, name: links.find(item => item.target === target)?.name || label })
        return marker
    })
    return { text, references }
}

export function encodeLandingEditorText(text, references) {
    const tokens = new Map(references.map(item => [item.marker, item.token]))
    return String(text || '').replace(/⟦[^⟧]*⟧/g, marker => tokens.get(marker) || marker)
}

export function insertLandingEditorReference(value, start, end, link, links = []) {
    const current = landingEditorText(value, links)
    const marker = markerFor(link.label, current.text, current.references, link.token)
    const before = current.text.slice(0, start), after = current.text.slice(end)
    return {
        value: encodeLandingEditorText(before + marker + after, [...current.references, { marker, token: link.token }]),
        cursor: before.length + marker.length,
    }
}

export function supportsLandingReferences(context = '', key) {
    const path = `${context}.${key}`
    if (['hero.description', 'contact.description', 'sources.description'].includes(path)) return true
    return context.startsWith('block') && ['description', 'text', 'note', 'secondaryText', 'afterword', 'yieldText', 'paragraphs', 'bullets', 'cells'].includes(key)
}
