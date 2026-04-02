import axios from 'axios'

export function useEntityApi() {
    const endpoint = '/api/entities'

    const getList = async (params = {}) => {
        const { data } = await axios.get(endpoint, { params })
        return data
    }

    const getOne = async (id) => {
        const { data } = await axios.get(`${endpoint}/${id}`)
        return data
    }

    const createOne = async (payload) => {
        const { data } = await axios.post(endpoint, payload)
        return data
    }

    const updateOne = async (id, payload) => {
        const { data } = await axios.put(`${endpoint}/${id}`, payload)
        return data
    }

    const deleteOne = async (id) => {
        const { data } = await axios.delete(`${endpoint}/${id}`)
        return data
    }

    return {
        getList,
        getOne,
        createOne,
        updateOne,
        deleteOne,
    }
}
