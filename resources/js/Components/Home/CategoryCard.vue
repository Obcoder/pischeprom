<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
    category: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <Link :href="route('category.show', category.id)" class="text-decoration-none">
        <v-card
            rounded="xl"
            elevation="2"
            class="category-card h-100 overflow-hidden"
        >
            <v-img
                :src="category.image || '/images/placeholders/category.jpg'"
                height="180"
                cover
            />

            <v-card-text class="pa-4">
                <div class="text-h6 font-weight-bold text-high-emphasis mb-2">
                    {{ category.name }}
                </div>

                <div
                    v-if="category.goods_count !== undefined"
                    class="text-body-2 text-medium-emphasis"
                >
                    {{ category.goods_count }} товаров
                </div>
            </v-card-text>
        </v-card>
    </Link>
</template>

<style scoped>
.category-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.category-card:hover {
    transform: translateY(-4px);
}
</style>
