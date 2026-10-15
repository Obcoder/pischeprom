import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { findVNode, hasClass, templateRenderer } from './support/renderTemplate.mjs'
const flush = () => new Promise(resolve => setImmediate(resolve))
function harness(t, name = 'SupplierOrderComposer', initial = {}) {
    const filename = `resources/js/Components/Unit/Mail/${name}.vue`
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename }); assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: name })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: name, compilerOptions: { bindingMetadata: compiled.bindings } }); assert.deepEqual(template.errors, [])
    const requests = [], emitted = []
    const props = Vue.reactive({ unitId: 6, orderId: null, recipients: [], mailboxes: [{ address: 'com@food-server.ru' }], unitFiles: [], initialTo: ['supplier@example.test'], initialStorageFiles: [], modelValue: false, sending: false, replyContext: null, initialMode: 'supplier_order', initialSupplierOrderId: null, ...initial })
    const env = { ...Vue, _mergeModels: Vue.mergeModels, _useModel: (props, key) => Vue.computed({ get: () => props[key], set: value => { props[key] = value } }), MailTemplatesDialog: {}, SupplierOrderComposer: {}, axios: Object.fromEntries(['get', 'post', 'patch', 'delete'].map(method => [method, (url, body) => new Promise((resolve, reject) => requests.push({ method, url, body, resolve: data => resolve({ data }), reject }))])) }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(env), scope = Vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    t.after(() => scope.stop())
    return { state, props, requests, emitted, render: templateRenderer(template, state, props) }
}
const measurement = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
const good = { good_id: 14, name: 'Мак пищевой', measurement, unit_price: null, currency_id: null, currency_code: null, demand_quantity: 8, stock_quantity: 2, shortage_quantity: 6, ordered_quantity: 1, to_order_quantity: 5, customer_orders: [{ id: 10, item_id: 20, number: 'К-10', quantity: 8, unit_label: 'кг' }], supplier_orders: [], unit_issues: [], incoming_warnings: [] }
const defaults = { mailbox: 'com@food-server.ru', subject: 'ПИЩЕПРОМ-СЕРВЕР: заказ от 15.10.2026 0007', body: 'Добрый день!\nПросим принять в работу заказ и подтвердить наличие, цены и срок поставки (возможность отгрузки) указанных товаров:', subject_date: '2026-10-15', supplier_sequence: 7, auto_subject: true }
const context = () => ({ data: [structuredClone(good)], meta: { total: 1, current_page: 1, last_page: 1 }, warehouses: [{ id: 1, name: 'Основной' }], warehouse_id: 1, currencies: [{ id: 1, code: 'RUB' }], recipients: [], unit: { id: 6, name: 'Поставщик' }, can_send: true, defaults: { ...defaults } })
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
    h.state.selectTab('demand'); const old = h.requests.at(-1)
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


test('supplier assortment opens immediately and a quoted product keeps source, currency and tax in the draft', async t => {
    const h = harness(t)
    assert.equal(h.requests[0].body.params.tab, 'assortment')
    const quoted = { ...good, unit_price: 11, currency_id: 2, currency_code: 'EUR', quotation_id: 31,
        quotation_snapshot: { vat_label: 'Включая НДС 10%', price: 100, denominator: 10 } }
    h.requests[0].resolve({ ...context(), data: [quoted], currencies: [{ id: 2, code: 'EUR' }] }); await flush()
    h.state.addGood(quoted, 2.5)
    assert.equal(h.state.payload().items[0].quotation_id, 31)
    assert.equal(h.state.payload().items[0].unit_price, 11)
    assert.equal(h.state.payload().items[0].currency_id, 2)
    assert.equal(h.state.vatLabel(h.state.form.items[0]), 'Включая НДС 10%')
    const save = h.state.saveDraft(), request = h.requests.at(-1)
    request.resolve(stored(request.body, { items: [{ ...h.state.form.items[0] }] })); await save
    assert.equal(h.state.payload().supplier_order_id, 80)
    assert.equal(h.state.form.items[0].quotation_id, 31)
    const price = findVNode(h.render(), node => node.type === 'input' && node.props?.['aria-label'] === `Цена ${good.name}`)
    assert.ok(price?.props.onInput, 'Manual edits explicitly detach the source quotation')
    price.props.onInput({ target: { value: '15' } })
    assert.equal(h.state.payload().items[0].quotation_id, null)
    assert.equal(h.state.form.items[0].quotation_snapshot, null)
    assert.equal(h.state.vatLabel(h.state.form.items[0]), 'НДС не указан')
})

