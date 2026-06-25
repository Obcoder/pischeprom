<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import axios from 'axios'
import { useMailMessages } from '@/Composables/useMailMessages.js'
import MailMessagesToolbar from './MailMessagesToolbar.vue'
import MailMessagesTable from './MailMessagesTable.vue'
import MailMessageReaderDialog from './MailMessageReaderDialog.vue'
import MailComposerDialog from './MailComposerDialog.vue'
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

async function openMessage(message) {
    readerDialog.value = true
    await readMessage(message)
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

onMounted(async () => {
    applyView(activeView.value)

    await Promise.all([
        fetchMailboxes(),
        fetchMessages(),
    ])

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
</style>
