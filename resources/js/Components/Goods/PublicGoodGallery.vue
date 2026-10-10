<script setup>
import { computed, ref, watch } from 'vue'
import GoodProductReel from '@/Components/Goods/GoodProductReel.vue'

const props = defineProps({ good: { type: Object, required: true } })

const selectedMedia = ref(0)
const zoomOpen = ref(false)
const brokenImages = ref(new Set())
const loadedDimensions = ref({})
const country = computed(() => props.good.country?.name)
const mainVideo = computed(() => {
    const videos = (props.good.published_media || []).filter(item => item.is_published && item.type === 'video' && (item.video_mp4_url || item.url))
    return videos.find(item => item.is_main_video && item.processing_status === 'done')
        || videos.find(item => item.processing_status === 'done')
        || videos[0] || null
})
const media = computed(() => {
    const all = (props.good.published_media || [])
        .filter(item => item.is_published && (item.type === 'image' || item.type === 'video'))
        .sort((a, b) => Number(b.is_ava) - Number(a.is_ava) || (a.sort_order ?? 100) - (b.sort_order ?? 100) || a.id - b.id)
    const images = all.filter(item => item.type === 'image' && item.url)
    if (!images.length && (props.good.ava_image || props.good.ava_thumb)) {
        images.push({ id: 'avatar', type: 'image', url: props.good.ava_image || props.good.ava_thumb })
    }
    return [...images, ...all.filter(item => item.type === 'video' && item.id !== mainVideo.value?.id && (item.video_mp4_url || item.url))]
})
const activeMedia = computed(() => media.value[selectedMedia.value] || media.value[0])
const activeFrame = computed(() => frameFor(activeMedia.value))

function frameFor(item) {
    const dimensions = loadedDimensions.value[item?.url] || item || {}
    const width = Number(dimensions.width)
    const height = Number(dimensions.height)
    const ratio = width > 0 && height > 0 && Number.isFinite(width / height) ? width / height : (item?.type === 'video' ? 16 / 9 : 4 / 3)
    return { ratio, orientation: ratio < 1 ? 'portrait' : ratio > 1 ? 'landscape' : 'square' }
}

function imageLoaded(event) {
    const image = event.currentTarget
    const url = image.getAttribute('src')
    // Read the event's URL, not the current selection: a previous photo may finish loading late.
    if (!media.value.some(item => item.type === 'image' && item.url === url)) return
    if (image.naturalWidth > 0 && image.naturalHeight > 0) {
        loadedDimensions.value = { ...loadedDimensions.value, [url]: { width: image.naturalWidth, height: image.naturalHeight } }
    }
}

function imageFailed(event) {
    const url = event.currentTarget.getAttribute('src')
    if (media.value.some(item => item.url === url)) brokenImages.value = new Set([...brokenImages.value, url])
}

function thumbnailSource(item) {
    // Stored image thumbnails are square crops; use the original to preserve its full frame.
    return item.type === 'image' ? item.url : item.poster_url || item.thumb_url
}

watch(() => props.good.id, () => {
    selectedMedia.value = 0
    zoomOpen.value = false
    brokenImages.value = new Set()
    loadedDimensions.value = {}
}, { flush: 'sync' })
</script>

<template>
    <div class="product-gallery">
        <div class="product-gallery__visuals" :class="{ 'product-gallery__visuals--with-reel': mainVideo }">
            <GoodProductReel v-if="mainVideo" :key="`${good.id}-${mainVideo.id}`" :video="mainVideo" :name="good.name" class="product-gallery__reel" />
            <div
                v-if="activeMedia || !mainVideo"
                class="product-gallery__stage"
                :class="`product-gallery__stage--${activeFrame.orientation}`"
                :style="{ '--media-aspect-ratio': activeFrame.ratio }"
            >
                <span class="gallery-label"><v-icon icon="mdi-image-outline" size="15" /> Товар в деталях</span>
                <video v-if="activeMedia?.type === 'video'" :key="activeMedia.id" :src="activeMedia.video_mp4_url || activeMedia.url" :poster="activeMedia.poster_url || undefined" controls playsinline preload="metadata" :aria-label="`Видео: ${good.name}`" />
                <button v-else-if="activeMedia?.url && !brokenImages.has(activeMedia.url)" class="product-gallery__image-button" type="button" aria-label="Увеличить фотографию товара" @click="zoomOpen = true">
                    <img :key="activeMedia.url" :src="activeMedia.url" :alt="activeMedia.alt || good.name" fetchpriority="high" @load="imageLoaded" @error="imageFailed">
                    <span class="gallery-zoom"><v-icon icon="mdi-arrow-expand" size="20" /></span>
                </button>
                <div v-else class="gallery-empty"><v-icon icon="mdi-package-variant" size="72" /><span>{{ good.name }}</span><small>Фотографии можно запросить у менеджера</small></div>
                <span v-if="country" class="gallery-country"><v-icon icon="mdi-earth" size="15" /> {{ country }}</span>
            </div>
        </div>
        <div v-if="media.length > 1" class="gallery-thumbnails" aria-label="Фотографии и видео товара">
            <button
                v-for="(item, index) in media"
                :key="item.id"
                type="button"
                :class="{ selected: selectedMedia === index }"
                :style="{ '--media-aspect-ratio': frameFor(item).ratio }"
                :aria-label="item.type === 'video' ? 'Смотреть видео товара' : `Фотография ${index + 1}`"
                :aria-pressed="selectedMedia === index"
                @click="selectedMedia = index"
            >
                <img v-if="thumbnailSource(item) && !brokenImages.has(thumbnailSource(item))" :src="thumbnailSource(item)" alt="" loading="lazy" @load="imageLoaded" @error="imageFailed">
                <v-icon v-else-if="item.type === 'image'" icon="mdi-image-outline" size="22" />
                <v-icon v-if="item.type === 'video'" class="thumbnail-play" icon="mdi-play-circle" size="25" />
            </button>
        </div>
        <div class="gallery-caption"><v-icon icon="mdi-magnify" size="17" /> Рассмотрите товар перед заказом <span v-if="mainVideo">· Видео товара в движении</span></div>

        <v-dialog v-model="zoomOpen" max-width="1100" aria-label="Фотография товара">
            <div class="zoom-view">
                <button type="button" class="zoom-close" aria-label="Закрыть фотографию" @click="zoomOpen = false"><v-icon icon="mdi-close" /></button>
                <img v-if="activeMedia?.type === 'image'" :src="activeMedia.url" :alt="activeMedia.alt || good.name">
            </div>
        </v-dialog>
    </div>
