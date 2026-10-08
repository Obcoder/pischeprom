<script setup>
defineProps({
    guide: { type: Object, required: true },
    guideKey: { type: String, required: true },
    inlineGoods: { type: Object, default: () => ({}) },
})
</script>

<template>
    <div :data-guide-article="guideKey"><component :is="guide.article" :inline-goods="inlineGoods" /></div>
</template>
