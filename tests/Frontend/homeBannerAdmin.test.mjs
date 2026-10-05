import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import * as helpers from '../../resources/js/Pages/Ameise/homeBannerAdmin.js'

const initialSettings = {
    enabled: true, desktop_height: 96, gap: 8, mobile_enabled: true,
    mobile_layout: 'scroll', mobile_height: 96, mobile_columns: 2,
    mobile_hide_empty: true, mobile_order: [1, 2, 3, 4, 5, 6],
}
const emptyFeed = () => ({ desktop: Array(6).fill(null), mobile: Array(6).fill(null) })

function harness(t, { confirm = true, patchError = null, deleteError = null, getHandler = null } = {}) {
    const filename = 'resources/js/Pages/Ameise/HomeBanners.vue'
    const { descriptor, errors } = parse(readFileSync(filename, 'utf8'), { filename })
    assert.deepEqual(errors, [])
    const compiled = compileScript(descriptor, { id: 'home-banner-admin-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'home-banner-admin-test', compilerOptions: { bindingMetadata: compiled.bindings } })
    assert.deepEqual(template.errors, [])
    const requests = []
    const confirmations = []
    const clipboard = []
    const environment = {
        ...Vue, ...helpers,
        onMounted() {}, useHead() {}, VerwalterLayout: {}, HomeBannerStrip: {},
        console: { error() {} },
        window: { confirm(text) { confirmations.push(text); return confirm }, setTimeout() {} },
        navigator: { clipboard: { async writeText(value) { clipboard.push(value) } } },
        axios: {
            async get(url, options) {
                requests.push({ method: 'get', url, options })
                if (getHandler) return getHandler(url, options)
                return { data: url === '/api/home-banner-settings'
                    ? { data: { ...initialSettings }, feed: emptyFeed() }
                    : { data: [], folders: [], files: [] } }
            },
            async patch(url, body) {
                requests.push({ method: 'patch', url, body })
                if (patchError) throw patchError
                return { data: { data: body, feed: emptyFeed() } }
            },
            async post(url, body) { requests.push({ method: 'post', url, body }); return { data: { data: body } } },
            async delete(url) {
                requests.push({ method: 'delete', url })
                if (deleteError) throw deleteError
                return { data: {} }
            },
        },
    }
    const script = compiled.content.replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const component = new Function('env', `with(env){${script}}`)(environment)
    const scope = Vue.effectScope()
    const state = scope.run(() => component.setup({}, { expose() {} }))
    t.after(() => scope.stop())
    return { state, requests, confirmations, clipboard }
}

test('Moscow schedule input crosses midnight and serializes the same instant regardless of browser timezone', () => {
    assert.equal(helpers.moscowDateTimeInput('2026-12-31T21:30:00Z'), '2027-01-01T00:30')
    const payload = helpers.moscowDateTimePayload('2027-01-01T00:30')
    assert.equal(payload, '2027-01-01T00:30:00+03:00')
    assert.equal(new Date(payload).toISOString(), '2026-12-31T21:30:00.000Z')
    assert.equal(helpers.moscowDateTimeInput(null), '')
    assert.equal(helpers.moscowDateTimeInput('invalid'), '')
    assert.equal(helpers.moscowDateTimePayload(''), null)
})

test('mobile slot movement preserves all six slots and rejects invalid orders', () => {
    const order = [1, 2, 3, 4, 5, 6]
    assert.deepEqual(helpers.moveMobileSlot(order, 2, -1), [1, 3, 2, 4, 5, 6])
    assert.deepEqual(order, [1, 2, 3, 4, 5, 6])
    assert.deepEqual(helpers.moveMobileSlot(order, 0, -1), order)
    assert.equal(helpers.validMobileOrder([6, 5, 4, 3, 2, 1]), true)
    assert.equal(helpers.validMobileOrder([1, 2, 3, 4, 5, 5]), false)
    assert.equal(helpers.validMobileOrder([1, 2, 3, 4, 5]), false)
    assert.equal(helpers.validMobileOrder(['1', 2, 3, 4, 5, 6]), false)
})

test('creating from a slot and saving sends its number, Moscow dates and per-device crop settings', async t => {
    const { state, requests } = harness(t)
    state.openCreateDialog(4)
    assert.equal(state.form.slot_number, 4)
    Object.assign(state.form, {
        title: 'Осеннее предложение', starts_at: '2026-10-15T09:00', ends_at: '2026-10-22T23:00',
        image_fit: 'cover', image_position: 'right top', mobile_image_fit: 'contain', mobile_image_position: 'left bottom',
    })
    await state.saveBanner()
    const request = requests.find(item => item.method === 'post')
    assert.equal(request.url, '/api/home-banners')
    assert.equal(request.body.slot_number, 4)
    assert.equal(request.body.starts_at, '2026-10-15T09:00:00+03:00')
    assert.equal(request.body.ends_at, '2026-10-22T23:00:00+03:00')
    assert.equal(request.body.image_position, 'right top')
    assert.equal(request.body.mobile_image_position, 'left bottom')
    assert.equal(state.dialogOpen.value, false)
    assert.equal(state.successMessage.value, 'Баннер сохранён.')
})

test('editing converts stored UTC dates to Moscow and keeps the dialog and field errors on a schedule conflict', async t => {
    const { state, requests } = harness(t, { patchError: { response: { data: { message: 'Validation failed', errors: { slot_number: ['Слот 2 занят в выбранный период.'] } } } } })
    state.editBanner({ id: 8, title: 'Акция', slot_number: 2, starts_at: '2026-10-14T21:00:00Z' })
    assert.equal(state.form.starts_at, '2026-10-15T00:00')
    await state.saveBanner()
    assert.equal(state.dialogOpen.value, true)
    assert.equal(state.editingId.value, 8)
    assert.deepEqual(state.fieldErrors.value.slot_number, ['Слот 2 занят в выбранный период.'])
    assert.match(state.formErrorMessage.value, /Слот 2 занят/)
    assert.equal(state.saving.value, false)
    assert.equal(requests.find(item => item.method === 'patch').body.starts_at, '2026-10-15T00:00:00+03:00')
})

test('editing a sanitized live feed fetches the full record before opening and preserves its schedule and visibility', async t => {
    const stored = {
        id: 9, title: 'Предложение', slot_number: 4, starts_at: '2026-10-14T21:00:00Z',
        ends_at: '2026-10-20T20:00:00Z', is_published: true, show_on_desktop: true, show_on_mobile: false, sort_order: 37,
    }
    const { state, requests } = harness(t, { getHandler: async url => ({ data: url === '/api/home-banners/9' ? { data: stored } : { files: [], folders: [] } }) })
    await state.editFeedBanner({ id: 9, title: 'Предложение', slot_number: 4 })
    assert.equal(requests[0].url, '/api/home-banners/9')
    assert.equal(state.form.starts_at, '2026-10-15T00:00')
    assert.equal(state.form.ends_at, '2026-10-20T23:00')
    assert.equal(state.form.show_on_mobile, false)
    assert.equal(state.form.is_published, true)
    assert.equal(state.form.sort_order, 37)
    assert.equal(state.editingPending.value, null)
    assert.equal(state.dialogOpen.value, true)
})

test('banner archive loads all server pages so scheduled and older campaigns remain available', async t => {
    const { state, requests } = harness(t, { getHandler: async (url, options) => ({ data: {
        data: [{ id: options.params.page, slot_number: options.params.page }], last_page: 3,
    } }) })
    state.search.value = 'Акция'
    await state.fetchBanners()
    assert.deepEqual(state.banners.value.map(item => item.id), [1, 2, 3])
    assert.deepEqual(requests.map(item => item.options.params.page), [1, 2, 3])
    assert.ok(requests.every(item => item.options.params.search === 'Акция' && item.options.params.per_page === 300))
    assert.equal(state.loading.value, false)
})

test('saving mobile grid settings sends the reordered slots and resets the unsaved marker', async t => {
    const { state, requests } = harness(t)
    await state.fetchSettings()
    assert.equal(state.settingsDirty.value, false)
    state.moveSlot(2, -1)
    Object.assign(state.settings, { mobile_layout: 'grid', mobile_columns: 1, mobile_height: 80, mobile_hide_empty: false, enabled: false })
    assert.equal(state.settingsDirty.value, true)
    await state.saveSettings()
    const request = requests.find(item => item.method === 'patch')
    assert.equal(request.url, '/api/home-banner-settings')
    assert.deepEqual(request.body.mobile_order, [1, 3, 2, 4, 5, 6])
    assert.equal(request.body.mobile_layout, 'grid')
    assert.equal(request.body.enabled, false)
    assert.equal(state.settingsDirty.value, false)
    assert.equal(state.successMessage.value, 'Настройки ленты сохранены.')
    state.settings.mobile_order = [1, 1, 3, 4, 5, 6]
    await state.saveSettings()
    assert.equal(requests.filter(item => item.method === 'patch').length, 1)
    assert.match(state.settingsErrorMessage.value, /ровно один раз/)
})

test('publication conflict is handled without optimistic mutation or sending unrelated fields', async t => {
    const { state, requests } = harness(t, { patchError: { response: { data: { errors: { slot_number: ['Пересечение сроков в слоте 3.'] } } } } })
    const banner = { id: 4, title: 'Баннер', is_published: false, slot_number: 3 }
    state.banners.value = [banner]
    await state.togglePublished(banner)
    assert.equal(banner.is_published, false)
    assert.deepEqual(requests.find(item => item.method === 'patch').body, { is_published: true })
    assert.match(state.errorMessage.value, /Пересечение сроков/)
    assert.equal(state.actionPending.value, null)
})

test('cancelled deletion sends no request and a deletion failure leaves the banner available', async t => {
    const cancelled = harness(t, { confirm: false })
    await cancelled.state.deleteBanner({ id: 7, title: 'Скидка' })
    assert.equal(cancelled.requests.length, 0)
    assert.match(cancelled.confirmations[0], /Скидка/)
    const failed = harness(t, { deleteError: { response: { data: { message: 'Удаление недоступно' } } } })
    failed.state.banners.value = [{ id: 7, title: 'Скидка' }]
    await failed.state.deleteBanner(failed.state.banners.value[0])
    assert.equal(failed.state.banners.value.length, 1)
    assert.equal(failed.state.errorMessage.value, 'Удаление недоступно')
    assert.equal(failed.state.actionPending.value, null)
})

test('copyable ChatGPT brief uses selected device, slot, safe area, size and content mode', async t => {
    const { state, clipboard } = harness(t)
    Object.assign(state.form, { title: 'Мука', slot_number: 6, content_mode: 'overlay', mobile_image_position: 'right bottom' })
    state.previewDevice.value = 'mobile'
    await state.copyProductionBrief()
    assert.match(clipboard[0], /мобильная версия, слот 6/)
    assert.match(clipboard[0], /1600 × 800 px/)
    assert.match(clipboard[0], /10%/)
    assert.match(clipboard[0], /right bottom/)
    assert.match(clipboard[0], /Не добавляй текст/)
    assert.equal(state.copiedBrief.value, true)
})

test('text-only banner brief asks for readable copy instead of an unused image', async t => {
    const { state, clipboard } = harness(t)
    Object.assign(state.form, { title: 'Мука', content_mode: 'text' })
    await state.copyProductionBrief()
    assert.match(clipboard[0], /Подготовь короткое текстовое предложение/)
    assert.match(clipboard[0], /Изображение не требуется/)
})
