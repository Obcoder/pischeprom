<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'

import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import PublicGoodInfographicCard from '@/Components/Goods/PublicGoodInfographicCard.vue'
import { useAppRoute } from '@/Composables/useAppRoute'

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    goods: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
        }),
    },
    field: {
        type: Object,
        default: null,
    },
    fields: {
        type: Array,
        default: () => [],
    },
    country: {
        type: Object,
        default: null,
    },
})

const page = usePage()
const { route: appRoute } = useAppRoute()

const searchGoods = ref(props.filters.search || '')
let searchTimer = null

const isAuthenticated = computed(() => Boolean(page.props.auth?.user))
const isFieldPage = computed(() => Boolean(props.field?.id))
const isCountryPage = computed(() => Boolean(props.country?.id))
const availableFields = computed(() => Array.isArray(props.fields) ? props.fields : [])
const goodsCountText = computed(() => `${props.goods.length} ${pluralizeGoods(props.goods.length)}`)

const pageH1 = computed(() => {
    if (isFieldPage.value) {
        return props.field.title || props.field.name || 'Подборка товаров'
    }

    if (isCountryPage.value) {
        return `Товары из ${props.country.name}`
    }

    return 'Каталог товаров'
})

const pageKicker = computed(() => {
    if (isFieldPage.value) {
        return 'Подборка'
    }

    if (isCountryPage.value) {
        return 'География'
    }

    return 'ПИЩЕПРОМ-СЕРВЕР'
})

const pageDescription = computed(() => {
    if (isFieldPage.value) {
        return props.field.description || `Товары подборки ${props.field.title || props.field.name}`
    }

    if (isCountryPage.value) {
        return `Подборка товаров из ${props.country.name}: пищевое сырьё, ингредиенты и позиции для производств.`
    }

    return 'Пищевое сырьё, ингредиенты и добавки для закупки, сравнения цен и выбора товарных позиций.'
})

const pageTitle = computed(() => {
    if (isFieldPage.value) {
        return `${props.field.title || props.field.name} — подборка товаров ПИЩЕПРОМ-СЕРВЕР`
    }

    if (isCountryPage.value) {
        return `Товары из ${props.country.name} — ПИЩЕПРОМ-СЕРВЕР`
    }

    return 'Товары, которые Вы можете приобрести на ПИЩЕПРОМ-СЕРВЕРЕ: пищевое сырьё, пищевые ингредиенты, пищевые добавки'
})

function indexGoods() {
    clearTimeout(searchTimer)

    searchTimer = setTimeout(() => {
        const target = isFieldPage.value
            ? appRoute('public.fields.show', props.field.slug || props.field.id)
            : appRoute('public.goods.index')

        router.get(target, {
            search: searchGoods.value || undefined,
            country_id: props.country?.id || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 250)
}

function withQuery(url, params = {}) {
    const query = new URLSearchParams()

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, value)
        }
    })

    const queryString = query.toString()

    return queryString ? `${url}?${queryString}` : url
}

function catalogHref() {
    return withQuery(appRoute('public.goods.index'), {
        search: searchGoods.value || undefined,
        country_id: props.country?.id || undefined,
    })
}

function fieldHref(field) {
    return withQuery(appRoute('public.fields.show', field.slug || field.id), {
        search: searchGoods.value || undefined,
    })
}

function isActiveField(field) {
    return String(field?.id || '') === String(props.field?.id || '')
}

function pluralizeGoods(count) {
    const mod10 = count % 10
    const mod100 = count % 100

    if (mod10 === 1 && mod100 !== 11) {
        return 'товар'
    }

    if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) {
        return 'товара'
    }

    return 'товаров'
}

useHead({
    title: pageTitle,
    meta: [
        {
            name: 'description',
            content: pageDescription,
        },
    ],
})
</script>

