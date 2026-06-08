<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'

import { useAppRoute } from '@/Composables/useAppRoute'

import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import HomeHeroSection from '@/Components/Home/HomeHeroSection.vue'
import HomeCategoriesSection from '@/Components/Home/HomeCategoriesSection.vue'
import HomeFeaturedProductsSection from '@/Components/Home/HomeFeaturedProductsSection.vue'
import CocoaButterClassification from '@/Components/CocoaButterClassification.vue'
import HomeGoodsSearchCard from '@/Components/Home/HomeGoodsSearchCard.vue'
import HomeGoodsBookCard from '@/Components/Home/HomeGoodsBookCard.vue'
import HomeWelcomeBanner from '@/Components/Home/HomeWelcomeBanner.vue'

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    canLogin: {
        type: Boolean,
        default: false,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
    laravelVersion: {
        type: String,
        default: '',
    },
    phpVersion: {
        type: String,
        default: '',
    },
    goodOfTheDay: {
        type: Object,
        default: null,
    },
    productsCount: {
        type: Number,
        default: 0,
    },
    goodsCount: {
        type: Number,
        default: 0,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    featuredProducts: {
        type: Array,
        default: () => [],
    },
    promoProduct: {
        type: Object,
        default: null,
    },
    videos: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            productsCount: 0,
            goodsCount: 0,
        }),
    },
    heroGoods: {
        type: Array,
        default: () => [],
    },
    homeGoodsModule: {
        type: Object,
        default: null,
    },
})

const page = usePage()

const {
    route: appRoute,
} = useAppRoute()

const SITE_BASE_URL_FALLBACK = 'https://пищепром-сервер.рф'

const siteBaseUrl = computed(() => {
    return String(page.props.ziggy?.url || SITE_BASE_URL_FALLBACK).replace(/\/+$/, '')
})

function absoluteUrl(path) {
    if (!path) {
        return siteBaseUrl.value
    }

    const value = String(path)

    if (value.startsWith('http://') || value.startsWith('https://')) {
        return value
    }

    return `${siteBaseUrl.value}${value.startsWith('/') ? value : `/${value}`}`
}

function firstParam(params) {
    if (params === null || params === undefined) {
        return ''
    }

    if (typeof params === 'string' || typeof params === 'number') {
        return params
    }

    if (typeof params === 'object') {
        return params.slug ?? params.category ?? params.good ?? params.id ?? ''
    }

    return ''
}

function fallbackRoute(name, params = {}, absolute = false) {
    let path = '#'

    switch (name) {
        case 'home':
            path = '/'
            break

        case 'goods.published':
            path = '/goods/published'
            break

        case 'public.goods.index':
            path = '/g'
            break

        case 'category.show': {
            const category = firstParam(params)
            path = category
                ? `/категория/${encodeURIComponent(category)}`
                : '#'
            break
        }

        case 'web.sesame':
            path = '/кунжут'
            break

        case 'legal.privacy':
            path = '/privacy-policy'
            break

        case 'legal.terms':
            path = '/terms'
            break

        case 'legal.personal-data-consent':
            path = '/personal-data-consent'
            break

        default:
            path = '#'
            break
    }

    return absolute && path !== '#'
        ? absoluteUrl(path)
        : path
}

function routeExistsInZiggy(name) {
    const ziggy = page.props.ziggy || globalThis.Ziggy || {}

    return Boolean(ziggy.routes?.[name])
}

function hasRoute(name) {
    return routeExistsInZiggy(name)
}

function route(name, params = {}, absolute = false) {
    try {
        const url = appRoute(name, params, absolute)

        if (typeof url === 'string' && url.length > 0 && url !== '#') {
            return url
        }
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn(`[Welcome.vue] Ziggy route fallback used for "${name}"`, error)
        }
    }

    return fallbackRoute(name, params, absolute)
}

const goods = ref([])
const goodsLoading = ref(false)
const showGlycerin = ref(false)

