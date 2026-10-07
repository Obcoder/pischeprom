import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

function harness(t, node) {
    const filename = 'resources/js/Components/Catalog/CatalogAvatar.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const script = compileScript(descriptor, { id: 'catalog-avatar-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'catalog-avatar-test', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    const code = script.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${code}}`)(Vue)
    const props = Vue.reactive({ node, icon: 'mdi-tag-outline' })
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit() {} }))
    t.after(() => scope.stop())
    const fail = url => state.imageFailed({ target: { getAttribute: () => url } })
    return { state, props, fail }
}

test('avatar tries thumbnail, then original, then placeholder without replacing original metadata', t => {
    const node = { id: 1, name: 'Изюм', image: '/photos/original.jpg', thumbnail_url: 'https://cdn.example.test/thumb.jpg' }
    const { state, props, fail } = harness(t, node)
    assert.equal(state.source.value, node.thumbnail_url)
    fail(node.thumbnail_url)
    assert.equal(state.source.value, node.image)
    fail(node.image)
    assert.equal(state.source.value, undefined)
    assert.equal(props.node.image, '/photos/original.jpg')
    assert.equal(props.node.thumbnail_url, 'https://cdn.example.test/thumb.jpg')
})

test('avatar retries changed photos and does not loop when thumbnail equals original', async t => {
    const { state, props, fail } = harness(t, { id: 1, name: 'Товар', image: '/same.jpg', thumbnail_url: '/same.jpg' })
    assert.equal(state.sources.value.length, 1)
    fail('/same.jpg')
    assert.equal(state.source.value, undefined)
    props.node = { id: 2, name: 'Другой товар', image: '/same.jpg' }
    await Vue.nextTick()
    assert.equal(state.source.value, '/same.jpg')
    props.node.thumbnail_url = '/new-thumb.jpg'
    await Vue.nextTick()
    assert.equal(state.source.value, '/new-thumb.jpg')
})
