<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import MailInvoiceDetails from './MailInvoiceDetails.vue'
import MailPdfViewer from './MailPdfViewer.vue'
import MailMessageReaderHeader from './MailMessageReaderHeader.vue'
import MailMessageCrmTools from './MailMessageCrmTools.vue'
import WordAttachmentPreview from './WordAttachmentPreview.vue'

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
const crmActionLoading = ref(false)
const crmNoteDialog = ref(false)
const crmLeadDialog = ref(false)
const noteTitle = ref('')
const noteBody = ref('')
const noteImportance = ref('important')
const leadTitle = ref('')
const leadDescription = ref('')
const feedback = ref(null)
const localMessage = ref(null)
const savingAttachmentIndex = ref(null)
const downloadingAttachmentIndex = ref(null)
const selectedAttachmentIndex = ref(null)
const storageFolders = ref([])
const foldersLoading = ref(false)
const creatingFolder = ref(false)
const selectedFolderPath = ref(null)
const newFolderPath = ref('')
const lastSavedAttachment = ref(null)
const relatedMessageLoading = ref(false)
let relatedMessageRequest = null
let folderRequest = 0

const currentMessage = computed(() => localMessage.value || props.message)
const readerLoading = computed(() => props.loading || relatedMessageLoading.value)
const relatedNavigationBlocked = computed(() => readerLoading.value || actionLoading.value || crmActionLoading.value
    || creatingFolder.value || savingAttachmentIndex.value !== null)
const relatedContexts = computed(() => {
    const contexts = new Map()
    const emails = currentMessage.value?.emails || []
    const add = (type, item) => {
        const id = Number(item?.id)
        if (!Number.isInteger(id) || id <= 0 || contexts.has(`${type}:${id}`)) return

        let href
        try {
            href = route(type === 'entity' ? 'Ameise.entity.show' : 'web.unit.show', id)
        } catch {
            href = `/Ameise/${type}/${id}`
        }
        contexts.set(`${type}:${id}`, {
            type,
            id,
            name: item.name || `${type === 'entity' ? 'Контрагент' : 'Подразделение'} #${id}`,
            href,
        })
    }

    for (const email of emails) {
        for (const entity of email.entities || []) add('entity', entity)
    }
    for (const email of emails) {
        for (const unit of email.units || []) add('unit', unit)
        for (const entity of email.entities || []) {
            for (const unit of entity.units || []) add('unit', unit)
        }
    }

    return [...contexts.values()]
})
const currentDefaultEntityId = computed(() => relatedContexts.value.some(context =>
    context.type === 'entity' && context.id === Number(props.defaultEntityId)) ? props.defaultEntityId : null)
const currentDefaultUnitId = computed(() => relatedContexts.value.some(context =>
    context.type === 'unit' && context.id === Number(props.defaultUnitId)) ? props.defaultUnitId : null)

const bodyHtml = computed(() => {
    return currentMessage.value?.html || null
})

const bodyText = computed(() => {
    return currentMessage.value?.text || 'Тело письма не загружено или письмо пустое.'
})
const syncError = computed(() => currentMessage.value?.mail_sync_error || null)

const attachments = computed(() => currentMessage.value?.attachments || [])
const availableAttachments = computed(() => currentMessage.value?.available_attachments || [])
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
const pdfAttachments = computed(() => attachmentRows.value.filter(isAttachmentPdf))
const selectedAttachment = computed(() => {
    const selected = selectedAttachmentIndex.value === null || selectedAttachmentIndex.value === undefined
        ? null
        : attachmentRows.value.find((attachment) => Number(attachment.index) === Number(selectedAttachmentIndex.value))

    return selected
        || pdfAttachments.value[0]
        || imageAttachments.value[0]
        || attachmentRows.value[0]
        || null
})
const selectedImageAttachment = computed(() => {
    return selectedAttachment.value && isAttachmentImage(selectedAttachment.value) ? selectedAttachment.value : null
})
const selectedPdfAttachment = computed(() => {
    return selectedAttachment.value && isAttachmentPdf(selectedAttachment.value) ? selectedAttachment.value : null
})
const selectedPdfIdentity = computed(() => selectedPdfAttachment.value
    ? `${currentMessage.value?.id}:${selectedPdfAttachment.value.index}:${selectedPdfAttachment.value.id || 'mail'}`
    : null)
const selectedWordAttachment = computed(() => selectedAttachment.value && isAttachmentWord(selectedAttachment.value) ? selectedAttachment.value : null)
const selectedWordIdentity = computed(() => selectedWordAttachment.value
    ? `${currentMessage.value?.id}:${selectedWordAttachment.value.index}:${selectedWordAttachment.value.id || 'mail'}`
    : null)
const notes = computed(() => currentMessage.value?.notes || [])
const leads = computed(() => currentMessage.value?.leads || [])
const hasAttachmentSignal = computed(() => Boolean(currentMessage.value?.has_attachments || attachmentRows.value.length || attachments.value.length))
const quickFolderTargets = computed(() => {
    const targets = []

    if (currentDefaultUnitId.value) {
        targets.push(folderFromPath(`units/${currentDefaultUnitId.value}`))
    }

    if (currentMessage.value?.id) {
        targets.push(folderFromPath(defaultFolderPath()))
    }

    return targets
})
const allStorageFolders = computed(() => mergeFolders([
    ...quickFolderTargets.value,
    ...storageFolders.value,
]))
const folderOptions = computed(() => allStorageFolders.value.map((folder) => ({
    title: folder.relative_path || folder.path,
    value: folder.path,
})))
const targetFolderPath = computed(() => {
    return selectedFolderPath.value
        || allStorageFolders.value.find((folder) => folder.path === defaultFolderPath())?.path
        || defaultFolderPath()
})
const targetFolder = computed(() => allStorageFolders.value.find((folder) => folder.path === targetFolderPath.value) || null)

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

