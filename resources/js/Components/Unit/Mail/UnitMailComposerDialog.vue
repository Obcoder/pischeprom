<script setup>
import { computed, onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'
import MailTemplatesDialog from '@/Components/Contacts/Emails/MailTemplatesDialog.vue'
import SupplierOrderComposer from './SupplierOrderComposer.vue'

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
    initialTo: {
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
    mailboxes: {
        type: Array,
        default: () => [],
    },
    sending: Boolean,
    initialSupplierOrderId: { type: [Number, String], default: null },
    initialMode: { type: String, default: 'supplier_order' },
})

const emit = defineEmits(['sent', 'saved'])
const mode = ref('supplier_order'), supplierMounted = ref(false), supplierComposer = ref(null), supplierState = ref({ dirty: false, busy: false })
const submitting = ref(false), sendError = ref(''), closeConfirmation = ref(false), mailInitialized = ref(false), mailLoading = ref(false), mailBaseline = ref('')
let session = 0
const mailSnapshot = () => JSON.stringify({ to: to.value, cc: cc.value, subject: subject.value, body: body.value, mailbox: selectedMailbox.value, storage: storageFiles.value, files: localFiles.value.map(file => [file.name, file.size, file.lastModified]) })
const mailDirty = computed(() => mailInitialized.value && mailSnapshot() !== mailBaseline.value)


const to = ref([])
const cc = ref([])
const subject = ref('')
const body = ref('')
const selectedMailbox = ref(null)
const availableMailboxes = ref([])
const localFiles = ref([])
const storageFiles = ref([])
const templates = ref([])
const selectedTemplateId = ref(null)
const templatesDialog = ref(false)
const loadingTemplates = ref(false)
const fileInput = ref(null)
const idempotencyKey = ref(crypto.randomUUID())

const isReply = computed(() => Boolean(props.replyContext?.id))
const mailboxItems = computed(() => {
    const source = props.mailboxes.length ? props.mailboxes : availableMailboxes.value

    return source.map((mailbox) => ({
        title: mailbox.label || mailbox.address,
        value: mailbox.address,
    }))
})

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
    const version = session
    loadingTemplates.value = true

    try {
        const { data } = await axios.get('/api/mail-templates')
        if (version !== session || !model.value) return
        templates.value = (data ?? []).filter((template) => template.is_active !== false)
    } catch (error) {
        if (version === session) sendError.value = 'Не удалось загрузить шаблоны.'
    } finally {
        if (version === session) loadingTemplates.value = false
    }
}

