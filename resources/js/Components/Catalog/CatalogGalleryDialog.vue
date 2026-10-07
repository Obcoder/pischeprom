<script setup>
import axios from 'axios'
import { computed, onScopeDispose, ref, watch } from 'vue'
import { buildCatalogPhotos, safeGalleryUrl } from './gallery.js'

const open = defineModel({ type: Boolean, default: false })
const props = defineProps({ node: { type: Object, default: null } })
const media = ref([])
const loading = ref(false)
const error = ref('')
const selectedIndex = ref(0)
const brokenImages = ref(new Set())
const brokenThumbnails = ref(new Set())
const loadedImages = ref({})
const imageAttempt = ref(0)
let requestVersion = 0
let controller = null

const photos = computed(() => buildCatalogPhotos(props.node, media.value))
const activePhoto = computed(() => photos.value[selectedIndex.value] || null)
const entityName = computed(() => ({ good: 'Товар', product: 'Продукт', category: 'Категория' })[props.node?.entity_type] || 'Объект')
const identity = computed(() => `${entityName.value} № ${props.node?.entity_id || props.node?.id || '—'}`)
const classificationPath = computed(() => props.node?.path_label || (props.node?.ancestors || []).map(item => item.name).filter(Boolean).join(' / '))
const editUrl = computed(() => safeGalleryUrl(props.node?.edit_url))
const photoDetails = computed(() => {
    const photo = activePhoto.value
    if (!photo) return ''
    const loaded = loadedImages.value[photo.url]
    const width = Number(photo.width) || loaded?.width
    const height = Number(photo.height) || loaded?.height
    const parts = []
    if (width > 0 && height > 0) parts.push(`${width} × ${height} px`)
    if (Number(photo.size) > 0) {
        const megabytes = Number(photo.size) / 1048576
        parts.push(megabytes >= 1 ? `${megabytes.toLocaleString('ru', { maximumFractionDigits: 1 })} МБ` : `${Math.max(1, Math.round(Number(photo.size) / 1024))} КБ`)
    }
    return parts.join(' · ')
})

function cancelRequest() {
    requestVersion++
    controller?.abort()
    controller = null
}
async function loadMedia() {
    cancelRequest()
    error.value = ''
    loading.value = false
    if (!open.value || props.node?.entity_type !== 'good' || !/^[1-9]\d*$/.test(String(props.node?.entity_id || ''))) return
    const version = requestVersion
    controller = new AbortController()
    loading.value = true
    try {
        const { data } = await axios.get(`/api/goods/${props.node.entity_id}/media`, { signal: controller.signal })
        if (version !== requestVersion || !open.value) return
        media.value = Array.isArray(data) ? data : []
    } catch (failure) {
        if (version !== requestVersion || !open.value) return
        const status = failure.response?.status
        error.value = [401, 403].includes(status) ? 'Нет доступа к фотографиям товара.'
            : status === 404 ? 'Галерея товара недоступна.' : 'Не удалось загрузить фотографии.'
    } finally {
        if (version === requestVersion) { loading.value = false; controller = null }
    }
}
function reset() {
    media.value = []; selectedIndex.value = 0; brokenImages.value = new Set(); brokenThumbnails.value = new Set()
    loadedImages.value = {}; imageAttempt.value = 0
    loadMedia()
}
watch([open, () => props.node], reset, { immediate: true, flush: 'sync' })
watch(photos, value => { selectedIndex.value = Math.min(selectedIndex.value, Math.max(0, value.length - 1)) })
onScopeDispose(cancelRequest)

function select(index) {
    if (photos.value.length) selectedIndex.value = (index + photos.value.length) % photos.value.length
}
function onKeydown(event) {
    if (!open.value || event.altKey || event.ctrlKey || event.metaKey) return
    if (event.key === 'Escape') { event.preventDefault(); open.value = false; return }
    if (event.target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName)) return
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault()
        select(selectedIndex.value + (event.key === 'ArrowLeft' ? -1 : 1))
    }
}
const eventImage = event => event.currentTarget || event.target
const eventImageUrl = event => eventImage(event)?.getAttribute('src') || ''
function imageFailed(event) {
    const url = eventImageUrl(event)
    if (photos.value.some(photo => photo.url === url)) brokenImages.value = new Set([...brokenImages.value, url])
}
function imageLoaded(event) {
    const image = eventImage(event)
    const url = eventImageUrl(event)
    if (photos.value.some(photo => photo.url === url)) loadedImages.value = { ...loadedImages.value, [url]: { width: image.naturalWidth, height: image.naturalHeight } }
}
function retryImage() {
    const next = new Set(brokenImages.value)
    next.delete(activePhoto.value?.url)
    brokenImages.value = next
    imageAttempt.value++
}
function thumbnailSource(photo) { return brokenThumbnails.value.has(photo.thumbnail) ? photo.url : photo.thumbnail }
function thumbnailFailed(event) { brokenThumbnails.value = new Set([...brokenThumbnails.value, eventImageUrl(event)]) }
</script>

