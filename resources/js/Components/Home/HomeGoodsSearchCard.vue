<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { logo } from "@/Pages/Helpers/consts.js";
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl';
import { useAppRoute } from "@/Composables/useAppRoute";

const { route } = useAppRoute();

const { goodPublicUrl } = usePublicGoodUrl()

const props = defineProps({
    goods: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    limit: {
        type: Number,
        default: 18,
    },
});

const search = ref("");

const filteredGoods = computed(() => {
    const query = search.value.trim().toLowerCase();

    let items = [...props.goods];

    if (query) {
        items = items.filter((good) => {
            const productText = (good.products || [])
                .map((product) => {
                    return [
                        product.rus,
                        product.eng,
                        product.name,
                        product.title,
                        product.category?.name,
                    ]
                        .filter(Boolean)
                        .join(" ");
                })
                .join(" ");

            const haystack = [
                good.name,
                good.description,
                good.slug,
                productText,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            return haystack.includes(query);
        });
    }

    return items.slice(0, props.limit);
});

const hasSearch = computed(() => {
    return search.value.trim().length > 0;
});

const newestGoods = computed(() => {
    return [...props.goods]
        .sort((a, b) => {
            const dateA = new Date(a.created_at || 0).getTime();
            const dateB = new Date(b.created_at || 0).getTime();

            return dateB - dateA;
        })
        .slice(0, props.limit);
});

const visibleGoods = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return newestGoods.value;
    }

    return props.goods
        .filter((good) => {
            const productText = (good.products || [])
                .map((product) => {
                    return [
                        product.rus,
                        product.eng,
                        product.name,
                        product.title,
                        product.category?.name,
                    ]
                        .filter(Boolean)
                        .join(" ");
                })
                .join(" ");

            const haystack = [
                good.name,
                good.description,
                good.slug,
                productText,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            return haystack.includes(query);
        })
        .slice(0, props.limit);
});

const groupedGoods = computed(() => {
    const groups = new Map();

    visibleGoods.value.forEach((good) => {
        const products = (good.products || []).length
            ? good.products
            : [null];

        products.forEach((product) => {
            const key = product?.id ? `product-${product.id}` : "no-product";

            if (!groups.has(key)) {
                groups.set(key, {
                    id: product?.id || null,
                    title: product?.rus || product?.name || product?.title || "Без Product",
                    category: product?.category?.name || null,
                    goods: [],
                });
            }

            groups.get(key).goods.push(good);
        });
    });

    return [...groups.values()];
});

function goodImage(good) {
    return good.ava_thumb || good.ava_image || logo;
}

function latestPrice(good) {
    return good.latest_price || good.latestPrice || good.prices?.[0] || null;
}

