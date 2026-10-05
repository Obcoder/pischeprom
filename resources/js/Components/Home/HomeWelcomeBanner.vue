<script setup>
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from '@/Composables/useAppRoute'

const { route } = useAppRoute()
</script>

<template>
    <section class="home-welcome-banner" aria-labelledby="home-welcome-title">
        <div class="home-welcome-banner__intro">
            <span class="home-welcome-banner__icon" aria-hidden="true">
                <v-icon icon="mdi-compass-outline" size="28" />
            </span>
            <span class="home-welcome-banner__eyebrow">Добро пожаловать</span>
        </div>

        <h2 id="home-welcome-title" class="home-welcome-banner__title">
            Ваш проводник в пищевой промышленности
        </h2>

        <p class="home-welcome-banner__text">
            Помогаем найти сырьё, ингредиенты и продукты для вашего производства.
        </p>

        <Link :href="route('public.goods.index')" class="home-welcome-banner__link">
            Открыть каталог
            <v-icon icon="mdi-arrow-right" size="18" aria-hidden="true" />
        </Link>
    </section>
</template>

<style scoped>
.home-welcome-banner {
    position: relative;
    display: flex;
    width: 100%;
    aspect-ratio: 1;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 20px;
    overflow: hidden;
    border: 1px solid rgba(128, 0, 0, 0.12);
    border-radius: 24px;
    background:
        radial-gradient(circle at 100% 0%, rgba(230, 177, 113, 0.24), transparent 54%),
        linear-gradient(145deg, #fff8ed, #fff1e8);
    color: #3f1d1d;
}

.home-welcome-banner::after {
    position: absolute;
    right: -44px;
    bottom: -64px;
    width: 160px;
    height: 160px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 50%;
    content: '';
    pointer-events: none;
}

.home-welcome-banner__intro {
    display: flex;
    align-items: center;
    gap: 10px;
}

.home-welcome-banner__icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 14px;
    background: #800000;
    color: #fff8ed;
}

.home-welcome-banner__eyebrow {
    color: #7b5141;
    font-size: 0.75rem;
    font-weight: 700;
}

.home-welcome-banner__title {
    margin: 0;
    font-size: clamp(1.2rem, 1.5vw, 1.6rem);
    line-height: 1.18;
    font-weight: 800;
    overflow-wrap: anywhere;
    text-wrap: balance;
}

.home-welcome-banner__text {
    margin: 0;
    color: #72584b;
    font-size: 0.875rem;
    line-height: 1.5;
}

.home-welcome-banner__link {
    position: relative;
    z-index: 1;
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    color: #800000;
    font-size: 0.875rem;
    font-weight: 800;
    text-decoration: none;
}

.home-welcome-banner__link:hover {
    text-decoration: underline;
}

.home-welcome-banner__link:focus-visible {
    outline: 2px solid #800000;
    outline-offset: 4px;
    border-radius: 4px;
}

@media (max-width: 599px) {
    .home-welcome-banner {
        gap: 10px;
    }

    .home-welcome-banner__title {
        max-width: 300px;
        font-size: clamp(1.4rem, 6vw, 1.75rem);
    }
}
</style>
