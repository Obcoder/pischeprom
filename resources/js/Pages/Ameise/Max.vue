<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { useHead } from '@vueuse/head'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const chats = ref([])
const messages = ref([])
const subscriptions = ref([])
const selectedChatId = ref(null)
const loading = ref(false)
const saving = ref(false)
const sending = ref(false)
const subscriptionLoading = ref(false)
const dialog = ref(false)
const isEdit = ref(false)
const error = ref('')
const notice = ref('')
const webhookUrl = ref('')
const defaultUpdateTypes = ref([])
const remoteSubscriptions = ref(null)

const chatHeaders = [
    { title: 'Телефон', key: 'phone', width: 130 },
    { title: 'MAX', key: 'max_target' },
    { title: 'Контакт', key: 'contact' },
    { title: 'Связь', key: 'relation' },
    { title: 'Сообщений', key: 'messages_count', width: 96 },
    { title: 'Последнее', key: 'last_message_at', width: 120 },
    { title: '', key: 'actions', sortable: false, width: 160 },
]

const subscriptionHeaders = [
    { title: 'URL', key: 'url' },
    { title: 'События', key: 'update_types' },
    { title: 'Активна', key: 'is_active', width: 92 },
    { title: '', key: 'actions', sortable: false, width: 120 },
]

const chatForm = reactive({
    id: null,
    phone: '',
    chat_id: '',
    user_id: '',
    contact_name: '',
    title: '',
    entity_id: null,
    unit_id: null,
    source_type: 'manual',
    is_active: true,
})

const subscriptionForm = reactive({
    url: '',
    secret: '',
    update_types: [],
})

const sendText = ref('')

const selectedChat = computed(() => {
    return chats.value.find((chat) => Number(chat.id) === Number(selectedChatId.value)) || null
})
const selectedChatCanSend = computed(() => {
    return Boolean(selectedChat.value && chatHasTarget(selectedChat.value) && sendText.value.trim())
})

const pageTitle = computed(() => 'MAX')

useHead({
    title: pageTitle,
})

onMounted(async () => {
    await Promise.all([
        loadChats(),
        loadSubscriptions(),
    ])
})

async function loadChats() {
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get('/api/max/chats', {
            params: { all: 1 },
        })

        chats.value = data.data || []

        if (!selectedChatId.value && chats.value.length) {
            selectedChatId.value = chats.value[0].id
            await loadMessages()
        }
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось загрузить MAX-чаты.'
    } finally {
        loading.value = false
    }
}

async function loadMessages() {
    if (!selectedChatId.value) {
        messages.value = []
        return
    }

    try {
        const { data } = await axios.get(`/api/max/chats/${selectedChatId.value}/messages`, {
            params: { limit: 80 },
        })

        messages.value = data.data || []
    } catch (err) {
        console.error(err)
    }
}

async function loadSubscriptions(remote = false) {
    subscriptionLoading.value = true

    try {
        const { data } = await axios.get('/api/max/subscriptions', {
            params: { remote: remote ? 1 : 0 },
        })

        subscriptions.value = data.data || []
        webhookUrl.value = data.webhook_url || ''
        defaultUpdateTypes.value = data.default_update_types || []
        remoteSubscriptions.value = data.remote || null

        if (!subscriptionForm.url) {
            subscriptionForm.url = webhookUrl.value
        }

        if (!subscriptionForm.update_types.length) {
            subscriptionForm.update_types = [...defaultUpdateTypes.value]
        }
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось загрузить подписки MAX.'
    } finally {
        subscriptionLoading.value = false
    }
}

function openCreate() {
    Object.assign(chatForm, {
        id: null,
        phone: '',
        chat_id: '',
        user_id: '',
        contact_name: '',
        title: '',
        entity_id: null,
        unit_id: null,
        source_type: 'manual',
        is_active: true,
    })
    isEdit.value = false
    dialog.value = true
}

