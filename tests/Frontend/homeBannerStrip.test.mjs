import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const filename = fileURLToPath(new URL('../../resources/js/Components/Home/HomeBannerStrip.vue', import.meta.url))
const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
const compiled = compileScript(descriptor, { id: 'home-banner-strip' })
const template = compileTemplate({ source: descriptor.template.content, filename, id: 'home-banner-strip', compilerOptions: { bindingMetadata: compiled.bindings } })
assert.deepEqual(template.errors, [])
const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')

function harness(feed, previewDevice = null) {
    const environment = {
        ...Vue,
        useAppRoute: () => ({ route: (name, param) => ({ 'public.goods.index': '/g', 'category.show': '/категория/' + param }[name] || '#') }),
        usePublicGoodUrl: () => ({ goodPublicUrl: good => '/g/' + (good.slug || good.id) }),
    }
    const component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope()
    const props = Vue.reactive({ feed, previewDevice })
    const api = scope.run(() => component.setup(props, { expose: () => {} }))
    return { api, props, render: templateRenderer(template, api, props), dispose: () => scope.stop() }
}

const banner = (id, overrides = {}) => ({ id, title: 'Баннер ' + id, image_url: '/banners/' + id + '.webp', cta_url: '/g?banner=' + id, ...overrides })
const row = (root, name) => findVNode(root, node => hasClass(node, name))
const children = node => node.children.flatMap(child => Array.isArray(child.children) ? child.children : [child])

test('six desktop slots preserve empty positions while mobile follows its saved order', t => {
    const feed = { settings: { mobile_order: [6, 5, 4, 3, 2, 1] }, desktop: [banner(1), null, banner(3)], mobile: [banner(1), null, banner(3), null, null, banner(6)] }
    const h = harness(feed)
    t.after(h.dispose)
    assert.equal(h.api.desktopSlots.value.length, 6)
    assert.deepEqual(h.api.desktopSlots.value.map(item => item.banner?.id || null), [1, null, 3, null, null, null])
    assert.deepEqual(h.api.mobileSlots.value.map(item => item.slot), [6, 3, 1])
    const desktop = row(h.render(), 'home-banner-strip__desktop')
    assert.equal(children(desktop).length, 6)
    assert.equal(findVNode(children(desktop)[1], node => node.type === 'a'), null)
    assert.deepEqual(children(row(h.render(), 'home-banner-strip__track')).map(item => item.props['data-slot']), [6, 3, 1])
})

test('disabled and empty feeds collapse; explicit admin previews still reserve all six positions', t => {
    for (const feed of [{}, { settings: { enabled: false }, desktop: [banner(1)] }]) {
        const h = harness(feed)
        t.after(h.dispose)
        assert.equal(row(h.render(), 'home-banner-strip'), null)
    }
    const h = harness({ settings: { enabled: false }, desktop: [] }, 'desktop')
    t.after(h.dispose)
    assert.ok(row(h.render(), 'home-banner-strip--preview-desktop'))
    assert.equal(h.api.desktopSlots.value.length, 6)
})

test('mobile images and their alignment are independent from desktop and new tabs are protected', t => {
    const item = banner(1, { mobile_image_url: '/banners/mobile.webp', image_fit: 'cover', image_position: 'left top', mobile_image_fit: 'contain', mobile_image_position: 'right bottom', alt_text: 'Ингредиенты', open_in_new_tab: true })
    const h = harness({ desktop: [item], mobile: [item] })
    t.after(h.dispose)
    const mobile = row(h.render(), 'home-banner-strip__mobile')
    const link = findVNode(mobile, node => node.type === 'a')
    assert.equal(link.props.target, '_blank')
    assert.equal(link.props.rel, 'noopener noreferrer')
    assert.equal(link.props.style['--banner-image-fit'], 'contain')
    assert.equal(link.props.style['--banner-image-position'], 'right bottom')
    const image = findVNode(link, node => node.type === 'img')
    assert.equal(image.props.src, '/banners/mobile.webp')
    assert.equal(image.props.alt, 'Ингредиенты')
})

test('unsafe URLs cannot become navigable links or image sources and related products use their public URL', t => {
    for (const url of ['javascript:alert(1)', 'data:text/html,hello', '//external.example', '/\\external.example']) {
        const item = banner(1, { cta_url: url, image_url: url })
        const h = harness({ desktop: [item] })
        t.after(h.dispose)
        const link = findVNode(h.render(), node => node.type === 'a')
        assert.equal(link.props.href, '/g')
        assert.equal(findVNode(link, node => node.type === 'img'), null)
        assert.ok(findVNode(link, node => hasClass(node, 'home-banner-strip__title')))
    }
    const h = harness({ desktop: [banner(1, { cta_url: '', product: { id: 42 } })] })
    t.after(h.dispose)
    assert.equal(findVNode(h.render(), node => node.type === 'a').props.href, '/p/42')
})

test('scroll controls move the actual mobile track and update range at both ends', t => {
    const items = Array.from({ length: 6 }, (_, index) => banner(index + 1))
    const h = harness({ mobile: items })
    t.after(h.dispose)
    const commands = []
    h.api.mobileTrack.value = { clientWidth: 352, scrollBy: command => commands.push(command) }
    h.api.scrollMobile(1)
    assert.equal(commands[0].left, 180)
    h.api.onMobileScroll({ target: { clientWidth: 352, scrollLeft: 720 } })
    assert.equal(h.api.firstMobileIndex.value, 4)
    const next = findVNode(h.render(), node => node.type === 'button' && node.props['aria-label'] === 'Следующие баннеры')
    assert.equal(next.props.disabled, true)
    h.api.scrollMobile(-1)
    assert.equal(commands[1].left, -180)
})

test('admin previews cannot navigate away from unsaved banner changes', t => {
    const h = harness({ desktop: [banner(1)] }, 'desktop')
    t.after(h.dispose)
    const link = findVNode(h.render(), node => node.type === 'a')
    assert.equal(link.props.tabindex, -1)
    let prevented = false
    link.props.onClick({ preventDefault: () => { prevented = true } })
    assert.equal(prevented, true)
})