test('new supplier orders prefer the food-server mailbox and use the requested introduction', async t => {
    const h = harness(t, 'SupplierOrderComposer', { mailboxes: [{ address: 'office@example.test' }, { address: 'com@food-server.ru', label: 'Food-server' }] })
    h.requests[0].resolve(context()); await flush()
    assert.equal(h.state.form.mailbox, 'com@food-server.ru')
    assert.equal(h.state.form.body, 'Добрый день!\nПросим принять в работу заказ и подтвердить наличие, цены и срок поставки (возможность отгрузки) указанных товаров:')
    assert.equal(h.state.dirty.value, false, 'Defaults are the initial draft, not unsaved user changes')
    h.state.form.mailbox = 'office@example.test'
    h.state.selectTab('demand'); h.requests.at(-1).resolve(context()); await flush()
    assert.equal(h.state.form.mailbox, 'office@example.test', 'Context refresh never overwrites an explicitly selected sender')
})

test('the preferred sender is selected after mailbox loading but an unavailable sender never silently switches accounts', async t => {
    const preferred = harness(t, 'SupplierOrderComposer', { mailboxes: [] })
    const mailboxRequest = preferred.requests.find(item => item.url === '/api/mailboxes')
    mailboxRequest.resolve([{ address: 'office@example.test' }, { address: 'com@food-server.ru' }])
    preferred.requests.find(item => item.url.includes('supplier-order-context')).resolve(context()); await flush()
    assert.equal(preferred.state.form.mailbox, 'com@food-server.ru'); assert.equal(preferred.state.dirty.value, false)

    const unavailable = harness(t, 'SupplierOrderComposer', { mailboxes: [{ address: 'office@example.test' }] })
    unavailable.requests[0].resolve(context()); await flush()
    assert.equal(unavailable.state.form.mailbox, '', 'Require an explicit account choice when the requested sender is unavailable')
    assert.equal(unavailable.state.canSend.value, false)
})

test('opening an existing draft keeps its sender and letter instead of replacing them with new-order defaults', async t => {
    const h = harness(t, 'SupplierOrderComposer', { orderId: 80, mailboxes: [{ address: 'com@food-server.ru' }, { address: 'office@example.test' }] })
    const header = { unit_id: 6, subject: 'Согласованный заказ', body: 'Согласованный текст', mailbox: 'office@example.test', to: ['supplier@example.test'], cc: [], items: [] }
    h.requests[0].resolve(stored(header)); await flush(); h.requests.at(-1).resolve(context()); await flush()
    assert.equal(h.state.form.mailbox, 'office@example.test'); assert.equal(h.state.form.subject, 'Согласованный заказ'); assert.equal(h.state.form.body, 'Согласованный текст')
    assert.equal(h.state.dirty.value, false)
})

test('refreshing context keeps the table visible but blocks adding a stale price until the response arrives', async t => {
    const h = await ready(t)
    h.state.contextQuantities[good.good_id] = '2'
    h.state.selectTab('demand')
    const request = h.requests.at(-1), tree = h.render()
    assert.ok(findVNode(tree, node => node.type === 'table' && hasClass(node, 'context-table')), 'Loading must not replace the entire table with an empty loading paragraph')
    const addButton = findVNode(tree, node => node.type === 'button' && node.props?.['aria-label'] === `Добавить ${good.name}`)
    assert.ok(addButton?.props.disabled, 'Retained rows must not be actionable while a different query is loading')
    h.state.addGood(good, 2)
    assert.equal(h.state.form.items.length, 0, 'Keyboard and direct add paths also guard stale data')
    request.resolve({ ...context(), data: [{ ...good, unit_price: 99, currency_id: 1, currency_code: 'RUB' }] }); await flush()
    const updated = findVNode(h.render(), node => node.type === 'button' && node.props?.['aria-label'] === `Добавить ${good.name}`)
    assert.equal(Boolean(updated.props.disabled), false)
    updated.props.onClick()
    assert.equal(h.state.form.items[0].unit_price, 99)
    assert.equal(h.state.form.items[0].quantity, 2)
})

