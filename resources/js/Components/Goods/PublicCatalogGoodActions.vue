<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { useOrderCart } from '@/Composables/useOrderCart'
import { publicCatalogOffer } from '@/utils/publicCatalog'
import { unitLabel } from '@/utils/goodMeasurement'

const props = defineProps({ good: { type: Object, required: true }, compact: Boolean })
const emit = defineEmits(['inquiry', 'notice'])
const { addGood, cartError } = useOrderCart()
const { goodPublicUrl } = usePublicGoodUrl()
const quantity = ref(1)
const offer = computed(() => publicCatalogOffer(props.good))
const unit = computed(() => unitLabel(offer.value.measurement))
const canOrder = computed(() => Boolean(offer.value.measurement?.measure_id) && offer.value.can_order !== false)
const validQuantity = computed(() => Number.isFinite(Number(quantity.value)) && Number(quantity.value) >= 0.001 && Number(quantity.value) <= 9999)
const error = ref('')

function validate() {
    error.value = validQuantity.value ? '' : 'Укажите количество от 0,001 до 9999.'
    return canOrder.value && validQuantity.value
}
function adjustQuantity(direction) {
    const current = Number(quantity.value)
    const start = validQuantity.value ? current : 1
    quantity.value = Math.round(Math.min(9999, Math.max(0.001, start + direction)) * 1000) / 1000
    error.value = ''
}
function addToCart() {
    if (!validate()) return
    const amount = Math.round(Number(quantity.value) * 1000) / 1000
    const success = addGood(props.good, amount, offer.value)
    emit('notice', { success, message: success ? `${props.good.name}: ${amount.toLocaleString('ru-RU')} ${unit.value} добавлено в корзину` : cartError.value })
}
function inquire(kind) {
    if (kind !== 'email' && !validate()) return
    emit('inquiry', { good: props.good, quantity: validQuantity.value ? Number(quantity.value) : 1, purchase: offer.value, kind })
}
</script>

<template>
    <div class="catalog-actions" :class="{ 'catalog-actions--compact': compact }">
        <div class="catalog-actions__purchase">
            <div v-if="canOrder" class="catalog-actions__quantity" :class="{ 'catalog-actions__quantity--invalid': error }">
                <button v-if="!compact" type="button" class="catalog-actions__step" :disabled="validQuantity && Number(quantity) <= 0.001" :aria-label="`Уменьшить количество ${good.name}`" @click="adjustQuantity(-1)">−</button>
                <label>
                    <input v-model="quantity" type="number" min="0.001" max="9999" step="0.001" inputmode="decimal" :aria-label="`Количество ${good.name}, ${unit}`" :aria-invalid="Boolean(error)" @input="error = ''" @keydown.enter.prevent="addToCart">
                    <span :title="unit">{{ unit }}</span>
                </label>
                <button v-if="!compact" type="button" class="catalog-actions__step" :disabled="validQuantity && Number(quantity) >= 9999" :aria-label="`Увеличить количество ${good.name}`" @click="adjustQuantity(1)">+</button>
            </div>
            <button v-if="canOrder" type="button" class="catalog-actions__cart" :title="`Добавить ${good.name} в корзину`" :aria-label="`Добавить ${good.name} в корзину`" @click="addToCart">
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 3h2l2 10h9l2-7H5 M7 16h.01 M14 16h.01 M10 5v5 M7.5 7.5h5" /></svg><span>В корзину</span>
            </button>
            <Link v-else :href="good.public_url || goodPublicUrl(good)" class="catalog-actions__contact">Подробнее о товаре</Link>
        </div>
        <div class="catalog-actions__links">
            <button v-if="canOrder" type="button" @click="inquire('order')">Заказать сразу</button>
            <button v-if="canOrder" type="button" @click="inquire('bargain')">Торг</button>
            <button type="button" :title="`Задать вопрос о товаре ${good.name}`" @click="inquire('email')">Вопрос</button>
        </div>
        <span v-if="error" class="catalog-actions__error" role="alert">{{ error }}</span>
    </div>
