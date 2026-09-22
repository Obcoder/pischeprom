<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'

const props = defineProps({
    unit: { type: Object, required: true },
    dict: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['refresh'])
const showAttachForm = ref(false)
const productSearch = ref('')
const productSearchResults = ref([])
const productSearchLoading = ref(false)
const saving = ref(false)
const deletingProductId = ref(null)
const errors = ref({})
const feedback = ref('')
const categoryFilter = ref(null)
const form = reactive({ product_id: null })
let searchRequest = 0

const manufactures = computed(() => [...new Map((props.unit?.manufactures || [])
    .filter((product) => product?.id)
    .map((product) => [product.id, product])).values()]
    .sort((a, b) => productTitle(a).localeCompare(productTitle(b), 'ru')))
const categories = computed(() => [...new Map(manufactures.value
    .filter((product) => product.category?.id)
    .map((product) => [product.category.id, product.category])).values()])
const filteredManufactures = computed(() => manufactures.value.filter((product) => !categoryFilter.value || product.category?.id === categoryFilter.value))
const availableProducts = computed(() => {
    const existing = new Set(manufactures.value.map((product) => product.id))
    const source = productSearch.value.trim() ? productSearchResults.value : (props.dict.products || [])
    return [...new Map(source.filter((product) => product?.id && !existing.has(product.id))
        .map((product) => [product.id, { ...product, searchTitle: [productTitle(product), product.category?.name].filter(Boolean).join(' · ') }])).values()]
})

const debouncedSearch = useDebounceFn(searchProducts, 300)
watch(productSearch, (value) => {
    const requestId = ++searchRequest
    if (!String(value || '').trim()) {
        productSearchResults.value = []
        productSearchLoading.value = false
        return
    }
    debouncedSearch(value, requestId)
})
watch(() => props.unit.id, () => {
    searchRequest++
    showAttachForm.value = false
    categoryFilter.value = null
    productSearch.value = ''
    form.product_id = null
    feedback.value = ''
    errors.value = {}
})

function productTitle(product) {
    return product?.rus || product?.name || product?.eng || `Product #${product?.id ?? '—'}`
}

async function searchProducts(value, requestId) {
    if (requestId !== searchRequest) return
    productSearchLoading.value = true
    try {
        const { data } = await axios.get(route('products.index'), { params: { search: String(value).trim() } })
        if (requestId === searchRequest) {
            productSearchResults.value = Array.isArray(data) ? data : (data.data || [])
        }
    } catch (error) {
        if (requestId === searchRequest) feedback.value = 'Не удалось найти продукты. Повторите поиск.'
    } finally {
        if (requestId === searchRequest) productSearchLoading.value = false
    }
}

async function attachManufacture() {
    if (!form.product_id || saving.value) return
    saving.value = true
    errors.value = {}
    feedback.value = ''
    try {
        await axios.post(route('api.units.manufactures.attach', { unit: props.unit.id }), { product_id: form.product_id })
        form.product_id = null
        productSearch.value = ''
        showAttachForm.value = false
        emit('refresh')
    } catch (error) {
        errors.value = error.response?.data?.errors || {}
        feedback.value = error.response?.data?.message || 'Не удалось добавить продукт.'
    } finally {
        saving.value = false
    }
}

async function detachManufacture(product) {
    if (!product?.id || deletingProductId.value) return
    if (!window.confirm(`Убрать «${productTitle(product)}» из производимых продуктов?`)) return
    deletingProductId.value = product.id
    feedback.value = ''
    try {
        await axios.delete(route('api.units.manufactures.detach', { unit: props.unit.id, product: product.id }))
        emit('refresh')
    } catch (error) {
        feedback.value = error.response?.data?.message || 'Не удалось убрать продукт.'
    } finally {
        deletingProductId.value = null
    }
}
</script>

<template>
    <div class="unit-manufactures">
        <div class="unit-manufactures__toolbar">
            <span>Продукция Unit · потенциал для наших закупок</span>
            <button type="button" class="unit-manufactures__button" :aria-expanded="showAttachForm" @click="showAttachForm = !showAttachForm">
                <v-icon :icon="showAttachForm ? 'mdi-close' : 'mdi-plus'" size="15" />
                {{ showAttachForm ? 'Закрыть' : 'Добавить продукт' }}
            </button>
        </div>
        <p v-if="feedback" role="alert" class="unit-manufactures__error">{{ feedback }}</p>
        <form v-if="showAttachForm" class="unit-manufactures__form" @submit.prevent="attachManufacture">
            <v-autocomplete
                v-model="form.product_id"
                v-model:search="productSearch"
                :items="availableProducts"
                item-title="searchTitle"
                item-value="id"
                label="Продукт"
                placeholder="Название или категория"
                variant="outlined"
                density="compact"
                clearable
                no-filter
                hide-details="auto"
                :loading="productSearchLoading"
                :error-messages="errors.product_id || []"
                no-data-text="Продукты не найдены"
            />
            <v-btn type="submit" color="#352345" variant="flat" rounded="0" :disabled="!form.product_id" :loading="saving">Добавить</v-btn>
        </form>
        <v-select
            v-if="categories.length > 1"
            v-model="categoryFilter"
            :items="categories"
            item-title="name"
            item-value="id"
            label="Категория"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            class="unit-manufactures__filter"
        />
        <div v-if="filteredManufactures.length" class="unit-manufactures__table-scroll">
            <table class="unit-manufactures__table">
                <thead><tr><th>Продукт</th><th>Категория</th><th><span class="sr-only">Действия</span></th></tr></thead>
                <tbody>
                    <tr v-for="product in filteredManufactures" :key="product.id">
                        <td><Link :href="route('product.show', product.id)">{{ productTitle(product) }}</Link></td>
                        <td><Link v-if="product.category?.id" :href="route('category.show', product.category.id)" class="unit-manufactures__category">{{ product.category.name }}</Link><span v-else>—</span></td>
                        <td class="unit-manufactures__controls">
                            <button type="button" :disabled="deletingProductId === product.id" :aria-label="`Убрать ${productTitle(product)}`" title="Убрать из производимых" @click="detachManufacture(product)"><v-icon icon="mdi-link-off" size="17" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="unit-manufactures__empty">{{ categoryFilter ? 'В этой категории нет продуктов.' : 'Производимые продукты пока не добавлены.' }}</p>
    </div>
</template>

<style scoped>
.unit-manufactures { color: #222; font-size: 13px; }
.unit-manufactures__toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; color: #666; }
.unit-manufactures__button { display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 5px 9px; min-height: 30px; border: 1px solid #cfcbd2; background: #fff; color: #352345; white-space: nowrap; }
.unit-manufactures__button:hover { background: #f3f2f4; }
.unit-manufactures__form { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; padding: 0 12px 12px; align-items: start; }
.unit-manufactures__filter { max-width: 300px; margin: 0 12px 12px; }
.unit-manufactures__table-scroll { overflow: auto; max-height: 390px; }
.unit-manufactures__table { width: 100%; border-collapse: collapse; }
.unit-manufactures__table th { position: sticky; top: 0; text-align: left; color: #666; font-size: 11px; font-weight: 500; background: #f5f5f5; }
.unit-manufactures__table th, .unit-manufactures__table td { padding: 8px 12px; border-bottom: 1px solid #e7e7e7; }
.unit-manufactures__table a { color: #352345; text-decoration: none; }
.unit-manufactures__table a:hover { text-decoration: underline; }
.unit-manufactures__table .unit-manufactures__category { color: #666; font-size: 12px; }
.unit-manufactures__controls { width: 40px; text-align: right; }
.unit-manufactures__controls button { width: 28px; height: 28px; color: #651c2e; }
.unit-manufactures__controls button:hover { background: #f4f1f2; }
.unit-manufactures__controls button:disabled { opacity: .5; }
.unit-manufactures__empty { padding: 20px 12px; margin: 0; color: #777; }
.unit-manufactures__error { padding: 8px 12px; color: #651c2e; margin: 0; }
.unit-manufactures :deep(.v-field) { border-radius: 0; }
@media (max-width: 600px) { .unit-manufactures__toolbar { flex-wrap: wrap; } .unit-manufactures__form { grid-template-columns: 1fr; } }
</style>