const heroStats = computed(() => ({
    productsCount: props.productsCount ?? props.stats?.productsCount ?? 0,
    goodsCount: props.goodsCount ?? props.stats?.goodsCount ?? 0,
}))

function indexGoods() {
    goodsLoading.value = true

    axios.get(route('goods.published'))
        .then((response) => {
            goods.value = Array.isArray(response.data)
                ? response.data
                : response.data.data || []
        })
        .catch((error) => {
            console.error('[Welcome.vue] goods.published loading error:', error)
            goods.value = []
        })
        .finally(() => {
            goodsLoading.value = false
        })
}

const siteName = 'ПИЩЕПРОМ-СЕРВЕР'
const title = 'Пищепром-Сервер — ингредиенты оптом для пищевой промышленности'
const description = 'Каталог ингредиентов, сырья и товаров пищевой промышленности: замороженные овощи, ягоды, бакалея, продукция для производств, HoReCa и оптовых и розничных покупателей.'

const canonicalUrl = computed(() => {
    return route('home', {}, true)
})

const homeOgImage = computed(() => {
    return absoluteUrl('/images/og/pischeprom-home.jpg')
})

const searchTargetUrl = computed(() => {
    return `${route('public.goods.index', {}, true)}?search={search_term_string}`
})

const websiteJsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: 'Пищепром-сервер',
    alternateName: [
        'Pischeprom-server',
        'Ингредиенты оптом',
    ],
    url: canonicalUrl.value,
    description,
    potentialAction: {
        '@type': 'SearchAction',
        target: searchTargetUrl.value,
        'query-input': 'required name=search_term_string',
    },
}))

const organizationJsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Пищепром-сервер',
    url: canonicalUrl.value,
    logo: absoluteUrl('/images/logo/pischeprom-logo.png'),
    description,
}))

onMounted(() => {
    indexGoods()
})
</script>