function attachmentPath(attachment) {
    return attachment?.path || null
}

function attachmentFolderPath(attachment) {
    return attachment?.folder_path || (attachmentPath(attachment) ? String(attachmentPath(attachment)).split('/').slice(0, -1).join('/') : null)
}

function normalizePath(path) {
    return String(path || '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '')
}

function defaultFolderPath() {
    return currentMessage.value?.id ? `mail/attachments/${currentMessage.value.id}` : 'mail/attachments'
}

function isAttachmentSaved(attachment) {
    return Boolean(attachment?.is_saved && (attachment?.path || attachment?.url || attachment?.id))
}

function canSaveAttachment(attachment) {
    return Boolean(currentMessage.value?.id && attachment?.index !== null && attachment?.index !== undefined)
}

function canDownloadAttachment(attachment) {
    return Boolean(currentMessage.value?.id && attachment?.index !== null && attachment?.index !== undefined)
}

function isAttachmentImage(attachment) {
    const mime = String(attachment?.mime_type || attachment?.mime || '').toLowerCase()
    const name = attachmentName(attachment)

    return Boolean(attachment?.is_image)
        || mime.startsWith('image/')
        || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name)
}

function isAttachmentPdf(attachment) {
    const mime = String(attachment?.mime_type || attachment?.mime || '').toLowerCase()
    const name = attachmentName(attachment)

    return Boolean(attachment?.is_pdf)
        || mime === 'application/pdf'
        || /\.pdf$/i.test(name)
}

function isAttachmentWord(attachment) {
    return /\.docx?$/i.test(attachmentName(attachment))
        || ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(attachmentMime(attachment).toLowerCase())
}

function attachmentIcon(attachment) {
    const mime = attachmentMime(attachment).toLowerCase()
    const name = attachmentName(attachment).toLowerCase()

    if (isAttachmentImage(attachment)) return 'mdi-image-outline'
    if (isAttachmentPdf(attachment)) return 'mdi-file-pdf-box'
    if (mime.includes('word') || /\.(doc|docx)$/i.test(name)) return 'mdi-file-word-box'
    if (mime.includes('excel') || mime.includes('spreadsheet') || /\.(xls|xlsx|xlsm|csv)$/i.test(name)) return 'mdi-file-excel-box'
    if (mime.includes('powerpoint') || mime.includes('presentation') || /\.(ppt|pptx)$/i.test(name)) return 'mdi-file-powerpoint-box'
    if (mime.includes('zip') || mime.includes('rar') || /\.(zip|rar|7z)$/i.test(name)) return 'mdi-folder-zip'

    return 'mdi-file-outline'
}

function selectAttachment(attachment) {
    selectedAttachmentIndex.value = attachment?.index ?? null
}

function downloadAttachmentUrl(attachment) {
    const params = new URLSearchParams()

    if (attachment?.id) {
        params.set('attachment_id', attachment.id)
    }

    const query = params.toString()

    return `/api/mail-messages/${currentMessage.value.id}/attachments/${attachment.index}/download${query ? `?${query}` : ''}`
}

function plainBody() {
    return (currentMessage.value?.text || String(currentMessage.value?.html || '').replace(/<[^>]+>/g, ' ') || currentMessage.value?.preview || '').trim()
}

function resetForms() {
    crmNoteDialog.value = false
    crmLeadDialog.value = false
    noteTitle.value = ''
    noteBody.value = ''
    noteImportance.value = 'important'
    leadTitle.value = currentMessage.value?.subject || 'Лид из письма'
    leadDescription.value = [
        `From: ${[currentMessage.value?.from_name, currentMessage.value?.from_address].filter(Boolean).join(' ')}`,
        `Subject: ${currentMessage.value?.subject || 'Без темы'}`,
        plainBody().slice(0, 1800),
    ].filter(Boolean).join('\n\n')
    feedback.value = null
    savingAttachmentIndex.value = null
    downloadingAttachmentIndex.value = null
    selectedAttachmentIndex.value = pdfAttachments.value[0]?.index ?? imageAttachments.value[0]?.index ?? attachmentRows.value[0]?.index ?? null
    lastSavedAttachment.value = null
}

function applyMessageUpdate(message) {
    if (!message) {
        return
    }

    localMessage.value = message
    emit('updated', message)
}

async function refreshCrmMessage() {
    const id = currentMessage.value?.id
    if (!id) return
    try {
        const { data } = await axios.get(`/api/mail-messages/${id}`)
        if (model.value && Number(currentMessage.value?.id) === Number(id)) applyMessageUpdate(data)
    } catch {
        if (model.value && Number(currentMessage.value?.id) === Number(id)) {
            feedback.value = { type: 'warning', text: 'Данные CRM сохранены. Обновите письмо, чтобы увидеть связи.' }
        }
    }
}

function folderFromPath(path, url = null) {
    const normalized = normalizePath(path)
    const parts = normalized.split('/').filter(Boolean)

    return {
        name: parts[parts.length - 1] || 'attachments',
        path: normalized,
        relative_path: normalized,
        url,
    }
}

function mergeFolders(folders) {
    const byPath = new Map()

    folders
        .filter((folder) => folder?.path)
        .forEach((folder) => {
            const path = normalizePath(folder.path)
            byPath.set(path, {
                ...folder,
                path,
                relative_path: folder.relative_path || path,
            })
        })

    return Array.from(byPath.values()).sort((a, b) => String(a.path).localeCompare(String(b.path)))
}

function upsertStorageFolder(folder) {
    if (!folder?.path) {
        return
    }

    const path = normalizePath(folder.path)
    const existingIndex = storageFolders.value.findIndex((item) => normalizePath(item.path) === path)
    const payload = {
        ...folder,
        path,
    }

    if (existingIndex >= 0) {
        storageFolders.value.splice(existingIndex, 1, payload)
    } else {
        storageFolders.value.push(payload)
        storageFolders.value.sort((a, b) => String(a.path).localeCompare(String(b.path)))
    }
}