<template>
    <v-container fluid class="goods-page">
        <section class="goods-page__header">
            <div class="goods-page__header-copy">
                <div class="goods-page__kicker">
                    {{ pageKicker }}
                </div>

                <h1>{{ pageH1 }}</h1>

                <p>{{ pageDescription }}</p>
            </div>

            <div v-if="country" class="goods-page__country">
                <span class="goods-page__country-flag">
                    <img
                        v-if="country.flag"
                        :src="country.flag"
                        :alt="country.name"
                    >
                    <span v-else>{{ country.name?.slice(0, 1) }}</span>
                </span>
                <span>{{ country.name }}</span>
            </div>
        </section>

        <div class="goods-page__layout">
            <aside class="goods-page__filters">
                <div class="goods-filter-panel">
                    <div class="goods-filter-panel__title">
                        <span>Фильтры</span>
                        <strong>{{ goodsCountText }}</strong>
                    </div>

                    <v-text-field
                        v-model="searchGoods"
                        density="compact"
                        variant="outlined"
                        prepend-inner-icon="mdi-magnify"
                        :label="isFieldPage || isCountryPage ? 'Поиск в текущем списке' : 'Поиск по товарам'"
                        placeholder="Название товара"
                        hide-details
                        clearable
                        class="goods-filter-panel__search"
                        @input="indexGoods"
                        @click:clear="indexGoods"
                    />

                    <div v-if="isCountryPage" class="goods-filter-panel__active">
                        <span>Страна</span>
                        <strong>{{ country.name }}</strong>
                    </div>

                    <div class="goods-filter-panel__group">
                        <div class="goods-filter-panel__label">
                            Подборки
                        </div>

                        <div class="goods-filter-panel__links">
                            <Link
                                :href="catalogHref()"
                                class="goods-filter-link"
                                :class="{ 'goods-filter-link--active': !isFieldPage }"
                            >
                                <span>Весь каталог</span>
                                <small>Все товары</small>
                            </Link>

                            <Link
                                v-for="item in availableFields"
                                :key="item.id"
                                :href="fieldHref(item)"
                                class="goods-filter-link"
                                :class="{ 'goods-filter-link--active': isActiveField(item) }"
                            >
                                <span>{{ item.title || item.name }}</span>
                                <small>{{ item.goods_count || 0 }} товаров</small>
                            </Link>

                            <div v-if="!availableFields.length" class="goods-filter-panel__empty">
                                Опубликованные подборки пока не настроены.
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="goods-page__content">
                <div class="goods-page__result-head">
                    <div>
                        <span>Результаты</span>
                        <strong>{{ goodsCountText }}</strong>
                    </div>
                </div>

                <div v-if="goods.length" class="goods-page__grid">
                    <PublicGoodInfographicCard
                        v-for="good in goods"
                        :key="good.id"
                        :good="good"
                        :is-authenticated="isAuthenticated"
                    />
                </div>

                <div v-else class="goods-page__empty">
                    <v-icon icon="mdi-package-variant" size="34" />
                    <strong>Товары не найдены</strong>
                    <span>Измените поисковый запрос или выберите другую подборку.</span>
                </div>
            </main>
        </div>
    </v-container>
</template>

<style scoped>
.goods-page {
    min-height: calc(100vh - 64px);
    padding: 18px;
    background:
        linear-gradient(135deg, rgba(255, 250, 246, 0.96), rgba(247, 244, 236, 0.94)),
        #fffaf6;
    color: #2d201d;
}

.goods-page__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 16px;
    padding: 18px;
    border: 1px solid rgba(128, 0, 0, 0.10);
    border-radius: 8px;
    background:
        linear-gradient(90deg, rgba(128, 0, 0, 0.07), transparent 54%),
        #fffdf9;
    box-shadow: 0 12px 30px rgba(63, 29, 29, 0.07);
}

.goods-page__header-copy {
    max-width: 920px;
}

