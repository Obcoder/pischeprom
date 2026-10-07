<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import CatalogToolbar from '@/Components/Dictionaries/CatalogToolbar.vue'
import CatalogSchemaDialog from './CatalogSchemaDialog.vue'
import { catalogTree, descendantIds } from './tree.js'

const nodes = ref([])
const levels = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const errors = ref({})
const notice = ref('')
const noticeOpen = computed({ get: () => Boolean(notice.value), set: value => { if (!value) notice.value = '' } })
const search = ref('')
const publication = ref('all')
const expanded = ref(new Set())
const selectedId = ref(null)
const editing = ref(false)
const schemaOpen = ref(false)
const deleteOpen = ref(false)
const pendingSelection = ref(null)
const discardOpen = ref(false)
const imageFile = ref(null)
const baseline = ref('')
const form = reactive({})
const selected = computed(() => nodes.value.find(node => node.id === selectedId.value))
const levelMap = computed(() => new Map(levels.value.map(level => [level.id, level])))
const currentLevel = computed(() => levelMap.value.get(form.level_id))
const editableLevels = computed(() => selectedId.value && !selected.value?.entity_type ? levels.value.filter(level => level.entity_type === 'custom') : levels.value)
const rows = computed(() => catalogTree(nodes.value, { search: search.value, publication: publication.value, expanded: expanded.value }))
const dirty = computed(() => editing.value && (JSON.stringify(form) !== baseline.value || Boolean(imageFile.value)))
const blockedParents = computed(() => selectedId.value ? descendantIds(nodes.value, selectedId.value) : new Set())
const parentOptions = computed(() => [{ id: null, name: 'Корень каталога' }, ...nodes.value.filter(node => !blockedParents.value.has(node.id)).map(node => ({ id: node.id, name: `${node.name} · ${levelMap.value.get(node.level_id)?.name || ''} · #${node.id}` }))])
const breadcrumbs = computed(() => {
    const byId = new Map(nodes.value.map(node => [node.id, node]))
    const result = []
    let parent = byId.get(form.parent_id)
    const visited = new Set()
    while (parent && !visited.has(parent.id)) {
        result.unshift(parent.name)
        visited.add(parent.id)
        parent = byId.get(parent.parent_id)
    }
    return result.join(' / ')
})
const publishedCount = computed(() => nodes.value.filter(node => node.is_published).length)
const publicationOptions = [
    { title: 'Все записи', value: 'all' }, { title: 'Опубликованные', value: 'published' },
    { title: 'Черновики', value: 'draft' }, { title: 'На витрине', value: 'featured' },
]
const iconFor = node => ({ category: 'mdi-folder-outline', product: 'mdi-package-variant-closed', good: 'mdi-tag-outline' }[node.entity_type] || 'mdi-file-tree-outline')
const fieldErrors = key => errors.value[key] || []

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get('/api/catalog')
        nodes.value = data.nodes
        levels.value = data.levels
        if (!expanded.value.size && !editing.value) expanded.value = new Set(data.nodes.filter(node => !node.parent_id).map(node => node.id))
    } catch (failure) { error.value = failure.response?.data?.message || 'Не удалось загрузить каталог. Повторите попытку.' }
    finally { loading.value = false }
}
function toggle(node) {
    const next = new Set(expanded.value)
    next.has(node.id) ? next.delete(node.id) : next.add(node.id)
    expanded.value = next
}
function resetForm(node, parentId = null) {
    Object.keys(form).forEach(key => delete form[key])
    Object.assign(form, {
        level_id: node?.level_id || levels.value[0]?.id,
        parent_id: node?.parent_id ?? parentId,
        name: node?.name || '', slug: node?.slug || '', image: node?.image || '',
        description: node?.description || '', meta_title: node?.meta_title || '', meta_description: node?.meta_description || '',
        is_published: node?.is_published ?? false, is_featured: node?.is_featured ?? false,
        sort_order: node?.sort_order ?? 0,
        properties: JSON.parse(JSON.stringify(node?.properties || {})),
    })
    selectedId.value = node?.id ?? null
    editing.value = true
    errors.value = {}
    imageFile.value = null
    baseline.value = JSON.stringify(form)
}
function requestSelection(node = null, parentId = null) {
    if (saving.value) return
    if (dirty.value) { pendingSelection.value = { node, parentId }; discardOpen.value = true; return }
    resetForm(node, parentId)
}
function discardAndSelect() {
    const { node, parentId } = pendingSelection.value
    discardOpen.value = false
    resetForm(node, parentId)
}
function createChild() {
    const parent = selected.value
    requestSelection(null, parent?.id ?? null)
    if (!discardOpen.value && parent) {
        const type = parent.entity_type === 'category' ? 'product' : parent.entity_type === 'product' ? 'good' : 'custom'
        form.level_id = levels.value.find(level => level.entity_type === type)?.id || form.level_id
        baseline.value = JSON.stringify(form)
    }
}
function changeLevel() {
    form.properties = {}
    for (const field of currentLevel.value?.fields || []) {
        if (field.type === 'boolean') form.properties[field.key] = false
    }
}
async function save() {
    if (saving.value) return
    saving.value = true
    error.value = ''; errors.value = {}; notice.value = ''
    try {
        const payload = { ...form, properties: { ...form.properties } }
        // Empty optional numeric/date/select fields are stored as null, never NaN.
        for (const field of currentLevel.value?.fields || []) {
            const value = payload.properties[field.key]
            if (value === '' || value === undefined) payload.properties[field.key] = null
            else if (field.type === 'number') payload.properties[field.key] = Number(value)
        }
        const { data } = selectedId.value
            ? await axios.patch(`/api/catalog/nodes/${selectedId.value}`, payload)
            : await axios.post('/api/catalog/nodes', payload)
        const savedId = data.data.id
        selectedId.value = savedId
        baseline.value = JSON.stringify(form)
        if (imageFile.value) {
            const body = new FormData()
            body.append('image', Array.isArray(imageFile.value) ? imageFile.value[0] : imageFile.value)
            try { await axios.post(`/api/catalog/nodes/${savedId}/image`, body); imageFile.value = null }
            catch (failure) {
                await load()
                throw failure
            }
        }
        if (form.parent_id) expanded.value = new Set([...expanded.value, form.parent_id])
        await load()
        const saved = nodes.value.find(node => node.id === savedId)
        if (saved) resetForm(saved)
        notice.value = 'Запись сохранена'
    } catch (failure) {
        errors.value = failure.response?.data?.errors || {}
        error.value = Object.values(errors.value).flat().join(' ') || failure.response?.data?.message || 'Не удалось сохранить запись.'
    } finally { saving.value = false }
}
async function remove() {
    if (!selectedId.value || saving.value) return
    saving.value = true; error.value = ''
    try {
        await axios.delete(`/api/catalog/nodes/${selectedId.value}`)
        deleteOpen.value = false; editing.value = false; selectedId.value = null
        await load(); notice.value = 'Запись удалена'
    } catch (failure) { error.value = failure.response?.data?.message || 'Не удалось удалить запись.'; deleteOpen.value = false }
    finally { saving.value = false }
}
async function schemaChanged() {
    await load()
    if (editing.value) {
        const allowed = new Set((currentLevel.value?.fields || []).map(field => field.key))
        for (const key of Object.keys(form.properties)) if (!allowed.has(key)) delete form.properties[key]
    }
}
onMounted(load)
</script>

