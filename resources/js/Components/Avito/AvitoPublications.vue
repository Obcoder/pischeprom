<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    enabled: { type: Boolean, default: true },
    mutationsEnabled: { type: Boolean, default: false },
    workspace: { type: Object, default: () => ({ ready: false }) },
})

const emit = defineEmits(['notice', 'error', 'open-settings'])

const STATUS_OPTIONS = [
    { title: 'Черновик', value: 'draft', color: 'grey' },
    { title: 'Готово к feed', value: 'ready', color: 'indigo-lighten-1' },
    { title: 'Отправляется', value: 'publishing', color: 'blue' },
    { title: 'Опубликовано', value: 'published', color: 'success' },
    { title: 'С предупреждением', value: 'warning', color: 'warning' },
    { title: 'Отклонено', value: 'rejected', color: 'error' },
    { title: 'Архив', value: 'archived', color: 'blue-grey' },
]

const FIELD_OPTIONS = [
    { title: 'Название', value: 'title', icon: 'mdi-format-title' },
    { title: 'Описание', value: 'description', icon: 'mdi-text-long' },
    { title: 'Цена', value: 'price', icon: 'mdi-currency-rub' },
    { title: 'Фотографии', value: 'images', icon: 'mdi-image-multiple-outline' },
]

const accountId = ref(null)
const connectionId = ref(null)
const loading = ref(false)
const detailLoading = ref(false)
const saving = ref(false)
const actionLoading = ref('')
const inlineError = ref('')
const items = ref([])
const feed = ref(null)
const meta = ref({ page: 1, last_page: 1, total: 0 })
const statusCounts = ref({})
const page = ref(1)
const perPage = ref(50)
const search = ref('')
const statusFilter = ref(null)
const selectedId = ref(null)
const workspaceTab = ref('source')
const publication = ref(null)
const preview = ref(null)
const previewXml = ref('')
const revisions = ref([])

const categories = ref([])
const categoriesLoading = ref(false)
const categoryFieldsLoading = ref(false)
const categorySearch = ref('')

const createDialog = ref(false)
const createSearch = ref('')
const createGoods = ref([])
const createGoodId = ref(null)
const createLoading = ref(false)
let goodsTimer = null
let initialized = false

const profileDialog = ref(false)
const profileLoading = ref(false)
const profileExists = ref(null)
const profileForm = reactive({
    address: '',
    contact_phone: '',
    manager_name: '',
    report_email: '',
    autoload_enabled: false,
    agreement: false,
    schedule_json: '[]',
})

const approveDialog = ref(false)
const uploadDialog = ref(false)
const uploadStatusDialog = ref(false)
const uploadStatus = ref(null)

const editor = reactive({
    category_node_slug: null,
    category_name: '',
    selected_fields: ['title', 'description', 'price', 'images'],
    price_value_id: null,
    media_ids: [],
    include_facts: true,
    title_override: '',
    description_override: '',
    price_override: null,
    address: '',
    contact_phone: '',
    manager_name: '',
    allow_email: false,
    ad_type: 'Товар приобретен на продажу',
    condition: 'Новое',
    listing_fee: null,
    category_fields: {},
    category_schema: [],
})

const good = computed(() => publication.value?.good || null)
const currentRevision = computed(() => publication.value?.current_revision || null)
const priceOptions = computed(() => (good.value?.prices || []).map((item) => ({
    title: `${item.name} · ${formatMoney(item.amount)} ${item.currency_code}${item.is_public ? ' · публичная' : ''}`,
    value: item.id,
})))
const categoryOptions = computed(() => {
    const needle = categorySearch.value.trim().toLocaleLowerCase('ru')
    const leaves = categories.value.filter((item) => item.is_leaf !== false)
    const rows = needle
        ? leaves.filter((item) => `${item.path} ${item.slug}`.toLocaleLowerCase('ru').includes(needle))
        : leaves
    return rows.slice(0, 250).map((item) => ({ title: item.path || item.name, value: item.slug }))
})
const dynamicFields = computed(() => (editor.category_schema || []).filter((item) =>
    item?.key && ![
        'Id', 'Title', 'Description', 'Price', 'Images', 'Category', 'Address',
        'ContactPhone', 'ManagerName', 'AllowEmail', 'AdType', 'Condition', 'ListingFee',
    ].includes(item.key)))
const unknownCategoryFields = computed(() => Object.keys(editor.category_fields || {})
    .filter((key) => !dynamicFields.value.some((item) => item.key === key) && !['AdType', 'Condition'].includes(key)))
const canEdit = computed(() => publication.value && publication.value.status !== 'archived')
const canUseRemoteMutations = computed(() => props.enabled && props.mutationsEnabled)
const workspaceReady = computed(() => Boolean(props.workspace?.ready && positiveInteger(props.workspace?.account_id)))

watch(() => [props.workspace?.account_id, props.workspace?.connection_id], async ([nextAccountId, nextConnectionId]) => {
    const accountChanged = positiveInteger(nextAccountId) !== positiveInteger(accountId.value)
    const connectionChanged = (nextConnectionId ?? null) !== (connectionId.value ?? null)
    applyWorkspace()
    if (initialized && (accountChanged || connectionChanged)) {
        selectedId.value = null
        if (workspaceReady.value) await loadList(true)
        else {
            items.value = []
            feed.value = null
            clearEditor()
        }
    }
})

watch(createSearch, () => {
    clearTimeout(goodsTimer)
    goodsTimer = setTimeout(loadGoods, 280)
})

onMounted(initialize)
onBeforeUnmount(() => clearTimeout(goodsTimer))

async function initialize() {
    initialized = true
    applyWorkspace()
    if (workspaceReady.value) await loadList(true)
}

function applyWorkspace() {
    accountId.value = positiveInteger(props.workspace?.account_id)
    connectionId.value = props.workspace?.connection_id ?? null
}

async function loadList(reset = false) {
    const id = positiveInteger(accountId.value)
    if (!id) return
    if (reset) page.value = 1
    loading.value = true
    inlineError.value = ''
    try {
        const { data } = await axios.get('/api/avito/publications', { params: {
            account_id: id,
            page: page.value,
            per_page: perPage.value,
            search: search.value.trim() || undefined,
            status: statusFilter.value || undefined,
        } })
        items.value = data.items || []
        feed.value = data.feed || null
        meta.value = data.meta || { page: 1, last_page: 1, total: items.value.length }
        statusCounts.value = data.status_counts || {}
        if (!items.value.some((item) => Number(item.id) === Number(selectedId.value))) {
            selectedId.value = items.value[0]?.id || null
        }
        if (selectedId.value) await loadPublication(selectedId.value)
        else clearEditor()
    } catch (exception) {
        showError(exception, 'Не удалось загрузить черновики публикаций.')
    } finally {
        loading.value = false
    }
}

