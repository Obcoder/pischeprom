<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    contexts: { type: Array, default: () => [] },
    messageId: { type: [Number, String], default: null },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])
const menuOpen = ref(false)
const selectedContextKey = ref(null)
const search = ref('')
const appliedSearch = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const messages = ref([])
const loading = ref(false)
const error = ref('')
const pageSize = 20
let requestController = null
let requestVersion = 0
let searchTimer = null

const availableContexts = computed(() => {
    const contexts = props.contexts
        .filter((context) => ['entity', 'unit'].includes(context?.type)
            && Number.isSafeInteger(Number(context.id)) && Number(context.id) > 0)
        .map((context) => ({
            ...context,
            id: Number(context.id),
            key: `${context.type}-${context.id}`,
            title: `${context.type === 'entity' ? 'Контрагент' : 'Подразделение'} · ${context.name || `#${context.id}`}`,
        }))

    return Array.from(new Map(contexts.map((context) => [context.key, context])).values())
})

const selectedContext = computed(() => availableContexts.value
    .find((context) => context.key === selectedContextKey.value) || null)

const rangeLabel = computed(() => {
    if (!total.value) return 'Всего: 0'

    const first = (page.value - 1) * pageSize + 1
    const last = Math.min(first + messages.value.length - 1, total.value)

    return `${first}–${last} из ${total.value}`
})

function cancelRequest() {
    requestVersion += 1
    requestController?.abort()
    requestController = null
}

async function fetchMessages() {
    cancelRequest()

    const context = selectedContext.value
    if (!menuOpen.value || !context) return

    const version = requestVersion
    const controller = new AbortController()
    requestController = controller
    loading.value = true
    error.value = ''
    messages.value = []

    try {
        const { data } = await axios.get('/api/mail-messages', {
            signal: controller.signal,
            params: {
                filters: { [`${context.type}_id`]: context.id },
                search: appliedSearch.value,
                page: page.value,
                itemsPerPage: pageSize,
            },
        })

        if (version !== requestVersion) return

        messages.value = Array.isArray(data.data) ? data.data : []
        total.value = Math.max(0, Number(data.total) || 0)
        lastPage.value = Math.max(1, Number(data.last_page) || Math.ceil(total.value / pageSize))

        if (page.value > lastPage.value) {
            page.value = lastPage.value
        }
    } catch (requestError) {
        if (version !== requestVersion || axios.isCancel(requestError)) return

        error.value = 'Не удалось загрузить письма. Попробуйте ещё раз.'
        total.value = 0
        lastPage.value = 1
    } finally {
        if (version === requestVersion) {
            loading.value = false
            requestController = null
        }
    }
}

function openMessage(message) {
    if (!message?.id || loading.value || props.disabled) return

    menuOpen.value = false
    emit('open', message)
}

function formatDate(value) {
    if (!value) return 'Дата не указана'

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return 'Дата не указана'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    }).format(date)
}

function senderLabel(message) {
    return [message.from_name, message.from_address].filter(Boolean).join(' · ') || 'Отправитель не указан'
}

function recipientLabel(message) {
    return (message.to || [])
        .map((recipient) => recipient.name || recipient.address)
        .filter(Boolean)
        .join(', ')
}

watch(availableContexts, (contexts) => {
    if (!contexts.some((context) => context.key === selectedContextKey.value)) {
        selectedContextKey.value = contexts[0]?.key || null
    }

    if (!contexts.length) menuOpen.value = false
}, { immediate: true })

watch(selectedContextKey, () => {
    clearTimeout(searchTimer)
    searchTimer = null
    search.value = ''
    appliedSearch.value = ''
    page.value = 1
    total.value = 0
    lastPage.value = 1
})

watch(search, (value) => {
    const hadPendingSearch = searchTimer !== null
    clearTimeout(searchTimer)
    searchTimer = null
    const nextSearch = String(value || '').trim()
    if (nextSearch === appliedSearch.value) {
        if (hadPendingSearch && menuOpen.value) fetchMessages()
        return
    }

    cancelRequest()
    loading.value = menuOpen.value

    searchTimer = setTimeout(() => {
        searchTimer = null
        page.value = 1
        appliedSearch.value = nextSearch
    }, 300)
})

watch([menuOpen, selectedContextKey, appliedSearch, page], ([isOpen]) => {
    if (!isOpen || !selectedContext.value) {
        clearTimeout(searchTimer)
        searchTimer = null
        cancelRequest()
        loading.value = false
        if (appliedSearch.value !== String(search.value || '').trim()) {
            page.value = 1
            appliedSearch.value = String(search.value || '').trim()
        }
        return
    }

    fetchMessages()
}, { flush: 'post' })

