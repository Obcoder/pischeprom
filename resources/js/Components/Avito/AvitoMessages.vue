<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAvitoRealtime } from '../../Composables/useAvitoRealtime.js'
import { collectAvitoChanges, mergeAvitoMessages } from '../../Stores/avito.js'
import axios from 'axios'
import AvitoCrmPanel from './AvitoCrmPanel.vue'

const props = defineProps({
    connections: { type: Array, default: () => [] },
    embedded: { type: Boolean, default: false },
    fullFeatured: { type: Boolean, default: false },
    autoMarkRead: { type: Boolean, default: true },
    chat: { type: Object, default: null },
})

const emit = defineEmits(['notice', 'error', 'waiting-change', 'chat-updated'])

const store = useAvitoRealtime({ key: 'messages', topics: ['avito_messages'], load: reloadRealtime })
const { loading, chatsLoading, chatLoading, sending, syncing, overview, chats, chatsMeta,
    selectedChat, messages, messagesMeta, subscriptions, selectedConnectionId, activeRun,
    composerText, composerTemplateId, composerTemplateName, filters } = storeToRefs(store)
const composerInput = ref(null)
const imageInput = ref(null)
const messageStream = ref(null)
const crmPanel = ref(null)
const mobilePane = ref(store.selectedChat ? 'conversation' : 'chats')
const refreshingArchive = ref(false)
const archiveRefreshFailed = ref(false)
const waitingSaving = ref(null)
const toolsEnabled = computed(() => !props.embedded || props.fullFeatured)
let manualRefreshController = null
let searchTimer = null
let readTimer = null
let readRetryAt = 0
let disposed = false
let streamVersion = 0
let olderPromise = null
let followNewMessages = true

const connectionOptions = computed(() => [
    { title: 'Основной аккаунт', value: null },
    ...props.connections.map((connection) => ({ title: connection.name, value: connection.id })),
])
const accountOptions = computed(() => overview.value.accounts.map((account) => ({
    title: `${account.name || account.external_user_id} · ${account.chats_count || 0}`,
    value: account.id,
})))
const runningRun = computed(() => activeRun.value
    || overview.value.latest_runs.find((run) => ['queued', 'running'].includes(run.status)))
const canSend = computed(() => selectedChat.value && composerText.value.trim().length > 0 && !sending.value)
const realtimeFailed = computed(() => archiveRefreshFailed.value
    || Object.keys(store.refreshErrors).some((key) => key.startsWith('messages:')))
const realtimeStatus = computed(() => {
    if (realtimeFailed.value) return { label: 'Не удалось обновить архив', short: 'Сбой', icon: 'mdi-alert-circle-outline', warning: true }
    return {
        live: { label: 'Обновляется автоматически', short: 'Авто', icon: 'mdi-access-point', warning: false },
        connecting: { label: 'Подключение…', short: 'Связь…', icon: 'mdi-connection', warning: false },
        disabled: { label: 'Обновление вручную', short: 'Вручную', icon: 'mdi-sync-off', warning: true },
        offline: { label: 'Связь потеряна', short: 'Нет связи', icon: 'mdi-wifi-off', warning: true },
        forbidden: { label: 'Нет доступа к автообновлению', short: 'Нет доступа', icon: 'mdi-lock-outline', warning: true },
        error: { label: 'Автообновление недоступно', short: 'Сбой', icon: 'mdi-alert-circle-outline', warning: true },
    }[store.status] || { label: 'Обновление вручную', short: 'Вручную', icon: 'mdi-sync-off', warning: true }
})
const realtimeHint = computed(() => `${realtimeStatus.value.label}. ${store.status === 'disabled'
    ? 'Нажмите, чтобы перечитать сохранённые чаты и сообщения.'
    : 'Нажмите, чтобы восстановить соединение и перечитать архив.'}`)

watch(filters, () => {
    if (props.embedded) return
    store.invalidateChats()
    chatsMeta.value.current_page = 1
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadChats(1), 280)
}, { deep: true, flush: 'sync' })

async function initialize() {
    loading.value = true
    try {
        if (props.embedded) {
            await selectEmbeddedChat(props.chat)
            return
        }
        await Promise.all([loadOverview(), loadChats(chatsMeta.value.current_page), loadSubscriptions()])
        if (selectedChat.value) await loadChatPage(1, false, { preserve: true })
    } finally {
        if (!disposed) {
            loading.value = false
            if (props.embedded) {
                await scrollToBottom()
                scheduleReadReceipt()
            }
        }
    }
}

async function loadOverview(options = {}) {
    if (props.embedded) return
    const previousRun = activeRun.value
    try {
        const data = await store.loadOverview(options)
        const finished = data?.latest_runs?.find((run) => run.id === previousRun?.id && !['queued', 'running'].includes(run.status))
        if (finished?.status === 'success') {
            notify(`Архив обновлён: ${finished.messages_created} новых сообщений, ${finished.chats_created} новых чатов.`)
        } else if (finished) emit('error', finished.error_message || 'Синхронизация Avito завершилась с ошибкой.')
    } catch (exception) {
        fail(exception, 'Не удалось загрузить состояние архива сообщений.')
        if (options.signal) throw exception
    }
}

async function loadChats(page = chatsMeta.value.current_page, options = {}) {
    if (props.embedded) return
    try {
        await store.loadChats(page, options)
    } catch (exception) {
        fail(exception, 'Не удалось загрузить локальный архив чатов.')
        if (options.signal) throw exception
    }
}

async function openChat(chat) {
    mobilePane.value = 'conversation'
    if (selectedChat.value?.id === chat.id) return
    store.selectChat(chat)
    await loadChatPage(1, false, { forceScroll: true })
}

async function selectEmbeddedChat(chat) {
    clearTimeout(readTimer)
    streamVersion++
    followNewMessages = true
    mobilePane.value = 'conversation'
    store.selectChat(chat)
    if (chat) await loadChatPage(1, false, { forceScroll: true })
}

watch(() => props.chat, (chat) => {
    if (!props.embedded || disposed) return
    if (chat?.id !== selectedChat.value?.id) {
        void selectEmbeddedChat(chat)
    } else if (chat && selectedChat.value
        && (chat.waiting_since !== selectedChat.value.waiting_since || chat.waiting_note !== selectedChat.value.waiting_note)) {
        selectedChat.value = { ...selectedChat.value, waiting_since: chat.waiting_since, waiting_note: chat.waiting_note }
    }
})

watch(selectedChat, (chat) => {
    if (!disposed && chat && (!props.embedded || chat.id === props.chat?.id)) emit('chat-updated', { ...chat })
})

