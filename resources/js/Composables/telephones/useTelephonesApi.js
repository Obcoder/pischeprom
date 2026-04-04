import axios from 'axios'

const BASE_URL = '/api/telephones'

export function useTelephonesApi() {
    const getMeta = async () => {
        const { data } = await axios.get(`${BASE_URL}/meta`)
        return data
    }

    const getList = async (params) => {
        const { data } = await axios.get(BASE_URL, { params })
        return data
    }

    const createItem = async (payload) => {
        const { data } = await axios.post(BASE_URL, payload)
        return data
    }

    const updateItem = async (id, payload) => {
        const { data } = await axios.put(`${BASE_URL}/${id}`, payload)
        return data
    }

    const getItem = async (id) => {
        const { data } = await axios.get(`${BASE_URL}/${id}`)
        return data
    }

    return {
        getMeta,
        getList,
        createItem,
        updateItem,
        getItem,
    }
}
