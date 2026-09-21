<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Capacitor, CapacitorHttp } from '@capacitor/core'
import { App as NativeApp } from '@capacitor/app'
import { ApiError, createApi } from './api.js'
import { createShipmentOperation, validatePreparation } from './shipment.js'
import DeliveryContacts from './DeliveryContacts.vue'
import DeliveryMap from './DeliveryMap.vue'
import { calendarDay, deliveryDateQuery, formatDeliveryDate } from './delivery-date.js'

const user = ref(null)
const abilities = ref([])
const email = ref('')
const password = ref('')
const twoFactorCode = ref('')
const useRecoveryCode = ref(false)
const loggingIn = ref(false)
const loggingOut = ref(false)
const loginError = ref('')
const configError = ref('')
const online = ref(navigator.onLine)
const orders = ref([])
const search = ref('')
const filter = ref('awaiting')
const deliveryDate = ref('')
const deliveryUnscheduled = ref(false)
const deliveryDateDraft = ref('')
const savingDeliveryDate = ref(false)
const screen = ref('list')
const listLoading = ref(false)
const listError = ref('')
const meta = ref({ total: 0, current_page: 1, last_page: 1 })
const selectedId = ref(null)
const order = ref(null)
const orderLoading = ref(false)
const orderError = ref('')
const rows = ref([])
const stale = ref(false)
const preparing = ref(false)
const shipping = ref(false)
const uncertainShipment = ref(false)
const confirmShipment = ref(false)
const notification = ref('')
const showNotification = ref(false)
let listRequest = 0
let detailRequest = 0
let session = 0
let searchTimer
let backListener
let api
let shipment

function clearSession(message = '') {
    session += 1
    listRequest += 1
    detailRequest += 1
    api?.clearToken()
    shipment?.clear()
    user.value = null
    abilities.value = []
    password.value = ''
    twoFactorCode.value = ''
    orders.value = []
    order.value = null
    selectedId.value = null
    screen.value = 'list'
    confirmShipment.value = false
    loginError.value = message
    listLoading.value = false
    orderLoading.value = false
}

try {
    api = createApi({
        baseUrl: import.meta.env.VITE_MOBILE_API_URL,
        nativeRequest: Capacitor.isNativePlatform() ? options => CapacitorHttp.request(options) : null,
        onUnauthorized: () => clearSession('Сессия завершена. Войдите снова.'),
    })
    shipment = createShipmentOperation(api)
} catch (error) {
    configError.value = error.message
}

const filters = [
    { value: 'all', label: 'Все' },
    { value: 'today', label: 'Созданы сегодня' },
    { value: 'awaiting', label: 'К сборке' },
    { value: 'ready', label: 'К отгрузке' },
    { value: 'shipped', label: 'Отгружены' },
]
const statuses = {
    awaiting: { label: 'Ожидает сборки', color: 'warning', icon: 'mdi-package-variant' },
    ready: { label: 'Готов к отгрузке', color: 'primary', icon: 'mdi-package-variant-closed-check' },
    shipped: { label: 'Отгружен', color: 'primary', icon: 'mdi-check-circle-outline' },
}
const status = value => statuses[value] || { label: 'Требует проверки', color: 'warning', icon: 'mdi-alert-circle-outline' }
const inDetail = computed(() => selectedId.value !== null)
const busy = computed(() => preparing.value || shipping.value || savingDeliveryDate.value || loggingOut.value)
const deliveryDateDirty = computed(() => (deliveryDateDraft.value || '') !== (order.value?.delivery_date || ''))
const canSaveDeliveryDate = computed(() => order.value && deliveryDateDirty.value && !busy.value && !stale.value && online.value && !orderLoading.value && !uncertainShipment.value)
const checkedCount = computed(() => rows.value.filter(row => row.checked).length)
const preparationError = computed(() => validatePreparation(order.value, rows.value))
const canPrepare = computed(() => order.value?.can_prepare && !preparationError.value && !deliveryDateDirty.value && !stale.value && online.value && !orderLoading.value)
const canShip = computed(() => order.value?.can_ship && !deliveryDateDirty.value && !stale.value && online.value && !orderLoading.value)
const initials = computed(() => (user.value?.name || 'Сотрудник').split(' ').slice(0, 2).map(part => part[0]).join('').toUpperCase())
const date = value => value && !Number.isNaN(new Date(value).getTime()) ? new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : 'Дата не указана'
const quantity = value => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 6 }).format(Number(value || 0))
function money(value, currency = 'RUB') {
    try { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: currency || 'RUB', maximumFractionDigits: 2 }).format(Number(value || 0)) }
    catch { return `${quantity(value)} ${currency || ''}`.trim() }
}
function notify(message) {
    notification.value = message
    showNotification.value = true
}

