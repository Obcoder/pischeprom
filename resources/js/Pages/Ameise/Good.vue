<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { ref, onMounted, computed } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import { useHead } from "@vueuse/head"; // Используем @vueuse/head
import {route} from "ziggy-js"

defineOptions({
    layout: VerwalterLayout,
});

const good = ref(null);
const loading = ref(true);
const error = ref(null);
// const route = useRoute();

// Загрузка данных товара
const fetchGood = async () => {
    try {
        const response = await axios.get(route('good.fetch'))
        good.value = response.data;
        loading.value = false;
    } catch (err) {
        error.value = err.message;
        loading.value = false;
    }
};

// Динамические метатеги для SEO
useHead({
    title: computed(() => (good.value ? `${good.value.name} - Your Store` : "Loading...")),
    meta: [
        {
            name: "description",
            content: computed(() =>
                good.value ? good.value.description.slice(0, 160) : "Loading product details..."
            ),
        },
        { name: "keywords", content: computed(() => (good.value ? `${good.value.name}, product, store` : "product")) },
        { property: "og:title", content: computed(() => (good.value ? good.value.name : "Loading...")) },
        {
            property: "og:description",
            content: computed(() => (good.value ? good.value.description.slice(0, 160) : "Loading...")),
        },
        { property: "og:image", content: computed(() => (good.value ? good.value.image_url : "/default-image.jpg")) },
        { property: "og:url", content: computed(() => window.location.href) },
    ],
});

onMounted(() => {
    fetchGood();
});
</script>

<template>
    <v-container>
        <v-row v-if="loading">
            <v-col>
                <v-progress-circular indeterminate color="primary"></v-progress-circular>
            </v-col>
        </v-row>
        <v-row v-else-if="error">
            <v-col>
                <v-alert type="error">{{ error }}</v-alert>
            </v-col>
        </v-row>
        <v-row v-else>
            <v-col cols="12" md="3"></v-col>
            <v-col cols="12" md="6">
                <v-card>
                    <v-img
                        :src="good.image_url || '/default-image.jpg'"
                        :alt="good.name"
                        lazy-src="/placeholder.jpg"
                        aspect-ratio="1"
                        cover
                        class="mb-4"
                    ></v-img>
                    <v-card-title>{{ good.name }}</v-card-title>
                    <v-card-text>
                        <p class="text-h6">Price: ${{ good.price }}</p>
                        <p>{{ good.description }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="12" md="3"></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.v-card {
    margin-top: 20px;
}
.v-img {
    border-radius: 8px;
}
</style>
