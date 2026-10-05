<script setup>
import { computed, ref, watch } from 'vue'
import { useAppRoute } from '@/Composables/useAppRoute'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'

const props = defineProps({
    feed: { type: Object, default: () => ({}) },
    previewDevice: { type: String, default: null },
})

const { route } = useAppRoute()
const { goodPublicUrl } = usePublicGoodUrl()
const mobileTrack = ref(null)
const firstMobileIndex = ref(0)

const settings = computed(() => ({
    enabled: true,
    desktop_height: 96,
    gap: 8,
    mobile_enabled: true,
    mobile_layout: 'scroll',
    mobile_height: 96,
    mobile_columns: 2,
    mobile_hide_empty: true,
    mobile_order: [1, 2, 3, 4, 5, 6],
    ...props.feed.settings,
}))

function sixSlots(items) {
    return Array.from({ length: 6 }, (_, index) => ({
        slot: index + 1,
        banner: Array.isArray(items) ? items[index] || null : null,
    }))
}

const desktopSlots = computed(() => sixSlots(props.feed.desktop))
const mobileSlots = computed(() => {
    const slots = sixSlots(props.feed.mobile)
    const requested = settings.value.mobile_order
    const order = Array.isArray(requested) && requested.length === 6
        && new Set(requested).size === 6 && requested.every(slot => Number.isInteger(slot) && slot >= 1 && slot <= 6)
        ? requested : [1, 2, 3, 4, 5, 6]
    const ordered = order.map(slot => slots[slot - 1])

    return settings.value.mobile_hide_empty ? ordered.filter(item => item.banner) : ordered
})
const hasDesktop = computed(() => desktopSlots.value.some(item => item.banner))
const hasMobile = computed(() => mobileSlots.value.some(item => item.banner))
const visible = computed(() => props.previewDevice || (settings.value.enabled && (hasDesktop.value || hasMobile.value)))
const mobileColumns = computed(() => Number(settings.value.mobile_columns) === 1 ? 1 : 2)
const lastMobileIndex = computed(() => Math.max(0, mobileSlots.value.length - mobileColumns.value))
const scrollControls = computed(() => settings.value.mobile_layout === 'scroll'
    && mobileSlots.value.length > mobileColumns.value)
const rowStyle = computed(() => ({
    '--strip-desktop-height': bounded(settings.value.desktop_height, 60, 160, 96) + 'px',
    '--strip-mobile-height': bounded(settings.value.mobile_height, 60, 160, 96) + 'px',
    '--strip-gap': bounded(settings.value.gap, 4, 20, 8) + 'px',
    '--strip-mobile-columns': mobileColumns.value,
}))

watch(mobileSlots, () => {
    firstMobileIndex.value = 0
    mobileTrack.value?.scrollTo({ left: 0, behavior: 'instant' })
}, { flush: 'post' })

function bounded(value, min, max, fallback) {
    const number = Number(value)
    return Number.isFinite(number) ? Math.min(max, Math.max(min, number)) : fallback
}

