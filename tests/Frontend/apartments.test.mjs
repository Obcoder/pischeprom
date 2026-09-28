import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as apartments from '../../resources/js/utils/buildingApartments.js'

function harness(t, filename, initialProps, environmentOverrides = {}) {
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'apartments-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'apartments-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    const request = method => (url, body) => new Promise((resolve, reject) => {
        requests.push({ method, url, body, resolve: data => resolve({ data }), reject })
    })
    const env = {
        ...Vue, ...apartments,
        axios: { get: request('GET'), post: request('POST'), put: request('PUT'), delete: request('DELETE') },
        window: { confirm: () => true },
        onMounted: () => {}, useHead: () => {}, route: () => '',
        ...environmentOverrides,
    }
    const script = compiled.content.replace(/^import (.+?) from ['"].*['"];?$/gm, (_, imports) => {
        const names = imports.startsWith('{') ? imports.slice(1, -1).split(',').map(name => name.trim()) : [imports]
        for (const name of names) if (!(name in env)) env[name] = {}
        return ''
    }).replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(env)
    const props = Vue.reactive(initialProps)
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { api, props, requests, emitted }
}

const selector = 'resources/js/Components/Geography/Buildings/ApartmentSelector.vue'

test('apartment CRUD selects new entries, retains selection on edit and clears a deleted selection', async t => {
    const { api, props, requests, emitted } = harness(t, selector, { building: { id: 8, apartments: [] }, modelValue: null, selectable: true })
    api.form.number = ' 12Б '
    api.form.type = 'office'
    const creating = api.save()
    assert.deepEqual(requests[0].body, { number: '12Б', type: 'office' })
    assert.equal(requests[0].url, '/api/buildings/8/apartments')
    requests[0].resolve({ id: 3, building_id: 8, number: '12Б', type: 'office' })
    await creating
    assert.deepEqual(emitted[0], ['update:modelValue', 3])
    props.modelValue = 3
    api.resetForm(api.apartments.value[0])
    api.form.number = '14'
    const editing = api.save()
    assert.equal(requests[1].method, 'PUT')
    assert.equal(requests[1].url, '/api/buildings/8/apartments/3')
    requests[1].resolve({ id: 3, building_id: 8, number: '14', type: 'office' })
    await editing
    assert.equal(api.apartments.value[0].number, '14')
    const deleting = api.remove(api.apartments.value[0])
    assert.equal(requests[2].method, 'DELETE')
    requests[2].resolve({})
    await deleting
    assert.deepEqual(api.apartments.value, [])
    assert.ok(emitted.some(([name, value]) => name === 'update:modelValue' && value === null))
})

test('a rejected apartment mutation keeps the existing list and number draft', async t => {
    const { api, requests, emitted } = harness(t, selector, { building: { id: 8, apartments: [] }, selectable: true })
    api.form.number = '12'
    const saving = api.save()
    requests[0].reject({ response: { data: { errors: { number: ['Номер уже существует.'] } } } })
    await saving
    assert.equal(api.form.number, '12')
    assert.deepEqual(api.errors.value.number, ['Номер уже существует.'])
    assert.deepEqual(api.apartments.value, [])
    assert.deepEqual(emitted, [])
})

test('late apartment responses from another building cannot replace the active choices', async t => {
    const { api, props, requests } = harness(t, selector, { building: { id: 1 }, selectable: true })
    props.building = { id: 2 }
    await Vue.nextTick()
    requests[1].resolve([{ id: 20, building_id: 2, number: '20' }])
    await Vue.nextTick()
    requests[0].resolve([{ id: 10, building_id: 1, number: '10' }])
    await Vue.nextTick()
    assert.equal(api.apartments.value[0].building_id, 2)
})

test('order editing preserves apartment assignments and omits unselected building assignments', t => {
    const { api } = harness(t, 'resources/js/Pages/Ameise/Orders/Show.vue', { orderId: 9, permissions: {} })
    api.fillForm({ buildings: [
        { id: 1, apartment: { id: 3, number: '3' } },
        { id: 2, apartment_id: 4, apartments: [{ id: 4, number: '4' }] },
    ], items: [] })
    assert.deepEqual(api.form.building_apartments, { 1: 3, 2: 4 })
    api.form.building_ids = [2]
    assert.deepEqual(api.contentPayload().building_apartments, { 2: 4 })
    api.form.building_apartments[2] = null
    assert.deepEqual(api.contentPayload().building_apartments, { 2: null })
})

test('legacy building addresses remain usable without apartment data', () => {
    assert.equal(apartments.buildingApartmentLabel({ address: 'Мира, 1' }), '')
    assert.equal(apartments.buildingApartmentLabel({ pivot: { apartment_id: 2 }, apartments: [{ id: 2, number: '7А', type: 'premise' }] }), 'пом. 7А')
})

test('a building deletion conflict is shown without removing the attached unit address', async t => {
    const building = { id: 8, address: 'Мира, 10' }
    const { api, props, requests, emitted } = harness(t, 'resources/js/Components/Unit/UnitBuildingsTab.vue', { unit: { id: 3, buildings: [building] }, dict: {} })
    const deleting = api.deleteBuilding(building)
    requests[0].reject({ response: { status: 409, data: { message: 'Помещение используется в заказе.' } } })
    await deleting
    assert.equal(api.relationError.value, 'Помещение используется в заказе.')
    assert.equal(api.deletingId.value, null)
    assert.equal(props.unit.buildings.length, 1)
    assert.deepEqual(emitted, [])
})
