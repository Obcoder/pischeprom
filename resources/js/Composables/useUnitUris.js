import axios from 'axios'
import { route } from 'ziggy-js'

export function useUnitUris(refreshUnit) {

    async function attachUri(unitId, uriId) {
        await axios.post(route('api.units.uris.attach', unitId), {
            uri_id: uriId,
        })

        await refreshUnit()
    }

    async function detachUri(unitId, uriId) {
        await axios.delete(route('api.units.uris.detach', {
            unit: unitId,
            uri: uriId,
        }))

        await refreshUnit()
    }

    async function createUri(address) {
        const { data } = await axios.post(route('api.uris.store'), {
            address,
        })

        return data.uri
    }

    async function updateUri(uriId, address) {
        await axios.put(route('api.uris.update', uriId), {
            address,
        })
    }

    async function deleteUri(uriId) {
        await axios.delete(route('api.uris.destroy', uriId))
    }

    return {
        attachUri,
        detachUri,
        createUri,
        updateUri,
        deleteUri,
    }
}
