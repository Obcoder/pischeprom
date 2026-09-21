<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { Capacitor } from '@capacitor/core'
import DeliveryContacts from './DeliveryContacts.vue'
import { exactGeocodeCoordinates, groupDeliveryAddresses, loadDeliveryOrders, loadYandexMaps, resolveDeliveryGroups, timedGeocode } from './delivery-map.js'
import { disposeYandexRoute, fastestYandexRoute, planDeliveryRoute, requestYandexRoute, ROUTE_LIMITS, splitRoutePoints, yandexRouteUrl } from './delivery-route.js'
import { formatDeliveryDate } from './delivery-date.js'

const props = defineProps({
    api: { type: Object, required: true }, search: { type: String, default: '' }, filter: { type: String, default: 'all' },
    deliveryDate: { type: String, default: '' }, deliveryUnscheduled: { type: Boolean, default: false },
})
const emit = defineEmits(['open-order', 'notification'])
const canvas = ref(null)
const orders = ref([])
const loading = ref(false)
const configured = ref(true)
const mapReady = ref(false)
const online = ref(navigator.onLine)
const error = ref('')
const progress = ref('')
const locatedCount = ref(0)
const unresolved = ref([])
const selected = shallowRef(null)
const selectedElement = ref(null)
const displayedCount = ref(30)
const locatedPoints = shallowRef([])
const originAddress = ref('')
const returnToStart = ref(false)
const planning = ref(false)
const routeProgress = ref('')
const routeError = ref('')
const routePlan = shallowRef(null)
const externalTarget = Capacitor.isNativePlatform() ? undefined : '_blank'
let active = null
let map = null
let mapsApi = null
let filterTimer
let routeController = null
let routeObjects = []
let originMarker = null
const locatedOrderIds = new Set()

const routeBlocked = computed(() => {
    if (!props.deliveryDate || props.deliveryUnscheduled) return 'Выберите день доставки, чтобы построить маршрут.'
    if (!configured.value) return 'Для расчёта маршрута нужно подключить Яндекс Карты.'
    if (!online.value) return 'Для расчёта маршрута нужен интернет.'
    if (loading.value) return 'Дождитесь определения всех адресов.'
    if (error.value) return 'Обновите карту перед расчётом маршрута.'
    if (unresolved.value.length) return 'Уточните все нераспознанные адреса ниже, затем обновите карту. Маршрут должен включать каждый адрес.'
    if (!locatedPoints.value.length) return 'В выбранный день нет адресов для маршрута.'
    if (locatedPoints.value.length > ROUTE_LIMITS.maximumStops) return `В одном маршруте может быть до ${ROUTE_LIMITS.maximumStops} адресов. Уточните поиск для выбора части доставок.`
    return ''
})

function clearRoute() {
    routeController?.abort()
    routeController = null
    for (const route of routeObjects) disposeYandexRoute(route)
    routeObjects = []
    if (originMarker && map) map.geoObjects.remove(originMarker)
    originMarker = null
    for (const point of locatedPoints.value) point.marker.properties.set('iconContent', String(point.orders.length))
    routePlan.value = null
    routeError.value = ''
    routeProgress.value = ''
    planning.value = false
}

