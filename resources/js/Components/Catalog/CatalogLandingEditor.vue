<script setup>
import axios from 'axios'
import { computed, nextTick, onScopeDispose, ref, watch } from 'vue'
import CatalogLandingFields from './CatalogLandingFields.vue'
import { blockTypes, createLandingBlock, heroFields, catalogFields, contactFields, sourcesFields } from './Landing/schema.js'

const props = defineProps({
    node: { type: Object, required: true },
    active: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    cardDirty: { type: Boolean, default: false },
})
const emit = defineEmits(['state', 'saved', 'navigate', 'save-card'])
const state = ref(null)
const content = ref(null)
const baseline = ref('null')
const loading = ref(false)
const saving = ref(false)
const uploading = ref(false)
const ready = ref(false)
const error = ref('')
const message = ref('')
const conflict = ref(false)
const conflictSnapshot = ref(null)
const resolving = ref(false)
const templateKey = ref('')
const newBlockType = ref('text')
const expandedBlock = ref(null)
const unpublishOpen = ref(false)
const productOptions = ref([])
const goodOptions = ref([])
const search = ref('')
const searching = ref(false)
const optionsError = ref('')
let identity = 0
let readController = null
let optionsController = null
let searchController = null
let searchTimer = null
let searchVersion = 0
let disposed = false

