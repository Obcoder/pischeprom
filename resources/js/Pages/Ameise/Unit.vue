<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useHead } from '@unhead/vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

import { useUnitPage } from '@/Composables/useUnitPage'

import UnitCommunicationsPanel from '@/Components/Unit/UnitCommunicationsPanel.vue'
import UnitStatisticsPanel from '@/Components/Unit/UnitStatisticsPanel.vue'
import UnitOverviewCard from '@/Components/Unit/UnitOverviewCard.vue'
import UnitWebsiteResearchCard from '@/Components/Unit/UnitWebsiteResearchCard.vue'
import UnitTradeTabsCard from '@/Components/Unit/UnitTradeTabsCard.vue'
import UnitBusinessContextsPanel from '@/Components/Unit/AiSales/UnitBusinessContextsPanel.vue'
import AiControlPlanePanel from '@/Components/Unit/AiSales/AiControlPlanePanel.vue'
import UnitProspectingDossierPanel from '@/Components/Unit/AiSales/UnitProspectingDossierPanel.vue'
import UnitOutreachPanel from '@/Components/Unit/AiSales/UnitOutreachPanel.vue'

defineOptions({
    layout: VerwalterLayout,
})

const props = defineProps({
    unit: Object,
    dictionaries: {
        type: Object,
        default: () => ({})
    },
    files: {
        type: Array,
        default: () => []
    },
    permissions: {
        type: Object,
        default: () => ({
            orders: {
                view: false,
                create: false,
            },
        }),
    },
    aiSales: {
        type: Object,
        default: () => ({ capabilities: { view: false } }),
    },
})

const {
    unit,
    files,
    dict,
    loading,
    refreshUnit,
    loadDictionaries,
    searchGoods,
} = useUnitPage(props.unit, props.dictionaries, props.files)

const pageTitle = computed(() => `Unit: ${unit.value?.name ?? ''}`)
const activeSection = ref('communications')
const communicationsPanel = ref(null)
const detailsPanel = ref(null)
const detailSections = ['entities', 'classification', 'logistics', 'files']
const aiSection = ref('research')

const sectionTabs = [
    { value: 'communications', title: 'Коммуникации', icon: 'mdi-message-outline' },
    { value: 'entities', title: 'Entities', icon: 'mdi-account-group-outline' },
    { value: 'relations', title: 'Продукты и товары', icon: 'mdi-package-variant-closed' },
    { value: 'classification', title: 'Классификация и сегментация', icon: 'mdi-tag-outline' },
    { value: 'logistics', title: 'Логистика', icon: 'mdi-truck-outline' },
    { value: 'statistics', title: 'Статистика', icon: 'mdi-chart-box-outline' },
    { value: 'files', title: 'Файлы', icon: 'mdi-paperclip' },
    { value: 'ai-sales', title: 'AI Sales', icon: 'mdi-auto-fix' },
]
const workspaceDefaults = {
    VCard: { rounded: 0, elevation: 0 },
    VBtn: { rounded: 0, elevation: 0, color: '#352345' },
    VChip: { rounded: 0, color: '#352345' },
    VTabs: { color: '#352345', density: 'compact' },
    VTextField: { variant: 'outlined', density: 'compact' },
    VSelect: { variant: 'outlined', density: 'compact' },
    VAutocomplete: { variant: 'outlined', density: 'compact' },
    VDialog: { contentClass: 'unit-workspace-dialog' },
}

useHead(() => ({
    title: pageTitle.value,
    meta: [
        {
            name: 'description',
            content: `Информация о блоке ${unit.value?.name ?? ''}`
        }
    ]
}))

const requiredDictionaryKeys = [
    'buildings',
    'buildingTypes',
    'cities',
    'currencies',
    'emails',
    'entities',
    'entityClassifications',
    'fields',
    'goods',
    'industries',
    'labels',
    'measures',
    'products',
    'telephones',
    'uris',
]

function hasAllDictionaries(source = {}) {
    return requiredDictionaryKeys.every((key) => Array.isArray(source?.[key]))
}

async function refreshAll() {
    await Promise.all([
        refreshUnit(),
        loadDictionaries(),
    ])
}

async function openMailComposer() {
    activeSection.value = 'communications'
    await nextTick()
    communicationsPanel.value?.openNewMessage()
}

function readLocation() {
    const params = new URLSearchParams(window.location.search)
    const legacy = { overview: 'entities', trade: 'relations' }
    const requested = legacy[params.get('section')] || params.get('section')
    const requestedAiSection = params.get('ai_section')
    const aiSections = ['research', 'contexts', 'dossier', 'control', ...(props.aiSales.outreach_enabled ? ['outreach'] : [])]
    aiSection.value = aiSections.includes(requestedAiSection) ? requestedAiSection : params.get('ai_sales') === '1' ? 'contexts' : 'research'
    if (window.location.hash === '#website-research') {
        activeSection.value = 'ai-sales'
        aiSection.value = 'research'
    } else if (window.location.hash === '#prospecting-dossier') {
        activeSection.value = 'ai-sales'
        aiSection.value = 'dossier'
    } else if (sectionTabs.some(tab => tab.value === requested)) {
        activeSection.value = requested
    } else if (params.get('ai_sales') === '1') {
        activeSection.value = 'ai-sales'
    } else {
        activeSection.value = 'communications'
    }
}

