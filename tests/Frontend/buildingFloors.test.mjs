import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as floors from '../../resources/js/utils/buildingFloors.js'
import * as apartments from '../../resources/js/utils/buildingApartments.js'

const manager = 'resources/js/Components/Geography/Buildings/BuildingStructureManager.vue'
const workspace = 'resources/js/Components/Geography/Buildings/BuildingsPage.vue'
function harness(t, filename, initialProps = {}) {
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'building-floors-test' })
    assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename, id: 'building-floors-test', compilerOptions: { bindingMetadata: compiled.bindings } }).errors, [])
    const requests = [], emitted = []
    const request = method => (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))
    const env = { ...Vue, ...floors, ...apartments, axios: { get: request('GET'), post: request('POST'), put: request('PUT'), patch: request('PATCH'), delete: request('DELETE') }, window: { confirm: () => true }, onMounted: () => {}, route: value => value, BuildingFloorPlan: {}, BuildingStructureManager: {} }
    const content = compiled.content.replace(/^import (.+?) from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${content}}`)(env), scope = Vue.effectScope(), props = Vue.reactive(initialProps)
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { api, props, requests, emitted, env }
}
const tick = async () => { await Vue.nextTick(); await Promise.resolve(); await Vue.nextTick() }
const structure = () => ({ id: 8, address: 'Мира, 8', floors: [{ id: 10, number: 1 }, { id: 20, number: 2 }], apartments: [{ id: 50, number: '12А', type: 'office', building_floor_id: 10, notes: 'Вход со двора', entities: [{ id: 7, name: 'Entity 7' }] }], unassigned_entities: [] })

test('floor plan orders underground and upper floors and keeps legacy rooms in a separate group', () => {
    const groups = floors.floorGroups({ floors: [{ id: 1, number: -1 }, { id: 2, number: 0 }, { id: 3, number: 2 }], apartments: [{ id: 1, number: '10', building_floor_id: 3 }, { id: 2, number: '2', building_floor_id: 3 }, { id: 3, number: '01А', building_floor_id: null }] })
    assert.deepEqual(groups.map(g => g.id), [3, 2, 1, null])
    assert.deepEqual(groups[0].rooms.map(r => r.number), ['2', '10'])
    assert.equal(groups[3].label, 'Без этажа')
    assert.equal(floors.floorLabel({ number: 0, name: 'Цоколь' }), '0 этаж · Цоколь')
})

test('editing a room submits the chosen floor and preserves draft and links after rejected mutation', async t => {
    const { api, requests } = harness(t, manager, { buildingId: 8 })
    requests[0].resolve(structure()); await tick()
    api.editRoom(api.building.value.apartments[0]); api.roomForm.building_floor_id = 20
    const saving = api.saveRoom()
    assert.deepEqual(requests[1].body, { number: '12А', type: 'office', building_floor_id: 20, notes: 'Вход со двора' })
    requests[1].reject({ response: { data: { message: 'Этаж удалён' } } }); await saving
    assert.equal(api.roomForm.building_floor_id, 20)
    assert.equal(api.currentRoom.value.entities[0].id, 7)
    assert.equal(api.roomEditing.value, true)
    assert.match(api.error.value, /Этаж удалён/)
})

test('Entity attach targets the selected room, refreshes structure, and never attaches to the whole building', async t => {
    const { api, requests, emitted } = harness(t, manager, { buildingId: 8 })
    requests[0].resolve(structure()); await tick(); api.editRoom(api.building.value.apartments[0])
    const saving = api.attach({ id: 9 })
    assert.equal(requests[1].url, '/api/buildings/8/apartments/50/entities')
    assert.deepEqual(requests[1].body, { entity_id: 9, replace_building_link: false })
    requests[1].resolve({}); await tick()
    const updated = structure(); updated.apartments[0].entities.push({ id: 9, name: 'Entity 9' })
    requests[2].resolve(updated); await saving
    assert.equal(api.currentRoom.value.entities.length, 2)
    assert.ok(emitted.some(([event]) => event === 'changed'))
})

test('legacy refinement removes the building-only link only through an explicit action', async t => {
    const { api, requests } = harness(t, manager, { buildingId: 8 })
    requests[0].resolve(structure()); await tick()
    const saving = api.attach({ id: 9 }, 50, true)
    assert.deepEqual(requests[1].body, { entity_id: 9, replace_building_link: true })
    requests[1].resolve({}); await tick(); requests[2].resolve(structure()); await saving
})

test('floor creation supports zero and cancellation preserves an unsaved room draft', async t => {
    const { api, requests, emitted, env } = harness(t, manager, { buildingId: 8 })
    requests[0].resolve(structure()); await tick(); api.editRoom(); api.roomForm.number = '15'
    env.window.confirm = () => false; api.close(); api.editFloor()
    assert.equal(emitted.length, 0); assert.equal(api.roomForm.number, '15'); assert.equal(api.floorEditing.value, false)
    env.window.confirm = () => true; api.editFloor(); api.floorForm.number = 0
    const saving = api.saveFloor(); assert.equal(requests[1].body.number, 0)
    requests[1].resolve({ id: 30 }); await tick(); requests[2].resolve(structure()); await saving
    assert.equal(api.floorEditing.value, false)
})

test('refresh responses cannot replace a newer structure or a closed editor', async t => {
    const { api, requests } = harness(t, manager, { buildingId: 8 })
    const refresh = api.load(); requests[1].resolve(structure()); await refresh
    requests[0].resolve({ id: 8, address: 'Устаревший ответ', apartments: [], floors: [] }); await tick()
    assert.equal(api.building.value.address, 'Мира, 8')
})

test('registry uses server pagination and whole-filter statistics and rejects stale responses', async t => {
    const { api, requests } = harness(t, workspace)
    api.filters.search = 'Мира'; await tick()
    const first = api.loadBuildings(2), second = api.loadBuildings(1)
    const one = { data: [structure()], summary: { buildings: 150, floors: 500, apartments: 3000 }, meta: { current_page: 1, last_page: 3, total: 150, per_page: 50 } }
    requests[1].resolve(one); await second
    requests[0].resolve({ ...one, data: [], meta: { ...one.meta, current_page: 2 } }); await first
    assert.equal(api.meta.current_page, 1); assert.equal(api.buildings.value.length, 1); assert.equal(api.summary.value.apartments, 3000)
    assert.equal(requests[0].body.params.structure, 1); assert.equal(requests[0].body.params.search, 'Мира')
})

test('floor plan buttons expose room types and Entity links in accessible labels', t => {
    harness(t, 'resources/js/Components/Geography/Buildings/BuildingFloorPlan.vue', { building: structure() })
    assert.match(floors.roomTitle(structure().apartments[0]), /офис 12А.*Entity 7.*Вход со двора/)
})

test('clearing search restores the first page immediately and ignores the previous search response', async t => {
    const { api, requests } = harness(t, workspace)
    api.filters.city_id = 4; await tick()
    requests[0].resolve({ data: [], summary: {}, meta: { current_page: 1, last_page: 1, total: 0, per_page: 50 } }); await tick()
    api.filters.search = 'Мира'; await tick()
    const searching = api.loadBuildings(2)
    api.clearSearch(); await tick()
    assert.equal(api.filters.search, '')
    assert.equal(requests[2].body.params.search, undefined)
    assert.equal(requests[2].body.params.page, 1)
    assert.equal(requests[2].body.params.city_id, 4)
    const restored = { data: [structure(), { id: 9, address: 'Другое здание' }], summary: { buildings: 2 }, meta: { current_page: 1, last_page: 1, total: 2, per_page: 50 } }
    requests[2].resolve(restored); await tick()
    requests[1].resolve({ ...restored, data: [structure()], summary: { buildings: 1 }, meta: { ...restored.meta, total: 1, current_page: 2 } }); await searching
    assert.equal(api.buildings.value.length, 2)
    assert.equal(api.meta.current_page, 1)
    assert.equal(api.summary.value.buildings, 2)
})
