<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

const categories = ref([])
const total = ref(0)
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialog = ref(false)
const deleteDialog = ref(false)
const activeTab = ref('main')
const selectedCategory = ref(null)
const avatarFile = ref(null)
const previewUrl = ref(null)
const errors = ref({})
const search = ref('')
const publishedFilter = ref('all')
const featuredFilter = ref('all')

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
})

const options = ref({
    page: 1,
    itemsPerPage: 50,
    sortBy: [{ key: 'sort_order', order: 'asc' }],
})

const form = reactive({
    id: null,
    name: '',
    slug: '',
    local_code: '',
    image: '',
    image_alt: '',
    remove_avatar: false,
    is_published: true,
    is_featured: false,
    sort_order: 500,
    published_at: '',
    h1: '',
    short_description: '',
    description: '',
    seo_text: '',
    meta_title: '',
    meta_description: '',
    keywordsText: '',
    robots: 'index,follow',
    canonical_url: '',
    og_title: '',
    og_description: '',
    og_image: '',
    products: [],
})

const headers = [
    { title: '', key: 'image', sortable: false, width: 86 },
    { title: 'Категория', key: 'name', sortable: true },
    { title: 'SEO', key: 'seo', sortable: false, width: 150 },
    { title: 'Публикация', key: 'is_published', sortable: false, width: 150 },
    { title: 'Порядок', key: 'sort_order', sortable: true, width: 110 },
    { title: 'Продукты', key: 'products_count', sortable: true, width: 110 },
    { title: 'Товары', key: 'goods_count', sortable: true, width: 100 },
    { title: '', key: 'actions', sortable: false, width: 150 },
]

const pageCount = computed(() => {
    const perPage = Number(options.value.itemsPerPage || 1)

    return Math.max(1, Math.ceil(Number(total.value || 0) / perPage))
})

const dialogTitle = computed(() => {
    return form.id ? 'Редактирование категории' : 'Новая категория'
})

const currentImage = computed(() => {
    return previewUrl.value || form.image || '/images/placeholders/category.jpg'
})

const seoTitlePreview = computed(() => {
    return form.meta_title || form.h1 || form.name || 'Название категории'
})

const seoDescriptionPreview = computed(() => {
    return form.meta_description || form.short_description || 'Описание категории для поисковой выдачи.'
})

const publicUrl = computed(() => {
    if (selectedCategory.value?.public_url) {
        return selectedCategory.value.public_url
    }

    const param = form.slug || form.id

    return param ? route('category.show', param) : ''
})

function showMessage(text, color = 'success') {
    snackbar.text = text
    snackbar.color = color
    snackbar.show = true
}

function categoryParam(category) {
    return category.slug || category.id
}

function parseKeywords(value) {
    return String(value || '')
        .split(/[\n,]/)
        .map((keyword) => keyword.trim())
        .filter(Boolean)
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date)
}

function datetimeLocal(value) {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return String(value).slice(0, 16)
    }

    const offset = date.getTimezoneOffset()
    const local = new Date(date.getTime() - offset * 60000)

    return local.toISOString().slice(0, 16)
}

function slugify(value) {
    const map = {
        а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k',
        л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'c',
        ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
    }

    return String(value || '')
        .trim()
        .toLowerCase()
        .split('')
        .map((char) => map[char] ?? char)
        .join('')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
}

function resetForm() {
    Object.assign(form, {
        id: null,
        name: '',
        slug: '',
        local_code: '',
        image: '',
        image_alt: '',
        remove_avatar: false,
        is_published: true,
        is_featured: false,
        sort_order: 500,
        published_at: '',
        h1: '',
        short_description: '',
        description: '',
        seo_text: '',
        meta_title: '',
        meta_description: '',
        keywordsText: '',
        robots: 'index,follow',
        canonical_url: '',
        og_title: '',
        og_description: '',
        og_image: '',
        products: [],
    })

    avatarFile.value = null
    selectedCategory.value = null
    errors.value = {}
    activeTab.value = 'main'
}