<template>
    <Head>
        <title>{{ title }}</title>

        <meta
            head-key="description"
            name="description"
            :content="description"
        >

        <meta
            head-key="robots"
            name="robots"
            content="index,follow"
        >

        <link
            head-key="canonical"
            rel="canonical"
            :href="canonicalUrl"
        >

        <meta
            head-key="og:type"
            property="og:type"
            content="website"
        >

        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="siteName"
        >

        <meta
            head-key="og:title"
            property="og:title"
            :content="title"
        >

        <meta
            head-key="og:description"
            property="og:description"
            :content="description"
        >

        <meta
            head-key="og:url"
            property="og:url"
            :content="canonicalUrl"
        >

        <meta
            head-key="og:image"
            property="og:image"
            :content="homeOgImage"
        >

        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        >

        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="title"
        >

        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="description"
        >

        <meta
            head-key="twitter:image"
            name="twitter:image"
            :content="homeOgImage"
        >

        <component
            :is="'script'"
            head-key="json-ld-website"
            type="application/ld+json"
            v-html="websiteJsonLd"
        />

        <component
            :is="'script'"
            head-key="json-ld-organization"
            type="application/ld+json"
            v-html="organizationJsonLd"
        />
    </Head>

    <div class="welcome-page">
        <section class="welcome-section welcome-section--soft welcome-section--top-search">
            <v-container>
                <HomeWelcomeBanner :categories="categories" />

                <v-row dense class="align-stretch">
                    <v-col cols="12" lg="4">
                        <HomeGoodsSearchCard
                            :goods="goods"
                            :loading="goodsLoading"
                            :limit="24"
                        />
                    </v-col>

                    <v-col cols="12" lg="8">
                        <HomeGoodsBookCard
                            :module="homeGoodsModule"
                            :limit="24"
                        />
                    </v-col>
                </v-row>

                <v-card rounded="xl" elevation="1" class="for-whom-card">
                    <v-card-text class="for-whom-card__body">
                        <div class="for-whom-card__title">
                            Для кого
                        </div>

                        <ul class="for-whom-card__list">
                            <li>производства пищевой промышленности</li>
                            <li>переработчики и заготовители</li>
                            <li>HoReCa и общепит</li>
                            <li>оптовые и частные заказчики</li>
                        </ul>
                    </v-card-text>
                </v-card>
            </v-container>
        </section>

        <HomeHeroSection
            :stats="heroStats"
            :hero-goods="heroGoods"
        />

        <HomeCategoriesSection :categories="categories" />

        <HomeFeaturedProductsSection
            title="Популярные товары"
            subtitle="Ключевые позиции для пищевой промышленности"
            :products="featuredProducts"
        />

        <section class="welcome-section">
            <v-container>
                <v-row class="align-stretch" dense>
                    <v-col cols="12" lg="8">
                        <v-card class="welcome-banner-card" rounded="xl" elevation="2">
                            <v-row dense class="fill-height">
                                <v-col cols="12" md="7">
                                    <div class="welcome-banner-card__content">
                                        <div class="welcome-eyebrow">
                                            Маркетплейс для пищевой промышленности
                                        </div>

                                        <h2 class="welcome-title">
                                            Пищевое сырьё, ингредиенты и добавки
                                        </h2>

                                        <p class="welcome-text">
                                            Подбор номенклатуры, каталог товарных позиций,
                                            быстрый поиск и удобная навигация по категориям.
                                        </p>

                                        <div class="welcome-actions">
                                            <Link :href="route('public.goods.index')">
                                                <v-btn color="#800000" rounded="xl" size="large">
                                                    Перейти в каталог
                                                </v-btn>
                                            </Link>

                                            <Link
                                                v-if="hasRoute('web.sesame')"
                                                :href="route('web.sesame')"
                                            >
                                                <v-btn
                                                    variant="outlined"
                                                    color="#800000"
                                                    rounded="xl"
                                                    size="large"
                                                >
                                                    Кунжут
                                                </v-btn>
                                            </Link>
                                        </div>
                                    </div>
                                </v-col>

                                <v-col cols="12" md="5">
                                    <v-img
                                        src="https://storage.yandexcloud.net/pps/banners/%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD_%D0%9B%D0%B5%D1%86%D0%B5%D1%82%D0%B8%D0%BD%20%D0%BA%D0%BE%D0%BF%D0%B8%D1%8F.png"
                                        height="100%"
                                        cover
                                        class="welcome-banner-card__image"
                                    />
                                </v-col>
                            </v-row>
                        </v-card>
                    </v-col>

                    <v-col cols="12" lg="4">
                        <v-card class="stats-card h-100" rounded="xl" elevation="2">
                            <v-card-title class="text-h6 font-weight-bold">
                                О проекте
                            </v-card-title>

                            <v-card-text class="stats-card__body">
                                <div class="stats-card__numbers">
                                    <div class="stats-chip">
                                        <span class="stats-chip__value">
                                            {{ heroStats.productsCount }}
                                        </span>
                                        <span class="stats-chip__label">
                                            товарных наименований
                                        </span>
                                    </div>

                                    <div class="stats-chip">
                                        <span class="stats-chip__value">
                                            {{ heroStats.goodsCount }}
                                        </span>
                                        <span class="stats-chip__label">
                                            товаров
                                        </span>
                                    </div>
                                </div>

                                <p class="welcome-text mb-0">
                                    ПИЩЕПРОМ-СЕРВЕР — работа по поддержанию широкого
                                    ассортимента пищевых добавок, ингредиентов и сырья.
                                </p>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </section>

        <section class="welcome-section">
            <v-container>
                <div class="section-heading">
                    <div>
                        <div class="welcome-eyebrow">
                            Тематические материалы
                        </div>

                        <h2 class="section-title">
                            Сырьё и ингредиенты
                        </h2>
                    </div>
                </div>

                <v-row dense class="align-stretch">
                    <v-col cols="12" lg="6">
                        <v-card rounded="xl" elevation="2" class="h-100">
                            <v-card-title class="text-h6 font-weight-bold">
                                Классификация какао-масла
                            </v-card-title>

                            <v-card-text>
                                <CocoaButterClassification />
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" lg="6">
                        <v-card rounded="xl" elevation="2" class="h-100">
                            <v-img
                                height="240"
                                src="https://storage.yandexcloud.net/cold-reserve/Goods/Cocoa/butter/buttercocoa-borrowed-01-330.jpg"
                                cover
                            />

                            <v-card-title class="text-h6 font-weight-bold">
                                Какао-масло
                            </v-card-title>

                            <v-card-text class="text-body-2">
                                <p>
                                    <strong>Состав:</strong>
                                    диглицериды и триглицериды, смешанные с жирными кислотами.
                                </p>

                                <p>
                                    <strong>Температура плавления:</strong>
                                    32–35 °C. При 40 °C масло прозрачное.
                                </p>

                                <p>
                                    <strong>Применение:</strong>
                                    шоколад, кондитерские изделия, косметика,
                                    фармацевтика.
                                </p>

                                <p class="mb-0">
                                    Существует в нерафинированном и рафинированном виде,
                                    отличающихся степенью очистки, запахом и сроком хранения.
                                </p>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </section>

        <section class="welcome-section welcome-section--soft">
            <v-container>
                <v-row dense class="align-stretch">
                    <v-col cols="12" md="6" lg="4">
                        <v-card rounded="xl" elevation="2" class="h-100">
                            <v-img
                                height="220"
                                src="https://storage.yandexcloud.net/cold-reserve/Goods/Lecithin/%D0%A1%D0%BD%D0%B8%D0%BC%D0%BE%D0%BA%20%D1%8D%D0%BA%D1%80%D0%B0%D0%BD%D0%B0%202024-08-21%20%D0%B2%2019.51.31.png"
                                cover
                            />

                            <v-card-title class="text-h6 font-weight-bold">
                                Лецитин
                            </v-card-title>

                            <v-card-subtitle>
                                Подсолнечный
                            </v-card-subtitle>

                            <v-card-text class="text-body-2">
                                Концентрат фосфатидный подсолнечный. Используется в
                                шоколадном производстве, помогает стабилизировать массу
                                и улучшать текстуру продукта.
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="6" lg="4">
                        <v-card rounded="xl" elevation="2" class="h-100">
                            <v-img
                                height="220"
                                src="https://storage.yandexcloud.net/cold-reserve/Goods/Glycerol/glycerol-25.jpg"
                                cover
                            />

                            <v-card-title class="text-h6 font-weight-bold">
                                Глицерин
                            </v-card-title>

                            <v-card-subtitle>
                                ПК-94, Д-98
                            </v-card-subtitle>

                            <v-card-actions>
                                <v-btn
                                    color="#800000"
                                    variant="text"
                                    @click="showGlycerin = !showGlycerin"
                                >
                                    {{ showGlycerin ? 'Скрыть' : 'Подробнее' }}
                                </v-btn>
                            </v-card-actions>

                            <v-expand-transition>
                                <div v-show="showGlycerin">
                                    <v-divider />

                                    <v-card-text class="text-body-2">
                                        Пищевая добавка Е422. Канистры 10 кг и 25 кг.
                                        Используется для фармакопейных целей, а также в
                                        пищевой, косметической и других отраслях промышленности.
                                        Срок хранения до вскрытия упаковки — 5 лет.
                                    </v-card-text>
                                </div>
                            </v-expand-transition>
                        </v-card>
                    </v-col>

                    <v-col cols="12" lg="4">
                        <v-card rounded="xl" elevation="2" class="h-100 info-card">
                            <v-card-title class="text-h6 font-weight-bold">
                                Что есть на платформе
                            </v-card-title>

                            <v-card-text>
                                <ul class="info-list">
                                    <li>каталог товаров и ингредиентов</li>
                                    <li>навигация по категориям</li>
                                    <li>подбор популярных позиций</li>
                                    <li>поиск по опубликованным товарам</li>
                                    <li>витрина для пищевой промышленности</li>
                                </ul>
                            </v-card-text>

                            <v-card-actions class="px-4 pb-4">
                                <Link :href="route('public.goods.index')" class="w-100">
                                    <v-btn block color="#800000" rounded="xl">
                                        Смотреть товары
                                    </v-btn>
                                </Link>
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>
            </v-container>
        </section>
    </div>