async function calculateRoute() {
    if (routeBlocked.value || !originAddress.value.trim() || !mapsApi || !map) return
    clearRoute()
    const controller = new AbortController()
    routeController = controller
    const current = () => routeController === controller && !controller.signal.aborted
    planning.value = true
    try {
        routeProgress.value = 'Определяем адрес отправления…'
        const origin = exactGeocodeCoordinates(await timedGeocode(address => mapsApi.geocode(address, { results: 1, kind: 'house' }),
            originAddress.value.trim(), 12000, controller.signal))
        if (!current()) return
        if (!origin) throw new Error('Не удалось точно определить адрес отправления. Укажите город, улицу и дом.')
        const plan = await planDeliveryRoute(origin, locatedPoints.value, async (from, to, signal) => {
            const route = await requestYandexRoute(mapsApi, [from, to], { signal })
            try { return fastestYandexRoute(route, 2).seconds } finally { disposeYandexRoute(route) }
        }, {
            returnToStart: returnToStart.value, signal: controller.signal,
            onProgress: ({ completed, total }) => { if (current()) routeProgress.value = `Сравниваем время проезда: ${completed} из ${total}` },
        })
        if (!current()) return
        const points = [origin, ...plan.stops.map(point => point.coordinates), ...(returnToStart.value ? [origin] : [])]
        const chunks = splitRoutePoints(points)
        let seconds = 0
        let metres = 0
        for (let index = 0; index < chunks.length; index++) {
            routeProgress.value = `Строим маршрут: участок ${index + 1} из ${chunks.length}`
            const route = await requestYandexRoute(mapsApi, chunks[index], { signal: controller.signal })
            if (!current()) { disposeYandexRoute(route); return }
            routeObjects.push(route)
            const stats = fastestYandexRoute(route, chunks[index].length)
            seconds += stats.seconds
            metres += stats.metres
            route.options.set({
                wayPointVisible: false, viaPointVisible: false, routeActiveStrokeWidth: 5,
                routeActiveStrokeColor: '#157347', routeStrokeColor: '#157347', boundsAutoApply: false,
            })
            map.geoObjects.add(route)
        }
        if (!current()) return
        for (let index = 0; index < plan.stops.length; index++) plan.stops[index].marker.properties.set('iconContent', String(index + 1))
        originMarker = new mapsApi.Placemark(origin, { iconContent: 'С' }, { preset: 'islands#blueIcon', openBalloonOnClick: false })
        map.geoObjects.add(originMarker)
        const bounds = map.geoObjects.getBounds()
        if (bounds) await map.setBounds(bounds, { checkZoomRange: true, zoomMargin: 36 })
        if (!current()) return
        routePlan.value = { ...plan, origin: originAddress.value.trim(), returnToStart: returnToStart.value, seconds, metres,
            navigation: chunks.map(yandexRouteUrl) }
    } catch (failure) {
        if (!current() || failure.name === 'AbortError') return
        clearRoute()
        routeError.value = failure.message || 'Не удалось рассчитать маршрут. Повторите попытку.'
    } finally {
        if (current()) { planning.value = false; routeProgress.value = '' }
    }
}

function routeDuration(seconds) {
    const minutes = Math.ceil(seconds / 60)
    return minutes >= 60 ? `${Math.floor(minutes / 60)} ч ${minutes % 60} мин` : `${minutes} мин`
}

const fallbackOrders = computed(() => {
    if (!configured.value || error.value) return orders.value.map(order => ({ order, reason: '' }))
    const unique = new Map()
    for (const entry of unresolved.value) if (!unique.has(entry.order.id)) unique.set(entry.order.id, entry)
    return [...unique.values()]
})
const shownOrders = computed(() => fallbackOrders.value.slice(0, displayedCount.value))

function disposeMap() {
    clearRoute()
    map?.destroy()
    map = null
    mapsApi = null
    locatedPoints.value = []
    mapReady.value = false
}

async function selectPoint(point) {
    selected.value = point
    await nextTick()
    selectedElement.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
}

