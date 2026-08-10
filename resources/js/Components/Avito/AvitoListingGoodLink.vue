<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import debounce from 'lodash.debounce'

const props = defineProps({
    item: { type: Object, default: () => ({}) },
    accountId: { type: [Number, String], default: null },
    authConnectionId: { type: [Number, String], default: null },
    mutationsEnabled: { type: Boolean, default: false },
})

const emit = defineEmits(['notice', 'error', 'price-applied'])

const FIELD_OPTIONS = [
    { value: 'title', label: 'Название', icon: 'mdi-format-title', mode: 'manual' },
    { value: 'description', label: 'Описание', icon: 'mdi-text-long', mode: 'manual' },
    { value: 'price', label: 'Цена', icon: 'mdi-currency-rub', mode: 'api' },
    { value: 'images', label: 'Фото', icon: 'mdi-image-multiple-outline', mode: 'manual' },
]

const loading = ref(false)
const goodsLoading = ref(false)
const linking = ref(false)
const preparing = ref(false)
const applying = ref(false)
const localError = ref('')
const link = ref(null)
const history = ref([])
const goods = ref([])
const goodsSearch = ref('')
const selectedGoodId = ref(null)
const relinkOpen = ref(false)
const selectedFields = ref(['title', 'description', 'price', 'images'])
const priceValueId = ref(null)
const mediaIds = ref([])
const includeFacts = ref(true)
const preview = ref(null)
const confirmed = ref(false)

const itemId = computed(() => positiveInteger(props.item?.id))
const resolvedAccountId = computed(() => positiveInteger(props.accountId))
const linkedGood = computed(() => link.value?.good || null)
const goodOptions = computed(() => {
    const byId = new Map()
    if (linkedGood.value?.id) byId.set(Number(linkedGood.value.id), linkedGood.value)
    goods.value.forEach((good) => byId.set(Number(good.id), good))
    return [...byId.values()]
})
const priceOptions = computed(() => (linkedGood.value?.prices || []).map((price) => ({
    title: `${price.name || 'Цена'} · ${formatMoney(price.amount, price.currency_code)}`,
    value: Number(price.id),
    disabled: price.amount === null || price.amount === undefined,
})))
const canPrepare = computed(() => Boolean(
    link.value
    && selectedFields.value.length
    && (!selectedFields.value.includes('price') || positiveInteger(priceValueId.value))
    && !preparing.value,
))
const canApplyPrice = computed(() => Boolean(
    props.mutationsEnabled
    && confirmed.value
    && preview.value?.price?.can_apply
    && !applying.value,
))

const debouncedGoodsSearch = debounce(() => loadGoods(goodsSearch.value), 250)

watch(
    () => `${positiveInteger(props.item?.id) || ''}:${positiveInteger(props.accountId) || ''}`,
    loadLink,
    { immediate: true },
)
watch(goodsSearch, () => debouncedGoodsSearch())
watch(selectedFields, invalidatePreview, { deep: true })
watch(priceValueId, invalidatePreview)
watch(mediaIds, invalidatePreview, { deep: true })
watch(includeFacts, invalidatePreview)
onBeforeUnmount(() => debouncedGoodsSearch.cancel())

async function loadLink() {
    const requestedItemId = itemId.value
    const accountId = resolvedAccountId.value
    resetState()
    if (!requestedItemId || !accountId) return

    loading.value = true
    try {
        const { data } = await axios.get(`/api/avito/listings/${requestedItemId}/good-link`, {
            params: { account_id: accountId },
        })
        if (requestedItemId !== itemId.value || accountId !== resolvedAccountId.value) return
        applyContext(data)
        if (!data.link) await loadGoods('')
    } catch (exception) {
        showError(exception, 'Не удалось загрузить связь объявления с Good.')
    } finally {
        if (requestedItemId === itemId.value) loading.value = false
    }
}

async function loadGoods(search = '') {
    goodsLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/listings/goods', {
            params: { search: String(search || '').trim() || undefined },
        })
        goods.value = Array.isArray(data.items) ? data.items : []
    } catch (exception) {
        showError(exception, 'Не удалось найти Good.')
    } finally {
        goodsLoading.value = false
    }
}

