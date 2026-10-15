<script setup>
import { computed, onScopeDispose, ref, watch } from 'vue'
import axios from 'axios'
import AvitoPublications from '@/Components/Avito/AvitoPublications.vue'
import GoodAvitoPriceDialog from './GoodAvitoPriceDialog.vue'
import { avitoDate, avitoError, avitoListingUrl, avitoMoney } from '@/Services/goodAvito'

const props = defineProps({ goodId: { type: [Number, String], required: true }, active: { type: Boolean, default: true } })
const emit = defineEmits(['changed', 'state'])
const context = ref(null), status = ref(null), account = ref(null), loading = ref(false), connecting = ref(false)
const error = ref(''), connectionError = ref(''), notice = ref(''), tab = ref('listings')
const picker = ref(false), listingSearch = ref(''), listingId = ref(''), listingStatus = ref(''), listings = ref([]), listPage = ref(1), listLoading = ref(false)
const selectedLink = ref(null), details = ref({}), remoteLoading = ref(false), action = ref(''), removing = ref(null)
const fields = ref(['title', 'description', 'images']), includeFacts = ref(true), prepared = ref(null), preparing = ref(false)
const priceOpen = ref(false), priceBusy = ref(false), publicationBusy = ref(false), publicationDirty = ref(false)
const publicationVisited = ref(false), publications = ref(null)
let generation = 0, listVersion = 0, detailVersion = 0, prepareVersion = 0
const base = () => `/api/avito/goods/${props.goodId}`
const links = computed(() => context.value?.links || [])
const link = computed(() => links.value.find(item => Number(item.id) === Number(selectedLink.value)) || null)
const detail = computed(() => details.value[selectedLink.value] || null)
const apiReady = computed(() => Boolean(status.value?.enabled && status.value?.configured))
const workspace = computed(() => ({ ready: Boolean(account.value?.id && apiReady.value), account_id: account.value?.id || null, connection_id: null }))
const busy = computed(() => Boolean(action.value || preparing.value || priceBusy.value || publicationBusy.value))
const filteredListings = computed(() => {
    const search = listingSearch.value.trim().toLocaleLowerCase('ru')
    return search ? listings.value.filter(item => `${item.id} ${item.title || ''}`.toLocaleLowerCase('ru').includes(search)) : listings.value
})
const statusLabel = value => ({ active: 'Активно', removed: 'Снято', old: 'Истёк срок', blocked: 'Заблокировано', rejected: 'Отклонено', draft: 'Черновик', ready: 'Готово к публикации', published: 'Опубликовано', publishing: 'Отправляется', warning: 'С предупреждением', archived: 'Архив' })[value] || value || '—'
const linkTitle = item => details.value[item.id]?.title || `Объявление № ${item.avito_item_id}`

