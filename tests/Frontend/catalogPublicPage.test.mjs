import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, filename, initial) {
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'PublicCatalogPage' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'PublicCatalogPage', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const guide = { article: {}, catalogTitle: 'Скумбрия в каталоге' }
    const environment = { ...Vue, Head: 'Head', Link: 'Link', LayoutDefault: {}, PublicCatalogCards: 'PublicCatalogCards', ClassLanding: 'ClassLanding',
        GoodStockAlertButton: 'GoodStockAlertButton', canSubscribeToGoodStock: () => false,
        route: (name, params) => `/g/${params.good}`, resolveClassGuide: key => key === 'mackerel' ? guide : null }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive(initial)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { props, api, render: templateRenderer(template, api, props) }
}

const catalogProps = (changes = {}) => ({
    node: { id: 160, name: 'Скумбрия', level_name: 'Класс' },
    breadcrumbs: [{ id: 25, name: 'Рыба', public_url: '/catalog/ryba' }],
    children: [], properties: [], classPage: null,
    seo: { title: 'Обычный раздел', description: 'Описание раздела', canonical: 'https://example.test/catalog/ryba/skumbriia', image: '/category.jpg', robots: 'index,follow' }, ...changes,
})

test('a configured catalog class renders the existing guide and its current canonical metadata', t => {
    const classPage = { guide: 'mackerel', goods: [{ id: 75, url: '/g/latest' }], inlineGoods: { 75: { id: 75, url: '/g/latest' } },
        seo: { title: 'Скумбрия — ПИЩЕПРОМ-СЕРВЕР', description: 'Полный гид', h1: 'Скумбрия', canonical: 'https://example.test/catalog/ryba/skumbriia', robots: 'index,follow', jsonLd: { '@type': 'CollectionPage', name: 'Скумбрия <свежая>' } },
        breadcrumbs: [{ name: 'Рыба', url: '/catalog/ryba' }, { name: 'Скумбрия', url: '/catalog/ryba/skumbriia' }] }
    const h = harness(t, 'resources/js/Pages/Catalog/Show.vue', catalogProps({ classPage }))
    const landing = findVNode(h.render(), node => node.type === 'ClassLanding')
    assert.equal(landing.props.page, h.props.classPage)
    assert.equal(findVNode(h.render(), node => hasClass(node, 'catalog-public')), null)
    assert.equal(findVNode(h.render(), node => node.type === 'Head').props.title, classPage.seo.title)
    assert.equal(findVNode(h.render(), node => node.props?.rel === 'canonical').props.href, classPage.seo.canonical)
    assert.equal(findVNode(h.render(), node => node.props?.property === 'og:image').props.content, '/category.jpg')
    const jsonLd = h.api.JsonLdHead()
    assert.equal(jsonLd.props.type, 'application/ld+json')
    assert.ok(!jsonLd.children.includes('<'))
    assert.deepEqual(JSON.parse(jsonLd.children), classPage.seo.jsonLd)
    h.props.node.id = 999
    assert.ok(findVNode(h.render(), node => node.type === 'ClassLanding'), 'Selection follows the server guide key, not a hardcoded node ID')
})

test('generic catalog pages retain breadcrumbs, properties, children and metadata without a guide', t => {
    const children = [{ id: 161, name: 'Форель', public_url: '/catalog/ryba/forel' }]
    const h = harness(t, 'resources/js/Pages/Catalog/Show.vue', catalogProps({ node: { id: 25, name: 'Рыба' }, children,
        properties: [{ label: 'Проверено', type: 'boolean', value: true }] }))
    assert.equal(findVNode(h.render(), node => node.type === 'ClassLanding'), null)
    assert.equal(findVNode(h.render(), node => node.type === 'h1').children, 'Рыба')
    assert.equal(findVNode(h.render(), node => node.type === 'PublicCatalogCards').props.items, h.props.children)
    assert.equal(findVNode(h.render(), node => node.props?.rel === 'canonical').props.href, h.props.seo.canonical)
    assert.equal(h.api.displayValue(h.props.properties[0]), 'Да')
    h.props.classPage = { guide: 'unknown-guide', seo: { title: 'Wrong title' } }
    assert.equal(findVNode(h.render(), node => node.type === 'Head').props.title, h.props.seo.title)
    assert.equal(findVNode(h.render(), node => node.type === 'ClassLanding'), null)
})

test('ordinary Product pages continue to render their original goods grid', t => {
    const h = harness(t, 'resources/js/Pages/Products/Show.vue', { product: { id: 201, rus: 'Скумбрия замороженная' },
        goods: [{ id: 75, name: 'Партия скумбрии', slug: 'current-good', ava_image: '/fish.jpg' }], seo: { canonical: 'https://example.test/p/201' }, classPage: null })
    assert.equal(findVNode(h.render(), node => node.type === 'ClassLanding'), null)
    assert.equal(findVNode(h.render(), node => node.type === 'h1').children, 'Скумбрия замороженная')
    assert.equal(findVNode(h.render(), node => hasClass(node, 'product-good-card__link')).props.href, '/g/current-good')
    assert.equal(findVNode(h.render(), node => node.type === 'v-img').props.src, '/fish.jpg')
})
