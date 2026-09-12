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
    const browser = typeof window !== 'undefined' && typeof document !== 'undefined'
    let actor = null
    let nextId = 0
    let controlConsumers = 0
    let stopControl = () => {}
    const coordinator = createRealtimeCoordinator({
        connect: connectCommerceSocket,
        allowedTopics: AVITO_TOPICS,
        isVisible: () => browser && document.visibilityState !== 'hidden',
        isOnline: () => browser && navigator.onLine !== false,
        onStatus: (value) => { status.value = value },
        onRefreshed: (id) => { delete refreshErrors[id] },
        onError: (id) => { refreshErrors[id] = true },
    })

    // Both cancellation and identity checks are needed: a response can finish while a chat changes.
    function cancelRequest(key) {
        requests.get(key)?.abort()
        requests.delete(key)
    }

    async function read(key, url, options, apply, fetch = (path, config) => axios.get(path, config)) {
        cancelRequest(key)
        const controller = new AbortController()
        const external = options?.signal
        const abort = () => controller.abort()
        if (external?.aborted) controller.abort()
        else external?.addEventListener('abort', abort, { once: true })
        requests.set(key, controller)
        try {
            const { data } = await fetch(url, { ...options, signal: controller.signal })
            if (requests.get(key) !== controller || controller.signal.aborted) return null
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
            overview.value = data
            activeRun.value = data.latest_runs?.find((run) => ['queued', 'running'].includes(run.status)) || null
        })
    }

    function invalidateChats() {
        cancelRequest('chats')
        chatsLoading.value = false
    }

    function loadChats(page = chatsMeta.value.current_page, options = {}) {
        chatsLoading.value = true
        return read('chats', '/api/avito/messenger/chats', {
            ...options,
            params: { page, per_page: 50, search: filters.search || undefined,
                account_id: filters.account_id || undefined, unread_only: filters.unread_only || undefined,
                chat_type: filters.chat_type || undefined },
        }, (data) => {
            chats.value = data.data || []
            chatsMeta.value = data
            const current = chats.value.find((chat) => chat.id === selectedChat.value?.id)
            if (current) selectedChat.value = { ...selectedChat.value, ...current }
        })
    }

    function selectChat(chat) {
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
        for (const key of ['overview', 'chats', 'chat', 'older', 'subscriptions']) cancelRequest(key)
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
        loadOverview, loadChats, invalidateChats, selectChat, loadChatPage, refreshChat, loadSubscriptions, releaseMessenger }
})
