<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useHead } from '@unhead/vue'
import axios from 'axios'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import AvitoAutoReplies from '@/Components/Avito/AvitoAutoReplies.vue'
import AvitoMessageTemplates from '@/Components/Avito/AvitoMessageTemplates.vue'
import AvitoMessages from '@/Components/Avito/AvitoMessages.vue'
import AvitoListings from '@/Components/Avito/AvitoListings.vue'
import AvitoPublications from '@/Components/Avito/AvitoPublications.vue'

defineOptions({ layout: VerwalterLayout })

useHead({ title: 'Avito API · Ameise' })

const tab = ref('overview')
const loading = ref(true)
const notice = ref('')
const error = ref('')
const status = ref({ catalog: { counts: {}, sections: [] } })
const capabilities = ref([])
const connections = ref([])
const calls = ref([])
const callsTotal = ref(0)
const webhooks = ref([])
const webhooksTotal = ref(0)
const selectedIds = ref([])

const filters = reactive({ search: '', section: '', method: '', access: '', enabled: '' })
const page = ref(1)
const perPage = ref(50)
const callsPage = ref(1)
const webhooksPage = ref(1)

const executorDialog = ref(false)
const executorLoading = ref(false)
const executing = ref(false)
const selectedCapability = ref(null)
const executionResult = ref(null)
const uploadFiles = ref([])
const executionForm = reactive({
    connection_id: null,
    path: {},
    query: {},
    headers: {},
    body: '',
    content_type: '',
    confirmed: false,
})

const detailDialog = ref(false)
const detailLoading = ref(false)
const detailTitle = ref('')
const detailPayload = ref(null)
const preflightLoading = ref(false)

const methodOptions = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
const accessOptions = [
    { title: 'Чтение / расчёт', value: 'read' },
    { title: 'Изменение', value: 'mutation' },
    { title: 'Управляется Ameise', value: 'managed' },
]

const sectionOptions = computed(() => (status.value.catalog?.sections || []).map((section) => ({
    title: `${section.title} · ${section.operation_count}`,
    value: section.slug,
})))

const filteredCapabilities = computed(() => {
    const needle = filters.search.trim().toLocaleLowerCase('ru')

    return capabilities.value.filter((item) => {
        if (filters.section && item.section !== filters.section) return false
        if (filters.method && item.method !== filters.method) return false
        if (filters.access && item.access !== filters.access) return false
        if (filters.enabled !== '' && item.enabled !== filters.enabled) return false
        if (!needle) return true

        return [item.summary, item.path, item.operation_id, item.section_title]
            .join(' ')
            .toLocaleLowerCase('ru')
            .includes(needle)
    })
})

const paginatedCapabilities = computed(() => {
    const start = (page.value - 1) * perPage.value
    return filteredCapabilities.value.slice(start, start + perPage.value)
})

const pageCount = computed(() => Math.max(1, Math.ceil(filteredCapabilities.value.length / perPage.value)))
const allPageSelected = computed(() => paginatedCapabilities.value.length > 0
    && paginatedCapabilities.value.every((item) => selectedIds.value.includes(item.id)))
const activeCount = computed(() => capabilities.value.filter((item) => item.enabled).length)
const safeCount = computed(() => capabilities.value.filter((item) => item.access === 'read').length)
const mutationCount = computed(() => capabilities.value.filter((item) => item.access === 'mutation').length)

watch(filters, () => { page.value = 1 }, { deep: true })
watch([perPage, () => filteredCapabilities.value.length], () => {
    if (page.value > pageCount.value) page.value = pageCount.value
})

watch(tab, (value) => {
    if (value === 'calls') loadCalls()
    if (value === 'webhooks') loadWebhooks()
})

function showNotice(message) {
    notice.value = message
    error.value = ''
}

function showError(exception, fallback = 'Операция не выполнена.') {
    error.value = exception?.response?.data?.message || fallback
    notice.value = ''
}

async function loadAll() {
    loading.value = true
    try {
        const [statusResponse, capabilitiesResponse, connectionsResponse] = await Promise.all([
            axios.get('/api/avito/status'),
            axios.get('/api/avito/capabilities'),
            axios.get('/api/avito/connections'),
        ])
        status.value = statusResponse.data
        capabilities.value = capabilitiesResponse.data.items || []
        connections.value = connectionsResponse.data.items || []
        handleOAuthResult()
    } catch (exception) {
        showError(exception, 'Не удалось загрузить центр управления Avito.')
    } finally {
        loading.value = false
    }
}

function handleOAuthResult() {
    const query = new URLSearchParams(window.location.search)
    const oauth = query.get('oauth')
    if (!oauth) return

    if (oauth === 'success') showNotice('Аккаунт Avito подключён по OAuth.')
    else if (oauth === 'denied') error.value = 'Подключение отменено в Avito.'
    else error.value = 'Avito не выдал OAuth-токен. Проверьте настройки приложения.'

    window.history.replaceState({}, '', window.location.pathname)
}

async function reloadStatus() {
    const [statusResponse, connectionsResponse] = await Promise.all([
        axios.get('/api/avito/status'),
        axios.get('/api/avito/connections'),
    ])
    status.value = statusResponse.data
    connections.value = connectionsResponse.data.items || []
}

async function toggleCapability(item) {
    const next = !item.enabled
    item.enabled = next
    try {
        await axios.patch(`/api/avito/capabilities/${encodeURIComponent(item.id)}`, { enabled: next })
    } catch (exception) {
        item.enabled = !next
        showError(exception, 'Не удалось изменить состояние функции.')
    }
}

function togglePageSelection() {
    const pageIds = paginatedCapabilities.value.map((item) => item.id)
    if (allPageSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !pageIds.includes(id))
    } else {
        selectedIds.value = [...new Set([...selectedIds.value, ...pageIds])]
    }
}

async function bulkToggle(enabled) {
    if (!selectedIds.value.length) return
    try {
        await axios.patch('/api/avito/capabilities', { ids: selectedIds.value, enabled })
        const selected = new Set(selectedIds.value)
        capabilities.value.forEach((item) => {
            if (selected.has(item.id)) item.enabled = enabled
        })
        showNotice(`${selectedIds.value.length} функций ${enabled ? 'включено' : 'отключено'}.`)
        selectedIds.value = []
    } catch (exception) {
        showError(exception, 'Групповое изменение не выполнено.')
    }
}

