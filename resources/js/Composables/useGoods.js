import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useGoods() {
    const loading = ref(false)
    const saving = ref(false)

    const goods = ref([])
    const products = ref([])
    const vatRates = ref([])
    const totalItems = ref(0)

    const publishLoading = ref({})

    async function indexGoods(params = {}) {
        loading.value = true

        try {
            const { data } = await axios.get(route('goods.index'), {
                params,
            })

            goods.value = Array.isArray(data) ? data : (data.data || [])
            totalItems.value = data.total || 0
        } catch (e) {
            console.error(e)
            goods.value = []
            totalItems.value = 0
            throw e
        } finally {
            loading.value = false
        }
    }

    async function indexProducts() {
        try {
            const { data } = await axios.get(route('products.index'))

            products.value = (Array.isArray(data) ? data : (data.data || []))
                .sort((a, b) => (a.rus || '').localeCompare(b.rus || ''))
        } catch (e) {
            console.error(e)
            products.value = []
            throw e
        }
    }

    async function indexVatRates() {
        try {
            const { data } = await axios.get(route('api.vat-rates'))
            vatRates.value = Array.isArray(data) ? data : (data.data || [])
        } catch (e) {
            console.error(e)
            vatRates.value = []
            throw e
        }
    }

    async function showGood(id) {
        const { data } = await axios.get(route('api.goods.show', id))
        return data
    }

    async function saveGood(form) {
        saving.value = true

        try {
            const hasFile = form.ava_image instanceof File
            const isUpdate = !!form.id

            if (hasFile) {
                const fd = new FormData()

                fd.append('name', form.name ?? '')
                fd.append('denominator', form.denominator ?? '')
                fd.append('description', form.description ?? '')
                fd.append('vat_rate_id', form.vat_rate_id ?? '')
                fd.append('is_published', form.is_published ? '1' : '0')
                fd.append('remove_ava', form.remove_ava ? '1' : '0')

                ;(form.products || []).forEach((id) => {
                    fd.append('products[]', String(id))
                })

                fd.append('ava_image', form.ava_image)

                if (isUpdate) {
                    fd.append('_method', 'PUT')

                    await axios.post(route('goods.update', form.id), fd, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    })
                } else {
                    await axios.post(route('goods.store'), fd, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    })
                }
            } else {
                const payload = {
                    name: form.name,
                    denominator: form.denominator,
                    description: form.description,
                    vat_rate_id: form.vat_rate_id,
                    is_published: form.is_published,
                    products: form.products,
                    remove_ava: form.remove_ava,
                }

                if (isUpdate) {
                    await axios.put(route('goods.update', form.id), payload)
                } else {
                    await axios.post(route('goods.store'), payload)
                }
            }
        } finally {
            saving.value = false
        }
    }

    async function deleteGood(id) {
        await axios.delete(route('api.goods.destroy', id))
    }

    async function toggleGoodPublish(item) {
        if (publishLoading.value[item.id]) return

        publishLoading.value[item.id] = true

        const prev = item.is_published
        item.is_published = !prev

        try {
            const { data } = await axios.patch(route('api.goods.publish', item.id), {
                is_published: item.is_published,
            })

            item.is_published = data.is_published
        } catch (e) {
            console.error(e)
            item.is_published = prev
            throw e
        } finally {
            publishLoading.value[item.id] = false
        }
    }

    return {
        loading,
        saving,
        goods,
        products,
        vatRates,
        totalItems,
        publishLoading,
        indexGoods,
        indexProducts,
        indexVatRates,
        showGood,
        saveGood,
        deleteGood,
        toggleGoodPublish,
    }
}
