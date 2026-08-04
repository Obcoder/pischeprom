<script setup>
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, ref, watch } from 'vue'

import Categories from '@/Components/Dictionaries/Categories.vue'
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

const activeTab = ref('categories')
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
    const search = componentSearch.value.trim().toLocaleLowerCase('ru-RU')

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

    <v-theme-provider theme="dark">
        <main class="products-page">
            <div class="products-page__shell">
                <header class="products-page__header">
                    <div class="products-page__identity">
                        <div class="products-page__icon" aria-hidden="true">
                            <v-icon icon="mdi-package-variant-closed" size="26" />
                        </div>
                        <div>
                            <div class="products-page__eyebrow">Ameise · товарный контур</div>
                            <h1>Products</h1>
                            <p>Категории, продукты, товары, компоненты, commodities и услуги.</p>
                        </div>
                    </div>
                </header>

                <v-card class="products-page__workspace" variant="outlined">
                    <v-tabs
                        v-model="activeTab"
                        class="products-page__tabs"
                        color="light-blue-accent-2"
                        show-arrows
                        aria-label="Разделы Products"
                    >
                        <v-tab v-for="tab in tabs" :key="tab.value" :value="tab.value">
                            <v-icon :icon="tab.icon" start />
                            {{ tab.title }}
                        </v-tab>
                    </v-tabs>

                    <v-divider />

                    <v-tabs-window v-model="activeTab" class="products-page__content">
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
                                <div class="components-panel__toolbar">
                                    <div>
                                        <h2>Components</h2>
                                        <span>{{ filteredComponents.length }} / {{ components.length }} записей</span>
                                    </div>
                                    <v-text-field
                                        v-model="componentSearch"
                                        label="Поиск Components"
                                        prepend-inner-icon="mdi-magnify"
                                        variant="outlined"
                                        density="compact"
                                        clearable
                                        hide-details
                                        class="components-panel__search"
                                    />
                                    <v-btn
                                        icon="mdi-refresh"
                                        variant="outlined"
                                        :loading="componentsLoading"
                                        aria-label="Обновить Components"
                                        title="Обновить Components"
                                        @click="loadComponents(true)"
                                    />
                                </div>

                                <v-alert v-if="componentsError" type="error" variant="tonal" class="mb-3">
                                    {{ componentsError }}
                                </v-alert>

                                <v-data-table
                                    :items="filteredComponents"
                                    :headers="componentHeaders"
                                    :items-per-page="150"
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
    </v-theme-provider>
</template>

