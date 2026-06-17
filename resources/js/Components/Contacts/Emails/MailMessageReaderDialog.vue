<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    message: {
        type: Object,
        default: null,
    },
    loading: Boolean,
    defaultEntityId: {
        type: Number,
        default: null,
    },
    defaultUnitId: {
        type: Number,
        default: null,
    },
})

const emit = defineEmits([
    'reload',
    'reply',
    'updated',
])

const actionLoading = ref(false)
const noteTitle = ref('')
const noteBody = ref('')
const noteImportance = ref('important')
const leadTitle = ref('')
const leadDescription = ref('')
const feedback = ref(null)
const savingAttachmentIndex = ref(null)
const selectedAttachmentIndex = ref(null)

const bodyHtml = computed(() => {
    return props.message?.html || null
})

const bodyText = computed(() => {
    return props.message?.text || 'Тело письма не загружено или письмо пустое.'
})

const attachments = computed(() => props.message?.attachments || [])
const availableAttachments = computed(() => props.message?.available_attachments || [])
const attachmentRows = computed(() => {
    if (availableAttachments.value.length) {
        return availableAttachments.value
    }

    return attachments.value.map((attachment, index) => ({
        ...attachment,
        index,
        is_saved: true,
        is_image: isAttachmentImage(attachment),
        preview_url: isAttachmentImage(attachment) ? attachment.url : null,
    }))
})
const imageAttachments = computed(() => attachmentRows.value.filter(isAttachmentImage))
const selectedAttachment = computed(() => {
    return attachmentRows.value.find((attachment) => Number(attachment.index) === Number(selectedAttachmentIndex.value))
        || imageAttachments.value[0]
        || attachmentRows.value[0]
        || null
})
const selectedImageAttachment = computed(() => {
    return selectedAttachment.value && isAttachmentImage(selectedAttachment.value)
        ? selectedAttachment.value
        : imageAttachments.value[0] || null
})
const notes = computed(() => props.message?.notes || [])
const leads = computed(() => props.message?.leads || [])
const hasAttachmentSignal = computed(() => Boolean(props.message?.has_attachments || attachmentRows.value.length || attachments.value.length))

function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function recipients(list) {
    return list?.map((item) => item.address).join(', ') || '—'
}

function formatSize(value) {
    const size = Number(value || 0)

    if (!size) {
        return '—'
    }

    if (size < 1024 * 1024) {
        return `${Math.round(size / 1024)} KB`
    }

    return `${(size / 1024 / 1024).toFixed(2)} MB`
}

function attachmentName(attachment) {
    return attachment?.original_name || attachment?.file_name || attachment?.name || 'attachment'
}

function attachmentMime(attachment) {
    return attachment?.mime_type || attachment?.mime || 'file'
}

function isAttachmentImage(attachment) {
    const mime = String(attachment?.mime_type || attachment?.mime || '').toLowerCase()
    const name = attachmentName(attachment)

    return Boolean(attachment?.is_image)
        || mime.startsWith('image/')
        || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name)
}

function selectAttachment(attachment) {
    selectedAttachmentIndex.value = attachment?.index ?? null
}

function plainBody() {
    return (props.message?.text || String(props.message?.html || '').replace(/<[^>]+>/g, ' ') || props.message?.preview || '').trim()
}

function resetForms() {
    noteTitle.value = ''
    noteBody.value = ''
    noteImportance.value = 'important'
    leadTitle.value = props.message?.subject || 'Лид из письма'
    leadDescription.value = [
        `From: ${[props.message?.from_name, props.message?.from_address].filter(Boolean).join(' ')}`,
        `Subject: ${props.message?.subject || 'Без темы'}`,
        plainBody().slice(0, 1800),
    ].filter(Boolean).join('\n\n')
    feedback.value = null
    savingAttachmentIndex.value = null
    selectedAttachmentIndex.value = imageAttachments.value[0]?.index ?? attachmentRows.value[0]?.index ?? null
}

