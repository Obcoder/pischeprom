<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import MailRelatedMessagesMenu from './MailRelatedMessagesMenu.vue'

const props = defineProps({
    message: {
        type: Object,
        default: null,
    },
    loading: Boolean,
    relatedDisabled: Boolean,
    feedback: {
        type: Object,
        default: null,
    },
    contexts: {
        type: Array,
        default: () => [],
    },
    syncError: {
        type: String,
        default: null,
    },
})

const emit = defineEmits(['reload', 'reply', 'close', 'open'])

const incoming = computed(() => props.message?.direction === 'incoming')
const mailboxAddress = computed(() => String(props.message?.mailbox || '').trim() || 'Ящик не определён')
const mailboxLabel = computed(() => incoming.value ? 'На ящик' : 'С ящика')
const sender = computed(() => formatRecipient({
    name: props.message?.from_name,
    address: props.message?.from_address,
}) || '—')
const recipients = computed(() => formatRecipients(props.message?.to))
const copies = computed(() => formatRecipients(props.message?.cc))
const messageDate = computed(() => {
    if (!props.message?.message_date) return '—'

    const date = new Date(props.message.message_date)
    if (Number.isNaN(date.getTime())) return '—'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
})
const syncNotice = computed(() => props.syncError || props.message?.mail_sync_error || null)

function formatRecipient(recipient) {
    if (typeof recipient === 'string') return recipient.trim()
    if (!recipient || typeof recipient !== 'object') return ''

    const name = String(recipient.name || '').trim()
    const address = String(recipient.address || '').trim()

    return name && address && name !== address ? `${name} <${address}>` : address || name
}

function formatRecipients(list) {
    return (Array.isArray(list) ? list : []).map(formatRecipient).filter(Boolean).join(', ')
}

function contextLabel(context) {
    return context.type === 'unit' ? 'Подразделение' : 'Контрагент'
}
</script>

<template>
    <header class="mail-reader-header">
        <div class="mail-reader-header__main">
            <h2 class="mail-reader-header__subject" :title="message?.subject || 'Без темы'">
                {{ message?.subject || 'Без темы' }}
            </h2>

            <div class="mail-reader-header__meta">
                <span
                    class="mail-reader-header__direction"
                    :class="{ 'mail-reader-header__direction--incoming': incoming }"
                >
                    <v-icon :icon="incoming ? 'mdi-inbox-arrow-down-outline' : 'mdi-send-outline'" size="14" />
                    {{ incoming ? 'Входящее' : 'Исходящее' }}
                </span>

                <time class="mail-reader-header__date" :datetime="message?.message_date || undefined">
                    {{ messageDate }}
                </time>

                <span class="mail-reader-header__mailbox" :title="`${mailboxLabel}: ${mailboxAddress}`">
                    <span class="mail-reader-header__label">{{ mailboxLabel }}:</span>
                    <strong>{{ mailboxAddress }}</strong>
                </span>
            </div>

            <div class="mail-reader-header__addresses">
                <span class="mail-reader-header__address mail-reader-header__from" :title="`От: ${sender}`">
                    <span class="mail-reader-header__label">От:</span>
                    <span>{{ sender }}</span>
                </span>
                <span class="mail-reader-header__address mail-reader-header__to" :title="`Кому: ${recipients || '—'}`">
                    <span class="mail-reader-header__label">Кому:</span>
                    <span>{{ recipients || '—' }}</span>
                </span>
                <span
                    v-if="copies"
                    class="mail-reader-header__address mail-reader-header__cc"
                    :title="`Копия: ${copies}`"
                >
                    <span class="mail-reader-header__label">Копия:</span>
                    <span>{{ copies }}</span>
                </span>
            </div>

            <nav v-if="contexts.length" class="mail-reader-header__contexts" aria-label="Связанные контрагенты и подразделения">
                <Link
                    v-for="context in contexts"
                    :key="`${context.type}:${context.id}`"
                    :href="context.href"
                    class="mail-reader-header__context"
                    :title="`${contextLabel(context)}: ${context.name}`"
                >
                    <v-icon :icon="context.type === 'unit' ? 'mdi-office-building-marker-outline' : 'mdi-domain'" size="14" />
                    <span class="mail-reader-header__context-label">{{ contextLabel(context) }}:</span>
                    <span class="mail-reader-header__context-name">{{ context.name }}</span>
                    <v-icon icon="mdi-arrow-top-right" size="12" />
                </Link>
            </nav>

            <div v-if="feedback || syncNotice" class="mail-reader-header__notices" role="status" aria-live="polite">
                <div
                    v-if="feedback"
                    class="mail-reader-header__notice"
                    :class="`mail-reader-header__notice--${feedback.type}`"
                    :title="feedback.text"
                >
                    <v-icon :icon="feedback.type === 'success' ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'" size="14" />
                    <span>{{ feedback.text }}</span>
                </div>
                <div
                    v-if="syncNotice"
                    class="mail-reader-header__notice mail-reader-header__notice--warning"
                    :title="syncNotice"
                >
                    <v-icon icon="mdi-alert-outline" size="14" />
                    <span>{{ syncNotice }}</span>
                </div>
            </div>
        </div>

        <div class="mail-reader-header__actions" role="group" aria-label="Действия с письмом">
            <MailRelatedMessagesMenu
                v-if="contexts.length"
                :contexts="contexts"
                :message-id="message?.id"
                :disabled="loading || relatedDisabled"
                @open="emit('open', $event)"
            />

            <v-btn
                icon="mdi-refresh"
                size="small"
                density="comfortable"
                variant="text"
                color="blue"
                title="Обновить письмо"
                aria-label="Обновить письмо"
                :loading="loading"
                @click="emit('reload')"
            />
            <v-btn
                v-if="incoming"
                icon="mdi-reply"
                size="small"
                density="comfortable"
                variant="text"
                color="teal"
                title="Ответить на письмо"
                aria-label="Ответить на письмо"
                :disabled="loading"
                @click="emit('reply', message)"
            />
            <v-btn
                icon="mdi-close"
                size="small"
                density="comfortable"
                variant="text"
                title="Закрыть письмо"
                aria-label="Закрыть письмо"
                @click="emit('close')"
            />
        </div>
    </header>