watch(() => props.disabled, (disabled) => {
    if (disabled) menuOpen.value = false
})

onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    cancelRequest()
})
</script>

<template>
    <v-menu
        v-if="availableContexts.length"
        v-model="menuOpen"
        :close-on-content-click="false"
        location="bottom end"
        :offset="6"
        :max-width="680"
        content-class="mail-related-messages-overlay"
    >
        <template #activator="{ props: activatorProps }">
            <v-btn
                v-bind="activatorProps"
                class="mail-related-messages-activator"
                :disabled="disabled"
                size="small"
                density="compact"
                variant="tonal"
                color="blue-lighten-3"
                prepend-icon="mdi-email-multiple-outline"
                append-icon="mdi-chevron-down"
                title="Все письма связанного контрагента или подразделения"
                aria-label="Связанные письма"
            >
                Связанные письма
            </v-btn>
        </template>

        <v-card class="mail-related-messages" height="min(580px, 70dvh)" role="region" aria-label="Связанные письма">
            <div class="mail-related-messages__header">
                <div class="mail-related-messages__heading">
                    <strong>Связанные письма</strong>
                    <span>Все входящие и исходящие · все папки</span>
                </div>
                <v-btn
                    icon="mdi-close"
                    size="x-small"
                    density="comfortable"
                    variant="text"
                    title="Закрыть список писем"
                    aria-label="Закрыть список писем"
                    @click="menuOpen = false"
                />
            </div>

            <div class="mail-related-messages__filters">
                <v-select
                    v-if="availableContexts.length > 1"
                    v-model="selectedContextKey"
                    :items="availableContexts"
                    item-title="title"
                    item-value="key"
                    label="Переписка с"
                    density="compact"
                    variant="outlined"
                    hide-details
                    :menu-props="{ maxHeight: 260 }"
                />
                <div v-else class="mail-related-messages__context" :title="selectedContext?.title">
                    <v-icon :icon="selectedContext?.type === 'entity' ? 'mdi-domain' : 'mdi-factory'" size="16" />
                    <span>{{ selectedContext?.title }}</span>
                </div>
                <v-text-field
                    v-model="search"
                    placeholder="Поиск по переписке"
                    aria-label="Поиск по связанным письмам"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    density="compact"
                    variant="outlined"
                    hide-details
                />
            </div>

            <div class="mail-related-messages__list" :aria-busy="loading">
                <div v-if="loading" class="mail-related-messages__state" role="status">
                    <v-progress-circular indeterminate size="22" width="2" color="blue-lighten-3" />
                    <span>Загрузка писем…</span>
                </div>
                <div v-else-if="error" class="mail-related-messages__state" role="alert">
                    <span>{{ error }}</span>
                    <v-btn size="small" variant="tonal" prepend-icon="mdi-refresh" @click="fetchMessages">
                        Повторить
                    </v-btn>
                </div>
                <div v-else-if="!messages.length" class="mail-related-messages__state" role="status">
                    <v-icon icon="mdi-email-search-outline" size="26" />
                    <span>{{ appliedSearch ? 'По этому запросу писем нет' : 'Связанных писем пока нет' }}</span>
                </div>
                <template v-else>
                    <button
                        v-for="message in messages"
                        :key="message.id"
                        type="button"
                        class="mail-related-messages__message"
                        :class="{ 'mail-related-messages__message--current': String(message.id) === String(messageId) }"
                        :aria-current="String(message.id) === String(messageId) ? 'true' : undefined"
                        :disabled="disabled"
                        @click="openMessage(message)"
                    >
                        <span class="mail-related-messages__message-meta">
                            <span
                                class="mail-related-messages__direction"
                                :class="{ 'mail-related-messages__direction--outgoing': message.direction === 'outgoing' }"
                            >
                                <v-icon :icon="message.direction === 'outgoing' ? 'mdi-arrow-top-right' : 'mdi-arrow-bottom-left'" size="14" />
                                {{ message.direction === 'outgoing' ? 'Исходящее' : 'Входящее' }}
                            </span>
                            <span v-if="String(message.id) === String(messageId)" class="mail-related-messages__current-label">Открыто</span>
                            <time class="mail-related-messages__date" :datetime="message.message_date">{{ formatDate(message.message_date) }}</time>
                        </span>
                        <span class="mail-related-messages__subject" :title="message.subject || 'Без темы'">
                            <v-icon v-if="message.has_attachments" icon="mdi-paperclip" size="14" />
                            <span>{{ message.subject || 'Без темы' }}</span>
                        </span>
                        <span class="mail-related-messages__sender" :title="senderLabel(message)">{{ senderLabel(message) }}</span>
                        <span v-if="message.direction === 'outgoing' && recipientLabel(message)" class="mail-related-messages__recipient" :title="recipientLabel(message)">
                            Кому: {{ recipientLabel(message) }}
                        </span>
                    </button>
                </template>
            </div>

            <div class="mail-related-messages__footer">
                <span aria-live="polite">{{ loading ? 'Загрузка…' : rangeLabel }}</span>
                <div class="mail-related-messages__pages">
                    <v-btn
                        icon="mdi-chevron-left"
                        size="x-small"
                        density="comfortable"
                        variant="text"
                        :disabled="loading || !!error || page <= 1"
                        title="Предыдущая страница"
                        aria-label="Предыдущая страница"
                        @click="page -= 1"
                    />
                    <span>{{ page }} / {{ lastPage }}</span>
                    <v-btn
                        icon="mdi-chevron-right"
                        size="x-small"
                        density="comfortable"
                        variant="text"
                        :disabled="loading || !!error || page >= lastPage"
                        title="Следующая страница"
                        aria-label="Следующая страница"
                        @click="page += 1"
                    />
                </div>
            </div>
        </v-card>
    </v-menu>
