<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { calendarDay } from './delivery-date.js'
import { createOrderOperation, newOrderPayload, orderLineTotal, orderTotals, validateNewOrder } from './order-create.js'

const props = defineProps({ api: { type: Object, required: true }, online: { type: Boolean, default: true } })
const emit = defineEmits(['created', 'cancel', 'busy-change'])
const options = ref({ statuses: [], currency_codes: ['RUB'], default_status_id: null })
const optionsLoading = ref(false)
const optionsReady = ref(false)
const optionsError = ref('')
const customer = ref(null)
const chosenGood = ref(null)
const goodsNotice = ref('')
const saving = ref(false)
const uncertain = ref(false)
const error = ref('')
const errors = ref({})
const errorElement = ref(null)
const confirmCancel = ref(false)
const saved = ref(false)
const initialDay = calendarDay()
const form = reactive({
    entity_id: null, order_status_id: null, contact_telephone_id: null,
    delivery_date: initialDay, building_ids: [], building_apartments: {},
    preferred_delivery_time: '', internal_comment: '', items: [],
})
let disposed = false
let lineKey = 0
const operation = createOrderOperation(props.api)
const disabled = computed(() => saving.value || uncertain.value || !optionsReady.value || saved.value)
const totals = computed(() => orderTotals(form.items))
const addresses = computed(() => customer.value?.buildings || [])
const deliveryBuilding = computed({
    get: () => form.building_ids[0] ?? null,
    set: id => { form.building_ids = id ? [id] : [] },
})
const selectedAddresses = computed(() => addresses.value.filter(building => form.building_ids.includes(building.id)))
const telephones = computed(() => customer.value?.telephones || [])
const currencies = computed(() => [...new Set([...options.value.currency_codes, ...form.items.map(item => item.currency_code)].filter(Boolean))])
const validationMessages = computed(() => [...new Set(Object.values(errors.value).flat())])
const dirty = computed(() => Boolean(form.entity_id || form.items.length || form.delivery_date !== initialDay
    || form.preferred_delivery_time || form.internal_comment || (optionsReady.value && form.order_status_id !== options.value.default_status_id)))

function remoteOptions(fetcher, selected) {
    const state = reactive({ search: '', items: [], loading: false, error: '', page: 0, lastPage: 1 })
    let request = 0
    let timer
    async function load(more = false) {
        clearTimeout(timer)
        if (!props.online || disabled.value || (more && state.loading)) return
        const current = ++request
        const query = String(state.search || '').trim()
        const page = more ? state.page + 1 : 1
        state.loading = true
        state.error = ''
        if (!more) state.items = selected.value ? [selected.value] : []
        try {
            const result = await fetcher({ search: query, page, per_page: 20 })
            if (disposed || current !== request) return
            const items = more ? [...state.items, ...result.data] : [...(selected.value ? [selected.value] : []), ...result.data]
            state.items = [...new Map(items.map(item => [item.id, item])).values()]
            state.page = result.meta?.current_page || page
            state.lastPage = result.meta?.last_page || page
        } catch (failure) {
            if (!disposed && current === request) state.error = failure.message
        } finally {
            if (!disposed && current === request) state.loading = false
        }
    }
    watch(() => state.search, value => {
        clearTimeout(timer)
        request += 1
        state.loading = false
        if (selected.value && value === selected.value.name) return
        timer = setTimeout(() => load(), 300)
    })
    onBeforeUnmount(() => { clearTimeout(timer); request += 1 })
    return { state, load }
}
const customers = remoteOptions(query => props.api.orderCustomers(query), customer)
const goods = remoteOptions(query => props.api.orderGoods(query), chosenGood)

