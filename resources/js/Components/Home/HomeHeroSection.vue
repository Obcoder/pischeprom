<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { logo } from '@/Pages/Helpers/consts.js'
import { useAppRoute } from '@/Composables/useAppRoute'

const { route } = useAppRoute()

const { goodPublicUrl } = usePublicGoodUrl()

const props = defineProps({
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
    categories: {
        type: Array,
        default: () => [],
    },
})

const search = ref('')

const previewGoods = computed(() => props.heroGoods.slice(0, 7))
const leadGood = computed(() => previewGoods.value[0] || null)
const sideGoods = computed(() => previewGoods.value.slice(1, 7))

const quickQueries = [
    'Лецитин',
    'Глицерин',
    'Какао-масло',
    'Эмульгаторы',
    'Красители',
    'Консерванты',
]

const heroCategoryTargets = [
    {
        title: 'Рыба',
        description: 'Филе, морепродукты и позиции для переработки',
        aliases: ['рыба'],
        slug: 'ryba',
        icon: 'mdi-fish',
    },
    {
        title: 'Овощи',
        description: 'Заморозка, смеси, нарезка и полуфабрикаты',
        aliases: ['овощи'],
        slug: 'ovoschi',
        icon: 'mdi-carrot',
    },
    {
        title: 'Бакалея',
        description: 'Сыпучие ингредиенты, добавки и сухие смеси',
        aliases: ['бакалея'],
        search: 'Бакалея',
        icon: 'mdi-package-variant-closed',
    },
    {
        title: 'Ягоды',
        description: 'Замороженные ягоды, фрукты и смеси',
        aliases: ['ягоды', 'ягоды и фрукты'],
        slug: 'yagody-i-frukty',
        icon: 'mdi-fruit-cherries',
    },
]

const advantages = [
    'Найти товар по названию',
    'Перейти в нужную категорию',
    'Посмотреть фото и описание',
    'Собрать заявку для закупки',
]

const customerTasks = [
    {
        title: 'Закупить сырьё',
        text: 'быстро открыть позиции для производства',
        icon: 'mdi-cart-arrow-down',
        query: 'сырьё',
    },
    {
        title: 'Найти добавку',
        text: 'эмульгаторы, красители, консерванты',
        icon: 'mdi-flask-outline',
        query: 'эмульгаторы',
    },
    {
        title: 'Подобрать замену',
        text: 'сравнить похожие товарные позиции',
        icon: 'mdi-swap-horizontal',
        query: 'зам.',
    },
]

const availableCategories = computed(() => {
    return Array.isArray(props.categories)
        ? props.categories.filter((category) => category?.id && category?.name)
        : []
})

const resolvedHeroCategories = computed(() => {
    return heroCategoryTargets.map((item) => ({
        ...item,
        href: categoryHref(item),
    }))
})

function normalizeText(value) {
    return String(value || '').trim().toLowerCase()
}

function findCategory(target) {
    const aliases = [target.title, ...(target.aliases || [])].map(normalizeText)

    return availableCategories.value.find((category) => {
        const name = normalizeText(category.name)

        return aliases.some((alias) => name.includes(alias) || alias.includes(name))
    })
}

function categoryHref(target) {
    const category = findCategory(target)
    const categoryParam = category?.slug || category?.id || target.slug

    if (categoryParam) {
        return route('category.show', categoryParam)
    }

    const searchQuery = target.search || target.title

    return `${route('public.goods.index')}?search=${encodeURIComponent(searchQuery)}`
}

function leadGoodImage(good) {
    const mediaImage = (good?.published_media || good?.publishedMedia || [])
        .find((media) => media.type === 'image')

    return mediaImage?.url || good?.ava_image || mediaImage?.thumb_url || good?.ava_thumb || logo
}

function goodImage(good) {
    const mediaImage = (good?.published_media || good?.publishedMedia || [])
        .find((media) => media.type === 'image')

    return mediaImage?.thumb_url || good?.ava_thumb || mediaImage?.url || good?.ava_image || logo
}

