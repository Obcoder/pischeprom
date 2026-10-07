import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { buildCatalogPhotos, safeGalleryUrl } from '../../resources/js/Components/Catalog/gallery.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

const good = (changes = {}) => ({
    id: 71, entity_type: 'good', entity_id: 42, name: 'Филе форели', level_name: 'Товары',
    image: '/storage/trout-original.jpg', thumbnail_url: '/storage/trout-thumb.jpg',
    path_label: 'Рыба / Форель / Форель филе', edit_url: '/ameise/goods/42', ...changes,
})
const photo = (changes = {}) => ({
    id: 1, type: 'image', url: '/storage/trout-original.jpg', thumb_url: '/storage/trout-thumb.jpg',
    title: 'Филе крупным планом', alt: 'Филе охлаждённое', caption: 'Фасовка 1 кг',
    width: 2400, height: 1600, size: 2097152, is_published: true, is_ava: true, ...changes,
})
function harness(t, initialProps = {}) {
    const filename = 'resources/js/Components/Catalog/CatalogGalleryDialog.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'CatalogGalleryDialog' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'CatalogGalleryDialog', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    const environment = {
        ...Vue, buildCatalogPhotos, safeGalleryUrl, _mergeModels: Vue.mergeModels,
        _useModel: (props, name) => Vue.computed({ get: () => props[name], set: value => { props[name] = value; emitted.push([`update:${name}`, value]) } }),
        axios: { get(url, options) {
            let resolve, reject
            const pending = new Promise((success, failure) => { resolve = success; reject = failure })
            requests.push({ url, options, resolve: data => resolve({ data }), reject })
            return pending
        } },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const props = Vue.reactive({ modelValue: true, node: good(), ...initialProps })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { api, props, requests, emitted, scope, render: templateRenderer(template, api, props) }
}
const settle = async () => { await Promise.resolve(); await Vue.nextTick() }
const original = h => findVNode(h.render(), node => node.type === 'img' && hasClass(node, 'catalog-gallery__original'))
const button = (h, label) => findVNode(h.render(), node => node.props?.['aria-label'] === label)
const imageEvent = (url, width = 0, height = 0) => ({ currentTarget: { getAttribute: name => name === 'src' ? url : null, naturalWidth: width, naturalHeight: height } })
function key(h, name, target = {}) {
    let prevented = false
    const event = { key: name, target, preventDefault() { prevented = true } }
    findVNode(h.render(), node => node.type === 'v-dialog').props.onKeydown(event)
    return prevented
}

test('gallery fetches media only for an open good and displays original avatars for other entities', async t => {
    const h = harness(t, { modelValue: false })
    assert.equal(h.requests.length, 0)
    h.props.node = good({ entity_type: 'product', edit_url: null })
    h.props.modelValue = true
    assert.equal(h.requests.length, 0)
    assert.equal(original(h).props.src, '/storage/trout-original.jpg')
    assert.equal(h.api.identity.value, 'Продукт № 42')
    assert.equal(h.api.classificationPath.value, 'Рыба / Форель / Форель филе')
    h.props.node = good({ image: null, thumbnail_url: '/only-thumb.jpg', entity_type: 'custom', path_label: '', ancestors: [{ name: 'Рыба' }, { name: 'Форель' }] })
    assert.equal(h.api.photos.value.length, 0, 'A thumbnail must not masquerade as a full-resolution image')
    assert.equal(h.api.classificationPath.value, 'Рыба / Форель')
    assert.equal(original(h), null)
    h.props.node = good({ entity_id: '../42' })
    assert.equal(h.requests.length, 0)
    h.props.node = good()
    assert.equal(h.requests[0].url, '/api/goods/42/media')
    h.requests[0].resolve([])
    await settle()
    assert.equal(h.api.photos.value.length, 1)
})

test('staff gallery deduplicates the avatar, includes unpublished photos and renders original URLs instead of thumbnails', async t => {
    const h = harness(t)
    h.requests[0].resolve([
        photo({ is_ava: false }),
        photo({ id: 2, url: '/second-original.png', thumb_url: '/second-thumb.png', is_ava: false, is_published: false, title: 'Упаковка' }),
        photo({ id: 3, type: 'video', url: '/clip.mp4' }),
        photo({ id: 4, type: 'document', url: '/spec.pdf' }),
        photo({ id: 5, url: null, thumb_url: '/orphan-thumb.png' }),
    ])
    await settle()
    assert.equal(h.api.photos.value.length, 2)
    assert.equal(h.api.activePhoto.value.isAvatar, true)
    assert.equal(h.api.activePhoto.value.title, 'Филе крупным планом')
    assert.equal(h.api.photoDetails.value, '2400 × 1600 px · 2 МБ')
    assert.equal(original(h).props.src, '/storage/trout-original.jpg')
    const thumb = button(h, 'Фото 2: Упаковка')
    assert.equal(findVNode(thumb, node => node.type === 'img').props.src, '/second-thumb.png')
    thumb.props.onClick()
    assert.equal(original(h).props.src, '/second-original.png')
    assert.equal(h.api.activePhoto.value.isPublished, false)
    const link = findVNode(h.render(), node => node.props?.href === '/second-original.png')
    assert.equal(link.props.target, '_blank')
    assert.equal(link.props.rel, 'noopener noreferrer')
})