const clone = value => value == null ? null : JSON.parse(JSON.stringify(value))
const endpoint = () => `/api/catalog/nodes/${props.node.id}/landing`
const dirty = computed(() => ready.value && JSON.stringify(content.value) !== baseline.value)
const busy = computed(() => saving.value || uploading.value || resolving.value)
const controlsDisabled = computed(() => props.disabled || busy.value || loading.value || !ready.value || conflict.value)
const isGood = computed(() => state.value?.mode === 'good')
const blockOptions = Object.entries(blockTypes).map(([value, definition]) => ({ value, title: definition.label }))
const assortmentFields = catalogFields.filter(field => !field.key.endsWith('_ids'))
const hasSavedDraft = computed(() => state.value?.exists && state.value?.draft_content)
const published = computed(() => Boolean(state.value?.published_content))
const publicationLabel = computed(() => published.value ? (state.value?.has_changes ? 'Опубликован · есть сохранённый черновик' : 'Опубликован') : state.value?.exists ? 'Черновик · не опубликован' : 'Лендинг ещё не создан')
const visibilityMessage = computed(() => ({ hidden_ancestor: 'Страница скрыта родительским разделом каталога.', hidden_node: 'Запись каталога скрыта. Включите публикацию в основных данных.', missing_parent: 'Расположение записи недоступно. Проверьте родительский раздел.', cycle: 'Проверьте расположение записи в каталоге.' })[state.value?.visibility_reason] || 'Страница скрыта настройками каталога.')
const sourceProducts = computed(() => mergeOptions(state.value?.source_products || [], productOptions.value))
const selectedGoods = computed(() => mergeOptions(state.value?.selected_goods || [], goodOptions.value))
const textLinks = computed(() => [
    ...selectedGoods.value.filter(good => (content.value?.catalog?.inline_good_ids || []).includes(good.id)).map(good => {
        const label = good.name.replaceAll('[', '（').replaceAll(']', '）')
        return { name: `Товар: ${good.name}`, label, target: `good:${good.id}`, token: `[[good:${good.id}|${label}]]` }
    }),
    ...(content.value?.sources?.items || []).filter(source => /^[a-z][a-z0-9_-]*$/.test(source.id || '')).map(source => {
        const index = content.value.sources.items.indexOf(source) + 1, label = `[${index}]`
        return { name: `Источник ${index}: ${source.title || 'Без названия'}`, label, target: `#${source.id}`, token: `[[#${source.id}|${label}]]` }
    }),
])
const publicUrl = computed(() => safeUrl(props.node.public_url || state.value?.public_url))
const previewUrl = computed(() => safeUrl(state.value?.preview_url))
function safeUrl(value) { return typeof value === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(value) ? value : '' }
function mergeOptions(...sets) {
    return [...new Map(sets.flat().map(item => [Number(item.id), { id: Number(item.id), name: item.name || item.rus || item.title || 'Без названия' }])).values()]
}
function announceState() { emit('state', { dirty: dirty.value, busy: busy.value, ready: ready.value }) }
watch([dirty, busy, ready], announceState, { immediate: true, flush: 'sync' })
function cancelReads() {
    readController?.abort(); readController = null
    optionsController?.abort(); optionsController = null
    searchController?.abort(); searchController = null
    clearTimeout(searchTimer); searchTimer = null; searchVersion++
    searching.value = false; loading.value = false
}
function applyState(data, keepDraft = false) {
    if (!data || !['catalog', 'good'].includes(data.mode)) throw new Error('Не удалось прочитать данные лендинга.')
    state.value = data
    baseline.value = JSON.stringify(data.draft_content || null)
    if (!keepDraft) content.value = clone(data.draft_content || null)
    templateKey.value ||= data.templates?.[0]?.key || ''
    ready.value = true
}
async function load() {
    if (!props.node?.id || !props.active || loading.value || busy.value) return
    readController?.abort()
    const current = new AbortController(), version = identity
    readController = current; loading.value = true; error.value = ''
    try {
        const response = await axios.get(endpoint(), { signal: current.signal })
        if (disposed || current.signal.aborted || version !== identity) return
        applyState(response.data.data)
        if (!isGood.value) loadOptions()
    } catch (failure) {
        if (!disposed && !current.signal.aborted && version === identity) error.value = failure.response?.data?.message || failure.message || 'Не удалось загрузить лендинг.'
    } finally { if (version === identity && readController === current) { loading.value = false; readController = null } }
}
async function loadOptions() {
    optionsController?.abort()
    const current = new AbortController(), version = identity
    optionsController = current; optionsError.value = ''
    try {
        const response = await axios.get('/api/goods', { params: { view: 'filters' }, signal: current.signal })
        if (disposed || current.signal.aborted || version !== identity) return
        const data = response.data.data || response.data
        productOptions.value = data.products || []
    } catch (failure) {
        if (!disposed && !current.signal.aborted && version === identity) optionsError.value = 'Не удалось загрузить список продуктов. Сохранённый подбор сохранится; повторите загрузку для его изменения.'
    } finally { if (optionsController === current) optionsController = null }
}
async function searchGoods(query = search.value) {
    if (!props.active || controlsDisabled.value) return
    clearTimeout(searchTimer); searchTimer = null
    searchController?.abort()
    const current = new AbortController(), version = identity, attempt = ++searchVersion
    searchController = current; searching.value = true; optionsError.value = ''
    try {
        const response = await axios.get('/api/goods', { params: { view: 'table', search: query, per_page: 30 }, signal: current.signal })
        if (disposed || current.signal.aborted || version !== identity || attempt !== searchVersion) return
        goodOptions.value = mergeOptions(goodOptions.value, response.data.data || [])
    } catch (failure) {
        if (!disposed && !current.signal.aborted && version === identity && attempt === searchVersion) optionsError.value = 'Не удалось найти товары. Повторите поиск.'
    } finally { if (attempt === searchVersion) { searching.value = false; searchController = null } }
}
function queueSearch(query) {
    search.value = query || ''
    clearTimeout(searchTimer)
    searchController?.abort(); searchVersion++; searching.value = false
    if (props.active && !controlsDisabled.value) searchTimer = setTimeout(() => searchGoods(search.value), 250)
}
function create() {
    if (controlsDisabled.value || content.value || isGood.value) return
    const template = state.value.templates?.find(item => item.key === templateKey.value)
    if (!template?.content) return
    content.value = clone(template.content)
    if (!content.value.hero?.title) content.value.hero = { ...content.value.hero, title: props.node.name || '' }
    message.value = 'Лендинг подготовлен. Заполните содержание и сохраните черновик.'
}
function addBlock() {
    if (controlsDisabled.value || !content.value) return
    const block = createLandingBlock(newBlockType.value)
    if (!block) return
    content.value.blocks ||= []
    content.value.blocks.push(block)
    expandedBlock.value = block.id
}
function moveBlock(index, direction) {
    if (controlsDisabled.value) return
    const target = index + direction, blocks = content.value?.blocks || []
    if (target < 0 || target >= blocks.length) return
    ;[blocks[index], blocks[target]] = [blocks[target], blocks[index]]
}
function removeBlock(index) { if (!controlsDisabled.value) content.value.blocks.splice(index, 1) }
function reset() {
    if (busy.value) return
    content.value = JSON.parse(baseline.value)
    error.value = ''; message.value = ''; conflict.value = false; conflictSnapshot.value = null
}
async function recoverConflict() {
    conflict.value = true; conflictSnapshot.value = null; resolving.value = true
    error.value = 'Лендинг изменён в другой карточке. Ваши изменения сохранены в этой форме. Загрузите актуальную версию или оставьте свою для следующего сохранения.'
    const version = identity
    try {
        const response = await axios.get(endpoint())
        if (!disposed && version === identity) conflictSnapshot.value = response.data.data
    } catch { if (!disposed && version === identity) error.value += ' Не удалось загрузить актуальную версию. Повторите получение версии.' }
    finally { if (version === identity) resolving.value = false }
}
function resolveConflict(keepDraft) {
    if (!conflictSnapshot.value || busy.value) return
    applyState(conflictSnapshot.value, keepDraft)
    conflict.value = false; conflictSnapshot.value = null; error.value = ''
    message.value = keepDraft ? 'Ваш вариант оставлен в форме. Проверьте его и сохраните черновик; он заменит текущий черновик.' : 'Загружена актуальная версия лендинга.'
}
async function mutate(action) {
    if (controlsDisabled.value || !content.value || isGood.value) return false
    if (action === 'save' && !dirty.value) return false
    if (action !== 'save' && (dirty.value || props.cardDirty || !hasSavedDraft.value)) return false
    saving.value = true; error.value = ''; message.value = ''
    const version = identity
    try {
        const payload = { version: state.value.version }
        if (action === 'save') payload.content = clone(content.value)
        const response = action === 'save' ? await axios.put(endpoint(), payload) : await axios.post(`${endpoint()}/${action}`, payload)
        if (disposed || version !== identity) return false
        applyState(response.data.data)
        message.value = action === 'save' ? 'Черновик сохранён.' : action === 'publish' ? 'Лендинг опубликован.' : 'Лендинг отключён. На сайте отображается обычная страница раздела.'
        unpublishOpen.value = false
        emit('saved')
        return true
    } catch (failure) {
        if (disposed || version !== identity) return false
        if (failure.response?.status === 409) await recoverConflict()
        else error.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || failure.message || 'Не удалось сохранить лендинг.'
        return false
    } finally { if (version === identity) saving.value = false }
}
async function uploadImage({ file, apply }) {
    if (controlsDisabled.value || !file) return false
    if (file.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) { error.value = 'Выберите JPG, PNG, WebP или GIF размером до 5 МБ.'; return false }
    const version = identity
    uploading.value = true; error.value = ''
    try {
        const body = new FormData(); body.append('image', file)
        const response = await axios.post(`${endpoint()}/image`, body)
        if (disposed || version !== identity) return false
        const url = response.data.url || response.data.data?.url
        if (!safeUrl(url)) throw new Error('Сервер не вернул адрес изображения.')
        uploading.value = false
        await nextTick()
        if (disposed || version !== identity) return false
        apply(url)
        message.value = 'Изображение загружено. Сохраните черновик, чтобы закрепить изменение.'
        return true
    } catch (failure) {
        if (!disposed && version === identity) error.value = failure.response?.data?.message || failure.message || 'Не удалось загрузить изображение.'
        return false
    } finally { if (version === identity) uploading.value = false }
}
watch(() => props.node?.id, () => {
    identity++; cancelReads(); state.value = null; content.value = null; baseline.value = 'null'; ready.value = false
    saving.value = false; uploading.value = false; resolving.value = false; conflict.value = false; conflictSnapshot.value = null
    error.value = ''; message.value = ''; templateKey.value = ''; expandedBlock.value = null; productOptions.value = []; goodOptions.value = []; search.value = ''; optionsError.value = ''; unpublishOpen.value = false
    load()
}, { immediate: true, flush: 'sync' })
watch(() => props.active, active => { if (!active) cancelReads(); else if (!ready.value) load(); else if (!isGood.value && !productOptions.value.length) loadOptions() })
onScopeDispose(() => { disposed = true; identity++; cancelReads() })
defineExpose({ reset, save: () => mutate('save') })
</script>

