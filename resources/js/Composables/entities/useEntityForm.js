import { reactive } from 'vue'

export function useEntityForm() {
    const initialState = () => ({
        id: null,
        name: '',
        full_name: '',
        entity_classification_id: null,
        INN: '',
        KPP: '',
        OGRN: '',
        legal_address: '',
        country_id: null,
        buildings: [],
        cities: [],
        emails: [],
        telephones: [],
        units: [],
        chats: [],
    })

    const form = reactive(initialState())

    const resetForm = () => {
        Object.assign(form, initialState())
    }

    const fillForm = (entity) => {
        form.id = entity.id ?? null
        form.name = entity.name ?? ''
        form.full_name = entity.full_name ?? ''
        form.entity_classification_id = entity.entity_classification_id ?? null
        form.INN = entity.INN ?? ''
        form.KPP = entity.KPP ?? ''
        form.OGRN = entity.OGRN ?? ''
        form.legal_address = entity.legal_address ?? ''
        form.country_id = entity.country_id ?? null
        form.buildings = entity.buildings?.map(x => x.id) ?? []
        form.cities = entity.cities?.map(x => x.id) ?? []
        form.emails = entity.emails?.map(x => x.id) ?? []
        form.telephones = entity.telephones?.map(x => x.id) ?? []
        form.units = entity.units?.map(x => x.id) ?? []
        form.chats = entity.chats?.map(x => x.id) ?? []
    }

    const toPayload = () => ({
        name: form.name,
        full_name: form.full_name,
        entity_classification_id: form.entity_classification_id,
        INN: form.INN,
        KPP: form.KPP,
        OGRN: form.OGRN,
        legal_address: form.legal_address,
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
        resetForm,
        fillForm,
        toPayload,
    }
}
