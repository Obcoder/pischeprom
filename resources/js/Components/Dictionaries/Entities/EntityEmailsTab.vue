<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import MailComposerDialog from '@/Components/Contacts/Emails/MailComposerDialog.vue'
import MailMessageReaderDialog from '@/Components/Contacts/Emails/MailMessageReaderDialog.vue'

const props = defineProps({
    entity: {
        type: Object,
        default: null,
    },
})

const emails = computed(() => {
    return [...(props.entity?.emails || [])].sort((a, b) => {
        return String(a.address || '').localeCompare(String(b.address || ''), 'ru')
    })
})

const messages = ref([])
const totalMessages = ref(0)
const loadingMessages = ref(false)
const reading = ref(false)
const syncing = ref(false)
const readerDialog = ref(false)
const composerDialog = ref(false)
const selectedMessage = ref(null)
const replyContext = ref(null)
const initialTo = ref([])
const search = ref('')
const direction = ref(null)
const feedback = ref(null)

const directionItems = [
    { title: 'Все', value: null },
    { title: 'Входящие', value: 'incoming' },
    { title: 'Исходящие', value: 'outgoing' },
]

const recipientItems = computed(() => {
    return emails.value.map((email) => ({
        ...email,
        source_label: props.entity?.name ? `Entity: ${props.entity.name}` : 'Entity',
    }))
})

const storageSuggestions = computed(() => {
    return messages.value
        .flatMap((message) => message.attachments || [])
        .map((attachment) => attachment.path)
        .filter(Boolean)
})

function domain(email) {
    if (email?.domain) {
        return email.domain
    }

    return String(email?.address || '').split('@')[1] || '—'
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
}

function statusText(email) {
    return email?.is_active === false ? 'off' : 'active'
}

function messageContact(message) {
    if (message.direction === 'incoming') {
        return message.from_address || '—'
    }

    return (message.to || []).map((item) => item.address).join(', ') || '—'
}

async function fetchMessages() {
    if (!props.entity?.id) {
        messages.value = []
        totalMessages.value = 0
        return
    }

    loadingMessages.value = true

    try {
        const { data } = await axios.get('/api/mail-messages', {
            params: {
                search: search.value,
                page: 1,
                itemsPerPage: 80,
                filters: {
                    entity_id: props.entity.id,
                    direction: direction.value,
                },
            },
        })

        messages.value = data.data || []
        totalMessages.value = data.total || 0
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось загрузить письма Entity.',
        }
    } finally {
        loadingMessages.value = false
    }
}

async function readMessage(message, force = false) {
    if (!message?.id) {
        return
    }

    reading.value = true
    readerDialog.value = true

    try {
        const { data } = await axios.get(`/api/mail-messages/${message.id}`, {
            params: { force },
        })

        selectedMessage.value = data
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось прочитать письмо.',
        }
    } finally {
        reading.value = false
    }
}

async function forceReloadMessage() {
    if (selectedMessage.value) {
        await readMessage(selectedMessage.value, true)
    }
}

function updateSelectedMessage(message) {
    selectedMessage.value = message
    fetchMessages()
}

function openComposer(email = null) {
    replyContext.value = null
    initialTo.value = email?.address ? [email.address] : []
    composerDialog.value = true
}

function replyToMessage(message) {
    if (!message || message.direction !== 'incoming') {
        return
    }

    replyContext.value = message
    initialTo.value = message.from_address ? [message.from_address] : []
    readerDialog.value = false
    composerDialog.value = true
}

async function syncYandex() {
    syncing.value = true
    feedback.value = null

    try {
        await axios.post('/api/emails/sync-yandex', {
            limit: 1000,
        })

        feedback.value = {
            type: 'success',
            text: 'Синхронизация Yandex почты поставлена в очередь.',
        }
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось запустить синхронизацию.',
        }
    } finally {
        syncing.value = false
    }
}

async function afterSent() {
    composerDialog.value = false
    replyContext.value = null
    await fetchMessages()
}

let searchTimer = null

watch([search, direction], () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(fetchMessages, 300)
})

watch(() => props.entity?.id, fetchMessages)

onMounted(fetchMessages)
</script>