.goods-page__kicker {
    color: rgba(128, 0, 0, 0.72);
    font-size: 11px;
    font-weight: 950;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.goods-page__header h1 {
    margin: 4px 0 6px;
    color: #2d201d;
    font-size: clamp(1.65rem, 3vw, 2.6rem);
    font-weight: 950;
    line-height: 1.08;
}

.goods-page__header p {
    max-width: 840px;
    margin: 0;
    color: #74665f;
    font-size: 0.96rem;
    line-height: 1.55;
}

.goods-page__country {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid rgba(71, 118, 90, 0.16);
    border-radius: 8px;
    background: rgba(71, 118, 90, 0.08);
    color: #30463a;
    font-size: 13px;
    font-weight: 950;
    white-space: nowrap;
}

.goods-page__country-flag {
    display: grid;
    width: 28px;
    height: 28px;
    overflow: hidden;
    place-items: center;
    border-radius: 50%;
    background: #fff;
}

.goods-page__country-flag img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.goods-page__layout {
    display: grid;
    grid-template-columns: 286px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.goods-page__filters {
    position: sticky;
    top: 74px;
}

.goods-filter-panel {
    display: grid;
    gap: 14px;
    padding: 14px;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 8px;
    background: #fffdf9;
    box-shadow: 0 12px 28px rgba(63, 29, 29, 0.07);
}

.goods-filter-panel__title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.goods-filter-panel__title span,
.goods-filter-panel__label,
.goods-page__result-head span,
.goods-filter-panel__active span {
    color: rgba(128, 0, 0, 0.72);
    font-size: 10px;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.goods-filter-panel__title strong,
.goods-page__result-head strong {
    color: #2d201d;
    font-size: 15px;
    font-weight: 950;
}

.goods-filter-panel__search :deep(.v-field) {
    border-radius: 8px;
    background: #fff;
}

.goods-filter-panel__active {
    display: grid;
    gap: 3px;
    padding: 10px;
    border: 1px solid rgba(71, 118, 90, 0.14);
    border-radius: 8px;
    background: rgba(71, 118, 90, 0.07);
}

.goods-filter-panel__active strong {
    color: #30463a;
    font-size: 13px;
    font-weight: 950;
}

.goods-filter-panel__group {
    display: grid;
    gap: 8px;
}

.goods-filter-panel__links {
    display: grid;
    gap: 7px;
}

.goods-filter-link {
    display: grid;
    gap: 2px;
    padding: 9px 10px;
    border: 1px solid rgba(128, 0, 0, 0.10);
    border-radius: 8px;
    background: #fff;
    color: #3d302c;
    text-decoration: none;
    transition: border-color 0.16s ease, background 0.16s ease, transform 0.16s ease;
}

.goods-filter-link:hover {
    border-color: rgba(128, 0, 0, 0.28);
    transform: translateX(2px);
}

.goods-filter-link--active {
    border-color: rgba(128, 0, 0, 0.28);
    background: rgba(128, 0, 0, 0.07);
}

.goods-filter-link span {
    overflow-wrap: anywhere;
    font-size: 13px;
    font-weight: 900;
    line-height: 1.25;
}

.goods-filter-link small {
    color: #796b64;
    font-size: 11px;
    font-weight: 800;
}

.goods-filter-panel__empty {
    padding: 10px;
    border: 1px dashed rgba(128, 0, 0, 0.16);
    border-radius: 8px;
    color: #796b64;
    font-size: 12px;
}

.goods-page__content {
    min-width: 0;
}

.goods-page__result-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.goods-page__result-head div {
    display: grid;
    gap: 2px;
}

.goods-page__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(390px, 1fr));
    gap: 14px;
}

.goods-page__empty {
    display: grid;
    min-height: 260px;
    place-items: center;
    gap: 6px;
    border: 1px dashed rgba(128, 0, 0, 0.18);
    border-radius: 8px;
    background: #fffdf9;
    color: #796b64;
    text-align: center;
}

.goods-page__empty strong {
    color: #2d201d;
    font-size: 18px;
}

@media (max-width: 1100px) {
    .goods-page__layout {
        grid-template-columns: 1fr;
    }

    .goods-page__filters {
        position: static;
    }

    .goods-filter-panel__links {
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    }
}

@media (max-width: 640px) {
    .goods-page {
        padding: 12px;
    }

    .goods-page__header {
        align-items: flex-start;
        flex-direction: column;
    }

    .goods-page__grid {
        grid-template-columns: 1fr;
    }
}
</style>
