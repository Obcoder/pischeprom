<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from "@/Composables/useAppRoute";
import HeroFlyingBee from '@/Components/Home/HeroFlyingBee.vue'

const { route } = useAppRoute();

const props = defineProps({
    fields: {
        type: Array,
        default: () => [],
    },
})

const visibleFields = computed(() => props.fields.slice(0, 3))
</script>

<template>
    <v-card class="home-welcome-banner" rounded="xl" elevation="0">
        <div class="home-welcome-banner__glow home-welcome-banner__glow--one" />
        <div class="home-welcome-banner__glow home-welcome-banner__glow--two" />
        <HeroFlyingBee />

        <v-row dense class="home-welcome-banner__row">
            <v-col cols="12" lg="6">
                <div class="home-welcome-banner__content">
                    <h1 class="home-welcome-banner__title">
                        Пищевая промышленность: Сырьё, ингредиенты, добавки, продукция
                    </h1>

                    <p class="home-welcome-banner__text">
                        Ресурс в помощь предприятиям пищевой промышленности,
                        переработчикам, предприятиям общественного питания
                        и частным заказчикам.
                    </p>

                    <div class="home-welcome-banner__actions">
                        <Link :href="route('public.goods.index')">
                            <v-btn color="#800000" rounded="xl" size="small">
                                Перейти в каталог
                            </v-btn>
                        </Link>

                        <a href="mailto:office@180022.ru" class="home-welcome-banner__contact-link">
                            <v-btn
                                variant="outlined"
                                color="#800000"
                                rounded="xl"
                                size="small"
                            >
                                Связаться с менеджером
                            </v-btn>
                        </a>
                    </div>
                </div>
            </v-col>

            <v-col cols="12" lg="6" class="home-welcome-banner__fields-col">
                <div class="home-welcome-banner__meta">
                    <div class="home-welcome-banner__fields">
                        <Link
                            v-for="field in visibleFields"
                            :key="field.id"
                            :href="route('public.fields.show', field.slug || field.id)"
                            class="home-welcome-banner__field-card"
                        >
                            <span class="home-welcome-banner__field-card-mark" />

                            <span class="home-welcome-banner__field-card-body">
                                <span class="home-welcome-banner__field-eyebrow">
                                    Подборка
                                </span>

                                <span class="home-welcome-banner__field-title">
                                    {{ field.title || field.name }}
                                </span>

                                <span class="home-welcome-banner__field-description">
                                    {{ field.description || 'Товары для конкретного направления производства.' }}
                                </span>
                            </span>

                            <span class="home-welcome-banner__field-footer">
                                <span>{{ field.goods_count || 0 }} товаров</span>
                                <span>Открыть</span>
                            </span>
                        </Link>

                        <div
                            v-if="!visibleFields.length"
                            class="home-welcome-banner__field-card home-welcome-banner__field-card--empty"
                        >
                            Опубликованные подборки появятся здесь
                        </div>
                    </div>
                </div>
            </v-col>
        </v-row>
    </v-card>
</template>