<template>
    <v-card class="entity-emails">
        <div class="entity-emails__head">
            <div>
                <span>Emails</span>
                <strong>{{ emails.length }}</strong>
            </div>

            <small>{{ entity?.name || 'Entity не выбрана' }}</small>
        </div>

        <div v-if="emails.length" class="entity-emails__sheet-wrap">
            <table class="entity-emails__sheet">
                <thead>
                    <tr>
                        <th class="entity-emails__rownum">#</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Domain</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>Last seen</th>
                        <th>Comment</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="(email, index) in emails" :key="email.id || email.address">
                        <td class="entity-emails__rownum">{{ index + 1 }}</td>
                        <td class="entity-emails__address">
                            <a :href="`mailto:${email.address}`">{{ email.address }}</a>
                        </td>
                        <td>{{ email.name || '—' }}</td>
                        <td>{{ domain(email) }}</td>
                        <td>{{ email.source || '—' }}</td>
                        <td>
                            <span
                                class="entity-emails__status"
                                :class="{ 'entity-emails__status--off': email.is_active === false }"
                            >
                                {{ statusText(email) }}
                            </span>
                        </td>
                        <td>{{ formatDate(email.verified_at) }}</td>
                        <td>{{ formatDate(email.last_seen_at) }}</td>
                        <td class="entity-emails__comment">{{ email.comment || '—' }}</td>
                        <td>
                            <button type="button" class="entity-emails__mini-action" @click="openComposer(email)">
                                write
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="entity-emails__empty">
            Emails для этой Entity не связаны.
        </div>

        <div class="entity-mails">
            <div class="entity-mails__toolbar">
                <div>
                    <span>Письма Entity</span>
                    <strong>{{ totalMessages }}</strong>
                </div>

                <div class="entity-mails__actions">
                    <v-text-field
                        v-model="search"
                        label="Поиск"
                        density="compact"
                        variant="outlined"
                        hide-details
                        clearable
                        class="entity-mails__search"
                    />

                    <v-select
                        v-model="direction"
                        :items="directionItems"
                        label="Тип"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="entity-mails__direction"
                    />

                    <v-btn
                        size="small"
                        variant="tonal"
                        color="#800000"
                        prepend-icon="mdi-sync"
                        :loading="syncing"
                        @click="syncYandex"
                    >
                        Sync Yandex
                    </v-btn>

                    <v-btn
                        size="small"
                        color="#800000"
                        prepend-icon="mdi-email-plus-outline"
                        @click="openComposer()"
                    >
                        Написать
                    </v-btn>
                </div>
            </div>

            <v-alert
                v-if="feedback"
                :type="feedback.type"
                density="compact"
                variant="tonal"
                class="ma-2"
            >
                {{ feedback.text }}
            </v-alert>

            <v-progress-linear v-if="loadingMessages" indeterminate color="#800000" />

            <div class="entity-mails__sheet-wrap">
                <table class="entity-mails__sheet">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Тип</th>
                            <th>Контакт</th>
                            <th>Тема</th>
                            <th>Файлы</th>
                            <th>CRM</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="message in messages" :key="message.id" @dblclick="readMessage(message)">
                            <td>{{ formatDate(message.message_date) }}</td>
                            <td>
                                <span class="entity-mails__direction-badge" :class="`is-${message.direction}`">
                                    {{ message.direction === 'incoming' ? 'in' : 'out' }}
                                </span>
                            </td>
                            <td>{{ messageContact(message) }}</td>
                            <td class="entity-mails__subject">
                                <strong>{{ message.subject || 'Без темы' }}</strong>
                                <small v-if="message.preview">{{ message.preview }}</small>
                            </td>
                            <td>{{ message.attachments_count || message.attachments?.length || (message.has_attachments ? '?' : 0) }}</td>
                            <td>{{ (message.notes_count || 0) + (message.leads_count || 0) }}</td>
                            <td class="entity-mails__row-actions">
                                <button type="button" @click="readMessage(message)">read</button>
                                <button
                                    v-if="message.direction === 'incoming'"
                                    type="button"
                                    @click="replyToMessage(message)"
                                >
                                    reply
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!messages.length && !loadingMessages">
                            <td colspan="7" class="entity-mails__empty">
                                Писем по emails этой Entity пока нет.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <MailMessageReaderDialog
            v-model="readerDialog"
            :message="selectedMessage"
            :loading="reading"
            :default-entity-id="entity?.id"
            @reload="forceReloadMessage"
            @reply="replyToMessage"
            @updated="updateSelectedMessage"
        />

        <MailComposerDialog
            v-model="composerDialog"
            :recipients="recipientItems"
            :initial-to="initialTo"
            :initial-storage-files="storageSuggestions"
            :reply-context="replyContext"
            :entity-id="entity?.id"
            @sent="afterSent"
        />
    </v-card>
</template>

<style scoped>
.entity-emails {
    overflow: hidden;
    border: 1px solid rgba(128, 0, 0, 0.18);
    border-radius: 18px;
    background: #fffdf8;
    box-shadow: 0 12px 30px rgba(48, 20, 10, 0.08);
}

.entity-emails__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(128, 0, 0, 0.18);
    background:
        linear-gradient(90deg, rgba(128, 0, 0, 0.14), rgba(128, 0, 0, 0.03)),
        #fff7ed;
}

