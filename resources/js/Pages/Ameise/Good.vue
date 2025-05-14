<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { ref, onMounted, computed } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import { useHead } from "@vueuse/head"; // Используем @vueuse/head
import {route} from "ziggy-js"
import {useDate} from "vuetify";
defineOptions({
    layout: VerwalterLayout,
});
const props = defineProps({
    good: Object,
})
const date = useDate()

const good = ref(null);
const loading = ref(true);
const error = ref(null);
// const route = useRoute();

// Загрузка данных товара
const fetchGood = async () => {
    try {
        const response = await axios.get(route('good.fetch', props.good.id))
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
            <v-col lg="3" md="1">
                <v-card class="w-full">
                    <v-img
                        :src="good.ava_image || '/default-image.jpg'"
                        :alt="good.name"
                        lazy-src="/placeholder.jpg"
                        aspect-ratio="1"
                        cover
                        class="mb-4"
                    ></v-img>
                    <v-card-title>
                        <div class="whitespace-normal">
                            {{ good.name }}
                        </div>
                    </v-card-title>
                    <v-card-text>
                        <p>{{ good.description }}</p>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col lg="3" md="1">
                <v-list border
                        rounded
                >
                    <v-list-item v-for="price in good.prices">
                        <v-row>
                            <v-col>
                                <span class="text-xs font-mono"
                                >
                                    {{date.format(price.created_at, 'fullDate')}}</span>
                            </v-col>
                            <v-col>
                                <span>{{price.price}}</span>
                            </v-col>
                            <v-col>
                                <span>{{price.currency.code}}</span>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col lg="3">
                <v-list border
                        rounded
                >
                    <v-list-item v-for="sale in good.sales">
                        <v-row>
                            <v-col>
                                <span>{{sale.date}}</span>
                            </v-col>
                            <v-col>
                                <span>{{sale.pivot.quantity}}</span>
                            </v-col>
                            <v-col>
                                <span>{{sale.pivot.price}}</span>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>
            </v-col>
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