watch([activeSection, aiSection], ([section, ai]) => {
    const url = new URL(window.location.href)
    url.searchParams.set('section', section)
    url.searchParams.delete('ai_sales')
    if (section === 'ai-sales') url.searchParams.set('ai_section', ai)
    else url.searchParams.delete('ai_section')
    if (['#website-research', '#prospecting-dossier'].includes(url.hash)) url.hash = ''
    window.history.replaceState(window.history.state, '', url)
})

onMounted(async () => {
    readLocation()
    window.addEventListener('popstate', readLocation)
    window.addEventListener('hashchange', readLocation)
    if (!hasAllDictionaries(props.dictionaries)) await loadDictionaries()
})
onBeforeUnmount(() => {
    window.removeEventListener('popstate', readLocation)
    window.removeEventListener('hashchange', readLocation)
})
</script>

<template>
    <v-defaults-provider :defaults="workspaceDefaults">
        <main class="unit-page">
            <header class="unit-page__header">
                <div class="unit-page__identity">
                    <a href="/Ameise/units" class="unit-page__back" title="Все Units" aria-label="Все Units"><v-icon icon="mdi-arrow-left" size="19" /></a>
                    <div>
                        <div class="unit-page__eyebrow">UNIT / РАБОЧЕЕ ПРОСТРАНСТВО</div>
                        <h1>{{ unit.name }}</h1>
                    </div>
                    <div v-if="unit.is_customer || unit.is_supplier" class="unit-page__roles">
                        <span v-if="unit.is_customer">Покупатель</span>
                        <span v-if="unit.is_supplier">Поставщик</span>
                    </div>
                </div>
                <div class="unit-page__actions">
                    <button v-if="permissions.unit?.send_mail" type="button" class="unit-page__write" @click="openMailComposer"><v-icon icon="mdi-email-outline" size="16" /> Написать</button>
                    <button type="button" @click="detailsPanel?.openUnitDialog()"><v-icon icon="mdi-pencil-outline" size="16" /> Изменить Unit</button>
                    <button type="button" :disabled="loading.unit || loading.dict" title="Обновить данные" aria-label="Обновить данные Unit" @click="refreshAll"><v-icon icon="mdi-refresh" size="18" :class="{ 'unit-page__refreshing': loading.unit }" /></button>
                </div>
            </header>

            <nav class="unit-page__navigation" aria-label="Разделы Unit">
                <v-tabs v-model="activeSection" color="#352345" density="compact" show-arrows>
                    <v-tab v-for="tab in sectionTabs" :key="tab.value" :value="tab.value"><v-icon :icon="tab.icon" size="16" class="mr-2" />{{ tab.title }}</v-tab>
                </v-tabs>
            </nav>
            <div class="unit-page__content">
                <UnitOverviewCard
                    ref="detailsPanel"
                    v-show="detailSections.includes(activeSection)"
                    :section="activeSection"
                    :unit="unit"
                    :files="files"
                    :dict="dict"
                    :loading="loading"
                    @refresh="refreshAll"
                />
                <v-window v-model="activeSection" :touch="false">
                    <v-window-item value="communications">
                        <UnitCommunicationsPanel
                            ref="communicationsPanel"
                            :unit="unit"
                            :dict="dict"
                            :can-manage="permissions.unit?.manage_contacts ?? permissions.unit?.manage_emails"
                            :can-send="permissions.unit?.send_mail"
                            @refresh="refreshAll"
                        />
                    </v-window-item>
                    <v-window-item value="relations">
                        <UnitTradeTabsCard
                            :unit="unit"
                            :dict="dict"
                            :can-view-orders="permissions.orders?.view"
                            :can-create-orders="permissions.orders?.create"
                            :goods-loading="loading.goods"
                            :search-goods="searchGoods"
                            @refresh="refreshUnit"
                        />
                    </v-window-item>
                    <v-window-item value="statistics">
                        <UnitStatisticsPanel :unit="unit" :can-view-orders="Boolean(permissions.orders?.view)" />
                    </v-window-item>
                    <v-window-item value="ai-sales">
                        <div class="unit-page__ai">
                            <v-tabs v-model="aiSection" color="#352345" density="compact" show-arrows>
                                <v-tab value="research">Исследования сайтов</v-tab>
                                <v-tab value="contexts">Бизнес-контексты</v-tab>
                                <v-tab value="dossier">Досье</v-tab>
                                <v-tab value="control">Управление AI</v-tab>
                                <v-tab v-if="aiSales.outreach_enabled" value="outreach">Outreach</v-tab>
                            </v-tabs>
                            <v-window v-model="aiSection" :touch="false">
                                <v-window-item value="research"><UnitWebsiteResearchCard :unit-id="Number(unit.id)" /></v-window-item>
                                <v-window-item value="contexts"><UnitBusinessContextsPanel :unit-id="Number(unit.id)" :initial-capabilities="aiSales.capabilities || {}" @unit-updated="refreshUnit" /></v-window-item>
                                <v-window-item value="dossier"><div id="prospecting-dossier"><UnitProspectingDossierPanel :unit-id="Number(unit.id)" /></div></v-window-item>
                                <v-window-item value="control"><AiControlPlanePanel :unit-id="Number(unit.id)" :initial-capabilities="aiSales.capabilities || {}" /></v-window-item>
                                <v-window-item v-if="aiSales.outreach_enabled" value="outreach"><UnitOutreachPanel :unit-id="Number(unit.id)" /></v-window-item>
                            </v-window>
                        </div>
                    </v-window-item>
                </v-window>
            </div>
        </main>
    </v-defaults-provider>
