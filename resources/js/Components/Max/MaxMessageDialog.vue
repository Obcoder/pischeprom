<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    phone: {
        type: String,
        default: '',
    },
    entityId: {
        type: [Number, String],
        default: null,
    },
    unitId: {
        type: [Number, String],
        default: null,
    },
    contextTitle: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['update:modelValue', 'sent'])

const chats = ref([])
const messages = ref([])
const selectedChatId = ref(null)
const loading = ref(false)
const saving = ref(false)
const sending = ref(false)
const error = ref('')
const notice = ref('')

const form = reactive({
    phone: '',
    chat_id: '',
    user_id: '',
    contact_name: '',
    title: '',
    text: '',
})

const selectedChat = computed(() => {
    return chats.value.find((chat) => Number(chat.id) === Number(selectedChatId.value)) || null
})

const normalizedPhone = computed(() => normalizePhone(form.phone || props.phone))
const canSend = computed(() => {
    return Boolean(form.text.trim()) && Boolean(form.chat_id || form.user_id)
})

watch(
    () => props.modelValue,
    async (value) => {
        if (!value) {
            return
        }

        error.value = ''
        notice.value = ''
        form.phone = props.phone || ''
        form.contact_name = props.contextTitle || ''
        await loadChats()
    }
)

watch(selectedChatId, () => {
    fillFromSelected()
    loadMessages()
})

function close() {
    emit('update:modelValue', false)
}

function normalizePhone(value) {
    const digits = String(value || '').replace(/\D+/g, '')

    if (!digits) {
        return ''
    }

    if (digits.length === 11 && digits.startsWith('8')) {
        return `7${digits.slice(1)}`
    }

    if (digits.length === 10) {
        return `7${digits}`
    }

    return digits
}

function chatTitle(chat) {
    return [
        chat.contact_name || chat.title || chat.phone || chat.phone_normalized || `MAX #${chat.id}`,
        chat.chat_id ? `chat ${chat.chat_id}` : null,
        chat.user_id ? `user ${chat.user_id}` : null,
    ].filter(Boolean).join(' · ')
}

function fillFromSelected() {
    const chat = selectedChat.value

    if (!chat) {
        form.chat_id = ''
        form.user_id = ''
        form.title = ''
        return
    }

    form.phone = chat.phone || form.phone || props.phone || ''
    form.chat_id = chat.chat_id || ''
    form.user_id = chat.user_id || ''
    form.contact_name = chat.contact_name || form.contact_name || props.contextTitle || ''
    form.title = chat.title || ''
}

async function loadChats() {
    loading.value = true

    try {
        const params = normalizedPhone.value
            ? { phone: normalizedPhone.value, all: 1 }
            : { search: props.contextTitle, all: 1 }
        const { data } = await axios.get('/api/max/chats', { params })

        chats.value = data.data || []
        selectedChatId.value = chats.value[0]?.id || null

        if (!selectedChatId.value) {
            messages.value = []
            form.chat_id = ''
            form.user_id = ''
            form.title = ''
        }
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось загрузить MAX-привязку.'
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
            params: { limit: 30 },
        })

        messages.value = data.data || []
    } catch (err) {
        console.error(err)
    }
}

async function saveBinding() {
    saving.value = true
    error.value = ''
    notice.value = ''

    try {
        const payload = {
            phone: form.phone || props.phone || normalizedPhone.value,
            chat_id: form.chat_id || null,
            user_id: form.user_id || null,
            entity_id: props.entityId || null,
            unit_id: props.unitId || null,
            contact_name: form.contact_name || props.contextTitle || null,
            title: form.title || null,
            is_active: true,
        }
        const request = selectedChatId.value
            ? axios.put(`/api/max/chats/${selectedChatId.value}`, payload)
            : axios.post('/api/max/chats', payload)
        const { data } = await request

        const chat = data.data
        chats.value = [
            chat,
            ...chats.value.filter((item) => Number(item.id) !== Number(chat.id)),
        ]
        selectedChatId.value = chat.id
        notice.value = 'MAX-привязка сохранена.'

        return chat
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось сохранить MAX-привязку.'
        return null
    } finally {
        saving.value = false
    }
}

async function sendMessage() {
    error.value = ''
    notice.value = ''

    if (!form.chat_id && !form.user_id) {
        error.value = 'Укажите MAX chat_id или user_id для этого телефона.'
        return
    }

    if (!form.text.trim()) {
        error.value = 'Введите текст сообщения.'
        return
    }

    sending.value = true

    try {
        const chat = selectedChatId.value ? selectedChat.value : await saveBinding()

        const { data } = await axios.post('/api/max/messages/send', {
            max_chat_id: chat?.id || selectedChatId.value || null,
            phone: form.phone || props.phone || normalizedPhone.value,
            chat_id: form.chat_id || null,
            user_id: form.user_id || null,
            entity_id: props.entityId || null,
            unit_id: props.unitId || null,
            contact_name: form.contact_name || props.contextTitle || null,
            title: form.title || null,
            text: form.text,
        })

        const savedChat = data.data
        chats.value = [
            savedChat,
            ...chats.value.filter((item) => Number(item.id) !== Number(savedChat.id)),
        ]
        selectedChatId.value = savedChat.id
        form.text = ''
        notice.value = 'Сообщение отправлено в MAX.'
        emit('sent', data.max_message)
        await loadMessages()
    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Не удалось отправить сообщение в MAX.'
    } finally {
        sending.value = false
    }
}