function openEdit(chat) {
    Object.assign(chatForm, {
        id: chat.id,
        phone: chat.phone || '',
        chat_id: chat.chat_id || '',
        user_id: chat.user_id || '',
        contact_name: chat.contact_name || '',
        title: chat.title || '',
        entity_id: chat.entity_id || null,
        unit_id: chat.unit_id || null,
        source_type: chat.source_type || 'manual',
        is_active: Boolean(chat.is_active),
    })
    isEdit.value = true
    dialog.value = true
}

async function saveChat() {
    saving.value = true
    error.value = ''
    notice.value = ''

    try {
        const payload = {
            phone: chatForm.phone || null,
            chat_id: chatForm.chat_id || null,
            user_id: chatForm.user_id || null,
            contact_name: chatForm.contact_name || null,
            title: chatForm.title || null,
            entity_id: chatForm.entity_id || null,
            unit_id: chatForm.unit_id || null,
            source_type: chatForm.source_type || 'manual',
            is_active: chatForm.is_active,
        }
        const request = isEdit.value
            ? axios.put(`/api/max/chats/${chatForm.id}`, payload)
            : axios.post('/api/max/chats', payload)
        const { data } = await request

        const chat = data.data
        chats.value = [
            chat,
            ...chats.value.filter((item) => Number(item.id) !== Number(chat.id)),
        ]
        selectedChatId.value = chat.id
        dialog.value = false
        notice.value = 'MAX-чат сохранён.'
        await loadMessages()
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось сохранить MAX-чат.'
    } finally {
        saving.value = false
    }
}

async function deleteChat(chat) {
    if (!window.confirm(`Удалить MAX-чат "${chatTitle(chat)}" из локальной базы?`)) {
        return
    }

    try {
        await axios.delete(`/api/max/chats/${chat.id}`)
        chats.value = chats.value.filter((item) => Number(item.id) !== Number(chat.id))

        if (Number(selectedChatId.value) === Number(chat.id)) {
            selectedChatId.value = chats.value[0]?.id || null
            await loadMessages()
        }
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось удалить MAX-чат.'
    }
}

async function syncChat(chat) {
    error.value = ''
    notice.value = ''

    try {
        const { data } = await axios.post(`/api/max/chats/${chat.id}/sync`)
        chats.value = chats.value.map((item) => Number(item.id) === Number(chat.id) ? data.data : item)
        notice.value = 'Данные MAX-чата обновлены.'
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось синхронизировать MAX-чат.'
    }
}

async function sendSelected() {
    if (!selectedChat.value || !sendText.value.trim()) {
        return
    }

    if (!chatHasTarget(selectedChat.value)) {
        error.value = 'У выбранного MAX-чата нет chat_id или user_id. Откройте редактирование и сохраните привязку.'
        return
    }

    sending.value = true
    error.value = ''
    notice.value = ''

    try {
        const { data } = await axios.post('/api/max/messages/send', {
            max_chat_id: selectedChat.value.id,
            phone: selectedChat.value.phone || selectedChat.value.phone_normalized,
            chat_id: selectedChat.value.chat_id,
            user_id: selectedChat.value.user_id,
            text: sendText.value,
        })

        chats.value = chats.value.map((item) => Number(item.id) === Number(data.data.id) ? data.data : item)
        sendText.value = ''
        notice.value = 'Сообщение отправлено.'
        await loadMessages()
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось отправить сообщение.'
    } finally {
        sending.value = false
    }
}

async function createSubscription() {
    subscriptionLoading.value = true
    error.value = ''
    notice.value = ''

    try {
        await axios.post('/api/max/subscriptions', {
            url: subscriptionForm.url || webhookUrl.value,
            secret: subscriptionForm.secret || null,
            update_types: subscriptionForm.update_types,
        })

        notice.value = 'Webhook-подписка MAX создана.'
        await loadSubscriptions()
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось создать MAX webhook-подписку.'
    } finally {
        subscriptionLoading.value = false
    }
}

