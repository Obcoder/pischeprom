<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from '@/Composables/useAppRoute'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import SectionHeader from './SectionHeader.vue'

const props = defineProps({
    banners: {
        type: Array,
        default: () => [],
    },
})

const { route } = useAppRoute()
const { goodPublicUrl } = usePublicGoodUrl()

const visibleBanners = computed(() => props.banners.filter((banner) => {
    return banner.show_on_desktop || banner.show_on_mobile
}))

function bannerClass(banner) {
    return [
        'home-banner-card',
        `home-banner-card--${banner.size || 'standard'}`,
        !banner.show_on_desktop ? 'home-banner-card--desktop-hidden' : '',
        !banner.show_on_mobile ? 'home-banner-card--mobile-hidden' : '',
    ]
}

function bannerStyle(banner) {
    return {
        '--banner-bg': banner.background_color || '#fff4df',
        '--banner-text': banner.text_color || '#2c2118',
        '--banner-accent': banner.accent_color || '#f2aa00',
    }
}

function bannerHref(banner) {
    if (banner.cta_url) {
        return banner.cta_url
    }

    if (banner.good) {
        return goodPublicUrl(banner.good, false)
    }

    if (banner.category?.slug || banner.category?.id) {
        return route('category.show', banner.category.slug || banner.category.id)
    }

    if (banner.product?.id) {
        return route('shop.products.show', banner.product.id)
    }

    return route('public.goods.index')
}

function relationLabel(banner) {
    if (banner.good) {
        return banner.good.name
    }

    if (banner.product) {
        return banner.product.rus || banner.product.eng
    }

    if (banner.category) {
        return banner.category.name
    }

    return 'Каталог'
}
</script>

<template>
    <section
        v-if="visibleBanners.length"
        class="home-banners-section"
    >
        <v-container>
            <SectionHeader
                title="Акции и предложения"
                subtitle="Промо-баннеры для быстрых переходов к товарам, категориям и сезонным предложениям"
            />

            <div class="home-banners-grid">
                <Link
                    v-for="banner in visibleBanners"
                    :key="banner.id"
                    :href="bannerHref(banner)"
                    :class="bannerClass(banner)"
                    :style="bannerStyle(banner)"
                >
                    <div class="home-banner-card__copy">
                        <span class="home-banner-card__eyebrow">
                            {{ banner.eyebrow || relationLabel(banner) }}
                        </span>

                        <h3>{{ banner.title }}</h3>

                        <p v-if="banner.subtitle || banner.description">
                            {{ banner.subtitle || banner.description }}
                        </p>

                        <span class="home-banner-card__cta">
                            {{ banner.cta_label || 'Открыть предложение' }}
                            <v-icon icon="mdi-arrow-right" size="18" />
                        </span>
                    </div>

                    <div class="home-banner-card__image">
                        <picture v-if="banner.image_url || banner.mobile_image_url">
                            <source
                                v-if="banner.mobile_image_url"
                                media="(max-width: 720px)"
                                :srcset="banner.mobile_image_url"
                            >
                            <img
                                :src="banner.image_url || banner.mobile_image_url"
                                :alt="banner.title"
                                loading="lazy"
                            >
                        </picture>
                        <div v-else class="home-banner-card__placeholder">
                            {{ banner.title?.slice(0, 1) }}
                        </div>
                    </div>
                </Link>
            </div>
        </v-container>
    </section>
</template>

<style scoped>
.home-banners-section {
    padding: 42px 0 50px;
    background:
        radial-gradient(circle at 92% 0%, rgba(242, 170, 0, 0.20), transparent 28%),
        linear-gradient(180deg, #fffaf4 0%, #ffffff 100%);
}

.home-banners-grid {
    display: grid;
    grid-auto-flow: dense;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 16px;
}

.home-banner-card {
    --banner-bg: #fff4df;
    --banner-text: #2c2118;
    --banner-accent: #f2aa00;
    position: relative;
    display: grid;
    min-height: 292px;
    overflow: hidden;
    grid-column: span 6;
    grid-template-columns: minmax(0, 0.9fr) minmax(220px, 1.1fr);
    border: 1px solid rgba(90, 56, 24, 0.10);
    border-radius: 32px;
    background:
        radial-gradient(circle at 0% 100%, color-mix(in srgb, var(--banner-accent), transparent 72%), transparent 38%),
        var(--banner-bg);
    box-shadow: 0 22px 58px rgba(61, 42, 22, 0.12);
    color: var(--banner-text);
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.home-banner-card--wide {
    grid-column: span 8;
}

.home-banner-card--compact {
    grid-column: span 4;
    grid-template-columns: 1fr;
}

.home-banner-card:hover {
    border-color: color-mix(in srgb, var(--banner-accent), #000 18%);
    box-shadow: 0 30px 76px rgba(61, 42, 22, 0.18);
    transform: translateY(-5px);
}

.home-banner-card__copy {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 28px;
}

.home-banner-card__eyebrow {
    align-self: flex-start;
    padding: 7px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.72);
    color: color-mix(in srgb, var(--banner-accent), #5b1c00 42%);
    font-size: 0.72rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.home-banner-card h3 {
    margin: 16px 0 0;
    font-size: clamp(1.55rem, 3.1vw, 3.2rem);
    font-weight: 950;
    letter-spacing: -0.05em;
    line-height: 0.98;
}

.home-banner-card p {
    max-width: 520px;
    margin: 14px 0 0;
    color: color-mix(in srgb, var(--banner-text), #fff 28%);
    font-size: 0.98rem;
    line-height: 1.48;
}

.home-banner-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    align-self: flex-start;
    margin-top: 24px;
    padding: 11px 14px;
    border-radius: 999px;
    background: var(--banner-accent);
    color: #2b2118;
    font-size: 0.82rem;
    font-weight: 950;
}

.home-banner-card__image {
    position: relative;
    min-height: 100%;
    overflow: hidden;
}

.home-banner-card__image picture,
.home-banner-card__image img {
    display: block;
    width: 100%;
    height: 100%;
}

.home-banner-card__image img {
    object-fit: cover;
    transition: transform 0.35s ease;
}

.home-banner-card:hover .home-banner-card__image img {
    transform: scale(1.04);
}

.home-banner-card__placeholder {
    display: grid;
    width: 100%;
    height: 100%;
    min-height: 220px;
    place-items: center;
    background: color-mix(in srgb, var(--banner-accent), #fff 72%);
    color: var(--banner-text);
    font-size: 5rem;
    font-weight: 950;
}

@media (min-width: 721px) {
    .home-banner-card--desktop-hidden {
        display: none;
    }
}

@media (max-width: 1180px) {
    .home-banner-card,
    .home-banner-card--wide,
    .home-banner-card--compact {
        grid-column: span 12;
    }
}

@media (max-width: 720px) {
    .home-banners-section {
        padding: 32px 0 38px;
    }

    .home-banner-card--mobile-hidden {
        display: none;
    }

    .home-banner-card,
    .home-banner-card--wide,
    .home-banner-card--compact {
        min-height: 0;
        grid-template-columns: 1fr;
        border-radius: 26px;
    }

    .home-banner-card__copy {
        padding: 22px;
    }

    .home-banner-card__image {
        min-height: 190px;
        order: -1;
    }
}
</style>
