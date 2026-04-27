<script setup>
import { computed } from 'vue'
import { useHead } from '@vueuse/head'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import CityShowPage from '@/Components/Geography/Cities/Show/CityShowPage.vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    cityId: {
        type: [Number, String],
        required: false,
        default: null,
    },
    city: {
        type: Object,
        required: false,
        default: null,
    },
})

const resolvedCityId = computed(() => props.cityId ?? props.city?.id)

useHead({
    title: 'Город',
    meta: [
        {
            name: 'description',
            content: 'Страница города',
        },
    ],
})
</script>

<template>
    <CityShowPage
        :city-id="resolvedCityId"
        :initial-city="city"
    />
</template>
