<script setup>
import { Head } from '@inertiajs/vue3'
import { nextTick, onMounted, ref } from 'vue'
import SalesBoard from '@/Components/Grossbuch/GrossbuchSales.vue'
import TradeFlowIcon from '@/Components/Icons/TradeFlowIcon.vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import PurchasesBoard from '@/Pages/Purchases/Purchases.vue'

defineOptions({ layout: VerwalterLayout })

const TAB_KEY = 'ameise:commerce:tab'
const tabs = [
    { value: 'purchases', title: 'Закупки', icon: 'mdi-arrow-bottom-left' },
    { value: 'sales', title: 'Продажи', icon: 'mdi-arrow-top-right' },
]
const activeTab = ref('purchases')
const visitedTabs = ref(['purchases'])

function selectTab(value) {
    if (!tabs.some((tab) => tab.value === value)) return

    activeTab.value = value
    if (!visitedTabs.value.includes(value)) visitedTabs.value.push(value)

    try {
        window.localStorage.setItem(TAB_KEY, value)
    } catch {
        // The workspace remains usable when browser storage is unavailable.
    }
}

async function navigateTabs(event) {
    const index = tabs.findIndex((tab) => tab.value === activeTab.value)
    let nextIndex

    if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length
    else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length
    else if (event.key === 'Home') nextIndex = 0
    else if (event.key === 'End') nextIndex = tabs.length - 1
    else return

    event.preventDefault()
    selectTab(tabs[nextIndex].value)
    await nextTick()
    document.getElementById(`commerce-tab-${activeTab.value}`)?.focus()
}

onMounted(() => {
    try {
        const savedTab = window.localStorage.getItem(TAB_KEY)
        if (savedTab) selectTab(savedTab)
    } catch {
        // Use the default tab when storage is unavailable.
    }
})
</script>

<template>
    <Head title="Закупки и продажи" />

    <v-theme-provider theme="light">
        <main class="commerce-page">
            <header class="commerce-header">
                <div class="commerce-heading">
                    <span class="commerce-heading__icon" aria-hidden="true"><TradeFlowIcon /></span>
                    <div>
                        <h1>Закупки и продажи</h1>
                        <p>Товары, контрагенты и суммы операций</p>
                    </div>
                </div>

                <div class="commerce-tabs" role="tablist" aria-label="Реестры операций" @keydown="navigateTabs">
                    <button
                        v-for="tab in tabs"
                        :id="`commerce-tab-${tab.value}`"
                        :key="tab.value"
                        type="button"
                        role="tab"
                        :class="['commerce-tab', `commerce-tab--${tab.value}`, { 'is-active': activeTab === tab.value }]"
                        :aria-selected="activeTab === tab.value"
                        :aria-controls="`commerce-panel-${tab.value}`"
                        :tabindex="activeTab === tab.value ? 0 : -1"
                        @click="selectTab(tab.value)"
                    >
                        <v-icon :icon="tab.icon" size="17" />
                        {{ tab.title }}
                    </button>
                </div>
            </header>

            <div class="commerce-workspace">
                <section
                    id="commerce-panel-purchases"
                    v-show="activeTab === 'purchases'"
                    class="commerce-panel"
                    role="tabpanel"
                    aria-labelledby="commerce-tab-purchases"
                    tabindex="0"
                >
                    <PurchasesBoard />
                </section>
                <section
                    id="commerce-panel-sales"
                    v-show="activeTab === 'sales'"
                    class="commerce-panel"
                    role="tabpanel"
                    aria-labelledby="commerce-tab-sales"
                    tabindex="0"
                >
                    <SalesBoard v-if="visitedTabs.includes('sales')" />
                </section>
            </div>
        </main>
    </v-theme-provider>
</template>

<style scoped>
.commerce-page {
    display: flex;
    flex-direction: column;
    align-self: stretch;
    width: 100%;
    height: calc(100vh - var(--v-layout-top, 58px) - var(--v-layout-bottom, 0px));
    height: calc(100dvh - var(--v-layout-top, 58px) - var(--v-layout-bottom, 0px));
    min-width: 0;
    min-height: 0;
    padding: 12px 16px 10px;
    overflow: hidden;
    background: #f1f5f9;
    color: #1e293b;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto-Regular', sans-serif;
    font-size: 13px;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.commerce-header {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 0 2px 12px;
}

.commerce-heading {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 10px;
}

.commerce-heading__icon {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;
    border: 1px solid #d8e3ed;
    border-radius: 11px;
    background: #fff;
    color: #0f766e;
}

.commerce-heading__icon :deep(svg) {
    width: 24px;
    height: 24px;
}

.commerce-heading h1 {
    margin: 0;
    font-size: 19px;
    font-weight: 650;
    line-height: 1.3;
    letter-spacing: -.4px;
}

.commerce-heading p {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 11px;
    line-height: 1.4;
}

.commerce-tabs {
    display: flex;
    flex-shrink: 0;
    gap: 3px;
    padding: 3px;
    border: 1px solid #dce4ee;
    border-radius: 10px;
    background: #e8eef5;
}

.commerce-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 34px;
    padding: 5px 17px;
    border-radius: 7px;
    color: #526176;
    font-size: 13px;
    font-weight: 600;
    transition: color 140ms ease, background 140ms ease, box-shadow 140ms ease;
}

.commerce-tab:hover { background: #f8fafc; }
.commerce-tab.is-active { background: #fff; box-shadow: 0 1px 4px #0f172a14; }
.commerce-tab--purchases.is-active { color: #2563eb; }
.commerce-tab--sales.is-active { color: #0f766e; }

.commerce-tab:focus-visible,
.commerce-panel:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

.commerce-workspace,
.commerce-panel {
    display: flex;
    flex-direction: column;
    flex: 1 1 0;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

.commerce-workspace {
    border: 1px solid #dce4ee;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 2px 6px #0f172a04;
}

.commerce-panel { height: 100%; }

@media (max-width: 600px) {
    .commerce-page { padding: 8px; }
    .commerce-header { flex-wrap: wrap; gap: 8px; padding-bottom: 8px; }
    .commerce-heading h1 { font-size: 17px; }
    .commerce-heading p { display: none; }
    .commerce-heading__icon { width: 30px; height: 30px; flex-basis: 30px; border-radius: 8px; }
    .commerce-tabs { width: 100%; }
    .commerce-tab { flex: 1; min-height: 34px; }
    .commerce-workspace { border-radius: 9px; }
}

@media (max-height: 550px) and (min-width: 601px) {
    .commerce-page { padding: 6px 10px; }
    .commerce-header { padding-bottom: 6px; }
    .commerce-heading p { display: none; }
}

@media (prefers-reduced-motion: reduce) {
    .commerce-tab { transition: none; }
}
</style>
