<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    permissions: {
        type: Object,
        required: true,
    },
    readOnly: {
        type: Boolean,
        default: true,
    },
    bankTimezone: {
        type: String,
        default: 'Europe/Moscow',
    },
})

const activeTab = ref('overview')
const busy = reactive({
    dashboard: false,
    transactions: false,
    detail: false,
    sync: false,
    logs: false,
    drafts: false,
    reconcile: false,
    health: false,
})
const dashboard = ref({
    enabled: false,
    read_only: true,
    connection: null,
    accounts: [],
    totals: {},
    counters: {},
})
const transactions = ref([])
const transactionsTotal = ref(0)
const transactionPage = ref(1)
const transactionPerPage = ref(25)
const transactionSort = reactive({ by: 'operation_date', direction: 'desc' })
const transactionDrawer = ref(false)
const selectedTransaction = ref(null)
const receivables = ref([])
const receivableSearch = ref('')
const allocationRows = ref([])
const allocationComment = ref('')
const drafts = ref([])
const draftTotal = ref(0)
const draftOptions = ref({ accounts: [], entities: [], purchases: [] })
const draftDialog = ref(false)
const editingDraft = ref(null)
const syncRuns = ref([])
const syncRunsTotal = ref(0)
const errors = ref([])
const errorsTotal = ref(0)
const auditEvents = ref([])
const auditTotal = ref(0)
const health = ref(null)
const ownerEntities = ref([])
const ownerEntityId = ref(null)
const pageError = ref('')
const snackbar = reactive({ show: false, text: '', color: 'success' })

const filters = reactive({
    date_from: '',
    date_to: '',
    account_id: null,
    direction: null,
    amount_min: '',
    amount_max: '',
    entity: '',
    inn: '',
    purpose: '',
    status: null,
    reconciliation_status: null,
    warning: false,
})

const emptyDraft = () => ({
    number: '',
    document_date: new Date().toISOString().slice(0, 10),
    payer_bank_account_id: null,
    recipient_entity_id: null,
    purchase_id: null,
    amount: '',
    currency: 'RUB',
    payer_name: '',
    payer_inn: '',
    payer_kpp: '',
    payer_account: '',
    payer_bank_name: '',
    payer_bic: '',
    payer_corr_account: '',
    recipient_name: '',
    recipient_inn: '',
    recipient_kpp: '',
    recipient_account: '',
    recipient_bank_name: '',
    recipient_bic: '',
    recipient_corr_account: '',
    purpose: '',
    vat_type: 'without_vat',
    vat_rate: null,
    vat_amount: '',
    payment_priority: 5,
    budget_fields: {
        kbk: '',
        oktmo: '',
        payment_basis: '',
        tax_period: '',
        document_number: '',
        document_date: '',
        uin: '',
    },
})
const draftForm = reactive(emptyDraft())

const tabs = computed(() => [
    { value: 'overview', title: 'Обзор', icon: 'mdi-view-dashboard-outline' },
    { value: 'accounts', title: 'Расчётные счета', icon: 'mdi-bank-outline' },
    { value: 'transactions', title: 'Операции', icon: 'mdi-swap-horizontal' },
    { value: 'linked', title: 'Связанные продажи', icon: 'mdi-link-variant' },
    { value: 'unmatched', title: 'Неопознанные', icon: 'mdi-help-circle-outline' },
    { value: 'partial', title: 'Частичные и переплаты', icon: 'mdi-scale-balance' },
    ...(props.permissions.manage_payment_drafts && props.permissions.view_sensitive
        ? [{ value: 'drafts', title: 'Черновики', icon: 'mdi-file-document-edit-outline' }]
        : []),
    { value: 'sync', title: 'Синхронизация', icon: 'mdi-sync' },
    { value: 'errors', title: 'Ошибки', icon: 'mdi-alert-circle-outline' },
    ...(props.permissions.manage_connection || props.permissions.view_audit
        ? [{ value: 'settings', title: 'Настройки и аудит', icon: 'mdi-shield-lock-outline' }]
        : []),
])

const operationTabs = ['transactions', 'linked', 'unmatched', 'partial']
const transactionHeaders = [
    { title: 'Дата', key: 'operation_date', sortable: true, width: 120 },
    { title: 'Счёт', key: 'account.number', sortable: false, width: 180 },
    { title: 'Направление', key: 'direction', sortable: true, width: 130 },
    { title: 'Контрагент', key: 'counterparty', sortable: false, minWidth: 200 },
    { title: 'Назначение', key: 'purpose', sortable: false, minWidth: 320 },
    { title: 'Сумма', key: 'amount', sortable: true, align: 'end', width: 150 },
    { title: 'Сверка', key: 'reconciliation_status', sortable: true, width: 170 },
    { title: '', key: 'actions', sortable: false, width: 52 },
]
const draftHeaders = [
    { title: '№ / дата', key: 'document', sortable: false },
    { title: 'Получатель', key: 'recipient_name', sortable: false },
    { title: 'Сумма', key: 'amount', sortable: false, align: 'end' },
    { title: 'Статус', key: 'status', sortable: false },
    { title: 'Создал', key: 'created_by.name', sortable: false },
    { title: '', key: 'actions', sortable: false, width: 180 },
]
const syncHeaders = [
    { title: 'Начало', key: 'started_at' },
    { title: 'Тип', key: 'type' },
    { title: 'Счёт', key: 'account.masked_number' },
    { title: 'Статус', key: 'status' },
    { title: 'Получено', key: 'received_count' },
    { title: 'Создано / обновлено', key: 'changed' },
    { title: 'Совпадения', key: 'matched_count' },
    { title: 'Correlation ID', key: 'correlation_id' },
]
const errorHeaders = [
    { title: 'Время', key: 'created_at' },
    { title: 'Категория', key: 'category' },
    { title: 'Сообщение', key: 'safe_message' },
    { title: 'HTTP / код', key: 'http' },
    { title: 'Счёт', key: 'account' },
    { title: 'Попытка', key: 'attempt_count' },
    { title: 'Correlation ID', key: 'correlation' },
    { title: 'Вмешательство', key: 'intervention' },
    { title: 'Решение', key: 'resolution' },
]
const auditHeaders = [
    { title: 'Время', key: 'created_at' },
    { title: 'Действие', key: 'action' },
    { title: 'Пользователь', key: 'user.name' },
    { title: 'Объект', key: 'subject' },
    { title: 'Correlation ID', key: 'correlation_id' },
]
const directionItems = [
    { title: 'Поступление', value: 'credit' },
    { title: 'Списание', value: 'debit' },
]
const operationStatusItems = [
    { title: 'Проведена', value: 'posted' },
    { title: 'Ожидает', value: 'pending' },
    { title: 'Отменена', value: 'cancelled' },
    { title: 'Сторнирована', value: 'reversed' },
    { title: 'Неизвестно', value: 'unknown' },
]
const reconciliationItems = [
    { title: 'Неопознана', value: 'unmatched' },
    { title: 'Есть предложения', value: 'suggested' },
    { title: 'Частично распределена', value: 'partially_allocated' },
    { title: 'Распределена', value: 'allocated' },
    { title: 'Переплата', value: 'overpaid' },
    { title: 'Не требует сверки', value: 'not_required' },
    { title: 'Требует проверки', value: 'needs_review' },
]

