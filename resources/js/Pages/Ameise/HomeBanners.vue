<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import HomeBannerStrip from '@/Components/Home/HomeBannerStrip.vue'
import { bannerProductionBrief, moscowDateTimeInput, moscowDateTimePayload, moveMobileSlot, validMobileOrder } from './homeBannerAdmin'
import axios from 'axios'
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useHead } from '@unhead/vue'

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
const formErrorMessage = ref('')
const fieldErrors = ref({})
const successMessage = ref('')
const settingsSaving = ref(false)
const settingsLoading = ref(true)
const settingsLoaded = ref(false)
const settingsErrorMessage = ref('')
const settingsFieldErrors = ref({})
const actionPending = ref(null)
const editingPending = ref(null)
const statusFilter = ref(null)
const slotFilter = ref(null)
const previewDevice = ref('desktop')
const formPreviewScroll = ref(null)
const copiedBrief = ref(false)
const slotNumbers = [1, 2, 3, 4, 5, 6]
const activeFeed = reactive({ desktop: Array(6).fill(null), mobile: Array(6).fill(null) })
const settings = reactive({
    enabled: true, desktop_height: 96, gap: 8, mobile_enabled: true,
    mobile_layout: 'scroll', mobile_height: 96, mobile_columns: 2,
    mobile_hide_empty: true, mobile_order: [1, 2, 3, 4, 5, 6],
})
const savedSettings = ref(null)
const settingsDirty = computed(() => JSON.stringify(settings) !== savedSettings.value)
const slotOptions = slotNumbers.map((value) => ({ title: `Слот ${value}`, value }))
const contentOptions = [
    { title: 'Готовое изображение с надписями', value: 'image' },
    { title: 'Изображение и текст поверх него', value: 'overlay' },
    { title: 'Текст на цветном фоне', value: 'text' },
]
const fitOptions = [{ title: 'Вписать полностью', value: 'contain' }, { title: 'Заполнить с обрезкой', value: 'cover' }]
const colorFields = [
    { key: 'background_color', label: 'Фон', fallback: '#f5f2ed' },
    { key: 'text_color', label: 'Текст', fallback: '#292624' },
    { key: 'accent_color', label: 'Акцент', fallback: '#800000' },
]
const positionOptions = [
    { title: 'Слева сверху', value: 'left top' }, { title: 'Сверху по центру', value: 'center top' }, { title: 'Справа сверху', value: 'right top' },
    { title: 'Слева по центру', value: 'left center' }, { title: 'По центру', value: 'center center' }, { title: 'Справа по центру', value: 'right center' },
    { title: 'Слева снизу', value: 'left bottom' }, { title: 'Снизу по центру', value: 'center bottom' }, { title: 'Справа снизу', value: 'right bottom' },
]
const statusOptions = [
    { title: 'Сейчас в эфире', value: 'active', color: 'green' },
    { title: 'Запланирован', value: 'scheduled', color: 'blue' },
    { title: 'Срок завершён', value: 'expired', color: 'grey' },
    { title: 'Черновик', value: 'draft', color: 'grey' },
    { title: 'Без слота', value: 'unassigned', color: 'orange' },
    { title: 'Скрыт на устройствах', value: 'hidden', color: 'orange' },
]
const filteredBanners = computed(() => banners.value.filter((banner) =>
    (!statusFilter.value || banner.published_status === statusFilter.value) &&
    (!slotFilter.value || banner.slot_number === slotFilter.value)))
const currentPreviewFeed = computed(() => ({ settings: { ...settings }, ...activeFeed }))
const formPreviewFeed = computed(() => {
    const desktop = Array(6).fill(null)
    const mobile = Array(6).fill(null)
    const slot = Math.max(0, (form.slot_number || 1) - 1)
    const banner = { ...form, id: editingId.value || 'preview' }
    desktop[slot] = banner
    mobile[slot] = banner
    return { settings: { ...settings, enabled: true, mobile_enabled: true, mobile_hide_empty: false }, desktop, mobile }
})

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

const headers = [
    { title: 'Баннер', key: 'preview', sortable: false },
    { title: 'Статус и сроки · МСК', key: 'is_published', width: 230 },
    { title: 'Слот', key: 'slot_number', width: 85 },
    { title: 'Видимость', key: 'visibility', sortable: false, width: 150 },
    { title: 'Связи', key: 'relations', sortable: false },
    { title: 'Порядок', key: 'sort_order', width: 110 },
    { title: '', key: 'actions', sortable: false, width: 150 },
]

