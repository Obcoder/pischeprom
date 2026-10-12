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
        <div class="home-welcome-banner__intro">
            <div class="home-welcome-banner__content">
                <h1 id="home-welcome-title" class="home-welcome-banner__title">
                    Сервер о вкусной<br>
                    <span>и здоровой пище</span>
                </h1>

                <p class="home-welcome-banner__slogan">Питание. Здоровье. Пищепром.</p>

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

            <figure class="home-welcome-banner__art">
                <img
                    src="/home/food-industry-collage.webp"
                    alt="Овощи, фрукты, ягоды, мясо, рыболовецкий траулер, зерно и мука. Пища для людей - наше дело."
                    width="1536"
                    height="1024"
                    decoding="async"
                    fetchpriority="high"
                >
            </figure>
        </div>

        <div class="home-welcome-banner__collections">
            <div class="home-welcome-banner__collections-heading">
                <h2 class="home-welcome-banner__collections-title">Для вашего дела</h2>
                <span class="home-welcome-banner__collections-note">Подборки по направлениям</span>
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
    --paper: #f2ebdf;
    --ink: #243d32;
    --muted: #625f50;
    --terracotta: #a55136;
    position: relative;
    isolation: isolate;
    margin-bottom: 24px;
    padding: 22px 24px 24px;
    background: var(--paper);
    color: var(--ink);
}

.home-welcome-banner::before {
    content: '';
    position: absolute;
    z-index: -1;
    inset: 0;
    background: url('/home/paper-grain.svg');
    pointer-events: none;
}

.home-welcome-banner__intro {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
    gap: 24px;
    align-items: center;
}

