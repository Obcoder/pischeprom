<script setup>
import { computed, ref } from 'vue'
import MaxMessageDialog from '@/Components/Max/MaxMessageDialog.vue'

const props = defineProps({
    phone: {
        type: String,
        default: '',
    },
    entityId: {
        type: [Number, String],
        default: null,
    },
    unitId: {
        type: [Number, String],
        default: null,
    },
    contextTitle: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'x-small',
    },
    color: {
        type: String,
        default: '#800000',
    },
    variant: {
        type: String,
        default: 'text',
    },
})

const dialog = ref(false)

const hasPhone = computed(() => String(props.phone || '').trim() !== '')
</script>

<template>
    <span class="max-contact-button">
        <v-btn
            icon="mdi-message-text-outline"
            :size="size"
            :color="color"
            :variant="variant"
            :disabled="!hasPhone"
            title="Написать в MAX"
            aria-label="Написать в MAX"
            @click.stop.prevent="dialog = true"
        />

        <MaxMessageDialog
            v-model="dialog"
            :phone="phone"
            :entity-id="entityId"
            :unit-id="unitId"
            :context-title="contextTitle"
        />
    </span>
</template>

<style scoped>
.max-contact-button {
    display: inline-flex;
    align-items: center;
    line-height: 1;
}
</style>
