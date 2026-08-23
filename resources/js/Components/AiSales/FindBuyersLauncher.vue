<script setup>
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'
import FindBuyersWizard from '@/Components/AiSales/FindBuyersWizard.vue'

const props = defineProps({
    sourceType: {
        type: String,
        required: true,
        validator: value => ['product', 'good'].includes(value),
    },
    sourceId: {
        type: [Number, String],
        required: true,
    },
})

const context = ref(null)
const loading = ref(false)
const visible = ref(false)
const wizardOpen = ref(false)

const selectable = computed(() => (
    context.value?.eligibility?.eligible
    || context.value?.eligibility?.reason_code === 'product_selection_required'
))

async function loadContext() {
    if (!props.sourceId) return
    loading.value = true
    try {
        const { data } = await axios.get('/api/ai-sales/find-buyers/launch-context', {
            params: {
                source_type: props.sourceType,
                source_id: props.sourceId,
            },
        })
        context.value = data.data
        visible.value = true
    } catch (error) {
        if (![401, 403, 404].includes(error?.response?.status)) {
            console.error('Find Buyers launch context failed safely.', error)
        }
        context.value = null
        visible.value = false
    } finally {
        loading.value = false
    }
}

watch(() => [props.sourceType, props.sourceId], loadContext)
onMounted(loadContext)
</script>

<template>
    <div v-if="visible" class="find-buyers-launcher">
        <v-btn
            color="deep-purple"
            variant="elevated"
            prepend-icon="mdi-robot-outline"
            :loading="loading"
            :disabled="!selectable"
            @click="wizardOpen = true"
        >
            🤖 Найти покупателей
        </v-btn>

        <div
            v-if="!selectable && context?.eligibility?.message"
            class="text-caption text-error mt-1"
        >
            {{ context.eligibility.message }}
        </div>

        <FindBuyersWizard
            v-if="context"
            v-model="wizardOpen"
            :launch-context="context"
            @context-updated="context = $event"
        />
    </div>
</template>