<style scoped>
.products-page {
    align-self: stretch;
    width: 100%;
    height: calc(100dvh - 58px);
    min-width: 0;
    min-height: 0;
    padding: 20px clamp(12px, 2vw, 32px) 18px;
    overflow: hidden;
    box-sizing: border-box;
    background:
        radial-gradient(circle at 8% 0%, rgba(14, 165, 233, 0.14), transparent 30rem),
        linear-gradient(145deg, #060b18 0%, #080d1d 48%, #050914 100%);
}

.products-page__shell {
    display: flex;
    height: 100%;
    width: min(1680px, 100%);
    min-width: 0;
    min-height: 0;
    margin-inline: auto;
    flex-direction: column;
}

.products-page__header {
    flex: 0 0 auto;
    margin-bottom: 14px;
}

.products-page__identity {
    display: flex;
    align-items: center;
    gap: 14px;
}

.products-page__icon {
    display: grid;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    place-items: center;
    border: 1px solid rgba(125, 211, 252, 0.3);
    border-radius: 15px;
    background: linear-gradient(145deg, rgba(14, 165, 233, 0.24), rgba(37, 99, 235, 0.09));
    color: #bae6fd;
    box-shadow: 0 0 24px rgba(14, 165, 233, 0.13);
}

.products-page__eyebrow {
    color: #7dd3fc;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.products-page h1 {
    margin: 2px 0 3px;
    color: #f8fafc;
    font-size: clamp(1.65rem, 3vw, 2.35rem);
    line-height: 1.08;
}

.products-page p {
    margin: 0;
    color: #94a3b8;
}

.products-page__workspace {
    display: flex;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    flex-direction: column;
    border-color: rgba(125, 211, 252, 0.2) !important;
    background: rgba(8, 15, 32, 0.92) !important;
    box-shadow: 0 22px 60px rgba(2, 6, 23, 0.38);
}

.products-page__tabs {
    flex: 0 0 auto;
    background: rgba(15, 23, 42, 0.7);
}

.products-page__tabs :deep(.v-tab) {
    min-width: 0;
    color: #dbeafe !important;
    opacity: 0.88;
}

.products-page__tabs :deep(.v-tab:hover),
.products-page__tabs :deep(.v-tab:focus-visible) {
    background: rgba(125, 211, 252, 0.1);
    color: #f8fafc !important;
    opacity: 1;
}

.products-page__tabs :deep(.v-tab.v-tab--selected) {
    background: linear-gradient(180deg, rgba(14, 165, 233, 0.17), rgba(37, 99, 235, 0.08));
    color: #7dd3fc !important;
    opacity: 1;
}

.products-page__content {
    flex: 1 1 0;
    height: 0;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

.products-page__content :deep(.v-window__container),
.products-page__content :deep(.v-window-item) {
    height: 100%;
    min-height: 0;
}

.products-page__content :deep(.v-window-item--active) {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.products-page__content :deep(.v-field:not(.v-field--variant-plain)) {
    border-radius: 9px;
    background: rgba(248, 250, 252, 0.96) !important;
    color: #0f172a !important;
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.26);
}

.products-page__content :deep(.v-field:not(.v-field--variant-plain) .v-field__overlay) {
    opacity: 0 !important;
}

.products-page__content :deep(.v-field:not(.v-field--variant-plain) .v-field__input),
.products-page__content :deep(.v-field:not(.v-field--variant-plain) .v-label),
.products-page__content :deep(.v-field:not(.v-field--variant-plain) .v-icon) {
    color: #1e293b !important;
    opacity: 1;
}

.products-page__content :deep(.v-field--variant-outlined .v-label.v-field-label--floating) {
    padding-inline: 4px;
    border-radius: 3px;
    background: #f8fafc;
    color: #334155 !important;
}

.products-page__content :deep(.v-field:not(.v-field--variant-plain).v-field--focused) {
    background: #ffffff !important;
    box-shadow:
        inset 0 0 0 1px rgba(56, 189, 248, 0.72),
        0 0 0 3px rgba(56, 189, 248, 0.12);
}

.products-page__content :deep(.v-field:not(.v-field--variant-plain) input::placeholder) {
    color: #64748b !important;
    opacity: 1;
}

.products-page__content :deep(input[type="date"]) {
    color-scheme: light;
}

.components-panel {
    display: flex;
    height: 100%;
    min-height: 0;
    padding: 12px;
    flex-direction: column;
}

.components-panel__toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.components-panel__toolbar h2 {
    margin: 0;
    font-size: 1.05rem;
}

.components-panel__toolbar span {
    color: #94a3b8;
    font-size: .75rem;
}

.components-panel__search {
    width: min(360px, 100%);
    margin-left: auto;
}

.components-panel__table {
    display: flex;
    flex: 1 1 auto;
    min-height: 0;
    flex-direction: column;
    border: 1px solid rgba(125, 211, 252, 0.14);
    border-radius: 12px;
}

.components-panel__table :deep(.v-table__wrapper) {
    flex: 1 1 auto;
    min-height: 0;
}

@media (max-width: 720px) {
    .products-page {
        padding: 14px 10px 12px;
    }

    .products-page__identity {
        align-items: flex-start;
    }

    .components-panel__toolbar {
        align-items: stretch;
        flex-wrap: wrap;
    }

    .components-panel__search {
        width: 100%;
        margin-left: 0;
    }

    .components-panel {
        padding: 8px;
    }
}
</style>
