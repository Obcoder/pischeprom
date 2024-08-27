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
            <div v-for="cat in categories">
                {{cat.name}}
            </div>
        </p>
    </aside>

    <div><h1>
            Приветствуем Вас на ПИЩЕПРОМ-СЕРВЕРЕ
        </h1></div>
</template>
