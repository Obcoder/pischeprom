import axios from 'axios'
import { route } from 'ziggy-js'

export function useUnitRelations(refreshUnit) {
    async function attachUri(unitId, uriId) {
        await axios.post(route('api.units.uris.attach', unitId), { uri_id: uriId })
        await refreshUnit()
    }

    async function detachUri(unitId, uriId) {
        await axios.delete(route('api.units.uris.detach', { unit: unitId, uri: uriId }))
        await refreshUnit()
    }

    async function attachTelephone(unitId, telephoneId) {
        await axios.post(route('api.units.telephones.attach', unitId), { telephone_id: telephoneId })
        await refreshUnit()
    }

    async function detachTelephone(unitId, telephoneId) {
        await axios.delete(route('api.units.telephones.detach', { unit: unitId, telephone: telephoneId }))
        await refreshUnit()
    }

    async function attachBuilding(unitId, buildingId) {
        await axios.post(route('api.units.buildings.attach', unitId), { building_id: buildingId })
        await refreshUnit()
    }

    async function detachBuilding(unitId, buildingId) {
        await axios.delete(route('api.units.buildings.detach', { unit: unitId, building: buildingId }))
        await refreshUnit()
    }

    async function attachLabel(unitId, labelId) {
        await axios.post(route('api.units.labels.attach', unitId), { label_id: labelId })
        await refreshUnit()
    }

    async function detachLabel(unitId, labelId) {
        await axios.delete(route('api.units.labels.detach', { unit: unitId, label: labelId }))
        await refreshUnit()
    }

    async function attachField(unitId, fieldId) {
        await axios.post(route('api.units.fields.attach', unitId), { field_id: fieldId })
        await refreshUnit()
    }

    async function detachField(unitId, fieldId) {
        await axios.delete(route('api.units.fields.detach', { unit: unitId, field: fieldId }))
        await refreshUnit()
    }

    async function attachEntity(unitId, entityId) {
        await axios.post(route('api.units.entities.attach', unitId), { entity_id: entityId })
        await refreshUnit()
    }

    async function detachEntity(unitId, entityId) {
        await axios.delete(route('api.units.entities.detach', { unit: unitId, entity: entityId }))
        await refreshUnit()
    }

    return {
        attachUri,
        detachUri,
        attachTelephone,
        detachTelephone,
        attachBuilding,
        detachBuilding,
        attachLabel,
        detachLabel,
        attachField,
        detachField,
        attachEntity,
        detachEntity,
    }
}
