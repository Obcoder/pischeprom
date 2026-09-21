import axios from 'axios'
import { defineStore } from 'pinia'
import { onScopeDispose, reactive, ref } from 'vue'
import { connectCommerceSocket } from '../Services/commerceSocket.js'
import { createRealtimeCoordinator } from '../Services/realtimeCoordinator.js'

export const AVITO_TOPICS = ['avito_messages', 'avito_auto_replies']

export function mergeAvitoMessages(existing, incoming) {
    return [...new Map([...existing, ...incoming].map((item) => [item.id, item])).values()]
        .sort((a, b) => new Date(a.remote_created_at || 0) - new Date(b.remote_created_at || 0) || a.id - b.id)
}

export function collectAvitoChanges(events) {
    if (!events?.length || events.some((event) => !event.changes)) return null
    const chatIds = new Set()
    const messageIds = new Set()
    let overview = false
    let chats = false
    for (const { changes } of events) {
        for (const id of changes.chat_ids || []) chatIds.add(id)
        for (const id of changes.message_ids || []) messageIds.add(id)
        overview ||= changes.overview === true
        chats ||= changes.chats === true
    }
    if (chatIds.size > 200 || messageIds.size > 200) return null
    return { chatIds, messageIds, overview, chats }
}

export const useAvitoStore = defineStore('avito', () => {
    const overview = ref({ counts: {}, accounts: [], latest_runs: [], tools: [] })
    const chats = ref([])
    const chatsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
    const selectedChat = ref(null)
    const messages = ref([])
    const messagesMeta = ref({ current_page: 1, last_page: 1, total: 0 })
    const subscriptions = ref([])
    const selectedConnectionId = ref(null)
    const activeRun = ref(null)
    const filters = reactive({ search: '', account_id: null, unread_only: false, chat_type: null })
    const composerText = ref('')
    const composerTemplateId = ref(null)
    const composerTemplateName = ref('')
    const loading = ref(true)
    const chatsLoading = ref(false)
    const chatLoading = ref(false)
    const sending = ref(false)
    const syncing = ref(false)
    const status = ref('disabled')
    const refreshErrors = reactive({})
    const controlSettings = ref(null)
    const controlLoading = ref(false)
    const stopLoading = ref(false)
    const controlVersion = ref(0)
    const requests = new Map()
    const readReceipts = new Map()
    const chatReceiptVersions = new Map()
    const browser = typeof window !== 'undefined' && typeof document !== 'undefined'
    let actor = null
    let nextId = 0
    let controlConsumers = 0
    let stopControl = () => {}
    let receiptVersion = 0
    let selectionVersion = 0
    const coordinator = createRealtimeCoordinator({
        connect: connectCommerceSocket,
        allowedTopics: AVITO_TOPICS,
        isVisible: () => browser && document.visibilityState !== 'hidden',
        isOnline: () => browser && navigator.onLine !== false,
        onStatus: (value) => { status.value = value },
        onRefreshed: (id) => { delete refreshErrors[id] },
        onError: (id) => { refreshErrors[id] = true },
        debounceMs: 250,
        minRefreshIntervalMs: 2000,
    })

    // Both cancellation and identity checks are needed: a response can finish while a chat changes.
    function cancelRequest(key) {
        requests.get(key)?.abort()
        requests.delete(key)
    }

    async function read(key, url, options, apply, fetch = (path, config) => axios.get(path, config)) {
        cancelRequest(key)
        const version = receiptVersion
        const receiptChatId = selectedChat.value?.id
        const chatVersion = chatReceiptVersions.get(receiptChatId) || 0
        const controller = new AbortController()
        const external = options?.signal
        const abort = () => controller.abort()
        if (external?.aborted) controller.abort()
        else external?.addEventListener('abort', abort, { once: true })
        requests.set(key, controller)
        try {
            const { data } = await fetch(url, { ...options, signal: controller.signal })
            if (requests.get(key) !== controller || controller.signal.aborted) return null
            if (['overview', 'chats'].includes(key) && version !== receiptVersion) return null
            if (['chat', 'older'].includes(key) && chatVersion !== (chatReceiptVersions.get(receiptChatId) || 0)) return null
            apply(data)
            return data
        } catch (error) {
            if (controller.signal.aborted || error?.code === 'ERR_CANCELED') return null
            throw error
        } finally {
            external?.removeEventListener('abort', abort)
            if (requests.get(key) === controller) {
                requests.delete(key)
                if (key === 'chats') chatsLoading.value = false
                if (key === 'chat' || key === 'older') chatLoading.value = requests.has('chat') || requests.has('older')
                if (key === 'control') controlLoading.value = false
            }
        }
    }

    function loadOverview(options = {}) {
        return read('overview', '/api/avito/messenger/overview', options, (data) => {
            applyOverview(data)
        })
    }

    function applyOverview(data) {
        overview.value = { ...overview.value, ...data }
        activeRun.value = data.latest_runs?.find((run) => ['queued', 'running'].includes(run.status)) || null
    }

    function applyChats(data, syncSelected = true) {
        chats.value = data.data || []
        chatsMeta.value = data
        const current = chats.value.find((chat) => chat.id === selectedChat.value?.id)
        if (current && syncSelected) selectedChat.value = { ...selectedChat.value, ...current }
    }

    function chatFilters(page = chatsMeta.value.current_page) {
        return { page, per_page: 50, search: filters.search || undefined,
            account_id: filters.account_id || undefined, unread_only: filters.unread_only ? 1 : undefined,
            chat_type: filters.chat_type || undefined }
    }

    function invalidateChats() {
        cancelRequest('chats')
        chatsLoading.value = false
    }

    async function loadChats(page = chatsMeta.value.current_page, options = {}) {
        chatsLoading.value = true
        const selection = selectionVersion
        const data = await read('chats', '/api/avito/messenger/chats', {
            ...options,
            params: chatFilters(page),
        }, (data) => applyChats(data, selection === selectionVersion))
        if (data && data.current_page > data.last_page) return loadChats(Math.max(1, data.last_page), options)
        return data
    }

    async function loadUpdates(changes, options = {}) {
        const chatId = selectedChat.value?.id
        const selected = Boolean(chatId && changes.chatIds.has(chatId))
        const knownMessageIds = new Set(messages.value.map((message) => message.id))
        const version = receiptVersion
        const chatVersion = chatReceiptVersions.get(chatId) || 0
        const selection = selectionVersion
        const params = chatFilters()
        const filterKey = JSON.stringify(params)
        const data = await read('updates', '/api/avito/messenger/updates', {
            ...options,
            params: { ...params, overview: changes.overview ? 1 : 0, chats: changes.chats ? 1 : 0,
                selected: selected ? 1 : 0, selected_chat_id: selected ? chatId : undefined,
                after_message_id: selected ? Math.max(0, ...knownMessageIds) : undefined,
                message_ids: selected ? [...changes.messageIds] : undefined },
        }, (data) => {
            if (data.overview && version === receiptVersion) applyOverview(data.overview)
            if (data.chats && version === receiptVersion && filterKey === JSON.stringify(chatFilters())) {
                applyChats(data.chats, selection === selectionVersion)
            }
            if (data.selected?.chat && selectedChat.value?.id === chatId
                && selection === selectionVersion
                && chatVersion === (chatReceiptVersions.get(chatId) || 0)) {
                selectedChat.value = data.selected.chat
                const missing = new Set(data.selected.missing_message_ids || [])
                messages.value = mergeAvitoMessages(messages.value.filter((message) => !missing.has(message.id)), data.selected.messages || [])
                if (!data.selected.chat.is_unread) {
                    messages.value.forEach((message) => {
                        if (message.direction === 'in' && knownMessageIds.has(message.id)) message.is_read = true
                    })
                }
                messagesMeta.value.total = data.selected.chat.messages_count ?? messagesMeta.value.total
                messagesMeta.value.last_page = Math.max(1, Math.ceil(messagesMeta.value.total / (messagesMeta.value.per_page || 100)))
            }
        })
        if (data?.chats && filterKey === JSON.stringify(chatFilters()) && data.chats.current_page > data.chats.last_page) {
            await loadChats(Math.max(1, data.chats.last_page), options)
        }
        if (data?.selected?.has_more && selectedChat.value?.id === chatId && selection === selectionVersion) await refreshChat(options)
        return data
    }

    function applyReadReceipt(chatId, data) {
        const wasListed = chats.value.some((chat) => chat.id === chatId)
        const previous = chats.value.find((chat) => chat.id === chatId)
            || (selectedChat.value?.id === chatId ? selectedChat.value : null)
        let chat = data.chat
        if (!chat) return
        receiptVersion++
        chatReceiptVersions.set(chatId, (chatReceiptVersions.get(chatId) || 0) + 1)
        if (selectedChat.value?.id === chatId) {
            const incoming = messages.value.filter((message) => message.direction === 'in' && !message.is_read
                && message.id > data.read_through_id && !['deleted', 'system'].includes(message.remote_type))
            if (incoming.length) {
                const newerPreview = previous?.last_message_at && new Date(previous.last_message_at) > new Date(chat.last_message_at || 0)
                chat = { ...chat, ...(newerPreview ? previous : {}),
                    is_unread: true, unread_count: Math.max(chat.unread_count || 0, incoming.length) }
            }
        }
        if (previous) {
            const unreadDelta = Number(Boolean(chat.is_unread)) - Number(Boolean(previous.is_unread))
            overview.value.counts.unread_chats = Math.max(0, (overview.value.counts.unread_chats || 0) + unreadDelta)
            if (overview.value.counts.unread_messages !== undefined) {
                overview.value.counts.unread_messages = Math.max(0, overview.value.counts.unread_messages + (chat.unread_count || 0) - (previous.unread_count || 0))
            }
            const account = overview.value.accounts.find((item) => item.id === chat.account_id)
            if (account) account.unread_chats_count = Math.max(0, (account.unread_chats_count || 0) + unreadDelta)
        }
        chats.value = chats.value.map((item) => item.id === chatId ? { ...item, ...chat } : item)
        if (filters.unread_only && !chat.is_unread) {
            chats.value = chats.value.filter((item) => item.id !== chatId)
            if (wasListed) chatsMeta.value.total = Math.max(0, (chatsMeta.value.total || 0) - 1)
        }
        if (selectedChat.value?.id === chatId) {
            selectedChat.value = { ...selectedChat.value, ...chat }
            messages.value.forEach((message) => {
                if (message.direction === 'in' && message.id <= data.read_through_id) message.is_read = true
            })
        }
    }

    async function markRead(chatId, throughMessageId) {
        if (readReceipts.has(chatId)) return readReceipts.get(chatId)
        const currentActor = actor
        const request = axios.post(`/api/avito/messenger/chats/${chatId}/read`, {
            through_message_id: throughMessageId || undefined,
        }).then(({ data }) => {
            if (currentActor === actor) applyReadReceipt(chatId, data)
            return data
        }).finally(() => { if (readReceipts.get(chatId) === request) readReceipts.delete(chatId) })
        readReceipts.set(chatId, request)
        return request
    }

    function selectChat(chat) {
        selectionVersion++
        cancelRequest('chat')
        cancelRequest('older')
        selectedChat.value = chat
        messages.value = []
        messagesMeta.value = { current_page: 1, last_page: 1, total: 0 }
        composerText.value = ''
        composerTemplateId.value = null
        composerTemplateName.value = ''
        chatLoading.value = false
    }

    function loadChatPage(page = 1, { prepend = false, preserve = false, signal } = {}) {
        const chatId = selectedChat.value?.id
        if (!chatId) return Promise.resolve(null)
        chatLoading.value = true
        return read(prepend ? 'older' : 'chat', `/api/avito/messenger/chats/${chatId}`, {
            params: { page, per_page: 100 }, signal,
        }, (data) => {
            if (selectedChat.value?.id !== chatId) return
            selectedChat.value = data.chat
            messages.value = mergeAvitoMessages(prepend || preserve ? messages.value : [], data.messages?.data || [])
            const previousPage = messagesMeta.value.current_page
            messagesMeta.value = data.messages || messagesMeta.value
            if (preserve && !prepend) messagesMeta.value.current_page = previousPage
        })
    }

    function refreshChat(options = {}) {
        const chatId = selectedChat.value?.id
        if (!chatId) return Promise.resolve(null)
        const oldestId = messages.value[0]?.id
        chatLoading.value = true
        return read('chat', `/api/avito/messenger/chats/${chatId}`, options, (data) => {
            if (selectedChat.value?.id !== chatId) return
            selectedChat.value = data.chat
            messages.value = mergeAvitoMessages(messages.value, data.messages?.data || [])
            messagesMeta.value = data.messages || messagesMeta.value
        }, async (url, config) => {
            let page = 1
            let result
            let incoming = []
            do {
                if (config.signal.aborted) return { data: null }
                result = await axios.get(url, { ...config, params: { page, per_page: 100 } })
                const batch = result.data.messages?.data || []
                incoming = mergeAvitoMessages(incoming, batch)
                if (!oldestId || batch.some((message) => message.id === oldestId) || !batch.length) break
                page++
            } while (page <= (result.data.messages?.last_page || 1))
            return { data: { ...result.data, messages: { ...result.data.messages, data: incoming } } }
        })
    }

    function loadSubscriptions(options = {}) {
        return read('subscriptions', '/api/avito/messenger/subscriptions', {
            ...options, params: { connection_id: selectedConnectionId.value || undefined },
        }, (data) => { subscriptions.value = data.items || [] })
    }

    function applyControl(settings, version = controlVersion.value) {
        if (!settings || version !== controlVersion.value || stopLoading.value) return false
        if (!controlSettings.value || ['mode', 'is_emergency_stopped', 'emergency_stopped_at']
            .some((key) => controlSettings.value[key] !== settings[key])) controlVersion.value++
        controlSettings.value = { ...settings }
        return true
    }

    function loadControl(options = {}) {
        const version = controlVersion.value
        controlLoading.value = true
        return read('control', '/api/avito/messenger/auto-replies/control', options, (data) => {
            if (applyControl(data.settings, version)) delete refreshErrors.control
        })
    }

    async function mutateControl(action) {
        if (stopLoading.value) return null
        const version = ++controlVersion.value
        const currentActor = actor
        cancelRequest('control')
        controlLoading.value = false
        stopLoading.value = true
        try {
            const { data } = await axios.post(`/api/avito/messenger/auto-replies/${action}`)
            if (version !== controlVersion.value || currentActor !== actor) return null
            controlSettings.value = data.settings
            return data
        } finally {
            if (version === controlVersion.value) {
                controlVersion.value++
                stopLoading.value = false
            }
        }
    }

    function configure(settings, actorId) {
        const nextActor = actorId ? String(actorId) : null
        if (actor !== null && actor !== nextActor) {
            releaseMessenger()
            readReceipts.clear()
            chatReceiptVersions.clear()
            receiptVersion++
            cancelRequest('control')
            controlVersion.value++
            controlSettings.value = null
            controlLoading.value = false
            stopLoading.value = false
            overview.value = { counts: {}, accounts: [], latest_runs: [], tools: [] }
            chats.value = []
            chatsMeta.value = { current_page: 1, last_page: 1, total: 0 }
            selectChat(null)
            subscriptions.value = []
            selectedConnectionId.value = null
            activeRun.value = null
            Object.assign(filters, { search: '', account_id: null, unread_only: false, chat_type: null })
        }
        actor = nextActor
        if (browser) coordinator.configure({ event: 'avito.changed', ...settings }, actorId)
    }

    function subscribe(key, topics, load) {
        if (!browser) return () => {}
        const id = `${key}:${++nextId}`
        const unsubscribe = coordinator.subscribe(id, topics, load)
        return () => { unsubscribe(); delete refreshErrors[id] }
    }

    function retainControl() {
        controlConsumers++
        if (controlConsumers === 1) {
            stopControl = subscribe('control', ['avito_auto_replies'], loadControl)
            void loadControl().catch(() => { refreshErrors.control = true })
        }
        return () => {
            controlConsumers--
            if (controlConsumers === 0) { stopControl(); cancelRequest('control'); controlLoading.value = false }
        }
    }

    function releaseMessenger() {
        for (const key of ['overview', 'chats', 'chat', 'older', 'subscriptions', 'updates']) cancelRequest(key)
        loading.value = false
        chatsLoading.value = false
        chatLoading.value = false
    }

    if (browser) {
        document.addEventListener('visibilitychange', coordinator.visibilityChanged)
        window.addEventListener('online', coordinator.onlineChanged)
        window.addEventListener('offline', coordinator.onlineChanged)
    }
    onScopeDispose(() => {
        for (const key of requests.keys()) cancelRequest(key)
        coordinator.dispose()
        if (browser) {
            document.removeEventListener('visibilitychange', coordinator.visibilityChanged)
            window.removeEventListener('online', coordinator.onlineChanged)
            window.removeEventListener('offline', coordinator.onlineChanged)
        }
    })

    return { overview, chats, chatsMeta, selectedChat, messages, messagesMeta, subscriptions,
        selectedConnectionId, activeRun, filters, composerText, composerTemplateId, composerTemplateName,
        loading, chatsLoading, chatLoading, sending, syncing, status, refreshErrors,
        controlSettings, controlLoading, stopLoading, controlVersion, applyControl, loadControl,
        emergencyStop: () => mutateControl('emergency-stop'), resumeAutomation: () => mutateControl('resume'),
        configure, subscribe, retainControl, reconnect: coordinator.reconnect,
        loadOverview, loadChats, invalidateChats, selectChat, loadChatPage, refreshChat, loadSubscriptions, releaseMessenger,
        loadUpdates, markRead, applyReadReceipt }
})
