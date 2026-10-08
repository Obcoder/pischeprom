import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { after, before, test } from 'node:test'
import { createServer } from 'vite'
import vue from '@vitejs/plugin-vue'
import { createSSRApp, h } from 'vue'
import { renderToString } from '@vue/server-renderer'
import postcss from 'postcss'
import { currentClassSection, revealClassAnchor } from '../../resources/js/Components/Products/ClassPage/anchors.js'

let server, ClassLanding, resolveClassGuide
before(async () => {
    server = await createServer({ configFile: false, plugins: [vue()], server: { middlewareMode: true }, appType: 'custom', logLevel: 'error', optimizeDeps: { noDiscovery: true, include: [] } })
    ClassLanding = (await server.ssrLoadModule('/resources/js/Components/Products/ClassPage/ClassLanding.vue')).default
    resolveClassGuide = (await server.ssrLoadModule('/resources/js/Components/Products/ClassPage/guides.js')).resolveClassGuide
})
after(async () => { await server?.close() })

const good = (changes = {}) => ({
    id: 75, name: 'Актуальное название партии', url: '/g/current-live-url', image: '/storage/current-good.jpg', image_alt: 'Текущая партия',
    attributes: [{ label: 'Страна', value: 'Россия' }, { label: 'Упаковка', value: '15 кг' }],
    offer: { price: 120, currency_code: 'RUB', price_unit_label: 'кг', package_price: 1800, package_weight: 15, includes_vat: true },
    availability: { status: 'in_stock', is_in_stock: true, can_subscribe: false }, ...changes,
})
const page = (changes = {}) => ({ guide: 'mackerel', seo: { h1: 'Скумбрия' }, goods: [], inlineGoods: {}, breadcrumbs: [{ name: 'Главная', url: '/' }, { name: 'Скумбрия', url: '/p/124' }], ...changes })
const render = data => renderToString(createSSRApp({ render: () => h(ClassLanding, { page: data, guide: resolveClassGuide(data.guide) }) }))
const text = html => html.replace(/<!--[\s\S]*?-->/g, '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()

test('the full prototype article, diagrams, FAQs and image attribution are present during SSR', async () => {
    const html = await render(page())
    assert.match(html, /data-class-guide="mackerel"/)
    assert.match(html, /data-guide-article="mackerel"/)
    assert.match(html, /<h1 id="hero-title">Скумбрия<\/h1>/)
    const prototype = readFileSync('docs/prototypes/pischeprom-mackerel-prototype/dist/index.html', 'utf8')
    const article = prototype.slice(prototype.indexOf('<section id="guide"'), prototype.indexOf('<section id="contact"'))
    const articleParagraphs = [...article.matchAll(/<p(?:\s[^>]*)?>([\s\S]*?)<\/p>/g)].map(match => text(match[1]))
    for (const paragraph of articleParagraphs) assert.ok(text(html).includes(paragraph), `Missing prototype paragraph: ${paragraph}`)
    assert.equal((html.match(/<details(?:\s|>)/g) || []).length, 9, 'Eight FAQ entries plus the original source disclosure')
    for (const fact of ['95% — рыба', '5% — глазурь', '9,5', 'ПРИМЕР РАСЧЁТА, НЕ ХАРАКТЕРИСТИКА ТОВАРА', 'ГОСТ 35273-2025', 'Petar Milošević', 'Jocian', 'CC BY-SA 4.0', 'CC BY-SA 3.0']) assert.ok(html.includes(fact), fact)
    assert.ok(html.includes('/class-assets/mackerel/mackerel-hero.jpg'))
    assert.ok(html.includes('/class-assets/mackerel/mackerel-smoked.jpg'))
    assert.ok(!html.includes('mailto:office@180022.ru'), 'Prototype contacts must not be published as verified store settings')
    assert.ok(!html.includes('data-good-id='), 'Empty published goods never leave product links')
})

test('catalog cards use live images, facts, price units, package totals and availability', async () => {
    const liveGood = good()
    const html = await render(page({ goods: [liveGood], inlineGoods: { 75: liveGood } }))
    for (const value of ['Актуальное название партии', '/storage/current-good.jpg', 'Текущая партия', 'Россия', '120 ₽', '1 800 ₽', '15 кг', 'В наличии', 'С НДС']) assert.ok(html.includes(value), value)
    assert.match(html, /data-class-goods/)
    assert.match(html, /<article[^>]*data-good-id="75"/)
    assert.match(html, /href="\/g\/current-live-url"[^>]*data-good-id="75"/)
    assert.ok(!html.includes('scomber-scombrus-600-round-foroyar'), 'Prototype URLs are never catalog data')
})

test('renamed and unpublished goods update contextual links without changing article text', async () => {
    const liveGood = good({ url: '/g/renamed-canonical' })
    const linked = await render(page({ inlineGoods: { 75: liveGood } }))
    assert.equal((linked.match(/data-inline-good-link/g) || []).length, 2)
    assert.ok(linked.includes('href="/g/renamed-canonical"'))
    const hidden = await render(page())
    assert.ok(!hidden.includes('/g/renamed-canonical'))
    assert.ok(hidden.replace(/<!--[\s\S]*?-->/g, '').includes('<span>скумбрию 600+ с Фарерских островов</span>'))
    assert.ok(hidden.includes('Сейчас в этом разделе нет опубликованных товаров.'))
})

test('unknown images, prices and VAT have honest fallback states', async () => {
    const html = await render(page({ goods: [good({ image: null, offer: { price: null }, availability: { status: 'on_request' } }), good({ id: 104, offer: { price: 200, price_unit_label: 'упаковка', currency_code: 'RUB', includes_vat: false } })] }))
    assert.ok(html.includes('Фото уточняется'))
    assert.ok(html.includes('Цена по запросу'))
    assert.ok(html.includes('Наличие уточним'))
    assert.ok(html.includes('НДС уточняется при подтверждении'))
    assert.ok(html.includes(' / упаковка'))
    assert.ok(!html.includes('Без НДС'))
    assert.equal(resolveClassGuide('unconfigured'), null)
    assert.equal(resolveClassGuide('toString'), null)
})

test('anchor navigation reveals nested disclosures and safely ignores missing or invalid hashes', () => {
    const root = { contains: node => [root, outer, inner, target].includes(node), querySelectorAll: () => [outer, inner, target] }
    const outer = { id: 'sources', tagName: 'DETAILS', open: false, parentElement: root }
    const inner = { id: 'source-details', tagName: 'DETAILS', open: false, parentElement: outer }
    const target = { id: 'source-fao', tagName: 'LI', parentElement: inner }
    assert.equal(revealClassAnchor(root, '#source-fao'), target)
    assert.equal(outer.open, true)
    assert.equal(inner.open, true)
    inner.open = false
    assert.equal(revealClassAnchor(root, '#source-details'), inner)
    assert.equal(inner.open, true, 'A disclosure target itself opens too')
    for (const hash of ['#', '#missing', '#%invalid', '']) assert.equal(revealClassAnchor(root, hash), null)
    assert.equal(currentClassSection([{ id: 'catalog', getBoundingClientRect: () => ({ top: -150 }) }, { id: 'guide', getBoundingClientRect: () => ({ top: 110 }) }, { id: 'faq', getBoundingClientRect: () => ({ top: 800 }) }], 140), 'guide')
})

test('prototype CSS is confined to the class landing and does not load outside fonts or images', () => {
    const css = readFileSync('resources/js/Components/Products/ClassPage/classPage.css', 'utf8')
    postcss.parse(css).walkRules(rule => {
        for (const selector of rule.selectors) assert.ok(selector.startsWith('.class-landing'), selector)
    })
    assert.doesNotMatch(css, /@import|@font-face|url\(/)
})
