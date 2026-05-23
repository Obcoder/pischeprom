<script setup>
import { computed, ref, watch, onMounted } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { route as ziggyRoute } from "ziggy-js";

import LayoutDefault from "@/Layouts/LayoutDefault.vue";
import { useYandexMetrica } from "@/Composables/useYandexMetrica";
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl';

const { goodPublicUrl } = usePublicGoodUrl()

defineOptions({
    layout: LayoutDefault,
});

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
    relatedGoods: {
        type: Array,
        default: () => [],
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
});

const route = (name, params = {}, absolute = true) => {
    return ziggyRoute(name, params, absolute, page.props.ziggy);
};

const metricaCounterId = import.meta.env.VITE_YANDEX_METRICA_COUNTER_ID;

const {
    reachGoal,
    ecommerceViewItem,
} = useYandexMetrica(metricaCounterId);

const activeImageId = ref(null);

const mediaItems = computed(() => {
    return (props.good.published_media || [])
        .filter((item) => item.is_published)
        .sort((a, b) => {
            if (Number(b.is_ava) !== Number(a.is_ava)) {
                return Number(b.is_ava) - Number(a.is_ava);
            }

            const orderA = Number(a.sort_order || 100);
            const orderB = Number(b.sort_order || 100);

            if (orderA !== orderB) {
                return orderA - orderB;
            }

            return Number(a.id) - Number(b.id);
        });
});

const imageItems = computed(() => {
    return mediaItems.value.filter((item) => item.type === "image");
});

const videoItems = computed(() => {
    return mediaItems.value.filter((item) => {
        return item.type === "video" && mediaVideoUrl(item);
    });
});

const mainVideo = computed(() => {
    return videoItems.value.find((item) => {
            return item.is_main_video && item.processing_status === "done";
        }) ||
        videoItems.value.find((item) => item.processing_status === "done") ||
        videoItems.value[0] ||
        null;
});

const otherVideoItems = computed(() => {
    if (!mainVideo.value) {
        return videoItems.value;
    }

    return videoItems.value.filter((item) => item.id !== mainVideo.value.id);
});

const activeImage = computed(() => {
    if (!imageItems.value.length) {
        return null;
    }

    if (!activeImageId.value) {
        return imageItems.value[0];
    }

    return imageItems.value.find((item) => item.id === activeImageId.value) || imageItems.value[0];
});

const fallbackImage = computed(() => {
    return props.good.ava_image || props.good.ava_thumb || null;
});

const publicPrices = computed(() => {
    return (props.good.price_type_values || [])
        .filter((item) => item.is_published)
        .sort((a, b) => {
            const orderA = Number(a.price_type?.sort_order || 100);
            const orderB = Number(b.price_type?.sort_order || 100);

            if (orderA !== orderB) {
                return orderA - orderB;
            }

            return String(a.price_type?.name || "").localeCompare(String(b.price_type?.name || ""));
        });
});

const productItems = computed(() => {
    return (props.good.products || [])
        .map((product) => ({
            id: product.id,
            title: product.rus || product.name || product.title || product.eng || `Product #${product.id}`,
        }))
        .filter((product) => product.id && product.title);
});

/*
|--------------------------------------------------------------------------
| SEO / Head / JSON-LD
|--------------------------------------------------------------------------
*/

const pageSeo = computed(() => {
    return props.seo || {};
});

const seo = computed(() => {
    return props.good.seo || {};
});

const seoIsActive = computed(() => {
    return seo.value?.is_active !== false;
});

const pageTitle = computed(() => {
    return pageSeo.value.title
        || (seoIsActive.value && seo.value.meta_title)
        || `${props.good.name} — ПИЩЕПРОМ-СЕРВЕР`;
});

const pageDescription = computed(() => {
    return pageSeo.value.description
        || (seoIsActive.value && seo.value.meta_description)
        || String(props.good.description || "").slice(0, 160);
});

const pageH1 = computed(() => {
    return pageSeo.value.h1
        || (seoIsActive.value && seo.value.h1)
        || props.good.name;
});