const connectionStatus = computed(() => dashboard.value.connection?.status || 'not_connected')
const selectedUnallocated = computed(() => selectedTransaction.value?.unallocated_amount || '0.00')
const draftAccountItems = computed(() => (draftOptions.value.accounts || []).map((item) => ({
    ...item,
    label: `${item.name || 'Счёт'} · ${item.masked_number} · ${item.currency}`,
})))
const draftEntityItems = computed(() => (draftOptions.value.entities || []).map((item) => ({
    ...item,
    label: `${item.name} · ИНН ${item.INN || '—'}`,
})))
const draftPurchaseItems = computed(() => (draftOptions.value.purchases || []).map((item) => ({
    ...item,
    label: `Закупка #${item.id} · ${formatDate(item.date)} · ${formatMoney(item.amount)}`,
})))
const receivableItems = computed(() => receivables.value.map((item) => ({
    ...item,
    label: `Продажа ${item.number} · ${item.entity?.name || 'Без контрагента'} · долг ${formatMoney(item.outstanding_amount)}`,
})))

function notify(text, color = 'success') {
    snackbar.text = text
    snackbar.color = color
    snackbar.show = true
}

function errorMessage(error, fallback) {
    const errors = error?.response?.data?.errors
    const first = errors && Object.values(errors).flat()[0]

    return first || error?.response?.data?.message || fallback
}

function formatMoney(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') {
        return '—'
    }

    const raw = String(value).trim().replace(/\s+/g, '').replace(',', '.')
    const match = raw.match(/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/)

    if (!match) {
        return `${raw} ${currency || 'RUB'}`
    }

    const sign = match[1] === '-' ? '−' : match[1]
    const whole = match[2].replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')
    const fraction = (match[3] || '').padEnd(2, '0')
    const unit = (currency || 'RUB').toUpperCase() === 'RUB' ? '₽' : (currency || 'RUB').toUpperCase()

    return `${sign}${whole},${fraction}\u00a0${unit}`
}

function formatDate(value, withTime = false) {
    if (!value) {
        return '—'
    }

    const parsed = new Date(value)

    if (Number.isNaN(parsed.getTime())) {
        return String(value)
    }

    return new Intl.DateTimeFormat('ru-RU', withTime
        ? { dateStyle: 'short', timeStyle: 'short', timeZone: props.bankTimezone }
        : { dateStyle: 'short', timeZone: props.bankTimezone }).format(parsed)
}

function labelFor(value, items) {
    return items.find((item) => item.value === value)?.title || value || '—'
}

function statusColor(value) {
    return {
        active: 'success',
        connected: 'success',
        completed: 'success',
        posted: 'success',
        allocated: 'success',
        paid: 'success',
        draft: 'blue',
        queued: 'blue',
        running: 'blue',
        exported: 'indigo',
        suggested: 'amber',
        partially_allocated: 'amber',
        partially_paid: 'amber',
        overpaid: 'deep-orange',
        needs_review: 'deep-orange',
        unmatched: 'grey',
        not_required: 'grey',
        cancelled: 'red',
        reversed: 'red',
        failed: 'red',
        reauthorization_required: 'red',
    }[value] || 'grey'
}

function effectiveReconciliationStatus() {
    if (filters.reconciliation_status) {
        return filters.reconciliation_status
    }

    return {
        unmatched: 'unmatched',
    }[activeTab.value] || null
}

function effectiveWorklist() {
    if (filters.reconciliation_status) {
        return null
    }

    return {
        linked: 'linked',
        partial: 'partial_overpaid',
    }[activeTab.value] || null
}

async function loadDashboard() {
    busy.dashboard = true
    pageError.value = ''

    try {
        const { data } = await axios.get('/Ameise/bank/dashboard')
        dashboard.value = data.data
    } catch (error) {
        pageError.value = errorMessage(error, 'Не удалось загрузить обзор банка.')
    } finally {
        busy.dashboard = false
    }
}

async function loadTransactions() {
    busy.transactions = true
    pageError.value = ''

    try {
        const params = {
            page: transactionPage.value,
            per_page: transactionPerPage.value,
            sort_by: transactionSort.by,
            sort_dir: transactionSort.direction,
            ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== false)),
        }
        const tabStatus = effectiveReconciliationStatus()
        const worklist = effectiveWorklist()

        if (tabStatus) {
            params.reconciliation_status = tabStatus
        }

        if (worklist) {
            params.worklist = worklist
        }

        const { data } = await axios.get('/Ameise/bank/transactions', { params })
        transactions.value = data.data || []
        transactionsTotal.value = data.total || 0
    } catch (error) {
        pageError.value = errorMessage(error, 'Не удалось загрузить банковские операции.')
    } finally {
        busy.transactions = false
    }
}

function updateTableOptions(options) {
    transactionPage.value = options.page
    transactionPerPage.value = options.itemsPerPage

    if (options.sortBy?.length) {
        transactionSort.by = options.sortBy[0].key
        transactionSort.direction = options.sortBy[0].order
    }

    loadTransactions()
}

function resetFilters() {
    Object.assign(filters, {
        date_from: '',
        date_to: '',
        account_id: null,
        direction: null,
        amount_min: '',
        amount_max: '',
        entity: '',
        inn: '',
        purpose: '',
        status: null,
        reconciliation_status: null,
        warning: false,
    })
    transactionPage.value = 1
    loadTransactions()
}

async function openTransaction(item) {
    busy.detail = true
    transactionDrawer.value = true
    selectedTransaction.value = null

    try {
        const { data } = await axios.get(`/Ameise/bank/transactions/${item.id}`)
        selectedTransaction.value = data.data
        allocationRows.value = []
        allocationComment.value = ''

        if (props.permissions.reconcile) {
            await searchReceivables()
        }
    } catch (error) {
        notify(errorMessage(error, 'Не удалось открыть операцию.'), 'error')
        transactionDrawer.value = false
    } finally {
        busy.detail = false
    }
}

async function refreshTransaction() {
    if (!selectedTransaction.value?.id) {
        return
    }

    await openTransaction({ id: selectedTransaction.value.id })
    await loadTransactions()
    await loadDashboard()
}

async function searchReceivables() {
    if (!props.permissions.reconcile) {
        return
    }

    try {
        const { data } = await axios.get('/Ameise/bank/receivables', {
            params: {
                search: receivableSearch.value || undefined,
                limit: 100,
            },
        })
        receivables.value = data.data || []
    } catch (error) {
        notify(errorMessage(error, 'Не удалось найти продажи.'), 'error')
    }
}

function addAllocation(saleId = null, amount = '') {
    if (allocationRows.value.length >= 50) {
        return
    }

    allocationRows.value.push({
        sale_id: saleId,
        amount: amount || (allocationRows.value.length === 0 ? selectedUnallocated.value : ''),
    })
}

function useSuggestion(suggestion) {
    if (!suggestion.sale_id || allocationRows.value.some((row) => row.sale_id === suggestion.sale_id)) {
        return
    }

    addAllocation(suggestion.sale_id, selectedUnallocated.value)
}

async function saveAllocations() {
    busy.reconcile = true

    try {
        await axios.post(`/Ameise/bank/transactions/${selectedTransaction.value.id}/allocations`, {
            allocations: allocationRows.value,
            comment: allocationComment.value || null,
        })
        notify('Распределение платежа сохранено.')
        await refreshTransaction()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось сохранить распределение.'), 'error')
    } finally {
        busy.reconcile = false
    }
}

async function reverseAllocation(allocation) {
    const reason = window.prompt('Причина отмены распределения:')

    if (!reason) {
        return
    }

    busy.reconcile = true

    try {
        await axios.post(
            `/Ameise/bank/transactions/${selectedTransaction.value.id}/allocations/${allocation.id}/reverse`,
            { reason },
        )
        notify('Распределение отменено.')
        await refreshTransaction()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось отменить распределение.'), 'error')
    } finally {
        busy.reconcile = false
    }
}

