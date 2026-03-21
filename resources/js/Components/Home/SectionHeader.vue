<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <div class="mb-6">
        <div class="text-h4 font-weight-bold mb-2">
            {{ title }}
        </div>

        <div
            v-if="subtitle"
            class="text-body-1 text-medium-emphasis"
        >
            {{ subtitle }}
        </div>
    </div>
</template>
