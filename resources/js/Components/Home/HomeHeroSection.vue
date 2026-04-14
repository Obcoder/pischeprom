<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

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
})

const collageItems = computed(() => {
    const shuffled = [...props.heroGoods]
        .sort(() => Math.random() - 0.5)

    return shuffled.map((item, index) => {
        const patterns = [
            'tall',
            'wide',
            'square',
            'square',
            'medium',
            'square',
            'wide',
            'tall',
            'square',
            'medium',
        ]

        return {
            ...item,
            layout: patterns[index % patterns.length],
        }
    })
})

const quickQueries = [
    'Лецитин',
    'Глицерин',
    'Какао-масло',
    'Эмульгаторы',
    'Красители',
    'Консерванты',
]

const heroCategories = [
    {
        title: 'Рыба',
        description: 'Сырьё и товарные позиции для рыбной промышленности',
        href: '/Seaprom',
        icon: 'mdi-fish',
    },
    {
        title: 'Овощи',
        description: 'Продукция и направления для овощного сегмента',
        href: '/vegetables',
        icon: 'mdi-carrot',
    },
    {
        title: 'Бакалея',
        description: 'Сухие смеси, сыпучие товары и ингредиенты',
        href: '/grocery',
        icon: 'mdi-package-variant-closed',
    },
]

const advantages = [
    'Фото и описания товаров',
    'Категории и быстрый поиск',
    'Характеристики и назначение',
    'Товары для B2B-задач',
]

