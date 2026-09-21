import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { useEntityFilters } from '../../resources/js/Composables/entities/useEntityFilters.js'

function harness(componentName = 'Entities', props = {}) {
    const requests = []
    const disposal = []
    const environment = {
        ...Vue,
        watch: () => () => {},
        onMounted: () => {},
        onBeforeUnmount: callback => disposal.push(callback),
        useEntityFilters,
        useEntityForm: () => ({ form: Vue.reactive({}) }),
        usePhoneFormatter: () => ({ formatPhones: () => '' }),
        useEntityApi: () => ({
            getList(params) {
                let resolve
                const promise = new Promise(done => { resolve = done })
                requests.push({ params, resolve })
                return promise
            },
        }),
    }
    const filename = `resources/js/Components/Dictionaries/Entities/${componentName}.vue`
    const { descriptor } = parse(readFileSync(new URL(`../../${filename}`, import.meta.url), 'utf8'), { filename })
    const compiled = compileScript(descriptor, { id: componentName })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: componentName,
        compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const script = compiled.content.replace(/^import (.+?) from ['"].*['"];?$/gm, (_, imports) => {
        const names = imports.startsWith('{')
            ? imports.slice(1, -1).split(',').map(part => part.trim().split(/\s+as\s+/).at(-1))
            : [imports]
        for (const name of names) if (!(name in environment)) environment[name] = {}
        return ''
    }).replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const state = component.setup(props, { expose() {}, emit() {} })
    return { state, requests, dispose: () => disposal.forEach(callback => callback()) }
}

const result = (items) => ({ data: items, meta: { total: items.length, page_markers: [{ page: 1, first_name: items[0]?.name }] } })

test('entity presence filters preserve explicit absence and reset to unrestricted queries', async () => {
    const { state, requests, dispose } = harness()
    Object.assign(state.filters, { has_sales: false, has_orders: true, has_avito_chats: true, has_unread_avito: false })
    const first = state.loadItems()
    assert.equal(requests[0].params.has_sales, false)
    assert.equal(requests[0].params.has_orders, true)
    assert.equal(requests[0].params.has_avito_chats, true)
    assert.equal(requests[0].params.has_unread_avito, false)
    requests[0].resolve(result([]))
    await first
    const reset = state.handleResetFilters()
    for (const key of ['has_sales', 'has_orders', 'has_avito_chats', 'has_unread_avito']) {
        assert.equal(requests[1].params[key], null)
    }
    requests[1].resolve(result([]))
    await reset
    dispose()
})

test('a slower entity response cannot overwrite newer filtered rows or pagination', async () => {
    const { state, requests, dispose } = harness()
    const old = state.loadItems()
    state.filters.has_orders = true
    const current = state.loadItems()
    requests[1].resolve(result([{ id: 2, name: 'С заказом' }]))
    await current
    requests[0].resolve(result([{ id: 1, name: 'Без заказа' }, { id: 2, name: 'С заказом' }]))
    await old
    assert.deepEqual(state.items.value.map(item => item.id), [2])
    assert.equal(state.totalItems.value, 1)
    assert.equal(state.pageMarkers.value[0].first_name, 'С заказом')
    const pending = state.loadItems()
    dispose()
    requests[2].resolve(result([]))
    await pending
    await state.loadItems()
    assert.equal(requests.length, 3)
    assert.equal(state.items.value[0].id, 2)
})

test('opening and reading an Avito chat updates only its entity and keeps the other chats', () => {
    const { state, dispose } = harness()
    state.items.value = [
        { id: 1, name: 'Покупатель', avito_chats: [{ id: 7, is_unread: true }, { id: 8, is_unread: true }] },
        { id: 2, avito_chats: [{ id: 9, is_unread: true }] },
    ]
    state.openAvitoChat({ entity: state.items.value[0], chat: state.items.value[0].avito_chats[1] })
    assert.equal(state.avitoDialogOpened.value, true)
    assert.equal(state.avitoChat.value.id, 8)
    assert.equal(state.avitoEntityName.value, 'Покупатель')
    state.updateAvitoChat({ id: 8, is_unread: false, unread_count: 0 })
    assert.equal(state.items.value[0].avito_unread_chats_count, 1)
    assert.equal(state.items.value[0].avito_chats[0].is_unread, true)
    assert.equal(state.items.value[1].avito_chats[0].is_unread, true)
    dispose()
})

