<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useHead } from '@unhead/vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

import { useUnitPage } from '@/Composables/useUnitPage'

import UnitOverviewCard from '@/Components/Unit/UnitOverviewCard.vue'
import UnitSendingsCard from '@/Components/Unit/UnitSendingsCard.vue'
import UnitSalesCard from '@/Components/Unit/UnitSalesCard.vue'
import UnitCallsCard from '@/Components/Unit/UnitCallsCard.vue'
import UnitManufacturesCard from '@/Components/Unit/UnitManufacturesCard.vue'
import UnitTradeTabsCard from '@/Components/Unit/UnitTradeTabsCard.vue'
import UnitBusinessContextsPanel from '@/Components/Unit/AiSales/UnitBusinessContextsPanel.vue'
import AiControlPlanePanel from '@/Components/Unit/AiSales/AiControlPlanePanel.vue'
import UnitProspectingDossierPanel from '@/Components/Unit/AiSales/UnitProspectingDossierPanel.vue'
import UnitOutreachPanel from '@/Components/Unit/AiSales/UnitOutreachPanel.vue'
import UnitEmailContactsCard from '@/Components/Unit/Mail/UnitEmailContactsCard.vue'

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
    loadFiles,
    loadDictionaries,
    searchGoods,
} = useUnitPage(props.unit, props.dictionaries, props.files)

const pageTitle = computed(() => `Unit: ${unit.value?.name ?? ''}`)
const activeSection = ref('overview')
const sendingsCard = ref(null)

const sectionTabs = computed(() => [
    { value: 'overview', title: 'Обзор' },
    { value: 'trade', title: 'Торговля' },
    {
        value: 'communications',
        title: 'Коммуникации',
        badge: unit.value?.emails?.length || null,
    },
    { value: 'ai-sales', title: 'AI Sales' },
])

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
    sendingsCard.value?.openNewMessage()
}

onMounted(async () => {
    const params = new URLSearchParams(window.location.search)
    const requestedSection = params.get('section')

    if (sectionTabs.value.some((tab) => tab.value === requestedSection)) {
        activeSection.value = requestedSection
    } else if (params.get('ai_sales') === '1' || window.location.hash === '#prospecting-dossier') {
        activeSection.value = 'ai-sales'
    }

    if (!props.files?.length) {
        await loadFiles()
    }

    if (!hasAllDictionaries(props.dictionaries)) {
        await loadDictionaries()
    }
})
</script>

