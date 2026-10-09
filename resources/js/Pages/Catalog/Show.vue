<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed, h } from 'vue'
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import PublicCatalogCards from '@/Components/Catalog/PublicCatalogCards.vue'
import ClassLanding from '@/Components/Products/ClassPage/ClassLanding.vue'
import { resolveClassGuide } from '@/Components/Products/ClassPage/guides.js'

defineOptions({ layout: LayoutDefault })
const props = defineProps({
    node: { type: Object, required: true },
    breadcrumbs: { type: Array, default: () => [] },
    children: { type: Array, default: () => [] },
    properties: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    classPage: { type: Object, default: null },
})

const guide = computed(() => resolveClassGuide(props.classPage?.guide))
const pageSeo = computed(() => ({ ...props.seo, ...(guide.value ? props.classPage.seo : {}) }))
const JsonLdHead = () => h('script', { 'head-key': 'catalog-structured-data', type: 'application/ld+json' }, JSON.stringify(pageSeo.value.jsonLd).replace(/</g, '\\u003c'))

function displayValue(property) {
    if (property.type === 'boolean') return property.value ? 'Да' : 'Нет'
    if (Array.isArray(property.value)) return property.value.join(', ')
    return String(property.value)
}
</script>

<template>
    <Head :title="pageSeo.title">
        <meta head-key="description" name="description" :content="pageSeo.description">
        <meta v-if="pageSeo.robots" head-key="robots" name="robots" :content="pageSeo.robots">
        <link v-if="pageSeo.canonical" head-key="canonical" rel="canonical" :href="pageSeo.canonical">
        <meta head-key="og:title" property="og:title" :content="pageSeo.title">
        <meta head-key="og:description" property="og:description" :content="pageSeo.description">
        <meta v-if="pageSeo.canonical" head-key="og:url" property="og:url" :content="pageSeo.canonical">
        <meta v-if="pageSeo.image" head-key="og:image" property="og:image" :content="pageSeo.image">
        <meta head-key="og:type" property="og:type" content="website">
        <JsonLdHead v-if="pageSeo.jsonLd" />
    </Head>

    <ClassLanding v-if="guide" :key="`${node.id}-${classPage.guide}`" :page="classPage" :guide="guide" />
    <v-container v-else class="catalog-public py-7 py-md-10">
        <nav aria-label="Навигационная цепочка" class="catalog-public__breadcrumbs">
            <Link href="/">Главная</Link>
            <template v-for="ancestor in breadcrumbs" :key="ancestor.id">
                <span aria-hidden="true">/</span>
                <Link :href="ancestor.public_url">{{ ancestor.name }}</Link>
            </template>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ node.name }}</span>
        </nav>

        <header class="catalog-public__header">
            <div>
                <div class="catalog-public__kicker">{{ node.level_name || 'Каталог' }}</div>
                <h1>{{ node.name }}</h1>
                <p v-if="node.description" class="catalog-public__description">{{ node.description }}</p>
                <Link v-if="node.offer_url" :href="node.offer_url" class="catalog-public__offer">
                    Перейти к товару <v-icon icon="mdi-arrow-right" size="18" />
                </Link>
            </div>
            <img v-if="node.image" class="catalog-public__avatar" :src="node.image" :alt="node.name">
        </header>

        <section v-if="properties.length" aria-labelledby="catalog-properties-title" class="catalog-public__properties">
            <h2 id="catalog-properties-title">Характеристики</h2>
            <dl>
                <div v-for="property in properties" :key="property.label">
                    <dt>{{ property.label }}</dt>
                    <dd>{{ displayValue(property) }}</dd>
                </div>
            </dl>
        </section>

        <section v-if="children.length" aria-labelledby="catalog-children-title">
            <h2 id="catalog-children-title" class="mb-5">В этом разделе</h2>
            <PublicCatalogCards :items="children" />
        </section>
        <p v-else-if="!node.offer_url" class="catalog-public__empty">Ассортимент этого раздела пополняется. Свяжитесь с нами, чтобы уточнить наличие.</p>
    </v-container>
</template>

<style scoped>
.catalog-public { max-width: 1280px; color: #173e32; }
.catalog-public__breadcrumbs { display: flex; flex-wrap: wrap; gap: 9px; align-items: center; font-size: 13px; color: #6f7e77; margin-bottom: 35px; }
.catalog-public__breadcrumbs a { color: #39715a; text-decoration: none; }
.catalog-public__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 30px; padding-bottom: 34px; }
.catalog-public__header h1 { margin: 8px 0 18px; font-size: clamp(28px, 4vw, 46px); font-weight: 650; line-height: 1.15; overflow-wrap: anywhere; }
.catalog-public__kicker { color: #698875; font-size: 12px; text-transform: uppercase; letter-spacing: .12em; }
.catalog-public__description { max-width: 780px; color: #5c6c63; line-height: 1.75; white-space: pre-line; overflow-wrap: anywhere; }
.catalog-public__offer { display: inline-flex; align-items: center; gap: 12px; margin-top: 20px; padding: 12px 20px; border-radius: 12px; background: #176849; color: white; text-decoration: none; font-weight: 600; }
.catalog-public__offer:focus-visible { outline: 3px solid #168865; outline-offset: 3px; }
.catalog-public__avatar { width: 200px; height: 180px; border-radius: 22px; object-fit: cover; }
.catalog-public h2 { font-size: 23px; font-weight: 600; }
.catalog-public__properties { margin-bottom: 36px; padding: 25px; border-radius: 20px; background: #f4f7f5; }
.catalog-public__properties dl { margin-top: 15px; }
.catalog-public__properties dl > div { display: grid; grid-template-columns: minmax(140px, 1fr) 2fr; gap: 20px; padding: 12px 0; border-bottom: 1px solid #dfe8e2; }
.catalog-public__properties dt { color: #6d7d73; }
.catalog-public__properties dd { white-space: pre-line; overflow-wrap: anywhere; }
.catalog-public__empty { padding: 25px 0; color: #6d7d73; }
@media (max-width: 600px) {
    .catalog-public__header { flex-direction: column-reverse; }
    .catalog-public__avatar { width: 100%; height: 210px; }
    .catalog-public__properties dl > div { grid-template-columns: 1fr; gap: 5px; }
}
</style>
