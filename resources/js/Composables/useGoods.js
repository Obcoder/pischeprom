import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { goodTradeCodeValues } from '@/utils/goodTradeCodes'

export function useGoods() {
    const loading = ref(false)
    const saving = ref(false)

    const goods = ref([])
    const industries = ref([])
    const entityClassifications = ref([])
    const categories = ref([])
    const products = ref([])
    const countries = ref([])
    const fields = ref([])
    const vatRates = ref([])
    const measures = ref([])
    const totalItems = ref(0)

    const publishLoading = ref({})
    let goodsController = null

    async function indexGoods(params = {}) {
        goodsController?.abort()
        const controller = new AbortController()
        goodsController = controller
        loading.value = true

        try {
            const { data } = await axios.get(route('goods.index'), {
                params,
                signal: controller.signal,
            })

            if (goodsController !== controller || controller.signal.aborted) return
            goods.value = Array.isArray(data) ? data : (data.data || [])
            totalItems.value = Array.isArray(data) ? data.length : (data.total || 0)
        } catch (e) {
            if (goodsController !== controller || controller.signal.aborted || axios.isCancel(e)) return
            console.error(e)
            goods.value = []
            totalItems.value = 0
            throw e
        } finally {
            if (goodsController === controller) {
                loading.value = false
                goodsController = null
            }
        }
    }

    function cancelGoodsRequest() {
        goodsController?.abort()
        goodsController = null
        loading.value = false
    }

    async function indexDictionaries() {
        const { data } = await axios.get(route('goods.index'), { params: { view: 'filters' } })
        industries.value = data.industries || []
        entityClassifications.value = data.entity_classifications || []
        categories.value = data.categories || []
        products.value = data.products || []
        countries.value = data.countries || []
        fields.value = data.fields || []
        vatRates.value = data.vat_rates || []
        measures.value = data.measures || []
    }

    async function saveGood(form) {
        saving.value = true

        try {
            const hasFile = form.ava_image instanceof File
            const isUpdate = !!form.id

            if (hasFile) {
                const fd = new FormData()

                fd.append('name', form.name ?? '')
                fd.append('incoming_code', form.incoming_code ?? '')
                fd.append('denominator', form.denominator ?? '')
                fd.append('measure_id', form.measure_id ?? '')
                fd.append('unit_weight_kg', form.unit_weight_kg ?? '')
                fd.append('description', form.description ?? '')
                fd.append('vat_rate_id', form.vat_rate_id ?? '')
                fd.append('country_id', form.country_id ?? '')
                fd.append('is_published', form.is_published ? '1' : '0')
                fd.append('remove_ava', form.remove_ava ? '1' : '0')
                for (const [key, value] of Object.entries(goodTradeCodeValues(form))) fd.append(key, value ?? '')
                // Explicit empty values let the API clear existing relations on multipart updates.
                if (!form.products?.length) fd.append('products', '')
                if (!form.fields?.length) fd.append('fields', '')

                ;(form.products || []).forEach((id) => {
                    fd.append('products[]', String(id))
                })

                ;(form.fields || []).forEach((id) => {
                    fd.append('fields[]', String(id))
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
                    ...goodTradeCodeValues(form),
                    avatar_source_url: form.avatar_source_url || null,
                    avatar_thumb_source_url: form.avatar_thumb_source_url || null,
                    name: form.name,
                    incoming_code: form.incoming_code ?? null,
                    denominator: form.denominator,
                    measure_id: form.measure_id ?? null,
                    unit_weight_kg: form.unit_weight_kg ?? null,
                    description: form.description,
                    vat_rate_id: form.vat_rate_id,
                    country_id: form.country_id,
                    is_published: form.is_published,
                    products: form.products,
                    fields: form.fields,
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
        await axios.delete(route('goods.destroy', id))
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
        industries,
        entityClassifications,
        categories,
        products,
        countries,
        fields,
        vatRates,
        measures,
        totalItems,
        publishLoading,
        indexGoods,
        indexDictionaries,
        saveGood,
        deleteGood,
        toggleGoodPublish,
        cancelGoodsRequest,
    }
}