const form = reactive(defaultForm())

watch([dialogOpen, previewDevice, () => form.slot_number, () => settings.mobile_layout, () => settings.mobile_order.join(',')], async () => {
    if (!dialogOpen.value) return
    await nextTick()
    const viewport = formPreviewScroll.value
    if (!viewport) return
    const slot = form.slot_number || 1
    if (previewDevice.value === 'desktop') {
        viewport.scrollLeft = Math.max(0, ((slot - 1) * 1200 / 6) - 16)
    } else {
        viewport.scrollLeft = 0
        const track = viewport.querySelector('.home-banner-strip__track--scroll')
        const selected = track?.querySelector(`[data-slot="${slot}"]`)
        if (track && selected) track.scrollLeft = selected.offsetLeft - track.offsetLeft
    }
}, { flush: 'post' })

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
        size: 'compact',
        slot_number: null,
        content_mode: 'image',
        image_fit: 'contain',
        image_position: 'center center',
        mobile_image_fit: 'contain',
        mobile_image_position: 'center center',
        text_align: 'left',
        vertical_align: 'center',
        alt_text: '',
        open_in_new_tab: false,
        is_published: false,
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
    const errors = error.response?.data?.errors
    const detail = Object.values(errors || {}).flat().filter(Boolean).join(' ')
    return detail || error.response?.data?.message || fallback
}

async function fetchSettings() {
    settingsLoading.value = true
    settingsErrorMessage.value = ''
    try {
        const { data } = await axios.get('/api/home-banner-settings')
        Object.assign(settings, data.data)
        settings.mobile_order = [...(data.data?.mobile_order || slotNumbers)]
        Object.assign(activeFeed, data.feed)
        savedSettings.value = JSON.stringify(settings)
        settingsLoaded.value = true
    } catch (error) {
        settingsErrorMessage.value = apiMessage(error, 'Не удалось загрузить настройки ленты. Изменения пока недоступны.')
    } finally {
        settingsLoading.value = false
    }
}

async function refreshFeed() {
    try {
        const { data } = await axios.get('/api/home-banner-settings')
        Object.assign(activeFeed, data.feed)
    } catch (error) {
        errorMessage.value = apiMessage(error, 'Баннер сохранён, но не удалось обновить состояние слотов. Обновите страницу.')
    }
}

async function saveSettings() {
    if (settingsSaving.value) return
    settingsErrorMessage.value = ''
    successMessage.value = ''
    settingsFieldErrors.value = {}
    if (!validMobileOrder(settings.mobile_order)) {
        settingsErrorMessage.value = 'Порядок мобильных слотов должен содержать каждый номер от 1 до 6 ровно один раз.'
        return
    }
    settingsSaving.value = true
    try {
        const { data } = await axios.patch('/api/home-banner-settings', { ...settings })
        Object.assign(settings, data.data)
        Object.assign(activeFeed, data.feed)
        savedSettings.value = JSON.stringify(settings)
        successMessage.value = 'Настройки ленты сохранены.'
    } catch (error) {
        settingsFieldErrors.value = error.response?.data?.errors || {}
        settingsErrorMessage.value = apiMessage(error, 'Не удалось сохранить настройки ленты.')
    } finally {
        settingsSaving.value = false
    }
}

function moveSlot(index, direction) {
    settings.mobile_order = moveMobileSlot(settings.mobile_order, index, direction)
}

function statusInfo(banner) {
    return statusOptions.find((status) => status.value === banner.published_status) ||
        { title: banner.is_published ? 'Опубликован' : 'Черновик', color: banner.is_published ? 'green' : 'grey' }
}

function formattedDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU', { timeZone: 'Europe/Moscow', dateStyle: 'short', timeStyle: 'short' }) : ''
}

function dateWindow(banner) {
    return `${banner.starts_at ? `С ${formattedDate(banner.starts_at)}` : 'Начало: сразу'} · ${banner.ends_at ? `до ${formattedDate(banner.ends_at)}` : 'без окончания'}`
}

function selectedSlotCampaigns(slot) {
    return banners.value.filter((banner) => banner.slot_number === slot)
}

async function copyProductionBrief() {
    try {
        await navigator.clipboard.writeText(bannerProductionBrief(form, previewDevice.value))
        copiedBrief.value = true
        window.setTimeout(() => { copiedBrief.value = false }, 3000)
    } catch {
        formErrorMessage.value = 'Не удалось скопировать ТЗ. Проверьте доступ браузера к буферу обмена.'
    }
}

