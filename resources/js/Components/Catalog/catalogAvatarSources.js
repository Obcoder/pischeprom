export function catalogAvatarSources(node = {}) {
    return [...new Set([node?.thumbnail_url, node?.image].flatMap(value => {
        if (typeof value !== 'string') return []
        const url = value.trim()
        if (!url || /[\\\u0000-\u001f\u007f]/.test(url)) return []
        if (/^\/(?!\/)/.test(url)) return [url]
        if (!/^https?:\/\//i.test(url)) return []
        try {
            const parsed = new URL(url)
            return parsed.hostname && !parsed.username && !parsed.password ? [url] : []
        } catch { return [] }
    }))]
}
