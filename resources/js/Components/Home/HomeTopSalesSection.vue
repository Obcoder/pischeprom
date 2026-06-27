<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import SectionHeader from './SectionHeader.vue'

const props = defineProps({
    goods: {
        type: Array,
        default: () => [],
    },
})

const { goodPublicUrl } = usePublicGoodUrl()

const visibleGoods = computed(() => props.goods.slice(0, 8))

function goodImage(good) {
    return good.image || good.ava_image || good.ava_thumb || '/default-image.jpg'
}

function productLabel(good) {
    const product = good.products?.[0]

    return product?.rus || product?.eng || product?.category?.name || 'Позиция каталога'
}

function formatNumber(value, digits = 0) {
    const number = Number(value || 0)

    return new Intl.NumberFormat('ru-RU', {
        maximumFractionDigits: digits,
    }).format(number)
}

function formatDate(value) {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'long',
    }).format(date)
}
</script>

<template>
    <section
        v-if="visibleGoods.length"
        class="top-sales-section"
    >
        <v-container>
            <SectionHeader
                title="Популярные товары"
                subtitle="Top-продаж по реальным данным Sales: позиции, которые уже покупают и повторно выбирают"
            />

            <div class="top-sales-grid">
                <Link
                    v-for="good in visibleGoods"
                    :key="good.id"
                    :href="goodPublicUrl(good, false)"
                    class="top-sales-card"
                >
                    <div class="top-sales-card__media">
                        <img
                            :src="goodImage(good)"
                            :alt="good.name"
                            loading="lazy"
                        >
                        <span class="top-sales-card__rank">
                            #{{ good.rank }}
                        </span>
                        <span class="top-sales-card__badge">
                            Top-продаж
                        </span>
                    </div>

                    <div class="top-sales-card__body">
                        <div class="top-sales-card__category">
                            {{ productLabel(good) }}
                        </div>

                        <h3>{{ good.name }}</h3>

                        <div class="top-sales-card__chips">
                            <span v-if="good.country" class="top-sales-card__chip">
                                <img
                                    v-if="good.country.flag"
                                    :src="good.country.flag"
                                    :alt="good.country.name"
                                >
                                {{ good.country.name }}
                            </span>
                            <span class="top-sales-card__chip">
                                {{ formatNumber(good.sales_count) }} продаж
                            </span>
                        </div>

                        <div class="top-sales-card__metrics">
                            <div>
                                <strong>{{ formatNumber(good.quantity, 1) }}</strong>
                                <span>объем спроса</span>
                            </div>
                            <div>
                                <strong>{{ formatDate(good.last_sale_date) || 'активно' }}</strong>
                                <span>последняя продажа</span>
                            </div>
                        </div>

                        <div class="top-sales-card__footer">
                            <span>Открыть товар</span>
                            <v-icon icon="mdi-arrow-right" size="18" />
                        </div>
                    </div>
                </Link>
            </div>
        </v-container>
    </section>
</template>

<style scoped>
.top-sales-section {
    padding: 44px 0 52px;
    background:
        radial-gradient(circle at 14% 0%, rgba(128, 0, 0, 0.11), transparent 26%),
        linear-gradient(180deg, #ffffff 0%, #fff8f1 100%);
}

.top-sales-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.top-sales-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(80, 39, 22, 0.12);
    border-radius: 28px;
    background: #fff;
    box-shadow: 0 18px 46px rgba(61, 39, 26, 0.10);
    color: inherit;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.top-sales-card:hover {
    border-color: rgba(128, 0, 0, 0.28);
    box-shadow: 0 28px 64px rgba(61, 39, 26, 0.16);
    transform: translateY(-5px);
}

.top-sales-card__media {
    position: relative;
    height: 210px;
    overflow: hidden;
    background: #2d2926;
}

.top-sales-card__media img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.34s ease, filter 0.34s ease;
}

.top-sales-card:hover .top-sales-card__media img {
    filter: saturate(1.08) contrast(1.03);
    transform: scale(1.06);
}

.top-sales-card__rank,
.top-sales-card__badge {
    position: absolute;
    z-index: 1;
    border-radius: 999px;
    font-weight: 950;
}

.top-sales-card__rank {
    left: 12px;
    top: 12px;
    display: grid;
    width: 46px;
    height: 46px;
    place-items: center;
    background: #fff;
    color: #800000;
    font-size: 1rem;
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.16);
}

.top-sales-card__badge {
    right: 12px;
    bottom: 12px;
    padding: 7px 10px;
    background: rgba(128, 0, 0, 0.92);
    color: #fff;
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.top-sales-card__body {
    display: flex;
    min-height: 242px;
    flex-direction: column;
    padding: 16px;
}

.top-sales-card__category {
    color: #8a100c;
    font-size: 0.74rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.top-sales-card h3 {
    display: -webkit-box;
    overflow: hidden;
    margin: 8px 0 0;
    color: #2f251f;
    font-size: 1.08rem;
    font-weight: 950;
    line-height: 1.18;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.top-sales-card__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 12px;
}

.top-sales-card__chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    border: 1px solid rgba(71, 118, 90, 0.18);
    border-radius: 999px;
    background: #f8fbf6;
    color: #47765a;
    font-size: 0.72rem;
    font-weight: 900;
}

.top-sales-card__chip img {
    width: 18px;
    height: 18px;
    border-radius: 999px;
    object-fit: cover;
}

.top-sales-card__metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin-top: 15px;
}

.top-sales-card__metrics div {
    padding: 10px;
    border-radius: 16px;
    background: #fff7ef;
}

.top-sales-card__metrics strong,
.top-sales-card__metrics span {
    display: block;
}

.top-sales-card__metrics strong {
    color: #3b2b23;
    font-size: 0.95rem;
    font-weight: 950;
}

.top-sales-card__metrics span {
    margin-top: 3px;
    color: #7a6a62;
    font-size: 0.68rem;
    font-weight: 800;
}

.top-sales-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 14px;
    color: #800000;
    font-size: 0.82rem;
    font-weight: 950;
}

@media (max-width: 1260px) {
    .top-sales-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .top-sales-section {
        padding: 34px 0 40px;
    }

    .top-sales-grid {
        grid-template-columns: 1fr;
    }

    .top-sales-card__media {
        height: 190px;
    }
}
</style>
