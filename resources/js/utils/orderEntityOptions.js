const textValue = value => typeof value === 'string' || typeof value === 'number' ? String(value) : ''

export function entityRequisites(entity) {
    return [entity?.INN && `ИНН ${entity.INN}`, entity?.KPP && `КПП ${entity.KPP}`, entity?.OGRN && `ОГРН ${entity.OGRN}`].filter(Boolean)
}

export function entityContacts(entity) {
    return [
        ...(entity?.telephones || []).map(phone => textValue(phone.number ?? phone)),
        ...(entity?.emails || []).map(email => textValue(email.address ?? email)),
    ].filter(Boolean)
}

export function entityMatches(entity, query) {
    const terms = String(query || '').trim().toLocaleLowerCase('ru').split(/\s+/).filter(Boolean)
    if (!terms.length) return true
    const haystack = [entity?.id, entity?.name, entity?.full_name, entity?.classification?.name,
        entity?.legal_address, ...entityRequisites(entity), ...entityContacts(entity)].join(' ').toLocaleLowerCase('ru')
    const phoneQuery = String(query || '').replace(/\D/g, '')
    if (/^[+\d\s()\-]+$/.test(query) && phoneQuery.length >= 3
        && entityContacts(entity).some(value => value.replace(/\D/g, '').includes(phoneQuery))) return true
    return terms.every(term => haystack.includes(term))
}
