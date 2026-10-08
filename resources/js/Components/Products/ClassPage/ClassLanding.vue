<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import ClassHero from './ClassHero.vue'
import ClassGoods from './ClassGoods.vue'
import ClassGuide from './ClassGuide.vue'
import ClassSources from './ClassSources.vue'
import { currentClassSection, revealClassAnchor } from './anchors.js'
import './classPage.css'

const props = defineProps({ page: { type: Object, required: true }, guide: { type: Object, required: true } })
const root = ref(null)
const activeSection = ref('catalog')
const headerOffset = ref(0)
const footerVisible = ref(false)
let cleanup = () => {}

function handleAnchor(event) {
    const link = event.target?.closest?.('a[href^="#"]')
    if (!link || !root.value?.contains(link)) return
    // Native link activation also emits click for Enter, before the browser navigates.
    revealClassAnchor(root.value, link.getAttribute('href'))
}

onMounted(() => {
    const header = document.querySelector('.app-header')
    const footer = root.value.closest('.layout-shell')?.querySelector('.layout-footer')
    const sections = props.guide.navigation.map(item => root.value.querySelector(`#${item.id}`)).filter(Boolean)
    let frame = null
    let initialFrame = null
    const update = () => {
        frame = null
        activeSection.value = currentClassSection(sections, headerOffset.value + 140)
    }
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
        observer?.disconnect()
        footerObserver?.disconnect()
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
    <div ref="root" class="class-landing" :data-class-guide="page.guide" :style="{ '--class-header-offset': `${headerOffset}px` }" @click="handleAnchor">
        <a class="skip-link" href="#class-main">Перейти к содержимому</a>
        <div id="class-main">
            <nav class="wrap breadcrumbs" aria-label="Хлебные крошки">
                <template v-for="(item, index) in page.breadcrumbs" :key="`${item.name}-${index}`">
                    <span v-if="index" aria-hidden="true">/</span>
                    <a v-if="item.url && index < page.breadcrumbs.length - 1" :href="item.url">{{ item.name }}</a>
                    <span v-else :aria-current="index === page.breadcrumbs.length - 1 ? 'page' : undefined">{{ item.name }}</span>
                </template>
            </nav>
            <ClassHero :title="page.seo.h1" :hero="guide.hero" />
            <div class="section-nav"><nav class="wrap" aria-label="Разделы страницы">
                <a v-for="(item, index) in guide.navigation" :key="item.id" :href="`#${item.id}`" :class="{ active: activeSection === item.id }" :aria-current="activeSection === item.id ? 'location' : undefined"><span>{{ String(index + 1).padStart(2, '0') }}</span> {{ item.title }}</a>
            </nav></div>
            <ClassGoods :title="guide.catalogTitle" :goods="page.goods" />
            <ClassGuide :guide="guide" :guide-key="page.guide" :inline-goods="page.inlineGoods" />
            <section id="contact" class="contact-section wrap">
                <div><div class="eyebrow light">ОТ ВЫБОРА К ПОСТАВКЕ</div><h2><template v-for="(line, index) in guide.contactTitle" :key="line"><br v-if="index">{{ line }}</template></h2><p>Объём, калибр, задача и город доставки.<br>Эти детали помогут обсудить конкретную поставку.</p></div>
                <div class="contact-actions"><a class="button lime" href="#catalog">Выбрать товар и обсудить поставку</a><p>Запрос и условия заказа —<br>в карточке выбранного товара.</p></div>
            </section>
            <ClassSources :guide="guide" />
        </div>
        <div v-show="!footerVisible" class="mobile-bar"><a href="#catalog">{{ guide.catalogAction }}</a><a href="#guide">Читать гид</a></div>
    </div>
</template>