async function login() {
    if (loggingIn.value || !online.value || !email.value.trim() || !password.value || !api) return
    loggingIn.value = true
    loginError.value = ''
    try {
        const result = await api.login({
            email: email.value.trim(), password: password.value,
            device_name: Capacitor.isNativePlatform() ? 'Пищепром · Android' : 'Пищепром · Мобильный браузер',
            ...(twoFactorCode.value.trim() ? { [useRecoveryCode.value ? 'recovery_code' : 'two_factor_code']: twoFactorCode.value.trim() } : {}),
        })
        if (!result.token || !result.user) throw new Error('Сервер не подтвердил вход. Обратитесь к администратору.')
        api.setToken(result.token)
        user.value = result.user
        abilities.value = result.abilities || []
        password.value = ''
        twoFactorCode.value = ''
        await loadOrders()
    } catch (error) {
        loginError.value = error.message
    } finally {
        loggingIn.value = false
    }
}

async function logout() {
    if (busy.value) return
    loggingOut.value = true
    try {
        await api.logout()
        clearSession()
    } catch (error) {
        if (error.status !== 401) notify('Не удалось завершить сессию на сервере. Повторите выход при восстановлении связи.')
    } finally {
        loggingOut.value = false
    }
}

async function loadOrders(page = 1) {
    if (!user.value) return
    const request = ++listRequest
    listLoading.value = true
    listError.value = ''
    if (page === 1) orders.value = []
    try {
        const result = await api.orders({ search: String(search.value || '').trim(), filter: filter.value, ...deliveryDateQuery(deliveryDate.value, deliveryUnscheduled.value), page, per_page: 20 })
        if (request !== listRequest || !user.value) return
        orders.value = page > 1 ? [...orders.value, ...result.data] : result.data
        meta.value = result.meta
    } catch (error) {
        if (request === listRequest) listError.value = error.message
    } finally {
        if (request === listRequest) listLoading.value = false
    }
}

function applyOrder(value) {
    order.value = value
    deliveryDateDraft.value = value.delivery_date || ''
    rows.value = (value.items || []).map(item => ({
        id: item.id,
        quantity: String(item.quantity),
        measure_id: item.measure_id || null,
        checked: false,
    }))
    uncertainShipment.value = shipment.hasPending(value)
    stale.value = false
}

function chooseDeliveryDay(value = '', unscheduled = false) {
    deliveryDate.value = value
    deliveryUnscheduled.value = unscheduled
}

function showAllOrders() {
    search.value = ''
    filter.value = 'all'
    chooseDeliveryDay()
}

async function saveDeliveryDate() {
    if (!canSaveDeliveryDate.value) return
    savingDeliveryDate.value = true
    orderError.value = ''
    const currentSession = session
    try {
        const result = await api.setDeliveryDate(order.value.id, {
            version: order.value.version,
            delivery_date: deliveryDateDraft.value || null,
        })
        if (currentSession !== session) return
        applyOrder(result.data)
        notify(result.data.delivery_date ? `Доставка назначена на ${formatDeliveryDate(result.data.delivery_date)}.` : 'Дата доставки снята.')
    } catch (error) {
        if (currentSession !== session || error.status === 401) return
        if (error.status === 409) {
            stale.value = true
            orderError.value = 'Заказ изменился. Обновите карточку и проверьте дату доставки перед сохранением.'
        } else if (!(error instanceof ApiError) || error.uncertain) {
            stale.value = true
            orderError.value = 'Ответ о сохранении даты не получен. Обновите карточку, чтобы проверить дату на сервере.'
        } else {
            orderError.value = error.message
        }
    } finally {
        savingDeliveryDate.value = false
    }
}