async function saveAttachment(attachment) {
    if (!props.message?.id || attachment?.index === null || attachment?.index === undefined || attachment?.is_saved) {
        return
    }

    savingAttachmentIndex.value = attachment.index
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${props.message.id}/attachments/${attachment.index}/save`)

        emit('updated', data)
        selectedAttachmentIndex.value = attachment.index
        feedback.value = {
            type: 'success',
            text: `Файл "${attachmentName(attachment)}" сохранён в Yandex S3.`,
        }
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось сохранить файл.',
        }
    } finally {
        savingAttachmentIndex.value = null
    }
}

async function saveNote() {
    if (!props.message?.id || !noteBody.value.trim()) {
        return
    }

    actionLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${props.message.id}/notes`, {
            title: noteTitle.value,
            body: noteBody.value,
            importance: noteImportance.value,
        })

        emit('updated', data)
        noteTitle.value = ''
        noteBody.value = ''
        feedback.value = {
            type: 'success',
            text: 'Важная информация сохранена.',
        }
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось сохранить информацию.',
        }
    } finally {
        actionLoading.value = false
    }
}

async function createLead() {
    if (!props.message?.id || !leadTitle.value.trim()) {
        return
    }

    actionLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${props.message.id}/lead`, {
            title: leadTitle.value,
            description: leadDescription.value,
            entity_id: props.defaultEntityId,
            unit_id: props.defaultUnitId,
        })

        emit('updated', data.mail_message)
        feedback.value = {
            type: 'success',
            text: `Лид #${data.lead?.id} создан.`,
        }
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось создать лид.',
        }
    } finally {
        actionLoading.value = false
    }
}

watch(() => props.message?.id, resetForms)
</script>