</template>

<style scoped>
.catalog-actions { display: grid; min-width: 0; gap: 8px; }
.catalog-actions__purchase { display: flex; gap: 8px; min-width: 0; }
.catalog-actions__quantity { display: flex; align-items: center; flex: 1 1 48%; min-width: 0; border: 1px solid #d9d8ca; border-radius: 2px; background: #fffefa; }
.catalog-actions__quantity--invalid { border-color: #a10000; }
.catalog-actions__quantity label { display: flex; align-items: baseline; justify-content: center; gap: 3px; flex: 1; min-width: 0; }
.catalog-actions__quantity input { width: 100%; min-width: 28px; padding: 8px 0; border: 0; border-radius: 0; background: transparent; color: #353b2f; font-family: inherit; font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: center; appearance: textfield; box-shadow: none; }
.catalog-actions__quantity input::-webkit-inner-spin-button, .catalog-actions__quantity input::-webkit-outer-spin-button { margin: 0; -webkit-appearance: none; }
.catalog-actions__quantity label > span { flex: 0 1 auto; max-width: 45px; overflow: hidden; color: #7a7f6f; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
.catalog-actions__step { display: grid; place-items: center; flex: 0 0 23px; align-self: stretch; color: #767e6c; font-size: 15px; font-weight: 400; cursor: pointer; }
.catalog-actions__step:hover { background: #eeede3; color: #343c2c; }
.catalog-actions__step:disabled { opacity: .4; cursor: default; }
.catalog-actions__cart, .catalog-actions__contact { display: flex; align-items: center; justify-content: center; gap: 6px; flex: 1 1 52%; padding: 9px 10px; border: 1px solid #800000; border-radius: 2px; background: #800000; color: #fffdf5; font-size: 11px; font-weight: 700; line-height: 1.3; white-space: nowrap; text-decoration: none; cursor: pointer; }
.catalog-actions__cart svg { width: 17px; height: 17px; flex: 0 0 17px; stroke: currentColor; stroke-width: 1.4; stroke-linecap: round; stroke-linejoin: round; }
.catalog-actions__cart:hover, .catalog-actions__contact:hover { background: #650000; border-color: #650000; }
.catalog-actions__links { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px 10px; }
.catalog-actions__links button { padding: 2px 0; color: #727969; font-size: 10px; line-height: 1.5; text-decoration: none; cursor: pointer; }
.catalog-actions__links button:first-child { color: #7e4137; }
.catalog-actions__links button:hover { color: #800000; text-decoration: underline; text-underline-offset: 3px; }
.catalog-actions__error { color: #a10000; font-size: 11px; line-height: 1.4; }
.catalog-actions--compact { display: flex; gap: 10px; align-items: center; position: relative; }
.catalog-actions--compact .catalog-actions__quantity { width: 95px; flex: none; border-radius: 3px; }
.catalog-actions--compact .catalog-actions__quantity input { padding: 3px 0 3px 6px; font-size: 12px; }
.catalog-actions--compact .catalog-actions__quantity label > span { padding-right: 6px; max-width: 50px; }
.catalog-actions--compact .catalog-actions__cart { padding: 5px 8px; border-radius: 3px; }
.catalog-actions--compact .catalog-actions__cart span { display: none; }
.catalog-actions--compact .catalog-actions__links { flex-wrap: nowrap; justify-content: flex-start; gap: 9px; }
.catalog-actions--compact .catalog-actions__links button { font-size: 11px; }
.catalog-actions--compact .catalog-actions__error { position: absolute; top: 100%; z-index: 3; padding: 4px; background: #fff; border: 1px solid #ecc; }
button:focus-visible, input:focus-visible, a:focus-visible { outline: 2px solid #800000; outline-offset: 3px; }
</style>
