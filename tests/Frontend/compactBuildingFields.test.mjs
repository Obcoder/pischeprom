import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'

function harness(t, overrides = {}) {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Geography/Buildings/CompactBuildingFields.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'compact-building-fields' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'compact-building-fields', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const env = { ...Vue, useId: () => 'test-building' }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(env)
    const props = Vue.reactive({
        modelValue: { candidate_id: 9, city_id: null, address: 'Мира, 1', building_type_id: null, postcode: '', delivery_apartment_type: 'apartment', delivery_apartment_number: '' },
        search: '', cities: [{ id: 1, label: 'Москва' }, { id: 2, label: 'Московский' }],
        cityLoading: false, disabled: false, buildingTypes: [], errors: {}, ...overrides,
    })
    const emitted = []
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, {
        expose() {},
        emit(name, value) {
            emitted.push([name, value])
            if (name === 'update:modelValue') props.modelValue = value
            if (name === 'update:search') props.search = value
        },
    }))
    t.after(() => scope.stop())
    return { api, props, emitted }
}

function key(api, value, overrides = {}) {
    const state = { prevented: false, stopped: false }
    api.cityKeydown({ key: value, preventDefault() { state.prevented = true }, stopPropagation() { state.stopped = true }, ...overrides })
    return state
}

test('city selection works with arrows and Enter and preserves the building and apartment draft', t => {
    const { api, props } = harness(t)
    props.modelValue.delivery_apartment_number = '12Б'
    assert.equal(key(api, 'ArrowUp').prevented, true)
    assert.equal(api.activeCityIndex.value, 1)
    assert.equal(api.cityOpen.value, true)
    assert.equal(key(api, 'ArrowDown').prevented, true)
    assert.equal(api.activeCityIndex.value, 0)
    assert.equal(key(api, 'Enter').prevented, true)
    assert.equal(props.modelValue.city_id, 1)
    assert.equal(props.modelValue.candidate_id, 9)
    assert.equal(props.modelValue.address, 'Мира, 1')
    assert.equal(props.modelValue.delivery_apartment_number, '12Б')
    assert.equal(api.cityText.value, 'Москва')
    assert.equal(api.cityOpen.value, false)
    assert.equal(api.activeCityId.value, undefined)
})

test('remote searches cannot erase the chosen city label and editing it clears its id', async t => {
    const { api, props } = harness(t)
    api.selectCity(props.cities[0])
    props.cities = [{ id: 3, label: 'Казань' }]
    props.search = 'Каз'
    await Vue.nextTick()
    assert.equal(props.modelValue.city_id, 1)
    assert.equal(api.cityText.value, 'Москва')
    api.searchCities({ target: { value: 'Казань' } })
    assert.equal(props.modelValue.city_id, null)
    assert.equal(api.cityText.value, 'Казань')
    assert.equal(key(api, 'Enter').prevented, true)
    assert.equal(props.modelValue.city_id, null)
    key(api, 'ArrowDown')
    key(api, 'Enter')
    assert.equal(props.modelValue.city_id, 3)
})

test('loading and disabled fields cannot select stale cities or alter the address', async t => {
    const { api, props, emitted } = harness(t)
    key(api, 'ArrowDown')
    props.cityLoading = true
    await Vue.nextTick()
    key(api, 'Enter')
    api.selectCity(props.cities[0])
    assert.equal(props.modelValue.city_id, null)
    assert.equal(api.activeCityId.value, undefined)
    props.disabled = true
    props.cityLoading = false
    await Vue.nextTick()
    key(api, 'ArrowDown')
    api.searchCities({ target: { value: 'Казань' } })
    api.selectCity(props.cities[0])
    api.updateField('address', 'Другой адрес')
    assert.equal(api.cityOpen.value, false)
    assert.equal(props.modelValue.address, 'Мира, 1')
    assert.equal(props.search, '')
    assert.deepEqual(emitted, [])
})

test('Escape dismisses only the city menu, Tab leaves it, and composition cannot select a city', t => {
    const { api, props } = harness(t)
    key(api, 'ArrowDown')
    key(api, 'Enter', { isComposing: true })
    assert.equal(props.modelValue.city_id, null)
    assert.equal(api.cityOpen.value, true)
    assert.deepEqual(key(api, 'Escape'), { prevented: true, stopped: true })
    assert.equal(api.cityOpen.value, false)
    assert.deepEqual(key(api, 'Escape'), { prevented: false, stopped: false })
    key(api, 'ArrowDown')
    assert.deepEqual(key(api, 'Tab'), { prevented: false, stopped: false })
    assert.equal(api.cityOpen.value, false)
})