<template>
    <v-dialog
        v-model="model"
        width="1200"
        scrollable
    >
        <v-card class="rounded border border-blue-900 bg-slate-950">
            <v-card-title class="d-flex justify-space-between align-start">
                <div>
                    <div class="text-blue-lighten-3 text-base">
                        {{ message?.subject || 'Без темы' }}
                    </div>

                    <div class="text-[11px] text-grey mt-1">
                        {{ formatDate(message?.message_date) }}
                    </div>
                </div>

                <div class="d-flex ga-1">
                    <v-chip
                        size="small"
                        :color="message?.direction === 'incoming' ? 'purple' : 'blue'"
                        variant="tonal"
                    >
                        {{ message?.direction === 'incoming' ? 'Входящее' : 'Исходящее' }}
                    </v-chip>

                    <v-btn
                        icon="mdi-refresh"
                        size="small"
                        variant="text"
                        color="blue"
                        :loading="loading"
                        @click="emit('reload')"
                    />

                    <v-btn
                        v-if="message?.direction === 'incoming'"
                        icon="mdi-reply"
                        size="small"
                        variant="text"
                        color="teal"
                        :disabled="loading"
                        @click="emit('reply', message)"
                    />
                </div>
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row dense>
                    <v-col cols="12" lg="6">
                        <div class="text-[11px] text-grey">From</div>
                        <div class="text-sm text-purple-lighten-3">
                            {{ message?.from_name || '' }}
                            {{ message?.from_address || '—' }}
                        </div>
                    </v-col>

                    <v-col cols="12" lg="6">
                        <div class="text-[11px] text-grey">To</div>
                        <div class="text-sm text-blue-lighten-3">
                            {{ recipients(message?.to) }}
                        </div>

                        <div
                            v-if="message?.cc?.length"
                            class="mt-1"
                        >
                            <div class="text-[11px] text-grey">CC</div>
                            <div class="text-sm text-blue-lighten-3">
                                {{ recipients(message?.cc) }}
                            </div>
                        </div>
                    </v-col>
                </v-row>

                <v-divider class="my-4" />

                <v-alert
                    v-if="feedback"
                    :type="feedback.type"
                    variant="tonal"
                    density="compact"
                    class="mb-3"
                >
                    {{ feedback.text }}
                </v-alert>

                <v-row dense class="mb-4">
                    <v-col cols="12" lg="7">
                        <v-card variant="tonal" color="blue" class="mail-tools-card mail-attachments-card">
                            <v-card-title class="mail-attachments-card__title py-2">
                                <span>Вложения</span>
                                <small v-if="attachmentRows.length">{{ attachmentRows.length }} файлов</small>
                            </v-card-title>

                            <v-card-text class="pt-0">
                                <div v-if="attachmentRows.length" class="mail-attachments-workspace">
                                    <div class="mail-attachments-sheet">
                                        <div class="mail-attachments-sheet__row is-head">
                                            <span>#</span>
                                            <span>Файл</span>
                                            <span>Тип</span>
                                            <span>Размер</span>
                                            <span>S3</span>
                                        </div>

                                        <div
                                            v-for="(attachment, index) in attachmentRows"
                                            :key="`${attachment.index}-${attachmentName(attachment)}-${index}`"
                                            role="button"
                                            tabindex="0"
                                            class="mail-attachments-sheet__row"
                                            :class="{
                                                'is-active': selectedAttachment?.index === attachment.index,
                                                'is-image': isAttachmentImage(attachment),
                                                'is-saved': attachment.is_saved,
                                            }"
                                            @click="selectAttachment(attachment)"
                                            @keydown.enter.prevent="selectAttachment(attachment)"
                                        >
                                            <span class="mail-attachments-sheet__num">{{ index + 1 }}</span>
                                            <span class="mail-attachments-sheet__file">
                                                <v-icon
                                                    :icon="isAttachmentImage(attachment) ? 'mdi-image-outline' : 'mdi-file-outline'"
                                                    size="13"
                                                />
                                                <span>{{ attachmentName(attachment) }}</span>
                                            </span>
                                            <span>{{ attachmentMime(attachment) }}</span>
                                            <span>{{ formatSize(attachment.size) }}</span>
                                            <span class="mail-attachments-sheet__s3">
                                                <a
                                                    v-if="attachment.is_saved && attachment.url"
                                                    :href="attachment.url"
                                                    target="_blank"
                                                    @click.stop
                                                >
                                                    open
                                                </a>

                                                <v-btn
                                                    v-else
                                                    size="x-small"
                                                    density="compact"
                                                    variant="tonal"
                                                    color="blue"
                                                    :loading="savingAttachmentIndex === attachment.index"
                                                    @click.stop="saveAttachment(attachment)"
                                                >
                                                    save
                                                </v-btn>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mail-attachment-preview">
                                        <template v-if="selectedImageAttachment">
                                            <div class="mail-attachment-preview__meta">
                                                <strong>{{ attachmentName(selectedImageAttachment) }}</strong>
                                                <span>{{ formatSize(selectedImageAttachment.size) }}</span>
                                            </div>

                                            <img
                                                v-if="selectedImageAttachment.preview_url || selectedImageAttachment.url"
                                                :src="selectedImageAttachment.preview_url || selectedImageAttachment.url"
                                                :alt="attachmentName(selectedImageAttachment)"
                                            >

                                            <div v-else class="mail-attachment-preview__empty">
                                                Preview недоступен. Сохраните файл в S3 и откройте ссылку.
                                            </div>
                                        </template>

                                        <div v-else class="mail-attachment-preview__empty">
                                            В письме нет изображений для предпросмотра.
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mail-attachments-empty">
                                    {{ hasAttachmentSignal ? 'Вложения ещё не загружены. Нажмите refresh письма.' : 'Вложений нет.' }}
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" lg="5">
                        <v-card variant="tonal" color="teal" class="mail-tools-card">
                            <v-card-title class="text-sm py-2">
                                CRM
                            </v-card-title>

                            <v-card-text class="pt-0">
                                <v-row dense>
                                    <v-col cols="12" md="5">
                                        <v-text-field
                                            v-model="noteTitle"
                                            label="Заголовок важного"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                        />
                                    </v-col>

                                    <v-col cols="12" md="3">
                                        <v-select
                                            v-model="noteImportance"
                                            :items="[
                                                { title: 'Important', value: 'important' },
                                                { title: 'Critical', value: 'critical' },
                                                { title: 'Normal', value: 'normal' },
                                            ]"
                                            label="Важность"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-btn
                                            block
                                            color="teal"
                                            variant="elevated"
                                            prepend-icon="mdi-content-save-outline"
                                            :loading="actionLoading"
                                            :disabled="!noteBody.trim()"
                                            @click="saveNote"
                                        >
                                            Сохранить
                                        </v-btn>
                                    </v-col>

                                    <v-col cols="12">
                                        <v-textarea
                                            v-model="noteBody"
                                            label="Важная информация из письма"
                                            density="compact"
                                            variant="outlined"
                                            rows="2"
                                            auto-grow
                                            hide-details
                                        />
                                    </v-col>

                                    <v-col cols="12" md="8">
                                        <v-text-field
                                            v-model="leadTitle"
                                            label="Лид"
                                            density="compact"
                                            variant="outlined"
                                            hide-details
                                        />
                                    </v-col>

                                    <v-col cols="12" md="4">
                                        <v-btn
                                            block
                                            color="amber"
                                            variant="elevated"
                                            prepend-icon="mdi-account-plus-outline"
                                            :loading="actionLoading"
                                            :disabled="!leadTitle.trim()"
                                            @click="createLead"
                                        >
                                            Создать лид
                                        </v-btn>
                                    </v-col>
                                </v-row>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <div v-if="notes.length || leads.length" class="mail-crm-history mb-4">
                    <v-chip
                        v-for="lead in leads"
                        :key="`lead-${lead.id}`"
                        size="x-small"
                        color="amber"
                        variant="tonal"
                    >
                        Lead #{{ lead.id }} {{ lead.title }}
                    </v-chip>

                    <v-chip
                        v-for="note in notes"
                        :key="`note-${note.id}`"
                        size="x-small"
                        :color="note.importance === 'critical' ? 'red' : 'teal'"
                        variant="tonal"
                    >
                        {{ note.title || note.body }}
                    </v-chip>
                </div>

                <v-progress-linear
                    v-if="loading"
                    indeterminate
                    color="blue"
                    class="mb-3"
                />

                <div
                    v-if="bodyHtml"
                    class="mail-body bg-white text-black rounded pa-4"
                    v-html="bodyHtml"
                />

                <pre
                    v-else
                    class="mail-body-text rounded border border-blue-900 bg-slate-900 text-grey-lighten-2 pa-4"
                >{{ bodyText }}</pre>
            </v-card-text>

            <v-card-actions>
                <v-btn
                    v-if="message?.direction === 'incoming'"
                    color="teal"
                    variant="tonal"
                    prepend-icon="mdi-reply"
                    :disabled="loading"
                    @click="emit('reply', message)"
                >
                    Ответить
                </v-btn>

                <v-spacer />

                <v-btn
                    text="Закрыть"
                    variant="text"
                    @click="model = false"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.mail-body {
    max-height: 620px;
    overflow: auto;
}

