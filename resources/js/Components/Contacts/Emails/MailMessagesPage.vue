<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'
import { useMailMessages } from '@/Composables/useMailMessages.js'
import MailMessagesToolbar from './MailMessagesToolbar.vue'
import MailMessagesTable from './MailMessagesTable.vue'
import MailMessageReaderDialog from './MailMessageReaderDialog.vue'
import MailComposerDialog from './MailComposerDialog.vue'
import MailTemplatesDialog from './MailTemplatesDialog.vue'
import MailboxesManagerDialog from './MailboxesManagerDialog.vue'

const props = defineProps({
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

const tableHeight = computed(() => props.standalone ? 'calc(100vh - 350px)' : 720)

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

const activeFilterChips = computed(() => {
    const chips = []

    if (search.value) {
        chips.push({
            key: 'search',
            label: `Поиск: ${search.value}`,
            color: 'blue',
            closable: true,
        })
    }

    if (filters.value.mailbox) {
        const mailbox = mailboxes.value.find((item) => item.address === filters.value.mailbox)

        chips.push({
            key: 'mailbox',
            label: `Ящик: ${mailbox?.label || filters.value.mailbox}`,
            color: 'cyan',
            closable: true,
        })
    }

    if (filters.value.direction) {
        chips.push({
            key: 'direction',
            label: filters.value.direction === 'incoming' ? 'Тип: входящие' : 'Тип: исходящие',
            color: filters.value.direction === 'incoming' ? 'purple' : 'blue',
            closable: activeView.value !== 'price_requests',
        })
    }

    if (filters.value.folder) {
        chips.push({
            key: 'folder',
            label: `Папка: ${filters.value.folder}`,
            color: filters.value.folder === 'Sent' ? 'blue' : 'purple',
            closable: true,
        })
    }

    if (filters.value.today) {
        chips.push({
            key: 'today',
            label: 'Почта сегодня',
            color: 'amber',
            closable: true,
        })
    }

    if (filters.value.subject_exact && activeView.value !== 'price_requests') {
        chips.push({
            key: 'subject_exact',
            label: `Тема: ${filters.value.subject_exact}`,
            color: 'blue-grey',
            closable: true,
        })
    }

    return chips
})

const hasResettableFilters = computed(() => activeFilterChips.value.some((chip) => chip.closable))

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
        subject_exact: activeView.value === 'price_requests' ? PRICE_REQUEST_SUBJECT : null,
    }
}

function clearFilter(key) {
    options.value.page = 1

    if (key === 'search') {
        search.value = ''

        return
    }

    if (key === 'direction' && activeView.value === 'price_requests') {
        return
    }

    filters.value = {
        ...filters.value,
        [key]: key === 'today' ? false : null,
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
        <v-card-title class="d-flex justify-space-between align-center py-2">
            <div>
                <div class="text-blue-lighten-3 font-ComfortaaVariableFont">
                    Письма
                </div>

                <div class="text-[10px] text-grey">
                    {{ mailboxSubtitle }}
                </div>
            </div>

            <div class="d-flex align-center ga-2">
                <v-btn
                    size="small"
                    color="cyan"
                    variant="tonal"
                    prepend-icon="mdi-email-cog-outline"
                    @click="openMailboxes"
                >
                    Почтовые ящики
                </v-btn>

                <v-btn
                    size="small"
                    color="blue-grey"
                    variant="tonal"
                    prepend-icon="mdi-file-document-edit-outline"
                    @click="templatesDialog = true"
                >
                    Шаблоны
                </v-btn>

                <v-btn
                    class="mail-compose-launcher"
                    size="small"
                    variant="flat"
                    prepend-icon="mdi-email-plus-outline"
                    @click="openComposer"
                >
                    Написать письмо
                </v-btn>

                <v-chip
                    size="small"
                    color="blue"
                    variant="tonal"
                >
                    {{ totalItems }}
                </v-chip>
            </div>
        </v-card-title>

        <v-divider />

        <v-card-text>
            <v-tabs
                v-model="activeView"
                density="compact"
                color="blue-lighten-2"
                class="mail-view-tabs mb-3"
            >
                <v-tab
                    v-for="item in viewTabs"
                    :key="item.value"
                    :value="item.value"
                    :prepend-icon="item.icon"
                >
                    {{ item.title }}
                </v-tab>
            </v-tabs>

            <div class="mail-view-context mb-3">
                <div class="mail-view-context__text">
                    {{ viewDescription }}
                </div>

                <v-btn
                    v-if="hasResettableFilters"
                    size="x-small"
                    variant="text"
                    color="blue-lighten-3"
                    prepend-icon="mdi-filter-remove-outline"
                    @click="resetFilters"
                >
                    Сбросить фильтры
                </v-btn>
            </div>

            <div
                v-if="activeFilterChips.length"
                class="mail-active-filters mb-3"
            >
                <v-chip
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    size="x-small"
                    :color="chip.color"
                    variant="tonal"
                    :closable="chip.closable"
                    @click:close="clearFilter(chip.key)"
                >
                    {{ chip.label }}
                </v-chip>
            </div>

            <MailMessagesToolbar
                v-model:search="search"
                v-model:filters="filters"
                :mailboxes="mailboxes"
                :loading="loading"
                :locked-direction="activeView === 'price_requests'"
                @refresh="fetchMessages"
            />

            <MailMessagesTable
                class="mt-3"
                :messages="messages"
                :total-items="totalItems"
                :loading="loading"
                :options="options"
                :mailboxes="mailboxes"
                :height="tableHeight"
                @update:options="options = $event"
                @read="openMessage"
                @delete="deleteMessage"
            />
        </v-card-text>
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
}

.mail-messages-card--standalone {
    min-height: calc(100vh - 96px);
}

.mail-messages-card :deep(.v-card-text) {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
}

.mail-compose-launcher {
    border: 1px solid rgba(125, 211, 252, 0.6);
    background:
        linear-gradient(135deg, rgba(14, 165, 233, 0.92), rgba(29, 78, 216, 0.92)) !important;
    color: #fff !important;
    font-weight: 900;
    letter-spacing: 0.05em;
}

.mail-view-tabs {
    border-bottom: 1px solid rgba(96, 165, 250, 0.18);
}

.mail-view-context {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 28px;
    padding: 6px 10px;
    border: 1px solid rgba(96, 165, 250, 0.16);
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.58);
}

.mail-view-context__text {
    color: rgba(191, 219, 254, 0.82);
    font-size: 11px;
}

.mail-active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
</style>
