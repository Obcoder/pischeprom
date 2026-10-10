<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { ref, computed, watch, onMounted, onScopeDispose } from 'vue'
import axios from 'axios'
import { useHead } from '@unhead/vue'
import { route } from 'ziggy-js'
import { usePage } from '@inertiajs/vue3'
import CatalogGoodRecordDialog from '@/Components/Catalog/CatalogGoodRecordDialog.vue'
import CatalogGoodOperations from '@/Components/Catalog/CatalogGoodOperations.vue'
import CatalogGoodWarehouse from '@/Components/Catalog/CatalogGoodWarehouse.vue'

defineOptions({ layout: VerwalterLayout })
const props = defineProps({ good: { type: Object, required: true } })
const page = usePage()
const currentUrl = computed(() => String(page.props.ziggy?.location || page.props.ziggy?.url || 'https://пищепром-сервер.рф'))
const tabs = [
    { key: 'warehouse', label: 'Склад' }, { key: 'market', label: 'Маркет' }, { key: 'prices', label: 'Цены' },
    { key: 'price-types', label: 'Типы цен' }, { key: 'collections', label: 'Подборки' },
    { key: 'media', label: 'Медиа' }, { key: 'sales', label: 'Продажи' },
]
const tabAliases = { quotations: 'market', recommendations: 'sales', purchases: 'warehouse' }
function requestedTab() {
    try { return new URL(page.url || currentUrl.value, currentUrl.value).searchParams.get('tab') }
    catch { return null }
}
const requested = tabAliases[requestedTab()] || requestedTab()
const activeTab = ref(tabs.some(tab => tab.key === requested) ? requested : 'market')
const recordInitialTab = ref(requested === 'seo' ? 'seo' : 'overview')
const recordOpen = ref(requested === 'seo')
const goodData = ref(props.good)
const pageError = ref(null)
const operations = ref(null)
const warehouse = ref(null)
const operationsState = ref({ dirty: false, busy: false })
const warehouseState = ref({ dirty: false, busy: false })
const pendingNavigation = ref(null)
const warehouseOptions = ref({ measures: [] })
const warehouseLoading = ref(false)
const warehouseVisited = ref(false)
const warehouseLoadedId = ref(null)
const warehouseActive = computed(() => activeTab.value === 'warehouse' && !recordOpen.value)
const warehouseReady = computed(() => String(warehouseLoadedId.value) === String(props.good.id))
const warehouseGood = computed(() => ({
    ...goodData.value,
    measure_id: goodData.value.measure_id ?? goodData.value.measurement?.measure_id ?? null,
}))
const hasUnsavedChanges = computed(() => operationsState.value.dirty || warehouseState.value.dirty)
const busy = computed(() => operationsState.value.busy || warehouseState.value.busy)
const currentVatRate = computed(() => goodData.value?.vat_rate || goodData.value?.vatRate || null)
const currentFields = computed(() => goodData.value?.fields || [])
function navigate(destination) {
    if (destination.recordTab) { recordInitialTab.value = destination.recordTab; recordOpen.value = true }
    else activeTab.value = destination.tab
}
function requestNavigation(destination) {
    if (busy.value) return
    if (hasUnsavedChanges.value) pendingNavigation.value = destination
    else navigate(destination)
}
function openRecord(tab = 'overview') { requestNavigation({ recordTab: typeof tab === 'string' ? tab : 'overview' }) }
function selectTab(value) {
    const tab = tabAliases[value] || value
    if (tab !== activeTab.value && tabs.some(item => item.key === tab)) requestNavigation({ tab })
}
function discardAndNavigate() {
    if (busy.value || !pendingNavigation.value) return
    const destination = pendingNavigation.value
    operations.value?.reset()
    warehouse.value?.reset()
    operationsState.value = { dirty: false, busy: false }
    warehouseState.value = { dirty: false, busy: false }
    pendingNavigation.value = null
    navigate(destination)
}
function loaded(good) { goodData.value = good; pageError.value = null }
async function refreshGood() {
    pageError.value = null
    try {
        const good = operations.value ? await operations.value.refresh({ throwOnError: true }) : (await axios.get(route('good.fetch', props.good.id))).data
        if (good) loaded(good)
    } catch (failure) {
        if (failure.response?.status === 404) window.location.href = route('Ameise.products')
        else pageError.value = failure.response?.data?.message || 'Не удалось обновить товар'
    }
}
async function refreshAfterRecord() {
    await refreshGood()
    if (!pageError.value && warehouseActive.value) await warehouse.value?.refresh()
}

