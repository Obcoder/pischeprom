<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'

import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import { useAppRoute } from '@/Composables/useAppRoute'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { usePhoneCallRegistration } from '@/Composables/usePhoneCallRegistration'

const { route } = useAppRoute()
const { goodPublicUrl } = usePublicGoodUrl()
const { registerPhoneCallClick } = usePhoneCallRegistration()

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
    goods: {
        type: Array,
        default: () => [],
    },
    relatedCategories: {
        type: Array,
        default: () => [],
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref('')
const selectedProductId = ref(null)
const sortMode = ref('name')

const category = computed(() => props.category ?? {})
const products = computed(() => category.value.products ?? [])

const pageTitle = computed(() => props.seo.title || `${category.value.name} — ПИЩЕПРОМ-СЕРВЕР`)
const pageDescription = computed(() => props.seo.description || category.value.short_description || '')
const pageH1 = computed(() => props.seo.h1 || category.value.h1 || category.value.name)
const canonicalUrl = computed(() => props.seo.canonical || category.value.public_url || '')
const robots = computed(() => props.seo.robots || 'index,follow')
const ogTitle = computed(() => props.seo.ogTitle || pageTitle.value)
const ogDescription = computed(() => props.seo.ogDescription || pageDescription.value)
const ogImage = computed(() => props.seo.image || category.value.image || '')
const structuredData = computed(() => props.seo.jsonLd || null)

useHead({
    script: computed(() => {
        if (!structuredData.value) {
            return []
        }

        return [
            {
                type: 'application/ld+json',
                children: JSON.stringify(structuredData.value),
            },
        ]
    }),
})

const productFilterItems = computed(() => [
    { title: 'Все продукты категории', value: null },
    ...products.value.map((product) => ({
        title: product.rus || product.name || `Product #${product.id}`,
        value: product.id,
    })),
])

const filteredGoods = computed(() => {
    const query = search.value.trim().toLowerCase()

    return [...props.goods]
        .filter((good) => {
            if (selectedProductId.value) {
                const hasProduct = (good.products || []).some((product) => product.id === selectedProductId.value)

                if (!hasProduct) {
                    return false
                }
            }

            if (!query) {
                return true
            }

            return [good.name, good.description, ...(good.products || []).map((product) => product.rus || product.name)]
                .filter(Boolean)
                .join(' ')
                .toLowerCase()
                .includes(query)
        })
        .sort((a, b) => {
            if (sortMode.value === 'price') {
                return Number(latestPrice(a)?.price || 0) - Number(latestPrice(b)?.price || 0)
            }

            if (sortMode.value === 'price_desc') {
                return Number(latestPrice(b)?.price || 0) - Number(latestPrice(a)?.price || 0)
            }

            return String(a.name || '').localeCompare(String(b.name || ''), 'ru')
        })
})

const popularProducts = computed(() => {
    return [...products.value]
        .sort((a, b) => Number(b.goods_count || 0) - Number(a.goods_count || 0))
        .slice(0, 10)
})

function categoryUrl(item) {
    return route('category.show', item.slug || item.id)
}

function goodImage(item) {
    const mediaImage = (item.published_media || []).find((media) => media.type === 'image')

    return mediaImage?.thumb_url || mediaImage?.url || item.ava_thumb || item.ava_image || null
}

function latestPrice(good) {
    return good.latest_price || good.latestPrice || null
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return ''
    }

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value))
}

function priceText(good) {
    const price = latestPrice(good)

    if (!price) {
        return 'Цена по запросу'
    }

    return `${formatMoney(price.price)} ${price.currency?.code || 'RUB'}`
}

function productTitle(product) {
    return product.rus || product.name || `Product #${product.id}`
}

function requestCategoryPrice() {
    if (typeof window === 'undefined') {
        return
    }

    const subject = `Запрос по категории: ${category.value.name}`
    window.location.href = `mailto:office@180022.ru?subject=${encodeURIComponent(subject)}`
}

