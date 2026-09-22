export const communicationTypes = [
    { key: 'uris', title: 'Сайты', singular: 'Сайт', field: 'address', icon: 'mdi-web', placeholder: 'https://example.ru' },
    { key: 'telephones', title: 'Телефоны', singular: 'Телефон', field: 'number', icon: 'mdi-phone-outline', placeholder: '+7 900 000-00-00' },
    { key: 'emails', title: 'Почта', singular: 'Email', field: 'address', icon: 'mdi-email-outline', placeholder: 'mail@example.ru' },
]

export function contactKey(type, value) {
    const text = String(value || '').trim()
    if (type === 'telephones') {
        const digits = text.replace(/\D/g, '')
        if (digits.length === 10) return `7${digits}`
        return digits.length === 11 && digits.startsWith('8') ? `7${digits.slice(1)}` : digits
    }
    if (type === 'emails') return text.toLowerCase()
    const href = websiteHref(text)
    if (!href) return text.toLowerCase()
    const url = new URL(href)
    return `${url.host.toLowerCase().replace(/^www\./, '')}${url.pathname.replace(/\/$/, '')}${url.search}${url.hash}`
}

export function websiteHref(value) {
    const text = String(value || '').trim()
    if (!text || /[\s\\]/.test(text) || /^(?:\/|[a-z][a-z\d+.-]*:(?!\/\/))/i.test(text)) return null
    if (/^[a-z][a-z\d+.-]*:\/\//i.test(text) && !/^https?:\/\//i.test(text)) return null
    try {
        const url = new URL(/^https?:\/\//i.test(text) ? text : `https://${text}`)
        if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password) return null
        return url.href
    } catch {
        return null
    }
}

export function collectUnitCommunications(unit = {}) {
    return Object.fromEntries(communicationTypes.map((type) => {
        const records = new Map()
        const add = (contact, owner) => {
            const value = String(contact?.[type.field] || '').trim()
            if (!value || !contact?.id) return
            const key = contactKey(type.key, value)
            if (!records.has(key)) records.set(key, { ...contact, key, value, sources: [] })
            const record = records.get(key)
            if (!record.sources.some((source) => source.id === contact.id && source.owner.type === owner.type && source.owner.id === owner.id)) {
                record.sources.push({ id: contact.id, owner })
            }
        }
        for (const contact of unit[type.key] || []) add(contact, { type: 'unit', id: unit.id, name: 'Unit' })
        for (const entity of unit.entities || []) {
            for (const contact of entity[type.key] || []) add(contact, { type: 'entity', id: entity.id, name: entity.name || `Entity #${entity.id}` })
        }
        return [type.key, [...records.values()]]
    }))
}