const ogTitle = computed(() => {
    return pageSeo.value.title
        || (seoIsActive.value && seo.value.og_title)
        || pageTitle.value;
});

const ogDescription = computed(() => {
    return pageSeo.value.description
        || (seoIsActive.value && seo.value.og_description)
        || pageDescription.value;
});

const ogImage = computed(() => {
    return pageSeo.value.image
        || (seoIsActive.value && seo.value.og_image)
        || fallbackImage.value
        || "";
});

const canonicalUrl = computed(() => {
    return pageSeo.value.canonical
        || (seoIsActive.value && seo.value.canonical_url)
        || "";
});

const robots = computed(() => {
    return pageSeo.value.robots
        || (seoIsActive.value && seo.value.robots)
        || "index,follow";
});

const structuredData = computed(() => {
    if (pageSeo.value.jsonLd) {
        return pageSeo.value.jsonLd;
    }

    if (seoIsActive.value && seo.value.structured_data) {
        return seo.value.structured_data;
    }

    return {
        "@context": "https://schema.org",
        "@type": "Product",
        name: props.good.name,
        description: pageDescription.value,
        image: imageItems.value.map((item) => item.url).filter(Boolean),
        brand: {
            "@type": "Brand",
            name: "ПИЩЕПРОМ-СЕРВЕР",
        },
    };
});

const structuredDataForHead = computed(() => {
    const data = structuredData.value;

    if (!data) {
        return [];
    }

    if (Array.isArray(data)) {
        return data;
    }

    return [data];
});

/*
|--------------------------------------------------------------------------
| Watchers
|--------------------------------------------------------------------------
*/

