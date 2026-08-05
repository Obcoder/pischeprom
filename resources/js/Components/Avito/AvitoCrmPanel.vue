<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import axios from 'axios'
import AvitoMessageTemplates from './AvitoMessageTemplates.vue'

const props = defineProps({
    chat: { type: Object, default: null },
})

const emit = defineEmits(['notice', 'error', 'chat-updated', 'refresh-messages', 'insert-template', 'template-sent'])

const loading = ref(false)
const saving = ref(false)
const activeTab = ref('client')
const crm = ref({ entity: null, candidates: [], orders: [] })
const options = ref({
    entity_classifications: [],
    countries: [],
    building_types: [],
    order_statuses: [],
    currency_codes: ['RUB'],
})
const optionsLoaded = ref(false)

const entitySearch = ref('')
const entityResults = ref([])
const entitySearching = ref(false)
const createEntityOpen = ref(false)
const emptyEntityForm = () => ({
    name: '',
    full_name: '',
    entity_classification_id: null,
    INN: '',
    KPP: '',
    OGRN: '',
    legal_address: '',
    country_id: null,
    bank_account_number: '',
    bank_name: '',
    bank_bic: '',
    bank_corr_account: '',
})
const entityForm = reactive(emptyEntityForm())
const manualPhone = ref('')

const buildingOpen = ref(false)
const buildingForm = reactive({ candidate_id: null, city_id: null, building_type_id: null, address: '', postcode: '' })
const citySearch = ref('')
const cityResults = ref([])
const citySearching = ref(false)
const addressLookupLoading = ref(false)
const addressLookupMessage = ref('')

const goodsSearch = ref('')
const goods = ref([])
const goodsLoading = ref(false)
const selectedGood = ref(null)
const productDialog = ref(false)
const productForm = reactive({
    intro: '',
    price_value_id: null,
    quantity: 1,
    include_description: true,
    include_price: true,
    include_stock: true,
    include_link: true,
    media_ids: [],
})

const orderItems = ref([])
const orderForm = reactive({
    order_status_id: null,
    contact_telephone_id: null,
    building_ids: [],
    currency_code: 'RUB',
    preferred_delivery_time: '',
    internal_comment: '',
    send_confirmation: true,
})

let entityTimer = null
let cityTimer = null
let goodsTimer = null

const pendingPhones = computed(() => crm.value.candidates.filter((item) => item.type === 'phone' && item.status === 'pending'))
const pendingAddresses = computed(() => crm.value.candidates.filter((item) => item.type === 'address' && item.status === 'pending'))
const selectedPrice = computed(() => selectedGood.value?.prices?.find((item) => item.id === productForm.price_value_id) || null)
const orderTotal = computed(() => orderItems.value.reduce((total, item) => {
    const quantity = Number(item.quantity) || 0
    const price = item.unit_price === '' || item.unit_price === null ? 0 : Number(item.unit_price) || 0
    return total + quantity * price
}, 0))
const productPreview = computed(() => {
    if (!selectedGood.value) return ''
    const lines = [productForm.intro, selectedGood.value.name]
    if (productForm.include_description && selectedGood.value.description) lines.push(selectedGood.value.description.slice(0, 430))
    if (selectedGood.value.denominator) lines.push(`Фасовка: ${formatNumber(selectedGood.value.denominator)} кг`)
    if (selectedGood.value.country?.name) lines.push(`Страна: ${selectedGood.value.country.name}`)
    if (productForm.include_price && selectedPrice.value && selectedPrice.value.amount !== null) {
        const quantity = Number(productForm.quantity) || 0
        const total = quantity > 0 ? ` × ${formatNumber(quantity)} = ${formatMoney(selectedPrice.value.amount * quantity, selectedPrice.value.currency_code)}` : ''
        lines.push(`Цена · ${selectedPrice.value.name}: ${formatMoney(selectedPrice.value.amount, selectedPrice.value.currency_code)}${total}`)
    }
    if (productForm.include_stock) lines.push(`Наличие: ${availabilityLabel(selectedGood.value)}`)
    if (productForm.include_link && selectedGood.value.public_url) lines.push(selectedGood.value.public_url)
    return lines.filter(Boolean).join('\n')
})

watch(() => props.chat?.id, async (id) => {
    resetTransientState()
    if (!id) return
    await Promise.all([loadOptions(), loadCrm(), searchEntities(), searchCities(), searchGoods()])
}, { immediate: true })

watch(entitySearch, () => {
    clearTimeout(entityTimer)
    entityTimer = setTimeout(searchEntities, 260)
})

watch(citySearch, () => {
    clearTimeout(cityTimer)
    cityTimer = setTimeout(searchCities, 260)
})

watch(goodsSearch, () => {
    clearTimeout(goodsTimer)
    goodsTimer = setTimeout(searchGoods, 280)
})

async function loadOptions() {
    if (optionsLoaded.value) return
    try {
        const { data } = await axios.get('/api/avito/messenger/crm/options')
        options.value = data
        optionsLoaded.value = true
        setOrderDefaults()
    } catch (exception) {
        fail(exception, 'Не удалось загрузить CRM-справочники.')
    }
}

async function loadCrm() {
    if (!props.chat?.id) return
    loading.value = true
    try {
        const { data } = await axios.get(`/api/avito/messenger/chats/${props.chat.id}/crm`)
        crm.value = data
        if (!orderForm.contact_telephone_id) orderForm.contact_telephone_id = data.entity?.telephones?.[0]?.id || null
        if (!orderForm.building_ids.length && data.entity?.buildings?.[0]) orderForm.building_ids = [data.entity.buildings[0].id]
        if (!entityForm.name) entityForm.name = props.chat.peer_name || props.chat.title || 'Клиент Avito'
    } catch (exception) {
        fail(exception, 'Не удалось загрузить карточку клиента Avito.')
    } finally {
        loading.value = false
    }
}

