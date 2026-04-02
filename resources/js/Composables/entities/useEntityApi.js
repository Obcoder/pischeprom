import axios from 'axios'

export function useEntityApi() {
    const getMeta = async () => {
        const { data } = await axios.get('/api/entities-meta')
        return data
    }

    const getList = async (params = {}) => {
        const { data } = await axios.get('/api/entities', { params })
        return data
    }

    const getOne = async (id) => {
        const { data } = await axios.get(`/api/entities/${id}`)
        return data.data ?? data
    }

    const createOne = async (payload) => {
        const { data } = await axios.post('/api/entities', payload)
        return data.data ?? data
    }

    const updateOne = async (id, payload) => {
        const { data } = await axios.put(`/api/entities/${id}`, payload)
        return data.data ?? data
    }

    const deleteOne = async (id) => {
        const { data } = await axios.delete(`/api/entities/${id}`)
        return data
    }

    return {
        getMeta,
        getList,
        getOne,
        createOne,
        updateOne,
        deleteOne,
    }
}
