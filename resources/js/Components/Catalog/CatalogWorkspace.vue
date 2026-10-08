<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import CatalogToolbar from '@/Components/Dictionaries/CatalogToolbar.vue'
import CatalogSchemaDialog from './CatalogSchemaDialog.vue'
import CatalogNodeDialog from './CatalogNodeDialog.vue'
import CatalogAvatar from './CatalogAvatar.vue'
import CatalogGalleryDialog from './CatalogGalleryDialog.vue'
import CatalogGoodPricing from './CatalogGoodPricing.vue'
import { buildCatalogView } from './presentation.js'

const nodes = ref([])
const levels = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const publication = ref('all')
const domainId = ref(null)
const selections = ref({})
const selectedBranchId = ref(null)
const expanded = ref(new Set())
const schemaOpen = ref(false)
const editorOpen = ref(false)
const editorNode = ref(null)
const galleryOpen = ref(false)
const galleryNode = ref(null)
const editorContext = reactive({ parentId: null, levelId: null, entityType: 'custom' })
const page = ref(1)
const notice = ref('')
const noticeOpen = computed({ get: () => Boolean(notice.value), set: value => { if (!value) notice.value = '' } })
const nodeMap = computed(() => new Map(nodes.value.map(node => [node.id, node])))
const levelMap = computed(() => new Map(levels.value.map(level => [level.id, level])))
const domainLevels = computed(() => levels.value.filter(level => level.is_domain && level.display_mode === 'tabs'))
const view = computed(() => buildCatalogView(nodes.value, levels.value, {
    domainId: domainId.value, selections: selections.value, selectedBranchId: selectedBranchId.value,
    expanded: expanded.value, search: search.value, publication: publication.value,
}))
const deepest = candidates => candidates.filter(Boolean).sort((a, b) => pathTo(b).length - pathTo(a).length)[0]
const contextNode = computed(() => nodeMap.value.get(view.value.scopeNodeId))
const contextPath = computed(() => pathTo(contextNode.value))
const currentTitle = computed(() => contextNode.value?.name || (domainId.value === 'unassigned' ? 'Без домена' : 'Все объекты'))
const headers = [
    { key: 'name', title: 'Объект', minWidth: 220 },
    { key: 'level_name', title: 'Уровень', width: 140 },
    { key: 'path_label', title: 'Расположение', minWidth: 170 },
    { key: 'is_published', title: 'Публикация', width: 155 },
    { key: 'purchase_price', title: 'Закупка', width: 76, sortable: false },
    { key: 'sales_prices', title: 'Продажа', width: 225, sortable: false },
    { key: 'actions', title: '', sortable: false, width: 88 },
]
const tableItems = computed(() => view.value.items.map(node => {
    const hiddenAncestors = (node.ancestors || []).filter(ancestor => !nodeMap.value.get(ancestor.id)?.is_published)
    const visible = Boolean(node.is_published) && hiddenAncestors.length === 0
    return { ...node, level_name: levelMap.value.get(node.level_id)?.name || 'Без уровня', visible,
        hidden_sections_label: hiddenAncestors.map(ancestor => ancestor.name).join(' · '),
        status_label: !node.is_published ? 'Черновик' : visible ? 'На сайте' : 'Скрыт разделом' }
}))
const publicationOptions = [
    { title: 'Все статусы', value: 'all' }, { title: 'Опубликованные', value: 'published' },
    { title: 'Черновики', value: 'draft' }, { title: 'На витрине', value: 'featured' },
]
const iconFor = node => levelMap.value.get(node.level_id)?.is_domain ? 'mdi-earth' : ({ category: 'mdi-folder-outline', product: 'mdi-package-variant-closed', good: 'mdi-tag-outline' }[node.entity_type] || 'mdi-file-tree-outline')
const levelName = node => levelMap.value.get(node.level_id)?.name || 'Без уровня'
function pathTo(node) {
    const path = [], seen = new Set()
    while (node && !seen.has(node.id)) {
        path.unshift(node); seen.add(node.id); node = nodeMap.value.get(node.parent_id)
    }
    return path
}
function propertiesSummary(node) {
    return (levelMap.value.get(node.level_id)?.fields || []).filter(field => node.properties?.[field.key] !== null && node.properties?.[field.key] !== undefined && node.properties?.[field.key] !== '').slice(0, 2).map(field => {
        const value = node.properties[field.key]
        return `${field.label}: ${typeof value === 'boolean' ? (value ? 'Да' : 'Нет') : value}`
    }).join(' · ')
}
async function load() {
    if (loading.value) return
    loading.value = true; error.value = ''
    try {
        const { data } = await axios.get('/api/catalog')
        nodes.value = data.nodes; levels.value = data.levels
        const ids = new Set(data.nodes.map(node => node.id))
        if (typeof domainId.value === 'number' && !ids.has(domainId.value)) domainId.value = null
        if (!ids.has(selectedBranchId.value)) selectedBranchId.value = null
        const validLevels = new Set(data.levels.map(level => level.id))
        selections.value = Object.fromEntries(Object.entries(selections.value).filter(([levelId, id]) => validLevels.has(Number(levelId)) && ids.has(id)))
        const normalized = view.value
        domainId.value = normalized.domainId
        selections.value = normalized.selections
        selectedBranchId.value = normalized.selectedBranchId
    } catch (failure) { error.value = failure.response?.data?.message || 'Не удалось загрузить каталог. Повторите попытку.' }
    finally { loading.value = false }
}
function selectDomain(id) {
    domainId.value = id; selections.value = {}; selectedBranchId.value = null; page.value = 1
}
function selectTab(levelId, id) {
    const next = {}, index = view.value.tabRows.findIndex(row => row.levelId === levelId)
    for (const row of view.value.tabRows.slice(0, index)) if (view.value.selections[row.levelId]) next[row.levelId] = view.value.selections[row.levelId]
    if (id !== null) next[levelId] = id
    selections.value = next; selectedBranchId.value = null; page.value = 1
}
function selectBranch(node) { selectedBranchId.value = node?.id ?? null; page.value = 1 }
function toggle(node) {
    const next = new Set(expanded.value)
    next.has(node.id) ? next.delete(node.id) : next.add(node.id)
    expanded.value = next
}
function openEditor(node) {
    editorNode.value = node; editorOpen.value = true
}
function openGallery(node) {
    galleryNode.value = node; galleryOpen.value = true
}
function create({ parentId = contextNode.value?.id ?? null, levelId = null, entityType = 'custom' } = {}) {
    editorNode.value = null
    Object.assign(editorContext, { parentId, levelId, entityType })
    editorOpen.value = true
}
function createDomain() { create({ parentId: null, levelId: domainLevels.value[0]?.id ?? null }) }
function createForTab(row) {
    const previous = deepest([nodeMap.value.get(view.value.domainId), ...view.value.tabRows.slice(0, view.value.tabRows.indexOf(row)).map(item => nodeMap.value.get(view.value.selections[item.levelId]))])
    create({ parentId: previous?.id ?? null, levelId: row.levelId })
}
function createGood() {
    create({ levelId: levels.value.find(level => level.entity_type === 'good')?.id ?? null, entityType: 'good' })
}
async function saved(node) {
    if (node?.parent_id) expanded.value = new Set([...expanded.value, node.parent_id])
    await load(); notice.value = 'Запись сохранена'
}
async function deleted(id) {
    if (selectedBranchId.value === id) selectedBranchId.value = null
    await load(); notice.value = 'Запись удалена'
}
async function schemaChanged() {
    await load()
    // Display preferences may have moved a selected classifier into another pane.
    selectedBranchId.value = null; selections.value = {}
    if (typeof domainId.value === 'number' && !domainLevels.value.some(level => level.id === nodeMap.value.get(domainId.value)?.level_id)) domainId.value = null
}
function resetFilters() { search.value = ''; publication.value = 'all' }
watch([search, publication], () => { page.value = 1 })
onMounted(load)
</script>