async function deleteSubscription(subscription, unsubscribe = false) {
    const text = unsubscribe
        ? 'Отписать бота от этого webhook и удалить запись?'
        : 'Удалить локальную запись webhook-подписки?'

    if (!window.confirm(text)) {
        return
    }

    try {
        await axios.delete(`/api/max/subscriptions/${subscription.id}`, {
            params: { unsubscribe: unsubscribe ? 1 : 0 },
        })
        await loadSubscriptions()
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось удалить MAX webhook-подписку.'
    }
}

function selectChat(chat) {
    selectedChatId.value = chat.id
    loadMessages()
}

function chatTitle(chat) {
    return chat.contact_name || chat.title || chat.phone || chat.phone_normalized || `MAX #${chat.id}`
}

function relationTitle(chat) {
    return [
        chat.entity?.name ? `Entity: ${chat.entity.name}` : null,
        chat.unit?.name ? `Unit: ${chat.unit.name}` : null,
    ].filter(Boolean).join(' · ') || '-'
}

function maxTarget(chat) {
    return [
        chat.chat_id ? `chat ${chat.chat_id}` : null,
        chat.user_id ? `user ${chat.user_id}` : null,
    ].filter(Boolean).join(' · ') || '-'
}

function chatHasTarget(chat) {
    return Boolean(chat?.chat_id || chat?.user_id)
}