async function rejectSuggestion(suggestion) {
    const comment = window.prompt('Комментарий к отклонению (необязательно):') || ''

    busy.reconcile = true

    try {
        await axios.post(
            `/Ameise/bank/transactions/${selectedTransaction.value.id}/suggestions/${suggestion.id}/reject`,
            { comment },
        )
        notify('Предложение отклонено.')
        await refreshTransaction()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось отклонить предложение.'), 'error')
    } finally {
        busy.reconcile = false
    }
}

async function markNotRequired() {
    const comment = window.prompt('Почему операция не требует сверки?')

    if (!comment) {
        return
    }

    busy.reconcile = true

    try {
        await axios.post(`/Ameise/bank/transactions/${selectedTransaction.value.id}/not-required`, { comment })
        notify('Операция отмечена как не требующая сверки.')
        await refreshTransaction()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось изменить статус сверки.'), 'error')
    } finally {
        busy.reconcile = false
    }
}

async function queueSync(mode = 'incremental') {
    const connectionId = dashboard.value.connection?.id

    if (!connectionId) {
        notify('Сначала подключите Sber API.', 'warning')
        return
    }

    busy.sync = true

    try {
        await axios.post('/Ameise/bank/sync', {
            connection_id: connectionId,
            mode,
        })
        notify('Синхронизация поставлена в очередь.')
        await Promise.all([loadSyncRuns(), loadDashboard()])
    } catch (error) {
        notify(errorMessage(error, 'Не удалось поставить синхронизацию в очередь.'), 'error')
    } finally {
        busy.sync = false
    }
}

async function loadSyncRuns() {
    busy.logs = true

    try {
        const { data } = await axios.get('/Ameise/bank/sync-runs', { params: { per_page: 50 } })
        syncRuns.value = data.data || []
        syncRunsTotal.value = data.total || 0
    } catch (error) {
        notify(errorMessage(error, 'Не удалось загрузить историю синхронизации.'), 'error')
    } finally {
        busy.logs = false
    }
}

async function loadErrors() {
    busy.logs = true

    try {
        const { data } = await axios.get('/Ameise/bank/errors', { params: { per_page: 50 } })
        errors.value = data.data || []
        errorsTotal.value = data.total || 0
    } catch (error) {
        notify(errorMessage(error, 'Не удалось загрузить журнал ошибок.'), 'error')
    } finally {
        busy.logs = false
    }
}

async function resolveError(item) {
    const resolutionComment = window.prompt('Как была устранена ошибка?')

    if (!resolutionComment) {
        return
    }

    try {
        await axios.post(`/Ameise/bank/errors/${item.id}/resolve`, {
            resolution_comment: resolutionComment,
        })
        notify('Ошибка отмечена как решённая.')
        await loadErrors()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось закрыть ошибку.'), 'error')
    }
}

async function loadAudit() {
    if (!props.permissions.view_audit) {
        return
    }

    busy.logs = true

    try {
        const { data } = await axios.get('/Ameise/bank/audit', { params: { per_page: 50 } })
        auditEvents.value = data.data || []
        auditTotal.value = data.total || 0
    } catch (error) {
        notify(errorMessage(error, 'Не удалось загрузить аудит.'), 'error')
    } finally {
        busy.logs = false
    }
}

async function loadDrafts() {
    busy.drafts = true

    try {
        const { data } = await axios.get('/Ameise/bank/drafts', { params: { per_page: 50 } })
        drafts.value = data.data || []
        draftTotal.value = data.total || 0
    } catch (error) {
        notify(errorMessage(error, 'Не удалось загрузить черновики.'), 'error')
    } finally {
        busy.drafts = false
    }
}

async function loadDraftOptions(params = {}) {
    if (!props.permissions.manage_payment_drafts || !props.permissions.view_sensitive) {
        return
    }

    try {
        const { data } = await axios.get('/Ameise/bank/drafts/options', { params })
        draftOptions.value = data.data
    } catch (error) {
        notify(errorMessage(error, 'Не удалось загрузить реквизиты для черновика.'), 'error')
    }
}

async function openDraft(item = null, defaults = {}) {
    await loadDraftOptions({
        entity_id: item?.recipient_entity_id || defaults.recipient_entity_id || undefined,
        purchase_id: item?.purchase_id || defaults.purchase_id || undefined,
    })
    editingDraft.value = item
    Object.assign(draftForm, emptyDraft(), item
        ? {
            ...item,
            document_date: item.document_date?.slice(0, 10),
            payer_bank_account_id: item.payer_bank_account_id,
            recipient_entity_id: item.recipient_entity_id,
            purchase_id: item.purchase_id,
            number: item.number || '',
            amount: String(item.amount || ''),
            vat_amount: item.vat_amount === null ? '' : String(item.vat_amount),
            budget_fields: {
                ...emptyDraft().budget_fields,
                ...(item.budget_fields || {}),
            },
        }
        : defaults)
    draftDialog.value = true
}

async function saveDraft() {
    busy.drafts = true

    try {
        const budgetFields = Object.fromEntries(
            Object.entries(draftForm.budget_fields || {})
                .map(([key, value]) => [key, String(value || '').trim()])
                .filter(([, value]) => value !== ''),
        )
        const payload = {
            ...draftForm,
            number: draftForm.number || null,
            purchase_id: draftForm.purchase_id || null,
            payer_kpp: draftForm.payer_kpp || null,
            recipient_kpp: draftForm.recipient_kpp || null,
            vat_rate: draftForm.vat_type === 'without_vat' ? null : draftForm.vat_rate,
            vat_amount: draftForm.vat_amount || null,
            budget_fields: Object.keys(budgetFields).length ? budgetFields : null,
        }

        if (editingDraft.value) {
            await axios.put(`/Ameise/bank/drafts/${editingDraft.value.id}`, payload)
        } else {
            await axios.post('/Ameise/bank/drafts', payload)
        }

        draftDialog.value = false
        notify('Локальный черновик сохранён. В банк ничего не отправлено.')
        await loadDrafts()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось сохранить черновик.'), 'error')
    } finally {
        busy.drafts = false
    }
}

async function exportDraft(item) {
    try {
        const { data } = await axios.post(`/Ameise/bank/drafts/${item.id}/export`)
        window.open(data.print_url, '_blank', 'noopener')
        notify('Подготовлена локальная печатная форма. В банк ничего не отправлено.')
        await loadDrafts()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось подготовить печатную форму.'), 'error')
    }
}

async function cancelDraft(item) {
    if (!window.confirm(`Отменить локальный черновик ${item.number}?`)) {
        return
    }

    try {
        await axios.post(`/Ameise/bank/drafts/${item.id}/cancel`)
        notify('Локальный черновик отменён.')
        await loadDrafts()
    } catch (error) {
        notify(errorMessage(error, 'Не удалось отменить черновик.'), 'error')
    }
}

async function loadHealth() {
    if (!props.permissions.manage_connection) {
        return
    }

    busy.health = true

    try {
        const [{ data: healthResponse }, { data: ownersResponse }] = await Promise.all([
            axios.get('/Ameise/bank/sber/health'),
            axios.get('/Ameise/bank/sber/owners'),
        ])
        health.value = healthResponse.data
        ownerEntities.value = (ownersResponse.data || []).map((entity) => ({
            ...entity,
            label: `${entity.name} · ИНН ${entity.INN || '—'}`,
        }))
    } catch (error) {
        notify(errorMessage(error, 'Не удалось проверить конфигурацию Sber.'), 'error')
    } finally {
        busy.health = false
    }
}