<template>
    <v-container fluid class="unit-page pa-3">
        <header class="unit-page__hero">
            <div>
                <div class="unit-page__eyebrow">Unit #{{ unit.id }}</div>
                <h1>{{ unit.name }}</h1>
            </div>

            <div class="unit-page__hero-meta">
                <span v-if="unit.is_customer">Покупатель</span>
                <span v-if="unit.is_supplier">Поставщик</span>
                <span>{{ unit.entities?.length || 0 }} Entity</span>
                <span>{{ unit.emails?.length || 0 }} email</span>
            </div>
        </header>

        <v-card class="unit-page__navigation" rounded="xl" elevation="1" border>
            <v-tabs
                v-model="activeSection"
                color="teal-darken-3"
                density="comfortable"
                show-arrows
            >
                <v-tab
                    v-for="tab in sectionTabs"
                    :key="tab.value"
                    :value="tab.value"
                >
                    {{ tab.title }}
                    <span v-if="tab.badge" class="unit-page__tab-badge">{{ tab.badge }}</span>
                </v-tab>
            </v-tabs>
        </v-card>

        <v-window v-model="activeSection" class="unit-page__content" :touch="false">
            <v-window-item value="overview">
                <UnitOverviewCard
                    :unit="unit"
                    :files="files"
                    :dict="dict"
                    :loading="loading"
                    @refresh="refreshAll"
                />
            </v-window-item>

            <v-window-item value="trade">
                <div class="unit-page__stack">
                    <UnitTradeTabsCard
                        :unit="unit"
                        :dict="dict"
                        :can-view-orders="permissions.orders?.view"
                        :can-create-orders="permissions.orders?.create"
                        :goods-loading="loading.goods"
                        :search-goods="searchGoods"
                        @refresh="refreshUnit"
                    />

                    <div class="unit-page__two-columns">
                        <UnitManufacturesCard
                            :unit="unit"
                            :dict="dict"
                            @refresh="refreshUnit"
                        />
                        <UnitSalesCard :entities="unit.entities || []" />
                    </div>
                </div>
            </v-window-item>

            <v-window-item value="communications">
                <div class="unit-page__stack">
                    <UnitEmailContactsCard
                        :unit="unit"
                        :emails="dict.emails || []"
                        :can-manage="permissions.unit?.manage_emails"
                        :can-send="permissions.unit?.send_mail"
                        @compose="openMailComposer"
                        @refresh="refreshAll"
                    />

                    <div class="unit-page__communications-grid">
                        <UnitSendingsCard ref="sendingsCard" :unit="unit" />
                        <UnitCallsCard :unit="unit" />
                    </div>
                </div>
            </v-window-item>

            <v-window-item value="ai-sales">
                <div class="unit-page__stack">
                <UnitBusinessContextsPanel
                    :unit-id="Number(unit.id)"
                    :initial-capabilities="aiSales.capabilities || {}"
                    @unit-updated="refreshUnit"
                />

                <AiControlPlanePanel
                    :unit-id="Number(unit.id)"
                    :initial-capabilities="aiSales.capabilities || {}"
                />
                <div id="prospecting-dossier">
                    <UnitProspectingDossierPanel :unit-id="Number(unit.id)" />
                </div>

                <UnitOutreachPanel
                    v-if="aiSales.outreach_enabled"
                    :unit-id="Number(unit.id)"
                />
                </div>
            </v-window-item>
        </v-window>
    </v-container>
</template>

<style scoped>
.unit-page {
    --unit-gap: 12px;
    min-height: 100%;
    background:
        radial-gradient(circle at 10% 0%, rgba(0, 82, 77, 0.08), transparent 28%),
        linear-gradient(180deg, #f8fbfa, #fbf7f4);
}

.unit-page__hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 8px;
    padding: 4px 6px;
}

.unit-page__eyebrow {
    color: #71817f;
    font-size: 0.66rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.unit-page__hero h1 {
    margin: 0;
    color: #173c39;
    font-size: clamp(1.25rem, 2.4vw, 1.9rem);
    line-height: 1.08;
}

.unit-page__hero-meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 5px;
}

.unit-page__hero-meta span,
.unit-page__tab-badge {
    padding: 3px 8px;
    border-radius: 999px;
    background: rgba(0, 82, 77, 0.09);
    color: #315b58;
    font-size: 0.68rem;
    font-weight: 800;
}

.unit-page__navigation {
    position: sticky;
    z-index: 4;
    top: 4px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(10px);
}

.unit-page__navigation :deep(.v-tab) {
    min-width: 130px;
    font-weight: 850;
    letter-spacing: 0.01em;
    text-transform: none;
}

.unit-page__tab-badge {
    margin-left: 7px;
    padding: 1px 6px;
    color: inherit;
}

.unit-page__content {
    margin-top: var(--unit-gap);
}

.unit-page__stack {
    display: grid;
    gap: var(--unit-gap);
}

.unit-page__two-columns,
.unit-page__communications-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--unit-gap);
    align-items: start;
}

.unit-page__communications-grid {
    grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);
}

@media (max-width: 980px) {
    .unit-page__hero {
        align-items: flex-start;
        flex-direction: column;
    }

    .unit-page__hero-meta {
        justify-content: flex-start;
    }

    .unit-page__two-columns,
    .unit-page__communications-grid {
        grid-template-columns: 1fr;
    }
}
</style>
