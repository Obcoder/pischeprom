import { reactive } from 'vue'

export function useEntityFilters() {
    const filters = reactive({
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

    const resetFilters = () => {
        filters.search = ''
        filters.entity_classification_ids = []
        filters.country_ids = []
        filters.city_ids = []
        filters.building_ids = []
        filters.email_ids = []
        filters.telephone_ids = []
        filters.unit_ids = []
        filters.chat_ids = []
    }

    return {
        filters,
        resetFilters,
    }
}
