<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
const props = defineProps({ prices: { type: Array, default: () => [] }, profile: { type: Object, required: true } })
const entityId = ref(null), selected = ref({}), checkout = ref(false), busy = ref(false), error = ref(''), success = ref('')
const address = ref(props.profile.delivery_address || ''), phone = ref(props.profile.phone || ''), time = ref('')
const entities = computed(() => [...new Map(props.prices.map(price => [price.entity_id, { id: price.entity_id, name: price.entity_name }])).values()])
const visible = computed(() => props.prices.filter(price => price.entity_id === entityId.value))
const chosen = computed(() => visible.value.filter(price => selected.value[price.id] !== undefined))
const totals = computed(() => chosen.value.reduce((result, price) => { result[price.currency_code] = (result[price.currency_code] || 0) + Number(price.price_gross) * Number(selected.value[price.id] || 0); return result }, {}))
const money = value => Number(value).toLocaleString('ru-RU', { maximumFractionDigits: 4 })
watch(entities, items => { if (!items.some(entity => entity.id === entityId.value)) entityId.value = items[0]?.id || null }, { immediate: true })
watch(entityId, () => { selected.value = {}; checkout.value = false; error.value = '' })
watch(() => props.prices, () => { selected.value = {}; checkout.value = false })
function toggle(price) {
    if (selected.value[price.id] === undefined) selected.value[price.id] = 1
    else delete selected.value[price.id]
    error.value = ''; success.value = ''
}
async function submit() {
    if (busy.value || !chosen.value.length) return
    busy.value = true; error.value = ''; success.value = ''
    try {
        const response = await axios.post('/dashboard/entity-orders', {
            entity_id: entityId.value,
            items: chosen.value.map(price => ({ good_id: price.good_id, price_id: price.id, price_revision: price.revision, quantity: selected.value[price.id], measure_id: price.measurement.measure_id, measurement: price.measurement })),
            delivery_address: address.value, preferred_delivery_time: time.value, customer_phone: phone.value,
        })
        success.value = `Заказ ${response.data.order.number} создан по индивидуальным ценам.`
        selected.value = {}; checkout.value = false
        router.reload({ only: ['orders', 'privatePrices'], preserveScroll: true })
    } catch (failure) {
        error.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось оформить заказ. Повторите попытку.'
    } finally { busy.value = false }
}
</script>

<template>
    <section class="cabinet-prices" aria-label="Индивидуальные цены">
        <header><div><h2>Индивидуальные цены</h2><p>Условия для вашей организации. Выберите товары для отдельного заказа из личного кабинета.</p></div><button :disabled="busy" @click="router.reload({ only: ['privatePrices'], preserveScroll: true })">Обновить цены</button></header>
        <p v-if="success" class="success" role="status">{{ success }}</p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <p v-if="!prices.length" class="empty">Назначенные вашей организации цены появятся здесь после открытия доступа менеджером.</p>
        <template v-else>
            <label class="entity-label">Организация <select v-model="entityId" :disabled="busy"><option v-for="entity in entities" :key="entity.id" :value="entity.id">{{ entity.name }}</option></select></label>
            <div class="table-scroll"><table><thead><tr><th aria-label="Выбрать"></th><th>Товар</th><th>Единица</th><th class="numeric">Без НДС</th><th>НДС</th><th class="numeric">С НДС</th><th>Действует по</th><th>Количество</th><th class="numeric">Сумма</th></tr></thead><tbody><tr v-for="price in visible" :key="price.id" :class="{ selected: selected[price.id] !== undefined }"><td><input type="checkbox" :checked="selected[price.id] !== undefined" :disabled="busy" :aria-label="`Выбрать ${price.good_name}`" @change="toggle(price)"></td><td>{{ price.good_name }}</td><td>{{ price.measurement.unit_label }}</td><td class="numeric">{{ money(price.price_net) }}</td><td>{{ money(price.vat_rate) }}%</td><td class="numeric"><strong>{{ money(price.price_gross) }} {{ price.currency_code }}</strong></td><td>{{ price.valid_to || 'Без окончания' }}</td><td><input v-if="selected[price.id] !== undefined" v-model="selected[price.id]" :disabled="busy" type="number" min="0.001" max="9999" step="0.001" :aria-label="`Количество ${price.good_name}`" class="quantity"><span v-else>—</span></td><td class="numeric">{{ selected[price.id] !== undefined ? `${money(price.price_gross * Number(selected[price.id] || 0))} ${price.currency_code}` : '—' }}</td></tr></tbody></table></div>
            <div v-if="chosen.length" class="checkout-bar"><div><strong>{{ chosen.length }} поз.</strong><span v-for="(total, currency) in totals" :key="currency">{{ money(total) }} {{ currency }}</span></div><button class="primary" :disabled="busy" @click="checkout = !checkout">Оформить заказ</button></div>
            <form v-if="checkout && chosen.length" class="checkout-form" @submit.prevent="submit"><label>Адрес доставки<input v-model="address" :disabled="busy" required maxlength="3000" autocomplete="street-address"></label><label>Контактный телефон<input v-model="phone" :disabled="busy" required maxlength="64" type="tel" autocomplete="tel"></label><label>Удобное время доставки<input v-model="time" :disabled="busy" required maxlength="255" placeholder="Дата и интервал времени"></label><p>Заказ оформляется для {{ entities.find(entity => entity.id === entityId)?.name }}. Точные условия доступны в личном кабинете.</p><div><button class="primary" :disabled="busy">{{ busy ? 'Оформление…' : 'Подтвердить заказ' }}</button><button type="button" :disabled="busy" @click="checkout = false">Отмена</button></div></form>
        </template>
    </section>
