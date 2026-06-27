<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { useHead } from '@vueuse/head'

/*
 * Управление промо-баннерами главной страницы.
 * Изображения берутся из Yandex Object Storage: root-папка banners.
 */
defineOptions({
    layout: VerwalterLayout,
})

const banners = ref([])
const goods = ref([])
const products = ref([])
const categories = ref([])
const loading = ref(false)
const saving = ref(false)
const dialogOpen = ref(false)
const editingId = ref(null)
const search = ref('')
const errorMessage = ref('')

const assetLoading = ref(false)
const uploadingAsset = ref(false)
const assetFolder = ref('')
const assetFolders = ref([])
const assetFiles = ref([])
const assetErrorMessage = ref('')
const assetTargetField = ref('image_url')
const assetUploadInput = ref(null)
const folderDialogOpen = ref(false)
const renameDialogOpen = ref(false)
const moveDialogOpen = ref(false)

const folderForm = reactive({
    name: '',
})

const renameForm = reactive({
    path: '',
    type: 'file',
    name: '',
})

const moveForm = reactive({
    path: '',
    type: 'file',
    name: '',
    target_folder: '',
})

const sizeOptions = [
    { title: 'Широкий', value: 'wide' },
    { title: 'Стандартный', value: 'standard' },
    { title: 'Компактный', value: 'compact' },
]

const headers = [
    { title: 'Баннер', key: 'preview', sortable: false },
    { title: 'Публикация', key: 'is_published', width: 130 },
    { title: 'Размер', key: 'size', width: 130 },
    { title: 'Видимость', key: 'visibility', sortable: false, width: 150 },
    { title: 'Связи', key: 'relations', sortable: false },
    { title: 'Порядок', key: 'sort_order', width: 110 },
    { title: '', key: 'actions', sortable: false, width: 150 },
]

const form = reactive(defaultForm())

const formTitle = computed(() => editingId.value ? 'Редактировать баннер' : 'Новый баннер')

const assetBreadcrumbs = computed(() => {
    const parts = assetFolder.value ? assetFolder.value.split('/').filter(Boolean) : []
    let current = ''

    return [
        { title: 'banners', folder: '' },
        ...parts.map((part) => {
            current = current ? `${current}/${part}` : part

            return {
                title: part,
                folder: current,
            }
        }),
    ]
})

const parentAssetFolder = computed(() => {
    if (!assetFolder.value) {
        return null
    }

    const parts = assetFolder.value.split('/').filter(Boolean)
    parts.pop()

    return parts.join('/')
})

const assetIsEmpty = computed(() => !assetLoading.value && !assetFolders.value.length && !assetFiles.value.length)

const assetTargetLabel = computed(() => assetTargetField.value === 'mobile_image_url' ? 'mobile' : 'desktop')

const moveFolderOptions = computed(() => {
    const values = [
        '',
        assetFolder.value,
        parentAssetFolder.value,
        ...assetBreadcrumbs.value.map((breadcrumb) => breadcrumb.folder),
        ...assetFolders.value.map((folder) => folder.relative_path),
    ]
        .map((value) => normalizeAssetFolder(value || ''))
        .filter((value, index, items) => items.indexOf(value) === index)

    return values.map((value) => ({
        title: value ? `/${value}` : 'banners / корень',
        value,
    }))
})

function defaultForm() {
    return {
        title: '',
        eyebrow: '',
        subtitle: '',
        description: '',
        image_url: '',
        mobile_image_url: '',
        cta_label: '',
        cta_url: '',
        good_id: null,
        product_id: null,
        category_id: null,
        size: 'wide',
        is_published: true,
        show_on_desktop: true,
        show_on_mobile: true,
        sort_order: 500,
        background_color: '#fff7df',
        text_color: '#2b2118',
        accent_color: '#f2aa00',
        starts_at: '',
        ends_at: '',
    }
}

function normalizeList(response) {
    const payload = response?.data

    if (Array.isArray(payload)) {
        return payload
    }

    return payload?.data || []
}

function apiMessage(error, fallback) {
    return error.response?.data?.message || fallback
}