async function loadPublication(id) {
    if (!id || !positiveInteger(accountId.value)) return
    selectedId.value = id
    detailLoading.value = true
    inlineError.value = ''
    try {
        const { data } = await axios.get(`/api/avito/publications/${id}`, {
            params: { account_id: positiveInteger(accountId.value) },
        })
        hydrate(data)
    } catch (exception) {
        showError(exception, 'Не удалось открыть черновик.')
    } finally {
        detailLoading.value = false
    }
}

function hydrate(data) {
    publication.value = data.publication || null
    preview.value = data.preview || null
    feed.value = data.feed || feed.value
    revisions.value = publication.value?.revisions || []
    const draft = publication.value?.draft || {}
    Object.assign(editor, {
        category_node_slug: publication.value?.category_node_slug || null,
        category_name: publication.value?.category_name || '',
        selected_fields: [...(draft.selected_fields || [])],
        price_value_id: draft.price_value_id || null,
        media_ids: [...(draft.media_ids || [])],
        include_facts: draft.include_facts ?? true,
        title_override: draft.title_override || '',
        description_override: draft.description_override || '',
        price_override: draft.price_override ?? null,
        address: draft.address || '',
        contact_phone: draft.contact_phone || '',
        manager_name: draft.manager_name || '',
        allow_email: draft.allow_email ?? false,
        ad_type: draft.ad_type || '',
        condition: draft.condition || '',
        listing_fee: draft.listing_fee || null,
        category_fields: structuredClone(draft.category_fields || {}),
        category_schema: structuredClone(draft.category_schema || []),
    })
    previewXml.value = ''
}

function clearEditor() {
    publication.value = null
    preview.value = null
    previewXml.value = ''
    revisions.value = []
}

async function saveDraft(showMessage = true) {
    if (!publication.value || !canEdit.value) return false
    saving.value = true
    inlineError.value = ''
    try {
        const { data } = await axios.put(`/api/avito/publications/${publication.value.id}`, {
            account_id: positiveInteger(accountId.value),
            connection_id: connectionId.value || null,
            ...editorPayload(),
        })
        hydrate(data)
        replaceSummary(data.publication)
        if (showMessage) emit('notice', 'Черновик сохранён. Feed пока не изменён.')
        return true
    } catch (exception) {
        showError(exception, 'Не удалось сохранить черновик.')
        return false
    } finally {
        saving.value = false
    }
}

function editorPayload() {
    return {
        category_node_slug: editor.category_node_slug || null,
        category_name: editor.category_name || null,
        selected_fields: [...editor.selected_fields],
        price_value_id: editor.price_value_id || null,
        media_ids: [...editor.media_ids],
        include_facts: Boolean(editor.include_facts),
        title_override: editor.title_override || null,
        description_override: editor.description_override || null,
        price_override: editor.price_override === '' ? null : editor.price_override,
        address: editor.address || null,
        contact_phone: editor.contact_phone || null,
        manager_name: editor.manager_name || null,
        allow_email: Boolean(editor.allow_email),
        ad_type: editor.ad_type || null,
        condition: editor.condition || null,
        listing_fee: editor.listing_fee || null,
        category_fields: cleanCategoryFields(editor.category_fields),
        category_schema: editor.category_schema,
    }
}

async function preparePreview() {
    if (!await saveDraft(false)) return false
    actionLoading.value = 'preview'
    try {
        const { data } = await axios.post(`/api/avito/publications/${publication.value.id}/preview`, {
            account_id: positiveInteger(accountId.value),
        })
        preview.value = data.preview
        previewXml.value = data.xml || ''
        workspaceTab.value = 'preview'
        return Boolean(data.preview?.valid)
    } catch (exception) {
        showError(exception, 'Не удалось собрать предпросмотр.')
        return false
    } finally {
        actionLoading.value = ''
    }
}

async function openApprove() {
    await preparePreview()
    approveDialog.value = true
}

async function approve() {
    actionLoading.value = 'approve'
    try {
        const { data } = await axios.post(`/api/avito/publications/${publication.value.id}/approve`, {
            account_id: positiveInteger(accountId.value),
            confirmed: true,
        })
        hydrate(data)
        replaceSummary(data.publication)
        approveDialog.value = false
        emit('notice', data.message || 'Версия зафиксирована и включена в feed.')
        await loadList(false)
    } catch (exception) {
        showError(exception, 'Не удалось зафиксировать версию.')
    } finally {
        actionLoading.value = ''
    }
}

async function loadCategories() {
    if (categories.value.length || categoriesLoading.value) return
    categoriesLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/publications/categories', { params: baseParams() })
        categories.value = data.items || []
    } catch (exception) {
        showError(exception, 'Avito не вернул дерево категорий.')
    } finally {
        categoriesLoading.value = false
    }
}

async function chooseCategory(slug) {
    editor.category_node_slug = slug || null
    const category = categories.value.find((item) => item.slug === slug)
    editor.category_name = category?.name || category?.path?.split(' → ').at(-1) || ''
    editor.category_fields = {}
    editor.category_schema = []
    if (!slug) return
    categoryFieldsLoading.value = true
    try {
        const { data } = await axios.get(`/api/avito/publications/categories/${encodeURIComponent(slug)}/fields`, {
            params: baseParams(),
        })
        editor.category_schema = data.items || []
        const values = {}
        editor.category_schema.forEach((item) => {
            if (item.key === 'AdType') editor.ad_type = firstOption(item) || editor.ad_type
            else if (item.key === 'Condition') editor.condition = firstOption(item) || editor.condition
            else values[item.key] = fieldMultiple(item) ? [] : ''
        })
        editor.category_fields = values
    } catch (exception) {
        showError(exception, 'Не удалось получить поля выбранной категории.')
    } finally {
        categoryFieldsLoading.value = false
    }
}

async function openCreate() {
    createDialog.value = true
    createGoodId.value = null
    createSearch.value = ''
    await loadGoods()
}

async function loadGoods() {
    createLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/listings/goods', {
            params: { search: createSearch.value.trim() || undefined },
        })
        createGoods.value = data.items || []
    } catch (exception) {
        showError(exception, 'Не удалось найти Good.')
    } finally {
        createLoading.value = false
    }
}