async function searchEntities() {
    entitySearching.value = true
    try {
        const { data } = await axios.get('/api/avito/messenger/crm/entities', { params: { search: entitySearch.value || undefined } })
        entityResults.value = data.items || []
    } catch (exception) {
        fail(exception, 'Не удалось найти клиентов.')
    } finally {
        entitySearching.value = false
    }
}

async function searchCities() {
    citySearching.value = true
    try {
        const { data } = await axios.get('/api/avito/messenger/crm/cities', { params: { search: citySearch.value || undefined } })
        cityResults.value = data.items || []
    } catch (exception) {
        fail(exception, 'Не удалось найти города.')
    } finally {
        citySearching.value = false
    }
}

async function searchGoods() {
    goodsLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/messenger/crm/goods', { params: { search: goodsSearch.value || undefined } })
        goods.value = data.items || []
    } catch (exception) {
        fail(exception, 'Не удалось загрузить товары.')
    } finally {
        goodsLoading.value = false
    }
}

async function linkEntity(entityId) {
    if (!props.chat?.id) return
    saving.value = true
    try {
        const { data } = await axios.put(`/api/avito/messenger/chats/${props.chat.id}/crm/entity`, { entity_id: entityId })
        notify(data.message)
        await loadCrm()
        emit('chat-updated')
    } catch (exception) {
        fail(exception, 'Не удалось привязать клиента.')
    } finally {
        saving.value = false
    }
}

async function unlinkEntity() {
    if (!window.confirm('Удалить связь клиента со всеми чатами этого Avito-пользователя?')) return
    saving.value = true
    try {
        const { data } = await axios.delete(`/api/avito/messenger/chats/${props.chat.id}/crm/entity`)
        notify(data.message)
        await loadCrm()
        emit('chat-updated')
    } catch (exception) {
        fail(exception, 'Не удалось удалить связь клиента.')
    } finally {
        saving.value = false
    }
}

async function createEntity() {
    if (!entityForm.name.trim()) return
    saving.value = true
    try {
        const { data } = await axios.post(`/api/avito/messenger/chats/${props.chat.id}/crm/entity`, {
            name: entityForm.name.trim(),
            full_name: entityForm.full_name.trim() || null,
            entity_classification_id: entityForm.entity_classification_id,
            INN: entityForm.INN.trim() || null,
            KPP: entityForm.KPP.trim() || null,
            OGRN: entityForm.OGRN.trim() || null,
            legal_address: entityForm.legal_address.trim() || null,
            country_id: entityForm.country_id,
            bank_account_number: entityForm.bank_account_number.trim() || null,
            bank_name: entityForm.bank_name.trim() || null,
            bank_bic: entityForm.bank_bic.trim() || null,
            bank_corr_account: entityForm.bank_corr_account.trim() || null,
        })
        notify(data.message)
        createEntityOpen.value = false
        await loadCrm()
        emit('chat-updated')
    } catch (exception) {
        fail(exception, 'Не удалось создать клиента.')
    } finally {
        saving.value = false
    }
}

async function savePhone(candidate = null) {
    activeTab.value = 'client'
    if (!crm.value.entity) {
        fail(null, 'Сначала создайте или привяжите Entity.')
        return
    }
    saving.value = true
    try {
        const { data } = await axios.post(`/api/avito/messenger/chats/${props.chat.id}/crm/telephones`, candidate
            ? { candidate_id: candidate.id }
            : { number: manualPhone.value })
        notify(data.message)
        manualPhone.value = ''
        await loadCrm()
        emit('chat-updated')
    } catch (exception) {
        fail(exception, 'Не удалось сохранить телефон.')
    } finally {
        saving.value = false
    }
}

function prepareAddressCandidate(candidate) {
    activeTab.value = 'client'
    if (!crm.value.entity) {
        fail(null, 'Сначала создайте или привяжите Entity.')
        return
    }
    buildingOpen.value = true
    buildingForm.candidate_id = candidate?.id || null
    buildingForm.address = candidate?.raw_value || ''
    buildingForm.postcode = ''
    addressLookupMessage.value = ''
}

async function lookupAddress() {
    if (!buildingForm.city_id || !buildingForm.address.trim()) return
    addressLookupLoading.value = true
    addressLookupMessage.value = ''
    try {
        const { data } = await axios.get('/web/entities/building-postcode', {
            params: { city_id: buildingForm.city_id, address: buildingForm.address.trim() },
        })
        const match = data.data
        if (match?.postcode) buildingForm.postcode = match.postcode
        addressLookupMessage.value = match?.value || (match?.postcode ? `Индекс ${match.postcode} найден.` : 'DaData не нашла точного адреса.')
    } catch (exception) {
        addressLookupMessage.value = exception?.response?.data?.message || 'DaData сейчас недоступна; адрес можно сохранить вручную.'
    } finally {
        addressLookupLoading.value = false
    }
}

async function saveBuilding() {
    if (!crm.value.entity) return fail(null, 'Сначала создайте или привяжите Entity.')
    saving.value = true
    try {
        const { data } = await axios.post(`/api/avito/messenger/chats/${props.chat.id}/crm/buildings`, {
            candidate_id: buildingForm.candidate_id,
            city_id: buildingForm.city_id,
            building_type_id: buildingForm.building_type_id,
            address: buildingForm.address.trim(),
            postcode: buildingForm.postcode.trim() || null,
        })
        notify(data.message)
        buildingOpen.value = false
        Object.assign(buildingForm, { candidate_id: null, city_id: null, building_type_id: null, address: '', postcode: '' })
        await loadCrm()
        emit('chat-updated')
    } catch (exception) {
        fail(exception, 'Не удалось сохранить адрес.')
    } finally {
        saving.value = false
    }
}

