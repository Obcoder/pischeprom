import assert from 'node:assert/strict'
import { test } from 'node:test'
import { buildCatalogView } from '../../resources/js/Components/Catalog/presentation.js'

const levels = [
    { id: 1, name: 'Направление', display_mode: 'tabs', is_domain: true, sort_order: 0 },
    { id: 2, name: 'Категория', display_mode: 'tabs', is_domain: false, sort_order: 1 },
    { id: 3, name: 'Группа', display_mode: 'tree', is_domain: false, sort_order: 2 },
    { id: 4, name: 'Сорт', display_mode: 'tabs', is_domain: false, sort_order: 3 },
    { id: 5, name: 'Объект', display_mode: 'list', is_domain: false, sort_order: 4 },
]
const n = (id, parent_id, level_id, name, extras = {}) => ({
    id, parent_id, level_id, name, sort_order: id, is_published: true, is_featured: false, ...extras,
})
const nodes = [
    n(1, null, 1, 'Товары'),
    n(2, null, 1, 'Закупаемое'),
    n(3, null, 1, 'Пустое направление'),
    n(10, 1, 2, 'Зерновые'),
    n(11, 1, 2, 'Пока без подуровней'),
    n(20, 10, 3, 'Пшеница', { is_published: false }),
    n(30, 20, 4, 'Твёрдая'),
    n(40, 30, 5, 'Мука', { entity_type: 'good', is_featured: true }),
    n(41, 1, 3, 'Лист уровня дерева', { entity_type: 'product' }),
    n(42, 1, 5, 'Объект с подразделами'),
    n(43, 42, null, 'Без уровня внутри объекта'),
    n(50, 2, 3, 'Услуги'),
    n(51, 50, 900, 'Неизвестный уровень'),
    n(52, 51, 5, 'Доставка', { is_published: false }),
    n(53, 2, 3, 'Сервис без подуровней'),
    n(60, null, null, 'Без направления'),
    n(61, 60, 5, 'Самостоятельный объект'),
    n(62, null, null, 'Неклассифицированный лист'),
]
const ids = items => items.map(item => item.id)

test('All keeps uneven domains, skipped levels and unclassified leaves reachable without selecting defaults', () => {
    const view = buildCatalogView(nodes, levels)
    assert.deepEqual(ids(view.domainTabs), [null, 1, 2, 3, 'unassigned'])
    assert.deepEqual(ids(view.items), [11, 40, 41, 43, 52, 53, 61, 62])
    assert.deepEqual(view.selections, { 2: null, 4: null })
    assert.equal(view.domainId, null)
    assert.equal(view.selectedBranchId, null)
    assert.equal(view.scopeNodeId, null)
    assert.deepEqual(view.counts, {
        total: 8, domain: 8, scoped: 8, matched: 8, list: 8, branches: 5,
        published: 7, featured: 1, domainNodesCount: 18,
    })
    assert.equal(view.domainTabs.find(tab => tab.id === 1).count, 4)
    assert.equal(view.domainTabs.find(tab => tab.id === 2).count, 2)
    assert.equal(view.domainTabs.find(tab => tab.id === 3).count, 0)
    assert.equal(view.domainTabs.find(tab => tab.id === 'unassigned').count, 2)
})

test('structural leaves appear right even when their configured level is tabs or tree', () => {
    const view = buildCatalogView(nodes, levels, { domainId: 1 })
    assert.deepEqual(ids(view.items), [11, 40, 41, 43])
    assert.equal(view.items.find(item => item.id === 11).level_name, 'Категория')
    assert.equal(view.items.find(item => item.id === 41).level_name, 'Группа')
    assert.equal(view.items.find(item => item.id === 43).level_name, 'Без уровня')
    assert.deepEqual(ids(view.tabRows[0].items), [10])
    assert.ok(view.items.every(item => !item.hasChildren))
    assert.deepEqual(ids(view.treeRows), [20, 42])
})

test('toolbar selections cascade through interspersed tree levels and clear selections that have become leaves', () => {
    const view = buildCatalogView(nodes, levels, { domainId: 1, selections: { 2: 10, 4: 30 }, selectedBranchId: 50 })
    assert.deepEqual(ids(view.items), [40])
    assert.deepEqual(view.tabRows.map(row => row.levelId), [2, 4])
    assert.deepEqual(ids(view.tabRows[1].items), [30])
    assert.deepEqual(view.selections, { 2: 10, 4: 30 })
    assert.equal(view.selectedBranchId, null)
    assert.equal(view.items[0].path_label, 'Товары / Зерновые / Пшеница / Твёрдая')
    assert.deepEqual(ids(view.items[0].ancestors), [1, 10, 20, 30])

    const leaf = buildCatalogView(nodes, levels, { domainId: 1, selections: { 2: 11, 4: 30 } })
    assert.deepEqual(ids(leaf.items), [11, 40, 41, 43])
    assert.deepEqual(leaf.selections, { 2: null, 4: null })
    assert.deepEqual(leaf.tabRows.map(row => row.levelId), [2, 4])
})

