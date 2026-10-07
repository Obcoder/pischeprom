<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
    node: { type: Object, required: true },
    icon: { type: String, default: 'mdi-image-outline' },
})
const emit = defineEmits(['open'])
const failed = ref(new Set())
const sources = computed(() => [...new Set([props.node.thumbnail_url, props.node.image]
    .filter(url => typeof url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(url)))])
const source = computed(() => sources.value.find(url => !failed.value.has(url)))
function imageFailed(event) {
    const url = event.target.getAttribute('src')
    if (url) failed.value = new Set([...failed.value, url])
}
watch(() => [props.node.id, props.node.thumbnail_url, props.node.image], () => { failed.value = new Set() })
</script>

<template>
    <button type="button" class="catalog-avatar" :aria-label="`Открыть галерею: ${node.name}`" title="Открыть фотографии" @click.stop="emit('open', node)">
        <img v-if="source" :key="source" :src="source" alt="" width="64" height="64" loading="lazy" decoding="async" @error="imageFailed">
        <v-icon v-else :icon="sources.length ? 'mdi-image-off-outline' : icon" size="25" />
        <span class="catalog-avatar__zoom" aria-hidden="true"><v-icon icon="mdi-magnify-plus-outline" size="16" /></span>
    </button>
</template>

<style scoped>
.catalog-avatar { position: relative; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 64px; width: 64px; min-width: 64px; max-width: 64px; height: 64px; min-height: 64px; max-height: 64px; aspect-ratio: 1; margin: 2px; padding: 0; overflow: hidden; box-sizing: border-box; border: 1px solid #e5dfec; border-radius: 0; background: #f4f0f7; color: #aa9ab6; cursor: zoom-in; }
/* Absolute sizing keeps intrinsic portrait dimensions out of layout. */
.catalog-avatar > img { position: absolute; inset: 0; display: block; width: 100%; min-width: 0; max-width: 100%; height: 100%; min-height: 0; max-height: 100%; object-fit: cover; object-position: center; }
.catalog-avatar:focus-visible { outline: 2px solid #785491; outline-offset: 1px; }
.catalog-avatar__zoom { position: absolute; right: 0; bottom: 0; display: grid; place-items: center; width: 21px; height: 21px; background: #352345bb; color: #fff; opacity: 0; transition: opacity .15s; }
.catalog-avatar:hover .catalog-avatar__zoom, .catalog-avatar:focus-visible .catalog-avatar__zoom { opacity: 1; }
</style>