function folderCreatePath() {
    const selected = normalizePath(selectedFolderPath.value)
    const typed = normalizePath(newFolderPath.value)

    if (!typed) {
        return selected
    }

    if (selected && !typed.includes('/')) {
        return `${selected}/${typed}`
    }

    return typed
}

async function loadAttachmentFolders() {
    if (!currentMessage.value?.id) {
        return
    }

    const messageId = currentMessage.value.id
    const request = ++folderRequest
    foldersLoading.value = true

    try {
        const { data } = await axios.get(`/api/mail-messages/${messageId}/attachment-folders`)
        if (request !== folderRequest || currentMessage.value?.id !== messageId || !model.value) return
        storageFolders.value = mergeFolders(data.folders || [])

        if (!selectedFolderPath.value) {
            selectedFolderPath.value = allStorageFolders.value.find((folder) => folder.path === defaultFolderPath())?.path
                || allStorageFolders.value[0]?.path
                || defaultFolderPath()
        }
    } catch (error) {
        if (request !== folderRequest || currentMessage.value?.id !== messageId || !model.value) return
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось загрузить папки S3.',
        }
    } finally {
        if (request === folderRequest) foldersLoading.value = false
    }
}

async function createAttachmentFolder() {
    const folder = folderCreatePath()

    if (readerLoading.value || !currentMessage.value?.id || !folder) {
        return
    }

    creatingFolder.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${currentMessage.value.id}/attachment-folders`, {
            folder,
        })

        storageFolders.value = mergeFolders(data.folders || storageFolders.value)
        upsertStorageFolder(data.folder)
        selectedFolderPath.value = data.folder?.path || folder
        newFolderPath.value = ''
        feedback.value = {
            type: 'success',
            text: `Папка S3 создана: ${selectedFolderPath.value}`,
        }
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось создать папку S3.',
        }
    } finally {
        creatingFolder.value = false
    }
}

async function saveAttachment(attachment) {
    if (readerLoading.value || !currentMessage.value?.id || attachment?.index === null || attachment?.index === undefined) {
        return
    }

    savingAttachmentIndex.value = attachment.index
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${currentMessage.value.id}/attachments/${attachment.index}/save`, {
            folder: targetFolderPath.value,
        })

        const nextMessage = data.mail_message || data
        const savedAttachment = data.saved_attachment
            || nextMessage.saved_attachment
            || nextMessage.available_attachments?.find((item) => Number(item.index) === Number(attachment.index))

        if (savedAttachment?.folder_path) {
            upsertStorageFolder(folderFromPath(savedAttachment.folder_path, savedAttachment.folder_url))
        } else if (targetFolderPath.value) {
            upsertStorageFolder(folderFromPath(targetFolderPath.value, targetFolder.value?.url))
        }

        applyMessageUpdate(nextMessage)
        selectedAttachmentIndex.value = attachment.index
        lastSavedAttachment.value = savedAttachment || {
            ...attachment,
            is_saved: true,
            folder_path: targetFolderPath.value,
        }
        feedback.value = {
            type: 'success',
            text: `Файл "${attachmentName(attachment)}" сохранён: ${savedAttachment?.path || targetFolderPath.value}`,
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

async function downloadErrorMessage(error, fallback) {
    const data = error?.response?.data

    if (data instanceof Blob) {
        const text = await data.text()

        try {
            return JSON.parse(text)?.message || fallback
        } catch {
            return fallback
        }
    }

    return data?.message || fallback
}

async function downloadAttachment(attachment) {
    if (!canDownloadAttachment(attachment)) {
        return
    }

    downloadingAttachmentIndex.value = attachment.index
    feedback.value = null

    try {
        const response = await axios.get(downloadAttachmentUrl(attachment), {
            responseType: 'blob',
        })
        const blob = new Blob([response.data], {
            type: response.headers?.['content-type'] || attachmentMime(attachment) || 'application/octet-stream',
        })
        const url = window.URL.createObjectURL(blob)
        const link = document.createElement('a')

        link.href = url
        link.download = attachmentName(attachment)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.setTimeout(() => window.URL.revokeObjectURL(url), 1000)
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: await downloadErrorMessage(error, 'Не удалось скачать файл.'),
        }
    } finally {
        downloadingAttachmentIndex.value = null
    }
}

async function saveNote() {
    if (readerLoading.value || actionLoading.value || !currentMessage.value?.id || !noteBody.value.trim()) {
        return
    }

    const messageId = currentMessage.value.id
    actionLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${messageId}/notes`, {
            title: noteTitle.value,
            body: noteBody.value,
            importance: noteImportance.value,
        })

        if (!model.value || Number(currentMessage.value?.id) !== Number(messageId)) return
        applyMessageUpdate(data)
        noteTitle.value = ''
        noteBody.value = ''
        feedback.value = {
            type: 'success',
            text: 'Важная информация сохранена.',
        }
    } catch (error) {
        if (!model.value || Number(currentMessage.value?.id) !== Number(messageId)) return
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось сохранить информацию.',
        }
    } finally {
        actionLoading.value = false
    }
}

async function createLead() {
    if (readerLoading.value || actionLoading.value || leads.value.length || !currentMessage.value?.id || !leadTitle.value.trim()) {
        return
    }

    const messageId = currentMessage.value.id
    actionLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${messageId}/lead`, {
            title: leadTitle.value,
            description: leadDescription.value,
            entity_id: currentDefaultEntityId.value,
            unit_id: currentDefaultUnitId.value,
        })

        if (!model.value || Number(currentMessage.value?.id) !== Number(messageId)) return
        applyMessageUpdate(data.mail_message)
        feedback.value = {
            type: 'success',
            text: data.created === false ? `Лид #${data.lead?.id} уже существует для этого письма.` : `Лид #${data.lead?.id} создан.`,
        }
    } catch (error) {
        if (!model.value || Number(currentMessage.value?.id) !== Number(messageId)) return
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось создать лид.',
        }
    } finally {
        actionLoading.value = false
    }
}

