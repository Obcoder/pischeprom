<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})

const highlightedNames = ['Рыба', 'Овощи', 'Ягоды', 'Молочные']

const highlightedCategories = computed(() => {
    return highlightedNames
        .map((name) => {
            return props.categories.find((category) => {
                return String(category.name || '')
                    .toLowerCase()
                    .includes(name.toLowerCase())
            }) || {
                id: null,
                name,
            }
        })
})

const badges = [
    'Оптовые поставки',
    'Пищевые ингредиенты',
    'Заморозка',
    'Бакалея',
    'HoReCa',
]
</script>

<template>
    <v-card class="home-welcome-banner" rounded="xl" elevation="0">
        <div class="home-welcome-banner__glow home-welcome-banner__glow--one" />
        <div class="home-welcome-banner__glow home-welcome-banner__glow--two" />

        <v-row dense class="align-center">
            <v-col cols="12" lg="7">
                <div class="home-welcome-banner__content">
                    <div class="home-welcome-banner__eyebrow">
                        ПИЩЕПРОМ-СЕРВЕР
                    </div>

                    <h1 class="home-welcome-banner__title">
                        Всё, что связано с пищевой промышленностью
                    </h1>

                    <p class="home-welcome-banner__text">
                        Ресурс в помощь предприятиям пищевой промышленности,
                        переработчикам, предприятиям общественного питания
                        и частным заказчикам.
                    </p>

                    <div class="home-welcome-banner__badges">
                        <span
                            v-for="badge in badges"
                            :key="badge"
                            class="home-welcome-banner__badge"
                        >
                            {{ badge }}
                        </span>
                    </div>

                    <div class="home-welcome-banner__actions">
                        <Link :href="route('public.goods.index')">
                            <v-btn color="#800000" rounded="xl" size="large">
                                Перейти в каталог
                            </v-btn>
                        </Link>

                        <a href="mailto:office@180022.ru" class="home-welcome-banner__contact-link">
                            <v-btn
                                variant="outlined"
                                color="#800000"
                                rounded="xl"
                                size="large"
                            >
                                Связаться с менеджером
                            </v-btn>
                        </a>
                    </div>

                    <div class="home-welcome-banner__categories">
                        <template
                            v-for="category in highlightedCategories"
                            :key="category.name"
                        >
                            <Link
                                v-if="category.id"
                                :href="route('category.show', category.id)"
                                class="home-welcome-banner__category"
                            >
                                {{ category.name }}
                            </Link>

                            <span
                                v-else
                                class="home-welcome-banner__category"
                            >
                                {{ category.name }}
                            </span>
                        </template>
                    </div>
                </div>
            </v-col>

            <v-col cols="12" lg="5">
                <div class="home-welcome-banner__visual">
                    <div class="home-welcome-banner__image-card">
                        <v-img
                            src="/images/home/pischeprom-welcome-collage.jpg"
                            height="280"
                            cover
                            class="home-welcome-banner__image"
                        >
                            <template #error>
                                <div class="home-welcome-banner__image-fallback">
                                    <div class="home-welcome-banner__fallback-title">
                                        Пищевое сырьё и ингредиенты
                                    </div>
                                    <div class="home-welcome-banner__fallback-grid">
                                        <span>Рыба</span>
                                        <span>Овощи</span>
                                        <span>Ягоды</span>
                                        <span>Молочные</span>
                                    </div>
                                </div>
                            </template>
                        </v-img>
                    </div>

                    <div class="home-welcome-banner__floating-card home-welcome-banner__floating-card--top">
                        <strong>Логистика</strong>
                        <span>по региону поставки</span>
                    </div>

                    <div class="home-welcome-banner__floating-card home-welcome-banner__floating-card--bottom">
                        <strong>B2B цены</strong>
                        <span>для организаций</span>
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
    padding: 30px;
    background:
        radial-gradient(circle at top right, rgba(128, 0, 0, 0.14), transparent 34%),
        linear-gradient(135deg, #fffaf8 0%, #ffffff 48%, #fff2ee 100%);
    border: 1px solid rgba(128, 0, 0, 0.09);
    box-shadow: 0 18px 38px rgba(63, 29, 29, 0.10);
}

.home-welcome-banner__glow {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
}

.home-welcome-banner__glow--one {
    right: -120px;
    top: -120px;
    width: 260px;
    height: 260px;
    background: rgba(128, 0, 0, 0.09);
}

