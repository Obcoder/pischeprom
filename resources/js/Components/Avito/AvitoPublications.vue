<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    enabled: { type: Boolean, default: true },
    mutationsEnabled: { type: Boolean, default: false },
    workspace: { type: Object, default: () => ({ ready: false }) },
    goodId: { type: [Number, String], default: null },
    goodName: { type: String, default: '' },
    linkedListings: { type: Array, default: () => [] },
})

const emit = defineEmits(['notice', 'error', 'open-settings', 'changed', 'state'])

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

const AVITO_AUTOLOAD_SETTINGS_URL = 'https://www.avito.ru/autoload/settings'

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
const savedEditorSignature = ref('')

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
let listVersion = 0
let detailVersion = 0
let disposed = false

const profileDialog = ref(false)
const profileLoading = ref(false)
const profileExists = ref(null)
const profileAvailable = ref(null)
const profileUnavailableReason = ref('')
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
    price_basis: { mode: 'unit' },
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
    title: `${item.name} · ${formatMoney(item.amount)} ${item.currency_code}${good.value?.measurement?.unit_label ? ` / ${good.value.measurement.unit_label}` : ''}${item.is_public ? ' · публичная' : ''}`,
    value: item.id,
})))
const priceBasisOptions = computed(() => [
    { title: `За единицу товара · ${good.value?.measurement?.unit_label || 'единица не задана'}`, value: 'unit' },
    { title: good.value?.avito_price_basis?.package?.available
        ? `За упаковку · ${formatQuantity(good.value.avito_price_basis.package.quantity)} ${good.value?.measurement?.unit_label || ''}`
        : 'За упаковку · фасовка или единица не настроена', value: 'package', props: { disabled: !good.value?.avito_price_basis?.package?.available } },
    { title: 'За выбранное количество / партию', value: 'custom' },
])
const selectedPriceBasis = computed(() => {
    const mode = editor.price_basis?.mode || 'unit'
    if (mode === 'custom') return { ...editor.price_basis, quantity: Number(editor.price_basis.quantity), label: String(editor.price_basis.label || '').trim() }
    return good.value?.avito_price_basis?.[mode] || { mode, quantity: mode === 'unit' ? 1 : null, label: good.value?.measurement?.unit_label, available: mode === 'unit' }
})
const priceBasisStale = computed(() => {
    const stored = editor.price_basis?.measurement
    const current = good.value?.measurement
    const storedKg = stored?.kilograms_per_unit ?? null
    const currentKg = current?.kilograms_per_unit ?? null
    const changedMeasurement = Boolean(stored && (
        Number(stored.measure_id) !== Number(current?.measure_id)
        || (storedKg === null || currentKg === null ? storedKg !== currentKg : Math.abs(Number(storedKg) - Number(currentKg)) >= 0.0000001)
    ))
    const savedPackage = editor.price_basis?.package_kg
    const changedPackage = editor.price_basis?.mode === 'package' && savedPackage != null
        && Math.abs(Number(savedPackage) - Number(good.value?.denominator || 0)) > 0.0000001
    return changedMeasurement || changedPackage
})
const priceBasisIssue = computed(() => {
    const current = good.value?.measurement
    if (!current?.measure_id || !current?.unit_label) return 'Задайте единицу товара, чтобы определить объём для цены Avito.'
    if (priceBasisStale.value) return 'Единица товара или фасовка изменились. Проверьте количество и подтвердите текущий объём.'
    return selectedPriceBasis.value.block_reason || ''
})
const priceCalculation = computed(() => {
    if (priceBasisIssue.value) return null
    const basis = selectedPriceBasis.value
    const quantity = Number(basis.quantity)
    if (basis.available === false || !Number.isFinite(quantity) || quantity <= 0 || !basis.label) return null
    if (editor.price_override !== null && editor.price_override !== '') {
        const amount = Number(editor.price_override)
        return Number.isFinite(amount) && amount >= 0 ? { manual: true, amount, total: Math.round(amount), basis } : null
    }
    if (!editor.selected_fields.includes('price')) return null
    const price = (good.value?.prices || []).find(item => Number(item.id) === Number(editor.price_value_id))
    const amount = Number(price?.amount)
    if (!price || price.amount == null || price.currency_code !== 'RUB' || !Number.isFinite(amount) || amount < 0) return null
    return { manual: false, amount, total: Math.round(amount * quantity), unrounded: amount * quantity, basis }
})
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
const profileAttached = computed(() => feed.value?.profile_status === 'attached')
const scopedGoodId = computed(() => positiveInteger(props.goodId))
const hasLinkedListing = computed(() => Boolean(scopedGoodId.value && props.linkedListings.some((item) =>
    Number(item.avito_account_id) === Number(accountId.value)
    && (!item.good_id || Number(item.good_id) === scopedGoodId.value))))
const canCreate = computed(() => workspaceReady.value && !loading.value && !hasLinkedListing.value)
const dirty = computed(() => Boolean(canEdit.value && savedEditorSignature.value && JSON.stringify(editorPayload()) !== savedEditorSignature.value))
const busy = computed(() => Boolean(loading.value || detailLoading.value || saving.value || actionLoading.value || createLoading.value || profileLoading.value || categoriesLoading.value || categoryFieldsLoading.value))

watch([dirty, busy], ([dirty, busy]) => emit('state', { dirty, busy }), { immediate: true })

