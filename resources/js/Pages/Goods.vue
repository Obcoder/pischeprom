<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import LayoutDefault from '@/Layouts/LayoutDefault.vue'
import PublicCatalogGoodCard from '@/Components/Goods/PublicCatalogGoodCard.vue'
import PublicCatalogTree from '@/Components/Goods/PublicCatalogTree.vue'
import PublicCatalogPriceList from '@/Components/Goods/PublicCatalogPriceList.vue'
import PublicCatalogCategoryFilter from '@/Components/Goods/PublicCatalogCategoryFilter.vue'
import GoodInquiryDialog from '@/Components/Goods/GoodInquiryDialog.vue'
import CatalogNodeAvatar from '@/Components/Catalog/CatalogNodeAvatar.vue'
import { useAppRoute } from '@/Composables/useAppRoute'
import { catalogDefaults, normalizeCatalogFilters, catalogQuery, catalogPages } from '@/utils/publicCatalog'

defineOptions({ layout: LayoutDefault })
const props = defineProps({
    goods: { type: [Object, Array], default: () => [] },
    filters: { type: Object, default: () => ({}) },
    field: { type: Object, default: null },
    fields: { type: Array, default: () => [] },
    country: { type: Object, default: null },
    countries: { type: Array, default: () => [] },
    catalogTree: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    site: { type: Object, default: () => ({}) },
})
const { route: appRoute } = useAppRoute()
const page = usePage()
const form = reactive(normalizeCatalogFilters(props.filters))
const loading = ref(false)
const requestError = ref('')
const inquiry = ref(null)
const inquiryOpen = ref(false)
const notice = ref('')
const noticeOpen = ref(false)
const noticeSuccess = ref(true)
let searchTimer
let visitSequence = 0
const preferenceKey = 'public-catalog-preferences-v1'
const productList = computed(() => Array.isArray(props.goods) ? props.goods : props.goods.data || [])
const total = computed(() => Array.isArray(props.goods) ? props.goods.length : Number(props.goods.total || 0))
const currentPage = computed(() => Number(props.goods.current_page || 1))
const lastPage = computed(() => Number(props.goods.last_page || 1))
const resultFrom = computed(() => props.goods.from ?? (total.value ? 1 : 0))
const resultTo = computed(() => props.goods.to ?? productList.value.length)
const pages = computed(() => catalogPages(currentPage.value, lastPage.value))
const selectedNode = computed(() => props.catalogTree.find(node => String(node.id) === String(form.node_id)))
const siteName = computed(() => props.site?.name || 'ПИЩЕПРОМ-СЕРВЕР')
const pageH1 = computed(() => selectedNode.value?.name || props.field?.title || props.field?.name || (props.country ? `Товары из ${props.country.name}` : 'Каталог товаров'))
const pageDescription = computed(() => props.field?.description || 'Выбирайте товары, сравнивайте предложения и собирайте заказ. Условия и сроки поставки согласуем с вами.')
const visibleCategories = computed(() => {
    const ids = new Set(props.catalogTree.map(node => String(node.id)))
    return props.catalogTree.filter(node => form.node_id ? String(node.parent_id) === String(form.node_id) : !ids.has(String(node.parent_id)))
})
const activeFilters = computed(() => [
    form.search && { key: 'search', label: `Поиск: ${form.search}` },
    form.country_id && { key: 'country_id', label: props.countries.find(item => String(item.id) === String(form.country_id))?.name || props.country?.name || 'Страна' },
    form.field_id && !props.field && { key: 'field_id', label: props.fields.find(item => String(item.id) === String(form.field_id))?.title || props.fields.find(item => String(item.id) === String(form.field_id))?.name || 'Подборка' },
    form.availability !== 'all' && { key: 'availability', label: form.availability === 'in_stock' ? 'В наличии' : 'Наличие по запросу' },
    form.price_min !== '' && { key: 'price_min', label: `Цена от ${form.price_min}` },
    form.price_max !== '' && { key: 'price_max', label: `Цена до ${form.price_max}` },
].filter(Boolean))
const perPageOptions = computed(() => [...new Set([10, 20, 24, 50, 100, form.per_page])].sort((a, b) => a - b))
const totalLabel = computed(() => `${total.value.toLocaleString('ru-RU')} ${pluralize(total.value)}`)

