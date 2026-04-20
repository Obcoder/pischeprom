<script setup>
defineProps({
    title: String,
    icon: {
        type: String,
        default: null,
    },
})
</script>

<template>
    <v-card rounded="xl" elevation="1" border class="h-100">
        <v-card-item>
            <template v-if="icon" #prepend>
                <v-icon :icon="icon" class="me-2" />
            </template>

            <v-card-title class="text-subtitle-1 font-weight-bold">
                {{ title }}
            </v-card-title>

            <template #append>
                <slot name="actions" />
            </template>
        </v-card-item>

        <v-divider />

        <v-card-text class="pa-4">
            <slot />
        </v-card-text>
    </v-card>
</template>
