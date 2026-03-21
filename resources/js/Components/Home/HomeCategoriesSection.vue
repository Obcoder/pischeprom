<script setup>
import SectionHeader from './SectionHeader.vue'
import CategoryCard from './CategoryCard.vue'

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <section class="py-8 py-md-10 bg-white">
        <v-container>
            <SectionHeader
                title="Категории"
                subtitle="Основные направления пищевого сырья, ингредиентов и добавок"
            />

            <v-row>
                <v-col
                    v-for="category in categories"
                    :key="category.id"
                    cols="12"
                    sm="6"
                    md="4"
                    lg="3"
                >
                    <CategoryCard :category="category" />
                </v-col>
            </v-row>
        </v-container>
    </section>
</template>
