<script setup>
import PublicCatalogCards from '@/Components/Catalog/PublicCatalogCards.vue'
import SectionHeader from './SectionHeader.vue'

defineProps({ items: { type: Array, default: () => [] } })
</script>

<template>
    <section v-if="items.length" class="py-8 py-md-10">
        <v-container>
            <SectionHeader title="Витрина каталога" subtitle="Подборки и направления нашего ассортимента" />
            <PublicCatalogCards :items="items" />
        </v-container>
    </section>
</template>