async function rejectCandidate(candidate) {
    try {
        const { data } = await axios.patch(`/api/avito/messenger/crm/candidates/${candidate.id}`, { status: 'rejected' })
        notify(data.message)
        await loadCrm()
        emit('refresh-messages')
    } catch (exception) {
        fail(exception, 'Не удалось скрыть подсказку.')
    }
}

function addToOrder(good) {
    const existing = orderItems.value.find((item) => item.good_id === good.id)
    if (existing) {
        existing.quantity = Number(existing.quantity || 0) + 1
    } else {
        const price = good.prices?.[0] || null
        orderItems.value.push({
            good_id: good.id,
            name: good.name,
            image: good.media?.[0]?.url || null,
            quantity: 1,
            unit_price: price?.amount ?? '',
        })
        if (price?.currency_code) orderForm.currency_code = price.currency_code
    }
    activeTab.value = 'order'
    notify(`«${good.name}» добавлен в заказ.`)
}

function removeOrderItem(goodId) {
    orderItems.value = orderItems.value.filter((item) => item.good_id !== goodId)
}

async function createOrder() {
    if (!crm.value.entity || !orderItems.value.length) return
    saving.value = true
    try {
        const { data } = await axios.post(`/api/avito/messenger/chats/${props.chat.id}/crm/orders`, {
            ...orderForm,
            items: orderItems.value.map((item) => ({
                good_id: item.good_id,
                quantity: Number(item.quantity),
                unit_price: item.unit_price === '' || item.unit_price === null ? null : Number(item.unit_price),
            })),
        })
        notify(data.outbound?.warnings?.[0] || `${data.message} ${data.order.number}`)
        orderItems.value = []
        orderForm.preferred_delivery_time = ''
        orderForm.internal_comment = ''
        await loadCrm()
        emit('refresh-messages')
    } catch (exception) {
        fail(exception, 'Не удалось создать заказ.')
    } finally {
        saving.value = false
    }
}

function openProduct(good) {
    selectedGood.value = good
    const price = good.prices?.[0] || null
    Object.assign(productForm, {
        intro: '',
        price_value_id: price?.id || null,
        quantity: 1,
        include_description: true,
        include_price: true,
        include_stock: true,
        include_link: true,
        media_ids: good.media?.[0]?.id ? [good.media[0].id] : [],
    })
    productDialog.value = true
}

async function sendProduct() {
    if (!selectedGood.value) return
    saving.value = true
    try {
        const { data } = await axios.post(
            `/api/avito/messenger/chats/${props.chat.id}/crm/goods/${selectedGood.value.id}/send`,
            { ...productForm },
        )
        notify(data.warnings?.[0] || data.message)
        productDialog.value = false
        emit('refresh-messages')
    } catch (exception) {
        fail(exception, 'Не удалось отправить товар в чат Avito.')
    } finally {
        saving.value = false
    }
}

function openCatalog() {
    activeTab.value = 'catalog'
    if (!goods.value.length) searchGoods()
}

function openTemplates() {
    activeTab.value = 'templates'
}

function setOrderDefaults() {
    if (!orderForm.order_status_id) {
        orderForm.order_status_id = options.value.order_statuses.find((item) => item.code === 'open')?.id
            || options.value.order_statuses[0]?.id
            || null
    }
    if (!options.value.currency_codes.includes(orderForm.currency_code)) orderForm.currency_code = options.value.currency_codes[0] || 'RUB'
}

function resetTransientState() {
    crm.value = { entity: null, candidates: [], orders: [] }
    activeTab.value = 'client'
    entitySearch.value = ''
    entityResults.value = []
    createEntityOpen.value = false
    Object.assign(entityForm, emptyEntityForm(), {
        name: props.chat?.peer_name || props.chat?.title || 'Клиент Avito',
    })
    manualPhone.value = ''
    buildingOpen.value = false
    orderItems.value = []
    orderForm.contact_telephone_id = null
    orderForm.building_ids = []
}

function availabilityLabel(good) {
    return good.availability?.status === 'in_stock'
        ? 'в наличии'
        : (good.availability?.status === 'out_of_stock' ? 'нет в наличии' : 'под заказ')
}