async function fetchBanners() {
    loading.value = true
    errorMessage.value = ''

    try {
        const { data } = await axios.get('/api/home-banners', {
            params: {
                search: search.value || undefined,
                per_page: 200,
            },
        })

        banners.value = data.data || []
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Не удалось загрузить баннеры.'
    } finally {
        loading.value = false
    }
}

async function fetchDictionaries() {
    const [goodsResponse, productsResponse, categoriesResponse] = await Promise.all([
        axios.get('/api/goods', { params: { per_page: 500, sort_by: 'name' } }),
        axios.get('/api/products'),
        axios.get('/api/categories', { params: { per_page: 500, sortBy: 'name' } }),
    ])

    goods.value = normalizeList(goodsResponse)
    products.value = normalizeList(productsResponse)
    categories.value = normalizeList(categoriesResponse)
}

async function fetchAssets(folder = assetFolder.value) {
    assetLoading.value = true
    assetErrorMessage.value = ''

    try {
        const normalizedFolder = normalizeAssetFolder(folder)
        const { data } = await axios.get('/api/home-banner-assets', {
            params: {
                folder: normalizedFolder || undefined,
            },
        })

        assetFolder.value = data.folder || ''
        assetFolders.value = data.folders || []
        assetFiles.value = data.files || []
    } catch (error) {
        console.error(error)
        assetErrorMessage.value = apiMessage(error, 'Не удалось загрузить файлы из S3.')
    } finally {
        assetLoading.value = false
    }
}

function openCreateDialog() {
    editingId.value = null
    Object.assign(form, defaultForm())
    dialogOpen.value = true
    void fetchAssets(assetFolder.value)
}

function editBanner(banner) {
    editingId.value = banner.id
    Object.assign(form, {
        ...defaultForm(),
        ...banner,
        starts_at: toDateTimeInput(banner.starts_at),
        ends_at: toDateTimeInput(banner.ends_at),
    })
    dialogOpen.value = true
    void fetchAssets(assetFolder.value)
}

function closeDialog() {
    dialogOpen.value = false
    editingId.value = null
    Object.assign(form, defaultForm())
}

function payload() {
    return {
        ...form,
        good_id: form.good_id || null,
        product_id: form.product_id || null,
        category_id: form.category_id || null,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
    }
}

async function saveBanner() {
    saving.value = true
    errorMessage.value = ''

    try {
        if (editingId.value) {
            await axios.patch(`/api/home-banners/${editingId.value}`, payload())
        } else {
            await axios.post('/api/home-banners', payload())
        }

        closeDialog()
        await fetchBanners()
    } catch (error) {
        console.error(error)
        errorMessage.value = apiMessage(error, 'Не удалось сохранить баннер.')
    } finally {
        saving.value = false
    }
}

async function togglePublished(banner) {
    await axios.patch(`/api/home-banners/${banner.id}`, {
        ...banner,
        is_published: !banner.is_published,
    })
    await fetchBanners()
}

async function deleteBanner(banner) {
    if (!window.confirm(`Удалить баннер "${banner.title}"?`)) {
        return
    }

    await axios.delete(`/api/home-banners/${banner.id}`)
    await fetchBanners()
}

function openAssetFolder(folder) {
    const nextFolder = typeof folder === 'string' ? folder : folder?.relative_path

    void fetchAssets(nextFolder || '')
}

function triggerAssetUpload() {
    assetUploadInput.value?.click()
}

