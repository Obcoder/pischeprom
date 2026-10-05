<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from '@/Composables/useAppRoute'

const { route } = useAppRoute()

const props = defineProps({
    fields: {
        type: Array,
        default: () => [],
    },
})

const visibleFields = computed(() => props.fields.slice(0, 3))

function goodsLabel(count) {
    const total = Number(count) || 0
    const lastTwo = total % 100
    const last = total % 10
    const word = lastTwo >= 11 && lastTwo <= 14
        ? 'товаров'
        : last === 1 ? 'товар' : last >= 2 && last <= 4 ? 'товара' : 'товаров'

    return total + ' ' + word
}
</script>

<template>
    <section class="home-welcome-banner" aria-labelledby="home-welcome-title">
        <div class="home-welcome-banner__content">
            <p class="home-welcome-banner__eyebrow">
                <span class="home-welcome-banner__eyebrow-mark" aria-hidden="true" />
                Магазин для пищевой отрасли
            </p>

            <h1 id="home-welcome-title" class="home-welcome-banner__title">
                Сырьё и продукты<br>
                <span>для вашего бизнеса</span>
            </h1>

            <p class="home-welcome-banner__text">
                Ингредиенты, добавки и готовая продукция
                для производства, HoReCa и розницы.
            </p>

            <div class="home-welcome-banner__actions">
                <Link
                    :href="route('public.goods.index')"
                    class="home-welcome-banner__catalog-link"
                >
                    Открыть каталог
                    <v-icon icon="mdi-arrow-right" size="18" aria-hidden="true" />
                </Link>

            </div>
        </div>

        <div class="home-welcome-banner__collections">
            <div class="home-welcome-banner__collections-heading">
                <h2 class="home-welcome-banner__collections-title">Подборки по направлениям</h2>
                <v-icon icon="mdi-view-grid-outline" size="18" aria-hidden="true" />
            </div>

            <div class="home-welcome-banner__fields">
                <Link
                    v-for="(field, index) in visibleFields"
                    :key="field.id"
                    :href="route('public.fields.show', field.slug || field.id)"
                    class="home-welcome-banner__field-card"
                    :class="'home-welcome-banner__field-card--' + (index + 1)"
                >
                    <span class="home-welcome-banner__field-number" aria-hidden="true">
                        0{{ index + 1 }}
                    </span>

                    <div class="home-welcome-banner__field-body">
                        <h3 class="home-welcome-banner__field-title">
                            {{ field.title || field.name }}
                        </h3>

                        <p class="home-welcome-banner__field-description">
                            {{ field.description || 'Товары для вашего направления бизнеса' }}
                        </p>
                    </div>

                    <div class="home-welcome-banner__field-footer">
                        <span class="home-welcome-banner__field-count">
                            {{ goodsLabel(field.goods_count) }}
                        </span>

                        <span class="home-welcome-banner__field-arrow" aria-hidden="true">
                            <v-icon icon="mdi-arrow-top-right" size="18" />
                        </span>
                    </div>
                </Link>

                <div v-if="!visibleFields.length" class="home-welcome-banner__empty">
                    <v-icon icon="mdi-view-grid-outline" size="28" aria-hidden="true" />
                    <p>Подборки скоро появятся</p>
                    <Link :href="route('public.goods.index')">Посмотреть все товары</Link>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.home-welcome-banner {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: 32px;
    margin-bottom: 12px;
    padding: 24px;
    border: 1px solid #e8e5e2;
    border-radius: 20px;
    background: #fff;
    color: #292624;
}

.home-welcome-banner__content {
    display: flex;
    min-width: 0;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
}

.home-welcome-banner__eyebrow {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
    color: #68615b;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1.4;
}

.home-welcome-banner__eyebrow-mark {
    width: 7px;
    height: 7px;
    flex-shrink: 0;
    border-radius: 2px;
    background: #800000;
}

.home-welcome-banner__title {
    margin: 0;
    font-size: clamp(1.65rem, 2.2vw, 2.2rem);
    font-weight: 750;
    letter-spacing: -0.035em;
    line-height: 1.12;
}

.home-welcome-banner__title span {
    color: #800000;
}

.home-welcome-banner__text {
    max-width: 410px;
    margin: 10px 0 0;
    color: #68615b;
    font-size: 0.85rem;
    line-height: 1.5;
}

.home-welcome-banner__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px 18px;
    margin-top: 18px;
}

.home-welcome-banner__catalog-link {
    display: inline-flex;
    min-height: 40px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.2;
    text-decoration: none;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.home-welcome-banner__catalog-link {
    padding: 0 16px;
    background: #800000;
    color: #fff;
}

.home-welcome-banner__catalog-link:hover {
    background: #630000;
}

.home-welcome-banner__catalog-link:focus-visible,
.home-welcome-banner__field-card:focus-visible,
.home-welcome-banner__empty a:focus-visible {
    outline: 2px solid #800000;
    outline-offset: 4px;
}

.home-welcome-banner__collections {
    display: flex;
    min-width: 0;
    flex-direction: column;
    justify-content: center;
}

.home-welcome-banner__collections-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    color: #77706a;
}

