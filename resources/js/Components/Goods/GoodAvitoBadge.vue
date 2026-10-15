<script setup>
defineProps({ count: { type: Number, default: 0 } })
</script>

<template>
    <span v-if="count > 0" class="good-avito-badge" :title="`Связанных объявлений Авито: ${count}`" :aria-label="`Авито: связанных объявлений ${count}`">
        <span class="good-avito-badge__dot" aria-hidden="true" />Авито<span v-if="count > 1"> · {{ count }}</span>
    </span>
</template>

<style scoped>
.good-avito-badge { display: inline-flex; align-items: center; gap: 4px; padding: 1px 6px; border: 1px solid #d4c9e9; border-radius: 5px; background: #f5f0fc; color: #68419a; font: 600 10px/16px system-ui, sans-serif; white-space: nowrap; vertical-align: middle; }
.good-avito-badge__dot { width: 5px; height: 5px; flex-shrink: 0; border-radius: 50%; background: #975ad4; }
</style>
