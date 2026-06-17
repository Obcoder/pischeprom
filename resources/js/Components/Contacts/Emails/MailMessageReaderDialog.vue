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

const bodyHtml = computed(() => {
    return props.message?.html || null
})

const bodyText = computed(() => {
    return props.message?.text || 'Тело письма не загружено или письмо пустое.'
})

const attachments = computed(() => props.message?.attachments || [])
const notes = computed(() => props.message?.notes || [])
const leads = computed(() => props.message?.leads || [])
const hasAttachmentSignal = computed(() => Boolean(props.message?.has_attachments || attachments.value.length))

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
}

async function syncAttachments() {
    if (!props.message?.id) {
        return
    }

    actionLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${props.message.id}/attachments/sync`, {
            force: true,
        })

        emit('updated', data)
        feedback.value = {
            type: 'success',
            text: 'Вложения сохранены в Yandex S3.',
        }
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось сохранить вложения.',
        }
    } finally {
        actionLoading.value = false
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
                    <v-col cols="12" lg="5">
                        <v-card variant="tonal" color="blue" class="mail-tools-card">
                            <v-card-title class="text-sm py-2">
                                Вложения
                            </v-card-title>

                            <v-card-text class="pt-0">
                                <div v-if="attachments.length" class="mail-attachments">
                                    <a
                                        v-for="attachment in attachments"
                                        :key="attachment.id"
                                        :href="attachment.url"
                                        target="_blank"
                                        class="mail-attachment"
                                    >
                                        <v-icon icon="mdi-paperclip" size="14" />
                                        <span>{{ attachment.original_name || attachment.file_name }}</span>
                                        <small>{{ formatSize(attachment.size) }}</small>
                                    </a>
                                </div>

                                <div v-else class="text-[11px] text-grey-lighten-1">
                                    {{ hasAttachmentSignal ? 'Письмо содержит вложения. Нажмите сохранить.' : 'Вложений нет.' }}
                                </div>

                                <v-btn
                                    v-if="hasAttachmentSignal"
                                    class="mt-2"
                                    size="small"
                                    color="blue"
                                    variant="elevated"
                                    prepend-icon="mdi-cloud-upload-outline"
                                    :loading="actionLoading"
                                    @click="syncAttachments"
                                >
                                    Сохранить в Yandex S3
                                </v-btn>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" lg="7">
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

.mail-attachments,
.mail-crm-history {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.mail-attachment {
    align-items: center;
    border: 1px solid rgba(147, 197, 253, 0.34);
    border-radius: 8px;
    color: #bfdbfe;
    display: inline-flex;
    font-size: 11px;
    gap: 4px;
    max-width: 100%;
    padding: 4px 6px;
    text-decoration: none;
}

.mail-attachment span {
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachment small {
    color: #94a3b8;
}
</style>