<template>
    <section class="catalog-landing" aria-label="Управление лендингом">
        <div v-if="loading" class="catalog-landing__loading" role="status"><v-progress-circular indeterminate size="22" width="2" /> Загрузка лендинга…</div>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</v-alert>
        <div v-if="conflict" class="catalog-landing__actions mb-4">
            <v-btn v-if="!conflictSnapshot" :loading="resolving" :disabled="busy" variant="outlined" @click="recoverConflict">Повторить получение версии</v-btn>
            <v-btn :disabled="!conflictSnapshot || busy" variant="outlined" @click="resolveConflict(false)">Загрузить актуальную версию</v-btn>
            <v-btn :disabled="!conflictSnapshot || busy" variant="outlined" @click="resolveConflict(true)">Оставить мой вариант</v-btn>
        </div>
        <v-btn v-if="!ready && !loading" variant="outlined" @click="load">Повторить загрузку</v-btn>
        <template v-if="ready">
            <div class="catalog-landing__status">
                <div><h3>{{ isGood ? 'Страница товара' : publicationLabel }}</h3><a v-if="publicUrl" :href="publicUrl" target="_blank" rel="noopener">{{ publicUrl }} <v-icon icon="mdi-open-in-new" size="14" /></a></div>
                <v-chip v-if="dirty" size="small" color="warning" variant="tonal">Есть несохранённые изменения</v-chip>
            </div>
            <v-alert v-if="!state.effective_visible" type="warning" variant="tonal" density="compact" class="mb-4">{{ visibilityMessage }}</v-alert>
            <v-alert v-if="message" type="success" variant="tonal" density="compact" class="mb-4" role="status">{{ message }}</v-alert>
            <div v-if="isGood" class="catalog-landing__good">
                <p>У товара одна страница на сайте. Изменения действуют во всех ветках, в которых он размещён.</p>
                <div class="catalog-landing__actions">
                    <v-btn prepend-icon="mdi-magnify" variant="outlined" @click="emit('navigate', 'seo')">Тексты, SEO и FAQ</v-btn>
                    <v-btn prepend-icon="mdi-image-multiple-outline" variant="outlined" @click="emit('navigate', 'media')">Фотографии и медиа</v-btn>
                    <v-btn prepend-icon="mdi-tag-outline" variant="outlined" @click="emit('navigate', 'prices')">Цены</v-btn>
                    <v-btn prepend-icon="mdi-package-variant-closed" variant="text" @click="emit('navigate', 'overview')">Название и публикация</v-btn>
                </div>
            </div>
            <template v-else>
                <p class="catalog-landing__hint">Адрес, Title и Description редактируются в <button type="button" @click="emit('navigate', 'overview')">основных данных карточки</button>.</p>
                <div v-if="!content" class="catalog-landing__create">
                    <p>Создайте лендинг для этой записи каталога. Сохранённый черновик можно проверить перед публикацией.</p>
                    <v-select v-model="templateKey" :items="state.templates || []" item-title="label" item-value="key" label="Шаблон страницы" variant="outlined" density="compact" :disabled="controlsDisabled" />
                    <v-btn prepend-icon="mdi-plus" color="#4d315e" variant="flat" :disabled="controlsDisabled || !templateKey" @click="create">Создать из шаблона</v-btn>
                </div>
                <template v-else>
                    <v-alert v-if="cardDirty" type="info" variant="tonal" density="compact" class="mb-4">Перед публикацией сохраните изменения основных данных карточки. Черновик лендинга можно сохранить сейчас. <v-btn variant="text" size="small" :disabled="controlsDisabled || dirty" @click="emit('save-card')">Сохранить карточку и продолжить</v-btn></v-alert>
                    <div class="catalog-landing__actions catalog-landing__toolbar">
                        <v-btn color="#4d315e" variant="flat" prepend-icon="mdi-content-save-outline" :loading="saving" :disabled="controlsDisabled || !dirty" @click="mutate('save')">Сохранить черновик</v-btn>
                        <v-btn :href="previewUrl" target="_blank" rel="noopener" variant="outlined" prepend-icon="mdi-eye-outline" :disabled="!hasSavedDraft || !previewUrl || busy">Предпросмотр сохранённого</v-btn>
                        <v-btn color="success" variant="outlined" prepend-icon="mdi-publish" :disabled="controlsDisabled || dirty || cardDirty || !hasSavedDraft || (published && !state.has_changes)" @click="mutate('publish')">{{ published ? 'Опубликовать изменения' : 'Опубликовать' }}</v-btn>
                        <v-btn v-if="published" variant="text" color="error" :disabled="controlsDisabled || dirty || cardDirty" @click="unpublishOpen = true">Отключить лендинг</v-btn>
                    </div>
                    <p v-if="dirty && hasSavedDraft" class="catalog-landing__hint">Предпросмотр показывает последний сохранённый черновик. Новые изменения появятся в нём после сохранения.</p>
                    <details class="catalog-landing__section" open><summary>Первый экран</summary><p class="catalog-landing__hint">Если в основных данных карточки заполнено SEO H1, он используется вместо заголовка первого экрана.</p><CatalogLandingFields v-model="content.hero" :fields="heroFields" context="hero" :links="textLinks" :disabled="controlsDisabled" @upload="uploadImage" /></details>
                    <details class="catalog-landing__section"><summary>Ассортимент</summary>
                        <CatalogLandingFields v-model="content.catalog" :fields="assortmentFields" :disabled="controlsDisabled" @upload="uploadImage" />
                        <template v-if="content.catalog?.mode === 'selection'">
                            <v-autocomplete v-model="content.catalog.source_product_ids" :items="sourceProducts" item-title="name" item-value="id" label="Источники ассортимента: продукты" multiple chips closable-chips variant="outlined" density="compact" :disabled="controlsDisabled" hint="Показываются опубликованные товары выбранных продуктов." persistent-hint class="mb-4" />
                            <v-autocomplete v-model="content.catalog.good_ids" :items="selectedGoods" item-title="name" item-value="id" label="Дополнительно выбранные товары" multiple chips closable-chips no-filter variant="outlined" density="compact" :loading="searching" :disabled="controlsDisabled" @update:search="queueSearch" @focus="searchGoods()" />
                        </template>
                        <p v-else class="catalog-landing__hint">Товары подбираются автоматически из этой ветки каталога.</p>
                        <v-autocomplete v-model="content.catalog.inline_good_ids" :items="selectedGoods" item-title="name" item-value="id" label="Товары для ссылок в текстах" hint="Ссылки доступны только на опубликованные товары из ассортимента этого лендинга." persistent-hint multiple chips closable-chips no-filter variant="outlined" density="compact" :loading="searching" :disabled="controlsDisabled" @update:search="queueSearch" @focus="searchGoods()" />
                        <v-alert v-if="optionsError" type="warning" variant="tonal" density="compact">{{ optionsError }} <v-btn size="small" variant="text" @click="loadOptions">Повторить</v-btn></v-alert>
                    </details>
                    <div class="catalog-landing__section">
                        <h3>Разделы страницы</h3>
                        <p class="catalog-landing__hint">Порядок разделов совпадает с порядком на сайте. Выключенный раздел сохраняется в черновике.</p>
                        <div v-for="(block, index) in content.blocks" :key="block.id" class="catalog-landing__block">
                            <div class="catalog-landing__block-heading">
                                <button type="button" :aria-expanded="expandedBlock === block.id" @click="expandedBlock = expandedBlock === block.id ? null : block.id"><v-icon :icon="expandedBlock === block.id ? 'mdi-chevron-down' : 'mdi-chevron-right'" size="18" /> {{ index + 1 }}. {{ block.title || blockTypes[block.type]?.label }}</button>
                                <v-switch v-model="block.enabled" label="Показывать" color="#4d315e" density="compact" hide-details :disabled="controlsDisabled" />
                                <v-btn icon="mdi-arrow-up" variant="text" size="small" :disabled="controlsDisabled || index === 0" :aria-label="`Поднять раздел ${block.title}`" @click="moveBlock(index, -1)" />
                                <v-btn icon="mdi-arrow-down" variant="text" size="small" :disabled="controlsDisabled || index === content.blocks.length - 1" :aria-label="`Опустить раздел ${block.title}`" @click="moveBlock(index, 1)" />
                                <v-btn icon="mdi-close" variant="text" size="small" color="error" :disabled="controlsDisabled" :aria-label="`Удалить раздел ${block.title}`" @click="removeBlock(index)" />
                            </div>
                            <div v-if="expandedBlock === block.id" class="catalog-landing__block-body">
                                <p class="catalog-landing__hint">{{ blockTypes[block.type]?.label }}</p>
                                <v-text-field v-model="block.title" label="Заголовок раздела" variant="outlined" density="compact" :disabled="controlsDisabled" />
                                <v-text-field v-model="block.navTitle" label="Название в навигации" hint="Оставьте пустым, чтобы скрыть ссылку на раздел из меню." persistent-hint variant="outlined" density="compact" class="mb-4" :disabled="controlsDisabled" />
                                <CatalogLandingFields v-model="block.data" :fields="blockTypes[block.type]?.fields || []" context="block" :links="textLinks" :disabled="controlsDisabled" @upload="uploadImage" />
                            </div>
                        </div>
                        <div class="catalog-landing__add"><v-select v-model="newBlockType" :items="blockOptions" label="Тип нового раздела" variant="outlined" density="compact" hide-details :disabled="controlsDisabled" /><v-btn prepend-icon="mdi-plus" variant="outlined" :disabled="controlsDisabled" @click="addBlock">Добавить раздел</v-btn></div>
                    </div>
                    <details class="catalog-landing__section"><summary>Обращение и кнопка связи</summary><CatalogLandingFields v-model="content.contact" :fields="contactFields" context="contact" :links="textLinks" :disabled="controlsDisabled" @upload="uploadImage" /></details>
                    <details class="catalog-landing__section"><summary>Источники и фотографии</summary><CatalogLandingFields v-model="content.sources" :fields="sourcesFields" context="sources" :links="textLinks" :disabled="controlsDisabled" @upload="uploadImage" /></details>
                </template>
            </template>
        </template>
        <v-dialog v-model="unpublishOpen" max-width="480" :persistent="busy"><v-card title="Отключить лендинг?"><v-card-text>По этому адресу будет отображаться обычная страница раздела. Содержание лендинга сохранится для повторной публикации.</v-card-text><v-card-actions><v-spacer /><v-btn :disabled="busy" @click="unpublishOpen = false">Отмена</v-btn><v-btn color="error" :loading="saving" :disabled="busy" @click="mutate('unpublish')">Отключить</v-btn></v-card-actions></v-card></v-dialog>
    </section>
