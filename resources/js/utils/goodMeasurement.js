// Quantity and prices always refer to the product's configured accounting unit.
// Packaging (denominator) is descriptive and must never determine this factor.
export function measurementForGood(good) {
    return good?.measurement || { measure_id: good?.measure_id ?? null, unit_label: good?.measure?.name ?? null, kilograms_per_unit: null }
}

export function lineMeasurement(item, good) {
    if (item?._original_good_id != null && String(item.good_id) === String(item._original_good_id) && item.measurement) {
        return item.measurement
    }
    return measurementForGood(good)
}

export function quantityWeight(quantity, measurement) {
    const factor = Number(measurement?.kilograms_per_unit)
    const amount = Number(quantity)
    return Number.isFinite(amount) && Number.isFinite(factor) && factor > 0 ? amount * factor : null
}

export function unitLabel(measurement) {
    return measurement?.unit_label || 'единица не задана'
}

export function massUnitFactor(name) {
    const key = String(name || '').trim().toLowerCase().replace(/\.$/, '')
    if (['кг', 'kg', 'килограмм', 'килограммы', 'kilogram', 'kilograms'].includes(key)) return 1
    if (['г', 'g', 'гр', 'грамм', 'граммы', 'gram', 'grams'].includes(key)) return 0.001
    if (['т', 't', 'тн', 'тонна', 'тонны', 'тонн', 'ton', 'tons', 'tonne', 'tonnes'].includes(key)) return 1000
    return null
}
