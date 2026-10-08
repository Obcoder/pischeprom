<script setup>
import LayoutDefault from "@/Layouts/LayoutDefault.vue";
import { Head, Link } from "@inertiajs/vue3";
import { computed, h } from "vue";
import { route } from "ziggy-js";
import GoodStockAlertButton from "@/Components/Goods/GoodStockAlertButton.vue";
import { canSubscribeToGoodStock } from "@/Pages/Helpers/goodAvailability";
import ClassLanding from '@/Components/Products/ClassPage/ClassLanding.vue';
import { resolveClassGuide } from '@/Components/Products/ClassPage/guides.js';

defineOptions({
    layout: LayoutDefault,
});

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    goods: {
        type: Array,
        default: () => [],
    },
    seo: { type: Object, default: () => ({}) },
    classPage: { type: Object, default: null },
});

const productTitle = computed(() => {
    return props.product.rus || props.product.name || props.product.eng || `Product #${props.product.id}`;
});

const guide = computed(() => resolveClassGuide(props.classPage?.guide));
const pageSeo = computed(() => guide.value ? props.classPage.seo : props.seo);
const pageTitle = computed(() => pageSeo.value.title || `${productTitle.value} — ПИЩЕПРОМ-СЕРВЕР`);
const description = computed(() => pageSeo.value.description || `Товары по продукту: ${productTitle.value}`);
const JsonLdHead = () => h('script', { 'head-key': 'product-structured-data', type: 'application/ld+json' }, JSON.stringify(pageSeo.value.jsonLd).replace(/</g, '\\u003c'));

function goodImage(item) {
    const mediaImage = (item.published_media || []).find((media) => media.type === "image");

    return mediaImage?.thumb_url || mediaImage?.url || item.ava_thumb || item.ava_image || null;
}

function canSubscribeToStock(good) {
    return canSubscribeToGoodStock(good);
}
</script>

<template>
    <Head :title="pageTitle">
        <meta head-key="description" name="description" :content="description">
        <meta v-if="pageSeo.robots" head-key="robots" name="robots" :content="pageSeo.robots">
        <link v-if="pageSeo.canonical" head-key="canonical" rel="canonical" :href="pageSeo.canonical">
        <meta head-key="og:title" property="og:title" :content="pageTitle">
        <meta head-key="og:description" property="og:description" :content="description">
        <meta v-if="pageSeo.canonical" head-key="og:url" property="og:url" :content="pageSeo.canonical">
        <meta head-key="og:type" property="og:type" content="website">
        <JsonLdHead v-if="pageSeo.jsonLd" />
    </Head>
    <ClassLanding v-if="guide" :key="`${product.id}-${classPage.guide}`" :page="classPage" :guide="guide" />
    <v-container v-else class="py-8">
        <v-row>
            <v-col cols="12">
                <div class="product-page-kicker">
                    Product
                </div>

                <h1 class="text-h3 font-weight-bold mb-3">
                    {{ pageSeo.h1 || productTitle }}
                </h1>

                <div
                    v-if="product.category"
                    class="mb-6"
                >
                    <v-chip
                        color="teal"
                        variant="tonal"
                    >
                        {{ product.category.name }}
                    </v-chip>
                </div>
            </v-col>
        </v-row>

        <v-row v-if="goods.length">
            <v-col
                v-for="good in goods"
                :key="good.id"
                cols="12"
                sm="6"
                md="3"
            >
                <v-card
                    rounded="xl"
                    class="h-100 product-good-card"
                >
                    <Link
                        :href="route('public.goods.show', { good: good.slug })"
                        class="product-good-card__link text-decoration-none"
                    >
                        <v-img
                            v-if="goodImage(good)"
                            :src="goodImage(good)"
                            height="190"
                            cover
                        />

                        <div
                            v-else
                            class="product-good-empty"
                        >
                            <v-icon
                                icon="mdi-image-off"
                                size="42"
                            />
                        </div>

                        <v-card-text>
                            <div class="font-weight-bold text-body-1">
                                {{ good.name }}
                            </div>

                            <div
                                v-if="good.description"
                                class="text-caption text-medium-emphasis mt-1 product-good-description"
                            >
                                {{ good.description }}
                            </div>
                        </v-card-text>
                    </Link>

                    <v-card-actions
                        v-if="canSubscribeToStock(good)"
                        class="px-4 pb-4 pt-0"
                    >
                        <GoodStockAlertButton
                            :good="good"
                            label="Оповестить в MAX"
                            block
                            compact
                        />
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>

        <v-alert
            v-else
            type="info"
            variant="tonal"
        >
            По этому Product пока нет опубликованных товаров.
        </v-alert>
    </v-container>
</template>

<style scoped>
.product-page-kicker {
    display: inline-flex;
    align-items: center;
    margin-bottom: 12px;
    padding: 4px 12px;
    border-radius: 999px;
    color: #7a2500;
    background: #fff1df;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.product-good-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.product-good-card:hover {
    transform: translateY(-2px);
}

.product-good-card__link {
    display: block;
}

.product-good-empty {
    height: 190px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff7ed;
    color: rgba(var(--v-theme-on-surface), 0.5);
}

.product-good-description {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
