import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { ref } from 'vue'

test('the commodities photo filter sends false for items without a photo and resets to all', async () => {
    const requests = []
    const environment = {
        ref,
        route: name => name,
        axios: { get: async (url, options) => {
            requests.push({ url, options })
            return { data: { data: [], meta: { total: 0 } } }
        } },
    }
    const source = readFileSync(new URL('../../resources/js/Composables/useCommodities.js', import.meta.url), 'utf8')
        .replace(/^import .+ from ['"].*['"];?$/gm, '')
        .replace('export function useCommodities', 'function useCommodities')
    const state = new Function('env', `with(env){${source};return useCommodities()}`)(environment)

    state.filters.value.has_ava = false
    await state.indexCommodities()
    assert.equal(requests[0].url, 'commodities.index')
    assert.equal(requests[0].options.params.has_ava, false)

    state.filters.value.has_ava = true
    await state.indexCommodities()
    assert.equal(requests[1].options.params.has_ava, true)

    state.resetCommodityFilters()
    await state.indexCommodities()
    assert.equal(requests[2].options.params.has_ava, 'all')
})
