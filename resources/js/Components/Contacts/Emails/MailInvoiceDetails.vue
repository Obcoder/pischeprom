<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    messageId: { type: [Number, String], required: true },
    attachmentIndex: { type: [Number, String], required: true },
    attachmentId: { type: [Number, String], default: null },
})

const card = ref(null)
const result = ref(null)
const loading = ref(false)
const recognizingScan = ref(false)
const errorMessage = ref('')
const copyFeedback = ref('')
let request = null
let feedbackTimer = null

const isInvoice = computed(() => result.value?.status === 'invoice')
const fields = computed(() => {
    const values = result.value?.fields || {}
    const date = /^\d{4}-\d{2}-\d{2}$/.test(values.date || '')
        ? values.date.split('-').reverse().join('.')
        : values.date

    return [
        { key: 'number', label: '№ счёта', value: values.number },
        { key: 'date', label: 'Дата счёта', value: date },
        { key: 'counterparty', label: 'Контрагент · поставщик', value: values.counterparty },
        { key: 'heading', label: 'Строка счёта', value: values.heading },
    ]
})
const invoiceText = computed(() => fields.value
    .filter((field) => field.value)
    .map((field) => `${field.label}: ${field.value}`)
    .join('\n'))

async function analyze(ocr = false) {
    request?.abort()
    const controller = new AbortController()
    request = controller
    loading.value = true
    recognizingScan.value = ocr
    errorMessage.value = ''
    copyFeedback.value = ''

    try {
        const { data } = await axios.post(
            `/api/mail-messages/${props.messageId}/attachments/${props.attachmentIndex}/analyze`,
            {
                ...(props.attachmentId ? { attachment_id: props.attachmentId } : {}),
                ...(ocr ? { ocr: true } : {}),
            },
            { signal: controller.signal, timeout: ocr ? 150000 : 45000 },
        )

        if (!controller.signal.aborted) {
            result.value = data
        }
    } catch (error) {
        if (!controller.signal.aborted) {
            errorMessage.value = error?.response?.data?.message
                || 'Не удалось прочитать PDF. Попробуйте ещё раз.'
        }
    } finally {
        if (request === controller) {
            loading.value = false
            recognizingScan.value = false
        }
    }
}

async function copy(value) {
    if (!value) return

    let copied = false

    try {
        await navigator.clipboard.writeText(String(value))
        copied = true
    } catch {
        const input = document.createElement('textarea')
        const previouslyFocused = document.activeElement
        input.value = String(value)
        input.setAttribute('readonly', '')
        input.style.cssText = 'position:fixed;left:0;top:0;width:1px;height:1px;opacity:0;'
        card.value?.appendChild(input)
        input.select()

        try {
            copied = document.execCommand('copy')
        } catch {
            copied = false
        } finally {
            input.remove()
            previouslyFocused?.focus?.({ preventScroll: true })
        }
    }

    copyFeedback.value = copied ? 'Скопировано' : 'Выделите текст и нажмите Ctrl+C / ⌘C.'
    clearTimeout(feedbackTimer)
    feedbackTimer = setTimeout(() => { copyFeedback.value = '' }, 3500)
}

watch(() => [props.messageId, props.attachmentIndex, props.attachmentId], () => {
    result.value = null
    analyze()
}, { immediate: true })

onBeforeUnmount(() => {
    request?.abort()
    clearTimeout(feedbackTimer)
})
</script>

<template>
    <section ref="card" class="mail-invoice-details" aria-label="Реквизиты счёта из PDF" :aria-busy="loading">
        <div class="mail-invoice-details__header">
            <v-icon icon="mdi-file-document-check-outline" size="17" />
            <strong>Реквизиты счёта</strong>
            <span v-if="result && !loading" class="mail-invoice-details__source">
                {{ result.source === 'ocr' ? 'OCR' : 'PDF' }}
            </span>
        </div>

        <div v-if="loading" class="mail-invoice-details__status" role="status">
            <v-progress-circular indeterminate size="18" width="2" color="cyan-lighten-2" />
            <span>{{ recognizingScan ? 'Распознаём скан…' : 'Читаем PDF…' }}</span>
        </div>

        <div v-else-if="errorMessage" class="mail-invoice-details__notice is-error" role="alert">
            <p>{{ errorMessage }}</p>
            <button type="button" class="mail-invoice-details__button" @click="analyze()">
                <v-icon icon="mdi-refresh" size="14" />
                Повторить
            </button>
        </div>

        <template v-else-if="result">
            <dl v-if="isInvoice" class="mail-invoice-details__fields">
                <div v-for="field in fields" :key="field.key" class="mail-invoice-details__field" :class="`is-${field.key}`">
                    <dt>{{ field.label }}</dt>
                    <dd :class="{ 'is-missing': !field.value }">{{ field.value || '—' }}</dd>
                    <button
                        v-if="field.value"
                        type="button"
                        class="mail-invoice-details__copy"
                        :aria-label="`Копировать: ${field.label}`"
                        :title="`Копировать: ${field.label}`"
                        @click="copy(field.value)"
                    >
                        <v-icon icon="mdi-content-copy" size="14" />
                    </button>
                </div>
            </dl>

            <div v-else-if="result.status === 'no_text'" class="mail-invoice-details__notice">
                <p>В PDF нет доступного текста. Возможно, это скан.</p>
                <button
                    v-if="result.ocr_available"
                    type="button"
                    class="mail-invoice-details__button"
                    @click="analyze(true)"
                >
                    <v-icon icon="mdi-text-recognition" size="15" />
                    Распознать скан
                </button>
                <p v-else class="mail-invoice-details__hint">{{ result.ocr_message || 'OCR для этого файла недоступен.' }}</p>
            </div>

            <div v-else class="mail-invoice-details__notice">
                <p>Счёт на оплату не найден.</p>
                <p class="mail-invoice-details__hint">Текст PDF можно выделить или скопировать целиком.</p>
                <button
                    v-if="result.ocr_available"
                    type="button"
                    class="mail-invoice-details__button"
                    @click="analyze(true)"
                >
                    <v-icon icon="mdi-text-recognition" size="15" />
                    Распознать как скан
                </button>
                <p v-else-if="result.ocr_message" class="mail-invoice-details__hint">{{ result.ocr_message }}</p>
            </div>

            <div v-if="invoiceText || result.text" class="mail-invoice-details__actions">
                <button v-if="isInvoice && invoiceText" type="button" class="mail-invoice-details__button" @click="copy(invoiceText)">
                    <v-icon icon="mdi-content-copy" size="14" />
                    Реквизиты
                </button>
                <button v-if="result.text" type="button" class="mail-invoice-details__button is-secondary" @click="copy(result.text)">
                    <v-icon icon="mdi-text-box-outline" size="14" />
                    Весь текст
                </button>
            </div>

            <p v-if="isInvoice" class="mail-invoice-details__hint">Проверьте реквизиты по оригиналу.</p>
        </template>

        <div v-if="copyFeedback" class="mail-invoice-details__feedback" role="status">{{ copyFeedback }}</div>
    </section>
