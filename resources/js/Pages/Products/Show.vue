<script setup>
import LayoutDefault from "@/Layouts/LayoutDefault.vue";
import { Link } from "@inertiajs/vue3";
import { useHead } from "@vueuse/head";
import { computed } from "vue";
import { route } from "ziggy-js";

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
});

const productTitle = computed(() => {
    return props.product.rus || props.product.name || props.product.eng || `Product #${props.product.id}`;
});

useHead({
    title: computed(() => `${productTitle.value} — ПИЩЕПРОМ-СЕРВЕР`),
    meta: [
        {
            name: "description",
            content: computed(() => `Товары по продукту: ${productTitle.value}`),
        },
    ],
});

function goodImage(item) {
    const mediaImage = (item.published_media || []).find((media) => media.type === "image");

    return mediaImage?.thumb_url || mediaImage?.url || item.ava_thumb || item.ava_image || null;
}
</script>

<template>
    <v-container class="py-8">
        <v-row>
            <v-col cols="12">
                <div class="product-page-kicker">
                    Product
                </div>

                <h1 class="text-h3 font-weight-bold mb-3">
                    {{ productTitle }}
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
                <Link
                    :href="route('goods.show', good.slug)"
                    class="text-decoration-none"
                >
                    <v-card
                        rounded="xl"
                        class="h-100 product-good-card"
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
                    </v-card>
                </Link>
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
