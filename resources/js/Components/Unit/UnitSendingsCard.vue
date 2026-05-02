<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'
import MailMessageReaderDialog from '@/Components/Contacts/Emails/MailMessageReaderDialog.vue'
import UnitMailComposerDialog from '@/Components/Unit/Mail/UnitMailComposerDialog.vue'
import UnitMailTemplatesDialog from '@/Components/Unit/Mail/UnitMailTemplatesDialog.vue'
import { useUnitMail } from '@/Composables/useUnitMail.js'
import { useUnitFiles } from '@/Composables/useUnitFiles.js'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
})

const {
    messages,
    relatedEmails,
    totalItems,
    loading,
    sending,
    reading,
    selectedMessage,
    search,
    direction,
    options,
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

let autoRefreshTimer = null

const headers = [
    {
        title: 'Тип',
        key: 'direction',
        sortable: false,
        width: '84px',
    },
    {
        title: 'Дата',
        key: 'message_date',
        sortable: false,
        width: '140px',
    },
    {
        title: 'Контакт',
        key: 'contact',
        sortable: false,
        width: '260px',
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
        width: '70px',
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

const visibleRelatedEmails = computed(() => {
    return relatedEmails.value.slice(0, 5)
})

const hiddenRelatedEmailsCount = computed(() => {
    return Math.max(relatedEmails.value.length - visibleRelatedEmails.value.length, 0)
})

function formatDate(value) {
    if (!value) return '—'

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

async function forceReloadMessage() {
    if (selectedMessage.value) {
        await readMessage(selectedMessage.value, true)
    }
}

async function afterSent() {
    composerDialog.value = false
    await fetchMessages()
}

onMounted(async () => {
    await Promise.all([
        fetchMessages(),
        loadFiles(),
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
    <BaseSectionCard
        title="Mail"
        icon="mdi-email-fast-outline"
        compact
    >
        <template #actions>
            <div class="d-flex ga-1">
                <v-btn
                    icon="mdi-file-document-edit-outline"
                    size="small"
                    variant="text"
                    color="blue"
                    @click="templatesDialog = true"
                />

                <v-btn
                    icon="mdi-refresh"
                    size="small"
                    variant="text"
                    color="teal"
                    :loading="loading"
                    @click="fetchMessages"
                />

                <v-btn
                    icon="mdi-email-plus-outline"
                    size="small"
                    variant="text"
                    color="blue"
                    @click="composerDialog = true"
                />
            </div>
        </template>

        <div class="mb-3">
            <div class="text-caption text-medium-emphasis">
                Письма по emails Unit и emails связанных Entities
            </div>

            <div class="d-flex flex-wrap ga-1 mt-1 unit-mail-related-emails">
                <v-chip
                    v-for="email in visibleRelatedEmails"
                    :key="email.address"
                    size="x-small"
                    color="blue"
                    variant="tonal"
                >
                    {{ email.address }}
                </v-chip>

                <v-chip
                    v-if="hiddenRelatedEmailsCount"
                    size="x-small"
                    color="grey"
                    variant="tonal"
                >
                    +{{ hiddenRelatedEmailsCount }}
                </v-chip>
            </div>
        </div>

        <v-row dense class="mb-3">
            <v-col cols="12" lg="8">
                <v-text-field
                    v-model="search"
                    label="Поиск по письмам"
                    prepend-inner-icon="mdi-magnify"
                    variant="solo"
                    density="compact"
                    clearable
                    hide-details
                />
            </v-col>

            <v-col cols="12" lg="4">
                <v-select
                    v-model="direction"
                    :items="directionItems"
                    label="Тип"
                    variant="outlined"
                    density="compact"
                    hide-details
                />
            </v-col>
        </v-row>

        <v-data-table-server
            :headers="headers"
            :items="messages"
            :items-length="totalItems"
            :loading="loading"
            :page="options.page"
            :items-per-page="options.itemsPerPage"
            item-value="id"
            density="compact"
            fixed-header
            height="340"
            hover
            class="rounded border border-blue-900 bg-slate-950"
            @update:options="options = $event"
            @click:row="(_, row) => openMessage(row.item)"
        >
            <template #item.direction="{ item }">
                <v-chip
                    size="x-small"
                    :color="item.direction === 'incoming' ? 'purple' : 'blue'"
                    variant="tonal"
                >
                    {{ item.direction === 'incoming' ? '↓ in' : '↑ out' }}
                </v-chip>
            </template>

            <template #item.message_date="{ item }">
                <span class="text-[10px] font-mono">
                    {{ formatDate(item.message_date) }}
                </span>

                <div class="text-[9px] text-grey">
                    {{ item.folder }}
                </div>
            </template>

            <template #item.contact="{ item }">
                <div v-if="item.direction === 'incoming'">
                    <div class="text-purple-lighten-3 text-xs">
                        {{ item.from_address || '—' }}
                    </div>

                    <div
                        v-if="item.from_name"
                        class="text-[10px] text-grey"
                    >
                        {{ item.from_name }}
                    </div>
                </div>

                <div v-else class="text-[10px] text-grey-lighten-1">
                    <div
                        v-for="line in recipients(item)"
                        :key="line"
                    >
                        {{ line }}
                    </div>
                </div>
            </template>

            <template #item.subject="{ item }">
                <div class="py-1 cursor-pointer">
                    <div class="text-sm text-blue-lighten-4 hover:text-white">
                        {{ item.subject || 'Без темы' }}
                    </div>

                    <div
                        v-if="item.preview"
                        class="text-[10px] text-grey-lighten-1 line-clamp-2 mt-1"
                    >
                        {{ item.preview }}
                    </div>
                </div>
            </template>

            <template #item.actions="{ item }">
                <v-btn
                    icon="mdi-email-open-outline"
                    size="x-small"
                    variant="text"
                    color="blue"
                    @click.stop="openMessage(item)"
                />
            </template>
        </v-data-table-server>

        <UnitMailComposerDialog
            v-model="composerDialog"
            :unit-id="unit.id"
            :recipients="relatedEmails"
            :unit-files="files"
            :sending="sending"
            @sent="afterSent"
            @open-templates="templatesDialog = true"
        />

        <UnitMailTemplatesDialog v-model="templatesDialog" />

        <MailMessageReaderDialog
            v-model="readerDialog"
            :message="selectedMessage"
            :loading="reading"
            @reload="forceReloadMessage"
        />
    </BaseSectionCard>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.unit-mail-related-emails {
    max-height: 24px;
    overflow: hidden;
}
</style>
