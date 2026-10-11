<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useOrderCart } from '@/Composables/useOrderCart'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { catalogImage, catalogMoney, catalogStatus, publicCatalogOffer } from '@/utils/publicCatalog'
import { unitLabel } from '@/utils/goodMeasurement'
import { buildPriceListExport, priceListCanOrder, priceListDefaultColumns, priceListPack, priceListQuantity, priceListScope, priceListTotal, priceListUnit, readPriceListColumns, writePriceListColumns } from '@/utils/catalogPriceList'
import GoodStockAlertButton from '@/Components/Goods/GoodStockAlertButton.vue'
import CatalogNodeAvatar from '@/Components/Catalog/CatalogNodeAvatar.vue'

const props = defineProps({ goods: { type: Array, default: () => [] }, nodes: { type: Array, default: () => [] }, sort: { type: String, default: 'name' }, from: { type: Number, default: 1 } })
const emit = defineEmits(['sort', 'inquiry', 'notice'])
const { addGood, cartError } = useOrderCart()
const { goodPublicUrl } = usePublicGoodUrl()
const selected = ref(new Set())
const quantities = ref({})
const errors = ref({})
const failedImages = ref(new Set())
const columns = ref({ ...priceListDefaultColumns })
const copyFallback = ref('')
const copyField = ref(null)
const tableScroll = ref(null)
const copied = ref(false)
const columnNames = { country: 'Страна', pack: 'Фасовка', availability: 'Наличие' }

onMounted(() => { columns.value = readPriceListColumns() })
watch(columns, value => writePriceListColumns(value), { deep: true })
watch([selected, quantities], () => {
    copied.value = false
    copyFallback.value = ''
}, { deep: true, flush: 'sync' })
watch(() => [props.goods, props.from, props.sort], () => {
    selected.value = new Set()
    quantities.value = Object.fromEntries(props.goods.map(good => [String(good.id), '1']))
    errors.value = {}
    failedImages.value = new Set()
    copyFallback.value = ''
    copied.value = false
}, { immediate: true })

const rows = computed(() => {
    const sections = new Map(props.nodes.map(node => [String(node.id), node]))
    return props.goods.map((good, index) => ({ good, id: String(good.id), number: props.from + index, offer: publicCatalogOffer(good),
        status: catalogStatus(good), image: catalogImage(good), section: sections.get(String(good.catalog_node_id))?.name, sectionNode: sections.get(String(good.catalog_node_id)), unit: priceListUnit(good), canOrder: priceListCanOrder(good) }))
})
const selectedGoods = computed(() => props.goods.filter(good => selected.value.has(String(good.id))))
const selectedOrderable = computed(() => selectedGoods.value.filter(priceListCanOrder))
const allSelected = computed(() => props.goods.length > 0 && props.goods.every(good => selected.value.has(String(good.id))))
const exportGoods = computed(() => priceListScope(props.goods, selected.value))
const exportScope = computed(() => selected.value.size ? `выбранные (${exportGoods.value.length})` : `страницу (${props.goods.length})`)
const selectedTotals = computed(() => {
    const amounts = new Map()
    for (const good of selectedGoods.value) {
        const total = priceListTotal(good, quantities.value[String(good.id)])
        if (total === null) continue
        const currency = publicCatalogOffer(good).currency_code
        amounts.set(currency, (amounts.get(currency) || 0) + total)
    }
    return [...amounts].map(([currency, amount]) => money(amount, currency)).join(' + ')
})
const uncalculatedSelected = computed(() => selectedGoods.value.filter(good => priceListTotal(good, quantities.value[String(good.id)]) === null).length)