test('switching nodes and closing abort requests and ignore late responses or failures', async t => {
    const h = harness(t)
    const first = h.requests[0]
    h.props.node = good({ id: 72, entity_id: 43, image: '/other.jpg' })
    const second = h.requests[1]
    assert.equal(first.options.signal.aborted, true)
    first.resolve([photo({ url: '/stale.jpg' })])
    await settle()
    assert.deepEqual(h.api.photos.value.map(item => item.url), ['/other.jpg'])
    assert.equal(h.api.loading.value, true)
    second.resolve([photo({ url: '/other-detail.jpg' })])
    await settle()
    assert.deepEqual(h.api.photos.value.map(item => item.url), ['/other.jpg', '/other-detail.jpg'])
    h.props.node = good({ id: 73, entity_id: 44, image: null })
    const third = h.requests[2]
    h.props.modelValue = false
    assert.equal(third.options.signal.aborted, true)
    third.reject({ response: { status: 403 } })
    await settle()
    assert.equal(h.api.error.value, '')
    assert.equal(h.api.loading.value, false)
    assert.deepEqual(h.api.photos.value, [])
    h.props.modelValue = true
    const fourth = h.requests[3]
    h.scope.stop()
    assert.equal(fourth.options.signal.aborted, true)
    fourth.resolve([photo()])
    await settle()
    assert.deepEqual(h.api.media.value, [])
})

test('failed private media requests keep the avatar usable and retry retrieves photos', async t => {
    const h = harness(t)
    h.requests[0].reject({ response: { status: 403, data: { message: 'Private server details' } } })
    await settle()
    assert.equal(h.api.error.value, 'Нет доступа к фотографиям товара.')
    assert.equal(original(h).props.src, '/storage/trout-original.jpg')
    assert.equal(h.api.loading.value, false)
    const retry = h.api.loadMedia()
    assert.equal(h.api.error.value, '')
    h.requests[1].resolve([photo({ id: 2, url: '/second.jpg' })])
    await retry
    assert.equal(h.api.photos.value.length, 2)
    assert.equal(h.api.loading.value, false)

    h.props.node = good({ id: 72, entity_id: 43, image: null })
    h.requests[2].reject({ response: { status: 404 } })
    await settle()
    assert.equal(h.api.error.value, 'Галерея товара недоступна.')
    assert.equal(h.api.activePhoto.value, null)
    assert.ok(findVNode(h.render(), node => hasClass(node, 'catalog-gallery__placeholder')))
})

test('previous, next, thumbnail and keyboard controls traverse photos and Escape closes the gallery', async t => {
    const h = harness(t)
    h.requests[0].resolve([photo(), photo({ id: 2, url: '/second.jpg', title: 'Вторая' }), photo({ id: 3, url: '/third.jpg', title: 'Третья' })])
    await settle()
    button(h, 'Следующее фото').props.onClick()
    assert.equal(h.api.selectedIndex.value, 1)
    button(h, 'Предыдущее фото').props.onClick()
    assert.equal(h.api.selectedIndex.value, 0)
    assert.equal(key(h, 'ArrowLeft'), true)
    assert.equal(h.api.selectedIndex.value, 2)
    assert.equal(key(h, 'ArrowRight'), true)
    assert.equal(h.api.selectedIndex.value, 0)
    assert.equal(key(h, 'ArrowRight', { tagName: 'INPUT' }), false)
    assert.equal(h.api.selectedIndex.value, 0)
    button(h, 'Фото 3: Третья').props.onClick()
    assert.equal(h.api.selectedIndex.value, 2)
    assert.equal(button(h, 'Фото 3: Третья').props['aria-pressed'], true)
    assert.equal(key(h, 'Escape'), true)
    assert.equal(h.props.modelValue, false)
    assert.deepEqual(h.emitted.at(-1), ['update:modelValue', false])
})

test('broken full-resolution photos and thumbnails get usable placeholders and independent retry paths', async t => {
    const h = harness(t)
    h.requests[0].resolve([photo({ width: null, height: null }), photo({ id: 2, url: '/second.jpg', thumb_url: '/second-thumb.jpg', title: 'Вторая' })])
    await settle()
    original(h).props.onError(imageEvent(original(h).props.src))
    assert.equal(original(h), null)
    assert.ok(findVNode(h.render(), node => hasClass(node, 'catalog-gallery__placeholder')))
    h.api.retryImage()
    assert.equal(original(h).props.src, '/storage/trout-original.jpg')
    original(h).props.onLoad(imageEvent(original(h).props.src, 3200, 4800))
    assert.equal(h.api.photoDetails.value, '3200 × 4800 px · 2 МБ')

    let thumbnail = findVNode(button(h, 'Фото 2: Вторая'), node => node.type === 'img')
    thumbnail.props.onError(imageEvent(thumbnail.props.src))
    thumbnail = findVNode(button(h, 'Фото 2: Вторая'), node => node.type === 'img')
    assert.equal(thumbnail.props.src, '/second.jpg')
    thumbnail.props.onError(imageEvent(thumbnail.props.src))
    assert.equal(findVNode(button(h, 'Фото 2: Вторая'), node => node.type === 'img'), null)
    assert.ok(original(h), 'A broken thumbnail must not remove the selected original')
})