</template>

<style scoped>
.cabinet-prices { margin: 20px 0; padding: 18px; border: 1px solid #d6d3d9; border-radius: 8px; background: #fff; color: #38343e; font: 12px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
header { display: flex; gap: 16px; justify-content: space-between; align-items: start; margin-bottom: 14px; } h2 { font-size: 18px; font-weight: 600; margin: 0 0 3px; } p { color: #79737e; margin: 0; } button, input, select { font: inherit; } button { border: 1px solid #d6d3d9; background: #fff; border-radius: 5px; padding: 5px 11px; cursor: pointer; white-space: nowrap; } button:disabled { opacity: .5; } .primary { color: white; background: #4266b0; border-color: #4266b0; } input:not([type=checkbox]), select { border: 1px solid #d6d3d9; border-radius: 4px; padding: 5px 7px; background: white; max-width: 100%; } input[type=checkbox] { accent-color: #4266b0; } .entity-label { display: flex; gap: 12px; align-items: center; margin-bottom: 10px; } .table-scroll { overflow-x: auto; border: 1px solid #d6d3d9; border-radius: 5px; } table { width: 100%; min-width: 750px; border-collapse: collapse; font-size: 11px; } th { background: #f2f1f4; color: #77727c; font-size: 10px; font-weight: 600; text-align: left; white-space: nowrap; } th, td { padding: 6px 8px; border-bottom: 1px solid #e7e5ea; } tr:nth-child(even) { background: #faf9fb; } tr.selected { background: #f2f6ff; } .numeric { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; } .quantity { width: 85px; } .checkout-bar { display: flex; justify-content: space-between; gap: 10px; padding-top: 12px; } .checkout-bar span { margin-left: 12px; } .checkout-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; background: #faf9fc; padding: 15px; margin-top: 12px; border: 1px solid #e7e5ea; border-radius: 5px; } .checkout-form label { display: grid; gap: 4px; } .checkout-form p, .checkout-form > div { grid-column: 1 / -1; } .checkout-form button { margin-right: 6px; } .empty { padding: 14px 0; } .error, .success { padding: 10px; margin-bottom: 10px; border-radius: 4px; } .error { background: #fff0f2; color: #aa2938; } .success { background: #eaf6ee; color: #327049; } :is(button, input, select):focus-visible { outline: 2px solid #739ade; outline-offset: 2px; }
@media(max-width: 700px) { header { flex-direction: column; } .cabinet-prices { padding: 12px; } .checkout-form { grid-template-columns: 1fr; } }
</style>