async function createPublication() {
    if (!createGoodId.value) return
    createLoading.value = true
    try {
        const { data } = await axios.post('/api/avito/publications', {
            account_id: positiveInteger(accountId.value),
            connection_id: connectionId.value || null,
            good_id: createGoodId.value,
        })
        createDialog.value = false
        selectedId.value = data.publication.id
        hydrate(data)
        emit('notice', 'Черновик создан из Good. В Avito ничего не отправлено.')
        await loadList(true)
        selectedId.value = data.publication.id
        await loadPublication(data.publication.id)
    } catch (exception) {
        showError(exception, 'Не удалось создать черновик.')
    } finally {
        createLoading.value = false
    }
}

async function openProfile() {
    profileDialog.value = true
    profileExists.value = null
    Object.assign(profileForm, {
        address: feed.value?.defaults?.address || '',
        contact_phone: feed.value?.defaults?.contact_phone || '',
        manager_name: feed.value?.defaults?.manager_name || '',
        report_email: feed.value?.defaults?.report_email || '',
        autoload_enabled: false,
        agreement: false,
        schedule_json: '[]',
    })
    if (!await saveFeedDefaults()) return
    await checkProfile()
}

async function checkProfile() {
    if (!feed.value) return
    profileLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/publications/feed/profile', { params: baseParams() })
        feed.value = data.feed || feed.value
        profileExists.value = Boolean(data.exists)
        const remote = data.profile || {}
        profileForm.report_email = remote.report_email || profileForm.report_email
        profileForm.autoload_enabled = Boolean(remote.autoload_enabled)
        profileForm.schedule_json = JSON.stringify(remote.schedule || [], null, 2)
        emit('notice', data.attached ? 'Feed подключён к профилю Автозагрузки.' : 'Профиль проверен; feed ещё не подключён.')
    } catch (exception) {
        showError(exception, 'Не удалось проверить профиль Автозагрузки.')
    } finally {
        profileLoading.value = false
    }
}

async function saveFeedDefaults() {
    if (!positiveInteger(accountId.value)) return
    profileLoading.value = true
    try {
        const { data } = await axios.put('/api/avito/publications/feed', {
            ...baseParams(),
            address: profileForm.address || null,
            contact_phone: profileForm.contact_phone || null,
            manager_name: profileForm.manager_name || null,
            report_email: profileForm.report_email || null,
        })
        feed.value = data.feed
        return true
    } catch (exception) {
        showError(exception, 'Не удалось сохранить настройки feed.')
        return false
    } finally {
        profileLoading.value = false
    }
}

async function attachProfile() {
    let schedule
    try {
        schedule = JSON.parse(profileForm.schedule_json || '[]')
        if (!Array.isArray(schedule)) throw new Error()
    } catch {
        return showError(null, 'Расписание должно быть JSON-массивом.')
    }
    if (!await saveFeedDefaults()) return
    profileLoading.value = true
    try {
        const { data } = await axios.post('/api/avito/publications/feed/profile', {
            ...baseParams(),
            report_email: profileForm.report_email,
            autoload_enabled: Boolean(profileForm.autoload_enabled),
            agreement: Boolean(profileForm.agreement),
            schedule,
            confirmed: true,
        })
        feed.value = data.feed || feed.value
        profileDialog.value = false
        emit('notice', 'Feed Ameise подключён. Существующие feed Avito сохранены.')
    } catch (exception) {
        showError(exception, 'Avito не сохранил профиль Автозагрузки.')
    } finally {
        profileLoading.value = false
    }
}

async function requestUpload() {
    actionLoading.value = 'upload'
    try {
        const { data } = await axios.post('/api/avito/publications/feed/upload', {
            ...baseParams(), confirmed: true,
        })
        feed.value = data.feed || feed.value
        uploadDialog.value = false
        emit('notice', `Avito принял запуск: ${data.submitted_publications || 0} подтверждённых публикаций.`)
        await loadList(false)
    } catch (exception) {
        showError(exception, 'Не удалось запустить Автозагрузку.')
    } finally {
        actionLoading.value = ''
    }
}

async function refreshRemoteStatus() {
    if (!feed.value) return
    actionLoading.value = 'status'
    try {
        const { data } = await axios.get('/api/avito/publications/feed/upload', { params: baseParams() })
        feed.value = data.feed || feed.value
        uploadStatus.value = data.upload || null
        uploadStatusDialog.value = true
        emit('notice', data.exists ? 'Состояние текущей загрузки обновлено.' : 'Текущей загрузки в Avito нет.')
    } catch (exception) {
        showError(exception, 'Не удалось получить состояние загрузки.')
    } finally {
        actionLoading.value = ''
    }
}

async function syncPublication() {
    if (!publication.value?.current_revision) return
    actionLoading.value = 'sync'
    try {
        const { data } = await axios.post(`/api/avito/publications/${publication.value.id}/sync`, baseParams())
        publication.value = data.publication
        revisions.value = data.publication?.revisions || []
        replaceSummary(data.publication)
        emit('notice', data.avito_item_id
            ? `Объявление Avito #${data.avito_item_id} найдено и привязано к Good.`
            : 'Отчёт Avito прочитан; объявление ещё обрабатывается.')
    } catch (exception) {
        showError(exception, 'Не удалось прочитать отчёт публикации.')
    } finally {
        actionLoading.value = ''
    }
}

async function archivePublication() {
    if (!publication.value || !window.confirm('Исключить публикацию из следующих feed? Объявление на Avito автоматически не снимется.')) return
    actionLoading.value = 'archive'
    try {
        const { data } = await axios.post(`/api/avito/publications/${publication.value.id}/archive`, {
            ...baseParams(), confirmed: true,
        })
        hydrate(data)
        emit('notice', data.message)
        await loadList(false)
    } catch (exception) {
        showError(exception, 'Не удалось архивировать публикацию.')
    } finally {
        actionLoading.value = ''
    }
}

function addExtraField() {
    let index = 1
    while (Object.hasOwn(editor.category_fields, `CustomField${index}`)) index++
    editor.category_fields[`CustomField${index}`] = ''
}

function renameExtraField(oldKey, nextKey) {
    const key = String(nextKey || '').trim()
    if (!key || key === oldKey || Object.hasOwn(editor.category_fields, key)) return
    const value = editor.category_fields[oldKey]
    delete editor.category_fields[oldKey]
    editor.category_fields[key] = value
}

function removeExtraField(key) {
    delete editor.category_fields[key]
}

function replaceSummary(value) {
    if (!value) return
    const index = items.value.findIndex((item) => Number(item.id) === Number(value.id))
    if (index >= 0) items.value[index] = { ...items.value[index], ...value }
}