</template>

<style scoped>
.mail-related-messages-activator {
    text-transform: none;
    letter-spacing: 0;
}

.v-card.mail-related-messages {
    display: flex;
    flex-direction: column;
    width: min(680px, calc(100vw - 24px));
    max-width: 100%;
    max-height: inherit;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, .2);
    border-radius: 12px;
}

.mail-related-messages__header,
.mail-related-messages__footer {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 9px 12px;
}

.mail-related-messages__heading {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 2px;
}

.mail-related-messages__heading strong { font-size: 13px; font-weight: 600; }
.mail-related-messages__heading > span { font-size: 10px; opacity: .6; }

.mail-related-messages__filters {
    display: grid;
    flex: 0 0 auto;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    padding: 0 12px 10px;
}

.mail-related-messages__filters :deep(.v-field) { font-size: 12px; }
.mail-related-messages__context { display: flex; min-width: 0; align-items: center; gap: 6px; font-size: 12px; }
.mail-related-messages__context > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.mail-related-messages__list {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    overscroll-behavior: contain;
    border-block: 1px solid rgba(148, 163, 184, .16);
}

.mail-related-messages__state { display: flex; min-height: 110px; align-items: center; justify-content: center; flex-direction: column; gap: 12px; padding: 20px; text-align: center; font-size: 12px; opacity: .85; }

.mail-related-messages__message {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 4px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(148, 163, 184, .1);
    background: transparent;
    color: inherit;
    text-align: left;
    cursor: pointer;
}

.mail-related-messages__message:hover { background: rgba(96, 165, 250, .08); }
.mail-related-messages__message:focus-visible { outline: 2px solid #60a5fa; outline-offset: -2px; }
.mail-related-messages__message--current { background: rgba(96, 165, 250, .12); box-shadow: inset 3px 0 #60a5fa; }
.mail-related-messages__message-meta { display: flex; align-items: center; gap: 8px; font-size: 10px; }
.mail-related-messages__direction { display: inline-flex; align-items: center; gap: 3px; color: #c4b5fd; }
.mail-related-messages__direction--outgoing { color: #93c5fd; }
.mail-related-messages__current-label { color: #93c5fd; }
.mail-related-messages__date { margin-left: auto; white-space: nowrap; opacity: .62; font-variant-numeric: tabular-nums; }
.mail-related-messages__subject { display: flex; min-width: 0; align-items: center; gap: 4px; font-size: 12px; font-weight: 500; }
.mail-related-messages__subject > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mail-related-messages__sender,
.mail-related-messages__recipient { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; opacity: .68; }
.mail-related-messages__recipient { opacity: .52; }
.mail-related-messages__footer { font-size: 11px; }
.mail-related-messages__pages { display: flex; align-items: center; gap: 5px; font-variant-numeric: tabular-nums; }

@media (max-width: 480px) {
    .mail-related-messages__filters { grid-template-columns: minmax(0, 1fr); }
    .mail-related-messages__message-meta { gap: 5px; }
    .mail-related-messages__current-label { display: none; }
}
</style>