async function linkGood() {
    if (!itemId.value || !resolvedAccountId.value || !positiveInteger(selectedGoodId.value)) return
    linking.value = true
    localError.value = ''
    try {
        const { data } = await axios.put(`/api/avito/listings/${itemId.value}/good-link`, {
            account_id: resolvedAccountId.value,
            good_id: positiveInteger(selectedGoodId.value),
        })
        applyContext(data)
        relinkOpen.value = false
        emit('notice', 'Объявление привязано к Good. Источником истины остаётся база приложения.')
    } catch (exception) {
        showError(exception, 'Не удалось привязать Good.')
    } finally {
        linking.value = false
    }
}

async function unlinkGood() {
    if (!link.value || !window.confirm('Удалить связь объявления с Good? Данные Good и объявление Avito не изменятся.')) return
    linking.value = true
    try {
        await axios.delete(`/api/avito/listings/${itemId.value}/good-link`, {
            data: { account_id: resolvedAccountId.value },
        })
        resetState()
        await loadGoods('')
        emit('notice', 'Связь с Good удалена. Данные не изменялись.')
    } catch (exception) {
        showError(exception, 'Не удалось удалить связь с Good.')
    } finally {
        linking.value = false
    }
}

async function prepareTransfer() {
    if (!canPrepare.value) return
    preparing.value = true
    localError.value = ''
    try {
        const { data } = await axios.post(
            `/api/avito/listings/${itemId.value}/good-transfer/preview`,
            transferPayload(),
        )
        preview.value = data.preview || null
        link.value = data.link || link.value
        history.value = Array.isArray(data.history) ? data.history : history.value
        emit('notice', 'Выбранные данные Good подготовлены. Проверьте значения перед переносом.')
    } catch (exception) {
        showError(exception, 'Не удалось подготовить данные Good.')
    } finally {
        preparing.value = false
    }
}

async function applyGoodPrice() {
    if (!canApplyPrice.value) return
    applying.value = true
    localError.value = ''
    try {
        const { data } = await axios.post(
            `/api/avito/listings/${itemId.value}/good-transfer/apply`,
            {
                ...transferPayload(),
                confirmed: true,
                connection_id: positiveInteger(props.authConnectionId) || undefined,
            },
        )
        preview.value = data.transfer?.preview || preview.value
        link.value = data.link || link.value
        history.value = Array.isArray(data.history) ? data.history : history.value
        confirmed.value = false
        const price = data.transfer?.price?.avito_value
        emit('price-applied', { price, transfer: data.transfer })
        emit('notice', `Цена из Good применена в Avito: ${formatMoney(price, 'RUB')}.`)
    } catch (exception) {
        showError(exception, 'Avito не применил цену из Good.')
    } finally {
        applying.value = false
    }
}

function applyContext(data) {
    link.value = data.link || null
    history.value = Array.isArray(data.history) ? data.history : []
    preview.value = null
    confirmed.value = false
    selectedGoodId.value = data.link?.good_id || null
    if (!data.link) return

    const good = data.link.good || {}
    selectedFields.value = data.link.last_selected_fields?.length
        ? [...data.link.last_selected_fields]
        : FIELD_OPTIONS
            .filter((field) => field.value !== 'description' || good.description)
            .filter((field) => field.value !== 'price' || good.prices?.length)
            .filter((field) => field.value !== 'images' || good.media?.length)
            .map((field) => field.value)
    priceValueId.value = validPriceId(data.link.last_price_value_id, good)
        || good.prices?.find((price) => price.is_public)?.id
        || good.prices?.[0]?.id
        || null
    const availableMedia = new Set((good.media || []).map((media) => Number(media.id)))
    const restoredMedia = (data.link.last_media_ids || []).map(Number).filter((id) => availableMedia.has(id))
    mediaIds.value = restoredMedia.length
        ? restoredMedia
        : (good.media || []).slice(0, 10).map((media) => Number(media.id))
    includeFacts.value = data.link.include_facts !== false
}

