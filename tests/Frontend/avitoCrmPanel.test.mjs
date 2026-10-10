import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { measurementForGood, unitLabel } from '../../resources/js/utils/goodMeasurement.js'
import { selectedApartment, selectedBuildingApartments } from '../../resources/js/utils/buildingApartments.js'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'

function panelHarness() {
    const filename = fileURLToPath(new URL('../../resources/js/Components/Avito/AvitoCrmPanel.vue', import.meta.url))
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'avito-crm-panel' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'avito-crm-panel', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = [], emitted = [], disposal = []
    const timers = new Map()
    let timerId = 0
    const request = (method, url, options) => new Promise((resolve, reject) => {
        requests.push({ method, url, options, resolve: data => resolve({ data }), reject })
    })
    const env = {
        ...Vue, measurementForGood, unitLabel, selectedApartment, selectedBuildingApartments,
        AvitoAutoReplies: {}, AvitoMessageTemplates: {}, ApartmentSelector: {}, CompactBuildingFields: {}, OrderDetailsDialog: {},
        onBeforeUnmount: callback => disposal.push(callback),
        setTimeout: callback => { timers.set(++timerId, callback); return timerId },
        clearTimeout: id => timers.delete(id),
        axios: { get: (url, options) => request('get', url, options), post: (url, options) => request('post', url, options) },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(env)
    const scope = Vue.effectScope()
    const props = Vue.reactive({ chat: null })
    const api = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    return { api, props, requests, emitted, render: templateRenderer(template, api, props), dispose() { disposal.forEach(callback => callback()); scope.stop() } }
}

async function loadChat(harness, id) {
    harness.props.chat = { id }
    await Vue.nextTick()
    harness.requests.forEach(request => request.resolve(request.url.endsWith('/crm')
        ? { entity: { id: 3 }, candidates: [], orders: [] }
        : { items: [], order_statuses: [], currency_codes: ['RUB'] }))
    await Vue.nextTick()
    await Vue.nextTick()
    harness.requests.length = 0
    harness.emitted.length = 0
}

test('Avito opens order details without leaving the chat or clearing the order draft', t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, props, requests, render } = harness
    props.chat = { id: 7 }
    api.crm.value = { entity: { id: 3, telephones: [], buildings: [] }, candidates: [], orders: [{ id: 42, number: 'PP-42' }] }
    api.activeTab.value = 'order'
    api.orderItems.value = [{ good_id: 11, quantity: 3, unit_price: 200 }]
    api.orderForm.internal_comment = 'Сохранить черновик'
    const button = findVNode(render(), node => hasClass(node, 'recent-orders__item'))
    assert.equal(button.type, 'button')
    assert.equal(button.props['aria-haspopup'], 'dialog')
    assert.equal(button.props.href, undefined)
    button.props.onClick()
    assert.equal(api.orderDetailsOpen.value, true)
    assert.equal(api.selectedOrderId.value, 42)
    assert.equal(api.activeTab.value, 'order')
    assert.deepEqual(api.orderItems.value, [{ good_id: 11, quantity: 3, unit_price: 200 }])
    assert.equal(api.orderForm.internal_comment, 'Сохранить черновик')
    assert.equal(requests.length, 0)
    api.openOrderDetails({ id: 43 })
    assert.equal(api.selectedOrderId.value, 43)
    api.resetTransientState()
    assert.equal(api.orderDetailsOpen.value, false)
    assert.equal(api.selectedOrderId.value, null)
})

test('saved chat orders remain available after unlinking the customer and beyond the first four', t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    harness.props.chat = { id: 7 }
    harness.api.crm.value = { entity: null, candidates: [], orders: Array.from({ length: 5 }, (_, index) => ({ id: index + 1, number: `PP-${index + 1}` })) }
    harness.api.activeTab.value = 'order'
    const olderOrder = findVNode(harness.render(), node => node.props?.['aria-label'] === 'Детали заказа PP-5')
    assert.ok(olderOrder)
    olderOrder.props.onClick()
    assert.equal(harness.api.selectedOrderId.value, 5)
    assert.equal(harness.api.orderDetailsOpen.value, true)
})