function submitSearch() {
    const value = search.value?.trim() || ''

    router.get(route('goods'), {
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
        <v-container class="py-8 py-md-12">
            <v-row align="center" class="hero-v2__row">
                <!-- Левая колонка -->
                <v-col cols="12" lg="7">
                    <div class="hero-v2__content">
                        <div class="hero-v2__badge mb-4">
                            Маркетплейс для пищевой промышленности
                        </div>

                        <h1 class="hero-v2__title mb-4">
                            Пищевое сырьё, ингредиенты и товары
                            <span class="hero-v2__title-accent">для производственных задач</span>
                        </h1>

                        <p class="hero-v2__subtitle mb-6">
                            Главная стартовая страница должна быстро отвечать на вопрос:
                            <strong>что можно купить, к какой категории относится товар, как он выглядит и где его применять.</strong>
                            Именно на это и работает этот hero-блок.
                        </p>

                        <!-- Поиск -->
                        <div class="hero-v2__search mb-4">
                            <v-text-field
                                v-model="search"
                                label="Поиск по каталогу"
                                placeholder="Например: лецитин, глицерин, эмульгаторы, какао-масло"
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
                                color="#800000"
                                size="x-large"
                                rounded="xl"
                                class="hero-v2__search-btn"
                                @click="submitSearch"
                            >
                                Найти
                            </v-btn>
                        </div>

                        <!-- Популярные запросы -->
                        <div class="mb-8">
                            <div class="text-body-2 text-medium-emphasis mb-3">
                                Быстрые запросы:
                            </div>

                            <div class="d-flex flex-wrap ga-2">
                                <v-chip
                                    v-for="query in quickQueries"
                                    :key="query"
                                    variant="outlined"
                                    color="#800000"
                                    class="cursor-pointer"
                                    @click="applyQuickQuery(query)"
                                >
                                    {{ query }}
                                </v-chip>
                            </div>
                        </div>

                        <!-- Преимущества -->
                        <div class="hero-v2__advantages mb-8">
                            <div
                                v-for="item in advantages"
                                :key="item"
                                class="hero-v2__advantage"
                            >
                                <v-icon size="18" color="#800000">
                                    mdi-check-circle
                                </v-icon>
                                <span>{{ item }}</span>
                            </div>
                        </div>

                        <!-- Статистика -->
                        <v-row>
                            <v-col cols="12" sm="6">
                                <v-card
                                    rounded="xl"
                                    elevation="2"
                                    class="hero-v2__stat-card"
                                >
                                    <v-card-text>
                                        <div class="hero-v2__stat-value">
                                            {{ stats.productsCount }}
                                        </div>
                                        <div class="hero-v2__stat-label">
                                            товарных наименований
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>

                            <v-col cols="12" sm="6">
                                <v-card
                                    rounded="xl"
                                    elevation="2"
                                    class="hero-v2__stat-card"
                                >
                                    <v-card-text>
                                        <div class="hero-v2__stat-value">
                                            {{ stats.goodsCount }}
                                        </div>
                                        <div class="hero-v2__stat-label">
                                            товаров в каталоге
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </div>
                </v-col>

                <!-- Правая колонка -->
                <v-col cols="12" lg="5">
                    <v-card
                        rounded="xl"
                        elevation="4"
                        class="hero-v2__visual-main overflow-hidden"
                    >
                        <div class="hero-v2__collage">
                            <Link
                                v-for="item in collageItems"
                                :key="item.id"
                                :href="route('goods.show', item.slug)"
                                class="hero-v2__collage-item"
                                :class="`hero-v2__collage-item--${item.layout}`"
                            >
                                <div class="hero-v2__collage-media">
                                    <img
                                        :src="item.ava_thumb"
                                        :alt="item.name"
                                        loading="lazy"
                                    >

                                    <div class="hero-v2__collage-overlay">
                                        <div class="hero-v2__collage-name">
                                            {{ item.name }}
                                        </div>
                                        <div class="hero-v2__collage-action">
                                            Смотреть товар
                                        </div>
                                    </div>
                                </div>
                            </Link>

                            <div class="hero-v2__visual-overlay">
                                <div class="hero-v2__visual-top-chip">
                                    Каталог пищевой промышленности
                                </div>

                                <div class="hero-v2__visual-bottom-card">
                                    <div class="hero-v2__visual-bottom-title">
                                        Удобный вход в категории
                                    </div>
                                    <div class="hero-v2__visual-bottom-text">
                                        Быстрый переход к товарам, фото, описаниям и характеристикам
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-card>

                    <!-- Плитки категорий -->
                    <div class="hero-v2__category-grid">
                        <Link
                            v-for="item in heroCategories"
                            :key="item.title"
                            :href="item.href"
                            class="hero-v2__category-link"
                        >
                            <v-card
                                rounded="xl"
                                elevation="4"
                                class="hero-v2__category-card"
                            >
                                <v-card-text class="pa-4">
                                    <div class="hero-v2__category-icon-wrap mb-3">
                                        <v-icon size="24" color="#800000">
                                            {{ item.icon }}
                                        </v-icon>
                                    </div>

                                    <div class="hero-v2__category-title mb-2">
                                        {{ item.title }}
                                    </div>

                                    <div class="hero-v2__category-description">
                                        {{ item.description }}
                                    </div>
                                </v-card-text>
                            </v-card>
                        </Link>
                    </div>
                </v-col>
            </v-row>
        </v-container>
    </section>
</template>

<style scoped>
.hero-v2 {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at top left, rgba(128, 0, 0, 0.12), transparent 24%),
        radial-gradient(circle at bottom right, rgba(128, 0, 0, 0.08), transparent 24%),
        linear-gradient(180deg, #fff8f5 0%, #fffdfb 58%, #ffffff 100%);
}

.hero-v2__row {
    position: relative;
    z-index: 2;
}

.hero-v2__content {
    max-width: 760px;
}

.hero-v2__badge {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.08);
    color: #800000;
    font-size: 0.95rem;
    font-weight: 700;
}

.hero-v2__title {
    font-size: clamp(2.2rem, 4vw, 4rem);
    line-height: 1.05;
    font-weight: 900;
    color: #262626;
    max-width: 820px;
}

.hero-v2__title-accent {
    color: #800000;
}

.hero-v2__subtitle {
    font-size: 1.08rem;
    line-height: 1.75;
    color: rgba(0, 0, 0, 0.72);
    max-width: 700px;
}

.hero-v2__search {
    display: grid;
    grid-template-columns: 1fr 160px;
    gap: 12px;
    align-items: center;
}

.hero-v2__search-btn {
    height: 56px;
    font-weight: 700;
    letter-spacing: 0.2px;
}

.hero-v2__advantages {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 16px;
}

.hero-v2__advantage {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(0, 0, 0, 0.76);
    font-weight: 500;
}

.hero-v2__stat-card {
    border: 1px solid rgba(128, 0, 0, 0.08);
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(6px);
}

.hero-v2__stat-value {
    font-size: 2.1rem;
    line-height: 1;
    font-weight: 900;
    color: #800000;
    margin-bottom: 10px;
}

.hero-v2__stat-label {
    font-size: 1rem;
    color: rgba(0, 0, 0, 0.68);
}

.hero-v2__visual {
    position: relative;
}

.hero-v2__visual-main {
    position: relative;
    z-index: 1;
    min-height: 460px;
    border-radius: 24px;
    overflow: hidden;
    background:
        linear-gradient(135deg, rgba(128, 0, 0, 0.08), rgba(255, 255, 255, 0.4)),
        #fff;
}

.hero-v2__collage {
    position: relative;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-auto-rows: 96px;
    gap: 10px;
    min-height: 460px;
    padding: 12px;
    background:
        radial-gradient(circle at top left, rgba(128, 0, 0, 0.10), transparent 30%),
        linear-gradient(180deg, #fff7f4 0%, #fffdfc 100%);
}

.hero-v2__collage-item {
    position: relative;
    display: block;
    overflow: hidden;
    border-radius: 18px;
    text-decoration: none;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.10);
    transform: translateY(0) scale(1);
    transition:
        transform 0.28s ease,
        box-shadow 0.28s ease,
        filter 0.28s ease;
}

.hero-v2__collage-item:hover {
    transform: translateY(-6px) scale(1.02);
    box-shadow: 0 16px 30px rgba(0, 0, 0, 0.16);
    z-index: 3;
}

.hero-v2__collage-item--square {
    grid-column: span 1;
    grid-row: span 1;
}

.hero-v2__collage-item--medium {
    grid-column: span 1;
    grid-row: span 2;
}

.hero-v2__collage-item--wide {
    grid-column: span 2;
    grid-row: span 1;
}

.hero-v2__collage-item--tall {
    grid-column: span 2;
    grid-row: span 2;
}

.hero-v2__collage-media {
    position: relative;
    width: 100%;
    height: 100%;
}

.hero-v2__collage-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transform: scale(1.01);
    transition: transform 0.4s ease, filter 0.4s ease;
}

.hero-v2__collage-item:hover .hero-v2__collage-media img {
    transform: scale(1.08);
    filter: saturate(1.05) contrast(1.03);
}

.hero-v2__collage-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 12px;
    background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.66) 0%,
        rgba(0, 0, 0, 0.22) 45%,
        rgba(0, 0, 0, 0.02) 100%
    );
    opacity: 0.96;
}

