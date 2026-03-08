<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import { Link, useForm } from '@inertiajs/vue3'
import { logo } from '@/Pages/Helpers/consts.js'

const loading = ref(false)
const saving = ref(false)
const goods = ref([])
const products = ref([]) // справочник products для выбора в форме

const search = ref('')
const publishedFilter = ref('all') // all | published | hidden
const groupMode = ref('none')  // category | none

const drawer = ref(false)
const selectedGood = ref(null)

const dialogForm = ref(false)
const dialogDelete = ref(false)


// Таблица Goods
const totalItems = ref(0)
const tableOptions = ref({
    page: 1,
    itemsPerPage: 25,
    sortBy: [{ key: 'name', order: 'asc' }],
})
const pageCount = computed(() => {
    const total = Number(totalItems.value || 0)
    const perPage = Number(tableOptions.value.itemsPerPage || 1)
    return Math.max(1, Math.ceil(total / perPage))
})

const form = useForm({
    id: null,
    name: '',
    denominator: '',
    description: '',
    is_published: true,
    products: [],
    ava_image: null,     // File | null
    remove_ava: false,   // bool
})

const headers = [
    { key: 'group_category', title: 'Category', sortable: false, width: '175px' },
    { key: 'ava_image', title: '', sortable: false, width: '200px' },
    { key: 'name', title: 'Good', sortable: true },
    { key: 'is_published', title: 'Pub', sortable: true, width: '90px' },
    { key: 'products_count', title: 'Products', sortable: false, width: '100px' },
    { key: 'actions', title: '', sortable: false, width: '120px' },
]

// ---------- helpers ----------
function categoryTitleFromGood(g) {
    // Берём 1-ю категорию из связанных products (если есть)
    // Если у Product другая структура — скажи, подстрою
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

// ---------- API ----------
async function indexGoods() {
    loading.value = true
    try {
        const firstSort = tableOptions.value.sortBy?.[0] || null

        const { data } = await axios.get(route('goods.index'), {
            params: {
                search: search.value || null,
                is_published: publishedParam.value,
                page: tableOptions.value.page,
                per_page: tableOptions.value.itemsPerPage,
                sort_by: firstSort?.key || 'name',
                sort_desc: firstSort?.order === 'desc',
            },
        })

        goods.value = Array.isArray(data) ? data : (data.data || [])
        totalItems.value = data.total || 0
    } catch (e) {
        console.error(e)
        goods.value = []
        totalItems.value = 0
    } finally {
        loading.value = false
    }
}

function updateTableOptions(options) {
    tableOptions.value = {
        page: options.page,
        itemsPerPage: options.itemsPerPage,
        sortBy: options.sortBy,
    }

    indexGoods()
}

function setPage(page) {
    if (page === tableOptions.value.page) return
    tableOptions.value.page = page
    indexGoods()
}

function setItemsPerPage(value) {
    const perPage = Number(value)

    if (!perPage || perPage === tableOptions.value.itemsPerPage) return

    tableOptions.value.itemsPerPage = perPage
    tableOptions.value.page = 1
    indexGoods()
}

async function indexProducts() {
    try {
        const { data } = await axios.get(route('products.index'))
        products.value = (Array.isArray(data) ? data : (data.data || [])).sort((a,b) => a.rus.localeCompare(b.rus))
    } catch (e) {
        console.error(e)
        products.value = []
    }
}

async function showGood(id) {
    try {
        const { data } = await axios.get(route('api.goods.show', id))
        selectedGood.value = data
        drawer.value = true
    } catch (e) {
        console.error(e)
    }
}

function openCreate() {
    form.reset()
    form.clearErrors()
    form.id = null
    form.is_published = true
    dialogForm.value = true
}

function openEdit(g) {
    form.reset()
    form.clearErrors()
    form.id = g.id
    form.name = g.name ?? ''
    form.denominator = g.denominator ?? ''
    form.description = g.description ?? ''
    form.is_published = !!g.is_published
    form.products = (g.products || []).map(p => p.id)
    form.ava_image = null
    form.remove_ava = false
    dialogForm.value = true
}

async function saveGood() {
    saving.value = true
    form.clearErrors()

    try {
        const hasFile = form.ava_image instanceof File
        const isUpdate = !!form.id

        if (hasFile) {
            // multipart
            const fd = new FormData()
            fd.append('name', form.name ?? '')
            fd.append('denominator', form.denominator ?? '')
            fd.append('description', form.description ?? '')
            fd.append('is_published', form.is_published ? '1' : '0')
            fd.append('remove_ava', form.remove_ava ? '1' : '0')
            ;(form.products || []).forEach((id) => fd.append('products[]', String(id)))
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
            // json (без файла) — ✅ создание без аватарки
            const payload = {
                name: form.name,
                denominator: form.denominator,
                description: form.description,
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

        dialogForm.value = false
        await indexGoods()
    } catch (e) {
        if (e?.response?.status === 422) {
            form.setError(e.response.data.errors || {})
        } else {
            console.error(e)
        }
    } finally {
        saving.value = false
    }
}

const publishLoading = ref({}) // { [id]: true/false }
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
    } finally {
        publishLoading.value[item.id] = false
    }
}

function askDelete(g) {
    selectedGood.value = g
    dialogDelete.value = true
}

async function deleteGood() {
    if (!selectedGood.value?.id) return
    try {
        await axios.delete(route('api.goods.destroy', selectedGood.value.id))
        dialogDelete.value = false
        drawer.value = false
        await indexGoods()
    } catch (e) {
        console.error(e)
    }
}

// ---------- watchers ----------
let t = null
watch([search, publishedFilter], () => {
    clearTimeout(t)
    t = setTimeout(() => {
        tableOptions.value.page = 1
        indexGoods()
    }, 250)
})

// ---------- init ----------
onMounted(async () => {
    await Promise.all([indexGoods(), indexProducts()])
})

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
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
    }
})
</script>

