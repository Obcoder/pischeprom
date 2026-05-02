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
        color="white"
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
    background: #ffffff;
}

.base-section-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 48px;
    padding: 9px 14px;
    border-bottom: 1px solid rgba(128, 0, 32, 0.18);
}

.base-section-card__header--compact {
    min-height: 40px;
    padding-top: 6px;
    padding-bottom: 6px;
}

.base-section-card__header--maroon {
    color: #5f0f24;
    background:
        linear-gradient(
            135deg,
            rgba(128, 0, 32, 0.09),
            rgba(128, 0, 32, 0.045)
        );
}

.base-section-card__header--default {
    color: inherit;
    background: #ffffff;
}

.base-section-card__header--blue {
    color: #1e3a8a;
    background:
        linear-gradient(
            135deg,
            rgba(59, 130, 246, 0.08),
            rgba(59, 130, 246, 0.035)
        );
}

.base-section-card__icon {
    opacity: 0.9;
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
    background: #ffffff;
}

.base-section-card__body--compact {
    padding: 10px;
}
</style>