async function toggleWaiting() {
    const chat = selectedChat.value
    if (!chat || waitingSaving.value !== null) return
    const chatId = chat.id
    const remove = Boolean(chat.waiting_since)
    waitingSaving.value = chatId
    try {
        const url = `/api/avito/messenger/chats/${chatId}/waiting-list`
        const { data } = remove ? await axios.delete(url) : await axios.put(url)
        if (disposed) return
        // A waiting response must not replace messages/read state changed during the request.
        const waiting = { waiting_since: data.chat.waiting_since, waiting_note: data.chat.waiting_note }
        chats.value = chats.value.map((item) => item.id === chatId ? { ...item, ...waiting } : item)
        if (selectedChat.value?.id === chatId) selectedChat.value = { ...selectedChat.value, ...waiting }
        emit('waiting-change', data.chat)
        notify(remove ? 'Чат удалён из листа ожидания.' : 'Чат добавлен в лист ожидания на странице Ameise.')
    } catch (exception) {
        fail(exception, 'Не удалось изменить лист ожидания.')
    } finally {
        waitingSaving.value = null
    }
}

function atBottom() {
    const stream = messageStream.value
    return !stream || stream.scrollHeight - stream.scrollTop - stream.clientHeight < 80
}

function trackStreamScroll() {
    followNewMessages = atBottom()
    scheduleReadReceipt()
}

async function loadChatPage(page, prepend = false, options = {}) {
    if (options.preserve && olderPromise) await olderPromise
    if (disposed || options.signal?.aborted) return
    const chatId = selectedChat.value?.id
    const version = ++streamVersion
    const stream = messageStream.value
    const oldHeight = stream?.scrollHeight || 0
    const follow = options.forceScroll || atBottom()
    try {
        const data = options.preserve && !prepend
            ? await store.refreshChat({ signal: options.signal })
            : await store.loadChatPage(page, { prepend, signal: options.signal })
        if (!data || disposed || selectedChat.value?.id !== chatId || version !== streamVersion) return
        await nextTick()
        if (prepend && messageStream.value) messageStream.value.scrollTop += messageStream.value.scrollHeight - oldHeight
        else if (options.forceScroll || (follow && followNewMessages)) await scrollToBottom(chatId)
        scheduleReadReceipt()
    } catch (exception) {
        fail(exception, 'Не удалось открыть переписку.')
        if (options.signal) throw exception
    }
}

async function loadOlderMessages() {
    if (chatLoading.value || messagesMeta.value.current_page >= messagesMeta.value.last_page) return
    olderPromise = loadChatPage(messagesMeta.value.current_page + 1, true)
    try { await olderPromise } finally { olderPromise = null }
}

async function reloadRealtime(options) {
    if (olderPromise) await olderPromise
    if (disposed || options.signal.aborted) return
    const changes = options.reason === 'change' && !options.overflow ? collectAvitoChanges(options.events) : null
    if (changes) {
        const chatId = selectedChat.value?.id
        const follow = atBottom() && followNewMessages
        if (props.embedded && !changes.chatIds.has(chatId)) return
        await store.loadUpdates(props.embedded ? { ...changes, overview: false, chats: false } : changes, { signal: options.signal })
        if (!disposed && !options.signal.aborted && follow) await scrollToBottom(chatId)
        scheduleReadReceipt()
    } else {
        // Initial connection / reconnect reconciles any events missed while offline.
        await Promise.all([
            loadOverview(options), loadChats(chatsMeta.value.current_page, options),
            loadChatPage(1, false, { ...options, preserve: true }),
        ])
    }
    if (!disposed && !options.signal.aborted) archiveRefreshFailed.value = false
}

async function refreshArchive(reconnect = false) {
    if (refreshingArchive.value) return
    if (reconnect && store.status !== 'disabled') store.reconnect()
    const controller = new AbortController()
    manualRefreshController = controller
    refreshingArchive.value = true
    try {
        await Promise.all([reloadRealtime({ signal: controller.signal }), store.loadControl({ signal: controller.signal })])
        if (disposed || controller.signal.aborted) return
        archiveRefreshFailed.value = false
        for (const key of Object.keys(store.refreshErrors)) {
            if (key.startsWith('messages:')) delete store.refreshErrors[key]
        }
    } catch (exception) {
        if (!disposed && !controller.signal.aborted) {
            archiveRefreshFailed.value = true
            fail(exception, 'Не удалось перечитать архив сообщений.')
        }
    } finally {
        if (!disposed) refreshingArchive.value = false
    }
}

async function queueSync(full = false) {
    syncing.value = true
    try {
        const { data } = await axios.post('/api/avito/messenger/sync', {
            connection_id: selectedConnectionId.value,
            full,
        })
        activeRun.value = data.run
        notify(full ? 'Запущена полная архивация доступной истории Avito.' : 'Запущена синхронизация новых чатов и сообщений.')
    } catch (exception) {
        fail(exception, 'Не удалось запустить синхронизацию.')
    } finally {
        syncing.value = false
    }
}

async function refreshSelectedChat() {
    if (!selectedChat.value) return
    const chatId = selectedChat.value.id
    chatLoading.value = true
    try {
        await axios.post(`/api/avito/messenger/chats/${chatId}/refresh`, { message_limit: 100 })
        if (disposed || selectedChat.value?.id !== chatId) return
        await Promise.all([loadChatPage(1, false, { preserve: true }), loadChats(chatsMeta.value.current_page)])
        notify('Переписка обновлена из Avito.')
    } catch (exception) {
        fail(exception, 'Не удалось обновить чат.')
    } finally {
        if (!disposed && selectedChat.value?.id === chatId) chatLoading.value = false
    }
}

function canAcknowledgeVisibleChat() {
    return props.autoMarkRead && !disposed && selectedChat.value?.is_unread && !chatLoading.value
        && document.visibilityState === 'visible' && document.hasFocus()
        && messageStream.value?.getClientRects().length > 0 && atBottom()
        && (!selectedChat.value.last_message_id
            || messages.value.some((message) => message.external_message_id === selectedChat.value.last_message_id))
}

function scheduleReadReceipt() {
    clearTimeout(readTimer)
    if (!canAcknowledgeVisibleChat() || Date.now() < readRetryAt) return
    readTimer = setTimeout(() => {
        if (canAcknowledgeVisibleChat()) void markRead(true)
    }, 600)
}

async function markRead(automatic = false) {
    if (!selectedChat.value || (automatic && !props.autoMarkRead)) return
    const chatId = selectedChat.value.id
    const throughMessageId = automatic ? Math.max(0, ...messages.value.map((message) => message.id)) : undefined
    if (automatic && !throughMessageId) return
    try {
        await store.markRead(chatId, throughMessageId)
        if (disposed) return
        // Reconcile a read response with any arrivals that raced the acknowledgement.
        await store.loadUpdates({ chatIds: new Set([chatId]), messageIds: new Set(), overview: !props.embedded, chats: !props.embedded })
        if (!automatic) notify('Чат отмечен прочитанным на Avito.')
    } catch (exception) {
        readRetryAt = Date.now() + Math.max(5, Number(exception?.response?.data?.retry_after) || 30) * 1000
        fail(exception, 'Не удалось отметить чат прочитанным.')
    }
}

