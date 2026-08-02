<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import { computed, onMounted, provide, ref, watch } from 'vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import BuildingsPage from '@/Components/Geography/Buildings/BuildingsPage.vue'
import CitiesPage from '@/Components/Geography/Cities/CitiesPage.vue'
import CountriesPage from '@/Components/Geography/Countries/CountriesPage.vue'
import RegionsPage from '@/Components/Geography/Regions/RegionsPage.vue'
import OverviewTab from '@/Components/Logistics/OverviewTab.vue'
import TripsTab from '@/Components/Logistics/TripsTab.vue'
import VehiclesTab from '@/Components/Logistics/VehiclesTab.vue'
import MatrixTab from '@/Components/Logistics/MatrixTab.vue'
import DiagnosticsTab from '@/Components/Logistics/DiagnosticsTab.vue'
import MapTab from '@/Components/Logistics/MapTab.vue'
import { useLogisticsApi } from '@/Composables/logistics/useLogisticsApi.js'

defineOptions({ layout: VerwalterLayout })

const page = usePage()
const api = useLogisticsApi()
const LOGISTICS_TAB_KEY = 'ameise:logistics:tab'
const pageQuery = String(page.url || '').split('?')[1]?.split('#')[0] || ''
const requestedPageTab = new URLSearchParams(pageQuery).get('tab')
const activeTab = ref(requestedPageTab || 'overview')
const permissions = computed(() => page.props.auth?.permissions?.logistics || {})
const tabs = computed(() => [
    { value: 'overview', title: 'Обзор', icon: 'mdi-view-dashboard-outline', component: OverviewTab },
    { value: 'trips', title: 'Рейсы', icon: 'mdi-map-marker-path', component: TripsTab },
    { value: 'map', title: 'Карта', icon: 'mdi-map-outline', component: MapTab },
    { value: 'vehicles', title: 'Авто', icon: 'mdi-truck-outline', component: VehiclesTab },
    { value: 'matrix', title: 'Матрица', icon: 'mdi-grid', component: MatrixTab },
    ...(permissions.value.technical_view
        ? [{ value: 'diagnostics', title: 'Диагностика', icon: 'mdi-stethoscope', component: DiagnosticsTab }]
        : []),
    { value: 'cities', title: 'Cities', icon: 'mdi-city-variant-outline', component: CitiesPage, geographyStart: true },
    { value: 'buildings', title: 'Buildings', icon: 'mdi-office-building-outline', component: BuildingsPage },
    { value: 'regions', title: 'Regions', icon: 'mdi-map-marker-radius-outline', component: RegionsPage },
    { value: 'countries', title: 'Countries', icon: 'mdi-earth', component: CountriesPage },
])
const activeComponent = computed(() => tabs.value.find((item) => item.value === activeTab.value)?.component || OverviewTab)

function tabIsAvailable(value) {
    return tabs.value.some((item) => item.value === value)
}

onMounted(() => {
    const requestedTab = new URL(window.location.href).searchParams.get('tab')
    const storedTab = window.localStorage.getItem(LOGISTICS_TAB_KEY)
    const initialTab = requestedTab || storedTab

    if (initialTab && tabIsAvailable(initialTab)) {
        activeTab.value = initialTab
    } else if (!tabIsAvailable(activeTab.value)) {
        activeTab.value = 'overview'
    }
})

watch(tabs, () => {
    if (!tabIsAvailable(activeTab.value)) {
        activeTab.value = 'overview'
    }
})

watch(activeTab, (value) => {
    if (typeof window !== 'undefined' && tabIsAvailable(value)) {
        window.localStorage.setItem(LOGISTICS_TAB_KEY, value)

        const currentUrl = new URL(window.location.href)

        if (value === 'overview') {
            currentUrl.searchParams.delete('tab')
        } else {
            currentUrl.searchParams.set('tab', value)
        }

        window.history.replaceState(window.history.state, '', currentUrl)
    }
})

provide('logisticsApi', api)
provide('logisticsPermissions', permissions)
</script>

