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
            <label v-if="canOrder" class="catalog-actions__quantity">
                <input v-model="quantity" type="number" min="0.001" max="9999" step="0.001" inputmode="decimal" :aria-label="`Количество ${good.name}, ${unit}`" :aria-invalid="Boolean(error)" @input="error = ''" @keydown.enter.prevent="addToCart">
                <span>{{ unit }}</span>
            </label>
            <button v-if="canOrder" type="button" class="catalog-actions__cart" :title="`Добавить ${good.name} в корзину`" @click="addToCart">
                <v-icon icon="mdi-cart-plus" size="17" /><span>В корзину</span>
            </button>
            <Link v-else :href="good.public_url || goodPublicUrl(good)" class="catalog-actions__contact">Подробнее о товаре</Link>
        </div>
        <div v-if="canOrder" class="catalog-actions__links">
            <button v-if="canOrder" type="button" @click="inquire('order')">Заказать сразу</button>
            <button v-if="canOrder" type="button" @click="inquire('bargain')">Торг</button>
            <button type="button" :title="`Задать вопрос о товаре ${good.name}`" @click="inquire('email')">Вопрос</button>
        </div>
        <span v-if="error" class="catalog-actions__error" role="alert">{{ error }}</span>
    </div>
</template>

<style scoped>
.catalog-actions { display: grid; gap: 7px; }
.catalog-actions__purchase { display: flex; gap: 7px; }
.catalog-actions__quantity { display: flex; align-items: center; flex: 1; min-width: 0; border: 1px solid #dcdad3; border-radius: 7px; background: #fff; }
.catalog-actions__quantity input { width: 100%; min-width: 52px; padding: 8px 0 8px 9px; color: #272c26; font-size: 13px; font-weight: 650; outline-offset: 2px; }
.catalog-actions__quantity span { padding: 0 8px 0 4px; font-size: 11px; color: #6d7369; white-space: nowrap; }
.catalog-actions__cart, .catalog-actions__contact { display: flex; align-items: center; justify-content: center; gap: 6px; flex: 1; padding: 8px 12px; border-radius: 7px; background: #800000; color: white; font-size: 12px; font-weight: 700; white-space: nowrap; text-decoration: none; }
.catalog-actions__cart:hover, .catalog-actions__contact:hover { background: #650000; }
.catalog-actions__links { display: flex; align-items: center; gap: 14px; }
.catalog-actions__links button { color: #746b62; font-size: 11px; text-decoration: underline; text-underline-offset: 3px; }
.catalog-actions__links button:hover { color: #800000; }
.catalog-actions__error { color: #a10000; font-size: 11px; }
.catalog-actions--compact { display: flex; gap: 10px; align-items: center; position: relative; }
.catalog-actions--compact .catalog-actions__quantity { width: 95px; flex: none; border-radius: 4px; }
.catalog-actions--compact .catalog-actions__quantity input { padding-block: 3px; font-size: 12px; }
.catalog-actions--compact .catalog-actions__cart { padding: 5px 8px; border-radius: 4px; }
.catalog-actions--compact .catalog-actions__cart span { display: none; }
.catalog-actions--compact .catalog-actions__links { gap: 9px; }
.catalog-actions--compact .catalog-actions__error { position: absolute; top: 100%; z-index: 3; padding: 4px; background: #fff; border: 1px solid #ecc; }
button:focus-visible, input:focus-visible { outline: 2px solid #800000; outline-offset: 3px; }
</style>
