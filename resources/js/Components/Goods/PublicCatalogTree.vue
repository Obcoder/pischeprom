<script setup>
import { computed, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePublicGoodUrl } from '@/Composables/usePublicGoodUrl'
import { catalogTreeRows, expandProductBranches, catalogMoney, catalogStatus, publicCatalogOffer } from '@/utils/publicCatalog'
import { unitLabel } from '@/utils/goodMeasurement'
import PublicCatalogGoodActions from '@/Components/Goods/PublicCatalogGoodActions.vue'
import GoodStockAlertButton from '@/Components/Goods/GoodStockAlertButton.vue'

const props = defineProps({ nodes: { type: Array, default: () => [] }, goods: { type: Array, default: () => [] }, selectedId: [String, Number] })
defineEmits(['select', 'inquiry', 'notice'])
const { goodPublicUrl } = usePublicGoodUrl()
const expanded = ref(new Set())
watch(() => [props.nodes, props.goods], () => { expanded.value = expandProductBranches(props.nodes, props.goods) }, { immediate: true })
const rows = computed(() => catalogTreeRows(props.nodes, props.goods, expanded.value))
function toggle(id) {
    const next = new Set(expanded.value)
    const key = String(id)
    if (next.has(key)) next.delete(key)
    else next.add(key)
    expanded.value = next
}
</script>

<template>
    <div class="catalog-tree">
        <div class="catalog-tree__tools">
            <span><v-icon icon="mdi-file-tree-outline" size="16" /> Категории и товары</span>
            <button type="button" @click="expanded = new Set(nodes.map(node => String(node.id)))">Развернуть всё</button>
            <button type="button" @click="expanded = new Set()">Свернуть всё</button>
        </div>
        <div class="catalog-tree__scroll" tabindex="0" role="region" aria-label="Таблица каталога, прокрутка по горизонтали">
            <table>
                <caption class="catalog-tree__caption">Товары текущей страницы по категориям. Чтобы увидеть все товары категории, выберите её название.</caption>
                <thead><tr><th scope="col">Наименование</th><th scope="col">Страна / фасовка</th><th scope="col">Наличие</th><th scope="col">Цена за единицу</th><th scope="col">Количество и заказ</th></tr></thead>
                <tbody>
                    <template v-for="row in rows" :key="row.key">
                        <tr v-if="row.type === 'node'" class="catalog-tree__branch" :class="{ 'catalog-tree__branch--active': String(selectedId) === String(row.node.id) }">
                            <td colspan="5">
                                <div class="catalog-tree__branch-content" :style="{ paddingLeft: `${Math.min(row.depth, 8) * 15}px` }">
                                    <button v-if="row.expandable" type="button" class="catalog-tree__toggle" :aria-expanded="expanded.has(String(row.node.id))" :aria-label="`${expanded.has(String(row.node.id)) ? 'Свернуть' : 'Развернуть'} ${row.node.name}`" @click="toggle(row.node.id)"><v-icon :icon="expanded.has(String(row.node.id)) ? 'mdi-minus-box-outline' : 'mdi-plus-box-outline'" size="15" /></button>
                                    <span v-else class="catalog-tree__toggle" />
                                    <v-icon :icon="expanded.has(String(row.node.id)) ? 'mdi-folder-open-outline' : 'mdi-folder-outline'" size="16" />
                                    <button type="button" class="catalog-tree__category" @click="$emit('select', row.node.id)">{{ row.node.name }}</button>
                                    <span class="catalog-tree__count">{{ row.node.goods_count || 0 }}</span>
                                    <button type="button" class="catalog-tree__show" @click="$emit('select', row.node.id)">Все товары <v-icon icon="mdi-arrow-right" size="12" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-else class="catalog-tree__good">
                            <td><Link :href="row.good.public_url || goodPublicUrl(row.good)" class="catalog-tree__name" :style="{ paddingLeft: `${Math.min(row.depth, 8) * 15 + 22}px` }">{{ row.good.name }}</Link></td>
                            <td class="catalog-tree__origin">{{ row.good.country?.name || '—' }}<small v-if="Number(row.good.denominator) > 0">{{ Number(row.good.denominator).toLocaleString('ru-RU') }} кг / уп.</small></td>
                            <td><span class="catalog-tree__status" :class="{ 'catalog-tree__status--stock': catalogStatus(row.good).value === 'in_stock' }">{{ catalogStatus(row.good).label }}</span><GoodStockAlertButton v-if="catalogStatus(row.good).value !== 'in_stock'" :good="row.good" compact label="Оповестить" /></td>
                            <td class="catalog-tree__price">{{ catalogMoney(row.good) }}<small v-if="publicCatalogOffer(row.good).price">/ {{ unitLabel(publicCatalogOffer(row.good).measurement) }}</small></td>
                            <td><PublicCatalogGoodActions :good="row.good" compact @inquiry="$emit('inquiry', $event)" @notice="$emit('notice', $event)" /></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p class="catalog-tree__hint">В таблице — товары текущей страницы. Название категории открывает все её товары. На небольшом экране таблицу можно прокрутить вправо.</p>
    </div>