watch(
    imageItems,
    (items) => {
        if (!items.length) {
            activeImageId.value = null;
            return;
        }

        const exists = items.some((item) => item.id === activeImageId.value);

        if (!activeImageId.value || !exists) {
            activeImageId.value = items[0].id;
        }
    },
    {
        immediate: true,
    }
);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatMoney(value) {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    return new Intl.NumberFormat("ru-RU", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function mediaPreview(item) {
    if (!item) {
        return null;
    }

    if (item.type === "image") {
        return item.thumb_url || item.url;
    }

    if (item.type === "video") {
        return item.poster_url || item.thumb_url || null;
    }

    return null;
}

function mediaVideoUrl(item) {
    return item?.video_mp4_url || item?.url || null;
}

function relatedImage(item) {
    const mediaImage = (item.published_media || []).find((media) => media.type === "image");

    return mediaImage?.thumb_url || mediaImage?.url || item.ava_thumb || item.ava_image || null;
}

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

function requestPrice() {
    reachGoal("request_price_click", {
        good_id: props.good.id,
        good_name: props.good.name,
    });

    if (typeof window === "undefined") {
        return;
    }

    window.location.href = `mailto:office@180022.ru?subject=${encodeURIComponent("Запрос цены: " + props.good.name)}`;
}

function clickPhone() {
    reachGoal("phone_click", {
        good_id: props.good.id,
        good_name: props.good.name,
    });
}

function clickEmail() {
    reachGoal("email_click", {
        good_id: props.good.id,
        good_name: props.good.name,
    });
}

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    reachGoal("view_good", {
        good_id: props.good.id,
        good_name: props.good.name,
    });

    ecommerceViewItem({
        id: String(props.good.id),
        name: props.good.name,
        category: productItems.value[0]?.title || "Товар",
        price: publicPrices.value[0]?.price_gross || 0,
        currency:
            publicPrices.value[0]?.currency?.code ||
            publicPrices.value[0]?.price_type?.currency?.code ||
            "RUB",
    });
});
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>

        <meta
            head-key="description"
            name="description"
            :content="pageDescription"
        >

        <meta
            head-key="robots"
            name="robots"
            :content="robots"
        >

        <link
            v-if="canonicalUrl"
            head-key="canonical"
            rel="canonical"
            :href="canonicalUrl"
        >

        <meta
            head-key="og:title"
            property="og:title"
            :content="ogTitle"
        >

        <meta
            head-key="og:description"
            property="og:description"
            :content="ogDescription"
        >

        <meta
            head-key="og:type"
            property="og:type"
            content="product"
        >

        <meta
            v-if="canonicalUrl"
            head-key="og:url"
            property="og:url"
            :content="canonicalUrl"
        >

        <meta
            v-if="ogImage"
            head-key="og:image"
            property="og:image"
            :content="ogImage"
        >

        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        >

        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="ogTitle"
        >

        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="ogDescription"
        >

        <meta
            v-if="ogImage"
            head-key="twitter:image"
            name="twitter:image"
            :content="ogImage"
        >
    </Head>

    <v-container class="py-8">
        <v-row>
            <!-- MEDIA / PHOTO -->
            <v-col cols="12" md="5">
                <!-- MAIN VIDEO -->
                <v-card
                    v-if="mainVideo"
                    rounded="xl"
                    elevation="2"
                    class="overflow-hidden mb-4 hero-video-card"
                >
                    <video
                        class="product-hero-video"
                        autoplay
                        muted
                        loop
                        playsinline
                        preload="metadata"
                        disablepictureinpicture
                        controlslist="nodownload noplaybackrate nofullscreen"
                        :poster="mainVideo.poster_url || undefined"
                        :aria-label="mainVideo.title || good.name"
                    >
                        <source
                            :src="mediaVideoUrl(mainVideo)"
                            type="video/mp4"
                        >

                        Ваш браузер не поддерживает video.
                    </video>

                    <div class="video-overlay">
                        <v-chip
                            size="small"
                            color="black"
                            variant="flat"
                            class="text-white"
                        >
                            <v-icon
                                icon="mdi-play-circle"
                                size="16"
                                class="mr-1"
                            />

                            Видео товара
                        </v-chip>
                    </div>
                </v-card>

                <!-- PHOTO GALLERY -->
                <v-card
                    rounded="xl"
                    elevation="2"
                    class="overflow-hidden"
                >
                    <v-img
                        v-if="activeImage"
                        :src="activeImage.url"
                        :alt="activeImage.alt || good.name"
                        height="420"
                        cover
                    />

                    <v-img
                        v-else-if="fallbackImage"
                        :src="fallbackImage"
                        :alt="good.name"
                        height="420"
                        cover
                    />

                    <div
                        v-else
                        class="empty-preview"
                    >
                        <v-icon
                            icon="mdi-image-off"
                            size="64"
                            class="mb-3"
                        />

                        <div>Изображение пока не добавлено</div>
                    </div>
                </v-card>

                <v-row
                    v-if="imageItems.length"
                    class="mt-3"
                    dense
                >
                    <v-col
                        v-for="item in imageItems"
                        :key="item.id"
                        cols="3"
                    >
                        <v-card
                            class="media-thumb"
                            :class="{ 'media-thumb--active': activeImage?.id === item.id }"
                            rounded="lg"
                            variant="tonal"
                            @click="activeImageId = item.id"
                        >
                            <v-img
                                :src="mediaPreview(item)"
                                height="84"
                                cover
                            />
                        </v-card>
                    </v-col>
                </v-row>
            </v-col>

            <!-- INFO -->
            <v-col cols="12" md="7">
                <div
                    v-if="productItems.length"
                    class="mb-4 product-links"
                >
                    <Link
                        v-for="product in productItems"
                        :key="product.id"
                        :href="route('shop.products.show', { product: product.id })"
                        class="text-decoration-none d-inline-block mr-2 mb-2"
                    >
                        <v-chip
                            size="small"
                            class="product-link-chip"
                        >
                            <v-icon
                                icon="mdi-leaf"
                                size="15"
                                class="mr-1"
                            />

                            {{ product.title }}
                        </v-chip>
                    </Link>
                </div>

                <h1 class="text-h3 font-weight-bold mb-4">
                    {{ pageH1 }}
                </h1>

                <section
                    v-if="publicPrices.length"
                    class="price-panel mb-5"
                >
                    <div class="price-panel__header">
                        <div>
                            <div class="price-panel__eyebrow">
                                Актуальные цены
                            </div>

                            <h2 class="price-panel__title">
                                Цены
                            </h2>
                        </div>

                        <v-icon
                            icon="mdi-cash-multiple"
                            size="34"
                            class="price-panel__icon"
                        />
                    </div>

                    <v-row dense>
                        <v-col
                            v-for="price in publicPrices"
                            :key="price.id"
                            cols="12"
                            sm="6"
                        >
                            <div class="price-tile">
                                <div class="price-tile__name">
                                    {{ price.price_type?.name || "Цена" }}
                                </div>

                                <div class="price-tile__value">
                                    {{ formatMoney(price.price_gross) }}
                                    <span>
                                        {{ price.currency?.code || price.price_type?.currency?.code || "RUB" }}
                                    </span>
                                </div>

                                <div class="price-tile__note">
                                    с НДС / кг
                                </div>
                            </div>
                        </v-col>
                    </v-row>
                </section>

                <div
                    v-if="good.description"
                    class="text-body-1 mb-6"
                    style="line-height: 1.8;"
                >
                    {{ good.description }}
                </div>

                <div
                    v-else
                    class="text-body-1 text-medium-emphasis mb-6"
                >
                    Описание пока не заполнено.
                </div>

                <div
                    v-if="seoIsActive && seo.short_seo_text"
                    class="text-body-2 text-medium-emphasis mb-4"
                    style="line-height: 1.7;"
                >
                    {{ seo.short_seo_text }}
                </div>

                <div class="d-flex flex-wrap ga-3 mb-4">
                    <v-btn
                        color="deep-purple-darken-1"
                        size="large"
                        rounded="xl"
                        @click="requestPrice"
                    >
                        Запросить цену
                    </v-btn>

                    <v-btn
                        variant="tonal"
                        size="large"
                        rounded="xl"
                        href="tel:+79650160001"
                        @click="clickPhone"
                    >
                        Позвонить
                    </v-btn>

                    <v-btn
                        variant="tonal"
                        size="large"
                        rounded="xl"
                        href="mailto:office@180022.ru"
                        @click="clickEmail"
                    >
                        Написать на email
                    </v-btn>
                </div>

                <v-alert
                    type="info"
                    variant="tonal"
                    class="mb-4"
                >
                    Для уточнения цены и условий поставки свяжитесь с нами:
                    <strong>+7-965-016-0001</strong>,
                    <strong>office@180022.ru</strong>
                </v-alert>
            </v-col>
        </v-row>

        <!-- OTHER VIDEOS -->
        <v-row
            v-if="otherVideoItems.length"
            class="mt-8"
        >
            <v-col cols="12">
                <h2 class="text-h5 font-weight-bold mb-4">
                    Другие видео товара
                </h2>
            </v-col>

            <v-col
                v-for="video in otherVideoItems"
                :key="video.id"
                cols="12"
                md="6"
            >
                <v-card rounded="xl">
                    <video
                        class="product-video product-video--small"
                        controls
                        playsinline
                        preload="metadata"
                        :poster="video.poster_url || undefined"
                    >
                        <source
                            :src="mediaVideoUrl(video)"
                            type="video/mp4"
                        >

                        Ваш браузер не поддерживает video.
                    </video>

                    <v-card-text v-if="video.title || video.caption">
                        <div
                            v-if="video.title"
                            class="font-weight-bold mb-1"
                        >
                            {{ video.title }}
                        </div>

                        <div
                            v-if="video.caption"
                            class="text-body-2 text-medium-emphasis"
                        >
                            {{ video.caption }}
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- SEO TEXT -->
        <v-row
            v-if="seoIsActive && seo.seo_text"
            class="mt-8"
        >
            <v-col cols="12">
                <v-card
                    rounded="xl"
                    variant="tonal"
                >
                    <v-card-title>
                        Подробнее о товаре
                    </v-card-title>

                    <v-card-text
                        class="text-body-1"
                        style="line-height: 1.8;"
                    >
                        {{ seo.seo_text }}
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <!-- RELATED GOODS -->
        <v-row
            v-if="relatedGoods.length"
            class="mt-8"
        >
            <v-col cols="12">
                <h2 class="text-h5 font-weight-bold mb-4">
                    Другие товары
                </h2>
            </v-col>

            <v-col
                v-for="item in relatedGoods"
                :key="item.id"
                cols="12"
                sm="6"
                md="3"
            >
                <Link
                    v-if="goodPublicUrl(item)"
                    :href="goodPublicUrl(item)"
                    class="text-decoration-none"
                >
                    <v-card
                        rounded="xl"
                        class="h-100 related-card"
                    >
                        <v-img
                            v-if="relatedImage(item)"
                            :src="relatedImage(item)"
                            height="180"
                            cover
                        />

                        <div
                            v-else
                            class="related-empty"
                        >
                            <v-icon
                                icon="mdi-image-off"
                                size="42"
                            />
                        </div>

                        <v-card-text>
                            <div class="font-weight-bold text-body-1">
                                {{ item.name }}
                            </div>

                            <div
                                v-if="item.description"
                                class="text-caption text-medium-emphasis mt-1 related-description"
                            >
                                {{ item.description }}
                            </div>
                        </v-card-text>
                    </v-card>
                </Link>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.product-video {
    width: 100%;
    height: 420px;
    display: block;
    background: #000;
    object-fit: contain;
}

