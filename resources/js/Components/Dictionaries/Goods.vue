<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { route } from 'ziggy-js'
import { Link, useForm } from '@inertiajs/vue3'
import { logo } from '@/Pages/Helpers/consts.js'
import { useGoods } from '@/Composables/useGoods'
import CatalogToolbar from '@/Components/Dictionaries/CatalogToolbar.vue'
import GoodTableAvatar from '@/Components/Goods/GoodTableAvatar.vue'
import GoodTradeCodeFields from '@/Components/Goods/GoodTradeCodeFields.vue'
import GoodVatCheck from '@/Components/Goods/GoodVatCheck.vue'
import { goodTradeCodeValues } from '@/utils/goodTradeCodes'

const {
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
    totalItems,
    publishLoading,
    indexGoods: fetchGoods,
    indexDictionaries: fetchDictionaries,
    saveGood: persistGood,
    deleteGood: destroyGood,
    toggleGoodPublish,
    cancelGoodsRequest,
} = useGoods()

const loadError = ref('')
const dictionaryLoading = ref(false)
const dictionaryError = ref('')
const dictionariesLoaded = ref(false)
let reloadVersion = 0

async function reloadGoods() {
    const version = ++reloadVersion
    const firstSort = tableOptions.value.sortBy?.[0] || null
    loadError.value = ''

    try {
        await fetchGoods({
            view: 'table',
            search: appliedSearch.value || null,
            ...filters.value,
            is_published: publishedParam.value,
            page: tableOptions.value.page,
            per_page: tableOptions.value.itemsPerPage,
            sort_by: firstSort?.key || 'created_at',
            sort_desc: firstSort?.order === 'desc',
        })
    } catch {
        if (version === reloadVersion) loadError.value = 'Не удалось загрузить товары. Повторите запрос.'
    }
}

async function loadDictionaries() {
    if (dictionariesLoaded.value || dictionaryLoading.value) return
    dictionaryLoading.value = true
    dictionaryError.value = ''
    try {
        await fetchDictionaries()
        dictionariesLoaded.value = true
    } catch {
        dictionaryError.value = 'Не удалось загрузить справочники.'
    } finally {
        dictionaryLoading.value = false
    }
}

const search = ref('')
const appliedSearch = ref('')
const emptyFilters = () => ({ category_id: null, product_id: null, country_id: null, field_id: null,
    industry_id: null, entity_classification_id: null, vat_rate_id: null, has_avatar: null, has_trade_codes: null, created_from: null, created_to: null })
const filters = ref(emptyFilters())
const activeFiltersCount = computed(() => Object.values(filters.value).filter(value => value !== null && value !== '').length)
const relationFilters = computed(() => [
    { key: 'category_id', label: 'Категория', items: categories.value, title: 'name' },
    { key: 'product_id', label: 'Продукт', items: products.value, title: 'rus' },
    { key: 'country_id', label: 'Страна', items: countries.value, title: 'name' },
    { key: 'field_id', label: 'Подборка', items: fields.value, title: 'title' },
    { key: 'vat_rate_id', label: 'Ставка НДС', items: vatRates.value, title: 'title' },
    { key: 'industry_id', label: 'Отрасль', items: industries.value, title: 'title' },
    { key: 'entity_classification_id', label: 'Классификация контрагентов', items: entityClassifications.value, title: 'name' },
])
function filterItems(filter) {
    return [{ id: 'none', [filter.title]: 'Не указано' }, ...filter.items]
}
function resetFilters() {
    filters.value = emptyFilters()
    publishedFilter.value = 'all'
}
const publishedFilter = ref('all') // all | published | hidden
const groupMode = ref('none')  // category | none

const selectedGood = ref(null)

const dialogForm = ref(false)
const dialogDelete = ref(false)


