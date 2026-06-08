<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { logo } from '@/Pages/Helpers/consts.js'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { useAppRoute } from '@/Composables/useAppRoute'

const { route } = useAppRoute()
const { goodPublicUrl } = usePublicGoodUrl()

const props = defineProps({
    goods: {
        type: Array,
        default: () => [],
    },
    module: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    limit: {
        type: Number,
        default: 18,
    },
})

const search = ref('')
const activePersonalSection = ref('ordered')

const isPersonal = computed(() => Boolean(props.module?.authenticated))
const moduleTitle = computed(() => props.module?.title || (isPersonal.value ? 'Персональная витрина' : 'Оглавление товаров'))
const moduleSubtitle = computed(() => props.module?.subtitle || '')
const orderedGoods = computed(() => props.module?.ordered_goods || props.module?.orderedGoods || [])
const recommendedGoods = computed(() => props.module?.recommended_goods || props.module?.recommendedGoods || [])
const classifications = computed(() => props.module?.classifications || [])
const tableOfContents = computed(() => props.module?.table_of_contents || props.module?.tableOfContents || [])

const personalSections = computed(() => [
    {
        key: 'ordered',
        title: 'Ранее заказывали',
        goods: orderedGoods.value,
        empty: 'Истории заказов по вашим Entity пока нет.',
    },
    {
        key: 'recommended',
        title: 'Рекомендации по ОКВЭД',
        goods: recommendedGoods.value,
        empty: 'Назначьте ОКВЭД пользователю, Entity или Unit, чтобы получить рекомендации.',
    },
])

const activeSection = computed(() => personalSections.value.find((section) => section.key === activePersonalSection.value) || personalSections.value[0])

const currentGoods = computed(() => {
    if (isPersonal.value) {
        return activeSection.value.goods || []
    }

    return props.goods || []
})

const hasSearch = computed(() => search.value.trim().length > 0)

const visibleGoods = computed(() => {
    const source = isPersonal.value
        ? currentGoods.value
        : [...currentGoods.value].sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())

    return filterGoods(source).slice(0, props.limit)
})

const groupedGoods = computed(() => {
    const groups = new Map()

    visibleGoods.value.forEach((good) => {
        const products = (good.products || []).length ? good.products : [null]

        products.forEach((product) => {
            const key = product?.id ? `product-${product.id}` : 'no-product'

            if (!groups.has(key)) {
                groups.set(key, {
                    id: product?.id || null,
                    title: product?.rus || product?.name || product?.title || 'Без Product',
                    category: product?.category?.name || null,
                    goods: [],
                })
            }

            groups.get(key).goods.push(good)
        })
    })

    return [...groups.values()]
})

const filteredTableOfContents = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) {
        return tableOfContents.value
    }

    return tableOfContents.value
        .map((section) => {
            const sectionMatches = String(section.title || '').toLowerCase().includes(query)
            const entries = (section.entries || []).filter((entry) => {
                return sectionMatches || String(entry.title || '').toLowerCase().includes(query)
            })

            return {
                ...section,
                entries,
            }
        })
        .filter((section) => section.entries.length)
})

function filterGoods(items) {
    const query = search.value.trim().toLowerCase()

    if (!query) {
        return items
    }

    return items.filter((good) => {
        const productText = (good.products || [])
            .map((product) => [
                product.rus,
                product.eng,
                product.name,
                product.title,
                product.category?.name,
            ].filter(Boolean).join(' '))
            .join(' ')

        const classificationText = (good.industries || good.entity_classifications || good.entityClassifications || [])
            .map((classification) => classification.name || [classification.code, classification.title].filter(Boolean).join(' '))
            .filter(Boolean)
            .join(' ')

        const haystack = [
            good.name,
            good.description,
            good.slug,
            productText,
            classificationText,
        ].filter(Boolean).join(' ').toLowerCase()

        return haystack.includes(query)
    })
}

function goodImage(good) {
    return good.ava_thumb || good.ava_image || logo
}

function latestPrice(good) {
    return good.latest_price || good.latestPrice || good.prices?.[0] || null
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return '—'
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

    const currency = price.currency?.code || 'RUB'

    return `${formatMoney(price.price)} ${currency}`
}

function shortDescription(good) {
    const text = String(good.description || '').trim()

    if (!text) {
        return isPersonal.value && activePersonalSection.value === 'recommended'
            ? 'Рекомендовано по вашему ОКВЭД-профилю'
            : 'Описание пока не заполнено'
    }

    return text.length > 96 ? `${text.slice(0, 96)}…` : text
}

