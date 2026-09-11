<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    src: { type: String, required: true },
    title: { type: String, default: 'PDF-вложение' },
})

const root = ref(null)
const scrollArea = ref(null)
const pageHost = ref(null)
const loading = ref(true)
const rendering = ref(false)
const error = ref('')
const notice = ref('')
const pageNumber = ref(1)
const pageCount = ref(0)
const zoom = ref(1)
const copying = ref(false)
const hasPageText = ref(false)
const ready = computed(() => pageCount.value > 0 && !loading.value && !error.value)
const MAX_PAGES = 50
const MAX_TEXT = 200000
let pdfModule, documentTask, pdfDocument, renderTask, textLayer, resizeObserver, resizeTimer
let generation = 0
let renderGeneration = 0
let mounted = false
let copiedText = null

function cancelRender() {
    renderGeneration++
    renderTask?.cancel()
    textLayer?.cancel()
    renderTask = null
    textLayer = null
}

function releaseDocument() {
    cancelRender()
    const previous = documentTask
    documentTask = null
    pdfDocument = null
    previous?.destroy().catch(() => {})
}

async function openDocument() {
    const request = ++generation
    releaseDocument()
    loading.value = true
    rendering.value = false
    hasPageText.value = false
    error.value = ''
    notice.value = ''
    pageCount.value = 0
    pageNumber.value = 1
    zoom.value = 1
    copying.value = false
    copiedText = null
    pageHost.value?.replaceChildren()
    try {
        pdfModule ||= await import('./mailPdfDocument')
        if (request !== generation || !mounted) return
        documentTask = pdfModule.loadMailPdf(props.src)
        const document = await documentTask.promise
        if (request !== generation || !mounted) return
        if (document.numPages > MAX_PAGES) {
            error.value = `В просмотре доступны PDF до ${MAX_PAGES} страниц. Скачайте файл, чтобы открыть его полностью.`
            releaseDocument()
            return
        }
        pdfDocument = document
        pageCount.value = document.numPages
        loading.value = false
        await nextTick()
        await renderPage()
    } catch (exception) {
        if (request !== generation || !mounted) return
        error.value = exception.name === 'PasswordException'
            ? 'PDF защищён паролем. Скачайте файл и откройте его с паролем.'
            : 'Не удалось открыть PDF. Повторите загрузку или скачайте вложение.'
    } finally {
        if (request === generation) loading.value = false
    }
}

async function renderPage() {
    if (!pdfDocument || !pageHost.value || !scrollArea.value) return
    cancelRender()
    const request = renderGeneration
    const documentRequest = generation
    rendering.value = true
    notice.value = ''
    try {
        const page = await pdfDocument.getPage(pageNumber.value)
        if (request !== renderGeneration || documentRequest !== generation) return
        const natural = page.getViewport({ scale: 1 })
        const fitWidth = Math.max(120, scrollArea.value.clientWidth - 24)
        const scale = Math.min(3, fitWidth / natural.width * zoom.value)
        const viewport = page.getViewport({ scale })
        // Bound canvas memory even for unusually large PDF page dimensions.
        const density = Math.min(window.devicePixelRatio || 1, 2, Math.sqrt(12000000 / (viewport.width * viewport.height)))
        const canvas = document.createElement('canvas')
        canvas.width = Math.max(1, Math.floor(viewport.width * density))
        canvas.height = Math.max(1, Math.floor(viewport.height * density))
        canvas.style.width = `${viewport.width}px`
        canvas.style.height = `${viewport.height}px`
        canvas.setAttribute('aria-hidden', 'true')
        const textContainer = document.createElement('div')
        textContainer.className = 'textLayer'
        textContainer.setAttribute('aria-label', `Текст PDF, страница ${pageNumber.value}`)
        const host = pageHost.value
        host.style.width = `${viewport.width}px`
        host.style.height = `${viewport.height}px`
        host.style.setProperty('--total-scale-factor', String(scale * (viewport.userUnit || 1)))
        host.style.setProperty('--scale-factor', String(scale))
        host.replaceChildren(canvas, textContainer)
        scrollArea.value.scrollTop = 0
        const canvasTask = page.render({
            canvasContext: canvas.getContext('2d'),
            viewport,
            transform: density === 1 ? null : [density, 0, 0, density, 0, 0],
        })
        renderTask = canvasTask
        // Attach a rejection handler before waiting for text extraction.
        const canvasDone = canvasTask.promise.catch(exception => {
            if (exception.name !== 'RenderingCancelledException') throw exception
        })
        const textDone = (async () => {
            const text = await page.getTextContent()
            if (request !== renderGeneration || documentRequest !== generation) return
            hasPageText.value = text.items.some(item => item.str?.trim())
            textLayer = new pdfModule.TextLayer({ textContentSource: text, container: textContainer, viewport })
            await textLayer.render()
        })()
        await Promise.all([canvasDone, textDone])
    } catch (exception) {
        if (request !== renderGeneration || documentRequest !== generation) return
        error.value = 'Не удалось отобразить страницу PDF. Повторите загрузку или скачайте вложение.'
    } finally {
        if (request === renderGeneration) rendering.value = false
    }
}

