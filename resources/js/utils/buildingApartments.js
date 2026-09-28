export function apartmentLabel(apartment) {
    if (!apartment) return ''
    return apartment.label || `${{ apartment: 'кв.', office: 'офис', premise: 'пом.' }[apartment.type] || 'кв.'} ${apartment.number}`
}

export function selectedApartment(building) {
    const id = building?.apartment_id ?? building?.pivot?.apartment_id
    return building?.apartment || building?.apartments?.find(apartment => Number(apartment.id) === Number(id)) || null
}

export function buildingApartmentLabel(building) {
    return apartmentLabel(selectedApartment(building))
}

export function selectedBuildingApartments(buildingIds, apartments = {}) {
    return Object.fromEntries((buildingIds || []).map(id => [id, apartments[id] || null]))
}