test('a branch can skip all configured tabs and retain unknown intermediary levels', () => {
    const view = buildCatalogView(nodes, levels, { domainId: 2, expanded: new Set([50]) })
    assert.deepEqual(view.tabRows, [])
    assert.deepEqual(ids(view.treeRows), [50, 51])
    assert.deepEqual(view.treeRows.map(row => row.depth), [0, 1])
    assert.equal(view.treeRows[0].hasTreeChildren, true)
    assert.equal(view.treeRows[0].descendantCount, 2)
    assert.equal(view.treeRows[0].count, 1)
    assert.equal(view.treeRows[1].hasTreeChildren, false)
    assert.deepEqual(ids(view.items), [52, 53])
})

test('list-mode objects with children become navigable branches and preserve every descendant', () => {
    const view = buildCatalogView(nodes, levels, { domainId: 1, selectedBranchId: 42 })
    assert.deepEqual(ids(view.items), [43])
    assert.deepEqual(ids(view.treeRows), [20, 42])
    const branch = view.treeRows.find(row => row.id === 42)
    assert.equal(branch.hasChildren, true)
    assert.equal(branch.childCount, 1)
    assert.equal(branch.selected, true)
    assert.equal(view.counts.domain, 4)
    assert.equal(view.counts.scoped, 1)
})

test('domain levels configured as trees remain in the tree, including empty domain placeholders', () => {
    const treeLevels = levels.map(level => level.id === 1 ? { ...level, display_mode: 'tree' } : level)
    const view = buildCatalogView(nodes, treeLevels, { selectedBranchId: 2 })
    assert.deepEqual(ids(view.domainTabs), [null, 'unassigned'])
    assert.deepEqual(ids(view.treeRows), [1, 2, 3, 60])
    assert.deepEqual(ids(view.items), [52, 53])
    assert.equal(view.treeRows.find(row => row.id === 3).count, 0)
    assert.ok(!ids(view.items).includes(3))
    const expanded = buildCatalogView(nodes, treeLevels, { expanded: new Set([2]), selectedBranchId: 51 })
    assert.deepEqual(ids(expanded.items), [52])
    assert.ok(expanded.treeRows.some(row => row.id === 51))
})

test('publication filters match leaves and retain draft branch context with accurate descendant counts', () => {
    const view = buildCatalogView(nodes, levels, { publication: 'published' })
    assert.deepEqual(ids(view.items), [11, 40, 41, 43, 53, 61, 62])
    const wheat = view.treeRows.find(row => row.id === 20)
    assert.equal(wheat.context, true)
    assert.equal(wheat.count, 1)
    assert.equal(wheat.matchedCount, 1)
    assert.equal(wheat.expanded, true)
    const draft = buildCatalogView(nodes, levels, { publication: 'draft' })
    assert.deepEqual(ids(draft.items), [52])
    assert.equal(draft.counts.total, 8)
    assert.equal(draft.counts.matched, 1)
})

test('search finds terminals and descendants of matching branches through collapsed mixed-mode ancestry', () => {
    const direct = buildCatalogView(nodes, levels, { search: 'ДОСТАВКА' })
    assert.deepEqual(ids(direct.items), [52])
    assert.deepEqual(ids(direct.treeRows), [50, 51])
    assert.ok(direct.treeRows.every(row => row.context && row.expanded))
    const ancestor = buildCatalogView(nodes, levels, { search: 'пшеница', publication: 'featured' })
    assert.deepEqual(ids(ancestor.items), [40])
    assert.equal(ancestor.treeRows.find(row => row.id === 20).matchedCount, 1)
    assert.equal(buildCatalogView(nodes, levels, { search: null }).items.length, 8)
})

