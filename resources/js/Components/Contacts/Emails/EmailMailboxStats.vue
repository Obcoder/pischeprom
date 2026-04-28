<script setup>
defineProps({
    total: {
        type: Number,
        default: 0,
    },
    loading: Boolean,
})
</script>

<template>
    <div class="d-flex align-center ga-2">
        <v-chip
            size="small"
            color="teal"
            variant="tonal"
        >
            Emails: {{ total }}
        </v-chip>

        <v-progress-circular
            v-if="loading"
            indeterminate
            size="18"
            width="2"
            color="teal"
        />
    </div>
</template>
