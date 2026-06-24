<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from "@/Composables/useAppRoute";

const { route } = useAppRoute();

const props = defineProps({
    fields: {
        type: Array,
        default: () => [],
    },
})

const visibleFields = computed(() => props.fields.slice(0, 9))
</script>

<template>
    <v-card class="home-welcome-banner" rounded="xl" elevation="0">
        <div class="home-welcome-banner__glow home-welcome-banner__glow--one" />
        <div class="home-welcome-banner__glow home-welcome-banner__glow--two" />

        <v-row dense class="align-center">
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

            <v-col cols="12" lg="6">
                <div class="home-welcome-banner__meta">
                    <div class="home-welcome-banner__fields">
                        <Link
                            v-for="field in visibleFields"
                            :key="field.id"
                            :href="route('public.fields.show', field.slug || field.id)"
                            class="home-welcome-banner__field"
                        >
                            <span class="home-welcome-banner__field-title">
                                {{ field.title || field.name }}
                            </span>
                            <span class="home-welcome-banner__field-count">
                                {{ field.goods_count || 0 }} товаров
                            </span>
                        </Link>

                        <div
                            v-if="!visibleFields.length"
                            class="home-welcome-banner__field home-welcome-banner__field--empty"
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
    justify-content: flex-end;
}

.home-welcome-banner__field {
    display: flex;
    align-items: center;
    min-width: 138px;
    min-height: 54px;
    flex-direction: column;
    justify-content: center;
    padding: 9px 12px;
    border-radius: 14px;
    text-decoration: none;
    color: #3f1d1d;
    background: #fff;
    border: 1px solid rgba(128, 0, 0, 0.10);
    box-shadow: 0 6px 14px rgba(63, 29, 29, 0.05);
    font-size: 0.82rem;
    font-weight: 800;
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}

.home-welcome-banner__field:hover {
    border-color: rgba(128, 0, 0, 0.24);
    box-shadow: 0 10px 20px rgba(63, 29, 29, 0.09);
    transform: translateY(-2px);
}

.home-welcome-banner__field-title {
    max-width: 160px;
    overflow: hidden;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.home-welcome-banner__field-count {
    margin-top: 2px;
    color: #47765a;
    font-size: 0.7rem;
    font-weight: 800;
}

.home-welcome-banner__field--empty {
    color: #7a6a62;
    text-align: center;
}

@media (max-width: 960px) {
    .home-welcome-banner {
        padding: 14px;
    }

    .home-welcome-banner__fields {
        justify-content: flex-start;
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
}
</style>
