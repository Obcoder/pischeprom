import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, templateRenderer } from './support/renderTemplate.mjs'
const flush = () => new Promise(resolve => setImmediate(resolve))
function harness(t, name = 'SupplierOrderComposer', initial = {}) {
    const filename = `resources/js/Components/Unit/Mail/${name}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename }); assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: name })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: name, compilerOptions: { bindingMetadata: compiled.bindings } }); assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    const props = Vue.reactive({ unitId: 6, orderId: null, recipients: [], mailboxes: [{ address: 'office@example.test' }], unitFiles: [], initialTo: ['supplier@example.test'], initialStorageFiles: [], modelValue: false, sending: false, replyContext: null, initialMode: 'supplier_order', initialSupplierOrderId: null, ...initial })
    const env = { ...Vue, _mergeModels: Vue.mergeModels, _useModel: (props, key) => Vue.computed({ get: () => props[key], set: value => { props[key] = value } }), MailTemplatesDialog: {}, SupplierOrderComposer: {}, axios: Object.fromEntries(['get', 'post', 'patch', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])) }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(env), scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { state, props, requests, emitted, render: templateRenderer(template, state, props) }
}
const measurement = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
const good = { good_id: 14, name: 'Мак пищевой', measurement, unit_price: null, currency_id: null, currency_code: null, demand_quantity: 8, stock_quantity: 2, shortage_quantity: 6, ordered_quantity: 1, to_order_quantity: 5, customer_orders: [{ id: 10, item_id: 20, number: 'К-10', quantity: 8, unit_label: 'кг' }], supplier_orders: [], unit_issues: [], incoming_warnings: [] }
const context = () => ({ data: [structuredClone(good)], meta: { total: 1, current_page: 1, last_page: 1 }, warehouses: [{ id: 1, name: 'Основной' }], warehouse_id: 1, currencies: [{ id: 1, code: 'RUB' }], recipients: [], unit: { id: 6, name: 'Поставщик' }, can_send: true })
const stored = (payload, extra = {}) => ({ ...payload, id: 80, number: 'ЗП-80', revision: 1, status: 'draft', status_label: 'Черновик', editable: true, unit: { id: 6, name: 'Поставщик' }, items: payload.items.map(item => ({ ...item, name: good.name, currency_code: item.currency_id ? 'RUB' : null })), ...extra })
async function ready(t) { const h = harness(t); h.requests[0].resolve(context()); await flush(); return h }

test('new Unit messages default to supplier orders while replies initialize only ordinary mail', async t => {
    const h = harness(t, 'UnitMailComposerDialog')
    h.props.modelValue = true; await Vue.nextTick()
    assert.equal(h.state.mode.value, 'supplier_order'); assert.equal(h.state.supplierMounted.value, true)
    assert.equal(h.requests.length, 0, 'The wrapper delegates supplier context loading to the visible child')
    h.props.modelValue = false; await Vue.nextTick()
    h.props.replyContext = { id: 17, mailbox: 'office@example.test', from_address: 'supplier@example.test', subject: 'Наличие', text: 'Да, есть' }
    h.props.modelValue = true; await Vue.nextTick(); await flush()
    assert.equal(h.state.mode.value, 'mail'); assert.equal(h.state.supplierMounted.value, false)
    assert.ok(h.requests.every(item => !item.url.includes('supplier-order')))
    h.requests.at(-1).resolve([]); await flush()
    assert.equal(h.state.subject.value, 'Re: Наличие'); assert.match(h.state.body.value, /Да, есть/)
})

test('ordinary mail prevents duplicate submission and preserves the reply and attachments after a send error', async t => {
    const h = harness(t, 'UnitMailComposerDialog', { initialMode: 'mail', replyContext: { id: 17, mailbox: 'office@example.test', from_address: 'supplier@example.test', subject: 'Наличие', text: 'Да, есть' } })
    h.props.modelValue = true; await Vue.nextTick(); await flush(); h.requests.at(-1).resolve([]); await flush()
    h.state.body.value = 'Подтвердите срок'; h.state.storageFiles.value = ['units/6/spec.pdf']
    const pending = h.state.submit(), request = h.requests.at(-1); h.state.submit()
    assert.equal(h.requests.filter(item => item.url.endsWith('/mail/send')).length, 1)
    assert.equal(request.body.get('reply_to_mail_message_id'), '17'); assert.equal(request.body.get('storage_files[]'), 'units/6/spec.pdf')
    request.reject({ response: { data: { message: 'Ящик недоступен' } } }); await pending
    assert.equal(h.state.body.value, 'Подтвердите срок'); assert.equal(h.props.modelValue, true); assert.match(h.state.sendError.value, /Ящик недоступен/)
    h.state.mode.value = 'supplier_order'; await Vue.nextTick(); h.state.mode.value = 'mail'; await Vue.nextTick()
    assert.equal(h.state.body.value, 'Подтвердите срок', 'Switching tools preserves the ordinary draft')
})

test('supplier selection merges six-decimal quantities and unknown recommendations never invent a quantity or price', async t => {
    const h = await ready(t)
    h.state.addGood(good, 0.000001); h.state.addGood(good, 0.000002)
    assert.equal(h.state.form.items.length, 1); assert.equal(h.state.form.items[0].quantity, 0.000003)
    assert.equal(h.state.payload().items[0].unit_price, null)
    assert.equal(h.state.payload().include_prices, true)
    assert.deepEqual(h.state.payload().items[0].source_order_item_ids, [20])
    assert.equal(h.state.recommendedQuantity({ ...good, to_order_quantity: null }), null)
    h.state.addGood({ ...good, good_id: 99, to_order_quantity: null })
    assert.equal(h.state.form.items.length, 1); assert.match(h.state.contextError.value, /вручную/)
    assert.equal(h.state.money(0.000003), '0,000003')
})

test('changing tabs and filters preserves chosen lines and ignores slower context responses', async t => {
    const h = await ready(t); h.state.addGood(good, 2)
    h.state.selectTab('assortment'); const old = h.requests.at(-1)
    h.state.selectTab('catalog'); const latest = h.requests.at(-1)
    latest.resolve({ ...context(), data: [{ ...good, name: 'Новый результат' }] }); await flush()
    old.resolve({ ...context(), data: [] }); await flush()
    assert.equal(h.state.context.value.data[0].name, 'Новый результат'); assert.equal(h.state.form.items[0].quantity, 2)
    assert.equal(h.state.dirty.value, true)
    assert.equal(latest.body.params.tab, 'catalog')
})

test('preview never sends mail and a changed draft invalidates an in-flight preview', async t => {
    const h = await ready(t); h.state.addGood(good, 2)
    const pending = h.state.fetchPreview(), request = h.requests.at(-1)
    assert.equal(request.url, '/api/supplier-orders/preview'); assert.equal(request.body.include_prices, true)
    h.state.form.items[0].quantity = 3
    request.resolve({ html: '<p>Старый состав</p>' }); await pending
    assert.equal(h.state.previewHtml.value, '')
    assert.ok(h.requests.every(item => !item.url.endsWith('/send')))
    const rendered = h.render()
    assert.ok(findVNode(rendered, node => node.type === 'section' && node.props?.['aria-label'] === 'Таблица заказа в письме'))
})

test('saving a draft does not send it; explicit sending uses the saved revision and emits sent only after confirmation', async t => {
    const h = await ready(t); h.state.addGood(good, 2)
    const save = h.state.saveDraft(), create = h.requests.at(-1)
    assert.equal(create.url, '/api/supplier-orders'); assert.ok(create.body.create_key)
    create.resolve(stored(create.body)); await save
    assert.ok(!h.requests.some(item => item.url.endsWith('/send'))); assert.equal(h.state.dirty.value, false)
    h.state.storageFiles.value = ['units/6/spec.pdf']
    const send = h.state.send(), request = h.requests.at(-1); h.state.send()
    assert.equal(request.url, '/api/supplier-orders/80/send'); assert.equal(request.body.get('revision'), '1'); assert.equal(request.body.get('storage_files[]'), 'units/6/spec.pdf')
    assert.equal(h.requests.filter(item => item.url.endsWith('/send')).length, 1)
    request.resolve({ order: stored(create.body, { status: 'sent', status_label: 'Отправлен', editable: false, revision: 2 }) }); await send
    assert.equal(h.state.editable.value, false); assert.ok(h.emitted.some(([name, doc]) => name === 'sent' && doc.status === 'sent'))
    assert.equal(h.state.storageFiles.value.length, 0)
    h.requests.at(-1).resolve(context()); await flush()
})

test('a lost send response refreshes server status and blocks retries if status cannot be verified', async t => {
    const h = await ready(t); h.state.addGood(good, 2)
    const save = h.state.saveDraft(), create = h.requests.at(-1); create.resolve(stored(create.body)); await save
    const send = h.state.send(); h.requests.at(-1).reject(new Error('Network error')); await flush()
    assert.equal(h.requests.at(-1).method, 'get'); assert.equal(h.requests.at(-1).url, '/api/supplier-orders/80')
    h.requests.at(-1).reject(new Error('Still offline')); await send
    assert.equal(h.state.statusUncertain.value, true); assert.equal(h.state.canSend.value, false)
    const requestsBefore = h.requests.length; await h.state.send(); assert.equal(h.requests.length, requestsBefore)
    const check = h.state.reconcile(); assert.equal(h.requests.at(-1).method, 'get')
    h.requests.at(-1).resolve(stored(create.body, { status: 'sent', editable: false, revision: 2 })); await check
    assert.equal(h.state.statusUncertain.value, false); assert.ok(h.emitted.some(([event]) => event === 'sent'))
    h.requests.at(-1).resolve(context()); await flush()
})

test('historical readonly order previews use stored snapshots through GET', async t => {
    const h = harness(t, 'SupplierOrderComposer', { orderId: 80 })
    const header = { unit_id: 6, subject: 'Старый заказ', body: 'Текст', mailbox: 'office@example.test', to: ['supplier@example.test'], cc: [], include_prices: true, items: [{ good_id: 14, name: good.name, quantity: 1, measurement: { ...measurement, unit_label: 'стар.ед.' }, unit_price: null }] }
    h.requests[0].resolve(stored(header, { status: 'sent', editable: false })); await flush(); h.requests.at(-1).resolve(context()); await flush()
    const preview = h.state.fetchPreview(); assert.equal(h.requests.at(-1).method, 'get'); assert.equal(h.requests.at(-1).url, '/api/supplier-orders/80/preview')
    h.requests.at(-1).resolve({ html: '<p>Исторические единицы</p>' }); await preview
    assert.match(h.state.previewHtml.value, /Исторические/)
    const frame = findVNode(h.render(), node => node.type === 'iframe'); assert.equal(frame.props.sandbox, '')
})

test('closing a dirty supplier draft requires explicit discard and recipient limits prevent sending', async t => {
    const h = await ready(t); h.state.addGood(good, 2)
    h.state.requestClose(); assert.equal(h.state.closeConfirmation.value, true); assert.ok(!h.emitted.some(([name]) => name === 'close'))
    h.state.closeConfirmation.value = false; assert.equal(h.state.form.items.length, 1)
    h.state.form.to = Array.from({ length: 6 }, (_, index) => `s${index}@example.test`).join(', ')
    assert.equal(h.state.canSend.value, false)
    h.state.discard(); assert.ok(h.emitted.some(([name]) => name === 'close'))
})


test('adding the same good after its measurement changes cannot merge unlike quantities', async t => {
    const h = await ready(t); h.state.addGood(good, 2)
    h.state.addGood({ ...good, measurement: { measure_id: 2, unit_label: 'уп.', kilograms_per_unit: 25 } }, 3)
    assert.equal(h.state.form.items[0].quantity, 2)
    assert.match(h.state.contextError.value, /Единица или масса этого товара изменилась/)
})

test('a failed saved-order load never silently becomes an editable new order', async t => {
    const h = harness(t, 'SupplierOrderComposer', { orderId: 999 })
    h.requests[0].reject({ response: { data: { message: 'Заказ не найден' } } }); await flush()
    assert.equal(h.state.loadFailed.value, true); assert.equal(h.state.editable.value, false)
    const before = h.requests.length; await h.state.saveDraft(); assert.equal(h.requests.length, before)
})

test('supplier state reports local or storage attachments as unsaved even without document edits', async t => {
    const h = await ready(t)
    assert.equal(h.state.dirty.value, false)
    h.state.localFiles.value.push({ name: 'спецификация.pdf', size: 100, lastModified: 1 }); await Vue.nextTick()
    assert.equal(h.state.dirty.value, false)
    assert.deepEqual(h.emitted.filter(([event]) => event === 'state').at(-1)[1], { dirty: true, busy: false })
    h.state.requestClose(); assert.equal(h.state.closeConfirmation.value, true)
    h.state.localFiles.value = []; await Vue.nextTick()
    assert.equal(h.emitted.filter(([event]) => event === 'state').at(-1)[1].dirty, false)
    h.state.storageFiles.value = ['units/6/specification.pdf']; await Vue.nextTick()
    assert.equal(h.emitted.filter(([event]) => event === 'state').at(-1)[1].dirty, true)
})

test('closing from ordinary mode checks hidden supplier attachments first and then the ordinary draft', async t => {
    const h = harness(t, 'UnitMailComposerDialog')
    h.props.modelValue = true; await Vue.nextTick()
    h.state.mode.value = 'mail'; await Vue.nextTick(); await flush(); h.requests.at(-1).resolve([]); await flush()
    h.state.body.value = 'Несохранённое обычное письмо'
    h.state.supplierState.value = { dirty: true, busy: false }
    let requested = 0; h.state.supplierComposer.value = { requestClose() { requested++ } }
    h.state.close()
    assert.equal(requested, 1); assert.equal(h.state.mode.value, 'supplier_order'); assert.equal(h.props.modelValue, true)
    h.state.closeSupplier()
    assert.equal(h.state.closeConfirmation.value, true, 'Discarding supplier attachments still preserves the ordinary draft until a second explicit decision')
    assert.equal(h.props.modelValue, true); assert.equal(h.state.body.value, 'Несохранённое обычное письмо')
    h.state.closeConfirmation.value = false; h.state.body.value = ''
    h.state.closeSupplier(); assert.equal(h.props.modelValue, false)
})