test('absence filters count as active and all new activity columns support sorting', () => {
    const { filters } = useEntityFilters()
    filters.has_sales = false
    filters.has_orders = true
    filters.has_unread_avito = false
    const { state } = harness('EntityTable', { filters, meta: {} })
    assert.equal(state.activeFiltersCount.value, 3)
    assert.ok(state.headers.some(header => header.key === 'sales_count'))
    assert.ok(!state.headers.some(header => header.key === 'sales_max_date'))
    assert.ok(state.sortOptions.some(option => option.value === 'sales_max_date'))
})

test('geography keeps every address, postcode, type, city and entity country without duplicate city rows', () => {
    const { filters } = useEntityFilters()
    const { state } = harness('EntityTable', { filters, meta: {} })
    const city = { id: 10, name: 'Калуга', region: { id: 20, name: 'Калужская область', country: { id: 30, name: 'Россия' } } }
    const otherCity = { id: 11, name: 'Минск', region: { id: 21, name: 'Минская область', country: { id: 31, name: 'Беларусь' } } }
    const buildings = [
        { id: 1, city, address: 'ул. Ленина, 1', postcode: '248000', building_type: { id: 1, name: 'Офис' } },
        { id: 2, city, address: 'ул. Складская, 2', postcode: '248001', building_type: { id: 2, name: 'Склад' } },
        { id: 3, address: 'Рабочий адрес без города', building_type: { id: 3, name: 'Рабочий' } },
    ]
    const groups = state.geographyGroups({ country: { id: 32, name: 'Казахстан' }, cities: [{ id: 10, name: 'Калуга' }, otherCity], buildings })
    assert.equal(groups.length, 4)
    assert.equal(groups[0].country, 'Казахстан')
    const kaluga = groups.find(group => group.city === 'Калуга')
    assert.equal(kaluga.country, 'Россия')
    assert.equal(kaluga.region, 'Калужская область')
    assert.deepEqual(kaluga.addresses, buildings.slice(0, 2))
    assert.equal(groups.find(group => group.city === 'Минск').country, 'Беларусь')
    assert.equal(groups.find(group => !group.city && group.addresses.length).addresses[0].address, 'Рабочий адрес без города')
    assert.deepEqual(state.geographyGroups({}), [])
    assert.equal(state.geographyGroups({ country: city.region.country, cities: [city], buildings: [buildings[0]] }).length, 1)
    assert.equal(state.headers[state.headers.findIndex(header => header.key === 'classification_name') + 1].key, 'avito_chats_count')
})

test('country, region, city and address remain independent filters in requests and reset together', async () => {
    const { state, requests, dispose } = harness()
    Object.assign(state.filters, { country_ids: [1], region_ids: [2], city_ids: [3], building_ids: [4] })
    const load = state.loadItems()
    for (const [key, id] of [['country_ids', 1], ['region_ids', 2], ['city_ids', 3], ['building_ids', 4]]) {
        assert.deepEqual(requests[0].params[key], [id])
    }
    const table = harness('EntityTable', { filters: state.filters, meta: {} })
    assert.equal(table.state.activeFiltersCount.value, 4)
    requests[0].resolve(result([]))
    await load
    const reset = state.handleResetFilters()
    for (const key of ['country_ids', 'region_ids', 'city_ids', 'building_ids']) assert.deepEqual(requests[1].params[key], [])
    requests[1].resolve(result([]))
    await reset
    dispose()
})
