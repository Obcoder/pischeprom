import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useUnitPage(initialUnit, initialDictionaries = {}, initialFiles = []) {
    const unit = ref(initialUnit)
    const files = ref(initialFiles)

    const loading = ref({
        unit: false,
        dict: false,
        files: false,
        goods: false,
    })

    const dict = ref({
        buildings: initialDictionaries.buildings ?? [],
        emails: initialDictionaries.emails ?? [],
        entities: initialDictionaries.entities ?? [],
        entityClassifications: initialDictionaries.entityClassifications ?? [],
        goods: initialDictionaries.goods ?? [],
        labels: initialDictionaries.labels ?? [],
        measures: initialDictionaries.measures ?? [],
        products: initialDictionaries.products ?? [],
        telephones: initialDictionaries.telephones ?? [],
        uris: initialDictionaries.uris ?? [],
    })

    async function refreshUnit() {
        loading.value.unit = true
        try {
            const { data } = await axios.get(route('api.units.show', unit.value.id))
            unit.value = data.data ?? data
        } catch (e) {
            console.error('Ошибка обновления unit:', e)
        } finally {
            loading.value.unit = false
        }
    }

    async function loadFiles() {
        loading.value.files = true
        try {
            const { data } = await axios.get(`/api/units/${unit.value.name}/files`)
            files.value = Array.isArray(data) ? data : []
        } catch (e) {
            console.error('Ошибка загрузки файлов:', e)
            files.value = []
        } finally {
            loading.value.files = false
        }
    }

    async function loadDictionaries() {
        loading.value.dict = true
        try {
            const [
                buildingsRes,
                emailsRes,
                entitiesRes,
                classificationsRes,
                goodsRes,
                labelsRes,
                measuresRes,
                productsRes,
                telephonesRes,
                urisRes,
            ] = await Promise.all([
                axios.get(route('buildings.index')),
                axios.get(route('emails.index')),
                axios.get(route('entities.index')),
                axios.get(route('entities-classification.index')),
                axios.get(route('goods.index')),
                axios.get(route('labels.index')),
                axios.get(route('measures.index')),
                axios.get(route('products.index')),
                axios.get(route('telephones.index')),
                axios.get(route('uris.index')),
            ])

            dict.value = {
                buildings: buildingsRes.data ?? [],
                emails: emailsRes.data ?? [],
                entities: entitiesRes.data ?? [],
                entityClassifications: classificationsRes.data ?? [],
                goods: Array.isArray(goodsRes.data) ? goodsRes.data : (goodsRes.data?.data ?? []),
                labels: labelsRes.data ?? [],
                measures: Array.isArray(measuresRes.data) ? measuresRes.data : (measuresRes.data?.data ?? []),
                products: (productsRes.data ?? []).sort((a, b) => a.rus.localeCompare(b.rus)),
                telephones: telephonesRes.data ?? [],
                uris: urisRes.data ?? [],
            }
        } catch (e) {
            console.error('Ошибка загрузки справочников:', e)
        } finally {
            loading.value.dict = false
        }
    }

    async function searchGoods(search = '') {
        loading.value.goods = true
        try {
            const { data } = await axios.get(route('goods.index'), {
                params: { search }
            })

            dict.value.goods = Array.isArray(data)
                ? data
                : (data.data ?? [])
        } catch (e) {
            console.error('Ошибка поиска goods:', e)
        } finally {
            loading.value.goods = false
        }
    }

    return {
        unit,
        files,
        dict,
        loading,
        refreshUnit,
        loadFiles,
        loadDictionaries,
        searchGoods,
    }
}