async function refresh() {
    clearTimeout(filterTimer)
    active?.abort()
    const controller = new AbortController()
    active = controller
    const current = () => active === controller && !controller.signal.aborted
    disposeMap()
    orders.value = []
    unresolved.value = []
    selected.value = null
    locatedCount.value = 0
    locatedOrderIds.clear()
    displayedCount.value = 30
    error.value = ''
    configured.value = true
    progress.value = 'Загружаем доставки…'
    loading.value = false
    if (!online.value) return
    loading.value = true
    try {
        const [configuration] = await Promise.all([
            props.api.deliveryMapConfig(),
            loadDeliveryOrders(query => props.api.deliveryMapOrders(query), {
                search: props.search, filter: props.filter, delivery_date: props.deliveryDate, delivery_unscheduled: props.deliveryUnscheduled,
            }, {
                signal: controller.signal,
                onProgress: ({ loaded, total }) => { if (current()) progress.value = `Загружено заказов: ${loaded} из ${total}` },
            }).then(data => { if (current()) orders.value = data }),
        ])
        if (!current()) return
        configured.value = configuration?.data?.configured === true
        if (!configured.value || !orders.value.length) return
        const grouped = groupDeliveryAddresses(orders.value)
        unresolved.value = grouped.missing
        if (!grouped.groups.length) return
        progress.value = 'Подключаем Яндекс Карты…'
        const maps = await loadYandexMaps(configuration.data)
        if (!current()) return
        mapsApi = maps
        mapReady.value = true
        await nextTick()
        if (!current()) return
        map = new maps.Map(canvas.value, {
            center: configuration.data.default_center,
            zoom: configuration.data.default_zoom,
            controls: ['zoomControl'],
        }, { suppressMapOpenBlock: true })
        const cluster = new maps.Clusterer({ preset: 'islands#greenClusterIcons', clusterOpenBalloonOnClick: false })
        const points = new Map()
        map.geoObjects.add(cluster)
        cluster.events.add('click', event => {
            if (!current()) return
            const members = event.get('target')?.getGeoObjects?.()
            if (!members?.length) return
            const selectedPoints = members.map(marker => points.get(marker.properties.get('deliveryPointKey'))).filter(Boolean)
            if (!selectedPoints.length) return
            const deliveries = new Map()
            for (const point of selectedPoints) for (const order of point.orders) deliveries.set(order.id, order)
            selectPoint({
                key: null,
                address: { full_address: `Заказы по выбранным адресам · ${selectedPoints.length}` },
                orders: [...deliveries.values()],
            })
        })
        progress.value = `Определяем адреса: 0 из ${grouped.groups.length}`
        await resolveDeliveryGroups(grouped.groups, address => maps.geocode(address, { results: 1, kind: 'house' }), {
            signal: controller.signal,
            onResult({ group, coordinates, reason, completed, total }) {
                if (!current()) return
                progress.value = `Определяем адреса: ${completed} из ${total}`
                if (!coordinates) {
                    unresolved.value.push(...group.orders.map(order => ({ order, reason })))
                    return
                }
                for (const order of group.orders) locatedOrderIds.add(order.id)
                locatedCount.value = locatedOrderIds.size
                const key = coordinates.join(',')
                const existing = points.get(key)
                if (existing) {
                    for (const order of group.orders) if (!existing.orders.some(item => item.id === order.id)) existing.orders.push(order)
                    existing.marker.properties.set('iconContent', String(existing.orders.length))
                    if (selected.value?.key === key) selected.value = { ...existing, orders: [...existing.orders] }
                    return
                }
                const point = { key, address: group.address, coordinates, orders: [...group.orders] }
                // Only a numeric count goes into provider HTML; customer text is rendered by Vue below.
                point.marker = new maps.Placemark(coordinates, { iconContent: String(point.orders.length), deliveryPointKey: key }, {
                    preset: 'islands#greenIcon', openBalloonOnClick: false,
                })
                point.marker.events.add('click', () => { if (current()) selectPoint({ ...point, orders: [...point.orders] }) })
                points.set(key, point)
                locatedPoints.value = [...points.values()]
                cluster.add(point.marker)
                if (points.size === 1) map.setCenter(coordinates, 15)
            },
        })
        if (!current()) return
        if (points.size > 1) await map.setBounds(cluster.getBounds(), { checkZoomRange: true, zoomMargin: 36 })
        if (!current()) return
        progress.value = ''
    } catch (failure) {
        if (!current() || failure.name === 'AbortError') return
        error.value = failure.message || 'Не удалось загрузить карту доставок. Повторите обновление.'
        emit('notification', error.value)
    } finally {
        if (current()) loading.value = false
    }
}

function updateConnection() {
    online.value = navigator.onLine
    if (!online.value) {
        active?.abort()
        clearRoute()
        loading.value = false
    } else {
        refresh()
    }
}

watch(() => [props.search, props.filter, props.deliveryDate, props.deliveryUnscheduled], () => {
    active?.abort()
    disposeMap()
    selected.value = null
    orders.value = []
    unresolved.value = []
    locatedCount.value = 0
    error.value = ''
    loading.value = true
    clearTimeout(filterTimer)
    filterTimer = setTimeout(refresh, 300)
})

watch([originAddress, returnToStart], clearRoute)

onMounted(() => {
    window.addEventListener('online', updateConnection)
    window.addEventListener('offline', updateConnection)
    refresh()
})
onBeforeUnmount(() => {
    clearTimeout(filterTimer)
    active?.abort()
    disposeMap()
    window.removeEventListener('online', updateConnection)
    window.removeEventListener('offline', updateConnection)
})
</script>