function money(value, currency) {
    return `${value.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency === 'RUB' ? '₽' : currency}`
}
function toggleSelection(id, checked) {
    const next = new Set(selected.value)
    if (checked) next.add(String(id))
    else next.delete(String(id))
    selected.value = next
    copied.value = false
}
function selectAll(checked) {
    selected.value = new Set(checked ? props.goods.map(good => String(good.id)) : [])
    copied.value = false
}
function quantityChanged(id, value) {
    quantities.value[id] = value
    delete errors.value[id]
    copied.value = false
}
function validate(good) {
    const id = String(good.id)
    const amount = priceListQuantity(quantities.value[id])
    if (amount === null) errors.value[id] = 'От 0,001 до 9999'
    else delete errors.value[id]
    return amount
}
function addOne(good) {
    if (!priceListCanOrder(good)) return
    const amount = validate(good)
    if (amount === null) return
    const success = addGood(good, amount, publicCatalogOffer(good))
    emit('notice', { success, message: success ? `${good.name}: ${amount.toLocaleString('ru-RU')} ${unitLabel(publicCatalogOffer(good).measurement)} добавлено в корзину` : cartError.value })
}
function addSelected() {
    const goods = selectedOrderable.value
    if (!goods.length) return
    if (goods.map(validate).some(amount => amount === null)) {
        emit('notice', { success: false, message: 'Проверьте количество в выделенных строках: от 0,001 до 9999.' })
        return
    }
    let added = 0
    const failures = []
    const remaining = new Set(selected.value)
    for (const good of goods) {
        if (addGood(good, priceListQuantity(quantities.value[String(good.id)]), publicCatalogOffer(good))) {
            added++
            remaining.delete(String(good.id))
        } else failures.push(`${good.name}: ${cartError.value}`)
    }
    selected.value = remaining
    const skipped = selectedGoods.value.length - failures.length
    const message = [`Добавлено в корзину: ${added} поз.`, skipped ? `Для ${skipped} поз. условия заказа нужно уточнить.` : '', ...failures].filter(Boolean).join(' ')
    emit('notice', { success: failures.length === 0 && added > 0, message })
}
function inquire(good, kind) {
    if (kind !== 'email' && !priceListCanOrder(good)) return
    const amount = kind === 'email' ? priceListQuantity(quantities.value[String(good.id)]) : validate(good)
    if (kind !== 'email' && amount === null) return
    emit('inquiry', { good, quantity: amount ?? 1, purchase: publicCatalogOffer(good), kind })
}
function requestSort(field) {
    emit('sort', field === 'name' ? (props.sort === 'name' ? 'name_desc' : 'name') : (props.sort === 'price_asc' ? 'price_desc' : 'price_asc'))
}
function ariaSort(field) {
    if (field === 'name') return props.sort === 'name' ? 'ascending' : props.sort === 'name_desc' ? 'descending' : 'none'
    return props.sort === 'price_asc' ? 'ascending' : props.sort === 'price_desc' ? 'descending' : 'none'
}
function sortMark(field) {
    const direction = ariaSort(field)
    return direction === 'ascending' ? '↑' : direction === 'descending' ? '↓' : '↕'
}
function moveQuantity(event) {
    if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return
    const inputs = [...(tableScroll.value?.querySelectorAll('[data-price-quantity]') || [])]
    const current = inputs.indexOf(event.target)
    if (current < 0) return
    event.preventDefault()
    const direction = event.key === 'ArrowUp' || (event.key === 'Enter' && event.shiftKey) ? -1 : 1
    const target = inputs[Math.min(inputs.length - 1, Math.max(0, current + direction))]
    target?.focus()
    target?.select()
}
function exportText(format) {
    return buildPriceListExport(exportGoods.value, quantities.value, { format, nodes: props.nodes, from: props.from, pageGoods: props.goods })
}
async function copyRows() {
    const text = exportText('tsv')
    const count = exportGoods.value.length
    try {
        if (!globalThis.navigator?.clipboard?.writeText) throw new Error('clipboard unavailable')
        await navigator.clipboard.writeText(text)
        copied.value = text === exportText('tsv')
        copyFallback.value = ''
        emit('notice', { success: true, message: `Скопировано ${count} строк. Вставьте в Excel или другую таблицу.` })
    } catch {
        if (text !== exportText('tsv')) return
        copyFallback.value = text
        await nextTick()
        copyField.value?.focus()
        copyField.value?.select()
    }
}
function downloadRows() {
    const blob = new Blob([exportText('csv')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `pischeprom-price-${selected.value.size ? 'selected' : `page-${props.from}`}.csv`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
}
</script>

<template>
    <section class="price-list catalog-price-list" aria-label="Прайс-лист товаров">
        <div class="price-list__toolbar">
            <div class="price-list__heading"><span class="price-list__sheet-icon" aria-hidden="true">▦</span><strong>Прайс-лист</strong></div>
            <div class="price-list__tools">
                <button type="button" :disabled="!goods.length" @click="copyRows"><v-icon :icon="copied ? 'mdi-check' : 'mdi-content-copy'" size="14" />{{ copied ? 'Скопировано' : 'Копировать' }} {{ exportScope }}</button>
                <button type="button" :disabled="!goods.length" @click="downloadRows"><v-icon icon="mdi-download" size="15" /> CSV · {{ exportScope }}</button>
                <details class="price-list__columns"><summary><v-icon icon="mdi-table-column" size="15" /> Столбцы</summary><div><label v-for="(label, key) in columnNames" :key="key"><input v-model="columns[key]" type="checkbox">{{ label }}</label></div></details>
            </div>
        </div>
        <div class="price-list__selection">
            <span role="status">Выбрано <b>{{ selected.size }}</b> из {{ goods.length }} на странице</span>
            <button v-if="selected.size" type="button" class="price-list__clear" @click="selectAll(false)">Снять выбор</button>
            <span v-if="selectedTotals" class="price-list__selection-sum">Сумма: <strong>{{ selectedTotals }}</strong><small v-if="uncalculatedSelected"> + {{ uncalculatedSelected }} поз. требуют уточнения</small></span>
            <button type="button" class="price-list__bulk" :disabled="!selectedOrderable.length" @click="addSelected"><v-icon icon="mdi-cart-plus" size="15" />В корзину · {{ selectedOrderable.length }} поз.</button>
        </div>
        <div v-if="copyFallback" class="price-list__copy-fallback"><label for="price-list-copy">Нажмите Ctrl+C (⌘C на Mac), затем вставьте строки в Excel.</label><textarea id="price-list-copy" ref="copyField" :value="copyFallback" readonly rows="3" /><button type="button" @click="copyFallback = ''">Закрыть</button></div>
        <div ref="tableScroll" class="price-list__scroll" tabindex="0" role="region" aria-label="Прайс-лист текущей страницы, прокрутка по горизонтали и вертикали">
            <table>
                <caption class="price-list__sr-only">Товары текущей страницы. Сортировка по названию и цене применяется ко всему каталогу с учётом фильтров. Копирование и CSV включают выбранные строки или текущую страницу.</caption>
                <thead><tr>
                    <th scope="col" class="price-list__check"><input type="checkbox" :checked="allSelected" :indeterminate.prop="selected.size > 0 && !allSelected" :disabled="!goods.length" aria-label="Выбрать все товары текущей страницы" @change="selectAll($event.target.checked)"></th>
                    <th scope="col" class="price-list__number">№</th>
                    <th scope="col" class="price-list__product" :aria-sort="ariaSort('name')"><button type="button" :aria-label="`Наименование: сортировать ${sort === 'name' ? 'по убыванию' : 'по возрастанию'}`" @click="requestSort('name')">Наименование <span aria-hidden="true">{{ sortMark('name') }}</span></button></th>
                    <th v-if="columns.country" scope="col">Страна</th>
                    <th v-if="columns.pack" scope="col">Фасовка</th>
                    <th v-if="columns.availability" scope="col">Наличие</th>
                    <th scope="col" class="price-list__numeric" :aria-sort="ariaSort('price')"><button type="button" :aria-label="`Цена: сортировать ${sort === 'price_asc' ? 'по убыванию' : 'по возрастанию'}`" @click="requestSort('price')">Цена / ед. <span aria-hidden="true">{{ sortMark('price') }}</span></button></th>
                    <th scope="col">Количество</th>
                    <th scope="col" class="price-list__numeric">Сумма</th>
                    <th scope="col">Заказ</th>
                </tr></thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" :class="{ 'price-list__row--selected': selected.has(row.id) }">
                        <td class="price-list__check"><input type="checkbox" :checked="selected.has(row.id)" :aria-label="`Выбрать ${row.good.name}`" @change="toggleSelection(row.id, $event.target.checked)"></td>
                        <td class="price-list__number">{{ row.number }}</td>
                        <th scope="row" class="price-list__product"><Link :href="row.good.public_url || goodPublicUrl(row.good)" class="price-list__product-link"><span class="price-list__avatar"><img v-if="row.image && !failedImages.has(row.id)" :src="row.image" alt="" width="32" height="32" loading="lazy" @error="failedImages.add(row.id)"><v-icon v-else icon="mdi-package-variant-closed" size="18" /></span><span class="price-list__product-text"><span class="price-list__name">{{ row.good.name }}</span><small v-if="row.section"><CatalogNodeAvatar :node="row.sectionNode" :size="16" />{{ row.section }}</small></span></Link></th>
                        <td v-if="columns.country" class="price-list__country">{{ row.good.country?.name || '—' }}</td>
                        <td v-if="columns.pack" class="price-list__pack">{{ priceListPack(row.good) }}</td>
                        <td v-if="columns.availability" class="price-list__availability"><span :class="{ 'price-list__in-stock': row.status.value === 'in_stock' }"><i />{{ row.status.label }}</span><GoodStockAlertButton v-if="row.status.value !== 'in_stock'" :good="row.good" compact label="Оповестить" /></td>
                        <td class="price-list__numeric price-list__price"><strong :class="{ 'price-list__on-request': !row.offer.price }">{{ catalogMoney(row.good) }}</strong><small v-if="row.offer.price">{{ row.unit ? `/ ${row.unit}` : 'Единицу уточним' }}<span v-if="row.offer.includes_vat"> · с НДС</span></small></td>
                        <td class="price-list__quantity-cell"><div v-if="row.canOrder" class="price-list__quantity"><input data-price-quantity :value="quantities[row.id]" type="text" inputmode="decimal" maxlength="12" :aria-label="`Количество ${row.good.name}, ${unitLabel(row.offer.measurement)}`" :aria-invalid="Boolean(errors[row.id])" :aria-describedby="errors[row.id] ? `price-quantity-error-${row.id}` : undefined" @input="quantityChanged(row.id, $event.target.value)" @keydown="moveQuantity"><span>{{ unitLabel(row.offer.measurement) }}</span></div><span v-else class="price-list__unknown-unit">{{ row.offer.measurement?.measure_id ? 'Условия уточним' : 'Уточните единицу' }}</span><small v-if="errors[row.id]" :id="`price-quantity-error-${row.id}`" class="price-list__error" role="alert">{{ errors[row.id] }}</small></td>
                        <td class="price-list__numeric price-list__sum">{{ priceListTotal(row.good, quantities[row.id]) === null ? '—' : money(priceListTotal(row.good, quantities[row.id]), row.offer.currency_code) }}</td>
                        <td class="price-list__actions"><div class="price-list__action-line"><button v-if="row.canOrder" type="button" class="price-list__cart" :aria-label="`Добавить ${row.good.name} в корзину`" :title="`Добавить ${row.good.name} в корзину`" @click="addOne(row.good)"><v-icon icon="mdi-cart-plus" size="17" /></button><button v-if="row.canOrder" type="button" @click="inquire(row.good, 'order')">Заказать</button><Link v-else :href="row.good.public_url || goodPublicUrl(row.good)">Подробнее</Link><button v-if="row.canOrder" type="button" @click="inquire(row.good, 'bargain')">Торг</button><button type="button" :aria-label="`Задать вопрос о товаре ${row.good.name}`" @click="inquire(row.good, 'email')">Вопрос</button></div><GoodStockAlertButton v-if="!columns.availability && row.status.value !== 'in_stock'" :good="row.good" compact label="Оповестить" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="price-list__footer"><span><kbd>↑</kbd><kbd>↓</kbd> или <kbd>Enter</kbd> — перейти к количеству в соседней строке</span><span>Выбор и выгрузка — в пределах текущей страницы</span></div>
    </section>
</template>

<style scoped>
.price-list { min-width: 0; max-width: 100%; border: 1px solid #d9dbd1; border-radius: 9px; background: #fff; color: #343c32; font-variant-numeric: tabular-nums; }
.price-list__toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; padding: 7px 9px; border-radius: 9px 9px 0 0; background: #f6f5ef; }
.price-list__heading { display: flex; align-items: center; gap: 6px; margin-right: auto; }
.price-list__sheet-icon { color: #800000; font-size: 21px; line-height: 1; }
.price-list__heading strong { display: block; color: #31352b; font-size: 14px; }
.price-list__tools { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.price-list__tools > button, .price-list__columns summary { display: inline-flex; align-items: center; justify-content: center; gap: 5px; min-height: 28px; padding: 4px 7px; border: 1px solid #dcded5; border-radius: 4px; background: #fff; color: #515c48; font-size: 10px; cursor: pointer; }
.price-list__tools > button:hover, .price-list__columns summary:hover { border-color: #9d9f8d; }
.price-list__columns { position: relative; }
.price-list__columns summary { list-style: none; }
.price-list__columns summary::-webkit-details-marker { display: none; }
.price-list__columns > div { position: absolute; right: 0; top: calc(100% + 5px); z-index: 10; display: grid; gap: 10px; min-width: 155px; padding: 13px; border: 1px solid #d9dbd1; border-radius: 6px; background: white; box-shadow: 0 6px 20px #27332216; }
.price-list__columns label { display: flex; align-items: center; gap: 8px; font-size: 12px; cursor: pointer; }
.price-list__selection { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; min-height: 36px; padding: 4px 9px; border-top: 1px solid #e3e4da; border-bottom: 1px solid #d9dbd1; font-size: 11px; color: #7b8071; }
.price-list__selection b { color: #303c2e; }
.price-list__clear { color: #80564e; font-size: 10px; text-decoration: underline; text-underline-offset: 2px; }
.price-list__selection-sum { margin-left: auto; color: #77776a; }
.price-list__selection-sum strong { color: #384833; }
.price-list__selection-sum small { font-size: 10px; }
.price-list__bulk { display: inline-flex; align-items: center; gap: 6px; margin-left: auto; padding: 6px 9px; border-radius: 4px; background: #800000; color: #fff; font-size: 11px; font-weight: 650; }
.price-list__bulk:hover:not(:disabled) { background: #610000; }
.price-list button:disabled { opacity: .45; cursor: default; }
.price-list__scroll { max-width: 100%; max-height: min(72vh, 800px); min-height: 100px; overflow: auto; border-radius: 0 0 4px 4px; scrollbar-color: #b7bbab #f3f4ef; }
table { width: 100%; min-width: 960px; border-collapse: separate; border-spacing: 0; text-align: left; font-size: 11px; line-height: 1.35; }
th, td { border-right: 1px solid #e3e6dc; border-bottom: 1px solid #e3e6dc; padding: 6px 9px; vertical-align: middle; }
th:last-child, td:last-child { border-right: 0; }
thead th { position: sticky; top: 0; z-index: 3; height: 30px; background: #ebeee5; color: #5c6752; font-size: 10px; font-weight: 700; white-space: nowrap; }
thead th button { display: flex; align-items: center; gap: 20px; width: 100%; color: inherit; text-align: inherit; font-size: inherit; font-weight: inherit; }
thead th button span { margin-left: auto; color: #800000; font-size: 14px; }
thead th[aria-sort="ascending"], thead th[aria-sort="descending"] { background: #e2e7d9; color: #384932; }
tbody tr { --row-background: #fff; background: var(--row-background); }
tbody tr:nth-child(even) { --row-background: #fafbf7; }
tbody tr:hover, tbody tr:focus-within { --row-background: #fff9e8; }
tbody tr.price-list__row--selected { --row-background: #f1f5e9; }
.price-list__check { width: 33px; min-width: 33px; padding: 5px 9px; text-align: center; }
.price-list input[type="checkbox"] { display: block; width: 14px; height: 14px; margin: 0; accent-color: #697749; cursor: pointer; }
.price-list__number { width: 35px; min-width: 35px; color: #919789; font-size: 10px; text-align: center; }
.price-list__product { position: sticky; left: 0; z-index: 1; width: 29%; min-width: 265px; max-width: 410px; border-right-color: #cfd5c5; box-shadow: 2px 0 0 #384a1d05; }
tbody .price-list__product { background: var(--row-background); font-weight: 400; }
thead .price-list__product { z-index: 4; }
.price-list__product-link { display: flex; align-items: center; gap: 9px; min-height: 34px; color: #303d2e; text-decoration: none; }
.price-list__product-link:hover .price-list__name { color: #800000; text-decoration: underline; }
.price-list__avatar { display: grid; place-items: center; flex: 0 0 32px; width: 32px; height: 32px; overflow: hidden; border: 1px solid #e2e4da; border-radius: 4px; background: white; color: #b3b7a8; }
.price-list__avatar img { width: 32px; height: 32px; object-fit: contain; }
.price-list__product-text { min-width: 0; }
.price-list__name { display: block; font-size: 11px; font-weight: 600; overflow-wrap: anywhere; }
.price-list__product-text small { display: flex; align-items: center; gap: 4px; padding-top: 2px; color: #929787; font-size: 9px; font-weight: 400; }
.price-list__country { min-width: 85px; color: #727b69; }
.price-list__pack { color: #727b69; white-space: nowrap; font-size: 10px; }
.price-list__availability { min-width: 118px; }
.price-list__availability > span { display: inline-flex; align-items: center; gap: 4px; color: #957b4c; font-size: 9px; white-space: nowrap; }
.price-list__availability i { width: 4px; height: 4px; border-radius: 50%; background: currentColor; }
.price-list__availability > .price-list__in-stock { color: #4d7f46; }
.price-list__numeric { text-align: right; white-space: nowrap; }
.price-list__price { min-width: 110px; }
.price-list__price strong { font-size: 12px; font-weight: 700; }
.price-list__price .price-list__on-request { font-size: 10px; font-weight: 400; color: #8c7962; }
.price-list__price small { display: block; padding-top: 2px; color: #8d9482; font-size: 9px; }
.price-list__quantity-cell { min-width: 112px; }
.price-list__quantity { display: flex; align-items: center; gap: 4px; }
.price-list__quantity input { width: 67px; min-width: 0; padding: 5px 6px; border: 1px solid #d3d9c8; border-radius: 3px; background: white; color: #36472c; font-size: 12px; font-weight: 650; text-align: right; }
.price-list__quantity input[aria-invalid="true"] { border-color: #a54539; }
.price-list__quantity > span { color: #8b9381; font-size: 9px; white-space: nowrap; }
.price-list__unknown-unit { display: block; max-width: 100px; color: #a08a6f; font-size: 10px; }
.price-list__error { display: block; max-width: 105px; margin-top: 3px; color: #a1392e; font-size: 9px; }
.price-list__sum { min-width: 93px; color: #45533d; font-size: 11px; font-weight: 650; }
.price-list__action-line { display: flex; align-items: center; gap: 9px; white-space: nowrap; }
.price-list__action-line > button:not(.price-list__cart), .price-list__action-line > a { color: #8a7166; font-size: 9px; text-decoration: underline; text-underline-offset: 3px; }
.price-list__action-line > button:hover, .price-list__action-line > a:hover { color: #800000; }
.price-list__cart { display: grid; place-items: center; flex: 0 0 27px; width: 27px; height: 27px; border: 1px solid #e3d9cc; border-radius: 4px; background: #faf2e8; color: #800000; }
.price-list__cart:hover { border-color: #800000; background: #fff; }
.price-list__footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 7px 15px; padding: 9px 13px; color: #898e7e; font-size: 10px; }
.price-list__footer kbd { display: inline-block; min-width: 15px; margin-right: 2px; padding: 1px 3px; border: 1px solid #e0e3d8; border-radius: 3px; color: #757c69; font-size: 9px; text-align: center; }
.price-list__copy-fallback { display: grid; gap: 7px; padding: 12px; background: #fff9e8; font-size: 11px; }
.price-list__copy-fallback textarea { width: 100%; padding: 8px; border: 1px solid #ddd7c5; background: white; font-family: monospace; font-size: 11px; }
.price-list__copy-fallback button { justify-self: start; text-decoration: underline; }
.price-list :deep(.good-stock-alert-action .v-btn) { min-height: 18px; height: 18px; padding: 0; margin-top: 2px; background: transparent !important; color: #8b753c !important; font-size: 9px; font-weight: 500; text-align: left; justify-content: flex-start; box-shadow: none; }
.price-list :deep(.good-stock-alert-action .v-btn__prepend) { margin-right: 3px; }
.price-list__sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
button:focus-visible, input:focus-visible, a:focus-visible, summary:focus-visible, .price-list__scroll:focus-visible { outline: 2px solid #800000; outline-offset: 2px; }
@media (max-width: 600px) {
    .price-list__toolbar { padding: 6px 8px; }
    .price-list__tools { width: 100%; gap: 5px; }
    .price-list__tools > button { flex: 1; padding-inline: 6px; font-size: 9px; }
    .price-list__columns summary { padding-inline: 6px; font-size: 9px; }
    .price-list__selection { padding-inline: 9px; gap: 7px; font-size: 10px; }
    .price-list__selection-sum { width: 100%; order: 1; }
    .price-list__product { width: 150px; min-width: 150px; max-width: 150px; }
    .price-list__product-link { gap: 6px; }
    .price-list__name { font-size: 10px; }
    .price-list__action-line { width: 100px; flex-wrap: wrap; gap: 6px; }
    .price-list__scroll { max-height: 65vh; }
    .price-list__footer { padding-inline: 9px; }
}
</style>