function fillForm(category) {
    Object.assign(form, {
        id: category.id,
        name: category.name ?? '',
        slug: category.slug ?? '',
        local_code: category.local_code ?? '',
        image: category.image ?? '',
        image_alt: category.image_alt ?? '',
        remove_avatar: false,
        is_published: Boolean(category.is_published),
        is_featured: Boolean(category.is_featured),
        sort_order: category.sort_order ?? 500,
        published_at: datetimeLocal(category.published_at),
        h1: category.h1 ?? '',
        short_description: category.short_description ?? '',
        description: category.description ?? '',
        seo_text: category.seo_text ?? '',
        meta_title: category.meta_title ?? '',
        meta_description: category.meta_description ?? '',
        keywordsText: (category.keywords || []).join(', '),
        robots: category.robots || 'index,follow',
        canonical_url: category.canonical_url ?? '',
        og_title: category.og_title ?? '',
        og_description: category.og_description ?? '',
        og_image: category.og_image ?? '',
        products: category.products || [],
    })

    avatarFile.value = null
    errors.value = {}
}

function buildFormData(source = form, includeAvatar = true) {
    const data = new FormData()

    const textFields = [
        'name',
        'slug',
        'local_code',
        'image',
        'image_alt',
        'sort_order',
        'published_at',
        'h1',
        'short_description',
        'description',
        'seo_text',
        'meta_title',
        'meta_description',
        'robots',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
    ]

    textFields.forEach((field) => {
        data.append(field, source[field] ?? '')
    })

    data.append('is_published', source.is_published ? '1' : '0')
    data.append('is_featured', source.is_featured ? '1' : '0')
    data.append('remove_avatar', source.remove_avatar ? '1' : '0')
    data.append('keywords', JSON.stringify(parseKeywords(source.keywordsText)))

    const file = includeAvatar
        ? (Array.isArray(avatarFile.value) ? avatarFile.value[0] : avatarFile.value)
        : null

    if (file instanceof File) {
        data.append('avatar', file)
    }

    return data
}

function replaceCategory(category) {
    const index = categories.value.findIndex((item) => item.id === category.id)

    if (index >= 0) {
        categories.value.splice(index, 1, category)
        return
    }

    categories.value.unshift(category)
}

async function fetchCategories() {
    loading.value = true

    try {
        const sort = options.value.sortBy?.[0] || {}
        const response = await axios.get(route('categories.index'), {
            params: {
                page: options.value.page,
                per_page: options.value.itemsPerPage,
                search: search.value || null,
                published: publishedFilter.value === 'all' ? null : publishedFilter.value === 'published',
                featured: featuredFilter.value === 'all' ? null : featuredFilter.value === 'featured',
                sortBy: sort.key || 'sort_order',
                sortDesc: sort.order === 'desc',
            },
        })

        categories.value = response.data.data || []
        total.value = response.data.meta?.total || categories.value.length
    } catch (error) {
        console.error(error)
        showMessage('Не удалось загрузить категории', 'error')
    } finally {
        loading.value = false
    }
}

function openCreate() {
    resetForm()
    dialog.value = true
}

async function openEdit(category) {
    resetForm()
    dialog.value = true
    loading.value = true

    try {
        const response = await axios.get(route('categories.show', category.id))
        selectedCategory.value = response.data.data
        fillForm(response.data.data)
    } catch (error) {
        console.error(error)
        selectedCategory.value = category
        fillForm(category)
        showMessage('Категория открыта из таблицы, детальная загрузка не удалась', 'warning')
    } finally {
        loading.value = false
    }
}

async function saveCategory() {
    errors.value = {}
    saving.value = true

    try {
        const data = buildFormData()
        let response

        if (form.id) {
            data.append('_method', 'PATCH')
            response = await axios.post(route('categories.update', form.id), data)
        } else {
            response = await axios.post(route('categories.store'), data)
        }

        const wasEditing = Boolean(form.id)
        const category = response.data.data
        replaceCategory(category)
        selectedCategory.value = category
        fillForm(category)
        dialog.value = false
        showMessage(wasEditing ? 'Категория обновлена' : 'Категория создана')
        await fetchCategories()
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
            showMessage('Проверьте поля формы', 'error')
            return
        }

        console.error(error)
        showMessage('Не удалось сохранить категорию', 'error')
    } finally {
        saving.value = false
    }
}

