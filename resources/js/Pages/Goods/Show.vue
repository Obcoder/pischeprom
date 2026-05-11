<script setup>
import { computed, ref, watch } from "vue";
import { Link } from "@inertiajs/vue3";
import { useHead } from "@vueuse/head";
import { route } from "ziggy-js";

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
    relatedGoods: {
        type: Array,
        default: () => [],
    },
});

const activeMediaId = ref(null);

const seo = computed(() => {
    return props.good.seo || {};
});

const seoIsActive = computed(() => {
    return seo.value?.is_active !== false;
});

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
    return mediaItems.value.filter((item) => item.type === "video");
});

const activeMedia = computed(() => {
    if (!mediaItems.value.length) {
        return null;
    }

    if (!activeMediaId.value) {
        return mediaItems.value[0];
    }

    return mediaItems.value.find((item) => item.id === activeMediaId.value) || mediaItems.value[0];
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

const productNames = computed(() => {
    return (props.good.products || [])
        .map((product) => product.rus || product.name || product.title)
        .filter(Boolean);
});

const pageTitle = computed(() => {
    if (seoIsActive.value && seo.value.meta_title) {
        return seo.value.meta_title;
    }

    return `${props.good.name} — ПИЩЕПРОМ-СЕРВЕР`;
});

const pageDescription = computed(() => {
    if (seoIsActive.value && seo.value.meta_description) {
        return seo.value.meta_description;
    }

    return String(props.good.description || "").slice(0, 160);
});

const pageH1 = computed(() => {
    if (seoIsActive.value && seo.value.h1) {
        return seo.value.h1;
    }

    return props.good.name;
});

const ogTitle = computed(() => {
    return seoIsActive.value && seo.value.og_title
        ? seo.value.og_title
        : pageTitle.value;
});

const ogDescription = computed(() => {
    return seoIsActive.value && seo.value.og_description
        ? seo.value.og_description
        : pageDescription.value;
});

const ogImage = computed(() => {
    return seoIsActive.value && seo.value.og_image
        ? seo.value.og_image
        : fallbackImage.value;
});

const canonicalUrl = computed(() => {
    if (seoIsActive.value && seo.value.canonical_url) {
        return seo.value.canonical_url;
    }

    return typeof window !== "undefined" ? window.location.href : "";
});

const robots = computed(() => {
    return seoIsActive.value && seo.value.robots
        ? seo.value.robots
        : "index,follow";
});

const structuredData = computed(() => {
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

useHead({
    title: pageTitle,
    meta: [
        {
            name: "description",
            content: pageDescription,
        },
        {
            name: "robots",
            content: robots,
        },
        {
            property: "og:title",
            content: ogTitle,
        },
        {
            property: "og:description",
            content: ogDescription,
        },
        {
            property: "og:type",
            content: "product",
        },
        {
            property: "og:image",
            content: ogImage,
        },
        {
            property: "og:url",
            content: canonicalUrl,
        },
        {
            name: "twitter:card",
            content: "summary_large_image",
        },
        {
            name: "twitter:title",
            content: computed(() => seo.value.twitter_title || ogTitle.value),
        },
        {
            name: "twitter:description",
            content: computed(() => seo.value.twitter_description || ogDescription.value),
        },
        {
            name: "twitter:image",
            content: computed(() => seo.value.twitter_image || ogImage.value),
        },
    ],
    link: [
        {
            rel: "canonical",
            href: canonicalUrl,
        },
    ],
    script: [
        {
            type: "application/ld+json",
            children: computed(() => JSON.stringify(structuredData.value)),
        },
    ],
});

watch(
    mediaItems,
    (items) => {
        if (!activeMediaId.value && items.length) {
            activeMediaId.value = items[0].id;
        }
    },
    {
        immediate: true,
    }
);

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
</script>

<template>
    <v-container class="py-8">
        <v-row>
            <!-- GALLERY -->
            <v-col cols="12" md="5">
                <v-card
                    rounded="xl"
                    elevation="2"
                    class="overflow-hidden"
                >
                    <template v-if="activeMedia">
                        <v-img
                            v-if="activeMedia.type === 'image'"
                            :src="activeMedia.url"
                            :alt="activeMedia.alt || good.name"
                            height="420"
                            cover
                        />

                        <video
                            v-else-if="activeMedia.type === 'video' && mediaVideoUrl(activeMedia)"
                            class="product-video"
                            controls
                            playsinline
                            preload="metadata"
                            :poster="activeMedia.poster_url || undefined"
                        >
                            <source
                                :src="mediaVideoUrl(activeMedia)"
                                type="video/mp4"
                            >

                            Ваш браузер не поддерживает video.
                        </video>
                    </template>

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
                    v-if="mediaItems.length"
                    class="mt-3"
                    dense
                >
                    <v-col
                        v-for="item in mediaItems"
                        :key="item.id"
                        cols="3"
                    >
                        <v-card
                            class="media-thumb"
                            :class="{ 'media-thumb--active': activeMedia?.id === item.id }"
                            rounded="lg"
                            variant="tonal"
                            @click="activeMediaId = item.id"
                        >
                            <v-img
                                v-if="mediaPreview(item)"
                                :src="mediaPreview(item)"
                                height="84"
                                cover
                            />

                            <div
                                v-else
                                class="media-thumb-empty"
                            >
                                <v-icon
                                    :icon="item.type === 'video' ? 'mdi-video' : 'mdi-file'"
                                    size="28"
                                />
                            </div>

                            <v-chip
                                v-if="item.type === 'video'"
                                size="x-small"
                                color="deep-purple"
                                variant="flat"
                                class="media-thumb-chip"
                            >
                                video
                            </v-chip>
                        </v-card>
                    </v-col>
                </v-row>
            </v-col>

            <!-- INFO -->
            <v-col cols="12" md="7">
                <div class="mb-4 text-overline text-medium-emphasis">
                    Товар
                </div>

                <h1 class="text-h3 font-weight-bold mb-4">
                    {{ pageH1 }}
                </h1>

                <div
                    v-if="productNames.length"
                    class="mb-4"
                >
                    <v-chip
                        v-for="name in productNames"
                        :key="name"
                        size="small"
                        class="mr-2 mb-2"
                        color="teal"
                        variant="tonal"
                    >
                        {{ name }}
                    </v-chip>
                </div>

                <v-card
                    v-if="publicPrices.length"
                    variant="tonal"
                    class="mb-5"
                >
                    <v-card-title>
                        Цены
                    </v-card-title>

                    <v-card-text>
                        <v-row dense>
                            <v-col
                                v-for="price in publicPrices"
                                :key="price.id"
                                cols="12"
                                sm="6"
                            >
                                <v-card
                                    variant="flat"
                                    class="pa-3 price-card"
                                >
                                    <div class="text-caption text-medium-emphasis">
                                        {{ price.price_type?.name || "Цена" }}
                                    </div>

                                    <div class="text-h6 font-weight-bold">
                                        {{ formatMoney(price.price_gross) }}
                                        {{ price.currency?.code || price.price_type?.currency?.code || "RUB" }}
                                    </div>

                                    <div class="text-caption text-medium-emphasis">
                                        с НДС / кг
                                    </div>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>

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

        <!-- VIDEO BLOCK -->
        <v-row
            v-if="videoItems.length"
            class="mt-8"
        >
            <v-col cols="12">
                <h2 class="text-h5 font-weight-bold mb-4">
                    Видео товара
                </h2>
            </v-col>

            <v-col
                v-for="video in videoItems"
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
                    :href="route('goods.show', item.slug)"
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
</style>