async function sendText() {
    const text = composerText.value.trim()
    if (!text || !selectedChat.value || sending.value) return
    const chatId = selectedChat.value.id
    const draft = composerText.value
    const follow = atBottom()
    sending.value = true
    try {
        const { data } = await axios.post(`/api/avito/messenger/chats/${chatId}/messages`, {
            text,
            template_id: composerTemplateId.value || undefined,
        })
        if (disposed) return
        if (selectedChat.value?.id === chatId) {
            messages.value = uniqueMessages([...messages.value, data.item])
            if (composerText.value === draft) {
                composerText.value = ''
                clearComposerTemplate()
            }
            if (follow && followNewMessages) await scrollToBottom(chatId)
        }
        await loadChats(chatsMeta.value.current_page)
        await refreshEmbeddedSummary(chatId)
    } catch (exception) {
        fail(exception, 'Avito не принял сообщение.')
    } finally {
        sending.value = false
    }
}

function selectImage() {
    imageInput.value?.click()
}

async function sendImage(event) {
    const image = event.target.files?.[0]
    event.target.value = ''
    if (!image || !selectedChat.value || sending.value) return
    const chatId = selectedChat.value.id
    const follow = atBottom()
    sending.value = true
    try {
        const payload = new FormData()
        payload.append('image', image)
        const { data } = await axios.post(`/api/avito/messenger/chats/${chatId}/messages/image`, payload)
        if (disposed) return
        if (selectedChat.value?.id === chatId) {
            messages.value = uniqueMessages([...messages.value, data.item])
            if (follow && followNewMessages) await scrollToBottom(chatId)
        }
        await loadChats(chatsMeta.value.current_page)
        await refreshEmbeddedSummary(chatId)
    } catch (exception) {
        fail(exception, 'Avito не принял изображение.')
    } finally {
        sending.value = false
    }
}

async function refreshAfterCrmMutation() {
    await loadChats(chatsMeta.value.current_page)
    if (selectedChat.value) await loadChatPage(1, false, { preserve: true })
}

async function refreshMessagesFromCrm() {
    if (selectedChat.value) await loadChatPage(1, false, { preserve: true })
    await loadChats(chatsMeta.value.current_page)
}

async function refreshEmbeddedSummary(chatId) {
    if (!props.embedded || !props.fullFeatured || disposed || selectedChat.value?.id !== chatId) return
    try {
        await store.loadUpdates({ chatIds: new Set([chatId]), messageIds: new Set(), overview: false, chats: false })
        scheduleReadReceipt()
    } catch (exception) {
        fail(exception, 'Изменения сохранены, но не удалось обновить сведения о чате.')
    }
}

function handleContactCandidate(candidate) {
    mobilePane.value = 'details'
    if (candidate.type === 'phone') {
        crmPanel.value?.acceptPhoneCandidate(candidate)
        return
    }

    crmPanel.value?.prepareAddressCandidate(candidate)
}

function openCrmCatalog() {
    mobilePane.value = 'details'
    crmPanel.value?.openCatalog()
}

function openMessageTemplates() {
    mobilePane.value = 'details'
    crmPanel.value?.openTemplates()
}

function openAutoReplies() {
    mobilePane.value = 'details'
    crmPanel.value?.openAutoReplies()
}

async function insertMessageTemplate(payload) {
    mobilePane.value = 'conversation'
    composerText.value = payload.text || ''
    composerTemplateId.value = payload.template_id || null
    composerTemplateName.value = payload.template_name || ''
    await nextTick()
    composerInput.value?.focus()
}

async function handleTemplateSent(message) {
    if (disposed || (message.chat_id && message.chat_id !== selectedChat.value?.id)) return
    const follow = atBottom()
    messages.value = uniqueMessages([...messages.value, message])
    if (follow) await scrollToBottom()
    await loadChats(chatsMeta.value.current_page)
    await refreshEmbeddedSummary(selectedChat.value?.id)
}

function clearComposerTemplate() {
    composerTemplateId.value = null
    composerTemplateName.value = ''
}

async function deleteMessage(message) {
    if (!window.confirm('Удалить сообщение на Avito? Локальная архивная копия останется.')) return
    try {
        const { data } = await axios.delete(`/api/avito/messenger/messages/${message.id}`)
        if (disposed) return
        const index = messages.value.findIndex((item) => item.id === message.id)
        if (index >= 0) messages.value[index] = data.item
        await refreshEmbeddedSummary(message.chat_id || selectedChat.value?.id)
        notify('Сообщение удалено на Avito, архивная копия сохранена.')
    } catch (exception) {
        fail(exception, 'Не удалось удалить сообщение. Avito разрешает удаление только в течение часа после отправки.')
    }
}

async function blacklist(reasonId) {
    if (!selectedChat.value) return
    if (!window.confirm('Добавить собеседника в чёрный список Avito?')) return
    try {
        await axios.post(`/api/avito/messenger/chats/${selectedChat.value.id}/blacklist`, { reason_id: reasonId })
        notify('Собеседник добавлен в чёрный список Avito.')
    } catch (exception) {
        fail(exception, 'Не удалось добавить пользователя в чёрный список.')
    }
}

async function loadSubscriptions() {
    try {
        await store.loadSubscriptions()
    } catch (exception) {
        // A missing Messenger entitlement should not block the chat archive UI.
        subscriptions.value = []
        if (exception?.response?.status !== 403) fail(exception, 'Не удалось получить webhook-подписки.')
    }
}

async function changeSubscription(enabled) {
    try {
        const options = { data: { connection_id: selectedConnectionId.value } }
        if (enabled) {
            await axios.post('/api/avito/messenger/subscriptions', { connection_id: selectedConnectionId.value })
        } else {
            await axios.delete('/api/avito/messenger/subscriptions', options)
        }
        await loadSubscriptions()
        notify(enabled ? 'Webhook Messenger V3 подключён.' : 'Webhook Messenger отключён.')
    } catch (exception) {
        fail(exception, 'Не удалось изменить webhook-подписку.')
    }
}

function messageText(message) {
    if (message.text) return message.text
    if (message.type === 'location') return message.content?.location?.title || 'Геопозиция'
    if (message.type === 'link') return message.content?.link?.text || message.content?.link?.url || 'Ссылка'
    if (message.type === 'item') return message.content?.item?.title || 'Объявление'
    if (message.type === 'call') return 'Звонок'
    if (message.type === 'voice') return 'Голосовое сообщение'
    if (message.type === 'image') return 'Изображение'
    if (message.type === 'system') return 'Системное сообщение'
    return message.type || 'Сообщение'
}