function clickCategoryPhone() {
    registerPhoneCallClick({
        source: 'category_show',
        category_id: category.value.id,
        category_name: category.value.name,
    })
}
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>

        <meta head-key="description" name="description" :content="pageDescription">
        <meta head-key="robots" name="robots" :content="robots">
        <link v-if="canonicalUrl" head-key="canonical" rel="canonical" :href="canonicalUrl">
        <meta head-key="og:title" property="og:title" :content="ogTitle">
        <meta head-key="og:description" property="og:description" :content="ogDescription">
        <meta head-key="og:type" property="og:type" content="website">
        <meta v-if="canonicalUrl" head-key="og:url" property="og:url" :content="canonicalUrl">
        <meta v-if="ogImage" head-key="og:image" property="og:image" :content="ogImage">
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
        <meta head-key="twitter:title" name="twitter:title" :content="ogTitle">
        <meta head-key="twitter:description" name="twitter:description" :content="ogDescription">
        <meta v-if="ogImage" head-key="twitter:image" name="twitter:image" :content="ogImage">
    </Head>

    <v-container class="category-page py-8">
        <section class="category-hero">
            <div class="category-hero__glow category-hero__glow--one" />
            <div class="category-hero__glow category-hero__glow--two" />

            <v-row class="align-center" dense>
                <v-col cols="12" md="7">
                    <div class="category-hero__eyebrow">
                        Категория пищевой промышленности
                    </div>

                    <h1 class="category-hero__title">
                        {{ pageH1 }}
                    </h1>

                    <p v-if="category.short_description || pageDescription" class="category-hero__text">
                        {{ category.short_description || pageDescription }}
                    </p>

                    <div class="category-hero__stats">
                        <div class="category-stat">
                            <strong>{{ goods.length }}</strong>
                            <span>товаров</span>
                        </div>
                        <div class="category-stat">
                            <strong>{{ category.products_count ?? products.length }}</strong>
                            <span>продуктов</span>
                        </div>
                        <div class="category-stat">
                            <strong>{{ relatedCategories.length }}</strong>
                            <span>смежных категорий</span>
                        </div>
                    </div>

                    <div class="category-hero__actions">
                        <v-btn color="#8b1f12" rounded="xl" size="large" @click="requestCategoryPrice">
                            Запросить предложение
                        </v-btn>

                        <a href="tel:+79650160001" class="text-decoration-none" @click="clickCategoryPhone">
                            <v-btn variant="outlined" color="#8b1f12" rounded="xl" size="large">
                                Позвонить менеджеру
                            </v-btn>
                        </a>
                    </div>
                </v-col>

                <v-col cols="12" md="5">
                    <v-card rounded="xl" elevation="0" class="category-hero__image-card">
                        <v-img
                            :src="category.image || '/images/placeholders/category.jpg'"
                            :alt="category.image_alt || category.name"
                            height="360"
                            cover
                        />
                    </v-card>
                </v-col>
            </v-row>
        </section>

        <v-row class="mt-8" dense>
            <v-col cols="12" lg="3">
                <v-card rounded="xl" elevation="1" class="category-sidebar">
                    <v-card-title>Подбор внутри категории</v-card-title>
                    <v-card-text>
                        <v-text-field
                            v-model="search"
                            label="Найти товар"
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            density="comfortable"
                            clearable
                        />

                        <v-select
                            v-model="selectedProductId"
                            :items="productFilterItems"
                            label="Продукт"
                            variant="outlined"
                            density="comfortable"
                        />

                        <v-select
                            v-model="sortMode"
                            :items="[
                                { title: 'По названию', value: 'name' },
                                { title: 'Цена: сначала дешевле', value: 'price' },
                                { title: 'Цена: сначала дороже', value: 'price_desc' },
                            ]"
                            label="Сортировка"
                            variant="outlined"
                            density="comfortable"
                        />
                    </v-card-text>
                </v-card>

                <v-card v-if="popularProducts.length" rounded="xl" elevation="1" class="mt-4">
                    <v-card-title>Продукты</v-card-title>
                    <v-card-text>
                        <div class="category-product-list">
                            <button
                                v-for="product in popularProducts"
                                :key="product.id"
                                type="button"
                                class="category-product-chip"
                                :class="{ 'category-product-chip--active': selectedProductId === product.id }"
                                @click="selectedProductId = selectedProductId === product.id ? null : product.id"
                            >
                                <span>{{ productTitle(product) }}</span>
                                <small>{{ product.goods_count ?? 0 }}</small>
                            </button>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" lg="9">
                <div class="category-section-head">
                    <div>
                        <div class="category-section-kicker">Каталог</div>
                        <h2>Товары категории</h2>
                    </div>

                    <v-chip color="primary" variant="tonal">
                        Найдено: {{ filteredGoods.length }}
                    </v-chip>
                </div>

                <v-row v-if="filteredGoods.length" dense>
                    <v-col
                        v-for="good in filteredGoods"
                        :key="good.id"
                        cols="12"
                        sm="6"
                        lg="4"
                    >
                        <Link :href="goodPublicUrl(good, false)" class="text-decoration-none">
                            <v-card rounded="xl" elevation="1" class="category-good-card h-100">
                                <v-img
                                    v-if="goodImage(good)"
                                    :src="goodImage(good)"
                                    :alt="good.name"
                                    height="210"
                                    cover
                                />

                                <div v-else class="category-good-card__empty">
                                    <v-icon icon="mdi-image-off" size="42" />
                                </div>

                                <v-card-text>
                                    <div class="category-good-card__title">
                                        {{ good.name }}
                                    </div>

                                    <div v-if="good.description" class="category-good-card__description">
                                        {{ good.description }}
                                    </div>

                                    <div class="category-good-card__meta">
                                        <span>{{ priceText(good) }}</span>
                                        <v-icon icon="mdi-arrow-right" size="18" />
                                    </div>
                                </v-card-text>
                            </v-card>
                        </Link>
                    </v-col>
                </v-row>

                <v-alert v-else type="info" variant="tonal" rounded="xl">
                    По выбранным условиям товары не найдены. Измените фильтр или отправьте запрос менеджеру.
                </v-alert>
            </v-col>
        </v-row>

        <v-row v-if="category.description || category.seo_text" class="mt-8">
            <v-col cols="12" lg="8">
                <v-card rounded="xl" elevation="1" class="category-copy-card">
                    <v-card-text>
                        <div v-if="category.description" class="category-copy">
                            {{ category.description }}
                        </div>
                        <v-divider v-if="category.description && category.seo_text" class="my-5" />
                        <div v-if="category.seo_text" class="category-copy category-copy--muted">
                            {{ category.seo_text }}
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" lg="4">
                <v-card rounded="xl" elevation="1" class="category-cta-card">
                    <v-card-title>Не нашли нужный товар?</v-card-title>
                    <v-card-text>
                        <p>
                            Отправьте запрос по категории, менеджер подберет позиции, аналоги и условия поставки.
                        </p>
                        <v-btn block color="#8b1f12" rounded="xl" @click="requestCategoryPrice">
                            Отправить запрос
                        </v-btn>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <section v-if="relatedCategories.length" class="mt-8">
            <div class="category-section-head">
                <div>
                    <div class="category-section-kicker">Навигация</div>
                    <h2>Смежные категории</h2>
                </div>
            </div>

            <v-row dense>
                <v-col
                    v-for="item in relatedCategories"
                    :key="item.id"
                    cols="12"
                    sm="6"
                    md="3"
                >
                    <Link :href="categoryUrl(item)" class="text-decoration-none">
                        <v-card rounded="xl" elevation="1" class="related-category-card h-100">
                            <v-img
                                :src="item.image || '/images/placeholders/category.jpg'"
                                :alt="item.image_alt || item.name"
                                height="140"
                                cover
                            />
                            <v-card-text>
                                <div class="font-weight-bold text-high-emphasis">{{ item.name }}</div>
                                <div class="text-caption text-medium-emphasis mt-1">
                                    {{ item.products_count ?? 0 }} продуктов
                                </div>
                            </v-card-text>
                        </v-card>
                    </Link>
                </v-col>
            </v-row>
        </section>
    </v-container>