<template>
    <Head title="Логистика" />

    <main class="logistics-page">
        <div class="logistics-page__shell">
            <header class="logistics-page__header">
                <div>
                    <div class="logistics-page__eyebrow">Ameise · транспортный контур</div>
                    <h1>Логистика</h1>
                    <p>Рейсы, автопарк, расходы по чекам, география и собственные автомобильные расстояния.</p>
                </div>
                <v-chip color="green-darken-2" variant="tonal" prepend-icon="mdi-server-network">
                    OSM · Valhalla
                </v-chip>
            </header>

            <v-card class="logistics-tabs" variant="outlined">
                <v-tabs v-model="activeTab" color="green-darken-2" show-arrows aria-label="Разделы логистики">
                    <v-tab
                        v-for="tab in tabs"
                        :key="tab.value"
                        :value="tab.value"
                        :class="{ 'logistics-tab--geography-start': tab.geographyStart }"
                    >
                        <v-icon :icon="tab.icon" start />
                        {{ tab.title }}
                    </v-tab>
                </v-tabs>
            </v-card>

            <div class="logistics-tab-content">
                <keep-alive>
                    <component :is="activeComponent" />
                </keep-alive>
            </div>

            <footer class="logistics-attribution">
                Расчёты дорог: Valhalla. Данные карт: © OpenStreetMap contributors, ODbL. Живые пробки не учитываются.
            </footer>
        </div>

        <v-snackbar v-model="api.snackbar.open" :color="api.snackbar.color" location="bottom right" :timeout="5000">
            {{ api.snackbar.text }}
            <template #actions>
                <v-btn variant="text" @click="api.snackbar.open = false">Закрыть</v-btn>
            </template>
        </v-snackbar>
    </main>
</template>

<style>
.logistics-page {
    align-self: stretch;
    width: 100%;
    min-width: 0;
    min-height: calc(100vh - 58px);
    padding: 24px clamp(12px, 2vw, 32px) 32px;
    background: #f5f7f5;
    color: #26312b;
}

.logistics-page__shell {
    width: min(1680px, 100%);
    min-width: 0;
    margin-inline: auto;
}

.logistics-page__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    width: 100%;
    min-width: 0;
    gap: 20px;
    margin-bottom: 18px;
}

.logistics-page__header h1 {
    margin: 2px 0 4px;
    font-size: clamp(1.65rem, 3vw, 2.4rem);
    line-height: 1.1;
}

.logistics-page__header p {
    margin: 0;
    color: #607066;
}

.logistics-page__eyebrow {
    color: #3d7752;
    font-size: .73rem;
    font-weight: 750;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.logistics-tabs {
    width: 100%;
    min-width: 0;
    overflow: hidden;
    border-color: #dbe4dd !important;
    border-radius: 14px !important;
    background: #fff;
}

.logistics-tab--geography-start {
    margin-left: 8px;
    border-left: 1px solid #dbe4dd;
}

.logistics-tab-content {
    display: block;
    width: 100%;
    min-width: 0;
    margin-top: 18px;
}

.logistics-attribution {
    width: 100%;
    margin-top: 20px;
    color: #69766e;
    font-size: .78rem;
}

.logistics-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 14px;
}

.logistics-toolbar__filters {
    display: flex;
    align-items: center;
    flex: 1 1 620px;
    flex-wrap: wrap;
    gap: 10px;
}

.logistics-toolbar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.logistics-tab-content > section,
.logistics-tab-content > .v-container,
.logistics-tab-content .v-card,
.logistics-tab-content .v-table {
    min-width: 0;
}

.logistics-tab-content .v-table__wrapper {
    overflow-x: auto;
}

.logistics-empty {
    padding: 40px 20px;
    color: #708078;
    text-align: center;
}

.logistics-metric {
    min-height: 116px;
    border-color: #dbe4dd !important;
}

.logistics-metric__label {
    color: #657169;
    font-size: .78rem;
}

.logistics-metric__value {
    margin-top: 8px;
    font-size: 1.45rem;
    font-weight: 750;
    line-height: 1.15;
}

.logistics-metric__hint {
    margin-top: 5px;
    color: #7a867e;
    font-size: .73rem;
}

@media (max-width: 700px) {
    .logistics-page { padding-top: 14px; }
    .logistics-page__header { align-items: stretch; flex-direction: column; }
    .logistics-tab--geography-start { margin-left: 0; }
    .logistics-toolbar__filters > * { min-width: 100% !important; max-width: 100% !important; }
    .logistics-toolbar__actions { width: 100%; }
    .logistics-toolbar__actions .v-btn { flex: 1; }
}
</style>
