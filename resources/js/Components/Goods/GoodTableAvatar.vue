<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
})

const visible = ref(false)
const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

function onIntersect(isIntersecting) {
    if (isIntersecting) visible.value = true
}
</script>

<template>
    <div
        v-intersect.once="{ handler: onIntersect, options: { rootMargin: '80px' } }"
        class="good-table-avatar"
    >
        <img
            v-if="visible && src && !failed"
            :src="src"
            :alt="name"
            width="64"
            height="64"
            loading="lazy"
            decoding="async"
            @error="failed = true"
        >
        <v-icon v-else icon="mdi-package-variant-closed" size="28" aria-hidden="true" />
    </div>
</template>

<style scoped>
.good-table-avatar { display: grid; place-items: center; width: 64px; height: 64px; flex-shrink: 0; overflow: hidden; border: 1px solid #e4e1e7; background: #f5f5f6; color: #8a818f; }
.good-table-avatar img { display: block; width: 100%; height: 100%; object-fit: cover; }
</style>
