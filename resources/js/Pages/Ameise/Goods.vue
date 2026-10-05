<script setup>
import { Head } from '@inertiajs/vue3'
import Goods from '@/Components/Dictionaries/Goods.vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({ layout: VerwalterLayout })

const workspaceDefaults = {
    VCard: { rounded: 0, elevation: 0 },
    VBtn: { rounded: 0, elevation: 0, color: '#352345' },
    VChip: { rounded: 0, color: '#352345' },
    VTextField: { variant: 'outlined', density: 'compact' },
    VSelect: { variant: 'outlined', density: 'compact' },
    VAutocomplete: { variant: 'outlined', density: 'compact' },
}
</script>

<template>
    <Head title="Товары — Ameise" />
    <v-container fluid class="goods-page pa-2">
        <v-defaults-provider :defaults="workspaceDefaults">
            <Goods />
        </v-defaults-provider>
    </v-container>
</template>

<style scoped>
.goods-page {
    display: flex;
    flex-direction: column;
    height: calc(100dvh - 58px);
    min-height: 0;
    overflow: hidden;
}
.goods-page :deep(.goods-admin) {
    flex: 1 1 0;
    min-height: 0;
}
</style>
