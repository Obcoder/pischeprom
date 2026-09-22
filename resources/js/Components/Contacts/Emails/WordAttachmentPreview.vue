<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    messageId: { type: Number, required: true },
    attachmentIndex: { type: Number, required: true },
    attachmentId: { type: Number, default: null },
    filename: { type: String, default: '' },
})

const loading = ref(false)
const error = ref('')
const document = ref(null)
const copied = ref(false)
let request = 0
let controller = null
let copyTimer = null

async function load() {
    const revision = ++request
    controller?.abort()
    controller = new AbortController()
    document.value = null
    copied.value = false
    error.value = ''
    loading.value = true
    try {
        const { data } = await axios.post(`/api/mail-messages/${props.messageId}/attachments/${props.attachmentIndex}/word-preview`,
            props.attachmentId ? { attachment_id: props.attachmentId } : {}, { signal: controller.signal })
        if (revision === request) document.value = data
    } catch (failure) {
        if (revision === request && !axios.isCancel(failure)) {
            error.value = failure?.response?.data?.message || 'Не удалось открыть Word-документ. Попробуйте ещё раз.'
        }
    } finally {
        if (revision === request) loading.value = false
    }
}

async function copyText() {
    try {
        await navigator.clipboard.writeText(document.value?.text || '')
        copied.value = true
        clearTimeout(copyTimer)
        copyTimer = setTimeout(() => { copied.value = false }, 2000)
    } catch {
        copied.value = false
    }
}

watch(() => [props.messageId, props.attachmentIndex, props.attachmentId], load, { immediate: true })
onBeforeUnmount(() => { request++; controller?.abort(); clearTimeout(copyTimer) })
</script>

<template>
    <section class="word-preview" aria-label="Просмотр Word-вложения">
        <div class="word-preview__bar">
            <span><v-icon icon="mdi-file-word-box-outline" size="18" /> Документ Word</span>
            <button v-if="document?.has_text" type="button" @click="copyText"><v-icon :icon="copied ? 'mdi-check' : 'mdi-content-copy'" size="14" />{{ copied ? 'Скопировано' : 'Копировать текст' }}</button>
        </div>
        <div v-if="loading" class="word-preview__state" role="status"><v-progress-circular indeterminate size="28" width="2" color="#800000" /><span>Открываем документ…</span></div>
        <div v-else-if="error" class="word-preview__state word-preview__state--error" role="alert">
            <v-icon icon="mdi-file-alert-outline" size="30" /><p>{{ error }}</p>
            <button type="button" @click="load">Повторить</button>
        </div>
        <template v-else-if="document">
            <div v-if="document.truncated" class="word-preview__notice">Показана первая часть большого документа. Полный текст доступен в оригинале.</div>
            <iframe v-if="document.has_text" :srcdoc="document.html" :title="`Документ Word: ${filename || 'вложение'}`" sandbox="" referrerpolicy="no-referrer" class="word-preview__frame" />
            <div v-else class="word-preview__state"><v-icon icon="mdi-file-document-outline" size="30" /><p>В документе нет доступного текста. Скачайте оригинал, чтобы посмотреть изображения.</p></div>
            <p class="word-preview__caption">{{ document.format === 'doc' ? 'Текст документа DOC. Исходное оформление доступно в оригинале.' : 'Текст и таблицы документа. Изображения и сложное оформление доступны в оригинале.' }}</p>
        </template>
    </section>
</template>

<style scoped>
.word-preview { display: flex; flex: 1; flex-direction: column; min-height: 300px; width: 100%; overflow: hidden; border: 1px solid #dce4ec; border-radius: 10px; background: #eef2f6; color: #35475e; }
.word-preview__bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 10px 12px; background: #fff; border-bottom: 1px solid #dce4ec; font-size: 11px; }
.word-preview__bar > span, .word-preview__bar button { display: inline-flex; gap: 6px; align-items: center; }
.word-preview__bar > span { font-weight: 650; }
.word-preview button { color: #800000; font-size: 11px; }
.word-preview button:hover { text-decoration: underline; }
.word-preview button:focus-visible { outline: 2px solid #800000; outline-offset: 3px; }
.word-preview__frame { display: block; width: 100%; min-height: 340px; flex: 1; border: 0; background: #eef2f6; }
.word-preview__state { display: flex; flex: 1; min-height: 230px; flex-direction: column; gap: 14px; align-items: center; justify-content: center; padding: 24px; text-align: center; color: #71829a; font-size: 12px; }
.word-preview__state p { max-width: 360px; margin: 0; line-height: 1.6; }
.word-preview__state--error { color: #914141; }
.word-preview__caption { flex-shrink: 0; margin: 0; padding: 8px 12px; border-top: 1px solid #dce4ec; color: #8490a2; font-size: 10px; line-height: 1.5; }
.word-preview__notice { padding: 9px 12px; color: #886324; background: #fff8e7; font-size: 11px; line-height: 1.5; }
</style>