async function load() {
    if (busy.value) return
    const version = ++generation
    listVersion++; detailVersion++; invalidateContent()
    connecting.value = false; listLoading.value = false; remoteLoading.value = false
    loading.value = true; error.value = ''
    const results = await Promise.allSettled([axios.get(base()), axios.get('/api/avito/status')])
    if (version !== generation) return
    const [good, connection] = results
    if (good.status === 'fulfilled') {
        context.value = good.value.data
        if (!links.value.some(item => Number(item.id) === Number(selectedLink.value))) selectedLink.value = links.value[0]?.id || null
    } else error.value = avitoError(good.reason)
    if (connection.status === 'fulfilled') status.value = connection.value.data
    else connectionError.value = avitoError(connection.reason, 'Не удалось проверить настройки подключения.')
    loading.value = false
    if (apiReady.value && !account.value) connect()
}
async function connect() {
    if (!apiReady.value || connecting.value) return
    const version = generation
    connecting.value = true; connectionError.value = ''
    try {
        const { data } = await axios.get('/api/avito/listings/context')
        if (version !== generation) return
        account.value = data.account || null
        if (!account.value?.id) connectionError.value = 'Авито не вернул номер аккаунта. Проверьте подключение.'
    } catch (failure) { if (version === generation) connectionError.value = avitoError(failure, 'Не удалось подключиться к Авито.') }
    finally { if (version === generation) connecting.value = false }
}
async function openPicker() {
    picker.value = !picker.value
    if (!picker.value) return
    if (!account.value) await connect()
    if (account.value && !listings.value.length) await loadListings(1)
}
async function loadListings(page = 1) {
    if (!account.value?.id) return
    const version = generation, requestVersion = ++listVersion
    listLoading.value = true; error.value = ''
    try {
        const { data } = await axios.get('/api/avito/listings', { params: { account_id: account.value.id, page, per_page: 30, statuses: listingStatus.value ? [listingStatus.value] : undefined } })
        if (version !== generation || requestVersion !== listVersion) return
        listings.value = data.items || []; listPage.value = page
    } catch (failure) { if (version === generation && requestVersion === listVersion) error.value = avitoError(failure) }
    finally { if (version === generation && requestVersion === listVersion) listLoading.value = false }
}
async function attach(itemId) {
    if (busy.value || !account.value?.id || !/^\d+$/.test(String(itemId)) || Number(itemId) <= 0) return
    const version = generation
    action.value = 'link'; error.value = ''; notice.value = ''
    try {
        const { data } = await axios.put(`${base()}/links`, { account_id: account.value.id, item_id: Number(itemId) })
        if (version !== generation) return
        const next = data.link
        context.value.links = [...links.value.filter(item => Number(item.id) !== Number(next.id)), { ...next, history: data.history || [] }]
        const remote = data.listing?.item || data.listing
        const listed = listings.value.find(item => Number(item.id) === Number(itemId))
        if (remote || listed) details.value = { ...details.value, [next.id]: { ...listed, ...remote } }
        selectedLink.value = next.id; picker.value = false; listingId.value = ''
        notice.value = 'Объявление связано с товаром.'; emit('changed')
    } catch (failure) { if (version === generation) error.value = avitoError(failure) }
    finally { if (version === generation) action.value = '' }
}
async function detach() {
    if (!link.value || busy.value) return
    const version = generation, id = link.value.id
    action.value = 'unlink'; error.value = ''
    try {
        await axios.delete(`${base()}/links/${id}`)
        if (version !== generation) return
        context.value.links = links.value.filter(item => Number(item.id) !== Number(id)); selectedLink.value = links.value[0]?.id || null
        removing.value = null; notice.value = 'Связь удалена. Объявление остаётся в Авито.'; emit('changed')
    } catch (failure) { if (version === generation) error.value = avitoError(failure) }
    finally { if (version === generation) action.value = '' }
}
async function refreshListing() {
    if (!link.value || !apiReady.value) return
    const version = generation, requestVersion = ++detailVersion, id = link.value.id
    remoteLoading.value = true; error.value = ''
    try {
        const { data } = await axios.get(`${base()}/links/${id}/listing`)
        if (version !== generation || requestVersion !== detailVersion) return
        details.value = { ...details.value, [id]: { ...(details.value[id] || {}), ...(data.item || {}), price: data.item?.price ?? null, checked_at: new Date().toISOString() } }
    } catch (failure) { if (version === generation && requestVersion === detailVersion) error.value = avitoError(failure) }
    finally { if (version === generation && requestVersion === detailVersion) remoteLoading.value = false }
}
function invalidateContent() { prepareVersion++; prepared.value = null }
async function prepareContent() {
    if (!link.value || !fields.value.length || busy.value) return
    const version = generation, requestVersion = ++prepareVersion
    preparing.value = true; error.value = ''
    try {
        const { data } = await axios.post(`${base()}/links/${link.value.id}/preview`, {
            fields: [...fields.value], include_facts: includeFacts.value,
            media_ids: fields.value.includes('images') ? (context.value.good?.media || []).slice(0, 10).map(item => item.id) : [],
        })
        if (version !== generation || requestVersion !== prepareVersion) return
        prepared.value = data.preview
        applyLinkContext(data)
    } catch (failure) { if (version === generation && requestVersion === prepareVersion) error.value = avitoError(failure) }
    finally { if (version === generation) preparing.value = false }
}
function applyLinkContext(data) {
    if (data.link) context.value.links = links.value.map(item => item.id === data.link.id ? { ...data.link, history: data.history || item.history || [] } : item)
}
async function priceApplied(data) {
    applyLinkContext(data)
    await publicationChanged()
    if (link.value) refreshListing()
}
async function copy(value, label) {
    try { await navigator.clipboard.writeText(String(value)); notice.value = `${label} скопировано.` }
    catch { error.value = 'Не удалось скопировать. Выделите текст и скопируйте вручную.' }
}
async function publicationChanged() {
    emit('changed')
    const version = generation
    try {
        const { data } = await axios.get(base())
        if (version !== generation) return
        context.value = data
        if (!links.value.some(item => Number(item.id) === Number(selectedLink.value))) selectedLink.value = links.value[0]?.id || null
    } catch (failure) { if (version === generation) error.value = avitoError(failure) }
}
function reset() { invalidateContent(); picker.value = false; removing.value = null; notice.value = ''; error.value = ''; publications.value?.reset?.() }
watch(tab, value => { if (value === 'publication') publicationVisited.value = true })
watch([fields, includeFacts], invalidateContent, { deep: true })
watch(selectedLink, () => { invalidateContent(); removing.value = null; detailVersion++; remoteLoading.value = false })
watch([busy, publicationDirty], ([value, dirty]) => emit('state', { busy: value, dirty }), { immediate: true })
watch(() => props.goodId, () => {
    generation++; listVersion++; detailVersion++; prepareVersion++
    context.value = null; status.value = null; account.value = null; selectedLink.value = null; details.value = {}; listings.value = []
    connecting.value = false; loading.value = false; action.value = ''; listLoading.value = false; remoteLoading.value = false; preparing.value = false; priceOpen.value = false
    priceBusy.value = false; publicationBusy.value = false; publicationDirty.value = false; publicationVisited.value = false
    connectionError.value = ''; tab.value = 'listings'; reset()
    if (props.active) load()
}, { immediate: true })
watch(() => props.active, active => { if (active && !context.value && !loading.value) load() })
onScopeDispose(() => { generation++; listVersion++; detailVersion++; prepareVersion++ })
defineExpose({ reset, refresh: load })
</script>

