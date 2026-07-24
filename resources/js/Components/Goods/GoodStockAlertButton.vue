<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'

import { useAppRoute } from '@/Composables/useAppRoute'
import { useYandexMetrica } from '@/Composables/useYandexMetrica'
import { canSubscribeToGoodStock } from '@/Pages/Helpers/goodAvailability'

const props = defineProps({
    good: {
        type: Object,
        required: true,
    },
    availability: {
        type: Object,
        default: null,
    },
    label: {
        type: String,
        default: 'Оповестить о поступлении в MAX',
    },
    block: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
})

const { route } = useAppRoute()
const { reachGoal } = useYandexMetrica(
    import.meta.env.VITE_YANDEX_METRICA_COUNTER_ID,
)

const loading = ref(false)
const errorMessage = ref('')

const canSubscribe = computed(() => {
    return canSubscribeToGoodStock(props.good, props.availability)
})

async function subscribe() {
    if (!canSubscribe.value || loading.value) {
        return
    }

    errorMessage.value = ''
    loading.value = true

    reachGoal('max_stock_alert_click', {
        good_id: props.good.id,
        good_name: props.good.name,
    })

    try {
        const response = await axios.post(
            route('public.good-stock-alerts.store', { good: props.good.id }),
        )
        const deepLink = response?.data?.deep_link

        if (!deepLink) {
            throw new Error('MAX-ссылка не получена.')
        }

        window.location.assign(deepLink)
    } catch (error) {
        errorMessage.value = error?.response?.data?.message
            || error?.message
            || 'Не удалось оформить оповещение. Попробуйте ещё раз.'
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div
        v-if="canSubscribe"
        class="good-stock-alert-action"
        :class="{ 'good-stock-alert-action--compact': compact }"
    >
        <v-btn
            color="warning"
            variant="flat"
            rounded="xl"
            :block="block"
            :size="compact ? 'small' : 'default'"
            :loading="loading"
            prepend-icon="mdi-bell-outline"
            @click.stop.prevent="subscribe"
        >
            {{ label }}
        </v-btn>

        <div
            v-if="errorMessage"
            class="good-stock-alert-action__error"
            role="alert"
        >
            {{ errorMessage }}
        </div>
    </div>
</template>

<style scoped>
.good-stock-alert-action {
    display: grid;
    gap: 7px;
}

.good-stock-alert-action__error {
    color: rgb(var(--v-theme-error));
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.3;
}

.good-stock-alert-action--compact {
    width: 100%;
}

.good-stock-alert-action--compact :deep(.v-btn) {
    min-height: 34px;
    padding-inline: 10px;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0;
    text-transform: none;
}
</style>