function pluralize(count) { const n = count % 100; return n > 10 && n < 20 ? 'товаров' : count % 10 === 1 ? 'товар' : [2, 3, 4].includes(count % 10) ? 'товара' : 'товаров' }
function targetUrl() { return props.field?.id ? appRoute('public.fields.show', props.field.slug || props.field.id) : appRoute('public.goods.index') }
function rememberPreferences() {
    if (typeof window === 'undefined') return
    try { window.localStorage.setItem(preferenceKey, JSON.stringify({ view: form.view, per_page: form.per_page, show_filters: form.show_filters })) } catch { /* Browser storage can be disabled. */ }
}
function visit(pageNumber = 1, replace = false) {
    clearTimeout(searchTimer)
    requestError.value = ''
    rememberPreferences()
    const sequence = ++visitSequence
    router.get(targetUrl(), catalogQuery(form, pageNumber), {
        preserveState: true, preserveScroll: true, replace,
        onStart: () => { loading.value = true },
        onError: errors => { requestError.value = Object.values(errors).flat().join(' ') || 'Не удалось обновить каталог. Попробуйте ещё раз.' },
        onFinish: () => { if (sequence === visitSequence) loading.value = false },
    })
}
function search() { clearTimeout(searchTimer); searchTimer = setTimeout(() => visit(1, true), 350) }
function setSort(sort) { form.sort = sort; visit() }
function setView(view) { form.view = view; visit(currentPage.value, true) }
function toggleFilters() { form.show_filters = !form.show_filters; visit(currentPage.value, true) }
function selectNode(id) { form.node_id = id || ''; visit() }
function clearFilter(key) { form[key] = catalogDefaults[key]; visit() }
function resetFilters() { const preferences = { view: form.view, per_page: form.per_page, show_filters: form.show_filters }; Object.assign(form, catalogDefaults, preferences); visit() }
function openInquiry(payload) { inquiry.value = payload; inquiryOpen.value = true }
function showNotice(payload) { notice.value = payload.message; noticeSuccess.value = payload.success; noticeOpen.value = true }
watch(() => props.filters, values => { clearTimeout(searchTimer); Object.assign(form, normalizeCatalogFilters(values)) })
onMounted(() => {
    try {
        const stored = JSON.parse(window.localStorage.getItem(preferenceKey) || 'null')
        if (!stored || typeof stored !== 'object') return
        const query = new URL(page.url || window.location.href, window.location.origin).searchParams
        const next = normalizeCatalogFilters({ ...form, ...Object.fromEntries(['view', 'per_page', 'show_filters'].filter(key => !query.has(key) && stored[key] !== undefined).map(key => [key, stored[key]])) })
        if (next.view !== form.view || next.per_page !== form.per_page || next.show_filters !== form.show_filters) { Object.assign(form, next); visit(currentPage.value, true) }
    } catch { /* A malformed saved preference must not prevent browsing. */ }
})
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
    <Head :title="`${pageH1} — ${siteName}`">
        <meta head-key="description" name="description" :content="pageDescription">
    </Head>
    <div class="catalog-page">
        <div class="catalog-page__inner">
            <nav class="catalog-breadcrumbs" aria-label="Навигационная цепочка">
                <Link href="/">Главная</Link><v-icon icon="mdi-chevron-right" size="13" />
                <button v-if="form.node_id" type="button" @click="selectNode('')"><CatalogNodeAvatar v-if="site" :node="site" :size="18" />Каталог</button><span v-else aria-current="page"><CatalogNodeAvatar v-if="site" :node="site" :size="18" />Каталог</span>
                <template v-for="(crumb, index) in breadcrumbs" :key="crumb.id"><v-icon icon="mdi-chevron-right" size="13" /><span v-if="index === breadcrumbs.length - 1" aria-current="page"><CatalogNodeAvatar :node="crumb" :size="18" />{{ crumb.name }}</span><button v-else type="button" @click="selectNode(crumb.id)"><CatalogNodeAvatar :node="crumb" :size="18" />{{ crumb.name }}</button></template>
                <template v-if="field"><v-icon icon="mdi-chevron-right" size="13" /><span aria-current="page">{{ field.title || field.name }}</span></template>
            </nav>
            <header class="catalog-hero">
                <h1><CatalogNodeAvatar v-if="selectedNode" :node="selectedNode" :size="36" />{{ pageH1 }}</h1>
                <p v-if="field?.description">{{ field.description }}</p>
            </header>
            <section class="catalog-workspace" aria-label="Подбор товаров">
                <div class="catalog-searchbar">
                    <form class="catalog-search" role="search" @submit.prevent="visit(1, true)">
                        <v-icon icon="mdi-magnify" size="21" /><input v-model="form.search" type="search" placeholder="Название товара, ингредиент…" aria-label="Поиск по каталогу" @input="search"><button type="submit">Найти</button>
                    </form>
                    <button type="button" class="catalog-filter-toggle" :class="{ 'is-active': form.show_filters }" :aria-expanded="form.show_filters" aria-controls="catalog-filters" @click="toggleFilters"><v-icon icon="mdi-tune-variant" size="18" />Фильтры<span v-if="activeFilters.length">{{ activeFilters.length }}</span><v-icon :icon="form.show_filters ? 'mdi-chevron-up' : 'mdi-chevron-down'" size="15" /></button>
                    <div class="catalog-view-switch" role="group" aria-label="Вид каталога"><button type="button" :aria-pressed="form.view === 'cards'" :class="{ 'is-active': form.view === 'cards' }" @click="setView('cards')"><v-icon icon="mdi-view-grid-outline" size="18" /><span>Карточки</span></button><button type="button" :aria-pressed="form.view === 'tree'" :class="{ 'is-active': form.view === 'tree' }" @click="setView('tree')"><v-icon icon="mdi-file-tree-outline" size="18" /><span>Дерево</span></button><button type="button" :aria-pressed="form.view === 'pricelist'" :class="{ 'is-active': form.view === 'pricelist' }" @click="setView('pricelist')"><v-icon icon="mdi-table-large" size="18" /><span>Прайс-лист</span></button></div>
                </div>
                <div v-if="!form.show_filters && (visibleCategories.length || form.node_id)" class="catalog-categories" aria-label="Категории товаров"><button v-if="form.node_id" type="button" class="catalog-categories__back" @click="selectNode(selectedNode?.parent_id)"><v-icon icon="mdi-arrow-left" size="14" />На уровень выше</button><button v-for="node in visibleCategories" :key="node.id" type="button" @click="selectNode(node.id)"><CatalogNodeAvatar :node="node" :size="22" />{{ node.name }}<span>{{ node.goods_count || 0 }}</span></button></div>
                <div v-if="activeFilters.length" class="catalog-active-filters"><button v-for="filter in activeFilters" :key="filter.key" type="button" :aria-label="`Убрать фильтр ${filter.label}`" @click="clearFilter(filter.key)">{{ filter.label }}<v-icon icon="mdi-close" size="13" /></button><button type="button" class="catalog-reset" @click="resetFilters">Сбросить всё</button></div>
                <div class="catalog-layout" :class="{ 'catalog-layout--filters': form.show_filters }">
                    <aside v-if="form.show_filters" id="catalog-filters" class="catalog-filters" aria-label="Фильтры каталога">
                        <div class="catalog-filters__heading"><strong>Уточнить выбор</strong><button type="button" @click="resetFilters">Сбросить</button></div>
                        <PublicCatalogCategoryFilter class="catalog-category-picker" :nodes="catalogTree" :selected-id="form.node_id" @select="selectNode" />
                        <fieldset><legend>Наличие</legend><label class="catalog-radio"><input v-model="form.availability" type="radio" value="all" @change="visit()"><span>Все товары</span></label><label class="catalog-radio"><input v-model="form.availability" type="radio" value="in_stock" @change="visit()"><span>В наличии</span></label><label class="catalog-radio"><input v-model="form.availability" type="radio" value="on_request" @change="visit()"><span>Наличие по запросу</span></label></fieldset>
                        <label><span>Страна происхождения</span><select v-model="form.country_id" @change="visit()"><option value="">Все страны</option><option v-for="item in countries" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                        <label v-if="fields.length && !field"><span>Подборка</span><select v-model="form.field_id" @change="visit()"><option value="">Все подборки</option><option v-for="item in fields" :key="item.id" :value="item.id">{{ item.title || item.name }}</option></select></label>
                        <fieldset><legend>Цена за единицу</legend><div class="catalog-price-range"><input v-model="form.price_min" type="number" min="0" step="0.01" placeholder="От" aria-label="Минимальная цена" @change="visit()"><span>—</span><input v-model="form.price_max" type="number" min="0" step="0.01" placeholder="До" aria-label="Максимальная цена" @change="visit()"></div><small>В валюте цены товара, за его единицу учёта. Валюта и единица указаны у товара.</small></fieldset>
                        <p class="catalog-filters__note"><v-icon icon="mdi-lightbulb-outline" size="16" />Нужны особые условия? Предложите свою цену через кнопку «Торг» у товара.</p>
                    </aside>
                    <main class="catalog-results" :aria-busy="loading">
                        <div class="catalog-results__toolbar"><span class="catalog-results__total" aria-live="polite">{{ loading ? 'Обновляем каталог…' : totalLabel }}</span><label class="catalog-sort"><span>Сортировка</span><select v-model="form.sort" @change="visit()"><option value="name">Название: А–Я</option><option value="name_desc">Название: Я–А</option><option value="newest">Сначала новые</option><option value="price_asc">Сначала дешевле</option><option value="price_desc">Сначала дороже</option></select></label><label class="catalog-page-size"><span>На странице</span><select v-model.number="form.per_page" @change="visit()"><option v-for="amount in perPageOptions" :key="amount" :value="amount">{{ amount }}</option></select></label></div>
                        <div v-if="requestError" role="alert" class="catalog-error">{{ requestError }}<button type="button" @click="visit(currentPage)">Повторить</button></div>
                        <div class="catalog-results__body" :class="{ 'is-loading': loading }">
                            <div v-if="productList.length && form.view === 'cards'" class="catalog-grid"><PublicCatalogGoodCard v-for="good in productList" :key="good.id" :good="good" @inquiry="openInquiry" @notice="showNotice" /></div>
                            <PublicCatalogTree v-else-if="productList.length && form.view === 'tree'" :nodes="catalogTree" :goods="productList" :selected-id="form.node_id" @select="selectNode" @inquiry="openInquiry" @notice="showNotice" />
                            <PublicCatalogPriceList v-else-if="productList.length && form.view === 'pricelist'" :goods="productList" :nodes="catalogTree" :sort="form.sort" :from="resultFrom" @sort="setSort" @inquiry="openInquiry" @notice="showNotice" />
                            <div v-else class="catalog-empty"><div><v-icon icon="mdi-magnify" size="30" /></div><h2>Ничего не нашлось</h2><p>Попробуйте изменить запрос или убрать часть фильтров.</p><button type="button" @click="resetFilters">Показать все товары</button></div>
                        </div>
                        <div v-if="total" class="catalog-pagination"><span>Показано {{ resultFrom }}–{{ resultTo }} из {{ total.toLocaleString('ru-RU') }}</span><nav aria-label="Страницы каталога"><button type="button" :disabled="currentPage <= 1 || loading" aria-label="Предыдущая страница" @click="visit(currentPage - 1)"><v-icon icon="mdi-chevron-left" size="18" /></button><template v-for="(number, index) in pages" :key="index"><span v-if="number === '…'">…</span><button v-else type="button" :aria-current="number === currentPage ? 'page' : undefined" :aria-label="`Страница ${number}`" :class="{ 'is-active': number === currentPage }" :disabled="loading" @click="visit(number)">{{ number }}</button></template><button type="button" :disabled="currentPage >= lastPage || loading" aria-label="Следующая страница" @click="visit(currentPage + 1)"><v-icon icon="mdi-chevron-right" size="18" /></button></nav></div>
                    </main>
                </div>
            </section>
            <div class="catalog-assurance"><span><v-icon icon="mdi-package-variant-closed-check" size="18" />Заказ любого объёма обсудим индивидуально</span><span><v-icon icon="mdi-truck-outline" size="18" />Условия доставки — при подтверждении заказа</span><span><v-icon icon="mdi-handshake-outline" size="18" />Разовые и регулярные поставки</span></div>
        </div>
        <GoodInquiryDialog v-if="inquiry" :key="inquiry.good.id" v-model="inquiryOpen" :good="inquiry.good" :kind="inquiry.kind" :quantity="inquiry.quantity" :purchase="inquiry.purchase" />
        <v-snackbar v-model="noticeOpen" :color="noticeSuccess ? 'success' : 'error'" :timeout="4500">{{ notice }}</v-snackbar>
    </div>