watch(() => [props.workspace?.account_id, props.workspace?.connection_id, scopedGoodId.value], async ([nextAccountId, nextConnectionId, nextGoodId], [, , previousGoodId]) => {
    const accountChanged = positiveInteger(nextAccountId) !== positiveInteger(accountId.value)
    const connectionChanged = (nextConnectionId ?? null) !== (connectionId.value ?? null)
    applyWorkspace()
    if (initialized && (accountChanged || connectionChanged || nextGoodId !== previousGoodId)) {
        listVersion++
        detailVersion++
        selectedId.value = null
        items.value = []
        loading.value = false
        detailLoading.value = false
        clearEditor()
        createDialog.value = false
        if (workspaceReady.value) await loadList(true)
        else {
            items.value = []
            feed.value = null
            clearEditor()
        }
    }
})

watch(createSearch, () => {
    if (scopedGoodId.value) return
    clearTimeout(goodsTimer)
    goodsTimer = setTimeout(loadGoods, 280)
})

onMounted(initialize)
onBeforeUnmount(() => {
    disposed = true
    listVersion++
    detailVersion++
    clearTimeout(goodsTimer)
})

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
    const version = ++listVersion
    loading.value = true
    inlineError.value = ''
    try {
        const { data } = await axios.get('/api/avito/publications', { params: {
            account_id: id,
            good_id: scopedGoodId.value || undefined,
            page: page.value,
            per_page: perPage.value,
            search: String(search.value || '').trim() || undefined,
            status: statusFilter.value || undefined,
        } })
        if (disposed || version !== listVersion) return
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
        if (disposed || version !== listVersion) return
        showError(exception, 'Не удалось загрузить черновики публикаций.')
    } finally {
        if (version === listVersion) loading.value = false
    }
}

async function loadPublication(id) {
    if (!id || !positiveInteger(accountId.value)) return
    selectedId.value = id
    const version = ++detailVersion
    const requestedGood = scopedGoodId.value
    detailLoading.value = true
    inlineError.value = ''
    try {
        const { data } = await axios.get(`/api/avito/publications/${id}`, {
            params: { account_id: positiveInteger(accountId.value) },
        })
        if (disposed || version !== detailVersion) return
        if (requestedGood && Number(data.publication?.good_id) !== requestedGood) {
            clearEditor()
            return showError(null, 'Публикация относится к другому товару. Обновите список.')
        }
        hydrate(data)
    } catch (exception) {
        if (disposed || version !== detailVersion) return
        showError(exception, 'Не удалось открыть черновик.')
    } finally {
        if (version === detailVersion) detailLoading.value = false
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
        price_basis: Object.keys(cloneRecord(draft.price_basis)).length ? cloneRecord(draft.price_basis) : { mode: 'unit' },
        address: draft.address || '',
        contact_phone: draft.contact_phone || '',
        manager_name: draft.manager_name || '',
        allow_email: draft.allow_email ?? false,
        ad_type: draft.ad_type || '',
        condition: draft.condition || '',
        listing_fee: draft.listing_fee || null,
        category_fields: cloneRecord(draft.category_fields),
        category_schema: cloneList(draft.category_schema),
    })
    previewXml.value = ''
    validateSelectedCategory()
    savedEditorSignature.value = JSON.stringify(editorPayload())
}

function clearEditor() {
    publication.value = null
    preview.value = null
    previewXml.value = ''
    revisions.value = []
    savedEditorSignature.value = ''
}

function reset() {
    if (publication.value) hydrate({ publication: publication.value, preview: preview.value, feed: feed.value })
    else clearEditor()
    createDialog.value = false
    approveDialog.value = false
    profileDialog.value = false
    uploadDialog.value = false
    uploadStatusDialog.value = false
    inlineError.value = ''
}

defineExpose({ reset })

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
        emit('changed')
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
        price_basis: cloneRecord(editor.price_basis),
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

function changePriceBasis(mode) {
    editor.price_basis = mode === 'custom'
        ? { mode, quantity: 1, label: 'партия' }
        : { mode }
}

function changeCustomBasis(field, value) {
    // A new explicit quantity/label choice resets the saved measurement snapshot.
    editor.price_basis = {
        mode: 'custom',
        quantity: editor.price_basis?.quantity ?? 1,
        label: editor.price_basis?.label || '',
        [field]: value,
    }
}

function renewPriceBasis() {
    if (editor.price_basis?.mode === 'custom') changeCustomBasis('quantity', editor.price_basis.quantity)
    else changePriceBasis(editor.price_basis?.mode || 'unit')
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
        emit('changed')
        emit('notice', data.message || 'Версия зафиксирована и включена в feed.')
        await loadList(false)
    } catch (exception) {
        showError(exception, 'Не удалось зафиксировать версию.')
    } finally {
        actionLoading.value = ''
    }
}

async function loadCategories() {
    if (categories.value.length) {
        validateSelectedCategory()
        return
    }
    if (categoriesLoading.value) return
    categoriesLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/publications/categories', { params: baseParams() })
        categories.value = data.items || []
        validateSelectedCategory()
    } catch (exception) {
        showError(exception, 'Avito не вернул дерево категорий.')
    } finally {
        categoriesLoading.value = false
    }
}

async function chooseCategory(slug) {
    const category = categories.value.find((item) => item.slug === slug)
    if (category?.is_leaf === false) {
        rejectNonLeafCategory(category)
        return
    }

    editor.category_node_slug = slug || null
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
        if (isNonLeafCategoryError(exception)) {
            if (category) category.is_leaf = false
            rejectNonLeafCategory(category)
            return
        }
        showError(exception, 'Не удалось получить поля выбранной категории.')
    } finally {
        categoryFieldsLoading.value = false
    }
}