let warehouseController = null
let warehouseVersion = 0
function cancelWarehouseContext() {
    warehouseVersion++
    warehouseController?.abort()
    warehouseController = null
    warehouseLoading.value = false
}
async function loadWarehouseContext() {
    cancelWarehouseContext()
    if (!warehouseActive.value) return
    const version = warehouseVersion
    const id = props.good.id
    warehouseController = new AbortController()
    const options = { signal: warehouseController.signal }
    warehouseLoading.value = true
    pageError.value = null
    try {
        const [good, measures] = await Promise.all([
            axios.get(route('good.fetch', id), options),
            axios.get(route('measures.index'), options),
        ])
        if (version !== warehouseVersion || String(id) !== String(props.good.id)) return
        loaded(good.data)
        warehouseOptions.value = { measures: Array.isArray(measures.data) ? measures.data : measures.data?.data || [] }
        warehouseLoadedId.value = id
    } catch (failure) {
        if (version === warehouseVersion) pageError.value = failure.response?.data?.message || 'Не удалось загрузить данные склада товара.'
    } finally {
        if (version === warehouseVersion) { warehouseLoading.value = false; warehouseController = null }
    }
}
watch([warehouseActive, () => props.good.id], ([active, id], previous = []) => {
    if (String(id) !== String(previous[1])) {
        warehouseLoadedId.value = null
        warehouseOptions.value = { measures: [] }
        goodData.value = props.good
    }
    if (active) {
        warehouseVisited.value = true
        if (!warehouseReady.value) loadWarehouseContext()
    } else cancelWarehouseContext()
}, { immediate: true })
function beforeUnload(event) {
    if (!hasUnsavedChanges.value && !busy.value) return
    event.preventDefault()
    event.returnValue = ''
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onScopeDispose(() => {
    cancelWarehouseContext()
    if (typeof window !== 'undefined') window.removeEventListener('beforeunload', beforeUnload)
})
// --------------------------------------------------
// SEO HEAD FOR ADMIN PAGE
// --------------------------------------------------
const seoTitle = computed(() => {
    return goodData.value?.name
        ? `${goodData.value.name} - Ameise`
        : "Good - Ameise";
});

const seoDescription = computed(() => {
    const description = String(goodData.value?.description || "Страница товара в админке");

    return description.slice(0, 160);
});

useHead({
    title: seoTitle,
    meta: [
        {
            name: "description",
            content: seoDescription,
        },
        {
            name: "keywords",
            content: computed(() =>
                goodData.value?.name
                    ? `${goodData.value.name}, товар, pischeprom`
                    : "товар"
            ),
        },
        {
            property: "og:title",
            content: computed(() => goodData.value?.name || "Good"),
        },
        {
            property: "og:description",
            content: seoDescription,
        },
        {
            property: "og:image",
            content: computed(() => goodData.value?.ava_image || "/default-image.jpg"),
        },
        {
            property: "og:url",
            content: currentUrl,
        }
    ],
});

</script>

<template>
    <v-container fluid class="good-page">
        <CatalogGoodRecordDialog v-model="recordOpen" :good-id="good.id" :initial-tab="recordInitialTab" @saved="refreshAfterRecord" @deleted="refreshAfterRecord" />
        <div class="good-page__actions">
            <v-btn prepend-icon="mdi-card-text-outline" variant="tonal" density="compact" :disabled="busy" @click="openRecord()">Карточка записи</v-btn>
            <v-chip size="small" variant="tonal" color="deep-purple">ID: {{ goodData.id }}</v-chip>
            <v-chip size="small" variant="tonal" :color="goodData.is_published ? 'green' : 'grey'">{{ goodData.is_published ? 'published' : 'hidden' }}</v-chip>
            <v-chip v-if="currentVatRate" size="small" variant="tonal" color="blue-grey">НДС: {{ currentVatRate.title }} / {{ currentVatRate.rate }}%</v-chip>
        </div>
        <v-alert v-if="pageError" type="error" variant="tonal" density="compact" class="mb-3">{{ pageError }}</v-alert>
        <v-card class="mb-4">
            <v-card-text>
                <div class="text-caption text-medium-emphasis mb-1">Good / товар</div>
                <h1 class="text-h5 font-weight-bold mb-1">{{ goodData.name }}</h1>
                <div class="text-caption text-medium-emphasis">slug: {{ goodData.slug || '—' }}</div>
                <div class="good-page__summary"><span>Единица учёта: <strong>{{ goodData.measurement?.unit_label || 'не задана' }}</strong></span><span>Масса упаковки: <strong>{{ goodData.denominator || '—' }} кг</strong></span><span>Предложения: <strong>{{ (goodData.quotations || []).length }}</strong></span></div>
            </v-card-text>
        </v-card>
        <v-card class="mb-4"><v-tabs :model-value="activeTab" density="compact" color="deep-purple-darken-1" show-arrows @update:model-value="selectTab"><v-tab v-for="tab in tabs" :key="tab.key" :value="tab.key" :disabled="busy">{{ tab.label }}</v-tab></v-tabs></v-card>
        <section v-if="warehouseVisited" v-show="activeTab === 'warehouse'" aria-label="Склад товара">
            <div v-if="warehouseLoading && !warehouseReady" class="text-center pa-8"><v-progress-circular indeterminate size="28" /></div>
            <v-btn v-if="pageError && !warehouseReady" variant="tonal" @click="loadWarehouseContext">Повторить загрузку склада</v-btn>
            <CatalogGoodWarehouse
                v-if="warehouseReady"
                ref="warehouse"
                :good-id="good.id"
                :model-value="warehouseGood"
                :overview="goodData"
                :options="warehouseOptions"
                :active="warehouseActive"
                :disabled="recordOpen"
                @changed="refreshGood"
                @state="warehouseState = $event"
            >
                <template #measurement>
                    <v-card variant="outlined" class="mb-2">
                        <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-3">
                            <span>Единица учёта и упаковка</span>
                            <v-btn prepend-icon="mdi-pencil-outline" size="small" variant="tonal" :disabled="busy" @click="openRecord('warehouse')">Изменить единицы и упаковку</v-btn>
                        </v-card-title>
                        <v-card-text>
                            <div class="good-page__summary mt-0"><span>Единица количества и цены: <strong>{{ goodData.measurement?.unit_label || warehouseOptions.measures.find(item => String(item.id) === String(warehouseGood.measure_id))?.name || 'не задана' }}</strong></span><span>Масса упаковки: <strong>{{ goodData.denominator || '—' }} кг</strong></span></div>
                            <p class="text-caption text-medium-emphasis mt-3 mb-0">Единицы и упаковка изменяются в карточке товара. Движения склада сохраняются при проведении.</p>
                        </v-card-text>
                    </v-card>
                </template>
            </CatalogGoodWarehouse>
        </section>
        <CatalogGoodOperations ref="operations" v-show="activeTab !== 'warehouse'" :good-id="good.id" :active-tab="activeTab" :active="!recordOpen && activeTab !== 'warehouse'" @loaded="loaded" @changed="loaded" @request-basics="openRecord()" @state="operationsState = $event" />
        <v-dialog :model-value="Boolean(pendingNavigation)" max-width="440" persistent>
            <v-card>
                <v-card-title>Несохранённые изменения</v-card-title>
                <v-card-text>Перейти и отменить изменения в текущем разделе?</v-card-text>
                <v-card-actions><v-spacer /><v-btn :disabled="busy" @click="pendingNavigation = null">Остаться</v-btn><v-btn color="error" :disabled="busy" @click="discardAndNavigate">Отменить изменения</v-btn></v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<style scoped>
.good-page { min-width: 0; }
.good-page__actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.good-page__summary { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-top: 14px; font-size: 12px; color: #86738f; }
.good-page h1 { overflow-wrap: anywhere; }
</style>