function transferPayload() {
    return {
        account_id: resolvedAccountId.value,
        fields: [...selectedFields.value],
        price_value_id: selectedFields.value.includes('price') ? positiveInteger(priceValueId.value) : undefined,
        media_ids: selectedFields.value.includes('images') ? mediaIds.value.map(Number) : [],
        include_facts: includeFacts.value,
        avito: {
            title: props.item?.title || undefined,
            price: Number.isFinite(Number(props.item?.price)) ? Number(props.item.price) : undefined,
        },
    }
}

function resetState() {
    link.value = null
    history.value = []
    selectedGoodId.value = null
    selectedFields.value = ['title', 'description', 'price', 'images']
    priceValueId.value = null
    mediaIds.value = []
    includeFacts.value = true
    preview.value = null
    confirmed.value = false
    localError.value = ''
    relinkOpen.value = false
}

function invalidatePreview() {
    preview.value = null
    confirmed.value = false
}

function toggleField(field) {
    selectedFields.value = selectedFields.value.includes(field)
        ? selectedFields.value.filter((item) => item !== field)
        : [...selectedFields.value, field]
}

function toggleMedia(id) {
    const value = Number(id)
    mediaIds.value = mediaIds.value.includes(value)
        ? mediaIds.value.filter((item) => item !== value)
        : [...mediaIds.value, value].slice(0, 10)
}

async function copyValue(value, label) {
    if (value === null || value === undefined || value === '') return
    try {
        await navigator.clipboard.writeText(String(value))
        emit('notice', `${label} скопировано из Good.`)
    } catch {
        emit('error', 'Браузер не разрешил копирование.')
    }
}

function showError(exception, fallback) {
    localError.value = errorMessage(exception, fallback)
    emit('error', localError.value)
}

function errorMessage(exception, fallback) {
    const errors = exception?.response?.data?.errors
    if (errors && typeof errors === 'object') return Object.values(errors).flat()[0] || fallback
    return exception?.response?.data?.message || fallback
}

function validPriceId(value, good) {
    const id = positiveInteger(value)
    return id && good.prices?.some((price) => Number(price.id) === id) ? id : null
}

function fieldLabel(field) {
    return FIELD_OPTIONS.find((item) => item.value === field)?.label || field
}

function transferStatusLabel(status) {
    return ({
        prepared: 'Подготовлено',
        applied: 'Цена применена',
        price_applied_manual_ready: 'Цена применена · остальное вручную',
        failed: 'Ошибка',
    })[status] || status
}

function positiveInteger(value) {
    const parsed = Number.parseInt(value, 10)
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

function formatMoney(value, currency = 'RUB') {
    if (value === null || value === undefined || value === '') return '—'
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: String(currency || 'RUB').toUpperCase(),
        maximumFractionDigits: 2,
    }).format(Number(value))
}

function formatDate(value) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short' }).format(date)
}
</script>

