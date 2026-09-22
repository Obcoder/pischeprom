<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'
import { useMailMessages } from '@/Composables/useMailMessages.js'
import MailMessagesToolbar from './MailMessagesToolbar.vue'
import MailDateFilter from './MailDateFilter.vue'
import { mailDateRangeLabel } from './mailDateFilters.js'
import MailMessagesTable from './MailMessagesTable.vue'
import MailMessageReaderDialog from './MailMessageReaderDialog.vue'
import MailComposerDialog from './MailComposerDialog.vue'
import MailTemplatesDialog from './MailTemplatesDialog.vue'
import MailboxesManagerDialog from './MailboxesManagerDialog.vue'

const props = defineProps({
    timezone: { type: String, default: 'Europe/Moscow' },
    standalone: {
        type: Boolean,
        default: false,
    },
})

const {
    messages,
    totalItems,
    loading,
    reading,
    markingReadIds,
    markReadError,
    markReadStatus,
    fetchError,
    markMessageRead,
    selectedMessage,
    mailboxes,
    search,
    filters,
    options,
    fetchMailboxes,
    fetchMessages,
    readMessage,
    clearSelectedMessage,
} = useMailMessages()

const PRICE_REQUEST_SUBJECT = 'ПИЩЕПРОМ-СЕРВЕР: запрос прайса'

const readerDialog = ref(false)
const composerDialog = ref(false)
const mailboxesDialog = ref(false)
const templatesDialog = ref(false)
const replyContext = ref(null)
const activeView = ref('all')
let autoRefreshTimer = null

const tableHeight = computed(() => props.standalone ? '100%' : 720)

const mailboxSubtitle = computed(() => {
    if (!mailboxes.value.length) {
        return 'Все входящие и исходящие письма настроенных ящиков'
    }

    return `Ящики: ${mailboxes.value.map((mailbox) => mailbox.address).join(' / ')}`
})

const viewTabs = [
    {
        title: 'Все письма',
        value: 'all',
        icon: 'mdi-email-multiple-outline',
    },
    {
        title: 'Запросы прайса',
        value: 'price_requests',
        icon: 'mdi-file-document-arrow-right-outline',
    },
]

const viewDescription = computed(() => {
    if (activeView.value === 'price_requests') {
        return `Показаны только исходящие письма с точной темой: ${PRICE_REQUEST_SUBJECT}`
    }

    return 'Общая серверная выборка писем по всем активным фильтрам.'
})

const hasResettableFilters = computed(() => Boolean(
    search.value || filters.value.mailbox || filters.value.folder || filters.value.today
    || filters.value.date_from || filters.value.date_to
    || (activeView.value !== 'price_requests' && (filters.value.direction || filters.value.subject_exact))
))
const dateDescription = computed(() => filters.value.date_from || filters.value.date_to
    ? mailDateRangeLabel(filters.value.date_from, filters.value.date_to)
    : '')
const statusError = computed(() => markReadError.value || fetchError.value)

async function openMessage(message) {
    readerDialog.value = true
    await readMessage(message)
}

async function openInitialMessageFromUrl() {
    if (typeof window === 'undefined') {
        return
    }

    const mailMessageId = new URLSearchParams(window.location.search).get('mail_message_id')

    if (!mailMessageId) {
        return
    }

    readerDialog.value = true
    await readMessage({ id: mailMessageId })
}

async function forceReloadMessage() {
    if (selectedMessage.value) {
        await readMessage(selectedMessage.value, true)
    }
}

function updateSelectedMessage(message) {
    selectedMessage.value = message
}

function openComposer() {
    replyContext.value = null
    composerDialog.value = true
}

function openMailboxes() {
    mailboxesDialog.value = true
}

function replyToMessage(message) {
    if (!message || message.direction !== 'incoming') {
        return
    }

    replyContext.value = message
    readerDialog.value = false
    composerDialog.value = true
}

async function afterSent() {
    composerDialog.value = false
    replyContext.value = null
    await fetchMessages()
}

async function afterMailboxesChanged() {
    await Promise.all([
        fetchMailboxes(),
        fetchMessages(),
    ])
}

async function deleteMessage(message) {
    if (!message?.id) {
        return
    }

    if (!window.confirm(`Удалить письмо "${message.subject || 'Без темы'}" из базы?`)) {
        return
    }

    try {
        await axios.delete(`/api/mail-messages/${message.id}`)
    } catch (error) {
        window.alert(error?.response?.data?.message || 'Не удалось удалить письмо.')

        return
    }

    if (selectedMessage.value?.id === message.id) {
        readerDialog.value = false
        clearSelectedMessage()
    }

    await fetchMessages()
}

function applyView(value) {
    options.value.page = 1

    if (value === 'price_requests') {
        filters.value.direction = 'outgoing'
        filters.value.subject_exact = PRICE_REQUEST_SUBJECT

        return
    }

    filters.value.subject_exact = null
    filters.value.direction = null
}

function resetFilters() {
    search.value = ''
    options.value.page = 1
    filters.value = {
        ...filters.value,
        direction: activeView.value === 'price_requests' ? 'outgoing' : null,
        folder: null,
        mailbox: null,
        email_id: null,
        today: false,
        date_from: null,
        date_to: null,
        subject_exact: activeView.value === 'price_requests' ? PRICE_REQUEST_SUBJECT : null,
    }
}

onMounted(async () => {
    applyView(activeView.value)

    await Promise.all([
        fetchMailboxes(),
        fetchMessages(),
    ])

    await openInitialMessageFromUrl()

    autoRefreshTimer = window.setInterval(() => {
        fetchMessages()
    }, 30000)
})

onUnmounted(() => {
    if (autoRefreshTimer) {
        window.clearInterval(autoRefreshTimer)
    }
})

