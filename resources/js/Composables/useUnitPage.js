import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

function asArray(payload) {
    if (Array.isArray(payload)) return payload
    if (Array.isArray(payload?.data)) return payload.data
    return []
}

function sortByString(items, key) {
    return [...items].sort((a, b) => String(a?.[key] ?? '').localeCompare(String(b?.[key] ?? '')))
}

export function useUnitPage(initialUnit, initialDictionaries = {}, initialFiles = []) {
    const unit = ref(initialUnit)
    const files = ref(Array.isArray(initialFiles) ? initialFiles : [])

    const loading = ref({
        unit: false,
        dict: false,
        files: false,
        goods: false,
    })

    const dict = ref({
        buildings: asArray(initialDictionaries.buildings),
        cities: asArray(initialDictionaries.cities),
        emails: asArray(initialDictionaries.emails),
        entities: asArray(initialDictionaries.entities),
        entityClassifications: asArray(initialDictionaries.entityClassifications),
        fields: asArray(initialDictionaries.fields),
        goods: asArray(initialDictionaries.goods),
        labels: asArray(initialDictionaries.labels),
        measures: asArray(initialDictionaries.measures),
        products: asArray(initialDictionaries.products),
        telephones: asArray(initialDictionaries.telephones),
        uris: asArray(initialDictionaries.uris),
    })

    async function refreshUnit() {
        if (!unit.value?.id) return

        loading.value.unit = true
        try {
            const { data } = await axios.get(route('api.units.show', unit.value.id))
            unit.value = data?.data ?? data
        } catch (e) {
            console.error('Ошибка обновления unit:', e)
        } finally {
            loading.value.unit = false
        }
    }

    async function loadFiles() {
        if (!unit.value?.name) {
            files.value = []
            return
        }

        loading.value.files = true
        try {
            const { data } = await axios.get(`/api/units/${unit.value.name}/files`)
            files.value = asArray(data)
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
                citiesRes,
                emailsRes,
                entitiesRes,
                classificationsRes,
                fieldsRes,
                goodsRes,
                labelsRes,
                measuresRes,
                productsRes,
                telephonesRes,
                urisRes,
            ] = await Promise.all([
                axios.get(route('buildings.index')),
                axios.get(route('cities.index')),
                axios.get(route('emails.index')),
                axios.get(route('entities.index')),
                axios.get(route('entities-classification.index')),
                axios.get(route('fields.index')),
                axios.get(route('goods.index')),
                axios.get(route('labels.index')),
                axios.get(route('measures.index')),
                axios.get(route('products.index')),
                axios.get(route('telephones.index')),
                axios.get(route('uris.index')),
            ])

            dict.value = {
                buildings: asArray(buildingsRes.data),
                cities: sortByString(asArray(citiesRes.data), 'name'),
                emails: sortByString(asArray(emailsRes.data), 'address'),
                entities: asArray(entitiesRes.data),
                entityClassifications: asArray(classificationsRes.data),
                fields: sortByString(asArray(fieldsRes.data), 'name'),
                goods: asArray(goodsRes.data),
                labels: sortByString(asArray(labelsRes.data), 'name'),
                measures: asArray(measuresRes.data),
                products: sortByString(asArray(productsRes.data), 'rus'),
                telephones: sortByString(asArray(telephonesRes.data), 'number'),
                uris: sortByString(asArray(urisRes.data), 'address'),
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
                params: { search },
            })

            dict.value.goods = asArray(data)
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