<template>
    <section class="good-avito" aria-label="Объявления товара в Авито">
        <header class="avito-header"><div><h3>Товар в Авито</h3><p>Связанные объявления, цена и материалы для публикации.</p></div><div class="header-actions"><span class="connection" :class="{ connected: account?.id }"><i />{{ connecting ? 'Проверка подключения…' : account?.id ? `Аккаунт ${account.id}` : apiReady ? 'Подключение настроено' : 'Подключение недоступно' }}</span><button :disabled="busy || loading" @click="load">Обновить</button><a href="/Ameise/avito" target="_blank" rel="noopener">Настройки Авито ↗</a></div></header>
        <p v-if="connectionError" class="feedback warning" role="alert">{{ connectionError }} <button :disabled="connecting || !apiReady" @click="connect">Проверить снова</button></p>
        <p v-if="error" class="feedback error" role="alert">{{ error }}</p><p v-if="notice" class="feedback success" role="status">{{ notice }}</p>
        <p v-if="loading && !context" class="empty" role="status">Загрузка связей товара…</p>
        <template v-if="context">
            <div class="toolbar"><div class="segments" role="group" aria-label="Разделы Авито"><button :disabled="busy" :aria-pressed="tab === 'listings'" @click="tab = 'listings'">Объявления · {{ links.length }}</button><button :disabled="busy" :aria-pressed="tab === 'publication'" @click="tab = 'publication'">Публикация и контент</button></div><button v-if="tab === 'listings'" :disabled="busy || !apiReady" :aria-expanded="picker" @click="openPicker">＋ Связать объявление</button></div>
            <div v-show="tab === 'listings'">
                <section v-if="picker" class="panel picker" aria-label="Выбор объявления Авито">
                    <div class="toolbar"><strong>Объявления вашего аккаунта</strong><select v-model="listingStatus" :disabled="listLoading" aria-label="Статус объявления" @change="loadListings(1)"><option value="">Все статусы</option><option v-for="key in ['active', 'removed', 'old', 'blocked', 'rejected']" :key="key" :value="key">{{ statusLabel(key) }}</option></select></div>
                    <div class="picker-tools"><input v-model="listingSearch" aria-label="Поиск объявления на странице" placeholder="Название или ID на этой странице"><form @submit.prevent="attach(listingId)"><input v-model="listingId" aria-label="ID объявления Авито" placeholder="ID объявления" inputmode="numeric"><button :disabled="busy || !account?.id || !/^\d+$/.test(listingId) || Number(listingId) <= 0">Связать по ID</button></form></div>
                    <p v-if="listLoading || connecting" class="empty" role="status">Загрузка объявлений…</p>
                    <div v-else class="table-scroll"><table><thead><tr><th>Объявление</th><th>Статус</th><th class="number">Цена Авито</th><th></th></tr></thead><tbody><tr v-for="item in filteredListings" :key="item.id"><td><strong>{{ item.title || `Объявление № ${item.id}` }}</strong><small>№ {{ item.id }}</small></td><td>{{ statusLabel(item.status) }}</td><td class="number">{{ avitoMoney(item.price) }}</td><td><button :disabled="busy || links.some(link => Number(link.avito_item_id) === Number(item.id) && Number(link.avito_account_id) === Number(account?.id))" @click="attach(item.id)">Связать</button></td></tr><tr v-if="!filteredListings.length"><td colspan="4" class="empty">На этой странице объявления не найдены. Можно указать ID напрямую.</td></tr></tbody></table></div>
                    <div class="pagination"><button :disabled="listLoading || listPage <= 1" @click="loadListings(listPage - 1)">Назад</button><span>Страница {{ listPage }}</span><button :disabled="listLoading || listings.length < 30" @click="loadListings(listPage + 1)">Далее</button></div>
                </section>
                <div class="table-scroll"><table class="links-table"><thead><tr><th>Объявление</th><th>Аккаунт</th><th>Последняя передача цены</th><th></th></tr></thead><tbody><tr v-for="item in links" :key="item.id" :class="{ selected: Number(item.id) === Number(selectedLink) }"><td><button class="text-button" :aria-pressed="Number(item.id) === Number(selectedLink)" @click="selectedLink = item.id">{{ linkTitle(item) }}</button></td><td>{{ item.avito_account_id }}</td><td>{{ avitoDate(item.last_applied_at) }}<small v-if="item.price_basis">1 {{ item.price_basis.label }} = {{ avitoMoney(item.price_basis.quantity, '') }} {{ item.price_basis.measurement?.unit_label || context.measurement?.unit_label }}</small></td><td><button :disabled="busy" @click="selectedLink = item.id; priceOpen = true">Передать цену…</button></td></tr><tr v-if="!links.length"><td colspan="4" class="empty">Свяжите существующее объявление или откройте «Публикация и контент» для подготовки нового.</td></tr></tbody></table></div>
                <section v-if="link" class="panel linked-detail">
                    <div class="toolbar"><div><strong>{{ linkTitle(link) }}</strong><small>№ {{ link.avito_item_id }} · аккаунт {{ link.avito_account_id }}</small></div><div class="button-group"><a v-if="avitoListingUrl(detail?.url)" :href="avitoListingUrl(detail.url)" target="_blank" rel="noopener noreferrer">Открыть в Авито ↗</a><button :disabled="remoteLoading || busy || !apiReady" @click="refreshListing">{{ remoteLoading ? 'Проверка…' : 'Обновить из Авито' }}</button><button :disabled="busy" @click="removing = link.id">Отвязать</button></div></div>
                    <div v-if="removing === link.id" class="feedback warning">Удалить связь? Объявление и товар сохранятся. <button :disabled="busy" @click="detach">Удалить связь</button><button :disabled="busy" @click="removing = null">Отмена</button></div>
                    <dl v-if="detail" class="facts"><div><dt>Статус</dt><dd>{{ statusLabel(detail.status) }}</dd></div><div><dt>Цена из Авито</dt><dd>{{ detail.price == null ? 'API не вернул значение' : avitoMoney(detail.price) }}</dd></div><div><dt>Проверено</dt><dd>{{ avitoDate(detail.checked_at) }}</dd></div></dl>
                    <p v-else class="hint">Нажмите «Обновить из Авито», чтобы проверить текущее состояние объявления.</p>
                    <details class="content-tools"><summary>Название, описание и фотографии из товара</summary><p class="hint">Для существующего объявления подготовьте материалы и перенесите их в редактор Авито. Для объявлений, управляемых автозагрузкой, используйте «Публикация и контент».</p><div class="button-group"><label v-for="(label, field) in { title: 'Название', description: 'Описание', images: 'Фото' }" :key="field"><input v-model="fields" type="checkbox" :value="field" :disabled="preparing">{{ label }}</label><label><input v-model="includeFacts" type="checkbox" :disabled="preparing">Добавить характеристики</label><button :disabled="busy || !fields.length" @click="prepareContent">Подготовить материалы</button></div>
                        <div v-if="prepared" class="prepared-content"><p v-for="warning in prepared.warnings || []" :key="warning" class="hint">{{ warning }}</p><section v-if="prepared.title"><div class="toolbar"><strong>Название</strong><button @click="copy(prepared.title.good_value, 'Название')">Копировать</button></div><p>{{ prepared.title.good_value }}</p></section><section v-if="prepared.description"><div class="toolbar"><strong>Описание</strong><button @click="copy(prepared.description.good_value, 'Описание')">Копировать</button></div><pre>{{ prepared.description.good_value }}</pre></section><div v-if="prepared.images?.length" class="media"><a v-for="image in prepared.images" :key="image.id" :href="image.download_url" :title="`Скачать ${image.title || 'фото'}`"><img :src="image.url || image.full_url" :alt="image.title || 'Фото товара'"><span>Скачать</span></a></div></div>
                    </details>
                    <details v-if="link.history?.length" class="history"><summary>История синхронизации · {{ link.history.length }}</summary><div class="table-scroll"><table><thead><tr><th>Дата</th><th>Действие</th><th>Данные</th><th>Цена / объём</th></tr></thead><tbody><tr v-for="entry in link.history" :key="entry.id"><td>{{ avitoDate(entry.created_at) }}</td><td>{{ ({ prepared: 'Подготовлено', applied: 'Цена передана', failed: 'Ошибка', unknown: 'Результат уточняется', price_applied_manual_ready: 'Цена передана; материалы подготовлены' })[entry.status] || entry.status }}</td><td>{{ (entry.selected_fields || []).map(field => ({ price: 'Цена', title: 'Название', description: 'Описание', images: 'Фото' })[field] || field).join(', ') }}</td><td :title="entry.price?.formula"><template v-if="entry.price?.avito_value != null">{{ avitoMoney(entry.price.avito_value) }} / {{ entry.price.price_basis?.label || 'ед.' }}<small v-if="entry.price.price_basis">{{ avitoMoney(entry.price.price_basis.quantity, '') }} {{ entry.price.price_basis.measurement?.unit_label }}</small></template><span v-else>—</span></td></tr></tbody></table></div></details>
                </section>
            </div>
            <div v-if="publicationVisited" v-show="tab === 'publication'" class="publication-panel">
                <AvitoPublications v-if="workspace.ready" :key="`${goodId}:${workspace.account_id}`" ref="publications" :good-id="goodId" :good-name="context.good?.name" :linked-listings="links" :workspace="workspace" :enabled="apiReady" :mutations-enabled="Boolean(status?.mutations_enabled)" @notice="notice = $event" @error="error = $event" @changed="publicationChanged" @state="publicationBusy = !!$event.busy; publicationDirty = !!$event.dirty" />
                <p v-else class="empty">Для публикации проверьте подключение к аккаунту Авито. <button :disabled="connecting || !apiReady" @click="connect">Проверить подключение</button></p>
            </div>
        </template>
        <GoodAvitoPriceDialog v-model="priceOpen" :good-id="goodId" :link-id="selectedLink" @state="priceBusy = $event.busy" @applied="priceApplied" />
    </section>