function attachment(message, kind) {
    return message.attachments?.find((item) => item.kind === kind && item.archived)
}

function uniqueMessages(items) {
    return mergeAvitoMessages([], items)
}

function formatDate(value, compact = false) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('ru-RU', compact
        ? { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }
        : { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function formatBytes(value) {
    if (!value) return ''
    return value < 1024 * 1024 ? `${Math.round(value / 1024)} КБ` : `${(value / 1024 / 1024).toFixed(1)} МБ`
}

async function scrollToBottom(chatId = selectedChat.value?.id) {
    await nextTick()
    if (!disposed && selectedChat.value?.id === chatId && messageStream.value) messageStream.value.scrollTop = messageStream.value.scrollHeight
}

function notify(message) {
    if (!disposed) emit('notice', message)
}

function fail(exception, fallback) {
    if (!disposed && exception?.code !== 'ERR_CANCELED') emit('error', exception?.response?.data?.message || fallback)
}

watch(() => [props.autoMarkRead, selectedChat.value?.is_unread, selectedChat.value?.unread_count, mobilePane.value], () => {
    void nextTick(scheduleReadReceipt)
})
onMounted(() => {
    void initialize()
    document.addEventListener('visibilitychange', scheduleReadReceipt)
    window.addEventListener('focus', scheduleReadReceipt)
})
onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    clearTimeout(readTimer)
    document.removeEventListener('visibilitychange', scheduleReadReceipt)
    window.removeEventListener('focus', scheduleReadReceipt)
    disposed = true
    manualRefreshController?.abort()
    streamVersion++
    store.releaseMessenger()
})
</script>