function validateSelectedCategory() {
    const slug = editor.category_node_slug
    if (!slug || !categories.value.length) return true
    const category = categories.value.find((item) => item.slug === slug)
    if (category?.is_leaf !== false) return true

    rejectNonLeafCategory(category)
    return false
}

function rejectNonLeafCategory(category) {
    const name = category?.path || category?.name || editor.category_name || 'Выбранный раздел'
    editor.category_node_slug = null
    editor.category_name = ''
    editor.category_fields = {}
    editor.category_schema = []
    categorySearch.value = ''
    showError(null, `«${name}» — раздел Avito, а не конечная категория. Выберите самый нижний пункт дерева.`)
}

function isNonLeafCategoryError(exception) {
    const message = errorMessage(exception, '')
    return exception?.response?.status === 400
        && /not a leaf node|не (?:является )?конечн/i.test(message)
}

async function openCreate() {
    if (!canCreate.value) return
    createDialog.value = true
    createGoodId.value = scopedGoodId.value
    createSearch.value = ''
    if (!scopedGoodId.value) await loadGoods()
}

async function loadGoods() {
    createLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/listings/goods', {
            params: { search: String(createSearch.value || '').trim() || undefined },
        })
        createGoods.value = data.items || []
    } catch (exception) {
        showError(exception, 'Не удалось найти Good.')
    } finally {
        createLoading.value = false
    }
}

async function createPublication() {
    if (!canCreate.value || createLoading.value) return
    const goodId = scopedGoodId.value || positiveInteger(createGoodId.value)
    const publicationAccountId = positiveInteger(accountId.value)
    if (!goodId || !publicationAccountId) return

    createLoading.value = true
    inlineError.value = ''

    let data = null

    try {
        const response = await axios.post('/api/avito/publications', {
            account_id: publicationAccountId,
            connection_id: connectionId.value || null,
            good_id: goodId,
        })

        data = response.data
    } catch (exception) {
        showError(exception, 'Не удалось создать черновик.')
        createLoading.value = false

        return
    }

    const publicationId = positiveInteger(data?.publication?.id)
    if (!publicationId) {
        showError(
            new Error('Сервер не вернул ID сохранённого черновика.'),
            'Черновик сохранён, но сервер вернул неполный ответ.'
        )
        createLoading.value = false

        return
    }

    createDialog.value = false
    selectedId.value = publicationId

    try {
        hydrate(data)
        emit('changed')
        emit('notice', 'Черновик создан из Good. В Avito ничего не отправлено.')
        await loadList(true)
        selectedId.value = publicationId
        await loadPublication(publicationId)
    } catch (exception) {
        console.error('Avito publication editor refresh failed after draft creation:', exception)
        emit('notice', `Черновик #${publicationId} создан. Обновите список, если редактор не открылся.`)
    } finally {
        createLoading.value = false
    }
}

async function openProfile() {
    profileDialog.value = true
    profileExists.value = null
    profileAvailable.value = null
    profileUnavailableReason.value = ''
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
        profileAvailable.value = data.available !== false
        profileUnavailableReason.value = data.unavailable_reason || ''
        profileExists.value = profileAvailable.value ? Boolean(data.exists) : null
        if (!profileAvailable.value) return
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
    if (profileAvailable.value === false) return
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
        emit('changed')
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
        emit('changed')
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

function cloneRecord(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return {}

    try {
        return JSON.parse(JSON.stringify(value))
    } catch {
        return {}
    }
}

function cloneList(value) {
    if (!Array.isArray(value)) return []

    try {
        return JSON.parse(JSON.stringify(value))
    } catch {
        return []
    }
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

function profileStatusLabel(status) {
    return ({
        not_checked: 'не проверен',
        attached: 'feed подключён',
        feed_missing: 'feed не подключён',
        missing: 'профиль не создан',
        unavailable: 'доступ не открыт',
    })[status] || status || 'не проверен'
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—'
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(Number(value))
}

function formatQuantity(value) {
    if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return '—'
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 6 }).format(Number(value))
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
    return exception?.response?.data?.message || exception?.message || fallback
}

function showError(exception, fallback) {
    console.error(fallback, exception)
    inlineError.value = errorMessage(exception, fallback)
    emit('error', inlineError.value)
}
</script>