</template>

<style scoped>
.good-avito { min-width: 0; color: #3e3746; font: 12px/1.45 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }.avito-header { display: flex; align-items: start; justify-content: space-between; gap: 12px; padding: 10px 0 16px; } h3 { margin: 0 0 3px; font-size: 17px; } p { margin: 0; }.avito-header p, .hint { color: #8d809b; font-size: 11px; }.header-actions, .toolbar, .button-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }.header-actions { justify-content: flex-end; }.toolbar { justify-content: space-between; margin-bottom: 10px; }.toolbar > div > small { display: block; color: #978ca2; font-size: 10px; }.connection { display: inline-flex; align-items: center; gap: 5px; color: #8a8194; font-size: 10px; }.connection i { width: 6px; height: 6px; border-radius: 50%; background: #b9acbd; }.connection.connected i { background: #69a27d; }
button, input, select { border: 1px solid #d8d1e0; border-radius: 4px; background: #fff; padding: 5px 8px; color: #4b4254; font: inherit; } input, select { min-width: 0; }button:disabled { opacity: .5; cursor: default; }button:not(:disabled), summary { cursor: pointer; }button:focus-visible, input:focus-visible, select:focus-visible, summary:focus-visible { outline: 2px solid #7b99d1; outline-offset: 2px; }a { color: #597bb7; text-decoration: none; }.segments { display: flex; gap: 2px; padding: 3px; border: 1px solid #d8d1e0; border-radius: 6px; background: #eeebf3; }.segments button { background: transparent; border: 0; }.segments button[aria-pressed=true] { background: #fff; box-shadow: 0 1px 3px #44364d1a; }.feedback { padding: 9px 12px; margin-bottom: 10px; border-radius: 5px; }.feedback button { padding: 3px 7px; margin-left: 6px; }.error { color: #ac3e55; background: #fff0f3; }.success { color: #417353; background: #f0f8f2; }.warning { color: #90783f; background: #fcf8ee; }.empty { padding: 26px 12px; text-align: center; color: #94879f; }
.table-scroll { width: 100%; overflow-x: auto; border: 1px solid #dcd6e3; border-radius: 5px; }table { width: 100%; border-collapse: collapse; text-align: left; font-size: 11px; font-variant-numeric: tabular-nums; }th { background: #f0edf5; color: #8e829c; font-size: 10px; font-weight: 600; letter-spacing: .03em; }th, td { padding: 7px 9px; border-bottom: 1px solid #e6e1ec; }tr:last-child td { border-bottom: 0; }tbody tr:nth-child(even) { background: #fbf9fd; }td small { display: block; color: #978ca2; font-size: 10px; }td .text-button { border: 0; background: transparent; padding: 0; color: #5d486f; text-align: left; }tr.selected { background: #f0f4ff !important; }.number { text-align: right; white-space: nowrap; }
.panel { margin: 12px 0; padding: 12px; border: 1px solid #e0dae7; border-radius: 7px; background: #fff; }.picker-tools { display: flex; gap: 8px; margin-bottom: 10px; }.picker-tools > input { flex: 1; }.picker-tools form { display: flex; gap: 5px; }.picker-tools form input { width: 140px; }.pagination { display: flex; justify-content: flex-end; align-items: center; gap: 8px; padding-top: 8px; color: #94879e; font-size: 10px; }.facts { display: flex; gap: 30px; margin: 10px 0; padding: 10px; background: #faf9fc; border-radius: 4px; }.facts dt { color: #96889f; font-size: 10px; }.facts dd { margin: 3px 0 0; }.content-tools, .history { margin-top: 13px; padding-top: 11px; border-top: 1px solid #eee9f3; }summary { color: #5f7eb4; font-size: 11px; }.content-tools > p { margin: 10px 0; }.content-tools label { display: flex; align-items: center; gap: 4px; font-size: 11px; }.prepared-content { display: grid; gap: 12px; margin-top: 12px; }.prepared-content section { padding: 10px; border: 1px solid #e2dcea; border-radius: 5px; }.prepared-content pre { font: inherit; white-space: pre-wrap; overflow-wrap: anywhere; margin: 0; max-height: 260px; overflow-y: auto; }.media { display: flex; gap: 8px; flex-wrap: wrap; }.media a { width: 80px; text-align: center; font-size: 10px; }.media img { width: 80px; height: 80px; object-fit: cover; border-radius: 5px; }.media span { display: block; }.history > .table-scroll { margin-top: 10px; }.publication-panel { min-width: 0; }
@media (max-width: 700px) { .avito-header { flex-direction: column; }.header-actions { justify-content: flex-start; }.picker-tools { flex-direction: column; }.picker-tools form input { flex: 1; }.facts { gap: 15px; flex-wrap: wrap; }.links-table { min-width: 580px; }.segments button { font-size: 10px; } }
</style>