function submitSearch() {
    const value = search.value?.trim() || ''

    router.get(route('public.goods.index'), {
        search: value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function applyQuickQuery(query) {
    search.value = query
    submitSearch()
}
</script>

<template>
    <section class="hero-v2">
        <div class="hero-v2__grain" />
        <div class="hero-v2__orb hero-v2__orb--one" />
        <div class="hero-v2__orb hero-v2__orb--two" />

        <v-container class="py-7 py-md-11">
            <v-row align="center" class="hero-v2__row">
                <v-col cols="12" lg="6">
                    <div class="hero-v2__content">
                        <div class="hero-v2__badge">
                            <v-icon icon="mdi-sparkles" size="16" />
                            Каталог для закупки и производства
                        </div>

                        <h1 class="hero-v2__title">
                            Найдите пищевое сырьё
                            <span>под свою задачу</span>
                        </h1>

                        <p class="hero-v2__subtitle">
                            Ищите ингредиенты, добавки, заморозку и бакалею по названию,
                            назначению или категории. Смотрите фото, описание и быстро
                            переходите к нужной товарной позиции.
                        </p>

                        <div class="hero-v2__finder">
                            <div class="hero-v2__finder-label">
                                Что нужно найти?
                            </div>

                            <div class="hero-v2__search">
                                <v-text-field
                                    v-model="search"
                                    placeholder="Например: лецитин, глицерин, какао-масло"
                                    variant="solo"
                                    bg-color="white"
                                    density="comfortable"
                                    rounded="xl"
                                    hide-details
                                    clearable
                                    prepend-inner-icon="mdi-magnify"
                                    class="hero-v2__search-field"
                                    @keyup.enter="submitSearch"
                                />

                                <v-btn
                                    color="#8a100c"
                                    size="x-large"
                                    rounded="xl"
                                    class="hero-v2__search-btn"
                                    @click="submitSearch"
                                >
                                    Найти
                                </v-btn>
                            </div>

                            <div class="hero-v2__quick">
                                <button
                                    v-for="query in quickQueries"
                                    :key="query"
                                    type="button"
                                    class="hero-v2__quick-chip"
                                    @click="applyQuickQuery(query)"
                                >
                                    {{ query }}
                                </button>
                            </div>
                        </div>

                        <div class="hero-v2__task-grid">
                            <button
                                v-for="task in customerTasks"
                                :key="task.title"
                                type="button"
                                class="hero-v2__task-card"
                                @click="applyQuickQuery(task.query)"
                            >
                                <span class="hero-v2__task-icon">
                                    <v-icon :icon="task.icon" size="18" />
                                </span>
                                <span>
                                    <strong>{{ task.title }}</strong>
                                    <small>{{ task.text }}</small>
                                </span>
                            </button>
                        </div>

                        <div class="hero-v2__advantages">
                            <div
                                v-for="item in advantages"
                                :key="item"
                                class="hero-v2__advantage"
                            >
                                <v-icon icon="mdi-check-bold" size="14" />
                                {{ item }}
                            </div>
                        </div>
                    </div>
                </v-col>

                <v-col cols="12" lg="6">
                    <div class="hero-v2__showcase">
                        <div class="hero-v2__showcase-head">
                            <div>
                                <span>Живая витрина</span>
                                <strong>популярные позиции каталога</strong>
                            </div>

                            <Link :href="route('public.goods.index')" class="hero-v2__showcase-link">
                                В каталог
                                <v-icon icon="mdi-arrow-right" size="16" />
                            </Link>
                        </div>

                        <div v-if="leadGood" class="hero-v2__goods-layout">
                            <Link
                                :href="goodPublicUrl(leadGood)"
                                class="hero-v2__lead-good"
                            >
                                <img
                                    :src="leadGoodImage(leadGood)"
                                    :alt="leadGood.name"
                                    loading="eager"
                                    decoding="async"
                                    fetchpriority="high"
                                >

                                <span class="hero-v2__lead-badge">Смотреть товар</span>

                                <div class="hero-v2__lead-copy">
                                    <small>Витрина каталога</small>
                                    <strong>{{ leadGood.name }}</strong>
                                </div>
                            </Link>

                            <div class="hero-v2__side-goods">
                                <Link
                                    v-for="item in sideGoods"
                                    :key="item.id"
                                    :href="goodPublicUrl(item)"
                                    class="hero-v2__side-good"
                                >
                                    <img
                                        :src="goodImage(item)"
                                        :alt="item.name"
                                        loading="lazy"
                                    >

                                    <span>{{ item.name }}</span>
                                </Link>
                            </div>
                        </div>

                        <div v-else class="hero-v2__empty-showcase">
                            Товары для витрины появятся после публикации каталога.
                        </div>

                        <div class="hero-v2__meta-strip">
                            <div class="hero-v2__meta-card">
                                <strong>{{ stats.productsCount }}</strong>
                                <span>наименований</span>
                            </div>

                            <div class="hero-v2__meta-card">
                                <strong>{{ stats.goodsCount }}</strong>
                                <span>товаров</span>
                            </div>

                            <div class="hero-v2__meta-note">
                                Фото, категории и описания в одном месте.
                            </div>
                        </div>
                    </div>

                    <div class="hero-v2__category-grid">
                        <Link
                            v-for="item in resolvedHeroCategories"
                            :key="item.title"
                            :href="item.href"
                            class="hero-v2__category-link"
                        >
                            <div class="hero-v2__category-card">
                                <div class="hero-v2__category-icon-wrap">
                                    <v-icon size="22">
                                        {{ item.icon }}
                                    </v-icon>
                                </div>

                                <div>
                                    <div class="hero-v2__category-title">
                                        {{ item.title }}
                                    </div>

                                    <div class="hero-v2__category-description">
                                        {{ item.description }}
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </v-col>
            </v-row>
        </v-container>
    </section>
</template>

<style scoped>
.hero-v2 {
    --hero-ink: #241817;
    --hero-muted: #6b5b54;
    --hero-red: #8a100c;
    --hero-red-dark: #5f0a08;
    --hero-cream: #fff7ef;
    --hero-sand: #ead7c8;
    --hero-line: rgba(138, 16, 12, 0.14);
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 7% 14%, rgba(138, 16, 12, 0.13), transparent 28%),
        radial-gradient(circle at 88% 6%, rgba(201, 139, 68, 0.18), transparent 24%),
        linear-gradient(135deg, #fffaf6 0%, #fff4eb 45%, #ffffff 100%);
    color: var(--hero-ink);
    font-family: "Comfortaa", "Trebuchet MS", sans-serif;
}

.hero-v2__grain {
    position: absolute;
    inset: 0;
    opacity: 0.34;
    pointer-events: none;
    background-image:
        linear-gradient(rgba(138, 16, 12, 0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(138, 16, 12, 0.03) 1px, transparent 1px);
    background-size: 42px 42px;
    mask-image: linear-gradient(90deg, rgba(0, 0, 0, 0.76), transparent 72%);
}

.hero-v2__orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(4px);
    pointer-events: none;
}

.hero-v2__orb--one {
    left: -120px;
    top: 90px;
    width: 280px;
    height: 280px;
    background: rgba(138, 16, 12, 0.08);
}

.hero-v2__orb--two {
    right: -140px;
    bottom: 30px;
    width: 360px;
    height: 360px;
    background: rgba(210, 142, 64, 0.16);
}

.hero-v2__row,
.hero-v2__content,
.hero-v2__showcase {
    position: relative;
    z-index: 1;
}

.hero-v2__content {
    max-width: 760px;
}

.hero-v2__badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 18px;
    padding: 9px 14px;
    border: 1px solid var(--hero-line);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.72);
    box-shadow: 0 12px 28px rgba(95, 10, 8, 0.08);
    color: var(--hero-red);
    font-size: 0.83rem;
    font-weight: 900;
    letter-spacing: 0.02em;
}

.hero-v2__title {
    max-width: 760px;
    margin: 0;
    color: var(--hero-ink);
    font-size: clamp(2.55rem, 5.7vw, 6.4rem);
    font-weight: 950;
    letter-spacing: -0.07em;
    line-height: 0.93;
}

.hero-v2__title span {
    display: block;
    margin-top: 4px;
    color: var(--hero-red);
}

.hero-v2__subtitle {
    max-width: 690px;
    margin: 22px 0 0;
    color: var(--hero-muted);
    font-size: clamp(1rem, 1.4vw, 1.2rem);
    line-height: 1.72;
}

.hero-v2__finder {
    max-width: 780px;
    margin-top: 28px;
    padding: 14px;
    border: 1px solid rgba(138, 16, 12, 0.11);
    border-radius: 30px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 54px rgba(79, 38, 22, 0.13);
    backdrop-filter: blur(10px);
}

.hero-v2__finder-label {
    margin: 0 0 10px 6px;
    color: var(--hero-red-dark);
    font-size: 0.82rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.hero-v2__search {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 154px;
    gap: 10px;
    align-items: center;
}

.hero-v2__search-field :deep(.v-field) {
    border: 1px solid rgba(36, 24, 23, 0.08);
    box-shadow: none;
}

.hero-v2__search-btn {
    height: 56px;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    box-shadow: 0 16px 34px rgba(138, 16, 12, 0.28);
}

.hero-v2__quick {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.hero-v2__quick-chip {
    padding: 8px 12px;
    border: 1px solid rgba(138, 16, 12, 0.18);
    border-radius: 999px;
    background: rgba(255, 250, 246, 0.86);
    color: var(--hero-red);
    cursor: pointer;
    font: inherit;
    font-size: 0.86rem;
    font-weight: 800;
    transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
}

.hero-v2__quick-chip:hover {
    transform: translateY(-2px);
    background: var(--hero-red);
    color: #fff;
}

.hero-v2__task-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 18px;
}

.hero-v2__task-card {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-height: 104px;
    padding: 14px;
    border: 1px solid rgba(138, 16, 12, 0.12);
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.64);
    color: var(--hero-ink);
    cursor: pointer;
    font: inherit;
    text-align: left;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.hero-v2__task-card:hover {
    transform: translateY(-4px);
    border-color: rgba(138, 16, 12, 0.26);
    box-shadow: 0 18px 36px rgba(79, 38, 22, 0.12);
}

.hero-v2__task-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    flex: 0 0 auto;
    border-radius: 14px;
    background: #fff1e8;
    color: var(--hero-red);
}

.hero-v2__task-card strong,
.hero-v2__task-card small {
    display: block;
}

.hero-v2__task-card strong {
    font-size: 0.92rem;
    font-weight: 950;
}

.hero-v2__task-card small {
    margin-top: 5px;
    color: var(--hero-muted);
    font-size: 0.74rem;
    line-height: 1.45;
}

.hero-v2__advantages {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 14px;
    margin-top: 22px;
}

.hero-v2__advantage {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #4b403c;
    font-size: 0.92rem;
    font-weight: 800;
}

.hero-v2__advantage :deep(.v-icon) {
    color: var(--hero-red);
}

.hero-v2__showcase {
    overflow: hidden;
    padding: 18px;
    border: 1px solid rgba(138, 16, 12, 0.10);
    border-radius: 36px;
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.83), rgba(255, 243, 234, 0.74)),
        #fff;
    box-shadow: 0 32px 70px rgba(62, 35, 24, 0.18);
    backdrop-filter: blur(14px);
}