async function openOrder(id) {
    if (busy.value) return
    const request = ++detailRequest
    if (selectedId.value !== id) order.value = null
    selectedId.value = id
    orderLoading.value = true
    orderError.value = ''
    confirmShipment.value = false
    try {
        const result = await api.order(id)
        if (request !== detailRequest || !user.value) return
        applyOrder(result.data)
        window.scrollTo({ top: 0, behavior: 'instant' })
    } catch (error) {
        if (request !== detailRequest) return
        orderError.value = error.message
        stale.value = true
    } finally {
        if (request === detailRequest) orderLoading.value = false
    }
}

function back() {
    if (busy.value) return
    if (confirmShipment.value) {
        confirmShipment.value = false
        return
    }
    if (inDetail.value) {
        detailRequest += 1
        selectedId.value = null
        order.value = null
        orderError.value = ''
        orderLoading.value = false
        if (screen.value === 'list') loadOrders()
        return
    }
    if (screen.value === 'map') screen.value = 'list'
}

function operationError(error, action) {
    if (error.status === 401) return
    if (error.status === 409) {
        stale.value = true
        orderError.value = 'Заказ или остатки изменились. Обновите заказ и проверьте данные перед подтверждением.'
    } else if (action === 'ship' && (!(error instanceof ApiError) || error.uncertain)) {
        uncertainShipment.value = true
        orderError.value = 'Ответ об отгрузке не получен. Операция могла завершиться. Обновите заказ или повторите подтверждение: повторный запрос использует тот же номер операции.'
    } else {
        orderError.value = error.message
    }
}