<template>
    <section class="delivery-map-screen" aria-label="Карта доставок">
        <div class="delivery-map-heading">
            <div>
                <h2>Карта доставок</h2>
                <p v-if="deliveryDate">Доставка {{ formatDeliveryDate(deliveryDate) }}</p>
                <p v-else-if="deliveryUnscheduled">Доставки без назначенной даты</p>
                <p v-if="orders.length">На карте {{ locatedCount }} из {{ orders.length }} заказов</p>
            </div>
            <v-btn icon="mdi-refresh" variant="text" aria-label="Обновить карту доставок" :disabled="!online" :loading="loading" @click="refresh" />
        </div>
        <v-alert v-if="!online" type="warning" variant="tonal">Для карты доставок нужен интернет. Подключитесь и обновите карту.</v-alert>
        <div v-if="loading" class="delivery-map-progress" role="status" aria-live="polite">
            <v-progress-linear indeterminate color="primary" />
            <p>{{ progress }}</p>
        </div>
        <v-alert v-if="error" type="warning" variant="tonal">{{ error }}</v-alert>
        <v-alert v-else-if="!configured" type="info" variant="tonal">
            Карта доставок пока не подключена. Адрес каждого заказа можно открыть в Яндекс Картах ниже.
        </v-alert>
        <div v-show="mapReady" ref="canvas" class="delivery-map-canvas" aria-label="Адреса доставки на Яндекс Картах" />
        <p v-if="mapReady && locatedCount" class="delivery-map-hint">Нажмите на точку или группу точек, чтобы выбрать заказ.</p>
        <section class="delivery-route-panel" aria-label="Маршрут доставок на день">
            <h3>Маршрут на день</h3>
            <p v-if="routeBlocked" class="delivery-route-note">{{ routeBlocked }}</p>
            <template v-else>
                <p class="delivery-route-note">Адресов: {{ locatedPoints.length }}. Заказов: {{ orders.length }}. Отгрузка со склада не означает, что заказ уже доставлен.</p>
                <v-alert v-if="search.trim() || filter !== 'all'" type="info" variant="tonal" density="compact">
                    Маршрут включает заказы по текущему поиску и статусу. Чтобы включить весь день, нажмите «Все доставки дня» над картой.
                </v-alert>
                <v-text-field v-model="originAddress" label="Адрес отправления" placeholder="Город, улица, дом" prepend-inner-icon="mdi-flag-outline"
                    variant="outlined" density="comfortable" hide-details="auto" autocomplete="off" maxlength="500" />
                <v-switch v-model="returnToStart" label="Вернуться в точку отправления" color="primary" density="compact" hide-details />
                <p v-if="locatedPoints.length > ROUTE_LIMITS.exactStops" class="delivery-route-note">Для большого числа адресов порядок объезда рассчитывается приближённо по времени проезда дорог. Самый короткий маршрут не гарантируется.</p>
                <v-btn v-if="!planning" color="primary" prepend-icon="mdi-routes" block :disabled="!originAddress.trim()" @click="calculateRoute">{{ routePlan ? 'Пересчитать маршрут' : 'Рассчитать маршрут' }}</v-btn>
                <template v-else>
                    <v-progress-linear indeterminate color="primary" />
                    <p class="delivery-route-note" role="status" aria-live="polite">{{ routeProgress }}</p>
                    <v-btn variant="tonal" block @click="clearRoute">Отменить расчёт</v-btn>
                </template>
                <p class="delivery-route-note">Оценка по текущей дорожной обстановке, без времени разгрузки и окон доставки. Для будущего дня это предварительный план: пересчитайте перед выездом.</p>
            </template>
            <v-alert v-if="routeError" type="warning" variant="tonal">{{ routeError }}</v-alert>
            <div v-if="routePlan" class="delivery-route-result">
                <strong>{{ (routePlan.metres / 1000).toLocaleString('ru-RU', { maximumFractionDigits: 1 }) }} км · {{ routeDuration(routePlan.seconds) }} в пути</strong>
                <p class="delivery-route-note">{{ routePlan.exact ? 'Выбран порядок с минимальным суммарным временем по рассчитанным дорогам.' : 'Приближённый порядок объезда по времени проезда дорог.' }}</p>
                <p class="delivery-route-note">Старт: {{ routePlan.origin }}</p>
                <article v-for="(point, index) in routePlan.stops" :key="point.key" class="delivery-route-stop">
                    <strong>{{ index + 1 }}. {{ point.address.full_address }}</strong>
                    <v-btn v-for="order in point.orders" :key="order.id" variant="text" color="primary" append-icon="mdi-arrow-top-right"
                        class="delivery-route-order-link" block @click="emit('open-order', order.id)">Заказ {{ order.number }} · {{ order.entity?.name || 'Покупатель не указан' }}</v-btn>
                </article>
                <p v-if="routePlan.returnToStart" class="delivery-route-note">Финиш: возврат в {{ routePlan.origin }}</p>
                <v-btn v-for="(url, index) in routePlan.navigation" :key="url" :href="url" :target="externalTarget" rel="noopener noreferrer"
                    color="primary" variant="tonal" prepend-icon="mdi-navigation-variant-outline" block>
                    {{ routePlan.navigation.length === 1 ? 'Открыть маршрут в Яндекс Картах' : `Открыть участок ${index + 1} из ${routePlan.navigation.length}` }}
                </v-btn>
            </div>
        </section>
        <div v-if="selected" ref="selectedElement" class="delivery-map-selection">
            <div class="delivery-map-selection-heading">
                <h3>{{ selected.address.full_address }}</h3>
                <v-btn icon="mdi-close" variant="text" aria-label="Закрыть выбранный адрес" @click="selected = null" />
            </div>
            <article v-for="order in selected.orders" :key="order.id" class="delivery-map-order">
                <strong>Заказ {{ order.number }}</strong>
                <p>{{ order.entity?.name || 'Покупатель не указан' }}</p>
                <DeliveryContacts :addresses="order.delivery_addresses" :show-telephone="false" />
                <v-btn color="primary" variant="tonal" append-icon="mdi-arrow-top-right" block @click="emit('open-order', order.id)">Открыть заказ</v-btn>
            </article>
        </div>
        <p v-if="!loading && online && !error && !orders.length" class="delivery-map-empty">По выбранным фильтрам доставок нет.</p>
        <div v-if="fallbackOrders.length" class="delivery-map-unlocated">
            <h3>{{ !configured || error ? 'Адреса доставок' : 'Адреса, которые пока не показаны на карте' }} · {{ fallbackOrders.length }}</h3>
            <article v-for="entry in shownOrders" :key="entry.order.id" class="delivery-map-order">
                <strong>Заказ {{ entry.order.number }}</strong>
                <p>{{ entry.order.entity?.name || 'Покупатель не указан' }}</p>
                <p v-if="entry.reason" class="delivery-map-reason">{{ entry.reason }}</p>
                <DeliveryContacts :addresses="entry.order.delivery_addresses" :show-telephone="false" />
                <v-btn variant="tonal" append-icon="mdi-arrow-top-right" block @click="emit('open-order', entry.order.id)">Открыть заказ</v-btn>
            </article>
            <v-btn v-if="shownOrders.length < fallbackOrders.length" block variant="text" @click="displayedCount += 30">Показать ещё</v-btn>
        </div>
    </section>
