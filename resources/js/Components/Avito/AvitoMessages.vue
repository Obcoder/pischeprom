<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import AvitoCrmPanel from './AvitoCrmPanel.vue'

const props = defineProps({
    connections: { type: Array, default: () => [] },
})

const emit = defineEmits(['notice', 'error'])

const loading = ref(true)
const chatsLoading = ref(false)
const chatLoading = ref(false)
const sending = ref(false)
const syncing = ref(false)
const overview = ref({ counts: {}, accounts: [], latest_runs: [], tools: [] })
const chats = ref([])
const chatsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const selectedChat = ref(null)
const messages = ref([])
const messagesMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const subscriptions = ref([])
const selectedConnectionId = ref(null)
const activeRun = ref(null)
const composerText = ref('')
const composerTemplateId = ref(null)
const composerTemplateName = ref('')
const composerInput = ref(null)
const imageInput = ref(null)
const messageStream = ref(null)
const crmPanel = ref(null)
const filters = reactive({ search: '', account_id: null, unread_only: false, chat_type: null })
let searchTimer = null
let runTimer = null

const connectionOptions = computed(() => [
    { title: 'Client credentials (.env)', value: null },
    ...props.connections.map((connection) => ({ title: connection.name, value: connection.id })),
])
const accountOptions = computed(() => overview.value.accounts.map((account) => ({
    title: `${account.name || account.external_user_id} · ${account.chats_count || 0}`,
    value: account.id,
})))
const runningRun = computed(() => activeRun.value
    || overview.value.latest_runs.find((run) => ['queued', 'running'].includes(run.status)))
const canSend = computed(() => selectedChat.value && composerText.value.trim().length > 0 && !sending.value)

watch(() => ({ ...filters }), () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadChats(1), 280)
}, { deep: true })

async function initialize() {
    loading.value = true
    try {
        await Promise.all([loadOverview(), loadChats(1), loadSubscriptions()])
    } finally {
        loading.value = false
    }
}

async function loadOverview() {
    try {
        const { data } = await axios.get('/api/avito/messenger/overview')
        overview.value = data
        const run = data.latest_runs?.find((item) => ['queued', 'running'].includes(item.status))
        if (run && !activeRun.value) {
            activeRun.value = run
            pollRun(run.id)
        }
    } catch (exception) {
        fail(exception, 'Не удалось загрузить состояние архива сообщений.')
    }
}

async function loadChats(page = 1) {
    chatsLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/messenger/chats', {
            params: {
                page,
                per_page: 50,
                search: filters.search || undefined,
                account_id: filters.account_id || undefined,
                unread_only: filters.unread_only || undefined,
                chat_type: filters.chat_type || undefined,
            },
        })
        chats.value = data.data || []
        chatsMeta.value = data
        if (selectedChat.value) {
            const current = chats.value.find((chat) => chat.id === selectedChat.value.id)
            if (current) selectedChat.value = { ...selectedChat.value, ...current }
        }
    } catch (exception) {
        fail(exception, 'Не удалось загрузить локальный архив чатов.')
    } finally {
        chatsLoading.value = false
    }
}

async function openChat(chat) {
    selectedChat.value = chat
    messages.value = []
    messagesMeta.value = { current_page: 1, last_page: 1, total: 0 }
    clearComposerTemplate()
    await loadChatPage(1, false)
}

async function loadChatPage(page, prepend) {
    if (!selectedChat.value) return
    chatLoading.value = true
    try {
        const { data } = await axios.get(`/api/avito/messenger/chats/${selectedChat.value.id}`, {
            params: { page, per_page: 100 },
        })
        selectedChat.value = data.chat
        const incoming = data.messages?.data || []
        messages.value = prepend
            ? uniqueMessages([...incoming, ...messages.value])
            : incoming
        messagesMeta.value = data.messages || messagesMeta.value
        if (!prepend) await scrollToBottom()
    } catch (exception) {
        fail(exception, 'Не удалось открыть переписку.')
    } finally {
        chatLoading.value = false
    }
}

async function loadOlderMessages() {
    if (messagesMeta.value.current_page >= messagesMeta.value.last_page) return
    await loadChatPage(messagesMeta.value.current_page + 1, true)
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
        pollRun(data.run.id)
    } catch (exception) {
        fail(exception, 'Не удалось запустить синхронизацию.')
    } finally {
        syncing.value = false
    }
}

