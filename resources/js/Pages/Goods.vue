<template>
    <div>
        <div v-for="good in goods">
            <div>
                <img :src="good.ava_image" class="max-w-[103px]">
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
