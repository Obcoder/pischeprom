<script setup>
import { ref, computed, watch, onMounted } from 'vue'
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
    { key: 'group_category', title: 'Category', sortable: true, width: '175px' },
    { key: 'ava_image', title: '', sortable: false, width: '200px' },
    { key: 'name', title: 'Good', sortable: true },
    { key: 'is_published', title: 'Pub', sortable: true, width: '90px' },
    { key: 'products_count', title: 'Products', sortable: true, width: '100px' },
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
        const { data } = await axios.get(route('goods.index'), {
            params: {
                search: search.value || null,
                published: publishedParam.value,
                per_page: 500,
            },
        })
        goods.value = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        console.error(e)
        goods.value = []
    } finally {
        loading.value = false
    }
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
    t = setTimeout(() => indexGoods(), 250)
})

// ---------- init ----------
onMounted(async () => {
    await Promise.all([indexGoods(), indexProducts()])
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
                <v-data-table
                    :items="itemsForTable"
                    :headers="headers"
                    :loading="loading"
                    :group-by="groupBy"
                    items-per-page="100"
                    fixed-header
                    height="760px"
                    density="compact"
                    hover
                    class="border rounded"
                >
                    <template #group-header="{ item, columns, toggleGroup, isGroupOpen }">
                        <tr>
                            <td :colspan="columns.length">
                                <v-btn variant="text" density="compact" @click="toggleGroup(item)">
                                    <span class="mr-2">{{ isGroupOpen(item) ? '▾' : '▸' }}</span>
                                    <strong>{{ item.value }}</strong>
                                    <span class="ml-2 text-caption">({{ item.items.length }})</span>
                                </v-btn>
                            </td>
                        </tr>
                    </template>

                    <template #item.ava_image="{ item }">
                        <v-avatar size="79" class="cursor-pointer" @click="showGood(item.id)">
                            <v-img :src="item.ava_image || logo" cover />
                        </v-avatar>
                    </template>

                    <template #item.name="{ item }">
                        <!-- если у тебя уже есть Ameise.good.show — оставь его -->
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
                </v-data-table>
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
</template>goods