async function prepare() {
    if (busy.value || !canPrepare.value) return
    preparing.value = true
    orderError.value = ''
    const currentSession = session
    try {
        const result = await api.prepare(order.value.id, {
            version: order.value.version,
            items: rows.value.map(row => ({ id: row.id, measure_id: row.measure_id, quantity: Number(String(row.quantity).replace(',', '.')) })),
        })
        if (currentSession !== session) return
        applyOrder(result.data)
        notify('Сборка подтверждена. Заказ готов к отгрузке.')
        window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (error) {
        if (currentSession === session) operationError(error, 'prepare')
    } finally {
        preparing.value = false
    }
}

async function ship() {
    if (busy.value || !canShip.value) return
    shipping.value = true
    orderError.value = ''
    confirmShipment.value = false
    const currentSession = session
    try {
        const result = await shipment.send(order.value)
        if (currentSession !== session) return
        applyOrder(result.data)
        notify('Отгрузка проведена. Продажа и складские движения оформлены.')
        window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (error) {
        if (currentSession === session) operationError(error, 'ship')
    } finally {
        shipping.value = false
    }
}

function available(item, row) {
    return item.measure_options?.find(option => String(option.id) === String(row?.measure_id))?.available_quantity ?? item.available_quantity
}

watch(search, () => {
    clearTimeout(searchTimer)
    if (screen.value === 'list') searchTimer = setTimeout(() => loadOrders(), 350)
})
watch([filter, deliveryDate, deliveryUnscheduled], () => {
    clearTimeout(searchTimer)
    if (screen.value === 'list') loadOrders()
})
watch(screen, value => {
    clearTimeout(searchTimer)
    if (value === 'list') loadOrders()
})
const updateConnection = () => { online.value = navigator.onLine }
onMounted(async () => {
    window.addEventListener('online', updateConnection)
    window.addEventListener('offline', updateConnection)
    if (Capacitor.isNativePlatform()) {
        backListener = await NativeApp.addListener('backButton', () => {
            if (inDetail.value || confirmShipment.value || screen.value === 'map') back()
            else if (!busy.value) NativeApp.minimizeApp()
        })
    }
})
onBeforeUnmount(() => {
    clearTimeout(searchTimer)
    window.removeEventListener('online', updateConnection)
    window.removeEventListener('offline', updateConnection)
    backListener?.remove()
})
</script>

<template>
    <v-app>
        <div class="app-shell">
            <header class="topbar">
                <button v-if="user && (inDetail || screen === 'map')" class="icon-button" :aria-label="inDetail && screen === 'map' ? 'Назад к карте' : 'Назад к заказам'" :disabled="busy" @click="back">
                    <v-icon icon="mdi-arrow-left" />
                </button>
                <div v-else class="brand-mark" aria-hidden="true"><v-icon icon="mdi-package-variant-closed" size="25" /></div>
                <div class="brand-copy"><strong>Пищепром</strong><span>{{ user ? 'Заказы и отгрузка' : 'Рабочее приложение' }}</span></div>
                <v-menu v-if="user" location="bottom end">
                    <template #activator="{ props }">
                        <v-btn v-bind="props" icon variant="text" :disabled="busy" aria-label="Учётная запись"><span class="user-avatar">{{ initials }}</span></v-btn>
                    </template>
                    <v-list rounded="lg" min-width="230">
                        <v-list-item :title="user.name" :subtitle="user.email" />
                        <v-divider />
                        <v-list-item title="Выйти" prepend-icon="mdi-logout" :disabled="busy" @click="logout" />
                    </v-list>
                </v-menu>
            </header>

            <main :class="['main-content', { 'has-actions': user && order && order.workflow_status !== 'shipped' }]">
                <v-alert v-if="!online" type="warning" class="mb-4" role="status">Нет подключения. Данные могут быть устаревшими. Для подтверждения нужна связь с сервером.</v-alert>

                <section v-if="configError" class="login-section">
                    <div class="hero-icon"><v-icon icon="mdi-server-outline" size="32" /></div>
                    <h1>Нужно подключить сервер</h1>
                    <p class="muted">{{ configError }}</p>
                </section>

                <section v-else-if="!user" class="login-section">
                    <div class="eyebrow">ДЛЯ СОТРУДНИКОВ</div>
                    <h1>Заказы —<br />под рукой.</h1>
                    <p class="login-description">Собирайте заказы и подтверждайте отгрузку прямо со склада.</p>
                    <v-card class="login-card">
                        <h2>Вход в приложение</h2>
                        <p class="muted mb-6">Используйте свою рабочую учётную запись.</p>
                        <v-form @submit.prevent="login">
                            <v-alert v-if="loginError" type="error" class="mb-5" role="alert">{{ loginError }}</v-alert>
                            <v-text-field v-model="email" label="Электронная почта" type="email" autocomplete="username" inputmode="email" autocapitalize="none" :disabled="loggingIn" required />
                            <v-text-field v-model="password" label="Пароль" type="password" autocomplete="current-password" :disabled="loggingIn" required />
                            <v-text-field v-model="twoFactorCode" :label="useRecoveryCode ? 'Код восстановления' : 'Код двухфакторной защиты'" hint="Если защита включена в вашей учётной записи" persistent-hint :inputmode="useRecoveryCode ? 'text' : 'numeric'" autocomplete="one-time-code" :disabled="loggingIn" :maxlength="useRecoveryCode ? 32 : 8" />
                            <v-btn variant="text" size="small" class="mb-5" :disabled="loggingIn" @click="useRecoveryCode = !useRecoveryCode; twoFactorCode = ''">{{ useRecoveryCode ? 'Ввести код из приложения' : 'Использовать код восстановления' }}</v-btn>
                            <v-btn type="submit" color="primary" size="x-large" block :loading="loggingIn" :disabled="!online || !email.trim() || !password">Войти <v-icon icon="mdi-arrow-right" end /></v-btn>
                        </v-form>
                    </v-card>
                    <p class="login-footnote"><v-icon icon="mdi-lock-outline" size="16" /> Данные из общей системы учёта</p>
                </section>

                <section v-else-if="!inDetail" aria-labelledby="orders-title">
                    <div class="page-title-row">
                        <div><div class="eyebrow">РАБОЧЕЕ МЕСТО</div><h1 id="orders-title">{{ screen === 'map' ? 'Карта доставок' : 'Заказы' }}<span v-if="screen === 'list' && !listLoading" class="count-badge">{{ meta.total }}</span></h1></div>
                        <v-btn v-if="screen === 'list'" icon="mdi-refresh" variant="text" aria-label="Обновить заказы" :loading="listLoading" @click="loadOrders()" />
                    </div>
                    <div class="screen-switch" role="group" aria-label="Вид заказов">
                        <button :class="{ active: screen === 'list' }" :aria-pressed="screen === 'list'" @click="screen = 'list'"><v-icon icon="mdi-format-list-bulleted" size="20" />Список</button>
                        <button :class="{ active: screen === 'map' }" :aria-pressed="screen === 'map'" @click="screen = 'map'"><v-icon icon="mdi-map-outline" size="20" />Карта</button>
                    </div>
                    <v-text-field v-model="search" placeholder="Номер заказа или покупатель" aria-label="Поиск по номеру заказа или покупателю" prepend-inner-icon="mdi-magnify" clearable hide-details density="comfortable" bg-color="surface" class="search-field" @click:clear="search = ''" />
                    <div class="filter-scroll" role="group" aria-label="Фильтр заказов">
                        <button v-for="option in filters" :key="option.value" :class="['filter-button', { active: filter === option.value }]" :aria-pressed="filter === option.value" @click="filter = option.value">{{ option.label }}</button>
                    </div>

                    <div class="delivery-filter" aria-label="Фильтр по дате доставки">
                        <div class="delivery-filter-heading"><v-icon icon="mdi-calendar-clock-outline" size="18" /><strong>День доставки</strong><span>{{ deliveryUnscheduled ? 'Не назначен' : deliveryDate ? formatDeliveryDate(deliveryDate) : 'Все даты' }}</span></div>
                        <div class="delivery-day-buttons" role="group" aria-label="Выбрать день доставки">
                            <button :class="{ active: !deliveryDate && !deliveryUnscheduled }" :aria-pressed="!deliveryDate && !deliveryUnscheduled" @click="chooseDeliveryDay()">Все даты</button>
                            <button :class="{ active: deliveryDate === calendarDay() && !deliveryUnscheduled }" :aria-pressed="deliveryDate === calendarDay() && !deliveryUnscheduled" @click="chooseDeliveryDay(calendarDay())">Сегодня</button>
                            <button :class="{ active: deliveryDate === calendarDay(1) && !deliveryUnscheduled }" :aria-pressed="deliveryDate === calendarDay(1) && !deliveryUnscheduled" @click="chooseDeliveryDay(calendarDay(1))">Завтра</button>
                            <button :class="{ active: deliveryUnscheduled }" :aria-pressed="deliveryUnscheduled" @click="chooseDeliveryDay('', true)">Без даты</button>
                        </div>
                        <label class="delivery-calendar"><span>Выбрать дату</span><input :value="deliveryDate" type="date" aria-label="Дата доставки для списка и карты" @input="chooseDeliveryDay($event.target.value)" /></label>
                        <v-btn v-if="deliveryDate && (filter !== 'all' || search)" block variant="tonal" color="primary" class="mt-3" @click="filter = 'all'; search = ''">Все доставки дня</v-btn>
                    </div>

                    <DeliveryMap v-if="screen === 'map'" :api="api" :search="search || ''" :filter="filter" :delivery-date="deliveryDate" :delivery-unscheduled="deliveryUnscheduled" @open-order="openOrder" @notification="notify" />
                    <template v-else>
                    <v-alert v-if="listError" type="error" class="mb-4" role="alert">{{ listError }}<v-btn variant="text" class="mt-2" @click="loadOrders()">Повторить</v-btn></v-alert>
                    <div v-if="listLoading && !orders.length" class="order-list" aria-label="Загрузка заказов" aria-busy="true">
                        <v-skeleton-loader v-for="n in 3" :key="n" type="article, list-item-two-line" class="skeleton-card" />
                    </div>
                    <div v-else-if="!orders.length && !listError" class="empty-state">
                        <div class="hero-icon"><v-icon icon="mdi-package-variant" size="36" /></div>
                        <h2>Здесь пока нет заказов</h2>
                        <p class="muted">{{ search ? 'Попробуйте другой номер или имя покупателя.' : 'Выберите другой фильтр или обновите список.' }}</p>
                        <v-btn v-if="search || filter !== 'all' || deliveryDate || deliveryUnscheduled" color="primary" variant="tonal" class="mt-4" @click="showAllOrders">Показать все заказы</v-btn>
                    </div>
                    <div v-else class="order-list">
                        <article v-for="item in orders" :key="item.id" class="order-card">
                            <button class="order-card-main" :aria-label="`Открыть заказ № ${item.number}`" @click="openOrder(item.id)">
                            <div class="card-top"><span class="order-number">№ {{ item.number }}</span><v-chip :color="status(item.workflow_status).color" size="small" label>{{ status(item.workflow_status).label }}</v-chip></div>
                            <h2 class="customer-name">{{ item.entity?.name || 'Покупатель не указан' }}</h2>
                            <div class="card-date">{{ date(item.submitted_at) }}<span>·</span>{{ item.items_count }} поз.</div>
                            </button>
                            <div class="card-divider" />
                            <div class="card-delivery-day"><v-icon icon="mdi-calendar-clock-outline" size="18" /><span>Доставка: <strong>{{ formatDeliveryDate(item.delivery_date) }}</strong></span></div>
                            <DeliveryContacts :addresses="item.delivery_addresses || []" :telephone="item.contact_telephone" compact />
                            <div class="card-info"><v-icon icon="mdi-warehouse" size="18" /><span>{{ item.warehouse?.name || 'Склад не указан' }}</span></div>
                            <div class="card-info"><v-icon icon="mdi-account-outline" size="18" /><span>{{ item.responsible?.name || 'Ответственный не назначен' }}</span></div>
                            <div v-if="item.warnings?.length" class="card-warning"><v-icon icon="mdi-alert-circle-outline" size="17" /><span>{{ item.warnings[0].message }}<span v-if="item.warnings.length > 1"> · ещё {{ item.warnings.length - 1 }}</span></span></div>
                            <button class="card-bottom" :aria-label="`Открыть заказ № ${item.number}`" @click="openOrder(item.id)"><strong>{{ money(item.total_amount, item.currency_code) }}</strong><span class="open-order">Открыть<v-icon icon="mdi-arrow-right" size="19" /></span></button>
                        </article>
                    </div>
                    <v-btn v-if="orders.length && meta.current_page < meta.last_page" block variant="outlined" size="large" class="mt-5" :loading="listLoading" @click="loadOrders(meta.current_page + 1)">Показать ещё</v-btn>
                    <p v-if="orders.length" class="list-footnote">Показано {{ orders.length }} из {{ meta.total }}</p>
                    </template>
                </section>

                <section v-else aria-labelledby="order-title">
                    <div class="page-title-row">
                        <div><div class="eyebrow">КАРТОЧКА ЗАКАЗА</div><h1 id="order-title">{{ order ? `№ ${order.number}` : 'Заказ' }}</h1></div>
                        <v-btn icon="mdi-refresh" variant="text" aria-label="Обновить заказ" :loading="orderLoading" :disabled="busy" @click="openOrder(selectedId)" />
                    </div>
                    <v-alert v-if="orderError" type="warning" class="mb-4" role="alert"><div>{{ orderError }}</div><v-btn variant="text" class="mt-2" :disabled="busy" @click="openOrder(selectedId)">Обновить заказ</v-btn></v-alert>
                    <v-progress-linear v-if="orderLoading" indeterminate color="primary" class="mb-4" aria-label="Загрузка заказа" />
                    <template v-if="order">
                        <div v-if="order.workflow_status === 'shipped'" class="shipment-receipt" role="status">
                            <v-icon icon="mdi-check-circle" size="38" color="primary" />
                            <div><h2>Отгрузка оформлена</h2><p>Продажа № {{ order.sale?.id }} · {{ date(order.shipped_at || order.sale?.date) }}</p><p>Товар списан со склада. {{ money(order.sale?.total ?? order.total_amount, order.currency_code) }}</p></div>
                        </div>

                        <v-card class="detail-summary mb-4">
                            <v-chip :color="status(order.workflow_status).color" :prepend-icon="status(order.workflow_status).icon" size="small" label>{{ status(order.workflow_status).label }}</v-chip>
                            <h2 class="detail-customer">{{ order.entity?.name || 'Покупатель не указан' }}</h2>
                            <p class="muted">{{ date(order.submitted_at) }}</p>
                            <DeliveryContacts :addresses="order.delivery_addresses || []" :telephone="order.contact_telephone" />
                            <section class="delivery-date-editor" aria-label="Плановая дата доставки">
                                <div class="delivery-filter-heading"><v-icon icon="mdi-calendar-clock-outline" size="18" /><strong>Плановая доставка</strong></div>
                                <p class="muted mb-3">{{ formatDeliveryDate(order.delivery_date) }}</p>
                                <v-text-field v-model="deliveryDateDraft" type="date" label="Дата доставки" clearable hide-details density="comfortable" :disabled="busy || stale || orderLoading || !online || uncertainShipment" @click:clear="deliveryDateDraft = ''" />
                                <div class="delivery-date-actions">
                                    <v-btn color="primary" :loading="savingDeliveryDate" :disabled="!canSaveDeliveryDate" @click="saveDeliveryDate">Сохранить дату</v-btn>
                                    <v-btn v-if="deliveryDateDirty" variant="text" :disabled="busy" @click="deliveryDateDraft = order.delivery_date || ''">Отмена</v-btn>
                                </div>
                                <p v-if="uncertainShipment" class="section-hint mt-3 mb-0">Сначала проверьте результат отгрузки: обновите заказ или повторите подтверждение.</p>
                                <p v-else-if="deliveryDateDirty" class="section-hint mt-3 mb-0">Сохраните дату или отмените изменение перед сборкой и отгрузкой.</p>
                            </section>
                            <dl class="detail-facts">
                                <div><dt><v-icon icon="mdi-warehouse" size="18" />Склад</dt><dd>{{ order.warehouse?.name || 'Не указан' }}</dd></div>
                                <div><dt><v-icon icon="mdi-account-outline" size="18" />Ответственный</dt><dd>{{ order.responsible?.name || 'Не назначен' }}</dd></div>
                            </dl>
                            <div class="detail-total"><span>Сумма заказа</span><strong>{{ money(order.total_amount, order.currency_code) }}</strong></div>
                        </v-card>

                        <v-alert v-for="(warning, index) in order.warnings || []" :key="`${warning.code}-${index}`" type="warning" class="mb-3">{{ warning.message }}</v-alert>

                        <div class="section-title"><h2>Состав заказа</h2><span>{{ order.items_count }} поз.</span></div>
                        <p v-if="order.workflow_status === 'awaiting' && order.can_prepare" class="section-hint">Сверьте полное количество по каждой позиции. Выберите единицу, в которой заказано количество; пересчёт не выполняется.</p>
                        <div class="items-list">
                            <v-card v-for="(item, index) in order.items || []" :key="item.id" class="item-card">
                                <div class="item-heading"><span class="item-index">{{ index + 1 }}</span><h3>{{ item.name }}</h3></div>
                                <div class="item-price"><span>{{ quantity(item.quantity) }} {{ item.measure_name || 'ед.' }} × {{ money(item.price, order.currency_code) }}</span><strong>{{ money(item.total, order.currency_code) }}</strong></div>
                                <template v-if="order.workflow_status === 'awaiting' && order.can_prepare && rows[index]">
                                    <div class="quantity-fields">
                                        <v-text-field v-model="rows[index].quantity" label="Собрано" inputmode="decimal" density="comfortable" hide-details :disabled="busy || stale || orderLoading" @update:model-value="rows[index].checked = false" />
                                        <v-select v-model="rows[index].measure_id" :items="item.measure_options || []" item-title="name" item-value="id" label="Единица измерения" density="comfortable" hide-details :disabled="busy || stale || orderLoading" no-data-text="Нет единиц измерения" @update:model-value="rows[index].checked = false" />
                                    </div>
                                    <div :class="['stock-label', { insufficient: rows[index].measure_id && Number(available(item, rows[index])) < Number(item.quantity) }]"><v-icon icon="mdi-cube-outline" size="16" />{{ rows[index].measure_id ? `На складе: ${quantity(available(item, rows[index]))}` : 'Выберите единицу для проверки остатка' }}</div>
                                    <v-checkbox v-model="rows[index].checked" label="Количество и единица сверены" color="primary" hide-details density="comfortable" :disabled="busy || stale || orderLoading || !rows[index].measure_id" class="verification-checkbox" />
                                </template>
                                <div v-else-if="order.workflow_status !== 'shipped'" class="stock-label"><v-icon icon="mdi-cube-outline" size="16" />На складе: {{ quantity(item.available_quantity) }} {{ item.measure_name }}</div>
                            </v-card>
                        </div>

                        <v-alert v-if="order.workflow_status === 'awaiting' && !order.can_prepare" type="info" class="mt-4">Сборка сейчас недоступна. Проверьте предупреждения и права своей учётной записи.</v-alert>
                        <v-alert v-if="order.workflow_status === 'ready' && !order.can_ship" type="info" class="mt-4">Отгрузка сейчас недоступна. Проверьте остатки, предупреждения и права своей учётной записи.</v-alert>
                        <v-btn v-if="order.workflow_status === 'shipped'" color="primary" variant="tonal" size="x-large" block class="mt-5" @click="back">{{ screen === 'map' ? 'К карте доставок' : 'К списку заказов' }}</v-btn>
                    </template>
                </section>
            </main>

            <footer v-if="user && order && order.workflow_status !== 'shipped'" class="action-bar">
                <template v-if="order.workflow_status === 'awaiting'">
                    <div class="action-summary"><span>Сверено {{ checkedCount }} из {{ order.items_count }}</span><strong>{{ money(order.total_amount, order.currency_code) }}</strong></div>
                    <v-btn color="primary" size="x-large" block :disabled="!canPrepare || busy" :loading="preparing" prepend-icon="mdi-package-variant-closed-check" @click="prepare">Подтвердить сборку</v-btn>
                    <p v-if="order.can_prepare && preparationError && !stale" class="action-hint">{{ preparationError }}</p>
                </template>
                <template v-else-if="order.workflow_status === 'ready'">
                    <div class="action-summary"><span>К полной отгрузке</span><strong>{{ money(order.total_amount, order.currency_code) }}</strong></div>
                    <v-btn color="primary" size="x-large" block :disabled="!canShip || busy" :loading="shipping" prepend-icon="mdi-truck-check-outline" @click="confirmShipment = true">{{ uncertainShipment ? 'Повторить подтверждение' : 'Подтвердить отгрузку' }}</v-btn>
                </template>
                <p v-if="stale" class="action-hint">Обновите заказ перед подтверждением.</p>
            </footer>
        </div>

        <v-dialog v-model="confirmShipment" max-width="460" :persistent="shipping">
            <v-card v-if="order" class="confirmation-card">
                <div class="hero-icon small"><v-icon icon="mdi-truck-check-outline" size="30" /></div>
                <h2>Отгрузить заказ № {{ order.number }}?</h2>
                <p class="muted mt-3">{{ order.entity?.name }}</p>
                <dl class="confirmation-facts"><div><dt>Склад</dt><dd>{{ order.warehouse?.name }}</dd></div><div><dt>Позиций</dt><dd>{{ order.items_count }}</dd></div><div><dt>Сумма</dt><dd>{{ money(order.total_amount, order.currency_code) }}</dd></div></dl>
                <p class="confirmation-note">Весь товар будет списан с указанного склада, а продажа оформлена в общей системе учёта.</p>
                <v-alert v-if="order.allow_negative_stock === true" type="warning" class="mb-4">Временно разрешена отгрузка без достаточного остатка. Если товара на складе по учёту не хватает, остаток станет отрицательным. Подтверждайте только фактически переданный товар.</v-alert>
                <v-alert v-if="uncertainShipment" type="info" class="mb-4">Повторное подтверждение проверит ту же операцию и не создаст вторую продажу.</v-alert>
                <v-btn color="primary" size="x-large" block :loading="shipping" :disabled="!canShip" @click="ship">{{ uncertainShipment ? 'Повторить подтверждение' : 'Отгрузить весь заказ' }}</v-btn>
                <v-btn variant="text" size="large" block class="mt-2" :disabled="shipping" @click="confirmShipment = false">Вернуться к проверке</v-btn>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="showNotification" :timeout="6000" color="secondary" location="top">{{ notification }}<template #actions><v-btn icon="mdi-close" variant="text" aria-label="Закрыть уведомление" @click="showNotification = false" /></template></v-snackbar>
    </v-app>
</template>