async function copyDocumentText() {
    if (!pdfDocument || copying.value) return
    const request = generation
    const document = pdfDocument
    copying.value = true
    notice.value = ''
    try {
        if (copiedText === null) {
            const pages = []
            let length = 0
            for (let index = 1; index <= document.numPages; index++) {
                const page = await document.getPage(index)
                const content = await page.getTextContent()
                if (request !== generation || !mounted) return
                const text = content.items.map(item => item.str ? `${item.str}${item.hasEOL ? '\n' : ' '}` : '').join('').trim()
                length += text.length
                if (length > MAX_TEXT) throw new Error('text_limit')
                pages.push(text)
            }
            copiedText = pages.join('\n\n').trim()
        }
        if (!copiedText) {
            notice.value = 'В PDF нет текстового слоя. Для скана используйте распознавание слева.'
            return
        }
        try {
            await navigator.clipboard.writeText(copiedText)
        } catch {
            const field = documentCreateCopyField(copiedText)
            root.value.appendChild(field)
            field.focus()
            field.select()
            const copied = window.document.execCommand('copy')
            field.remove()
            if (!copied) throw new Error('clipboard')
        }
        if (request === generation) notice.value = 'Текст PDF скопирован'
    } catch (exception) {
        if (request === generation) notice.value = exception.message === 'text_limit'
            ? 'Слишком много текста для одной операции. Выделите нужный фрагмент на странице.'
            : 'Не удалось скопировать текст. Выделите нужный фрагмент и нажмите Ctrl/Cmd+C.'
    } finally {
        if (request === generation) copying.value = false
    }
}

function documentCreateCopyField(text) {
    const field = document.createElement('textarea')
    field.value = text
    field.style.cssText = 'position:fixed;opacity:0;width:1px;height:1px;'
    field.setAttribute('readonly', '')
    return field
}

watch(() => props.src, () => { if (mounted) openDocument() })
watch([pageNumber, zoom], () => { if (ready.value) renderPage() })

onMounted(() => {
    mounted = true
    let previousWidth = 0
    resizeObserver = new ResizeObserver(([entry]) => {
        if (Math.abs(entry.contentRect.width - previousWidth) < 2) return
        previousWidth = entry.contentRect.width
        clearTimeout(resizeTimer)
        resizeTimer = setTimeout(() => { if (ready.value) renderPage() }, 100)
    })
    resizeObserver.observe(scrollArea.value)
    openDocument()
})

onBeforeUnmount(() => {
    mounted = false
    generation++
    clearTimeout(resizeTimer)
    resizeObserver?.disconnect()
    releaseDocument()
})
</script>

<template>
    <section ref="root" class="mail-pdf-viewer" :aria-label="title">
        <div class="mail-pdf-viewer__toolbar">
            <div class="mail-pdf-viewer__controls">
                <v-btn icon="mdi-chevron-left" aria-label="Предыдущая страница PDF" title="Предыдущая страница" variant="text" size="x-small" :disabled="!ready || pageNumber <= 1" @click="pageNumber--" />
                <span class="mail-pdf-viewer__pages" aria-live="polite">{{ pageCount ? `${pageNumber} / ${pageCount}` : 'PDF' }}</span>
                <v-btn icon="mdi-chevron-right" aria-label="Следующая страница PDF" title="Следующая страница" variant="text" size="x-small" :disabled="!ready || pageNumber >= pageCount" @click="pageNumber++" />
            </div>
            <div class="mail-pdf-viewer__controls">
                <v-btn icon="mdi-minus" aria-label="Уменьшить PDF" title="Уменьшить" variant="text" size="x-small" :disabled="!ready || zoom <= 0.5" @click="zoom = Math.max(0.5, zoom - 0.25)" />
                <v-btn class="mail-pdf-viewer__fit" aria-label="PDF по ширине" title="По ширине" variant="text" size="x-small" :disabled="!ready" @click="zoom = 1">{{ Math.round(zoom * 100) }}%</v-btn>
                <v-btn icon="mdi-plus" aria-label="Увеличить PDF" title="Увеличить" variant="text" size="x-small" :disabled="!ready || zoom >= 2.5" @click="zoom = Math.min(2.5, zoom + 0.25)" />
            </div>
            <v-btn class="mail-pdf-viewer__copy" icon="mdi-content-copy" aria-label="Копировать весь текст PDF" title="Копировать весь текст PDF" variant="text" size="x-small" :disabled="!ready" :loading="copying" @click="copyDocumentText" />
        </div>
        <div ref="scrollArea" class="mail-pdf-viewer__scroll" :aria-busy="loading || rendering">
            <div v-if="loading" class="mail-pdf-viewer__status" role="status"><v-progress-circular indeterminate size="24" width="2" /><span>Открываем PDF…</span></div>
            <div v-else-if="error" class="mail-pdf-viewer__status" role="alert"><span>{{ error }}</span><v-btn size="small" variant="tonal" @click="openDocument">Повторить</v-btn></div>
            <div ref="pageHost" v-show="ready" class="mail-pdf-viewer__page" />
        </div>
        <div class="mail-pdf-viewer__hint" role="status">{{ notice || (rendering || loading ? 'Загрузка…' : hasPageText ? 'Выделяйте текст для копирования · Ctrl/Cmd+C' : 'На этой странице нет текстового слоя') }}</div>
    </section>