function catalogueEntryHref(entry) {
    const query = encodeURIComponent(entry.title || '')

    return `${route('public.goods.index')}?search=${query}`
}

function catalogueSectionHref(section) {
    return route('category.show', section.slug || section.id)
}

</script>

<template>
    <v-card rounded="xl" elevation="2" class="goods-book-card h-100">
        <div class="goods-book-card__floating goods-book-card__floating--top">
            <v-icon icon="mdi-truck-fast-outline" size="18" />
            <strong>Логистика</strong>
            <span>по региону поставки</span>
        </div>

        <div class="goods-book-card__floating goods-book-card__floating--bottom">
            <v-icon icon="mdi-cash-multiple" size="18" />
            <strong>B2B цены</strong>
            <span>для организаций</span>
        </div>

        <v-card-text class="pb-3">
            <div class="goods-book-card__kicker">
                {{ isPersonal ? 'Ваш каталог' : 'Пищепром-книга' }}
            </div>

            <div class="goods-book-card__headline">
                {{ moduleTitle }}
            </div>

            <div v-if="moduleSubtitle" class="goods-book-card__subtitle">
                {{ moduleSubtitle }}
            </div>

            <v-text-field
                v-model="search"
                class="mt-3"
                variant="outlined"
                density="comfortable"
                :label="isPersonal ? 'Поиск по персональной витрине' : 'Поиск по оглавлению'"
                placeholder="Например: рыба, какао-масло, супы"
                hide-details
                clearable
                prepend-inner-icon="mdi-magnify"
            />
        </v-card-text>

        <v-divider />

        <template v-if="isPersonal">
            <v-card-text class="pt-3 pb-2">
                <div class="personal-switcher">
                    <button
                        v-for="section in personalSections"
                        :key="section.key"
                        type="button"
                        class="personal-switcher__button"
                        :class="{ 'personal-switcher__button--active': activePersonalSection === section.key }"
                        @click="activePersonalSection = section.key"
                    >
                        <span>{{ section.title }}</span>
                        <strong>{{ section.goods.length }}</strong>
                    </button>
                </div>

                <div v-if="classifications.length" class="classification-strip">
                    <span>ОКВЭД</span>
                    <v-chip
                        v-for="classification in classifications"
                        :key="classification.id"
                        size="x-small"
                        color="#800000"
                        variant="tonal"
                    >
                        {{ classification.name }}
                    </v-chip>
                </div>
            </v-card-text>

            <v-card-title class="text-subtitle-1 font-weight-bold pt-0">
                {{ hasSearch ? 'Найденные товары' : activeSection.title }}
            </v-card-title>

            <v-card-text class="goods-book-card__body">
                <v-progress-linear v-if="loading" indeterminate class="mb-3" />

                <template v-if="groupedGoods.length">
                    <div v-for="group in groupedGoods" :key="group.id || 'no-product'" class="product-group">
                        <div class="product-group__header">
                            <div>
                                <div class="product-group__title">
                                    {{ group.title }}
                                </div>

                                <div v-if="group.category" class="product-group__category">
                                    {{ group.category }}
                                </div>
                            </div>

                            <v-chip size="x-small" color="#800000" variant="tonal">
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
                                <v-avatar size="52" rounded="lg" class="good-row__image">
                                    <v-img :src="goodImage(good)" cover />
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

                <v-alert v-else type="info" variant="tonal">
                    {{ hasSearch ? 'Ничего не найдено.' : activeSection.empty }}
                </v-alert>
            </v-card-text>
        </template>

        <template v-else>
            <v-card-text class="goods-book-card__body goods-book-card__body--book">
                <v-progress-linear v-if="loading" indeterminate class="mb-3" />

                <div v-if="filteredTableOfContents.length" class="book-toc">
                    <div class="book-toc__ornament" />

                    <section v-for="section in filteredTableOfContents" :key="section.id" class="book-toc__section">
                        <Link :href="catalogueSectionHref(section)" class="book-toc__section-title">
                            <span>{{ section.title }}</span>
                            <b>{{ section.page }}</b>
                        </Link>

                        <Link
                            v-for="entry in section.entries"
                            :key="`${section.id}-${entry.id}`"
                            :href="catalogueEntryHref(entry)"
                            class="book-toc__entry"
                        >
                            <span>{{ entry.title }}</span>
                            <i />
                            <b>{{ entry.page }}</b>
                        </Link>
                    </section>
                </div>

                <v-alert v-else type="info" variant="tonal">
                    {{ hasSearch ? 'В оглавлении ничего не найдено.' : 'Оглавление пока не собрано.' }}
                </v-alert>
            </v-card-text>
        </template>

    </v-card>