<style scoped>
.home-welcome-banner {
    position: relative;
    overflow: hidden;
    margin-bottom: 8px;
    padding: 14px 18px;
    background:
        radial-gradient(circle at 90% -20%, rgba(128, 0, 0, 0.10), transparent 28%),
        linear-gradient(135deg, #fffaf8 0%, #ffffff 58%, #fff4ef 100%);
    border: 1px solid rgba(128, 0, 0, 0.09);
    box-shadow: 0 10px 24px rgba(63, 29, 29, 0.08);
}

.home-welcome-banner__glow {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
}

.home-welcome-banner__glow--one {
    right: -120px;
    top: -120px;
    width: 220px;
    height: 220px;
    background: rgba(128, 0, 0, 0.07);
}

.home-welcome-banner__glow--two {
    left: 40%;
    bottom: -160px;
    width: 260px;
    height: 260px;
    background: rgba(245, 158, 11, 0.09);
}

.home-welcome-banner__content,
.home-welcome-banner__meta {
    position: relative;
    z-index: 1;
}

.home-welcome-banner__row {
    align-items: stretch;
}

.home-welcome-banner__content {
    display: flex;
    min-height: 232px;
    height: 100%;
    flex-direction: column;
    justify-content: center;
    padding: 8px 0;
}

.home-welcome-banner__fields-col {
    display: flex;
}

.home-welcome-banner__meta {
    display: flex;
    width: 100%;
    min-height: 232px;
}

.home-welcome-banner__title {
    max-width: 820px;
    margin: 0 0 6px;
    color: #3f1d1d;
    font-size: clamp(1.35rem, 2.2vw, 2.05rem);
    line-height: 1.08;
    font-weight: 950;
}

.home-welcome-banner__text {
    max-width: 780px;
    margin: 0;
    color: #5f5753;
    font-size: 0.92rem;
    line-height: 1.45;
}

.home-welcome-banner__fields,
.home-welcome-banner__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.home-welcome-banner__actions {
    margin-top: 10px;
}

.home-welcome-banner__contact-link {
    text-decoration: none;
}

.home-welcome-banner__fields {
    display: grid;
    width: 100%;
    grid-template-columns: repeat(auto-fit, minmax(164px, 1fr));
    align-items: stretch;
}

.home-welcome-banner__field-card {
    position: relative;
    display: flex;
    min-height: 100%;
    overflow: hidden;
    flex-direction: column;
    justify-content: space-between;
    padding: 16px;
    border-radius: 22px;
    text-decoration: none;
    color: #3f1d1d;
    background:
        radial-gradient(circle at 90% 12%, rgba(220, 122, 81, 0.18), transparent 28%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.94), rgba(255, 250, 244, 0.86));
    border: 1px solid rgba(128, 0, 0, 0.12);
    box-shadow: 0 18px 34px rgba(63, 29, 29, 0.10);
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}

.home-welcome-banner__field-card:hover {
    border-color: rgba(128, 0, 0, 0.28);
    box-shadow: 0 24px 42px rgba(63, 29, 29, 0.14);
    transform: translateY(-3px);
}

.home-welcome-banner__field-card-mark {
    position: absolute;
    right: -34px;
    top: -42px;
    width: 104px;
    height: 104px;
    border-radius: 30px;
    background: rgba(71, 118, 90, 0.10);
    transform: rotate(14deg);
}

.home-welcome-banner__field-card-body,
.home-welcome-banner__field-footer {
    position: relative;
    z-index: 1;
}

.home-welcome-banner__field-card-body {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.home-welcome-banner__field-eyebrow {
    color: #800000;
    font-size: 0.68rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.home-welcome-banner__field-title {
    display: -webkit-box;
    overflow: hidden;
    color: #3f1d1d;
    font-size: clamp(1rem, 1.1vw, 1.28rem);
    font-weight: 950;
    line-height: 1.08;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.home-welcome-banner__field-description {
    display: -webkit-box;
    overflow: hidden;
    color: #695d57;
    font-size: 0.82rem;
    font-weight: 500;
    line-height: 1.35;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
}

.home-welcome-banner__field-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 18px;
    color: #47765a;
    font-size: 0.78rem;
    font-weight: 900;
}

.home-welcome-banner__field-card--empty {
    align-items: center;
    justify-content: center;
    color: #7a6a62;
    text-align: center;
}

@media (max-width: 960px) {
    .home-welcome-banner {
        padding: 14px;
    }

    .home-welcome-banner__content,
    .home-welcome-banner__meta {
        min-height: auto;
    }

    .home-welcome-banner__fields {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .home-welcome-banner__field-card {
        min-height: 168px;
    }
}

@media (max-width: 600px) {
    .home-welcome-banner {
        padding: 12px;
    }

    .home-welcome-banner__actions {
        flex-direction: column;
    }

    .home-welcome-banner__actions :deep(.v-btn) {
        width: 100%;
    }

    .home-welcome-banner__fields {
        grid-template-columns: 1fr;
    }
}
</style>
