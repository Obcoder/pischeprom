<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'

const dialog = defineModel({
    type: Boolean,
    default: false,
})

const emit = defineEmits([
    'changed',
])

const mailboxes = ref([])
const loading = ref(false)
const saving = ref(false)
const deletingId = ref(null)
const syncingAddress = ref(null)
const formDialog = ref(false)
const editing = ref(null)
const error = ref('')
const formError = ref('')
const notice = ref('')
const syncStatuses = ref({})

const encryptionOptions = [
    { title: 'SSL', value: 'ssl' },
    { title: 'TLS', value: 'tls' },
    { title: 'STARTTLS', value: 'starttls' },
]

const formTitle = computed(() => editing.value ? 'Редактировать ящик' : 'Новый почтовый ящик')

const editableCount = computed(() => mailboxes.value.filter((mailbox) => mailbox.can_edit).length)

const form = ref(defaultForm())

function defaultForm() {
    return {
        address: '',
        name: '',
        from_name: '',
        is_active: true,
        is_default: false,
        imap_host: '',
        imap_port: 993,
        imap_encryption: 'ssl',
        imap_username: '',
        imap_password: '',
        imap_inbox: 'INBOX',
        imap_sent: 'Sent',
        imap_folders_text: 'INBOX\nSent',
        has_imap_password: false,
        clear_imap_password: false,
        smtp_host: '',
        smtp_port: 465,
        smtp_encryption: 'ssl',
        smtp_username: '',
        smtp_password: '',
        smtp_timeout: 30,
        has_smtp_password: false,
        clear_smtp_password: false,
    }
}

function hydrateForm(mailbox) {
    const folders = mailbox.folders?.length
        ? mailbox.folders
        : [mailbox.imap?.inbox, mailbox.imap?.sent].filter(Boolean)

    return {
        address: mailbox.address || '',
        name: mailbox.name || '',
        from_name: mailbox.from_name || '',
        is_active: mailbox.is_active !== false,
        is_default: Boolean(mailbox.is_default),
        imap_host: mailbox.imap?.host || '',
        imap_port: mailbox.imap?.port || 993,
        imap_encryption: mailbox.imap?.encryption || 'ssl',
        imap_username: mailbox.imap?.username || mailbox.address || '',
        imap_password: '',
        imap_inbox: mailbox.imap?.inbox || 'INBOX',
        imap_sent: mailbox.imap?.sent || 'Sent',
        imap_folders_text: folders.join('\n'),
        has_imap_password: Boolean(mailbox.has_imap_password),
        clear_imap_password: false,
        smtp_host: mailbox.smtp?.host || '',
        smtp_port: mailbox.smtp?.port || 465,
        smtp_encryption: mailbox.smtp?.encryption || 'ssl',
        smtp_username: mailbox.smtp?.username || mailbox.address || '',
        smtp_password: '',
        smtp_timeout: mailbox.smtp?.timeout || 30,
        has_smtp_password: Boolean(mailbox.has_smtp_password),
        clear_smtp_password: false,
    }
}

function passwordHint(hasPassword) {
    return hasPassword
        ? 'Пароль сохранён. Заполните поле только для замены.'
        : 'Пароль не сохранён.'
}

function sourceLabel(mailbox) {
    return mailbox.source === 'database' ? 'БД' : '.env'
}

function formatSyncTime(date = new Date()) {
    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date)
}

function syncStatus(mailbox) {
    return syncStatuses.value[mailbox.address] || null
}

function syncStatusText(mailbox) {
    const status = syncStatus(mailbox)

    if (!status) {
        return null
    }

    if (status.state === 'queued') {
        return `В очереди с ${status.time}`
    }

    return `Ошибка запуска ${status.time}`
}

function errorMessage(error) {
    const response = error?.response?.data

    if (response?.errors) {
        return Object.values(response.errors).flat().join('\n')
    }

    return response?.message || error?.message || 'Не удалось выполнить действие.'
}

