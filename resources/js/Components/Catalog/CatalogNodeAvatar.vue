<script setup>
import { computed, ref, watch } from 'vue'
import { catalogAvatarSources } from './catalogAvatarSources.js'

const props = defineProps({
    node: { type: Object, required: true },
    size: { type: [Number, String], default: 28 },
})
const failed = ref(new Set())
const sources = computed(() => catalogAvatarSources(props.node))
const source = computed(() => sources.value.find(url => !failed.value.has(url)))
const pixels = computed(() => Number.isFinite(Number(props.size)) && Number(props.size) > 0 ? Number(props.size) : 28)
function imageFailed(event) {
    const url = event.target.getAttribute('src')
    if (url) failed.value = new Set([...failed.value, url])
}
watch(() => [props.node?.id, props.node?.thumbnail_url, props.node?.image], () => { failed.value = new Set() })
</script>

<template>
    <img v-if="source" :key="source" class="catalog-node-avatar" :src="source" alt="" aria-hidden="true" :width="pixels" :height="pixels" :style="{ '--catalog-avatar-size': `${pixels}px` }" loading="lazy" decoding="async" @error="imageFailed">
</template>

<style scoped>
.catalog-node-avatar { display: inline-block; flex: 0 0 var(--catalog-avatar-size); width: var(--catalog-avatar-size); min-width: var(--catalog-avatar-size); max-width: var(--catalog-avatar-size); height: var(--catalog-avatar-size); min-height: var(--catalog-avatar-size); max-height: var(--catalog-avatar-size); aspect-ratio: 1; object-fit: contain; object-position: center; border-radius: 0; vertical-align: middle; background: transparent; }
</style>