.hero-v2__collage-name {
    font-size: 0.92rem;
    line-height: 1.3;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.22);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hero-v2__collage-action {
    margin-top: 6px;
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.hero-v2__visual-overlay {
    pointer-events: none;
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 20px;
}

@media (max-width: 960px) {
    .hero-v2__visual-main {
        min-height: 360px;
    }

    .hero-v2__collage {
        grid-template-columns: repeat(3, 1fr);
        grid-auto-rows: 92px;
        min-height: 360px;
    }

    .hero-v2__collage-item--wide,
    .hero-v2__collage-item--tall {
        grid-column: span 1;
        grid-row: span 1;
    }
}

@media (max-width: 600px) {
    .hero-v2__collage {
        grid-template-columns: repeat(2, 1fr);
        grid-auto-rows: 88px;
    }

    .hero-v2__collage-name {
        font-size: 0.82rem;
    }
}

.hero-v2__visual-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 20px;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.34), rgba(0, 0, 0, 0.04));
}

.hero-v2__visual-top-chip {
    align-self: flex-start;
    display: inline-flex;
    padding: 10px 14px;
    border-radius: 999px;
    background: rgba(255, 250, 245, 0.94);
    color: #800000;
    font-weight: 700;
    font-size: 0.92rem;
}

.hero-v2__visual-bottom-card {
    align-self: flex-end;
    max-width: 280px;
    padding: 16px 18px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.hero-v2__visual-bottom-title {
    font-size: 1rem;
    font-weight: 800;
    color: #2b2b2b;
    margin-bottom: 8px;
}

.hero-v2__visual-bottom-text {
    font-size: 0.95rem;
    line-height: 1.55;
    color: rgba(0, 0, 0, 0.7);
}

.hero-v2__category-grid {
    position: relative;
    z-index: 2;
    margin-top: -54px;
    padding-left: 18px;
    padding-right: 18px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}

.hero-v2__category-link {
    text-decoration: none;
}

.hero-v2__category-card {
    height: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    background: rgba(255, 255, 255, 0.98);
}

.hero-v2__category-card:hover {
    transform: translateY(-4px);
}

.hero-v2__category-icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: rgba(128, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-v2__category-title {
    font-size: 1rem;
    font-weight: 800;
    color: #2f2f2f;
}

.hero-v2__category-description {
    font-size: 0.9rem;
    line-height: 1.5;
    color: rgba(0, 0, 0, 0.66);
}

@media (max-width: 1264px) {
    .hero-v2__category-grid {
        grid-template-columns: 1fr;
        margin-top: 16px;
        padding-left: 0;
        padding-right: 0;
    }
}

@media (max-width: 960px) {
    .hero-v2__search {
        grid-template-columns: 1fr;
    }

    .hero-v2__advantages {
        grid-template-columns: 1fr;
    }

    .hero-v2__visual-main :deep(.v-img) {
        min-height: 360px;
    }
}

@media (max-width: 600px) {
    .hero-v2__title {
        font-size: 2rem;
    }

    .hero-v2__subtitle {
        font-size: 1rem;
        line-height: 1.65;
    }

    .hero-v2__visual-bottom-card {
        max-width: 100%;
    }
}
</style>