function pollRun(id) {
    clearTimeout(runTimer)
    runTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(`/api/avito/messenger/sync-runs/${id}`)
            activeRun.value = data.run
            if (['queued', 'running'].includes(data.run.status)) {
                pollRun(id)
                return
            }

            if (data.run.status === 'success') {
                notify(`Архив обновлён: ${data.run.messages_created} новых сообщений, ${data.run.chats_created} новых чатов.`)
                await Promise.all([loadOverview(), loadChats(1)])
                if (selectedChat.value) await loadChatPage(1, false)
            } else {
                emit('error', data.run.error_message || 'Синхронизация Avito завершилась с ошибкой.')
            }
            activeRun.value = null
        } catch (exception) {
            fail(exception, 'Не удалось получить статус синхронизации.')
            pollRun(id)
        }
    }, 2200)
}

async function refreshSelectedChat() {
    if (!selectedChat.value) return
    chatLoading.value = true
    try {
        await axios.post(`/api/avito/messenger/chats/${selectedChat.value.id}/refresh`, { message_limit: 100 })
        await Promise.all([loadChatPage(1, false), loadChats(chatsMeta.value.current_page)])
        notify('Переписка обновлена из Avito.')
    } catch (exception) {
        fail(exception, 'Не удалось обновить чат.')
    } finally {
        chatLoading.value = false
    }
}

async function markRead() {
    if (!selectedChat.value) return
    try {
        await axios.post(`/api/avito/messenger/chats/${selectedChat.value.id}/read`)
        selectedChat.value.is_unread = false
        selectedChat.value.unread_count = 0
        messages.value.forEach((message) => {
            if (message.direction === 'in') message.is_read = true
        })
        await loadChats(chatsMeta.value.current_page)
        notify('Чат отмечен прочитанным на Avito.')
    } catch (exception) {
        fail(exception, 'Не удалось отметить чат прочитанным.')
    }
}