<template>
    <section class="catalog-workspace" aria-label="Иерархия товаров">
        <CatalogToolbar :count="rows.length" :total="nodes.length">
            <v-text-field v-model="search" label="Поиск по дереву" prepend-inner-icon="mdi-magnify" clearable hide-details />
            <v-select v-model="publication" :items="publicationOptions" label="Отображение" hide-details />
            <template #actions>
                <v-btn prepend-icon="mdi-tune-variant" variant="text" @click="schemaOpen = true">Уровни и поля</v-btn>
                <v-btn icon="mdi-refresh" variant="text" :loading="loading" :disabled="saving" aria-label="Обновить каталог" @click="load" />
                <v-btn prepend-icon="mdi-plus" :disabled="!levels.length || saving" @click="requestSelection()">Добавить</v-btn>
            </template>
        </CatalogToolbar>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" closable @click:close="error = ''">{{ error }}</v-alert>
        <v-progress-linear v-if="loading" indeterminate color="#352345" />
        <div class="catalog-workspace__body">
            <div class="catalog-tree-panel">
                <div class="catalog-tree-panel__heading">
                    <span>Структура каталога <small>{{ publishedCount }} опубликовано</small></span>
                    <div>
                        <v-btn icon="mdi-unfold-more-horizontal" variant="text" size="x-small" aria-label="Развернуть всё" title="Развернуть всё" @click="expanded = new Set(nodes.map(node => node.id))" />
                        <v-btn icon="mdi-unfold-less-horizontal" variant="text" size="x-small" aria-label="Свернуть всё" title="Свернуть всё" @click="expanded = new Set()" />
                    </div>
                </div>
                <div class="catalog-tree" role="tree" aria-label="Категории, продукты и товары">
                    <div v-for="node in rows" :key="node.id" role="treeitem" :aria-level="node.depth + 1" :aria-expanded="node.hasChildren ? node.expanded : undefined" :aria-selected="selectedId === node.id" tabindex="0"
                         class="catalog-tree__row" :class="{ 'is-selected': selectedId === node.id, 'is-context': node.context }"
                         :style="{ '--depth': node.depth }" @click="requestSelection(node)" @keydown.enter.prevent="requestSelection(node)" @keydown.space.prevent="requestSelection(node)" @keydown.right.prevent="!node.expanded && toggle(node)" @keydown.left.prevent="node.expanded && toggle(node)">
                        <button v-if="node.hasChildren" type="button" class="catalog-tree__toggle" :aria-label="`${node.expanded ? 'Свернуть' : 'Развернуть'} ${node.name}`" @click.stop="toggle(node)">
                            <v-icon :icon="node.expanded ? 'mdi-chevron-down' : 'mdi-chevron-right'" size="18" />
                        </button>
                        <span v-else class="catalog-tree__spacer" />
                        <v-icon :icon="iconFor(node)" size="18" :color="node.is_published ? '#756682' : '#a29ca7'" />
                        <div class="catalog-tree__name"><span>{{ node.name }}</span><small>{{ levelMap.get(node.level_id)?.name }}<template v-if="node.childCount"> · {{ node.childCount }}</template></small></div>
                        <v-icon v-if="node.is_featured" icon="mdi-storefront-outline" size="15" color="#88651f" title="На витрине" />
                        <span class="catalog-tree__status" :class="{ 'is-published': node.is_published }" :title="node.is_published ? 'Опубликовано' : 'Черновик'" :aria-label="node.is_published ? 'Опубликовано' : 'Черновик'" />
                    </div>
                    <div v-if="!rows.length && !loading" class="catalog-empty"><v-icon icon="mdi-file-tree-outline" size="36" /><strong>{{ nodes.length ? 'Ничего не найдено' : 'Каталог пока пуст' }}</strong><span>{{ nodes.length ? 'Измените поиск или фильтр.' : 'Создайте первую категорию или свой уровень.' }}</span><v-btn v-if="!nodes.length" variant="tonal" @click="requestSelection()">Добавить запись</v-btn></div>
                </div>
                <div class="catalog-tree-panel__legend"><span class="catalog-tree__status is-published" /> Опубликовано <span class="catalog-tree__status" /> Черновик <v-icon icon="mdi-storefront-outline" size="14" /> Витрина</div>
            </div>
            <div v-if="editing" class="catalog-editor">
                <div class="catalog-editor__heading"><div><small>{{ selectedId ? 'Редактирование записи' : 'Новая запись' }}</small><h2>{{ selected?.name || 'Добавление в каталог' }}</h2></div><v-chip v-if="dirty" size="x-small" variant="tonal">Не сохранено</v-chip></div>
                <div class="catalog-editor__scroll">
                    <p v-if="breadcrumbs" class="catalog-editor__path">{{ breadcrumbs }}</p>
                    <v-form id="catalog-node-form" @submit.prevent="save">
                        <fieldset :disabled="saving" class="catalog-editor__fieldset">
                            <div class="catalog-editor__grid">
                                <v-select v-model="form.level_id" :items="editableLevels" item-title="name" item-value="id" label="Уровень классификации" :disabled="Boolean(selectedId && selected?.entity_type)" :error-messages="fieldErrors('level_id')" @update:model-value="changeLevel" />
                                <v-text-field v-model.number="form.sort_order" label="Порядок в ветке" type="number" :error-messages="fieldErrors('sort_order')" />
                            </div>
                            <v-text-field v-model="form.name" label="Название *" :error-messages="fieldErrors('name')" required maxlength="255" />
                            <v-autocomplete v-model="form.parent_id" :items="parentOptions" item-title="name" item-value="id" label="Родительская запись" :error-messages="fieldErrors('parent_id')" hint="Выберите другую запись, чтобы перенести ветку" persistent-hint />
                            <div class="catalog-editor__publication">
                                <v-switch v-model="form.is_published" label="Публикация на сайте" color="#352345" hide-details density="compact" />
                                <v-switch v-model="form.is_featured" label="Разместить на витрине" color="#352345" hide-details density="compact" />
                                <small>Витрина показывает опубликованные записи. Скрытая родительская ветка скрывает вложенные разделы каталога.</small>
                            </div>
                            <h3>Содержание</h3>
                            <v-textarea v-model="form.description" label="Описание" variant="outlined" rows="3" auto-grow :error-messages="fieldErrors('description')" />
                            <div class="catalog-editor__avatar"><v-img v-if="form.image" :src="form.image" width="60" height="60" cover /><v-text-field v-model="form.image" label="Аватар · URL изображения" clearable :error-messages="fieldErrors('image')" /></div>
                            <v-file-input v-model="imageFile" label="Загрузить аватар" accept="image/jpeg,image/png,image/webp,image/gif" variant="outlined" density="compact" :error-messages="fieldErrors('image')" hint="JPG, PNG, WebP или GIF до 5 МБ" persistent-hint />
                            <h3>SEO</h3>
                            <v-text-field v-model="form.slug" label="Адрес страницы (slug)" :error-messages="fieldErrors('slug')" />
                            <v-text-field v-model="form.meta_title" label="SEO · Title" :error-messages="fieldErrors('meta_title')" />
                            <v-textarea v-model="form.meta_description" label="SEO · Description" variant="outlined" rows="2" :error-messages="fieldErrors('meta_description')" />
                            <div class="catalog-editor__properties-title"><h3>Свойства · {{ currentLevel?.name }}</h3><v-btn variant="text" size="small" @click="schemaOpen = true">Настроить поля</v-btn></div>
                            <p v-if="!currentLevel?.fields?.length" class="catalog-editor__hint">Добавьте свойства этого уровня: текст, число, дату, переключатель или список вариантов.</p>
                            <template v-for="field in currentLevel?.fields || []" :key="field.id">
                                <v-checkbox v-if="field.type === 'boolean'" v-model="form.properties[field.key]" :label="field.label + (field.required ? ' *' : '')" :error-messages="fieldErrors(`properties.${field.key}`)" density="compact" />
                                <v-textarea v-else-if="field.type === 'textarea'" v-model="form.properties[field.key]" :label="field.label + (field.required ? ' *' : '')" variant="outlined" rows="3" :error-messages="fieldErrors(`properties.${field.key}`)" />
                                <v-select v-else-if="field.type === 'select'" v-model="form.properties[field.key]" :items="field.options || []" :label="field.label + (field.required ? ' *' : '')" clearable :error-messages="fieldErrors(`properties.${field.key}`)" />
                                <v-text-field v-else v-model="form.properties[field.key]" :label="field.label + (field.required ? ' *' : '')" :type="['number', 'date', 'url'].includes(field.type) ? field.type : 'text'" :step="field.type === 'number' ? 'any' : undefined" :error-messages="fieldErrors(`properties.${field.key}`)" />
                            </template>
                        </fieldset>
                    </v-form>
                </div>
                <div class="catalog-editor__footer">
                    <v-btn type="submit" form="catalog-node-form" :loading="saving" prepend-icon="mdi-check">Сохранить</v-btn>
                    <v-btn v-if="selectedId" variant="tonal" :disabled="saving" prepend-icon="mdi-plus" @click="createChild">Подуровень</v-btn>
                    <v-menu v-if="selectedId"><template #activator="{ props }"><v-btn v-bind="props" icon="mdi-dots-horizontal" variant="text" aria-label="Действия с записью" /></template><v-list density="compact">
                        <v-list-item v-if="selected?.public_url && selected?.is_published" :href="selected.public_url" target="_blank" title="Открыть на сайте" prepend-icon="mdi-open-in-new" />
                        <v-list-item v-if="selected?.edit_url" :href="selected.edit_url" target="_blank" title="Полная карточка" prepend-icon="mdi-card-text-outline" />
                        <v-list-item title="Добавить соседнюю запись" prepend-icon="mdi-plus" @click="requestSelection(null, form.parent_id)" />
                        <v-list-item title="Удалить" prepend-icon="mdi-delete-outline" base-color="error" :disabled="saving" @click="deleteOpen = true" />
                    </v-list></v-menu>
                </div>
            </div>
            <div v-else class="catalog-editor catalog-empty"><v-icon icon="mdi-file-tree-outline" size="52" color="#baafc4" /><strong>Каталог начинается со структуры</strong><span>Выберите запись слева для редактирования<br>или добавьте категорию, продукт, товар или свой раздел.</span><v-btn prepend-icon="mdi-plus" variant="tonal" :disabled="!levels.length" @click="requestSelection()">Добавить запись</v-btn></div>
        </div>
        <CatalogSchemaDialog v-model="schemaOpen" :levels="levels" @changed="schemaChanged" />
        <v-dialog v-model="deleteOpen" max-width="460"><v-card title="Удалить запись?"><v-card-text>«{{ selected?.name }}» будет удалена. Сначала перенесите вложенные записи. Связанные с операциями товары защищены от удаления.</v-card-text><v-card-actions><v-spacer /><v-btn :disabled="saving" @click="deleteOpen = false">Отмена</v-btn><v-btn color="error" :loading="saving" @click="remove">Удалить</v-btn></v-card-actions></v-card></v-dialog>
        <v-dialog v-model="discardOpen" max-width="440"><v-card title="Есть несохранённые изменения"><v-card-text>Перейти к другой записи и отменить изменения?</v-card-text><v-card-actions><v-spacer /><v-btn @click="discardOpen = false">Остаться</v-btn><v-btn @click="discardAndSelect">Отменить изменения</v-btn></v-card-actions></v-card></v-dialog>
        <v-snackbar v-model="noticeOpen" :timeout="3000" color="#352345">{{ notice }}</v-snackbar>
    </section>