function foldersFromText(value) {
    return String(value || '')
        .split(/[\r\n,]+/)
        .map((folder) => folder.trim())
        .filter(Boolean)
}

function buildPayload() {
    const payload = {
        address: form.value.address,
        name: form.value.name,
        from_name: form.value.from_name,
        is_active: form.value.is_active,
        is_default: form.value.is_default,
        imap_host: form.value.imap_host,
        imap_port: Number(form.value.imap_port) || 993,
        imap_encryption: form.value.imap_encryption,
        imap_username: form.value.imap_username,
        imap_inbox: form.value.imap_inbox,
        imap_sent: form.value.imap_sent,
        imap_folders: foldersFromText(form.value.imap_folders_text),
        clear_imap_password: form.value.clear_imap_password,
        smtp_host: form.value.smtp_host,
        smtp_port: Number(form.value.smtp_port) || 465,
        smtp_encryption: form.value.smtp_encryption,
        smtp_username: form.value.smtp_username,
        smtp_timeout: Number(form.value.smtp_timeout) || 30,
        clear_smtp_password: form.value.clear_smtp_password,
    }

    if (form.value.imap_password) {
        payload.imap_password = form.value.imap_password
    }

    if (form.value.smtp_password) {
        payload.smtp_password = form.value.smtp_password
    }

    return payload
}

async function fetchMailboxes() {
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get('/api/mailboxes', {
            params: {
                management: 1,
                include_inactive: 1,
            },
        })

        mailboxes.value = data.data ?? []
    } catch (fetchError) {
        error.value = errorMessage(fetchError)
        mailboxes.value = []
    } finally {
        loading.value = false
    }
}

function openCreate() {
    editing.value = null
    form.value = defaultForm()
    formError.value = ''
    formDialog.value = true
}

function openEdit(mailbox) {
    if (!mailbox.can_edit) {
        return
    }

    editing.value = mailbox
    form.value = hydrateForm(mailbox)
    formError.value = ''
    formDialog.value = true
}

async function saveMailbox() {
    saving.value = true
    formError.value = ''
    notice.value = ''

    try {
        if (editing.value?.id) {
            await axios.patch(`/api/mailboxes/${editing.value.id}`, buildPayload())
        } else {
            await axios.post('/api/mailboxes', buildPayload())
        }

        formDialog.value = false
        await fetchMailboxes()
        emit('changed')
        notice.value = 'Настройки ящиков обновлены.'
    } catch (saveError) {
        formError.value = errorMessage(saveError)
    } finally {
        saving.value = false
    }
}

async function deleteMailbox(mailbox) {
    if (!mailbox.can_edit || !mailbox.id) {
        return
    }

    if (!window.confirm(`Удалить почтовый ящик ${mailbox.address}? Письма останутся в истории.`)) {
        return
    }

    deletingId.value = mailbox.id
    error.value = ''
    notice.value = ''

    try {
        await axios.delete(`/api/mailboxes/${mailbox.id}`)
        await fetchMailboxes()
        emit('changed')
        notice.value = 'Почтовый ящик удалён из настроек.'
    } catch (deleteError) {
        error.value = errorMessage(deleteError)
    } finally {
        deletingId.value = null
    }
}

async function syncMailbox(mailbox) {
    syncingAddress.value = mailbox.address
    error.value = ''
    notice.value = ''

    try {
        await axios.post('/api/emails/sync-yandex', {
            mailbox: mailbox.address,
            limit: 1000,
        })

        syncStatuses.value = {
            ...syncStatuses.value,
            [mailbox.address]: {
                state: 'queued',
                time: formatSyncTime(),
            },
        }
        notice.value = `Синхронизация ${mailbox.address} поставлена в очередь.`
    } catch (syncError) {
        syncStatuses.value = {
            ...syncStatuses.value,
            [mailbox.address]: {
                state: 'failed',
                time: formatSyncTime(),
            },
        }
        error.value = errorMessage(syncError)
    } finally {
        syncingAddress.value = null
    }
}