</template>

<style scoped>
.delivery-map-screen { display: grid; gap: 14px; }
.delivery-map-heading, .delivery-map-selection-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.delivery-map-heading h2 { font-size: 1.18rem; margin: 0; }
.delivery-map-heading p, .delivery-map-order p { margin: 4px 0 10px; }
.delivery-map-heading p, .delivery-map-hint { color: #61716b; font-size: .87rem; }
.delivery-map-progress p { margin: 8px 0 0; color: #61716b; font-size: .87rem; }
.delivery-map-canvas { width: 100%; height: min(54vh, 460px); min-height: 280px; border-radius: 16px; overflow: hidden; background: #e8eeea; }
.delivery-map-hint { margin: 0; }
.delivery-map-selection, .delivery-map-unlocated { display: grid; gap: 10px; }
.delivery-map-selection h3, .delivery-map-unlocated h3 { font-size: 1rem; line-height: 1.45; overflow-wrap: anywhere; }
.delivery-map-order { padding: 16px; background: white; border: 1px solid #dce7e0; border-radius: 16px; overflow-wrap: anywhere; }
.delivery-map-order :deep(.delivery-contacts) { margin-bottom: 12px; }
.delivery-map-reason { color: #956119; font-size: .87rem; }
.delivery-map-empty { text-align: center; color: #61716b; padding: 30px 12px; }
.delivery-route-panel, .delivery-route-result { display: grid; gap: 12px; min-width: 0; }
.delivery-route-panel { background: white; padding: 16px; border: 1px solid #dce7e0; border-radius: 16px; }
.delivery-route-panel h3 { margin: 0; font-size: 1.05rem; }
.delivery-route-note { color: #61716b; font-size: .84rem; line-height: 1.45; margin: 0; overflow-wrap: anywhere; }
.delivery-route-stop { padding: 10px 0; border-top: 1px solid #e3ebe5; overflow-wrap: anywhere; }
.delivery-route-order-link { height: auto !important; min-height: 44px; padding: 8px 0 !important; justify-content: flex-start; }
.delivery-route-order-link :deep(.v-btn__content) { white-space: normal; text-align: left; }
</style>