async function fetchBanners() {
    loading.value = true
    errorMessage.value = ''

    try {
        const allBanners = []
        let page = 1
        let lastPage = 1
        do {
            const { data } = await axios.get('/api/home-banners', {
                params: { search: search.value || undefined, per_page: 300, page },
            })
            allBanners.push(...(data.data || []))
            lastPage = Number(data.last_page) || 1
            page += 1
        } while (page <= lastPage)
        banners.value = allBanners
    } catch (error) {
        console.error(error)
        errorMessage.value = 'Не удалось загрузить баннеры.'
    } finally {
        loading.value = false
    }
}

async function fetchDictionaries() {
    const results = await Promise.allSettled([
        axios.get('/api/goods', { params: { per_page: 500, sort_by: 'name' } }),
        axios.get('/api/products'),
        axios.get('/api/categories', { params: { per_page: 500, sortBy: 'name' } }),
    ])

    const targets = [goods, products, categories]
    results.forEach((result, index) => {
        if (result.status === 'fulfilled') targets[index].value = normalizeList(result.value)
        else errorMessage.value = 'Не удалось загрузить часть справочников каталога. Для баннера можно указать прямую ссылку.'
    })
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

function openCreateDialog(slot = null) {
    editingId.value = null
    Object.assign(form, defaultForm())
    form.slot_number = typeof slot === 'number' ? slot : null
    formErrorMessage.value = ''
    fieldErrors.value = {}
    copiedBrief.value = false
    dialogOpen.value = true
    void fetchAssets(assetFolder.value)
}

function editBanner(banner) {
    editingId.value = banner.id
    Object.assign(form, {
        ...defaultForm(),
        ...banner,
        starts_at: moscowDateTimeInput(banner.starts_at),
        ends_at: moscowDateTimeInput(banner.ends_at),
    })
    formErrorMessage.value = ''
    fieldErrors.value = {}
    copiedBrief.value = false
    dialogOpen.value = true
    void fetchAssets(assetFolder.value)
}

async function editFeedBanner(banner) {
    if (editingPending.value !== null) return
    editingPending.value = banner.id
    errorMessage.value = ''
    try {
        const { data } = await axios.get(`/api/home-banners/${banner.id}`)
        editBanner(data.data)
    } catch (error) {
        errorMessage.value = apiMessage(error, 'Не удалось открыть баннер для редактирования. Попробуйте ещё раз.')
    } finally {
        editingPending.value = null
    }
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
        starts_at: moscowDateTimePayload(form.starts_at),
        ends_at: moscowDateTimePayload(form.ends_at),
    }
}

async function saveBanner() {
    if (saving.value) return
    saving.value = true
    formErrorMessage.value = ''
    fieldErrors.value = {}
    successMessage.value = ''

    try {
        if (editingId.value) {
            await axios.patch(`/api/home-banners/${editingId.value}`, payload())
        } else {
            await axios.post('/api/home-banners', payload())
        }

        closeDialog()
        await fetchBanners()
        await refreshFeed()
        successMessage.value = 'Баннер сохранён.'
    } catch (error) {
        console.error(error)
        fieldErrors.value = error.response?.data?.errors || {}
        formErrorMessage.value = apiMessage(error, 'Не удалось сохранить баннер.')
    } finally {
        saving.value = false
    }
}

async function togglePublished(banner) {
    if (actionPending.value !== null) return
    actionPending.value = banner.id
    errorMessage.value = ''
    successMessage.value = ''
    try {
        await axios.patch(`/api/home-banners/${banner.id}`, { is_published: !banner.is_published })
        await fetchBanners()
        await refreshFeed()
        successMessage.value = banner.is_published ? 'Баннер снят с публикации.' : 'Публикация баннера включена.'
    } catch (error) {
        errorMessage.value = apiMessage(error, 'Не удалось изменить публикацию баннера.')
    } finally {
        actionPending.value = null
    }
}

