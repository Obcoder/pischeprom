import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { measurementForGood, unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { publicCategoryUrl } from '../../resources/js/utils/publicCategoryUrl.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, filename, values) {
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'catalog-public-avatars' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'catalog-public-avatars', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const componentImports = Object.fromEntries([...compiled.content.matchAll(/^import (\w+) from ['"].*\.vue['"];?$/gm)].map(match => [match[1], match[1]]))
    const environment = { ...Vue, ...componentImports, Link: 'Link', Head: 'Head', logo: '/logo.png', measurementForGood, unitLabel, publicCategoryUrl,
        useAppRoute: () => ({ route: (name, id) => name === 'public.goods.index' ? '/g' : name === 'category.show' ? `/category/${id}` : `/${name}` }),
        usePublicGoodUrl: () => ({ goodPublicUrl: good => `/g/${good.id}` }),
        usePhoneCallRegistration: () => ({ registerPhoneCallClick() {} }),
        usePage: () => ({ props: { publicCategoryUrls: { 25: '/catalog/fish' } } }),
        useHead() {}, canSubscribeToGoodStock: () => false, router: {}, onMounted() {}, onBeforeUnmount() {},
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive(Object.fromEntries(Object.entries(component.props).map(([key, setting]) => [key, typeof setting.default === 'function' ? setting.default() : setting.default])))
    Object.assign(props, values)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { props, api, render: templateRenderer(template, api, props) }
}

const category = { id: 25, name: 'Рыба', image: '/fish.png', thumbnail_url: '/fish-thumb.png' }
const product = { id: 42, rus: 'Скумбрия', image: '/mackerel.png', thumbnail_url: '/mackerel-thumb.png', category }
const good = { id: 75, name: 'Скумбрия замороженная', products: [product], created_at: '2026-10-10' }

for (const filename of ['HomeGoodsBookCard', 'HomeGoodsSearchCard']) {
    test(`${filename} retains product and category images when grouping goods`, t => {
        const h = harness(t, `resources/js/Components/Home/${filename}.vue`, { goods: [good], module: { authenticated: true, ordered_goods: [good] } })
        const group = findVNode(h.render(), node => hasClass(node, 'product-group__header'))
        const productTitle = findVNode(group, node => hasClass(node, 'product-group__title'))
        assert.equal(findVNode(productTitle, node => node.type === 'CatalogNodeAvatar').props.node.thumbnail_url, '/mackerel-thumb.png')
        const categoryTitle = findVNode(group, node => hasClass(node, 'product-group__category'))
        assert.equal(findVNode(categoryTitle, node => node.type === 'CatalogNodeAvatar').props.node.thumbnail_url, '/fish-thumb.png')
        h.props.goods = [{ ...good, products: [] }]
        if (filename === 'HomeGoodsBookCard') h.props.module.ordered_goods = h.props.goods
        assert.equal(findVNode(findVNode(h.render(), node => hasClass(node, 'product-group__header')), node => node.type === 'CatalogNodeAvatar'), null)
    })
}

test('home table of contents renders section and product avatars while preserving navigation', t => {
    const h = harness(t, 'resources/js/Components/Home/HomeGoodsBookCard.vue', { module: { table_of_contents: [{ ...category, title: 'Рыба', page: 1, entries: [{ ...product, title: 'Скумбрия', page: 2 }] }] } })
    const section = findVNode(h.render(), node => hasClass(node, 'book-toc__section-title'))
    assert.equal(section.props.href, '/category/25')
    assert.equal(findVNode(section, node => node.type === 'CatalogNodeAvatar').props.node.image, '/fish.png')
    const entry = findVNode(h.render(), node => hasClass(node, 'book-toc__entry'))
    assert.equal(entry.props.href, `/g?search=${encodeURIComponent('Скумбрия')}`)
    assert.equal(findVNode(entry, node => node.type === 'CatalogNodeAvatar').props.node.image, '/mackerel.png')
})

test('home featured category keeps its structural icon beside the resolved category avatar', t => {
    const h = harness(t, 'resources/js/Components/Home/HomeHeroSection.vue', { categories: [category] })
    const link = findVNode(h.render(), node => hasClass(node, 'hero-v2__category-link') && node.props.href === '/category/25')
    assert.ok(findVNode(link, node => node.type === 'v-icon'))
    assert.equal(findVNode(link, node => node.type === 'CatalogNodeAvatar').props.node.image, '/fish.png')
})

test('header dropdown retains canonical category destinations and avatars', t => {
    const h = harness(t, 'resources/js/Components/Layout/AppHeader.vue', { categories: [category] })
    const navigation = findVNode(h.render(), node => node.type === 'nav')
    const dropdown = findVNode(navigation, node => node.type === 'details')
    const link = findVNode(dropdown, node => hasClass(node, 'app-header__category-link'))
    assert.equal(link.props.href, '/catalog/fish')
    assert.equal(findVNode(link, node => node.type === 'CatalogNodeAvatar').props.node.image, '/fish.png')
    h.props.categories = []
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__category-link')), null)
    assert.equal(findVNode(h.render(), node => node.type === 'CatalogNodeAvatar'), null)
})

test('legacy category product chips and picker carry the shared product avatar', t => {
    const h = harness(t, 'resources/js/Pages/Categories/Show.vue', { category: { ...category, products: [product] } })
    const chip = findVNode(h.render(), node => hasClass(node, 'category-product-chip'))
    assert.equal(findVNode(chip, node => node.type === 'CatalogNodeAvatar').props.node.image, '/mackerel.png')
    chip.props.onClick()
    assert.equal(h.api.selectedProductId.value, 42)
    const picker = findVNode(h.render(), node => node.props?.label === 'Продукт')
    const item = { raw: h.api.productFilterItems.value[1], title: 'Скумбрия' }
    const selection = picker.children.selection({ item })
    assert.equal(findVNode(selection, node => node.type === 'CatalogNodeAvatar').props.node.image, '/mackerel.png')
})