test('returning to a visited source immediately restores its data while a stale response cannot replace the active source', async t => {
    const h = await ready(t)
    h.state.selectTab('demand')
    h.requests.at(-1).resolve({ ...context(), data: [{ ...good, good_id: 15, name: 'Товар из потребности' }] }); await flush()
    h.state.selectTab('catalog'); const slow = h.requests.at(-1)
    h.state.selectTab('assortment')
    assert.equal(h.state.context.value.data[0].name, good.name, 'A visited tab shows its own cached rows without first emptying the form')
    const active = h.requests.at(-1)
    slow.resolve({ ...context(), data: [{ ...good, name: 'Запоздавший каталог' }] }); await flush()
    assert.equal(h.state.context.value.data[0].name, good.name)
    if (active !== slow) { active.resolve({ ...context(), data: [{ ...good, name: 'Обновлённый ассортимент' }] }); await flush() }
    assert.notEqual(h.state.context.value.data[0].name, 'Запоздавший каталог')
})

test('a new-order subject follows server supplier numbering and stops being automatic after an explicit edit', async t => {
    const h = await ready(t)
    assert.equal(h.state.form.subject, defaults.subject)
    assert.equal(h.state.payload().auto_subject, true)
    assert.equal(h.state.dirty.value, false)
    const subject = findVNode(h.render(), node => node.type === 'input' && node.props?.['aria-label'] === 'Тема заказа')
    assert.ok(subject?.props.onInput, 'Editing the subject must explicitly opt out of automatic numbering')
    subject.props['onUpdate:modelValue']('Согласованный заказ на пятницу')
    subject.props.onInput({ target: { value: 'Согласованный заказ на пятницу' } })
    assert.equal(h.state.payload().auto_subject, false)
    h.state.selectTab('demand')
    h.requests.at(-1).resolve({ ...context(), defaults: { ...defaults, subject: 'ПИЩЕПРОМ-СЕРВЕР: заказ от 16.10.2026 0008', supplier_sequence: 8 } }); await flush()
    assert.equal(h.state.form.subject, 'Согласованный заказ на пятницу')
    const save = h.state.saveDraft(), request = h.requests.at(-1)
    assert.equal(request.body.auto_subject, false)
    request.resolve(stored(request.body)); await save
    assert.equal(h.state.form.subject, 'Согласованный заказ на пятницу'); assert.equal(h.state.payload().auto_subject, false)
})

test('applying a subject template preserves the chosen wording instead of regenerating a numbered subject', async t => {
    const h = await ready(t)
    h.state.templates.value = [{ id: 5, subject: 'Специальный заказ', body: 'Просим подготовить образцы.' }]
    h.state.templateId.value = '5'; h.state.applyTemplate()
    assert.equal(h.state.form.subject, 'Специальный заказ'); assert.equal(h.state.payload().auto_subject, false)
    assert.equal(h.state.form.body, 'Просим подготовить образцы.')
})

test('a quantity validation error can be corrected without reloading supplier context', async t => {
    const h = await ready(t), before = h.requests.length
    h.state.addGood(good, 0)
    assert.equal(h.state.form.items.length, 0); assert.match(h.state.contextError.value, /положительное количество/)
    h.state.addGood(good, 2)
    assert.equal(h.state.form.items.length, 1, 'Invalid input must not lock the entire context pane')
    assert.equal(h.state.form.items[0].quantity, 2); assert.equal(h.state.contextError.value, '')
    assert.equal(h.requests.length, before)
})

test('a failed context refresh retains readable data and blocks additions until a successful retry', async t => {
    const h = await ready(t)
    h.state.selectTab('demand')
    h.requests.at(-1).reject({ response: { data: { message: 'Не удалось обновить остатки' } } }); await flush()
    assert.ok(findVNode(h.render(), node => node.type === 'table' && hasClass(node, 'context-table')))
    h.state.addGood(good, 2)
    assert.equal(h.state.form.items.length, 0)
    const retry = h.state.loadContext(); h.requests.at(-1).resolve(context()); await retry
    h.state.addGood(good, 2)
    assert.equal(h.state.form.items[0].quantity, 2)
})

