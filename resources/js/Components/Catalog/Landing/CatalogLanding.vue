<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import ClassGoods from '../../Products/ClassPage/ClassGoods.vue'
import { currentClassSection, revealClassAnchor } from '../../Products/ClassPage/anchors.js'
import LandingBlock from './LandingBlock.vue'
import LandingText from './LandingText.vue'
import { blockTypes } from './schema.js'
import { safeLandingUrl } from './links.js'
import '../../Products/ClassPage/classPage.css'
import './landing.css'

const props = defineProps({ page: { type: Object, required: true } })
const content = computed(() => props.page.content || {})
const hero = computed(() => content.value.hero || {})
const sources = computed(() => content.value.sources || {})
const contact = computed(() => content.value.contact || {})
const blocks = computed(() => (content.value.blocks || []).filter(block => block.enabled !== false && Object.hasOwn(blockTypes, block.type)))
const hasCatalog = computed(() => content.value.catalog?.enabled !== false)
const inlineGoods = computed(() => hasCatalog.value ? props.page.inlineGoods || {} : {})
const navigation = computed(() => [
    ...(hasCatalog.value ? [{ id: 'catalog', title: 'Каталог' }] : []),
    ...blocks.value.filter(block => block.navTitle).map(block => ({ id: block.id, title: block.navTitle })),
])
const firstBlockUrl = computed(() => blocks.value.length ? `#${blocks.value[0].id}` : '')
const availableAnchors = computed(() => new Set([
    ...blocks.value.map(block => `#${block.id}`),
    ...blocks.value.flatMap(block => block.type === 'faq' ? (block.data.items || []).filter(item => item.id).map(item => `#${item.id}`) : []),
    ...(hasCatalog.value ? ['#catalog'] : []),
    ...(contact.value.enabled ? ['#contact'] : []),
    ...(sources.value.enabled ? ['#sources', '#photo-credits', ...(sources.value.items || []).map(item => `#${item.id}`)] : []),
]))
const actionUrl = value => {
    const url = safeLandingUrl(value)
    return url?.startsWith('#') && !availableAnchors.value.has(url) ? null : url
}
const root = ref(null)
const activeSection = ref(navigation.value[0]?.id || '')
const headerOffset = ref(0)
const footerVisible = ref(false)
let cleanup = () => {}

function handleAnchor(event) {
    const link = event.target?.closest?.('a[href^="#"]')
    if (link && root.value?.contains(link)) revealClassAnchor(root.value, link.getAttribute('href'))
}

onMounted(() => {
    const header = document.querySelector('.app-header')
    const footer = root.value.closest('.layout-shell')?.querySelector('.layout-footer')
    const sections = navigation.value.map(item => root.value.querySelector(`#${item.id}`)).filter(Boolean)
    let frame = null
    let initialFrame = null
    const update = () => { frame = null; activeSection.value = currentClassSection(sections, headerOffset.value + 140) }
    const schedule = () => { if (frame === null) frame = window.requestAnimationFrame(update) }
    const measureHeader = () => { headerOffset.value = header?.getBoundingClientRect().height || 0; schedule() }
    const revealHash = () => {
        const target = revealClassAnchor(root.value, window.location.hash)
        if (target) target.scrollIntoView({ block: 'start' })
        schedule()
    }
    const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(measureHeader)
    const footerObserver = typeof IntersectionObserver === 'undefined' ? null : new IntersectionObserver(entries => {
        footerVisible.value = entries.some(entry => entry.isIntersecting)
    })
    if (header) observer?.observe(header)
    if (footer) footerObserver?.observe(footer)
    measureHeader()
    window.addEventListener('scroll', schedule, { passive: true })
    window.addEventListener('resize', measureHeader, { passive: true })
    window.addEventListener('hashchange', revealHash)
    initialFrame = window.requestAnimationFrame(revealHash)
    cleanup = () => {
        observer?.disconnect(); footerObserver?.disconnect()
        window.removeEventListener('scroll', schedule)
        window.removeEventListener('resize', measureHeader)
        window.removeEventListener('hashchange', revealHash)
        if (frame !== null) window.cancelAnimationFrame(frame)
        if (initialFrame !== null) window.cancelAnimationFrame(initialFrame)
    }
})
onUnmounted(() => cleanup())
</script>