<template>
    <v-dialog v-model="open" max-width="1040" scrollable @keydown="onKeydown">
        <v-card class="catalog-gallery">
            <v-card-title class="catalog-gallery__heading">
                <div class="catalog-gallery__heading-text">
                    <p class="catalog-gallery__eyebrow">Фотографии <span>· {{ identity }}</span></p>
                    <h2>{{ node?.name || 'Галерея' }}</h2>
                    <p v-if="classificationPath || node?.level_name" class="catalog-gallery__path">{{ classificationPath || node.level_name }}</p>
                </div>
                <v-btn icon="mdi-close" size="small" variant="text" aria-label="Закрыть галерею" @click="open = false" />
            </v-card-title>
            <v-divider />
            <v-card-text class="catalog-gallery__body">
                <div v-if="error" class="catalog-gallery__error" role="alert"><v-icon icon="mdi-alert-circle-outline" size="18" /><span>{{ error }}</span><v-btn size="small" variant="text" :loading="loading" @click="loadMedia">Повторить</v-btn></div>
                <div class="catalog-gallery__stage" :aria-busy="loading">
                    <v-progress-linear v-if="loading && activePhoto" indeterminate color="#725382" class="catalog-gallery__progress" />
                    <template v-if="activePhoto">
                        <div v-if="brokenImages.has(activePhoto.url)" class="catalog-gallery__placeholder" role="status"><v-icon icon="mdi-image-broken-variant" size="42" /><strong>Не удалось открыть фотографию</strong><v-btn variant="text" size="small" prepend-icon="mdi-refresh" @click="retryImage">Повторить</v-btn></div>
                        <template v-else>
                            <v-progress-circular v-if="!loadedImages[activePhoto.url]" indeterminate size="28" width="2" color="#8a719b" class="catalog-gallery__image-loading" />
                            <img :key="activePhoto.url + ':' + imageAttempt" :src="activePhoto.url" :alt="activePhoto.alt" class="catalog-gallery__original" @load="imageLoaded" @error="imageFailed" />
                        </template>
                        <span class="catalog-gallery__counter" aria-live="polite">{{ selectedIndex + 1 }} / {{ photos.length }}</span>
                        <template v-if="photos.length > 1">
                            <v-btn class="catalog-gallery__previous" icon="mdi-chevron-left" size="small" variant="flat" aria-label="Предыдущее фото" @click="select(selectedIndex - 1)" />
                            <v-btn class="catalog-gallery__next" icon="mdi-chevron-right" size="small" variant="flat" aria-label="Следующее фото" @click="select(selectedIndex + 1)" />
                        </template>
                    </template>
                    <div v-else class="catalog-gallery__placeholder" role="status">
                        <v-progress-circular v-if="loading" indeterminate size="30" width="2" color="#8a719b" />
                        <v-icon v-else icon="mdi-image-multiple-outline" size="42" />
                        <strong>{{ loading ? 'Загрузка фотографий…' : 'Фотографий пока нет' }}</strong>
                    </div>
                </div>
                <div v-if="activePhoto" class="catalog-gallery__information">
                    <div class="catalog-gallery__caption"><strong v-if="activePhoto.title">{{ activePhoto.title }}</strong><p v-if="activePhoto.caption">{{ activePhoto.caption }}</p><span v-if="photoDetails" class="catalog-gallery__details">{{ photoDetails }}</span></div>
                    <div class="catalog-gallery__labels"><span v-if="activePhoto.isAvatar" class="catalog-gallery__label">Аватар</span><span v-if="activePhoto.isPublished === false" class="catalog-gallery__label">Не опубликовано</span></div>
                </div>
                <div v-if="photos.length > 1" class="catalog-gallery__thumbnails" aria-label="Фотографии товара">
                    <button v-for="(photo, index) in photos" :key="photo.url" type="button" class="catalog-gallery__thumbnail" :class="{ 'is-selected': index === selectedIndex }" :aria-label="`Фото ${index + 1}${photo.title ? ': ' + photo.title : ''}`" :aria-pressed="index === selectedIndex" @click="select(index)">
                        <v-icon v-if="brokenThumbnails.has(thumbnailSource(photo))" icon="mdi-image-outline" size="24" />
                        <img v-else :src="thumbnailSource(photo)" :alt="photo.alt" loading="lazy" @error="thumbnailFailed" />
                    </button>
                </div>
            </v-card-text>
            <v-divider />
            <v-card-actions class="catalog-gallery__actions">
                <v-btn v-if="editUrl" :href="editUrl" target="_blank" rel="noopener noreferrer" variant="text" prepend-icon="mdi-card-text-outline" size="small">Карточка</v-btn>
                <v-spacer />
                <v-btn v-if="activePhoto" :href="activePhoto.url" target="_blank" rel="noopener noreferrer" variant="tonal" prepend-icon="mdi-open-in-new" size="small">Открыть оригинал</v-btn>
                <v-btn variant="text" size="small" @click="open = false">Закрыть</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.catalog-gallery { border-radius: 14px !important; }
