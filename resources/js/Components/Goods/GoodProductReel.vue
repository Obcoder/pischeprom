<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    video: { type: Object, required: true },
    name: { type: String, default: '' },
})

const container = ref(null)
const player = ref(null)
const source = computed(() => props.video.video_mp4_url || props.video.url || '')
const poster = computed(() => props.video.poster_url || props.video.thumb_url || undefined)
const label = computed(() => props.video.title || props.name || 'Видео товара')
const isPlaying = ref(false)
const isMuted = ref(true)
const isVisible = ref(false)
const pageVisible = ref(true)
const reducedMotion = ref(true)
const userPaused = ref(false)
const userStarted = ref(false)
const needsInteraction = ref(false)
const hasError = ref(false)
const shouldPlay = computed(() => isVisible.value && pageVisible.value && !userPaused.value
    && (!reducedMotion.value || userStarted.value) && !hasError.value && !!source.value)

let observer
let motionPreference
let mounted = false
let playRequest = 0

async function syncPlayback() {
    const element = player.value
    if (!mounted || !element) return

    const request = ++playRequest
    if (!shouldPlay.value) {
        element.pause()
        return
    }

    element.muted = isMuted.value
    if (!element.paused) return

    try {
        await element.play()
        if (request !== playRequest || !mounted) return
        needsInteraction.value = false
        if (!shouldPlay.value) element.pause()
    } catch (error) {
        if (mounted && request === playRequest && error?.name !== 'AbortError') {
            needsInteraction.value = true
        }
    }
}

function togglePlayback() {
    if (isPlaying.value) {
        userPaused.value = true
        player.value?.pause()
    } else {
        userPaused.value = false
        userStarted.value = true
        needsInteraction.value = false
        syncPlayback()
    }
}

function toggleSound() {
    isMuted.value = !isMuted.value
    if (player.value) player.value.muted = isMuted.value
}

function updateVisibility() {
    pageVisible.value = document.visibilityState !== 'hidden'
}

function updateMotionPreference() {
    reducedMotion.value = !!motionPreference?.matches
}

function handleError() {
    hasError.value = true
    isPlaying.value = false
}

watch(shouldPlay, syncPlayback)
watch(source, async () => {
    hasError.value = false
    needsInteraction.value = false
    isPlaying.value = false
    userPaused.value = false
    userStarted.value = false
    isMuted.value = true
    await nextTick()
    syncPlayback()
})

onMounted(() => {
    mounted = true
    motionPreference = window.matchMedia?.('(prefers-reduced-motion: reduce)')
    updateMotionPreference()
    motionPreference?.addEventListener('change', updateMotionPreference)
    updateVisibility()
    document.addEventListener('visibilitychange', updateVisibility)

    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(([entry]) => {
            isVisible.value = entry.isIntersecting && entry.intersectionRatio >= 0.1
        }, { threshold: [0, 0.1] })
        observer.observe(container.value)
    } else {
        isVisible.value = true
    }

    syncPlayback()
})

onBeforeUnmount(() => {
    mounted = false
    playRequest++
    observer?.disconnect()
    motionPreference?.removeEventListener('change', updateMotionPreference)
    document.removeEventListener('visibilitychange', updateVisibility)
    player.value?.pause()
})
</script>