function connectSber() {
    const query = ownerEntityId.value ? `?owner_entity_id=${encodeURIComponent(ownerEntityId.value)}` : ''
    window.location.assign(`/Ameise/bank/sber/authorize${query}`)
}

watch(
    () => draftForm.payer_bank_account_id,
    (id) => {
        const account = draftOptions.value.accounts?.find((item) => item.id === id)

        if (!account) {
            return
        }

        draftForm.currency = account.currency || 'RUB'
        draftForm.payer_account = account.number || ''
        draftForm.payer_bank_name = account.bank_name || ''
        draftForm.payer_bic = account.bic || ''
        draftForm.payer_corr_account = account.corr_account || ''
        draftForm.payer_name = account.owner?.full_name || account.owner?.name || ''
        draftForm.payer_inn = account.owner?.INN || ''
        draftForm.payer_kpp = account.owner?.KPP || ''
    },
)

watch(
    () => draftForm.recipient_entity_id,
    (id) => {
        const entity = draftOptions.value.entities?.find((item) => item.id === id)

        if (!entity) {
            return
        }

        draftForm.recipient_name = entity.full_name || entity.name || ''
        draftForm.recipient_inn = entity.INN || ''
        draftForm.recipient_kpp = entity.KPP || ''
        draftForm.recipient_account = entity.bank_account_number || ''
        draftForm.recipient_bank_name = entity.bank_name || ''
        draftForm.recipient_bic = entity.bank_bic || ''
        draftForm.recipient_corr_account = entity.bank_corr_account || ''
    },
)

watch(
    () => draftForm.purchase_id,
    (id) => {
        const purchase = draftOptions.value.purchases?.find((item) => item.id === id)

        if (!purchase) {
            return
        }

        draftForm.recipient_entity_id = purchase.entity_id
        draftForm.amount = String(purchase.amount || '')
        draftForm.purpose = `Оплата по закупке № ${purchase.id}`
    },
)

watch(activeTab, async (tab) => {
    pageError.value = ''

    if (tab === 'overview' || tab === 'accounts') {
        await loadDashboard()
    } else if (operationTabs.includes(tab)) {
        filters.reconciliation_status = null
        transactionPage.value = 1
        await loadTransactions()
    } else if (tab === 'drafts') {
        await loadDrafts()
    } else if (tab === 'sync') {
        await Promise.all([loadSyncRuns(), loadDashboard()])
    } else if (tab === 'errors') {
        await loadErrors()
    } else if (tab === 'settings') {
        await Promise.all([loadHealth(), loadAudit(), loadDashboard()])
    }
})

function queryId(value) {
    const normalized = String(value || '').trim()

    if (!/^\d+$/.test(normalized)) {
        return null
    }

    const id = Number(normalized)

    return Number.isSafeInteger(id) && id > 0 ? id : null
}

async function initializePage() {
    await loadDashboard()

    if (!props.permissions.manage_payment_drafts || !props.permissions.view_sensitive) {
        return
    }

    const params = new URLSearchParams(window.location.search)
    const recipientEntityId = queryId(params.get('draft_entity_id'))
    const purchaseId = queryId(params.get('draft_purchase_id'))

    if (!recipientEntityId && !purchaseId) {
        return
    }

    activeTab.value = 'drafts'
    await openDraft(null, {
        recipient_entity_id: recipientEntityId,
        purchase_id: purchaseId,
    })
}

onMounted(initializePage)
</script>

