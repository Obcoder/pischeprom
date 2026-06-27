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

function descriptionSnippet(good) {
    const description = String(good.description || '').trim()

    if (!description) {
        return ''
    }

    return description.length > 118 ? `${description.slice(0, 118).trim()}...` : description
}

function characteristicFields(good) {
    return (good.fields || []).filter((field) => field?.name).slice(0, 3)
}

function hasCharacteristics(good) {
    return Boolean(good.country || characteristicFields(good).length)
}

function countryName(country) {
    return country?.name || 'Страна'
}
</script>

<template>
    <section
        v-if="visibleGoods.length"
        class="featured-goods-section"
    >
        <v-container>
            <SectionHeader
                title="Подборка товаров"
                subtitle="Товарные карточки с категорией, страной происхождения, описанием и характеристиками"
            />

            <div class="featured-goods-grid">
                <Link
                    v-for="good in visibleGoods"
                    :key="good.id"
                    :href="goodPublicUrl(good, false)"
                    class="featured-goods-card"
                >
                    <div class="featured-goods-card__media">
                        <img
                            :src="goodImage(good)"
                            :alt="good.name"
                            loading="lazy"
                        >
                    </div>

                    <div class="featured-goods-card__body">
                        <div class="featured-goods-card__category">
                            {{ productLabel(good) }}
                        </div>

                        <h3>{{ good.name }}</h3>

                        <div class="featured-goods-card__chips">
                            <span v-if="good.country" class="featured-goods-card__chip">
                                <img
                                    v-if="good.country.flag"
                                    :src="good.country.flag"
                                    :alt="countryName(good.country)"
                                >
                                {{ countryName(good.country) }}
                            </span>
                            <span
                                v-for="field in characteristicFields(good)"
                                :key="field.id || field.name"
                                class="featured-goods-card__chip"
                            >
                                {{ field.name }}
                            </span>
                        </div>

                        <p
                            v-if="descriptionSnippet(good)"
                            class="featured-goods-card__description"
                        >
                            {{ descriptionSnippet(good) }}
                        </p>

                        <div
                            v-if="!hasCharacteristics(good) && !descriptionSnippet(good)"
                            class="featured-goods-card__note"
                        >
                            Характеристики доступны в карточке товара.
                        </div>

                        <div class="featured-goods-card__footer">
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
.featured-goods-section {
    padding: 44px 0 52px;
    background:
        radial-gradient(circle at 14% 0%, rgba(128, 0, 0, 0.11), transparent 26%),
        linear-gradient(180deg, #ffffff 0%, #fff8f1 100%);
}

.featured-goods-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.featured-goods-card {
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

.featured-goods-card:hover {
    border-color: rgba(128, 0, 0, 0.28);
    box-shadow: 0 28px 64px rgba(61, 39, 26, 0.16);
    transform: translateY(-5px);
}

.featured-goods-card__media {
    position: relative;
    height: 210px;
    overflow: hidden;
    background: #2d2926;
}

.featured-goods-card__media img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.34s ease, filter 0.34s ease;
}

.featured-goods-card:hover .featured-goods-card__media img {
    filter: saturate(1.08) contrast(1.03);
    transform: scale(1.06);
}

.featured-goods-card__body {
    display: flex;
    min-height: 214px;
    flex-direction: column;
    padding: 16px;
}

.featured-goods-card__category {
    color: #8a100c;
    font-size: 0.74rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.featured-goods-card h3 {
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

.featured-goods-card__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 12px;
}

.featured-goods-card__chip {
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

.featured-goods-card__chip img {
    width: 18px;
    height: 18px;
    border-radius: 999px;
    object-fit: cover;
}

.featured-goods-card__description,
.featured-goods-card__note {
    margin: 14px 0 0;
    color: #6f625c;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.45;
}

.featured-goods-card__description {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.featured-goods-card__note {
    padding: 10px 12px;
    border-radius: 16px;
    background: #fff7ef;
}

.featured-goods-card__footer {
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
    .featured-goods-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .featured-goods-section {
        padding: 34px 0 40px;
    }

    .featured-goods-grid {
        grid-template-columns: 1fr;
    }

    .featured-goods-card__media {
        height: 190px;
    }
}
</style>
