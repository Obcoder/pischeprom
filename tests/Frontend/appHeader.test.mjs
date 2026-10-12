import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { publicCategoryUrl } from '../../resources/js/utils/publicCategoryUrl.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function harness(t, { categories = [], pageProps = {}, url = '/', routeUrls = {} } = {}) {
    const filename = 'resources/js/Components/Layout/AppHeader.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'app-header' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'app-header', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const page = Vue.reactive({ url, props: { auth: { user: null }, canLogin: false, canRegister: false, ziggy: { routes: {} }, publicCategoryUrls: {}, ...pageProps } })
    const requests = []
    const urls = { home: '/', 'public.goods.index': '/g', login: '/login', register: '/register', 'customer.profile.edit': '/dashboard/profile', 'profile.show': '/user/profile', dashboard: '/dashboard', logout: '/logout', ...routeUrls }
    const environment = {
        ...Vue, Link: 'Link', CitySelector: 'CitySelector', OrderCartStrip: 'OrderCartStrip', CatalogNodeAvatar: 'CatalogNodeAvatar', publicCategoryUrl,
        usePage: () => page,
        useAppRoute: () => ({ route: (name, parameter) => name === 'category.show' ? `/category/${parameter}` : urls[name] }),
        router: { post: (...args) => requests.push(args) },
    }
    const source = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${source}}`)(environment)
    const props = Vue.reactive({ categories })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    t.after(() => scope.stop())
    return { page, props, api, requests, render: templateRenderer(template, api, props) }
}

function allNodes(root, predicate) {
    if (!root || typeof root !== 'object') return []
    const children = Array.isArray(root) ? root : Array.isArray(root.children) ? root.children : root.children?.default?.() || []
    return [...(predicate(root) ? [root] : []), ...children.flatMap(child => allNodes(child, predicate))]
}

function textOf(root) {
    if (typeof root === 'string') return root
    if (Array.isArray(root)) return root.map(textOf).join('')
    if (root?.type === Vue.Comment) return ''
    return textOf(typeof root?.children === 'object' && !Array.isArray(root.children) ? root.children?.default?.() || '' : root?.children || '')
}

const navLinks = tree => allNodes(tree, node => hasClass(node, 'app-header__nav-link'))
const fish = { id: 25, name: 'Рыба', slug: 'fish', public_url: '/old-fish', image: '/fish.png' }

test('navigation is separate from top contacts and uses only supplied categories with their canonical links and avatars', t => {
    const h = harness(t, {
        categories: [fish, { id: 26, name: 'Специи', public_url: '/catalog/spices' }, { id: 27, name: 'Мука', slug: 'flour' }, null, { id: 28 }, { name: 'Без идентификатора' }],
        pageProps: { publicCategoryUrls: { 25: '/catalog/fish' } },
    })
    const tree = h.render()
    const top = findVNode(tree, node => hasClass(node, 'app-header__top'))
    const navigation = findVNode(tree, node => hasClass(node, 'app-header__navigation'))
    assert.ok(tree.children.includes(top))
    assert.ok(tree.children.includes(navigation))
    assert.equal(findVNode(top, node => node.type === 'nav'), null)
    assert.equal(findVNode(navigation, node => node.type === 'nav').props['aria-label'], 'Разделы магазина')
    assert.deepEqual(navLinks(navigation).map(link => [link.props.href, textOf(link)]), [
        ['/', 'Начало'], ['/g', 'Каталог'], ['/catalog/fish', 'Рыба'], ['/catalog/spices', 'Специи'], ['/category/flour', 'Мука'],
    ])
    const fishLink = navLinks(navigation)[2]
    assert.equal(findVNode(fishLink, node => node.type === 'CatalogNodeAvatar').props.node.image, '/fish.png')
    assert.equal(findVNode(top, node => hasClass(node, 'app-header__brand')).props.href, '/')
    assert.equal(findVNode(top, node => node.type === 'CitySelector').props.compact, '')
    assert.ok(findVNode(tree, node => node.type === 'OrderCartStrip'))

    h.props.categories = []
    assert.deepEqual(navLinks(h.render()).map(textOf), ['Начало', 'Каталог'])
    h.props.categories = [{ id: 90, name: 'Добавленная категория' }]
    assert.deepEqual(navLinks(h.render()).map(link => link.props.href), ['/', '/g', '/category/90'])
})

test('current navigation ignores queries and trailing slashes for relative and absolute URLs', t => {
    const h = harness(t, {
        categories: [fish],
        pageProps: { publicCategoryUrls: { 25: 'https://shop.example/catalog/fish/?source=menu' } },
        routeUrls: { home: 'https://shop.example/', 'public.goods.index': 'https://shop.example/g' },
    })
    for (const [url, selected] of [
        ['/?ref=home', 'Начало'],
        ['https://shop.example/g/?search=рыба', 'Каталог'],
        ['/catalog/fish/?page=2#products', 'Рыба'],
        ['https://shop.example/catalog/fish?sort=name', 'Рыба'],
        ['/g/some-product', null],
    ]) {
        h.page.url = url
        const links = navLinks(h.render())
        assert.deepEqual(links.filter(link => link.props['aria-current'] === 'page').map(textOf), selected ? [selected] : [])
        assert.deepEqual(links.filter(link => hasClass(link, 'app-header__nav-link--active')).map(textOf), selected ? [selected] : [])
    }
})

test('guest account links follow login and registration flags and route fallbacks', t => {
    const h = harness(t)
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__login')), null)
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__register')), null)
    h.page.props.canLogin = true
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__login')).props.href, '/login')
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__register')), null)
    h.page.props.canRegister = true
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__register')).props.href, '/register')
    h.page.props.canLogin = false
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__login')), null)
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__profile')), null)

    const configured = harness(t, {
        pageProps: { canLogin: true, canRegister: true, ziggy: { routes: { login: {}, register: {} } } },
        routeUrls: { login: '/customer-login', register: '/customer-register' },
    })
    assert.equal(findVNode(configured.render(), node => hasClass(node, 'app-header__login')).props.href, '/customer-login')
    assert.equal(findVNode(configured.render(), node => hasClass(node, 'app-header__register')).props.href, '/customer-register')
})

test('authenticated account uses available profile routes and logout closes the menu with a POST', t => {
    const h = harness(t, {
        pageProps: { auth: { user: { name: 'Анна' } }, canLogin: true, canRegister: true, ziggy: { routes: { 'customer.profile.edit': {}, 'profile.show': {}, dashboard: {}, logout: {} } } },
    })
    const tree = h.render()
    assert.equal(findVNode(tree, node => hasClass(node, 'app-header__login')), null)
    assert.equal(findVNode(tree, node => hasClass(node, 'app-header__register')), null)
    const menu = findVNode(tree, node => node.type === 'v-menu')
    menu.props['onUpdate:modelValue'](true)
    assert.equal(h.api.accountMenu.value, true)
    const profile = findVNode(h.render(), node => hasClass(node, 'app-header__profile'))
    assert.equal(profile.props.href, '/dashboard/profile')
    profile.props.onClick()
    assert.equal(h.api.accountMenu.value, false)
    delete h.page.props.ziggy.routes['customer.profile.edit']
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__profile')).props.href, '/user/profile')
    delete h.page.props.ziggy.routes['profile.show']
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__profile')).props.href, '/dashboard')
    delete h.page.props.ziggy.routes.dashboard
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__profile')).props.href, '/dashboard')

    h.api.accountMenu.value = true
    findVNode(h.render(), node => node.props?.title === 'Выйти').props.onClick()
    assert.equal(h.api.accountMenu.value, false)
    assert.deepEqual(h.requests, [['/logout', {}, { preserveScroll: true }]])
    delete h.page.props.ziggy.routes.logout
    findVNode(h.render(), node => node.props?.title === 'Выйти').props.onClick()
    assert.equal(h.requests.length, 1)
})