</template>

<style scoped>
.catalog-page { min-height: calc(100vh - 64px); background: #f6f5f1; color: #34372f; padding: 10px clamp(14px, 2vw, 32px) 24px; }
.catalog-page__inner { max-width: 1640px; margin: 0 auto; }
.catalog-breadcrumbs { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 5px; color: #929389; font-size: 10px; line-height: 16px; }
.catalog-breadcrumbs a, .catalog-breadcrumbs button { color: #84877b; text-decoration: none; }
.catalog-breadcrumbs > span, .catalog-breadcrumbs button { display: inline-flex; align-items: center; gap: 5px; }
.catalog-breadcrumbs [aria-current] { color: #4c5145; }
.catalog-hero { margin-bottom: 10px; }
.catalog-hero h1 { display: flex; align-items: center; gap: 10px; margin: 0; color: #30362b; font-size: clamp(22px, 2.1vw, 28px); font-weight: 750; line-height: 1.2; letter-spacing: -.025em; overflow-wrap: anywhere; }
.catalog-hero p { max-width: 900px; margin: 5px 0 0; color: #7b8072; font-size: 12px; line-height: 1.45; }
.catalog-workspace { padding: 12px; border: 1px solid #e7e7df; border-radius: 10px; background: #fffefa; }
.catalog-searchbar { display: flex; gap: 8px; }
.catalog-search { display: flex; flex: 1; min-width: 0; align-items: center; gap: 7px; padding: 3px 4px 3px 10px; border: 1px solid #dfe1d6; border-radius: 8px; background: #fff; color: #989d8e; }
.catalog-search:focus-within { border-color: #a1aa90; box-shadow: 0 0 0 2px #65754b12; }
.catalog-search input { width: 100%; min-width: 0; font-size: 13px; color: #30392b; outline: none; }
.catalog-search input::placeholder { color: #a0a394; }
.catalog-search button { padding: 5px 12px; border-radius: 4px; background: #657350; color: #fff; font-size: 12px; font-weight: 650; }
.catalog-filter-toggle { display: flex; justify-content: center; align-items: center; gap: 6px; padding: 5px 10px; border: 1px solid #dfe1d6; border-radius: 8px; color: #626b56; background: #fff; font-size: 12px; font-weight: 600; }
.catalog-filter-toggle.is-active { border-color: #bac1ae; background: #eef1e6; }
.catalog-filter-toggle > span { display: grid; place-items: center; width: 17px; height: 17px; border-radius: 50%; background: #657350; color: white; font-size: 9px; }
.catalog-view-switch { display: flex; flex: none; gap: 2px; padding: 3px; border: 1px solid #e4e5dd; border-radius: 8px; background: #f2f3ed; }
.catalog-view-switch button { display: flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 4px; color: #78816d; font-size: 11px; }
.catalog-view-switch .is-active { background: #fff; color: #46553a; box-shadow: 0 1px 3px #0000000d; }
.catalog-categories { display: flex; gap: 5px; max-width: 100%; overflow-x: auto; padding: 7px 0 2px; scrollbar-width: thin; }
.catalog-categories button { display: flex; flex: none; align-items: center; gap: 6px; padding: 4px 7px; white-space: nowrap; border: 1px solid #e9e7df; border-radius: 6px; color: #656a5c; font-size: 11px; background: #faf9f5; }
.catalog-categories button:hover { color: #800000; border-color: #cdb9ac; }
.catalog-categories button span { color: #a4a698; font-size: 9px; }
.catalog-categories .catalog-categories__back { background: #f0f2e8; }
.catalog-active-filters { display: flex; flex-wrap: wrap; gap: 5px; padding-top: 7px; }
.catalog-active-filters button { display: inline-flex; align-items: center; gap: 6px; padding: 4px 7px; color: #686e5c; background: #eef0e6; border-radius: 4px; font-size: 10px; }
.catalog-active-filters .catalog-reset { background: transparent; color: #907b6d; text-decoration: underline; text-underline-offset: 3px; }
.catalog-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; padding-top: 10px; }
.catalog-layout--filters { grid-template-columns: 224px minmax(0, 1fr); }
.catalog-filters { min-width: 0; display: flex; flex-direction: column; gap: 16px; align-self: start; padding: 3px 12px 10px 0; border-right: 1px solid #eeeee5; }
.catalog-filters__heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.catalog-filters__heading strong { color: #4a5340; font-size: 12px; }
.catalog-filters__heading button { color: #9b9b8b; font-size: 10px; }
.catalog-filters label { display: grid; gap: 7px; }
.catalog-filters label > span, .catalog-filters legend { color: #626955; font-size: 11px; font-weight: 650; }
.catalog-filters select { width: 100%; min-width: 0; padding: 8px; background-color: white; border: 1px solid #dedfd4; border-radius: 5px; color: #686f5e; font-size: 11px; }
.catalog-filters fieldset { min-width: 0; border: 0; padding: 0; }
.catalog-filters legend { margin-bottom: 9px; }
.catalog-filters .catalog-radio { display: flex; align-items: center; gap: 7px; margin-bottom: 8px; }
.catalog-radio input { width: 13px; height: 13px; accent-color: #657350; }
.catalog-filters .catalog-radio > span { font-weight: 400; }
.catalog-price-range { display: flex; align-items: center; gap: 5px; }
.catalog-price-range input { width: 50%; min-width: 0; padding: 7px; background: white; border: 1px solid #dedfd4; border-radius: 5px; color: #555f49; font-size: 11px; }
.catalog-price-range > span { color: #b4b7a8; }
.catalog-filters small { display: block; margin-top: 7px; color: #a1a390; font-size: 9px; line-height: 1.5; }
.catalog-filters__note { display: flex; gap: 7px; margin: 0; padding: 10px 9px; border-radius: 6px; background: #f6f4e9; color: #969073; font-size: 10px; line-height: 1.6; }
.catalog-results { min-width: 0; }
.catalog-results__toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 7px; min-height: 28px; }
.catalog-results__total { margin-right: auto; color: #858b78; font-size: 11px; }
.catalog-sort, .catalog-page-size { display: flex; align-items: center; gap: 7px; }
.catalog-sort > span, .catalog-page-size > span { color: #a0a48e; font-size: 10px; }
.catalog-sort select, .catalog-page-size select { padding: 5px 23px 5px 7px; border: 1px solid #e4e5dc; border-radius: 5px; background-color: white; color: #70795f; font-size: 11px; }
.catalog-results__body { transition: opacity .12s; }
.catalog-results__body.is-loading { opacity: .55; }
.catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: 13px; align-items: stretch; }
.catalog-empty { display: grid; justify-items: center; gap: 10px; padding: 56px 20px; border: 1px dashed #dfe2d4; border-radius: 9px; background: #fafbf5; text-align: center; }
.catalog-empty > div { display: grid; place-items: center; width: 60px; height: 60px; margin-bottom: 4px; border-radius: 50%; background: #edf0e1; color: #929d7a; }
.catalog-empty h2 { font-size: 19px; color: #58624b; }
.catalog-empty p { color: #929985; font-size: 12px; }
.catalog-empty button { margin-top: 6px; padding: 9px 15px; border-radius: 6px; background: #657350; color: white; font-size: 12px; }
.catalog-pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px; margin-top: 22px; }
.catalog-pagination > span { color: #9a9e8a; font-size: 10px; }
.catalog-pagination nav { display: flex; align-items: center; gap: 5px; }
.catalog-pagination button { display: grid; place-items: center; min-width: 32px; height: 32px; padding: 0 6px; border: 1px solid #e2e5d8; border-radius: 6px; color: #767e66; background: white; font-size: 11px; }
.catalog-pagination button.is-active { border-color: #657350; background: #657350; color: white; }
.catalog-pagination button:disabled { opacity: .4; cursor: default; }
.catalog-pagination nav > span { color: #a2a791; }
.catalog-error { display: flex; gap: 15px; justify-content: space-between; padding: 10px; margin-bottom: 15px; background: #fff1ef; border-radius: 5px; color: #a34232; font-size: 12px; }
.catalog-error button { text-decoration: underline; }
.catalog-assurance { display: flex; flex-wrap: wrap; gap: 14px 28px; align-items: center; justify-content: center; padding-top: 23px; color: #a0a48f; font-size: 10px; }
.catalog-assurance span { display: inline-flex; align-items: center; gap: 7px; }
button:focus-visible, a:focus-visible, select:focus-visible, input:focus-visible { outline: 2px solid #657350; outline-offset: 3px; }
@media (max-width: 1100px) {
    .catalog-layout--filters { grid-template-columns: 205px minmax(0, 1fr); gap: 12px; }
    .catalog-view-switch button { padding-inline: 7px; }
    .catalog-sort > span { display: none; }
    .catalog-grid { grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); }
}
@media (max-width: 800px) {
    .catalog-page { padding: 8px 10px 22px; }
    .catalog-breadcrumbs { margin-bottom: 4px; }
    .catalog-hero { margin-bottom: 8px; }
    .catalog-workspace { padding: 9px; border-radius: 8px; }
    .catalog-searchbar { flex-wrap: wrap; gap: 6px; }
    .catalog-search { flex: 1 1 0; }
    .catalog-search input { font-size: 12px; }
    .catalog-search button { padding-inline: 8px; font-size: 11px; }
    .catalog-filter-toggle { flex: none; gap: 4px; padding-inline: 7px; font-size: 11px; }
    .catalog-filter-toggle > .v-icon:last-child { display: none; }
    .catalog-view-switch { flex: 1 1 100%; min-width: 0; }
    .catalog-view-switch button { flex: 1; min-width: 0; justify-content: center; padding-inline: 6px; font-size: 11px; }
    .catalog-layout--filters { grid-template-columns: minmax(0, 1fr); }
    .catalog-filters { display: grid; grid-template-columns: 1fr 1fr; align-items: start; gap: 12px; padding: 10px; border: 1px solid #e8e9de; border-radius: 6px; background: #f9faf4; }
    .catalog-filters__heading, .catalog-category-picker, .catalog-filters__note { grid-column: 1 / -1; }
    .catalog-grid { grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); }
}
@media (max-width: 520px) {
    .catalog-hero h1 { font-size: 22px; }
    .catalog-results__toolbar { gap: 6px; }
    .catalog-results__total { flex-basis: 100%; }
    .catalog-page-size { margin-left: auto; }
    .catalog-grid { grid-template-columns: minmax(0, 1fr); }
    .catalog-pagination { justify-content: center; }
    .catalog-pagination > span { flex-basis: 100%; text-align: center; }
    .catalog-assurance { justify-content: flex-start; }
}
</style>