<template>
    <section class="publisher" :class="{ 'publisher--embedded': scopedGoodId }">
        <header class="publisher-toolbar">
            <strong v-if="scopedGoodId" class="scoped-title">Публикации этого товара</strong>
            <v-text-field v-else v-model="search" label="Good, категория или Ameise ID" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" hide-details clearable @keyup.enter="loadList(true)" />
            <v-select v-model="statusFilter" :items="STATUS_OPTIONS" label="Статус" density="compact" variant="outlined" hide-details clearable />
            <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" :disabled="!workspaceReady" @click="loadList(true)">Обновить</v-btn>
            <v-btn size="small" color="deep-purple-accent-1" prepend-icon="mdi-plus" :disabled="!canCreate" :title="hasLinkedListing ? 'У товара уже есть связанное объявление. Редактируйте существующую публикацию.' : ''" @click="openCreate">{{ scopedGoodId ? 'Новая публикация' : 'Из Good' }}</v-btn>
        </header>

        <p v-if="scopedGoodId" class="publication-flow">Черновик → предпросмотр → подтверждённая версия → Автозагрузка → отчёт Avito. Сохранение черновика само по себе не меняет объявление.</p>
        <v-alert v-if="hasLinkedListing && !items.length && !loading" type="info" variant="tonal" density="compact" class="publisher-alert">
            У товара уже есть объявление Avito. Новая публикация создаст дубликат. Для объявления, созданного вручную, цену меняйте во вкладке «Цены», остальные данные — в кабинете Avito. Публикации Автозагрузки доступны здесь после сброса фильтра статуса, если они были созданы в приложении.
        </v-alert>

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
        <v-alert v-if="feed?.profile_status === 'unavailable'" type="warning" variant="tonal" density="compact" class="publisher-alert">
            Avito не открыл API профиля Автозагрузки для этого кабинета. Активируйте Автозагрузку в кабинете Avito, затем повторите проверку профиля.
            <template #append><v-btn :href="AVITO_AUTOLOAD_SETTINGS_URL" target="_blank" rel="noopener" size="x-small" variant="outlined">Настройки Avito</v-btn></template>
        </v-alert>

        <div class="feed-strip">
            <div>
                <span class="feed-label">Защищённый feed</span>
                <strong>{{ feed?.name || 'ещё не создан' }}</strong>
                <small>{{ feed ? `${feed.approved_publications_count} версий · профиль: ${profileStatusLabel(feed.profile_status)}` : 'создастся вместе с первым черновиком' }}</small>
            </div>
            <code v-if="feed && !scopedGoodId" :title="feed.url">{{ feed.url }}</code>
            <small v-if="scopedGoodId" class="feed-scope">Запуск обрабатывает весь профиль Автозагрузки кабинета</small>
            <v-btn size="x-small" variant="text" prepend-icon="mdi-cog-outline" :disabled="!workspaceReady" @click="openProfile">Профиль</v-btn>
            <v-btn size="x-small" variant="text" prepend-icon="mdi-cloud-search-outline" :disabled="!profileAttached" :loading="actionLoading === 'status'" @click="refreshRemoteStatus">Загрузка</v-btn>
            <v-btn size="x-small" color="deep-purple" prepend-icon="mdi-cloud-upload-outline" :title="profileAttached ? 'Запустить Автозагрузку' : 'Сначала подключите feed в профиле'" :disabled="!feed?.approved_publications_count || !canUseRemoteMutations || !profileAttached" @click="uploadDialog = true">Запустить</v-btn>
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
                                <v-select :model-value="editor.price_basis.mode" :items="priceBasisOptions" label="Цена на Avito указана за" density="compact" variant="outlined" :disabled="!canEdit" @update:model-value="changePriceBasis" />
                                <v-text-field v-model.number="editor.price_override" label="Своя итоговая цена за выбранный объём, ₽" hint="Необязательно. Указанная сумма не умножается на количество." persistent-hint type="number" min="0" density="compact" variant="outlined" :disabled="!canEdit" />
                                <div v-if="editor.price_basis.mode === 'custom'" class="price-basis-custom span-2">
                                    <v-text-field :model-value="editor.price_basis.quantity" :label="`Количество, ${good?.measurement?.unit_label || 'ед. товара'}`" type="number" min="0.000001" step="any" density="compact" variant="outlined" :disabled="!canEdit" @update:model-value="changeCustomBasis('quantity', $event)" />
                                    <v-text-field :model-value="editor.price_basis.label" label="Обозначение объёма: партия, мешок…" counter="80" maxlength="80" density="compact" variant="outlined" :disabled="!canEdit" @update:model-value="changeCustomBasis('label', $event)" />
                                </div>
                                <div class="price-basis-calculation span-2" aria-live="polite">
                                    <template v-if="priceCalculation">
                                        <span v-if="priceCalculation.manual">Итог задан вручную · {{ formatQuantity(priceCalculation.amount) }} ₽</span>
                                        <span v-else>{{ formatQuantity(priceCalculation.amount) }} ₽ / {{ good?.measurement?.unit_label }} × {{ formatQuantity(priceCalculation.basis.quantity) }} {{ good?.measurement?.unit_label }} = {{ formatQuantity(priceCalculation.unrounded) }} ₽</span>
                                        <strong>На Avito: {{ formatMoney(priceCalculation.total) }} ₽ / {{ priceCalculation.basis.label }}</strong>
                                        <small>Округление до целого рубля после расчёта. Точный результат и единица будут проверены в предпросмотре.</small>
                                    </template>
                                    <span v-else>{{ priceBasisIssue || 'Выберите цену в RUB и задайте положительное количество с обозначением объёма.' }}</span>
                                    <v-btn v-if="priceBasisStale" size="x-small" variant="outlined" :disabled="!canEdit || !good?.measurement?.measure_id" @click="renewPriceBasis">Подтвердить текущий объём</v-btn>
                                </div>
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
                                    <span>{{ preview.payload?.category }}</span><h4>{{ preview.payload?.title }}</h4><strong>{{ formatMoney(preview.payload?.price) }} ₽<template v-if="preview.price_details?.sale_unit_label"> / {{ preview.price_details.sale_unit_label }}</template></strong><small v-if="preview.price_details?.formula">{{ preview.price_details.formula }}</small><small>{{ preview.payload?.address }} · {{ preview.payload?.contact_phone }}</small><pre>{{ preview.payload?.description }}</pre>
                                </section>
                                <section v-if="currentRevision" class="snapshot-preview">
                                    <header><strong>Точно опубликованная версия v{{ currentRevision.version }}</strong><v-chip size="x-small" :color="statusColor(publication.status)">{{ currentRevision.status }}</v-chip></header>
                                    <small>Это локальный неизменяемый снимок. Изменения Good его не переписывают.</small>
                                    <strong class="snapshot-price">{{ formatMoney(currentRevision.payload?.price) }} ₽<template v-if="currentRevision.payload?.price_details?.sale_unit_label"> / {{ currentRevision.payload.price_details.sale_unit_label }}</template></strong>
                                    <small v-if="currentRevision.payload?.price_details?.formula">{{ currentRevision.payload.price_details.formula }}</small>
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
                                    <div><strong>{{ revision.payload?.title }}</strong><span class="revision-price">{{ formatMoney(revision.payload?.price) }} ₽<template v-if="revision.payload?.price_details?.sale_unit_label"> / {{ revision.payload.price_details.sale_unit_label }}</template></span><small v-if="revision.payload?.price_details?.formula" class="revision-formula">{{ revision.payload.price_details.formula }}</small><p v-for="message in revision.report_messages" :key="`${message.code}-${message.message}`" :class="`report-${message.level}`">{{ message.level }} · {{ message.message }}</p><pre>{{ revision.payload?.description }}</pre></div>
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
            <v-card class="dialog-card" :class="{ 'dialog-card--embedded': scopedGoodId }"><v-card-title>Новый черновик объявления</v-card-title><v-card-text>
                <p v-if="scopedGoodId"><strong>{{ goodName || `Товар #${scopedGoodId}` }}</strong><br>Название, описание, фото и цену можно проверить перед публикацией. Черновик сохраняется только в приложении.</p>
                <v-text-field v-else v-model="createSearch" label="Найти Good" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" autofocus clearable />
                <div v-if="!scopedGoodId" class="good-results" role="radiogroup" aria-label="Выбор Good для объявления">
                    <button v-for="item in createGoods" :key="item.id" type="button" role="radio" class="good-result" :class="{ active: Number(createGoodId) === Number(item.id) }" :aria-checked="Number(createGoodId) === Number(item.id)" @click="createGoodId = Number(item.id)"><v-icon :icon="Number(createGoodId) === Number(item.id) ? 'mdi-radiobox-marked' : 'mdi-radiobox-blank'" size="22" /><div><strong>{{ item.name }}</strong><small>#{{ item.id }} · {{ item.is_published ? 'опубликован' : 'не опубликован' }} · {{ item.prices?.length || 0 }} цен · {{ item.media?.length || 0 }} фото</small></div></button>
                </div>
            </v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="createDialog = false">Отмена</v-btn><v-btn color="deep-purple" :disabled="!positiveInteger(createGoodId)" :loading="createLoading" @click="createPublication">Создать черновик</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="approveDialog" max-width="640">
            <v-card class="dialog-card" :class="{ 'dialog-card--embedded': scopedGoodId }"><v-card-title>Зафиксировать версию?</v-card-title><v-card-text>
                <v-alert :type="preview?.valid ? 'info' : 'error'" variant="tonal">{{ preview?.valid ? 'Будут сохранены точные значения текста, цены и копии выбранных фото. Версия попадёт в feed, но запуск Avito останется отдельным действием.' : 'Preview содержит ошибки. Подтверждение будет отклонено сервером.' }}</v-alert>
                <p>Good останется источником истины. Последующие изменения Good не изменят эту версию — потребуется новое осознанное подтверждение.</p>
            </v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="approveDialog = false">Отмена</v-btn><v-btn color="deep-purple" :disabled="!preview?.valid" :loading="actionLoading === 'approve'" @click="approve">Зафиксировать</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="profileDialog" max-width="860">
            <v-card class="dialog-card" :class="{ 'dialog-card--embedded': scopedGoodId }"><v-card-title>Feed и профиль Автозагрузки</v-card-title><v-card-text>
                <v-alert type="info" variant="tonal" density="compact">Подключение сохраняет все чужие feed профиля и добавляет только <b>{{ feed?.name }}</b>. Автозагрузка не включается сама.</v-alert>
                <v-alert v-if="profileAvailable === false" type="warning" variant="tonal" density="compact" class="mt-2">
                    {{ profileUnavailableReason || 'Avito не открыл API профиля Автозагрузки для этого кабинета.' }}
                    <div class="mt-2"><v-btn :href="AVITO_AUTOLOAD_SETTINGS_URL" target="_blank" rel="noopener" size="small" variant="outlined" prepend-icon="mdi-open-in-new">Открыть настройки Avito</v-btn></div>
                </v-alert>
                <div class="form-grid mt-4"><v-text-field v-model="profileForm.address" label="Адрес по умолчанию" density="compact" variant="outlined" /><v-text-field v-model="profileForm.contact_phone" label="Телефон по умолчанию" density="compact" variant="outlined" /><v-text-field v-model="profileForm.manager_name" label="Контактное лицо" density="compact" variant="outlined" /><v-text-field v-model="profileForm.report_email" label="E-mail отчётов *" density="compact" variant="outlined" /></div>
                <v-switch v-model="profileForm.autoload_enabled" label="Разрешить Avito выполнять feed по расписанию" color="warning" density="compact" />
                <v-textarea v-model="profileForm.schedule_json" label="Расписание JSON (Москва): weekdays 0–6, time_slots 0–23, rate" rows="5" density="compact" variant="outlined" spellcheck="false" />
                <v-checkbox v-if="profileExists === false" v-model="profileForm.agreement" label="Принимаю правила использования Avito Автозагрузки" density="compact" />
            </v-card-text><v-card-actions><v-btn variant="text" :loading="profileLoading" @click="checkProfile">Проверить снова</v-btn><v-spacer /><v-btn variant="text" @click="profileDialog = false">Закрыть</v-btn><v-btn color="deep-purple" :disabled="!canUseRemoteMutations || profileAvailable === false" :loading="profileLoading" @click="attachProfile">Сохранить и подключить feed</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="uploadDialog" max-width="620">
            <v-card class="dialog-card" :class="{ 'dialog-card--embedded': scopedGoodId }"><v-card-title>Запустить Автозагрузку Avito?</v-card-title><v-card-text><v-alert type="warning" variant="tonal">Avito обработает весь подключённый профиль, включая <b>{{ feed?.approved_publications_count || 0 }}</b> текущих версий Ameise и другие feed кабинета. Повторный ручной запуск возможен не чаще раза в час.</v-alert><p>Черновики и архивные публикации не отправляются. После запуска используйте «Прочитать отчёт Avito».</p></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="uploadDialog = false">Отмена</v-btn><v-btn color="warning" :loading="actionLoading === 'upload'" @click="requestUpload">Подтверждаю запуск</v-btn></v-card-actions></v-card>
        </v-dialog>

        <v-dialog v-model="uploadStatusDialog" max-width="760">
            <v-card class="dialog-card" :class="{ 'dialog-card--embedded': scopedGoodId }"><v-card-title>Текущая загрузка Avito</v-card-title><v-card-text><pre class="status-json">{{ prettyJson(uploadStatus) }}</pre></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="uploadStatusDialog = false">Закрыть</v-btn></v-card-actions></v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.publisher, .dialog-card { --pub-text: #e8ebff; --pub-surface: #181b30; --pub-border: #30344e; --pub-panel: #111427; --pub-muted: #8e94b3; --pub-accent: #9f91f2; --pub-mutedAlt: #8d93af; --pub-canvas: #15182b; --pub-buttonText: #9da3c0; --pub-buttonBorder: #30344d; --pub-strong: #c7baff; --pub-rowText: #dfe2f6; --pub-rowBorder: #282c43; --pub-hover: #1b1f37; --pub-selected: #20233d; --pub-rowMuted: #8e94ae; --pub-pagerBorder: #343850; --pub-pager: #171a2e; --pub-empty: #888faa; --pub-link: #a99bff; --pub-fieldText: #979db9; --pub-fieldSelected: #e8e3ff; --pub-help: #8289a6; --pub-media: #1b1f35; --pub-previewHelp: #8d94b1; --pub-error: #e2a4a9; --pub-warning: #e6bd70; --pub-previewMuted: #8e95b2; --pub-pre: #cfd3e8; --pub-code: #b7e6cc; --pub-historyMuted: #8c93ad; --pub-reportError: #ffb2b8; --pub-reportWarning: #ffd48a; --pub-reportInfo: #aeb6d6; --pub-dialogText: #edf0ff; --pub-dialog: #191c31; --pub-dialogMuted: #a7adc7; --pub-statusCode: #b8e3cb; --pub-status: #101326; }
.publisher--embedded, .dialog-card--embedded { --pub-text: #353341; --pub-surface: #ffffff; --pub-border: #ddd9e5; --pub-panel: #faf9fc; --pub-muted: #746b82; --pub-accent: #71558f; --pub-mutedAlt: #746b82; --pub-canvas: #f6f4f9; --pub-buttonText: #71677d; --pub-buttonBorder: #ddd9e5; --pub-strong: #60467d; --pub-rowText: #353341; --pub-rowBorder: #e7e3ec; --pub-hover: #f1edf7; --pub-selected: #eee7f6; --pub-rowMuted: #807688; --pub-pagerBorder: #ddd9e5; --pub-pager: #f5f2f8; --pub-empty: #807688; --pub-link: #694a94; --pub-fieldText: #736780; --pub-fieldSelected: #563e73; --pub-help: #81768c; --pub-media: #f0ecf5; --pub-previewHelp: #81768c; --pub-error: #a13b47; --pub-warning: #91602b; --pub-previewMuted: #81768c; --pub-pre: #46404e; --pub-code: #36754f; --pub-historyMuted: #81768c; --pub-reportError: #a13b47; --pub-reportWarning: #91602b; --pub-reportInfo: #766d85; --pub-dialogText: #353341; --pub-dialog: #ffffff; --pub-dialogMuted: #81768c; --pub-statusCode: #36754f; --pub-status: #f7f5fa; }
.publisher { min-height: calc(100vh - 245px); color: var(--pub-text); }
.publisher-toolbar { display: grid; grid-template-columns: minmax(260px, 1fr) 180px auto auto; gap: 5px; padding: 5px; border-bottom: 1px solid var(--pub-border); background: var(--pub-surface); }
.publisher-alert { margin: 5px; font-size: 11px; }
.feed-strip { display: grid; grid-template-columns: minmax(220px, auto) minmax(180px, 1fr) auto auto auto; align-items: center; gap: 7px; min-height: 48px; padding: 5px 9px; border-bottom: 1px solid var(--pub-border); background: var(--pub-panel); }
.feed-strip > div { display: grid; line-height: 1.15; }.feed-label, .eyebrow { color: var(--pub-accent); font-size: 8px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }.feed-strip strong { font-size: 12px; }.feed-strip small { color: var(--pub-muted); font-size: 9px; }.feed-strip code { overflow: hidden; color: var(--pub-mutedAlt); font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.status-strip { display: flex; overflow-x: auto; gap: 4px; padding: 4px 5px; border-bottom: 1px solid var(--pub-border); background: var(--pub-canvas); }.status-strip button { display: flex; align-items: center; gap: 6px; min-width: max-content; padding: 3px 7px; color: var(--pub-buttonText); font-size: 9px; border: 1px solid var(--pub-buttonBorder); border-radius: 6px; }.status-strip button.active { color: #fff; border-color: #8d75ff; background: rgba(115, 82, 232, .2); }.status-strip strong { color: var(--pub-strong); }
.publisher-grid { display: grid; grid-template-columns: minmax(245px, 21%) minmax(0, 1fr); height: calc(100vh - 378px); min-height: 520px; }
.draft-list { position: relative; overflow: auto; border-right: 1px solid var(--pub-border); background: var(--pub-panel); }.draft-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 2px 6px; width: 100%; padding: 7px 8px; color: var(--pub-rowText); text-align: left; border-bottom: 1px solid var(--pub-rowBorder); background: transparent; }.draft-row:hover { background: var(--pub-hover); }.draft-row.active { box-shadow: inset 3px 0 #957dff; background: var(--pub-selected); }.draft-row__main { display: grid; min-width: 0; }.draft-row__main strong, .draft-row__main small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.draft-row__main strong { font-size: 11px; }.draft-row__main small, .draft-row__id { color: var(--pub-rowMuted); font-size: 8px; }.draft-row__meta { display: flex; align-items: center; gap: 4px; }.draft-row__id { grid-column: 1 / 3; font-family: monospace; }.list-pager { position: sticky; bottom: 0; display: flex; align-items: center; justify-content: flex-end; gap: 4px; padding: 4px 7px; font-size: 9px; border-top: 1px solid var(--pub-pagerBorder); background: var(--pub-pager); }.list-pager span { margin-right: auto; }
.empty-list, .editor-empty, .center { display: flex; min-height: 220px; align-items: center; justify-content: center; flex-direction: column; gap: 6px; color: var(--pub-empty); text-align: center; }.empty-list small, .editor-empty span { max-width: 440px; font-size: 10px; }
.editor-shell { position: relative; display: flex; overflow: hidden; min-width: 0; flex-direction: column; background: var(--pub-canvas); }.editor-head { display: flex; min-height: 49px; align-items: center; justify-content: space-between; gap: 8px; padding: 5px 10px; border-bottom: 1px solid var(--pub-border); }.editor-head h3 { overflow: hidden; max-width: 720px; margin: 1px 0 0; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }.editor-head__meta { display: flex; align-items: center; gap: 5px; }.editor-head__meta a { color: var(--pub-link); font-size: 10px; }.editor-tabs { min-height: 34px; border-bottom: 1px solid var(--pub-border); }.editor-tabs :deep(.v-tab) { min-height: 34px; padding: 0 10px; font-size: 9px; }.editor-window { overflow: auto; flex: 1; padding: 7px; }.editor-window :deep(.v-window__container), .editor-window :deep(.v-window-item) { min-height: 100%; }
.field-picker { display: flex; align-items: center; gap: 5px; margin-bottom: 7px; }.field-picker label { display: flex; align-items: center; gap: 2px; padding: 1px 6px 1px 1px; color: var(--pub-fieldText); font-size: 9px; border: 1px solid var(--pub-pagerBorder); border-radius: 6px; }.field-picker label.active { color: var(--pub-fieldSelected); border-color: #7963df; background: rgba(105, 76, 210, .17); }.field-picker > small { margin-left: auto; max-width: 430px; color: var(--pub-help); font-size: 8px; text-align: right; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }.span-2 { grid-column: 1 / -1; }.form-grid :deep(.v-input), .category-line :deep(.v-input) { font-size: 10px; }.core-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); }.category-line { display: grid; grid-template-columns: minmax(280px, 1.5fr) minmax(180px, 1fr) auto; gap: 5px; }
.media-picker, .dynamic-fields, .extra-fields { margin-top: 7px; padding: 7px; border: 1px solid var(--pub-border); border-radius: 7px; background: var(--pub-panel); }.media-picker > header, .dynamic-fields > header, .extra-fields > header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 10px; }.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(105px, 1fr)); gap: 5px; }.media-grid label { position: relative; overflow: hidden; min-height: 112px; border: 2px solid transparent; border-radius: 6px; background: var(--pub-media); }.media-grid label.active { border-color: #927aff; }.media-grid img { width: 100%; height: 82px; object-fit: cover; }.media-grid :deep(.v-checkbox-btn) { position: absolute; top: 1px; right: 1px; border-radius: 50%; background: rgba(10, 11, 22, .72); }.media-grid span { display: block; overflow: hidden; padding: 2px 4px; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }.extra-row { display: grid; grid-template-columns: .7fr 1.3fr auto; gap: 4px; margin-top: 4px; }
.preview-toolbar, .history-actions { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; color: var(--pub-previewHelp); font-size: 9px; }.preview-grid { display: grid; grid-template-columns: minmax(240px, .7fr) minmax(340px, 1.3fr); gap: 7px; }.validation-box, .ad-preview, .snapshot-preview, .xml-preview { padding: 8px; border: 1px solid var(--pub-border); border-radius: 7px; background: var(--pub-panel); }.validation-box.valid { border-color: rgba(54, 189, 120, .5); }.validation-box.invalid { border-color: rgba(238, 85, 99, .5); }.validation-box header, .snapshot-preview header { display: flex; align-items: center; justify-content: space-between; gap: 5px; }.validation-box ul, .validation-box p { margin: 5px 0; padding-left: 15px; color: var(--pub-error); font-size: 9px; }.validation-box p { color: var(--pub-warning); }.ad-preview { display: grid; gap: 4px; }.ad-preview h4 { margin: 0; font-size: 16px; }.ad-preview > span, .ad-preview > small, .snapshot-preview > small { color: var(--pub-previewMuted); font-size: 9px; }.ad-preview pre, .snapshot-preview pre, .revision-list pre { overflow: auto; max-height: 260px; margin: 4px 0 0; color: var(--pub-pre); font: 10px/1.45 inherit; white-space: pre-wrap; }.snapshot-preview, .xml-preview { grid-column: 1 / -1; }.xml-preview summary { cursor: pointer; font-size: 10px; }.xml-preview pre { overflow: auto; max-height: 330px; color: var(--pub-code); font: 9px/1.45 monospace; white-space: pre-wrap; }
.revision-list { display: grid; gap: 5px; }.revision-list details { border: 1px solid var(--pub-border); border-radius: 6px; background: var(--pub-panel); }.revision-list summary { display: flex; align-items: center; gap: 8px; padding: 7px; cursor: pointer; font-size: 9px; }.revision-list summary span { margin-left: auto; color: var(--pub-historyMuted); }.revision-list details > div { display: grid; gap: 3px; padding: 7px; border-top: 1px solid var(--pub-border); font-size: 10px; }
.revision-list p { margin: 1px 0; padding: 3px 5px; border-radius: 4px; }.report-error { color: var(--pub-reportError); background: rgba(220, 56, 74, .14); }.report-warning { color: var(--pub-reportWarning); background: rgba(224, 160, 48, .12); }.report-info { color: var(--pub-reportInfo); background: rgba(120, 130, 170, .1); }
.editor-actions { display: flex; align-items: center; gap: 5px; min-height: 45px; padding: 5px 8px; border-top: 1px solid var(--pub-border); background: var(--pub-panel); }.action-spacer { flex: 1; }
.dialog-card { color: var(--pub-dialogText) !important; background: var(--pub-dialog) !important; }.dialog-card p { color: var(--pub-dialogMuted); font-size: 11px; }.good-results { overflow: auto; max-height: 420px; }.good-result { display: flex; width: 100%; align-items: center; gap: 8px; padding: 7px 5px; color: var(--pub-text); text-align: left; border-bottom: 1px solid var(--pub-border); background: transparent; }.good-result:hover, .good-result.active { background: rgba(109, 78, 218, .18); }.good-result.active { box-shadow: inset 3px 0 #9b82ff; }.good-result div { display: grid; }.good-result strong { font-size: 11px; }.good-result small { color: var(--pub-rowMuted); font-size: 9px; }
.status-json { overflow: auto; max-height: 60vh; padding: 8px; color: var(--pub-statusCode); font: 10px/1.5 monospace; border: 1px solid var(--pub-border); border-radius: 6px; background: var(--pub-status); white-space: pre-wrap; }
@media (max-width: 1050px) { .publisher-toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }.publisher-grid { grid-template-columns: 220px minmax(0, 1fr); height: auto; min-height: 650px; }.feed-strip { grid-template-columns: 1fr auto auto auto; }.feed-strip code { display: none; }.core-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px) { .publisher-grid { display: block; }.draft-list { max-height: 240px; border-right: 0; border-bottom: 1px solid var(--pub-border); }.editor-shell { min-height: 650px; }.form-grid, .core-fields, .category-line, .preview-grid { grid-template-columns: 1fr; }.span-2, .snapshot-preview, .xml-preview { grid-column: 1; }.field-picker { flex-wrap: wrap; }.field-picker > small { margin-left: 0; text-align: left; } }
.publisher--embedded { min-height: 0; border: 1px solid var(--pub-border); border-radius: 8px; overflow: hidden; }
.publisher--embedded .publisher-toolbar { align-items: center; grid-template-columns: minmax(150px, 1fr) 160px auto auto; padding: 6px 8px; }
.scoped-title { font-size: 12px; }
.price-basis-custom { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }
.price-basis-calculation { display: grid; gap: 3px; padding: 8px 10px; border: 1px solid var(--pub-border); border-radius: 6px; background: var(--pub-panel); font-size: 12px; }
.price-basis-calculation small { color: var(--pub-muted); font-size: 10px; }
.publication-flow { margin: 0; padding: 7px 9px; color: var(--pub-muted); background: var(--pub-panel); font-size: 11px; border-bottom: 1px solid var(--pub-border); }
.feed-scope { color: var(--pub-muted); font-size: 10px; }
.publisher--embedded .publisher-grid { height: min(680px, 75vh); min-height: 460px; grid-template-columns: minmax(170px, 21%) minmax(0, 1fr); }
.publisher--embedded .status-strip button.active { color: #60467d; }
.publisher--embedded .editor-head h3 { max-width: min(50vw, 580px); }
.publisher--embedded .editor-actions { flex-wrap: wrap; }
.publisher--embedded :deep(.v-field__input) { min-height: 35px; padding-top: 7px; padding-bottom: 7px; font-size: 12px; }
.publisher--embedded :deep(.v-btn) { font-size: 10px; letter-spacing: .02em; }
@media (max-width: 1050px) { .publisher--embedded .feed-strip { grid-template-columns: 1fr auto auto auto; }.publisher--embedded .feed-scope { grid-column: 1 / -1; grid-row: 2; } }
@media (max-width: 720px) { .publisher--embedded .publisher-toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); }.publisher--embedded .publisher-grid { display: block; height: auto; min-height: 0; }.publisher--embedded .editor-shell { min-height: 480px; }.publisher--embedded .feed-strip { grid-template-columns: repeat(3, auto); }.publisher--embedded .feed-strip > div { grid-column: 1 / -1; }.publisher--embedded .feed-scope { grid-row: auto; } }
</style>