test('city searches cancel and discard outdated results and unmount cancels the active request', async t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    api.citySearch.value = 'Мос'
    await Vue.nextTick()
    const older = api.searchCities()
    api.citySearch.value = 'Санкт'
    await Vue.nextTick()
    assert.equal(requests[0].options.signal.aborted, true)
    const newer = api.searchCities()
    requests[1].resolve({ items: [{ id: 2, label: 'Санкт-Петербург' }] })
    await newer
    requests[0].resolve({ items: [{ id: 1, label: 'Москва' }] })
    await older
    assert.deepEqual(api.cityResults.value, [{ id: 2, label: 'Санкт-Петербург' }])
    assert.equal(api.citySearching.value, false)
    const last = api.searchCities()
    harness.dispose()
    assert.equal(requests[2].options.signal.aborted, true)
    requests[2].resolve({ items: [] })
    await last
    assert.deepEqual(api.cityResults.value, [{ id: 2, label: 'Санкт-Петербург' }])
})

test('saving Avito order details updates its card without touching the active draft and ignores a switched chat', t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, props, requests, render } = harness
    props.chat = { id: 7 }
    api.crm.value = { entity: { id: 3 }, candidates: [], orders: [{ id: 42, number: 'PP-42', total_amount: 10 }] }
    api.activeTab.value = 'order'
    api.orderItems.value = [{ good_id: 11, quantity: 3 }]
    api.orderForm.internal_comment = 'Черновик'
    api.openOrderDetails({ id: 42 })
    const dialog = findVNode(render(), node => node.type === api.OrderDetailsDialog)
    dialog.props.onSaved({ id: 42, total_amount: 150, status: { name: 'Отложен' } })
    assert.equal(api.crm.value.orders[0].total_amount, 150)
    assert.equal(api.crm.value.orders[0].number, 'PP-42')
    assert.equal(api.crm.value.orders[0].status.name, 'Отложен')
    assert.equal(api.activeTab.value, 'order')
    assert.deepEqual(api.orderItems.value, [{ good_id: 11, quantity: 3 }])
    assert.equal(api.orderForm.internal_comment, 'Черновик')
    assert.equal(requests.length, 0)
    props.chat = { id: 8 }
    dialog.props.onSaved({ id: 42, total_amount: 999 })
    assert.equal(api.crm.value.orders[0].total_amount, 150)
})

test('building validation keeps the entered address and apartment and shows field errors', async t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, props, requests } = harness
    // The parent can already have a loaded customer while the chat object is updated in place.
    props.chat = { id: 7 }
    await Vue.nextTick()
    requests.forEach(request => request.resolve(request.url.endsWith('/crm') ? { entity: { id: 3 }, candidates: [], orders: [] } : { items: [], order_statuses: [], currency_codes: ['RUB'] }))
    await Vue.nextTick()
    api.crm.value.entity = { id: 3 }
    api.buildingOpen.value = true
    Object.assign(api.buildingForm, { city_id: 1, address: ' Мира, 10 ', postcode: ' 101000 ', delivery_apartment_number: ' 12А ', delivery_apartment_type: 'office' })
    const saving = api.saveBuilding()
    const request = requests.at(-1)
    assert.equal(request.url, '/api/avito/messenger/chats/7/crm/buildings')
    assert.equal(request.options.address, 'Мира, 10')
    assert.equal(request.options.delivery_apartment_number, '12А')
    assert.equal(request.options.delivery_apartment_type, 'office')
    request.reject({ response: { data: { errors: { city_id: ['Выберите город'], postcode: ['Проверьте индекс'] } } } })
    await saving
    assert.equal(api.buildingOpen.value, true)
    assert.equal(api.buildingForm.delivery_apartment_number, ' 12А ')
    assert.deepEqual(api.buildingErrors.value.postcode, ['Проверьте индекс'])
    api.updateBuildingForm({ ...api.buildingForm, postcode: '191000' })
    assert.equal(api.buildingErrors.value.postcode, undefined)
    assert.deepEqual(api.buildingErrors.value.city_id, ['Выберите город'])
})

test('building save requires a selected city and does not submit a free-text city', async t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, requests } = harness
    api.crm.value.entity = { id: 3 }
    api.buildingForm.address = 'Мира, 10'
    api.citySearch.value = 'Москва'
    await api.saveBuilding()
    assert.match(api.buildingErrors.value.city_id[0], /Выберите город/)
    assert.equal(requests.length, 0)
    assert.equal(api.saving.value, false)
})

