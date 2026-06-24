<script setup>
import SectionHeader from './SectionHeader.vue'
import FieldCollectionCard from './FieldCollectionCard.vue'

defineProps({
    fields: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <section class="home-fields-section">
        <v-container>
            <SectionHeader
                title="Подборки"
                subtitle="Собранные направления товаров для конкретных производств и задач"
            />

            <v-row v-if="fields.length" dense>
                <v-col
                    v-for="field in fields"
                    :key="field.id"
                    cols="12"
                    sm="6"
                    lg="4"
                    xl="3"
                >
                    <FieldCollectionCard :field="field" />
                </v-col>
            </v-row>

            <v-alert v-else type="info" variant="tonal" rounded="lg">
                Опубликованные подборки пока не настроены.
            </v-alert>
        </v-container>
    </section>
</template>

<style scoped>
.home-fields-section {
    padding: 34px 0 42px;
    background:
        linear-gradient(180deg, #ffffff 0%, #fffaf4 54%, #ffffff 100%);
}
</style>