.mail-body-text {
    max-height: 620px;
    overflow: auto;
    white-space: pre-wrap;
    font-size: 12px;
    line-height: 1.5;
}

.mail-tools-card {
    height: 100%;
}

.mail-crm-history {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.mail-attachments-card__title {
    align-items: baseline;
    display: flex;
    gap: 8px;
    justify-content: space-between;
}

.mail-attachments-card__title span {
    font-size: 0.88rem;
    font-weight: 900;
}

.mail-attachments-card__title small {
    color: #93c5fd;
    font-size: 10px;
    font-weight: 800;
}

.mail-attachments-workspace {
    display: grid;
    grid-template-columns: minmax(310px, 0.92fr) minmax(260px, 1fr);
    gap: 10px;
    min-height: 260px;
}

.mail-attachments-sheet {
    overflow: auto;
    border: 1px solid rgba(147, 197, 253, 0.28);
    border-radius: 10px;
    background: rgba(15, 23, 42, 0.78);
}

.mail-attachments-sheet__row {
    display: grid;
    width: 100%;
    grid-template-columns: 30px minmax(140px, 1fr) 88px 62px 58px;
    align-items: center;
    gap: 0;
    border: 0;
    border-bottom: 1px solid rgba(147, 197, 253, 0.18);
    background: transparent;
    color: #dbeafe;
    cursor: pointer;
    font-size: 10px;
    line-height: 1.15;
    padding: 0;
    text-align: left;
}

.mail-attachments-sheet__row > span {
    min-height: 28px;
    min-width: 0;
    overflow: hidden;
    padding: 5px 6px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachments-sheet__row > span + span {
    border-left: 1px solid rgba(147, 197, 253, 0.14);
}

.mail-attachments-sheet__row.is-head {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #1e3a8a;
    color: #eff6ff;
    cursor: default;
    font-size: 9px;
    font-weight: 950;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.mail-attachments-sheet__row:not(.is-head):nth-child(odd) {
    background: rgba(30, 58, 138, 0.12);
}

.mail-attachments-sheet__row:not(.is-head):hover,
.mail-attachments-sheet__row.is-active {
    background: rgba(59, 130, 246, 0.24);
}

.mail-attachments-sheet__row.is-image .mail-attachments-sheet__file {
    color: #bfdbfe;
    font-weight: 900;
}

.mail-attachments-sheet__row.is-saved .mail-attachments-sheet__s3 {
    color: #86efac;
}

.mail-attachments-sheet__num {
    color: #93c5fd;
    font-family: monospace;
    text-align: right;
}

.mail-attachments-sheet__file {
    align-items: center;
    display: inline-flex;
    gap: 4px;
}

.mail-attachments-sheet__file span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachments-sheet__s3 {
    align-items: center;
    display: inline-flex;
    justify-content: center;
}

.mail-attachments-sheet__s3 a {
    color: #86efac;
    font-size: 10px;
    font-weight: 900;
    text-decoration: none;
}

.mail-attachments-sheet__s3 a:hover {
    text-decoration: underline;
}

.mail-attachment-preview {
    display: grid;
    align-content: center;
    gap: 8px;
    min-height: 260px;
    overflow: hidden;
    border: 1px solid rgba(147, 197, 253, 0.28);
    border-radius: 12px;
    background:
        linear-gradient(135deg, rgba(15, 23, 42, 0.92), rgba(30, 41, 59, 0.92)),
        repeating-linear-gradient(45deg, rgba(147, 197, 253, 0.08) 0 8px, transparent 8px 16px);
    padding: 10px;
}

.mail-attachment-preview__meta {
    align-items: center;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    min-width: 0;
}

.mail-attachment-preview__meta strong {
    overflow: hidden;
    color: #dbeafe;
    font-size: 11px;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachment-preview__meta span {
    color: #94a3b8;
    flex: 0 0 auto;
    font-size: 10px;
}

.mail-attachment-preview img {
    display: block;
    max-height: 390px;
    max-width: 100%;
    justify-self: center;
    border-radius: 10px;
    object-fit: contain;
}

.mail-attachment-preview__empty,
.mail-attachments-empty {
    display: grid;
    min-height: 170px;
    place-items: center;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 800;
    text-align: center;
}

@media (max-width: 960px) {
    .mail-attachments-workspace {
        grid-template-columns: 1fr;
    }

    .mail-attachments-sheet__row {
        grid-template-columns: 30px minmax(140px, 1fr) 74px 58px 54px;
    }
}
</style>
