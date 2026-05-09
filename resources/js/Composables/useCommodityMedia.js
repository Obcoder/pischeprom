import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useCommodityMedia() {
    const media = ref([])

    const loadingMedia = ref(false)
    const uploadingMedia = ref(false)
    const deletingMedia = ref(false)

    async function indexCommodityMedia(commodityId) {
        loadingMedia.value = true

        try {
            const response = await axios.get(
                route('api.commodities.media.index', commodityId)
            )

            media.value = response.data.data || []

            return media.value
        } catch (error) {
            console.error('indexCommodityMedia error:', error)
            throw error
        } finally {
            loadingMedia.value = false
        }
    }

    async function uploadCommodityMedia(commodityId, files) {
        uploadingMedia.value = true

        const formData = new FormData()

        Array.from(files || []).forEach((file) => {
            formData.append('files[]', file)
        })

        try {
            const response = await axios.post(
                route('api.commodities.media.store', commodityId),
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                }
            )

            media.value = response.data.data || []

            return media.value
        } catch (error) {
            console.error('uploadCommodityMedia error:', error)
            throw error
        } finally {
            uploadingMedia.value = false
        }
    }

    async function renameCommodityMedia(commodityId, mediaId, filename) {
        try {
            const response = await axios.patch(
                route('api.commodities.media.rename', {
                    commodity: commodityId,
                    media: mediaId,
                }),
                {
                    filename,
                }
            )

            return response.data.data
        } catch (error) {
            console.error('renameCommodityMedia error:', error)
            throw error
        }
    }

    async function setCommodityAva(commodityId, mediaId) {
        try {
            const response = await axios.patch(
                route('api.commodities.media.ava', {
                    commodity: commodityId,
                    media: mediaId,
                })
            )

            return response.data
        } catch (error) {
            console.error('setCommodityAva error:', error)
            throw error
        }
    }

    async function deleteCommodityMedia(commodityId, mediaId) {
        deletingMedia.value = true

        try {
            await axios.delete(
                route('api.commodities.media.destroy', {
                    commodity: commodityId,
                    media: mediaId,
                })
            )
        } catch (error) {
            console.error('deleteCommodityMedia error:', error)
            throw error
        } finally {
            deletingMedia.value = false
        }
    }

    return {
        media,

        loadingMedia,
        uploadingMedia,
        deletingMedia,

        indexCommodityMedia,
        uploadCommodityMedia,
        renameCommodityMedia,
        setCommodityAva,
        deleteCommodityMedia,
    }
}
