<script setup>
import { computed } from 'vue'

const props = defineProps({
    goodId: { type: [String, Number], required: true },
    goods: { type: Object, default: () => ({}) },
})
const good = computed(() => props.goods[String(props.goodId)])
</script>

<template>
    <a v-if="good?.url" :href="good.url" :data-good-id="goodId" data-inline-good-link><slot /></a>
    <span v-else><slot /></span>
</template>