</template>

<style scoped>
.goods-book-card {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 90% 0%, rgba(128, 0, 0, 0.08), transparent 26%),
        #fffdf8;
}

.goods-book-card__floating {
    position: absolute;
    z-index: 4;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 1px 8px;
    min-width: 154px;
    padding: 12px 14px;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 14px 28px rgba(63, 29, 29, 0.14);
    backdrop-filter: blur(8px);
}

.goods-book-card__floating :deep(.v-icon) {
    grid-row: span 2;
    align-self: center;
    color: #800000;
}

.goods-book-card__floating strong {
    color: #800000;
    font-size: 0.96rem;
    line-height: 1.1;
}

.goods-book-card__floating span {
    color: #6b625d;
    font-size: 0.76rem;
    line-height: 1.15;
}

.goods-book-card__floating--top {
    top: 14px;
    right: 14px;
}

.goods-book-card__floating--bottom {
    right: 14px;
    bottom: 14px;
}

.goods-book-card__kicker {
    color: #800000;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.goods-book-card__headline {
    margin-top: 4px;
    color: #2f1b18;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.45rem;
    font-weight: 900;
    line-height: 1.05;
}

.goods-book-card__subtitle {
    margin-top: 7px;
    color: #6b625d;
    font-size: 0.82rem;
    line-height: 1.35;
}

.goods-book-card__body {
    max-height: 520px;
    overflow-y: auto;
}

.goods-book-card__body--book {
    background: linear-gradient(90deg, rgba(79, 44, 25, 0.03), transparent 18%, rgba(79, 44, 25, 0.03));
}

.personal-switcher {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.personal-switcher__button {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 10px;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 14px;
    background: #ffffff;
    color: #4a2525;
    font-size: 0.78rem;
    font-weight: 800;
    text-align: left;
}

.personal-switcher__button--active {
    background: #800000;
    color: #ffffff;
}

.personal-switcher__button strong {
    font-family: 'Courier New', monospace;
}

.classification-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
}

.classification-strip > span {
    color: #7b6d64;
    font-family: 'Courier New', monospace;
    font-size: 0.66rem;
    font-weight: 900;
    letter-spacing: 0.12em;
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
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 14px;
    background: #fff7ed;
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
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 16px;
    background: #ffffff;
    color: inherit;
    text-decoration: none;
    transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.good-row:hover {
    background: #fff8f0;
    box-shadow: 0 8px 22px rgba(92, 0, 0, 0.08);
    transform: translateY(-1px);
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
    align-self: start;
    padding: 5px 8px;
    border-radius: 999px;
    background: #fff1df;
    color: #5c0000;
    font-size: 0.78rem;
    font-weight: 800;
    white-space: nowrap;
}

.book-toc {
    position: relative;
    padding: 6px 2px 2px;
    color: #231f1b;
    font-family: Georgia, 'Times New Roman', serif;
}

.book-toc__ornament {
    height: 1px;
    margin: 0 0 14px;
    background: linear-gradient(90deg, transparent, rgba(155, 82, 36, 0.75), transparent);
}

.book-toc__section {
    margin-bottom: 18px;
}

.book-toc__section-title,
.book-toc__entry {
    display: flex;
    align-items: baseline;
    gap: 8px;
    color: inherit;
    text-decoration: none;
}

.book-toc__section-title {
    margin-bottom: 7px;
    font-size: 1.02rem;
    font-weight: 900;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.book-toc__section-title b,
.book-toc__entry b {
    flex: 0 0 auto;
    color: #4f3f35;
    font-weight: 900;
}

.book-toc__entry {
    padding: 2px 0;
    font-size: 0.86rem;
    font-weight: 700;
    line-height: 1.25;
}

.book-toc__entry:hover span,
.book-toc__section-title:hover span {
    color: #800000;
}

.book-toc__entry i {
    flex: 1 1 auto;
    min-width: 16px;
    border-bottom: 1px dotted rgba(65, 46, 32, 0.35);
}

@media (max-width: 600px) {
    .personal-switcher {
        grid-template-columns: 1fr;
    }

    .goods-book-card__floating {
        position: static;
        display: inline-grid;
        margin: 8px 8px 0;
    }

    .good-row {
        grid-template-columns: 48px minmax(0, 1fr);
    }

    .good-row__price {
        grid-column: 2;
        justify-self: start;
    }
}
</style>