async function fetchMailboxes() {
    const version = session
    if (props.mailboxes.length) {
        return
    }

    try {
        const { data } = await axios.get('/api/mailboxes')
        if (version !== session || !model.value) return
        availableMailboxes.value = data.data ?? data ?? []
    } catch (error) {
        if (version === session) { sendError.value = 'Не удалось загрузить почтовые ящики.'; availableMailboxes.value = [] }
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
    selectedMailbox.value = props.replyContext.mailbox || mailboxItems.value[0]?.value || null
    subject.value = replySubject(props.replyContext)
    body.value = replyQuote(props.replyContext)
}

function normalizeInitialTo() {
    return (props.initialTo || [])
        .map((item) => {
            if (typeof item === 'string') {
                return item
            }

            return item?.address || item?.email || null
        })
        .filter(Boolean)
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
    if (submitting.value || props.sending) return
    const version = session
    submitting.value = true; sendError.value = ''
    const formData = new FormData()

    formData.append('idempotency_key', idempotencyKey.value)

    to.value.forEach((address) => formData.append('to[]', address))
    cc.value.forEach((address) => formData.append('cc[]', address))

    formData.append('subject', subject.value)
    formData.append('body', body.value)

    if (selectedMailbox.value) {
        formData.append('mailbox', selectedMailbox.value)
    }

    storageFiles.value.forEach((path) => {
        formData.append('storage_files[]', path)
    })

    localFiles.value.forEach((file) => {
        formData.append('attachments[]', file)
    })

    if (props.replyContext?.id) {
        formData.append('reply_to_mail_message_id', props.replyContext.id)
    }

    try {
        await axios.post(`/api/units/${props.unitId}/mail/send`, formData)
        if (version !== session) return
        emit('sent'); model.value = false
    } catch (failure) {
        if (version === session) sendError.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось отправить письмо. Черновик сохранён в этом окне; повторите попытку.'
    } finally { if (version === session) submitting.value = false }
}

function close() {
    if (submitting.value || props.sending || supplierState.value.busy) return
    if (mode.value === 'supplier_order' || supplierState.value.dirty) { mode.value = 'supplier_order'; supplierComposer.value?.requestClose(); return }
    if (mailDirty.value) closeConfirmation.value = true
    else model.value = false
}
function closeSupplier() {
    if (mailDirty.value) closeConfirmation.value = true
    else model.value = false
}
async function prepareMail() {
    if (mailInitialized.value || mailLoading.value || !model.value) return
    const version = session; mailLoading.value = true
    await fetchMailboxes()
    if (version !== session || !model.value) return
    storageFiles.value = [...props.initialStorageFiles]
    selectedMailbox.value = props.replyContext?.mailbox || mailboxItems.value[0]?.value || null
    if (props.replyContext) applyReplyContext(); else to.value = normalizeInitialTo()
    mailBaseline.value = mailSnapshot(); mailInitialized.value = true; mailLoading.value = false
    await fetchTemplates()
}

function resetForm() {
    mailInitialized.value = false; mailLoading.value = false; sendError.value = ''; closeConfirmation.value = false; submitting.value = false; supplierMounted.value = false; supplierState.value = { dirty: false, busy: false }
    to.value = []
    cc.value = []
    subject.value = ''
    body.value = ''
    selectedMailbox.value = null
    localFiles.value = []
    storageFiles.value = [...props.initialStorageFiles]
    selectedTemplateId.value = null
    templatesDialog.value = false
    idempotencyKey.value = crypto.randomUUID()

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

watch(model, async (value) => {
    session++
    if (value) {
        mode.value = props.replyContext ? 'mail' : props.initialMode === 'mail' ? 'mail' : 'supplier_order'
        if (mode.value === 'supplier_order') supplierMounted.value = true
        else await prepareMail()
    } else resetForm()
}, { immediate: true })
watch(mode, value => {
    if (!model.value) return
    if (value === 'supplier_order') supplierMounted.value = true
    else prepareMail()
})
onScopeDispose(() => { session++ })

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

watch(() => props.initialTo, () => {
    if (model.value && !props.replyContext) {
        to.value = normalizeInitialTo()
    }
}, {
    deep: true,
})

</script>

<template>
    <v-dialog v-model="model" :width="mode === 'supplier_order' ? '96vw' : 'min(1100px, 96vw)'" max-width="1800" persistent scrollable>
        <v-card class="unit-mail-composer">
            <div v-if="!isReply" class="composer-mode-bar" role="group" aria-label="Режим сообщения"><button :aria-pressed="mode === 'supplier_order'" :disabled="submitting || sending || supplierState.busy" @click="mode = 'supplier_order'">Заказ поставщику</button><button :aria-pressed="mode === 'mail'" :disabled="submitting || sending || supplierState.busy" @click="mode = 'mail'">Письмо</button><span>Черновики сохраняются при переключении</span></div>
            <div v-if="closeConfirmation" class="mail-close-confirmation"><span>В обычном письме есть несохранённые изменения. Закрыть окно?</span><button @click="closeConfirmation = false">Продолжить работу</button><button @click="model = false">Закрыть без сохранения</button></div>
            <SupplierOrderComposer v-if="supplierMounted && model" v-show="mode === 'supplier_order'" ref="supplierComposer" :unit-id="unitId" :order-id="initialSupplierOrderId" :recipients="recipients" :mailboxes="mailboxes" :unit-files="unitFiles" :initial-to="initialTo" :initial-storage-files="initialStorageFiles" @state="supplierState = $event" @close="closeSupplier" @saved="emit('saved', $event)" @sent="emit('sent', $event)" />
            <div v-show="mode === 'mail'" class="ordinary-mail">
            <v-card-title class="d-flex justify-space-between align-center">
                <div>
                    <div class="ordinary-mail-title">
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
                <p v-if="sendError" class="mail-error" role="alert">{{ sendError }}</p>
                <fieldset :disabled="submitting || sending" class="ordinary-fields">
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

                    <v-col cols="12" lg="4">
                        <v-select
                            v-model="selectedMailbox"
                            :items="mailboxItems"
                            label="От ящика"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
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
                            no-data-text="Активных шаблонов нет"
                        />
                    </v-col>

                    <v-col cols="12" lg="4">
                        <v-btn
                            block
                            variant="tonal"
                            color="blue"
                            prepend-icon="mdi-file-document-edit-outline"
                            @click="templatesDialog = true"
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
                </fieldset>
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
                    :loading="submitting || sending"
                    :disabled="submitting || sending || !selectedMailbox || !to.length || !subject || !body"
                    @click="submit"
                >
                    {{ isReply ? 'Отправить ответ' : 'Отправить' }}
                </v-btn>
            </v-card-actions>
            </div>
        </v-card>
    </v-dialog>

    <MailTemplatesDialog
        v-model="templatesDialog"
        @changed="fetchTemplates"
    />
</template>


<style scoped>
.unit-mail-composer { background: #fff !important; color: #463b50 !important; max-height: 96vh; border: 1px solid #dad3e3; border-radius: 8px !important; }.composer-mode-bar { display: flex; align-items: center; gap: 3px; padding: 6px 12px; background: #ece8f1; border-bottom: 1px solid #dad3e3; }.composer-mode-bar button, .mail-close-confirmation button { border: 1px solid transparent; border-radius: 4px; padding: 4px 11px; color: #80718e; font: 11px -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }.composer-mode-bar button[aria-pressed=true] { border-color: #dcd4e5; background: #fff; color: #665175; box-shadow: 0 1px 2px #4020500b; }.composer-mode-bar span { margin-left: auto; color: #9b8aa8; font-size: 9px; }.ordinary-mail { overflow-y: auto; }.ordinary-mail-title { color: #665175; font-size: 15px; }.ordinary-fields { border: 0; padding: 0; margin: 0; min-width: 0; }.ordinary-mail :deep(.v-field) { font-size: 12px; }.ordinary-mail :deep(.v-label) { font-size: 11px; }.ordinary-mail :deep(.v-card-text) { padding-top: 10px; padding-bottom: 8px; }.ordinary-mail :deep(.v-btn) { font-size: 11px; text-transform: none; letter-spacing: normal; }.mail-error { background: #fff0f2; border: 1px solid #efcdd5; color: #a43e51; padding: 8px; margin-bottom: 10px; font-size: 11px; }.mail-close-confirmation { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 12px; background: #fff8ec; color: #9b7640; font-size: 10px; }.mail-close-confirmation button { border-color: #e2d4bc; background: #fff; }.composer-mode-bar button:focus-visible, .mail-close-confirmation button:focus-visible { outline: 2px solid #ac93cf; outline-offset: 2px; }@media(max-width:650px) { .composer-mode-bar span { display: none; } }
</style>