</template>

<style scoped>
.unit-page { --unit-purple: #352345; --unit-red: #651c2e; --unit-line: #d9d7dc; min-height: 100%; padding: 16px 20px 24px; color: #242127; background: #f5f5f6; font-size: 13px; }
.unit-page__header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-bottom: 16px; }
.unit-page__identity { display: flex; align-items: center; gap: 14px; min-width: 0; }
.unit-page__eyebrow { color: #85808a; font-size: 9px; font-weight: 650; letter-spacing: .13em; margin-bottom: 3px; }
.unit-page h1 { font-size: clamp(20px, 2vw, 28px); font-weight: 650; line-height: 1.15; letter-spacing: -.025em; overflow-wrap: anywhere; }
.unit-page__back { display: grid; place-items: center; color: #77727b; width: 30px; height: 34px; flex-shrink: 0; border: 1px solid var(--unit-line); }
.unit-page__back:hover { color: #352345; background: #fff; }
.unit-page__roles { display: flex; gap: 6px; flex-wrap: wrap; }
.unit-page__roles span { border-left: 2px solid #352345; padding-left: 7px; color: #66606c; font-size: 10px; white-space: nowrap; }
.unit-page__actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.unit-page__actions button { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 32px; padding: 6px 9px; border: 1px solid var(--unit-line); background: #fff; color: var(--unit-purple); font-size: 11px; font-weight: 600; }
.unit-page__actions button:hover { background: #eeecef; }
.unit-page__actions button.unit-page__write { background: #352345; color: #fff; border-color: #352345; }
.unit-page__actions button:disabled { opacity: .5; }
.unit-page__navigation { border-top: 1px solid var(--unit-line); border-bottom: 1px solid var(--unit-line); background: #fff; }
.unit-page :deep(.v-tab) { min-width: 0; padding: 0 13px; font-size: 12px; font-weight: 600; letter-spacing: 0; text-transform: none; }
.unit-page__content { margin-top: 14px; }
.unit-page__ai > .v-tabs { border: 1px solid var(--unit-line); border-bottom: 0; background: #fff; }
.unit-page :deep(.v-card), .unit-page :deep(.v-sheet), .unit-page :deep(.v-btn), .unit-page :deep(.v-chip), .unit-page :deep(.v-alert), .unit-page :deep(.v-field) { border-radius: 0 !important; box-shadow: none !important; background-image: none !important; }
.unit-page :deep(.base-section-card) { border-color: var(--unit-line); }
.unit-page :deep(.base-section-card__header) { background: #fff; color: #352345; border-bottom: 1px solid var(--unit-line); min-height: 39px; padding: 7px 12px; }
.unit-page :deep(.base-section-card__title) { font-size: 13px; font-weight: 650; }
.unit-page :deep(.base-section-card__body) { padding: 12px; }
.unit-page :deep(.base-section-card__body.unit-trade-tabs) { padding: 0; }
.unit-page :deep(.v-btn) { text-transform: none; letter-spacing: 0; }
.unit-page :deep(.ai-sales-panel) { background: #fff; }
.unit-page :deep(button:focus-visible), .unit-page :deep(a:focus-visible) { outline: 2px solid #352345; outline-offset: 2px; }
.unit-page__refreshing { animation: unit-spin 1s linear infinite; }
@keyframes unit-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .unit-page__refreshing { animation: none; } }
@media (max-width: 1100px) { .unit-page__roles { flex-direction: column; } }
@media (max-width: 760px) { .unit-page { padding: 12px; } .unit-page__header { flex-wrap: wrap; gap: 12px; } .unit-page__roles { display: none; } .unit-page__actions { margin-left: 44px; } .unit-page :deep(.v-tab) { padding: 0 10px; } }
</style>

<style>
.unit-workspace-dialog .v-card, .unit-workspace-dialog .v-field, .unit-workspace-dialog .v-btn { border-radius: 0 !important; box-shadow: none !important; }
.unit-workspace-dialog .v-card { border: 1px solid #d9d7dc; }
.unit-workspace-dialog .v-btn { letter-spacing: 0; text-transform: none; }
</style>
