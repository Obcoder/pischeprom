import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { catalogSeoPreview } from '../../resources/js/Components/Catalog/seoPreview.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const levels = [{ id: 1, is_domain: true }, { id: 2, is_domain: false }]
const nodes = [
    { id: 1, level_id: 1, parent_id: null, name: 'Сайт', slug: 'site', domain_hosts: ['food.test'], public_url: 'https://food.test/g' },
    { id: 2, level_id: 2, parent_id: 1, name: 'Рыба', slug: 'fish', catalog_path: 'fish', public_url: 'https://food.test/catalog/fish' },
    { id: 3, level_id: 2, parent_id: 2, name: 'Форель', slug: 'trout', catalog_path: 'fish/trout', public_url: 'https://food.test/catalog/fish/trout' },
    { id: 4, level_id: 1, parent_id: null, name: 'Другой сайт', slug: 'other', domain_hosts: ['other.test'], public_url: 'https://other.test/g' },
    { id: 5, level_id: 2, parent_id: 4, name: 'Рыба', slug: 'fish', catalog_path: 'fish', public_url: 'https://other.test/catalog/fish' },
]

test('metadata follows public catalog fallbacks and uses draft fields without substituting H1 for title', () => {
    const result = catalogSeoPreview({ name: 'Форель', h1: 'Другой H1', description: '<p>Свежая рыба</p>' })
    assert.equal(result.title, 'Форель — ПИЩЕПРОМ-СЕРВЕР')
    assert.equal(result.description, 'Свежая рыба')
    const explicit = catalogSeoPreview({ name: 'Название', meta_title: '  Ручной Title  ', meta_description: '  Ручное описание  ' })
    assert.equal(explicit.title, 'Ручной Title')
    assert.equal(explicit.description, 'Ручное описание')
    assert.equal(catalogSeoPreview({ name: 'Название' }).description, 'Название')
    assert.equal(Array.from(catalogSeoPreview({ description: '🌾'.repeat(260) }).description).length, 240)
})

test('existing URLs remain authoritative while explicit draft slug and parent edits update the proper site', () => {
    const record = nodes[2]
    assert.equal(catalogSeoPreview(record, record, nodes, levels).url, record.public_url)
    const edited = catalogSeoPreview({ ...record, slug: 'fresh-trout' }, record, nodes, levels, 'https://admin.test')
    assert.equal(edited.url, 'https://food.test/catalog/fish/fresh-trout')
    const moved = catalogSeoPreview({ ...record, parent_id: 5 }, record, nodes, levels, 'https://admin.test')
    assert.equal(moved.url, 'https://other.test/catalog/fish/trout')
    const root = catalogSeoPreview({ ...record, parent_id: null }, record, nodes, levels, 'https://admin.test')
    assert.equal(root.url, 'https://admin.test/catalog/trout')
})

test('domain nodes use goods landing and descendants omit the domain segment', () => {
    assert.equal(catalogSeoPreview({ ...nodes[0], domain_hosts: ['new.test'] }, nodes[0], nodes, levels).url, 'https://new.test/g')
    assert.equal(catalogSeoPreview({ name: 'Мука', slug: 'flour', parent_id: 1, level_id: 2 }, null, nodes, levels).url, 'https://food.test/catalog/flour')
    const domains = [{ ...nodes[0], domain_hosts: ['food.test', 'alias.test'] }, ...nodes.slice(1)]
    assert.equal(catalogSeoPreview({ name: 'Мука', slug: 'flour', parent_id: 1, level_id: 2 }, null, domains, levels, 'https://alias.test').url, 'https://alias.test/catalog/flour')
})

test('known duplicate and numeric root paths receive the same ID suffix as public routes', () => {
    const record = { id: 9, level_id: 2, parent_id: 2, slug: 'salmon' }
    assert.equal(catalogSeoPreview({ ...record, slug: 'trout' }, record, nodes, levels).url, 'https://food.test/catalog/fish/trout-node-9')
    assert.equal(catalogSeoPreview({ ...record, parent_id: null, slug: '123' }, record, nodes, levels).url, '/catalog/123-node-9')
    const fresh = catalogSeoPreview({ level_id: 2, parent_id: 2, slug: 'trout' }, null, nodes, levels)
    assert.equal(fresh.url, '')
    assert.match(fresh.urlHint, /уникальным суффиксом/)
})

test('automatic slugs, malformed domains and missing parents never show invented or stale URLs', () => {
    const record = nodes[2]
    for (const slug of ['', 'Новый адрес', 'javascript:alert(1)']) {
        const result = catalogSeoPreview({ ...record, slug }, record, nodes, levels)
        assert.equal(result.url, '')
        assert.match(result.urlHint, /после сохранения/)
    }
    assert.equal(catalogSeoPreview({ ...record, parent_id: 404 }, record, nodes, levels).url, '')
    assert.equal(catalogSeoPreview({ ...nodes[0], domain_hosts: ['evil.test/path'] }, nodes[0], nodes, levels).url, '')
})

test('rendered search snippet reacts to unsaved edits and displays user text without HTML injection', t => {
    const filename = 'resources/js/Components/Catalog/CatalogSeoPreview.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const script = compileScript(descriptor, { id: 'seo-preview-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'seo-preview-test', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)({ ...Vue, catalogSeoPreview })
    const props = Vue.reactive({ form: { ...nodes[2], meta_title: '', meta_description: '' }, record: nodes[2], nodes, levels })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    const render = templateRenderer(template, api, props)
    assert.equal(findVNode(render(), node => hasClass(node, 'catalog-seo-preview__title')).children, 'Форель — ПИЩЕПРОМ-СЕРВЕР')
    props.form.meta_title = 'Новый заголовок'
    props.form.meta_description = '<img src=x onerror=alert(1)>'
    props.form.slug = 'new-slug'
    assert.equal(findVNode(render(), node => hasClass(node, 'catalog-seo-preview__title')).children, 'Новый заголовок')
    const description = findVNode(render(), node => hasClass(node, 'catalog-seo-preview__description'))
    assert.equal(description.children, '<img src=x onerror=alert(1)>')
    assert.equal(description.props.innerHTML, undefined)
    assert.equal(findVNode(render(), node => hasClass(node, 'catalog-seo-preview__url')).children, 'https://food.test/catalog/fish/new-slug')
})