.entity-emails__head div {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.entity-emails__head span {
    color: #3f1d1d;
    font-size: 0.78rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-emails__head strong {
    color: #800000;
    font-size: 1rem;
    font-weight: 950;
}

.entity-emails__head small {
    overflow: hidden;
    color: #7c5148;
    font-size: 0.68rem;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-emails__sheet-wrap {
    max-height: 590px;
    overflow: auto;
    background: #fff;
}

.entity-emails__sheet,
.entity-mails__sheet {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
    color: #2d1a12;
    font-size: 11px;
    line-height: 1.18;
}

.entity-emails__sheet th,
.entity-emails__sheet td,
.entity-mails__sheet th,
.entity-mails__sheet td {
    max-width: 240px;
    padding: 3px 5px;
    border-right: 1px solid rgba(128, 0, 0, 0.14);
    border-bottom: 1px solid rgba(128, 0, 0, 0.12);
    overflow: hidden;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-emails__sheet th,
.entity-mails__sheet th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #800000;
    color: #fff7ed;
    font-size: 10px;
    font-weight: 950;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.entity-emails__sheet tr:nth-child(even) td,
.entity-mails__sheet tr:nth-child(even) td {
    background: #fffaf4;
}

.entity-emails__sheet tr:hover td,
.entity-mails__sheet tr:hover td {
    background: #ffe8df;
}

.entity-emails__rownum {
    width: 42px;
    background: #f8eadf;
    color: #7c5148;
    font-weight: 900;
    text-align: right !important;
}

.entity-emails__sheet th.entity-emails__rownum {
    background: #5f0000;
    color: #fff;
}

.entity-emails__address a {
    color: #800000;
    font-weight: 900;
    text-decoration: none;
}

.entity-emails__address a:hover {
    text-decoration: underline;
}

.entity-emails__status {
    display: inline-flex;
    min-width: 42px;
    justify-content: center;
    padding: 1px 5px;
    border: 1px solid rgba(128, 0, 0, 0.22);
    border-radius: 2px;
    background: #f7d9ce;
    color: #800000;
    font-size: 10px;
    font-weight: 950;
}

.entity-emails__status--off {
    border-color: rgba(77, 63, 58, 0.28);
    background: #eee7e2;
    color: #6f625c;
}

.entity-emails__comment {
    max-width: 360px;
}

.entity-emails__empty {
    display: grid;
    min-height: 180px;
    place-items: center;
    color: #8b6b61;
    font-size: 0.82rem;
    font-weight: 800;
}

.entity-emails__mini-action,
.entity-mails__row-actions button {
    border: 1px solid rgba(128, 0, 0, 0.26);
    border-radius: 4px;
    background: #fff7ed;
    color: #800000;
    font-size: 10px;
    font-weight: 950;
    line-height: 1;
    padding: 3px 5px;
}

.entity-mails {
    border-top: 1px solid rgba(128, 0, 0, 0.14);
}

.entity-mails__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    background: #fffaf4;
}

.entity-mails__toolbar > div:first-child {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.entity-mails__toolbar span {
    color: #3f1d1d;
    font-size: 0.78rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-mails__toolbar strong {
    color: #800000;
}

.entity-mails__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
}

.entity-mails__search {
    width: 220px;
}

.entity-mails__direction {
    width: 140px;
}

.entity-mails__sheet-wrap {
    max-height: 460px;
    overflow: auto;
    background: #fff;
}

.entity-mails__direction-badge {
    display: inline-flex;
    min-width: 28px;
    justify-content: center;
    border-radius: 3px;
    padding: 2px 4px;
    font-size: 10px;
    font-weight: 950;
}

.entity-mails__direction-badge.is-incoming {
    background: #f7d9ce;
    color: #800000;
}

.entity-mails__direction-badge.is-outgoing {
    background: #e7dacd;
    color: #4b2418;
}

.entity-mails__subject {
    display: grid;
    gap: 2px;
}

.entity-mails__subject strong,
.entity-mails__subject small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-mails__subject small {
    color: #8b6b61;
}

.entity-mails__row-actions {
    display: flex;
    gap: 4px;
}

.entity-mails__empty {
    padding: 22px !important;
    color: #8b6b61;
    font-weight: 800;
    text-align: center !important;
}

@media (max-width: 760px) {
    .entity-emails__head {
        align-items: flex-start;
        flex-direction: column;
    }

    .entity-emails__sheet-wrap {
        max-height: 520px;
    }

    .entity-mails__toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .entity-mails__actions,
    .entity-mails__search,
    .entity-mails__direction {
        width: 100%;
    }
}
</style>