// Таблица Goods
const tableOptions = ref({
    page: 1,
    itemsPerPage: 50,
    sortBy: [{ key: 'created_at', order: 'desc' }],
})
const pageCount = computed(() => {
    const total = Number(totalItems.value || 0)
    const perPage = Number(tableOptions.value.itemsPerPage || 1)
    return Math.max(1, Math.ceil(total / perPage))
})

const form = useForm({
    ...goodTradeCodeValues(),
    id: null,
    name: '',
    denominator: '',
    description: '',
    vat_rate_id: null,
    country_id: null,
    is_published: true,
    products: [],
    fields: [],
    avatar_source_url: null,
    avatar_thumb_source_url: null,
    ava_image: null,     // File | null
    remove_ava: false,   // bool
})

const headers = [
    { key: 'group_category', title: 'Category', sortable: false, width: '150px' },
    { key: 'ava_image', title: '', sortable: false, width: '80px' },
    { key: 'name', title: 'Good', sortable: true },
    { key: 'country', title: 'Страна', sortable: false, width: '132px' },
    { key: 'fields', title: 'Fields', sortable: false, width: '190px' },
    { key: 'vat_rate', title: 'НДС', sortable: false, width: '64px' },
    { key: 'is_published', title: 'Pub', sortable: true, width: '72px' },
    { key: 'created_at', title: 'Создан', sortable: true, width: '132px' },
    { key: 'actions', title: '', sortable: false, width: '80px' },
]

// ---------- helpers ----------
function categoryTitleFromGood(g) {
    const first = g.products?.[0]
    return first?.category?.name || 'Без категории'
}

const itemsForTable = computed(() => {
    return goods.value.map(g => ({
        ...g,
        group_category: categoryTitleFromGood(g),
        products_count: g.products?.length ?? 0,
    }))
})

const groupBy = computed(() => {
    if (groupMode.value === 'none') return []
    return [{ key: 'group_category', order: 'asc' }]
})

const publishedParam = computed(() => {
    if (publishedFilter.value === 'published') return true
    if (publishedFilter.value === 'hidden') return false
    return null
})

function formatCreatedDate(value) {
    if (!value) return ''

    const date = new Date(value)
    const now = new Date()

    const oneDay = 24 * 60 * 60 * 1000

    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    const startOfDate = new Date(date.getFullYear(), date.getMonth(), date.getDate())

    const diffDays = Math.floor((startOfToday - startOfDate) / oneDay)

    if (diffDays === 0) return 'сегодня'
    if (diffDays === 1) return 'вчера'
    if (diffDays < 7) return `${diffDays} дня назад`

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date)
}

let lastTableOptions = ''
function updateTableOptions(options) {
    const key = JSON.stringify([options.page, options.itemsPerPage, options.sortBy])
    if (key === lastTableOptions) return
    lastTableOptions = key
    tableOptions.value = {
        page: options.page,
        itemsPerPage: options.itemsPerPage,
        sortBy: options.sortBy,
    }

    clearTimeout(searchTimer)
    appliedSearch.value = (search.value || '').trim()
    reloadGoods()
}

function setPage(page) {
    if (page === tableOptions.value.page) return
    tableOptions.value.page = page
}

function setItemsPerPage(value) {
    const perPage = Number(value)

    if (!perPage || perPage === tableOptions.value.itemsPerPage) return

    tableOptions.value.itemsPerPage = perPage
    tableOptions.value.page = 1
}

function openCreate() {
    form.reset()
    form.clearErrors()
    form.id = null
    form.name = ''
    form.denominator = ''
    form.description = ''
    form.vat_rate_id = null
    form.country_id = null
    form.is_published = true
    form.products = []
    form.fields = []
    form.ava_image = null
    form.remove_ava = false
    dialogForm.value = true
}

