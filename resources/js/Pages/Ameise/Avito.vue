<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const ads = ref([]);
const error = ref(null);
const loading = ref(false);

const fetchAds = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/avito/ads', {
            headers: {
                Authorization: `Bearer ${localStorage.getItem('token')}`, // Предполагается, что токен хранится в localStorage
            },
        });
        ads.value = response.data.data;
    } catch (err) {
        error.value = 'Ошибка при загрузке объявлений';
        console.error(err);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchAds);
</script>

<template>
    <div class="container mx-auto p-4">
        <h2 class="text-2xl font-bold mb-4">Объявления с Avito</h2>

        <div v-if="loading" class="text-center">
            <p>Загрузка...</p>
        </div>

        <div v-else-if="error" class="text-red-500">
            {{ error }}
        </div>

        <div v-else-if="ads.length === 0" class="text-gray-500">
            Нет объявлений
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="ad in ads" :key="ad.id" class="border p-4 rounded-lg shadow">
                <h3 class="text-lg font-semibold">{{ ad.title }}</h3>
                <p class="text-gray-600">{{ ad.description }}</p>
                <p class="text-green-500 font-bold">{{ ad.price }}</p>
            </div>
        </div>
    </div>
</template>