</template>

<style scoped>
.catalog-landing { max-width: 1060px; margin-inline: auto; color: #4d4058; }
.catalog-landing h3 { font-size: 15px; margin: 0 0 10px; }
.catalog-landing__loading { display: flex; align-items: center; gap: 12px; padding: 36px; justify-content: center; }
.catalog-landing__status { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.catalog-landing__status a { font-size: 12px; overflow-wrap: anywhere; color: #806592; }
.catalog-landing__actions { display: flex; flex-wrap: wrap; gap: 8px; }
.catalog-landing__toolbar { position: sticky; top: -18px; padding: 14px 0; background: #fdfcfe; z-index: 2; }
.catalog-landing__hint { font-size: 12px; color: #887892; line-height: 1.7; margin: 0 0 16px; }
.catalog-landing__hint button { text-decoration: underline; color: #694f7b; }
.catalog-landing__create { max-width: 650px; padding: 24px 0; }
.catalog-landing__create > p, .catalog-landing__good > p { font-size: 14px; line-height: 1.7; margin-bottom: 22px; }
.catalog-landing__section { padding: 18px; border: 1px solid #e6e0eb; border-radius: 10px; margin-bottom: 16px; background: white; }
.catalog-landing__section > summary { cursor: pointer; font-size: 14px; font-weight: 600; }
.catalog-landing__section[open] > summary { margin-bottom: 22px; }
.catalog-landing__block { border: 1px solid #e6e0eb; border-radius: 8px; margin: 12px 0; }
.catalog-landing__block-heading { display: flex; align-items: center; gap: 4px; padding: 8px; background: #faf8fc; border-radius: 8px; }
.catalog-landing__block-heading > button:first-child { flex: 1; text-align: left; font-size: 13px; }
.catalog-landing__block-heading :deep(.v-switch) { flex: 0 0 auto; }
.catalog-landing__block-heading :deep(.v-label) { font-size: 11px; }
.catalog-landing__block-body { padding: 18px; }
.catalog-landing__add { display: flex; align-items: center; gap: 12px; margin-top: 20px; }
.catalog-landing__add :deep(.v-select) { flex: 1; }
@media (max-width: 700px) { .catalog-landing__status, .catalog-landing__add { align-items: stretch; flex-direction: column; } .catalog-landing__section { padding: 12px; } .catalog-landing__block-heading { flex-wrap: wrap; } .catalog-landing__block-heading > button:first-child { flex-basis: 100%; } .catalog-landing__toolbar { position: static; } }
</style>