function formatMoney(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') return 'Цена не задана'
    return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(Number(value))} ${currency}`
}

function formatNumber(value) {
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 3 }).format(Number(value) || 0)
}

function formatDate(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

function notify(message) {
    emit('notice', message)
}

function fail(exception, fallback) {
    emit('error', exception?.response?.data?.message || fallback)
}

defineExpose({
    acceptPhoneCandidate: savePhone,
    prepareAddressCandidate,
    openCatalog,
    openTemplates,
    refresh: loadCrm,
})

onBeforeUnmount(() => {
    clearTimeout(entityTimer)
    clearTimeout(cityTimer)
    clearTimeout(goodsTimer)
})
</script>

<template>
    <aside class="avito-crm-pane">
        <header class="crm-header">
            <div><span>Avito CRM</span><strong>Клиент · заказ · товары · шаблоны</strong></div>
            <v-btn icon="mdi-refresh" size="x-small" variant="text" :loading="loading" title="Обновить CRM-карточку" @click="loadCrm" />
        </header>

        <v-tabs v-model="activeTab" class="crm-tabs" density="compact" grow>
            <v-tab value="client"><v-icon icon="mdi-account-card-outline" size="15" /><span>Клиент</span><b v-if="pendingPhones.length + pendingAddresses.length">{{ pendingPhones.length + pendingAddresses.length }}</b></v-tab>
            <v-tab value="order"><v-icon icon="mdi-cart-outline" size="15" /><span>Заказ</span><b v-if="orderItems.length">{{ orderItems.length }}</b></v-tab>
            <v-tab value="catalog"><v-icon icon="mdi-package-variant-closed" size="15" /><span>Товары</span></v-tab>
            <v-tab value="templates"><v-icon icon="mdi-text-box-multiple-outline" size="15" /><span>Шаблоны</span></v-tab>
        </v-tabs>

        <v-progress-linear v-if="loading" indeterminate color="deep-purple-accent-1" height="2" />

        <div class="crm-content">
            <section v-if="activeTab === 'client'" class="crm-section">
                <div v-if="crm.entity" class="entity-card">
                    <div class="entity-card__top">
                        <v-avatar color="deep-purple-darken-2" size="34"><v-icon icon="mdi-account-check-outline" size="19" /></v-avatar>
                        <div><span>Связанный клиент</span><a :href="`/Ameise/entity/${crm.entity.id}`" target="_blank">{{ crm.entity.name }}</a><small>{{ crm.entity.classification || 'Классификация не задана' }}</small></div>
                        <v-btn icon="mdi-link-variant-off" color="error" size="x-small" variant="text" title="Отвязать" @click="unlinkEntity" />
                    </div>
                    <div v-if="crm.entity.telephones?.length" class="entity-facts">
                        <span v-for="phone in crm.entity.telephones" :key="phone.id"><v-icon icon="mdi-phone-outline" size="12" />{{ phone.number }}</span>
                    </div>
                    <div v-if="crm.entity.buildings?.length" class="entity-buildings">
                        <span v-for="building in crm.entity.buildings" :key="building.id"><v-icon icon="mdi-map-marker-outline" size="12" />{{ building.label }}</span>
                    </div>
                </div>

                <template v-else>
                    <div class="crm-callout"><v-icon icon="mdi-account-plus-outline" /><div><strong>Клиент ещё не связан</strong><span>Найдите существующую Entity или создайте новую. Связь применится ко всем чатам этого Avito ID.</span></div></div>
                    <label class="compact-label">Найти Entity</label>
                    <v-text-field v-model="entitySearch" prepend-inner-icon="mdi-magnify" placeholder="Название, ИНН или телефон" density="compact" variant="outlined" hide-details clearable :loading="entitySearching" />
                    <div class="entity-results">
                        <button v-for="entity in entityResults" :key="entity.id" type="button" @click="linkEntity(entity.id)">
                            <strong>{{ entity.name }}</strong><span>{{ [entity.classification, entity.INN, ...(entity.telephones || [])].filter(Boolean).join(' · ') || `Entity #${entity.id}` }}</span><v-icon icon="mdi-link-variant-plus" size="15" />
                        </button>
                    </div>
                    <v-btn block size="small" variant="tonal" prepend-icon="mdi-account-plus" @click="createEntityOpen = !createEntityOpen">Создать новую Entity</v-btn>
                    <div v-if="createEntityOpen" class="compact-form">
                        <v-text-field v-model="entityForm.name" label="Название / имя клиента" density="compact" variant="outlined" hide-details maxlength="255" />
                        <v-text-field v-model="entityForm.full_name" label="Полное название / ФИО" density="compact" variant="outlined" hide-details clearable maxlength="1024" />
                        <div class="entity-form-grid">
                            <v-select v-model="entityForm.entity_classification_id" :items="options.entity_classifications" item-title="name" item-value="id" label="Классификация" density="compact" variant="outlined" hide-details clearable />
                            <v-select v-model="entityForm.country_id" :items="options.countries" item-title="name" item-value="id" label="Страна" density="compact" variant="outlined" hide-details clearable />
                        </div>
                        <div class="entity-form-grid entity-form-grid--ids">
                            <v-text-field v-model="entityForm.INN" label="ИНН" density="compact" variant="outlined" hide-details clearable maxlength="32" inputmode="numeric" />
                            <v-text-field v-model="entityForm.KPP" label="КПП" density="compact" variant="outlined" hide-details clearable maxlength="32" inputmode="numeric" />
                            <v-text-field v-model="entityForm.OGRN" label="ОГРН" density="compact" variant="outlined" hide-details clearable maxlength="32" inputmode="numeric" />
                        </div>
                        <v-textarea v-model="entityForm.legal_address" label="Юридический адрес" rows="2" density="compact" variant="outlined" hide-details clearable maxlength="1024" />
                        <details class="entity-bank-fields">
                            <summary><span>Банковские реквизиты</span><small>4 необязательных поля</small></summary>
                            <div class="entity-form-grid">
                                <v-text-field v-model="entityForm.bank_account_number" label="Расчётный счёт" density="compact" variant="outlined" hide-details clearable maxlength="34" inputmode="numeric" />
                                <v-text-field v-model="entityForm.bank_bic" label="БИК" density="compact" variant="outlined" hide-details clearable maxlength="16" inputmode="numeric" />
                                <v-text-field v-model="entityForm.bank_name" class="span-two" label="Название банка" density="compact" variant="outlined" hide-details clearable maxlength="1024" />
                                <v-text-field v-model="entityForm.bank_corr_account" class="span-two" label="Корреспондентский счёт" density="compact" variant="outlined" hide-details clearable maxlength="34" inputmode="numeric" />
                            </div>
                        </details>
                        <v-btn size="small" color="deep-purple-lighten-1" :loading="saving" :disabled="!entityForm.name.trim()" @click="createEntity">Создать и привязать</v-btn>
                    </div>
                </template>

                <div v-if="pendingPhones.length" class="fact-group">
                    <div class="section-title"><span><v-icon icon="mdi-phone-in-talk-outline" size="14" />Найдены телефоны</span><b>{{ pendingPhones.length }}</b></div>
                    <article v-for="candidate in pendingPhones" :key="candidate.id" class="fact-card">
                        <div><strong>{{ candidate.normalized_value }}</strong><small>«{{ candidate.raw_value }}» · точность {{ candidate.confidence }}%</small><time>{{ formatDate(candidate.message_at) }}</time></div>
                        <div v-if="!crm.entity && candidate.matched_entities?.length" class="candidate-matches">
                            <button v-for="entity in candidate.matched_entities" :key="entity.id" type="button" @click="linkEntity(entity.id)"><v-icon icon="mdi-account-search" size="12" />Привязать {{ entity.name }}</button>
                        </div>
                        <footer><v-btn v-if="crm.entity" size="x-small" color="green-lighten-1" variant="tonal" prepend-icon="mdi-content-save-check-outline" @click="savePhone(candidate)">Сохранить</v-btn><v-btn size="x-small" variant="text" color="grey" @click="rejectCandidate(candidate)">Скрыть</v-btn></footer>
                    </article>
                </div>

                <div v-if="crm.entity" class="inline-add">
                    <v-text-field v-model="manualPhone" label="Добавить телефон вручную" placeholder="+7 999 123-45-67" density="compact" variant="outlined" hide-details />
                    <v-btn icon="mdi-plus" size="small" variant="tonal" :disabled="!manualPhone.trim()" @click="savePhone()" />
                </div>

                <div v-if="pendingAddresses.length" class="fact-group">
                    <div class="section-title"><span><v-icon icon="mdi-map-marker-radius-outline" size="14" />Найдены адреса</span><b>{{ pendingAddresses.length }}</b></div>
                    <article v-for="candidate in pendingAddresses" :key="candidate.id" class="fact-card">
                        <div><strong>{{ candidate.raw_value }}</strong><small>Точность {{ candidate.confidence }}% · {{ formatDate(candidate.message_at) }}</small></div>
                        <footer><v-btn v-if="crm.entity" size="x-small" color="cyan-lighten-1" variant="tonal" prepend-icon="mdi-office-building-plus-outline" @click="prepareAddressCandidate(candidate)">Создать Building</v-btn><v-btn size="x-small" variant="text" color="grey" @click="rejectCandidate(candidate)">Скрыть</v-btn></footer>
                    </article>
                </div>

                <v-btn v-if="crm.entity && !buildingOpen" block size="small" variant="text" prepend-icon="mdi-map-marker-plus-outline" @click="prepareAddressCandidate(null)">Добавить адрес вручную</v-btn>
                <div v-if="buildingOpen" class="compact-form building-form">
                    <div class="section-title"><span>Новый Building</span><v-btn icon="mdi-close" size="x-small" variant="text" @click="buildingOpen = false" /></div>
                    <v-autocomplete v-model="buildingForm.city_id" v-model:search="citySearch" :items="cityResults" item-title="label" item-value="id" label="Город" density="compact" variant="outlined" hide-details clearable :loading="citySearching" no-filter />
                    <v-textarea v-model="buildingForm.address" label="Адрес" rows="2" auto-grow density="compact" variant="outlined" hide-details />
                    <div class="form-grid"><v-select v-model="buildingForm.building_type_id" :items="options.building_types" item-title="name" item-value="id" label="Тип" density="compact" variant="outlined" hide-details clearable /><v-text-field v-model="buildingForm.postcode" label="Индекс" density="compact" variant="outlined" hide-details /></div>
                    <div class="form-actions"><v-btn size="x-small" variant="text" prepend-icon="mdi-map-search-outline" :loading="addressLookupLoading" :disabled="!buildingForm.city_id || !buildingForm.address.trim()" @click="lookupAddress">Проверить DaData</v-btn><v-btn size="small" color="deep-purple-lighten-1" :loading="saving" :disabled="!buildingForm.city_id || !buildingForm.address.trim()" @click="saveBuilding">Сохранить</v-btn></div>
                    <small v-if="addressLookupMessage" class="form-hint">{{ addressLookupMessage }}</small>
                </div>
            </section>

            <section v-else-if="activeTab === 'order'" class="crm-section">
                <div v-if="!crm.entity" class="crm-callout"><v-icon icon="mdi-account-alert-outline" /><div><strong>Сначала свяжите клиента</strong><span>Заказ должен принадлежать Entity. Откройте вкладку «Клиент».</span></div></div>
                <template v-else>
                    <div v-if="crm.orders?.length" class="recent-orders">
                        <div class="section-title"><span>Заказы из этого чата</span><b>{{ crm.orders.length }}</b></div>
                        <a v-for="order in crm.orders.slice(0, 4)" :key="order.id" :href="`/Ameise/orders/${order.id}`" target="_blank"><span><strong>{{ order.number }}</strong><small>{{ order.status?.name }} · {{ order.items?.length || order.items_count || 0 }} поз.</small></span><b>{{ formatMoney(order.total_amount, order.currency_code) }}</b><v-icon icon="mdi-open-in-new" size="12" /></a>
                    </div>

                    <div class="section-title"><span>Новый заказ</span><v-btn size="x-small" variant="text" prepend-icon="mdi-package-variant-plus" @click="openCatalog">Добавить товары</v-btn></div>
                    <div v-if="!orderItems.length" class="order-empty"><v-icon icon="mdi-cart-plus" size="28" /><span>Добавьте товары из вкладки «Товары».</span><v-btn size="x-small" variant="tonal" @click="openCatalog">Открыть каталог</v-btn></div>
                    <div v-else class="order-lines">
                        <article v-for="item in orderItems" :key="item.good_id">
                            <img v-if="item.image" :src="item.image" alt="" loading="lazy"><div><strong>{{ item.name }}</strong><div><label>Кол-во<input v-model="item.quantity" type="number" min="0.001" step="0.001"></label><label>Цена<input v-model="item.unit_price" type="number" min="0" step="0.01"></label></div></div><v-btn icon="mdi-close" color="error" size="x-small" variant="text" @click="removeOrderItem(item.good_id)" />
                        </article>
                        <div class="order-total"><span>Предварительный итог</span><strong>{{ formatMoney(orderTotal, orderForm.currency_code) }}</strong></div>
                    </div>

                    <div class="compact-form order-form">
                        <div class="form-grid"><v-select v-model="orderForm.order_status_id" :items="options.order_statuses" item-title="name" item-value="id" label="Статус" density="compact" variant="outlined" hide-details /><v-select v-model="orderForm.currency_code" :items="options.currency_codes" label="Валюта" density="compact" variant="outlined" hide-details /></div>
                        <v-select v-model="orderForm.contact_telephone_id" :items="crm.entity.telephones" item-title="number" item-value="id" label="Телефон заказа" density="compact" variant="outlined" hide-details clearable />
                        <v-select v-model="orderForm.building_ids" :items="crm.entity.buildings" item-title="label" item-value="id" label="Адрес доставки" density="compact" variant="outlined" hide-details clearable multiple chips />
                        <v-text-field v-model="orderForm.preferred_delivery_time" label="Желаемое время доставки" density="compact" variant="outlined" hide-details clearable />
                        <v-textarea v-model="orderForm.internal_comment" label="Внутренний комментарий" rows="2" auto-grow density="compact" variant="outlined" hide-details />
                        <v-checkbox v-model="orderForm.send_confirmation" label="Отправить подтверждение заказа в Avito" density="compact" hide-details />
                        <v-btn block color="deep-purple-lighten-1" size="small" prepend-icon="mdi-cart-check" :loading="saving" :disabled="!orderItems.length" @click="createOrder">Создать заказ</v-btn>
                    </div>
                </template>
            </section>

            <section v-else-if="activeTab === 'catalog'" class="crm-section catalog-section">
                <v-text-field v-model="goodsSearch" prepend-inner-icon="mdi-magnify" placeholder="Товар, описание, slug" density="compact" variant="outlined" hide-details clearable :loading="goodsLoading" />
                <div class="goods-list" :class="{ 'is-loading': goodsLoading }">
                    <article v-for="good in goods" :key="good.id">
                        <div class="good-image"><img v-if="good.media?.[0]?.url" :src="good.media[0].url" alt="" loading="lazy"><v-icon v-else icon="mdi-image-off-outline" /></div>
                        <div class="good-copy"><strong>{{ good.name }}</strong><span :class="`stock-${good.availability?.status}`">{{ availabilityLabel(good) }}</span><small>{{ good.prices?.[0] ? `${good.prices[0].name}: ${formatMoney(good.prices[0].amount, good.prices[0].currency_code)}` : 'Опубликованной цены нет' }}</small><em v-if="!good.is_published">Не опубликован</em></div>
                        <div class="good-actions"><v-btn icon="mdi-cart-plus" size="x-small" variant="text" title="Добавить в заказ" @click="addToOrder(good)" /><v-btn icon="mdi-send" color="deep-purple-lighten-1" size="x-small" variant="tonal" title="Отправить клиенту" @click="openProduct(good)" /></div>
                    </article>
                    <div v-if="!goods.length && !goodsLoading" class="order-empty"><v-icon icon="mdi-package-variant-remove" size="28" /><span>Товары не найдены.</span></div>
                </div>
            </section>

            <AvitoMessageTemplates
                v-else
                :chat="chat"
                :crm="crm"
                @notice="notify"
                @error="(message) => emit('error', message)"
                @insert="(payload) => emit('insert-template', payload)"
                @sent="(message) => emit('template-sent', message)"
            />
        </div>

        <footer class="crm-context"><span><v-icon icon="mdi-identifier" size="11" />{{ chat?.peer_user_id || 'peer не определён' }}</span><span><v-icon icon="mdi-message-text-outline" size="11" />{{ chat?.external_chat_id?.slice(0, 14) }}</span></footer>

        <v-dialog v-model="productDialog" max-width="660" scrollable>
            <v-card v-if="selectedGood" class="product-dialog">
                <v-card-title><div><span>Сообщение о товаре</span><strong>{{ selectedGood.name }}</strong></div><v-btn icon="mdi-close" size="small" variant="text" @click="productDialog = false" /></v-card-title>
                <v-card-text>
                    <div class="product-dialog__grid">
                        <div class="product-settings">
                            <v-text-field v-model="productForm.intro" label="Вводная фраза" placeholder="Например: Подобрал подходящий товар" density="compact" variant="outlined" hide-details clearable />
                            <div class="form-grid"><v-select v-model="productForm.price_value_id" :items="selectedGood.prices" :item-title="item => `${item.name} · ${formatMoney(item.amount, item.currency_code)}`" item-value="id" label="Цена" density="compact" variant="outlined" hide-details clearable /><v-text-field v-model="productForm.quantity" type="number" min="0.001" step="0.001" label="Количество" density="compact" variant="outlined" hide-details /></div>
                            <div class="send-options"><v-checkbox v-model="productForm.include_description" label="Описание" density="compact" hide-details /><v-checkbox v-model="productForm.include_price" label="Цена" density="compact" hide-details /><v-checkbox v-model="productForm.include_stock" label="Наличие" density="compact" hide-details /><v-checkbox v-model="productForm.include_link" label="Ссылка" density="compact" hide-details /></div>
                            <div v-if="selectedGood.media?.length" class="media-picker"><span>Фотографии · до 5</span><label v-for="media in selectedGood.media" :key="media.id"><input v-model="productForm.media_ids" type="checkbox" :value="media.id" :disabled="!productForm.media_ids.includes(media.id) && productForm.media_ids.length >= 5"><img :src="media.url" :alt="media.title" loading="lazy"><i v-if="media.is_ava">Главная</i></label></div>
                        </div>
                        <div class="message-preview"><span>Предпросмотр текста</span><pre>{{ productPreview }}</pre><small>Каждое выбранное фото будет отправлено отдельным сообщением.</small></div>
                    </div>
                </v-card-text>
                <v-card-actions><v-btn variant="text" @click="addToOrder(selectedGood); productDialog = false">Добавить в заказ</v-btn><v-spacer /><v-btn color="deep-purple-lighten-1" prepend-icon="mdi-send" :loading="saving" @click="sendProduct">Отправить в Avito</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </aside>