</template>

<style scoped>
.mail-reader-header {
    align-items: flex-start;
    display: flex;
    flex: 0 0 auto;
    gap: 8px 16px;
    min-width: 0;
    padding: 8px 10px;
}

.mail-reader-header__main {
    flex: 1 1 auto;
    max-height: min(180px, 28dvh);
    min-width: 0;
    overflow: auto;
    scrollbar-width: thin;
}

.mail-reader-header__subject {
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    color: #bfdbfe;
    display: -webkit-box;
    font-size: 15px;
    font-weight: 650;
    line-height: 1.3;
    margin: 0;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.mail-reader-header__meta,
.mail-reader-header__addresses,
.mail-reader-header__contexts,
.mail-reader-header__notices {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    min-width: 0;
}

.mail-reader-header__meta {
    color: #94a3b8;
    font-size: 11px;
    gap: 3px 12px;
    line-height: 18px;
    margin-top: 3px;
}

.mail-reader-header__direction,
.mail-reader-header__mailbox {
    align-items: center;
    display: inline-flex;
    gap: 4px;
    min-width: 0;
}

.mail-reader-header__direction {
    color: #93c5fd;
    flex: 0 0 auto;
}

.mail-reader-header__direction--incoming {
    color: #d8b4fe;
}

.mail-reader-header__date {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.mail-reader-header__mailbox {
    max-width: 100%;
}

.mail-reader-header__mailbox strong {
    color: #a5f3fc;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-reader-header__label {
    color: #94a3b8;
    flex-shrink: 0;
    font-weight: 400;
}

.mail-reader-header__addresses {
    font-size: 11px;
    gap: 2px 14px;
    line-height: 17px;
    margin-top: 1px;
}

.mail-reader-header__address {
    align-items: baseline;
    display: inline-flex;
    gap: 4px;
    max-width: 100%;
    min-width: 0;
}

.mail-reader-header__address > span:last-child {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-reader-header__from {
    color: #cbd5e1;
}

.mail-reader-header__to,
.mail-reader-header__cc {
    color: #93c5fd;
}

.mail-reader-header__contexts {
    gap: 4px 6px;
    margin-top: 5px;
    max-height: 52px;
    overflow-y: auto;
    scrollbar-width: thin;
}

.mail-reader-header__context {
    align-items: center;
    background: rgba(59, 130, 246, 0.09);
    border: 1px solid rgba(96, 165, 250, 0.2);
    border-radius: 5px;
    color: #bfdbfe;
    display: inline-flex;
    font-size: 11px;
    gap: 4px;
    line-height: 20px;
    max-width: min(440px, 100%);
    min-width: 0;
    padding: 0 6px;
    text-decoration: none;
}

.mail-reader-header__context:hover {
    background: rgba(59, 130, 246, 0.18);
    border-color: rgba(96, 165, 250, 0.5);
}

.mail-reader-header__context:focus-visible {
    outline: 2px solid #93c5fd;
    outline-offset: 1px;
}

.mail-reader-header__context-label {
    color: #94a3b8;
    flex: 0 0 auto;
}

.mail-reader-header__context-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-reader-header__notices {
    gap: 4px;
    margin-top: 4px;
}

.mail-reader-header__notice {
    align-items: center;
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 5px;
    color: #cbd5e1;
    display: inline-flex;
    font-size: 11px;
    gap: 4px;
    line-height: 16px;
    max-width: 100%;
    min-width: 0;
    padding: 1px 6px;
}

.mail-reader-header__notice span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-reader-header__notice--success {
    background: rgba(20, 184, 166, 0.12);
    border-color: rgba(45, 212, 191, 0.3);
    color: #5eead4;
}

.mail-reader-header__notice--error,
.mail-reader-header__notice--warning {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(251, 191, 36, 0.32);
    color: #fbbf24;
}

.mail-reader-header__actions {
    align-items: center;
    display: flex;
    flex: 0 0 auto;
    gap: 2px;
    justify-content: flex-end;
    max-width: 100%;
}

@media (max-width: 760px) {
    .mail-reader-header {
        flex-wrap: wrap;
        gap: 5px;
        padding: 6px 8px;
    }

    .mail-reader-header__main {
        flex-basis: 100%;
        max-height: min(150px, 26dvh);
    }

    .mail-reader-header__actions {
        margin-left: auto;
    }

    .mail-reader-header__meta {
        gap: 2px 8px;
    }

    .mail-reader-header__context-label {
        display: none;
    }
}
</style>