watch(dialog, (isOpen) => {
    if (isOpen) {
        fetchMailboxes()
    }
})
</script>

<template>
    <v-dialog
        v-model="dialog"
        max-width="1180"
        scrollable
    >
        <v-card class="mailboxes-dialog rounded border border-blue-900 bg-slate-950">
            <v-card-title class="d-flex align-center justify-space-between py-3">
                <div>
                    <div class="text-blue-lighten-3 font-ComfortaaVariableFont">
                        Почтовые ящики
                    </div>

                    <div class="text-[11px] text-grey">
                        {{ editableCount }} управляемых из БД, конфиговые ящики доступны только для чтения
                    </div>
                </div>

                <div class="d-flex align-center ga-2">
                    <v-btn
                        color="blue"
                        variant="tonal"
                        size="small"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        Добавить
                    </v-btn>

                    <v-btn
                        icon="mdi-close"
                        size="small"
                        variant="text"
                        @click="dialog = false"
                    />
                </div>
            </v-card-title>

            <v-divider />

            <v-card-text class="mailboxes-dialog__body">
                <v-alert
                    v-if="error"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mb-3 whitespace-pre-line"
                >
                    {{ error }}
                </v-alert>

                <v-alert
                    v-if="notice"
                    type="success"
                    variant="tonal"
                    density="compact"
                    class="mb-3"
                >
                    {{ notice }}
                </v-alert>

                <v-progress-linear
                    v-if="loading"
                    indeterminate
                    color="blue"
                    class="mb-3"
                />

                <div
                    v-if="!loading && !mailboxes.length"
                    class="mailboxes-empty"
                >
                    <v-icon icon="mdi-email-outline" size="36" />
                    <div>Почтовые ящики не найдены.</div>
                    <v-btn
                        color="blue"
                        variant="tonal"
                        size="small"
                        prepend-icon="mdi-plus"
                        @click="openCreate"
                    >
                        Создать первый ящик
                    </v-btn>
                </div>

                <v-row dense>
                    <v-col
                        v-for="mailbox in mailboxes"
                        :key="`${mailbox.source}-${mailbox.address}`"
                        cols="12"
                        md="6"
                        xl="4"
                    >
                        <v-card class="mailbox-card h-100" variant="tonal">
                            <div class="d-flex align-start justify-space-between ga-3">
                                <div class="min-w-0">
                                    <div class="mailbox-card__name text-truncate">
                                        {{ mailbox.name || mailbox.address }}
                                    </div>

                                    <div class="mailbox-card__address text-truncate">
                                        {{ mailbox.address }}
                                    </div>
                                </div>

                                <div class="d-flex align-center ga-1 flex-wrap justify-end">
                                    <v-chip
                                        size="x-small"
                                        :color="mailbox.source === 'database' ? 'cyan' : 'grey'"
                                        variant="tonal"
                                    >
                                        {{ sourceLabel(mailbox) }}
                                    </v-chip>

                                    <v-chip
                                        v-if="mailbox.is_default"
                                        size="x-small"
                                        color="blue"
                                        variant="tonal"
                                    >
                                        default
                                    </v-chip>

                                    <v-chip
                                        v-if="mailbox.is_active === false"
                                        size="x-small"
                                        color="orange"
                                        variant="tonal"
                                    >
                                        off
                                    </v-chip>
                                </div>
                            </div>

                            <div class="mailbox-card__grid mt-4">
                                <div>
                                    <div class="mailbox-card__label">IMAP</div>
                                    <div class="mailbox-card__value text-truncate">
                                        {{ mailbox.imap?.host || 'не задан' }}:{{ mailbox.imap?.port || 993 }}
                                    </div>
                                    <div class="mailbox-card__muted text-truncate">
                                        {{ mailbox.imap?.username || mailbox.address }}
                                    </div>
                                </div>

                                <div>
                                    <div class="mailbox-card__label">SMTP</div>
                                    <div class="mailbox-card__value text-truncate">
                                        {{ mailbox.smtp?.host || 'не задан' }}:{{ mailbox.smtp?.port || 465 }}
                                    </div>
                                    <div class="mailbox-card__muted text-truncate">
                                        {{ mailbox.smtp?.username || mailbox.address }}
                                    </div>
                                </div>
                            </div>

                            <div class="mailbox-card__folders mt-3">
                                <v-chip
                                    v-for="folder in mailbox.folders"
                                    :key="`${mailbox.address}-${folder}`"
                                    size="x-small"
                                    variant="outlined"
                                    color="blue-grey-lighten-2"
                                >
                                    {{ folder }}
                                </v-chip>
                            </div>

                            <div
                                v-if="syncStatus(mailbox)"
                                class="mailbox-card__sync-status mt-3"
                                :class="`mailbox-card__sync-status--${syncStatus(mailbox).state}`"
                            >
                                <v-icon
                                    :icon="syncStatus(mailbox).state === 'queued' ? 'mdi-timer-sand' : 'mdi-alert-circle-outline'"
                                    size="13"
                                />
                                <span>{{ syncStatusText(mailbox) }}</span>
                            </div>

                            <div class="d-flex align-center justify-space-between mt-4">
                                <v-btn
                                    size="small"
                                    color="blue"
                                    variant="text"
                                    prepend-icon="mdi-sync"
                                    :loading="syncingAddress === mailbox.address"
                                    @click="syncMailbox(mailbox)"
                                >
                                    Синхр.
                                </v-btn>

                                <div class="d-flex align-center ga-1">
                                    <v-btn
                                        v-if="mailbox.can_edit"
                                        icon="mdi-pencil-outline"
                                        size="small"
                                        variant="text"
                                        @click="openEdit(mailbox)"
                                    />

                                    <v-btn
                                        v-if="mailbox.can_edit"
                                        icon="mdi-delete-outline"
                                        size="small"
                                        color="red"
                                        variant="text"
                                        :loading="deletingId === mailbox.id"
                                        @click="deleteMailbox(mailbox)"
                                    />

                                    <v-tooltip v-if="!mailbox.can_edit" text="Ящик задан в .env/config и редактируется на сервере">
                                        <template #activator="{ props }">
                                            <v-icon
                                                v-bind="props"
                                                icon="mdi-lock-outline"
                                                size="18"
                                                color="grey"
                                            />
                                        </template>
                                    </v-tooltip>
                                </div>
                            </div>
                        </v-card>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog
        v-model="formDialog"
        max-width="980"
        scrollable
    >
        <v-card class="rounded border border-blue-900 bg-slate-950">
            <v-card-title class="d-flex align-center justify-space-between py-3">
                <div class="text-blue-lighten-3 font-ComfortaaVariableFont">
                    {{ formTitle }}
                </div>

                <v-btn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="formDialog = false"
                />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-alert
                    v-if="formError"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mb-3 whitespace-pre-line"
                >
                    {{ formError }}
                </v-alert>

                <v-row dense>
                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.address"
                            label="Email ящика"
                            type="email"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.name"
                            label="Название"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.from_name"
                            label="Имя отправителя"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-switch
                            v-model="form.is_active"
                            label="Активен для отправки и синхронизации"
                            density="compact"
                            color="blue"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-switch
                            v-model="form.is_default"
                            label="Ящик по умолчанию"
                            density="compact"
                            color="blue"
                            hide-details
                        />
                    </v-col>
                </v-row>

                <v-divider class="my-4" />

                <div class="mailbox-form-section">IMAP: получение и синхронизация</div>

                <v-row dense>
                    <v-col cols="12" md="5">
                        <v-text-field
                            v-model="form.imap_host"
                            label="IMAP host"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6" md="2">
                        <v-text-field
                            v-model="form.imap_port"
                            label="Порт"
                            type="number"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6" md="2">
                        <v-select
                            v-model="form.imap_encryption"
                            :items="encryptionOptions"
                            label="Шифрование"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="3">
                        <v-text-field
                            v-model="form.imap_username"
                            label="IMAP login"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.imap_password"
                            label="IMAP пароль"
                            type="password"
                            :hint="passwordHint(form.has_imap_password)"
                            persistent-hint
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>

                    <v-col cols="12" md="2">
                        <v-checkbox
                            v-model="form.clear_imap_password"
                            label="Очистить пароль"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6" md="3">
                        <v-text-field
                            v-model="form.imap_inbox"
                            label="Папка входящих"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6" md="3">
                        <v-text-field
                            v-model="form.imap_sent"
                            label="Папка исходящих"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-textarea
                            v-model="form.imap_folders_text"
                            label="Папки для синхронизации"
                            rows="3"
                            auto-grow
                            hint="Каждая папка с новой строки или через запятую"
                            persistent-hint
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>
                </v-row>

                <v-divider class="my-4" />

                <div class="mailbox-form-section">SMTP: отправка</div>

                <v-row dense>
                    <v-col cols="12" md="5">
                        <v-text-field
                            v-model="form.smtp_host"
                            label="SMTP host"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6" md="2">
                        <v-text-field
                            v-model="form.smtp_port"
                            label="Порт"
                            type="number"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="6" md="2">
                        <v-select
                            v-model="form.smtp_encryption"
                            :items="encryptionOptions"
                            label="Шифрование"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="3">
                        <v-text-field
                            v-model="form.smtp_username"
                            label="SMTP login"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-text-field
                            v-model="form.smtp_password"
                            label="SMTP пароль"
                            type="password"
                            :hint="passwordHint(form.has_smtp_password)"
                            persistent-hint
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>

                    <v-col cols="12" md="2">
                        <v-checkbox
                            v-model="form.clear_smtp_password"
                            label="Очистить пароль"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" md="2">
                        <v-text-field
                            v-model="form.smtp_timeout"
                            label="Timeout"
                            type="number"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>
                </v-row>
            </v-card-text>

            <v-divider />

            <v-card-actions class="justify-end">
                <v-btn
                    variant="text"
                    @click="formDialog = false"
                >
                    Отмена
                </v-btn>

                <v-btn
                    color="blue"
                    variant="tonal"
                    :loading="saving"
                    @click="saveMailbox"
                >
                    Сохранить
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.mailboxes-dialog__body {
    min-height: 520px;
}

