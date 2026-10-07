<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted, ref, watch } from 'vue'
import CatalogWorkspace from '@/Components/Catalog/CatalogWorkspace.vue'
import { restoredCatalogTab } from '@/Components/Catalog/tree.js'
import ComponentsPage from '@/Components/Dictionaries/ComponentsPage.vue'
import CommoditiesPage from '@/Components/Dictionaries/Commodities/CommoditiesPage.vue'
import ServicesPage from '@/Components/Dictionaries/Services/ServicesPage.vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({ layout: VerwalterLayout })
const activeTab = ref('goods')
const purchasedTab = ref('commodities')
const tabsReady = ref(false)
const workspaceDefaults = {
    VCard: { rounded: 0, elevation: 0 },
    VBtn: { rounded: 0, elevation: 0, color: '#352345' },
    VChip: { rounded: 0, color: '#352345' },
    VTextField: { variant: 'outlined', density: 'compact' },
    VSelect: { variant: 'outlined', density: 'compact' },
    VAutocomplete: { variant: 'outlined', density: 'compact' },
    VDialog: { contentClass: 'products-workspace-dialog' },
}
onMounted(() => {
    try {
        const stored = window.localStorage.getItem('ameise:products:tab') || window.localStorage.getItem('ameise:grossbuch:products-tab')
        activeTab.value = restoredCatalogTab(stored)
        purchasedTab.value = stored === 'services' ? 'services' : (window.localStorage.getItem('ameise:products:purchased-tab') === 'services' ? 'services' : 'commodities')
    } catch { /* Storage can be disabled in private browsing. */ }
    tabsReady.value = true
})
watch(activeTab, value => { try { window.localStorage.setItem('ameise:products:tab', value) } catch {} })
watch(purchasedTab, value => { try { window.localStorage.setItem('ameise:products:purchased-tab', value) } catch {} })
</script>

<template>
    <Head title="Товароведение" />
    <v-theme-provider theme="light">
        <v-defaults-provider :defaults="workspaceDefaults">
            <main class="products-page" aria-label="Товароведение">
                <div class="products-page__shell">
                    <v-card class="products-page__workspace" variant="outlined">
                        <v-tabs v-model="activeTab" class="products-page__tabs" color="#352345" height="40" show-arrows aria-label="Разделы товароведения">
                            <v-tab value="goods"><v-icon icon="mdi-file-tree-outline" size="17" class="mr-2" />Товары</v-tab>
                            <v-tab value="purchased"><v-icon icon="mdi-cart-arrow-down" size="17" class="mr-2" />Закупаемое</v-tab>
                            <v-tab value="components"><v-icon icon="mdi-puzzle-outline" size="17" class="mr-2" />Компоненты</v-tab>
                        </v-tabs>
                        <v-divider />
                        <v-tabs-window v-if="tabsReady" v-model="activeTab" :touch="false" class="products-page__content">
                            <v-tabs-window-item value="goods"><CatalogWorkspace /></v-tabs-window-item>
                            <v-tabs-window-item value="purchased">
                                <v-tabs v-model="purchasedTab" class="products-page__tabs" height="36" aria-label="Виды закупаемого">
                                    <v-tab value="commodities">Commodities · Материалы</v-tab>
                                    <v-tab value="services">Услуги</v-tab>
                                </v-tabs>
                                <v-divider />
                                <v-tabs-window v-model="purchasedTab" :touch="false" class="products-page__content">
                                    <v-tabs-window-item value="commodities"><CommoditiesPage /></v-tabs-window-item>
                                    <v-tabs-window-item value="services"><ServicesPage /></v-tabs-window-item>
                                </v-tabs-window>
                            </v-tabs-window-item>
                            <v-tabs-window-item value="components"><ComponentsPage /></v-tabs-window-item>
                        </v-tabs-window>
                    </v-card>
                </div>
            </main>
        </v-defaults-provider>
    </v-theme-provider>