<template>
    <div class="bank-page">
        <v-container fluid class="pa-4 pa-md-6">
            <div class="bank-page__heading">
                <div>
                    <div class="text-overline text-medium-emphasis">Пищепром-Сервер</div>
                    <h1 class="text-h4 font-weight-bold">Банк</h1>
                    <p class="text-body-2 text-medium-emphasis mt-1 mb-0">
                        Выписки, сверка поступлений и локальные черновики · {{ bankTimezone }}
                    </p>
                </div>
                <div class="d-flex ga-2 align-center flex-wrap">
                    <v-chip color="success" variant="tonal" prepend-icon="mdi-lock-check-outline">
                        Только чтение Sber API
                    </v-chip>
                    <v-btn
                        v-if="permissions.sync"
                        color="#8f1111"
                        prepend-icon="mdi-sync"
                        :loading="busy.sync"
                        :disabled="!dashboard.connection || dashboard.counters?.running_syncs > 0"
                        @click="queueSync('incremental')"
                    >
                        Синхронизировать сейчас
                    </v-btn>
                </div>
            </div>

            <v-alert v-if="!readOnly" type="error" variant="tonal" class="my-4">
                Нарушена обязательная настройка read-only. Внешние запросы заблокированы сервером.
            </v-alert>
            <v-alert v-if="pageError" type="error" variant="tonal" closable class="my-4" @click:close="pageError = ''">
                {{ pageError }}
            </v-alert>

            <v-card class="mt-5 bank-shell" elevation="1">
                <v-tabs
                    v-model="activeTab"
                    color="#8f1111"
                    show-arrows
                    class="bank-tabs"
                >
                    <v-tab v-for="tab in tabs" :key="tab.value" :value="tab.value">
                        <v-icon :icon="tab.icon" start />
                        {{ tab.title }}
                    </v-tab>
                </v-tabs>

                <v-divider />

                <v-window v-model="activeTab">
                    <v-window-item value="overview">
                        <div class="pa-4 pa-md-6">
                            <v-row>
                                <v-col cols="12" md="8">
                                    <v-card variant="tonal" color="blue-grey" class="h-100">
                                        <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2">
                                            <span>Подключение</span>
                                            <v-chip :color="statusColor(connectionStatus)" size="small">
                                                {{ connectionStatus }}
                                            </v-chip>
                                        </v-card-title>
                                        <v-card-text>
                                            <template v-if="dashboard.connection">
                                                <div class="bank-meta-grid">
                                                    <div>
                                                        <span>Среда</span>
                                                        <strong>{{ dashboard.connection.environment }}</strong>
                                                    </div>
                                                    <div>
                                                        <span>Последняя успешная синхронизация</span>
                                                        <strong>{{ formatDate(dashboard.connection.last_successful_sync_at, true) }}</strong>
                                                    </div>
                                                    <div>
                                                        <span>Активные процессы</span>
                                                        <strong>{{ dashboard.counters?.running_syncs || 0 }}</strong>
                                                    </div>
                                                    <div>
                                                        <span>Последняя ошибка</span>
                                                        <strong>{{ formatDate(dashboard.connection.last_error_at, true) }}</strong>
                                                    </div>
                                                </div>
                                            </template>
                                            <div v-else class="text-medium-emphasis">
                                                Sber API ещё не подключён. Подключение доступно администратору в разделе настроек.
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col cols="12" md="4">
                                    <v-card variant="tonal" color="red-darken-3" class="h-100">
                                        <v-card-title>Требует внимания</v-card-title>
                                        <v-card-text class="d-flex flex-column ga-3">
                                            <div class="bank-counter">
                                                <strong>{{ dashboard.counters?.unmatched || 0 }}</strong>
                                                <span>неопознанных поступлений</span>
                                            </div>
                                            <div class="bank-counter">
                                                <strong>{{ dashboard.counters?.errors_requiring_intervention || 0 }}</strong>
                                                <span>нерешённых ошибок</span>
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <h2 class="text-h6 mt-7 mb-3">Движение средств</h2>
                            <v-row>
                                <v-col v-for="period in ['today', 'week', 'month']" :key="period" cols="12" sm="4">
                                    <v-card variant="outlined">
                                        <v-card-subtitle class="pt-4">
                                            {{ { today: 'Сегодня', week: 'Неделя', month: 'Месяц' }[period] }}
                                        </v-card-subtitle>
                                        <v-card-text>
                                            <div class="text-success font-weight-bold">
                                                + {{ formatMoney(dashboard.totals?.[`credits_${period}`]) }}
                                            </div>
                                            <div class="text-error font-weight-bold mt-2">
                                                − {{ formatMoney(dashboard.totals?.[`debits_${period}`]) }}
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>

                            <h2 class="text-h6 mt-7 mb-3">Последние известные остатки</h2>
                            <v-row>
                                <v-col v-for="account in dashboard.accounts" :key="account.id" cols="12" md="6" lg="4">
                                    <v-card variant="outlined">
                                        <v-card-title class="text-subtitle-1">{{ account.name || 'Расчётный счёт' }}</v-card-title>
                                        <v-card-subtitle>{{ account.number }}</v-card-subtitle>
                                        <v-card-text>
                                            <div class="text-h6">{{ formatMoney(account.balance, account.currency) }}</div>
                                            <div class="text-caption text-medium-emphasis mt-2">
                                                По состоянию на {{ formatDate(account.balance_as_of, true) }}
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col v-if="!dashboard.accounts?.length" cols="12">
                                    <v-alert variant="tonal" type="info">Расчётные счета ещё не синхронизированы.</v-alert>
                                </v-col>
                            </v-row>

                            <v-row class="mt-3">
                                <v-col v-for="counter in [
                                    ['suggested', 'Предложений на проверку'],
                                    ['partial', 'Частичных оплат'],
                                    ['overpayments', 'Переплат'],
                                ]" :key="counter[0]" cols="12" sm="4">
                                    <v-card variant="flat" color="grey-lighten-4">
                                        <v-card-text class="bank-counter">
                                            <strong>{{ dashboard.counters?.[counter[0]] || 0 }}</strong>
                                            <span>{{ counter[1] }}</span>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </div>
                    </v-window-item>

                    <v-window-item value="accounts">
                        <div class="pa-4 pa-md-6">
                            <div class="d-flex justify-space-between align-center mb-4">
                                <div>
                                    <h2 class="text-h6">Расчётные счета</h2>
                                    <div class="text-caption text-medium-emphasis">
                                        Остаток отражает последнюю полученную выписку и не называется онлайн-остатком.
                                    </div>
                                </div>
                                <v-btn icon="mdi-refresh" variant="text" :loading="busy.dashboard" @click="loadDashboard" />
                            </div>
                            <v-row>
                                <v-col v-for="account in dashboard.accounts" :key="account.id" cols="12" md="6">
                                    <v-card variant="outlined">
                                        <v-card-title class="d-flex justify-space-between ga-3">
                                            <span>{{ account.name || 'Расчётный счёт' }}</span>
                                            <v-chip :color="statusColor(account.status)" size="small">{{ account.status }}</v-chip>
                                        </v-card-title>
                                        <v-card-subtitle>{{ account.number }} · {{ account.currency }}</v-card-subtitle>
                                        <v-card-text>
                                            <div class="text-h5">{{ formatMoney(account.balance, account.currency) }}</div>
                                            <div class="bank-meta-grid mt-4">
                                                <div><span>По состоянию на</span><strong>{{ formatDate(account.balance_as_of, true) }}</strong></div>
                                                <div><span>Дата выписки</span><strong>{{ formatDate(account.balance_statement_date) }}</strong></div>
                                                <div><span>Синхронизирован</span><strong>{{ formatDate(account.last_synced_at, true) }}</strong></div>
                                            </div>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </div>
                    </v-window-item>

                    <v-window-item v-for="tab in operationTabs" :key="tab" :value="tab">
                        <div class="pa-4 pa-md-6">
                            <div class="mb-4">
                                <h2 class="text-h6">
                                    {{
                                        {
                                            transactions: 'Поступления и списания',
                                            linked: 'Связанные счета и продажи',
                                            unmatched: 'Неопознанные платежи',
                                            partial: 'Частичные оплаты и переплаты',
                                        }[tab]
                                    }}
                                </h2>
                                <div v-if="tab === 'linked'" class="text-caption text-medium-emphasis">
                                    Канонический оплачиваемый объект в текущей модели проекта — продажа Sale.
                                </div>
                            </div>

                            <v-card variant="outlined" class="mb-4">
                                <v-card-text>
                                    <v-row dense>
                                        <v-col cols="12" sm="3">
                                            <v-text-field v-model="filters.date_from" label="С даты" type="date" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="3">
                                            <v-text-field v-model="filters.date_to" label="По дату" type="date" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="3">
                                            <v-select
                                                v-model="filters.account_id"
                                                :items="dashboard.accounts"
                                                item-title="number"
                                                item-value="id"
                                                label="Счёт"
                                                clearable
                                                density="compact"
                                                hide-details
                                            />
                                        </v-col>
                                        <v-col cols="12" sm="3">
                                            <v-select v-model="filters.direction" :items="directionItems" label="Направление" clearable density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="2">
                                            <v-text-field v-model="filters.amount_min" label="Сумма от" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="2">
                                            <v-text-field v-model="filters.amount_max" label="Сумма до" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="2">
                                            <v-text-field v-model="filters.inn" label="ИНН" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="3">
                                            <v-text-field v-model="filters.entity" label="Контрагент" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="3">
                                            <v-text-field v-model="filters.purpose" label="Назначение" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="3">
                                            <v-select v-model="filters.status" :items="operationStatusItems" label="Статус операции" clearable density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="3">
                                            <v-select v-model="filters.reconciliation_status" :items="reconciliationItems" label="Статус сверки" clearable density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="3" class="d-flex align-center">
                                            <v-checkbox v-model="filters.warning" label="Есть предупреждение" density="compact" hide-details />
                                        </v-col>
                                        <v-col cols="12" sm="6" class="d-flex justify-end ga-2 align-center">
                                            <v-btn variant="text" prepend-icon="mdi-filter-remove-outline" @click="resetFilters">Сбросить</v-btn>
                                            <v-btn color="#8f1111" prepend-icon="mdi-filter-outline" @click="transactionPage = 1; loadTransactions()">Применить</v-btn>
                                        </v-col>
                                    </v-row>
                                </v-card-text>
                            </v-card>

                            <v-data-table-server
                                :headers="transactionHeaders"
                                :items="transactions"
                                :items-length="transactionsTotal"
                                :loading="busy.transactions"
                                :items-per-page="transactionPerPage"
                                :page="transactionPage"
                                item-value="id"
                                hover
                                @update:options="updateTableOptions"
                                @click:row="(_, row) => openTransaction(row.item)"
                            >
                                <template #item.direction="{ item }">
                                    <v-chip :color="item.direction === 'credit' ? 'success' : 'error'" size="small" variant="tonal">
                                        {{ item.direction === 'credit' ? 'Поступление' : 'Списание' }}
                                    </v-chip>
                                </template>
                                <template #item.counterparty="{ item }">
                                    <div class="font-weight-medium">{{ item.entity?.name || item.payer_name || item.recipient_name || '—' }}</div>
                                    <div class="text-caption text-medium-emphasis">ИНН {{ item.payer_inn || item.recipient_inn || '—' }}</div>
                                </template>
                                <template #item.purpose="{ item }">
                                    <div class="bank-purpose">{{ item.purpose || '—' }}</div>
                                </template>
                                <template #item.amount="{ item }">
                                    <span :class="item.direction === 'credit' ? 'text-success' : 'text-error'" class="font-weight-bold">
                                        {{ item.direction === 'credit' ? '+' : '−' }} {{ formatMoney(item.amount, item.currency) }}
                                    </span>
                                </template>
                                <template #item.reconciliation_status="{ item }">
                                    <v-chip :color="statusColor(item.reconciliation_status)" size="small">
                                        {{ labelFor(item.reconciliation_status, reconciliationItems) }}
                                    </v-chip>
                                </template>
                                <template #item.actions="{ item }">
                                    <v-btn icon="mdi-eye-outline" size="small" variant="text" @click.stop="openTransaction(item)" />
                                </template>
                            </v-data-table-server>
                        </div>
                    </v-window-item>

                    <v-window-item value="drafts">
                        <div class="pa-4 pa-md-6">
                            <v-alert type="warning" variant="tonal" prominent class="mb-5">
                                Это локальный черновик. Он не отправлен в Сбер, не подписан и не является исполненным платёжным поручением.
                            </v-alert>
                            <div class="d-flex justify-space-between align-center mb-4">
                                <h2 class="text-h6">Локальные черновики платёжных поручений</h2>
                                <v-btn
                                    v-if="permissions.manage_payment_drafts"
                                    color="#8f1111"
                                    prepend-icon="mdi-plus"
                                    @click="openDraft()"
                                >
                                    Создать черновик
                                </v-btn>
                            </div>
                            <v-data-table
                                :headers="draftHeaders"
                                :items="drafts"
                                :loading="busy.drafts"
                                item-value="id"
                            >
                                <template #item.document="{ item }">
                                    <div class="font-weight-medium">{{ item.number }}</div>
                                    <div class="text-caption">{{ formatDate(item.document_date) }}</div>
                                </template>
                                <template #item.amount="{ item }">{{ formatMoney(item.amount, item.currency) }}</template>
                                <template #item.status="{ item }">
                                    <v-chip :color="statusColor(item.status)" size="small">{{ item.status }}</v-chip>
                                </template>
                                <template #item.actions="{ item }">
                                    <div class="d-flex ga-1">
                                        <v-btn
                                            v-if="permissions.manage_payment_drafts && item.status === 'draft'"
                                            icon="mdi-pencil-outline"
                                            size="small"
                                            variant="text"
                                            title="Редактировать"
                                            @click="openDraft(item)"
                                        />
                                        <v-btn
                                            v-if="permissions.manage_payment_drafts && item.status !== 'cancelled'"
                                            icon="mdi-printer-outline"
                                            size="small"
                                            variant="text"
                                            title="Локальная печать"
                                            @click="exportDraft(item)"
                                        />
                                        <v-btn
                                            v-if="permissions.manage_payment_drafts && item.status === 'draft'"
                                            icon="mdi-cancel"
                                            size="small"
                                            variant="text"
                                            color="error"
                                            title="Отменить локальный черновик"
                                            @click="cancelDraft(item)"
                                        />
                                    </div>
                                </template>
                            </v-data-table>
                        </div>
                    </v-window-item>

                    <v-window-item value="sync">
                        <div class="pa-4 pa-md-6">
                            <div class="d-flex justify-space-between align-center flex-wrap ga-3 mb-4">
                                <div>
                                    <h2 class="text-h6">История синхронизации</h2>
                                    <div class="text-caption text-medium-emphasis">HTTP-запрос не ожидает завершения: работа выполняется в очереди banking.</div>
                                </div>
                                <div v-if="permissions.sync" class="d-flex ga-2">
                                    <v-btn variant="outlined" :loading="busy.sync" @click="queueSync('control')">Контрольная</v-btn>
                                    <v-btn color="#8f1111" prepend-icon="mdi-sync" :loading="busy.sync" @click="queueSync('incremental')">Инкрементальная</v-btn>
                                </div>
                            </div>
                            <v-data-table :headers="syncHeaders" :items="syncRuns" :loading="busy.logs">
                                <template #item.started_at="{ item }">{{ formatDate(item.started_at || item.created_at, true) }}</template>
                                <template #item.status="{ item }">
                                    <v-chip :color="statusColor(item.status)" size="small">{{ item.status }}</v-chip>
                                </template>
                                <template #item.changed="{ item }">{{ item.created_count }} / {{ item.updated_count }}</template>
                            </v-data-table>
                        </div>
                    </v-window-item>

                    <v-window-item value="errors">
                        <div class="pa-4 pa-md-6">
                            <div class="d-flex justify-space-between align-center mb-4">
                                <h2 class="text-h6">Журнал безопасных ошибок</h2>
                                <v-btn icon="mdi-refresh" variant="text" :loading="busy.logs" @click="loadErrors" />
                            </div>
                            <v-data-table :headers="errorHeaders" :items="errors" :loading="busy.logs">
                                <template #item.created_at="{ item }">{{ formatDate(item.created_at, true) }}</template>
                                <template #item.http="{ item }">{{ item.http_status || '—' }} / {{ item.bank_cause || '—' }}</template>
                                <template #item.account="{ item }">{{ item.sync_run?.account?.masked_number || '—' }}</template>
                                <template #item.correlation="{ item }">{{ item.correlation_id || item.sync_run?.correlation_id || '—' }}</template>
                                <template #item.intervention="{ item }">
                                    <v-chip :color="item.requires_intervention ? 'error' : 'grey'" size="small">
                                        {{ item.requires_intervention ? 'Требуется' : 'Нет' }}
                                    </v-chip>
                                </template>
                                <template #item.resolution="{ item }">
                                    <v-chip v-if="item.resolved_at" color="success" size="small">Решена</v-chip>
                                    <v-btn
                                        v-else-if="permissions.manage_connection"
                                        size="small"
                                        variant="tonal"
                                        color="warning"
                                        @click="resolveError(item)"
                                    >
                                        Отметить решённой
                                    </v-btn>
                                    <v-chip v-else color="warning" size="small">Открыта</v-chip>
                                </template>
                            </v-data-table>
                        </div>
                    </v-window-item>

                    <v-window-item value="settings">
                        <div class="pa-4 pa-md-6">
                            <v-row>
                                <v-col v-if="permissions.manage_connection" cols="12" lg="5">
                                    <v-card variant="outlined">
                                        <v-card-title>Подключение Sber API</v-card-title>
                                        <v-card-text>
                                            <v-alert type="info" variant="tonal" class="mb-4">
                                                Запрашиваются только read-only scopes. Платёжные scopes и write-endpoints отсутствуют в клиенте.
                                            </v-alert>
                                            <v-select
                                                v-model="ownerEntityId"
                                                :items="ownerEntities"
                                                item-title="label"
                                                item-value="id"
                                                label="Собственная организация (Entity)"
                                                clearable
                                                class="mb-3"
                                            />
                                            <v-btn
                                                color="#8f1111"
                                                prepend-icon="mdi-bank-plus"
                                                :disabled="!ownerEntityId"
                                                @click="connectSber"
                                            >
                                                Подключить Сбер
                                            </v-btn>
                                            <v-divider class="my-5" />
                                            <h3 class="text-subtitle-1 font-weight-bold mb-3">Проверка конфигурации</h3>
                                            <v-list v-if="health" density="compact">
                                                <v-list-item v-for="check in health.checks" :key="check.name">
                                                    <template #prepend>
                                                        <v-icon :color="check.status === 'ok' ? 'success' : (check.status === 'warning' ? 'warning' : 'error')">
                                                            {{ check.status === 'ok' ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline' }}
                                                        </v-icon>
                                                    </template>
                                                    <v-list-item-title>{{ check.name }}</v-list-item-title>
                                                    <v-list-item-subtitle>{{ check.message }}</v-list-item-subtitle>
                                                </v-list-item>
                                            </v-list>
                                            <v-btn variant="text" prepend-icon="mdi-refresh" :loading="busy.health" @click="loadHealth">
                                                Проверить снова
                                            </v-btn>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                                <v-col v-if="permissions.view_audit" cols="12" :lg="permissions.manage_connection ? 7 : 12">
                                    <v-card variant="outlined">
                                        <v-card-title>Append-only аудит</v-card-title>
                                        <v-card-text class="pa-0">
                                            <v-data-table :headers="auditHeaders" :items="auditEvents" :loading="busy.logs" density="compact">
                                                <template #item.created_at="{ item }">{{ formatDate(item.created_at, true) }}</template>
                                                <template #item.subject="{ item }">{{ item.auditable_type }} #{{ item.auditable_id }}</template>
                                            </v-data-table>
                                        </v-card-text>
                                    </v-card>
                                </v-col>
                            </v-row>
                        </div>
                    </v-window-item>
                </v-window>
            </v-card>
        </v-container>

        <v-navigation-drawer
            v-model="transactionDrawer"
            location="right"
            temporary
            width="760"
            class="bank-operation-drawer"
        >
            <div class="pa-4 pa-md-6">
                <div class="d-flex justify-space-between align-center mb-4">
                    <div>
                        <div class="text-overline">Банковская операция</div>
                        <h2 class="text-h6">#{{ selectedTransaction?.id || '—' }}</h2>
                    </div>
                    <v-btn icon="mdi-close" variant="text" @click="transactionDrawer = false" />
                </div>
                <v-progress-linear v-if="busy.detail" indeterminate color="#8f1111" />

                <template v-if="selectedTransaction">
                    <v-alert v-if="selectedTransaction.review_reason" type="warning" variant="tonal" class="mb-4">
                        {{ selectedTransaction.review_reason }}
                    </v-alert>
                    <v-card variant="outlined" class="mb-4">
                        <v-card-text>
                            <div class="bank-meta-grid">
                                <div><span>Дата операции</span><strong>{{ formatDate(selectedTransaction.operation_date) }}</strong></div>
                                <div><span>Статус</span><strong>{{ selectedTransaction.status }}</strong></div>
                                <div><span>Направление</span><strong>{{ selectedTransaction.direction === 'credit' ? 'Поступление' : 'Списание' }}</strong></div>
                                <div><span>Сумма</span><strong>{{ formatMoney(selectedTransaction.amount, selectedTransaction.currency) }}</strong></div>
                                <div><span>Распределено</span><strong>{{ formatMoney(selectedTransaction.allocated_amount, selectedTransaction.currency) }}</strong></div>
                                <div><span>Не распределено</span><strong>{{ formatMoney(selectedTransaction.unallocated_amount, selectedTransaction.currency) }}</strong></div>
                            </div>
                            <v-divider class="my-4" />
                            <div class="text-caption text-medium-emphasis">Назначение платежа</div>
                            <div class="mt-1">{{ selectedTransaction.purpose || '—' }}</div>
                            <v-divider class="my-4" />
                            <div class="text-subtitle-2">{{ selectedTransaction.payer_name || 'Плательщик не указан' }}</div>
                            <div class="text-body-2">ИНН {{ selectedTransaction.payer_inn || '—' }}, КПП {{ selectedTransaction.payer_kpp || '—' }}</div>
                            <div class="text-body-2">Счёт {{ selectedTransaction.payer_account || '—' }}</div>
                        </v-card-text>
                    </v-card>

                    <h3 class="text-subtitle-1 font-weight-bold mb-2">Распределения</h3>
                    <v-card v-for="allocation in selectedTransaction.allocations" :key="allocation.id" variant="outlined" class="mb-2">
                        <v-card-text class="d-flex justify-space-between align-center ga-3">
                            <div>
                                <div class="font-weight-medium">
                                    Продажа {{ allocation.sale?.number || allocation.sale_id }} · {{ formatMoney(allocation.amount, selectedTransaction.currency) }}
                                </div>
                                <div class="text-caption">
                                    {{ allocation.source }} · {{ allocation.is_active ? 'активно' : `отменено: ${allocation.reversal_reason || '—'}` }}
                                </div>
                            </div>
                            <v-btn
                                v-if="permissions.reconcile && allocation.is_active"
                                size="small"
                                variant="text"
                                color="error"
                                @click="reverseAllocation(allocation)"
                            >
                                Отменить
                            </v-btn>
                        </v-card-text>
                    </v-card>
                    <div v-if="!selectedTransaction.allocations?.length" class="text-body-2 text-medium-emphasis mb-4">
                        Распределений нет.
                    </div>

                    <template v-if="selectedTransaction.suggestions?.length">
                        <h3 class="text-subtitle-1 font-weight-bold mt-5 mb-2">Предложенные совпадения</h3>
                        <v-card v-for="suggestion in selectedTransaction.suggestions" :key="suggestion.id" variant="tonal" class="mb-2">
                            <v-card-text>
                                <div class="d-flex justify-space-between ga-3">
                                    <div>
                                        <div class="font-weight-medium">
                                            Продажа {{ suggestion.sale?.number || suggestion.sale_id }} · score {{ suggestion.score }}
                                        </div>
                                        <div class="text-caption">{{ (suggestion.rules || []).join(' · ') }}</div>
                                    </div>
                                    <v-chip :color="statusColor(suggestion.status)" size="small">{{ suggestion.status }}</v-chip>
                                </div>
                                <div v-if="permissions.reconcile && suggestion.status === 'pending'" class="d-flex ga-2 mt-3">
                                    <v-btn size="small" color="success" variant="tonal" @click="useSuggestion(suggestion)">В распределение</v-btn>
                                    <v-btn size="small" color="error" variant="text" @click="rejectSuggestion(suggestion)">Отклонить</v-btn>
                                </div>
                            </v-card-text>
                        </v-card>
                    </template>

                    <v-card
                        v-if="permissions.reconcile && selectedTransaction.direction === 'credit' && selectedTransaction.status === 'posted'"
                        variant="outlined"
                        class="mt-5"
                    >
                        <v-card-title class="text-subtitle-1">Ручная сверка</v-card-title>
                        <v-card-text>
                            <div class="d-flex ga-2 mb-3">
                                <v-text-field
                                    v-model="receivableSearch"
                                    label="Продажа, номер, контрагент или ИНН"
                                    density="compact"
                                    hide-details
                                    @keyup.enter="searchReceivables"
                                />
                                <v-btn icon="mdi-magnify" variant="tonal" @click="searchReceivables" />
                            </div>
                            <div v-for="(row, index) in allocationRows" :key="index" class="allocation-row">
                                <v-autocomplete
                                    v-model="row.sale_id"
                                    :items="receivableItems"
                                    item-title="label"
                                    item-value="id"
                                    label="Продажа"
                                    density="compact"
                                />
                                <v-text-field v-model="row.amount" label="Сумма" density="compact" />
                                <v-btn icon="mdi-delete-outline" color="error" variant="text" @click="allocationRows.splice(index, 1)" />
                            </div>
                            <v-btn variant="text" prepend-icon="mdi-plus" @click="addAllocation()">Добавить часть</v-btn>
                            <v-textarea v-model="allocationComment" label="Комментарий" rows="2" class="mt-3" />
                            <div class="d-flex flex-wrap ga-2">
                                <v-btn
                                    color="#8f1111"
                                    :loading="busy.reconcile"
                                    :disabled="!allocationRows.length"
                                    @click="saveAllocations"
                                >
                                    Сохранить распределение
                                </v-btn>
                                <v-btn variant="text" @click="markNotRequired">Не требует сверки</v-btn>
                            </div>
                        </v-card-text>
                    </v-card>

                    <v-expansion-panels v-if="selectedTransaction.audit?.length" class="mt-5">
                        <v-expansion-panel title="Аудит операции">
                            <v-expansion-panel-text>
                                <v-timeline density="compact" side="end">
                                    <v-timeline-item v-for="event in selectedTransaction.audit" :key="event.id" size="small">
                                        <div class="font-weight-medium">{{ event.action }}</div>
                                        <div class="text-caption">{{ formatDate(event.created_at, true) }} · {{ event.correlation_id }}</div>
                                    </v-timeline-item>
                                </v-timeline>
                            </v-expansion-panel-text>
                        </v-expansion-panel>
                    </v-expansion-panels>
                </template>
            </div>
        </v-navigation-drawer>

        <v-dialog v-model="draftDialog" max-width="1050" persistent scrollable>
            <v-card>
                <v-card-title class="d-flex justify-space-between align-center">
                    <span>{{ editingDraft ? 'Редактирование черновика' : 'Новый локальный черновик' }}</span>
                    <v-btn icon="mdi-close" variant="text" @click="draftDialog = false" />
                </v-card-title>
                <v-card-text>
                    <v-alert type="warning" variant="tonal" class="mb-5">
                        Это локальный черновик. Он не отправлен в Сбер, не подписан и не является исполненным платёжным поручением.
                    </v-alert>
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="draftForm.number" label="Номер (автоматически, если пусто)" />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="draftForm.document_date" label="Дата" type="date" />
                        </v-col>
                        <v-col cols="12" md="4">
                            <v-text-field v-model="draftForm.amount" label="Сумма" inputmode="decimal" />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-select
                                v-model="draftForm.payer_bank_account_id"
                                :items="draftAccountItems"
                                item-title="label"
                                item-value="id"
                                label="Собственный расчётный счёт"
                            />
                        </v-col>
                        <v-col cols="12" md="6">
                            <v-select
                                v-model="draftForm.purchase_id"
                                :items="draftPurchaseItems"
                                item-title="label"
                                item-value="id"
                                label="Закупка (необязательно)"
                                clearable
                            />
                        </v-col>
                        <v-col cols="12">
                            <h3 class="text-subtitle-1 font-weight-bold">Плательщик</h3>
                        </v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.payer_name" label="Наименование" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="draftForm.payer_inn" label="ИНН" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="draftForm.payer_kpp" label="КПП" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.payer_account" label="Расчётный счёт" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.payer_bank_name" label="Банк" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.payer_bic" label="БИК" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.payer_corr_account" label="Корреспондентский счёт" /></v-col>
                        <v-col cols="12">
                            <h3 class="text-subtitle-1 font-weight-bold">Получатель</h3>
                        </v-col>
                        <v-col cols="12">
                            <v-autocomplete
                                v-model="draftForm.recipient_entity_id"
                                :items="draftEntityItems"
                                item-title="label"
                                item-value="id"
                                label="Поставщик Entity"
                            />
                        </v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.recipient_name" label="Наименование" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="draftForm.recipient_inn" label="ИНН" /></v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="draftForm.recipient_kpp" label="КПП" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.recipient_account" label="Расчётный счёт" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.recipient_bank_name" label="Банк" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.recipient_bic" label="БИК" /></v-col>
                        <v-col cols="12" md="6"><v-text-field v-model="draftForm.recipient_corr_account" label="Корреспондентский счёт" /></v-col>
                        <v-col cols="12"><v-textarea v-model="draftForm.purpose" label="Назначение платежа" rows="2" counter="210" /></v-col>
                        <v-col cols="12" md="3">
                            <v-select
                                v-model="draftForm.vat_type"
                                :items="[
                                    { title: 'Без НДС', value: 'without_vat' },
                                    { title: 'НДС включён', value: 'included' },
                                    { title: 'НДС сверху', value: 'on_top' },
                                ]"
                                label="НДС"
                            />
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-select v-model="draftForm.vat_rate" :items="['0', '5', '7', '10', '20']" label="Ставка, %" clearable />
                        </v-col>
                        <v-col cols="12" md="3"><v-text-field v-model="draftForm.vat_amount" label="Сумма НДС" /></v-col>
                        <v-col cols="12" md="3"><v-select v-model="draftForm.payment_priority" :items="[1, 2, 3, 4, 5]" label="Очерёдность" /></v-col>
                        <v-col cols="12">
                            <v-expansion-panels variant="accordion">
                                <v-expansion-panel title="Бюджетные и налоговые поля (необязательно)">
                                    <v-expansion-panel-text>
                                        <v-row>
                                            <v-col cols="12" md="4"><v-text-field v-model="draftForm.budget_fields.kbk" label="КБК" /></v-col>
                                            <v-col cols="12" md="4"><v-text-field v-model="draftForm.budget_fields.oktmo" label="ОКТМО" /></v-col>
                                            <v-col cols="12" md="4"><v-text-field v-model="draftForm.budget_fields.uin" label="УИН" /></v-col>
                                            <v-col cols="12" md="4"><v-text-field v-model="draftForm.budget_fields.payment_basis" label="Основание платежа" /></v-col>
                                            <v-col cols="12" md="4"><v-text-field v-model="draftForm.budget_fields.tax_period" label="Налоговый период" /></v-col>
                                            <v-col cols="12" md="4"><v-text-field v-model="draftForm.budget_fields.document_number" label="Номер документа" /></v-col>
                                            <v-col cols="12" md="4"><v-text-field v-model="draftForm.budget_fields.document_date" label="Дата документа" /></v-col>
                                        </v-row>
                                    </v-expansion-panel-text>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="draftDialog = false">Закрыть</v-btn>
                    <v-btn color="#8f1111" :loading="busy.drafts" @click="saveDraft">Сохранить локально</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="5000">
            {{ snackbar.text }}
            <template #actions>
                <v-btn variant="text" @click="snackbar.show = false">Закрыть</v-btn>
            </template>
        </v-snackbar>
    </div>
</template>

<style scoped>
.bank-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at 95% 0%, rgb(143 17 17 / 9%), transparent 32rem),
        #f6f7f8;
}

.bank-page__heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
}

.bank-shell {
    border: 1px solid rgb(20 31 45 / 8%);
}

.bank-tabs {
    background: #fff;
}

.bank-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.bank-meta-grid > div {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.bank-meta-grid span {
    color: rgb(var(--v-theme-on-surface), 0.58);
    font-size: 0.75rem;
}

.bank-counter {
    display: flex;
    align-items: baseline;
    gap: 0.65rem;
}

.bank-counter strong {
    font-size: 1.65rem;
}

.bank-purpose {
    max-width: 32rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.allocation-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 150px 42px;
    gap: 0.5rem;
    align-items: start;
}

:deep(.bank-operation-drawer) {
    max-width: 100vw;
}

@media (max-width: 700px) {
    .bank-page__heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .bank-meta-grid {
        grid-template-columns: 1fr;
    }

    .allocation-row {
        grid-template-columns: 1fr 110px 42px;
    }
}
</style>