.mailboxes-empty {
    min-height: 260px;
    display: grid;
    place-items: center;
    gap: 10px;
    color: rgb(var(--v-theme-grey));
    border: 1px dashed rgba(148, 163, 184, 0.35);
    border-radius: 14px;
}

.mailbox-card {
    padding: 16px;
    background:
        linear-gradient(135deg, rgba(14, 165, 233, 0.14), rgba(15, 23, 42, 0.4)),
        rgba(15, 23, 42, 0.82);
    border: 1px solid rgba(96, 165, 250, 0.22);
}

.mailbox-card__name {
    font-size: 15px;
    font-weight: 700;
    color: #dbeafe;
}

.mailbox-card__address,
.mailbox-card__muted {
    font-size: 11px;
    color: rgba(203, 213, 225, 0.72);
}

.mailbox-card__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 10px;
}

.mailbox-card__label {
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(147, 197, 253, 0.82);
}

.mailbox-card__value {
    font-size: 12px;
    color: #e2e8f0;
}

.mailbox-card__folders {
    min-height: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.mailbox-card__sync-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.02em;
}

.mailbox-card__sync-status--queued {
    color: #93c5fd;
    background: rgba(59, 130, 246, 0.16);
    border: 1px solid rgba(96, 165, 250, 0.28);
}

.mailbox-card__sync-status--failed {
    color: #fca5a5;
    background: rgba(239, 68, 68, 0.16);
    border: 1px solid rgba(248, 113, 113, 0.28);
}

.mailbox-form-section {
    margin-bottom: 10px;
    font-size: 12px;
    font-weight: 700;
    color: #bfdbfe;
    letter-spacing: 0.02em;
}
</style>
