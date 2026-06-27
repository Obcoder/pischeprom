<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from '@/Composables/useAppRoute'
import SectionHeader from './SectionHeader.vue'

const props = defineProps({
    goods: {
        type: Array,
        default: () => [],
    },
    collections: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    limit: {
        type: Number,
        default: 8,
    },
})

const { route } = useAppRoute()

const countryCollections = computed(() => {
    if (props.collections.length) {
        return props.collections.slice(0, props.limit)
    }

    const map = new Map()

    props.goods.forEach((good) => {
        const country = good?.country

        if (!country?.id) {
            return
        }

        const id = Number(country.id)

        if (!map.has(id)) {
            map.set(id, {
                country,
                goods: [],
            })
        }

        map.get(id).goods.push(good)
    })

    return [...map.values()]
        .sort((a, b) => {
            const countDiff = b.goods.length - a.goods.length

            if (countDiff !== 0) {
                return countDiff
            }

            return String(a.country.name || '').localeCompare(String(b.country.name || ''), 'ru')
        })
        .slice(0, props.limit)
})

function countryUrl(country) {
    const base = route('public.goods.index')
    const separator = base.includes('?') ? '&' : '?'

    return `${base}${separator}country_id=${encodeURIComponent(country.id)}`
}
</script>

<template>
    <section
        v-if="loading || countryCollections.length"
        class="home-country-section"
    >
        <v-container>
            <SectionHeader
                title="География вкуса"
                subtitle="Позиции по странам происхождения: удобно выбрать направление закупки и быстро перейти к товарам"
            />

            <v-row v-if="loading && !countryCollections.length" dense>
                <v-col
                    v-for="item in 4"
                    :key="item"
                    cols="12"
                    sm="6"
                    lg="3"
                >
                    <v-skeleton-loader type="image, article" rounded="xl" />
                </v-col>
            </v-row>

            <div v-else class="country-rail">
                <Link
                    v-for="collection in countryCollections"
                    :key="collection.country.id"
                    :href="countryUrl(collection.country)"
                    class="country-card-link"
                >
                    <article class="country-card">
                        <div class="country-card__shine" />

                        <div class="country-card__top">
                            <div class="country-card__flag">
                                <v-img
                                    v-if="collection.country.flag"
                                    :src="collection.country.flag"
                                    :alt="collection.country.name"
                                    cover
                                />
                                <span v-else>{{ collection.country.name?.slice(0, 1) }}</span>
                            </div>

                            <span class="country-card__count">
                                {{ collection.goods_count || collection.goods.length }} товаров
                            </span>
                        </div>

                        <div class="country-card__body">
                            <span class="country-card__eyebrow">Страна происхождения</span>

                            <h3>
                                Товары из {{ collection.country.name }}
                            </h3>

                            <p>
                                Сырьё, ингредиенты и товарные позиции для закупки от проверенных направлений.
                            </p>
                        </div>

                        <div class="country-card__goods">
                            <div
                                v-for="good in collection.goods.slice(0, 3)"
                                :key="good.id"
                                class="country-card__good"
                            >
                                <v-avatar size="34" rounded="lg">
                                    <v-img
                                        :src="good.ava_thumb || good.ava_image || '/default-image.jpg'"
                                        :alt="good.name"
                                        cover
                                    />
                                </v-avatar>
                                <span>{{ good.name }}</span>
                            </div>
                        </div>

                        <div class="country-card__footer">
                            <span>Открыть подборку</span>
                            <v-icon icon="mdi-arrow-right" size="18" />
                        </div>
                    </article>
                </Link>
            </div>
        </v-container>
    </section>
</template>

<style scoped>
.home-country-section {
    position: relative;
    overflow: hidden;
    padding: 38px 0 52px;
    background:
        radial-gradient(circle at 6% 10%, rgba(128, 0, 0, 0.12), transparent 28%),
        radial-gradient(circle at 90% 0%, rgba(71, 118, 90, 0.18), transparent 30%),
        linear-gradient(180deg, #fffaf2 0%, #eef6ef 54%, #ffffff 100%);
}

.country-rail {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.country-card-link {
    display: block;
    height: 100%;
    color: inherit;
    text-decoration: none;
}

.country-card {
    position: relative;
    display: flex;
    min-height: 318px;
    height: 100%;
    overflow: hidden;
    flex-direction: column;
    padding: 16px;
    border: 1px solid rgba(47, 49, 41, 0.10);
    border-radius: 24px;
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(255, 248, 233, 0.86)),
        radial-gradient(circle at 100% 100%, rgba(128, 0, 0, 0.12), transparent 36%);
    box-shadow: 0 18px 46px rgba(49, 65, 48, 0.12);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.country-card:hover {
    border-color: rgba(128, 0, 0, 0.28);
    box-shadow: 0 28px 70px rgba(49, 65, 48, 0.18);
    transform: translateY(-5px);
}

.country-card__shine {
    position: absolute;
    right: -56px;
    top: -72px;
    width: 170px;
    height: 170px;
    border-radius: 48px;
    background: rgba(71, 118, 90, 0.12);
    transform: rotate(16deg);
}

.country-card__top,
.country-card__body,
.country-card__goods,
.country-card__footer {
    position: relative;
    z-index: 1;
}

.country-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.country-card__flag {
    display: grid;
    width: 70px;
    height: 70px;
    overflow: hidden;
    place-items: center;
    border: 6px solid rgba(255, 255, 255, 0.86);
    border-radius: 999px;
    background: #f3ead9;
    box-shadow: 0 14px 28px rgba(47, 49, 41, 0.14);
    color: #47765a;
    font-size: 1.8rem;
    font-weight: 900;
}

.country-card__count {
    padding: 6px 9px;
    border: 1px solid rgba(71, 118, 90, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.72);
    color: #47765a;
    font-size: 0.72rem;
    font-weight: 900;
}

.country-card__body {
    margin-top: 16px;
}

.country-card__eyebrow {
    color: #800000;
    font-size: 0.68rem;
    font-weight: 950;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.country-card h3 {
    margin: 8px 0 0;
    color: #272a22;
    font-size: 1.35rem;
    font-weight: 950;
    line-height: 1.02;
}

.country-card p {
    margin: 10px 0 0;
    color: #62685e;
    font-size: 0.86rem;
    line-height: 1.42;
}

.country-card__goods {
    display: grid;
    gap: 7px;
    margin-top: 16px;
}

.country-card__good {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    padding: 6px;
    border: 1px solid rgba(71, 118, 90, 0.10);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.68);
}

.country-card__good span {
    overflow: hidden;
    color: #34372f;
    font-size: 0.78rem;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.country-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: auto;
    padding-top: 14px;
    color: #800000;
    font-size: 0.82rem;
    font-weight: 950;
}

@media (max-width: 1260px) {
    .country-rail {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .country-rail {
        grid-template-columns: 1fr;
    }

    .country-card {
        min-height: 0;
    }
}
</style>
