<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    unitId: {
        type: Number,
        required: true,
    },
    recipients: {
        type: Array,
        default: () => [],
    },
    unitFiles: {
        type: Array,
        default: () => [],
    },
    initialStorageFiles: {
        type: Array,
        default: () => [],
    },
    replyContext: {
        type: Object,
        default: null,
    },
    sending: Boolean,
})

const emit = defineEmits([
    'sent',
    'open-templates',
])

const to = ref([])
const cc = ref([])
const subject = ref('')
const body = ref('')
const localFiles = ref([])
const storageFiles = ref([])
const templates = ref([])
const selectedTemplateId = ref(null)
const loadingTemplates = ref(false)
const fileInput = ref(null)

const isReply = computed(() => Boolean(props.replyContext?.id))

const replyRecipient = computed(() => {
    if (!props.replyContext?.from_address) {
        return null
    }

    return {
        id: props.replyContext.from_address,
        address: props.replyContext.from_address,
        name: props.replyContext.from_name || null,
        source: 'reply',
        source_label: 'Ответ',
    }
})

const recipientItems = computed(() => {
    return [
        ...(replyRecipient.value ? [replyRecipient.value] : []),
        ...(props.recipients || []),
    ]
        .filter((item) => item?.address)
        .filter((item, index, array) => {
            const address = String(item.address).toLowerCase()

            return array.findIndex((candidate) => String(candidate.address).toLowerCase() === address) === index
        })
        .map((item) => ({
            ...item,
            title: `${item.address}${item.source_label ? ` — ${item.source_label}` : ''}`,
            value: item.address,
        }))
})

const storageFileItems = computed(() => {
    const fromUnit = props.unitFiles.map((file) => ({
        title: file.name || file.path,
        value: file.path,
    }))

    const fromInitial = props.initialStorageFiles.map((path) => ({
        title: path,
        value: path,
    }))

    return [...fromUnit, ...fromInitial]
        .filter((item, index, array) => (
            array.findIndex((candidate) => candidate.value === item.value) === index
        ))
})

async function fetchTemplates() {
    loadingTemplates.value = true

    try {
        const { data } = await axios.get('/api/mail-templates')
        templates.value = data ?? []
    } catch (error) {
        console.error('Templates loading error:', error)
    } finally {
        loadingTemplates.value = false
    }
}

function applyTemplate(templateId) {
    const template = templates.value.find((item) => item.id === templateId)

    if (!template) return

    subject.value = template.subject || subject.value
    body.value = template.body || body.value
}

function replySubject(message) {
    const value = (message?.subject || '').trim()

    if (!value) {
        return 'Re: Без темы'
    }

    return /^re:/i.test(value) ? value : `Re: ${value}`
}

function replyQuote(message) {
    const source = (message?.text || stripHtml(message?.html) || message?.preview || '').trim()

    if (!source) {
        return ''
    }

    const date = message?.message_date
        ? new Intl.DateTimeFormat('ru-RU', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(message.message_date))
        : ''

    const from = [message?.from_name, message?.from_address]
        .filter(Boolean)
        .join(' ')

    const header = [date, from]
        .filter(Boolean)
        .join(', ')

    const quoted = source
        .split('\n')
        .map((line) => `> ${line}`)
        .join('\n')

    return `\n\n${header ? `${header} писал(а):\n` : ''}${quoted}`
}

function stripHtml(value) {
    if (!value) {
        return ''
    }

    return String(value)
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
}

function applyReplyContext() {
    if (!props.replyContext) {
        return
    }

    to.value = props.replyContext.from_address ? [props.replyContext.from_address] : []
    cc.value = []
    subject.value = replySubject(props.replyContext)
    body.value = replyQuote(props.replyContext)
}

function onLocalFilesSelected(event) {
    localFiles.value = Array.from(event.target.files || [])
}

function removeLocalFile(index) {
    localFiles.value.splice(index, 1)

    if (!localFiles.value.length && fileInput.value) {
        fileInput.value.value = ''
    }
}

async function submit() {
    const formData = new FormData()

    to.value.forEach((address) => formData.append('to[]', address))
    cc.value.forEach((address) => formData.append('cc[]', address))

    formData.append('subject', subject.value)
    formData.append('body', body.value)

    storageFiles.value.forEach((path) => {
        formData.append('storage_files[]', path)
    })

    localFiles.value.forEach((file) => {
        formData.append('attachments[]', file)
    })

    if (props.replyContext?.id) {
        formData.append('reply_to_mail_message_id', props.replyContext.id)
    }

    await axios.post(`/api/units/${props.unitId}/mail/send`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    })

    emit('sent')
    close()
}

function close() {
    model.value = false
}

