<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})

defineProps({
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
});


</script>

<template>
    <Head title="Welcome" />

    <aside>
        <p>
            Категории
        </p>
    </aside>

    <div>
        Приветствуем Вас на ПИЩЕПРОМ-СЕРВЕРЕ
    </div>
</template>