.home-welcome-banner__content { min-width: 0; padding-block: 22px 30px; }
.home-welcome-banner__title {
    margin: 0;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: clamp(34px, 4.1vw, 64px);
    font-weight: 400;
    letter-spacing: -.045em;
    line-height: 1.04;
    text-wrap: balance;
}
.home-welcome-banner__title span { color: var(--terracotta); font-style: italic; }
.home-welcome-banner__slogan { margin: 20px 0 0; color: var(--ink); font-size: 16px; font-weight: 600; line-height: 1.5; }
.home-welcome-banner__text {
    max-width: 380px;
    margin: 12px 0 0;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.65;
}
.home-welcome-banner__actions { margin-top: 22px; }
.home-welcome-banner__catalog-link {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    gap: 24px;
    padding: 11px 22px;
    border: 1px solid var(--ink);
    border-radius: 999px;
    background: var(--ink);
    color: #fffaf0;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.4;
    text-decoration: none;
    transition: background-color .15s ease, border-color .15s ease;
}
.home-welcome-banner__catalog-link:hover { background: #365443; border-color: #365443; }
.home-welcome-banner a:focus-visible { outline: 2px solid var(--terracotta); outline-offset: 5px; }

.home-welcome-banner__art { min-width: 0; margin: 0; }
.home-welcome-banner__art img { display: block; width: 100%; height: auto; object-fit: contain; }
.home-welcome-banner__collections { margin-top: 26px; padding-top: 21px; border-top: 1px solid #b8b29f; }
.home-welcome-banner__collections-heading { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 6px 16px; margin-bottom: 18px; }
.home-welcome-banner__collections-title { margin: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 28px; font-weight: 400; letter-spacing: -.025em; line-height: 1.2; }
.home-welcome-banner__collections-note { color: var(--muted); font-size: 10px; letter-spacing: .12em; text-transform: uppercase; }
.home-welcome-banner__fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 26px; }
.home-welcome-banner__field-card { display: grid; grid-template-columns: 24px minmax(0, 1fr); gap: 8px 12px; min-width: 0; padding: 4px 0 8px; color: inherit; text-decoration: none; }
.home-welcome-banner__field-card + .home-welcome-banner__field-card { border-left: 1px solid #c7c1b0; padding-left: 24px; }
.home-welcome-banner__field-number { padding-top: 5px; color: var(--terracotta); font-size: 10px; letter-spacing: .12em; font-variant-numeric: tabular-nums; }
.home-welcome-banner__field-body { min-width: 0; }
.home-welcome-banner__field-title { margin: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 23px; font-weight: 400; line-height: 1.15; overflow-wrap: anywhere; }
.home-welcome-banner__field-description { display: -webkit-box; overflow: hidden; margin: 8px 0 0; color: var(--muted); font-size: 12px; line-height: 1.55; overflow-wrap: anywhere; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.home-welcome-banner__field-footer { display: flex; grid-column: 2; align-items: center; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 6px; }
.home-welcome-banner__field-count { color: var(--muted); font-size: 11px; white-space: nowrap; }
.home-welcome-banner__field-arrow { display: inline-flex; width: 30px; height: 30px; flex-shrink: 0; align-items: center; justify-content: center; border: 1px solid #9caa9d; border-radius: 50%; color: var(--ink); transition: color .15s ease, background-color .15s ease; }
.home-welcome-banner__field-card:hover .home-welcome-banner__field-title { text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 4px; }
.home-welcome-banner__field-card:hover .home-welcome-banner__field-arrow { color: #fffaf0; background: var(--ink); border-color: var(--ink); }
.home-welcome-banner__empty { display: flex; grid-column: 1 / -1; align-items: center; gap: 12px; flex-wrap: wrap; color: var(--muted); font-size: 13px; }
.home-welcome-banner__empty p { margin: 0; }
.home-welcome-banner__empty a { color: var(--ink); text-underline-offset: 4px; }

@media (max-width: 1100px) {
    .home-welcome-banner { padding-inline: 12px; }
    .home-welcome-banner__intro { gap: 12px; }
    .home-welcome-banner__field-title { font-size: 21px; }
    .home-welcome-banner__fields { gap: 18px; }
    .home-welcome-banner__field-card + .home-welcome-banner__field-card { padding-left: 18px; }
}
@media (max-width: 760px) {
    .home-welcome-banner__intro { grid-template-columns: minmax(0, 1fr) minmax(0, .9fr); }
    .home-welcome-banner__title { font-size: 37px; }
    .home-welcome-banner__field-card { grid-template-columns: minmax(0, 1fr); }
    .home-welcome-banner__field-footer { grid-column: 1; }
    .home-welcome-banner__field-title { font-size: 20px; }
}
@media (max-width: 599px) {
    .home-welcome-banner { padding: 12px 2px 20px; margin-bottom: 20px; }
    .home-welcome-banner__intro { grid-template-columns: minmax(0, 1fr); gap: 4px; }
    .home-welcome-banner__content { padding: 8px 0 0; }
    .home-welcome-banner__title { max-width: 440px; font-size: clamp(36px, 9vw, 48px); }
    .home-welcome-banner__text { margin-top: 16px; font-size: 13px; }
    .home-welcome-banner__actions { margin-top: 18px; }
    .home-welcome-banner__art { max-width: 500px; width: 100%; margin: 20px auto 0; }
    .home-welcome-banner__collections { margin-top: 24px; padding-top: 18px; }
    .home-welcome-banner__collections-heading { margin-bottom: 14px; }
    .home-welcome-banner__collections-title { font-size: 27px; }
    .home-welcome-banner__collections-note { font-size: 9px; }
    .home-welcome-banner__fields { grid-template-columns: minmax(0, 1fr); gap: 0; }
    .home-welcome-banner__field-card { grid-template-columns: 22px minmax(0, 1fr) auto; gap: 10px; padding: 16px 0; }
    .home-welcome-banner__field-card + .home-welcome-banner__field-card { border-left: 0; border-top: 1px solid #c7c1b0; padding-left: 0; }
    .home-welcome-banner__field-title { font-size: 22px; }
    .home-welcome-banner__field-description { margin-top: 5px; }
    .home-welcome-banner__field-footer { grid-column: 3; align-self: start; flex-direction: column-reverse; gap: 8px; padding: 0; }
    .home-welcome-banner__field-count { font-size: 10px; }
}
@media (prefers-reduced-motion: reduce) { .home-welcome-banner a, .home-welcome-banner__field-arrow { transition: none; } }
</style>