</template>

<style scoped>
.catalog-tree { overflow: hidden; border: 1px solid #dcded5; border-radius: 8px; background: #fff; }
.catalog-tree__tools { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 9px 12px; border-bottom: 1px solid #dcded5; background: #f6f7f2; }
.catalog-tree__tools span { display: inline-flex; align-items: center; gap: 6px; margin-right: auto; color: #4a5445; font-size: 12px; font-weight: 700; }
.catalog-tree__tools button { color: #707765; font-size: 11px; text-decoration: underline; text-underline-offset: 3px; }
.catalog-tree__scroll { overflow-x: auto; }
table { width: 100%; min-width: 960px; border-collapse: collapse; text-align: left; font-size: 12px; }
th { padding: 8px 10px; background: #f1f2ec; color: #6f7665; border: 1px solid #e0e2d9; border-top: 0; font-size: 10px; font-weight: 700; white-space: nowrap; }
th:first-child { width: 40%; }
td { padding: 5px 10px; border: 1px solid #e6e7e1; }
th:first-child, td:first-child { border-left: 0; }
th:last-child, td:last-child { border-right: 0; }
.catalog-tree__branch { background: #f6f4e7; }
.catalog-tree__branch--active { background: #eee8cb; }
.catalog-tree__branch td { padding-block: 3px; }
.catalog-tree__branch-content { display: flex; gap: 6px; align-items: center; color: #77784c; min-height: 23px; }
.catalog-tree__toggle { display: inline-grid; place-items: center; flex: 0 0 18px; width: 18px; height: 22px; }
.catalog-tree__category { color: #444b36; text-align: left; font-size: 12px; font-weight: 700; }
.catalog-tree__category:hover { color: #800000; text-decoration: underline; }
.catalog-tree__count { color: #939582; font-size: 10px; }
.catalog-tree__show { margin-left: auto; color: #878870; font-size: 10px; }
.catalog-tree__good:hover { background: #fffdf3; }
.catalog-tree__name { display: block; min-width: 260px; max-width: 600px; color: #333e33; text-decoration: none; line-height: 1.35; }
.catalog-tree__name:hover { color: #800000; text-decoration: underline; }
.catalog-tree__origin { color: #707766; font-size: 11px; }
.catalog-tree__origin small, .catalog-tree__price small { display: block; color: #929887; font-size: 10px; font-weight: 400; }
.catalog-tree__status { color: #98844e; font-size: 10px; white-space: nowrap; }
.catalog-tree__status--stock { color: #4b8057; }
.catalog-tree__price { color: #3c4636; font-size: 12px; font-weight: 700; white-space: nowrap; }
.catalog-tree__hint { margin: 0; padding: 8px 12px; color: #898c7e; font-size: 10px; }
.catalog-tree__caption { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
.catalog-tree :deep(.good-stock-alert-action .v-btn) { min-height: 20px; height: 20px; margin-top: 2px; padding: 0; background: transparent !important; color: #8b753c !important; font-size: 9px; text-align: left; justify-content: flex-start; box-shadow: none; }
.catalog-tree :deep(.good-stock-alert-action .v-btn__prepend) { margin-right: 4px; }
button:focus-visible, a:focus-visible { outline: 2px solid #800000; outline-offset: 2px; }
</style>
