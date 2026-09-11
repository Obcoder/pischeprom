<script setup>
import { Head } from '@inertiajs/vue3'
import SalesBoard from '@/Components/Grossbuch/GrossbuchSales.vue'
import TradeFlowIcon from '@/Components/Icons/TradeFlowIcon.vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import PurchasesBoard from '@/Pages/Purchases/Purchases.vue'

defineOptions({ layout: VerwalterLayout })
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
                        <p>Контрагенты, суммы и история операций</p>
                    </div>
                </div>
            </header>

            <div class="commerce-workspace">
                <section class="commerce-panel commerce-panel--purchases" aria-labelledby="commerce-purchases-title">
                    <header class="commerce-panel__heading">
                        <v-icon icon="mdi-arrow-bottom-left" size="17" aria-hidden="true" />
                        <h2 id="commerce-purchases-title">Закупки</h2>
                    </header>
                    <PurchasesBoard />
                </section>
                <section class="commerce-panel commerce-panel--sales" aria-labelledby="commerce-sales-title">
                    <header class="commerce-panel__heading">
                        <v-icon icon="mdi-arrow-top-right" size="17" aria-hidden="true" />
                        <h2 id="commerce-sales-title">Продажи</h2>
                    </header>
                    <SalesBoard />
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

.commerce-workspace {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-rows: minmax(0, 1fr);
    flex: 1 1 0;
    min-width: 0;
    min-height: 0;
    gap: 12px;
}

.commerce-panel {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    border: 1px solid #dce4ee;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 2px 6px #0f172a04;
    container-type: inline-size;
    container-name: commerce-panel;
}

.commerce-panel--purchases { --panel-accent: #2563eb; --panel-tint: #eff6ff; }
.commerce-panel--sales { --panel-accent: #0f766e; --panel-tint: #f0fdfa; }

.commerce-panel__heading {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 7px;
    min-height: 33px;
    padding: 5px 12px;
    border-bottom: 1px solid #e2e8f0;
    background: var(--panel-tint);
    color: var(--panel-accent);
}

.commerce-panel__heading h2 {
    margin: 0;
    font-size: 13px;
    font-weight: 650;
    line-height: 1.4;
}

@media (max-width: 760px) {
    .commerce-page { padding: 6px; }
    .commerce-header { padding-bottom: 6px; }
    .commerce-heading h1 { font-size: 16px; }
    .commerce-heading p { display: none; }
    .commerce-heading__icon { width: 28px; height: 28px; flex-basis: 28px; border-radius: 8px; }
    .commerce-workspace { grid-template-columns: minmax(0, 1fr); grid-template-rows: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .commerce-panel { border-radius: 8px; }
    .commerce-panel__heading { min-height: 28px; padding: 3px 9px; }
}

@media (max-height: 550px) and (min-width: 761px) {
    .commerce-page { padding: 6px 10px; }
    .commerce-header { padding-bottom: 6px; }
    .commerce-heading p { display: none; }
    .commerce-panel__heading { min-height: 28px; padding-block: 3px; }
}
</style>