</template>

<style scoped>
.products-page {
    --catalog-purple: #352345;
    --catalog-line: #d9d7dc;
    align-self: stretch;
    width: 100%;
    height: calc(100dvh - 58px);
    min-width: 0;
    min-height: 0;
    padding: 8px 16px 10px;
    overflow: hidden;
    box-sizing: border-box;
    background: #f5f5f6;
    color: #242127;
    font-size: 13px;
}

.products-page__shell, .products-page__workspace {
    display: flex;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
}
.products-page__workspace {
    flex: 1 1 0;
    height: 0;
    overflow: hidden;
    border-color: var(--catalog-line);
    background: #fff;
}
.products-page__tabs { flex: 0 0 auto; background: #fff; }
.products-page__tabs :deep(.v-tab) {
    min-width: 0;
    padding: 0 13px;
    color: #66606c;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0;
    text-transform: none;
}
.products-page__tabs :deep(.v-tab--selected) { background: #f2eff5; color: var(--catalog-purple); }
.products-page__content { flex: 1 1 0; height: 0; min-width: 0; min-height: 0; overflow: hidden; }
.products-page__content > :deep(.v-window__container),
.products-page__content > :deep(.v-window__container > .v-window-item) { height: 100%; min-height: 0; }
.products-page__content > :deep(.v-window__container > .v-window-item--active) { display: flex; flex-direction: column; overflow: hidden; }
.products-page :deep(.v-card), .products-page :deep(.v-sheet),
.products-page :deep(.v-btn), .products-page :deep(.v-chip),
.products-page :deep(.v-alert), .products-page :deep(.v-field) {
    border-radius: 0 !important;
    box-shadow: none !important;
    background-image: none !important;
}
.products-page :deep(.v-btn) { text-transform: none; letter-spacing: 0; }
.products-page__content :deep(.v-field:not(.v-field--variant-plain)) { background: #fff; color: #242127; }
.products-page__content :deep(.v-table) { color: #242127; font-size: 12px; border-radius: 0 !important; }
.products-page__content :deep(.v-data-table__th) { height: 32px !important; background: #f5f5f6 !important; color: #66606c; font-size: 11px; font-weight: 600; white-space: nowrap; }
.products-page__content :deep(.v-data-table-footer) { flex-shrink: 0; min-height: 36px; padding: 2px 8px; border-top: 1px solid var(--catalog-line); font-size: 11px; gap: 8px; }
.products-page__content :deep(.v-data-table-footer__items-per-page) { gap: 6px; }
.products-page__content :deep(.v-data-table-footer__info) { min-width: 80px; }
.products-page__content :deep(.v-data-table-footer .v-field__input) { min-height: 28px; padding-block: 2px; font-size: 11px; }
.products-page__content :deep(.v-data-table-footer .v-btn) { width: 28px; height: 28px; }
.products-page :deep(button:focus-visible), .products-page :deep(a:focus-visible) { outline: 2px solid var(--catalog-purple); outline-offset: 2px; }
.components-panel { display: flex; height: 100%; min-height: 0; flex-direction: column; }
.components-panel__table { display: flex; flex: 1 1 0; min-height: 0; flex-direction: column; }
.components-panel__table :deep(.v-table__wrapper) { flex: 1 1 auto; min-height: 0; }
@media (max-width: 760px) {
    .products-page { padding: 8px; }
    .products-page__tabs :deep(.v-tab) { padding: 0 10px; }
    .products-page__content :deep(.v-data-table-footer) { justify-content: center; }
}
</style>

<style>
.products-workspace-dialog .v-card, .products-workspace-dialog .v-field, .products-workspace-dialog .v-btn { border-radius: 0 !important; box-shadow: none !important; }
.products-workspace-dialog .v-card { border: 1px solid #d9d7dc; }
.products-workspace-dialog .v-btn { letter-spacing: 0; text-transform: none; }
</style>