.home-welcome-banner__glow--two {
    left: 40%;
    bottom: -160px;
    width: 320px;
    height: 320px;
    background: rgba(245, 158, 11, 0.12);
}

.home-welcome-banner__content,
.home-welcome-banner__visual {
    position: relative;
    z-index: 1;
}

.home-welcome-banner__eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(128, 0, 0, 0.08);
    color: #800000;
    font-size: 0.86rem;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.home-welcome-banner__title {
    max-width: 720px;
    margin: 16px 0 12px;
    color: #3f1d1d;
    font-size: clamp(2rem, 4vw, 3.4rem);
    line-height: 1.04;
    font-weight: 950;
}

.home-welcome-banner__text {
    max-width: 660px;
    color: #5f5753;
    font-size: 1.05rem;
    line-height: 1.7;
}

.home-welcome-banner__badges,
.home-welcome-banner__categories,
.home-welcome-banner__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.home-welcome-banner__badges {
    margin-top: 18px;
}

.home-welcome-banner__badge {
    display: inline-flex;
    padding: 8px 12px;
    border-radius: 999px;
    color: #7f1d1d;
    background: rgba(128, 0, 0, 0.06);
    border: 1px solid rgba(128, 0, 0, 0.08);
    font-weight: 700;
    font-size: 0.9rem;
}

.home-welcome-banner__actions {
    margin-top: 24px;
}

.home-welcome-banner__contact-link {
    text-decoration: none;
}

.home-welcome-banner__categories {
    margin-top: 22px;
}

.home-welcome-banner__category {
    display: inline-flex;
    align-items: center;
    min-height: 36px;
    padding: 8px 13px;
    border-radius: 12px;
    text-decoration: none;
    color: #3f1d1d;
    background: #fff;
    border: 1px solid rgba(128, 0, 0, 0.10);
    box-shadow: 0 8px 18px rgba(63, 29, 29, 0.06);
    font-weight: 800;
}

.home-welcome-banner__visual {
    min-height: 340px;
}

.home-welcome-banner__image-card {
    overflow: hidden;
    border-radius: 28px;
    background: #fff;
    border: 1px solid rgba(128, 0, 0, 0.10);
    box-shadow: 0 18px 40px rgba(63, 29, 29, 0.16);
}

.home-welcome-banner__image {
    border-radius: 28px;
}

.home-welcome-banner__image-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    padding: 24px;
    flex-direction: column;
    justify-content: center;
    background:
        linear-gradient(135deg, rgba(128, 0, 0, 0.88), rgba(63, 29, 29, 0.88)),
        linear-gradient(45deg, #fffaf8, #fff2ee);
    color: #fff;
}

.home-welcome-banner__fallback-title {
    font-size: 1.45rem;
    line-height: 1.15;
    font-weight: 900;
}

.home-welcome-banner__fallback-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 18px;
}

.home-welcome-banner__fallback-grid span {
    padding: 10px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.14);
    font-weight: 800;
}

.home-welcome-banner__floating-card {
    position: absolute;
    display: grid;
    gap: 2px;
    min-width: 150px;
    padding: 13px 15px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(128, 0, 0, 0.10);
    box-shadow: 0 14px 30px rgba(63, 29, 29, 0.14);
    backdrop-filter: blur(8px);
}

.home-welcome-banner__floating-card strong {
    color: #800000;
    font-size: 1rem;
}

.home-welcome-banner__floating-card span {
    color: #6b625d;
    font-size: 0.86rem;
}

.home-welcome-banner__floating-card--top {
    top: 18px;
    right: -10px;
}

.home-welcome-banner__floating-card--bottom {
    left: -10px;
    bottom: 20px;
}

@media (max-width: 960px) {
    .home-welcome-banner {
        padding: 24px;
    }

    .home-welcome-banner__visual {
        margin-top: 22px;
        min-height: auto;
    }

    .home-welcome-banner__floating-card--top {
        right: 12px;
    }

    .home-welcome-banner__floating-card--bottom {
        left: 12px;
    }
}

@media (max-width: 600px) {
    .home-welcome-banner {
        padding: 20px;
    }

    .home-welcome-banner__actions {
        flex-direction: column;
    }

    .home-welcome-banner__actions :deep(.v-btn) {
        width: 100%;
    }

    .home-welcome-banner__floating-card {
        position: static;
        margin-top: 10px;
    }
}
</style>