function safeUrl(value) {
    const url = typeof value === 'string' ? value.trim() : ''
    if (/^\/(?!\/)/.test(url) && !/[\\\u0000-\u001f]/.test(url)) return url
    if (/^https?:\/\//i.test(url) && !/[\\\u0000-\u001f]/.test(url)) return url
    return ''
}

function bannerHref(banner) {
    if (safeUrl(banner.cta_url)) return safeUrl(banner.cta_url)
    if (banner.good) return goodPublicUrl(banner.good, false)
    if (banner.category?.slug || banner.category?.id) {
        return route('category.show', banner.category.slug || banner.category.id)
    }
    if (banner.product?.id) {
        const href = route('shop.products.show', banner.product.id)
        return href === '#' ? '/p/' + encodeURIComponent(banner.product.id) : href
    }
    return route('public.goods.index')
}

function bannerImage(banner, device) {
    return safeUrl(device === 'mobile'
        ? banner.mobile_image_url || banner.image_url
        : banner.image_url || banner.mobile_image_url)
}

function bannerMode(banner, device) {
    return bannerImage(banner, device) ? banner.content_mode || 'image' : 'text'
}

function bannerStyle(banner, device) {
    const mobile = device === 'mobile'
    const vertical = banner.vertical_align || 'center'
    return {
        '--banner-background': banner.background_color || '#f5f2ed',
        '--banner-text': banner.text_color || '#292624',
        '--banner-accent': banner.accent_color || '#800000',
        '--banner-image-fit': (mobile ? banner.mobile_image_fit : banner.image_fit) || 'contain',
        '--banner-image-position': (mobile ? banner.mobile_image_position : banner.image_position) || 'center center',
        '--banner-text-align': banner.text_align || 'left',
        '--banner-vertical-align': vertical === 'top' ? 'flex-start' : vertical === 'bottom' ? 'flex-end' : 'center',
    }
}

function onMobileScroll(event) {
    const track = event.target
    const step = (track.clientWidth + bounded(settings.value.gap, 4, 20, 8)) / mobileColumns.value
    firstMobileIndex.value = Math.min(lastMobileIndex.value, Math.max(0, Math.round(track.scrollLeft / step)))
}

function scrollMobile(direction) {
    const track = mobileTrack.value
    if (!track) return
    const step = (track.clientWidth + bounded(settings.value.gap, 4, 20, 8)) / mobileColumns.value
    const reduced = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    track.scrollBy({ left: direction * step, behavior: reduced ? 'instant' : 'smooth' })
}
</script>

<template>
    <section
        v-if="visible"
        class="home-banner-strip"
        :class="{
            'home-banner-strip--preview-desktop': previewDevice === 'desktop',
            'home-banner-strip--preview-mobile': previewDevice === 'mobile',
            'home-banner-strip--desktop-empty': !hasDesktop && !previewDevice,
            'home-banner-strip--mobile-empty': (!hasMobile || !settings.mobile_enabled) && !previewDevice,
        }"
        :style="rowStyle"
        aria-label="Предложения магазина"
    >
        <div class="home-banner-strip__desktop" role="list" aria-label="Шесть позиций баннеров">
            <div v-for="item in desktopSlots" :key="item.slot" class="home-banner-strip__slot" role="listitem" :data-slot="item.slot">
                <a
                    v-if="item.banner"
                    :href="bannerHref(item.banner)"
                    :target="item.banner.open_in_new_tab ? '_blank' : undefined"
                    :rel="item.banner.open_in_new_tab ? 'noopener noreferrer' : undefined"
                    :aria-label="item.banner.title"
                    :tabindex="previewDevice ? -1 : undefined"
                    class="home-banner-strip__banner"
                    :class="'home-banner-strip__banner--' + bannerMode(item.banner, 'desktop')"
                    :style="bannerStyle(item.banner, 'desktop')"
                    @click="previewDevice && $event.preventDefault()"
                >
                    <img
                        v-if="bannerMode(item.banner, 'desktop') !== 'text'"
                        :src="bannerImage(item.banner, 'desktop')"
                        :alt="item.banner.alt_text || item.banner.title"
                        width="800"
                        height="400"
                        loading="lazy"
                        decoding="async"
                    >
                    <div v-if="bannerMode(item.banner, 'desktop') !== 'image'" class="home-banner-strip__copy">
                        <span class="home-banner-strip__title">{{ item.banner.title }}</span>
                        <span v-if="item.banner.subtitle" class="home-banner-strip__subtitle">{{ item.banner.subtitle }}</span>
                    </div>
                </a>
                <div v-else class="home-banner-strip__empty" aria-hidden="true" />
            </div>
        </div>

        <div class="home-banner-strip__mobile">
            <div
                ref="mobileTrack"
                class="home-banner-strip__track"
                :class="'home-banner-strip__track--' + settings.mobile_layout"
                role="list"
                aria-label="Баннеры на телефоне"
                @scroll="onMobileScroll"
                @keydown.left.prevent="scrollMobile(-1)"
                @keydown.right.prevent="scrollMobile(1)"
            >
                <div v-for="item in mobileSlots" :key="item.slot" class="home-banner-strip__slot" role="listitem" :data-slot="item.slot">
                    <a
                        v-if="item.banner"
                        :href="bannerHref(item.banner)"
                        :target="item.banner.open_in_new_tab ? '_blank' : undefined"
                        :rel="item.banner.open_in_new_tab ? 'noopener noreferrer' : undefined"
                        :aria-label="item.banner.title"
                        :tabindex="previewDevice ? -1 : undefined"
                        class="home-banner-strip__banner"
                        :class="'home-banner-strip__banner--' + bannerMode(item.banner, 'mobile')"
                        :style="bannerStyle(item.banner, 'mobile')"
                        @click="previewDevice && $event.preventDefault()"
                    >
                        <img
                            v-if="bannerMode(item.banner, 'mobile') !== 'text'"
                            :src="bannerImage(item.banner, 'mobile')"
                            :alt="item.banner.alt_text || item.banner.title"
                            width="800"
                            height="400"
                            loading="lazy"
                            decoding="async"
                        >
                        <div v-if="bannerMode(item.banner, 'mobile') !== 'image'" class="home-banner-strip__copy">
                            <span class="home-banner-strip__title">{{ item.banner.title }}</span>
                            <span v-if="item.banner.subtitle" class="home-banner-strip__subtitle">{{ item.banner.subtitle }}</span>
                        </div>
                    </a>
                    <div v-else class="home-banner-strip__empty" aria-hidden="true" />
                </div>
            </div>

            <div v-if="scrollControls" class="home-banner-strip__controls">
                <span aria-live="polite">{{ firstMobileIndex + 1 }}–{{ Math.min(firstMobileIndex + mobileColumns, mobileSlots.length) }} из {{ mobileSlots.length }}</span>
                <button type="button" aria-label="Предыдущие баннеры" :disabled="firstMobileIndex === 0" @click="scrollMobile(-1)">
                    <v-icon icon="mdi-chevron-left" size="18" aria-hidden="true" />
                </button>
                <button type="button" aria-label="Следующие баннеры" :disabled="firstMobileIndex >= lastMobileIndex" @click="scrollMobile(1)">
                    <v-icon icon="mdi-chevron-right" size="18" aria-hidden="true" />
                </button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.home-banner-strip {
    min-width: 0;
    margin-bottom: 12px;
}