<template>
    <section class="messenger-module" :class="[`mobile-pane-${mobilePane}`, { 'is-embedded': embedded, 'is-full-featured': embedded && fullFeatured }]">
        <header v-if="!embedded" class="messenger-toolbar">
            <div class="messenger-counts"><strong>Всего чатов: {{ overview.counts.chats || 0 }}</strong><span>Непрочитанных чатов: {{ overview.counts.unread_chats || 0 }}</span></div>
            <button type="button" class="realtime-indicator" :class="{ 'is-warning': realtimeStatus.warning }" :title="realtimeHint" :aria-label="realtimeHint" :disabled="refreshingArchive" @click="refreshArchive(true)"><v-icon :icon="realtimeStatus.icon" size="15" /><span class="realtime-label">{{ realtimeStatus.label }}</span><span class="realtime-short-label">{{ realtimeStatus.short }}</span></button>
            <span v-if="runningRun" class="sync-progress" role="status" :title="runningRun.status === 'queued' ? 'Синхронизация ожидает запуска' : `Синхронизация · ${runningRun.messages_seen || 0} сообщений`"><v-progress-circular indeterminate size="13" width="2" /><span class="sync-progress-label">{{ runningRun.status === 'queued' ? 'В очереди' : `Синхронизация · ${runningRun.messages_seen || 0}` }}</span></span>
            <v-spacer />
            <v-select v-model="selectedConnectionId" :items="connectionOptions" label="Источник" density="compact" variant="outlined" hide-details @update:model-value="loadSubscriptions" />
            <v-btn class="archive-refresh" icon="mdi-refresh" size="small" variant="text" :loading="refreshingArchive" title="Перечитать сохранённый архив" aria-label="Перечитать сохранённый архив" @click="refreshArchive()" />
            <v-btn class="archive-sync" size="small" color="deep-purple-lighten-1" :loading="syncing || !!runningRun" title="Синхронизировать сообщения с Avito" aria-label="Синхронизировать сообщения с Avito" @click="queueSync(false)"><v-icon icon="mdi-sync" size="17" /><span class="sync-label">Синхронизировать</span></v-btn>
            <v-menu>
                <template #activator="{ props: menuProps }"><v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="small" variant="text" /></template>
                <v-list density="compact">
                    <v-list-item prepend-icon="mdi-archive-sync-outline" title="Полная архивация" subtitle="До 1100 чатов и сообщений на чат" @click="queueSync(true)" />
                    <v-list-item prepend-icon="mdi-webhook" title="Подключить webhook V3" @click="changeSubscription(true)" />
                    <v-list-item prepend-icon="mdi-webhook-off" title="Отключить webhook" @click="changeSubscription(false)" />
                </v-list>
            </v-menu>
        </header>

        <nav v-if="toolsEnabled" class="mobile-pane-tabs" aria-label="Разделы переписки">
            <button v-if="!embedded" type="button" :aria-pressed="mobilePane === 'chats'" @click="mobilePane = 'chats'"><v-icon icon="mdi-forum-outline" size="16" />Чаты</button>
            <button type="button" :aria-pressed="mobilePane === 'conversation'" @click="mobilePane = 'conversation'"><v-icon icon="mdi-message-text-outline" size="16" />Переписка</button>
            <button type="button" :aria-pressed="mobilePane === 'details'" @click="mobilePane = 'details'"><v-icon icon="mdi-card-account-details-outline" size="16" />Клиент и AI</button>
        </nav>

        <div v-if="loading" class="messenger-loading"><v-progress-circular indeterminate :color="embedded ? 'pink-lighten-2' : 'deep-purple-lighten-2'" /><span>{{ embedded ? 'Открываем переписку…' : 'Открываем архив Avito…' }}</span></div>

        <div v-else class="messenger-layout">
            <aside v-if="!embedded" class="chat-list-pane">
                <div class="chat-filters">
                    <v-text-field v-model="filters.search" prepend-inner-icon="mdi-magnify" placeholder="Чат, клиент или текст сообщения" title="Поиск по данным чата, клиенту и всей сохранённой переписке" density="compact" variant="outlined" hide-details clearable />
                    <v-select v-model="filters.account_id" :items="accountOptions" placeholder="Все аккаунты" density="compact" variant="outlined" hide-details clearable />
                    <v-btn
                        class="unread-filter"
                        :color="filters.unread_only ? 'deep-purple-lighten-2' : undefined"
                        :variant="filters.unread_only ? 'tonal' : 'outlined'"
                        :prepend-icon="filters.unread_only ? 'mdi-filter-check-outline' : 'mdi-message-badge-outline'"
                        :aria-pressed="filters.unread_only"
                        :title="filters.unread_only ? 'Сбросить фильтр непрочитанных чатов' : 'Показать только непрочитанные чаты'"
                        size="small"
                        @click="filters.unread_only = !filters.unread_only"
                    >Непрочитанные чаты <span class="unread-filter__count">{{ overview.counts.unread_chats || 0 }}</span><v-icon v-if="filters.unread_only" icon="mdi-close" size="14" /></v-btn>
                </div>
                <div class="chat-list" :class="{ 'is-loading': chatsLoading }">
                    <button v-for="chat in chats" :key="chat.id" type="button" class="chat-row" :class="{ 'is-active': selectedChat?.id === chat.id, 'is-unread': chat.is_unread }" @click="openChat(chat)">
                        <v-avatar size="34" color="deep-purple-darken-1"><v-img v-if="chat.peer_avatar_url" :src="chat.peer_avatar_url" cover /><span v-else>{{ (chat.peer_name || chat.title || 'A').slice(0, 1).toUpperCase() }}</span></v-avatar>
                        <span class="chat-row__body"><strong>{{ chat.peer_name || chat.title || 'Чат Avito' }}</strong><small>{{ chat.last_message_preview || 'Сообщений пока нет' }}</small><em>{{ chat.title !== chat.peer_name ? chat.title : `ID ${chat.external_chat_id.slice(0, 8)}` }}</em></span>
                        <span class="chat-row__meta"><time>{{ formatDate(chat.last_message_at, true) }}</time><b v-if="chat.is_unread" :title="chat.unread_count ? `Непрочитанных сообщений: ${chat.unread_count}` : 'Есть непрочитанные сообщения'" :aria-label="chat.unread_count ? `Непрочитанных сообщений: ${chat.unread_count}` : 'Есть непрочитанные сообщения'">{{ chat.unread_count || '•' }}</b><v-icon v-if="chat.waiting_since" class="waiting-indicator" icon="mdi-clock-outline" size="14" :title="`В листе ожидания с ${formatDate(chat.waiting_since)}`" aria-label="В листе ожидания" /><v-icon v-if="chat.entity" icon="mdi-account-check-outline" size="11" color="green-lighten-1" :title="chat.entity.name" /><i>{{ chat.chat_type || 'u2i' }}</i></span>
                    </button>
                    <div v-if="!chats.length && !chatsLoading" class="pane-empty">
                        <v-icon :icon="filters.unread_only ? 'mdi-message-check-outline' : 'mdi-forum-remove-outline'" size="36" />
                        <strong>{{ filters.unread_only ? 'Непрочитанных чатов нет' : (filters.search || filters.account_id ? 'Чаты не найдены' : 'Архив пока пуст') }}</strong>
                        <span>{{ filters.unread_only || filters.search || filters.account_id ? 'Попробуйте изменить поиск или фильтры.' : 'Запустите синхронизацию — чаты и сообщения сохранятся на сервере.' }}</span>
                        <v-btn v-if="filters.unread_only" size="small" variant="text" @click="filters.unread_only = false">Показать все чаты</v-btn>
                    </div>
                </div>
                <v-pagination v-if="chatsMeta.last_page > 1" v-model="chatsMeta.current_page" :length="chatsMeta.last_page" density="compact" total-visible="4" @update:model-value="loadChats" />
            </aside>

            <main class="conversation-pane">
                <template v-if="selectedChat">
                    <header class="conversation-header">
                        <div><strong>{{ selectedChat.peer_name || selectedChat.title }}</strong><span v-if="embedded">{{ selectedChat.title || 'Переписка Avito' }} · {{ selectedChat.messages_count || messagesMeta.total || 0 }} сообщений</span><span v-else>{{ selectedChat.entity?.name || 'Entity не связана' }} · {{ selectedChat.title }} · {{ selectedChat.messages_count || messagesMeta.total || 0 }} сообщений · архив {{ formatDate(selectedChat.last_synced_at) }}</span></div>
                        <button v-if="embedded && fullFeatured" type="button" class="realtime-indicator" :class="{ 'is-warning': realtimeStatus.warning }" :title="realtimeHint" :aria-label="realtimeHint" :disabled="refreshingArchive" @click="refreshArchive(true)"><v-icon :icon="realtimeStatus.icon" size="15" /></button>
                        <v-btn
                            class="waiting-toggle"
                            :icon="selectedChat.waiting_since ? 'mdi-clock-check-outline' : 'mdi-clock-plus-outline'"
                            :variant="selectedChat.waiting_since ? 'tonal' : 'text'"
                            :color="selectedChat.waiting_since ? 'pink-lighten-2' : undefined"
                            size="small"
                            :loading="waitingSaving === selectedChat.id"
                            :disabled="waitingSaving !== null"
                            :aria-pressed="Boolean(selectedChat.waiting_since)"
                            :title="selectedChat.waiting_since ? 'Убрать из листа ожидания' : 'В лист ожидания — ответить позже'"
                            :aria-label="selectedChat.waiting_since ? 'Убрать из листа ожидания' : 'В лист ожидания — ответить позже'"
                            @click="toggleWaiting"
                        />
                        <v-btn icon="mdi-refresh" size="small" variant="text" :loading="chatLoading" title="Обновить из Avito" aria-label="Обновить из Avito" @click="refreshSelectedChat" />
                        <v-btn icon="mdi-check-all" size="small" variant="text" title="Отметить прочитанным" aria-label="Отметить прочитанным" @click="markRead()" />
                        <v-menu v-if="toolsEnabled">
                            <template #activator="{ props: menuProps }"><v-btn v-bind="menuProps" icon="mdi-account-cancel-outline" color="error" size="small" variant="text" /></template>
                            <v-list density="compact"><v-list-subheader>Причина блокировки</v-list-subheader><v-list-item v-for="reason in [{ id: 1, title: 'Спам' }, { id: 2, title: 'Мошенничество' }, { id: 3, title: 'Оскорбления' }, { id: 4, title: 'Другая' }]" :key="reason.id" :title="reason.title" @click="blacklist(reason.id)" /></v-list>
                        </v-menu>
                    </header>

                    <div ref="messageStream" class="message-stream" @scroll.passive="trackStreamScroll">
                        <v-btn v-if="messagesMeta.current_page < messagesMeta.last_page" class="older-button" size="x-small" variant="tonal" :loading="chatLoading" @click="loadOlderMessages">Загрузить более ранние</v-btn>
                        <article v-for="message in messages" :key="message.id" class="message-bubble" :class="[`is-${message.direction || 'in'}`, { 'is-deleted': message.remote_type === 'deleted' }]">
                            <div v-if="attachment(message, 'image')" class="message-image"><img :src="attachment(message, 'image').url" alt="Изображение из архива Avito" loading="lazy"></div>
                            <audio v-if="attachment(message, 'voice')" :src="attachment(message, 'voice').url" controls preload="none" />
                            <p v-if="message.type !== 'image' || !attachment(message, 'image')">{{ messageText(message) }}</p>
                            <div v-if="toolsEnabled && message.contact_candidates?.length" class="message-candidates">
                                <button v-for="candidate in message.contact_candidates" :key="candidate.id" type="button" :class="`is-${candidate.type}`" @click="handleContactCandidate(candidate)">
                                    <v-icon :icon="candidate.type === 'phone' ? 'mdi-phone-plus-outline' : 'mdi-map-marker-plus-outline'" size="11" />
                                    {{ candidate.type === 'phone' ? candidate.normalized_value : 'Сохранить адрес' }}
                                </button>
                            </div>
                            <div v-if="message.remote_type === 'deleted'" class="archive-marker"><v-icon icon="mdi-archive-lock-outline" size="12" />Удалено на Avito · копия сохранена</div>
                            <footer><span>{{ message.type }}</span><time>{{ formatDate(message.remote_created_at) }}</time><v-icon v-if="message.direction === 'out'" :icon="message.is_read ? 'mdi-check-all' : 'mdi-check'" size="13" /><v-btn v-if="message.direction === 'out' && message.remote_type !== 'deleted'" icon="mdi-delete-outline" color="error" size="x-small" variant="text" @click="deleteMessage(message)" /></footer>
                        </article>
                        <div v-if="chatLoading && !messages.length" class="pane-empty"><v-progress-circular indeterminate size="28" /></div>
                        <div v-if="!chatLoading && !messages.length" class="pane-empty"><v-icon icon="mdi-message-outline" size="36" /><span>В архиве этого чата сообщений пока нет.</span></div>
                    </div>

                    <footer class="composer">
                        <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/gif" hidden @change="sendImage">
                        <v-btn v-if="toolsEnabled" icon="mdi-package-variant-closed-plus" size="small" variant="text" :disabled="sending" title="Выбрать товар из Пищепром-Сервера" aria-label="Выбрать товар" @click="openCrmCatalog" />
                        <v-btn v-if="toolsEnabled" icon="mdi-text-box-multiple-outline" size="small" variant="text" :disabled="sending" title="Шаблоны сообщений" aria-label="Шаблоны сообщений" @click="openMessageTemplates" />
                        <v-btn v-if="toolsEnabled" icon="mdi-robot-outline" size="small" variant="text" :disabled="sending" title="Автоответы и безопасная проверка" aria-label="Автоответы" @click="openAutoReplies" />
                        <v-btn icon="mdi-image-plus-outline" size="small" variant="text" :disabled="sending" title="Отправить изображение" aria-label="Отправить изображение" @click="selectImage" />
                        <v-textarea ref="composerInput" v-model="composerText" :placeholder="composerTemplateName ? `Шаблон: ${composerTemplateName}` : 'Сообщение до 1000 символов'" rows="1" max-rows="4" auto-grow density="compact" variant="solo-filled" hide-details maxlength="1000" @keydown.ctrl.enter.prevent="sendText" />
                        <span :title="composerTemplateName ? `Используется шаблон «${composerTemplateName}»` : ''">{{ composerText.length }}/1000<b v-if="composerTemplateId">Ш</b></span>
                        <v-btn icon="mdi-send" :color="embedded ? 'pink-darken-1' : 'deep-purple-lighten-1'" size="small" :loading="sending" :disabled="!canSend" title="Отправить сообщение (Ctrl+Enter)" aria-label="Отправить сообщение" @click="sendText" />
                    </footer>
                </template>
                <div v-else class="conversation-empty"><v-icon icon="mdi-message-text-outline" size="48" /><strong>Выберите переписку</strong><span>{{ embedded ? 'Выберите чат в листе ожидания, чтобы прочитать сообщения и ответить.' : 'Здесь доступны отправка текста и изображений, удаление, прочтение и блокировка.' }}</span></div>
            </main>

            <AvitoCrmPanel
                v-if="selectedChat && toolsEnabled"
                ref="crmPanel"
                class="messenger-details-pane"
                :chat="selectedChat"
                @notice="notify"
                @error="(message) => emit('error', message)"
                @chat-updated="refreshAfterCrmMutation"
                @refresh-messages="refreshMessagesFromCrm"
                @insert-template="insertMessageTemplate"
                @template-sent="handleTemplateSent"
            />

            <aside v-else-if="!embedded" class="messenger-info-pane messenger-details-pane">
                <section><span class="info-eyebrow">Локальный архив</span><dl><dt>Аккаунтов</dt><dd>{{ overview.counts.accounts || 0 }}</dd><dt>Чатов</dt><dd>{{ overview.counts.chats || 0 }}</dd><dt>Сообщений</dt><dd>{{ overview.counts.messages || 0 }}</dd><dt>Вложений</dt><dd>{{ overview.counts.attachments || 0 }}</dd></dl></section>
                <section><span class="info-eyebrow">Realtime</span><strong>{{ subscriptions.length ? 'Webhook V3 активен' : 'Webhook не найден' }}</strong><small>{{ subscriptions.length ? `${subscriptions.length} подписок Avito` : 'Плановая синхронизация выполняется каждые 5 минут' }}</small><div><v-btn v-if="!subscriptions.length" size="x-small" variant="tonal" @click="changeSubscription(true)">Подключить</v-btn><v-btn v-else size="x-small" color="error" variant="text" @click="changeSubscription(false)">Отключить</v-btn></div></section>
                <section><span class="info-eyebrow">Возможности Avito</span><small>API разрешает создать и удалить сообщение, но не предоставляет редактирование уже отправленного текста. Удаление доступно не позднее часа.</small></section>
                <v-expansion-panels variant="accordion" class="tools-panel"><v-expansion-panel><v-expansion-panel-title>13 инструментов API</v-expansion-panel-title><v-expansion-panel-text><a v-for="tool in overview.tools" :key="tool.id" :href="tool.documentation_url" target="_blank" rel="noopener noreferrer"><span>{{ tool.method }}</span>{{ tool.summary }}</a></v-expansion-panel-text></v-expansion-panel></v-expansion-panels>
            </aside>
        </div>
    </section>