</template>

<style scoped>
.mail-invoice-details {
    background: linear-gradient(145deg, rgba(8, 47, 73, 0.72), rgba(15, 23, 42, 0.95));
    border: 1px solid rgba(103, 232, 249, 0.25);
    border-radius: 9px;
    color: #e2e8f0;
    font-size: 12px;
    line-height: 1.4;
    min-width: 0;
    padding: 8px;
}

.mail-invoice-details__header,
.mail-invoice-details__status,
.mail-invoice-details__actions {
    align-items: center;
    display: flex;
    gap: 6px;
}

.mail-invoice-details__header {
    color: #a5f3fc;
    margin-bottom: 7px;
}

.mail-invoice-details__header strong {
    font-size: 12px;
    font-weight: 600;
}

.mail-invoice-details__source {
    background: rgba(6, 182, 212, 0.1);
    border-radius: 4px;
    color: #67e8f9;
    font-size: 10px;
    margin-left: auto;
    padding: 1px 5px;
}

.mail-invoice-details__status {
    color: #94a3b8;
    min-height: 54px;
}

.mail-invoice-details__fields {
    display: grid;
    gap: 6px 8px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin: 0;
}

.mail-invoice-details__field {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 26px;
    min-width: 0;
}

.mail-invoice-details__field.is-counterparty,
.mail-invoice-details__field.is-heading {
    border-top: 1px solid rgba(148, 163, 184, 0.12);
    grid-column: 1 / -1;
    padding-top: 5px;
}

.mail-invoice-details__field dt {
    color: #94a3b8;
    font-size: 10px;
    grid-column: 1 / -1;
}

.mail-invoice-details__field dd {
    align-self: center;
    margin: 0;
    overflow-wrap: anywhere;
    user-select: text;
}

.mail-invoice-details__field.is-number dd {
    color: #a5f3fc;
    font-size: 13px;
    font-weight: 500;
}

.mail-invoice-details__field dd.is-missing {
    color: #64748b;
}

.mail-invoice-details__copy,
.mail-invoice-details__button {
    align-items: center;
    border: 1px solid transparent;
    border-radius: 5px;
    color: #a5f3fc;
    cursor: pointer;
    display: inline-flex;
    font: inherit;
    gap: 4px;
    justify-content: center;
    min-height: 28px;
    padding: 3px 6px;
}

.mail-invoice-details__copy {
    align-self: start;
    color: #94a3b8;
    padding: 3px;
}

.mail-invoice-details__button {
    background: rgba(6, 182, 212, 0.12);
    border-color: rgba(103, 232, 249, 0.18);
    font-size: 11px;
}

.mail-invoice-details__button.is-secondary {
    background: rgba(148, 163, 184, 0.07);
    border-color: rgba(148, 163, 184, 0.14);
    color: #cbd5e1;
}

.mail-invoice-details__copy:hover,
.mail-invoice-details__button:hover {
    background: rgba(6, 182, 212, 0.22);
    color: #ecfeff;
}

.mail-invoice-details__copy:focus-visible,
.mail-invoice-details__button:focus-visible {
    outline: 2px solid #67e8f9;
    outline-offset: 2px;
}

.mail-invoice-details__actions {
    flex-wrap: wrap;
    margin-top: 8px;
}

.mail-invoice-details__notice p {
    margin: 0 0 7px;
    overflow-wrap: anywhere;
}

.mail-invoice-details__notice.is-error {
    color: #fda4af;
}

.mail-invoice-details__hint {
    color: #94a3b8;
    font-size: 10px;
    margin: 6px 0 0;
}

.mail-invoice-details__feedback {
    color: #6ee7b7;
    font-size: 11px;
    margin-top: 5px;
}
</style>