async function openExecutor(item) {
    executorDialog.value = true
    executorLoading.value = true
    executionResult.value = null
    uploadFiles.value = []
    selectedCapability.value = null
    try {
        const { data } = await axios.get(`/api/avito/capabilities/${encodeURIComponent(item.id)}`)
        selectedCapability.value = data.capability
        const capability = data.capability
        executionForm.connection_id = null
        executionForm.path = fieldsFromParameters(capability.parameters, 'path')
        executionForm.query = fieldsFromParameters(capability.parameters, 'query')
        executionForm.headers = fieldsFromParameters(capability.parameters, 'header', ['authorization'])
        executionForm.content_type = Object.keys(capability.request_body?.content || {})[0] || ''
        executionForm.body = prettyJson(exampleBody(capability))
        executionForm.confirmed = false
    } catch (exception) {
        showError(exception, 'Не удалось открыть описание функции.')
        executorDialog.value = false
    } finally {
        executorLoading.value = false
    }
}

function fieldsFromParameters(parameters, location, excluded = []) {
    return Object.fromEntries((parameters || [])
        .filter((parameter) => parameter.in === location && !excluded.includes(parameter.name.toLowerCase()))
        .map((parameter) => [parameter.name, parameter.example ?? parameter.schema?.default ?? '']))
}

function exampleBody(capability) {
    const media = capability.request_body?.content?.[executionForm.content_type]
        || Object.values(capability.request_body?.content || {})[0]
    if (!media) return null
    if (media.example != null) return media.example
    return exampleFromSchema(media.schema || {})
}

function exampleFromSchema(schema) {
    if (schema.example !== undefined) return schema.example
    if (schema.default !== undefined) return schema.default
    if (schema.enum?.length) return schema.enum[0]
    if (schema.allOf?.length) return Object.assign({}, ...schema.allOf.map(exampleFromSchema).filter(isObject))
    if (schema.oneOf?.length) return exampleFromSchema(schema.oneOf[0])
    if (schema.anyOf?.length) return exampleFromSchema(schema.anyOf[0])
    if (schema.type === 'object' || schema.properties) {
        return Object.fromEntries(Object.entries(schema.properties || {})
            .filter(([, child]) => child.format !== 'binary')
            .map(([key, child]) => [key, exampleFromSchema(child)]))
    }
    if (schema.type === 'array') return [exampleFromSchema(schema.items || {})]
    if (schema.type === 'integer' || schema.type === 'number') return 0
    if (schema.type === 'boolean') return false
    return ''
}

function isObject(value) {
    return value && typeof value === 'object' && !Array.isArray(value)
}

function prettyJson(value) {
    return value == null ? '' : JSON.stringify(value, null, 2)
}

function parseBody() {
    const text = executionForm.body.trim()
    if (!text) return null
    try {
        return JSON.parse(text)
    } catch {
        throw new Error('Тело запроса содержит некорректный JSON.')
    }
}

async function executeCapability() {
    executing.value = true
    executionResult.value = null
    try {
        const body = parseBody()
        const payload = {
            connection_id: executionForm.connection_id,
            path: compactObject(executionForm.path),
            query: compactObject(executionForm.query),
            headers: compactObject(executionForm.headers),
            body,
            content_type: executionForm.content_type || null,
            confirmation: executionForm.confirmed ? 'AVITO' : null,
        }
        const url = `/api/avito/capabilities/${encodeURIComponent(selectedCapability.value.id)}/execute`
        let response

        if (executionForm.content_type === 'multipart/form-data') {
            const formData = new FormData()
            if (payload.connection_id) formData.append('connection_id', payload.connection_id)
            Object.entries(payload.path).forEach(([key, value]) => formData.append(`path[${key}]`, value))
            Object.entries(payload.query).forEach(([key, value]) => formData.append(`query[${key}]`, value))
            Object.entries(payload.headers).forEach(([key, value]) => formData.append(`headers[${key}]`, value))
            formData.append('body', JSON.stringify(body || {}))
            formData.append('content_type', payload.content_type)
            if (payload.confirmation) formData.append('confirmation', payload.confirmation)
            uploadFiles.value.forEach((file) => formData.append('files[]', file))
            response = await axios.post(url, formData)
        } else {
            response = await axios.post(url, payload)
        }

        executionResult.value = response.data
        const index = capabilities.value.findIndex((item) => item.id === selectedCapability.value.id)
        if (index >= 0) {
            capabilities.value[index].last_status = response.data.ok ? 'success' : 'remote_error'
            capabilities.value[index].last_http_status = response.data.status
            capabilities.value[index].last_used_at = new Date().toISOString()
        }
    } catch (exception) {
        const message = exception?.message?.includes('JSON')
            ? exception.message
            : exception?.response?.data?.message || 'Запрос к Avito не выполнен.'
        executionResult.value = { ok: false, message, details: exception?.response?.data || null }
    } finally {
        executing.value = false
    }
}

function compactObject(value) {
    return Object.fromEntries(Object.entries(value || {}).filter(([, item]) => item !== '' && item !== null && item !== undefined))
}

function changeContentType() {
    executionForm.body = prettyJson(exampleBody(selectedCapability.value))
    uploadFiles.value = []
}

