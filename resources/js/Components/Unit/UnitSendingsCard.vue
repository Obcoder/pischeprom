<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'
import MailMessageReaderDialog from '@/Components/Contacts/Emails/MailMessageReaderDialog.vue'
import MailTemplatesDialog from '@/Components/Contacts/Emails/MailTemplatesDialog.vue'
import UnitMailComposerDialog from '@/Components/Unit/Mail/UnitMailComposerDialog.vue'
import { useUnitMail } from '@/Composables/useUnitMail.js'
import { useUnitFiles } from '@/Composables/useUnitFiles.js'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
    canSend: Boolean,
})

const {
    messages,
    error,
    relatedEmails,
    totalItems,
    loading,
    sending,
    reading,
    selectedMessage,
    mailboxes,
    search,
    direction,
    mailbox,
    options,
    fetchMailboxes,
    fetchMessages,
    readMessage,
} = useUnitMail(props.unit.id)

const {
    files,
    loadFiles,
} = useUnitFiles(props.unit.id)

const readerDialog = ref(false)
const composerDialog = ref(false)
const templatesDialog = ref(false)
const replyContext = ref(null)
const initialTo = ref([])

let autoRefreshTimer = null

const headers = [
    {
        title: 'Тип',
        key: 'direction',
        sortable: false,
        width: '54px',
    },
    {
        title: 'Дата',
        key: 'message_date',
        sortable: false,
        width: '112px',
    },
    {
        title: 'Контакт',
        key: 'contact',
        sortable: false,
        width: '180px',
    },
    {
        title: 'Тема',
        key: 'subject',
        sortable: false,
    },
    {
        title: '',
        key: 'actions',
        sortable: false,
        align: 'end',
        width: '72px',
    },
]

const directionItems = [
    {
        title: 'Все',
        value: null,
    },
    {
        title: 'Входящие',
        value: 'incoming',
    },
    {
        title: 'Исходящие',
        value: 'outgoing',
    },
]

const mailboxItems = computed(() => [
    {
        title: 'Все ящики',
        value: null,
    },
    ...mailboxes.value.map((item) => ({
        title: item.label || item.address,
        value: item.address,
    })),
])

