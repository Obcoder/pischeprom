const string = value => String(value ?? '').trim()
const sameId = (first, second) => String(first ?? '') === String(second ?? '')
const isDomain = (node, levels) => levels.some(level => level.is_domain && sameId(level.id, node?.level_id))
const plainText = value => string(value).replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()

function originOf(value) {
    try {
        const url = new URL(value)
        return ['https:', 'http:'].includes(url.protocol) ? url.origin : ''
    } catch { return '' }
}

// Do not approximate Laravel's transliteration of automatic or non-Latin slugs.
// The saved snapshot supplies the authoritative URL in those cases.
function segmentFor(node) {
    const slug = string(node?.slug)
    return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) ? slug : null
}

function previewAddress(form, record, nodes, levels, baseUrl) {
    const unchanged = record && sameId(form.parent_id, record.parent_id)
        && sameId(form.level_id, record.level_id) && string(form.slug) === string(record.slug)
        && JSON.stringify(form.domain_hosts || []) === JSON.stringify(record.domain_hosts || [])
        && (string(form.slug) || string(form.name) === string(record.name))
    if (unchanged && /^https?:\/\//.test(record.public_url || '') && originOf(record.public_url)) {
        return { url: record.public_url, urlHint: '' }
    }

    const byId = new Map(nodes.map(node => [String(node.id), node]))
    const parent = form.parent_id == null ? null : byId.get(String(form.parent_id))
    let domain = isDomain(form, levels) ? form : null
    let ancestor = parent
    const visited = new Set()
    while (!domain && ancestor && !visited.has(String(ancestor.id))) {
        visited.add(String(ancestor.id))
        if (isDomain(ancestor, levels)) domain = ancestor
        ancestor = byId.get(String(ancestor.parent_id))
    }
    const currentOrigin = originOf(baseUrl)
    let origin = currentOrigin
    if (domain) {
        const hosts = (domain.domain_hosts || []).map(string).filter(Boolean)
        const knownOrigin = originOf(domain.public_url || (isDomain(form, levels) ? record?.public_url : ''))
        const candidate = currentOrigin || knownOrigin
        const currentHost = candidate ? new URL(candidate).hostname : ''
        if (hosts.includes(currentHost) || !hosts.length) origin = candidate
        else {
            const scheme = (knownOrigin || currentOrigin).startsWith('http:') ? 'http:' : 'https:'
            const hostOrigin = originOf(`${scheme}//${hosts[0]}`)
            // Invalid domain drafts must not become a seemingly valid preview address.
            if (!hostOrigin || new URL(hostOrigin).host !== hosts[0]) return { url: '', urlHint: 'Адрес появится после сохранения корректного домена.' }
            origin = hostOrigin
        }
    }
    if (isDomain(form, levels)) return { url: `${origin}/g`, urlHint: '' }

    const segment = segmentFor(form)
    if (!segment) return {
        url: '',
        urlHint: string(form.slug) ? 'Точный адрес будет сформирован после сохранения.' : 'Адрес сформируется из названия после сохранения.',
    }
    if (form.parent_id != null && (!parent || (!isDomain(parent, levels) && !parent.catalog_path))) {
        return { url: '', urlHint: 'Адрес появится после сохранения расположения в каталоге.' }
    }
    const prefix = parent && !isDomain(parent, levels) ? `${parent.catalog_path}/` : ''
    const collision = nodes.some(node => !sameId(node.id, record?.id)
        && sameId(node.parent_id, form.parent_id) && !isDomain(node, levels)
        && segmentFor(node) === segment)
    const needsId = collision || (!prefix && /^\d+$/.test(segment))
    if (needsId && record?.id == null) return { url: '', urlHint: 'Точный адрес с уникальным суффиксом появится после сохранения.' }
    return {
        url: `${origin}/catalog/${prefix}${segment}${needsId ? `-node-${record.id}` : ''}`,
        urlHint: 'Адрес по текущему расположению и slug; изменения вступят в силу после сохранения.',
    }
}

export function catalogSeoPreview(form, record = null, nodes = [], levels = [], baseUrl = '') {
    const draft = { ...record, ...form }
    const name = string(draft.name)
    const title = string(draft.meta_title) || (name ? `${name} — ПИЩЕПРОМ-СЕРВЕР` : 'Название страницы — ПИЩЕПРОМ-СЕРВЕР')
    const description = string(draft.meta_description)
        || Array.from(plainText(draft.description || name)).slice(0, 240).join('')
    return {
        title,
        description,
        ...previewAddress(draft, record, nodes, levels, baseUrl),
    }
}