function downloadBinary() {
    if (!executionResult.value?.binary || !executionResult.value?.data) return
    const bytes = Uint8Array.from(atob(executionResult.value.data), (character) => character.charCodeAt(0))
    const blob = new Blob([bytes], { type: executionResult.value.headers?.content_type || 'application/octet-stream' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = `avito-${executionResult.value.request_id || 'response'}`
    link.click()
    URL.revokeObjectURL(link.href)
}

async function runPreflight(connectionId = null) {
    preflightLoading.value = true
    try {
        const { data } = await axios.post('/api/avito/preflight', { connection_id: connectionId })
        if (data.ok) showNotice('Preflight пройден: Avito принял токен и вернул профиль.')
        else error.value = data.message
        await reloadStatus()
    } catch (exception) {
        showError(exception, 'Preflight Avito не пройден.')
    } finally {
        preflightLoading.value = false
    }
}

function connectOAuth() {
    window.location.href = '/api/avito/oauth/redirect'
}

async function refreshConnection(connection) {
    try {
        await axios.post(`/api/avito/connections/${connection.id}/refresh`)
        await reloadStatus()
        showNotice(`Токен «${connection.name}» обновлён.`)
    } catch (exception) {
        showError(exception, 'Не удалось обновить OAuth-токен.')
    }
}

async function deleteConnection(connection) {
    if (!window.confirm(`Удалить подключение «${connection.name}» и сохранённые токены?`)) return
    try {
        await axios.delete(`/api/avito/connections/${connection.id}`)
        await reloadStatus()
        showNotice('OAuth-подключение удалено.')
    } catch (exception) {
        showError(exception, 'Не удалось удалить подключение.')
    }
}

async function loadCalls() {
    try {
        const { data } = await axios.get('/api/avito/calls', { params: { page: callsPage.value, per_page: 30 } })
        calls.value = data.data || []
        callsTotal.value = data.total || 0
    } catch (exception) {
        showError(exception, 'Не удалось загрузить журнал запросов.')
    }
}

async function loadWebhooks() {
    try {
        const { data } = await axios.get('/api/avito/webhooks', { params: { page: webhooksPage.value, per_page: 30 } })
        webhooks.value = data.data || []
        webhooksTotal.value = data.total || 0
    } catch (exception) {
        showError(exception, 'Не удалось загрузить webhook-события.')
    }
}

async function showCall(call) {
    detailDialog.value = true
    detailLoading.value = true
    detailTitle.value = `Запрос ${call.request_id}`
    try {
        const { data } = await axios.get(`/api/avito/calls/${call.id}`)
        detailPayload.value = data.call
    } catch (exception) {
        detailPayload.value = { error: exception?.response?.data?.message || 'Не удалось загрузить запись.' }
    } finally {
        detailLoading.value = false
    }
}

async function showWebhook(event) {
    detailDialog.value = true
    detailLoading.value = true
    detailTitle.value = `Webhook #${event.id}`
    try {
        const { data } = await axios.get(`/api/avito/webhooks/${event.id}`)
        detailPayload.value = data.event
    } catch (exception) {
        detailPayload.value = { error: exception?.response?.data?.message || 'Не удалось загрузить событие.' }
    } finally {
        detailLoading.value = false
    }
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text)
        showNotice('Адрес скопирован.')
    } catch {
        error.value = 'Браузер не разрешил копирование.'
    }
}

function methodClass(method) {
    return `method-${String(method).toLowerCase()}`
}

function statusColor(value) {
    if (value === 'success' || value === 'active') return 'success'
    if (value === 'running' || value === 'received') return 'info'
    if (value === 'remote_error' || value === 'error') return 'error'
    return 'grey'
}

function authLabel(item) {
    const schemes = [...new Set((item.security || []).map((security) => security.scheme))]
    if (!schemes.length) return 'Без токена'
    return schemes.map((scheme) => scheme === 'authorization_code' ? 'OAuth code' : 'Client credentials').join(' / ')
}

function scopesLabel(item) {
    const scopes = [...new Set((item.security || []).flatMap((security) => security.scopes || []))]
    return scopes.length ? scopes.join(', ') : 'scope не требуется'
}

function formatDate(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(value))
}

onMounted(loadAll)
</script>