test('Avito sends a saved order repeatedly and refreshes chat messages after success', async t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, props, requests, emitted, render } = harness
    await loadChat(harness, 7)
    api.openOrderDetails({ id: 42 })
    const dialog = findVNode(render(), node => node.type === api.OrderDetailsDialog)
    const sendAction = disabled => findVNode(dialog.children.actions({ order: { id: 42 }, disabled, editing: disabled }), node => node.type === 'v-btn')
    assert.equal(sendAction(true).props.disabled, true)
    const send = sendAction(false).props.onClick()
    assert.equal(requests[0].url, '/api/avito/messenger/chats/7/crm/orders/42/send-confirmation')
    assert.equal(requests[0].options, undefined)
    assert.equal(api.orderSending.value, true)
    assert.equal(findVNode(render(), node => node.type === api.OrderDetailsDialog).props['external-busy'], true)
    await api.sendOrderConfirmation({ id: 42 })
    assert.equal(requests.length, 1)
    requests[0].resolve({ message: 'Заказ отправлен.', outbound: { sent: 1, warnings: [] } })
    await send
    assert.equal(api.orderSending.value, false)
    assert.equal(api.orderSendNotice.value, 'Заказ отправлен.')
    assert.deepEqual(emitted, [['notice', 'Заказ отправлен.'], ['refresh-messages']])
    const repeated = api.sendOrderConfirmation({ id: 42 })
    assert.equal(requests[1].url, requests[0].url)
    requests[1].resolve({ message: 'Отправлено частично.', outbound: { sent: 1, warnings: ['Вторая часть не отправлена.'] } })
    await repeated
    assert.equal(api.orderSendNotice.value, 'Отправлено частично. Вторая часть не отправлена.')
    api.orderSaved({ id: 42, total_amount: 999 })
    assert.equal(api.orderSendNotice.value, '')
})

test('Avito sending preserves actionable failures and allows retrying the saved order', async t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, props, requests, emitted } = harness
    await loadChat(harness, 7)
    api.openOrderDetails({ id: 42 })
    const failed = api.sendOrderConfirmation({ id: 42 })
    requests[0].resolve({ message: 'Заказ не отправлен.', outbound: { sent: 0, warnings: ['Avito временно недоступен.', 'Повторите позже.'] } })
    await failed
    assert.equal(api.orderSendError.value, 'Avito временно недоступен. Повторите позже.')
    assert.equal(api.orderSending.value, false)
    assert.equal(api.orderSendNotice.value, '')
    assert.deepEqual(emitted, [['error', 'Avito временно недоступен. Повторите позже.']])
    const retry = api.sendOrderConfirmation({ id: 42 })
    assert.equal(api.orderSendError.value, '')
    requests[1].reject({ response: { data: { message: 'Заказ больше не связан с чатом.' } } })
    await retry
    assert.equal(api.orderSendError.value, 'Заказ больше не связан с чатом.')
    assert.equal(api.orderSending.value, false)
    assert.equal(emitted.some(event => event[0] === 'refresh-messages'), false)
})

test('a late order send cannot update another order, another chat, or an unmounted panel', async t => {
    const harness = panelHarness()
    t.after(() => harness.dispose())
    const { api, props, requests, emitted } = harness
    await loadChat(harness, 7)
    api.openOrderDetails({ id: 42 })
    const older = api.sendOrderConfirmation({ id: 42 })
    api.openOrderDetails({ id: 43 })
    const newer = api.sendOrderConfirmation({ id: 43 })
    requests[0].resolve({ message: 'Старый заказ отправлен.', outbound: { sent: 1 } })
    await older
    assert.equal(api.orderSending.value, true)
    assert.equal(api.orderSendNotice.value, '')
    props.chat = { id: 8 }
    requests[1].reject({ response: { data: { message: 'Ошибка старого чата.' } } })
    await newer
    assert.deepEqual(emitted, [])
    await loadChat(harness, 8)
    api.openOrderDetails({ id: 44 })
    const last = api.sendOrderConfirmation({ id: 44 })
    const request = requests.at(-1)
    harness.dispose()
    request.resolve({ message: 'Отправлено.', outbound: { sent: 1 } })
    await last
    assert.equal(api.orderSendNotice.value, '')
    assert.deepEqual(emitted, [])
})