async function uploadAsset(event) {
    const file = event.target.files?.[0]

    if (!file) {
        return
    }

    uploadingAsset.value = true
    assetErrorMessage.value = ''

    const formData = new FormData()
    formData.append('file', file)
    formData.append('folder', assetFolder.value)

    try {
        await axios.post('/api/home-banner-assets/upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })
        await fetchAssets(assetFolder.value)
    } catch (error) {
        console.error(error)
        assetErrorMessage.value = apiMessage(error, 'Не удалось загрузить изображение.')
    } finally {
        uploadingAsset.value = false
        event.target.value = ''
    }
}

function openFolderDialog() {
    folderForm.name = ''
    folderDialogOpen.value = true
}

async function createAssetFolder() {
    if (!folderForm.name.trim()) {
        return
    }

    assetErrorMessage.value = ''

    try {
        await axios.post('/api/home-banner-assets/folders', {
            name: folderForm.name,
            parent: assetFolder.value,
        })
        folderDialogOpen.value = false
        folderForm.name = ''
        await fetchAssets(assetFolder.value)
    } catch (error) {
        console.error(error)
        assetErrorMessage.value = apiMessage(error, 'Не удалось создать папку.')
    }
}

function useAsset(file, field = assetTargetField.value) {
    form[field] = file.url
    assetTargetField.value = field
}

function openRenameAsset(item) {
    Object.assign(renameForm, {
        path: item.path,
        type: item.type,
        name: item.name,
    })
    renameDialogOpen.value = true
}

async function renameAsset() {
    if (!renameForm.name.trim()) {
        return
    }

    assetErrorMessage.value = ''

    try {
        await axios.patch('/api/home-banner-assets/rename', {
            path: renameForm.path,
            type: renameForm.type,
            new_name: renameForm.name,
        })
        renameDialogOpen.value = false
        await fetchAssets(assetFolder.value)
    } catch (error) {
        console.error(error)
        assetErrorMessage.value = apiMessage(error, 'Не удалось переименовать объект.')
    }
}

function openMoveAsset(item) {
    Object.assign(moveForm, {
        path: item.path,
        type: item.type,
        name: item.name,
        target_folder: assetFolder.value,
    })
    moveDialogOpen.value = true
}

async function moveAsset() {
    assetErrorMessage.value = ''

    try {
        await axios.patch('/api/home-banner-assets/move', {
            path: moveForm.path,
            type: moveForm.type,
            target_folder: normalizeAssetFolder(moveForm.target_folder || ''),
        })
        moveDialogOpen.value = false
        await fetchAssets(assetFolder.value)
    } catch (error) {
        console.error(error)
        assetErrorMessage.value = apiMessage(error, 'Не удалось переместить объект.')
    }
}

async function deleteAsset(item) {
    const typeLabel = item.type === 'folder' ? 'папку' : 'файл'

    if (!window.confirm(`Удалить ${typeLabel} "${item.name}" из S3?`)) {
        return
    }

    assetErrorMessage.value = ''

    try {
        await axios.delete('/api/home-banner-assets', {
            data: {
                path: item.path,
                type: item.type,
            },
        })
        await fetchAssets(assetFolder.value)
    } catch (error) {
        console.error(error)
        assetErrorMessage.value = apiMessage(error, 'Не удалось удалить объект.')
    }
}

function normalizeAssetFolder(folder) {
    if (folder && typeof folder === 'object') {
        folder = folder.value ?? folder.title ?? ''
    }

    return String(folder || '')
        .replace(/\\/g, '/')
        .replace(/^\/+|\/+$/g, '')
        .replace(/^banners\/?/, '')
}

function productTitle(product) {
    return product?.rus || product?.eng || `Product #${product?.id}`
}

function categoryTitle(category) {
    return category?.name || `Category #${category?.id}`
}

function goodTitle(good) {
    return good?.name || `Good #${good?.id}`
}

function relationLabels(banner) {
    return [
        banner.good ? `Good: ${banner.good.name}` : '',
        banner.product ? `Product: ${productTitle(banner.product)}` : '',
        banner.category ? `Category: ${banner.category.name}` : '',
    ].filter(Boolean)
}

function sizeLabel(value) {
    return sizeOptions.find((item) => item.value === value)?.title || value
}

function toDateTimeInput(value) {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    return date.toISOString().slice(0, 16)
}

function assetSize(size) {
    if (!size && size !== 0) {
        return 'размер неизвестен'
    }

    if (size < 1024) {
        return `${size} B`
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`
    }

    return `${(size / 1024 / 1024).toFixed(1)} MB`
}

function assetDate(timestamp) {
    if (!timestamp) {
        return ''
    }

    return new Date(timestamp * 1000).toLocaleDateString('ru-RU')
}

onMounted(async () => {
    await Promise.all([
        fetchBanners(),
        fetchDictionaries(),
        fetchAssets(),
    ])
})

useHead({
    title: 'Баннеры главной',
})
</script>

<template>
    <v-container fluid class="home-banners-admin">
        <div class="home-banners-admin__header">
            <div>
                <div class="home-banners-admin__eyebrow">Ameise / Welcome</div>
                <h1>Рекламные баннеры главной</h1>
                <p>Управление галереей промо-блоков: публикация, порядок, размер, мобильность, S3-файлы и связи с каталогом.</p>
            </div>

            <v-btn color="#8f1111" rounded="xl" prepend-icon="mdi-plus" @click="openCreateDialog">
                Новый баннер
            </v-btn>
        </div>

        <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            class="mb-4"
        >
            {{ errorMessage }}
        </v-alert>

        <v-card rounded="xl" elevation="1" class="mb-4">
            <v-card-text>
                <v-row align="center" dense>
                    <v-col cols="12" md="8">
                        <v-text-field
                            v-model="search"
                            label="Поиск по баннерам"
                            variant="outlined"
                            density="compact"
                            hide-details
                            clearable
                            prepend-inner-icon="mdi-magnify"
                            @keyup.enter="fetchBanners"
                        />
                    </v-col>
                    <v-col cols="12" md="4">
                        <v-btn block rounded="xl" variant="tonal" @click="fetchBanners">
                            Обновить
                        </v-btn>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>

        <v-card rounded="xl" elevation="1">
            <v-data-table
                :headers="headers"
                :items="banners"
                :loading="loading"
                item-value="id"
            >
                <template #item.preview="{ item }">
                    <div class="banner-preview">
                        <div class="banner-preview__image">
                            <img
                                v-if="item.image_url || item.mobile_image_url"
                                :src="item.image_url || item.mobile_image_url"
                                :alt="item.title"
                            >
                            <span v-else>{{ item.title?.slice(0, 1) }}</span>
                        </div>
                        <div>
                            <strong>{{ item.title }}</strong>
                            <span>{{ item.subtitle || item.eyebrow || 'Без подзаголовка' }}</span>
                        </div>
                    </div>
                </template>

                <template #item.is_published="{ item }">
                    <v-chip :color="item.is_published ? 'green' : 'grey'" size="small" variant="tonal">
                        {{ item.is_published ? 'Опубликован' : 'Черновик' }}
                    </v-chip>
                </template>

                <template #item.size="{ item }">
                    {{ sizeLabel(item.size) }}
                </template>

                <template #item.visibility="{ item }">
                    <div class="visibility-tags">
                        <v-chip v-if="item.show_on_desktop" size="x-small" variant="tonal">Desktop</v-chip>
                        <v-chip v-if="item.show_on_mobile" size="x-small" variant="tonal">Mobile</v-chip>
                        <v-chip v-if="!item.show_on_desktop && !item.show_on_mobile" size="x-small" color="red" variant="tonal">Скрыт</v-chip>
                    </div>
                </template>

                <template #item.relations="{ item }">
                    <div class="relation-list">
                        <span v-for="label in relationLabels(item)" :key="label">{{ label }}</span>
                        <span v-if="!relationLabels(item).length">Без связи</span>
                    </div>
                </template>

                <template #item.actions="{ item }">
                    <div class="banner-actions">
                        <v-btn icon="mdi-pencil" size="small" variant="text" @click="editBanner(item)" />
                        <v-btn
                            :icon="item.is_published ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                            size="small"
                            variant="text"
                            @click="togglePublished(item)"
                        />
                        <v-btn icon="mdi-delete-outline" size="small" color="red" variant="text" @click="deleteBanner(item)" />
                    </div>
                </template>
            </v-data-table>
        </v-card>

        <v-dialog v-model="dialogOpen" max-width="1480" persistent>
            <v-card rounded="xl">
                <v-card-title class="d-flex align-center justify-space-between">
                    <span>{{ formTitle }}</span>
                    <v-btn icon="mdi-close" variant="text" @click="closeDialog" />
                </v-card-title>

                <v-divider />

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" lg="6">
                            <v-row dense>
                                <v-col cols="12" md="8">
                                    <v-text-field v-model="form.title" label="Заголовок" variant="outlined" density="compact" />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-text-field v-model.number="form.sort_order" label="Порядок" type="number" variant="outlined" density="compact" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.eyebrow" label="Надпись над заголовком" variant="outlined" density="compact" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-select v-model="form.size" :items="sizeOptions" label="Размер" variant="outlined" density="compact" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="form.subtitle" label="Подзаголовок" variant="outlined" density="compact" />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea v-model="form.description" label="Описание" variant="outlined" rows="3" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field
                                        v-model="form.image_url"
                                        label="Desktop image URL"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-monitor"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field
                                        v-model="form.mobile_image_url"
                                        label="Mobile image URL"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-cellphone"
                                    />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.cta_label" label="Текст кнопки" variant="outlined" density="compact" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-text-field v-model="form.cta_url" label="CTA URL" variant="outlined" density="compact" hint="Если пусто, ссылка строится по связанной сущности" persistent-hint />
                                </v-col>
                            </v-row>

                            <v-card rounded="xl" variant="tonal" class="mt-2">
                                <v-card-title class="text-subtitle-1">Превью</v-card-title>
                                <v-card-text>
                                    <div class="banner-form-preview">
                                        <img v-if="form.image_url || form.mobile_image_url" :src="form.image_url || form.mobile_image_url" :alt="form.title || 'Баннер'">
                                        <div v-else class="banner-form-preview__empty">Выберите изображение из S3</div>
                                    </div>
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col cols="12" lg="6">
                            <v-card rounded="xl" class="asset-manager" elevation="0">
                                <v-card-title class="asset-manager__title">
                                    <div>
                                        <span>S3 / Yandex Object Storage</span>
                                        <small>Файлы баннеров хранятся в папке banners</small>
                                    </div>
                                    <v-chip size="small" variant="tonal" color="orange">{{ assetTargetLabel }}</v-chip>
                                </v-card-title>

                                <v-card-text>
                                    <v-alert
                                        v-if="assetErrorMessage"
                                        type="error"
                                        variant="tonal"
                                        density="compact"
                                        class="mb-3"
                                    >
                                        {{ assetErrorMessage }}
                                    </v-alert>

                                    <div class="asset-manager__controls">
                                        <v-btn-toggle v-model="assetTargetField" mandatory density="compact" variant="outlined" rounded="xl">
                                            <v-btn value="image_url" size="small" prepend-icon="mdi-monitor">Desktop</v-btn>
                                            <v-btn value="mobile_image_url" size="small" prepend-icon="mdi-cellphone">Mobile</v-btn>
                                        </v-btn-toggle>

                                        <div class="asset-manager__buttons">
                                            <v-btn size="small" variant="tonal" prepend-icon="mdi-folder-plus-outline" @click="openFolderDialog">
                                                Папка
                                            </v-btn>
                                            <v-btn size="small" color="#8f1111" variant="flat" prepend-icon="mdi-cloud-upload-outline" :loading="uploadingAsset" @click="triggerAssetUpload">
                                                Загрузить
                                            </v-btn>
                                            <input
                                                ref="assetUploadInput"
                                                class="asset-upload-input"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
                                                @change="uploadAsset"
                                            >
                                        </div>
                                    </div>

                                    <div class="asset-path">
                                        <button
                                            v-for="breadcrumb in assetBreadcrumbs"
                                            :key="breadcrumb.folder || 'root'"
                                            type="button"
                                            @click="openAssetFolder(breadcrumb.folder)"
                                        >
                                            {{ breadcrumb.title }}
                                        </button>
                                    </div>

                                    <div class="asset-manager__subbar">
                                        <v-btn
                                            v-if="parentAssetFolder !== null"
                                            size="small"
                                            variant="text"
                                            prepend-icon="mdi-arrow-up-left"
                                            @click="openAssetFolder(parentAssetFolder)"
                                        >
                                            Наверх
                                        </v-btn>
                                        <v-btn size="small" variant="text" prepend-icon="mdi-refresh" :loading="assetLoading" @click="fetchAssets(assetFolder)">
                                            Обновить
                                        </v-btn>
                                    </div>

                                    <v-progress-linear v-if="assetLoading" indeterminate color="#8f1111" class="mb-3" />

                                    <div v-if="assetIsEmpty" class="asset-empty">
                                        В этой папке пока нет файлов. Загрузите баннер или создайте папку.
                                    </div>

                                    <div v-else class="asset-grid">
                                        <div v-for="folder in assetFolders" :key="folder.path" class="asset-card asset-card--folder">
                                            <button type="button" class="asset-card__main" @click="openAssetFolder(folder)">
                                                <span class="asset-card__folder-icon">mdi-folder</span>
                                                <strong>{{ folder.name }}</strong>
                                                <small>/{{ folder.relative_path }}</small>
                                            </button>
                                            <div class="asset-card__actions">
                                                <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" @click="openRenameAsset(folder)" />
                                                <v-btn icon="mdi-folder-move-outline" size="x-small" variant="text" @click="openMoveAsset(folder)" />
                                                <v-btn icon="mdi-delete-outline" size="x-small" variant="text" color="red" @click="deleteAsset(folder)" />
                                            </div>
                                        </div>

                                        <div v-for="file in assetFiles" :key="file.path" class="asset-card asset-card--file">
                                            <button type="button" class="asset-card__main" @click="useAsset(file)">
                                                <span class="asset-card__thumb">
                                                    <img v-if="file.is_image" :src="file.url" :alt="file.name">
                                                    <span v-else>{{ file.extension || 'file' }}</span>
                                                </span>
                                                <strong>{{ file.name }}</strong>
                                                <small>{{ assetSize(file.size) }}<span v-if="assetDate(file.last_modified)"> · {{ assetDate(file.last_modified) }}</span></small>
                                            </button>
                                            <div class="asset-card__quick-actions">
                                                <v-btn size="x-small" variant="tonal" @click="useAsset(file, 'image_url')">Desktop</v-btn>
                                                <v-btn size="x-small" variant="tonal" @click="useAsset(file, 'mobile_image_url')">Mobile</v-btn>
                                            </div>
                                            <div class="asset-card__actions">
                                                <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" @click="openRenameAsset(file)" />
                                                <v-btn icon="mdi-folder-move-outline" size="x-small" variant="text" @click="openMoveAsset(file)" />
                                                <v-btn icon="mdi-delete-outline" size="x-small" variant="text" color="red" @click="deleteAsset(file)" />
                                            </div>
                                        </div>
                                    </div>
                                </v-card-text>
                            </v-card>

                            <v-row dense class="mt-3">
                                <v-col cols="12" md="6">
                                    <v-card rounded="xl" variant="tonal" class="h-100">
                                        <v-card-title class="text-subtitle-1">Связи</v-card-title>
                                        <v-card-text>
                                            <v-autocomplete
                                                v-model="form.good_id"
                                                :items="goods"
                                                :item-title="goodTitle"
                                                item-value="id"
                                                label="Good"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                            />
                                            <v-autocomplete
                                                v-model="form.product_id"
                                                :items="products"
                                                :item-title="productTitle"
                                                item-value="id"
                                                label="Product"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                            />
                                            <v-autocomplete
                                                v-model="form.category_id"
                                                :items="categories"
                                                :item-title="categoryTitle"
                                                item-value="id"
                                                label="Category"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                            />
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-card rounded="xl" variant="tonal" class="h-100">
                                        <v-card-title class="text-subtitle-1">Показ</v-card-title>
                                        <v-card-text>
                                            <v-switch v-model="form.is_published" color="green" label="Опубликован" hide-details />
                                            <v-switch v-model="form.show_on_desktop" color="green" label="Desktop" hide-details />
                                            <v-switch v-model="form.show_on_mobile" color="green" label="Mobile" hide-details />
                                            <v-text-field v-model="form.starts_at" label="Начало показа" type="datetime-local" variant="outlined" density="compact" class="mt-3" />
                                            <v-text-field v-model="form.ends_at" label="Конец показа" type="datetime-local" variant="outlined" density="compact" />
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <v-col cols="12">
                                    <v-card rounded="xl" variant="tonal">
                                        <v-card-title class="text-subtitle-1">Цвета</v-card-title>
                                        <v-card-text>
                                            <v-row dense>
                                                <v-col cols="12" sm="4">
                                                    <v-text-field v-model="form.background_color" label="Фон" variant="outlined" density="compact" />
                                                </v-col>
                                                <v-col cols="12" sm="4">
                                                    <v-text-field v-model="form.text_color" label="Текст" variant="outlined" density="compact" />
                                                </v-col>
                                                <v-col cols="12" sm="4">
                                                    <v-text-field v-model="form.accent_color" label="Акцент" variant="outlined" density="compact" />
                                                </v-col>
                                            </v-row>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-divider />

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeDialog">Отмена</v-btn>
                    <v-btn color="#8f1111" rounded="xl" :loading="saving" @click="saveBanner">
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="folderDialogOpen" max-width="460">
            <v-card rounded="xl">
                <v-card-title>Новая папка в S3</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="folderForm.name"
                        label="Название папки"
                        variant="outlined"
                        density="compact"
                        hint="Будет создана внутри текущей папки"
                        persistent-hint
                        @keyup.enter="createAssetFolder"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="folderDialogOpen = false">Отмена</v-btn>
                    <v-btn color="#8f1111" rounded="xl" @click="createAssetFolder">Создать</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="renameDialogOpen" max-width="520">
            <v-card rounded="xl">
                <v-card-title>Переименовать {{ renameForm.type === 'folder' ? 'папку' : 'файл' }}</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="renameForm.name"
                        label="Новое имя"
                        variant="outlined"
                        density="compact"
                        hint="Для файла укажите расширение, если оно должно сохраниться"
                        persistent-hint
                        @keyup.enter="renameAsset"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="renameDialogOpen = false">Отмена</v-btn>
                    <v-btn color="#8f1111" rounded="xl" @click="renameAsset">Переименовать</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="moveDialogOpen" max-width="560">
            <v-card rounded="xl">
                <v-card-title>Переместить {{ moveForm.type === 'folder' ? 'папку' : 'файл' }}</v-card-title>
                <v-card-text>
                    <div class="move-object-name">{{ moveForm.name }}</div>
                    <v-combobox
                        v-model="moveForm.target_folder"
                        :items="moveFolderOptions"
                        item-title="title"
                        item-value="value"
                        label="Целевая папка"
                        variant="outlined"
                        density="compact"
                        clearable
                        hint="Пустое значение означает корень banners. Можно ввести путь вручную: seasonal/summer."
                        persistent-hint
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="moveDialogOpen = false">Отмена</v-btn>
                    <v-btn color="#8f1111" rounded="xl" @click="moveAsset">Переместить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.home-banners-admin {
    min-height: 100vh;
    background: #f8fafc;
}

.home-banners-admin__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
    padding: 24px;
    border-radius: 28px;
    background:
        radial-gradient(circle at 100% 0%, rgba(143, 17, 17, 0.12), transparent 28%),
        #fff;
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
}

.home-banners-admin__eyebrow {
    color: #8f1111;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.home-banners-admin h1 {
    margin: 6px 0 0;
    color: #1f2937;
    font-size: clamp(1.8rem, 3vw, 3rem);
    font-weight: 950;
    letter-spacing: -0.04em;
}

.home-banners-admin p {
    max-width: 760px;
    margin: 8px 0 0;
    color: #64748b;
}

.banner-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 280px;
    padding: 8px 0;
}

.banner-preview__image {
    display: grid;
    width: 96px;
    height: 58px;
    overflow: hidden;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 16px;
    background: #fff7ed;
    color: #8f1111;
    font-size: 1.4rem;
    font-weight: 950;
}

.banner-preview__image img,
.banner-form-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.banner-preview strong,
.banner-preview span {
    display: block;
}

.banner-preview strong {
    color: #1f2937;
    font-weight: 900;
}

.banner-preview span {
    margin-top: 3px;
    color: #64748b;
    font-size: 0.82rem;
}

.banner-form-preview {
    display: grid;
    min-height: 150px;
    overflow: hidden;
    place-items: center;
    border: 1px dashed rgba(143, 17, 17, 0.28);
    border-radius: 22px;
    background: #fff;
}

.banner-form-preview__empty {
    color: #94a3b8;
    font-size: 0.9rem;
    font-weight: 800;
}

.visibility-tags,
.relation-list,
.banner-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.relation-list span {
    padding: 4px 7px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 700;
}

.asset-manager {
    border: 1px solid rgba(143, 17, 17, 0.12);
    background:
        linear-gradient(135deg, rgba(255, 247, 237, 0.92), rgba(255, 255, 255, 0.94)),
        radial-gradient(circle at 10% 0%, rgba(242, 170, 0, 0.18), transparent 34%);
}

.asset-manager__title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.asset-manager__title span,
.asset-manager__title small {
    display: block;
}

.asset-manager__title span {
    color: #1f2937;
    font-weight: 950;
}

.asset-manager__title small {
    margin-top: 4px;
    color: #64748b;
    font-size: 0.76rem;
    font-weight: 700;
}

.asset-manager__controls,
.asset-manager__buttons,
.asset-manager__subbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.asset-manager__controls {
    justify-content: space-between;
    margin-bottom: 12px;
}

.asset-upload-input {
    display: none;
}

.asset-path {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}

.asset-path button {
    border: 0;
    border-radius: 999px;
    padding: 5px 10px;
    background: rgba(143, 17, 17, 0.08);
    color: #8f1111;
    cursor: pointer;
    font-size: 0.76rem;
    font-weight: 900;
}

.asset-path button:not(:last-child)::after {
    content: '/';
    margin-left: 8px;
    color: #c08447;
}

.asset-manager__subbar {
    justify-content: space-between;
    min-height: 34px;
    margin-bottom: 8px;
}

.asset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(156px, 1fr));
    gap: 10px;
    max-height: 520px;
    overflow: auto;
    padding-right: 4px;
}

.asset-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.24);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
}

.asset-card__main {
    display: block;
    width: 100%;
    border: 0;
    padding: 10px;
    background: transparent;
    color: inherit;
    cursor: pointer;
    text-align: left;
}

.asset-card__main strong,
.asset-card__main small {
    display: block;
}

.asset-card__main strong {
    overflow: hidden;
    color: #1f2937;
    font-size: 0.8rem;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.asset-card__main small {
    overflow: hidden;
    margin-top: 3px;
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.asset-card__folder-icon,
.asset-card__thumb {
    display: grid;
    width: 100%;
    height: 92px;
    margin-bottom: 8px;
    overflow: hidden;
    place-items: center;
    border-radius: 14px;
}

.asset-card__folder-icon {
    background: linear-gradient(135deg, #fde68a, #f97316);
    color: transparent;
}

.asset-card__folder-icon::before {
    content: '';
    width: 58px;
    height: 44px;
    border-radius: 9px 9px 12px 12px;
    background: #92400e;
    clip-path: polygon(0 24%, 36% 24%, 43% 8%, 100% 8%, 100% 100%, 0 100%);
    opacity: 0.92;
}

.asset-card__thumb {
    background: #f8fafc;
}

.asset-card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.asset-card__thumb span {
    color: #8f1111;
    font-size: 0.82rem;
    font-weight: 950;
    text-transform: uppercase;
}

.asset-card__quick-actions,
.asset-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 0 8px 8px;
}

.asset-card__actions {
    justify-content: flex-end;
    padding-top: 0;
}

.asset-empty {
    display: grid;
    min-height: 140px;
    place-items: center;
    border: 1px dashed rgba(148, 163, 184, 0.5);
    border-radius: 18px;
    color: #64748b;
    font-weight: 800;
    text-align: center;
}

.move-object-name {
    margin-bottom: 12px;
    padding: 8px 10px;
    border-radius: 12px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.86rem;
    font-weight: 900;
}

@media (max-width: 720px) {
    .home-banners-admin__header {
        flex-direction: column;
    }

    .asset-manager__controls {
        align-items: stretch;
        flex-direction: column;
    }

    .asset-grid {
        grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
        max-height: 420px;
    }
}
</style>