<template>
    <main class="avito-page">
        <section class="avito-hero">
            <div>
                <div class="avito-kicker">AMEISE · MARKETPLACE OPERATIONS</div>
                <h1>Avito API</h1>
                <p>Объявления, аналитика и продвижение, чаты с долговременным архивом, подключения и журнал событий.</p>
            </div>
            <div class="avito-hero__actions">
                <v-btn
                    size="small"
                    variant="tonal"
                    color="white"
                    prepend-icon="mdi-book-open-page-variant-outline"
                    :href="status.documentation_url"
                    target="_blank"
                    rel="noopener noreferrer"
                >Документация</v-btn>
                <v-btn size="small" color="white" prepend-icon="mdi-link-variant-plus" @click="connectOAuth">Подключить OAuth</v-btn>
            </div>
        </section>

        <v-alert v-if="notice" class="mb-3" type="success" variant="tonal" closable @click:close="notice = ''">{{ notice }}</v-alert>
        <v-alert v-if="error" class="mb-3" type="error" variant="tonal" closable @click:close="error = ''">{{ error }}</v-alert>

        <div v-if="loading" class="avito-loading">
            <v-progress-circular indeterminate color="deep-purple-lighten-2" size="44" />
            <span>Загружаем официальный каталог Avito…</span>
        </div>

        <template v-else>
            <section class="avito-metrics">
                <article><span>Функции API</span><strong>{{ status.catalog?.counts?.capabilities || 0 }}</strong><small>{{ status.catalog?.counts?.sections || 0 }} разделов</small></article>
                <article><span>Доступно в реестре</span><strong>{{ activeCount }}</strong><small>{{ safeCount }} операций чтения</small></article>
                <article><span>Изменяющие</span><strong>{{ mutationCount }}</strong><small>{{ status.mutations_enabled ? 'операции разрешены' : 'операции отключены в .env' }}</small></article>
                <article><span>Подключения</span><strong>{{ status.active_connections || 0 }}</strong><small>{{ status.configured ? 'client credentials настроены' : 'нужна настройка .env' }}</small></article>
            </section>

            <v-tabs v-model="tab" class="avito-tabs" color="deep-purple-accent-1" show-arrows>
                <v-tab value="overview" prepend-icon="mdi-view-dashboard-outline">Обзор</v-tab>
                <v-tab value="listings" prepend-icon="mdi-view-grid-outline">Объявления</v-tab>
                <v-tab value="publications" prepend-icon="mdi-file-document-plus-outline">Создание</v-tab>
                <v-tab value="messages" prepend-icon="mdi-forum-outline">Сообщения</v-tab>
                <v-tab value="auto-replies" prepend-icon="mdi-robot-outline">Автоответы</v-tab>
                <v-tab value="templates" prepend-icon="mdi-text-box-multiple-outline">Шаблоны</v-tab>
                <v-tab value="catalog" prepend-icon="mdi-table-large">API-функции</v-tab>
                <v-tab value="connections" prepend-icon="mdi-link-variant">Подключения</v-tab>
                <v-tab value="calls" prepend-icon="mdi-console-line">Журнал</v-tab>
                <v-tab value="webhooks" prepend-icon="mdi-webhook">Webhooks</v-tab>
            </v-tabs>

            <v-window v-model="tab" class="avito-window">
                <v-window-item value="overview" class="avito-tab-item">
                    <div class="avito-grid avito-grid--overview">
                        <section class="avito-panel">
                            <div class="avito-panel__header">
                                <div><span class="eyebrow">Готовность</span><h2>Состояние интеграции</h2></div>
                                <v-chip :color="status.configured ? 'success' : 'warning'" variant="tonal">
                                    {{ status.configured ? 'Учётные данные заданы' : 'Требуется настройка' }}
                                </v-chip>
                            </div>
                            <div class="readiness-list">
                                <div><v-icon :color="status.enabled ? 'success' : 'error'" :icon="status.enabled ? 'mdi-check-circle' : 'mdi-close-circle'" /><span>Модуль Avito</span><strong>{{ status.enabled ? 'включён' : 'отключён' }}</strong></div>
                                <div><v-icon :color="status.configured ? 'success' : 'warning'" :icon="status.configured ? 'mdi-check-circle' : 'mdi-alert-circle'" /><span>Client credentials</span><strong>{{ status.configured ? 'готовы' : 'не заданы' }}</strong></div>
                                <div><v-icon color="success" icon="mdi-check-circle" /><span>OpenAPI snapshot</span><strong>{{ status.catalog?.counts?.capabilities }} функций</strong></div>
                                <div><v-icon :color="status.webhook_protected ? 'success' : 'warning'" :icon="status.webhook_protected ? 'mdi-shield-check' : 'mdi-shield-alert'" /><span>Webhook endpoint</span><strong>{{ status.webhook_protected ? 'защищён секретом' : 'секрет не задан' }}</strong></div>
                                <div><v-icon :color="status.mutations_enabled ? 'success' : 'warning'" :icon="status.mutations_enabled ? 'mdi-lock-open-outline' : 'mdi-lock-outline'" /><span>Удалённые изменения</span><strong>{{ status.mutations_enabled ? 'разрешены без отдельного контура' : 'отключены' }}</strong></div>
                            </div>
                            <v-alert v-if="status.missing_environment?.length" type="warning" variant="tonal" class="mt-4">
                                На production добавьте секреты: <code>{{ status.missing_environment.join(', ') }}</code>.
                            </v-alert>
                            <div class="panel-actions">
                                <v-btn color="deep-purple" prepend-icon="mdi-stethoscope" :loading="preflightLoading" :disabled="!status.configured" @click="runPreflight()">Preflight</v-btn>
                                <v-btn variant="outlined" prepend-icon="mdi-table-arrow-right" @click="tab = 'catalog'">Открыть все функции</v-btn>
                            </div>
                        </section>

                        <section class="avito-panel">
                            <div class="avito-panel__header"><div><span class="eyebrow">OAuth 2.0</span><h2>Адреса приложения</h2></div></div>
                            <label class="copy-field"><span>Redirect URI</span><code>{{ status.oauth_redirect_uri }}</code><v-btn icon="mdi-content-copy" size="small" variant="text" @click="copyText(status.oauth_redirect_uri)" /></label>
                            <label class="copy-field"><span>Webhook URL</span><code>{{ status.webhook_url }}</code><v-btn icon="mdi-content-copy" size="small" variant="text" @click="copyText(status.webhook_url)" /></label>
                            <p class="panel-note">Redirect URI нужно дословно зарегистрировать в приложении Avito. Для webhook задайте <code>AVITO_WEBHOOK_SECRET</code>: API «Работа» передаёт его официальным заголовком <code>X-Secret</code>; для Messenger можно зарегистрировать URL с параметром <code>?secret=…</code>.</p>
                        </section>

                        <section class="avito-panel avito-panel--wide">
                            <div class="avito-panel__header">
                                <div><span class="eyebrow">Покрытие</span><h2>25 разделов официального API</h2></div>
                                <small>Обновлено {{ formatDate(status.catalog?.generated_at) }}</small>
                            </div>
                            <div class="section-cloud">
                                <button v-for="section in status.catalog?.sections" :key="section.slug" type="button" @click="filters.section = section.slug; tab = 'catalog'">
                                    <span>{{ section.title }}</span><strong>{{ section.operation_count }}</strong>
                                </button>
                            </div>
                        </section>
                    </div>
                </v-window-item>

                <v-window-item value="listings" class="avito-tab-item">
                    <AvitoListings
                        :connections="connections"
                        :configured="status.configured"
                        :enabled="status.enabled"
                        :mutations-enabled="status.mutations_enabled"
                        :documentation-url="status.documentation_url"
                        @notice="notice = $event; error = ''"
                        @error="error = $event; notice = ''"
                        @open-catalog="filters.section = $event || 'item'; tab = 'catalog'"
                    />
                </v-window-item>

                <v-window-item value="publications" class="avito-tab-item">
                    <AvitoPublications
                        :connections="connections"
                        :configured="status.configured"
                        :enabled="status.enabled"
                        :mutations-enabled="status.mutations_enabled"
                        @notice="notice = $event; error = ''"
                        @error="error = $event; notice = ''"
                    />
                </v-window-item>

                <v-window-item value="messages" class="avito-tab-item">
                    <AvitoMessages
                        :connections="connections"
                        @notice="notice = $event; error = ''"
                        @error="error = $event; notice = ''"
                    />
                </v-window-item>

                <v-window-item value="auto-replies" class="avito-tab-item">
                    <AvitoAutoReplies
                        standalone
                        @notice="notice = $event; error = ''"
                        @error="error = $event; notice = ''"
                    />
                </v-window-item>

                <v-window-item value="templates" class="avito-tab-item">
                    <AvitoMessageTemplates
                        standalone
                        @notice="notice = $event; error = ''"
                        @error="error = $event; notice = ''"
                    />
                </v-window-item>

                <v-window-item value="catalog" class="avito-tab-item">
                    <section class="avito-panel avito-panel--catalog">
                        <div class="catalog-toolbar">
                            <v-text-field v-model="filters.search" prepend-inner-icon="mdi-magnify" label="Поиск по функции, endpoint или operationId" variant="outlined" density="compact" hide-details clearable />
                            <v-select v-model="filters.section" :items="sectionOptions" label="Раздел" variant="outlined" density="compact" hide-details clearable />
                            <v-select v-model="filters.method" :items="methodOptions" label="Метод" variant="outlined" density="compact" hide-details clearable />
                            <v-select v-model="filters.access" :items="accessOptions" label="Доступ" variant="outlined" density="compact" hide-details clearable />
                            <v-select v-model="filters.enabled" :items="[{ title: 'Включены', value: true }, { title: 'Отключены', value: false }]" label="Состояние" variant="outlined" density="compact" hide-details clearable />
                        </div>

                        <div class="bulk-toolbar">
                            <span>Показано {{ filteredCapabilities.length }} из {{ capabilities.length }}</span>
                            <template v-if="selectedIds.length">
                                <v-divider vertical />
                                <strong>Выбрано: {{ selectedIds.length }}</strong>
                                <v-btn size="small" variant="tonal" color="success" @click="bulkToggle(true)">Включить</v-btn>
                                <v-btn size="small" variant="tonal" color="error" @click="bulkToggle(false)">Отключить</v-btn>
                            </template>
                        </div>

                        <div class="excel-shell">
                            <table class="excel-table">
                                <thead><tr>
                                    <th class="cell-select"><v-checkbox-btn :model-value="allPageSelected" @click.stop="togglePageSelection" /></th>
                                    <th class="cell-enabled">On</th>
                                    <th class="cell-method">Метод</th>
                                    <th class="cell-function">Функция</th>
                                    <th class="cell-endpoint">Endpoint</th>
                                    <th class="cell-auth">Авторизация / scopes</th>
                                    <th class="cell-risk">Режим</th>
                                    <th class="cell-state">Последний вызов</th>
                                    <th class="cell-actions">Действия</th>
                                </tr></thead>
                                <tbody>
                                    <tr v-for="item in paginatedCapabilities" :key="item.id" :class="{ 'is-disabled': !item.enabled, 'is-deprecated': item.deprecated }">
                                        <td class="cell-select"><v-checkbox-btn v-model="selectedIds" :value="item.id" /></td>
                                        <td class="cell-enabled"><v-switch :model-value="item.enabled" color="success" density="compact" hide-details @click.stop="toggleCapability(item)" /></td>
                                        <td class="cell-method"><span class="method-pill" :class="methodClass(item.method)">{{ item.method }}</span></td>
                                        <td class="cell-function"><strong>{{ item.summary }}</strong><small>{{ item.section_title }} · {{ item.operation_id }}</small><v-chip v-if="item.deprecated" size="x-small" color="warning" variant="tonal">deprecated</v-chip></td>
                                        <td class="cell-endpoint"><code>{{ item.path }}</code></td>
                                        <td class="cell-auth"><strong>{{ authLabel(item) }}</strong><small :title="scopesLabel(item)">{{ scopesLabel(item) }}</small></td>
                                        <td class="cell-risk"><v-chip size="small" :color="item.access === 'mutation' ? (item.risk === 'destructive' ? 'error' : 'warning') : item.access === 'managed' ? 'info' : 'success'" variant="tonal">{{ item.access === 'mutation' ? (item.risk === 'destructive' ? 'опасная' : 'изменение') : item.access === 'managed' ? 'системная' : 'чтение' }}</v-chip></td>
                                        <td class="cell-state"><v-chip v-if="item.last_status" size="x-small" :color="statusColor(item.last_status)" variant="tonal">{{ item.last_http_status || item.last_status }}</v-chip><small>{{ formatDate(item.last_used_at) }}</small></td>
                                        <td class="cell-actions"><v-btn size="small" color="deep-purple" variant="tonal" prepend-icon="mdi-play" :disabled="item.managed_by_integration || !item.enabled" @click="openExecutor(item)">Вызвать</v-btn><v-btn :href="item.documentation_url" target="_blank" rel="noopener noreferrer" icon="mdi-open-in-new" size="small" variant="text" /></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="catalog-footer">
                            <v-select v-model="perPage" :items="[25, 50, 100]" density="compact" variant="outlined" hide-details label="Строк" />
                            <v-pagination v-model="page" :length="pageCount" :total-visible="7" density="compact" />
                        </div>
                    </section>
                </v-window-item>

                <v-window-item value="connections" class="avito-tab-item">
                    <section class="avito-panel">
                        <div class="avito-panel__header"><div><span class="eyebrow">AUTHORIZATION CODE</span><h2>OAuth-подключения</h2></div><v-btn color="deep-purple" prepend-icon="mdi-link-variant-plus" @click="connectOAuth">Подключить аккаунт</v-btn></div>
                        <v-alert type="info" variant="tonal" class="mb-4">Client credentials из <code>.env</code> используются для собственного аккаунта. OAuth-подключения нужны для управления аккаунтами других пользователей в рамках выданных scopes.</v-alert>
                        <div v-if="connections.length" class="connections-grid">
                            <article v-for="connection in connections" :key="connection.id" class="connection-card">
                                <div><v-icon icon="mdi-account-key-outline" size="28" /><div><strong>{{ connection.name }}</strong><small>ID {{ connection.external_user_id || 'не определён' }}</small></div><v-chip :color="statusColor(connection.status)" size="small" variant="tonal">{{ connection.status }}</v-chip></div>
                                <dl><dt>Токен до</dt><dd>{{ formatDate(connection.token_expires_at) }}</dd><dt>Scopes</dt><dd>{{ connection.scopes?.length || 0 }}</dd><dt>Проверен</dt><dd>{{ formatDate(connection.last_checked_at) }}</dd></dl>
                                <div class="connection-card__actions"><v-btn size="small" variant="tonal" :loading="preflightLoading" @click="runPreflight(connection.id)">Проверить</v-btn><v-btn size="small" variant="text" @click="refreshConnection(connection)">Обновить токен</v-btn><v-btn size="small" color="error" variant="text" @click="deleteConnection(connection)">Удалить</v-btn></div>
                            </article>
                        </div>
                        <div v-else class="empty-state"><v-icon icon="mdi-link-variant-off" size="42" /><strong>OAuth-подключений пока нет</strong><span>Персональный доступ продолжит работать через client credentials.</span></div>
                    </section>
                </v-window-item>

                <v-window-item value="calls" class="avito-tab-item">
                    <section class="avito-panel">
                        <div class="avito-panel__header"><div><span class="eyebrow">AUDIT TRAIL</span><h2>Журнал API-запросов</h2></div><v-btn icon="mdi-refresh" variant="text" @click="loadCalls" /></div>
                        <div class="simple-table-shell"><table class="simple-table"><thead><tr><th>Время</th><th>Request ID</th><th>Функция</th><th>Метод / endpoint</th><th>Статус</th><th>Время</th><th></th></tr></thead><tbody><tr v-for="call in calls" :key="call.id"><td>{{ formatDate(call.created_at) }}</td><td><code>{{ call.request_id }}</code></td><td>{{ call.capability_id }}</td><td><span class="method-pill" :class="methodClass(call.method)">{{ call.method }}</span><code>{{ call.endpoint }}</code></td><td><v-chip size="small" :color="statusColor(call.status)" variant="tonal">{{ call.http_status || call.status }}</v-chip></td><td>{{ call.duration_ms != null ? `${call.duration_ms} ms` : '—' }}</td><td><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="showCall(call)" /></td></tr><tr v-if="!calls.length"><td colspan="7" class="empty-cell">Запросов ещё не было.</td></tr></tbody></table></div>
                        <v-pagination v-if="callsTotal > 30" v-model="callsPage" :length="Math.ceil(callsTotal / 30)" @update:model-value="loadCalls" />
                    </section>
                </v-window-item>

                <v-window-item value="webhooks" class="avito-tab-item">
                    <section class="avito-panel">
                        <div class="avito-panel__header"><div><span class="eyebrow">EVENT INBOX</span><h2>Webhook-события</h2></div><v-btn icon="mdi-refresh" variant="text" @click="loadWebhooks" /></div>
                        <label class="copy-field copy-field--inline"><span>Endpoint для подписок Avito</span><code>{{ status.webhook_url }}</code><v-btn icon="mdi-content-copy" size="small" variant="text" @click="copyText(status.webhook_url)" /></label>
                        <v-alert v-if="!status.webhook_protected" type="warning" variant="tonal" class="my-4">До регистрации webhook задайте <code>AVITO_WEBHOOK_SECRET</code>. События дедуплицируются, а payload хранится в БД в зашифрованном виде.</v-alert>
                        <div class="simple-table-shell"><table class="simple-table"><thead><tr><th>Получено</th><th>Тип</th><th>External ID</th><th>Статус</th><th></th></tr></thead><tbody><tr v-for="event in webhooks" :key="event.id"><td>{{ formatDate(event.received_at) }}</td><td>{{ event.event_type }}</td><td><code>{{ event.external_event_id || '—' }}</code></td><td><v-chip size="small" :color="statusColor(event.status)" variant="tonal">{{ event.status }}</v-chip></td><td><v-btn icon="mdi-eye-outline" size="small" variant="text" @click="showWebhook(event)" /></td></tr><tr v-if="!webhooks.length"><td colspan="5" class="empty-cell">Webhook-событий пока нет.</td></tr></tbody></table></div>
                        <v-pagination v-if="webhooksTotal > 30" v-model="webhooksPage" :length="Math.ceil(webhooksTotal / 30)" @update:model-value="loadWebhooks" />
                    </section>
                </v-window-item>
            </v-window>
        </template>

        <v-dialog v-model="executorDialog" max-width="1120" persistent scrollable>
            <v-card class="executor-card">
                <v-card-title class="executor-title"><div><small>{{ selectedCapability?.section_title }}</small><strong>{{ selectedCapability?.summary || 'Функция Avito' }}</strong></div><v-btn icon="mdi-close" variant="text" @click="executorDialog = false" /></v-card-title>
                <v-card-text>
                    <div v-if="executorLoading" class="avito-loading"><v-progress-circular indeterminate /><span>Загружаем OpenAPI-схему…</span></div>
                    <template v-else-if="selectedCapability">
                        <div class="endpoint-banner"><span class="method-pill" :class="methodClass(selectedCapability.method)">{{ selectedCapability.method }}</span><code>{{ selectedCapability.server }}{{ selectedCapability.path }}</code><v-chip :color="selectedCapability.access === 'mutation' ? 'warning' : 'success'" size="small" variant="tonal">{{ selectedCapability.access }}</v-chip></div>
                        <p v-if="selectedCapability.description" class="operation-description">{{ selectedCapability.description }}</p>
                        <v-select v-model="executionForm.connection_id" :items="[{ title: 'Client credentials (.env)', value: null }, ...connections.map((item) => ({ title: item.name, value: item.id }))]" label="Способ авторизации" variant="outlined" density="compact" />

                        <div v-if="Object.keys(executionForm.path).length" class="form-section"><h3>Path-параметры</h3><div class="parameter-grid"><v-text-field v-for="(_, name) in executionForm.path" :key="name" v-model="executionForm.path[name]" :label="name" variant="outlined" density="compact" /></div></div>
                        <div v-if="Object.keys(executionForm.query).length" class="form-section"><h3>Query-параметры</h3><div class="parameter-grid"><v-text-field v-for="(_, name) in executionForm.query" :key="name" v-model="executionForm.query[name]" :label="name" variant="outlined" density="compact" /></div></div>
                        <div v-if="Object.keys(executionForm.headers).length" class="form-section"><h3>Дополнительные headers</h3><div class="parameter-grid"><v-text-field v-for="(_, name) in executionForm.headers" :key="name" v-model="executionForm.headers[name]" :label="name" variant="outlined" density="compact" /></div></div>
                        <div v-if="selectedCapability.request_body" class="form-section"><h3>Тело запроса</h3><v-select v-if="Object.keys(selectedCapability.request_body.content || {}).length > 1" v-model="executionForm.content_type" :items="Object.keys(selectedCapability.request_body.content)" label="Content-Type" variant="outlined" density="compact" @update:model-value="changeContentType" /><v-textarea v-model="executionForm.body" label="JSON body" variant="outlined" rows="10" spellcheck="false" class="json-editor" /><v-file-input v-if="executionForm.content_type === 'multipart/form-data'" v-model="uploadFiles" label="Файл по OpenAPI-схеме" variant="outlined" multiple show-size /></div>
                        <v-alert v-if="selectedCapability.access === 'mutation' && !status.mutations_enabled" type="warning" variant="tonal" class="mb-3">Сервер блокирует изменяющие операции. Это намеренно, пока Ameise работает без общей авторизации.</v-alert>
                        <v-checkbox v-if="selectedCapability.access === 'mutation'" v-model="executionForm.confirmed" color="warning" label="Подтверждаю реальное изменение данных в Avito" />
                        <section v-if="executionResult" class="execution-result" :class="executionResult.ok ? 'is-success' : 'is-error'"><div><strong>{{ executionResult.ok ? 'Avito принял запрос' : 'Запрос завершён с ошибкой' }}</strong><span v-if="executionResult.status">HTTP {{ executionResult.status }} · {{ executionResult.duration_ms }} ms · {{ executionResult.request_id }}</span></div><v-btn v-if="executionResult.binary" size="small" prepend-icon="mdi-download" @click="downloadBinary">Скачать файл</v-btn><pre v-else>{{ prettyJson(executionResult.data ?? executionResult) }}</pre></section>
                    </template>
                </v-card-text>
                <v-card-actions><v-btn variant="text" @click="executorDialog = false">Закрыть</v-btn><v-spacer /><v-btn color="deep-purple" prepend-icon="mdi-play" :loading="executing" :disabled="executorLoading || (selectedCapability?.access === 'mutation' && (!status.mutations_enabled || !executionForm.confirmed))" @click="executeCapability">Выполнить запрос</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="detailDialog" max-width="900" scrollable><v-card><v-card-title class="d-flex align-center"><span>{{ detailTitle }}</span><v-spacer /><v-btn icon="mdi-close" variant="text" @click="detailDialog = false" /></v-card-title><v-card-text><div v-if="detailLoading" class="avito-loading"><v-progress-circular indeterminate /></div><pre v-else class="detail-json">{{ prettyJson(detailPayload) }}</pre></v-card-text></v-card></v-dialog>
    </main>
