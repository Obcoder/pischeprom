<script setup>
import { measurementForGood, unitLabel } from '@/utils/goodMeasurement'
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { logo } from "@/Pages/Helpers/consts.js";
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl';
import { useAppRoute } from "@/Composables/useAppRoute";
import { usePhoneCallRegistration } from "@/Composables/usePhoneCallRegistration";
import CatalogNodeAvatar from '@/Components/Catalog/CatalogNodeAvatar.vue';

const { route } = useAppRoute();

const { goodPublicUrl } = usePublicGoodUrl()
const { registerPhoneCallClick } = usePhoneCallRegistration()

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
const searchQuery = computed(() => String(search.value ?? "").trim());
const hasSearch = computed(() => searchQuery.value.length > 0);

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
    const query = searchQuery.value.toLowerCase();

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
                    node: product,
                    categoryNode: product?.category,
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
    const values = good.price_type_values || good.priceTypeValues || [];

    return values.find((item) => item.is_published) || null;
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

    const value = price.price_gross ?? price.price_net ?? price.price;
    const currency = price.currency?.code || price.price_type?.currency?.code || price.priceType?.currency?.code || "RUB";

    return `${formatMoney(value)} ${currency} / ${unitLabel(measurementForGood(good))}`;
}

function shortDescription(good) {
    const text = String(good.description || "").trim();

    if (!text) {
        return "Описание пока не заполнено";
    }

    return text.length > 96 ? `${text.slice(0, 96)}…` : text;
}

function clickHomePhone() {
    registerPhoneCallClick({
        source: "home_goods_search_card",
        search: searchQuery.value || null,
        good_id: visibleGoods.value[0]?.id || null,
        good_name: visibleGoods.value[0]?.name || null,
    });
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

        <v-card-subtitle class="goods-search-card__subtitle">
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
                        <div class="product-group__labels">
                            <div class="product-group__title">
                                <CatalogNodeAvatar v-if="group.node" :node="group.node" :size="26" />
                                <span>{{ group.title }}</span>
                            </div>

                            <div
                                v-if="group.category"
                                class="product-group__category"
                            >
                                <CatalogNodeAvatar v-if="group.categoryNode" :node="group.categoryNode" :size="20" />
                                <span>{{ group.category }}</span>
                            </div>
                        </div>

                        <v-chip
                            class="product-group__count"
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
                                    :alt="good.name"
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

        <v-card-actions class="goods-search-card__actions px-4 pb-4">
            <Link
                :href="route('public.goods.index')"
                class="goods-search-card__action-link"
            >
                <v-btn
                    block
                    color="#800000"
                    rounded="xl"
                >
                    Открыть весь каталог
                </v-btn>
            </Link>

            <v-btn
                class="goods-search-card__phone"
                href="tel:+79650160001"
                variant="tonal"
                color="#800000"
                rounded="xl"
                prepend-icon="mdi-phone"
                @click="clickHomePhone"
            >
                Позвонить
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<style scoped>
.goods-search-card {
    container: home-goods-search / inline-size;
    min-width: 0;
    overflow: hidden;
    background: #ffffff;
}

.goods-search-card__subtitle {
    white-space: normal;
    line-height: 1.45;
}

.goods-search-card__body {
    max-height: 520px;
    overflow-y: auto;
}

.goods-search-card__actions {
    align-items: stretch;
    flex-wrap: wrap;
    gap: 10px;
}

.goods-search-card__action-link {
    flex: 1 1 180px;
    min-width: 0;
}

.goods-search-card__phone {
    flex: 1 1 120px;
    margin-inline-start: 0 !important;
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

.product-group__labels {
    min-width: 0;
}

.product-group__title,
.product-group__category {
    display: flex;
    align-items: center;
    gap: 7px;
    min-width: 0;
}

.product-group__title span,
.product-group__category span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.product-group__count {
    flex-shrink: 0;
    align-self: flex-start;
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
    grid-template-columns: 52px minmax(0, 1fr);
    grid-template-areas: "image content" "price price";
    gap: 10px;
    align-items: start;
    min-width: 0;
    padding: 10px;
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
    grid-area: image;
    background: #fff1df;
}

.good-row__content {
    grid-area: content;
    min-width: 0;
    overflow-wrap: anywhere;
}

.good-row__name {
    color: #3f1d1d;
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.4;
}

.good-row__description {
    margin-top: 3px;
    color: #6b625d;
    font-size: 0.76rem;
    line-height: 1.3;
}

.good-row__price {
    grid-area: price;
    justify-self: start;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
    padding: 5px 8px;
    border-radius: 999px;
    color: #5c0000;
    background: #fff1df;
    font-size: 0.78rem;
    font-weight: 800;
}

@container home-goods-search (max-width: 360px) {
    .goods-search-card__actions {
        flex-direction: column;
    }

    .goods-search-card__action-link,
    .goods-search-card__phone {
        flex: 0 0 auto;
        width: 100%;
    }
}
</style>