.home-banner-strip__desktop,
.home-banner-strip__track {
    display: grid;
    min-width: 0;
    gap: var(--strip-gap);
}

.home-banner-strip__desktop {
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.home-banner-strip__slot {
    min-width: 0;
    height: var(--strip-desktop-height);
}

.home-banner-strip__banner,
.home-banner-strip__empty {
    display: block;
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: 10px;
    background: var(--banner-background, #f5f4f2);
}

.home-banner-strip__banner {
    color: var(--banner-text);
    text-decoration: none;
}

.home-banner-strip__banner:hover {
    box-shadow: inset 0 0 0 1px var(--banner-accent);
}

.home-banner-strip__banner:focus-visible {
    outline: 2px solid #800000;
    outline-offset: -3px;
}

.home-banner-strip__empty {
    border: 1px solid #eeece8;
    background: #faf9f7;
}

.home-banner-strip__banner img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: var(--banner-image-fit);
    object-position: var(--banner-image-position);
}

.home-banner-strip__copy {
    display: flex;
    position: absolute;
    inset: 0;
    flex-direction: column;
    justify-content: var(--banner-vertical-align);
    gap: 4px;
    padding: 10px;
    text-align: var(--banner-text-align);
}

.home-banner-strip__banner--overlay .home-banner-strip__copy {
    color: #fff;
    background: linear-gradient(180deg, #00000018, #000000b3);
}

.home-banner-strip__title,
.home-banner-strip__subtitle {
    display: -webkit-box;
    overflow: hidden;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
}

.home-banner-strip__title {
    font-size: 0.86rem;
    font-weight: 650;
    line-height: 1.2;
    -webkit-line-clamp: 2;
}

.home-banner-strip__subtitle {
    font-size: 0.7rem;
    line-height: 1.3;
    -webkit-line-clamp: 1;
}

.home-banner-strip__mobile {
    display: none;
    min-width: 0;
}

.home-banner-strip__track {
    grid-template-columns: repeat(var(--strip-mobile-columns), minmax(0, 1fr));
}

.home-banner-strip__track--scroll {
    grid-template-columns: none;
    grid-auto-flow: column;
    grid-auto-columns: calc((100% - (var(--strip-mobile-columns) - 1) * var(--strip-gap)) / var(--strip-mobile-columns));
    overflow-x: auto;
    overscroll-behavior-x: contain;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
}

.home-banner-strip__mobile .home-banner-strip__slot {
    height: var(--strip-mobile-height);
    scroll-snap-align: start;
}

.home-banner-strip__controls {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    margin-top: 4px;
    color: #68615b;
    font-size: 0.68rem;
}

.home-banner-strip__controls button {
    display: inline-flex;
    width: 28px;
    height: 28px;
    align-items: center;
    justify-content: center;
    border: 1px solid #e8e5e2;
    border-radius: 8px;
    background: #fff;
}

.home-banner-strip__controls button:disabled {
    opacity: 0.35;
}

.home-banner-strip__controls button:focus-visible {
    outline: 2px solid #800000;
    outline-offset: 1px;
}

@media (min-width: 721px) {
    .home-banner-strip--desktop-empty {
        display: none;
    }
}

@media (max-width: 720px) {
    .home-banner-strip__desktop,
    .home-banner-strip--mobile-empty {
        display: none;
    }

    .home-banner-strip__mobile {
        display: block;
    }
}

.home-banner-strip--preview-desktop .home-banner-strip__desktop {
    display: grid;
}

.home-banner-strip--preview-desktop .home-banner-strip__mobile,
.home-banner-strip--preview-mobile .home-banner-strip__desktop {
    display: none;
}

.home-banner-strip--preview-mobile .home-banner-strip__mobile {
    display: block;
}
</style>
