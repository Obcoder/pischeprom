<script setup>
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import { computed } from 'vue'

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
})

const products = computed(() => props.category?.products ?? [])
</script>

<template>
    <v-container class="py-8">
        <v-row class="mb-6" align="center">
            <v-col cols="12" md="3">
                <v-img
                    v-if="category.image"
                    :src="category.image"
                    aspect-ratio="1/1"
                    cover
                    rounded
                />
            </v-col>

            <v-col cols="12" md="9">
                <h1 class="text-h4 font-weight-bold mb-2">
                    {{ category.name }}
                </h1>

                <div class="text-body-1 text-medium-emphasis">
                    Товаров в категории: {{ category.products_count ?? products.length }}
                </div>
            </v-col>
        </v-row>

        <v-row>
            <v-col cols="12" md="8" lg="6">
                <v-card rounded="xl" elevation="2">
                    <v-card-title>Товары категории</v-card-title>

                    <v-card-text>
                        <v-list v-if="products.length">
                            <v-list-item
                                v-for="product in products"
                                :key="product.id"
                            >
                                <div>{{ product.rus || product.name }}</div>
                            </v-list-item>
                        </v-list>

                        <div
                            v-else
                            class="text-body-2 text-medium-emphasis"
                        >
                            В этой категории пока нет опубликованных товаров.
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
