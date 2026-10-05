<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
})
defineEmits(['preview'])

const visible = ref(false)
const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

function onIntersect(isIntersecting) {
    if (isIntersecting) visible.value = true
}
</script>

<template>
    <button
        v-intersect.once="{ handler: onIntersect, options: { rootMargin: '80px' } }"
        type="button"
        class="good-table-avatar"
        :aria-label="`Просмотр товара: ${name}`"
        @click="$emit('preview')"
    >
        <img
            v-if="visible && src && !failed"
            :src="src"
            alt=""
            width="48"
            height="48"
            loading="lazy"
            decoding="async"
            @error="failed = true"
        >
        <v-icon v-else icon="mdi-package-variant-closed" size="24" aria-hidden="true" />
    </button>
</template>

<style scoped>
.good-table-avatar { display: grid; place-items: center; width: 48px; height: 48px; flex-shrink: 0; overflow: hidden; border: 1px solid #e4e1e7; background: #f5f5f6; color: #8a818f; }
.good-table-avatar img { display: block; width: 100%; height: 100%; object-fit: cover; }
.good-table-avatar:hover { border-color: #352345; }
</style>