</template>

<style scoped>
.catalog-workspace { display: flex; flex: 1 1 0; flex-direction: column; min-height: 0; overflow: hidden; }
.catalog-workspace__body { display: grid; grid-template-columns: minmax(300px, 1fr) minmax(360px, 0.9fr); flex: 1 1 0; min-height: 0; }
.catalog-tree-panel { display: flex; flex-direction: column; min-width: 0; min-height: 0; border-right: 1px solid #ded9e3; }
.catalog-tree-panel__heading { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 12px; background: #faf9fb; border-bottom: 1px solid #eeeaf1; font-weight: 600; }
.catalog-tree-panel__heading small { font-size: 10px; color: #8c8394; font-weight: 400; margin-left: 8px; }
.catalog-tree { flex: 1 1 0; min-height: 0; overflow: auto; padding: 6px 0; }
.catalog-tree__row { display: flex; align-items: center; gap: 8px; min-height: 48px; padding: 6px 12px 6px calc(8px + var(--depth) * 22px); cursor: pointer; border-bottom: 1px solid #f5f3f6; }
.catalog-tree__row:hover { background: #faf8fc; }
.catalog-tree__row.is-selected { background: #f0eaf5; box-shadow: inset 3px 0 #5d3b77; }
.catalog-tree__row.is-context .catalog-tree__name { color: #8c8394; }
.catalog-tree__toggle, .catalog-tree__spacer { flex: 0 0 20px; width: 20px; }
.catalog-tree__name { display: flex; flex-direction: column; flex: 1; min-width: 140px; line-height: 1.5; font-weight: 500; }
.catalog-tree__name small { font-size: 10px; color: #8a8291; font-weight: 400; }
.catalog-tree__status { display: inline-block; flex: 0 0 7px; width: 7px; height: 7px; border-radius: 50%; background: #c7c0cc; }
.catalog-tree__status.is-published { background: #408368; }
.catalog-tree-panel__legend { display: flex; gap: 7px; align-items: center; padding: 8px 12px; border-top: 1px solid #eeeaf1; color: #8c8394; font-size: 10px; }
.catalog-editor { min-height: 0; min-width: 0; display: flex; flex-direction: column; background: #fdfcfe; }
.catalog-editor__heading { display: flex; justify-content: space-between; align-items: center; gap: 8px; border-bottom: 1px solid #eeeaf1; padding: 12px 18px; }
.catalog-editor__heading small { font-size: 10px; color: #8c8394; }
.catalog-editor__heading h2 { font-size: 16px; font-weight: 600; margin: 2px 0 0; }
.catalog-editor__scroll { flex: 1 1 0; min-height: 0; overflow: auto; padding: 14px 18px; }
.catalog-editor__path { font-size: 11px; color: #8c8394; margin-bottom: 12px; }
.catalog-editor__fieldset { border: 0; min-width: 0; }
.catalog-editor__fieldset:disabled { opacity: .7; }
.catalog-editor__grid { display: grid; grid-template-columns: 1fr 130px; gap: 10px; }
.catalog-editor__publication { padding: 6px 12px 12px; margin: 14px 0; border: 1px solid #e5dfe9; background: #f8f5fa; }
.catalog-editor__publication small { display: block; font-size: 10px; color: #84778f; }
.catalog-editor h3 { font-size: 12px; font-weight: 650; margin: 12px 0; }
.catalog-editor__avatar { display: flex; align-items: flex-start; gap: 10px; }
.catalog-editor__avatar :deep(.v-img) { flex: 0 0 60px; }
.catalog-editor__properties-title { display: flex; justify-content: space-between; align-items: center; }
.catalog-editor__hint { font-size: 12px; color: #8c8394; margin-bottom: 14px; }
.catalog-editor__footer { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px 18px; border-top: 1px solid #ded9e3; background: #fff; }
.catalog-editor__footer :deep(.v-btn) { font-size: 12px; }
.catalog-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; text-align: center; padding: 36px 20px; color: #93899d; }
.catalog-empty strong { font-size: 16px; color: #65566f; font-weight: 500; }
.catalog-empty span { font-size: 12px; line-height: 1.8; }
@media (max-width: 900px) { .catalog-workspace__body { grid-template-columns: minmax(240px, .8fr) minmax(320px, 1fr); } }
@media (max-width: 680px) { .catalog-workspace__body { display: flex; flex-direction: column; overflow: auto; } .catalog-tree-panel { min-height: 250px; flex: 0 0 40%; border-right: 0; border-bottom: 1px solid #ded9e3; } .catalog-editor { min-height: 430px; flex: 1 0 auto; } .catalog-editor__scroll { flex: 1 0 auto; overflow: visible; } }
</style>