function cancelRelatedMessage() {
    relatedMessageRequest?.abort()
    relatedMessageRequest = null
    relatedMessageLoading.value = false
}

async function openRelatedMessage(message) {
    if (relatedNavigationBlocked.value || !message?.id || Number(message.id) === Number(currentMessage.value?.id)) return

    cancelRelatedMessage()
    const request = new AbortController()
    relatedMessageRequest = request
    relatedMessageLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.get(`/api/mail-messages/${message.id}`, {
            signal: request.signal,
            timeout: 60000,
        })
        if (request.signal.aborted || !model.value) return
        if (Number(data?.id) !== Number(message.id)) throw new Error('Invalid mail response')
        applyMessageUpdate(data)
    } catch (error) {
        if (!request.signal.aborted && model.value) {
            feedback.value = {
                type: 'error',
                text: error?.response?.data?.message || 'Не удалось открыть письмо. Выберите его в списке ещё раз.',
            }
        }
    } finally {
        if (relatedMessageRequest === request) {
            relatedMessageRequest = null
            relatedMessageLoading.value = false
        }
    }
}

watch(() => props.message, () => {
    cancelRelatedMessage()
    localMessage.value = null
})

watch(() => currentMessage.value?.id, async () => {
    cancelRelatedMessage()
    storageFolders.value = []
    selectedFolderPath.value = null
    newFolderPath.value = ''
    resetForms()

    if (model.value && currentMessage.value?.id) {
        await loadAttachmentFolders()
    }
})

watch(model, async (isOpen) => {
    if (!isOpen) {
        crmNoteDialog.value = false
        crmLeadDialog.value = false
        cancelRelatedMessage()
        folderRequest++
        foldersLoading.value = false
        return
    }
    if (isOpen && currentMessage.value?.id) {
        await loadAttachmentFolders()
    }
})

onBeforeUnmount(() => {
    cancelRelatedMessage()
    folderRequest++
})
</script>

