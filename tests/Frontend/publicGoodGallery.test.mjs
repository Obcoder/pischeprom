import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const photo = (changes = {}) => ({ id: 1, type: 'image', is_published: true, url: '/portrait.jpg', thumb_url: '/cropped-square.jpg', width: 900, height: 1500, ...changes })
const video = (changes = {}) => ({ id: 10, type: 'video', is_published: true, processing_status: 'done', video_mp4_url: '/processed.mp4', ...changes })
const imageEvent = (url, width = 0, height = 0) => ({ currentTarget: { getAttribute: name => name === 'src' ? url : null, naturalWidth: width, naturalHeight: height } })

function harness(t, good = {}) {
    const filename = 'resources/js/Components/Goods/PublicGoodGallery.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'public-good-gallery' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'public-good-gallery', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const source = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${source}}`)({ ...Vue, GoodProductReel: 'GoodProductReel' })
    const props = Vue.reactive({ good: { id: 1, name: 'Товар', published_media: [photo()], ...good } })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { api, props, render: templateRenderer(template, api, props) }
}

const stage = h => findVNode(h.render(), node => hasClass(node, 'product-gallery__stage'))
const original = h => findVNode(stage(h), node => node.type === 'img')
const button = (h, label) => findVNode(h.render(), node => node.props?.['aria-label'] === label)

test('original dimensions give portrait, landscape and square photos their own aspect ratios', t => {
    const h = harness(t, { published_media: [photo(), photo({ id: 2, url: '/landscape.jpg', width: 1800, height: 900 }), photo({ id: 3, url: '/square.jpg', width: 800, height: 800 })] })
    assert.equal(stage(h).props.style['--media-aspect-ratio'], 0.6)
    assert.ok(hasClass(stage(h), 'product-gallery__stage--portrait'))
    button(h, 'Фотография 2').props.onClick()
    assert.equal(original(h).props.src, '/landscape.jpg')
    assert.equal(stage(h).props.style['--media-aspect-ratio'], 2)
    assert.ok(hasClass(stage(h), 'product-gallery__stage--landscape'))
    assert.equal(button(h, 'Фотография 2').props['aria-pressed'], true)
    button(h, 'Фотография 3').props.onClick()
    assert.equal(stage(h).props.style['--media-aspect-ratio'], 1)
    assert.ok(hasClass(stage(h), 'product-gallery__stage--square'))
})

test('natural dimensions supply missing metadata and correct stale orientation without using square thumbnails', t => {
    const h = harness(t, { published_media: [photo({ width: null, height: null }), photo({ id: 2, url: '/rotated.jpg', width: 1800, height: 900 })] })
    original(h).props.onLoad(imageEvent('/portrait.jpg', 900, 1500))
    assert.equal(h.api.activeFrame.value.ratio, 0.6)
    const thumb = findVNode(button(h, 'Фотография 2'), node => node.type === 'img')
    assert.equal(thumb.props.src, '/rotated.jpg', 'The backend thumbnail is a square crop and must not determine orientation')
    thumb.props.onLoad(imageEvent('/rotated.jpg', 900, 1800))
    assert.equal(button(h, 'Фотография 2').props.style['--media-aspect-ratio'], 0.5)
    button(h, 'Фотография 2').props.onClick()
    assert.equal(h.api.activeFrame.value.orientation, 'portrait')
    assert.equal(h.api.activeFrame.value.ratio, 0.5)
})

test('late image events remain associated with their source and are ignored after changing goods', t => {
    const h = harness(t, { published_media: [photo({ width: null, height: null }), photo({ id: 2, url: '/landscape.jpg', width: 1800, height: 900 })] })
    const first = original(h)
    button(h, 'Фотография 2').props.onClick()
    first.props.onLoad(imageEvent('/portrait.jpg', 900, 1500))
    first.props.onError(imageEvent('/portrait.jpg'))
    assert.equal(h.api.activeFrame.value.ratio, 2)
    assert.equal(original(h).props.src, '/landscape.jpg')
    h.props.good = { id: 2, name: 'Другой товар', ava_image: '/new.jpg' }
    first.props.onLoad(imageEvent('/portrait.jpg', 900, 1500))
    first.props.onError(imageEvent('/portrait.jpg'))
    assert.deepEqual(h.api.loadedDimensions.value, {})
    assert.equal(h.api.brokenImages.value.size, 0)
    assert.equal(h.api.selectedMedia.value, 0)
    assert.equal(original(h).props.src, '/new.jpg')
})

test('main processed video is separate from gallery while unpublished media and documents are excluded', t => {
    const h = harness(t, { published_media: [video(), photo({ is_published: false }), video({ id: 11, is_main_video: true }), photo({ id: 3, type: 'document', url: '/file.pdf' }), photo({ id: 2 })] })
    assert.equal(h.api.mainVideo.value.id, 11)
    assert.deepEqual(h.api.media.value.map(item => item.id), [2, 10])
    const reel = findVNode(h.render(), node => node.type === 'GoodProductReel')
    assert.equal(reel.props.video.id, 11)
    button(h, 'Смотреть видео товара').props.onClick()
    const player = findVNode(stage(h), node => node.type === 'video')
    assert.equal(player.props.src, '/processed.mp4')
    assert.equal(player.props.controls, '')
})

test('avatar-only and missing photos have usable display and zoom states; video-only media avoids an empty photo panel', t => {
    const h = harness(t, { published_media: [], ava_image: '/avatar.jpg', ava_thumb: '/avatar-crop.jpg' })
    assert.equal(original(h).props.src, '/avatar.jpg')
    original(h).props.onLoad(imageEvent('/avatar.jpg', 600, 1000))
    assert.equal(h.api.activeFrame.value.orientation, 'portrait')
    button(h, 'Увеличить фотографию товара').props.onClick()
    assert.equal(h.api.zoomOpen.value, true)
    button(h, 'Закрыть фотографию').props.onClick()
    assert.equal(h.api.zoomOpen.value, false)
    original(h).props.onError(imageEvent('/avatar.jpg'))
    assert.equal(original(h), null)
    assert.ok(findVNode(stage(h), node => hasClass(node, 'gallery-empty')))
    h.props.good = { id: 2, name: 'Видео', published_media: [video()] }
    assert.equal(stage(h), null)
    assert.ok(findVNode(h.render(), node => node.type === 'GoodProductReel'))
})