test('late image events belong to their original URL and do not break or resize the next selected photo', async t => {
    const h = harness(t)
    h.requests[0].resolve([photo({ width: null, height: null }), photo({ id: 2, url: '/second.jpg', title: 'Вторая', width: null, height: null, size: null })])
    await settle()
    const first = original(h)
    button(h, 'Следующее фото').props.onClick()
    first.props.onLoad(imageEvent(first.props.src, 100, 300))
    first.props.onError(imageEvent(first.props.src))
    assert.equal(original(h).props.src, '/second.jpg')
    assert.equal(h.api.photoDetails.value, '')
    original(h).props.onLoad(imageEvent('/second.jpg', 800, 600))
    assert.equal(h.api.photoDetails.value, '800 × 600 px')
    h.props.node = good({ id: 72, entity_type: 'product', image: '/third.jpg' })
    first.props.onLoad(imageEvent(first.props.src, 300, 500))
    first.props.onError(imageEvent(first.props.src))
    assert.equal(h.api.brokenImages.value.size, 0)
    assert.deepEqual(h.api.loadedImages.value, {})
    assert.equal(original(h).props.src, '/third.jpg')
})

test('image and card URLs reject unsafe protocols, credentials and malformed links', t => {
    for (const unsafe of ['javascript:alert(1)', 'data:image/png;base64,abc', 'vbscript:bad', '//evil.test/file.jpg', '/\\evil.test/file.jpg', 'https://user:pass@example.com/photo.jpg', 'https://example.com/\nphoto.jpg', 'relative.jpg']) {
        assert.equal(safeGalleryUrl(unsafe), '')
    }
    assert.equal(safeGalleryUrl('/storage/photo.jpg'), '/storage/photo.jpg')
    assert.equal(safeGalleryUrl('https://cdn.example.com/photo.jpg'), 'https://cdn.example.com/photo.jpg')
    assert.deepEqual(buildCatalogPhotos(good({ image: 'javascript:bad' }), [photo({ url: 'data:bad' })]), [])
    const h = harness(t, { node: good({ entity_type: 'custom', image: 'javascript:bad', edit_url: 'javascript:alert(1)' }) })
    assert.equal(h.api.editUrl.value, '')
    assert.equal(h.api.photos.value.length, 0)
    assert.equal(findVNode(h.render(), node => Boolean(node.props?.href)), null)
})

test('canonical duplicate originals retain media metadata without adding low-resolution-only entries', () => {
    const items = buildCatalogPhotos(good({ image: 'https://CDN.example.com/photo.jpg', thumbnail_url: '/avatar-thumb.jpg' }), [
        photo({ url: 'https://cdn.example.com/photo.jpg', thumb_url: '/media-thumb.jpg' }),
        photo({ id: 2, url: '/second.jpg', thumb_url: 'javascript:bad' }),
        photo({ id: 3, url: null, thumb_url: '/thumb-without-original.jpg' }),
    ])
    assert.equal(items.length, 2)
    assert.equal(items[0].url, 'https://CDN.example.com/photo.jpg')
    assert.equal(items[0].thumbnail, '/media-thumb.jpg')
    assert.equal(items[0].title, 'Филе крупным планом')
    assert.equal(items[1].thumbnail, '/second.jpg')
})

test('a legacy thumbnail-only avatar resolves to its explicitly linked original without duplicate low-resolution photos', async t => {
    const h = harness(t, { node: good({ image: '/storage/trout-thumb.jpg', thumbnail_url: '/storage/trout-thumb.jpg' }) })
    h.requests[0].resolve([photo(), photo({ id: 2, url: '/second.jpg', thumb_url: '/second-thumb.jpg', is_ava: false })])
    await settle()
    assert.equal(h.api.photos.value.length, 2)
    assert.equal(original(h).props.src, '/storage/trout-original.jpg')
    assert.equal(h.api.photos.value[0].thumbnail, '/storage/trout-thumb.jpg')
    assert.equal(h.api.photos.value[0].isAvatar, true)
    assert.ok(h.api.photos.value.every(item => item.url !== '/storage/trout-thumb.jpg'))
    const unmatched = buildCatalogPhotos(good({ image: '/unrelated-thumb.jpg' }), [photo()])
    assert.equal(unmatched[0].url, '/unrelated-thumb.jpg', 'Only an exact media thumbnail link may resolve the original')
})