<template>
    <v-container fluid>
        <v-row class="align-center">
            <v-col cols="12" md="4">
                <v-text-field
                    v-model="search"
                    label="Поиск: товары"
                    variant="solo-inverted"
                    density="compact"
                    hide-details
                    clearable
                />
            </v-col>

            <v-col cols="12" md="3">
                <v-select
                    v-model="publishedFilter"
                    :items="[
            { title: 'Все', value: 'all' },
            { title: 'Опубликованные', value: 'published' },
            { title: 'Скрытые', value: 'hidden' },
          ]"
                    label="Публикация"
                    variant="solo-inverted"
                    density="compact"
                    hide-details
                />
            </v-col>

            <v-col cols="12" md="3">
                <v-select
                    v-model="groupMode"
                    :items="[
                                { title: 'Группировать по категориям', value: 'category' },
                                { title: 'Без группировки', value: 'none' },
                              ]"
                    label="Группировка"
                    variant="solo-inverted"
                    density="compact"
                    hide-details
                />
            </v-col>

            <v-col cols="12" md="2">
                <v-btn block color="deep-purple-darken-1" variant="tonal" @click="openCreate">
                    Новый товар
                </v-btn>
            </v-col>
        </v-row>

        <v-row>
            <v-col>
                <v-data-table-server
                    :items="itemsForTable"
                    :headers="headers"
                    :loading="loading"
                    :items-length="totalItems"
                    :page="tableOptions.page"
                    :items-per-page="tableOptions.itemsPerPage"
                    :sort-by="tableOptions.sortBy"
                    item-value="id"
                    hide-default-footer
                    fixed-header
                    fixed-footer
                    height="760px"
                    density="compact"
                    hover
                    class="border rounded goods-table"
                    @update:options="updateTableOptions"
                >
                    <template #item.ava_image="{ item }">
                        <v-avatar size="79" class="cursor-pointer" @click="showGood(item.id)">
                            <v-img :src="item.ava_image || logo" cover />
                        </v-avatar>
                    </template>

                    <template #item.name="{ item }">
                        <Link
                            :href="route('Ameise.good.show', { id: item.id, slug: item.slug })"
                            class="text-decoration-none text-primary hover:underline"
                        >
                            {{ item.name }}
                        </Link>
                    </template>

                    <template #item.is_published="{ item }">
                        <v-switch
                            :model-value="!!item.is_published"
                            :disabled="!!publishLoading[item.id]"
                            :loading="!!publishLoading[item.id]"
                            @update:model-value="() => toggleGoodPublish(item)"
                            density="compact"
                            inset
                            hide-details
                            color="green"
                        />
                    </template>

                    <template #item.actions="{ item }">
                        <v-btn size="small" variant="text" icon="mdi-eye" @click="showGood(item.id)" />
                        <v-btn size="small" variant="text" icon="mdi-pencil" @click="openEdit(item)" />
                        <v-btn size="small" variant="text" icon="mdi-delete" @click="askDelete(item)" />
                    </template>

                    <template #bottom>
                        <div class="goods-table-footer">
                            <div class="goods-table-footer__left">
                                <span class="text-caption">Rows</span>

                                <v-select
                                    :model-value="tableOptions.itemsPerPage"
                                    :items="[25, 50, 100, 200]"
                                    variant="plain"
                                    density="compact"
                                    hide-details
                                    class="goods-table-footer__select"
                                    @update:model-value="setItemsPerPage"
                                />
                            </div>

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
            </v-col>
        </v-row>

        <!-- Drawer details -->
        <v-navigation-drawer v-model="drawer" location="right" width="420">
            <v-toolbar title="Good" />
            <v-container v-if="selectedGood">
                <v-row>
                    <v-col cols="12" class="d-flex align-center">
                        <v-avatar size="64" class="mr-3">
                            <v-img :src="selectedGood.ava_image || logo" cover />
                        </v-avatar>
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">{{ selectedGood.name }}</div>
                            <div class="text-caption">slug: {{ selectedGood.slug }}</div>
                        </div>
                    </v-col>

                    <v-col cols="12">
                        <v-chip size="small" :color="selectedGood.is_published ? 'green' : 'grey'">
                            {{ selectedGood.is_published ? 'published' : 'hidden' }}
                        </v-chip>
                    </v-col>

                    <v-col cols="12">
                        <div class="text-caption mb-1">Products</div>
                        <v-chip
                            v-for="p in (selectedGood.products || [])"
                            :key="p.id"
                            size="x-small"
                            class="ma-1"
                        >
                            {{ p.rus }}
                        </v-chip>
                    </v-col>
                </v-row>
            </v-container>
        </v-navigation-drawer>

        <!-- Create/Edit dialog -->
        <v-dialog v-model="dialogForm" width="900">
            <v-card>
                <v-toolbar :title="form.id ? 'Edit Good' : 'New Good'" />

                <v-card-text>
                    <v-row>
                        <v-col cols="12">
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
                                density="comfortable"
                                :error-messages="form.errors.products"
                            />
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-text-field
                                v-model="form.name"
                                label="Good name"
                                variant="outlined"
                                density="comfortable"
                                :error-messages="form.errors.name"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.denominator"
                                label="Denominator"
                                variant="outlined"
                                density="comfortable"
                                :error-messages="form.errors.denominator"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Description"
                                variant="outlined"
                                density="comfortable"
                                :error-messages="form.errors.description"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-switch v-model="form.is_published" label="Published" inset />
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-file-input
                                v-model="form.ava_image"
                                label="Avatar (optional)"
                                variant="outlined"
                                density="comfortable"
                                accept="image/*"
                                prepend-icon="mdi-camera"
                                :error-messages="form.errors.ava_image"
                                clearable
                            />
                            <v-row>
                                <v-col cols="12" md="4">
                                    <div class="text-caption mb-2">Preview</div>
                                    <v-avatar size="120" rounded="lg">
                                        <v-img
                                            :src="previewUrl || (form.id && goods.find(g => g.id === form.id)?.ava_thumb) || (form.id && goods.find(g => g.id === form.id)?.ava_image) || logo"
                                            cover
                                        />
                                    </v-avatar>
                                </v-col>
                            </v-row>
                            <v-checkbox
                                v-if="form.id"
                                v-model="form.remove_ava"
                                label="Remove current avatar"
                                density="compact"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-start">
                    <v-btn variant="text" text="Close" @click="dialogForm = false" :disabled="saving" />
                    <v-btn color="deep-purple-darken-1" variant="tonal" text="Save" @click="saveGood" :loading="saving" />
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
.goods-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 40px;
    padding: 4px 12px;
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
</style>
