import assert from 'node:assert/strict'
import { test } from 'node:test'
import { catalogTree, descendantIds, restoredCatalogTab } from '../../resources/js/Components/Catalog/tree.js'

const nodes = [
    { id: 1, parent_id: null, name: 'Сырьё', is_published: false, sort_order: 0 },
    { id: 2, parent_id: 1, name: 'Зерно', is_published: true, sort_order: 0 },
    { id: 3, parent_id: 2, name: 'Пшеница', is_published: false, sort_order: 0 },
    { id: 4, parent_id: null, name: 'Прочее', is_published: true, sort_order: 10 },
]

test('search reveals matching nested goods and preserves their ancestry through collapsed parents', () => {
    const rows = catalogTree(nodes, { search: 'ПШЕНИЦА' })
    assert.deepEqual(rows.map(row => row.id), [1, 2, 3])
    assert.deepEqual(rows.map(row => row.depth), [0, 1, 2])
    assert.equal(rows[0].context, true)
    assert.equal(rows[2].context, false)
})

test('publication filtering keeps draft ancestors as context and excludes unrelated records', () => {
    const rows = catalogTree(nodes, { publication: 'published' })
    assert.deepEqual(rows.map(row => row.id), [1, 2, 4])
    assert.equal(rows[0].context, true)
})

test('normal tree respects expansion and numeric ordering', () => {
    assert.deepEqual(catalogTree(nodes).map(row => row.id), [1, 4])
    assert.deepEqual(catalogTree(nodes, { expanded: new Set([1, 2]) }).map(row => row.id), [1, 2, 3, 4])
})

test('moving excludes all descendants even when malformed cycles are encountered', () => {
    assert.deepEqual([...descendantIds(nodes, 1)], [1, 2, 3])
    assert.deepEqual([...descendantIds([{ id: 1, parent_id: 2 }, { id: 2, parent_id: 1 }], 1)], [1, 2])
})

test('previous workspace tabs migrate to new three-tab structure', () => {
    for (const old of ['categories', 'categories_products', 'goods', null]) assert.equal(restoredCatalogTab(old), 'goods')
    for (const old of ['services', 'commodities']) assert.equal(restoredCatalogTab(old), 'purchased')
    assert.equal(restoredCatalogTab('components'), 'components')
})
