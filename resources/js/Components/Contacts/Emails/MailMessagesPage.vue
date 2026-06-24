<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useMailMessages } from '@/Composables/useMailMessages.js'
import MailMessagesToolbar from './MailMessagesToolbar.vue'
import MailMessagesTable from './MailMessagesTable.vue'
import MailMessageReaderDialog from './MailMessageReaderDialog.vue'
import MailComposerDialog from './MailComposerDialog.vue'
import MailboxesManagerDialog from './MailboxesManagerDialog.vue'

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
} = useMailMessages()

const readerDialog = ref(false)
const composerDialog = ref(false)
const mailboxesDialog = ref(false)
const replyContext = ref(null)
let autoRefreshTimer = null

const mailboxSubtitle = computed(() => {
    if (!mailboxes.value.length) {
        return 'Все входящие и исходящие письма настроенных ящиков'
    }

    return `Ящики: ${mailboxes.value.map((mailbox) => mailbox.address).join(' / ')}`
})

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

onMounted(async () => {
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
</script>

<template>
    <v-card class="rounded border border-blue-900 bg-slate-950">
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
                    Ящики
                </v-btn>

                <v-btn
                    size="small"
                    color="blue"
                    variant="tonal"
                    prepend-icon="mdi-email-plus-outline"
                    @click="openComposer"
                >
                    Написать
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
            <MailMessagesToolbar
                v-model:search="search"
                v-model:filters="filters"
                :mailboxes="mailboxes"
                :loading="loading"
                @refresh="fetchMessages"
            />

            <MailMessagesTable
                class="mt-3"
                :messages="messages"
                :total-items="totalItems"
                :loading="loading"
                :options="options"
                @update:options="options = $event"
                @read="openMessage"
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
