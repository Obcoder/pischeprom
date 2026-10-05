<script setup>
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'

import Categories from '@/Components/Dictionaries/Categories.vue'
import CatalogToolbar from '@/Components/Dictionaries/CatalogToolbar.vue'
import CommoditiesPage from '@/Components/Dictionaries/Commodities/CommoditiesPage.vue'
import Goods from '@/Components/Dictionaries/Goods.vue'
import Products from '@/Components/Dictionaries/Products.vue'
import ServicesPage from '@/Components/Dictionaries/Services/ServicesPage.vue'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

const PRODUCTS_TAB_KEY = 'ameise:products:tab'
const LEGACY_PRODUCTS_TAB_KEY = 'ameise:grossbuch:products-tab'
const allowedTabs = ['categories', 'categories_products', 'goods', 'components', 'commodities', 'services']

const workspaceDefaults = {
    VCard: { rounded: 0, elevation: 0 },
    VBtn: { rounded: 0, elevation: 0, color: '#352345' },
    VChip: { rounded: 0, color: '#352345' },
    VTextField: { variant: 'outlined', density: 'compact' },
    VSelect: { variant: 'outlined', density: 'compact' },
    VAutocomplete: { variant: 'outlined', density: 'compact' },
    VDialog: { contentClass: 'products-workspace-dialog' },
    VToolbar: { density: 'compact', color: '#f5f5f6' },
}

const activeTab = ref('categories')
const tabsReady = ref(false)
const components = ref([])
const componentsLoading = ref(false)
const componentsLoaded = ref(false)
const componentsError = ref('')
const componentSearch = ref('')

const tabs = [
    { value: 'categories', title: 'Categories', icon: 'mdi-shape-outline' },
    { value: 'categories_products', title: 'Products', icon: 'mdi-package-variant-closed' },
    { value: 'goods', title: 'Goods', icon: 'mdi-basket-outline' },
    { value: 'components', title: 'Components', icon: 'mdi-puzzle-outline' },
    { value: 'commodities', title: 'Commodities', icon: 'mdi-cube-outline' },
    { value: 'services', title: 'Услуги', icon: 'mdi-handshake-outline' },
]

const componentHeaders = [
    { key: 'name', title: 'Name', align: 'start', sortable: true },
]

const filteredComponents = computed(() => {
    const search = (componentSearch.value || '').trim().toLocaleLowerCase('ru-RU')

    if (!search) {
        return components.value
    }

    return components.value.filter((item) => String(item?.name || '').toLocaleLowerCase('ru-RU').includes(search))
})

function storedTab() {
    if (typeof window === 'undefined') {
        return 'categories'
    }

    const value = window.localStorage.getItem(PRODUCTS_TAB_KEY)
        || window.localStorage.getItem(LEGACY_PRODUCTS_TAB_KEY)

    return allowedTabs.includes(value) ? value : 'categories'
}

async function loadComponents(force = false) {
    if ((componentsLoaded.value && !force) || componentsLoading.value) {
        return
    }

    componentsLoading.value = true
    componentsError.value = ''

    try {
        const response = await axios.get('/api/components')
        components.value = Array.isArray(response.data) ? response.data : (response.data?.data || [])
        componentsLoaded.value = true
    } catch (error) {
        console.error(error)
        componentsError.value = 'Не удалось загрузить Components.'
    } finally {
        componentsLoading.value = false
    }
}

onMounted(() => {
    activeTab.value = storedTab()
    tabsReady.value = true

    if (activeTab.value === 'components') {
        loadComponents()
    }
})

watch(activeTab, (value) => {
    if (!allowedTabs.includes(value)) {
        activeTab.value = 'categories'
        return
    }

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(PRODUCTS_TAB_KEY, value)
    }

    if (value === 'components') {
        loadComponents()
    }
})
</script>

<template>
    <Head title="Products" />

    <v-theme-provider theme="light">
        <v-defaults-provider :defaults="workspaceDefaults">
            <main class="products-page" aria-label="Products — товарный каталог">
                <div class="products-page__shell">
                    <v-card class="products-page__workspace" variant="outlined">
                        <v-tabs
                            v-model="activeTab"
                            class="products-page__tabs"
                            color="#352345"
                            density="compact"
                            height="36"
                            show-arrows
                            aria-label="Разделы Products"
                        >
                            <v-tab v-for="tab in tabs" :key="tab.value" :value="tab.value">
                                <v-icon :icon="tab.icon" size="16" class="mr-2" />
                                {{ tab.title }}
                            </v-tab>
                        </v-tabs>

                        <v-divider />

                        <v-tabs-window v-if="tabsReady" v-model="activeTab" :touch="false" class="products-page__content">
                            <v-tabs-window-item value="categories" class="products-page__pane">
                                <Categories />
                            </v-tabs-window-item>

                            <v-tabs-window-item value="categories_products" class="products-page__pane">
                                <Products />
                            </v-tabs-window-item>

                            <v-tabs-window-item value="goods" class="products-page__pane">
                                <Goods />
                            </v-tabs-window-item>

                            <v-tabs-window-item value="components" class="products-page__pane">
                                <section class="components-panel">
                                    <CatalogToolbar :count="filteredComponents.length" :total="components.length">
                                        <v-text-field
                                            v-model="componentSearch"
                                            label="Поиск Components"
                                            prepend-inner-icon="mdi-magnify"
                                            variant="outlined"
                                            density="compact"
                                            clearable
                                            hide-details
                                            class="catalog-toolbar__search"
                                        />
                                        <template #actions>
                                            <v-btn
                                                icon="mdi-refresh"
                                                variant="text"
                                                :loading="componentsLoading"
                                                aria-label="Обновить Components"
                                                title="Обновить Components"
                                                @click="loadComponents(true)"
                                            />
                                        </template>
                                    </CatalogToolbar>

                                    <v-alert v-if="componentsError" type="error" variant="tonal" class="mb-3">
                                        {{ componentsError }}
                                    </v-alert>

                                    <v-data-table
                                        :items="filteredComponents"
                                        :headers="componentHeaders"
                                        :items-per-page="50"
                                        :loading="componentsLoading"
                                        fixed-header
                                        density="compact"
                                        hover
                                        class="components-panel__table"
                                    >
                                        <template #no-data>
                                            <div class="pa-6 text-medium-emphasis">Components не найдены.</div>
                                        </template>
                                    </v-data-table>
                                </section>
                            </v-tabs-window-item>

                            <v-tabs-window-item value="commodities" class="products-page__pane">
                                <CommoditiesPage />
                            </v-tabs-window-item>

                            <v-tabs-window-item value="services" class="products-page__pane">
                                <ServicesPage />
                            </v-tabs-window-item>
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
    padding: 10px 20px 12px;
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