<template>
    <v-dialog
        v-model="model"
        class="mail-reader-dialog"
        width="calc(100vw - 24px)"
        max-width="calc(100vw - 24px)"
        height="calc(100dvh - 24px)"
        max-height="calc(100dvh - 24px)"
        scrollable
    >
        <v-card class="mail-reader-card rounded border border-blue-900 bg-slate-950">
            <MailMessageReaderHeader
                v-if="model"
                :message="currentMessage"
                :loading="readerLoading"
                :related-disabled="relatedNavigationBlocked"
                :feedback="feedback"
                :sync-error="syncError"
                :contexts="relatedContexts"
                @reload="emit('reload')"
                @reply="emit('reply', $event)"
                @close="model = false"
                @open="openRelatedMessage"
            >
                <template #tools>
                    <MailMessageCrmTools
                        :message="currentMessage"
                        :disabled="relatedNavigationBlocked"
                        :default-entity-id="currentDefaultEntityId"
                        :default-unit-id="currentDefaultUnitId"
                        @changed="refreshCrmMessage"
                        @busy="crmActionLoading = $event"
                        @notice="feedback = $event"
                        @note="feedback = null; crmNoteDialog = true"
                        @lead="feedback = null; crmLeadDialog = true"
                    />
                </template>
            </MailMessageReaderHeader>

            <v-divider />

            <v-card-text class="mail-reader-body">
                <div class="mail-reader-layout">
                    <div class="mail-reader-main">
                        <v-card variant="tonal" color="blue" class="mail-tools-card mail-attachments-card">
                            <v-card-title class="mail-attachments-card__title py-1">
                                <span>Вложения</span>
                                <small v-if="attachmentRows.length">{{ attachmentRows.length }} файлов</small>
                            </v-card-title>

                            <v-card-text class="mail-attachments-card__body pt-0">
                                <div v-if="attachmentRows.length" class="mail-attachments-toolbar">
                                    <v-combobox
                                        v-model="selectedFolderPath"
                                        :items="folderOptions"
                                        item-title="title"
                                        item-value="value"
                                        label="Папка S3"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        clearable
                                        :return-object="false"
                                        :loading="foldersLoading"
                                    />

                                    <v-text-field
                                        v-model="newFolderPath"
                                        label="Новая папка"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        @keydown.enter.prevent="createAttachmentFolder"
                                    />

                                    <v-btn
                                        size="small"
                                        color="blue"
                                        variant="tonal"
                                        prepend-icon="mdi-folder-plus-outline"
                                        :loading="creatingFolder"
                                        :disabled="readerLoading || !folderCreatePath()"
                                        @click="createAttachmentFolder"
                                    >
                                        Создать
                                    </v-btn>

                                    <div v-if="quickFolderTargets.length" class="mail-attachments-quick-folders">
                                        <v-btn
                                            v-for="folder in quickFolderTargets"
                                            :key="folder.path"
                                            size="x-small"
                                            density="compact"
                                            variant="tonal"
                                            color="blue"
                                            @click="selectedFolderPath = folder.path"
                                        >
                                            {{ folder.path.startsWith('units/') ? 'Unit' : 'Mail' }}
                                        </v-btn>
                                    </div>
                                </div>

                                <div v-if="attachmentRows.length" class="mail-attachments-target">
                                    <span>S3:</span>
                                    <code>{{ targetFolderPath }}</code>
                                    <a
                                        v-if="targetFolder?.url"
                                        :href="targetFolder.url"
                                        target="_blank"
                                    >
                                        folder
                                    </a>
                                </div>

                                <div v-if="lastSavedAttachment" class="mail-attachments-saved">
                                    <strong>Saved:</strong>
                                    <code>{{ lastSavedAttachment.path || lastSavedAttachment.folder_path }}</code>
                                    <a
                                        v-if="lastSavedAttachment.url"
                                        :href="lastSavedAttachment.url"
                                        target="_blank"
                                    >
                                        file
                                    </a>
                                    <a
                                        v-if="lastSavedAttachment.folder_url"
                                        :href="lastSavedAttachment.folder_url"
                                        target="_blank"
                                    >
                                        folder
                                    </a>
                                </div>

                                <div
                                    class="mail-attachments-workspace"
                                    :class="{ 'mail-attachments-workspace--empty': !attachmentRows.length }"
                                >
                                    <div class="mail-attachments-sidebar">
                                        <MailInvoiceDetails
                                            v-if="model && selectedPdfAttachment && currentMessage?.id"
                                            :key="selectedPdfIdentity"
                                            :message-id="currentMessage.id"
                                            :attachment-index="selectedPdfAttachment.index"
                                            :attachment-id="selectedPdfAttachment.id || null"
                                        />

                                        <div v-if="attachmentRows.length" class="mail-attachments-sheet">
                                        <div class="mail-attachments-sheet__row is-head">
                                            <span>#</span>
                                            <span>Файл</span>
                                            <span>Размер</span>
                                            <span>ПК</span>
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
                                            :title="attachmentMime(attachment)"
                                            @click="selectAttachment(attachment)"
                                            @keydown.enter.prevent="selectAttachment(attachment)"
                                        >
                                            <span class="mail-attachments-sheet__num">{{ index + 1 }}</span>
                                            <span class="mail-attachments-sheet__file" :title="attachmentName(attachment)">
                                                <v-icon
                                                    :icon="attachmentIcon(attachment)"
                                                    size="13"
                                                />
                                                <span>{{ attachmentName(attachment) }}</span>
                                            </span>
                                            <span>{{ formatSize(attachment.size) }}</span>
                                            <span class="mail-attachments-sheet__download">
                                                <v-btn
                                                    icon="mdi-download"
                                                    size="x-small"
                                                    density="compact"
                                                    variant="text"
                                                    color="cyan"
                                                    :loading="downloadingAttachmentIndex === attachment.index"
                                                    :disabled="!canDownloadAttachment(attachment)"
                                                    @click.stop="downloadAttachment(attachment)"
                                                />
                                            </span>
                                            <span class="mail-attachments-sheet__s3" @keydown.enter.stop>
                                                <template v-if="isAttachmentSaved(attachment)">
                                                    <span class="mail-attachments-sheet__saved" role="img" aria-label="Сохранено в S3" title="Сохранено в S3"><v-icon icon="mdi-check-circle-outline" size="14" /></span>
                                                    <a
                                                        v-if="attachment.url"
                                                        :href="attachment.url"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        @click.stop
                                                    >
                                                        file
                                                    </a>
                                                    <a
                                                        v-if="attachment.folder_url"
                                                        :href="attachment.folder_url"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        @click.stop
                                                    >
                                                        dir
                                                    </a>
                                                </template>

                                                <v-btn
                                                    v-if="canSaveAttachment(attachment)"
                                                    size="x-small"
                                                    density="compact"
                                                    variant="tonal"
                                                    color="blue"
                                                    :loading="savingAttachmentIndex === attachment.index"
                                                    :disabled="readerLoading"
                                                    @click.stop="saveAttachment(attachment)"
                                                >
                                                    save
                                                </v-btn>
                                            </span>
                                        </div>
                                        </div>

                                        <div v-else class="mail-attachments-empty">
                                            {{ hasAttachmentSignal ? 'Вложения ещё не загружены. Нажмите refresh письма.' : 'Вложений нет.' }}
                                        </div>

                                    </div>

                                    <div v-if="attachmentRows.length" class="mail-attachment-preview">
                                        <template v-if="selectedAttachment">
                                            <div class="mail-attachment-preview__meta">
                                                <strong>{{ attachmentName(selectedAttachment) }}</strong>
                                                <span>{{ attachmentMime(selectedAttachment) }} · {{ formatSize(selectedAttachment.size) }}</span>
                                            </div>

                                            <img
                                                v-if="selectedImageAttachment && (selectedImageAttachment.preview_url || selectedImageAttachment.url)"
                                                :src="selectedImageAttachment.preview_url || selectedImageAttachment.url"
                                                :alt="attachmentName(selectedImageAttachment)"
                                            >

                                            <MailPdfViewer
                                                v-else-if="model && selectedPdfAttachment && currentMessage?.id"
                                                :key="selectedPdfIdentity"
                                                class="mail-attachment-preview__pdf"
                                                :src="downloadAttachmentUrl(selectedPdfAttachment)"
                                                :title="attachmentName(selectedPdfAttachment)"
                                            />

                                            <WordAttachmentPreview
                                                v-else-if="model && selectedWordAttachment && currentMessage?.id"
                                                :key="selectedWordIdentity"
                                                :message-id="Number(currentMessage.id)"
                                                :attachment-index="Number(selectedWordAttachment.index)"
                                                :attachment-id="selectedWordAttachment.id ? Number(selectedWordAttachment.id) : null"
                                                :filename="attachmentName(selectedWordAttachment)"
                                            />

                                            <div v-else class="mail-attachment-preview__empty">
                                                {{
                                                    isAttachmentImage(selectedAttachment) || isAttachmentPdf(selectedAttachment)
                                                        ? 'Preview недоступен. Сохраните файл в S3 и откройте ссылку.'
                                                        : 'Для этого типа файла preview не выводится.'
                                                }}
                                            </div>

                                            <div v-if="isAttachmentSaved(selectedAttachment)" class="mail-attachment-preview__storage">
                                                <span>Path</span>
                                                <code>{{ selectedAttachment.path }}</code>

                                                <div>
                                                    <a
                                                        v-if="selectedAttachment.url"
                                                        :href="selectedAttachment.url"
                                                        target="_blank"
                                                    >
                                                        открыть файл
                                                    </a>
                                                    <a
                                                        v-if="selectedAttachment.folder_url"
                                                        :href="selectedAttachment.folder_url"
                                                        target="_blank"
                                                    >
                                                        открыть папку
                                                    </a>
                                                </div>
                                            </div>
                                        </template>

                                        <div v-else class="mail-attachment-preview__empty">
                                            Выберите вложение.
                                        </div>
                                    </div>
                                </div>

                            </v-card-text>
                        </v-card>

                        <v-progress-linear
                            v-if="readerLoading"
                            indeterminate
                            color="blue"
                            class="my-2"
                        />

                        <div
                            v-if="bodyHtml"
                            class="mail-body bg-white text-black rounded"
                            v-html="bodyHtml"
                        />

                        <pre
                            v-else
                            class="mail-body-text rounded border border-blue-900 bg-slate-900 text-grey-lighten-2"
                        >{{ bodyText }}</pre>
                    </div>
                </div>
            </v-card-text>

            <v-card-actions class="mail-reader-actions">
                <v-btn
                    v-if="currentMessage?.direction === 'incoming'"
                    color="teal"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-reply"
                    :disabled="readerLoading"
                    @click="emit('reply', currentMessage)"
                >
                    Ответить
                </v-btn>

                <v-spacer />

                <v-btn
                    text="Закрыть"
                    variant="text"
                    size="small"
                    @click="model = false"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="crmNoteDialog" max-width="560" :persistent="actionLoading" scrollable>
        <v-card theme="dark" class="mail-reader-crm-dialog">
            <v-card-title>Важное из письма</v-card-title>
            <v-card-text class="mail-reader-crm-dialog__body">
                <v-text-field v-model="noteTitle" label="Заголовок" density="compact" variant="outlined" hide-details :disabled="actionLoading" />
                <v-select v-model="noteImportance" :items="[{ title: 'Обычная', value: 'normal' }, { title: 'Важная', value: 'important' }, { title: 'Критичная', value: 'critical' }]" label="Важность" density="compact" variant="outlined" hide-details :disabled="actionLoading" />
                <v-textarea v-model="noteBody" label="Заметка" rows="4" density="compact" variant="outlined" hide-details :disabled="actionLoading" />
                <v-alert v-if="feedback" :type="feedback.type === 'error' ? 'error' : 'success'" density="compact" variant="tonal">{{ feedback.text }}</v-alert>
                <div v-if="notes.length" class="mail-reader-crm-dialog__history"><article v-for="note in notes" :key="note.id"><strong>{{ note.title || 'Заметка' }}</strong><p>{{ note.body }}</p></article></div>
            </v-card-text>
            <v-card-actions><v-btn variant="text" :disabled="actionLoading" @click="crmNoteDialog = false">Закрыть</v-btn><v-spacer /><v-btn variant="tonal" color="teal" :loading="actionLoading" :disabled="readerLoading || actionLoading || !noteBody.trim()" @click="saveNote">Сохранить заметку</v-btn></v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="crmLeadDialog" max-width="560" :persistent="actionLoading" scrollable>
        <v-card theme="dark" class="mail-reader-crm-dialog">
            <v-card-title>{{ leads.length ? 'Лид этого письма' : 'Создать лид из письма' }}</v-card-title>
            <v-card-text class="mail-reader-crm-dialog__body">
                <template v-if="leads.length">
                    <p class="mail-reader-crm-dialog__hint">Письмо уже связано с лидом.</p>
                    <article v-for="lead in leads" :key="lead.id" class="mail-reader-crm-dialog__lead"><strong>#{{ lead.id }} · {{ lead.title }}</strong><span>{{ lead.status }}</span><p v-if="lead.description">{{ lead.description }}</p></article>
                </template>
                <template v-else><v-text-field v-model="leadTitle" label="Название лида" density="compact" variant="outlined" hide-details :disabled="actionLoading" /><v-textarea v-model="leadDescription" label="Описание" rows="5" density="compact" variant="outlined" hide-details :disabled="actionLoading" /></template>
                <v-alert v-if="feedback" :type="feedback.type === 'error' ? 'error' : 'success'" density="compact" variant="tonal">{{ feedback.text }}</v-alert>
            </v-card-text>
            <v-card-actions><v-btn variant="text" :disabled="actionLoading" @click="crmLeadDialog = false">Закрыть</v-btn><v-spacer /><v-btn v-if="!leads.length" variant="tonal" color="amber" :loading="actionLoading" :disabled="readerLoading || actionLoading || !leadTitle.trim()" @click="createLead">Создать лид</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.mail-reader-crm-dialog { border: 1px solid #354964; border-radius: 12px; background: #101d30; color: #d2def0; }
.mail-reader-crm-dialog > .v-card-title { font-size: 15px; color: #c5e4fc; padding: 14px 16px 8px; }
.mail-reader-crm-dialog__body { display: flex; flex-direction: column; gap: 12px; padding: 12px 16px !important; }
.mail-reader-crm-dialog__body :deep(.v-field), .mail-reader-crm-dialog__body :deep(.v-label), .mail-reader-crm-dialog__body :deep(.v-alert) { font-size: 12px; }
.mail-reader-crm-dialog__history { display: flex; flex-direction: column; gap: 8px; }
.mail-reader-crm-dialog__history article, .mail-reader-crm-dialog__lead { padding: 10px; border: 1px solid #354964; border-radius: 7px; font-size: 12px; }
.mail-reader-crm-dialog__history p, .mail-reader-crm-dialog__lead p { margin-top: 5px; color: #a8bdd4; white-space: pre-wrap; overflow-wrap: anywhere; }
.mail-reader-crm-dialog__lead > span { float: right; color: #f3cd8d; font-size: 11px; }
.mail-reader-crm-dialog__hint { color: #93a7c1; font-size: 12px; }
.mail-reader-crm-dialog :deep(.v-card-actions .v-btn) { font-size: 11px; letter-spacing: 0; text-transform: none; }

:global(.mail-reader-dialog > .v-overlay__content) {
    margin: 12px;
}

.mail-reader-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow: hidden;
}

.mail-reader-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    padding: 6px 10px 8px !important;
}

.mail-reader-layout {
    display: grid;
    align-items: stretch;
    flex: 1;
    gap: 6px;
    min-height: 0;
}

.mail-reader-main {
    display: grid;
    flex: 1;
    gap: 6px;
    grid-template-columns: minmax(0, 1fr) clamp(300px, 26vw, 600px);
    grid-template-rows: minmax(0, 1fr);
    min-height: 0;
    min-width: 0;
}

.mail-reader-main > .mail-attachments-card {
    grid-column: 1;
    grid-row: 1;
    min-height: 0;
}

.mail-reader-main > .v-progress-linear {
    align-self: start;
    grid-column: 1;
    grid-row: 1;
    margin: 0 !important;
    z-index: 1;
}

.mail-reader-main > .mail-body,
.mail-reader-main > .mail-body-text {
    grid-column: 2;
    grid-row: 1;
    min-height: 0;
}

.mail-reader-actions {
    min-height: 38px;
    padding: 3px 8px !important;
}

.mail-tools-card,
.mail-attachments-card {
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.mail-attachments-card__body {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
    padding-left: 8px;
    padding-right: 8px;
    padding-bottom: 8px;
}





.mail-attachments-card__title {
    align-items: baseline;
    display: flex;
    gap: 8px;
    justify-content: space-between;
}

.mail-attachments-card__title span {
    font-size: 14px;
    font-weight: 900;
}

.mail-attachments-card__title small {
    color: #93c5fd;
    font-size: 12px;
    font-weight: 800;
}

.mail-attachments-toolbar {
    align-items: center;
    display: grid;
    gap: 4px;
    grid-template-columns: minmax(160px, 1.2fr) minmax(120px, 0.9fr) auto auto;
    margin-bottom: 4px;
}

.mail-attachments-quick-folders {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.mail-attachments-workspace {
    display: grid;
    flex: 1;
    gap: 6px;
    grid-template-columns: clamp(320px, 24vw, 480px) minmax(0, 1fr);
    min-height: 0;
}

.mail-attachments-sidebar {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 0;
    min-width: 0;
    overflow: auto;
    scrollbar-width: thin;
}

.mail-attachments-sidebar > .mail-invoice-details {
    flex: 0 0 auto;
}

.mail-attachments-workspace--empty .mail-attachments-sidebar {
    display: grid;
    grid-column: 1 / -1;
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: minmax(0, 1fr);
}

.mail-attachments-workspace--empty .mail-attachments-empty {
    grid-column: 1;
    grid-row: 1;
}


.mail-attachments-target,
.mail-attachments-saved {
    align-items: center;
    background: rgba(15, 23, 42, 0.58);
    border: 1px solid rgba(147, 197, 253, 0.18);
    border-radius: 8px;
    color: #bfdbfe;
    display: flex;
    font-size: 12px;
    gap: 6px;
    margin-bottom: 4px;
    min-width: 0;
    overflow: hidden;
    padding: 2px 5px;
}

.mail-attachments-saved {
    border-color: rgba(134, 239, 172, 0.28);
    color: #bbf7d0;
}

.mail-attachments-target code,
.mail-attachments-saved code,
.mail-attachment-preview__storage code {
    color: #e0f2fe;
    font-size: 12px;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachments-target a,
.mail-attachments-saved a,
.mail-attachment-preview__storage a {
    color: #86efac;
    font-size: 12px;
    font-weight: 900;
    text-decoration: none;
}

.mail-attachments-target a:hover,
.mail-attachments-saved a:hover,
.mail-attachment-preview__storage a:hover {
    text-decoration: underline;
}

.mail-attachments-sheet {
    background: rgba(15, 23, 42, 0.78);
    border: 1px solid rgba(147, 197, 253, 0.28);
    border-radius: 10px;
    flex: 0 1 auto;
    max-height: 180px;
    min-height: 82px;
    overflow: auto;
}

.mail-attachments-sheet__row {
    align-items: center;
    background: transparent;
    border: 0;
    border-bottom: 1px solid rgba(147, 197, 253, 0.18);
    color: #dbeafe;
    cursor: pointer;
    display: grid;
    font-size: 12px;
    gap: 0;
    grid-template-columns: 22px minmax(0, 1fr) 48px 32px 132px;
    line-height: 1.15;
    padding: 0;
    text-align: left;
    width: 100%;
}

.mail-attachments-sheet__row > span {
    min-height: 32px;
    min-width: 0;
    overflow: hidden;
    padding: 5px 3px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachments-sheet__row > span + span {
    border-left: 1px solid rgba(147, 197, 253, 0.14);
}

.mail-attachments-sheet__row.is-head {
    background: #1e3a8a;
    color: #eff6ff;
    cursor: default;
    font-size: 10px;
    font-weight: 950;
    letter-spacing: 0.06em;
    position: sticky;
    text-transform: uppercase;
    top: 0;
    z-index: 1;
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
    gap: 4px;
    justify-content: center;
}

.mail-attachments-sheet__download {
    align-items: center;
    display: inline-flex;
    justify-content: center;
}

.mail-attachments-sheet__s3 a,
.mail-attachments-sheet__saved {
    color: #86efac;
    font-size: 11px;
    font-weight: 900;
    text-decoration: none;
}

.mail-attachments-sheet__saved {
    color: #bbf7d0;
    text-transform: uppercase;
}

.mail-attachments-sheet__s3 a:hover {
    text-decoration: underline;
}

.mail-attachment-preview {
    background:
        linear-gradient(135deg, rgba(15, 23, 42, 0.92), rgba(30, 41, 59, 0.92)),
        repeating-linear-gradient(45deg, rgba(147, 197, 253, 0.08) 0 8px, transparent 8px 16px);
    border: 1px solid rgba(147, 197, 253, 0.28);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 0;
    min-width: 0;
    overflow: hidden;
    padding: 8px;
}

.mail-attachment-preview__meta {
    align-items: center;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    min-width: 0;
}

.mail-attachment-preview__meta strong {
    color: #dbeafe;
    font-size: 12px;
    font-weight: 500;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachment-preview__meta span {
    color: #94a3b8;
    flex: 0 0 auto;
    font-size: 10px;
}

.mail-attachment-preview img {
    border-radius: 10px;
    display: block;
    flex: 1 1 auto;
    min-height: 0;
    object-fit: contain;
    width: 100%;
}

.mail-attachment-preview__pdf {
    background: #f8fafc;
    border: 1px solid rgba(191, 219, 254, 0.24);
    border-radius: 10px;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    width: 100%;
}

.mail-attachment-preview__empty,
.mail-attachments-empty {
    color: #94a3b8;
    display: grid;
    flex: 1 1 auto;
    font-size: 13px;
    font-weight: 800;
    min-height: 180px;
    place-items: center;
    text-align: center;
}

.mail-attachment-preview__storage {
    border-top: 1px solid rgba(147, 197, 253, 0.18);
    color: #bfdbfe;
    display: grid;
    font-size: 12px;
    gap: 4px;
    min-width: 0;
    padding-top: 6px;
}

.mail-attachment-preview__storage div {
    display: flex;
    gap: 8px;
}

.mail-body,
.mail-body-text {
    font-family: 'Rubik-Medium', 'Roboto-Regular', 'Segoe UI', sans-serif;
    font-size: 14px;
    letter-spacing: -0.01em;
    line-height: 1.4;
    height: 100%;
    max-height: none;
    min-height: 0;
    overflow: auto;
    padding: 8px 10px;
}

.mail-body :deep(p),
.mail-body :deep(div),
.mail-body :deep(td),
.mail-body :deep(li),
.mail-body :deep(span),
.mail-body :deep(a),
.mail-body :deep(font) {
    font-family: 'Rubik-Medium', 'Roboto-Regular', 'Segoe UI', sans-serif !important;
    line-height: 1.4;
}

.mail-body :deep(p) {
    margin: 0.35em 0;
}

.mail-body :deep(h1),
.mail-body :deep(h2),
.mail-body :deep(h3),
.mail-body :deep(h4) {
    font-family: 'Rubik-Medium', 'Roboto-Regular', sans-serif !important;
    font-size: 16px !important;
    line-height: 1.3;
    margin: 0.35em 0;
}

.mail-body :deep(img) {
    height: auto;
    max-height: 80px;
    max-width: 100%;
}

.mail-body-text {
    white-space: pre-wrap;
}

@media (max-width: 1100px) {
    .mail-reader-main {
        grid-template-columns: 1fr;
        grid-template-rows: minmax(0, 1fr) minmax(80px, 20%);
    }

    .mail-reader-main > .mail-body,
    .mail-reader-main > .mail-body-text {
        grid-column: 1;
        grid-row: 2;
        max-height: none;
    }

    .mail-attachments-workspace {
        grid-template-columns: clamp(300px, 32vw, 340px) minmax(0, 1fr);
    }

    .mail-attachments-workspace--empty .mail-attachments-sidebar {
        grid-template-columns: 1fr;
        grid-template-rows: minmax(180px, 1fr);
    }

    .mail-attachments-workspace--empty .mail-attachments-empty {
        grid-column: 1;
        grid-row: 1;
    }


    .mail-attachments-toolbar {
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) auto auto;
    }
}

@media (max-width: 700px) {
    .mail-reader-body {
        overflow: auto;
    }

    .mail-reader-layout,
    .mail-reader-main {
        flex: 0 0 auto;
    }

    .mail-reader-main {
        grid-template-rows: auto minmax(100px, 180px);
    }

    .mail-attachments-toolbar {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .mail-attachments-workspace {
        flex: 0 0 auto;
        grid-template-columns: 1fr;
        grid-template-rows: auto minmax(360px, 55dvh);
    }

    .mail-attachments-sidebar {
        max-height: 330px;
    }

    .mail-attachments-workspace--empty {
        grid-template-rows: auto;
    }

    .mail-attachment-preview__meta {
        align-items: flex-start;
        flex-direction: column;
        gap: 2px;
    }
}

@media (max-height: 500px) and (min-width: 701px) {
    .mail-reader-body {
        overflow: auto;
    }

    .mail-reader-layout,
    .mail-reader-main {
        flex: 0 0 auto;
        height: 380px;
        min-height: 380px;
    }

    .mail-reader-main {
        grid-template-columns: minmax(0, 1fr) clamp(180px, 24vw, 600px);
        grid-template-rows: minmax(0, 1fr);
    }

    .mail-reader-main > .mail-body,
    .mail-reader-main > .mail-body-text {
        grid-column: 2;
        grid-row: 1;
    }

    .mail-attachments-workspace {
        grid-template-columns: clamp(260px, 24vw, 480px) minmax(0, 1fr);
    }

    .mail-attachments-toolbar {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
    }

    .mail-attachments-quick-folders {
        display: none;
    }
}
</style>