function formatDate(value) {
    if (!value || Number.isNaN(new Date(value).getTime())) return '—'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function recipients(item) {
    const to = item.to?.map((recipient) => recipient.address).join(', ')
    const cc = item.cc?.map((recipient) => recipient.address).join(', ')

    return [
        to ? `To: ${to}` : null,
        cc ? `CC: ${cc}` : null,
    ].filter(Boolean)
}

async function openMessage(message) {
    readerDialog.value = true
    await readMessage(message)
}

function openNewMessage(address = null) {
    if (!props.canSend) return
    initialTo.value = typeof address === 'string' ? [address] : []
    replyContext.value = null
    composerDialog.value = true
    loadFiles()
}

defineExpose({ openNewMessage, refresh: fetchMessages })

async function replyToTableMessage(message) {
    const loadedMessage = await readMessage(message)
    if (loadedMessage) replyToMessage(loadedMessage)
}

function replyToMessage(message) {
    if (!props.canSend || !message || message.direction !== 'incoming') {
        return
    }

    initialTo.value = []
    replyContext.value = message
    loadFiles()
    readerDialog.value = false
    composerDialog.value = true
}

async function forceReloadMessage() {
    if (selectedMessage.value) {
        await readMessage(selectedMessage.value, true)
    }
}

async function afterSent() {
    composerDialog.value = false
    replyContext.value = null
    await fetchMessages()
}

function updateSelectedMessage(message) {
    selectedMessage.value = message
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
    <BaseSectionCard title="Emails" icon="mdi-email-outline" header-color="default" compact class="unit-mail-card">
        <template #actions>
            <span class="unit-mail-total">{{ totalItems }}</span>
            <v-btn v-if="canSend" icon="mdi-file-document-edit-outline" size="x-small" variant="text" aria-label="Шаблоны писем" title="Шаблоны писем" @click="templatesDialog = true" />
            <v-btn icon="mdi-refresh" size="x-small" variant="text" :loading="loading" aria-label="Обновить письма" title="Обновить письма" @click="fetchMessages" />
            <v-btn v-if="canSend" icon="mdi-email-plus-outline" size="x-small" variant="text" aria-label="Написать письмо" title="Написать письмо" @click="openNewMessage()" />
        </template>

        <div v-if="error" class="unit-mail-error" role="alert">{{ error }}</div>
        <div class="unit-mail-filters">
            <v-text-field v-model="search" label="Поиск по письмам" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" clearable hide-details />
            <v-select v-model="mailbox" :items="mailboxItems" label="Ящик" variant="outlined" density="compact" hide-details />
            <v-select v-model="direction" :items="directionItems" label="Направление" variant="outlined" density="compact" hide-details />
        </div>

        <v-data-table-server
            :headers="headers"
            :items="messages"
            :items-length="totalItems"
            :loading="loading"
            :page="options.page"
            :items-per-page="options.itemsPerPage"
            :items-per-page-options="[15, 25, 50]"
            item-value="id"
            density="compact"
            fixed-header
            :hide-default-footer="!totalItems"
            hover
            no-data-text="Переписки пока нет"
            loading-text="Загрузка писем…"
            items-per-page-text="На странице"
            class="unit-mail-table"
            @update:options="options = $event"
            @click:row="(_, row) => openMessage(row.item)"
        >
            <template #item.direction="{ item }">
                <span class="unit-mail-direction" :class="{ 'is-outgoing': item.direction !== 'incoming' }">
                    {{ item.direction === 'incoming' ? '↓ Вх' : '↑ Исх' }}
                </span>
            </template>
            <template #item.message_date="{ item }">
                <time class="unit-mail-date">{{ formatDate(item.message_date) }}</time>
                <div class="unit-mail-muted">{{ item.mailbox || '—' }}</div>
            </template>
            <template #item.contact="{ item }">
                <div v-if="item.direction === 'incoming'">
                    <div class="unit-mail-address">{{ item.from_address || '—' }}</div>
                    <div v-if="item.from_name" class="unit-mail-muted">{{ item.from_name }}</div>
                </div>
                <div v-else class="unit-mail-address">
                    <div v-for="line in recipients(item)" :key="line">{{ line }}</div>
                </div>
            </template>
            <template #item.subject="{ item }">
                <div class="unit-mail-subject">
                    <strong>{{ item.subject || 'Без темы' }}</strong>
                    <p v-if="item.preview">{{ item.preview }}</p>
                </div>
            </template>
            <template #item.actions="{ item }">
                <v-btn v-if="canSend && item.direction === 'incoming'" icon="mdi-reply" size="x-small" variant="text" aria-label="Ответить на письмо" title="Ответить" @click.stop="replyToTableMessage(item)" />
                <v-btn icon="mdi-email-open-outline" size="x-small" variant="text" aria-label="Открыть письмо" title="Открыть письмо" @click.stop="openMessage(item)" />
            </template>
        </v-data-table-server>

        <UnitMailComposerDialog v-if="canSend" v-model="composerDialog" :unit-id="unit.id" :recipients="relatedEmails" :initial-to="initialTo" :mailboxes="mailboxes" :unit-files="files" :reply-context="replyContext" :sending="sending" @sent="afterSent" />
        <MailTemplatesDialog v-if="canSend" v-model="templatesDialog" />
        <MailMessageReaderDialog v-model="readerDialog" :message="selectedMessage" :loading="reading" :default-unit-id="unit.id" @reload="forceReloadMessage" @reply="replyToMessage" @updated="updateSelectedMessage" />
    </BaseSectionCard>
</template>

<style scoped>
.unit-mail-card { display: flex; flex-direction: column; min-height: 0; border: 1px solid #d6d3d9 !important; border-radius: 0 !important; box-shadow: none !important; color: #252329; }
.unit-mail-card :deep(.base-section-card__header) { flex-shrink: 0; padding: 5px 10px; min-height: 42px; border-bottom: 1px solid #e4e2e6; }
.unit-mail-card :deep(.base-section-card__title) { font-size: 12px; }
.unit-mail-card :deep(.base-section-card__body) { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; padding: 10px; }
.unit-mail-total { color: #79737e; font-size: 11px; font-variant-numeric: tabular-nums; }
.unit-mail-filters { display: grid; flex-shrink: 0; grid-template-columns: minmax(150px, 1.4fr) minmax(100px, 1fr) minmax(110px, 1fr); gap: 8px; margin-bottom: 10px; }
.unit-mail-table { flex: 1; width: 100%; max-width: 100%; min-width: 0; min-height: 0; font-size: 11px; border-top: 1px solid #e4e2e6; }
.unit-mail-table :deep(.v-table__wrapper) { flex: 1; min-height: 0; max-width: 100%; overflow: auto; }
.unit-mail-table :deep(th) { font-size: 10px; color: #77727c; }
.unit-mail-table :deep(td) { padding: 5px 8px !important; }
.unit-mail-table :deep(.v-data-table-footer) { flex-shrink: 0; padding: 6px 0 0; font-size: 11px; gap: 8px; }
.unit-mail-direction { font-size: 10px; color: #382447; white-space: nowrap; }
.unit-mail-direction.is-outgoing { color: #6b2032; }
.unit-mail-date { font-size: 10px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.unit-mail-muted { color: #79737e; font-size: 10px; overflow-wrap: anywhere; }
.unit-mail-address { color: #382447; font-size: 11px; overflow-wrap: anywhere; }
.unit-mail-subject { min-width: 140px; padding: 3px 0; }
.unit-mail-subject strong { font-size: 11px; font-weight: 600; }
.unit-mail-subject p { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #79737e; font-size: 10px; margin: 3px 0 0; }
.unit-mail-error { flex-shrink: 0; padding: 8px; margin-bottom: 8px; border: 1px solid #b98a94; color: #6b2032; font-size: 12px; }
@media (max-width: 1100px), (max-height: 640px) { .unit-mail-card { height: min(640px, 80dvh); min-height: 300px; } }
@media (max-width: 600px) { .unit-mail-filters { grid-template-columns: 1fr 1fr; } .unit-mail-filters > :first-child { grid-column: 1 / -1; } }
</style>