function baseParams() {
    return {
        account_id: positiveInteger(accountId.value),
        connection_id: connectionId.value ?? null,
    }
}

function cleanCategoryFields(fields) {
    return Object.fromEntries(Object.entries(fields || {})
        .filter(([key]) => /^[A-Za-z_][A-Za-z0-9_.-]{0,119}$/.test(key))
        .map(([key, value]) => [key, Array.isArray(value) ? value.filter(notBlank) : value])
        .filter(([, value]) => Array.isArray(value) ? value.length : notBlank(value)))
}

function firstOption(field) {
    return field?.options?.[0]?.value || ''
}

function fieldMultiple(field) {
    return Boolean(field?.multiple) || /array|multi|multiple/i.test(field?.type || '')
}

function notBlank(value) {
    return value !== null && value !== undefined && String(value).trim() !== ''
}

function positiveInteger(value) {
    const number = Number(value)
    return Number.isInteger(number) && number > 0 ? number : null
}

function statusLabel(status) {
    return STATUS_OPTIONS.find((item) => item.value === status)?.title || status || '—'
}

function statusColor(status) {
    return STATUS_OPTIONS.find((item) => item.value === status)?.color || 'grey'
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—'
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(Number(value))
}

function formatDate(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function prettyJson(value) {
    return value == null ? 'Нет текущей загрузки.' : JSON.stringify(value, null, 2)
}

function errorMessage(exception, fallback) {
    const errors = exception?.response?.data?.errors
    if (errors && typeof errors === 'object') return Object.values(errors).flat()[0] || fallback
    return exception?.response?.data?.message || fallback
}

function showError(exception, fallback) {
    inlineError.value = errorMessage(exception, fallback)
    emit('error', inlineError.value)
}
</script>

<template>
    <section class="publisher">
        <header class="publisher-toolbar">
            <v-text-field v-model="search" label="Good, категория или Ameise ID" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" hide-details clearable @keyup.enter="loadList(true)" />
            <v-select v-model="statusFilter" :items="STATUS_OPTIONS" label="Статус" density="compact" variant="outlined" hide-details clearable />
            <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" :disabled="!workspaceReady" @click="loadList(true)">Обновить</v-btn>
            <v-btn size="small" color="deep-purple-accent-1" prepend-icon="mdi-plus" :disabled="!workspaceReady" @click="openCreate">Из Good</v-btn>
        </header>

        <v-alert v-if="inlineError" type="error" variant="tonal" density="compact" closable class="publisher-alert" @click:close="inlineError = ''">{{ inlineError }}</v-alert>
        <v-alert v-if="!workspaceReady" type="warning" variant="tonal" density="compact" class="publisher-alert">
            Рабочий кабинет Avito не настроен. Регистрационные данные находятся отдельно во вкладке «Подключения».
            <template #append><v-btn size="x-small" variant="outlined" @click="emit('open-settings')">Открыть настройки</v-btn></template>
        </v-alert>
        <v-alert v-else-if="!workspace.authorization_ready" type="warning" variant="tonal" density="compact" class="publisher-alert">
            Для рабочего кабинета недоступна авторизация Avito. Проверьте её в отдельной вкладке «Подключения».
            <template #append><v-btn size="x-small" variant="outlined" @click="emit('open-settings')">Открыть настройки</v-btn></template>
        </v-alert>
        <v-alert v-if="!mutationsEnabled" type="info" variant="tonal" density="compact" class="publisher-alert">
            Черновики и preview работают. Подключение feed и запуск Avito доступны после <code>AVITO_MUTATIONS_ENABLED=true</code>.
        </v-alert>

        <div class="feed-strip">
            <div>
                <span class="feed-label">Защищённый feed</span>
                <strong>{{ feed?.name || 'ещё не создан' }}</strong>
                <small>{{ feed ? `${feed.approved_publications_count} версий · профиль: ${feed.profile_status}` : 'создастся вместе с первым черновиком' }}</small>
            </div>
            <code v-if="feed" :title="feed.url">{{ feed.url }}</code>
            <v-btn size="x-small" variant="text" prepend-icon="mdi-cog-outline" :disabled="!workspaceReady" @click="openProfile">Профиль</v-btn>
            <v-btn size="x-small" variant="text" prepend-icon="mdi-cloud-search-outline" :disabled="!feed" :loading="actionLoading === 'status'" @click="refreshRemoteStatus">Загрузка</v-btn>
            <v-btn size="x-small" color="deep-purple" prepend-icon="mdi-cloud-upload-outline" :disabled="!feed?.approved_publications_count || !canUseRemoteMutations" @click="uploadDialog = true">Запустить</v-btn>
        </div>

        <div class="status-strip">
            <button v-for="status in STATUS_OPTIONS" :key="status.value" type="button" :class="{ active: statusFilter === status.value }" @click="statusFilter = statusFilter === status.value ? null : status.value; loadList(true)">
                <span>{{ status.title }}</span><strong>{{ statusCounts[status.value] || 0 }}</strong>
            </button>
        </div>

        <div class="publisher-grid">
            <aside class="draft-list">
                <div v-if="loading" class="center"><v-progress-circular indeterminate size="28" /></div>
                <button v-for="item in items" v-else :key="item.id" type="button" class="draft-row" :class="{ active: Number(selectedId) === Number(item.id) }" @click="loadPublication(item.id)">
                    <span class="draft-row__main"><strong>{{ item.good_name }}</strong><small>{{ item.category_name || 'Категория не выбрана' }}</small></span>
                    <span class="draft-row__meta"><v-chip :color="statusColor(item.status)" size="x-small" variant="tonal">{{ statusLabel(item.status) }}</v-chip><small>v{{ item.revision || '—' }}</small></span>
                    <span class="draft-row__id">{{ item.external_id }}</span>
                    <v-icon v-if="item.draft_dirty" icon="mdi-circle-medium" color="warning" size="18" title="Есть изменения после подтверждения" />
                </button>
                <div v-if="!loading && !items.length" class="empty-list"><v-icon icon="mdi-file-document-plus-outline" /><span>Черновиков нет</span><small>Создайте первый из карточки Good.</small></div>
                <footer class="list-pager">
                    <span>{{ meta.from || 0 }}–{{ meta.to || 0 }} из {{ meta.total || 0 }}</span>
                    <v-btn icon="mdi-chevron-left" size="x-small" variant="text" :disabled="page <= 1" @click="page--; loadList()" />
                    <b>{{ page }}/{{ meta.last_page || 1 }}</b>
                    <v-btn icon="mdi-chevron-right" size="x-small" variant="text" :disabled="page >= (meta.last_page || 1)" @click="page++; loadList()" />
                </footer>
            </aside>

            <main class="editor-shell">
                <div v-if="detailLoading" class="center"><v-progress-circular indeterminate /><span>Открываем черновик…</span></div>
                <div v-else-if="!publication" class="editor-empty"><v-icon icon="mdi-arrow-left-circle-outline" size="42" /><strong>Выберите черновик</strong><span>Good остаётся источником истины; в feed попадает только подтверждённая версия.</span></div>
                <template v-else>
                    <header class="editor-head">
                        <div><span class="eyebrow">Good #{{ publication.good_id }} → {{ publication.external_id }}</span><h3>{{ publication.good_name }}</h3></div>
                        <div class="editor-head__meta">
                            <v-chip :color="statusColor(publication.status)" size="small" variant="tonal">{{ statusLabel(publication.status) }}</v-chip>
                            <v-chip v-if="publication.draft_dirty" color="warning" size="small" variant="outlined">черновик изменён</v-chip>
                            <a v-if="publication.avito_item_id" :href="`https://www.avito.ru/${publication.avito_item_id}`" target="_blank" rel="noopener">Avito #{{ publication.avito_item_id }}</a>
                        </div>
                    </header>

                    <v-tabs v-model="workspaceTab" density="compact" class="editor-tabs" color="deep-purple-accent-1">
                        <v-tab value="source">Данные Good</v-tab>
                        <v-tab value="category">Категория и контакты</v-tab>
                        <v-tab value="preview">Preview / XML</v-tab>
                        <v-tab value="history">Версии {{ revisions.length }}</v-tab>
                    </v-tabs>

                    <v-window v-model="workspaceTab" class="editor-window">
                        <v-window-item value="source">
                            <div class="field-picker">
                                <label v-for="field in FIELD_OPTIONS" :key="field.value" :class="{ active: editor.selected_fields.includes(field.value) }">
                                    <v-checkbox-btn v-model="editor.selected_fields" :value="field.value" density="compact" /><v-icon :icon="field.icon" size="16" /><span>{{ field.title }}</span>
                                </label>
                                <small>Отмеченные данные берутся из текущего Good при подтверждении версии. Overrides применяются только к этому объявлению.</small>
                            </div>
                            <div class="form-grid">
                                <v-text-field v-model="editor.title_override" label="Название: пусто = из Good" density="compact" variant="outlined" :disabled="!canEdit" counter="100" />
                                <v-select v-model="editor.price_value_id" :items="priceOptions" label="Цена Good" density="compact" variant="outlined" clearable :disabled="!canEdit" />
                                <v-text-field v-model.number="editor.price_override" label="Своя цена, ₽ (необязательно)" type="number" min="0" density="compact" variant="outlined" :disabled="!canEdit" />
                                <v-switch v-model="editor.include_facts" label="Добавить фасовку, страну, наличие и ссылку" density="compact" color="deep-purple-accent-1" hide-details :disabled="!canEdit" />
                                <v-textarea v-model="editor.description_override" label="Описание: пусто = описание Good" rows="7" auto-grow density="compact" variant="outlined" counter="7500" class="span-2" :disabled="!canEdit" />
                            </div>
                            <section class="media-picker">
                                <header><strong>Фотографии Good</strong><span>{{ editor.media_ids.length }} / 10</span></header>
                                <div v-if="good?.media?.length" class="media-grid">
                                    <label v-for="media in good.media" :key="media.id" :class="{ active: editor.media_ids.includes(media.id) }">
                                        <img :src="media.url || media.full_url" :alt="media.title" loading="lazy" />
                                        <v-checkbox-btn v-model="editor.media_ids" :value="media.id" :disabled="!canEdit || (!editor.media_ids.includes(media.id) && editor.media_ids.length >= 10)" />
                                        <span>{{ media.title }}</span>
                                    </label>
                                </div>
                                <small v-else>У Good нет опубликованных изображений.</small>
                            </section>
                        </v-window-item>

                        <v-window-item value="category">
                            <div class="category-line">
                                <v-autocomplete v-model="editor.category_node_slug" v-model:search="categorySearch" :items="categoryOptions" :loading="categoriesLoading || categoryFieldsLoading" label="Конечная категория Avito" density="compact" variant="outlined" clearable no-filter @focus="loadCategories" @update:model-value="chooseCategory" />
                                <v-text-field v-model="editor.category_name" label="XML Category" density="compact" variant="outlined" :disabled="!canEdit" />
                                <v-btn size="small" variant="outlined" prepend-icon="mdi-source-branch" :loading="categoriesLoading" @click="loadCategories">Категории</v-btn>
                            </div>
                            <div class="form-grid core-fields">
                                <v-text-field v-model="editor.address" label="Адрес *" density="compact" variant="outlined" :disabled="!canEdit" />
                                <v-text-field v-model="editor.contact_phone" label="Телефон *" density="compact" variant="outlined" :disabled="!canEdit" />
                                <v-text-field v-model="editor.manager_name" label="Контактное лицо" density="compact" variant="outlined" :disabled="!canEdit" />
                                <v-switch v-model="editor.allow_email" label="Разрешить e-mail" density="compact" hide-details :disabled="!canEdit" />
                                <v-text-field v-model="editor.ad_type" label="AdType" density="compact" variant="outlined" :disabled="!canEdit" />
                                <v-text-field v-model="editor.condition" label="Condition" density="compact" variant="outlined" :disabled="!canEdit" />
                                <v-text-field v-model="editor.listing_fee" label="ListingFee (только осознанно)" density="compact" variant="outlined" clearable :disabled="!canEdit" />
                            </div>
                            <v-progress-linear v-if="categoryFieldsLoading" indeterminate color="deep-purple-accent-1" />
                            <section v-if="dynamicFields.length" class="dynamic-fields">
                                <header><strong>Поля категории из официальной схемы Avito</strong><small>* — обязательные</small></header>
                                <div class="form-grid">
                                    <template v-for="field in dynamicFields" :key="field.key">
                                        <v-select v-if="field.options?.length" v-model="editor.category_fields[field.key]" :items="field.options" item-title="title" item-value="value" :multiple="fieldMultiple(field)" :label="`${field.label}${field.required ? ' *' : ''}`" :hint="field.description" persistent-hint density="compact" variant="outlined" clearable :disabled="!canEdit" />
                                        <v-text-field v-else v-model="editor.category_fields[field.key]" :label="`${field.label}${field.required ? ' *' : ''}`" :hint="field.description" persistent-hint density="compact" variant="outlined" :disabled="!canEdit" />
                                    </template>
                                </div>
                            </section>
                            <section class="extra-fields">
                                <header><strong>Дополнительные XML-поля</strong><v-btn size="x-small" variant="text" prepend-icon="mdi-plus" :disabled="!canEdit" @click="addExtraField">Поле</v-btn></header>
                                <div v-for="key in unknownCategoryFields" :key="key" class="extra-row">
                                    <v-text-field :model-value="key" label="XML-тег" density="compact" variant="outlined" hide-details @change="renameExtraField(key, $event.target.value)" />
                                    <v-text-field v-model="editor.category_fields[key]" label="Значение" density="compact" variant="outlined" hide-details />
                                    <v-btn icon="mdi-close" size="x-small" variant="text" @click="removeExtraField(key)" />
                                </div>
                            </section>
                        </v-window-item>

                        <v-window-item value="preview">
                            <div class="preview-toolbar"><v-btn size="small" color="deep-purple" prepend-icon="mdi-eye-refresh-outline" :loading="actionLoading === 'preview'" @click="preparePreview">Пересобрать</v-btn><span>Preview всегда строится из актуального Good и текущих настроек черновика.</span></div>
                            <div v-if="preview" class="preview-grid">
                                <section class="validation-box" :class="preview.valid ? 'valid' : 'invalid'">
                                    <header><v-icon :icon="preview.valid ? 'mdi-check-circle' : 'mdi-alert-circle'" /><strong>{{ preview.valid ? 'Готово к фиксации' : 'Нужно исправить' }}</strong></header>
                                    <ul v-for="(messages, field) in preview.errors" :key="field"><li v-for="message in messages" :key="message"><b>{{ field }}:</b> {{ message }}</li></ul>
                                    <p v-for="warning in preview.warnings" :key="warning">⚠ {{ warning }}</p>
                                </section>
                                <section class="ad-preview">
                                    <span>{{ preview.payload?.category }}</span><h4>{{ preview.payload?.title }}</h4><strong>{{ formatMoney(preview.payload?.price) }} ₽</strong><small>{{ preview.payload?.address }} · {{ preview.payload?.contact_phone }}</small><pre>{{ preview.payload?.description }}</pre>
                                </section>
                                <section v-if="currentRevision" class="snapshot-preview">
                                    <header><strong>Точно опубликованная версия v{{ currentRevision.version }}</strong><v-chip size="x-small" :color="statusColor(publication.status)">{{ currentRevision.status }}</v-chip></header>
                                    <small>Это локальный неизменяемый снимок. Изменения Good его не переписывают.</small>
                                    <pre>{{ currentRevision.payload?.description }}</pre>
                                </section>
                                <details class="xml-preview" :open="Boolean(previewXml)"><summary>XML объявления</summary><pre>{{ previewXml || 'Нажмите «Пересобрать».' }}</pre></details>
                            </div>
                        </v-window-item>

                        <v-window-item value="history">
                            <div class="history-actions"><v-btn size="small" variant="outlined" prepend-icon="mdi-cloud-sync-outline" :disabled="!currentRevision" :loading="actionLoading === 'sync'" @click="syncPublication">Прочитать отчёт Avito</v-btn><span>Синхронизация только читает результат обработки и создаёт связь с Good при появлении Avito ID.</span></div>
                            <div v-if="revisions.length" class="revision-list">
                                <details v-for="revision in revisions" :key="revision.id" :open="revision.is_current">
                                    <summary><b>v{{ revision.version }}</b><v-chip size="x-small" :color="revision.is_current ? 'deep-purple' : 'grey'" variant="tonal">{{ revision.status }}</v-chip><span>{{ formatDate(revision.approved_at) }}</span><small>{{ revision.images?.length || 0 }} фото</small></summary>
                                    <div><strong>{{ revision.payload?.title }}</strong><span>{{ formatMoney(revision.payload?.price) }} ₽</span><p v-for="message in revision.report_messages" :key="`${message.code}-${message.message}`" :class="`report-${message.level}`">{{ message.level }} · {{ message.message }}</p><pre>{{ revision.payload?.description }}</pre></div>
                                </details>
                            </div>
                            <div v-else class="editor-empty"><span>Подтверждённых версий пока нет.</span></div>
                        </v-window-item>
                    </v-window>

                    <footer class="editor-actions">
                        <v-btn size="small" variant="outlined" prepend-icon="mdi-content-save-outline" :loading="saving" :disabled="!canEdit" @click="saveDraft()">Сохранить черновик</v-btn>
                        <v-btn size="small" variant="outlined" prepend-icon="mdi-eye-outline" :loading="actionLoading === 'preview'" @click="preparePreview">Preview</v-btn>
                        <v-btn size="small" color="deep-purple-accent-1" prepend-icon="mdi-check-decagram-outline" :disabled="!canEdit" @click="openApprove">Зафиксировать версию</v-btn>
                        <span class="action-spacer" />
                        <v-btn size="small" color="error" variant="text" prepend-icon="mdi-archive-outline" :disabled="publication.status === 'archived'" @click="archivePublication">В архив</v-btn>
                    </footer>
                </template>
            </main>
        </div>

        <v-dialog v-model="createDialog" max-width="720">
            <v-card class="dialog-card"><v-card-title>Новый черновик из Good</v-card-title><v-card-text>
                <v-text-field v-model="createSearch" label="Найти Good" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" autofocus clearable />
                <div class="good-results" role="radiogroup" aria-label="Выбор Good для объявления">
                    <button v-for="item in createGoods" :key="item.id" type="button" role="radio" class="good-result" :class="{ active: Number(createGoodId) === Number(item.id) }" :aria-checked="Number(createGoodId) === Number(item.id)" @click="createGoodId = Number(item.id)"><v-icon :icon="Number(createGoodId) === Number(item.id) ? 'mdi-radiobox-marked' : 'mdi-radiobox-blank'" size="22" /><div><strong>{{ item.name }}</strong><small>#{{ item.id }} · {{ item.is_published ? 'опубликован' : 'не опубликован' }} · {{ item.prices?.length || 0 }} цен · {{ item.media?.length || 0 }} фото</small></div></button>
                </div>
            </v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="createDialog = false">Отмена</v-btn><v-btn color="deep-purple" :disabled="!positiveInteger(createGoodId)" :loading="createLoading" @click="createPublication">Создать черновик</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="approveDialog" max-width="640">
            <v-card class="dialog-card"><v-card-title>Зафиксировать версию?</v-card-title><v-card-text>
                <v-alert :type="preview?.valid ? 'info' : 'error'" variant="tonal">{{ preview?.valid ? 'Будут сохранены точные значения текста, цены и копии выбранных фото. Версия попадёт в feed, но запуск Avito останется отдельным действием.' : 'Preview содержит ошибки. Подтверждение будет отклонено сервером.' }}</v-alert>
                <p>Good останется источником истины. Последующие изменения Good не изменят эту версию — потребуется новое осознанное подтверждение.</p>
            </v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="approveDialog = false">Отмена</v-btn><v-btn color="deep-purple" :disabled="!preview?.valid" :loading="actionLoading === 'approve'" @click="approve">Зафиксировать</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="profileDialog" max-width="860">
            <v-card class="dialog-card"><v-card-title>Feed и профиль Автозагрузки</v-card-title><v-card-text>
                <v-alert type="info" variant="tonal" density="compact">Подключение сохраняет все чужие feed профиля и добавляет только <b>{{ feed?.name }}</b>. Автозагрузка не включается сама.</v-alert>
                <div class="form-grid mt-4"><v-text-field v-model="profileForm.address" label="Адрес по умолчанию" density="compact" variant="outlined" /><v-text-field v-model="profileForm.contact_phone" label="Телефон по умолчанию" density="compact" variant="outlined" /><v-text-field v-model="profileForm.manager_name" label="Контактное лицо" density="compact" variant="outlined" /><v-text-field v-model="profileForm.report_email" label="E-mail отчётов *" density="compact" variant="outlined" /></div>
                <v-switch v-model="profileForm.autoload_enabled" label="Разрешить Avito выполнять feed по расписанию" color="warning" density="compact" />
                <v-textarea v-model="profileForm.schedule_json" label="Расписание JSON (Москва): weekdays 0–6, time_slots 0–23, rate" rows="5" density="compact" variant="outlined" spellcheck="false" />
                <v-checkbox v-if="profileExists === false" v-model="profileForm.agreement" label="Принимаю правила использования Avito Автозагрузки" density="compact" />
            </v-card-text><v-card-actions><v-btn variant="text" :loading="profileLoading" @click="checkProfile">Проверить</v-btn><v-spacer /><v-btn variant="text" @click="profileDialog = false">Закрыть</v-btn><v-btn color="deep-purple" :disabled="!canUseRemoteMutations" :loading="profileLoading" @click="attachProfile">Сохранить и подключить feed</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="uploadDialog" max-width="620">
            <v-card class="dialog-card"><v-card-title>Запустить Автозагрузку Avito?</v-card-title><v-card-text><v-alert type="warning" variant="tonal">Avito обработает весь подключённый профиль, включая <b>{{ feed?.approved_publications_count || 0 }}</b> текущих версий Ameise и другие feed кабинета. Повторный ручной запуск возможен не чаще раза в час.</v-alert><p>Черновики и архивные публикации не отправляются. После запуска используйте «Прочитать отчёт Avito».</p></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="uploadDialog = false">Отмена</v-btn><v-btn color="warning" :loading="actionLoading === 'upload'" @click="requestUpload">Подтверждаю запуск</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="uploadStatusDialog" max-width="760">
            <v-card class="dialog-card"><v-card-title>Текущая загрузка Avito</v-card-title><v-card-text><pre class="status-json">{{ prettyJson(uploadStatus) }}</pre></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="uploadStatusDialog = false">Закрыть</v-btn></v-card-actions></v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.publisher { min-height: calc(100vh - 245px); color: #e8ebff; }
.publisher-toolbar { display: grid; grid-template-columns: minmax(260px, 1fr) 180px auto auto; gap: 5px; padding: 5px; border-bottom: 1px solid #30344e; background: #181b30; }
.publisher-alert { margin: 5px; font-size: 11px; }
.feed-strip { display: grid; grid-template-columns: minmax(220px, auto) minmax(180px, 1fr) auto auto auto; align-items: center; gap: 7px; min-height: 48px; padding: 5px 9px; border-bottom: 1px solid #30344e; background: #111427; }
.feed-strip > div { display: grid; line-height: 1.15; }.feed-label, .eyebrow { color: #9f91f2; font-size: 8px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }.feed-strip strong { font-size: 12px; }.feed-strip small { color: #8e94b3; font-size: 9px; }.feed-strip code { overflow: hidden; color: #8d93af; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.status-strip { display: flex; overflow-x: auto; gap: 4px; padding: 4px 5px; border-bottom: 1px solid #30344e; background: #15182b; }.status-strip button { display: flex; align-items: center; gap: 6px; min-width: max-content; padding: 3px 7px; color: #9da3c0; font-size: 9px; border: 1px solid #30344d; border-radius: 6px; }.status-strip button.active { color: #fff; border-color: #8d75ff; background: rgba(115, 82, 232, .2); }.status-strip strong { color: #c7baff; }
.publisher-grid { display: grid; grid-template-columns: minmax(245px, 21%) minmax(0, 1fr); height: calc(100vh - 378px); min-height: 520px; }
.draft-list { position: relative; overflow: auto; border-right: 1px solid #30344e; background: #111427; }.draft-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 2px 6px; width: 100%; padding: 7px 8px; color: #dfe2f6; text-align: left; border-bottom: 1px solid #282c43; background: transparent; }.draft-row:hover { background: #1b1f37; }.draft-row.active { box-shadow: inset 3px 0 #957dff; background: #20233d; }.draft-row__main { display: grid; min-width: 0; }.draft-row__main strong, .draft-row__main small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.draft-row__main strong { font-size: 11px; }.draft-row__main small, .draft-row__id { color: #8e94ae; font-size: 8px; }.draft-row__meta { display: flex; align-items: center; gap: 4px; }.draft-row__id { grid-column: 1 / 3; font-family: monospace; }.list-pager { position: sticky; bottom: 0; display: flex; align-items: center; justify-content: flex-end; gap: 4px; padding: 4px 7px; font-size: 9px; border-top: 1px solid #343850; background: #171a2e; }.list-pager span { margin-right: auto; }
.empty-list, .editor-empty, .center { display: flex; min-height: 220px; align-items: center; justify-content: center; flex-direction: column; gap: 6px; color: #888faa; text-align: center; }.empty-list small, .editor-empty span { max-width: 440px; font-size: 10px; }
.editor-shell { position: relative; display: flex; overflow: hidden; min-width: 0; flex-direction: column; background: #15182b; }.editor-head { display: flex; min-height: 49px; align-items: center; justify-content: space-between; gap: 8px; padding: 5px 10px; border-bottom: 1px solid #30344e; }.editor-head h3 { overflow: hidden; max-width: 720px; margin: 1px 0 0; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }.editor-head__meta { display: flex; align-items: center; gap: 5px; }.editor-head__meta a { color: #a99bff; font-size: 10px; }.editor-tabs { min-height: 34px; border-bottom: 1px solid #30344e; }.editor-tabs :deep(.v-tab) { min-height: 34px; padding: 0 10px; font-size: 9px; }.editor-window { overflow: auto; flex: 1; padding: 7px; }.editor-window :deep(.v-window__container), .editor-window :deep(.v-window-item) { min-height: 100%; }
.field-picker { display: flex; align-items: center; gap: 5px; margin-bottom: 7px; }.field-picker label { display: flex; align-items: center; gap: 2px; padding: 1px 6px 1px 1px; color: #979db9; font-size: 9px; border: 1px solid #343850; border-radius: 6px; }.field-picker label.active { color: #e8e3ff; border-color: #7963df; background: rgba(105, 76, 210, .17); }.field-picker > small { margin-left: auto; max-width: 430px; color: #8289a6; font-size: 8px; text-align: right; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }.span-2 { grid-column: 1 / -1; }.form-grid :deep(.v-input), .category-line :deep(.v-input) { font-size: 10px; }.core-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); }.category-line { display: grid; grid-template-columns: minmax(280px, 1.5fr) minmax(180px, 1fr) auto; gap: 5px; }
.media-picker, .dynamic-fields, .extra-fields { margin-top: 7px; padding: 7px; border: 1px solid #30344e; border-radius: 7px; background: #111427; }.media-picker > header, .dynamic-fields > header, .extra-fields > header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 10px; }.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(105px, 1fr)); gap: 5px; }.media-grid label { position: relative; overflow: hidden; min-height: 112px; border: 2px solid transparent; border-radius: 6px; background: #1b1f35; }.media-grid label.active { border-color: #927aff; }.media-grid img { width: 100%; height: 82px; object-fit: cover; }.media-grid :deep(.v-checkbox-btn) { position: absolute; top: 1px; right: 1px; border-radius: 50%; background: rgba(10, 11, 22, .72); }.media-grid span { display: block; overflow: hidden; padding: 2px 4px; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }.extra-row { display: grid; grid-template-columns: .7fr 1.3fr auto; gap: 4px; margin-top: 4px; }
.preview-toolbar, .history-actions { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; color: #8d94b1; font-size: 9px; }.preview-grid { display: grid; grid-template-columns: minmax(240px, .7fr) minmax(340px, 1.3fr); gap: 7px; }.validation-box, .ad-preview, .snapshot-preview, .xml-preview { padding: 8px; border: 1px solid #30344e; border-radius: 7px; background: #111427; }.validation-box.valid { border-color: rgba(54, 189, 120, .5); }.validation-box.invalid { border-color: rgba(238, 85, 99, .5); }.validation-box header, .snapshot-preview header { display: flex; align-items: center; justify-content: space-between; gap: 5px; }.validation-box ul, .validation-box p { margin: 5px 0; padding-left: 15px; color: #e2a4a9; font-size: 9px; }.validation-box p { color: #e6bd70; }.ad-preview { display: grid; gap: 4px; }.ad-preview h4 { margin: 0; font-size: 16px; }.ad-preview > span, .ad-preview > small, .snapshot-preview > small { color: #8e95b2; font-size: 9px; }.ad-preview pre, .snapshot-preview pre, .revision-list pre { overflow: auto; max-height: 260px; margin: 4px 0 0; color: #cfd3e8; font: 10px/1.45 inherit; white-space: pre-wrap; }.snapshot-preview, .xml-preview { grid-column: 1 / -1; }.xml-preview summary { cursor: pointer; font-size: 10px; }.xml-preview pre { overflow: auto; max-height: 330px; color: #b7e6cc; font: 9px/1.45 monospace; white-space: pre-wrap; }
.revision-list { display: grid; gap: 5px; }.revision-list details { border: 1px solid #30344e; border-radius: 6px; background: #111427; }.revision-list summary { display: flex; align-items: center; gap: 8px; padding: 7px; cursor: pointer; font-size: 9px; }.revision-list summary span { margin-left: auto; color: #8c93ad; }.revision-list details > div { display: grid; gap: 3px; padding: 7px; border-top: 1px solid #30344e; font-size: 10px; }
.revision-list p { margin: 1px 0; padding: 3px 5px; border-radius: 4px; }.report-error { color: #ffb2b8; background: rgba(220, 56, 74, .14); }.report-warning { color: #ffd48a; background: rgba(224, 160, 48, .12); }.report-info { color: #aeb6d6; background: rgba(120, 130, 170, .1); }
.editor-actions { display: flex; align-items: center; gap: 5px; min-height: 45px; padding: 5px 8px; border-top: 1px solid #30344e; background: #111427; }.action-spacer { flex: 1; }
.dialog-card { color: #edf0ff !important; background: #191c31 !important; }.dialog-card p { color: #a7adc7; font-size: 11px; }.good-results { overflow: auto; max-height: 420px; }.good-result { display: flex; width: 100%; align-items: center; gap: 8px; padding: 7px 5px; color: #e8ebff; text-align: left; border-bottom: 1px solid #30344e; background: transparent; }.good-result:hover, .good-result.active { background: rgba(109, 78, 218, .18); }.good-result.active { box-shadow: inset 3px 0 #9b82ff; }.good-result div { display: grid; }.good-result strong { font-size: 11px; }.good-result small { color: #8e94ae; font-size: 9px; }
.status-json { overflow: auto; max-height: 60vh; padding: 8px; color: #b8e3cb; font: 10px/1.5 monospace; border: 1px solid #30344e; border-radius: 6px; background: #101326; white-space: pre-wrap; }
@media (max-width: 1050px) { .publisher-toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }.publisher-grid { grid-template-columns: 220px minmax(0, 1fr); height: auto; min-height: 650px; }.feed-strip { grid-template-columns: 1fr auto auto auto; }.feed-strip code { display: none; }.core-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px) { .publisher-grid { display: block; }.draft-list { max-height: 240px; border-right: 0; border-bottom: 1px solid #30344e; }.editor-shell { min-height: 650px; }.form-grid, .core-fields, .category-line, .preview-grid { grid-template-columns: 1fr; }.span-2, .snapshot-preview, .xml-preview { grid-column: 1; }.field-picker { flex-wrap: wrap; }.field-picker > small { margin-left: 0; text-align: left; } }
</style>