<template>
    <div class="good-transfer">
        <v-alert type="info" variant="tonal" density="compact" class="transfer-alert">
            <strong>Источник истины — Good.</strong> Никакой фоновой синхронизации: переносятся только выбранные здесь данные.
        </v-alert>

        <v-alert v-if="localError" type="error" variant="tonal" density="compact" closable class="transfer-alert" @click:close="localError = ''">
            {{ localError }}
        </v-alert>

        <div v-if="loading" class="transfer-loading"><v-progress-circular indeterminate size="28" /></div>

        <template v-else-if="!link">
            <section class="transfer-section">
                <div class="transfer-title"><strong>Привязать Good</strong><small>одно объявление → один Good</small></div>
                <v-autocomplete
                    v-model="selectedGoodId"
                    v-model:search="goodsSearch"
                    :items="goodOptions"
                    item-title="name"
                    item-value="id"
                    :loading="goodsLoading"
                    label="Найти товар в базе"
                    placeholder="Название или описание"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                    no-filter
                >
                    <template #item="{ props: optionProps, item: option }">
                        <v-list-item v-bind="optionProps" :subtitle="`Good #${option.raw.id} · ${option.raw.prices?.length || 0} цен · ${option.raw.media?.length || 0} фото`" />
                    </template>
                </v-autocomplete>
                <v-btn block size="small" color="deep-purple-lighten-1" :disabled="!positiveInteger(selectedGoodId)" :loading="linking" @click="linkGood">
                    Привязать к объявлению
                </v-btn>
                <p class="transfer-note">Связь хранится в БД приложения. Само объявление на этом шаге не изменяется.</p>
            </section>
        </template>

        <template v-else>
            <section class="good-link-card">
                <div>
                    <small>Good #{{ linkedGood.id }} · {{ linkedGood.is_published ? 'опубликован' : 'черновик' }}</small>
                    <strong>{{ linkedGood.name }}</strong>
                    <span>{{ linkedGood.prices?.length || 0 }} цен · {{ linkedGood.media?.length || 0 }} опубликованных фото</span>
                </div>
                <div class="good-link-actions">
                    <v-btn :href="linkedGood.admin_url" target="_blank" rel="noopener noreferrer" icon="mdi-database-eye-outline" size="x-small" variant="tonal" title="Открыть Good" />
                    <v-btn icon="mdi-swap-horizontal" size="x-small" variant="text" title="Сменить Good" @click="relinkOpen = !relinkOpen; loadGoods('')" />
                    <v-btn icon="mdi-link-off" size="x-small" color="error" variant="text" title="Удалить связь" :loading="linking" @click="unlinkGood" />
                </div>
            </section>

            <section v-if="relinkOpen" class="transfer-section transfer-section--inline">
                <v-autocomplete
                    v-model="selectedGoodId"
                    v-model:search="goodsSearch"
                    :items="goodOptions"
                    item-title="name"
                    item-value="id"
                    :loading="goodsLoading"
                    label="Новый Good"
                    variant="outlined"
                    density="compact"
                    hide-details
                    no-filter
                />
                <v-btn size="small" variant="tonal" :disabled="!positiveInteger(selectedGoodId)" :loading="linking" @click="linkGood">Сменить</v-btn>
            </section>

            <section class="transfer-section">
                <div class="transfer-title"><strong>Что переносим</strong><small>выборочно</small></div>
                <div class="field-picker">
                    <button
                        v-for="field in FIELD_OPTIONS"
                        :key="field.value"
                        type="button"
                        :class="{ selected: selectedFields.includes(field.value) }"
                        @click="toggleField(field.value)"
                    >
                        <v-icon :icon="field.icon" size="14" />
                        <span>{{ field.label }}</span>
                        <small>{{ field.mode === 'api' ? 'API' : 'вручную' }}</small>
                    </button>
                </div>

                <v-select
                    v-if="selectedFields.includes('price')"
                    v-model="priceValueId"
                    :items="priceOptions"
                    label="Тип цены из Good"
                    variant="outlined"
                    density="compact"
                    hide-details
                    class="mt-1"
                />

                <v-switch
                    v-if="selectedFields.includes('description')"
                    v-model="includeFacts"
                    label="Добавить фасовку, страну, наличие и ссылку"
                    color="deep-purple-lighten-1"
                    density="compact"
                    hide-details
                    inset
                />

                <div v-if="selectedFields.includes('images')" class="media-picker">
                    <button
                        v-for="media in linkedGood.media || []"
                        :key="media.id"
                        type="button"
                        :class="{ selected: mediaIds.includes(Number(media.id)) }"
                        :title="media.title"
                        @click="toggleMedia(media.id)"
                    >
                        <img :src="media.url || media.full_url" :alt="media.title || linkedGood.name">
                        <v-icon :icon="mediaIds.includes(Number(media.id)) ? 'mdi-check-circle' : 'mdi-circle-outline'" size="15" />
                    </button>
                    <span v-if="!linkedGood.media?.length" class="transfer-empty">В Good нет опубликованных фотографий.</span>
                </div>

                <v-btn block size="small" color="deep-purple-lighten-1" variant="tonal" prepend-icon="mdi-eye-check-outline" :disabled="!canPrepare" :loading="preparing" @click="prepareTransfer">
                    Подготовить и сравнить
                </v-btn>
            </section>

            <template v-if="preview">
                <v-alert v-for="warning in preview.warnings" :key="warning" type="warning" variant="tonal" density="compact" class="transfer-alert">
                    {{ warning }}
                </v-alert>

                <section v-if="preview.title" class="preview-card">
                    <header><strong>Название</strong><v-chip size="x-small" variant="tonal">вручную</v-chip></header>
                    <div class="compare-grid">
                        <small>Avito сейчас</small><span>{{ preview.title.avito_value || 'не получено' }}</span>
                        <small>Good</small><strong>{{ preview.title.good_value }}</strong>
                    </div>
                    <v-btn size="x-small" variant="tonal" prepend-icon="mdi-content-copy" @click="copyValue(preview.title.good_value, 'Название')">Копировать Good</v-btn>
                </section>

                <section v-if="preview.description" class="preview-card">
                    <header><strong>Описание</strong><v-chip size="x-small" variant="tonal">вручную</v-chip></header>
                    <pre>{{ preview.description.good_value }}</pre>
                    <v-btn size="x-small" variant="tonal" prepend-icon="mdi-content-copy" @click="copyValue(preview.description.good_value, 'Описание')">Копировать описание</v-btn>
                </section>

                <section v-if="preview.price" class="preview-card preview-card--price">
                    <header><strong>Цена</strong><v-chip size="x-small" color="success" variant="tonal">API</v-chip></header>
                    <div class="compare-grid">
                        <small>Avito сейчас</small><span>{{ formatMoney(preview.price.avito_current_value, 'RUB') }}</span>
                        <small>Good · {{ preview.price.price_type }}</small><strong>{{ formatMoney(preview.price.good_value, preview.price.currency_code) }}</strong>
                        <small>Будет передано</small><strong>{{ formatMoney(preview.price.avito_value, 'RUB') }}</strong>
                    </div>
                    <v-alert v-if="!mutationsEnabled" type="info" variant="tonal" density="compact" class="transfer-alert">Изменения Avito отключены серверным флагом.</v-alert>
                    <v-checkbox v-model="confirmed" density="compact" color="warning" hide-details label="Подтверждаю изменение цены в Avito" />
                    <v-btn block size="small" color="success" :disabled="!canApplyPrice" :loading="applying" prepend-icon="mdi-cloud-upload-outline" @click="applyGoodPrice">
                        Применить цену Good в Avito
                    </v-btn>
                </section>

                <section v-if="preview.images?.length" class="preview-card">
                    <header><strong>Фотографии</strong><v-chip size="x-small" variant="tonal">вручную</v-chip></header>
                    <div class="prepared-media">
                        <article v-for="media in preview.images" :key="media.id">
                            <img :src="media.url || media.full_url" :alt="media.title || linkedGood.name">
                            <span>{{ media.title || `Фото #${media.id}` }}</span>
                            <div>
                                <v-btn :href="media.download_url" icon="mdi-download" size="x-small" variant="tonal" title="Скачать оригинал" />
                                <v-btn icon="mdi-link-variant" size="x-small" variant="text" title="Копировать URL" @click="copyValue(media.full_url, 'Ссылка на фото')" />
                            </div>
                        </article>
                    </div>
                </section>
            </template>

            <section v-if="history.length" class="transfer-history">
                <div class="transfer-title"><strong>Последние операции</strong><small>{{ history.length }}</small></div>
                <div v-for="entry in history" :key="entry.id">
                    <v-icon :icon="entry.status === 'failed' ? 'mdi-alert-circle-outline' : 'mdi-check-circle-outline'" :color="entry.status === 'failed' ? 'error' : 'success'" size="13" />
                    <span>{{ transferStatusLabel(entry.status) }}</span>
                    <small>{{ entry.selected_fields.map(fieldLabel).join(', ') }} · {{ formatDate(entry.created_at) }}</small>
                </div>
            </section>
        </template>
    </div>
