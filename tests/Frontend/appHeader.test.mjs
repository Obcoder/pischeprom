import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
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
    const mounted = []
    const unmounted = []
    const events = new Map()
    const urls = { home: '/', 'public.goods.index': '/g', login: '/login', register: '/register', 'customer.profile.edit': '/dashboard/profile', 'profile.show': '/user/profile', dashboard: '/dashboard', logout: '/logout', ...routeUrls }
    const environment = {
        ...Vue, Link: 'Link', CitySelector: 'CitySelector', OrderCartStrip: 'OrderCartStrip', CatalogNodeAvatar: 'CatalogNodeAvatar',
        onMounted: callback => mounted.push(callback), onBeforeUnmount: callback => unmounted.push(callback),
        document: { addEventListener: (name, callback) => events.set(name, callback), removeEventListener: name => events.delete(name) },
        usePage: () => page,
        useAppRoute: () => ({ route: (name, parameter) => name === 'category.show' ? `/category/${parameter}` : urls[name] }),
        router: { post: (...args) => requests.push(args) },
    }
    const source = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${source}}`)(environment)
    const props = Vue.reactive({ categories })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {} }))
    mounted.forEach(callback => callback())
    t.after(() => { unmounted.forEach(callback => callback()); scope.stop() })
    return { page, props, api, requests, events, render: templateRenderer(template, api, props) }
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
const categoryLinks = tree => allNodes(tree, node => hasClass(node, 'app-header__category-link'))
const fish = { id: 25, parent_id: null, name: 'Рыба', public_url: '/catalog/fish', image: '/fish.png' }

test('navigation has only home and catalog, with supplied categories and canonical links in a closed dropdown', t => {
    const h = harness(t, {
        categories: [fish, { id: 26, name: 'Специи', public_url: '/catalog/spices' }, { id: 27, name: 'Мука', public_url: '/catalog/flour' }, null, { id: 28 }, { name: 'Без идентификатора' }],
        pageProps: { publicCategoryUrls: { 25: '/wrong-source-category' } },
    })
    const tree = h.render()
    const top = findVNode(tree, node => hasClass(node, 'app-header__top'))
    const navigation = findVNode(tree, node => hasClass(node, 'app-header__navigation'))
    assert.ok(tree.children.includes(top))
    assert.ok(tree.children.includes(navigation))
    assert.equal(findVNode(top, node => node.type === 'nav'), null)
    assert.equal(findVNode(navigation, node => node.type === 'nav').props['aria-label'], 'Разделы магазина')
    assert.deepEqual(navLinks(navigation).map(link => [link.props.href, textOf(link)]), [
        ['/', 'Начало'], ['/g', 'Каталог'],
    ])
    const dropdown = findVNode(navigation, node => node.type === 'details')
    assert.ok(dropdown)
    assert.equal(dropdown.props.open, undefined)
    assert.equal(findVNode(dropdown, node => node.type === 'summary').props['aria-label'], 'Категории каталога')
    assert.deepEqual(categoryLinks(dropdown).map(link => [link.props.href, textOf(link)]), [
        ['/catalog/fish', 'Рыба'], ['/catalog/spices', 'Специи'], ['/catalog/flour', 'Мука'],
    ])
    const fishLink = categoryLinks(dropdown)[0]
    assert.equal(findVNode(fishLink, node => node.type === 'CatalogNodeAvatar').props.node.image, '/fish.png')
    assert.equal(findVNode(top, node => hasClass(node, 'app-header__brand')).props.href, '/')
    assert.equal(textOf(findVNode(top, node => node.type === 'small')), 'Маркетплейс пищевой промышленности')
    assert.equal(textOf(findVNode(top, node => node.props?.href === 'mailto:com@food-server.ru')), 'com@food-server.ru')
    assert.equal(findVNode(top, node => node.type === 'CitySelector').props.compact, '')
    assert.ok(findVNode(tree, node => node.type === 'OrderCartStrip'))

    h.props.categories = []
    assert.deepEqual(navLinks(h.render()).map(textOf), ['Начало', 'Каталог'])
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__category-dropdown')), null)
    h.props.categories = [{ id: 90, name: 'Добавленная категория', public_url: '/catalog/new' }]
    assert.deepEqual(navLinks(h.render()).map(link => link.props.href), ['/', '/g'])
    assert.deepEqual(categoryLinks(h.render()).map(link => link.props.href), ['/catalog/new'])
})

test('catalog columns follow parents, replace deeper branches and never promote orphan nodes', async t => {
    const fruit = { id: 10, name: 'Плодово-ягодное направление', public_url: '/catalog/fruit', parent_id: null }
    const berry = { id: 11, name: 'Облепиха', public_url: '/catalog/fruit/berry', parent_id: 10 }
    const frozen = { id: 12, name: 'Облепиха замороженная', public_url: '/catalog/fruit/berry/frozen', parent_id: 11 }
    const salmon = { id: 26, name: 'Лосось', public_url: '/catalog/fish/salmon', parent_id: 25 }
    const h = harness(t, { categories: [frozen, berry, fruit, fish, salmon, { id: 99, name: 'Потерянный узел', public_url: '/orphan', parent_id: 98 }] })
    const columns = () => allNodes(h.render(), node => hasClass(node, 'app-header__category-column'))
    const expand = name => findVNode(h.render(), node => node.props?.['aria-label'] === `Подразделы: ${name}`)
    assert.deepEqual(columns().map(c => categoryLinks(c).map(textOf)), [[fruit.name, fish.name]])
    await expand(fruit.name).props.onClick()
    assert.deepEqual(columns().map(c => categoryLinks(c).map(textOf)), [[fruit.name, fish.name], [berry.name]])
    assert.equal(expand(fruit.name).props['aria-expanded'], true)
    await expand(berry.name).props.onClick()
    assert.deepEqual(columns().map(c => categoryLinks(c).map(textOf)), [[fruit.name, fish.name], [berry.name], [frozen.name]])
    assert.equal(expand(frozen.name), null)
    await expand(fish.name).props.onClick()
    assert.deepEqual(columns().map(c => categoryLinks(c).map(textOf)), [[fruit.name, fish.name], [salmon.name]])
    await h.api.previousCategory(1)
    assert.equal(columns().length, 1)
    await expand(fruit.name).props.onClick()
    h.api.categoryDropdown.value = { open: true }
    categoryLinks(columns()[1])[0].props.onClick()
    assert.equal(h.api.categoryDropdown.value.open, false)
    assert.equal(columns().length, 1)
    h.props.categories = [berry, frozen]
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__category-dropdown')), null)
})

test('useful pages dropdown always exists and renders only supplied landing destinations', t => {
    const h = harness(t)
    let dropdown = findVNode(h.render(), node => hasClass(node, 'app-header__landing-dropdown'))
    assert.equal(dropdown.type, 'details')
    assert.equal(dropdown.props.open, undefined)
    assert.equal(textOf(findVNode(dropdown, node => node.type === 'summary')), 'Полезные страницы')
    assert.equal(textOf(findVNode(dropdown, node => hasClass(node, 'app-header__landing-empty'))), 'Страницы скоро появятся')
    assert.equal(findVNode(dropdown, node => node.type === 'Link'), null)

    h.page.props.headerLandings = [
        { id: 10, title: 'Контроль качества', url: '/pages/quality' },
        { id: 11, title: 'Как мы работаем', url: 'https://shop.example/pages/how-we-work' },
        null, { id: 12, title: 'Без адреса' }, { id: 13, url: '/no-title' },
    ]
    dropdown = findVNode(h.render(), node => hasClass(node, 'app-header__landing-dropdown'))
    assert.deepEqual(allNodes(dropdown, node => hasClass(node, 'app-header__landing-link')).map(link => [link.props.href, textOf(link)]), [
        ['/pages/quality', 'Контроль качества'], ['https://shop.example/pages/how-we-work', 'Как мы работаем'],
    ])
    assert.equal(findVNode(dropdown, node => hasClass(node, 'app-header__landing-empty')), null)
    h.page.url = '/pages/quality/?source=home'
    assert.equal(findVNode(h.render(), node => hasClass(node, 'app-header__landing-link--active')).props['aria-current'], 'page')
    h.page.props.headerLandings = []
    assert.equal(allNodes(h.render(), node => hasClass(node, 'app-header__landing-link')).length, 0)
    assert.ok(findVNode(h.render(), node => hasClass(node, 'app-header__landing-empty')))
})

test('landing and category dropdowns close on outside clicks, selection, Escape and route changes', async t => {
    const h = harness(t, { categories: [fish], pageProps: { headerLandings: [{ id: 10, title: 'Контроль качества', url: '/pages/quality' }] } })
    let focused = 0
    h.api.categoryDropdown.value = { open: true, contains: target => target === 'category' }
    h.api.landingDropdown.value = { open: true, contains: target => target === 'landing', querySelector: () => ({ focus: () => focused++ }) }
    h.events.get('click')({ target: 'landing' })
    assert.equal(h.api.landingDropdown.value.open, true)
    assert.equal(h.api.categoryDropdown.value.open, false)
    h.api.categoryDropdown.value.open = true
    h.events.get('click')({ target: 'removed-back-button', composedPath: () => [h.api.categoryDropdown.value] })
    assert.equal(h.api.categoryDropdown.value.open, true)
    assert.equal(h.api.landingDropdown.value.open, false)
    h.events.get('click')({ target: 'outside' })
    assert.equal(h.api.categoryDropdown.value.open, false)
    assert.equal(h.api.landingDropdown.value.open, false)

    h.api.landingDropdown.value.open = true
    findVNode(h.render(), node => hasClass(node, 'app-header__landing-link')).props.onClick()
    assert.equal(h.api.landingDropdown.value.open, false)
    h.api.landingDropdown.value.open = true
    const dropdown = findVNode(h.render(), node => hasClass(node, 'app-header__landing-dropdown'))
    dropdown.props.onKeydown({ key: 'Escape', stopPropagation() {}, preventDefault() {} })
    assert.equal(h.api.landingDropdown.value.open, false)
    assert.equal(focused, 1)

    h.api.categoryDropdown.value.open = true
    h.api.landingDropdown.value.open = true
    h.page.url = '/pages/quality'
    await Vue.nextTick()
    assert.equal(h.api.categoryDropdown.value.open, false)
    assert.equal(h.api.landingDropdown.value.open, false)
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
        const links = [...navLinks(h.render()), ...categoryLinks(h.render())]
        assert.deepEqual(links.filter(link => link.props['aria-current'] === 'page').map(textOf), selected ? [selected] : [])
        assert.deepEqual(links.filter(link => hasClass(link, 'app-header__nav-link--active') || hasClass(link, 'app-header__category-link--active')).map(textOf), selected ? [selected] : [])
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