</template>

<style scoped>
.messenger-module { display: flex; flex-direction: column; overflow: hidden; width: 100%; height: 100%; min-height: 0; color: #e9ebff; border: 1px solid #30344d; border-radius: 10px; background: #111427; }
.messenger-toolbar { display: flex; flex: 0 0 auto; min-height: 48px; align-items: center; gap: 9px; padding: 8px 12px; border-bottom: 1px solid #30344d; background: #1b1e35; }
.messenger-toolbar > .v-select { max-width: 230px; }
.messenger-counts { display: flex; flex-direction: column; gap: 2px; white-space: nowrap; }.messenger-counts strong { font-size: 12px; }.messenger-counts span { color: #9299b9; font-size: 10px; }
.realtime-indicator { display: inline-flex; min-width: 0; flex: 0 0 auto; align-items: center; gap: 4px; padding: 4px 5px; color: #91dbbd; font-size: 10px; border: 1px solid #335449; border-radius: 5px; background: #1a302b; white-space: nowrap; cursor: pointer; }.realtime-indicator.is-warning { color: #dfbc81; border-color: #645235; background: #352d23; }.realtime-indicator:disabled { opacity: .65; cursor: wait; }.realtime-short-label { display: none; }.archive-sync :deep(.v-btn__content) { gap: 6px; }
.sync-progress { display: inline-flex; min-width: 0; align-items: center; gap: 5px; overflow: hidden; color: #bdacf3; font-size: 10px; white-space: nowrap; text-overflow: ellipsis; }
.messenger-loading { display: flex; flex: 1; min-height: 0; align-items: center; justify-content: center; gap: 12px; color: #9da3c3; }
.messenger-layout { display: grid; flex: 1; grid-template-columns: minmax(250px, 25fr) minmax(0, 40fr) minmax(315px, 35fr); min-height: 0; overflow: hidden; }
.mobile-pane-tabs { display: none; }
.chat-list-pane, .conversation-pane, .messenger-info-pane { min-width: 0; min-height: 0; }
.chat-list-pane { display: flex; flex-direction: column; border-right: 1px solid #30344d; background: #15182b; }
.chat-filters { display: grid; width: 100%; grid-template-columns: minmax(0, 1fr); gap: 6px; padding: 8px; border-bottom: 1px solid #2c3048; }.chat-filters > .v-input { min-width: 0; width: 100%; justify-self: stretch; }.chat-filters :deep(.v-input--horizontal) { grid-template-areas: 'control' 'messages'; grid-template-columns: minmax(0, 1fr); }
.unread-filter { justify-content: flex-start; min-width: 0; letter-spacing: normal; text-transform: none; }.unread-filter :deep(.v-btn__content) { flex: 1; gap: 6px; }.unread-filter__count { margin-left: auto; padding: 1px 6px; border-radius: 10px; background: rgba(147, 120, 255, .18); font-variant-numeric: tabular-nums; }.unread-filter[aria-pressed="true"] { box-shadow: inset 3px 0 #a38bef; }
.chat-list { min-height: 0; overflow-y: auto; overscroll-behavior: contain; flex: 1; transition: opacity .15s; }.chat-list.is-loading { opacity: .55; }
.chat-row { display: grid; width: 100%; grid-template-columns: 34px minmax(0, 1fr) auto; align-items: start; gap: 8px; padding: 10px 9px; color: #e9ebff; text-align: left; border: 0; border-bottom: 1px solid #292d45; background: transparent; cursor: pointer; }.chat-row:hover { background: #1d2038; }.chat-row.is-active { box-shadow: inset 3px 0 #9378ff; background: #24213f; }.chat-row.is-unread .chat-row__body strong { color: #fff; }
.chat-row__body { min-width: 0; }.chat-row__body strong, .chat-row__body small, .chat-row__body em { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.chat-row__body strong { font-size: 12px; }.chat-row__body small { margin-top: 3px; color: #9ca2c1; font-size: 11px; }.chat-row__body em { margin-top: 3px; color: #6f7596; font-size: 9px; font-style: normal; }
.chat-row__meta { display: grid; justify-items: end; gap: 4px; }.chat-row__meta time { color: #747b9e; font-size: 8px; }.chat-row__meta b { display: grid; min-width: 18px; height: 18px; place-items: center; color: #fff; font-size: 9px; border-radius: 20px; background: #7957e8; }.chat-row__meta i { color: #6f7596; font-size: 8px; font-style: normal; text-transform: uppercase; }
.waiting-indicator, .waiting-toggle { color: #ef9bbb; }
.conversation-pane { display: flex; flex-direction: column; background: radial-gradient(circle at 50% 0, rgba(100, 70, 190, .08), transparent 45%), #101324; }
.conversation-header { display: flex; flex: 0 0 auto; min-height: 50px; align-items: center; gap: 3px; padding: 8px 12px; border-bottom: 1px solid #30344d; background: #1a1d33; }.conversation-header > div:first-child { min-width: 0; flex: 1; }.conversation-header strong, .conversation-header span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.conversation-header strong { font-size: 13px; }.conversation-header span { margin-top: 3px; color: #858baa; font-size: 10px; }
.message-stream { display: flex; min-height: 0; overflow-y: auto; overscroll-behavior: contain; overflow-anchor: none; flex: 1; flex-direction: column; gap: 5px; padding: 12px 14px; }.older-button { align-self: center; margin: 3px 0 9px; }
.message-bubble { flex: 0 0 auto; width: fit-content; max-width: min(76%, 680px); padding: 7px 9px 4px; border: 1px solid #343951; border-radius: 11px 11px 11px 3px; background: #20243b; box-shadow: 0 4px 12px rgba(0, 0, 0, .12); }.message-bubble.is-out { align-self: flex-end; border-color: rgba(132, 103, 239, .38); border-radius: 11px 11px 3px; background: #392d62; }.message-bubble.is-deleted { border-style: dashed; opacity: .82; }.message-bubble p { margin: 0; color: #f0f1ff; font-size: 12px; line-height: 1.38; white-space: pre-wrap; word-break: break-word; }.message-bubble audio { width: 260px; max-width: 100%; height: 34px; }.message-image { overflow: hidden; max-width: 360px; margin: -3px -5px 4px; border-radius: 7px; }.message-image img { display: block; width: 100%; max-height: 320px; object-fit: contain; background: #0d1020; }.message-bubble footer { display: flex; align-items: center; justify-content: flex-end; gap: 4px; margin-top: 3px; color: #8f95b5; font-size: 8px; }.message-bubble footer span { margin-right: auto; text-transform: uppercase; }.archive-marker { display: flex; align-items: center; gap: 3px; margin-top: 5px; color: #d2a4ae; font-size: 8px; }
.message-candidates { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 5px; }.message-candidates button { display: flex; align-items: center; gap: 3px; padding: 2px 5px; color: #b7ead4; font-size: 7px; border: 1px solid rgba(90, 205, 154, .28); border-radius: 10px; background: rgba(34, 105, 75, .22); cursor: pointer; }.message-candidates button.is-address { color: #b9dff0; border-color: rgba(83, 175, 216, .28); background: rgba(33, 91, 119, .22); }
.composer { display: grid; flex: 0 0 auto; grid-template-columns: repeat(4, auto) minmax(0, 1fr) auto; align-items: center; gap: 5px; padding: 7px 10px; border-top: 1px solid #30344d; background: #1a1d33; }.composer > .v-textarea { grid-column: 1 / -1; grid-row: 1; min-width: 0; }.composer > .v-btn, .composer > span { grid-row: 2; }.composer > span { display: flex; justify-content: flex-end; align-items: center; gap: 3px; color: #737999; font-size: 8px; }.composer > span b { display: grid; width: 13px; height: 13px; place-items: center; color: #d9d0ff; font-size: 7px; border-radius: 10px; background: #654eb5; }.composer :deep(textarea) { font-size: 12px; line-height: 1.35; }
.conversation-empty, .pane-empty { display: grid; place-items: center; align-content: center; gap: 7px; color: #858baa; text-align: center; }.conversation-empty { flex: 1; }.conversation-empty strong, .pane-empty strong { color: #dfe2f8; }.conversation-empty span, .pane-empty span { max-width: 300px; font-size: 11px; }.pane-empty { min-height: 220px; padding: 20px; }
.messenger-info-pane { overflow-y: auto; padding: 9px; border-left: 1px solid #30344d; background: #15182b; }.messenger-info-pane section { margin-bottom: 8px; padding: 10px; border: 1px solid #2f334c; border-radius: 8px; background: #1b1e35; }.info-eyebrow { display: block; margin-bottom: 8px; color: #9d88f4; font-size: 8px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }.messenger-info-pane dl { display: grid; grid-template-columns: 1fr auto; gap: 5px 7px; margin: 0; font-size: 10px; }.messenger-info-pane dt { color: #858baa; }.messenger-info-pane dd { overflow: hidden; max-width: 125px; margin: 0; color: #e5e7fa; text-overflow: ellipsis; white-space: nowrap; }.messenger-info-pane section > strong, .messenger-info-pane section > small { display: block; }.messenger-info-pane section > strong { font-size: 11px; }.messenger-info-pane section > small { margin: 4px 0 8px; color: #858baa; font-size: 9px; line-height: 1.4; }.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 8px; }
.tools-panel :deep(.v-expansion-panel) { color: #dfe2f8; background: #1b1e35; }.tools-panel :deep(.v-expansion-panel-title) { min-height: 38px; padding: 8px 10px; font-size: 10px; }.tools-panel :deep(.v-expansion-panel-text__wrapper) { display: grid; gap: 4px; padding: 4px 8px 10px; }.tools-panel a { display: grid; grid-template-columns: 32px 1fr; gap: 4px; color: #bec4e3; font-size: 8px; text-decoration: none; }.tools-panel a span { color: #9c85f5; font-weight: 800; }
@media (max-width: 1250px) { .realtime-label { display: none; }.realtime-short-label { display: inline; }.composer { gap: 3px; padding: 5px; }.composer > .v-btn { width: 26px; height: 30px; }.composer :deep(.v-field__input) { padding-inline: 7px; }.message-stream { padding: 9px; } }
@media (max-width: 1000px) {
    .messenger-toolbar { gap: 5px; padding: 5px 7px; }.messenger-counts { display: none; }.messenger-toolbar > .v-select { min-width: 0; max-width: 210px; }.messenger-toolbar > .v-spacer { display: none; }.messenger-toolbar > .v-btn { flex: 0 0 auto; font-size: 10px; }.sync-progress { max-width: 100px; font-size: 9px; }
    .mobile-pane-tabs { display: flex; flex: 0 0 34px; border-bottom: 1px solid #30344d; background: #191c31; }.mobile-pane-tabs button { display: flex; min-width: 0; flex: 1; align-items: center; justify-content: center; gap: 5px; color: #9299b9; font-size: 11px; border-bottom: 2px solid transparent; }.mobile-pane-tabs button[aria-pressed="true"] { color: #d2c7ff; border-bottom-color: #a38bef; background: #26203e; }
    .messenger-layout { display: flex; }.messenger-layout > * { display: none; width: 100%; min-height: 0; height: 100%; border: 0; }.mobile-pane-chats .chat-list-pane, .mobile-pane-conversation .conversation-pane, .mobile-pane-details .messenger-details-pane { display: flex; }.mobile-pane-details .messenger-info-pane { display: block; }
    .message-bubble { max-width: 88%; }.conversation-header { padding: 6px; }.conversation-header strong { font-size: 12px; }.conversation-header span { font-size: 9px; }
}
@media (max-width: 600px) { .messenger-toolbar > .archive-sync { min-width: 32px; width: 32px; padding: 0; }.sync-label { display: none; }.messenger-toolbar > .archive-refresh { width: 32px; height: 32px; } }
@media (max-width: 480px) { .messenger-toolbar > .v-select { max-width: 128px; }.sync-progress { flex: 0 0 13px; }.sync-progress-label { display: none; }.realtime-indicator { max-width: 86px; font-size: 9px; }.realtime-short-label { overflow: hidden; text-overflow: ellipsis; }.messenger-toolbar > .v-btn { padding-inline: 7px; } }
.messenger-module.is-embedded { height: 420px; min-height: 320px; color: #f1edf0; border-color: #51434b; border-radius: 7px; background: #25262b; }
.is-embedded .messenger-layout { display: flex; }
.is-embedded .conversation-pane { display: flex; width: 100%; height: 100%; background: #25262b; }
.is-embedded .conversation-header { min-height: 43px; padding: 5px 7px; border-color: #4a4047; background: #303036; }
.is-embedded .conversation-header strong { font-size: 12px; }
.is-embedded .conversation-header span { color: #b3a7af; font-size: 9px; }
.is-embedded .conversation-header > .v-btn { flex-shrink: 0; width: 30px; height: 30px; }
.is-embedded .message-stream { gap: 5px; padding: 9px; }
.is-embedded .message-bubble { max-width: 88%; border-color: #49454c; background: #35353c; }
.is-embedded .message-bubble.is-out { border-color: #8c526b; background: #593c4a; }
.is-embedded .message-bubble p { color: #f7f0f4; }
.is-embedded .message-bubble footer { color: #c1aeba; }
.is-embedded .composer { grid-template-columns: auto minmax(0, 1fr) auto; gap: 4px; padding: 6px; border-color: #4a4047; background: #303036; }
.is-embedded .composer > span { color: #b3a7af; }
.is-embedded .composer :deep(.v-field) { background: #3b373e; }
.is-embedded .composer :deep(textarea) { color: #f7f0f4; caret-color: #ef9bbb; }
.is-embedded .composer :deep(textarea::placeholder) { color: #c1aeba; opacity: 1; }
.is-embedded .messenger-loading, .is-embedded .conversation-empty, .is-embedded .pane-empty { color: #b7a7b1; }
.is-embedded .conversation-empty strong { color: #ecc5d6; }
.messenger-module.is-full-featured { height: 100%; min-height: 0; border: 0; border-radius: 0; }
.is-full-featured .messenger-layout { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(330px, 1fr); }
.is-full-featured .composer { grid-template-columns: repeat(4, auto) minmax(0, 1fr) auto; }
@media (max-width: 1000px) {
    .is-full-featured .messenger-layout { display: flex; }
    .is-full-featured.mobile-pane-details .conversation-pane { display: none; }
}
</style>