.hero-v2__showcase-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
}

.hero-v2__showcase-head span,
.hero-v2__showcase-head strong {
    display: block;
}

.hero-v2__showcase-head span {
    color: var(--hero-red);
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.hero-v2__showcase-head strong {
    margin-top: 4px;
    color: var(--hero-ink);
    font-size: 1.1rem;
    font-weight: 950;
}

.hero-v2__showcase-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex: 0 0 auto;
    padding: 9px 12px;
    border-radius: 999px;
    background: #fff;
    color: var(--hero-red);
    font-size: 0.82rem;
    font-weight: 900;
    text-decoration: none;
}

.hero-v2__goods-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.04fr) minmax(230px, 0.96fr);
    gap: 12px;
}

.hero-v2__lead-good,
.hero-v2__side-good {
    position: relative;
    overflow: hidden;
    display: block;
    color: #fff;
    text-decoration: none;
    background: #2d2926;
}

.hero-v2__lead-good {
    min-height: 430px;
    border-radius: 30px;
    box-shadow: 0 22px 44px rgba(0, 0, 0, 0.18);
}

.hero-v2__lead-good img,
.hero-v2__side-good img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transform: scale(1.01);
    transition: transform 0.35s ease, filter 0.35s ease;
}

.hero-v2__lead-good::after,
.hero-v2__side-good::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.68), rgba(0, 0, 0, 0.12) 56%, transparent);
}

