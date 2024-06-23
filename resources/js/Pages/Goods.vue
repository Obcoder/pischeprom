<template>
    <div>
        <div v-for="good in goods">
            <div>
                {{good.ava_image}}
            </div>
            <div>
                {{good.name}}
            </div>
        </div>
    </div>
</template>

<script setup>
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})
const props = defineProps({
    goods: Object,
})
</script>
