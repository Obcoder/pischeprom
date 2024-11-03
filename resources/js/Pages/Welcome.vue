<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})

const props = defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
    categories: {
        type: Object,
    }
});


</script>

<template>
    <Head title="ПИЩЕПРОМ-СЕРВЕР: пищевое сырьё, пищевые ингредиенты и добавки, стабильно, каталог пищевых технологий" />

    <v-container>
        <v-row>
            <v-col>
                <v-card>
                    <v-card-title>Лецитин</v-card-title>
                    <v-card-subtitle>подсолнечный</v-card-subtitle>
                </v-card>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