</template>

<style scoped>
.product-gallery { min-width: 0; }
.product-gallery__visuals { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; align-items: start; --gallery-max-height: 530px; }
.product-gallery__visuals--with-reel { --gallery-max-height: 390px; }
.product-gallery__reel { width: 100%; min-width: 0; border-radius: 12px; }
.product-gallery__stage { position: relative; justify-self: center; width: min(100%, calc(var(--gallery-max-height) * var(--media-aspect-ratio))); aspect-ratio: var(--media-aspect-ratio); border-radius: 12px; overflow: hidden; background: #f3e9e5; }
.product-gallery__image-button { width: 100%; height: 100%; display: block; cursor: zoom-in; }
.product-gallery__stage img { display: block; width: 100%; height: 100%; object-fit: contain; }
.product-gallery__stage video { display: block; width: 100%; height: 100%; object-fit: contain; background: #241c1b; }
.gallery-label, .gallery-country { position: absolute; z-index: 1; display: flex; gap: 7px; align-items: center; font-size: 11px; background: #fffc; backdrop-filter: blur(10px); padding: 9px 12px; border-radius: 5px; pointer-events: none; }
.gallery-label { top: 12px; left: 12px; }
.gallery-country { bottom: 12px; left: 12px; max-width: calc(100% - 62px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.gallery-zoom { position: absolute; bottom: 12px; right: 12px; background: white; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; }
.gallery-empty { height: 100%; min-height: 220px; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 35px; text-align: center; gap: 18px; color: #846763; }
.gallery-empty small { color: var(--muted); }
.gallery-thumbnails { display: flex; gap: 10px; overflow-x: auto; padding: 14px 2px 3px; }
.gallery-thumbnails button { position: relative; width: auto; height: 70px; aspect-ratio: var(--media-aspect-ratio); min-width: 36px; max-width: 130px; flex-shrink: 0; border: 2px solid transparent; border-radius: 7px; overflow: hidden; background: #eee1dc; color: #846763; }
.gallery-thumbnails button.selected { border-color: var(--brand); box-shadow: 0 0 0 2px #fffaf8 inset; }
.gallery-thumbnails img { display: block; width: 100%; height: 100%; object-fit: contain; }
.thumbnail-play { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; filter: drop-shadow(0 1px 4px #000); }
.gallery-caption { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; color: var(--muted); font-size: 11px; margin-top: 15px; }
.zoom-view { position: relative; background: #fff8f4; border-radius: 10px; padding: 12px; }
.zoom-view img { display: block; width: 100%; max-height: 85vh; object-fit: contain; }
.zoom-close { position: absolute; top: 15px; right: 15px; border-radius: 50%; width: 38px; height: 38px; background: white; z-index: 1; }
button { cursor: pointer; -webkit-tap-highlight-color: transparent; }
button:focus-visible { outline: 3px solid #b04d33; outline-offset: 4px; }
@media (min-width: 1500px) {
    .product-gallery__visuals { --gallery-max-height: 590px; }
    .product-gallery__visuals--with-reel { --gallery-max-height: 430px; }
}
@media (max-width: 767px) {
    .product-gallery__visuals { gap: 8px; }
    .product-gallery__visuals--with-reel:has(.product-gallery__stage) { grid-template-columns: minmax(0, .78fr) minmax(0, 1fr); }
    .product-gallery__reel { height: clamp(246px, 66vw, 355px); aspect-ratio: auto; }
    .product-gallery__reel :deep(.product-reel__caption) { display: none; }
    .product-gallery__reel :deep(.product-reel__badge) { left: 9px; top: 10px; padding: 7px; font-size: 10px; gap: 5px; }
    .product-gallery__stage { width: 100%; height: clamp(246px, 66vw, 355px); aspect-ratio: auto; }
    .gallery-label { top: 10px; left: 10px; font-size: 10px; padding: 8px; }
    .gallery-label .v-icon { display: none; }
    .gallery-country { bottom: 10px; left: 10px; font-size: 9px; max-width: calc(100% - 54px); padding: 7px; }
    .gallery-zoom { right: 8px; bottom: 8px; width: 34px; height: 34px; }
    .gallery-thumbnails { gap: 8px; padding: 10px 1px 3px; scroll-snap-type: x proximity; scrollbar-width: none; }
    .gallery-thumbnails::-webkit-scrollbar { display: none; }
    .gallery-thumbnails button { width: 52px; height: 50px; scroll-snap-align: start; }
    .gallery-caption { display: none; }
}
</style>