</template>

<style scoped>
.welcome-page {
    background: #f8fafc;
}

.welcome-section {
    padding: 32px 0;
}

.welcome-section.welcome-section--top-search {
    padding-top: 4px;
    padding-bottom: 24px;
}

.for-whom-card {
    margin-top: 8px;
    background: #ffffff;
}

.for-whom-card__body {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 12px 16px;
}

.for-whom-card__title {
    color: #3f1d1d;
    font-size: 1rem;
    font-weight: 900;
    white-space: nowrap;
}

.for-whom-card__list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.for-whom-card__list li {
    padding: 7px 10px;
    border-radius: 999px;
    background: #fff7ed;
    color: #6b2b1e;
    font-size: 0.88rem;
    font-weight: 700;
}

.welcome-section--soft {
    background: linear-gradient(180deg, #fffaf8 0%, #fff 100%);
}

.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.section-title {
    margin: 4px 0 0;
    font-size: 1.75rem;
    line-height: 1.15;
    font-weight: 800;
    color: #3f1d1d;
}

.welcome-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.08);
    color: #8b1e1e;
    font-size: 0.86rem;
    font-weight: 700;
}

.welcome-title {
    margin: 14px 0 12px;
    font-size: 2rem;
    line-height: 1.15;
    font-weight: 800;
    color: #3f1d1d;
}