function formatDate(value) {
    if (!value) {
        return '-'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
}
</script>

<template>
    <v-container fluid class="max-page pa-3">
        <header class="max-page__header">
            <div>
                <span>Messenger</span>
                <h1>MAX</h1>
            </div>

            <div class="max-page__header-actions">
                <v-btn
                    color="#fff7ed"
                    prepend-icon="mdi-plus"
                    @click="openCreate"
                >
                    Чат
                </v-btn>

                <v-btn
                    color="white"
                    variant="tonal"
                    prepend-icon="mdi-refresh"
                    :loading="loading"
                    @click="loadChats"
                >
                    Обновить
                </v-btn>
            </div>
        </header>

        <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            class="mb-3"
        >
            {{ error }}
        </v-alert>

        <v-alert
            v-if="notice"
            type="success"
            variant="tonal"
            class="mb-3"
        >
            {{ notice }}
        </v-alert>

        <div class="max-page__layout">
            <section class="max-panel max-panel--table">
                <div class="max-panel__title">
                    <v-icon icon="mdi-message-text-outline" size="18" />
                    Привязки к телефонам
                </div>

                <v-data-table
                    :headers="chatHeaders"
                    :items="chats"
                    :loading="loading"
                    density="compact"
                    item-value="id"
                    hover
                >
                    <template #item.phone="{ item }">
                        <button
                            type="button"
                            class="max-page__link"
                            @click="selectChat(item)"
                        >
                            {{ item.phone || (item.phone_normalized ? `+${item.phone_normalized}` : '-') }}
                        </button>
                    </template>

                    <template #item.max_target="{ item }">
                        {{ maxTarget(item) }}
                    </template>

                    <template #item.contact="{ item }">
                        <strong>{{ chatTitle(item) }}</strong>
                        <span v-if="item.title" class="max-page__muted">{{ item.title }}</span>
                    </template>

                    <template #item.relation="{ item }">
                        {{ relationTitle(item) }}
                    </template>

                    <template #item.last_message_at="{ item }">
                        {{ formatDate(item.last_message_at) }}
                    </template>

                    <template #item.actions="{ item }">
                        <div class="max-page__row-actions">
                            <v-btn
                                icon="mdi-eye-outline"
                                size="x-small"
                                variant="text"
                                title="Открыть"
                                @click="selectChat(item)"
                            />
                            <v-btn
                                icon="mdi-cloud-sync-outline"
                                size="x-small"
                                variant="text"
                                title="Синхронизировать"
                                :disabled="!item.chat_id"
                                @click="syncChat(item)"
                            />
                            <v-btn
                                icon="mdi-pencil"
                                size="x-small"
                                variant="text"
                                title="Редактировать"
                                @click="openEdit(item)"
                            />
                            <v-btn
                                icon="mdi-delete-outline"
                                size="x-small"
                                variant="text"
                                color="error"
                                title="Удалить"
                                @click="deleteChat(item)"
                            />
                        </div>
                    </template>
                </v-data-table>
            </section>

            <aside class="max-panel max-panel--messages">
                <div class="max-panel__title">
                    <v-icon icon="mdi-forum-outline" size="18" />
                    {{ selectedChat ? chatTitle(selectedChat) : 'Чат не выбран' }}
                </div>

                <div v-if="selectedChat" class="max-page__selected">
                    <span>{{ selectedChat.phone || `+${selectedChat.phone_normalized}` }}</span>
                    <span>{{ maxTarget(selectedChat) }}</span>
                    <span>{{ relationTitle(selectedChat) }}</span>
                </div>

                <v-textarea
                    v-model="sendText"
                    label="Сообщение"
                    rows="4"
                    auto-grow
                    maxlength="4000"
                    counter
                    variant="outlined"
                    :disabled="!selectedChat"
                />

                <v-btn
                    color="#800000"
                    prepend-icon="mdi-send"
                    :disabled="!selectedChatCanSend"
                    :loading="sending"
                    @click="sendSelected"
                >
                    Отправить
                </v-btn>

                <div class="max-page__messages">
                    <div
                        v-if="!messages.length"
                        class="max-page__empty"
                    >
                        История пуста.
                    </div>

                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="max-page__message"
                        :class="`is-${message.direction}`"
                    >
                        <div>
                            <strong>{{ message.direction === 'incoming' ? 'Клиент' : 'Мы' }}</strong>
                            <span>{{ formatDate(message.sent_at || message.received_at || message.created_at) }}</span>
                            <em>{{ message.status }}</em>
                        </div>
                        <p>{{ message.text }}</p>
                        <small v-if="message.error_message">{{ message.error_message }}</small>
                    </article>
                </div>
            </aside>
        </div>

        <section class="max-panel max-panel--subscriptions mt-3">
            <div class="max-panel__title max-panel__title--split">
                <span>
                    <v-icon icon="mdi-webhook" size="18" />
                    Webhook-подписки
                </span>

                <v-btn
                    size="small"
                    variant="tonal"
                    color="#800000"
                    :loading="subscriptionLoading"
                    @click="loadSubscriptions(true)"
                >
                    Проверить MAX
                </v-btn>
            </div>

            <div class="max-page__subscription-form">
                <v-text-field
                    v-model="subscriptionForm.url"
                    label="Webhook URL"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-text-field
                    v-model="subscriptionForm.secret"
                    label="Secret"
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-combobox
                    v-model="subscriptionForm.update_types"
                    :items="defaultUpdateTypes"
                    label="События"
                    multiple
                    chips
                    closable-chips
                    density="compact"
                    variant="outlined"
                    hide-details
                />

                <v-btn
                    color="#800000"
                    prepend-icon="mdi-webhook"
                    :loading="subscriptionLoading"
                    @click="createSubscription"
                >
                    Подписать
                </v-btn>
            </div>

            <v-data-table
                :headers="subscriptionHeaders"
                :items="subscriptions"
                :loading="subscriptionLoading"
                density="compact"
                class="mt-3"
            >
                <template #item.update_types="{ item }">
                    <div class="max-page__chips">
                        <span
                            v-for="type in item.update_types"
                            :key="type"
                        >
                            {{ type }}
                        </span>
                    </div>
                </template>

                <template #item.is_active="{ item }">
                    <v-chip
                        :color="item.is_active ? 'green' : 'grey'"
                        size="x-small"
                        variant="tonal"
                    >
                        {{ item.is_active ? 'да' : 'нет' }}
                    </v-chip>
                </template>

                <template #item.actions="{ item }">
                    <div class="max-page__row-actions">
                        <v-btn
                            icon="mdi-webhook-off"
                            size="x-small"
                            variant="text"
                            title="Отписать и удалить"
                            @click="deleteSubscription(item, true)"
                        />
                        <v-btn
                            icon="mdi-delete-outline"
                            size="x-small"
                            variant="text"
                            color="error"
                            title="Удалить локально"
                            @click="deleteSubscription(item)"
                        />
                    </div>
                </template>
            </v-data-table>

            <pre v-if="remoteSubscriptions" class="max-page__remote">{{ remoteSubscriptions }}</pre>
        </section>

        <v-dialog v-model="dialog" max-width="720">
            <v-card>
                <v-card-title>{{ isEdit ? 'Редактировать MAX-чат' : 'Новый MAX-чат' }}</v-card-title>

                <v-card-text>
                    <div class="max-page__chat-form">
                        <v-text-field v-model="chatForm.phone" label="Телефон" variant="outlined" density="compact" />
                        <v-text-field v-model="chatForm.chat_id" label="MAX chat_id" variant="outlined" density="compact" />
                        <v-text-field v-model="chatForm.user_id" label="MAX user_id" variant="outlined" density="compact" />
                        <v-text-field v-model="chatForm.contact_name" label="Контакт" variant="outlined" density="compact" />
                        <v-text-field v-model="chatForm.title" label="Название чата" variant="outlined" density="compact" />
                        <v-switch v-model="chatForm.is_active" color="#800000" label="Активен" hide-details />
                    </div>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="dialog = false">Отмена</v-btn>
                    <v-btn color="#800000" :loading="saving" @click="saveChat">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.max-page {
    background: linear-gradient(180deg, #fffdf8 0%, #f6efe8 100%);
    color: #24180f;
}

.max-page__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 12px;
    padding: 18px;
    background: #4d1111;
    color: #fff;
}

.max-page__header span {
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.max-page__header h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 950;
    line-height: 1;
}

.max-page__header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.max-page__layout {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(360px, 0.8fr);
    gap: 12px;
}

.max-panel {
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(128, 0, 0, 0.10);
    background: #fff;
}

.max-panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: #3f1d1d;
    font-weight: 950;
}