.home-welcome-banner__collections-title {
    margin: 0;
    color: #514b46;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.4;
}

.home-welcome-banner__fields {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.home-welcome-banner__field-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: flex-start;
    padding: 16px;
    border: 1px solid transparent;
    border-radius: 14px;
    background: #f7f4ef;
    color: inherit;
    text-decoration: none;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}

.home-welcome-banner__field-card--2 {
    background: #f4f1f0;
}

.home-welcome-banner__field-card--3 {
    background: #eef4f1;
}

.home-welcome-banner__field-card:hover {
    border-color: #bdb4aa;
    background: #fff;
}

.home-welcome-banner__field-number {
    display: inline-flex;
    height: 24px;
    align-items: center;
    margin-bottom: 14px;
    color: #716961;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    font-variant-numeric: tabular-nums;
}

.home-welcome-banner__field-body {
    min-width: 0;
    width: 100%;
}

.home-welcome-banner__field-title {
    display: -webkit-box;
    overflow: hidden;
    margin: 0;
    font-size: 0.98rem;
    font-weight: 650;
    line-height: 1.25;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.home-welcome-banner__field-description {
    display: -webkit-box;
    overflow: hidden;
    margin: 8px 0 0;
    color: #70675f;
    font-size: 0.76rem;
    line-height: 1.4;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.home-welcome-banner__field-footer {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: auto;
    padding-top: 18px;
}

.home-welcome-banner__field-count {
    color: #514b46;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}

.home-welcome-banner__field-arrow {
    display: inline-flex;
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 1px solid #d8d3cb;
    border-radius: 50%;
    color: #514b46;
}

.home-welcome-banner__empty {
    display: flex;
    min-height: 182px;
    grid-column: 1 / -1;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 20px;
    border: 1px dashed #d8d3cb;
    border-radius: 14px;
    color: #77706a;
    text-align: center;
}

.home-welcome-banner__empty p {
    margin: 0;
    font-size: 0.85rem;
}

.home-welcome-banner__empty a {
    color: #800000;
    font-size: 0.8rem;
    text-underline-offset: 3px;
}

@media (max-width: 1100px) {
    .home-welcome-banner {
        grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
        gap: 24px;
        padding: 20px;
    }

    .home-welcome-banner__field-card {
        padding: 12px;
    }
}

@media (max-width: 900px) {
    .home-welcome-banner {
        grid-template-columns: minmax(0, 1fr);
        gap: 20px;
    }

    .home-welcome-banner__text {
        max-width: 520px;
    }

    .home-welcome-banner__field-number {
        margin-bottom: 8px;
    }

    .home-welcome-banner__field-footer {
        padding-top: 12px;
    }
}

@media (max-width: 600px) {
    .home-welcome-banner {
        gap: 18px;
        padding: 18px 16px;
        border-radius: 16px;
    }

    .home-welcome-banner__title {
        font-size: clamp(1.35rem, 6.2vw, 1.9rem);
    }

    .home-welcome-banner__eyebrow {
        margin-bottom: 8px;
    }

    .home-welcome-banner__text {
        margin-top: 8px;
        font-size: 0.8rem;
    }

    .home-welcome-banner__actions {
        gap: 8px 12px;
        margin-top: 12px;
    }

    .home-welcome-banner__catalog-link {
        min-height: 44px;
        gap: 6px;
        font-size: 0.72rem;
    }

    .home-welcome-banner__catalog-link {
        padding: 0 12px;
    }

    .home-welcome-banner__fields {
        grid-template-columns: minmax(0, 1fr);
        gap: 6px;
    }

    .home-welcome-banner__field-card {
        display: grid;
        grid-template-columns: 22px minmax(0, 1fr) auto;
        min-height: 64px;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
    }

    .home-welcome-banner__field-number {
        height: auto;
        margin: 0;
        font-size: 0.7rem;
    }

    .home-welcome-banner__field-title {
        font-size: 0.82rem;
        -webkit-line-clamp: 2;
    }

    .home-welcome-banner__field-description {
        display: none;
    }

    .home-welcome-banner__field-footer {
        width: auto;
        flex-direction: column-reverse;
        gap: 4px;
        margin: 0;
        padding: 0;
    }

    .home-welcome-banner__field-arrow {
        width: 22px;
        height: 22px;
        border: 0;
    }

    .home-welcome-banner__field-count {
        font-size: 0.72rem;
    }

    .home-welcome-banner__empty {
        min-height: 120px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .home-welcome-banner a {
        transition: none;
    }
}
</style>
