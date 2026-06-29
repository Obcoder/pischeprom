import axios from 'axios'

export function useGisApi() {
    const getEntities = async (params = {}) => {
        const { data } = await axios.get('/api/gis/entities', { params })
        return data
    }

    const getEntitiesWithoutLocation = async (params = {}) => {
        const { data } = await axios.get('/api/gis/entities/no-location', { params })
        return data
    }

    const getLocation = async (entityId) => {
        const { data } = await axios.get(`/api/gis/entities/${entityId}/location`)
        return data
    }

    const saveLocation = async (entityId, payload) => {
        const { data } = await axios.put(`/api/gis/entities/${entityId}/location`, payload)
        return data
    }

    const geocodeEntity = async (entityId, payload) => {
        const { data } = await axios.post(`/api/gis/entities/${entityId}/geocode`, payload)
        return data
    }

    const previewRoute = async (payload) => {
        const { data } = await axios.post('/api/gis/routes/preview', payload)
        return data
    }

    const saveRouteDraft = async (payload) => {
        const { data } = await axios.post('/api/gis/routes/drafts', payload)
        return data
    }

    const getRouteDraft = async (draftId) => {
        const { data } = await axios.get(`/api/gis/routes/drafts/${draftId}`)
        return data
    }

    const deleteRouteDraft = async (draftId) => {
        const { data } = await axios.delete(`/api/gis/routes/drafts/${draftId}`)
        return data
    }

    return {
        getEntities,
        getEntitiesWithoutLocation,
        getLocation,
        saveLocation,
        geocodeEntity,
        previewRoute,
        saveRouteDraft,
        getRouteDraft,
        deleteRouteDraft,
    }
}
