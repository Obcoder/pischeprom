import { reactive } from 'vue'

export function useEntityFilters() {
    const initialState = () => ({
        search: '',
        entity_classification_ids: [],
        country_ids: [],
        city_ids: [],
        building_ids: [],
        email_ids: [],
        telephone_ids: [],
        unit_ids: [],
        chat_ids: [],
    })

    const filters = reactive(initialState())

    const resetFilters = () => {
        Object.assign(filters, initialState())
    }

    return {
        filters,
        resetFilters,
    }
}
