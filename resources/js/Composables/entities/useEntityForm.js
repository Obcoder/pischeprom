import { reactive } from 'vue'

export function useEntityForm() {
    const initialState = () => ({
        id: null,
        name: '',
        entity_classification_id: null,
        INN: '',
        OGRN: '',
        country_id: null,
        buildings: [],
        cities: [],
        emails: [],
        telephones: [],
        units: [],
        chats: [],
    })

    const form = reactive(initialState())

    const fillForm = (entity) => {
        form.id = entity.id ?? null
        form.name = entity.name ?? ''
        form.entity_classification_id = entity.entity_classification_id ?? null
        form.INN = entity.INN ?? ''
        form.OGRN = entity.OGRN ?? ''
        form.country_id = entity.country_id ?? null
        form.buildings = entity.buildings?.map(i => i.id) ?? []
        form.cities = entity.cities?.map(i => i.id) ?? []
        form.emails = entity.emails?.map(i => i.id) ?? []
        form.telephones = entity.telephones?.map(i => i.id) ?? []
        form.units = entity.units?.map(i => i.id) ?? []
        form.chats = entity.chats?.map(i => i.id) ?? []
    }

    const resetForm = () => {
        Object.assign(form, initialState())
    }

    const toPayload = () => ({
        name: form.name,
        entity_classification_id: form.entity_classification_id,
        INN: form.INN,
        OGRN: form.OGRN,
        country_id: form.country_id,
        buildings: form.buildings,
        cities: form.cities,
        emails: form.emails,
        telephones: form.telephones,
        units: form.units,
        chats: form.chats,
    })

    return {
        form,
        fillForm,
        resetForm,
        toPayload,
    }
}
