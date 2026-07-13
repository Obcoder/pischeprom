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

const currentMessage = computed(() => localMessage.value || props.message)

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
const selectedAttachment = computed(() => {
    return attachmentRows.value.find((attachment) => Number(attachment.index) === Number(selectedAttachmentIndex.value))
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
const notes = computed(() => currentMessage.value?.notes || [])
const leads = computed(() => currentMessage.value?.leads || [])
const hasAttachmentSignal = computed(() => Boolean(currentMessage.value?.has_attachments || attachmentRows.value.length || attachments.value.length))
const quickFolderTargets = computed(() => {
    const targets = []

    if (props.defaultUnitId) {
        targets.push(folderFromPath(`units/${props.defaultUnitId}`))
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

function attachmentPreviewSrc(attachment) {
    return attachment?.preview_url || attachment?.url || null
}

function pdfPreviewSrc(attachment) {
    const src = attachmentPreviewSrc(attachment)

    if (!src) {
        return null
    }

    return String(src).startsWith('data:')
        ? src
        : `${src}#toolbar=0&navpanes=0&view=FitH`
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
    selectedAttachmentIndex.value = imageAttachments.value[0]?.index ?? attachmentRows.value[0]?.index ?? null
    lastSavedAttachment.value = null
}

function applyMessageUpdate(message) {
    if (!message) {
        return
    }

    localMessage.value = message
    emit('updated', message)
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

    foldersLoading.value = true

    try {
        const { data } = await axios.get(`/api/mail-messages/${currentMessage.value.id}/attachment-folders`)
        storageFolders.value = mergeFolders(data.folders || [])

        if (!selectedFolderPath.value) {
            selectedFolderPath.value = allStorageFolders.value.find((folder) => folder.path === defaultFolderPath())?.path
                || allStorageFolders.value[0]?.path
                || defaultFolderPath()
        }
    } catch (error) {
        feedback.value = {
            type: 'error',
            text: error?.response?.data?.message || 'Не удалось загрузить папки S3.',
        }
    } finally {
        foldersLoading.value = false
    }
}

async function createAttachmentFolder() {
    const folder = folderCreatePath()

    if (!currentMessage.value?.id || !folder) {
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
    if (!currentMessage.value?.id || attachment?.index === null || attachment?.index === undefined) {
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
    if (!currentMessage.value?.id || !noteBody.value.trim()) {
        return
    }

    actionLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${currentMessage.value.id}/notes`, {
            title: noteTitle.value,
            body: noteBody.value,
            importance: noteImportance.value,
        })

        applyMessageUpdate(data)
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
    if (!currentMessage.value?.id || !leadTitle.value.trim()) {
        return
    }

    actionLoading.value = true
    feedback.value = null

    try {
        const { data } = await axios.post(`/api/mail-messages/${currentMessage.value.id}/lead`, {
            title: leadTitle.value,
            description: leadDescription.value,
            entity_id: props.defaultEntityId,
            unit_id: props.defaultUnitId,
        })

        applyMessageUpdate(data.mail_message)
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

watch(() => props.message?.id, async () => {
    localMessage.value = null
    selectedFolderPath.value = null
    newFolderPath.value = ''
    resetForms()

    if (model.value && currentMessage.value?.id) {
        await loadAttachmentFolders()
    }
})

watch(model, async (isOpen) => {
    if (isOpen && currentMessage.value?.id) {
        await loadAttachmentFolders()
    }
})
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
                        {{ currentMessage?.subject || 'Без темы' }}
                    </div>

                    <div class="text-[11px] text-grey mt-1">
                        {{ formatDate(currentMessage?.message_date) }}
                    </div>
                </div>

                <div class="d-flex ga-1">
                    <v-chip
                        size="small"
                        :color="currentMessage?.direction === 'incoming' ? 'purple' : 'blue'"
                        variant="tonal"
                    >
                        {{ currentMessage?.direction === 'incoming' ? 'Входящее' : 'Исходящее' }}
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
                        v-if="currentMessage?.direction === 'incoming'"
                        icon="mdi-reply"
                        size="small"
                        variant="text"
                        color="teal"
                        :disabled="loading"
                        @click="emit('reply', currentMessage)"
                    />
                </div>
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row dense>
                    <v-col cols="12" lg="6">
                        <div class="text-[11px] text-grey">From</div>
                        <div class="text-sm text-purple-lighten-3">
                            {{ currentMessage?.from_name || '' }}
                            {{ currentMessage?.from_address || '—' }}
                        </div>
                    </v-col>

                    <v-col cols="12" lg="6">
                        <div class="text-[11px] text-grey">To</div>
                        <div class="text-sm text-blue-lighten-3">
                            {{ recipients(currentMessage?.to) }}
                        </div>

                        <div
                            v-if="currentMessage?.cc?.length"
                            class="mt-1"
                        >
                            <div class="text-[11px] text-grey">CC</div>
                            <div class="text-sm text-blue-lighten-3">
                                {{ recipients(currentMessage?.cc) }}
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

                <v-alert
                    v-if="syncError"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mb-3"
                >
                    {{ syncError }}
                </v-alert>

                <v-row dense class="mb-4">
                    <v-col cols="12" lg="7">
                        <v-card variant="tonal" color="blue" class="mail-tools-card mail-attachments-card">
                            <v-card-title class="mail-attachments-card__title py-2">
                                <span>Вложения</span>
                                <small v-if="attachmentRows.length">{{ attachmentRows.length }} файлов</small>
                            </v-card-title>

                            <v-card-text class="pt-0">
                                <div v-if="attachmentRows.length" class="mail-attachments-controls">
                                    <v-combobox
                                        v-model="selectedFolderPath"
                                        :items="folderOptions"
                                        item-title="title"
                                        item-value="value"
                                        label="Папка S3 / bucket"
                                        density="compact"
                                        variant="outlined"
                                        hide-details
                                        clearable
                                        :return-object="false"
                                        :loading="foldersLoading"
                                    />

                                    <v-text-field
                                        v-model="newFolderPath"
                                        label="Новая папка или полный path"
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
                                        :disabled="!folderCreatePath()"
                                        @click="createAttachmentFolder"
                                    >
                                        Создать
                                    </v-btn>
                                </div>

                                <div v-if="quickFolderTargets.length" class="mail-attachments-quick-folders">
                                    <span>Quick:</span>
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

                                <div v-if="attachmentRows.length" class="mail-attachments-target">
                                    <span>S3 target:</span>
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

                                <div v-if="attachmentRows.length" class="mail-attachments-workspace">
                                    <div class="mail-attachments-sheet">
                                        <div class="mail-attachments-sheet__row is-head">
                                            <span>#</span>
                                            <span>Файл</span>
                                            <span>Тип</span>
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
                                            @click="selectAttachment(attachment)"
                                            @keydown.enter.prevent="selectAttachment(attachment)"
                                        >
                                            <span class="mail-attachments-sheet__num">{{ index + 1 }}</span>
                                            <span class="mail-attachments-sheet__file">
                                                <v-icon
                                                    :icon="attachmentIcon(attachment)"
                                                    size="13"
                                                />
                                                <span>{{ attachmentName(attachment) }}</span>
                                            </span>
                                            <span>{{ attachmentMime(attachment) }}</span>
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
                                            <span class="mail-attachments-sheet__s3">
                                                <template v-if="isAttachmentSaved(attachment)">
                                                    <span class="mail-attachments-sheet__saved">saved</span>
                                                    <a
                                                        v-if="attachment.url"
                                                        :href="attachment.url"
                                                        target="_blank"
                                                        @click.stop
                                                    >
                                                        file
                                                    </a>
                                                    <a
                                                        v-if="attachment.folder_url"
                                                        :href="attachment.folder_url"
                                                        target="_blank"
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
                                                    @click.stop="saveAttachment(attachment)"
                                                >
                                                    save
                                                </v-btn>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mail-attachment-preview">
                                        <template v-if="selectedAttachment">
                                            <div class="mail-attachment-preview__meta">
                                                <strong>{{ attachmentName(selectedAttachment) }}</strong>
                                                <span>{{ formatSize(selectedAttachment.size) }}</span>
                                            </div>

                                            <img
                                                v-if="selectedImageAttachment && (selectedImageAttachment.preview_url || selectedImageAttachment.url)"
                                                :src="selectedImageAttachment.preview_url || selectedImageAttachment.url"
                                                :alt="attachmentName(selectedImageAttachment)"
                                            >

                                            <iframe
                                                v-else-if="selectedPdfAttachment && pdfPreviewSrc(selectedPdfAttachment)"
                                                class="mail-attachment-preview__pdf"
                                                :src="pdfPreviewSrc(selectedPdfAttachment)"
                                                :title="attachmentName(selectedPdfAttachment)"
                                            ></iframe>

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
                    v-if="currentMessage?.direction === 'incoming'"
                    color="teal"
                    variant="tonal"
                    prepend-icon="mdi-reply"
                    :disabled="loading"
                    @click="emit('reply', currentMessage)"
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
    grid-template-columns: minmax(390px, 1.08fr) minmax(260px, 0.92fr);
    gap: 10px;
    min-height: 260px;
}

.mail-attachments-controls {
    display: grid;
    grid-template-columns: minmax(190px, 1fr) minmax(150px, 0.8fr) auto;
    gap: 6px;
    margin-bottom: 6px;
}

.mail-attachments-quick-folders {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 6px;
    color: #93c5fd;
    font-size: 10px;
}

.mail-attachments-target,
.mail-attachments-saved {
    align-items: center;
    display: flex;
    gap: 6px;
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(147, 197, 253, 0.18);
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.58);
    color: #bfdbfe;
    font-size: 10px;
    margin-bottom: 6px;
    padding: 4px 6px;
}

.mail-attachments-saved {
    border-color: rgba(134, 239, 172, 0.28);
    color: #bbf7d0;
}

.mail-attachments-target code,
.mail-attachments-saved code,
.mail-attachment-preview__storage code {
    min-width: 0;
    overflow: hidden;
    color: #e0f2fe;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-attachments-target a,
.mail-attachments-saved a,
.mail-attachment-preview__storage a {
    color: #86efac;
    font-size: 10px;
    font-weight: 900;
    text-decoration: none;
}

.mail-attachments-target a:hover,
.mail-attachments-saved a:hover,
.mail-attachment-preview__storage a:hover {
    text-decoration: underline;
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
    grid-template-columns: 30px minmax(130px, 1fr) 78px 58px 46px 124px;
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
    font-size: 10px;
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

.mail-attachment-preview__pdf {
    width: 100%;
    height: clamp(260px, 44vh, 460px);
    border: 1px solid rgba(191, 219, 254, 0.24);
    border-radius: 10px;
    background: #f8fafc;
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

.mail-attachment-preview__storage {
    display: grid;
    gap: 4px;
    min-width: 0;
    border-top: 1px solid rgba(147, 197, 253, 0.18);
    color: #bfdbfe;
    font-size: 10px;
    padding-top: 8px;
}

.mail-attachment-preview__storage div {
    display: flex;
    gap: 8px;
}

@media (max-width: 960px) {
    .mail-attachments-workspace {
        grid-template-columns: 1fr;
    }

    .mail-attachments-controls {
        grid-template-columns: 1fr;
    }

    .mail-attachments-sheet__row {
        grid-template-columns: 30px minmax(120px, 1fr) 68px 54px 42px 112px;
    }
}
</style>