</template>

<style scoped>
.mail-pdf-viewer { display: flex; flex: 1; flex-direction: column; min-height: 0; min-width: 0; overflow: hidden; border: 1px solid #cbd5e1; border-radius: 8px; background: #e2e8f0; color: #334155; }
.mail-pdf-viewer__toolbar { display: flex; align-items: center; flex: 0 0 auto; flex-wrap: wrap; gap: 4px; padding: 2px 5px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; }
.mail-pdf-viewer__controls { display: flex; align-items: center; gap: 1px; }
.mail-pdf-viewer__pages { min-width: 42px; text-align: center; font-size: 12px; font-variant-numeric: tabular-nums; }
.mail-pdf-viewer__fit { min-width: 46px; padding-inline: 4px; }
.mail-pdf-viewer__copy { margin-left: auto; }
.mail-pdf-viewer__scroll { position: relative; flex: 1; min-height: 0; overflow: auto; padding: 12px; overscroll-behavior: contain; }
.mail-pdf-viewer__page { position: relative; margin-inline: auto; flex: none; background: white; box-shadow: 0 1px 6px #0f172a26; --scale-round-x: 1px; --scale-round-y: 1px; }
.mail-pdf-viewer__page :deep(canvas) { display: block; }
.mail-pdf-viewer__status { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; min-height: 160px; height: 100%; text-align: center; font-size: 13px; }
.mail-pdf-viewer__hint { flex: 0 0 auto; padding: 4px 7px; background: #f8fafc; border-top: 1px solid #cbd5e1; color: #64748b; font-size: 11px; line-height: 1.3; }
/* PDF.js text-layer positioning rules, scoped to the attachment viewer. */
.mail-pdf-viewer__page :deep(.textLayer) { position: absolute; inset: 0; overflow: clip; text-align: initial; line-height: 1; letter-spacing: normal; word-spacing: normal; text-size-adjust: none; forced-color-adjust: none; transform-origin: 0 0; --min-font-size: 1; --text-scale-factor: calc(var(--total-scale-factor) * var(--min-font-size)); --min-font-size-inv: calc(1 / var(--min-font-size)); }
.mail-pdf-viewer__page :deep(.textLayer :is(span, br)) { color: transparent; position: absolute; white-space: pre; cursor: text; transform-origin: 0 0; user-select: text; }
.mail-pdf-viewer__page :deep(.textLayer > :not(.markedContent)),
.mail-pdf-viewer__page :deep(.textLayer .markedContent span:not(.markedContent)) { z-index: 1; --font-height: 0; font-size: calc(var(--text-scale-factor) * var(--font-height)); --scale-x: 1; --rotate: 0deg; transform: rotate(var(--rotate)) scaleX(var(--scale-x)) scale(var(--min-font-size-inv)); }
.mail-pdf-viewer__page :deep(.textLayer .markedContent) { display: contents; }
.mail-pdf-viewer__page :deep(.textLayer[data-main-rotation="90"]) { transform: rotate(90deg) translateY(-100%); }
.mail-pdf-viewer__page :deep(.textLayer[data-main-rotation="180"]) { transform: rotate(180deg) translate(-100%, -100%); }
.mail-pdf-viewer__page :deep(.textLayer[data-main-rotation="270"]) { transform: rotate(270deg) translateX(-100%); }
.mail-pdf-viewer__page :deep(.textLayer ::selection) { color: transparent; background: #3b82f655; }
</style>