</template>

<style scoped>
.category-page {
    --category-accent: #8b1f12;
    --category-ink: #241310;
    --category-muted: #6f5a54;
    --category-surface: #fffaf2;
}

.category-hero {
    position: relative;
    overflow: hidden;
    padding: clamp(28px, 5vw, 56px);
    border-radius: 34px;
    color: var(--category-ink);
    background:
        radial-gradient(circle at 8% 15%, rgba(255, 196, 117, 0.45), transparent 34%),
        linear-gradient(135deg, #fff6e5 0%, #ffe1c5 50%, #f6b89b 100%);
}

.category-hero__glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(16px);
    opacity: 0.55;
    pointer-events: none;
}

.category-hero__glow--one {
    width: 220px;
    height: 220px;
    right: 10%;
    top: -70px;
    background: #fff;
}

.category-hero__glow--two {
    width: 160px;
    height: 160px;
    left: 38%;
    bottom: -80px;
    background: #c7572e;
}

.category-hero__eyebrow,
.category-section-kicker {
    display: inline-flex;
    margin-bottom: 12px;
    padding: 5px 12px;
    border-radius: 999px;
    color: var(--category-accent);
    background: rgba(255, 255, 255, 0.72);
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.category-hero__title {
    max-width: 760px;
    margin: 0;
    font-size: clamp(2.2rem, 5vw, 4.7rem);
    line-height: 0.98;
    letter-spacing: -0.055em;
}

.category-hero__text {
    max-width: 680px;
    margin: 20px 0 0;
    color: var(--category-muted);
    font-size: 1.1rem;
    line-height: 1.65;
}

.category-hero__stats {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 28px;
}

.category-stat {
    min-width: 128px;
    padding: 14px 16px;
    border: 1px solid rgba(139, 31, 18, 0.14);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.62);
}