function errorFor(field) {
    const value = errors.value[field]
    return Array.isArray(value) ? value : value ? [value] : []
}
function addressTitle(building) {
    return building.full_address || [typeof building.city === 'string' ? building.city : building.city?.name, building.address].filter(Boolean).join(', ')
}
function money(value, currency = 'RUB', digits = 2) {
    if (value === null) return '—'
    try { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency, maximumFractionDigits: digits }).format(value) }
    catch { return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(value)} ${currency}` }
}
function unitLabel(good) { return good.measurement?.unit_label || good.measure_name || 'ед.' }
function goodProps(good) { return { disabled: form.items.some(item => item.good_id === good.id) } }
function priceLabel(price) {
    return `${price.price_type?.name || 'Цена'} · ${money(price.unit_price ?? price.price_gross, price.currency_code || 'RUB', 4)}`
}

async function loadOptions() {
    if (optionsLoading.value || !props.online) return
    optionsLoading.value = true
    optionsError.value = ''
    try {
        const result = await props.api.orderCreateOptions()
        if (disposed) return
        options.value = { statuses: result.statuses || [], currency_codes: result.currency_codes?.length ? result.currency_codes : ['RUB'], default_status_id: result.default_status_id }
        if (!form.order_status_id) form.order_status_id = result.default_status_id
        optionsReady.value = true
        await Promise.allSettled([customers.load(), goods.load()])
    } catch (failure) {
        if (!disposed) optionsError.value = failure.message
    } finally {
        if (!disposed) optionsLoading.value = false
    }
}

function chooseCustomer(value) {
    customer.value = value
    form.entity_id = value?.id || null
    form.contact_telephone_id = value?.telephones?.[0]?.id || null
    form.building_ids = value?.buildings?.length === 1 ? [value.buildings[0].id] : []
    form.building_apartments = Object.fromEntries((value?.buildings || []).map(building => [building.id, building.apartment_id || null]))
    errors.value = {}
}
async function addGood(good) {
    if (!good || disabled.value) return
    if (form.items.some(item => item.good_id === good.id)) {
        goodsNotice.value = 'Товар уже добавлен. Измените его количество в составе заказа.'
        await nextTick()
        chosenGood.value = null
        goods.state.search = ''
        return
    }
    const price = good.prices?.[0]
    form.items.push({
        key: ++lineKey, good_id: good.id, name: good.name, measure_id: good.measure_id ?? good.measurement?.measure_id ?? null,
        measurement: good.measurement, unit_label: unitLabel(good), quantity: '1',
        unit_price: String(price?.unit_price ?? price?.price_gross ?? ''),
        currency_code: price?.currency_code || 'RUB', prices: good.prices || [], price_id: price?.id || null,
    })
    errors.value = {}
    goodsNotice.value = `${good.name} добавлен в заказ.`
    await nextTick()
    chosenGood.value = null
    goods.state.search = ''
}
function choosePrice(item, id) {
    const price = item.prices.find(candidate => candidate.id === id)
    if (!price) return
    item.unit_price = String(price.unit_price ?? price.price_gross ?? '')
    item.currency_code = price.currency_code || 'RUB'
}
function removeItem(index) {
    form.items.splice(index, 1)
    errors.value = {}
}
async function focusError() {
    await nextTick()
    errorElement.value?.scrollIntoView({ block: 'center', behavior: 'smooth' })
    errorElement.value?.focus({ preventScroll: true })
}
async function submit() {
    if (saving.value || saved.value || !props.online || !optionsReady.value) return
    errors.value = uncertain.value ? {} : validateNewOrder(form)
    if (Object.keys(errors.value).length) {
        error.value = 'Проверьте данные заказа.'
        await focusError()
        return
    }
    saving.value = true
    emit('busy-change', true)
    error.value = ''
    let createdOrder = null
    try {
        const result = await operation.send(newOrderPayload(form))
        if (disposed) return
        saved.value = true
        uncertain.value = false
        createdOrder = result.data
    } catch (failure) {
        if (disposed || failure.status === 401) return
        uncertain.value = operation.hasPending()
        errors.value = failure.errors || {}
        error.value = uncertain.value
            ? 'Ответ о создании не получен. Заказ мог сохраниться. Повторите подтверждение: приложение проверит ту же операцию и не создаст второй заказ.'
            : failure.message
        await focusError()
    } finally {
        saving.value = false
        emit('busy-change', false)
        if (createdOrder && !disposed) emit('created', createdOrder)
    }
}
function requestClose() {
    if (saving.value) return false
    if (dirty.value || uncertain.value) confirmCancel.value = true
    else emit('cancel')
    return true
}
function cancel() {
    confirmCancel.value = false
    emit('cancel', { checkOrders: uncertain.value })
}
watch(() => props.online, connected => {
    if (!connected) return
    if (!optionsReady.value) loadOptions()
    else {
        if (customers.state.error) customers.load()
        if (goods.state.error) goods.load()
    }
})
onMounted(loadOptions)
onBeforeUnmount(() => { disposed = true })
defineExpose({ requestClose })
</script>

<template>
    <section class="create-order" aria-labelledby="create-order-title">
        <header class="create-heading">
            <div><div class="eyebrow">ПРОДАЖИ И ДОСТАВКА</div><h1 id="create-order-title">Новый заказ</h1></div>
            <v-btn icon="mdi-close" variant="text" :disabled="saving" aria-label="Закрыть создание заказа" @click="requestClose" />
        </header>
        <p class="create-description">Выберите покупателя, дату доставки и товары.</p>
        <v-progress-linear v-if="optionsLoading" indeterminate color="primary" class="mb-4" aria-label="Загрузка формы заказа" />
        <v-alert v-if="optionsError" type="error" class="mb-4" role="alert">{{ optionsError }}<v-btn variant="text" :disabled="!online" class="mt-2" @click="loadOptions">Повторить загрузку</v-btn></v-alert>
        <form aria-label="Создание заказа" novalidate @submit.prevent="submit">
            <div v-if="error" ref="errorElement" class="create-error" :class="{ uncertain }" role="alert" tabindex="-1">
                <p>{{ error }}</p>
                <ul v-if="validationMessages.length"><li v-for="message in validationMessages" :key="message">{{ message }}</li></ul>
                <v-btn v-if="uncertain" color="primary" class="mt-3" :disabled="saving || !online" :loading="saving" @click="submit">Повторить подтверждение</v-btn>
            </div>

            <v-card class="create-section">
                <h2><v-icon icon="mdi-account-outline" size="21" />Покупатель</h2>
                <v-autocomplete :model-value="customer" v-model:search="customers.state.search" :items="customers.state.items" item-title="name" item-value="id" return-object no-filter clearable
                    label="Найти покупателя" placeholder="Имя, организация или телефон" :loading="customers.state.loading" :disabled="disabled" :error-messages="errorFor('entity_id')" hide-details="auto" class="mb-3"
                    :no-data-text="customers.state.loading ? 'Поиск покупателей…' : online ? 'Покупатели не найдены' : 'Для поиска нужно подключение'" @update:model-value="chooseCustomer">
                    <template #item="{ props: itemProps, item }"><v-list-item v-bind="itemProps" :subtitle="item.raw.telephones?.map(phone => phone.number).join(' · ')" /></template>
                    <template #append-item><v-btn v-if="customers.state.page < customers.state.lastPage" block variant="text" :loading="customers.state.loading" @click.stop="customers.load(true)">Ещё покупатели</v-btn></template>
                </v-autocomplete>
                <div v-if="customers.state.error" class="lookup-error" role="alert">{{ customers.state.error }}<v-btn variant="text" size="small" :disabled="!online" @click="customers.load()">Повторить поиск</v-btn></div>
                <template v-if="customer">
                    <v-select v-if="telephones.length" v-model="form.contact_telephone_id" :items="telephones" item-title="number" item-value="id" label="Телефон для связи" :disabled="disabled" :error-messages="errorFor('contact_telephone_id')" hide-details="auto" class="mb-3" />
                    <p v-else class="create-hint mb-3">У покупателя не указан телефон.</p>
                    <v-select v-if="addresses.length" v-model="deliveryBuilding" :items="addresses" :item-title="addressTitle" item-value="id" label="Адрес доставки" clearable :disabled="disabled" :error-messages="errorFor('building_ids')" hide-details="auto" class="mb-3" />
                    <p v-else class="create-hint mb-3">У покупателя нет сохранённых адресов. Заказ будет создан без адреса доставки.</p>
                    <div v-for="building in selectedAddresses" :key="building.id" class="apartment-field">
                        <template v-if="building.apartments?.length">
                            <p class="create-hint">{{ addressTitle(building) }}</p>
                            <v-select v-model="form.building_apartments[building.id]" :items="building.apartments" :item-title="apartment => apartment.label || `${apartment.type || 'Квартира'} ${apartment.number}`" item-value="id" label="Квартира / помещение" clearable :disabled="disabled" :error-messages="errorFor(`building_apartments.${building.id}`)" hide-details="auto" />
                        </template>
                    </div>
                </template>
            </v-card>

            <v-card class="create-section">
                <h2><v-icon icon="mdi-calendar-outline" size="21" />Доставка</h2>
                <div class="create-day-buttons" role="group" aria-label="Быстрый выбор даты доставки">
                    <v-btn v-for="day in [{ offset: 0, label: 'Сегодня' }, { offset: 1, label: 'Завтра' }]" :key="day.offset" :variant="form.delivery_date === calendarDay(day.offset) ? 'tonal' : 'outlined'" color="primary" :disabled="disabled" :aria-pressed="form.delivery_date === calendarDay(day.offset)" @click="form.delivery_date = calendarDay(day.offset)">{{ day.label }}</v-btn>
                </div>
                <v-text-field v-model="form.delivery_date" type="date" label="Дата доставки" min="1000-01-01" max="9999-12-31" :disabled="disabled" :error-messages="errorFor('delivery_date')" hide-details="auto" class="mb-3" />
                <v-text-field v-model="form.preferred_delivery_time" label="Удобное время доставки" placeholder="Например, с 10:00 до 14:00" maxlength="255" :disabled="disabled" :error-messages="errorFor('preferred_delivery_time')" hide-details="auto" />
            </v-card>

            <v-card class="create-section">
                <h2><v-icon icon="mdi-package-variant" size="21" />Товары<span class="create-count">{{ form.items.length }} поз.</span></h2>
                <v-autocomplete v-model="chosenGood" v-model:search="goods.state.search" :items="goods.state.items" :item-props="goodProps" item-title="name" item-value="id" return-object no-filter clearable
                    label="Добавить товар" placeholder="Название товара" :loading="goods.state.loading" :disabled="disabled" :error-messages="errorFor('items')" hide-details="auto"
                    :no-data-text="goods.state.loading ? 'Поиск товаров…' : online ? 'Товары не найдены' : 'Для поиска нужно подключение'" @update:model-value="addGood">
                    <template #item="{ props: itemProps, item }"><v-list-item v-bind="itemProps" :subtitle="itemProps.disabled ? 'Уже в заказе — измените количество в позиции' : [unitLabel(item.raw), item.raw.prices?.length ? priceLabel(item.raw.prices[0]) : 'Укажите цену при добавлении'].join(' · ')" /></template>
                    <template #append-item><v-btn v-if="goods.state.page < goods.state.lastPage" block variant="text" :loading="goods.state.loading" @click.stop="goods.load(true)">Ещё товары</v-btn></template>
                </v-autocomplete>
                <div v-if="goods.state.error" class="lookup-error" role="alert">{{ goods.state.error }}<v-btn variant="text" size="small" :disabled="!online" @click="goods.load()">Повторить поиск</v-btn></div>
                <p v-if="goodsNotice" class="create-hint mt-3" role="status">{{ goodsNotice }}</p>
                <p v-if="!form.items.length" class="create-hint mt-3">Выберите товар из поиска. После добавления укажите количество.</p>
                <article v-for="(item, index) in form.items" :key="item.key" class="create-line">
                    <div class="create-line-heading"><h3>{{ index + 1 }}. {{ item.name }}</h3><v-btn icon="mdi-delete-outline" variant="text" :disabled="disabled" :aria-label="`Удалить ${item.name}`" @click="removeItem(index)" /></div>
                    <div class="create-line-fields">
                        <v-text-field v-model="item.quantity" :label="`Количество, ${item.unit_label}`" inputmode="decimal" :disabled="disabled" :error-messages="errorFor(`items.${index}.quantity`)" hide-details="auto" />
                        <v-text-field v-model="item.unit_price" :label="`Цена за ${item.unit_label}`" inputmode="decimal" :disabled="disabled" :error-messages="errorFor(`items.${index}.unit_price`)" hide-details="auto" @update:model-value="item.price_id = null" />
                    </div>
                    <div class="create-price-fields">
                        <v-select v-if="item.prices.length > 1" v-model="item.price_id" :items="item.prices" :item-title="priceLabel" item-value="id" label="Тип цены" :disabled="disabled" hide-details="auto" @update:model-value="value => choosePrice(item, value)" />
                        <v-select v-model="item.currency_code" :items="currencies" label="Валюта" :disabled="disabled || currencies.length < 2" :error-messages="errorFor(`items.${index}.currency_code`)" hide-details="auto" />
                    </div>
                    <div class="create-line-total"><span>Сумма позиции</span><strong>{{ money(orderLineTotal(item), item.currency_code, 4) }}</strong></div>
                </article>
            </v-card>

            <v-card class="create-section">
                <h2><v-icon icon="mdi-text-box-outline" size="21" />Дополнительно</h2>
                <v-select v-model="form.order_status_id" :items="options.statuses" item-title="name" item-value="id" label="Статус заказа" :disabled="disabled" :error-messages="errorFor('order_status_id')" hide-details="auto" class="mb-3" />
                <v-textarea v-model="form.internal_comment" label="Внутренний комментарий" rows="2" auto-grow maxlength="5000" :disabled="disabled" :error-messages="errorFor('internal_comment')" hide-details="auto" />
            </v-card>

            <div class="create-summary" aria-live="polite">
                <span>Итого · {{ form.items.length }} поз.</span>
                <div><strong v-for="(total, currency) in totals" :key="currency">{{ money(total, currency) }}</strong><strong v-if="!Object.keys(totals).length">—</strong></div>
            </div>
            <v-btn type="submit" color="primary" size="x-large" block :loading="saving" :disabled="!online || !optionsReady || saved" prepend-icon="mdi-check">{{ uncertain ? 'Повторить подтверждение' : 'Создать заказ' }}</v-btn>
            <v-btn variant="text" size="large" block :disabled="saving" class="mt-2" @click="requestClose">Отмена</v-btn>
        </form>

        <v-dialog v-model="confirmCancel" max-width="430" :persistent="saving">
            <v-card class="create-cancel">
                <h2>{{ uncertain ? 'Вернуться к заказам?' : 'Закрыть новый заказ?' }}</h2>
                <p>{{ uncertain ? 'Заказ мог сохраниться на сервере. Проверьте список заказов перед созданием ещё одного. Можно остаться в форме и повторить подтверждение.' : 'Введённые данные не сохранятся.' }}</p>
                <v-btn color="primary" size="large" block @click="confirmCancel = false">{{ uncertain ? 'Остаться и проверить' : 'Продолжить создание' }}</v-btn>
                <v-btn variant="text" size="large" block class="mt-2" @click="cancel">{{ uncertain ? 'Проверить список заказов' : 'Закрыть без сохранения' }}</v-btn>
            </v-card>
        </v-dialog>
    </section>
</template>

<style scoped>
.create-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.create-heading h1 { font-size: 30px; }
.create-description { margin: 12px 0 22px; font-size: 14px; line-height: 1.55; color: #6b786f; }
.create-section { padding: 18px; margin-bottom: 16px; border: 1px solid #e1e7df; overflow: visible; }
.create-section h2 { display: flex; align-items: center; gap: 8px; font-size: 17px; margin-bottom: 18px; }
.create-section :deep(.v-field) { font-size: 14px; }
.create-section :deep(.v-select__selection-text), .create-section :deep(.v-chip__content) { white-space: normal; overflow-wrap: anywhere; }
.create-section :deep(.v-chip) { height: auto; min-height: 28px; padding-block: 5px; max-width: 100%; }
.create-section :deep(.v-field__input) { min-width: 0; }
.create-hint { color: #738376; font-size: 12px; line-height: 1.6; }
.apartment-field { display: grid; gap: 8px; margin-top: 12px; }
.create-day-buttons { display: flex; gap: 8px; margin-bottom: 18px; }
.create-count { margin-left: auto; font-size: 12px; font-weight: 500; color: #7c8a7f; }
.create-line { border-top: 1px solid #e7ece4; margin-top: 18px; padding-top: 12px; }
.create-line-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.create-line-heading h3 { font-size: 15px; line-height: 1.5; padding-top: 10px; overflow-wrap: anywhere; }
.create-line-fields, .create-price-fields { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px; }
.create-price-fields { margin-top: 12px; grid-template-columns: minmax(0, 1.4fr) minmax(0, .8fr); }
.create-price-fields > :only-child { grid-column: 2; }
.create-line-total { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; font-size: 13px; color: #748473; }
.create-line-total strong { color: #315d4a; text-align: right; }
.create-summary { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 12px 0 22px; }
.create-summary > span { color: #6c7d70; font-size: 14px; padding-top: 5px; }
.create-summary > div { display: grid; gap: 4px; text-align: right; }
.create-summary strong { color: #284933; font-size: 23px; }
.create-error { padding: 16px; border: 1px solid #ecc5c1; border-radius: 12px; margin-bottom: 18px; background: #fff4f2; color: #923a32; font-size: 13px; line-height: 1.6; }
.create-error.uncertain { background: #fff8ea; color: #845813; border-color: #ecd9b4; }
.create-error ul { margin-top: 8px; padding-left: 18px; }
.lookup-error { color: #923a32; font-size: 12px; margin-top: 8px; }
.create-cancel { padding: 24px; }
.create-cancel p { margin: 14px 0 22px; font-size: 14px; line-height: 1.6; color: #6b786f; }
@media (max-width: 360px) { .create-section { padding: 14px; } .create-line-fields { gap: 8px; } .create-price-fields { grid-template-columns: minmax(0, 1fr); } .create-price-fields > :only-child { grid-column: 1; } }
</style>