async function toggleCategory(category, field, value) {
    const source = {
        ...category,
        keywordsText: (category.keywords || []).join(', '),
        remove_avatar: false,
        [field]: value,
    }

    const previous = category[field]
    category[field] = value

    try {
        const data = buildFormData(source, false)
        data.append('_method', 'PATCH')
        const response = await axios.post(route('categories.update', category.id), data)
        replaceCategory(response.data.data)
    } catch (error) {
        category[field] = previous
        console.error(error)
        showMessage('Не удалось быстро обновить категорию', 'error')
    }
}

function askDelete(category) {
    selectedCategory.value = category
    deleteDialog.value = true
}

async function deleteCategory() {
    if (!selectedCategory.value?.id) {
        return
    }

    deleting.value = true

    try {
        await axios.delete(route('categories.destroy', selectedCategory.value.id))
        categories.value = categories.value.filter((item) => item.id !== selectedCategory.value.id)
        deleteDialog.value = false
        selectedCategory.value = null
        showMessage('Категория удалена')
        await fetchCategories()
    } catch (error) {
        console.error(error)
        const message = error.response?.status === 409
            ? 'Нельзя удалить категорию со связанными продуктами'
            : 'Не удалось удалить категорию'

        showMessage(message, 'error')
    } finally {
        deleting.value = false
    }
}

function generateSlug() {
    form.slug = slugify(form.name)
}

function setPage(page) {
    if (page === options.value.page) {
        return
    }

    options.value.page = page
    fetchCategories()
}

function setItemsPerPage(value) {
    const perPage = Number(value)

    if (!perPage || perPage === options.value.itemsPerPage) {
        return
    }

    options.value.itemsPerPage = perPage
    options.value.page = 1
    fetchCategories()
}

function updateOptions(nextOptions) {
    options.value = {
        page: nextOptions.page,
        itemsPerPage: nextOptions.itemsPerPage,
        sortBy: nextOptions.sortBy,
    }

    fetchCategories()
}

let searchTimer = null
watch([search, publishedFilter, featuredFilter], () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        options.value.page = 1
        fetchCategories()
    }, 300)
})

watch(avatarFile, (file) => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = null
    }

    const selected = Array.isArray(file) ? file[0] : file

    if (selected instanceof File) {
        previewUrl.value = URL.createObjectURL(selected)
    }
})

onMounted(fetchCategories)

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
    }
})
</script>