.category-stat strong {
    display: block;
    font-size: 1.6rem;
    line-height: 1;
}

.category-stat span {
    color: var(--category-muted);
    font-size: 0.86rem;
}

.category-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 28px;
}

.category-hero__image-card {
    border: 10px solid rgba(255, 255, 255, 0.48);
    transform: rotate(1.5deg);
}

.category-section-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
}

.category-section-head h2 {
    margin: 0;
    color: var(--category-ink);
    font-size: clamp(1.6rem, 3vw, 2.4rem);
    letter-spacing: -0.035em;
}

.category-sidebar {
    position: sticky;
    top: 96px;
}

.category-product-list {
    display: grid;
    gap: 8px;
}

.category-product-chip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border: 1px solid rgba(139, 31, 18, 0.12);
    border-radius: 14px;
    color: var(--category-ink);
    background: #fff;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.2s ease, transform 0.2s ease;
}

.category-product-chip:hover,
.category-product-chip--active {
    border-color: rgba(139, 31, 18, 0.48);
    transform: translateY(-1px);
}

.category-product-chip small {
    color: var(--category-accent);
    font-weight: 800;
}

.category-good-card,
.related-category-card {
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.category-good-card:hover,
.related-category-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 42px rgba(47, 24, 15, 0.14);
}

.category-good-card__empty {
    height: 210px;
    display: grid;
    place-items: center;
    color: rgba(36, 19, 16, 0.36);
    background: linear-gradient(135deg, #fff4e1, #f8dcc6);
}

.category-good-card__title {
    color: var(--category-ink);
    font-size: 1.04rem;
    font-weight: 800;
    line-height: 1.25;
}

.category-good-card__description {
    height: 42px;
    margin-top: 8px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    color: var(--category-muted);
    font-size: 0.86rem;
}

.category-good-card__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 16px;
    color: var(--category-accent);
    font-weight: 800;
}

.category-copy-card,
.category-cta-card {
    background: var(--category-surface);
}

.category-copy {
    color: var(--category-ink);
    line-height: 1.75;
    white-space: pre-line;
}

.category-copy--muted {
    color: var(--category-muted);
}

@media (max-width: 960px) {
    .category-sidebar {
        position: static;
    }

    .category-hero__image-card {
        transform: none;
    }
}
</style>