.catalog-gallery__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 16px 20px; white-space: normal; }
.catalog-gallery__heading-text { min-width: 0; }
.catalog-gallery__eyebrow { color: #74627f; font-size: 11px; font-weight: 550; line-height: 1.5; }
.catalog-gallery__eyebrow span { color: #998cA2; font-weight: 400; }
.catalog-gallery__heading h2 { font-size: 18px; line-height: 1.45; margin-top: 3px; overflow-wrap: anywhere; }
.catalog-gallery__path { color: #8c8095; font-size: 11px; line-height: 1.5; margin-top: 4px; overflow-wrap: anywhere; }
.catalog-gallery__body { padding: 14px 20px !important; }
.catalog-gallery__error { display: flex; align-items: center; gap: 8px; color: #925a34; font-size: 12px; background: #fff6ed; border-radius: 8px; padding: 3px 10px; margin-bottom: 12px; }
.catalog-gallery__error span { flex: 1; }
.catalog-gallery__stage { position: relative; display: flex; align-items: center; justify-content: center; width: 100%; height: clamp(190px, 49vh, 480px); overflow: hidden; border: 1px solid #eee8f2; border-radius: 10px; background: #faf8fc; }
.catalog-gallery__progress { position: absolute; inset: 0 0 auto; z-index: 2; }
.catalog-gallery__original { display: block; width: 100%; height: 100%; object-fit: contain; position: relative; }
.catalog-gallery__image-loading { position: absolute; }
.catalog-gallery__counter { position: absolute; top: 12px; right: 12px; border-radius: 12px; padding: 3px 9px; background: #ffffffed; color: #685674; border: 1px solid #e9e1ef; font-size: 11px; font-variant-numeric: tabular-nums; }
.catalog-gallery__previous, .catalog-gallery__next { position: absolute; top: 50%; transform: translateY(-50%); color: #685674; background: #ffffffed; border: 1px solid #e9e1ef; box-shadow: 0 2px 8px #2a103c12; }
.catalog-gallery__previous { left: 10px; }
.catalog-gallery__next { right: 10px; }
.catalog-gallery__placeholder { display: flex; align-items: center; flex-direction: column; gap: 12px; color: #998aa4; padding: 30px; text-align: center; font-size: 12px; }
.catalog-gallery__placeholder strong { font-weight: 500; }
.catalog-gallery__information { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 8px 12px; padding: 9px 2px 0; font-size: 12px; }
.catalog-gallery__caption { min-width: 0; overflow-wrap: anywhere; }
.catalog-gallery__caption strong { color: #62506d; font-weight: 550; }
.catalog-gallery__caption p { color: #87798f; line-height: 1.5; white-space: pre-line; }
.catalog-gallery__details { display: block; color: #998aa4; font-size: 11px; margin-top: 2px; }
.catalog-gallery__labels { display: flex; gap: 6px; flex-wrap: wrap; }
.catalog-gallery__label { padding: 2px 7px; background: #f3eef6; color: #8c779a; border-radius: 5px; font-size: 10px; white-space: nowrap; }
.catalog-gallery__thumbnails { display: flex; gap: 8px; overflow-x: auto; padding: 12px 2px 3px; }
.catalog-gallery__thumbnail { flex: 0 0 64px; width: 64px; height: 64px; min-width: 0; min-height: 0; overflow: hidden; padding: 3px; display: flex; align-items: center; justify-content: center; border: 1px solid #e8e0ed; border-radius: 7px; color: #a191ac; background: #faf8fc; cursor: pointer; }
.catalog-gallery__thumbnail img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
.catalog-gallery__thumbnail.is-selected { border: 2px solid #79538c; padding: 2px; background: #f1e9f7; }
.catalog-gallery__thumbnail:focus-visible { outline: 2px solid #79538c; outline-offset: 2px; }
.catalog-gallery__actions { padding: 10px 16px; gap: 4px; flex-wrap: wrap; }
@media (max-width: 600px) {
    .catalog-gallery__heading { padding: 12px; gap: 6px; }
    .catalog-gallery__heading h2 { font-size: 15px; }
    .catalog-gallery__body { padding: 10px !important; }
    .catalog-gallery__stage { height: clamp(180px, 43vh, 350px); }
    .catalog-gallery__thumbnail { flex-basis: 52px; width: 52px; height: 52px; }
    .catalog-gallery__actions { padding: 8px; }
    .catalog-gallery__actions :deep(.v-btn) { font-size: 11px; padding-inline: 9px; }
}
</style>
