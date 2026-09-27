<script setup>
import { computed } from 'vue'
import { mailHtmlDocument } from './mailHtmlDocument'

const props = defineProps({ html: { type: String, required: true } })
const document = computed(() => mailHtmlDocument(props.html))
</script>

<template>
    <iframe
        class="mail-html-body rounded"
        title="Текст письма"
        sandbox="allow-popups allow-popups-to-escape-sandbox"
        referrerpolicy="no-referrer"
        :srcdoc="document"
    />
</template>

<style scoped>
.mail-html-body {
    display: block;
    width: 100%;
    height: 65vh;
    min-height: 22rem;
    border: 0;
    background: white;
    resize: vertical;
}
</style>
