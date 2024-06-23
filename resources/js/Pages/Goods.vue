<template>
    <div>
        <div v-for="good in goods">
            <div class="relative">
                <div class="absolute z-50 text-white font-bold">
                    200 руб/кг
                </div>
                <div class="absolute z-1">
                    <img :src="good.ava_image" class="max-w-[103px]">
                </div>
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
