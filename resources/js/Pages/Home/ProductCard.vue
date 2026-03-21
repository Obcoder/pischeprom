<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
    product: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <Link :href="route('goods.show', product.slug)" class="text-decoration-none">
        <v-card
            rounded="xl"
            elevation="2"
            class="product-card h-100 d-flex flex-column"
        >
            <v-img
                :src="product.image || '/images/placeholders/product.jpg'"
                height="220"
                cover
            />

            <v-card-text class="pa-4 d-flex flex-column flex-grow-1">
                <div class="text-caption text-medium-emphasis mb-2">
                    {{ product.category_name || 'Каталог' }}
                </div>

                <div class="text-h6 font-weight-bold text-high-emphasis mb-2">
                    {{ product.name }}
                </div>

                <div
                    v-if="product.short_description"
                    class="text-body-2 text-medium-emphasis mb-4"
                >
                    {{ product.short_description }}
                </div>

                <div class="d-flex flex-wrap ga-2 mb-4">
                    <v-chip
                        v-if="product.country"
                        size="small"
                        variant="outlined"
                    >
                        {{ product.country }}
                    </v-chip>

                    <v-chip
                        v-if="product.packing"
                        size="small"
                        variant="outlined"
                    >
                        {{ product.packing }}
                    </v-chip>
                </div>

                <div class="mt-auto d-flex align-center justify-space-between ga-2">
                    <div class="text-subtitle-1 font-weight-bold text-primary">
                        <template v-if="product.price_from">
                            от {{ product.price_from }} {{ product.currency || '₽' }}
                        </template>
                        <template v-else>
                            По запросу
                        </template>
                    </div>

                    <v-btn
                        color="primary"
                        variant="flat"
                    >
                        Подробнее
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>
    </Link>
</template>

<style scoped>
.product-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-card:hover {
    transform: translateY(-4px);
}
</style>
