<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from '@/Composables/useAppRoute'

const props = defineProps({
    field: {
        type: Object,
        required: true,
    },
})

const { route } = useAppRoute()

const title = computed(() => props.field.title || props.field.name || 'Подборка')
const description = computed(() => props.field.description || 'Товары этой подборки')
const fieldUrl = computed(() => route('public.fields.show', props.field.slug || props.field.id))
</script>

<template>
    <Link :href="fieldUrl" class="field-card-link">
        <article class="field-card">
            <div class="field-card__mark" />

            <div class="field-card__content">
                <div class="field-card__eyebrow">Подборка</div>

                <h3 class="field-card__title">
                    {{ title }}
                </h3>

                <p class="field-card__description">
                    {{ description }}
                </p>
            </div>

            <div class="field-card__footer">
                <span>{{ field.goods_count || 0 }} товаров</span>
                <span class="field-card__arrow">Перейти</span>
            </div>
        </article>
    </Link>
</template>

<style scoped>
.field-card-link {
    display: block;
    height: 100%;
    color: inherit;
    text-decoration: none;
}

.field-card {
    position: relative;
    display: flex;
    min-height: 156px;
    height: 100%;
    overflow: hidden;
    flex-direction: column;
    justify-content: space-between;
    padding: 16px;
    border: 1px solid rgba(71, 118, 90, 0.18);
    border-radius: 18px;
    background:
        radial-gradient(circle at 92% 10%, rgba(220, 122, 81, 0.18), transparent 26%),
        linear-gradient(135deg, #fffdfa 0%, #f4f1e8 58%, #eef5ed 100%);
    box-shadow: 0 12px 28px rgba(35, 55, 42, 0.08);
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.field-card:hover {
    border-color: rgba(71, 118, 90, 0.42);
    box-shadow: 0 18px 38px rgba(35, 55, 42, 0.13);
    transform: translateY(-3px);
}

.field-card__mark {
    position: absolute;
    right: -22px;
    top: -34px;
    width: 96px;
    height: 96px;
    border-radius: 28px;
    background: rgba(128, 0, 0, 0.08);
    transform: rotate(14deg);
}

.field-card__content,
.field-card__footer {
    position: relative;
    z-index: 1;
}

.field-card__eyebrow {
    margin-bottom: 7px;
    color: #800000;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.field-card__title {
    margin: 0;
    color: #2f3129;
    font-size: 1.08rem;
    font-weight: 950;
    line-height: 1.08;
}

.field-card__description {
    display: -webkit-box;
    min-height: 40px;
    margin: 8px 0 0;
    overflow: hidden;
    color: #5c6258;
    font-size: 0.86rem;
    line-height: 1.35;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.field-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 14px;
    color: #47765a;
    font-size: 0.78rem;
    font-weight: 800;
}

.field-card__arrow {
    color: #800000;
}
</style>