function openEdit(g) {
    form.reset()
    form.clearErrors()
    Object.assign(form, goodTradeCodeValues(g))
    form.id = g.id
    form.name = g.name ?? ''
    form.denominator = g.denominator ?? ''
    form.description = g.description ?? ''
    form.vat_rate_id = g.vat_rate_id ?? null
    form.country_id = g.country_id ?? g.country?.id ?? null
    form.is_published = !!g.is_published
    form.products = (g.products || []).map(p => p.id)
    form.fields = (g.fields || []).map(field => field.id)
    form.ava_image = null
    form.remove_ava = false
    dialogForm.value = true
}

async function saveGood() {
    form.clearErrors()

    try {
        await persistGood(form)
        dialogForm.value = false
        await reloadGoods()
    } catch (e) {
        if (e?.response?.status === 422) {
            form.setError(e.response.data.errors || {})
        } else {
            console.error(e)
        }
    }
}

async function deleteGood() {
    if (!selectedGood.value?.id) return

    try {
        await destroyGood(selectedGood.value.id)
        dialogDelete.value = false
        await reloadGoods()
    } catch (e) {
        console.error(e)
    }
}

function askDelete(g) {
    selectedGood.value = g
    dialogDelete.value = true
}

// ---------- watchers ----------
let searchTimer = null
function applySearch() {
    clearTimeout(searchTimer)
    appliedSearch.value = (search.value || '').trim()
    if (tableOptions.value.page === 1) reloadGoods()
    else tableOptions.value.page = 1
}
watch(search, () => {
    clearTimeout(searchTimer)
    // Superseded responses must not repaint the table during the debounce interval.
    ++reloadVersion
    cancelGoodsRequest()
    if (!(search.value || '').trim()) applySearch()
    else searchTimer = setTimeout(applySearch, 400)
}, { flush: 'sync' })
watch([filters, publishedFilter], applySearch, { deep: true })

// Load the small shared dictionaries only when filters or the editor are opened.
watch(dialogForm, (open) => { if (open) loadDictionaries() })

const previewUrl = ref(null)

watch(() => form.ava_image, (file) => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = null
    }

    if (file instanceof File) {
        previewUrl.value = URL.createObjectURL(file)
    }
})

onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    cancelGoodsRequest()
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
    }
})
</script>