<template>
    <v-container fluid class="category-admin pa-4">
        <v-card rounded="xl" elevation="1">
            <v-card-title class="d-flex align-center ga-3 flex-wrap">
                <div>
                    <div class="text-h6 font-weight-bold">Категории</div>
                    <div class="text-caption text-medium-emphasis">
                        CRUD, аватары, slug и SEO-поля для публичных страниц категорий.
                    </div>
                </div>

                <v-spacer />

                <v-text-field
                    v-model="search"
                    label="Поиск"
                    prepend-inner-icon="mdi-magnify"
                    variant="solo-inverted"
                    density="compact"
                    hide-details
                    clearable
                    style="max-width: 300px"
                />

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
                    style="max-width: 190px"
                />

                <v-select
                    v-model="featuredFilter"
                    :items="[
                        { title: 'Все', value: 'all' },
                        { title: 'Избранные', value: 'featured' },
                        { title: 'Обычные', value: 'regular' },
                    ]"
                    label="Витрина"
                    variant="solo-inverted"
                    density="compact"
                    hide-details
                    style="max-width: 170px"
                />

                <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">
                    Категория
                </v-btn>
            </v-card-title>

            <v-data-table-server
                :headers="headers"
                :items="categories"
                :loading="loading"
                :items-length="total"
                :page="options.page"
                :items-per-page="options.itemsPerPage"
                :sort-by="options.sortBy"
                fixed-header
                height="720px"
                density="compact"
                hover
                item-value="id"
                class="category-admin__table"
                hide-default-footer
                @update:options="updateOptions"
            >
                <template #item.image="{ item }">
                    <v-avatar size="58" rounded="lg" class="my-2">
                        <v-img
                            :src="item.image || '/images/placeholders/category.jpg'"
                            :alt="item.image_alt || item.name"
                            cover
                        />
                    </v-avatar>
                </template>

                <template #item.name="{ item }">
                    <div class="py-2">
                        <div class="d-flex align-center ga-2 flex-wrap">
                            <a
                                v-if="item.public_url"
                                :href="item.public_url"
                                target="_blank"
                                rel="noopener"
                                class="text-primary font-weight-bold text-decoration-none"
                            >
                                {{ item.name }}
                            </a>
                            <span v-else class="font-weight-bold">{{ item.name }}</span>

                            <v-chip v-if="item.is_featured" color="amber" size="x-small" variant="tonal">
                                витрина
                            </v-chip>
                        </div>

                        <div class="text-caption text-medium-emphasis">
                            /категория/{{ categoryParam(item) }}
                        </div>

                        <div v-if="item.short_description" class="text-caption category-admin__description">
                            {{ item.short_description }}
                        </div>
                    </div>
                </template>

                <template #item.seo="{ item }">
                    <div class="d-flex ga-1 flex-wrap">
                        <v-chip
                            :color="item.slug ? 'green' : 'red'"
                            size="x-small"
                            variant="tonal"
                        >
                            slug
                        </v-chip>
                        <v-chip
                            :color="item.meta_title ? 'green' : 'orange'"
                            size="x-small"
                            variant="tonal"
                        >
                            title
                        </v-chip>
                        <v-chip
                            :color="item.meta_description ? 'green' : 'orange'"
                            size="x-small"
                            variant="tonal"
                        >
                            description
                        </v-chip>
                    </div>
                </template>

                <template #item.is_published="{ item }">
                    <div class="d-flex align-center ga-2">
                        <v-switch
                            :model-value="!!item.is_published"
                            color="green"
                            density="compact"
                            hide-details
                            inset
                            @update:model-value="(value) => toggleCategory(item, 'is_published', value)"
                        />
                        <span class="text-caption">
                            {{ item.is_published ? 'в индексе' : 'скрыта' }}
                        </span>
                    </div>
                </template>

                <template #item.products_count="{ item }">
                    {{ item.products_count ?? 0 }}
                </template>

                <template #item.goods_count="{ item }">
                    {{ item.goods_count ?? 0 }}
                </template>

                <template #item.actions="{ item }">
                    <div class="d-flex ga-1 justify-end">
                        <v-btn
                            v-if="item.public_url"
                            :href="item.public_url"
                            target="_blank"
                            rel="noopener"
                            icon="mdi-open-in-new"
                            size="small"
                            variant="text"
                        />
                        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(item)" />
                        <v-btn icon="mdi-delete" size="small" variant="text" color="red" @click="askDelete(item)" />
                    </div>
                </template>

                <template #bottom>
                    <div class="category-admin__footer">
                        <div class="d-flex align-center ga-2">
                            <span class="text-caption">Строк</span>
                            <v-select
                                :model-value="options.itemsPerPage"
                                :items="[25, 50, 100, 200]"
                                density="compact"
                                variant="plain"
                                hide-details
                                style="max-width: 84px"
                                @update:model-value="setItemsPerPage"
                            />
                        </div>

                        <v-pagination
                            :model-value="options.page"
                            :length="pageCount"
                            :total-visible="5"
                            density="compact"
                            @update:model-value="setPage"
                        />
                    </div>
                </template>
            </v-data-table-server>
        </v-card>

        <v-dialog v-model="dialog" max-width="1180" persistent scrollable>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center ga-3">
                    <span>{{ dialogTitle }}</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" :disabled="saving" @click="dialog = false" />
                </v-card-title>

                <v-divider />

                <v-tabs v-model="activeTab" color="primary">
                    <v-tab value="main">Основное</v-tab>
                    <v-tab value="seo">SEO</v-tab>
                    <v-tab value="social">OG / соцсети</v-tab>
                    <v-tab value="preview">Проверка</v-tab>
                </v-tabs>

                <v-divider />

                <v-card-text>
                    <v-window v-model="activeTab">
                        <v-window-item value="main">
                            <v-row>
                                <v-col cols="12" md="8">
                                    <v-row>
                                        <v-col cols="12" md="7">
                                            <v-text-field
                                                v-model="form.name"
                                                label="Название"
                                                variant="outlined"
                                                density="comfortable"
                                                :error-messages="errors.name"
                                                required
                                            />
                                        </v-col>

                                        <v-col cols="12" md="5">
                                            <v-text-field
                                                v-model="form.local_code"
                                                label="Local code"
                                                variant="outlined"
                                                density="comfortable"
                                                :error-messages="errors.local_code"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="8">
                                            <v-text-field
                                                v-model="form.slug"
                                                label="Slug"
                                                variant="outlined"
                                                density="comfortable"
                                                hint="Можно оставить пустым: Laravel сгенерирует уникальный slug из названия."
                                                persistent-hint
                                                :error-messages="errors.slug"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-btn block variant="tonal" height="56" @click="generateSlug">
                                                Сгенерировать slug
                                            </v-btn>
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model.number="form.sort_order"
                                                type="number"
                                                label="Порядок"
                                                variant="outlined"
                                                density="comfortable"
                                                :error-messages="errors.sort_order"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-text-field
                                                v-model="form.published_at"
                                                type="datetime-local"
                                                label="Дата публикации"
                                                variant="outlined"
                                                density="comfortable"
                                                :error-messages="errors.published_at"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-select
                                                v-model="form.robots"
                                                :items="['index,follow', 'noindex,follow', 'noindex,nofollow']"
                                                label="Robots"
                                                variant="outlined"
                                                density="comfortable"
                                                :error-messages="errors.robots"
                                            />
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-switch v-model="form.is_published" label="Опубликована" color="green" inset />
                                        </v-col>

                                        <v-col cols="12" md="4">
                                            <v-switch v-model="form.is_featured" label="На витрине" color="amber" inset />
                                        </v-col>
                                    </v-row>
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-card rounded="lg" variant="tonal">
                                        <v-img :src="currentImage" :alt="form.image_alt || form.name" height="210" cover />

                                        <v-card-text>
                                            <v-file-input
                                                v-model="avatarFile"
                                                label="Загрузить аватар"
                                                accept="image/*"
                                                variant="outlined"
                                                density="comfortable"
                                                prepend-icon="mdi-camera"
                                                clearable
                                                :error-messages="errors.avatar"
                                            />

                                            <v-text-field
                                                v-model="form.image"
                                                label="Или внешний URL изображения"
                                                variant="outlined"
                                                density="comfortable"
                                                :error-messages="errors.image"
                                            />

                                            <v-text-field
                                                v-model="form.image_alt"
                                                label="Alt изображения"
                                                variant="outlined"
                                                density="comfortable"
                                                :error-messages="errors.image_alt"
                                            />

                                            <v-checkbox
                                                v-if="form.id && form.image"
                                                v-model="form.remove_avatar"
                                                label="Удалить текущий локальный аватар"
                                                density="compact"
                                            />
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-window-item>

                        <v-window-item value="seo">
                            <v-row>
                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="form.h1"
                                        label="H1"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.h1"
                                    />
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="form.meta_title"
                                        label="Meta title"
                                        counter="255"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.meta_title"
                                    />
                                </v-col>

                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.meta_description"
                                        label="Meta description"
                                        counter="1000"
                                        rows="3"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.meta_description"
                                    />
                                </v-col>

                                <v-col cols="12">
                                    <v-textarea
                                        v-model="form.short_description"
                                        label="Короткое описание над товарами"
                                        rows="3"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.short_description"
                                    />
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-textarea
                                        v-model="form.description"
                                        label="Описание категории"
                                        rows="7"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.description"
                                    />
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-textarea
                                        v-model="form.seo_text"
                                        label="SEO-текст внизу страницы"
                                        rows="7"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.seo_text"
                                    />
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-textarea
                                        v-model="form.keywordsText"
                                        label="Keywords"
                                        hint="Через запятую или с новой строки."
                                        persistent-hint
                                        rows="3"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.keywords"
                                    />
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-text-field
                                        v-model="form.canonical_url"
                                        label="Canonical URL"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.canonical_url"
                                    />
                                </v-col>
                            </v-row>
                        </v-window-item>

                        <v-window-item value="social">
                            <v-row>
                                <v-col cols="12" md="7">
                                    <v-text-field
                                        v-model="form.og_title"
                                        label="OG title"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.og_title"
                                    />

                                    <v-textarea
                                        v-model="form.og_description"
                                        label="OG description"
                                        rows="4"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.og_description"
                                    />

                                    <v-text-field
                                        v-model="form.og_image"
                                        label="OG image URL"
                                        variant="outlined"
                                        density="comfortable"
                                        :error-messages="errors.og_image"
                                    />
                                </v-col>

                                <v-col cols="12" md="5">
                                    <v-card rounded="lg" elevation="1" class="overflow-hidden">
                                        <v-img :src="form.og_image || currentImage" height="220" cover />
                                        <v-card-text>
                                            <div class="text-subtitle-1 font-weight-bold mb-2">
                                                {{ form.og_title || seoTitlePreview }}
                                            </div>
                                            <div class="text-body-2 text-medium-emphasis">
                                                {{ form.og_description || seoDescriptionPreview }}
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-window-item>

                        <v-window-item value="preview">
                            <v-row>
                                <v-col cols="12" md="7">
                                    <v-card rounded="lg" variant="tonal" class="mb-4">
                                        <v-card-title>Поисковый сниппет</v-card-title>
                                        <v-card-text>
                                            <div class="category-admin__snippet-title">{{ seoTitlePreview }}</div>
                                            <div class="category-admin__snippet-url">{{ publicUrl || 'URL появится после сохранения' }}</div>
                                            <div class="category-admin__snippet-description">{{ seoDescriptionPreview }}</div>
                                        </v-card-text>
                                    </v-card>

                                    <v-card rounded="lg" variant="tonal">
                                        <v-card-title>Публичная страница</v-card-title>
                                        <v-card-text>
                                            <div class="text-h5 font-weight-bold mb-2">{{ form.h1 || form.name || 'H1 категории' }}</div>
                                            <p class="text-body-2 text-medium-emphasis mb-4">
                                                {{ form.short_description || 'Короткое описание появится в hero-блоке категории.' }}
                                            </p>

                                            <v-chip class="mr-2" color="primary" variant="tonal">
                                                {{ selectedCategory?.products_count ?? 0 }} продуктов
                                            </v-chip>
                                            <v-chip color="primary" variant="tonal">
                                                {{ selectedCategory?.goods_count ?? 0 }} товаров
                                            </v-chip>
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="5">
                                    <v-card rounded="lg" variant="outlined">
                                        <v-card-title>Продукты категории</v-card-title>
                                        <v-card-text>
                                            <v-list v-if="form.products.length" density="compact">
                                                <v-list-item
                                                    v-for="product in form.products"
                                                    :key="product.id"
                                                    :title="product.rus || product.name"
                                                    :subtitle="`${product.goods_count ?? 0} товаров`"
                                                />
                                            </v-list>

                                            <div v-else class="text-body-2 text-medium-emphasis">
                                                Продукты подтянутся после сохранения и привязки категории в Products.
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-window-item>
                    </v-window>
                </v-card-text>

                <v-divider />

                <v-card-actions>
                    <v-btn variant="text" :disabled="saving" @click="dialog = false">
                        Закрыть
                    </v-btn>
                    <v-spacer />
                    <v-btn
                        color="primary"
                        prepend-icon="mdi-content-save"
                        :loading="saving"
                        @click="saveCategory"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="520">
            <v-card rounded="xl">
                <v-card-title>Удалить категорию?</v-card-title>
                <v-card-text>
                    Категория <strong>{{ selectedCategory?.name }}</strong> будет удалена только если в ней нет связанных продуктов.
                </v-card-text>
                <v-card-actions>
                    <v-btn variant="text" :disabled="deleting" @click="deleteDialog = false">
                        Отмена
                    </v-btn>
                    <v-spacer />
                    <v-btn color="red" variant="tonal" :loading="deleting" @click="deleteCategory">
                        Удалить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<style scoped>
.category-admin__table {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.category-admin__description {
    max-width: 620px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.category-admin__footer {
    min-height: 52px;
    padding: 4px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.category-admin__snippet-title {
    color: #1a0dab;
    font-size: 20px;
    line-height: 1.25;
    margin-bottom: 2px;
}

.category-admin__snippet-url {
    color: #006621;
    font-size: 13px;
    margin-bottom: 6px;
    word-break: break-all;
}

.category-admin__snippet-description {
    color: #545454;
    line-height: 1.45;
}
</style>
