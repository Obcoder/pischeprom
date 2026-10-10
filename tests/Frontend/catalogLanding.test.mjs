import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { after, before, test } from 'node:test'
import { createServer } from 'vite'
import vue from '@vitejs/plugin-vue'
import { createSSRApp, h } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { landingTextParts, safeLandingUrl } from '../../resources/js/Components/Catalog/Landing/links.js'

let server, CatalogLanding
before(async () => {
    server = await createServer({ configFile: false, plugins: [vue()], server: { middlewareMode: true, hmr: false }, appType: 'custom', logLevel: 'error', optimizeDeps: { noDiscovery: true, include: [] } })
    CatalogLanding = (await server.ssrLoadModule('/resources/js/Components/Catalog/Landing/CatalogLanding.vue')).default
})
after(async () => { await server?.close() })
const mackerel = () => JSON.parse(readFileSync('resources/landings/mackerel.json', 'utf8'))
const page = (changes = {}) => ({ guide: 'editorial', content: mackerel(), seo: { h1: 'Скумбрия' }, goods: [], inlineGoods: {}, breadcrumbs: [{ name: 'Главная', url: '/' }, { name: 'Скумбрия', url: '/c/fish/mackerel' }], ...changes })
const render = data => renderToString(createSSRApp({ render: () => h(CatalogLanding, { page: data }) }))
const text = html => html.replace(/<!--[^]*?-->/g, '').replace(/<br\s*\/?\s*>/gi, ' ').replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/\s+/g, ' ').trim()

test('the migrated content renders all original article paragraphs, diagrams, FAQ and photographic credits during SSR', async () => {
    const html = await render(page())
    assert.match(html, /data-class-guide="editorial"/)
    assert.match(html, /data-guide-article="editorial"/)
    assert.match(html, /<h1 id="hero-title">Скумбрия<\/h1>/)
    const prototype = readFileSync('docs/prototypes/pischeprom-mackerel-prototype/dist/index.html', 'utf8')
    const article = prototype.slice(prototype.indexOf('<section id="guide"'), prototype.indexOf('<section id="contact"'))
    for (const match of article.matchAll(/<p(?:\s[^>]*)?>([^]*?)<\/p>/g)) {
        const paragraph = text(match[1])
        assert.ok(text(html).includes(paragraph), `Lost original paragraph: ${paragraph}`)
    }
    assert.equal((html.match(/<details(?:\s|>)/g) || []).length, 9)
    for (const expected of ['95% — рыба', '5% — глазурь', '9,5', 'Сравнение калибров', 'ГОСТ 35273-2025', 'Petar Milošević', 'Jocian', 'CC BY-SA 4.0', 'CC BY-SA 3.0', '/class-assets/mackerel/mackerel-hero.jpg', '/class-assets/mackerel/mackerel-smoked.jpg']) assert.ok(html.includes(expected), expected)
    assert.doesNotMatch(html, /\[\[|data-inline-good-link/)
})

test('live inline goods use the current canonical URL and unpublished goods retain readable text', async () => {
    const linked = await render(page({ inlineGoods: { 75: { url: '/g/live-75' }, 104: { url: '/g/live-104' } } }))
    assert.equal((linked.match(/data-inline-good-link/g) || []).length, 3)
    assert.equal((linked.match(/href="\/g\/live-75"/g) || []).length, 2)
    assert.match(linked, /href="\/g\/live-104"/)
    assert.match(linked, /href="#source-reg"[^>]*>\[1\]<\/a>/)
    const hidden = await render(page())
    assert.doesNotMatch(hidden, /\/g\/live-/)
    assert.ok(text(hidden).includes('скумбрию 600+ с Фарерских островов'))
})

test('block order and visibility control both the server article and section navigation', async () => {
    const content = mackerel()
    content.blocks = [content.blocks.find(b => b.id === 'faq'), content.blocks.find(b => b.id === 'uses'), { ...content.blocks.find(b => b.id === 'guide'), enabled: false }]
    const html = await render(page({ content }))
    assert.ok(html.indexOf('<section id="faq"') < html.indexOf('<section id="uses"'))
    assert.ok(html.indexOf('href="#faq"') < html.indexOf('href="#uses"'))
    assert.doesNotMatch(html, /<section id="guide"|>Как выбрать<|Размер — ещё не всё/)
    assert.doesNotMatch(html, /class="hero-text-link"/)
})

test('a landing at any catalog level renders generic blocks and can hide the entire catalog and contact', async () => {
    const content = {
        template: 'overview', hero: { title: 'Ингредиенты', subtitle: 'Для вашего производства' },
        catalog: { enabled: false }, contact: { enabled: false }, sources: { enabled: false },
        blocks: [
            { id: 'about', type: 'text', title: 'О разделе', navTitle: 'О разделе', enabled: true, data: { paragraphs: ['Полезное описание [[good:75|товара]].'] } },
            { id: 'comparison', type: 'table', title: 'Сравнение', navTitle: 'Сравнение', enabled: true, data: { headers: ['Вид', 'Применение'], rows: [{ cells: ['Мука', 'Выпечка'] }] } },
            { id: 'photo', type: 'image', title: 'Склад', enabled: true, data: { image: '/storage/warehouse.jpg', imageAlt: 'Склад', caption: 'Хранение сырья' } },
        ],
    }
    const html = await render(page({ guide: 'overview', content, inlineGoods: { 75: { url: '/g/75' } } }))
    for (const expected of ['Ингредиенты', 'Полезное описание товара.', 'Сравнение', 'Выпечка', '/storage/warehouse.jpg', 'Хранение сырья']) assert.ok(text(html).includes(expected) || html.includes(expected), expected)
    assert.doesNotMatch(html, /data-class-goods|id="catalog"|id="contact"|data-inline-good-link|Скумбрия<\/h1>/)
})

test('saved text is escaped and invalid image, action and inline good URLs never become active markup', async () => {
    const content = mackerel()
    content.hero.title = '<script>alert(1)</script>'
    content.hero.image = 'javascript:alert(1)'
    content.hero.actionUrl = '//evil.example'
    content.blocks = [{ id: 'safe', type: 'text', title: 'Текст', enabled: true, data: { paragraphs: ['<img src=x onerror=alert(1)> [[good:75|<b>Товар</b>]] [[#safe|Источник]]'] } }]
    const html = await render(page({ content, inlineGoods: { 75: { url: 'javascript:alert(1)' } } }))
    assert.ok(html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'))
    assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'))
    assert.ok(html.includes('&lt;b&gt;Товар&lt;/b&gt;'))
    assert.doesNotMatch(html, /javascript:|href="\/\/evil|<script|<img src=x|data-inline-good-link/)
    assert.match(html, /href="#safe"[^>]*>Источник<\/a>/)
})

test('safe token parsing preserves brackets and rejects ambiguous or dangerous URL schemes', () => {
    assert.deepEqual(landingTextParts('См. [[#source-reg|[1]]] и [[good:75|рыбу]].', { 75: { url: '/g/current' } }), [
        { text: 'См. ' }, { text: '[1]', href: '#source-reg', goodId: null }, { text: ' и ' }, { text: 'рыбу', href: '/g/current', goodId: '75' }, { text: '.' },
    ])
    for (const value of ['javascript:alert(1)', 'data:image/svg+xml,evil', '//evil.example', '/\\evil.example', 'https:\\evil.example', '/path\nwith-control']) assert.equal(safeLandingUrl(value), null)
    for (const value of ['/storage/photo.jpg', '#source-reg', 'https://example.org/page']) assert.equal(safeLandingUrl(value), value)
})