.max-panel__title--split {
    justify-content: space-between;
}

.max-panel__title--split span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.max-page__link {
    padding: 0;
    border: 0;
    background: transparent;
    color: #800000;
    cursor: pointer;
    font-weight: 900;
}

.max-page__muted {
    display: block;
    color: #8b6b61;
    font-size: 0.75rem;
}

.max-page__row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 2px;
}

.max-page__selected {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}

.max-page__selected span,
.max-page__chips span {
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    padding: 2px 7px;
    background: #fff7ed;
    color: #6f4439;
    font-size: 0.72rem;
    font-weight: 800;
}

.max-page__messages {
    display: grid;
    gap: 8px;
    margin-top: 14px;
}

.max-page__empty {
    padding: 14px;
    border: 1px dashed rgba(128, 0, 0, 0.2);
    color: #8b6b61;
}

.max-page__message {
    padding: 10px 12px;
    border: 1px solid rgba(64, 31, 20, 0.12);
}

.max-page__message.is-outgoing {
    border-left: 4px solid #800000;
}

.max-page__message.is-incoming {
    border-left: 4px solid #2f7d68;
}

.max-page__message div {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    color: #7a5d52;
    font-size: 0.74rem;
}

.max-page__message p {
    margin: 4px 0 0;
    white-space: pre-wrap;
}

.max-page__message small {
    color: #a20f0f;
}

.max-page__subscription-form {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(180px, 0.5fr) minmax(260px, 1fr) auto;
    gap: 10px;
    align-items: start;
}

.max-page__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.max-page__remote {
    max-height: 260px;
    margin-top: 12px;
    overflow: auto;
    background: #2a211d;
    color: #fff7ed;
    padding: 12px;
    font-size: 0.75rem;
    white-space: pre-wrap;
}

.max-page__chat-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

@media (max-width: 1180px) {
    .max-page__layout,
    .max-page__subscription-form,
    .max-page__chat-form {
        grid-template-columns: 1fr;
    }
}
</style>
