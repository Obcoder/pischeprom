<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: null,
    },
    headerColor: {
        type: String,
        default: 'maroon',
    },
    compact: {
        type: Boolean,
        default: false,
    },
    stretch: {
        type: Boolean,
        default: false,
    },
    bodyClass: {
        type: [String, Array, Object],
        default: null,
    },
})

const cardClasses = computed(() => [
    'base-section-card',
    props.stretch ? 'h-100' : null,
])

const headerClasses = computed(() => [
    'base-section-card__header',
    `base-section-card__header--${props.headerColor}`,
    props.compact ? 'base-section-card__header--compact' : null,
])

const bodyClasses = computed(() => [
    'base-section-card__body',
    props.compact ? 'base-section-card__body--compact' : null,
    props.bodyClass,
])
</script>

<template>
    <v-card
        rounded="xl"
        elevation="1"
        border
        :class="cardClasses"
    >
        <div :class="headerClasses">
            <div class="d-flex align-center min-w-0">
                <v-icon
                    v-if="icon"
                    :icon="icon"
                    size="small"
                    class="me-2 base-section-card__icon"
                />

                <div class="base-section-card__title text-truncate">
                    {{ title }}
                </div>
            </div>

            <div class="base-section-card__actions">
                <slot name="actions" />
            </div>
        </div>

        <v-card-text :class="bodyClasses">
            <slot />
        </v-card-text>
    </v-card>
</template>

<style scoped>
.base-section-card {
    overflow: hidden;
    background: rgba(15, 23, 42, 0.96);
}

.base-section-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 48px;
    padding: 9px 14px;
    border-bottom: 1px solid rgba(127, 29, 29, 0.55);
}

.base-section-card__header--compact {
    min-height: 40px;
    padding-top: 6px;
    padding-bottom: 6px;
}

.base-section-card__header--maroon {
    color: #ffe4e6;
    background:
        linear-gradient(
            135deg,
            rgba(76, 5, 25, 0.98),
            rgba(127, 29, 29, 0.94),
            rgba(88, 28, 28, 0.98)
        );
    box-shadow:
        inset 0 -1px 0 rgba(255, 255, 255, 0.08),
        0 8px 24px rgba(76, 5, 25, 0.2);
}

.base-section-card__header--default {
    color: inherit;
    background: rgba(15, 23, 42, 0.96);
}

.base-section-card__header--blue {
    color: #dbeafe;
    background:
        linear-gradient(
            135deg,
            rgba(30, 41, 59, 0.98),
            rgba(30, 64, 175, 0.88)
        );
}

.base-section-card__icon {
    opacity: 0.95;
}

.base-section-card__title {
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.base-section-card__actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.base-section-card__body {
    padding: 14px;
}

.base-section-card__body--compact {
    padding: 10px;
}
</style>
