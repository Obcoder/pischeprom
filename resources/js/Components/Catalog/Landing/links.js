/** Only local paths, fragment IDs, and ordinary web links may appear in saved content. */
export function safeLandingUrl(value) {
    if (typeof value !== 'string' || !value || /[\s\\\u0000-\u001f\u007f]/u.test(value)) return null
    if (/^#[a-z][a-z0-9_-]*$/i.test(value)) return value
    if (value.startsWith('/') && !value.startsWith('//')) return value
    try {
        const url = new URL(value)
        return ['http:', 'https:'].includes(url.protocol) ? value : null
    } catch { return null }
}

export function landingTextParts(value, goods = {}, anchors = null) {
    const text = String(value ?? '')
    const pattern = /\[\[(good:\d+|#[a-z][a-z0-9_-]*)\|(\[[^\]]*\]|[^\]]*)\]\]/gi
    const parts = []
    let cursor = 0
    for (const match of text.matchAll(pattern)) {
        if (match.index > cursor) parts.push({ text: text.slice(cursor, match.index) })
        const goodId = match[1].startsWith('good:') ? match[1].slice(5) : null
        let href = safeLandingUrl(goodId ? goods[goodId]?.url : match[1])
        if (href?.startsWith('#') && anchors && !anchors.has(href)) href = null
        parts.push({ text: match[2], href, goodId })
        cursor = match.index + match[0].length
    }
    if (cursor < text.length) parts.push({ text: text.slice(cursor) })
    return parts
}