function formatMoney(value) {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    return new Intl.NumberFormat("ru-RU", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function priceText(good) {
    const price = latestPrice(good);

    if (!price) {
        return "Цена по запросу";
    }

    const currency = price.currency?.code || "RUB";

    return `${formatMoney(price.price)} ${currency}`;
}

function shortDescription(good) {
    const text = String(good.description || "").trim();

    if (!text) {
        return "Описание пока не заполнено";
    }

    return text.length > 96 ? `${text.slice(0, 96)}…` : text;
}
</script>

<template>
    <v-card
        rounded="xl"
        elevation="2"
        class="goods-search-card h-100"
    >
        <v-card-text class="pb-3">
            <v-text-field
                v-model="search"
                variant="outlined"
                density="comfortable"
                label="Поиск по товарам"
                placeholder="Например: лецитин, глицерин, какао-масло"
                hide-details
                clearable
                prepend-inner-icon="mdi-magnify"
            />
        </v-card-text>

        <v-divider />

        <v-card-title class="text-subtitle-1 font-weight-bold">
            {{ hasSearch ? "Найденные товары" : "Новинки каталога" }}
        </v-card-title>

        <v-card-subtitle>
            {{
                hasSearch
                    ? "Поиск работает по названию, описанию и связанным продуктам"
                    : "Последние добавленные опубликованные товары"
            }}
        </v-card-subtitle>

        <v-card-text class="goods-search-card__body">
            <v-progress-linear
                v-if="loading"
                indeterminate
                class="mb-3"
            />

            <template v-if="groupedGoods.length">
                <div
                    v-for="group in groupedGoods"
                    :key="group.id || 'no-product'"
                    class="product-group"
                >
                    <div class="product-group__header">
                        <div>
                            <div class="product-group__title">
                                {{ group.title }}
                            </div>

                            <div
                                v-if="group.category"
                                class="product-group__category"
                            >
                                {{ group.category }}
                            </div>
                        </div>

                        <v-chip
                            size="x-small"
                            color="#800000"
                            variant="tonal"
                        >
                            {{ group.goods.length }}
                        </v-chip>
                    </div>

                    <div class="goods-list">
                        <Link
                            v-for="good in group.goods"
                            :key="`${group.id || 'no-product'}-${good.id}`"
                            :href="goodPublicUrl(good)"
                            class="good-row"
                        >
                            <v-avatar
                                size="52"
                                rounded="lg"
                                class="good-row__image"
                            >
                                <v-img
                                    :src="goodImage(good)"
                                    cover
                                />
                            </v-avatar>

                            <div class="good-row__content">
                                <div class="good-row__name">
                                    {{ good.name }}
                                </div>

                                <div class="good-row__description">
                                    {{ shortDescription(good) }}
                                </div>
                            </div>

                            <div class="good-row__price">
                                {{ priceText(good) }}
                            </div>
                        </Link>
                    </div>
                </div>
            </template>

            <v-alert
                v-else
                type="info"
                variant="tonal"
            >
                {{
                    hasSearch
                        ? "Ничего не найдено."
                        : "Новинки каталога пока не найдены."
                }}
            </v-alert>
        </v-card-text>

        <v-card-actions class="px-4 pb-4">
            <Link
                :href="route('public.goods.index')"
                class="w-100"
            >
                <v-btn
                    block
                    color="#800000"
                    rounded="xl"
                >
                    Открыть весь каталог
                </v-btn>
            </Link>
        </v-card-actions>
    </v-card>
</template>

<style scoped>
.goods-search-card {
    overflow: hidden;
    background: #ffffff;
}

.goods-search-card__body {
    max-height: 520px;
    overflow-y: auto;
}

.product-group {
    margin-bottom: 18px;
}

.product-group:last-child {
    margin-bottom: 0;
}

.product-group__header {
    position: sticky;
    top: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 10px;
    margin-bottom: 8px;
    border-radius: 14px;
    background: #fff7ed;
    border: 1px solid rgba(128, 0, 0, 0.08);
}

.product-group__title {
    color: #5c0000;
    font-size: 0.9rem;
    font-weight: 800;
}

.product-group__category {
    margin-top: 2px;
    color: #8a3b00;
    font-size: 0.72rem;
}

.goods-list {
    display: grid;
    gap: 8px;
}

.good-row {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 8px;
    border-radius: 16px;
    text-decoration: none;
    color: inherit;
    background: #ffffff;
    border: 1px solid rgba(128, 0, 0, 0.08);
    transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.good-row:hover {
    background: #fff8f0;
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(92, 0, 0, 0.08);
}

.good-row__image {
    background: #fff1df;
}

.good-row__content {
    min-width: 0;
}

.good-row__name {
    color: #3f1d1d;
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.25;
}

.good-row__description {
    margin-top: 3px;
    color: #6b625d;
    font-size: 0.76rem;
    line-height: 1.3;
}

.good-row__price {
    white-space: nowrap;
    align-self: start;
    padding: 5px 8px;
    border-radius: 999px;
    color: #5c0000;
    background: #fff1df;
    font-size: 0.78rem;
    font-weight: 800;
}

@media (max-width: 600px) {
    .good-row {
        grid-template-columns: 48px minmax(0, 1fr);
    }

    .good-row__price {
        grid-column: 2;
        justify-self: start;
    }
}
</style>