async function sendText() {
    const text = composerText.value.trim()
    if (!text || !selectedChat.value) return
    sending.value = true
    try {
        const { data } = await axios.post(`/api/avito/messenger/chats/${selectedChat.value.id}/messages`, {
            text,
            template_id: composerTemplateId.value || undefined,
        })
        messages.value = uniqueMessages([...messages.value, data.item])
        composerText.value = ''
        clearComposerTemplate()
        await scrollToBottom()
        await loadChats(chatsMeta.value.current_page)
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
    if (!image || !selectedChat.value) return
    sending.value = true
    try {
        const payload = new FormData()
        payload.append('image', image)
        const { data } = await axios.post(`/api/avito/messenger/chats/${selectedChat.value.id}/messages/image`, payload)
        messages.value = uniqueMessages([...messages.value, data.item])
        await scrollToBottom()
        await loadChats(chatsMeta.value.current_page)
    } catch (exception) {
        fail(exception, 'Avito не принял изображение.')
    } finally {
        sending.value = false
    }
}

async function refreshAfterCrmMutation() {
    await loadChats(chatsMeta.value.current_page)
    if (selectedChat.value) await loadChatPage(1, false)
}

async function refreshMessagesFromCrm() {
    if (selectedChat.value) await loadChatPage(1, false)
    await loadChats(chatsMeta.value.current_page)
}

function handleContactCandidate(candidate) {
    if (candidate.type === 'phone') {
        crmPanel.value?.acceptPhoneCandidate(candidate)
        return
    }

    crmPanel.value?.prepareAddressCandidate(candidate)
}

function openCrmCatalog() {
    crmPanel.value?.openCatalog()
}

function openMessageTemplates() {
    crmPanel.value?.openTemplates()
}

async function insertMessageTemplate(payload) {
    composerText.value = payload.text || ''
    composerTemplateId.value = payload.template_id || null
    composerTemplateName.value = payload.template_name || ''
    await nextTick()
    composerInput.value?.focus()
}

async function handleTemplateSent(message) {
    messages.value = uniqueMessages([...messages.value, message])
    await scrollToBottom()
    await loadChats(chatsMeta.value.current_page)
}

function clearComposerTemplate() {
    composerTemplateId.value = null
    composerTemplateName.value = ''
}

async function deleteMessage(message) {
    if (!window.confirm('Удалить сообщение на Avito? Локальная архивная копия останется.')) return
    try {
        const { data } = await axios.delete(`/api/avito/messenger/messages/${message.id}`)
        const index = messages.value.findIndex((item) => item.id === message.id)
        if (index >= 0) messages.value[index] = data.item
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
        const { data } = await axios.get('/api/avito/messenger/subscriptions', {
            params: { connection_id: selectedConnectionId.value || undefined },
        })
        subscriptions.value = data.items || []
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
    return [...new Map(items.map((item) => [item.id, item])).values()]
        .sort((a, b) => new Date(a.remote_created_at || 0) - new Date(b.remote_created_at || 0))
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

async function scrollToBottom() {
    await nextTick()
    if (messageStream.value) messageStream.value.scrollTop = messageStream.value.scrollHeight
}

function notify(message) {
    emit('notice', message)
}

function fail(exception, fallback) {
    emit('error', exception?.response?.data?.message || fallback)
}

onMounted(initialize)
onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    clearTimeout(runTimer)
})
</script>

<template>
    <section class="messenger-module">
        <header class="messenger-toolbar">
            <div class="messenger-stat"><v-icon icon="mdi-forum-outline" /><span>Чаты<strong>{{ overview.counts.chats || 0 }}</strong></span></div>
            <div class="messenger-stat"><v-icon icon="mdi-message-text-fast-outline" /><span>Сообщения<strong>{{ overview.counts.messages || 0 }}</strong></span></div>
            <div class="messenger-stat"><v-icon icon="mdi-message-badge-outline" /><span>Непрочитанные<strong>{{ overview.counts.unread_chats || 0 }}</strong></span></div>
            <div class="messenger-stat"><v-icon icon="mdi-archive-check-outline" /><span>Медиа в архиве<strong>{{ overview.counts.attachments || 0 }}</strong></span></div>
            <v-spacer />
            <v-select v-model="selectedConnectionId" :items="connectionOptions" label="Источник" density="compact" variant="outlined" hide-details @update:model-value="loadSubscriptions" />
            <v-btn size="small" color="deep-purple-lighten-1" prepend-icon="mdi-sync" :loading="syncing || !!runningRun" @click="queueSync(false)">Синхронизировать</v-btn>
            <v-menu>
                <template #activator="{ props: menuProps }"><v-btn v-bind="menuProps" icon="mdi-dots-vertical" size="small" variant="text" /></template>
                <v-list density="compact">
                    <v-list-item prepend-icon="mdi-archive-sync-outline" title="Полная архивация" subtitle="До 1100 чатов и сообщений на чат" @click="queueSync(true)" />
                    <v-list-item prepend-icon="mdi-webhook" title="Подключить webhook V3" @click="changeSubscription(true)" />
                    <v-list-item prepend-icon="mdi-webhook-off" title="Отключить webhook" @click="changeSubscription(false)" />
                </v-list>
            </v-menu>
        </header>

        <div v-if="runningRun" class="sync-strip">
            <v-progress-linear indeterminate color="deep-purple-accent-1" height="2" />
            <span>{{ runningRun.status === 'queued' ? 'Синхронизация ожидает запуска' : 'Архивируем чаты, сообщения и вложения' }}</span>
            <small>{{ runningRun.messages_seen || 0 }} сообщений обработано</small>
        </div>

        <div v-if="loading" class="messenger-loading"><v-progress-circular indeterminate color="deep-purple-lighten-2" /><span>Открываем архив Avito…</span></div>

        <div v-else class="messenger-layout">
            <aside class="chat-list-pane">
                <div class="chat-filters">
                    <v-text-field v-model="filters.search" prepend-inner-icon="mdi-magnify" placeholder="Чат, клиент или текст сообщения" title="Поиск по данным чата, клиенту и всей сохранённой переписке" density="compact" variant="outlined" hide-details clearable />
                    <div>
                        <v-select v-model="filters.account_id" :items="accountOptions" placeholder="Все аккаунты" density="compact" variant="outlined" hide-details clearable />
                        <v-btn :color="filters.unread_only ? 'deep-purple-lighten-1' : undefined" :variant="filters.unread_only ? 'tonal' : 'text'" icon="mdi-message-badge-outline" size="small" @click="filters.unread_only = !filters.unread_only" />
                    </div>
                </div>
                <div class="chat-list" :class="{ 'is-loading': chatsLoading }">
                    <button v-for="chat in chats" :key="chat.id" type="button" class="chat-row" :class="{ 'is-active': selectedChat?.id === chat.id, 'is-unread': chat.is_unread }" @click="openChat(chat)">
                        <v-avatar size="34" color="deep-purple-darken-1"><v-img v-if="chat.peer_avatar_url" :src="chat.peer_avatar_url" cover /><span v-else>{{ (chat.peer_name || chat.title || 'A').slice(0, 1).toUpperCase() }}</span></v-avatar>
                        <span class="chat-row__body"><strong>{{ chat.peer_name || chat.title || 'Чат Avito' }}</strong><small>{{ chat.last_message_preview || 'Сообщений пока нет' }}</small><em>{{ chat.title !== chat.peer_name ? chat.title : `ID ${chat.external_chat_id.slice(0, 8)}` }}</em></span>
                        <span class="chat-row__meta"><time>{{ formatDate(chat.last_message_at, true) }}</time><b v-if="chat.unread_count">{{ chat.unread_count }}</b><v-icon v-if="chat.entity" icon="mdi-account-check-outline" size="11" color="green-lighten-1" :title="chat.entity.name" /><i>{{ chat.chat_type || 'u2i' }}</i></span>
                    </button>
                    <div v-if="!chats.length" class="pane-empty"><v-icon icon="mdi-forum-remove-outline" size="36" /><strong>Архив пока пуст</strong><span>Запустите синхронизацию — чаты и сообщения сохранятся на сервере.</span></div>
                </div>
                <v-pagination v-if="chatsMeta.last_page > 1" v-model="chatsMeta.current_page" :length="chatsMeta.last_page" density="compact" total-visible="4" @update:model-value="loadChats" />
            </aside>

            <main class="conversation-pane">
                <template v-if="selectedChat">
                    <header class="conversation-header">
                        <div><strong>{{ selectedChat.peer_name || selectedChat.title }}</strong><span>{{ selectedChat.entity?.name || 'Entity не связана' }} · {{ selectedChat.title }} · {{ selectedChat.messages_count || messagesMeta.total || 0 }} сообщений · архив {{ formatDate(selectedChat.last_synced_at) }}</span></div>
                        <v-btn icon="mdi-refresh" size="small" variant="text" :loading="chatLoading" title="Обновить из Avito" @click="refreshSelectedChat" />
                        <v-btn icon="mdi-check-all" size="small" variant="text" title="Отметить прочитанным" @click="markRead" />
                        <v-menu>
                            <template #activator="{ props: menuProps }"><v-btn v-bind="menuProps" icon="mdi-account-cancel-outline" color="error" size="small" variant="text" /></template>
                            <v-list density="compact"><v-list-subheader>Причина блокировки</v-list-subheader><v-list-item v-for="reason in [{ id: 1, title: 'Спам' }, { id: 2, title: 'Мошенничество' }, { id: 3, title: 'Оскорбления' }, { id: 4, title: 'Другая' }]" :key="reason.id" :title="reason.title" @click="blacklist(reason.id)" /></v-list>
                        </v-menu>
                    </header>

                    <div ref="messageStream" class="message-stream">
                        <v-btn v-if="messagesMeta.current_page < messagesMeta.last_page" class="older-button" size="x-small" variant="tonal" :loading="chatLoading" @click="loadOlderMessages">Загрузить более ранние</v-btn>
                        <article v-for="message in messages" :key="message.id" class="message-bubble" :class="[`is-${message.direction || 'in'}`, { 'is-deleted': message.remote_type === 'deleted' }]">
                            <div v-if="attachment(message, 'image')" class="message-image"><img :src="attachment(message, 'image').url" alt="Изображение из архива Avito" loading="lazy"></div>
                            <audio v-if="attachment(message, 'voice')" :src="attachment(message, 'voice').url" controls preload="none" />
                            <p v-if="message.type !== 'image' || !attachment(message, 'image')">{{ messageText(message) }}</p>
                            <div v-if="message.contact_candidates?.length" class="message-candidates">
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
                        <v-btn icon="mdi-package-variant-closed-plus" size="small" variant="text" :disabled="sending" title="Выбрать товар из Пищепром-Сервера" @click="openCrmCatalog" />
                        <v-btn icon="mdi-text-box-multiple-outline" size="small" variant="text" :disabled="sending" title="Шаблоны сообщений" @click="openMessageTemplates" />
                        <v-btn icon="mdi-image-plus-outline" size="small" variant="text" :disabled="sending" title="Отправить изображение" @click="selectImage" />
                        <v-textarea ref="composerInput" v-model="composerText" :placeholder="composerTemplateName ? `Шаблон: ${composerTemplateName}` : 'Сообщение до 1000 символов'" rows="1" max-rows="4" auto-grow density="compact" variant="solo-filled" hide-details maxlength="1000" @keydown.ctrl.enter.prevent="sendText" />
                        <span :title="composerTemplateName ? `Используется шаблон «${composerTemplateName}»` : ''">{{ composerText.length }}/1000<b v-if="composerTemplateId">Ш</b></span>
                        <v-btn icon="mdi-send" color="deep-purple-lighten-1" size="small" :loading="sending" :disabled="!canSend" @click="sendText" />
                    </footer>
                </template>
                <div v-else class="conversation-empty"><v-icon icon="mdi-message-text-outline" size="48" /><strong>Выберите переписку</strong><span>Здесь доступны отправка текста и изображений, удаление, прочтение и блокировка.</span></div>
            </main>

            <AvitoCrmPanel
                v-if="selectedChat"
                ref="crmPanel"
                :chat="selectedChat"
                @notice="notify"
                @error="(message) => emit('error', message)"
                @chat-updated="refreshAfterCrmMutation"
                @refresh-messages="refreshMessagesFromCrm"
                @insert-template="insertMessageTemplate"
                @template-sent="handleTemplateSent"
            />

            <aside v-else class="messenger-info-pane">
                <section><span class="info-eyebrow">Локальный архив</span><dl><dt>Аккаунтов</dt><dd>{{ overview.counts.accounts || 0 }}</dd><dt>Чатов</dt><dd>{{ overview.counts.chats || 0 }}</dd><dt>Сообщений</dt><dd>{{ overview.counts.messages || 0 }}</dd><dt>Вложений</dt><dd>{{ overview.counts.attachments || 0 }}</dd></dl></section>
                <section><span class="info-eyebrow">Realtime</span><strong>{{ subscriptions.length ? 'Webhook V3 активен' : 'Webhook не найден' }}</strong><small>{{ subscriptions.length ? `${subscriptions.length} подписок Avito` : 'Плановая синхронизация выполняется каждые 5 минут' }}</small><div><v-btn v-if="!subscriptions.length" size="x-small" variant="tonal" @click="changeSubscription(true)">Подключить</v-btn><v-btn v-else size="x-small" color="error" variant="text" @click="changeSubscription(false)">Отключить</v-btn></div></section>
                <section><span class="info-eyebrow">Возможности Avito</span><small>API разрешает создать и удалить сообщение, но не предоставляет редактирование уже отправленного текста. Удаление доступно не позднее часа.</small></section>
                <v-expansion-panels variant="accordion" class="tools-panel"><v-expansion-panel><v-expansion-panel-title>13 инструментов API</v-expansion-panel-title><v-expansion-panel-text><a v-for="tool in overview.tools" :key="tool.id" :href="tool.documentation_url" target="_blank" rel="noopener noreferrer"><span>{{ tool.method }}</span>{{ tool.summary }}</a></v-expansion-panel-text></v-expansion-panel></v-expansion-panels>
            </aside>
        </div>
    </section>
</template>

<style scoped>
.messenger-module { overflow: hidden; width: 100%; min-height: calc(100vh - 285px); color: #e9ebff; border: 1px solid #30344d; border-radius: 10px; background: #111427; }
.messenger-toolbar { display: flex; min-height: 58px; align-items: center; gap: 9px; padding: 8px 12px; border-bottom: 1px solid #30344d; background: #1b1e35; }
.messenger-toolbar > .v-select { max-width: 230px; }
.messenger-stat { display: flex; min-width: 112px; align-items: center; gap: 8px; padding: 5px 9px; border: 1px solid #33374f; border-radius: 8px; background: #15182b; }
.messenger-stat > .v-icon { color: #a995ff; }.messenger-stat span, .messenger-stat strong { display: block; }.messenger-stat span { color: #858cac; font-size: 9px; text-transform: uppercase; }.messenger-stat strong { margin-top: 1px; color: #f1f2ff; font-size: 16px; }
.sync-strip { position: relative; display: grid; grid-template-columns: 1fr auto; gap: 2px 12px; padding: 7px 13px; color: #d9d1ff; font-size: 11px; background: #282044; }.sync-strip .v-progress-linear { position: absolute; inset: 0 0 auto; }.sync-strip small { color: #a7a0c9; }
.messenger-loading { display: flex; min-height: 430px; align-items: center; justify-content: center; gap: 12px; color: #9da3c3; }
.messenger-layout { display: grid; grid-template-columns: 320px minmax(410px, 1fr) 360px; height: calc(100vh - 355px); min-height: 520px; }
.chat-list-pane, .conversation-pane, .messenger-info-pane { min-width: 0; min-height: 0; }
.chat-list-pane { display: flex; flex-direction: column; border-right: 1px solid #30344d; background: #15182b; }
.chat-filters { display: grid; width: 100%; grid-template-columns: minmax(0, 1fr); gap: 6px; padding: 8px; border-bottom: 1px solid #2c3048; }.chat-filters > .v-input { grid-column: 1 / -1; min-width: 0; width: 100%; justify-self: stretch; }.chat-filters :deep(.v-input--horizontal) { grid-template-areas: 'control' 'messages'; grid-template-columns: minmax(0, 1fr); }.chat-filters > div { display: grid; min-width: 0; grid-template-columns: minmax(0, 1fr) auto; gap: 4px; }.chat-filters > div > .v-input { min-width: 0; width: 100%; }
.chat-list { overflow-y: auto; flex: 1; transition: opacity .15s; }.chat-list.is-loading { opacity: .55; }
.chat-row { display: grid; width: 100%; grid-template-columns: 34px minmax(0, 1fr) auto; align-items: start; gap: 8px; padding: 10px 9px; color: #e9ebff; text-align: left; border: 0; border-bottom: 1px solid #292d45; background: transparent; cursor: pointer; }.chat-row:hover { background: #1d2038; }.chat-row.is-active { box-shadow: inset 3px 0 #9378ff; background: #24213f; }.chat-row.is-unread .chat-row__body strong { color: #fff; }
.chat-row__body { min-width: 0; }.chat-row__body strong, .chat-row__body small, .chat-row__body em { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.chat-row__body strong { font-size: 12px; }.chat-row__body small { margin-top: 3px; color: #9ca2c1; font-size: 11px; }.chat-row__body em { margin-top: 3px; color: #6f7596; font-size: 9px; font-style: normal; }
.chat-row__meta { display: grid; justify-items: end; gap: 4px; }.chat-row__meta time { color: #747b9e; font-size: 8px; }.chat-row__meta b { display: grid; min-width: 18px; height: 18px; place-items: center; color: #fff; font-size: 9px; border-radius: 20px; background: #7957e8; }.chat-row__meta i { color: #6f7596; font-size: 8px; font-style: normal; text-transform: uppercase; }
.conversation-pane { display: flex; flex-direction: column; background: radial-gradient(circle at 50% 0, rgba(100, 70, 190, .08), transparent 45%), #101324; }
.conversation-header { display: flex; min-height: 58px; align-items: center; gap: 3px; padding: 8px 12px; border-bottom: 1px solid #30344d; background: #1a1d33; }.conversation-header > div:first-child { min-width: 0; flex: 1; }.conversation-header strong, .conversation-header span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.conversation-header strong { font-size: 13px; }.conversation-header span { margin-top: 3px; color: #858baa; font-size: 10px; }
.message-stream { display: flex; overflow-y: auto; flex: 1; flex-direction: column; gap: 5px; padding: 12px 14px; }.older-button { align-self: center; margin: 3px 0 9px; }
.message-bubble { width: fit-content; max-width: min(76%, 680px); padding: 7px 9px 4px; border: 1px solid #343951; border-radius: 11px 11px 11px 3px; background: #20243b; box-shadow: 0 4px 12px rgba(0, 0, 0, .12); }.message-bubble.is-out { align-self: flex-end; border-color: rgba(132, 103, 239, .38); border-radius: 11px 11px 3px; background: #392d62; }.message-bubble.is-deleted { border-style: dashed; opacity: .82; }.message-bubble p { margin: 0; color: #f0f1ff; font-size: 12px; line-height: 1.38; white-space: pre-wrap; word-break: break-word; }.message-bubble audio { width: 260px; max-width: 100%; height: 34px; }.message-image { overflow: hidden; max-width: 360px; margin: -3px -5px 4px; border-radius: 7px; }.message-image img { display: block; width: 100%; max-height: 320px; object-fit: contain; background: #0d1020; }.message-bubble footer { display: flex; align-items: center; justify-content: flex-end; gap: 4px; margin-top: 3px; color: #8f95b5; font-size: 8px; }.message-bubble footer span { margin-right: auto; text-transform: uppercase; }.archive-marker { display: flex; align-items: center; gap: 3px; margin-top: 5px; color: #d2a4ae; font-size: 8px; }
.message-candidates { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 5px; }.message-candidates button { display: flex; align-items: center; gap: 3px; padding: 2px 5px; color: #b7ead4; font-size: 7px; border: 1px solid rgba(90, 205, 154, .28); border-radius: 10px; background: rgba(34, 105, 75, .22); cursor: pointer; }.message-candidates button.is-address { color: #b9dff0; border-color: rgba(83, 175, 216, .28); background: rgba(33, 91, 119, .22); }
.composer { display: grid; grid-template-columns: auto auto auto minmax(0, 1fr) auto auto; align-items: center; gap: 5px; padding: 7px 10px; border-top: 1px solid #30344d; background: #1a1d33; }.composer > span { display: flex; align-items: center; gap: 3px; color: #737999; font-size: 8px; }.composer > span b { display: grid; width: 13px; height: 13px; place-items: center; color: #d9d0ff; font-size: 7px; border-radius: 10px; background: #654eb5; }.composer :deep(textarea) { font-size: 12px; line-height: 1.35; }
.conversation-empty, .pane-empty { display: grid; place-items: center; align-content: center; gap: 7px; color: #858baa; text-align: center; }.conversation-empty { flex: 1; }.conversation-empty strong, .pane-empty strong { color: #dfe2f8; }.conversation-empty span, .pane-empty span { max-width: 300px; font-size: 11px; }.pane-empty { min-height: 220px; padding: 20px; }
.messenger-info-pane { overflow-y: auto; padding: 9px; border-left: 1px solid #30344d; background: #15182b; }.messenger-info-pane section { margin-bottom: 8px; padding: 10px; border: 1px solid #2f334c; border-radius: 8px; background: #1b1e35; }.info-eyebrow { display: block; margin-bottom: 8px; color: #9d88f4; font-size: 8px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }.messenger-info-pane dl { display: grid; grid-template-columns: 1fr auto; gap: 5px 7px; margin: 0; font-size: 10px; }.messenger-info-pane dt { color: #858baa; }.messenger-info-pane dd { overflow: hidden; max-width: 125px; margin: 0; color: #e5e7fa; text-overflow: ellipsis; white-space: nowrap; }.messenger-info-pane section > strong, .messenger-info-pane section > small { display: block; }.messenger-info-pane section > strong { font-size: 11px; }.messenger-info-pane section > small { margin: 4px 0 8px; color: #858baa; font-size: 9px; line-height: 1.4; }.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 8px; }
.tools-panel :deep(.v-expansion-panel) { color: #dfe2f8; background: #1b1e35; }.tools-panel :deep(.v-expansion-panel-title) { min-height: 38px; padding: 8px 10px; font-size: 10px; }.tools-panel :deep(.v-expansion-panel-text__wrapper) { display: grid; gap: 4px; padding: 4px 8px 10px; }.tools-panel a { display: grid; grid-template-columns: 32px 1fr; gap: 4px; color: #bec4e3; font-size: 8px; text-decoration: none; }.tools-panel a span { color: #9c85f5; font-weight: 800; }
@media (max-width: 1250px) { .messenger-layout { grid-template-columns: 285px minmax(390px, 1fr) 330px; }.messenger-stat:nth-of-type(4) { display: none; } }
@media (max-width: 850px) { .messenger-toolbar { flex-wrap: wrap; }.messenger-stat { min-width: 90px; flex: 1; }.messenger-toolbar > .v-select { max-width: none; flex-basis: 190px; }.messenger-layout { display: block; height: auto; }.chat-list-pane { height: 360px; border-right: 0; }.conversation-pane { min-height: 620px; border-top: 1px solid #30344d; }.message-bubble { max-width: 88%; } }
</style>
