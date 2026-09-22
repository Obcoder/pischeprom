<script setup>
import { computed } from 'vue'

const props = defineProps({
    quantity: { type: [Number, String], default: 1 },
    purchase: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:quantity', 'inquiry', 'max'])
const count = computed(() => Math.min(9999, Math.max(1, Math.floor(Number(props.quantity) || 1))))
const total = computed(() => props.purchase.package_price > 0 ? props.purchase.package_price * count.value : null)
const weight = computed(() => Number(props.purchase.package_weight) || null)
const number = value => Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 3 })
const money = value => `${Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ${props.purchase.currency_code === 'RUB' ? '₽' : props.purchase.currency_code || '₽'}`
function update(value) { emit('update:quantity', Math.min(9999, Math.max(1, Math.floor(Number(value) || 1)))) }
</script>

<template>
    <section class="mobile-good-buy" aria-label="Заказ товара">
        <div class="mobile-good-buy__price-row">
            <div>
                <span class="mobile-good-buy__caption">{{ purchase.price ? 'Цена товара' : 'Условия под ваш объём' }}</span>
                <div class="mobile-good-buy__price">{{ purchase.price ? money(purchase.price) : 'Цена по запросу' }}<small v-if="purchase.price"> / {{ purchase.price_unit_label }}</small></div>
                <span v-if="purchase.price" class="mobile-good-buy__caption">{{ purchase.includes_vat ? 'С НДС' : 'НДС уточняется' }}<template v-if="purchase.package_price"> · {{ money(purchase.package_price) }} / уп.</template></span>
            </div>
            <button type="button" class="mobile-good-buy__bargain" @click="emit('inquiry', 'bargain')"><v-icon icon="mdi-handshake-outline" size="21" /><span>Торг<small>Своя цена</small></span></button>
        </div>
        <div class="mobile-good-buy__quantity-row">
            <label for="mobile-product-quantity">Количество<small>{{ weight ? `${number(count * weight)} кг · ${number(weight)} кг в упаковке` : 'В упаковках' }}</small></label>
            <div class="mobile-good-buy__stepper">
                <button type="button" aria-label="Уменьшить количество упаковок" :disabled="count === 1" @click="update(count - 1)">−</button>
                <input id="mobile-product-quantity" :value="quantity" type="number" inputmode="numeric" min="1" max="9999" step="1" @input="emit('update:quantity', $event.target.value)" @change="update($event.target.value)">
                <button type="button" aria-label="Увеличить количество упаковок" :disabled="count === 9999" @click="update(count + 1)">+</button>
            </div>
        </div>
        <button type="button" class="mobile-good-buy__order" @click="emit('inquiry', 'order')"><span><v-icon icon="mdi-basket-outline" size="21" /> Заказать</span><strong>{{ total ? money(total) : 'Оставить заявку' }} <v-icon icon="mdi-arrow-right" size="18" /></strong></button>
        <p class="mobile-good-buy__note">Без регистрации · Доставку согласуем отдельно</p>
        <div class="mobile-good-buy__contacts">
            <button type="button" @click="emit('inquiry', 'email')"><v-icon icon="mdi-email-outline" size="20" /> Написать на email</button>
            <button type="button" class="mobile-good-buy__max" @click="emit('max')"><v-icon icon="mdi-message-text-outline" size="20" /> Написать в MAX</button>
        </div>
    </section>
</template>

<style scoped>
.mobile-good-buy { display: none; }
@media (max-width: 767px) {
    .mobile-good-buy { display: block; padding: 20px 16px 16px; background: #fff; border: 1px solid #ecdedd; border-radius: 16px; }
    .mobile-good-buy__price-row, .mobile-good-buy__quantity-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .mobile-good-buy__caption { color: #897271; font-size: 11px; line-height: 1.5; }
    .mobile-good-buy__price { color: #322323; font-size: 30px; line-height: 1.25; font-weight: 750; margin: 4px 0; letter-spacing: -.7px; }
    .mobile-good-buy__price small { color: #8b7775; font-size: 13px; font-weight: 400; }
    .mobile-good-buy__bargain { display: flex; align-items: center; justify-content: center; flex-shrink: 0; gap: 8px; min-height: 52px; padding: 9px 12px; border: 1px solid #e4c3be; border-radius: 10px; background: #fff5f2; color: #800000; }
    .mobile-good-buy__bargain span { font-size: 13px; font-weight: 700; text-align: left; }
    .mobile-good-buy__bargain small { display: block; margin-top: 3px; font-size: 10px; font-weight: 400; }
    .mobile-good-buy__quantity-row { border-top: 1px solid #f1e8e5; margin-top: 16px; padding: 16px 0; }
    .mobile-good-buy__quantity-row label { font-size: 13px; font-weight: 650; color: #493131; }
    .mobile-good-buy__quantity-row label small { display: block; margin-top: 6px; font-size: 11px; font-weight: 400; color: #8b7775; }
    .mobile-good-buy__stepper { display: flex; flex-shrink: 0; height: 46px; border: 1px solid #e3d3cf; border-radius: 10px; overflow: hidden; }
    .mobile-good-buy__stepper button { width: 44px; color: #800000; font-size: 22px; }
    .mobile-good-buy__stepper button:disabled { opacity: .3; }
    .mobile-good-buy__stepper input { width: 42px; padding: 0; border: 0; background: #fff; font-size: 16px; text-align: center; appearance: textfield; -moz-appearance: textfield; }
    .mobile-good-buy__stepper input::-webkit-inner-spin-button { -webkit-appearance: none; }
    .mobile-good-buy__order { width: 100%; min-height: 52px; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 15px; background: #800000; color: #fff; border-radius: 11px; font-size: 14px; }
    .mobile-good-buy__order span, .mobile-good-buy__order strong { display: inline-flex; align-items: center; gap: 9px; }
    .mobile-good-buy__order strong { font-size: 13px; font-weight: 500; }
    .mobile-good-buy__note { margin: 10px 0 15px; text-align: center; color: #8b7775; font-size: 10px; line-height: 1.5; }
    .mobile-good-buy__contacts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .mobile-good-buy__contacts button { display: flex; align-items: center; justify-content: center; gap: 7px; min-height: 48px; padding: 9px 6px; border-radius: 9px; background: #fff5f2; color: #800000; font-size: 11px; font-weight: 650; }
    .mobile-good-buy__contacts .mobile-good-buy__max { background: #f0effc; color: #5356a1; }
    button:focus-visible, input:focus-visible { outline: 2px solid #a14343; outline-offset: -3px; }
}
@media (max-width: 359px) {
    .mobile-good-buy { padding: 16px 12px 12px; }
    .mobile-good-buy__price { font-size: 25px; }
    .mobile-good-buy__bargain { padding: 8px; gap: 5px; }
    .mobile-good-buy__stepper button { width: 40px; }
    .mobile-good-buy__contacts button { flex-direction: column; gap: 4px; }
}
</style>