.hero-v2__lead-good:hover img,
.hero-v2__side-good:hover img {
    transform: scale(1.07);
    filter: saturate(1.08) contrast(1.04);
}

.hero-v2__lead-badge,
.hero-v2__lead-copy,
.hero-v2__side-good span {
    position: absolute;
    z-index: 1;
}

.hero-v2__lead-badge {
    top: 16px;
    left: 16px;
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.88);
    color: var(--hero-red);
    font-size: 0.78rem;
    font-weight: 950;
}

.hero-v2__lead-copy {
    right: 18px;
    bottom: 18px;
    left: 18px;
}

.hero-v2__lead-copy small,
.hero-v2__lead-copy strong {
    display: block;
}

.hero-v2__lead-copy small {
    margin-bottom: 7px;
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.82rem;
    font-weight: 800;
}

.hero-v2__lead-copy strong {
    font-size: clamp(1.35rem, 2vw, 2rem);
    font-weight: 950;
    line-height: 1.14;
}

.hero-v2__side-goods {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.hero-v2__side-good {
    min-height: 132px;
    border-radius: 24px;
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.13);
}

.hero-v2__side-good span {
    right: 12px;
    bottom: 12px;
    left: 12px;
    display: -webkit-box;
    overflow: hidden;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 950;
    line-height: 1.24;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.hero-v2__empty-showcase {
    display: flex;
    min-height: 260px;
    align-items: center;
    justify-content: center;
    border: 1px dashed rgba(138, 16, 12, 0.2);
    border-radius: 28px;
    color: var(--hero-muted);
    text-align: center;
}

.hero-v2__meta-strip {
    display: grid;
    grid-template-columns: 0.8fr 0.8fr 1.5fr;
    gap: 10px;
    margin-top: 12px;
}

.hero-v2__meta-card,
.hero-v2__meta-note {
    min-height: 82px;
    padding: 14px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(138, 16, 12, 0.10);
}

.hero-v2__meta-card strong,
.hero-v2__meta-card span {
    display: block;
}

.hero-v2__meta-card strong {
    color: var(--hero-red);
    font-size: 1.8rem;
    font-weight: 950;
    line-height: 1;
}

.hero-v2__meta-card span,
.hero-v2__meta-note {
    color: var(--hero-muted);
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1.4;
}

.hero-v2__category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    gap: 10px;
    margin-top: 12px;
}