function formatDate(value) {
    if (!value) {
        return ''
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
    <v-dialog
        :model-value="modelValue"
        max-width="760"
        scrollable
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card class="max-message-dialog">
            <v-card-title class="max-message-dialog__head">
                <div>
                    <span>MAX</span>
                    <strong>{{ contextTitle || form.contact_name || form.phone || 'Сообщение' }}</strong>
                </div>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="close"
                />
            </v-card-title>

            <v-card-text class="max-message-dialog__body">
                <v-alert
                    v-if="error"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mb-3"
                >
                    {{ error }}
                </v-alert>

                <v-alert
                    v-if="notice"
                    type="success"
                    variant="tonal"
                    density="compact"
                    class="mb-3"
                >
                    {{ notice }}
                </v-alert>

                <v-progress-linear
                    v-if="loading"
                    indeterminate
                    color="#800000"
                    class="mb-3"
                />

                <div class="max-message-dialog__grid">
                    <v-text-field
                        v-model="form.phone"
                        label="Телефон"
                        density="compact"
                        variant="outlined"
                        hide-details
                    />

                    <v-select
                        v-model="selectedChatId"
                        :items="chats"
                        :item-title="chatTitle"
                        item-value="id"
                        label="Локальная привязка"
                        density="compact"
                        variant="outlined"
                        clearable
                        hide-details
                    />

                    <v-text-field
                        v-model="form.chat_id"
                        label="MAX chat_id"
                        density="compact"
                        variant="outlined"
                        hide-details
                    />

                    <v-text-field
                        v-model="form.user_id"
                        label="MAX user_id"
                        density="compact"
                        variant="outlined"
                        hide-details
                    />

                    <v-text-field
                        v-model="form.contact_name"
                        label="Контакт"
                        density="compact"
                        variant="outlined"
                        hide-details
                    />

                    <v-text-field
                        v-model="form.title"
                        label="Название чата"
                        density="compact"
                        variant="outlined"
                        hide-details
                    />
                </div>

                <div class="max-message-dialog__actions">
                    <v-btn
                        color="#800000"
                        variant="tonal"
                        prepend-icon="mdi-content-save-outline"
                        :loading="saving"
                        @click="saveBinding"
                    >
                        Сохранить привязку
                    </v-btn>

                    <span v-if="normalizedPhone">Номер: +{{ normalizedPhone }}</span>
                </div>

                <v-textarea
                    v-model="form.text"
                    label="Сообщение в MAX"
                    rows="4"
                    auto-grow
                    counter="4000"
                    maxlength="4000"
                    variant="outlined"
                    class="mt-4"
                />

                <div class="max-message-dialog__send">
                    <v-btn
                        color="#800000"
                        prepend-icon="mdi-send"
                        :disabled="!canSend"
                        :loading="sending"
                        @click="sendMessage"
                    >
                        Отправить
                    </v-btn>
                </div>

                <div class="max-message-dialog__history">
                    <div class="max-message-dialog__history-title">
                        История
                    </div>

                    <div
                        v-if="!messages.length"
                        class="max-message-dialog__empty"
                    >
                        Локальных сообщений пока нет.
                    </div>

                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="max-message-dialog__message"
                        :class="`is-${message.direction}`"
                    >
                        <div>
                            <strong>{{ message.direction === 'incoming' ? 'Клиент' : 'Мы' }}</strong>
                            <span>{{ formatDate(message.sent_at || message.received_at || message.created_at) }}</span>
                            <em>{{ message.status }}</em>
                        </div>
                        <p>{{ message.text }}</p>
                        <small v-if="message.error_message">{{ message.error_message }}</small>
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.max-message-dialog {
    border: 1px solid rgba(128, 0, 0, 0.14);
}

.max-message-dialog__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    background: #4d1111;
    color: #fff;
}

.max-message-dialog__head div {
    display: grid;
    gap: 2px;
}

.max-message-dialog__head span {
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.12em;
}

.max-message-dialog__head strong {
    font-size: 1.1rem;
    line-height: 1.2;
}

.max-message-dialog__body {
    background: #fffdf8;
}

.max-message-dialog__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.max-message-dialog__actions,
.max-message-dialog__send {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 12px;
}

.max-message-dialog__actions span {
    color: #7a5d52;
    font-size: 0.78rem;
    font-weight: 800;
}

.max-message-dialog__history {
    display: grid;
    gap: 8px;
    margin-top: 16px;
}

.max-message-dialog__history-title {
    color: #3f1d1d;
    font-size: 0.82rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.max-message-dialog__empty {
    padding: 12px;
    border: 1px dashed rgba(128, 0, 0, 0.22);
    color: #8b6b61;
    font-size: 0.86rem;
}

.max-message-dialog__message {
    display: grid;
    gap: 4px;
    padding: 10px 12px;
    border: 1px solid rgba(64, 31, 20, 0.12);
    background: #fff;
}

.max-message-dialog__message.is-outgoing {
    border-left: 4px solid #800000;
}

.max-message-dialog__message.is-incoming {
    border-left: 4px solid #2f7d68;
}

.max-message-dialog__message div {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    color: #7a5d52;
    font-size: 0.74rem;
}

.max-message-dialog__message strong {
    color: #32140c;
}

.max-message-dialog__message p {
    margin: 0;
    color: #24180f;
    white-space: pre-wrap;
}

.max-message-dialog__message small {
    color: #a20f0f;
}

@media (max-width: 720px) {
    .max-message-dialog__grid {
        grid-template-columns: 1fr;
    }

    .max-message-dialog__actions,
    .max-message-dialog__send {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>
