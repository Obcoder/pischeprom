import { selectedBuildingApartments } from './buildingApartments.js'

export function emptyUnitForm() {
    return {
        name: '', is_customer: false, is_supplier: false,
        entities: [], emails: [], telephones: [], uris: [],
        cities: [], buildings: [], building_apartments: {},
        fields: [], labels: [], industries: [], classifications: [], primary_industry_id: null,
    }
}

export function unitRelationIds(items = []) {
    return [...new Set(items.map(item => Number(item?.id ?? item)).filter(id => Number.isInteger(id) && id > 0))]
}

export function unitCreationPayload(form) {
    const payload = {
        name: form.name.trim(),
        is_customer: Boolean(form.is_customer),
        is_supplier: Boolean(form.is_supplier),
    }
    for (const key of ['entities', 'cities', 'buildings', 'fields', 'labels', 'industries', 'classifications']) {
        payload[key] = unitRelationIds(form[key])
    }
    for (const key of ['emails', 'telephones', 'uris']) {
        payload[key] = unitRelationIds(form[key].filter(item => typeof item === 'object'))
        payload[`new_${key}`] = [...new Set(form[key].filter(item => typeof item === 'string').map(item => item.trim()).filter(Boolean))]
    }
    payload.building_apartments = selectedBuildingApartments(payload.buildings, form.building_apartments)
    payload.primary_industry_id = payload.industries.includes(Number(form.primary_industry_id))
        ? Number(form.primary_industry_id) : null
    return payload
}

export function unitCreationErrorSection(key) {
    if (/^(new_)?(emails|telephones|uris)(\.|$)/.test(key)) return 'contacts'
    if (/^(cities|buildings|building_apartments)(\.|$)/.test(key)) return 'geography'
    if (/^(fields|labels|industries|classifications|primary_industry_id)(\.|$)/.test(key)) return 'classification'
    return 'profile'
}