.hero-v2__category-link {
    color: inherit;
    text-decoration: none;
}

.hero-v2__category-card {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-height: 112px;
    padding: 14px;
    border: 1px solid rgba(138, 16, 12, 0.10);
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.74);
    box-shadow: 0 18px 34px rgba(79, 38, 22, 0.10);
    transition: transform 0.18s ease, border-color 0.18s ease;
}

.hero-v2__category-card:hover {
    transform: translateY(-4px);
    border-color: rgba(138, 16, 12, 0.24);
}

.hero-v2__category-icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    border-radius: 15px;
    background: #fff1e8;
    color: var(--hero-red);
}

.hero-v2__category-title {
    color: var(--hero-ink);
    font-size: 0.92rem;
    font-weight: 950;
}

.hero-v2__category-description {
    margin-top: 4px;
    color: var(--hero-muted);
    font-size: 0.73rem;
    font-weight: 700;
    line-height: 1.42;
}

@media (max-width: 1264px) {
    .hero-v2__content {
        max-width: none;
    }

    .hero-v2__showcase {
        margin-top: 22px;
    }
}

@media (max-width: 960px) {
    .hero-v2__goods-layout {
        grid-template-columns: 1fr;
    }

    .hero-v2__lead-good {
        min-height: 320px;
    }

    .hero-v2__meta-strip,
    .hero-v2__category-grid,
    .hero-v2__task-grid {
        grid-template-columns: 1fr;
    }

    .hero-v2__advantages {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .hero-v2__title {
        font-size: 2.55rem;
        letter-spacing: -0.055em;
    }

    .hero-v2__finder,
    .hero-v2__showcase {
        border-radius: 24px;
        padding: 12px;
    }

    .hero-v2__search {
        grid-template-columns: 1fr;
    }

    .hero-v2__search-btn {
        width: 100%;
    }

    .hero-v2__side-goods {
        grid-template-columns: 1fr;
    }

    .hero-v2__showcase-head {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