<template>
    <section class="catalog-workspace" aria-label="Товароведение — классификация и объекты">
        <CatalogToolbar :count="tableItems.length" :total="view.counts.total">
            <v-text-field v-model="search" label="Поиск объектов и разделов" prepend-inner-icon="mdi-magnify" clearable hide-details class="catalog-toolbar__search" />
            <v-select v-model="publication" :items="publicationOptions" label="Публикация" hide-details />
            <template #actions>
                <v-btn prepend-icon="mdi-tune-variant" variant="text" @click="schemaOpen = true">Уровни и поля</v-btn>
                <v-btn icon="mdi-refresh" variant="text" :loading="loading" aria-label="Обновить каталог" @click="load" />
                <v-menu><template #activator="{ props }"><v-btn v-bind="props" prepend-icon="mdi-plus" append-icon="mdi-chevron-down">Создать</v-btn></template><v-list density="compact">
                    <v-list-item title="Товар" prepend-icon="mdi-tag-plus-outline" @click="createGood" />
                    <v-list-item title="Раздел или объект" prepend-icon="mdi-file-tree-outline" @click="create()" />
                    <v-list-item v-if="domainLevels.length" title="Домен" prepend-icon="mdi-earth" @click="createDomain" />
                    <v-divider /><v-list-item title="Уровень классификации" prepend-icon="mdi-tune-variant" @click="schemaOpen = true" />
                </v-list></v-menu>
            </template>
        </CatalogToolbar>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" closable @click:close="error = ''">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate color="#352345" height="2" />
        <div v-if="domainLevels.length" class="classification-row classification-row--domains">
            <span class="classification-row__label"><v-icon icon="mdi-earth" size="15" />Домены</span>
            <div class="classification-row__tabs" role="tablist" aria-label="Домены">
                <button v-for="domain in view.domainTabs" :key="domain.id ?? 'all'" type="button" role="tab" :aria-selected="domainId === domain.id" :class="['classification-tab', { 'is-selected': domainId === domain.id }]" @click="selectDomain(domain.id)">
                    {{ domain.name }}<span class="classification-tab__count">{{ domain.count }}</span>
                </button>
            </div>
            <v-btn v-if="nodeMap.has(domainId)" icon="mdi-pencil-outline" variant="text" size="x-small" aria-label="Изменить выбранный домен" @click="openEditor(nodeMap.get(domainId))" />
            <v-btn icon="mdi-plus" variant="text" size="x-small" aria-label="Добавить домен" @click="createDomain" />
        </div>
        <div v-for="row in view.tabRows" :key="row.levelId" class="classification-row">
            <span class="classification-row__label">{{ row.name }}</span>
            <div class="classification-row__tabs" role="tablist" :aria-label="row.name">
                <button type="button" role="tab" :aria-selected="!selections[row.levelId]" :class="['classification-tab', { 'is-selected': !selections[row.levelId] }]" @click="selectTab(row.levelId, null)">Все</button>
                <button v-for="item in row.items" :key="item.id" type="button" role="tab" :aria-selected="selections[row.levelId] === item.id" :title="pathTo(item).map(node => node.name).join(' / ')" :class="['classification-tab', { 'is-selected': selections[row.levelId] === item.id }]" @click="selectTab(row.levelId, item.id)">
                    <span v-if="!item.is_published" class="catalog-status-dot" title="Черновик" />{{ item.name }}<span class="classification-tab__count">{{ item.count }}</span>
                </button>
                <span v-if="!row.items.length" class="classification-row__empty">В выбранной ветке нет записей этого уровня</span>
            </div>
            <v-btn v-if="nodeMap.has(selections[row.levelId])" icon="mdi-pencil-outline" variant="text" size="x-small" :aria-label="`Изменить ${row.name}`" @click="openEditor(nodeMap.get(selections[row.levelId]))" />
            <v-btn icon="mdi-plus" variant="text" size="x-small" :aria-label="`Добавить: ${row.name}`" @click="createForTab(row)" />
        </div>
        <div class="catalog-workspace__body">
            <aside class="catalog-tree-panel" aria-label="Разделы выбранной ветки">
                <div class="catalog-panel-heading"><span><v-icon icon="mdi-file-tree-outline" size="16" />Иерархия <small>{{ view.counts.branches }}</small></span><div>
                    <v-btn icon="mdi-unfold-more-horizontal" variant="text" size="x-small" title="Развернуть всё" aria-label="Развернуть всё" @click="expanded = new Set(nodes.map(node => node.id))" />
                    <v-btn icon="mdi-unfold-less-horizontal" variant="text" size="x-small" title="Свернуть всё" aria-label="Свернуть всё" @click="expanded = new Set()" />
                </div></div>
                <button type="button" class="catalog-tree-all" :class="{ 'is-selected': !view.selectedBranchId }" @click="selectBranch(null)"><v-icon icon="mdi-view-list-outline" size="17" />Все объекты выбранного раздела<v-icon v-if="!view.selectedBranchId" icon="mdi-check" size="16" /></button>
                <div class="catalog-tree" role="tree" aria-label="Дерево классификаций">
                    <div v-for="node in view.treeRows" :key="node.id" role="treeitem" :aria-level="node.depth + 1" :aria-expanded="node.hasTreeChildren ? node.expanded : undefined" :aria-selected="view.selectedBranchId === node.id" tabindex="0" :style="{ '--depth': node.depth }"
                         :class="['catalog-tree__row', { 'is-selected': view.selectedBranchId === node.id, 'is-context': node.context }]"
                         @click="selectBranch(node)" @keydown.enter.prevent="selectBranch(node)" @keydown.space.prevent="selectBranch(node)" @keydown.right.prevent="!node.expanded && toggle(node)" @keydown.left.prevent="node.expanded && toggle(node)" @dblclick="openEditor(node)">
                        <button v-if="node.hasTreeChildren" type="button" class="catalog-tree__toggle" :aria-label="`${node.expanded ? 'Свернуть' : 'Развернуть'} ${node.name}`" @click.stop="toggle(node)"><v-icon :icon="node.expanded ? 'mdi-chevron-down' : 'mdi-chevron-right'" size="18" /></button><span v-else class="catalog-tree__spacer" />
                        <v-icon :icon="iconFor(node)" size="18" color="#84738f" />
                        <div class="catalog-tree__name"><span>{{ node.name }}</span><small>{{ levelName(node) }}<span v-if="!node.is_published"> · Черновик</span></small></div>
                        <span class="catalog-tree__count" title="Объектов в ветке">{{ node.count }}</span>
                        <v-menu><template #activator="{ props }"><v-btn v-bind="props" icon="mdi-dots-vertical" size="x-small" variant="text" :aria-label="`Действия: ${node.name}`" @click.stop /></template><v-list density="compact">
                            <v-list-item title="Редактировать" prepend-icon="mdi-pencil-outline" @click="openEditor(node)" />
                            <v-list-item title="Добавить в раздел" prepend-icon="mdi-plus" @click="create({ parentId: node.id })" />
                            <v-list-item v-if="node.is_published" title="Открыть на сайте" prepend-icon="mdi-open-in-new" :href="node.public_url" target="_blank" />
                        </v-list></v-menu>
                    </div>
                    <div v-if="!view.treeRows.length && !loading" class="catalog-tree-empty"><v-icon icon="mdi-file-tree-outline" size="30" /><p>Вложенных разделов нет</p><small>Объекты доступны в таблице справа. При необходимости добавьте промежуточный раздел.</small><v-btn variant="text" size="small" prepend-icon="mdi-plus" @click="create()">Добавить раздел</v-btn></div>
                </div>
                <div class="catalog-tree-footer"><v-icon icon="mdi-information-outline" size="14" />Уровни можно пропускать в любой ветке</div>
            </aside>
            <section class="catalog-items-panel" aria-label="Конечные объекты каталога">
                <div class="catalog-items-heading"><div class="catalog-items-heading__text"><nav v-if="contextPath.length" aria-label="Текущий раздел" class="catalog-path"><span v-for="(node, index) in contextPath" :key="node.id"><span v-if="index" class="catalog-path__separator">/</span>{{ node.name }}</span></nav><h2>{{ currentTitle }}<span>{{ tableItems.length }}</span></h2><p>Конечные записи всех вложенных веток</p></div>
                    <div class="catalog-items-heading__actions"><v-btn v-if="contextNode" prepend-icon="mdi-pencil-outline" size="small" variant="text" @click="openEditor(contextNode)">Изменить раздел</v-btn><v-btn prepend-icon="mdi-plus" size="small" variant="tonal" @click="create()">Объект</v-btn></div>
                </div>
                <v-data-table v-model:page="page" :items="tableItems" :headers="headers" :items-per-page="50" :items-per-page-options="[25, 50, 100]" :loading="loading" item-value="id" density="compact" fixed-header hover class="catalog-items-table" items-per-page-text="На странице" loading-text="Загрузка каталога…">
                    <template #item.name="{ item }"><div class="catalog-item-name"><CatalogAvatar :node="item" :icon="iconFor(item)" @open="openGallery" /><button type="button" class="catalog-item-name__button" @click="openEditor(item)"><strong>{{ item.name }}</strong><small v-if="propertiesSummary(item)" :title="propertiesSummary(item)">{{ propertiesSummary(item) }}</small><small v-else>№ {{ item.entity_id || item.id }}</small></button></div></template>
                    <template #item.level_name="{ item }"><button type="button" class="catalog-level-label" :class="{ 'is-unassigned': !item.level_id }" title="Изменить классификацию" @click="openEditor(item)">{{ item.level_name }}</button></template>
                    <template #item.path_label="{ item }"><span class="catalog-item-path" :title="item.path_label">{{ item.path_label || 'Корень каталога' }}</span></template>
                    <template #item.is_published="{ item }">
                        <div class="catalog-item-publication">
                            <div class="catalog-item-status" :title="item.is_published && !item.visible ? 'Запись опубликована, но родительский раздел скрыт на сайте' : item.status_label"><span :class="['catalog-status-dot', { 'is-published': item.visible, 'is-hidden': item.is_published && !item.visible }]" />{{ item.status_label }}<v-icon v-if="item.is_featured" icon="mdi-storefront-outline" size="16" color="#927332" title="На витрине" /></div>
                            <small v-if="item.is_published && !item.visible" class="catalog-item-hidden-section">{{ item.hidden_sections_label }}</small>
                        </div>
                    </template>
                    <template #item.purchase_price="{ item }"><CatalogGoodPricing v-if="item.entity_type === 'good'" :pricing="item.pricing" mode="purchase" /></template>
                    <template #item.sales_prices="{ item }"><CatalogGoodPricing v-if="item.entity_type === 'good'" :pricing="item.pricing" /></template>
                    <template #item.actions="{ item }"><div class="catalog-item-actions"><v-btn icon="mdi-pencil-outline" variant="text" size="x-small" :aria-label="`Редактировать ${item.name}`" @click="openEditor(item)" /><v-menu><template #activator="{ props }"><v-btn v-bind="props" icon="mdi-dots-vertical" variant="text" size="x-small" :aria-label="`Действия: ${item.name}`" /></template><v-list density="compact">
                        <v-list-item title="Добавить вложенную запись" prepend-icon="mdi-plus" @click="create({ parentId: item.id })" />
                        <v-list-item v-if="item.is_published" title="Открыть на сайте" prepend-icon="mdi-open-in-new" :href="item.public_url" target="_blank" />
                        <v-list-item v-if="item.edit_url" title="Полная карточка" prepend-icon="mdi-card-text-outline" :href="item.edit_url" target="_blank" />
                        <v-list-item title="Управление записью" prepend-icon="mdi-cog-outline" @click="openEditor(item)" />
                    </v-list></v-menu></div></template>
                    <template #no-data><div class="catalog-items-empty"><v-icon icon="mdi-view-list-outline" size="36" /><strong>{{ search || publication !== 'all' ? 'Объекты не найдены' : 'В этом разделе пока нет объектов' }}</strong><p>{{ search || publication !== 'all' ? 'Измените поисковый запрос или условия отображения.' : 'Создайте запись и назначьте ей нужный уровень. Промежуточные уровни необязательны.' }}</p><v-btn v-if="search || publication !== 'all'" variant="text" @click="resetFilters">Сбросить фильтры</v-btn><v-btn v-else variant="tonal" prepend-icon="mdi-plus" @click="create()">Создать объект</v-btn></div></template>
                </v-data-table>
            </section>
        </div>
        <CatalogNodeDialog v-model="editorOpen" :node="editorNode" :levels="levels" :nodes="nodes" :initial-parent-id="editorContext.parentId" :initial-level-id="editorContext.levelId" :initial-entity-type="editorContext.entityType" @saved="saved" @deleted="deleted" @changed="load" @schema="schemaOpen = true" />
        <CatalogSchemaDialog v-model="schemaOpen" :levels="levels" @changed="schemaChanged" />
        <CatalogGalleryDialog v-model="galleryOpen" :node="galleryNode" />
        <v-snackbar v-model="noticeOpen" :timeout="2500" color="#352345">{{ notice }}</v-snackbar>
    </section>
