<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useAppRoute } from "@/Composables/useAppRoute";

const { route } = useAppRoute();

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
})

const categoryUrl = computed(() => {
    return route('category.show', props.category.slug || props.category.id)
})
</script>

<template>
    <Link :href="categoryUrl" class="text-decoration-none">
        <v-card
            rounded="xl"
            elevation="2"
            class="category-card h-100 overflow-hidden"
        >
            <v-img
                :src="category.image || '/images/placeholders/category.jpg'"
                :alt="category.image_alt || category.name"
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
