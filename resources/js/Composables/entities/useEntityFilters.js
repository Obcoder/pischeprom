import { reactive } from 'vue'

export function useEntityFilters() {
    const initialState = () => ({
        search: '',
        entity_classification_ids: [],
        country_ids: [],
        region_ids: [],
        city_ids: [],
        building_ids: [],
        email_ids: [],
        telephone_ids: [],
        unit_ids: [],
        chat_ids: [],
        has_sales: null,
        has_orders: null,
        has_avito_chats: null,
        has_unread_avito: null,
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