<template>
    <v-container fluid class="goods-admin pa-0">
        <CatalogToolbar :count="totalItems" :filters-count="activeFiltersCount" @update:filters-open="open => open && loadDictionaries()">
            <v-text-field
                v-model="search"
                label="Название или категория"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="catalog-toolbar__search"
                @keydown.enter.prevent="applySearch"
            />
            <v-select
                v-model="publishedFilter"
                :items="[
                    { title: 'Все', value: 'all' },
                    { title: 'Опубликованные', value: 'published' },
                    { title: 'Скрытые', value: 'hidden' },
                ]"
                label="Публикация"
                variant="outlined"
                density="compact"
                hide-details
            />
            <v-select
                v-model="groupMode"
                :items="[
                    { title: 'По категориям', value: 'category' },
                    { title: 'Без группировки', value: 'none' },
                ]"
                label="Группировка"
                variant="outlined"
                density="compact"
                hide-details
            />
            <template #filters>
                <v-autocomplete
                    v-for="filter in relationFilters" :key="filter.key"
                    v-model="filters[filter.key]" :items="filterItems(filter)"
                    :item-title="filter.title" item-value="id" :label="filter.label"
                    :loading="dictionaryLoading" variant="outlined" density="compact" hide-details clearable
                />
                <v-select v-model="filters.has_avatar" label="Аватарка"
                    :items="[{ title: 'Есть фото', value: true }, { title: 'Без фото', value: false }]"
                    variant="outlined" density="compact" hide-details clearable />
                <v-select v-model="filters.has_trade_codes" label="Торговые коды"
                    :items="[{ title: 'Есть коды', value: true }, { title: 'Без кодов', value: false }]"
                    variant="outlined" density="compact" hide-details clearable />
                <v-text-field v-model="filters.created_from" label="Создан с" type="date" :max="filters.created_to || undefined"
                    variant="outlined" density="compact" hide-details clearable />
                <v-text-field v-model="filters.created_to" label="Создан по" type="date" :min="filters.created_from || undefined"
                    variant="outlined" density="compact" hide-details clearable />
                <v-btn v-if="activeFiltersCount || publishedFilter !== 'all'" variant="text" prepend-icon="mdi-filter-off-outline" @click="resetFilters">Сбросить</v-btn>
                <div v-if="dictionaryError" class="text-error text-caption">
                    {{ dictionaryError }} <v-btn variant="text" @click="loadDictionaries">Повторить</v-btn>
                </div>
            </template>
            <template #actions>
                <v-btn icon="mdi-refresh" variant="text" :loading="loading" title="Обновить товары" aria-label="Обновить товары" @click="reloadGoods" />
                <v-btn size="small" color="#352345" variant="flat" prepend-icon="mdi-plus" @click="openCreate">Новый товар</v-btn>
            </template>
        </CatalogToolbar>

        <v-alert v-if="loadError" type="error" variant="tonal" density="compact">
            {{ loadError }}
            <v-btn size="small" variant="text" @click="reloadGoods">Повторить</v-btn>
        </v-alert>

        <div class="goods-table-region">
            <v-data-table-server
                :items="itemsForTable"
                :headers="headers"
                :loading="loading"
                :items-length="totalItems"
                :page="tableOptions.page"
                :items-per-page="tableOptions.itemsPerPage"
                :sort-by="tableOptions.sortBy"
                :group-by="groupBy"
                item-value="id"
                hide-default-footer
                fixed-header
                fixed-footer
                density="compact"
                hover
                class="goods-table"
                @update:options="updateTableOptions"
            >
                    <template #item.ava_image="{ item }">
                        <GoodTableAvatar
                            :key="item.id"
                            :src="item.avatar_url || item.ava_thumb || item.ava_image"
                            :name="item.name"
                        />
                    </template>

                    <template #item.name="{ item }">
                        <Link
                            :href="route('Ameise.good.show', { id: item.id, slug: item.slug })"
                            class="goods-name"
                        >
                            {{ item.name }}
                        </Link>
                    </template>

                    <template #item.country="{ item }">
                        <div v-if="item.country" class="goods-country-cell">
                            <v-avatar size="20" rounded="circle" class="goods-country-cell__flag">
                                <v-img
                                    v-if="item.country.flag"
                                    :src="item.country.flag"
                                    :alt="item.country.name"
                                    cover
                                />
                                <span v-else>{{ item.country.name?.slice(0, 1) }}</span>
                            </v-avatar>
                            <span>{{ item.country.name }}</span>
                        </div>
                        <span v-else class="text-caption text-medium-emphasis">—</span>
                    </template>

                    <template #item.fields="{ item }">
                        <div v-if="(item.fields || []).length" class="goods-field-strip">
                            <v-chip
                                v-for="field in item.fields.slice(0, 3)"
                                :key="field.id"
                                size="x-small"
                                variant="tonal"
                                color="teal"
                            >
                                {{ field.title || field.name }}
                            </v-chip>
                            <v-chip v-if="item.fields.length > 3" size="x-small" variant="outlined">
                                +{{ item.fields.length - 3 }}
                            </v-chip>
                        </div>
                        <span v-else class="text-caption text-medium-emphasis">—</span>
                    </template>

                    <template #item.vat_rate="{ item }">
                        <span v-if="item.vat_rate">
                            {{ item.vat_rate.title }}
                        </span>
                        <span v-else class="text-medium-emphasis">
                            —
                        </span>
                    </template>

                    <template #item.is_published="{ item }">
                        <v-switch
                            :model-value="!!item.is_published"
                            :disabled="!!publishLoading[item.id]"
                            :loading="!!publishLoading[item.id]"
                            @update:model-value="() => toggleGoodPublish(item)"
                            density="compact"
                            :aria-label="`Публикация: ${item.name}`"
                            hide-details
                            color="#352345"
                        />
                    </template>

                    <template #item.created_at="{ item }">
                        <v-tooltip location="top">
                            <template #activator="{ props }">
                                <span
                                    v-bind="props"
                                    class="text-caption text-medium-emphasis"
                                >
                                    {{ formatCreatedDate(item.created_at) }}
                                </span>
                            </template>

                            {{ new Date(item.created_at).toLocaleString('ru-RU') }}
                        </v-tooltip>
                    </template>

                    <template #item.actions="{ item }">
                        <v-btn size="small" density="compact" variant="text" icon="mdi-pencil-outline" title="Изменить товар" aria-label="Изменить товар" @click="openEdit(item)" />
                        <v-btn size="small" density="compact" variant="text" icon="mdi-delete-outline" title="Удалить товар" aria-label="Удалить товар" @click="askDelete(item)" />
                    </template>

                    <template #bottom>
                        <div class="goods-table-footer">
                            <div class="goods-table-footer__left">
                                <span class="text-caption">Строк</span>

                                <v-select
                                    :model-value="tableOptions.itemsPerPage"
                                    :items="[25, 50, 100]"
                                    variant="plain"
                                    density="compact"
                                    hide-details
                                    class="goods-table-footer__select"
                                    aria-label="Товаров на странице"
                                    @update:model-value="setItemsPerPage"
                                />
                            </div>

                            <span class="goods-table-footer__range">{{ totalItems ? (tableOptions.page - 1) * tableOptions.itemsPerPage + 1 : 0 }}–{{ Math.min(tableOptions.page * tableOptions.itemsPerPage, totalItems) }} из {{ totalItems }}</span>
                            <v-pagination
                                :model-value="tableOptions.page"
                                :length="pageCount"
                                :total-visible="5"
                                density="compact"
                                rounded="0"
                                class="goods-table-footer__pagination"
                                @update:model-value="setPage"
                            />
                        </div>
                    </template>
            </v-data-table-server>
        </div>

        <!-- Create/Edit dialog -->
        <v-dialog v-model="dialogForm" width="900">
            <v-card>
                <v-toolbar :title="form.id ? 'Изменить товар' : 'Новый товар'" density="compact" />
                <v-progress-linear v-if="dictionaryLoading" indeterminate color="#352345" />
                <v-alert v-if="dictionaryError" type="error" variant="tonal" density="compact">
                    {{ dictionaryError }}
                    <v-btn variant="text" size="small" @click="loadDictionaries">Повторить</v-btn>
                </v-alert>

                <v-card-text class="pa-4">
                    <v-row dense>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.products"
                                :items="products"
                                item-title="rus"
                                item-value="id"
                                label="Products"
                                multiple
                                chips
                                clearable
                                closable-chips
                                variant="outlined"
                                density="compact"
                                :error-messages="form.errors.products"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.fields"
                                :items="fields"
                                item-title="title"
                                item-value="id"
                                label="Fields / подборки"
                                multiple
                                chips
                                clearable
                                closable-chips
                                variant="outlined"
                                density="compact"
                                :error-messages="form.errors.fields"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-text-field
                                v-model="form.name"
                                label="Название товара"
                                variant="outlined"
                                density="compact"
                                :error-messages="form.errors.name"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.denominator"
                                label="Denominator"
                                variant="outlined"
                                density="compact"
                                :error-messages="form.errors.denominator"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Описание"
                                rows="3"
                                auto-grow
                                max-rows="6"
                                variant="outlined"
                                density="compact"
                                :error-messages="form.errors.description"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-select
                                v-model="form.vat_rate_id"
                                :items="vatRates"
                                item-title="title"
                                item-value="id"
                                label="VAT rate"
                                variant="outlined"
                                density="compact"
                                :error-messages="form.errors.vat_rate_id"
                                hide-details="auto"
                                clearable
                            >
                                <template #item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        :title="item.raw.title"
                                        :subtitle="`${item.raw.rate}%`"
                                    />
                                </template>
                            </v-select>
                            <GoodVatCheck :draft="form" :vat-rates="vatRates" :active="dialogForm" :disabled="saving"
                                @apply="form.vat_rate_id = $event" />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.country_id"
                                :items="countries"
                                item-title="name"
                                item-value="id"
                                label="Страна происхождения"
                                variant="outlined"
                                density="compact"
                                clearable
                                :error-messages="form.errors.country_id"
                                hide-details="auto"
                            >
                                <template #item="{ props, item }">
                                    <v-list-item v-bind="props">
                                        <template #prepend>
                                            <v-avatar size="24" rounded="circle">
                                                <v-img
                                                    v-if="item.raw.flag"
                                                    :src="item.raw.flag"
                                                    :alt="item.raw.name"
                                                    cover
                                                />
                                                <span v-else>{{ item.raw.name?.slice(0, 1) }}</span>
                                            </v-avatar>
                                        </template>
                                    </v-list-item>
                                </template>

                                <template #selection="{ item }">
                                    <div class="goods-country-selection">
                                        <v-avatar size="22" rounded="circle">
                                            <v-img
                                                v-if="item.raw.flag"
                                                :src="item.raw.flag"
                                                :alt="item.raw.name"
                                                cover
                                            />
                                            <span v-else>{{ item.raw.name?.slice(0, 1) }}</span>
                                        </v-avatar>
                                        <span>{{ item.raw.name }}</span>
                                    </div>
                                </template>
                            </v-autocomplete>
                        </v-col>

                        <v-col cols="12">
                            <GoodTradeCodeFields :model-value="form" :errors="form.errors" :disabled="saving"
                                @update:model-value="Object.assign(form, $event)" />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-switch v-model="form.is_published" label="Опубликован" density="compact" hide-details inset />
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-file-input
                                v-model="form.ava_image"
                                label="Файл аватарки"
                                :disabled="form.remove_ava || !!(form.avatar_source_url || form.avatar_thumb_source_url)"
                                variant="outlined"
                                density="compact"
                                accept="image/*"
                                prepend-icon="mdi-camera"
                                :error-messages="form.errors.ava_image"
                                hide-details="auto"
                                clearable
                            />
                            <v-text-field v-model="form.avatar_source_url" label="Аватарка CDN · URL"
                                placeholder="https://cdn.example.com/good.jpg" :disabled="form.remove_ava || !!form.ava_image"
                                variant="outlined" density="compact" :error-messages="form.errors.avatar_source_url"
                                hint="Ссылка на изображение. Пустое поле сохраняет текущее фото." persistent-hint clearable />
                            <v-text-field v-model="form.avatar_thumb_source_url" label="Миниатюра CDN · URL"
                                placeholder="https://cdn.example.com/good-160.jpg" :disabled="form.remove_ava || !!form.ava_image"
                                variant="outlined" density="compact" :error-messages="form.errors.avatar_thumb_source_url"
                                hint="Небольшая версия для быстрой загрузки таблицы." persistent-hint clearable />
                            <v-row dense>
                                <v-col cols="12" md="4">
                                    <div class="text-caption mb-2">Предпросмотр</div>
                                    <v-avatar size="120" rounded="lg">
                                        <v-img
                                            :src="form.remove_ava ? logo : (previewUrl || form.avatar_thumb_source_url || form.avatar_source_url || (form.id && goods.find(g => g.id === form.id)?.ava_thumb) || (form.id && goods.find(g => g.id === form.id)?.ava_image) || logo)"
                                            cover
                                        />
                                    </v-avatar>
                                </v-col>
                            </v-row>
                            <v-checkbox
                                v-if="form.id"
                                v-model="form.remove_ava"
                                :disabled="!!(form.ava_image || form.avatar_source_url || form.avatar_thumb_source_url)"
                                label="Удалить текущую аватарку"
                                density="compact"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-start">
                    <v-btn variant="text" text="Закрыть" @click="dialogForm = false" :disabled="saving" />
                    <v-btn color="deep-purple-darken-1" variant="tonal" text="Сохранить" @click="saveGood" :loading="saving" :disabled="!dictionariesLoaded || dictionaryLoading" />
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete confirm -->
        <v-dialog v-model="dialogDelete" width="520">
            <v-card>
                <v-card-title>Delete Good?</v-card-title>
                <v-card-text>
                    Удалить: <strong>{{ selectedGood?.name }}</strong> ?
                </v-card-text>
                <v-card-actions class="justify-start">
                    <v-btn variant="text" text="Cancel" @click="dialogDelete = false" />
                    <v-btn color="red" variant="tonal" text="Delete" @click="deleteGood" />
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.goods-admin {
    display: flex;
    height: 100%;
    min-height: 0;
    overflow: hidden;
    flex-direction: column;
}

