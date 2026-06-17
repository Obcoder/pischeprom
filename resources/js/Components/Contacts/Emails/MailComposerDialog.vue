<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    recipients: {
        type: Array,
        default: () => [],
    },
    initialTo: {
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
    entityId: {
        type: Number,
        default: null,
    },
    unitId: {
        type: Number,
        default: null,
    },
    endpoint: {
        type: String,
        default: '/api/mail-messages/send',
    },
})

const emit = defineEmits(['sent'])

const to = ref([])
const cc = ref([])
const subject = ref('')
const body = ref('')
const localFiles = ref([])
const storageFiles = ref([])
const sending = ref(false)
const error = ref(null)
const fileInput = ref(null)

const isReply = computed(() => Boolean(props.replyContext?.id))

const normalizedTo = computed(() => selectedAddresses(to.value))
const normalizedCc = computed(() => selectedAddresses(cc.value))
const normalizedStorageFiles = computed(() => selectedStoragePaths(storageFiles.value))

const recipientItems = computed(() => {
    const replyRecipient = props.replyContext?.from_address
        ? [{
            address: props.replyContext.from_address,
            name: props.replyContext.from_name || null,
            source_label: 'Ответ',
        }]
        : []

    return [...replyRecipient, ...(props.recipients || [])]
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
    return props.initialStorageFiles.map((path) => ({
        title: path,
        value: path,
    }))
})

function stripHtml(value) {
    if (!value) return ''

    return String(value)
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
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

    if (!source) return ''

    const from = [message?.from_name, message?.from_address].filter(Boolean).join(' ')
    const quoted = source.split('\n').map((line) => `> ${line}`).join('\n')

    return `\n\n${from ? `${from} писал(а):\n` : ''}${quoted}`
}

function normalizeInitialTo() {
    return (props.initialTo || [])
        .map((item) => typeof item === 'string' ? item : item?.address || item?.email || null)
        .filter(Boolean)
}

function selectedValue(item) {
    if (typeof item === 'string') {
        return item
    }

    return item?.value || item?.address || item?.email || item?.path || item?.key || ''
}

function selectedAddresses(items) {
    return (items || [])
        .map(selectedValue)
        .map((address) => String(address || '').trim())
        .filter(Boolean)
}

function selectedStoragePaths(items) {
    return (items || [])
        .map(selectedValue)
        .map((path) => String(path || '').trim())
        .filter(Boolean)
}

function resetForm() {
    to.value = []
    cc.value = []
    subject.value = ''
    body.value = ''
    localFiles.value = []
    storageFiles.value = [...props.initialStorageFiles]
    error.value = null

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

function applyInitialState() {
    storageFiles.value = [...props.initialStorageFiles]

    if (props.replyContext) {
        to.value = props.replyContext.from_address ? [props.replyContext.from_address] : []
        cc.value = []
        subject.value = replySubject(props.replyContext)
        body.value = replyQuote(props.replyContext)

        return
    }

    to.value = normalizeInitialTo()
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
    sending.value = true
    error.value = null

    const formData = new FormData()

    normalizedTo.value.forEach((address) => formData.append('to[]', address))
    normalizedCc.value.forEach((address) => formData.append('cc[]', address))
    normalizedStorageFiles.value.forEach((path) => formData.append('storage_files[]', path))
    localFiles.value.forEach((file) => formData.append('attachments[]', file))

    formData.append('subject', subject.value)
    formData.append('body', body.value)

    if (props.replyContext?.id) {
        formData.append('reply_to_mail_message_id', props.replyContext.id)
    }

    if (props.entityId) {
        formData.append('entity_id', props.entityId)
    }

    if (props.unitId) {
        formData.append('unit_id', props.unitId)
    }

    try {
        const { data } = await axios.post(props.endpoint, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })

        emit('sent', data.mail_message)
        model.value = false
    } catch (err) {
        error.value = err?.response?.data?.message || 'Не удалось отправить письмо.'
    } finally {
        sending.value = false
    }
}

watch(model, (value) => {
    if (value) {
        applyInitialState()
    } else {
        resetForm()
    }
})

watch(() => props.replyContext, () => {
    if (model.value) {
        applyInitialState()
    }
}, {
    deep: true,
})
</script>

<template>
    <v-dialog v-model="model" max-width="980" scrollable>
        <v-card class="mail-composer">
            <v-card-title class="d-flex justify-space-between align-center">
                <div>
                    <div class="mail-composer__title">
                        {{ isReply ? 'Ответить на письмо' : 'Написать письмо' }}
                    </div>
                    <div class="mail-composer__subtitle">
                        Локальные файлы и файлы Yandex S3 можно отправлять вместе.
                    </div>
                </div>

                <v-btn icon="mdi-close" variant="text" @click="model = false" />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">
                    {{ error }}
                </v-alert>

                <v-row dense>
                    <v-col cols="12" lg="8">
                        <v-combobox
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
                        <v-combobox
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
                        />
                    </v-col>

                    <v-col cols="12" lg="6">
                        <v-combobox
                            v-model="storageFiles"
                            :items="storageFileItems"
                            label="Yandex S3 файлы: path или URL"
                            item-title="title"
                            item-value="value"
                            variant="outlined"
                            density="compact"
                            multiple
                            chips
                            closable-chips
                            hint="Например: mail/attachments/123/file.pdf или полный URL"
                            persistent-hint
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
                            color="teal"
                            variant="tonal"
                            prepend-icon="mdi-paperclip"
                            @click="fileInput?.click()"
                        >
                            Прикрепить файлы с компьютера
                        </v-btn>

                        <div v-if="localFiles.length" class="mail-composer__files mt-2">
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
                <v-btn variant="text" @click="model = false">Отмена</v-btn>
                <v-btn
                    color="#800000"
                    variant="elevated"
                    :loading="sending"
                    :disabled="!normalizedTo.length || !subject || !body"
                    @click="submit"
                >
                    {{ isReply ? 'Отправить ответ' : 'Отправить' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.mail-composer {
    border: 1px solid rgba(128, 0, 0, 0.22);
    border-radius: 18px;
    background: #fffdf8;
}

.mail-composer__title {
    color: #3f1d1d;
    font-weight: 950;
}

.mail-composer__subtitle {
    color: #7c5148;
    font-size: 11px;
}

.mail-composer__files {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
</style>
