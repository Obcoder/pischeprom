<script setup>
import { computed } from 'vue'
import LandingText from './LandingText.vue'
import { safeLandingUrl } from './links.js'

const props = defineProps({ block: { type: Object, required: true }, goods: { type: Object, default: () => ({}) }, anchors: { type: Object, default: null } })
const data = computed(() => props.block.data || {})
const percent = value => Math.max(0, Math.min(100, Number(value) || 0))
const linkUrl = value => {
    const url = safeLandingUrl(value)
    return url?.startsWith('#') && props.anchors && !props.anchors.has(url) ? null : url
}
const sectionClasses = computed(() => ({
    'section guide-section': props.block.type === 'cards',
    'section landing-caliber': props.block.type === 'caliber',
    'section mass-section': props.block.type === 'glaze',
    'section faq-section': props.block.type === 'faq',
    'section wrap': !['cards', 'caliber', 'glaze', 'faq'].includes(props.block.type),
}))
</script>

<template>
    <section :id="block.id" :class="sectionClasses" :data-landing-block="block.type">
        <div :class="{ wrap: ['cards', 'caliber', 'glaze', 'faq'].includes(block.type), 'mass-grid': block.type === 'glaze', 'faq-layout': block.type === 'faq' }">
            <div v-if="!['caliber', 'glaze', 'faq'].includes(block.type)" class="section-heading">
                <div><div v-if="data.eyebrow" class="eyebrow">{{ data.eyebrow }}</div><h2 class="landing-lines">{{ block.title }}</h2></div>
                <p v-if="data.description" class="landing-lines"><LandingText :text="data.description" :goods="goods" :anchors="anchors" /></p>
                <a v-if="data.linkText && linkUrl(data.linkUrl)" class="text-link" :href="linkUrl(data.linkUrl)">{{ data.linkText }}</a>
            </div>

            <div v-if="block.type === 'text'" class="landing-prose"><p v-for="(paragraph, index) in data.paragraphs" :key="index" class="landing-lines"><LandingText :text="paragraph" :goods="goods" :anchors="anchors" /></p></div>

            <div v-else-if="block.type === 'cards'" class="guide-grid">
                <article v-for="(item, index) in data.items" :key="index" class="guide-item">
                    <span class="guide-number">{{ String(index + 1).padStart(2, '0') }}</span><h3>{{ item.title }}</h3>
                    <p class="landing-lines"><LandingText :text="item.text" :goods="goods" :anchors="anchors" /></p>
                    <span v-if="item.note" class="micro-label"><LandingText :text="item.note" :goods="goods" :anchors="anchors" /></span>
                    <a v-if="item.linkText && linkUrl(item.linkUrl)" class="inline-link" :href="linkUrl(item.linkUrl)">{{ item.linkText }}</a>
                </article>
            </div>

            <template v-else-if="block.type === 'caliber'">
                <div class="caliber-panel">
                    <div class="caliber-intro"><div class="eyebrow">{{ data.eyebrow }}</div><h2 class="landing-lines">{{ block.title }}</h2><p><LandingText :text="data.description" :goods="goods" :anchors="anchors" /></p></div>
                    <div class="caliber-chart" :aria-label="data.chartLabel">
                        <div class="chart-axis"><span /><div><span v-for="(label, index) in data.axis" :key="index">{{ label }}</span></div></div>
                        <div v-for="(range, index) in data.ranges" :key="index" class="chart-row"><b>{{ range.label }}</b><div class="chart-track"><span class="range-bar" :class="range.open ? 'landing-range-open' : 'finite'" :style="{ left: `${percent(range.start)}%`, width: `${Math.min(percent(range.width), 100 - percent(range.start))}%` }"><span v-if="range.open">+</span></span></div></div>
                        <p class="chart-note landing-lines"><LandingText :text="data.note" :goods="goods" :anchors="anchors" /></p>
                    </div>
                </div>
                <p v-if="data.afterword" class="editorial-line"><LandingText :text="data.afterword" :goods="goods" :anchors="anchors" /></p>
            </template>

            <div v-else-if="block.type === 'uses'" class="uses-layout" :class="{ 'landing-no-image': !safeLandingUrl(data.image) }">
                <figure v-if="safeLandingUrl(data.image)" class="uses-photo"><img :src="safeLandingUrl(data.image)" :alt="data.imageAlt || ''" width="1100" height="825" loading="lazy"><figcaption class="landing-lines">{{ data.caption }}<span v-if="data.photoNote">{{ data.photoNote }}</span></figcaption></figure>
                <div class="uses-content"><div v-for="(item, index) in data.items" :key="index" class="use-row"><span>{{ String(index + 1).padStart(2, '0') }}</span><div><h3>{{ item.title }}</h3><p class="landing-lines"><LandingText :text="item.text" :goods="goods" :anchors="anchors" /></p><p v-if="item.note"><LandingText :text="item.note" :goods="goods" :anchors="anchors" /></p><a v-if="item.linkText && linkUrl(item.linkUrl)" class="inline-link" :href="linkUrl(item.linkUrl)">{{ item.linkText }}</a></div></div></div>
            </div>

            <template v-else-if="block.type === 'glaze'">
                <div class="mass-copy"><div class="eyebrow light">{{ data.eyebrow }}</div><h2 class="landing-lines">{{ block.title }}</h2><p v-for="(paragraph, index) in data.paragraphs" :key="index"><LandingText :text="paragraph" :goods="goods" :anchors="anchors" /></p><div v-if="data.reminder" class="mass-reminder"><span aria-hidden="true">≠</span><span class="landing-lines">{{ data.reminder }}</span></div></div>
                <div class="mass-visual"><div class="diagram-caption">{{ data.diagramCaption }}</div><div class="mass-equation"><template v-for="(item, index) in data.equation" :key="index"><b v-if="item.operator" class="math-sign">{{ item.operator }}</b><div :class="{ 'mass-result': index === data.equation.length - 1 }"><strong>{{ item.value }}<small>{{ item.unit }}</small></strong><span>{{ item.label }}</span></div></template></div><div class="mass-bar" role="img" :aria-label="data.barLabel"><span :style="{ width: `${percent(data.primaryPercent)}%` }" /><i :style="{ width: `${100 - percent(data.primaryPercent)}%` }" /></div><div class="mass-legend"><span><i /> {{ data.primaryLegend }}</span><span><i /> {{ data.secondaryLegend }}</span></div><div v-if="data.yieldTitle || data.yieldText" class="yield-note"><h3>{{ data.yieldTitle }}</h3><p><LandingText :text="data.yieldText" :goods="goods" :anchors="anchors" /></p></div></div>
            </template>

            <template v-else-if="block.type === 'quality'">
                <div class="quality-layout"><div class="quality-table"><div class="quality-table-head"><span v-for="(header, index) in data.headers" :key="index">{{ header }}</span></div><div v-for="(row, index) in data.rows" :key="index"><b>{{ row.title }}</b><p><LandingText :text="row.text" :goods="goods" :anchors="anchors" /></p></div></div>
                    <aside v-if="data.aside" class="cold-card"><span class="eyebrow">{{ data.aside.eyebrow }}</span><strong>{{ data.aside.value }}<span>{{ data.aside.unit }}</span></strong><h3>{{ data.aside.title }}</h3><p><LandingText :text="data.aside.text" :goods="goods" :anchors="anchors" /></p><div class="cold-divider" /><b>{{ data.aside.secondaryTitle }}</b><p><LandingText :text="data.aside.secondaryText" :goods="goods" :anchors="anchors" /></p></aside>
                </div>
                <div v-if="data.note?.title || data.note?.text" class="safety-note"><span class="note-symbol" aria-hidden="true">!</span><div><h3>{{ data.note.title }}</h3><p><LandingText :text="data.note.text" :goods="goods" :anchors="anchors" /></p></div></div>
            </template>

            <template v-else-if="block.type === 'faq'">
                <div class="faq-intro"><div class="eyebrow">{{ data.eyebrow }}</div><h2 class="landing-lines">{{ block.title }}</h2><p><LandingText :text="data.description" :goods="goods" :anchors="anchors" /></p><a v-if="data.action && linkUrl(data.actionUrl)" class="button outlined" :href="linkUrl(data.actionUrl)">{{ data.action }}</a></div>
                <div class="accordion"><details v-for="(item, index) in data.items" :id="item.id || undefined" :key="index" :open="item.open"><summary>{{ item.question }}<span aria-hidden="true">+</span></summary><div class="details-body">
                    <dl v-if="item.definitions?.length" class="definitions"><div v-for="(definition, definitionIndex) in item.definitions" :key="definitionIndex"><dt>{{ definition.term }}</dt><dd><LandingText :text="definition.description" :goods="goods" :anchors="anchors" /></dd></div></dl>
                    <p v-for="(paragraph, paragraphIndex) in item.paragraphs" :key="paragraphIndex"><LandingText :text="paragraph" :goods="goods" :anchors="anchors" /></p>
                    <ul v-if="item.bullets?.length" class="passport-list"><li v-for="(bullet, bulletIndex) in item.bullets" :key="bulletIndex"><LandingText :text="bullet" :goods="goods" :anchors="anchors" /></li></ul>
                    <p v-if="item.afterword" class="landing-lines"><LandingText :text="item.afterword" :goods="goods" :anchors="anchors" /></p>
                    <a v-if="item.linkText && linkUrl(item.linkUrl)" class="inline-link" :href="linkUrl(item.linkUrl)">{{ item.linkText }}</a>
                </div></details></div>
            </template>

            <div v-else-if="block.type === 'table'" class="landing-table-scroll"><table class="landing-table"><thead><tr><th v-for="(header, index) in data.headers" :key="index" scope="col">{{ header }}</th></tr></thead><tbody><tr v-for="(row, rowIndex) in data.rows" :key="rowIndex"><td v-for="(cell, index) in row.cells" :key="index"><LandingText :text="cell" :goods="goods" :anchors="anchors" /></td></tr></tbody></table><p v-if="data.note"><LandingText :text="data.note" :goods="goods" :anchors="anchors" /></p></div>
            <figure v-else-if="block.type === 'image' && safeLandingUrl(data.image)" class="landing-image"><img :src="safeLandingUrl(data.image)" :alt="data.imageAlt || ''" loading="lazy"><figcaption v-if="data.caption" class="landing-lines">{{ data.caption }}</figcaption></figure>
        </div>
    </section>
</template>