.goods-table-region {
    display: flex;
    flex: 1 1 0;
    height: 0;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

.goods-table {
    display: flex;
    flex: 1 1 0;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    flex-direction: column;
}

.goods-table :deep(.v-table__wrapper) {
    flex: 1 1 auto;
    min-height: 0;
}

.goods-table :deep(table) {
    min-width: 1080px;
}

.goods-table :deep(thead th) {
    height: 32px !important;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.goods-table :deep(tbody td) {
    height: 72px !important;
    padding: 3px 8px !important;
}

.goods-table :deep(tbody td:last-child) {
    white-space: nowrap;
}

.goods-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 36px;
    padding: 2px 10px;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.goods-table-footer__left {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 120px;
}

.goods-table-footer__select {
    max-width: 84px;
}

.goods-field-strip {
    display: flex;
    flex-wrap: nowrap;
    gap: 4px;
    max-height: 24px;
    overflow: hidden;
}

.goods-country-cell,
.goods-country-selection {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-width: 0;
    color: inherit;
    font-size: 12px;
    font-weight: 500;
}

.goods-country-cell__flag {
    border: 1px solid rgba(48, 70, 58, 0.16);
    background: #f3f7f0;
}

.goods-country-selection {
    max-width: 100%;
}

:deep(.goods-table-footer__select .v-field) {
    padding-inline: 0;
    min-height: 28px;
}

:deep(.goods-table-footer__select .v-field__input) {
    min-height: 28px;
    padding-top: 0;
    padding-bottom: 0;
}

:deep(.goods-table-footer__pagination .v-btn) {
    min-width: 28px;
    width: 28px;
    height: 28px;
}

:deep(.goods-table-footer__pagination .v-pagination__item),
:deep(.goods-table-footer__pagination .v-pagination__first),
:deep(.goods-table-footer__pagination .v-pagination__prev),
:deep(.goods-table-footer__pagination .v-pagination__next),
:deep(.goods-table-footer__pagination .v-pagination__last) {
    margin: 0 1px;
}
.goods-name { color: #352345; text-decoration: none; font-weight: 600; }
.goods-name:hover { text-decoration: underline; }
.goods-table :deep(.v-switch .v-selection-control) { min-height: 30px; }
.goods-table :deep(.v-switch .v-selection-control__wrapper) { width: 36px; height: 30px; }
.goods-table :deep(.v-switch .v-selection-control__input) { width: 30px; height: 30px; }
.goods-table :deep(.v-switch__track) { height: 12px; width: 28px; }
.goods-table :deep(.v-switch__thumb) { height: 16px; width: 16px; }
.goods-table-footer__range { margin-left: auto; margin-right: 12px; color: #77727b; font-size: 11px; white-space: nowrap; }
@media (max-width: 600px) {
    .goods-table-footer { flex-wrap: wrap; justify-content: center; gap: 4px; }
    .goods-table-footer__left { min-width: 100px; }
    .goods-table-footer__range { margin-right: 0; }
}
</style>