</template>

<style scoped>
.good-transfer { display: grid; gap: 5px; color: #dfe3f6; }
.transfer-alert { margin: 0; font-size: 9px; }.transfer-alert strong { font-weight: 800; }
.transfer-loading { display: grid; min-height: 210px; place-items: center; }
.transfer-section, .preview-card, .transfer-history { display: grid; gap: 6px; padding: 7px; border: 1px solid #30344b; border-radius: 7px; background: #15182a; }
.transfer-section--inline { grid-template-columns: minmax(0, 1fr) auto; align-items: center; }
.transfer-title { display: flex; align-items: center; justify-content: space-between; gap: 6px; font-size: 10px; }.transfer-title small { color: #858dac; font-size: 8px; }
.transfer-note, .transfer-empty { margin: 0; color: #858dac; font-size: 8px; line-height: 1.4; }
.good-link-card { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 6px; padding: 7px 8px; border: 1px solid rgba(138, 115, 245, .36); border-radius: 7px; background: rgba(77, 55, 156, .18); }.good-link-card small, .good-link-card strong, .good-link-card span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.good-link-card small, .good-link-card span { color: #9299ba; font-size: 8px; }.good-link-card strong { margin: 2px 0; font-size: 11px; }.good-link-actions { display: flex; gap: 2px; }
.field-picker { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 3px; }.field-picker button { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: 1px 4px; min-height: 38px; padding: 4px 5px; color: #aeb5d0; text-align: left; border: 1px solid #30354d; border-radius: 5px; background: #1a1d31; }.field-picker button.selected { color: #eeeaff; border-color: #806ae5; background: rgba(104, 78, 201, .24); }.field-picker span { overflow: hidden; font-size: 8px; text-overflow: ellipsis; }.field-picker small { grid-column: 2; color: #7f87a7; font-size: 7px; }
.media-picker { display: grid; grid-template-columns: repeat(6, 1fr); gap: 3px; }.media-picker button { position: relative; aspect-ratio: 1; overflow: hidden; padding: 0; border: 1px solid #343950; border-radius: 4px; background: #101324; }.media-picker button.selected { border-color: #9b87ff; box-shadow: 0 0 0 1px #7359dc; }.media-picker img { width: 100%; height: 100%; object-fit: cover; }.media-picker .v-icon { position: absolute; top: 2px; right: 2px; color: #b7a7ff; filter: drop-shadow(0 1px 2px #000); }.media-picker .transfer-empty { grid-column: 1 / -1; }
.preview-card header { display: flex; align-items: center; justify-content: space-between; gap: 5px; font-size: 10px; }.compare-grid { display: grid; grid-template-columns: 86px minmax(0, 1fr); gap: 3px 5px; padding: 5px; font-size: 9px; border-radius: 5px; background: #1c2035; }.compare-grid small { color: #858dac; }.compare-grid span, .compare-grid strong { overflow-wrap: anywhere; }.preview-card pre { max-height: 190px; overflow: auto; margin: 0; padding: 6px; color: #ccd1e8; font: 9px/1.45 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; border-radius: 5px; background: #101325; }.preview-card > .v-btn { justify-self: start; }
.prepared-media { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }.prepared-media article { display: grid; grid-template-columns: 42px minmax(0, 1fr); grid-template-rows: 22px 20px; gap: 2px 5px; padding: 3px; border: 1px solid #2e334a; border-radius: 5px; background: #1a1e32; }.prepared-media img { grid-row: 1 / 3; width: 42px; height: 42px; object-fit: cover; border-radius: 3px; }.prepared-media span { overflow: hidden; align-self: end; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }.prepared-media article > div { display: flex; align-items: center; gap: 2px; }
.transfer-history > div:not(.transfer-title) { display: grid; grid-template-columns: 15px minmax(0, 1fr); gap: 1px 4px; padding: 3px 1px; border-top: 1px solid #292d43; font-size: 8px; }.transfer-history > div:not(.transfer-title) small { grid-column: 2; overflow: hidden; color: #858dac; text-overflow: ellipsis; white-space: nowrap; }
.mt-1 { margin-top: 2px; }
:deep(.v-field) { font-size: 10px; }:deep(.v-label), :deep(.v-checkbox .v-label), :deep(.v-switch .v-label) { font-size: 9px; }
@media (max-width: 420px) { .field-picker { grid-template-columns: 1fr 1fr; }.media-picker { grid-template-columns: repeat(4, 1fr); }.prepared-media { grid-template-columns: 1fr; } }
</style>
