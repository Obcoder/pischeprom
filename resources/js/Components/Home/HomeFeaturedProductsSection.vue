<script setup>
import SectionHeader from './SectionHeader.vue'
import ProductCard from './ProductCard.vue'

defineProps({
    title: {
        type: String,
        default: 'Товары',
    },
    subtitle: {
        type: String,
        default: '',
    },
    products: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <section class="py-8 py-md-10">
        <v-container>
            <SectionHeader
                :title="title"
                :subtitle="subtitle"
            />

            <v-row>
                <v-col
                    v-for="product in products"
                    :key="product.id"
                    cols="12"
                    sm="6"
                    md="4"
                    lg="3"
                >
                    <ProductCard :product="product" />
                </v-col>
            </v-row>
        </v-container>
    </section>
</template>
