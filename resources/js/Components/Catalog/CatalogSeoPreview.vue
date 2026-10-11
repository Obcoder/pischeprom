<script setup>
import { computed } from 'vue'
import { catalogSeoPreview } from './seoPreview.js'

const props = defineProps({
    form: { type: Object, required: true },
    record: { type: Object, default: null },
    nodes: { type: Array, default: () => [] },
    levels: { type: Array, default: () => [] },
})
const preview = computed(() => catalogSeoPreview(props.form, props.record, props.nodes, props.levels,
    typeof window === 'undefined' ? '' : window.location.origin))
</script>

<template>
    <section class="catalog-seo-preview" aria-label="SEO-предпросмотр">
        <div class="catalog-seo-preview__heading">
            <v-icon size="17" aria-hidden="true">mdi-magnify</v-icon>
            <span>Предпросмотр в поиске</span>
        </div>
        <div class="catalog-seo-preview__snippet">
            <div v-if="preview.url" class="catalog-seo-preview__url" :title="preview.url">{{ preview.url }}</div>
            <div class="catalog-seo-preview__title">{{ preview.title }}</div>
            <p class="catalog-seo-preview__description" :class="{ 'catalog-seo-preview__description--empty': !preview.description }">{{ preview.description || 'Добавьте описание страницы, чтобы увидеть его в предпросмотре.' }}</p>
        </div>
        <p v-if="preview.urlHint" class="catalog-seo-preview__note">{{ preview.urlHint }}</p>
        <p class="catalog-seo-preview__note">Поисковая система может изменить заголовок и описание в выдаче.</p>
    </section>
</template>

<style scoped>
.catalog-seo-preview { min-width: 0; padding: 16px; border: 1px solid #dbe4ed; border-radius: 12px; background: #fff; }
.catalog-seo-preview__heading { display: flex; align-items: center; gap: 6px; margin-bottom: 14px; color: #526477; font-size: 12px; font-weight: 600; }
.catalog-seo-preview__snippet { min-width: 0; }
.catalog-seo-preview__url { overflow: hidden; margin-bottom: 5px; color: #3e5f47; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.catalog-seo-preview__title { display: -webkit-box; overflow: hidden; color: #1a0dab; font-size: 19px; line-height: 1.35; overflow-wrap: anywhere; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.catalog-seo-preview__description { display: -webkit-box; overflow: hidden; margin: 6px 0 0; color: #4d5156; font-size: 13px; line-height: 1.55; overflow-wrap: anywhere; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
.catalog-seo-preview__description--empty { color: #758191; }
.catalog-seo-preview__note { margin: 12px 0 0; color: #758191; font-size: 11px; line-height: 1.5; }
.catalog-seo-preview__note + .catalog-seo-preview__note { margin-top: 5px; }
</style>
