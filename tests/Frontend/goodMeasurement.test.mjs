import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import { compileScript, parse } from '@vue/compiler-sfc'
import * as Vue from 'vue'
import { massUnitFactor, measurementForGood, quantityWeight } from '../../resources/js/utils/goodMeasurement.js'

test('explicit measurement supports decimal mass and never derives accounting units from packaging', () => {
    assert.equal(quantityWeight(10, measurementForGood({ denominator: 10 })), null)
    assert.equal(quantityWeight(10.5, { kilograms_per_unit: 1 }), 10.5)
    assert.equal(quantityWeight(10, { kilograms_per_unit: 10 }), 100)
    assert.equal(quantityWeight(250, { kilograms_per_unit: massUnitFactor('г.') }), 0.25)
    assert.equal(quantityWeight(1.25, { kilograms_per_unit: massUnitFactor('тонна') }), 1250)
})

test('applying a kilogram calculation sends prices per configured accounting unit', async t => {
    const filename = 'resources/js/Components/Goods/GoodPriceCalculationsTab.vue'
    const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
    const script = compileScript(descriptor, { id: 'price-measurement-test' }).content
        .replace(/^import .+? from ['"].*['"];?$/gm, '').replace('export default', 'return')
    const writes = []
    const env = {
        ...Vue, onMounted() {},
        useGoodPriceCalculations: () => ({ calculations: Vue.ref([]), loading: Vue.ref(false), saving: Vue.ref(false), deleting: Vue.ref(false), fetchCalculations() {}, updateCalculation() {}, deleteCalculation() {} }),
        usePriceTypes: () => ({ priceTypes: Vue.ref([{ id: 1, is_public: true }]), fetchPriceTypes() {} }),
        useGoodPriceTypeValues: () => ({ saving: Vue.ref(false), async storeValue(value) { writes.push(value) } }),
    }
    const component = new Function('env', `with(env){${script}}`)(env)
    const props = Vue.reactive({ goodId: 42, measurement: { measure_id: 2, unit_label: 'коробка', kilograms_per_unit: 10 } })
    const scope = Vue.effectScope()
    const api = scope.run(() => component.setup(props, { expose() {}, emit() {} }))
    t.after(() => scope.stop())
    api.openApply({ id: 4, sale_net_per_kg: 100, sale_gross_per_kg: 120 })
    assert.equal(api.applyForm.price_net, 1000)
    assert.equal(api.applyForm.price_gross, 1200)
    assert.equal(api.priceUnit.value, 'коробка')
    api.applyForm.price_type_id = 1
    props.measurement = { measure_id: 1, unit_label: 'кг', kilograms_per_unit: 1 }
    assert.equal(api.priceUnit.value, 'коробка')
    await api.applyToPriceType()
    assert.deepEqual(writes[0].measurement, { measure_id: 2, unit_label: 'коробка', kilograms_per_unit: 10 })
    assert.equal(writes[0].price_gross, 1200)
    assert.equal(writes[0].calculation_id, 4)
    props.measurement = { measure_id: 3, unit_label: 'шт.', kilograms_per_unit: null }
    api.openApply({ id: 5, sale_net_per_kg: 200, sale_gross_per_kg: 240 })
    assert.equal(api.dialogApply.value, false)
    assert.match(api.applyError.value, /массу одной единицы/)
    assert.equal(writes.length, 1)
})
