import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { catalogAvatarSources } from '../../resources/js/Components/Catalog/catalogAvatarSources.js'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, node, size = 28) {
    const filename = 'resources/js/Components/Catalog/CatalogNodeAvatar.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'catalog-node-avatar-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'catalog-node-avatar-test', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)({ ...Vue, catalogAvatarSources })
    const props = Vue.reactive({ node, size })
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    const render = templateRenderer(template, state, props)
    const img = () => findVNode(render(), node => node.type === 'img')
    const fail = () => img().props.onError({ target: { getAttribute: () => img().props.src } })
    return { props, state, render, img, fail }
}

test('shared avatar preserves original data and falls back from a broken thumbnail before disappearing', t => {
    const node = { id: 9, name: 'Категория', image: '/storage/категория.webp', thumbnail_url: 'https://cdn.example.test/thumb.webp' }
    const h = harness(t, node, 40)
    assert.equal(h.img().props.src, node.thumbnail_url)
    assert.equal(h.img().props.alt, '')
    assert.equal(h.img().props['aria-hidden'], 'true')
    assert.equal(h.img().props.width, 40)
    assert.equal(h.img().props.height, 40)
    assert.equal(h.img().props.style['--catalog-avatar-size'], '40px')
    assert.equal(findVNode(h.render(), node => ['button', 'a'].includes(node.type)), null)
    h.fail()
    assert.equal(h.img().props.src, node.image)
    h.fail()
    assert.equal(h.img(), null)
    assert.deepEqual(h.props.node, node)
})

test('shared avatar stays absent for missing images and retries when the record or image changes', async t => {
    const h = harness(t, { id: 1, name: 'Домен' })
    assert.equal(h.img(), null)
    h.props.node.image = '/storage/avatar.png'
    await Vue.nextTick()
    h.fail()
    assert.equal(h.img(), null)
    h.props.node.id = 2
    await Vue.nextTick()
    assert.equal(h.img().props.src, '/storage/avatar.png')
    h.props.node.thumbnail_url = '/storage/new-thumb.png'
    await Vue.nextTick()
    assert.equal(h.img().props.src, '/storage/new-thumb.png')
})

test('shared avatar tolerates absent site metadata and recovers when metadata arrives', async t => {
    const h = harness(t, null)
    assert.equal(h.img(), null)
    h.props.node = { id: 1, image: '/storage/site.png' }
    await Vue.nextTick()
    assert.equal(h.img().props.src, '/storage/site.png')
    h.props.node = null
    await Vue.nextTick()
    assert.equal(h.img(), null)
})

test('avatar sources accept only explicit HTTP or local paths, normalize whitespace, and deduplicate', () => {
    assert.deepEqual(catalogAvatarSources({ thumbnail_url: ' /storage/avatar.png ', image: '/storage/avatar.png' }), ['/storage/avatar.png'])
    assert.deepEqual(catalogAvatarSources({ image: 'https://пример.рф/изображение.webp' }), ['https://пример.рф/изображение.webp'])
    for (const image of ['javascript:alert(1)', 'data:image/svg+xml,<svg/>', '//example.test/avatar.png', '/\\example.test/avatar.png', 'https://', 'https://user:secret@example.test/avatar.png', '/image\n.png', 'relative.png', 123, null]) {
        assert.deepEqual(catalogAvatarSources({ image }), [], String(image))
    }
})