</template>

<style scoped>
.avito-page { box-sizing: border-box; width: 100%; max-width: none; min-height: calc(100vh - 64px); align-self: stretch; padding: 6px 7px 14px; color: #edf0ff; background: radial-gradient(circle at 15% -5%, rgba(114, 70, 255, .2), transparent 38%), #0e1020; }
.avito-hero { display: flex; min-height: 68px; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; border: 1px solid rgba(180, 166, 255, .22); border-radius: 12px; background: linear-gradient(125deg, rgba(71, 42, 151, .94), rgba(28, 31, 64, .96)); box-shadow: 0 10px 24px rgba(0, 0, 0, .2); }
.avito-hero h1 { margin: 1px 0 2px; font-size: clamp(22px, 2.4vw, 30px); line-height: 1; letter-spacing: -.03em; }
.avito-hero p { max-width: 720px; margin: 4px 0 0; color: #c9c9e8; font-size: 11px; line-height: 1.35; }
.avito-kicker, .eyebrow { color: #b9a8ff; font-size: 8px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
.avito-hero__actions { display: flex; flex-wrap: wrap; gap: 6px; }
.avito-loading { display: flex; min-height: 260px; align-items: center; justify-content: center; gap: 14px; color: #bfc3de; }
.avito-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; margin: 6px 0; }
.avito-metrics article { display: grid; gap: 2px; min-height: 70px; padding: 9px 12px; border: 1px solid rgba(148, 154, 196, .16); border-radius: 10px; background: rgba(27, 30, 53, .88); }
.avito-metrics span, .avito-metrics small { color: #999fbe; font-size: 9px; }
.avito-metrics strong { font-size: 22px; line-height: 1; }
.avito-tabs { min-height: 42px; border: 1px solid rgba(148, 154, 196, .16); border-radius: 10px 10px 0 0; background: #191c32; }.avito-tabs :deep(.v-tab) { min-height: 42px; padding: 0 12px; font-size: 11px; }
.avito-window { border: 1px solid rgba(148, 154, 196, .16); border-top: 0; border-radius: 0 0 12px 12px; background: #14172a; }.avito-tab-item { padding: 5px; }
.avito-grid { display: grid; gap: 8px; padding: 0; }
.avito-grid--overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.avito-panel { padding: 14px; color: #e9ebff; border: 1px solid rgba(145, 152, 200, .16); border-radius: 10px; background: #1b1e35; }
.avito-panel--wide { grid-column: 1 / -1; }
.avito-panel--catalog { padding: 10px; border: 0; border-radius: 10px; }
.avito-panel__header { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.avito-panel h2 { margin: 2px 0 0; font-size: 16px; }
.readiness-list { display: grid; gap: 2px; }
.readiness-list > div { display: grid; grid-template-columns: 28px 1fr auto; align-items: center; gap: 8px; padding: 10px 0; border-bottom: 1px solid rgba(145, 152, 200, .12); }
.readiness-list strong { font-size: 13px; }
.panel-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
.copy-field { display: grid; grid-template-columns: 1fr auto; gap: 4px 8px; margin: 12px 0; padding: 12px 14px; border: 1px solid rgba(151, 155, 197, .16); border-radius: 10px; background: #121529; }
.copy-field span { grid-column: 1 / -1; color: #9298ba; font-size: 12px; }
.copy-field code { overflow: hidden; align-self: center; color: #d9d2ff; text-overflow: ellipsis; white-space: nowrap; }
.copy-field--inline { max-width: 780px; }
.panel-note { color: #aeb3cf; font-size: 13px; line-height: 1.6; }
.section-cloud { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.section-cloud button { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; color: #dfe1f5; text-align: left; border: 1px solid rgba(147, 154, 201, .16); border-radius: 9px; background: #15182d; transition: .15s ease; }
.section-cloud button:hover { border-color: #8b72ff; transform: translateY(-1px); }
.section-cloud strong { min-width: 28px; color: #b7a7ff; text-align: right; }
.catalog-toolbar { display: grid; grid-template-columns: minmax(260px, 2fr) minmax(180px, 1.2fr) 110px 160px 145px; gap: 9px; margin-bottom: 10px; }
.bulk-toolbar { display: flex; min-height: 38px; align-items: center; gap: 10px; padding: 4px 8px; color: #9da3c3; font-size: 13px; }
.excel-shell { overflow: auto; height: calc(100vh - 380px); min-height: 380px; border: 1px solid #343852; border-radius: 10px; background: #111427; }
.excel-table { width: 100%; min-width: 1540px; border-spacing: 0; border-collapse: separate; font-size: 12px; }
.excel-table th { position: sticky; z-index: 3; top: 0; height: 38px; padding: 5px 8px; color: #b8bedb; text-align: left; border-right: 1px solid #343852; border-bottom: 1px solid #424763; background: #24283f; }
.excel-table td { height: 52px; padding: 6px 8px; vertical-align: middle; border-right: 1px solid #292d45; border-bottom: 1px solid #292d45; background: #171a2e; }
.excel-table tr:nth-child(even) td { background: #15182b; }
.excel-table tr:hover td { background: #20243c; }
.excel-table tr.is-disabled { opacity: .55; }
.excel-table tr.is-deprecated td { box-shadow: inset 0 1px rgba(255, 166, 0, .18); }
.cell-select { width: 42px; text-align: center !important; }
.cell-enabled { width: 66px; }
.cell-method { width: 78px; }
.cell-function { min-width: 260px; max-width: 340px; }
.cell-function strong, .cell-function small, .cell-auth strong, .cell-auth small, .cell-state small { display: block; }
.cell-function small, .cell-auth small, .cell-state small { overflow: hidden; margin-top: 3px; color: #888eae; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
.cell-endpoint { min-width: 300px; max-width: 430px; }
.cell-endpoint code { display: block; overflow: hidden; color: #c8d6ff; text-overflow: ellipsis; white-space: nowrap; }
.cell-auth { width: 225px; max-width: 225px; }
.cell-risk { width: 112px; }
.cell-state { width: 115px; }
.cell-actions { display: flex; width: 145px; align-items: center; gap: 2px; }
.method-pill { display: inline-flex; min-width: 54px; justify-content: center; padding: 4px 6px; color: #fff; font-size: 10px; font-weight: 900; letter-spacing: .04em; border-radius: 5px; }
.method-get { background: #16825a; }.method-post { background: #7657d7; }.method-put, .method-patch { background: #b06c19; }.method-delete { background: #b63e55; }
.catalog-footer { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding-top: 10px; }
.catalog-footer .v-select { max-width: 115px; }
.connections-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.connection-card { padding: 16px; border: 1px solid rgba(153, 158, 205, .17); border-radius: 13px; background: #14172a; }
.connection-card > div:first-child { display: grid; grid-template-columns: 36px 1fr auto; align-items: center; gap: 8px; }
.connection-card strong, .connection-card small { display: block; }.connection-card small { color: #9298b8; }
.connection-card dl { display: grid; grid-template-columns: 100px 1fr; gap: 7px; margin: 16px 0; font-size: 13px; }.connection-card dt { color: #8f95b7; }.connection-card dd { margin: 0; }
.connection-card__actions { display: flex; flex-wrap: wrap; gap: 5px; }
.empty-state { display: grid; min-height: 260px; place-items: center; align-content: center; gap: 8px; color: #969cbc; }.empty-state strong { color: #e2e5fa; }
.simple-table-shell { overflow: auto; border: 1px solid #33374f; border-radius: 10px; }
.simple-table { width: 100%; min-width: 850px; border-collapse: collapse; font-size: 12px; }.simple-table th, .simple-table td { padding: 10px; text-align: left; border-bottom: 1px solid #2c3047; }.simple-table th { color: #aeb4d1; background: #23273d; }.simple-table td { background: #15182b; }.simple-table code { display: block; max-width: 320px; overflow: hidden; color: #cbd4ff; text-overflow: ellipsis; white-space: nowrap; }.empty-cell { height: 180px; color: #9298b8; text-align: center !important; }
.executor-card { color: #e9ebff !important; background: #171a2e !important; }.executor-title { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #33374e; }.executor-title small, .executor-title strong { display: block; }.executor-title small { color: #979dbd; font-size: 11px; }.endpoint-banner { display: flex; align-items: center; gap: 10px; padding: 11px 13px; border-radius: 9px; background: #0f1223; }.endpoint-banner code { overflow: auto; flex: 1; color: #d0d8ff; white-space: nowrap; }.operation-description { max-height: 130px; overflow: auto; padding: 10px 2px; color: #abb1cc; font-size: 13px; white-space: pre-line; }.form-section { margin: 16px 0; }.form-section h3 { margin: 0 0 10px; font-size: 14px; }.parameter-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }.json-editor :deep(textarea) { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; line-height: 1.5; }
.execution-result { display: grid; gap: 10px; margin-top: 15px; padding: 13px; border: 1px solid; border-radius: 10px; }.execution-result.is-success { border-color: rgba(56, 181, 125, .45); background: rgba(30, 114, 80, .14); }.execution-result.is-error { border-color: rgba(230, 75, 101, .45); background: rgba(151, 35, 58, .14); }.execution-result span { display: block; color: #aeb3cf; font-size: 11px; }.execution-result pre, .detail-json { max-height: 360px; overflow: auto; margin: 0; padding: 12px; color: #d8defb; font: 11px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; border-radius: 7px; background: #0d1020; }
code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
@media (max-width: 1100px) { .avito-metrics { grid-template-columns: repeat(2, 1fr); }.avito-grid--overview { grid-template-columns: 1fr; }.section-cloud { grid-template-columns: repeat(2, 1fr); }.catalog-toolbar { grid-template-columns: 2fr 1fr 1fr; }.catalog-toolbar > :nth-child(4), .catalog-toolbar > :nth-child(5) { grid-column: span 1; } }
@media (max-width: 700px) { .avito-page { padding: 5px; }.avito-hero { align-items: flex-start; flex-direction: column; padding: 10px 12px; }.avito-metrics { grid-template-columns: 1fr 1fr; }.avito-metrics article { min-height: 68px; padding: 8px; }.avito-grid { padding: 0; }.section-cloud, .connections-grid, .parameter-grid, .catalog-toolbar { grid-template-columns: 1fr; }.catalog-toolbar > * { grid-column: auto !important; }.excel-shell { height: calc(100vh - 470px); }.catalog-footer { align-items: flex-end; flex-direction: column; }.avito-hero__actions { width: 100%; }}
</style>
