<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import MailTemplatesDialog from './MailTemplatesDialog.vue'
import MailOfferPanel from './MailOfferPanel.vue'
import { emptyMailOffer, mailOfferPayload } from './mailOfferPayload.js'

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
    mailboxes: {
        type: Array,
        default: () => [],
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
const quotedBody = ref('')
const includeQuote = ref(true)
const quoteExpanded = ref(false)
const offer = ref(emptyMailOffer())
const activeTool = ref(null)
const editorMode = ref('edit')
const previewHtml = ref('')
const previewLoading = ref(false)
const previewError = ref('')
let previewTimer = null
let previewRequest = 0
let previewController = null
let openRequest = 0
const tools = [
    { key: 'products', icon: 'mdi-package-variant-closed', title: 'Товары' },
    { key: 'logistics', icon: 'mdi-truck-delivery-outline', title: 'Доставка' },
    { key: 'templates', icon: 'mdi-file-document-edit-outline', title: 'Шаблоны' },
    { key: 'attachments', icon: 'mdi-paperclip', title: 'Вложения' },
]
const structuredOffer = computed(() => mailOfferPayload(offer.value))
const contentPayload = computed(() => ({
    body: body.value,
    quoted_body: includeQuote.value ? quotedBody.value : '',
    ...(structuredOffer.value ? { offer: structuredOffer.value } : {}),
}))
const contentSignature = computed(() => JSON.stringify(contentPayload.value))
const hasContent = computed(() => Boolean(body.value.trim() || structuredOffer.value))
const attachmentCount = computed(() => localFiles.value.length + storageFiles.value.length)

function toggleTool(key) {
    activeTool.value = activeTool.value === key ? null : key
}

function cancelPreview() {
    clearTimeout(previewTimer)
    previewRequest++
    previewController?.abort()
    previewLoading.value = false
}

async function fetchPreview() {
    cancelPreview()
    const requestId = previewRequest
    previewController = new AbortController()
    previewLoading.value = true
    previewError.value = ''
    previewHtml.value = ''
    try {
        const { data } = await axios.post('/api/mail-offers/preview', contentPayload.value, { signal: previewController.signal })
        if (requestId === previewRequest && model.value) previewHtml.value = data.html
    } catch (requestError) {
        if (requestId === previewRequest && !axios.isCancel(requestError)) {
            const errors = Object.values(requestError?.response?.data?.errors || {}).flat()
            previewError.value = errors[0] || requestError?.response?.data?.message || 'Не удалось подготовить предпросмотр.'
        }
    } finally {
        if (requestId === previewRequest) previewLoading.value = false
    }
}

function productPrice(item) {
    const value = item.price_override === '' || item.price_override == null ? item.good?.price : item.price_override
    if (value == null) return 'Цена по запросу'
    return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(value)} ${item.good?.currency_code || 'RUB'} / ${item.good?.price_unit_label || 'упаковка'}`
}

const selectedMailbox = ref(null)
const availableMailboxes = ref([])
const localFiles = ref([])
const storageFiles = ref([])
const templates = ref([])
const selectedTemplateId = ref(null)
const templatesDialog = ref(false)
const loadingTemplates = ref(false)
const templateLoadError = ref(null)
const sending = ref(false)
const error = ref(null)
const fileInput = ref(null)
const idempotencyKey = ref(crypto.randomUUID())

const isReply = computed(() => Boolean(props.replyContext?.id))

const normalizedTo = computed(() => selectedAddresses(to.value))
const normalizedCc = computed(() => selectedAddresses(cc.value))
const normalizedStorageFiles = computed(() => selectedStoragePaths(storageFiles.value))
const mailboxItems = computed(() => {
    const source = props.mailboxes.length ? props.mailboxes : availableMailboxes.value

    return source.map((mailbox) => ({
        title: mailbox.label || mailbox.address,
        value: mailbox.address,
    }))
})

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

async function fetchTemplates() {
    loadingTemplates.value = true
    templateLoadError.value = null

    try {
        const { data } = await axios.get('/api/mail-templates')
        templates.value = (data ?? []).filter((template) => template.is_active !== false)
    } catch (requestError) {
        templates.value = []
        templateLoadError.value = requestError?.response?.data?.message || 'Не удалось загрузить шаблоны.'
    } finally {
        loadingTemplates.value = false
    }
}

async function fetchMailboxes() {
    if (props.mailboxes.length) {
        return
    }

    try {
        const { data } = await axios.get('/api/mailboxes')
        availableMailboxes.value = data.data ?? data ?? []
    } catch (error) {
        console.error('Mailboxes loading error:', error)
        availableMailboxes.value = []
    }
}

function applyTemplate(templateId) {
    const template = templates.value.find((item) => item.id === templateId)

    if (!template) return

    subject.value = template.subject || subject.value
    body.value = template.body || body.value
}

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
    quotedBody.value = ''
    includeQuote.value = true
    quoteExpanded.value = false
    offer.value = emptyMailOffer()
    activeTool.value = null
    editorMode.value = 'edit'
    cancelPreview()
    previewHtml.value = ''
    previewError.value = ''
    selectedMailbox.value = null
    localFiles.value = []
    storageFiles.value = [...props.initialStorageFiles]
    selectedTemplateId.value = null
    templatesDialog.value = false
    templateLoadError.value = null
    error.value = null
    idempotencyKey.value = crypto.randomUUID()

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

function applyInitialState() {
    storageFiles.value = [...props.initialStorageFiles]
    selectedMailbox.value = props.replyContext?.mailbox
        || mailboxItems.value[0]?.value
        || null

    if (props.replyContext) {
        to.value = props.replyContext.from_address ? [props.replyContext.from_address] : []
        cc.value = []
        subject.value = replySubject(props.replyContext)
        quotedBody.value = replyQuote(props.replyContext).trim()

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
    if (sending.value || !hasContent.value) return
    sending.value = true
    error.value = null

    const formData = new FormData()

    formData.append('idempotency_key', idempotencyKey.value)

    normalizedTo.value.forEach((address) => formData.append('to[]', address))
    normalizedCc.value.forEach((address) => formData.append('cc[]', address))
    normalizedStorageFiles.value.forEach((path) => formData.append('storage_files[]', path))
    localFiles.value.forEach((file) => formData.append('attachments[]', file))

    formData.append('subject', subject.value)
    formData.append('body', body.value)
    formData.append('quoted_body', includeQuote.value ? quotedBody.value : '')
    if (structuredOffer.value) formData.append('offer', JSON.stringify(structuredOffer.value))

    if (selectedMailbox.value) {
        formData.append('mailbox', selectedMailbox.value)
    }

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
        const errors = Object.values(err?.response?.data?.errors || {}).flat()
        error.value = errors[0] || err?.response?.data?.message || 'Не удалось отправить письмо.'
    } finally {
        sending.value = false
    }
}

watch(model, async (value) => {
    const requestId = ++openRequest
    if (value) {
        resetForm()
        applyInitialState()
        await Promise.all([fetchMailboxes(), fetchTemplates()])
        if (requestId === openRequest && model.value && !selectedMailbox.value) {
            selectedMailbox.value = mailboxItems.value[0]?.value || null
        }
    } else {
        resetForm()
    }
})

watch(selectedTemplateId, applyTemplate)
watch(() => props.replyContext?.id, () => {
    if (model.value) applyInitialState()
})
watch([editorMode, contentSignature], () => {
    cancelPreview()
    previewHtml.value = ''
    previewError.value = ''
    if (editorMode.value === 'preview' && model.value) {
        previewLoading.value = true
        previewTimer = setTimeout(fetchPreview, 300)
    }
})
onBeforeUnmount(() => {
    openRequest++
    cancelPreview()
})
</script>

<template>
    <v-dialog v-model="model" max-width="1420" height="min(920px, calc(100dvh - 48px))" :persistent="sending" class="mail-composer-dialog">
        <v-card class="mail-composer" theme="light" rounded="xl">
            <header class="mail-composer__header">
                <div>
                    <div class="mail-composer__eyebrow">ПИЩЕПРОМ-СЕРВЕР · ПОЧТА</div>
                    <h2 class="mail-composer__title">{{ isReply ? 'Ответить на письмо' : 'Новое письмо' }}</h2>
                </div>
                <div class="mail-composer__view" role="group" aria-label="Режим редактора">
                    <v-btn size="small" :variant="editorMode === 'edit' ? 'flat' : 'text'" :color="editorMode === 'edit' ? '#24364b' : undefined" :aria-pressed="editorMode === 'edit'" prepend-icon="mdi-pencil-outline" @click="editorMode = 'edit'">Редактор</v-btn>
                    <v-btn size="small" :variant="editorMode === 'preview' ? 'flat' : 'text'" :color="editorMode === 'preview' ? '#24364b' : undefined" :aria-pressed="editorMode === 'preview'" prepend-icon="mdi-eye-outline" @click="editorMode = 'preview'">Предпросмотр</v-btn>
                </div>
                <v-btn icon="mdi-close" size="32" variant="text" :disabled="sending" aria-label="Закрыть редактор письма" @click="model = false" />
            </header>

            <div class="mail-composer__address-bar">
                <v-select v-model="selectedMailbox" :items="mailboxItems" label="От ящика" variant="outlined" density="compact" hide-details :disabled="sending" />
                <v-combobox v-model="to" :items="recipientItems" label="Кому" item-title="title" item-value="value" variant="outlined" density="compact" multiple chips closable-chips hide-details :disabled="sending" />
                <v-combobox v-model="cc" :items="recipientItems" label="Копия (CC)" item-title="title" item-value="value" variant="outlined" density="compact" multiple chips closable-chips hide-details :disabled="sending" />
                <v-text-field v-model="subject" label="Тема" variant="outlined" density="compact" hide-details :disabled="sending" class="mail-composer__subject" />
            </div>
            <div v-if="error" class="mail-composer__error" role="alert">{{ error }}</div>

            <div class="mail-composer__workspace">
                <main class="mail-composer__main">
                    <div v-if="editorMode === 'edit'" class="mail-composer__editor">
                        <v-textarea v-model="body" label="Текст письма" placeholder="Добрый день! Подготовили для вас предложение…" variant="outlined" rows="7" hide-details :disabled="sending" class="mail-composer__body" />

                        <section v-if="offer.items.length" class="mail-composer__offer-summary">
                            <div class="mail-composer__section-title">
                                <span>Товарное предложение <small>{{ offer.items.length }}</small></span>
                                <v-btn size="x-small" variant="text" prepend-icon="mdi-tune-variant" :disabled="sending" @click="activeTool = 'products'">Настроить</v-btn>
                            </div>
                            <div v-for="item in offer.items" :key="item.good_id" class="mail-composer__product">
                                <img v-if="item.include_image && item.good?.image_url" :src="item.good.image_url" :alt="item.good.name" class="mail-composer__product-image">
                                <div v-else class="mail-composer__product-placeholder"><v-icon icon="mdi-package-variant-closed" size="22" /></div>
                                <div class="mail-composer__product-info">
                                    <strong>{{ item.good?.name || `Товар #${item.good_id}` }}</strong>
                                    <span>{{ [item.include_description && 'Описание', item.include_specifications && 'Характеристики', 'Ссылка на товар'].filter(Boolean).join(' · ') }}</span>
                                </div>
                                <div class="mail-composer__product-price">
                                    <strong>{{ productPrice(item) }}</strong>
                                    <span v-if="item.quantity">{{ item.quantity }} {{ item.good?.price_unit_label }}</span>
                                </div>
                            </div>
                        </section>

                        <section v-if="offer.logistics" class="mail-composer__delivery-summary">
                            <div class="mail-composer__section-title">
                                <span><v-icon icon="mdi-truck-delivery-outline" size="17" /> Доставка</span>
                                <v-btn size="x-small" variant="text" :disabled="sending" @click="activeTool = 'logistics'">Изменить</v-btn>
                            </div>
                            <div class="mail-composer__route">
                                <span>{{ offer.logistics.origin || 'Откуда' }}</span>
                                <span class="mail-composer__route-line" aria-hidden="true" />
                                <v-icon icon="mdi-arrow-right" size="18" />
                                <span>{{ offer.logistics.destination || 'Куда' }}</span>
                            </div>
                            <span class="mail-composer__delivery-count">Вариантов доставки: {{ offer.logistics.options?.length || 0 }}</span>
                        </section>

                        <div v-if="!offer.items.length && !offer.logistics" class="mail-composer__offer-hint">
                            <div><strong>Предложение, которое удобно изучить</strong><span>Добавьте товары из каталога и варианты доставки прямо в письмо.</span></div>
                            <v-btn size="small" variant="tonal" color="#800000" prepend-icon="mdi-plus" :disabled="sending" @click="activeTool = 'products'">Товар</v-btn>
                        </div>

                        <div v-if="quotedBody" class="mail-composer__quote">
                            <div class="mail-composer__quote-heading">
                                <v-checkbox v-model="includeQuote" label="Цитата исходного письма" density="compact" hide-details :disabled="sending" />
                                <v-btn size="small" variant="text" :append-icon="quoteExpanded ? 'mdi-chevron-up' : 'mdi-chevron-down'" @click="quoteExpanded = !quoteExpanded">{{ quoteExpanded ? 'Свернуть' : 'Показать' }}</v-btn>
                            </div>
                            <v-textarea v-if="quoteExpanded" v-model="quotedBody" label="Исходное сообщение" rows="5" hide-details variant="outlined" :disabled="sending || !includeQuote" />
                        </div>
                    </div>

                    <div v-else class="mail-composer__preview">
                        <div class="mail-composer__preview-caption"><v-icon icon="mdi-email-outline" size="16" /> Так письмо увидит получатель</div>
                        <v-progress-linear v-if="previewLoading" indeterminate color="#800000" height="2" />
                        <div v-if="previewError" class="mail-composer__preview-error" role="alert">
                            <v-icon icon="mdi-alert-circle-outline" size="26" />
                            <p>{{ previewError }}</p>
                            <v-btn size="small" variant="tonal" @click="fetchPreview">Повторить</v-btn>
                        </div>
                        <iframe v-else-if="previewHtml" :srcdoc="previewHtml" title="Предпросмотр письма" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" class="mail-composer__preview-frame" />
                        <div v-else class="mail-composer__preview-empty">{{ previewLoading ? 'Готовим письмо…' : 'Добавьте текст, товар или варианты доставки для предпросмотра.' }}</div>
                    </div>
                </main>

                <aside v-if="activeTool" class="mail-composer__panel">
                    <MailOfferPanel v-if="activeTool === 'products' || activeTool === 'logistics'" v-model:offer="offer" :section="activeTool" :disabled="sending" @close="activeTool = null" />
                    <div v-else class="mail-composer__secondary-panel">
                        <div class="mail-composer__section-title">
                            <strong>{{ activeTool === 'templates' ? 'Шаблоны писем' : 'Вложения' }}</strong>
                            <v-btn icon="mdi-close" size="28" variant="text" aria-label="Закрыть панель" @click="activeTool = null" />
                        </div>
                        <template v-if="activeTool === 'templates'">
                            <p class="mail-composer__panel-help">Выберите основу для текста. Товары и доставка сохранятся.</p>
                            <div v-if="templateLoadError" class="mail-composer__error">{{ templateLoadError }}</div>
                            <v-select v-model="selectedTemplateId" :items="templates" item-title="name" item-value="id" label="Шаблон" variant="outlined" density="compact" clearable :loading="loadingTemplates" :disabled="sending" no-data-text="Активных шаблонов нет" hide-details />
                            <v-btn block variant="tonal" prepend-icon="mdi-file-document-edit-outline" :disabled="sending" class="mt-3" @click="templatesDialog = true">Управлять шаблонами</v-btn>
                        </template>
                        <template v-else>
                            <p class="mail-composer__panel-help">Документы, сертификаты и другие файлы к предложению.</p>
                            <input ref="fileInput" type="file" multiple class="d-none" :disabled="sending" @change="onLocalFilesSelected">
                            <v-btn block color="#24364b" variant="tonal" prepend-icon="mdi-paperclip" :disabled="sending" @click="fileInput?.click()">Прикрепить файлы</v-btn>
                            <div v-if="localFiles.length" class="mail-composer__files mt-3">
                                <v-chip v-for="(file, index) in localFiles" :key="`${file.name}-${index}`" size="small" variant="tonal" :closable="!sending" @click:close="removeLocalFile(index)">{{ file.name }}</v-chip>
                            </div>
                            <v-combobox v-model="storageFiles" :items="storageFileItems" label="Файлы из хранилища" item-title="title" item-value="value" variant="outlined" density="compact" multiple chips closable-chips :disabled="sending" hint="Путь к файлу: mail/attachments/123/file.pdf" persistent-hint class="mt-5" />
                        </template>
                    </div>
                </aside>

                <nav class="mail-composer__tools" aria-label="Инструменты письма">
                    <button v-for="tool in tools" :key="tool.key" type="button" :class="{ 'is-active': activeTool === tool.key }" :disabled="sending" :title="tool.title" :aria-label="tool.title" :aria-pressed="activeTool === tool.key" @click="toggleTool(tool.key)">
                        <v-icon :icon="tool.icon" size="23" /><span>{{ tool.title }}</span>
                        <small v-if="tool.key === 'products' && offer.items.length">{{ offer.items.length }}</small>
                        <small v-if="tool.key === 'attachments' && attachmentCount">{{ attachmentCount }}</small>
                    </button>
                    <div class="mail-composer__tools-divider" />
                    <button type="button" :class="{ 'is-active': editorMode === 'preview' }" title="Предпросмотр" aria-label="Предпросмотр письма" :aria-pressed="editorMode === 'preview'" @click="editorMode = editorMode === 'preview' ? 'edit' : 'preview'">
                        <v-icon icon="mdi-eye-outline" size="23" /><span>Просмотр</span>
                    </button>
                </nav>
            </div>

            <footer class="mail-composer__footer">
                <div class="mail-composer__footer-info">
                    <span v-if="isReply"><v-icon icon="mdi-reply" size="15" /> Ответ в той же почтовой ветке</span>
                    <span v-else>{{ offer.items.length ? `Товаров в предложении: ${offer.items.length}` : 'Новое сообщение' }}</span>
                    <span v-if="attachmentCount"><v-icon icon="mdi-paperclip" size="14" /> {{ attachmentCount }}</span>
                </div>
                <v-btn variant="text" :disabled="sending" @click="model = false">Отмена</v-btn>
                <v-btn class="mail-composer__send" variant="flat" prepend-icon="mdi-send-outline" :loading="sending" :disabled="!selectedMailbox || !normalizedTo.length || !subject.trim() || !hasContent" @click="submit">{{ isReply ? 'Отправить ответ' : 'Отправить' }}</v-btn>
            </footer>
        </v-card>
    </v-dialog>
    <MailTemplatesDialog v-model="templatesDialog" @changed="fetchTemplates" />
</template>

<style scoped>
.mail-composer {
    height: 100%;
    max-height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #dbe2ea;
    border-radius: 16px;
    background: #fff;
    color: #25354a;
}
.mail-composer__header { display: flex; align-items: center; flex-shrink: 0; gap: 16px; padding: 14px 20px; border-bottom: 1px solid #e7ecf2; }
.mail-composer__eyebrow { color: #8d3d3d; font-size: 9px; font-weight: 700; letter-spacing: .1em; }
.mail-composer__title { color: #233249; font-size: 20px; font-weight: 700; margin-top: 2px; }
.mail-composer__view { display: flex; gap: 2px; padding: 3px; margin-left: auto; background: #f0f3f7; border-radius: 8px; }
.mail-composer__view :deep(.v-btn) { letter-spacing: 0; text-transform: none; }
.mail-composer__address-bar { display: grid; grid-template-columns: minmax(180px, .9fr) minmax(220px, 1.5fr) minmax(160px, 1fr); gap: 10px; padding: 16px 20px; border-bottom: 1px solid #e7ecf2; flex-shrink: 0; }
.mail-composer__subject { grid-column: 1 / -1; }
.mail-composer__workspace { display: flex; flex: 1 1 0; min-height: 0; overflow: hidden; }
.mail-composer__main { flex: 1 1 0; min-width: 0; display: flex; overflow: hidden; }
.mail-composer__editor { flex: 1; min-width: 0; overflow-y: auto; padding: 18px 20px; scrollbar-width: thin; }
.mail-composer__body :deep(textarea) { font-size: 14px; line-height: 1.6; }
.mail-composer__offer-summary { border: 1px solid #dfe6ed; border-radius: 12px; margin-top: 16px; overflow: hidden; }
.mail-composer__section-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; font-weight: 700; }
.mail-composer__section-title > span { display: flex; align-items: center; gap: 7px; }
.mail-composer__section-title small { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding-inline: 5px; background: #e6edf5; border-radius: 10px; color: #475e7a; }
.mail-composer__offer-summary > .mail-composer__section-title { background: #f4f7fa; padding: 8px 12px; }
.mail-composer__product { display: flex; align-items: center; gap: 12px; padding: 12px; border-top: 1px solid #e7ecf2; }
.mail-composer__product-image, .mail-composer__product-placeholder { width: 52px; height: 52px; object-fit: contain; border-radius: 8px; background: #f4f6f8; flex-shrink: 0; }
.mail-composer__product-placeholder { display: grid; place-items: center; color: #8b9aac; }
.mail-composer__product-info { display: flex; flex-direction: column; gap: 5px; min-width: 0; flex: 1; }
.mail-composer__product-info strong { font-size: 13px; font-weight: 600; }
.mail-composer__product-info span, .mail-composer__product-price span { font-size: 10px; color: #748196; }
.mail-composer__product-price { display: flex; flex-direction: column; gap: 4px; text-align: right; font-size: 12px; }
.mail-composer__delivery-summary { margin-top: 12px; padding: 10px 14px; background: #f1f6fa; border: 1px solid #dce5ee; border-radius: 12px; }
.mail-composer__route { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 13px; font-weight: 600; }
.mail-composer__route-line { flex: 1; border-top: 2px dashed #aec3d5; }
.mail-composer__delivery-count { display: block; color: #77879a; font-size: 10px; margin-top: 6px; }
.mail-composer__offer-hint { display: flex; align-items: center; gap: 16px; padding: 16px; margin-top: 16px; background: #f6f8fb; border: 1px dashed #d9e2ec; border-radius: 12px; }
.mail-composer__offer-hint > div { display: flex; flex-direction: column; gap: 5px; flex: 1; }
.mail-composer__offer-hint strong { font-size: 12px; font-weight: 600; }
.mail-composer__offer-hint span, .mail-composer__panel-help { font-size: 11px; color: #78879b; line-height: 1.5; }
.mail-composer__quote { margin-top: 16px; border-top: 1px solid #e6ebf0; }
.mail-composer__quote-heading { display: flex; align-items: center; justify-content: space-between; }
.mail-composer__quote-heading :deep(.v-label) { font-size: 11px; }
.mail-composer__quote :deep(textarea) { font-size: 11px; color: #7b889a !important; }
.mail-composer__preview { display: flex; flex-direction: column; min-height: 0; flex: 1; background: #edf1f5; }
.mail-composer__preview-caption { display: flex; justify-content: center; gap: 6px; align-items: center; color: #748196; font-size: 11px; padding: 8px; }
.mail-composer__preview-frame { width: 100%; border: 0; flex: 1; min-height: 0; background: #fff; }
.mail-composer__preview-empty, .mail-composer__preview-error { display: flex; flex: 1; flex-direction: column; gap: 12px; align-items: center; justify-content: center; padding: 24px; color: #7c8999; font-size: 13px; text-align: center; }
.mail-composer__preview-error { color: #a14242; }
.mail-composer__panel { width: 350px; max-width: 40%; flex: 0 0 auto; border-left: 1px solid #e2e8ef; background: #f8fafc; overflow-y: auto; scrollbar-width: thin; }
.mail-composer__secondary-panel { padding: 16px; }
.mail-composer__panel-help { margin: 12px 0 18px; }
.mail-composer__tools { width: 66px; display: flex; flex-shrink: 0; flex-direction: column; align-items: center; gap: 8px; padding: 12px 5px; border-left: 1px solid #e2e8ef; background: #f2f5f9; overflow-y: auto; scrollbar-width: thin; }
.mail-composer__tools > button { display: flex; position: relative; flex-direction: column; align-items: center; justify-content: center; gap: 5px; min-height: 58px; width: 54px; border-radius: 10px; color: #65758a; transition: background .15s, color .15s; }
.mail-composer__tools > button span { font-size: 9px; }
.mail-composer__tools > button:hover, .mail-composer__tools > button:focus-visible { background: #e4eaf2; color: #24364b; outline: 2px solid #c4d0de; outline-offset: -2px; }
.mail-composer__tools > button.is-active { background: #fff; color: #8a2323; box-shadow: 0 2px 8px #22344b12; }
.mail-composer__tools > button:disabled { opacity: .45; cursor: default; }
.mail-composer__tools small { position: absolute; top: 4px; right: 4px; background: #8a2323; color: white; min-width: 15px; padding: 1px 3px; border-radius: 8px; font-size: 9px; }
.mail-composer__tools-divider { width: 28px; border-top: 1px solid #d6dfe9; margin: 3px 0; }
.mail-composer__footer { display: flex; align-items: center; flex-shrink: 0; gap: 8px; padding: 12px 20px; border-top: 1px solid #e4eaf1; background: #fff; }
.mail-composer__footer-info { display: flex; align-items: center; gap: 14px; font-size: 10px; color: #7b899a; flex: 1; }
.mail-composer__footer-info span { display: inline-flex; align-items: center; gap: 4px; }
.mail-composer__send { background: #852525 !important; color: #fff !important; font-weight: 700; }
.mail-composer__footer :deep(.v-btn), .mail-composer__section-title :deep(.v-btn) { text-transform: none; letter-spacing: 0; }
.mail-composer__files { display: flex; flex-wrap: wrap; gap: 6px; }
.mail-composer__error { background: #fff0f0; color: #9b3030; padding: 8px 20px; font-size: 12px; flex-shrink: 0; }
.mail-composer :deep(.v-field) { font-size: 12px; background: #fff; }
.mail-composer :deep(.v-label), .mail-composer :deep(.v-field__input), .mail-composer :deep(.v-select__selection-text) { color: #2d4057; }
@media (max-width: 850px) {
    .mail-composer__header { padding: 12px; gap: 8px; }
    .mail-composer__eyebrow { font-size: 8px; }
    .mail-composer__title { font-size: 16px; }
    .mail-composer__view :deep(.v-btn) { min-width: 0; padding: 0 8px; font-size: 10px; }
    .mail-composer__view :deep(.v-btn__prepend) { display: none; }
    .mail-composer__address-bar { grid-template-columns: 1fr 1fr; padding: 12px; }
    .mail-composer__address-bar > :nth-child(3) { grid-column: 1 / -1; }
    .mail-composer__workspace { position: relative; }
    .mail-composer__panel { position: absolute; inset: 0 60px 0 0; width: auto; max-width: none; z-index: 1; border-left: 0; box-shadow: 4px 0 20px #22344b18; }
    .mail-composer__tools { width: 60px; }
    .mail-composer__tools > button { width: 48px; }
    .mail-composer__editor { padding: 12px; }
    .mail-composer__footer { padding: 10px 12px; }
    .mail-composer__footer-info { display: none; }
    .mail-composer__footer > :nth-child(2) { margin-left: auto; }
    .mail-composer__product { flex-wrap: wrap; }
    .mail-composer__product-price { margin-left: 64px; text-align: left; }
}
@media (max-width: 500px) {
    .mail-composer__header { flex-wrap: wrap; }
    .mail-composer__header > div:first-child { flex: 1; }
    .mail-composer__view { order: 3; width: 100%; margin-left: 0; }
    .mail-composer__view :deep(.v-btn) { flex: 1; }
    .mail-composer__address-bar { gap: 8px; }
    .mail-composer__offer-hint { flex-wrap: wrap; padding: 10px; }
    .mail-composer__offer-hint > div { flex-basis: 100%; }
}
</style>