</template>

<style scoped>
.avito-crm-pane { display: flex; overflow: hidden; min-width: 0; min-height: 0; flex-direction: column; color: #e9ebff; border-left: 1px solid #30344d; background: #15182b; }
.crm-header { display: flex; min-height: 52px; align-items: center; gap: 6px; padding: 7px 9px; border-bottom: 1px solid #30344d; background: #1b1e35; }.crm-header > div { min-width: 0; flex: 1; }.crm-header span, .crm-header strong { display: block; }.crm-header span { color: #9d88f4; font-size: 8px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }.crm-header strong { margin-top: 2px; font-size: 11px; }
.crm-tabs { min-height: 38px; border-bottom: 1px solid #2e324a; background: #191c31; }.crm-tabs :deep(.v-btn) { min-width: 0; min-height: 38px; padding: 0 5px; font-size: 8px; letter-spacing: 0; text-transform: none; }.crm-tabs :deep(.v-btn__content) { gap: 3px; }.crm-tabs b { display: grid; min-width: 15px; height: 15px; place-items: center; color: #fff; font-size: 7px; border-radius: 12px; background: #7558d7; }
.crm-content { overflow-y: auto; flex: 1; }.crm-section { display: grid; align-content: start; gap: 8px; padding: 9px; }
.crm-callout { display: grid; grid-template-columns: auto 1fr; gap: 8px; padding: 9px; color: #bfc4de; border: 1px dashed #4a4567; border-radius: 8px; background: #1b1e35; }.crm-callout > .v-icon { color: #a58df6; }.crm-callout strong, .crm-callout span { display: block; }.crm-callout strong { color: #f0f1ff; font-size: 11px; }.crm-callout span { margin-top: 3px; font-size: 9px; line-height: 1.4; }
.compact-label { color: #858baa; font-size: 8px; font-weight: 700; text-transform: uppercase; }.crm-section :deep(.v-field), .product-dialog :deep(.v-field) { font-size: 10px; }.crm-section :deep(.v-field__input), .product-dialog :deep(.v-field__input) { min-height: 34px; padding-top: 4px; padding-bottom: 4px; }.crm-section :deep(.v-label), .product-dialog :deep(.v-label) { font-size: 10px; }
.entity-results { display: grid; overflow-y: auto; max-height: 168px; gap: 3px; }.entity-results button { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 5px; padding: 6px 7px; color: #e4e6fa; text-align: left; border: 1px solid #30344d; border-radius: 6px; background: #1b1e35; cursor: pointer; }.entity-results button:hover { border-color: #6e5da8; background: #23203c; }.entity-results strong, .entity-results span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.entity-results strong { font-size: 10px; }.entity-results span { grid-column: 1; color: #858baa; font-size: 8px; }.entity-results .v-icon { grid-column: 2; grid-row: 1 / 3; align-self: center; color: #9d88f4; }
.compact-form { display: grid; gap: 6px; padding: 8px; border: 1px solid #343851; border-radius: 8px; background: #1b1e35; }.form-grid, .entity-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px; }.entity-form-grid--ids { grid-template-columns: repeat(3, minmax(0, 1fr)); }.entity-form-grid .span-two { grid-column: 1 / -1; }.entity-bank-fields { overflow: hidden; border: 1px solid #343851; border-radius: 7px; background: #171a2f; }.entity-bank-fields summary { display: flex; align-items: center; justify-content: space-between; gap: 5px; padding: 6px 8px; color: #c9cde4; font-size: 9px; cursor: pointer; list-style: none; }.entity-bank-fields summary::-webkit-details-marker { display: none; }.entity-bank-fields summary::after { content: '+'; color: #a995ff; font-size: 13px; }.entity-bank-fields[open] summary::after { content: '−'; }.entity-bank-fields summary small { margin-left: auto; color: #777e9f; font-size: 7px; }.entity-bank-fields > div { padding: 0 6px 6px; }.form-actions { display: flex; align-items: center; justify-content: space-between; gap: 5px; }.form-hint { color: #8e94b4; font-size: 8px; line-height: 1.35; }
.entity-card { padding: 8px; border: 1px solid rgba(118, 216, 177, .25); border-radius: 9px; background: linear-gradient(135deg, rgba(44, 116, 91, .18), rgba(31, 34, 56, .75)); }.entity-card__top { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 7px; }.entity-card__top span, .entity-card__top a, .entity-card__top small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.entity-card__top span { color: #83cdb0; font-size: 7px; font-weight: 800; text-transform: uppercase; }.entity-card__top a { color: #f0f4ff; font-size: 12px; font-weight: 700; text-decoration: none; }.entity-card__top small { color: #8e96b5; font-size: 8px; }.entity-facts, .entity-buildings { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 7px; }.entity-facts span, .entity-buildings span { display: flex; min-width: 0; align-items: center; gap: 3px; padding: 3px 5px; color: #cbd0e8; font-size: 8px; border-radius: 5px; background: rgba(11, 14, 28, .42); }.entity-buildings { display: grid; }.entity-buildings span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.section-title { display: flex; min-height: 24px; align-items: center; justify-content: space-between; gap: 5px; color: #d9dcf0; font-size: 9px; font-weight: 700; }.section-title > span { display: flex; align-items: center; gap: 4px; }.section-title > b { min-width: 17px; padding: 2px 4px; color: #b7a7f8; font-size: 8px; text-align: center; border-radius: 10px; background: #292445; }
.fact-group { display: grid; gap: 4px; }.fact-card { padding: 7px; border: 1px solid #343850; border-radius: 7px; background: #1b1e35; }.fact-card strong, .fact-card small, .fact-card time { display: block; }.fact-card strong { color: #eff1ff; font-size: 10px; }.fact-card small, .fact-card time { overflow: hidden; margin-top: 2px; color: #858baa; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }.fact-card footer { display: flex; justify-content: flex-end; gap: 3px; margin-top: 5px; }.candidate-matches { display: grid; gap: 3px; margin-top: 5px; }.candidate-matches button { display: flex; align-items: center; gap: 3px; padding: 4px 6px; color: #bce7d5; font-size: 8px; text-align: left; border: 1px solid rgba(87, 190, 148, .25); border-radius: 5px; background: rgba(40, 105, 78, .18); cursor: pointer; }
.inline-add { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 4px; }
.recent-orders { display: grid; gap: 4px; }.recent-orders > a { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 5px; padding: 6px 7px; color: #e7e9fb; border: 1px solid #343850; border-radius: 6px; background: #1b1e35; text-decoration: none; }.recent-orders span strong, .recent-orders span small { display: block; }.recent-orders span strong { font-size: 9px; }.recent-orders span small { color: #858baa; font-size: 7px; }.recent-orders > a > b { font-size: 8px; white-space: nowrap; }
.order-empty { display: grid; min-height: 120px; place-items: center; align-content: center; gap: 5px; color: #858baa; text-align: center; border: 1px dashed #3a3e56; border-radius: 8px; }.order-empty span { font-size: 9px; }.order-lines { display: grid; gap: 4px; }.order-lines article { display: grid; grid-template-columns: 32px minmax(0, 1fr) auto; align-items: center; gap: 6px; padding: 5px; border: 1px solid #343850; border-radius: 6px; background: #1b1e35; }.order-lines img { width: 32px; height: 32px; border-radius: 5px; object-fit: cover; }.order-lines article strong { display: block; overflow: hidden; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }.order-lines article > div > div { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; margin-top: 4px; }.order-lines label { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 3px; color: #7f86a6; font-size: 7px; }.order-lines input { min-width: 0; width: 100%; padding: 2px 4px; color: #e9ebff; font-size: 8px; border: 1px solid #3b405a; border-radius: 4px; outline: 0; background: #121527; }.order-total { display: flex; align-items: center; justify-content: space-between; padding: 6px 7px; color: #8e94b4; font-size: 8px; border-top: 1px solid #343850; }.order-total strong { color: #e9ebff; font-size: 11px; }.order-form :deep(.v-selection-control__wrapper) { width: 28px; }.order-form :deep(.v-label) { opacity: .85; }
.catalog-section { grid-template-rows: auto minmax(0, 1fr); min-height: 100%; }.goods-list { display: grid; align-content: start; gap: 4px; transition: opacity .15s; }.goods-list.is-loading { opacity: .55; }.goods-list article { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; align-items: center; gap: 6px; padding: 5px; border: 1px solid #32364f; border-radius: 7px; background: #1b1e35; }.good-image { display: grid; overflow: hidden; width: 42px; height: 42px; place-items: center; color: #69708f; border-radius: 5px; background: #101326; }.good-image img { width: 100%; height: 100%; object-fit: cover; }.good-copy { min-width: 0; }.good-copy strong, .good-copy small, .good-copy em { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.good-copy strong { font-size: 9px; }.good-copy span { display: inline-block; margin: 2px 0; padding: 1px 4px; color: #c6cbe4; font-size: 7px; border-radius: 4px; background: #2b3048; }.good-copy .stock-in_stock { color: #91dfbd; background: rgba(41, 119, 84, .25); }.good-copy .stock-out_of_stock { color: #e1a4ae; background: rgba(137, 54, 67, .22); }.good-copy small { color: #9aa0bf; font-size: 8px; }.good-copy em { color: #d3a064; font-size: 7px; font-style: normal; }.good-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; }
.crm-context { display: flex; min-height: 25px; align-items: center; justify-content: space-between; gap: 5px; padding: 4px 8px; color: #69708f; font-size: 7px; border-top: 1px solid #2d3149; background: #14172a; }.crm-context span { display: flex; overflow: hidden; align-items: center; gap: 3px; text-overflow: ellipsis; white-space: nowrap; }
.product-dialog { color: #ebedff; background: #171a2e; }.product-dialog :deep(.v-card-title) { display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #343850; }.product-dialog :deep(.v-card-title > div) { min-width: 0; flex: 1; }.product-dialog :deep(.v-card-title span), .product-dialog :deep(.v-card-title strong) { display: block; }.product-dialog :deep(.v-card-title span) { color: #9d88f4; font-size: 9px; text-transform: uppercase; }.product-dialog :deep(.v-card-title strong) { overflow: hidden; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }.product-dialog__grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(240px, .8fr); gap: 12px; }.product-settings { display: grid; align-content: start; gap: 8px; }.send-options { display: grid; grid-template-columns: 1fr 1fr; }.send-options :deep(.v-selection-control) { min-height: 28px; }.media-picker { display: flex; flex-wrap: wrap; gap: 6px; }.media-picker > span { width: 100%; color: #949abb; font-size: 9px; }.media-picker label { position: relative; overflow: hidden; width: 64px; height: 64px; border: 2px solid transparent; border-radius: 7px; cursor: pointer; }.media-picker label:has(input:checked) { border-color: #8b70ec; }.media-picker input { position: absolute; opacity: 0; }.media-picker img { width: 100%; height: 100%; object-fit: cover; }.media-picker i { position: absolute; right: 2px; bottom: 2px; padding: 1px 3px; color: #fff; font-size: 6px; font-style: normal; border-radius: 3px; background: #6550bb; }.message-preview { min-width: 0; padding: 10px; border: 1px solid #343850; border-radius: 8px; background: #101326; }.message-preview > span { color: #9481e4; font-size: 8px; font-weight: 800; text-transform: uppercase; }.message-preview pre { margin: 8px 0; color: #eef0ff; font: 11px/1.45 Inter, sans-serif; white-space: pre-wrap; word-break: break-word; }.message-preview small { color: #7f86a6; font-size: 8px; }
@media (max-width: 850px) { .avito-crm-pane { min-height: 620px; border-top: 1px solid #30344d; border-left: 0; }.product-dialog__grid { grid-template-columns: 1fr; } }
</style>