async function deleteBanner(banner) {
    if (actionPending.value !== null) return
    if (!window.confirm(`Удалить баннер "${banner.title}"?`)) {
        return
    }

    actionPending.value = banner.id
    errorMessage.value = ''
    successMessage.value = ''
    try {
        await axios.delete(`/api/home-banners/${banner.id}`)
        await fetchBanners()
        await refreshFeed()
        successMessage.value = 'Баннер удалён.'
    } catch (error) {
        errorMessage.value = apiMessage(error, 'Не удалось удалить баннер.')
    } finally {
        actionPending.value = null
    }
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
        fetchSettings(),
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
                <div class="home-banners-admin__eyebrow">Ameise / Главная</div>
                <h1>Лента баннеров</h1>
                <p>Шесть фиксированных слотов между первым блоком главной и поиском по товарам. Управляйте предложениями, сроками и показом на телефонах.</p>
            </div>

            <v-btn color="#8f1111" rounded="xl" prepend-icon="mdi-plus" @click="openCreateDialog()">
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

        <v-alert v-if="successMessage" type="success" variant="tonal" closable class="mb-4" @click:close="successMessage = ''">
            {{ successMessage }}
        </v-alert>

        <v-card rounded="xl" elevation="1" class="mb-4">
            <v-card-title class="admin-card-heading">
                <span>Настройки ленты</span>
                <v-chip :color="settings.enabled ? 'green' : 'grey'" size="small" variant="tonal">{{ settings.enabled ? 'Лента включена' : 'Лента выключена' }}</v-chip>
            </v-card-title>
            <v-card-text>
                <v-progress-linear v-if="settingsLoading" indeterminate class="mb-4" />
                <v-alert v-if="settingsErrorMessage" type="error" variant="tonal" class="mb-4">{{ settingsErrorMessage }}</v-alert>
                <v-btn v-if="!settingsLoaded && !settingsLoading" variant="tonal" class="mb-4" @click="fetchSettings">Повторить загрузку настроек</v-btn>
                <fieldset class="settings-fieldset" :disabled="!settingsLoaded || settingsSaving">
                    <v-row dense>
                        <v-col cols="12" md="3"><v-switch v-model="settings.enabled" label="Показывать ленту на главной" color="green" hide-details /></v-col>
                        <v-col cols="12" sm="6" md="3"><v-text-field v-model.number="settings.desktop_height" type="number" min="60" max="160" label="Высота на компьютере, px" density="compact" variant="outlined" :error-messages="settingsFieldErrors.desktop_height" /></v-col>
                        <v-col cols="12" sm="6" md="3"><v-text-field v-model.number="settings.gap" type="number" min="4" max="20" label="Расстояние между слотами, px" density="compact" variant="outlined" :error-messages="settingsFieldErrors.gap" /></v-col>
                        <v-col cols="12" md="3"><v-switch v-model="settings.mobile_enabled" label="Показывать на телефонах" color="green" hide-details /></v-col>
                        <v-col cols="12" sm="6" md="3"><v-select v-model="settings.mobile_layout" :items="[{ title: 'Один ряд с прокруткой', value: 'scroll' }, { title: 'Компактная сетка', value: 'grid' }]" label="Расположение на телефоне" density="compact" variant="outlined" :error-messages="settingsFieldErrors.mobile_layout" /></v-col>
                        <v-col cols="12" sm="6" md="3"><v-text-field v-model.number="settings.mobile_height" type="number" min="60" max="160" label="Высота мобильного баннера, px" density="compact" variant="outlined" :error-messages="settingsFieldErrors.mobile_height" /></v-col>
                        <v-col cols="12" sm="6" md="3"><v-select v-model="settings.mobile_columns" :items="[{ title: 'Один баннер', value: 1 }, { title: 'Два баннера', value: 2 }]" label="Баннеров в ряду на телефоне" density="compact" variant="outlined" :error-messages="settingsFieldErrors.mobile_columns" /></v-col>
                        <v-col cols="12" sm="6" md="3"><v-switch v-model="settings.mobile_hide_empty" label="Скрывать пустые слоты на телефоне" color="green" hide-details /></v-col>
                    </v-row>
                    <div class="mobile-order-label">Порядок слотов на телефоне</div>
                    <div class="mobile-slot-order">
                        <div v-for="(slot, index) in settings.mobile_order" :key="slot" class="mobile-slot-order__item">
                            <v-btn icon="mdi-chevron-left" size="x-small" variant="text" :disabled="index === 0" :aria-label="`Переместить слот ${slot} раньше`" @click="moveSlot(index, -1)" />
                            <strong>{{ slot }}</strong>
                            <v-btn icon="mdi-chevron-right" size="x-small" variant="text" :disabled="index === 5" :aria-label="`Переместить слот ${slot} позже`" @click="moveSlot(index, 1)" />
                        </div>
                    </div>
                    <div class="settings-save-row">
                        <span>{{ settingsDirty ? 'Изменения ещё не сохранены' : 'Все настройки сохранены' }} · На компьютере всегда 6 слотов в одном ряду.</span>
                        <v-btn color="#8f1111" rounded="xl" :loading="settingsSaving" :disabled="!settingsLoaded || !settingsDirty" @click="saveSettings">Сохранить настройки</v-btn>
                    </div>
                </fieldset>
            </v-card-text>
        </v-card>

        <v-card rounded="xl" elevation="1" class="mb-4">
            <v-card-title class="admin-card-heading"><span>Шесть слотов · сейчас в эфире</span><v-btn size="small" variant="text" prepend-icon="mdi-refresh" @click="refreshFeed">Обновить</v-btn></v-card-title>
            <v-card-text>
                <p class="admin-helper">Пустой слот остаётся зарезервирован на компьютере. Для одного слота можно подготовить будущие кампании с непересекающимися сроками; показ на компьютере и телефоне планируется отдельно.</p>
                <div class="slot-overview">
                    <div v-for="slot in slotNumbers" :key="slot" class="slot-overview__item">
                        <div class="slot-overview__heading"><strong>Слот {{ slot }}</strong><v-btn icon="mdi-plus" size="x-small" variant="text" :aria-label="`Создать баннер для слота ${slot}`" @click="openCreateDialog(slot)" /></div>
                        <div v-for="device in ['desktop', 'mobile']" :key="device" class="slot-overview__device">
                            <span><v-icon :icon="device === 'desktop' ? 'mdi-monitor' : 'mdi-cellphone'" size="14" /> {{ device === 'desktop' ? 'Компьютер' : 'Телефон' }}</span>
                            <button v-if="activeFeed[device][slot - 1]" type="button" class="slot-overview__banner" :disabled="editingPending !== null" @click="editFeedBanner(activeFeed[device][slot - 1])">
                                <img v-if="(device === 'mobile' && activeFeed[device][slot - 1].mobile_image_url) || activeFeed[device][slot - 1].image_url" :src="device === 'mobile' ? activeFeed[device][slot - 1].mobile_image_url || activeFeed[device][slot - 1].image_url : activeFeed[device][slot - 1].image_url" alt="">
                                <strong>{{ activeFeed[device][slot - 1].title }}</strong>
                            </button>
                            <div v-else class="slot-overview__empty">Свободен</div>
                        </div>
                        <small>{{ selectedSlotCampaigns(slot).length }} кампаний в списке</small>
                        <v-btn block size="small" variant="tonal" class="mt-2" @click="openCreateDialog(slot)">Запланировать</v-btn>
                    </div>
                </div>
                <div class="preview-heading"><strong>Проверка отображения ленты</strong><v-btn-toggle v-model="previewDevice" mandatory density="compact" variant="outlined"><v-btn value="desktop" size="small">Компьютер</v-btn><v-btn value="mobile" size="small">Телефон</v-btn></v-btn-toggle></div>
                <div class="strip-preview-scroll">
                    <div class="strip-preview-frame" :class="`strip-preview-frame--${previewDevice}`">
                        <HomeBannerStrip :feed="currentPreviewFeed" :preview-device="previewDevice" />
                    </div>
                </div>
                <small class="admin-helper">Превью в реальном масштабе: ширина {{ previewDevice === 'desktop' ? '1200' : '390' }} px. Изменения общих настроек отображаются до сохранения.</small>
            </v-card-text>
        </v-card>

        <v-expansion-panels class="mb-4" variant="accordion">
            <v-expansion-panel title="Размеры и рекомендации для создания баннера">
                <v-expansion-panel-text>
                    <div class="banner-size-guide"><strong>800 × 400 px · 2:1</strong><span>Рекомендуемый исходник: 1600 × 800 px для чёткого отображения. Для компьютера и телефона можно загрузить разные изображения с тем же соотношением сторон.</span><span>WebP или PNG, рекомендуемый вес до 1 МБ. Оставьте безопасную зону 10% по краям. Используйте крупные надписи и избегайте мелких деталей: баннер показывается в невысокой ленте.</span><span>В режиме «Вписать полностью» изображение сохраняется целиком; «Заполнить с обрезкой» использует выбранную точку выравнивания. Для готового баннера выбирайте режим «Готовое изображение с надписями».</span><span>В форме каждого баннера есть кнопка «Скопировать ТЗ для ChatGPT»: она учитывает текст, цвета, слот и выбранное устройство.</span></div>
                </v-expansion-panel-text>
            </v-expansion-panel>
        </v-expansion-panels>

        <v-card rounded="xl" elevation="1" class="mb-4">
            <v-card-text>
                <v-row align="center" dense>
                    <v-col cols="12" md="5">
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
                    <v-col cols="12" md="3"><v-select v-model="statusFilter" :items="statusOptions" label="Все статусы" clearable density="compact" variant="outlined" hide-details /></v-col>
                    <v-col cols="12" md="2"><v-select v-model="slotFilter" :items="slotOptions" label="Все слоты" clearable density="compact" variant="outlined" hide-details /></v-col>
                    <v-col cols="12" md="2">
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
                :items="filteredBanners"
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
                    <v-chip :color="statusInfo(item).color" size="small" variant="tonal">
                        {{ statusInfo(item).title }}
                    </v-chip>
                    <small class="banner-schedule">{{ dateWindow(item) }}</small>
                </template>

                <template #item.slot_number="{ item }">
                    <strong>{{ item.slot_number || '—' }}</strong>
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
                        <v-btn icon="mdi-pencil" size="small" variant="text" aria-label="Редактировать баннер" :disabled="actionPending !== null" @click="editBanner(item)" />
                        <v-btn
                            :icon="item.is_published ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                            size="small"
                            variant="text"
                            :aria-label="item.is_published ? 'Снять с публикации' : 'Опубликовать'"
                            :loading="actionPending === item.id"
                            :disabled="actionPending !== null && actionPending !== item.id"
                            @click="togglePublished(item)"
                        />
                        <v-btn icon="mdi-delete-outline" size="small" color="red" variant="text" aria-label="Удалить баннер" :disabled="actionPending !== null" @click="deleteBanner(item)" />
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
                    <v-alert v-if="formErrorMessage" type="error" variant="tonal" class="mb-4">{{ formErrorMessage }}</v-alert>
                    <v-row dense>
                        <v-col cols="12" lg="6">
                            <v-row dense>
                                <v-col cols="12" md="8">
                                    <v-text-field v-model="form.title" label="Название / заголовок баннера" variant="outlined" density="compact" :error-messages="fieldErrors.title" />
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-text-field v-model.number="form.sort_order" label="Порядок" type="number" variant="outlined" density="compact" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-select v-model="form.slot_number" :items="slotOptions" label="Фиксированный слот" clearable variant="outlined" density="compact" hint="Без слота баннер хранится в списке, но не показывается в ленте" persistent-hint :error-messages="fieldErrors.slot_number" />
                                </v-col>
                                <v-col cols="12" md="6">
                                    <v-select v-model="form.content_mode" :items="contentOptions" label="Содержимое баннера" variant="outlined" density="compact" :error-messages="fieldErrors.content_mode" />
                                </v-col>
                                <v-col cols="12" v-if="form.content_mode !== 'image'">
                                    <v-text-field v-model="form.subtitle" label="Подзаголовок" variant="outlined" density="compact" :error-messages="fieldErrors.subtitle" />
                                </v-col>
                                <v-col cols="12">
                                    <v-textarea v-model="form.description" label="Описание предложения для ТЗ" variant="outlined" rows="2" hint="Помогает подготовить задание для изображения; на сайте выводятся заголовок и подзаголовок" persistent-hint :error-messages="fieldErrors.description" />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field
                                        v-model="form.image_url"
                                        label="Изображение для компьютера · URL"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-monitor"
                                        :error-messages="fieldErrors.image_url"
                                        :disabled="form.content_mode === 'text'"
                                    />
                                </v-col>
                                <v-col cols="12">
                                    <v-text-field
                                        v-model="form.mobile_image_url"
                                        label="Изображение для телефона · URL"
                                        variant="outlined"
                                        density="compact"
                                        prepend-inner-icon="mdi-cellphone"
                                        hint="Если не задано, используется изображение для компьютера"
                                        persistent-hint
                                        :error-messages="fieldErrors.mobile_image_url"
                                        :disabled="form.content_mode === 'text'"
                                    />
                                </v-col>
                                <v-col cols="12" md="6"><v-select v-model="form.image_fit" :items="fitOptions" label="Компьютер: масштабирование" variant="outlined" density="compact" :disabled="form.content_mode === 'text'" :error-messages="fieldErrors.image_fit" /></v-col>
                                <v-col cols="12" md="6"><v-select v-model="form.image_position" :items="positionOptions" label="Компьютер: выравнивание изображения" variant="outlined" density="compact" :disabled="form.content_mode === 'text'" :error-messages="fieldErrors.image_position" /></v-col>
                                <v-col cols="12" md="6"><v-select v-model="form.mobile_image_fit" :items="fitOptions" label="Телефон: масштабирование" variant="outlined" density="compact" :disabled="form.content_mode === 'text'" :error-messages="fieldErrors.mobile_image_fit" /></v-col>
                                <v-col cols="12" md="6"><v-select v-model="form.mobile_image_position" :items="positionOptions" label="Телефон: выравнивание изображения" variant="outlined" density="compact" :disabled="form.content_mode === 'text'" :error-messages="fieldErrors.mobile_image_position" /></v-col>
                                <v-col cols="12" md="6" v-if="form.content_mode !== 'image'"><v-select v-model="form.text_align" :items="[{ title: 'Слева', value: 'left' }, { title: 'По центру', value: 'center' }, { title: 'Справа', value: 'right' }]" label="Выравнивание текста" variant="outlined" density="compact" :error-messages="fieldErrors.text_align" /></v-col>
                                <v-col cols="12" md="6" v-if="form.content_mode !== 'image'"><v-select v-model="form.vertical_align" :items="[{ title: 'Сверху', value: 'top' }, { title: 'По центру', value: 'center' }, { title: 'Снизу', value: 'bottom' }]" label="Текст по вертикали" variant="outlined" density="compact" :error-messages="fieldErrors.vertical_align" /></v-col>
                                <v-col cols="12"><v-text-field v-model="form.alt_text" label="Альтернативный текст изображения" variant="outlined" density="compact" hint="Кратко опишите предложение для людей, использующих озвучивание страницы" :error-messages="fieldErrors.alt_text" /></v-col>
                                <v-col cols="12">
                                    <v-text-field v-model="form.cta_url" label="Ссылка при нажатии" variant="outlined" density="compact" hint="Если пусто, используется связанный товар / категория или каталог" persistent-hint :error-messages="fieldErrors.cta_url" />
                                    <div class="admin-helper mb-4">Поиск в каталоге: <code>/g?search=облепиха</code>. Пробелы заменяйте на <code>%20</code>: <code>/g?search=масло%20облепихи</code>.</div>
                                </v-col>
                                <v-col cols="12"><v-switch v-model="form.open_in_new_tab" label="Открывать ссылку в новой вкладке" color="green" hide-details :error-messages="fieldErrors.open_in_new_tab" /></v-col>
                            </v-row>

                            <v-card rounded="xl" variant="tonal" class="mt-2">
                                <v-card-title class="admin-card-heading"><span class="text-subtitle-1">Превью в ленте</span><v-btn-toggle v-model="previewDevice" mandatory density="compact" variant="outlined"><v-btn value="desktop" size="small">Компьютер</v-btn><v-btn value="mobile" size="small">Телефон</v-btn></v-btn-toggle></v-card-title>
                                <v-card-text>
                                    <div ref="formPreviewScroll" class="strip-preview-scroll">
                                        <div class="strip-preview-frame" :class="`strip-preview-frame--${previewDevice}`">
                                            <HomeBannerStrip :feed="formPreviewFeed" :preview-device="previewDevice" />
                                        </div>
                                    </div>
                                    <small class="admin-helper">Слот {{ form.slot_number || '1 (пример; слот не выбран)' }} · превью показывает оформление независимо от публикации и дат.</small>
                                    <div class="banner-size-guide mt-4"><strong>Исходник: 800 × 400 px, рекомендуется 1600 × 800 px</strong><span>Соотношение 2:1 · WebP / PNG · до 1 МБ · безопасная зона 10% · крупные читаемые надписи.</span></div>
                                    <v-btn class="mt-3" block variant="tonal" prepend-icon="mdi-content-copy" @click="copyProductionBrief">{{ copiedBrief ? 'ТЗ скопировано' : `Скопировать ТЗ для ChatGPT · ${previewDevice === 'desktop' ? 'компьютер' : 'телефон'}` }}</v-btn>
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
                                                label="Товар"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                                :error-messages="fieldErrors.good_id"
                                            />
                                            <v-autocomplete
                                                v-model="form.product_id"
                                                :items="products"
                                                :item-title="productTitle"
                                                item-value="id"
                                                label="Продукт"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                                :error-messages="fieldErrors.product_id"
                                            />
                                            <v-autocomplete
                                                v-model="form.category_id"
                                                :items="categories"
                                                :item-title="categoryTitle"
                                                item-value="id"
                                                label="Категория"
                                                variant="outlined"
                                                density="compact"
                                                clearable
                                                :error-messages="fieldErrors.category_id"
                                            />
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-card rounded="xl" variant="tonal" class="h-100">
                                        <v-card-title class="text-subtitle-1">Публикация и сроки</v-card-title>
                                        <v-card-text>
                                            <v-switch v-model="form.is_published" color="green" label="Опубликован" hide-details />
                                            <v-switch v-model="form.show_on_desktop" color="green" label="Показывать на компьютере" hide-details />
                                            <v-switch v-model="form.show_on_mobile" color="green" label="Показывать на телефоне" hide-details />
                                            <div class="admin-helper mt-3">Все даты по Москве · UTC+03:00. Пустое начало — сразу, пустое окончание — бессрочно.</div>
                                            <v-text-field v-model="form.starts_at" label="Начало показа · МСК" type="datetime-local" variant="outlined" density="compact" class="mt-3" clearable :error-messages="fieldErrors.starts_at" />
                                            <v-text-field v-model="form.ends_at" label="Окончание показа · МСК" type="datetime-local" variant="outlined" density="compact" clearable :error-messages="fieldErrors.ends_at" />
                                            <div class="admin-helper">Система проверяет пересечения сроков в выбранном слоте и на выбранных устройствах. Будущая кампания начнётся автоматически.</div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>

                                <v-col cols="12">
                                    <v-card rounded="xl" variant="tonal">
                                        <v-card-title class="text-subtitle-1">Цвета</v-card-title>
                                        <v-card-text>
                                            <v-row dense>
                                                <v-col v-for="field in colorFields" :key="field.key" cols="12" sm="4">
                                                    <v-text-field v-model="form[field.key]" :label="field.label" variant="outlined" density="compact" :error-messages="fieldErrors[field.key]">
                                                        <template #prepend-inner>
                                                            <span class="banner-color-swatch" :style="{ '--color-preview': form[field.key] || field.fallback }" aria-hidden="true" />
                                                        </template>
                                                    </v-text-field>
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
.banner-color-swatch {
    position: relative;
    display: inline-block;
    flex: 0 0 30px;
    width: 30px;
    height: 28px;
    margin-inline-end: 6px;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.25);
    border-radius: 6px;
    background: repeating-conic-gradient(#d9d9d9 0% 25%, #fff 0% 50%) 50% / 10px 10px;
}

.banner-color-swatch::after {
    position: absolute;
    inset: 0;
    background-color: var(--color-preview);
    content: '';
}

.settings-fieldset {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.admin-card-heading,
.preview-heading,
.settings-save-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.admin-card-heading {
    padding: 18px 24px 8px;
    font-weight: 750;
    white-space: normal;
}

.admin-helper,
.banner-schedule {
    display: block;
    color: #64748b;
    font-size: 12px;
    line-height: 1.6;
}

.banner-schedule {
    margin-top: 6px;
    max-width: 235px;
}

.mobile-order-label {
    margin: 4px 0 10px;
    color: #334155;
    font-size: 13px;
    font-weight: 650;
}

.mobile-slot-order {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.mobile-slot-order__item {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
}

.settings-save-row {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.settings-save-row span {
    color: #64748b;
    font-size: 12px;
}

.slot-overview {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
    margin-top: 16px;
}

.slot-overview__item {
    min-width: 0;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
}

.slot-overview__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    margin-bottom: 8px;
    color: #334155;
    font-size: 13px;
}

.slot-overview__device > span,
.slot-overview__item > small {
    display: block;
    margin: 8px 0 5px;
    color: #64748b;
    font-size: 10px;
}

.slot-overview__banner,
.slot-overview__empty {
    display: flex;
    width: 100%;
    height: 60px;
    overflow: hidden;
    align-items: center;
    gap: 6px;
    padding: 6px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #475569;
    text-align: left;
    font-size: 11px;
}

.slot-overview__banner img {
    width: 44%;
    height: 100%;
    object-fit: contain;
}

.slot-overview__banner strong {
    display: -webkit-box;
    overflow: hidden;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
}

.slot-overview__empty {
    justify-content: center;
    border-style: dashed;
    color: #94a3b8;
}

.preview-heading {
    margin: 24px 0 12px;
    font-size: 13px;
}

.strip-preview-scroll {
    max-width: 100%;
    overflow: auto;
    margin-bottom: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.strip-preview-frame--desktop {
    width: 1200px;
}

.strip-preview-frame--mobile {
    width: 390px;
}

.strip-preview-frame :deep(a) {
    pointer-events: none;
}

.banner-size-guide {
    display: grid;
    gap: 8px;
    color: #475569;
    font-size: 13px;
    line-height: 1.6;
}

.banner-size-guide strong {
    color: #334155;
}

@media (max-width: 1200px) {
    .slot-overview {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .slot-overview {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

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