function resetForm() {
    to.value = []
    cc.value = []
    subject.value = ''
    body.value = ''
    localFiles.value = []
    storageFiles.value = [...props.initialStorageFiles]
    selectedTemplateId.value = null

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

watch(model, async (value) => {
    if (value) {
        storageFiles.value = [...props.initialStorageFiles]

        if (props.replyContext) {
            applyReplyContext()
        }

        if (!templates.value.length) {
            await fetchTemplates()
        }
    } else {
        resetForm()
    }
})

watch(selectedTemplateId, applyTemplate)

watch(() => props.initialStorageFiles, (value) => {
    if (model.value) {
        storageFiles.value = [...value]
    }
}, {
    deep: true,
})

watch(() => props.replyContext, () => {
    if (model.value) {
        applyReplyContext()
    }
}, {
    deep: true,
})

</script>

<template>
    <v-dialog v-model="model" max-width="980" scrollable>
        <v-card class="rounded border border-blue-900 bg-slate-950">
            <v-card-title class="d-flex justify-space-between align-center">
                <div>
                    <div class="text-blue-lighten-3">
                        {{ isReply ? 'Ответить на письмо' : 'Написать письмо' }}
                    </div>

                    <div class="text-[10px] text-grey">
                        {{ isReply ? 'Ответ будет отправлен в той же почтовой ветке' : 'Можно использовать шаблон и приложить локальные/S3 файлы' }}
                    </div>
                </div>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="close"
                />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row dense>
                    <v-col
                        v-if="isReply"
                        cols="12"
                    >
                        <v-alert
                            color="teal"
                            variant="tonal"
                            density="compact"
                            icon="mdi-reply"
                        >
                            <div class="text-xs">
                                Ответ на: {{ replyContext?.subject || 'Без темы' }}
                            </div>

                            <div class="text-[10px] text-grey-lighten-1">
                                {{ replyContext?.from_address }}
                            </div>
                        </v-alert>
                    </v-col>

                    <v-col cols="12" lg="8">
                        <v-autocomplete
                            v-model="to"
                            :items="recipientItems"
                            label="Кому"
                            item-title="title"
                            item-value="value"
                            variant="outlined"
                            density="compact"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" lg="4">
                        <v-autocomplete
                            v-model="cc"
                            :items="recipientItems"
                            label="CC"
                            item-title="title"
                            item-value="value"
                            variant="outlined"
                            density="compact"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" lg="8">
                        <v-select
                            v-model="selectedTemplateId"
                            :items="templates"
                            item-title="name"
                            item-value="id"
                            label="Шаблон"
                            variant="outlined"
                            density="compact"
                            clearable
                            :loading="loadingTemplates"
                        />
                    </v-col>

                    <v-col cols="12" lg="4">
                        <v-btn
                            block
                            variant="tonal"
                            color="blue"
                            prepend-icon="mdi-file-document-edit-outline"
                            @click="emit('open-templates')"
                        >
                            Управлять шаблонами
                        </v-btn>
                    </v-col>

                    <v-col cols="12">
                        <v-text-field
                            v-model="subject"
                            label="Тема"
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-textarea
                            v-model="body"
                            label="Текст письма"
                            variant="outlined"
                            rows="10"
                            auto-grow
                            hint="Доступные переменные: {{unit.name}}, {{unit.id}}, {{email.address}}"
                            persistent-hint
                        />
                    </v-col>

                    <v-col cols="12" lg="6">
                        <v-select
                            v-model="storageFiles"
                            :items="storageFileItems"
                            label="Файлы Unit из хранилища"
                            variant="outlined"
                            density="compact"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" lg="6">
                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            class="d-none"
                            @change="onLocalFilesSelected"
                        >

                        <v-btn
                            block
                            variant="tonal"
                            color="teal"
                            prepend-icon="mdi-paperclip"
                            @click="fileInput?.click()"
                        >
                            Прикрепить локальные файлы
                        </v-btn>

                        <div
                            v-if="localFiles.length"
                            class="d-flex flex-wrap ga-1 mt-2"
                        >
                            <v-chip
                                v-for="(file, index) in localFiles"
                                :key="`${file.name}-${index}`"
                                size="small"
                                color="teal"
                                variant="tonal"
                                closable
                                @click:close="removeLocalFile(index)"
                            >
                                {{ file.name }}
                            </v-chip>
                        </div>
                    </v-col>
                </v-row>
            </v-card-text>

            <v-card-actions>
                <v-spacer />

                <v-btn
                    variant="text"
                    @click="close"
                >
                    Отмена
                </v-btn>

                <v-btn
                    color="blue"
                    variant="elevated"
                    :loading="sending"
                    :disabled="!to.length || !subject || !body"
                    @click="submit"
                >
                    {{ isReply ? 'Отправить ответ' : 'Отправить' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
