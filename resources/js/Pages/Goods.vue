<template>
    <div>
        <div v-for="good in goods">
            <div class="grid grid-cols-10">
                <div class="col-span-4">
                    <img :src="good.ava_image" class="max-w-[103px]">
                </div>
                <div class="col-span-6">
                    {{good.name}}
                </div>
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