.welcome-text {
    color: #5f5753;
    line-height: 1.65;
    font-size: 0.98rem;
}

.welcome-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
}

.welcome-banner-card {
    overflow: hidden;
    background:
        linear-gradient(135deg, #fffaf8 0%, #fff 35%, #fff6f3 100%);
}

.welcome-banner-card__content {
    padding: 32px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 100%;
}

.welcome-banner-card__image {
    min-height: 100%;
}

.stats-card {
    background: linear-gradient(180deg, #ffffff 0%, #fff8f5 100%);
}

.stats-card__body {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.stats-card__numbers {
    display: grid;
    gap: 12px;
}

.stats-chip {
    display: flex;
    flex-direction: column;
    padding: 14px 16px;
    border-radius: 18px;
    background: rgba(128, 0, 0, 0.05);
    border: 1px solid rgba(128, 0, 0, 0.08);
}

.stats-chip__value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #7f1d1d;
    line-height: 1.1;
}

.stats-chip__label {
    margin-top: 4px;
    color: #6b625d;
    font-size: 0.92rem;
}

.promo-mini-card {
    background: #fff;
}

.category-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.category-tag {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    border-radius: 999px;
    text-decoration: none;
    color: #7f1d1d;
    background: rgba(128, 0, 0, 0.06);
    border: 1px solid rgba(128, 0, 0, 0.08);
    font-size: 0.9rem;
    font-weight: 600;
}

.category-tag:hover {
    background: rgba(128, 0, 0, 0.10);
}

.mini-list,
.info-list {
    margin: 0;
    padding-left: 18px;
    color: #5f5753;
    line-height: 1.8;
}

.info-card {
    background: linear-gradient(180deg, #fff 0%, #fffaf7 100%);
}

@media (max-width: 960px) {
    .welcome-section {
        padding: 24px 0;
    }

    .welcome-title {
        font-size: 1.65rem;
    }

    .section-title {
        font-size: 1.45rem;
    }

    .welcome-banner-card__content {
        padding: 22px;
    }
}

@media (max-width: 600px) {
    .welcome-actions {
        flex-direction: column;
    }

    .welcome-actions :deep(.v-btn) {
        width: 100%;
    }
}
</style>
