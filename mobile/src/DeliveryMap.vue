<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import DeliveryContacts from './DeliveryContacts.vue'
import { groupDeliveryAddresses, loadDeliveryOrders, loadYandexMaps, resolveDeliveryGroups } from './delivery-map.js'

const props = defineProps({ api: { type: Object, required: true }, search: { type: String, default: '' }, filter: { type: String, default: 'all' } })
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
let active = null
let map = null
let filterTimer
const locatedOrderIds = new Set()

const fallbackOrders = computed(() => {
    if (!configured.value || error.value) return orders.value.map(order => ({ order, reason: '' }))
    const unique = new Map()
    for (const entry of unresolved.value) if (!unique.has(entry.order.id)) unique.set(entry.order.id, entry)
    return [...unique.values()]
})
const shownOrders = computed(() => fallbackOrders.value.slice(0, displayedCount.value))

function disposeMap() {
    map?.destroy()
    map = null
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
            loadDeliveryOrders(query => props.api.deliveryMapOrders(query), { search: props.search, filter: props.filter }, {
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
        loading.value = false
    } else {
        refresh()
    }
}

watch(() => [props.search, props.filter], () => {
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
</style>