test('changing search invalidates an older response during the debounce window', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] })
    const h = await ready(t)
    const oldLoad = h.state.loadContext(), old = h.requests.at(-1)
    h.state.search.value = 'Новый поиск'; h.state.searchContext()
    old.resolve({ ...context(), data: [{ ...good, name: 'Старый поиск' }] }); await oldLoad
    h.state.addGood(good, 2)
    assert.equal(h.state.form.items.length, 0, 'The older response must not unlock rows while the new query is debouncing')
    t.mock.timers.tick(300); await flush()
    const current = h.requests.at(-1)
    assert.equal(current.body.params.search, 'Новый поиск')
    current.resolve({ ...context(), data: [{ ...good, name: 'Новый поиск' }] }); await flush()
    h.state.addGood(h.state.context.value.data[0], 2)
    assert.equal(h.state.form.items[0].name, 'Новый поиск')
})

test('a different supplier clears query cache and filters and rejects previous supplier responses', async t => {
    const h = await ready(t)
    h.state.deliveryTo.value = '2026-10-20'; h.state.includeDeferred.value = true
    h.state.selectTab('demand'); const old = h.requests.at(-1)
    h.props.unitId = 7; await Vue.nextTick()
    const active = h.requests.at(-1)
    assert.equal(active.url, '/api/units/7/supplier-order-context')
    assert.equal(active.body.params.tab, 'assortment'); assert.equal(active.body.params.delivery_to, undefined); assert.equal(active.body.params.include_deferred, 0)
    assert.equal(h.state.context.value, null)
    old.resolve({ ...context(), data: [{ ...good, name: 'Товар прежнего поставщика' }] }); await flush()
    assert.equal(h.state.context.value, null)
    active.resolve({ ...context(), unit: { id: 7, name: 'Другой поставщик' }, data: [{ ...good, good_id: 16, name: 'Товар другого поставщика' }] }); await flush()
    assert.equal(h.state.context.value.data[0].good_id, 16); assert.equal(h.state.dirty.value, false)
})

test('tab switching preserves the letter, quantity inputs, expanded rows and each source scroll position', async t => {
    const h = await ready(t)
    h.state.form.body = 'Подготовьте, пожалуйста, к пятнице.'
    h.state.contextQuantities[good.good_id] = '12.5'; h.state.expanded[good.good_id] = true
    h.state.toolsPane.value = { scrollTop: 180 }; h.state.contextTable.value = { scrollLeft: 70 }
    h.state.selectTab('demand'); await Vue.nextTick()
    assert.equal(h.state.toolsPane.value.scrollTop, 0); assert.equal(h.state.contextTable.value.scrollLeft, 0)
    h.requests.at(-1).resolve(context()); await flush()
    h.state.toolsPane.value.scrollTop = 40; h.state.contextTable.value.scrollLeft = 15
    h.state.selectTab('assortment'); await Vue.nextTick()
    assert.equal(h.state.toolsPane.value.scrollTop, 180); assert.equal(h.state.contextTable.value.scrollLeft, 70)
    assert.equal(h.state.form.body, 'Подготовьте, пожалуйста, к пятнице.')
    assert.equal(h.state.contextQuantities[good.good_id], '12.5'); assert.equal(h.state.expanded[good.good_id], true)
    h.requests.at(-1).resolve(context()); await flush()
    assert.equal(h.state.toolsPane.value.scrollTop, 180)
})

test('product thumbnails appear in the letter and a broken image leaves the named product intact', async t => {
    const h = await ready(t), illustrated = { ...good, avatar_url: 'https://static.example.test/products/poppy.jpg' }
    h.state.addGood(illustrated, 2)
    const letter = findVNode(h.render(), node => node.type === 'section' && node.props?.['aria-label'] === 'Таблица заказа в письме')
    const thumbnail = findVNode(letter, node => node.type === 'img' && node.props?.src === illustrated.avatar_url)
    assert.ok(thumbnail); assert.equal(Number(thumbnail.props.width), 32)
    thumbnail.props.onError()
    const refreshed = findVNode(h.render(), node => node.type === 'section' && node.props?.['aria-label'] === 'Таблица заказа в письме')
    assert.equal(findVNode(refreshed, node => node.type === 'img' && node.props?.src === illustrated.avatar_url), null)
    assert.ok(findVNode(refreshed, node => node.type === 'span' && node.children === good.name))
    assert.equal(h.state.form.items[0].quantity, 2)
})