watch(activeView, (value) => {
    applyView(value)
})
</script>

<template>
    <v-card
        class="mail-messages-card rounded border border-blue-900 bg-slate-950"
        :class="{ 'mail-messages-card--standalone': standalone }"
    >
        <div class="mail-heading">
            <div class="mail-topbar">
                <h1 class="mail-heading__title font-ComfortaaVariableFont" :title="mailboxSubtitle">Письма</h1>
                <v-chip size="x-small" color="blue" variant="tonal" :title="`Всего писем: ${totalItems}`">{{ totalItems }}</v-chip>

                <div class="mail-view-tabs" role="group" aria-label="Вид писем">
                    <v-btn
                        v-for="item in viewTabs"
                        :key="item.value"
                        :prepend-icon="item.icon"
                        :color="activeView === item.value ? 'blue-lighten-2' : 'blue-grey-lighten-3'"
                        :variant="activeView === item.value ? 'tonal' : 'text'"
                        :aria-pressed="activeView === item.value"
                        size="small"
                        @click="activeView = item.value"
                    >{{ item.title }}</v-btn>
                </div>

                <MailDateFilter v-model:filters="filters" :timezone="timezone" />
                <v-btn
                    icon="mdi-refresh"
                    size="28"
                    color="blue-lighten-2"
                    variant="text"
                    :loading="loading"
                    title="Обновить письма"
                    aria-label="Обновить письма"
                    @click="fetchMessages"
                />
                <div class="mail-topbar__spacer" />
                <v-btn icon="mdi-mailbox-outline" size="28" color="cyan" variant="text" title="Почтовые ящики" aria-label="Почтовые ящики" @click="openMailboxes" />
                <v-btn icon="mdi-file-document-edit-outline" size="28" color="blue-grey-lighten-2" variant="text" title="Шаблоны" aria-label="Шаблоны" @click="templatesDialog = true" />
                <v-btn class="mail-compose-launcher" size="small" variant="flat" prepend-icon="mdi-email-plus-outline" @click="openComposer">Написать письмо</v-btn>
            </div>
            <div class="mail-status-line" :class="{ 'mail-status-line--error': statusError }" role="status" aria-live="polite">
                <span :title="statusError || markReadStatus || viewDescription">{{ statusError || markReadStatus || viewDescription }}</span>
                <span v-if="dateDescription" class="mail-status-line__dates">{{ dateDescription }}</span>
            </div>
        </div>

        <div class="mail-messages-content">
            <MailMessagesToolbar
                v-model:search="search"
                v-model:filters="filters"
                :mailboxes="mailboxes"
                :locked-direction="activeView === 'price_requests'"
                :has-filters="hasResettableFilters"
                @reset="resetFilters"
            />
            <MailMessagesTable
                class="mail-messages-content__table"
                :messages="messages"
                :total-items="totalItems"
                :loading="loading"
                :options="options"
                :mailboxes="mailboxes"
                :height="tableHeight"
                :marking-read-ids="markingReadIds"
                @update:options="options = $event"
                @read="openMessage"
                @mark-read="markMessageRead"
                @delete="deleteMessage"
            />
        </div>
    </v-card>

    <MailMessageReaderDialog
        v-model="readerDialog"
        :message="selectedMessage"
        :loading="reading"
        @reload="forceReloadMessage"
        @reply="replyToMessage"
        @updated="updateSelectedMessage"
    />

    <MailComposerDialog
        v-model="composerDialog"
        :mailboxes="mailboxes"
        :reply-context="replyContext"
        @sent="afterSent"
    />

    <MailboxesManagerDialog
        v-model="mailboxesDialog"
        @changed="afterMailboxesChanged"
    />

    <MailTemplatesDialog v-model="templatesDialog" />
</template>

<style scoped>
.mail-messages-card {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.mail-messages-card--standalone {
    flex: 1 1 0;
    min-height: 0;
    overflow: hidden;
}
.mail-heading { flex: 0 0 auto; padding: 8px 10px 5px; border-bottom: 1px solid rgba(96, 165, 250, .18); }
.mail-topbar { display: flex; align-items: center; gap: 6px; overflow-x: auto; scrollbar-width: thin; }
.mail-topbar > * { flex-shrink: 0; }
.mail-heading__title { color: #93c5fd; font-size: 17px; margin-right: 1px; }
.mail-topbar__spacer { flex: 1 0 0; }
.mail-view-tabs { display: flex; gap: 2px; padding-inline: 6px; border-inline: 1px solid rgba(147, 197, 253, .18); }
.mail-view-tabs :deep(.v-btn), .mail-compose-launcher { min-width: 0; height: 28px; padding: 0 8px; font-size: 11px; letter-spacing: 0; text-transform: none; }
.mail-status-line { display: flex; align-items: center; gap: 12px; min-height: 18px; padding-top: 3px; color: #94a3b8; font-size: 10px; line-height: 15px; }
.mail-status-line > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mail-status-line--error { color: #fca5a5; }
.mail-status-line__dates { flex-shrink: 0; color: #fcd34d; }
.mail-messages-content { display: flex; flex: 1 1 0; flex-direction: column; gap: 8px; min-height: 0; padding: 10px 10px 0; }
.mail-messages-content__table { min-height: 0; }
.mail-messages-card--standalone .mail-messages-content__table { flex: 1 1 0; }
.mail-compose-launcher { border: 1px solid rgba(125, 211, 252, .6); background: linear-gradient(135deg, #0ea5e9, #1d4ed8) !important; color: #fff !important; font-weight: 700; }
@media (max-width: 700px) {
    .mail-heading, .mail-messages-content { padding-inline: 6px; }
    .mail-status-line__dates { font-size: 9px; }
}
</style>