.product-video--small {
    height: 320px;
}

.empty-preview {
    height: 420px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(var(--v-theme-on-surface), 0.55);
    background: rgba(var(--v-theme-surface-variant), 0.45);
}

.media-thumb {
    position: relative;
    cursor: pointer;
    overflow: hidden;
    border: 2px solid transparent;
}

.media-thumb--active {
    border-color: rgb(var(--v-theme-primary));
}

.media-thumb-empty {
    height: 84px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.media-thumb-chip {
    position: absolute;
    left: 6px;
    top: 6px;
}

.price-card {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.related-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.related-card:hover {
    transform: translateY(-2px);
}

.related-empty {
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--v-theme-surface-variant), 0.45);
    color: rgba(var(--v-theme-on-surface), 0.55);
}

.related-description {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hero-video-card {
    position: relative;
    background: #000;
}

.product-hero-video {
    width: 100%;
    height: 420px;
    display: block;
    object-fit: cover;
    background: #000;
}

.video-overlay {
    position: absolute;
    left: 16px;
    bottom: 16px;
    z-index: 2;
    pointer-events: none;
}

.product-link-chip {
    color: #5c0000;
    background: #fff1df;
    border: 1px solid rgba(128, 0, 0, 0.18);
    font-weight: 700;
}

.product-link-chip:hover {
    background: #ffe5c2;
}

.price-panel {
    border: 1px solid rgba(128, 0, 0, 0.16);
    border-radius: 24px;
    padding: 20px;
    background: linear-gradient(135deg, #ffffff 0%, #fff8f0 52%, #fff0dc 100%);
    box-shadow: 0 12px 32px rgba(92, 0, 0, 0.08);
}

.price-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.price-panel__eyebrow {
    color: #8a3b00;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.price-panel__title {
    margin: 0;
    color: #5c0000;
    font-size: 1.35rem;
    font-weight: 800;
}

.price-panel__icon {
    color: #800000;
    opacity: 0.85;
}

.price-tile {
    height: 100%;
    padding: 16px;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 18px;
    background: #ffffff;
}

.price-tile__name {
    margin-bottom: 6px;
    color: #7a2500;
    font-size: 0.82rem;
    font-weight: 700;
}

.price-tile__value {
    color: #5c0000;
    font-size: 1.35rem;
    font-weight: 900;
    line-height: 1.2;
}

.price-tile__value span {
    font-size: 0.9rem;
    font-weight: 700;
}

.price-tile__note {
    margin-top: 4px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.78rem;
}
</style>
