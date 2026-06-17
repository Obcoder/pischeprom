<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useMailMessages } from '@/Composables/useMailMessages.js'
import MailMessagesToolbar from './MailMessagesToolbar.vue'
import MailMessagesTable from './MailMessagesTable.vue'
import MailMessageReaderDialog from './MailMessageReaderDialog.vue'
import MailComposerDialog from './MailComposerDialog.vue'

const {
    messages,
    totalItems,
    loading,
    reading,
    selectedMessage,
    search,
    filters,
    options,
    fetchMessages,
    readMessage,
} = useMailMessages()

const readerDialog = ref(false)
const composerDialog = ref(false)
const replyContext = ref(null)
let autoRefreshTimer = null

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

onMounted(async () => {
    await fetchMessages()

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
                    Все входящие и исходящие письма office@180022.ru
                </div>
            </div>

            <div class="d-flex align-center ga-2">
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
        :reply-context="replyContext"
        @sent="afterSent"
    />
</template>