<template>
    <div ref="container" class="product-reel" role="group" :aria-label="`Видео товара: ${label}`">
        <video
            ref="player"
            :key="source"
            class="product-reel__video"
            :src="source"
            :poster="poster"
            :autoplay="shouldPlay"
            :muted="isMuted"
            loop
            playsinline
            preload="metadata"
            :aria-label="label"
            @playing="isPlaying = true"
            @pause="isPlaying = false"
            @error="handleError"
        />
        <img v-if="hasError && poster" :src="poster" class="product-reel__poster" alt="">
        <div class="product-reel__shade" aria-hidden="true" />
        <span class="product-reel__badge">
            <span class="product-reel__badge-dot" aria-hidden="true" />
            Видео товара
        </span>

        <div v-if="hasError" class="product-reel__error" role="status">
            <span>Видео временно недоступно</span>
            <small>Посмотрите фотографии товара рядом</small>
        </div>
        <button
            v-else-if="!isPlaying && (userPaused || reducedMotion || needsInteraction)"
            class="product-reel__start"
            type="button"
            aria-label="Воспроизвести видео товара"
            @click="togglePlayback"
        >
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m8 5 11 7-11 7z" /></svg>
        </button>

        <div class="product-reel__footer">
            <div class="product-reel__caption">
                <span>Рассмотрите поближе</span>
                <strong>{{ label }}</strong>
            </div>
            <div v-if="!hasError" class="product-reel__controls">
                <button
                    type="button"
                    class="product-reel__control"
                    :aria-label="isPlaying ? 'Приостановить видео' : 'Воспроизвести видео'"
                    :title="isPlaying ? 'Пауза' : 'Воспроизвести'"
                    @click="togglePlayback"
                >
                    <svg v-if="isPlaying" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zm8 0h4v14h-4z" /></svg>
                    <svg v-else viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m8 5 11 7-11 7z" /></svg>
                </button>
                <button
                    type="button"
                    class="product-reel__control"
                    :aria-label="isMuted ? 'Включить звук' : 'Выключить звук'"
                    :title="isMuted ? 'Включить звук' : 'Выключить звук'"
                    @click="toggleSound"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M11 5 6 9H3v6h3l5 4z" />
                        <path v-if="isMuted" d="m17 9 5 6m0-6-5 6" />
                        <template v-else><path d="M15 8a6 6 0 0 1 0 8" /><path d="M18 5a10 10 0 0 1 0 14" /></template>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.product-reel { position: relative; isolation: isolate; width: 100%; aspect-ratio: 9 / 16; overflow: hidden; border-radius: 24px; background: #271d20; color: #fff; }
.product-reel__video, .product-reel__poster { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.product-reel__shade { position: absolute; inset: 0; pointer-events: none; background: linear-gradient(180deg, rgb(24 12 17 / 32%) 0%, transparent 28%, transparent 52%, rgb(24 12 17 / 83%) 100%); }
.product-reel__badge { position: absolute; top: 16px; left: 16px; display: inline-flex; align-items: center; gap: 7px; padding: 7px 10px; border: 1px solid rgb(255 255 255 / 25%); border-radius: 100px; background: rgb(30 16 21 / 45%); font-size: 11px; font-weight: 700; line-height: 1.3; backdrop-filter: blur(10px); }
.product-reel__badge-dot { width: 6px; height: 6px; border-radius: 50%; background: #fff; }
.product-reel__footer { position: absolute; right: 16px; bottom: 16px; left: 16px; display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 12px; }
.product-reel__caption { flex: 1 1 120px; min-width: 0; }
.product-reel__caption span { display: block; margin-bottom: 5px; color: rgb(255 255 255 / 78%); font-size: 11px; line-height: 1.4; }
.product-reel__caption strong { display: -webkit-box; overflow: hidden; font-size: 13px; font-weight: 650; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.product-reel__controls { display: flex; flex-shrink: 0; gap: 7px; }
.product-reel__control, .product-reel__start { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 44px; height: 44px; padding: 0; border: 1px solid rgb(255 255 255 / 35%); border-radius: 50%; background: rgb(22 14 18 / 50%); color: #fff; cursor: pointer; backdrop-filter: blur(12px); }
.product-reel__control svg { width: 20px; height: 20px; }
.product-reel__control:hover, .product-reel__start:hover { background: rgb(92 28 47 / 90%); }
.product-reel__control:focus-visible, .product-reel__start:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }
.product-reel__start { position: absolute; top: 50%; left: 50%; width: 64px; height: 64px; transform: translate(-50%, -50%); }
.product-reel__start svg { width: 29px; height: 29px; }
.product-reel__error { position: absolute; inset: 30% 15px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center; text-shadow: 0 1px 8px #000; }
.product-reel__error span { font-size: 15px; font-weight: 700; }
.product-reel__error small { max-width: 180px; font-size: 12px; line-height: 1.5; }
@media (max-width: 600px) {
    .product-reel { border-radius: 20px; }
    .product-reel__badge { top: 12px; left: 12px; }
    .product-reel__footer { right: 12px; bottom: 12px; left: 12px; gap: 10px; }
}
</style>