</template>

<style scoped>
.catalog-workspace { display: flex; flex: 1 1 0; flex-direction: column; min-height: 0; overflow: hidden; }
.classification-row { display: flex; align-items: center; flex: 0 0 auto; gap: 8px; min-height: 37px; padding: 0 10px; border-bottom: 1px solid #e3dfe8; background: #faf9fb; }
.classification-row--domains { background: #f2eff6; }
.classification-row__label { flex: 0 0 112px; display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 650; color: #7b6b88; }
.classification-row__tabs { display: flex; align-items: stretch; gap: 4px; flex: 1; min-width: 0; overflow-x: auto; scrollbar-width: thin; }
.classification-tab { display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; gap: 7px; padding: 8px 12px; font-size: 12px; color: #776b80; border-bottom: 2px solid transparent; white-space: nowrap; line-height: 17px; }
.classification-tab:hover { background: #ede8f2; }
.classification-tab.is-selected { color: #49315c; border-bottom-color: #65437d; background: #e8e1ef; font-weight: 600; }
.classification-tab__count { min-width: 17px; padding: 0 4px; background: #ffffffa6; color: #95879e; font-size: 10px; line-height: 16px; text-align: center; font-weight: 500; }
.classification-row__empty { align-self: center; color: #aaa1b0; font-size: 11px; white-space: nowrap; }
.catalog-workspace__body { display: grid; grid-template-columns: minmax(265px, 30%) minmax(0, 1fr); flex: 1 1 0; min-height: 0; }
.catalog-tree-panel { display: flex; flex-direction: column; min-width: 0; min-height: 0; border-right: 1px solid #ded9e3; background: #fcfbfd; }
.catalog-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #e9e5ee; font-size: 12px; font-weight: 600; }
.catalog-panel-heading > span { display: flex; align-items: center; gap: 7px; }.catalog-panel-heading small { color: #a096a7; font-weight: 400; }
.catalog-tree-all { display: flex; align-items: center; gap: 8px; text-align: left; padding: 12px; font-size: 11px; color: #8e8099; border-bottom: 1px solid #ece8f0; }
.catalog-tree-all.is-selected { color: #68487e; background: #f1edf6; }.catalog-tree-all > :last-child { margin-left: auto; }
.catalog-tree { flex: 1 1 0; min-height: 0; overflow: auto; padding: 4px 0; }
.catalog-tree__row { display: flex; align-items: center; gap: 6px; min-height: 49px; padding: 6px 6px 6px calc(6px + var(--depth) * 18px); cursor: pointer; border-bottom: 1px solid #f0edf4; }
.catalog-tree__row:hover { background: #f4f0f8; }.catalog-tree__row.is-selected { background: #eae3f1; box-shadow: inset 3px 0 #785491; }.catalog-tree__row.is-context { color: #95889f; }
.catalog-tree__toggle, .catalog-tree__spacer { flex: 0 0 18px; width: 18px; }.catalog-tree__name { display: flex; flex-direction: column; flex: 1; min-width: 100px; gap: 3px; line-height: 1.4; font-size: 12px; font-weight: 500; }.catalog-tree__name small { font-size: 10px; color: #968b9f; font-weight: 400; }
.catalog-tree__count { color: #85738f; font-size: 11px; font-variant-numeric: tabular-nums; background: #f0eaf6; padding: 2px 6px; }
.catalog-tree-empty { text-align: center; padding: 32px 20px; color: #a095a9; }.catalog-tree-empty p { margin: 10px 0 6px; font-size: 12px; }.catalog-tree-empty small { display: block; font-size: 11px; line-height: 1.8; margin-bottom: 12px; }
.catalog-tree-footer { display: flex; align-items: center; gap: 6px; padding: 9px 12px; border-top: 1px solid #e9e5ee; color: #a095a9; font-size: 10px; }
.catalog-items-panel { min-height: 0; min-width: 0; display: flex; flex-direction: column; background: #fff; }
.catalog-items-heading { flex: 0 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 12px; min-height: 75px; padding: 11px 16px; border-bottom: 1px solid #e5dfea; }
.catalog-items-heading__text { min-width: 0; }.catalog-items-heading h2 { display: flex; align-items: center; gap: 9px; font-size: 16px; font-weight: 650; line-height: 1.5; }.catalog-items-heading h2 > span { font-size: 11px; font-weight: 500; padding: 1px 6px; color: #907e9e; background: #f0eaf5; }.catalog-items-heading p { font-size: 10px; margin-top: 3px; color: #a095a9; }.catalog-items-heading__actions { display: flex; align-items: center; flex-shrink: 0; gap: 6px; }
.catalog-path { display: flex; flex-wrap: wrap; gap: 5px; font-size: 10px; color: #998ca3; margin-bottom: 3px; }.catalog-path__separator { margin-right: 5px; color: #c2b8ca; }
.catalog-items-table { display: flex; flex: 1 1 0; flex-direction: column; min-height: 0; }.catalog-items-table :deep(.v-table__wrapper) { flex: 1 1 auto; min-height: 0; }.catalog-items-table :deep(.v-data-table__td) { height: 68px !important; border-bottom: 1px solid #f0edf3 !important; }.catalog-items-table :deep(.v-data-table__td:first-child) { padding: 0 !important; }.catalog-items-table :deep(.v-data-table-footer) { flex-shrink: 0; }
.catalog-item-name { display: flex; align-items: center; gap: 8px; min-width: 190px; padding: 0; }.catalog-item-name__button { display: flex; min-width: 0; flex-direction: column; text-align: left; gap: 4px; }.catalog-item-name__button strong { color: #4c3a59; font-size: 12px; line-height: 1.5; font-weight: 550; }.catalog-item-name__button:hover strong { text-decoration: underline; }.catalog-item-name__button small { font-size: 10px; color: #a092aa; line-height: 1.4; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.catalog-level-label { padding: 3px 6px; font-size: 10px; color: #7b638e; background: #f3eef8; text-align: left; }.catalog-level-label.is-unassigned { background: #f4f4f4; color: #aaa; }.catalog-level-label:hover { background: #e8dff1; }.catalog-item-path { display: block; color: #9a8ba5; font-size: 11px; line-height: 1.5; max-width: 300px; }.catalog-item-status { display: flex; align-items: center; gap: 7px; white-space: nowrap; font-size: 11px; color: #8c7c96; }.catalog-status-dot { display: inline-block; flex: 0 0 6px; width: 6px; height: 6px; border-radius: 50%; background: #c5bdcd; }.catalog-status-dot.is-published { background: #559c79; }.catalog-status-dot.is-hidden { background: #c59954; }.catalog-item-actions { display: flex; gap: 2px; }
.catalog-item-publication { padding: 4px 0; }
.catalog-item-hidden-section { display: block; margin: 3px 0 0 13px; max-width: 155px; font-size: 10px; line-height: 1.35; color: #968b9f; white-space: normal; overflow-wrap: anywhere; }
.catalog-items-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; padding: 50px 24px; color: #a092aa; }.catalog-items-empty strong { font-size: 16px; color: #7d698c; font-weight: 500; }.catalog-items-empty p { font-size: 12px; max-width: 400px; line-height: 1.8; }
@media (max-width: 980px) { .catalog-workspace__body { grid-template-columns: minmax(225px, 30%) minmax(0, 1fr); }.catalog-items-heading__actions { flex-direction: column; align-items: flex-end; }.catalog-tree__name { min-width: 75px; } }
@media (max-width: 680px) { .classification-row { gap: 4px; padding: 0 6px; }.classification-row__label { flex-basis: 74px; font-size: 10px; }.classification-tab { font-size: 11px; padding: 8px; }.catalog-workspace__body { display: flex; flex-direction: column; overflow: auto; }.catalog-tree-panel { flex: 0 0 200px; border-right: 0; border-bottom: 1px solid #ded9e3; }.catalog-items-panel { flex: 1 0 430px; min-height: 430px; }.catalog-items-heading { padding: 10px; }.catalog-items-heading h2 { font-size: 14px; }.catalog-items-heading__actions :deep(.v-btn) { font-size: 10px; }.catalog-tree-footer { display: none; } }
</style>