test('unassigned domain scope includes missing-parent records and unknown or absent levels', () => {
    const extended = [...nodes, n(70, 999, 999, 'Осиротевший объект')]
    const view = buildCatalogView(extended, levels, { domainId: 'unassigned', selections: { 2: 10 } })
    assert.deepEqual(ids(view.items), [61, 62, 70])
    assert.deepEqual(ids(view.treeRows), [60])
    assert.deepEqual(view.selections, { 2: null, 4: null })
    assert.equal(view.counts.domainNodesCount, 4)
    assert.equal(view.items.at(-1).path_label, '')
})

test('tab order need not equal ancestry: an earlier selection remains reachable through a later-level ancestor', () => {
    const reversed = [n(1, null, 1, 'Домен'), n(2, 1, 4, 'Сорт наверху'), n(3, 2, 2, 'Категория ниже'), n(4, 3, 5, 'Объект')]
    const view = buildCatalogView(reversed, levels, { selections: { 2: 3, 4: 2 } })
    assert.deepEqual(ids(view.items), [4])
    assert.deepEqual(ids(view.tabRows[1].items), [2])
    assert.deepEqual(view.selections, { 2: 3, 4: 2 })
    assert.equal(view.tabRows[1].items[0].count, 1)
})

test('cycle repair is iterative, retains all records and never mutates server data or selection sets', () => {
    const malformed = [n(1, 2, null, 'Первый'), n(2, 1, null, 'Второй'), n(3, 3, null, 'Сам себе родитель')]
    const before = structuredClone(malformed)
    const expanded = new Set([1, 2, 3])
    const view = buildCatalogView(malformed, [], { expanded })
    assert.deepEqual(new Set([...ids(view.treeRows), ...ids(view.items)]), new Set([1, 2, 3]))
    assert.deepEqual(malformed, before)
    assert.deepEqual(expanded, new Set([1, 2, 3]))
    assert.equal(view.counts.total, 2)
})

test('very deep branches do not overflow the JS stack and breadcrumb output retains skipped levels', () => {
    const deep = Array.from({ length: 12000 }, (_, index) => n(index + 1, index || null, null, `Уровень ${index + 1}`))
    const view = buildCatalogView(deep, [], { search: 'Уровень 12000' })
    assert.equal(view.items.length, 1)
    assert.equal(view.items[0].id, 12000)
    assert.equal(view.items[0].ancestors.length, 11999)
    assert.equal(view.treeRows.length, 11999)
    assert.equal(view.treeRows[0].descendantCount, 11999)
})

test('ordering is deterministic by sort order, Russian name and ID; string selections normalize to server IDs', () => {
    const unordered = [n(4, null, null, 'Б', { sort_order: 1 }), n(3, null, null, 'А', { sort_order: 1 }), n(2, null, null, 'А', { sort_order: 1 }), n(1, null, null, 'Я', { sort_order: 0 })]
    assert.deepEqual(ids(buildCatalogView(unordered, []).items), [1, 2, 3, 4])
    const selected = buildCatalogView(nodes, levels, { domainId: '1', selections: { 2: '10', 4: '30' }, selectedBranchId: '20' })
    assert.equal(selected.domainId, 1)
    assert.deepEqual(selected.selections, { 2: 10, 4: 30 })
    assert.equal(selected.selectedBranchId, 20)
    assert.equal(selected.scopeNodeId, 30)
    assert.deepEqual(ids(selected.items), [40])
})

test('creation scope is the deepest selected classifier, including empty domains', () => {
    const view = buildCatalogView(nodes, levels, { domainId: 1, selections: { 2: 10, 4: 30 }, selectedBranchId: 20 })
    assert.equal(view.scopeNodeId, 30)
    assert.deepEqual(ids(view.items), [40])
    assert.equal(buildCatalogView(nodes, levels, { domainId: 3 }).scopeNodeId, 3)
    assert.equal(buildCatalogView(nodes, levels, { domainId: 'unassigned' }).scopeNodeId, null)
})

test('a tabs-configured terminal level appears exclusively in the right table, including former tab selections', () => {
    const flat = [n(1, null, 1, 'Домен'), n(10, 1, 2, 'Категория без детей'), n(20, 1, 4, 'Сорт без детей')]
    const view = buildCatalogView(flat, levels, { domainId: 1, selections: { 2: 10, 4: 20 }, selectedBranchId: 20 })
    assert.deepEqual(view.tabRows, [])
    assert.deepEqual(view.treeRows, [])
    assert.deepEqual(ids(view.items), [10, 20])
    assert.deepEqual(view.selections, { 2: null, 4: null })
    assert.equal(view.selectedBranchId, null)
    assert.equal(view.scopeNodeId, 1)
    assert.deepEqual(ids(view.domainTabs), [null, 1])
})
