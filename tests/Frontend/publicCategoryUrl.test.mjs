import assert from 'node:assert/strict'
import { test } from 'node:test'
import { publicCategoryUrl } from '../../resources/js/utils/publicCategoryUrl.js'

test('category menus and quicklinks prefer the current published catalog destination by source category ID', () => {
    const urls = { 25: '/catalog/ryba' }
    assert.equal(publicCategoryUrl({ id: 25, slug: 'old-fish-name' }, urls, '/категория/old-fish-name'), '/catalog/ryba')
    assert.equal(publicCategoryUrl({ id: '25' }, urls, '/категория/25'), '/catalog/ryba')
    assert.equal(publicCategoryUrl({ id: 25, public_url: '/old/path' }, urls, '/категория/25'), '/catalog/ryba')
    assert.equal(publicCategoryUrl({ id: 30 }, urls, '/категория/30'), '/категория/30')
    assert.equal(publicCategoryUrl({ id: 25 }, {}, '/категория/25'), '/категория/25', 'A hidden or missing placement leaves the legacy category available')
    assert.equal(publicCategoryUrl({ id: 25, public_url: '/catalog/current-category' }, undefined, '/категория/25'), '/catalog/current-category')
})
