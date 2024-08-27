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

    <aside>
        <p>
            <v-card v-for="cat in categories">
                <v-card-item>
                    {{cat.name}}
                </v-card-item>
            </v-card>
        </p>
    </aside>

    <div><h1>
            Приветствуем Вас на ПИЩЕПРОМ-СЕРВЕРЕ
        </h1></div>
</template>