<template>
    <div ref="root" class="class-landing catalog-landing" :data-class-guide="page.guide || content.template" :style="{ '--class-header-offset': `${headerOffset}px` }" @click="handleAnchor">
        <a class="skip-link" href="#class-main">Перейти к содержимому</a>
        <div v-if="page.preview" class="wrap landing-preview" role="status">Предпросмотр черновика. Изменения появятся на сайте после публикации.</div>
        <div id="class-main">
            <nav class="wrap breadcrumbs" aria-label="Хлебные крошки"><template v-for="(item, index) in page.breadcrumbs" :key="index"><span v-if="index" aria-hidden="true">/</span><a v-if="item.url && index < page.breadcrumbs.length - 1" :href="item.url">{{ item.name }}</a><span v-else :aria-current="index === page.breadcrumbs.length - 1 ? 'page' : undefined">{{ item.name }}</span></template></nav>
            <section class="hero wrap" :class="{ 'landing-no-image': !safeLandingUrl(hero.image) }" aria-labelledby="hero-title">
                <div class="hero-copy">
                    <div v-if="hero.eyebrow" class="eyebrow light"><span class="small-line" /> {{ hero.eyebrow }}</div>
                    <h1 id="hero-title">{{ page.seo?.h1?.trim() || hero.title?.trim() }}</h1>
                    <p v-if="hero.subtitle" class="hero-serif landing-lines">{{ hero.subtitle }}</p>
                    <p v-if="hero.description" class="hero-description landing-lines"><LandingText :text="hero.description" :goods="inlineGoods" :anchors="availableAnchors" /></p>
                    <div class="hero-actions"><a v-if="hero.action && actionUrl(hero.actionUrl)" class="button lime" :href="actionUrl(hero.actionUrl)">{{ hero.action }}</a><a v-if="hero.secondaryAction && actionUrl(hero.secondaryActionUrl)" class="hero-text-link" :href="actionUrl(hero.secondaryActionUrl)">{{ hero.secondaryAction }}</a></div>
                    <div v-if="hero.audiences?.length" class="hero-audiences"><template v-for="(audience, index) in hero.audiences" :key="index"><span v-if="index">·</span>{{ audience }}</template></div>
                </div>
                <figure v-if="safeLandingUrl(hero.image)" class="hero-visual"><img :src="safeLandingUrl(hero.image)" :alt="hero.imageAlt || ''" width="1500" height="1051" fetchpriority="high"><div v-if="hero.photoIndex" class="photo-index" aria-hidden="true">{{ hero.photoIndex }}</div><figcaption v-if="hero.caption || hero.scientificName"><span>{{ hero.caption }}</span><i>{{ hero.scientificName }}</i></figcaption></figure>
            </section>
            <div v-if="navigation.length" class="section-nav"><nav class="wrap" aria-label="Разделы страницы"><a v-for="(item, index) in navigation" :key="item.id" :href="`#${item.id}`" :class="{ active: activeSection === item.id }" :aria-current="activeSection === item.id ? 'location' : undefined"><span>{{ String(index + 1).padStart(2, '0') }}</span> {{ item.title }}</a></nav></div>
            <ClassGoods v-if="hasCatalog" :title="content.catalog?.title || 'Каталог'" :goods="page.goods" :copy="content.catalog" :help-url="firstBlockUrl" />
            <nav v-if="hasCatalog && page.children?.length" class="wrap landing-branches" aria-label="Разделы каталога"><a v-for="child in page.children" :key="child.id" :href="child.public_url || child.url"><img v-if="child.image" :src="child.image" :alt="child.name" loading="lazy"><span>{{ child.name }}</span></a></nav>
            <div :data-guide-article="page.guide || content.template"><LandingBlock v-for="block in blocks" :key="block.id" :block="block" :goods="inlineGoods" :anchors="availableAnchors" /></div>
            <section v-if="contact.enabled" id="contact" class="contact-section wrap"><div><div v-if="contact.eyebrow" class="eyebrow light">{{ contact.eyebrow }}</div><h2 class="landing-lines">{{ contact.title }}</h2><p class="landing-lines"><LandingText :text="contact.description" :goods="inlineGoods" :anchors="availableAnchors" /></p></div><div class="contact-actions"><a v-if="contact.action && actionUrl(contact.actionUrl)" class="button lime" :href="actionUrl(contact.actionUrl)">{{ contact.action }}</a><p v-if="contact.note" class="landing-lines">{{ contact.note }}</p></div></section>
            <section v-if="sources.enabled" id="sources" class="wrap sources-section"><details><summary>{{ sources.title || 'Источники и фотографии' }}<span v-if="sources.verifiedOn">Проверено {{ sources.verifiedOn }}</span><b aria-hidden="true">+</b></summary><div class="sources-body">
                <p v-if="sources.description"><LandingText :text="sources.description" :goods="inlineGoods" :anchors="availableAnchors" /></p>
                <ol v-if="sources.items?.length" class="source-list"><li v-for="(source, index) in sources.items" :id="source.id || undefined" :key="index"><a v-if="safeLandingUrl(source.url)" :href="safeLandingUrl(source.url)" target="_blank" rel="noopener noreferrer">{{ source.title }}</a><span v-else>{{ source.title }}</span> — {{ source.description }}</li></ol>
                <div v-if="sources.photos?.length" id="photo-credits" class="photo-credits"><h3>{{ sources.photosTitle || 'Фотографии' }}</h3><p v-if="sources.photoNote">{{ sources.photoNote }}</p><p v-for="(photo, index) in sources.photos" :key="index">{{ photo.title }} — <a v-if="safeLandingUrl(photo.source)" :href="safeLandingUrl(photo.source)" target="_blank" rel="noopener noreferrer">{{ photo.author }}</a><span v-else>{{ photo.author }}</span>, <a v-if="safeLandingUrl(photo.licenseUrl)" :href="safeLandingUrl(photo.licenseUrl)" target="_blank" rel="noopener noreferrer">{{ photo.license }}</a><span v-else>{{ photo.license }}</span>.</p><p v-if="sources.closingNote">{{ sources.closingNote }}</p></div>
            </div></details></section>
        </div>
        <div v-if="hasCatalog || firstBlockUrl" v-show="!footerVisible" class="mobile-bar"><a v-if="hasCatalog" href="#catalog">{{ content.catalog?.action || content.catalog?.title || 'Каталог' }}</a><a v-if="firstBlockUrl" :href="firstBlockUrl">{{ hero.secondaryAction || 'Подробнее' }}</a></div>
    </div>
</template>
