<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    chat: { type: Object, default: null },
    crm: { type: Object, default: () => ({ entity: null, orders: [] }) },
    standalone: { type: Boolean, default: false },
})

const emit = defineEmits(['notice', 'error', 'insert', 'sent'])

const loading = ref(false)
const saving = ref(false)
const sending = ref(false)
const previewLoading = ref(false)
const templates = ref([])
const meta = ref({ categories: [], variables: [], message_limit: 1000 })
const view = ref('list')
const selectedTemplate = ref(null)
const preview = ref(null)
const goods = ref([])
const goodsSearch = ref('')
const goodsLoading = ref(false)
const filters = reactive({ search: '', category: null, favorites: false })
const context = reactive({ order_id: null, good_id: null, telephone_id: null, building_id: null })
const editor = reactive({ id: null, name: '', category: 'general', body: '', is_active: true, is_favorite: false, sort_order: 0 })
let goodsTimer = null
let previewTimer = null
let previewSequence = 0

const filteredTemplates = computed(() => {
    const search = filters.search.trim().toLocaleLowerCase('ru-RU')

    return templates.value.filter((template) => {
        if (filters.category && template.category !== filters.category) return false
        if (filters.favorites && !template.is_favorite) return false
        if (!search) return true

        return `${template.name} ${template.body} ${template.category_label}`.toLocaleLowerCase('ru-RU').includes(search)
    })
})
const variableGroups = computed(() => {
    const groups = new Map()
    meta.value.variables.forEach((variable) => {
        if (!groups.has(variable.group)) groups.set(variable.group, [])
        groups.get(variable.group).push(variable)
    })

    return [...groups.entries()].map(([name, items]) => ({ name, items }))
})
const unresolvedVariables = computed(() => (preview.value?.unresolved || []).map((key) => ({
    key,
    label: meta.value.variables.find((item) => item.key === key)?.label || key,
})))
const canSendPreview = computed(() => selectedTemplate.value?.is_active
    && preview.value?.text
    && preview.value?.within_limit
    && !unresolvedVariables.value.length
    && !sending.value)
const entityPhones = computed(() => props.crm?.entity?.telephones || [])
const entityBuildings = computed(() => props.crm?.entity?.buildings || [])
const chatOrders = computed(() => props.crm?.orders || [])

watch(() => props.chat?.id, async () => {
    resetForChat()
    await loadTemplates()
}, { immediate: true })

watch(() => [context.order_id, context.good_id, context.telephone_id, context.building_id], () => {
    schedulePreview()
})

watch(goodsSearch, () => {
    clearTimeout(goodsTimer)
    goodsTimer = setTimeout(searchGoods, 260)
})

async function loadTemplates() {
    loading.value = true
    try {
        const { data } = await axios.get('/api/avito/messenger/templates')
        templates.value = data.items || []
        meta.value = data.meta || meta.value
        if (selectedTemplate.value) {
            selectedTemplate.value = templates.value.find((item) => item.id === selectedTemplate.value.id) || null
        }
    } catch (exception) {
        fail(exception, 'Не удалось загрузить шаблоны сообщений.')
    } finally {
        loading.value = false
    }
}

function open() {
    view.value = 'list'
    if (!templates.value.length) loadTemplates()
}

async function beginUse(template) {
    if (!props.chat?.id) {
        startEdit(template)
        return
    }

    selectedTemplate.value = template
    view.value = 'use'
    preview.value = null
    context.order_id = chatOrders.value[0]?.id || null
    context.good_id = null
    context.telephone_id = entityPhones.value[0]?.id || null
    context.building_id = entityBuildings.value[0]?.id || null
    if (template.placeholders?.some((key) => key.startsWith('good_'))) await searchGoods()
    await loadPreview()
}

function startCreate() {
    Object.assign(editor, {
        id: null,
        name: '',
        category: filters.category || 'general',
        body: '',
        is_active: true,
        is_favorite: false,
        sort_order: templates.value.length * 10 + 10,
    })
    view.value = 'edit'
}

function startEdit(template) {
    selectedTemplate.value = template
    Object.assign(editor, {
        id: template.id,
        name: template.name,
        category: template.category,
        body: template.body,
        is_active: template.is_active,
        is_favorite: template.is_favorite,
        sort_order: template.sort_order,
    })
    view.value = 'edit'
}

function duplicateTemplate(template) {
    startEdit(template)
    selectedTemplate.value = null
    editor.id = null
    editor.name = `${template.name} — копия`
}

async function saveTemplate() {
    if (!editor.name.trim() || !editor.body.trim()) return
    saving.value = true
    try {
        const payload = {
            name: editor.name.trim(),
            category: editor.category,
            body: editor.body.trim(),
            is_active: editor.is_active,
            is_favorite: editor.is_favorite,
            sort_order: Number(editor.sort_order) || 0,
        }
        const { data } = editor.id
            ? await axios.put(`/api/avito/messenger/templates/${editor.id}`, payload)
            : await axios.post('/api/avito/messenger/templates', payload)
        notify(data.message)
        await loadTemplates()
        const savedTemplate = templates.value.find((item) => item.id === data.template.id) || data.template
        if (props.chat?.id) {
            await beginUse(savedTemplate)
        } else {
            selectedTemplate.value = null
            view.value = 'list'
        }
    } catch (exception) {
        fail(exception, 'Не удалось сохранить шаблон.')
    } finally {
        saving.value = false
    }
}

async function deleteTemplate(template) {
    if (!window.confirm(`Удалить шаблон «${template.name}»? История уже отправленных сообщений сохранится.`)) return
    saving.value = true
    try {
        const { data } = await axios.delete(`/api/avito/messenger/templates/${template.id}`)
        notify(data.message)
        view.value = 'list'
        selectedTemplate.value = null
        await loadTemplates()
    } catch (exception) {
        fail(exception, 'Не удалось удалить шаблон.')
    } finally {
        saving.value = false
    }
}

async function toggleTemplate(template, field) {
    try {
        const { data } = await axios.patch(`/api/avito/messenger/templates/${template.id}`, {
            [field]: !template[field],
        })
        const index = templates.value.findIndex((item) => item.id === template.id)
        if (index >= 0) templates.value[index] = data.template
        if (selectedTemplate.value?.id === template.id) selectedTemplate.value = data.template
    } catch (exception) {
        fail(exception, 'Не удалось изменить шаблон.')
    }
}

function appendVariable(key) {
    editor.body = `${editor.body}${editor.body && !editor.body.endsWith('\n') ? ' ' : ''}${placeholderText(key)}`
}

function placeholderText(key) {
    return `{{${key}}}`
}

async function searchGoods() {
    if (!props.chat?.id) return
    goodsLoading.value = true
    try {
        const { data } = await axios.get('/api/avito/messenger/crm/goods', {
            params: { search: goodsSearch.value || undefined },
        })
        goods.value = data.items || []
    } catch (exception) {
        fail(exception, 'Не удалось загрузить товары для шаблона.')
    } finally {
        goodsLoading.value = false
    }
}

function schedulePreview() {
    clearTimeout(previewTimer)
    previewTimer = setTimeout(loadPreview, 180)
}

async function loadPreview() {
    if (!props.chat?.id || !selectedTemplate.value?.id || view.value !== 'use') return
    const sequence = ++previewSequence
    previewLoading.value = true
    try {
        const { data } = await axios.post(
            `/api/avito/messenger/chats/${props.chat.id}/message-templates/${selectedTemplate.value.id}/preview`,
            contextPayload(),
        )
        if (sequence === previewSequence) preview.value = data.preview
    } catch (exception) {
        if (sequence === previewSequence) fail(exception, 'Не удалось сформировать предпросмотр шаблона.')
    } finally {
        if (sequence === previewSequence) previewLoading.value = false
    }
}

function insertIntoComposer() {
    if (!preview.value?.text || !selectedTemplate.value?.is_active) return
    emit('insert', {
        text: preview.value.text,
        template_id: selectedTemplate.value.id,
        template_name: selectedTemplate.value.name,
        context: preview.value.context,
    })
    notify(`Шаблон «${selectedTemplate.value.name}» вставлен в редактор.`)
}

async function sendDirect() {
    if (!canSendPreview.value) return
    sending.value = true
    try {
        const { data } = await axios.post(
            `/api/avito/messenger/chats/${props.chat.id}/message-templates/${selectedTemplate.value.id}/send`,
            contextPayload(),
        )
        notify(data.message)
        emit('sent', data.item)
        const index = templates.value.findIndex((item) => item.id === data.template.id)
        if (index >= 0) templates.value[index] = data.template
        selectedTemplate.value = data.template
        preview.value = data.preview
    } catch (exception) {
        fail(exception, 'Avito не принял сообщение по шаблону.')
    } finally {
        sending.value = false
    }
}

function contextPayload() {
    return Object.fromEntries(Object.entries(context).filter(([, value]) => value !== null && value !== ''))
}

function resetForChat() {
    view.value = 'list'
    selectedTemplate.value = null
    preview.value = null
    goods.value = []
    goodsSearch.value = ''
    Object.assign(context, { order_id: null, good_id: null, telephone_id: null, building_id: null })
}

function formatDate(value) {
    if (!value) return 'ещё не использовался'
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
}

function formatMoney(value, currency = 'RUB') {
    if (value === null || value === undefined) return '—'
    return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(Number(value))} ${currency}`
}

function notify(message) {
    emit('notice', message)
}

function fail(exception, fallback) {
    emit('error', exception?.response?.data?.message || fallback)
}

defineExpose({ open, reload: loadTemplates })

onBeforeUnmount(() => {
    clearTimeout(goodsTimer)
    clearTimeout(previewTimer)
})
</script>

<template>
    <section class="template-panel" :class="{ 'is-standalone': standalone }">
        <template v-if="view === 'list'">
            <header class="template-toolbar">
                <div><strong>Шаблоны сообщений</strong><span>{{ templates.length }} всего · {{ templates.filter(item => item.is_active).length }} активных</span></div>
                <v-btn icon="mdi-plus" color="deep-purple-lighten-1" size="x-small" variant="tonal" title="Новый шаблон" @click="startCreate" />
            </header>
            <v-text-field v-model="filters.search" prepend-inner-icon="mdi-magnify" placeholder="Название или текст" density="compact" variant="outlined" hide-details clearable :loading="loading" />
            <div class="template-filters">
                <v-select v-model="filters.category" :items="meta.categories" item-title="label" item-value="value" placeholder="Все категории" density="compact" variant="outlined" hide-details clearable />
                <v-btn :icon="filters.favorites ? 'mdi-star' : 'mdi-star-outline'" :color="filters.favorites ? 'amber' : undefined" size="small" variant="text" title="Только избранные" @click="filters.favorites = !filters.favorites" />
            </div>
            <div class="template-list">
                <article v-for="template in filteredTemplates" :key="template.id" class="template-card" :class="{ 'is-inactive': !template.is_active }">
                    <button type="button" class="template-card__body" :disabled="!template.is_active" @click="beginUse(template)">
                        <span><b>{{ template.category_label }}</b><i v-if="!template.is_active">Выключен</i></span>
                        <strong>{{ template.name }}</strong>
                        <p>{{ template.body }}</p>
                        <small><v-icon icon="mdi-send-clock-outline" size="10" />{{ template.usage_count }} · {{ formatDate(template.last_used_at) }}</small>
                    </button>
                    <footer>
                        <v-btn :icon="template.is_favorite ? 'mdi-star' : 'mdi-star-outline'" :color="template.is_favorite ? 'amber' : undefined" size="x-small" variant="text" title="Избранное" @click="toggleTemplate(template, 'is_favorite')" />
                        <v-btn :icon="template.is_active ? 'mdi-eye-outline' : 'mdi-eye-off-outline'" size="x-small" variant="text" title="Включить или выключить" @click="toggleTemplate(template, 'is_active')" />
                        <v-btn icon="mdi-content-copy" size="x-small" variant="text" title="Создать копию" @click="duplicateTemplate(template)" />
                        <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" title="Редактировать" @click="startEdit(template)" />
                    </footer>
                </article>
                <div v-if="!filteredTemplates.length && !loading" class="template-empty"><v-icon icon="mdi-text-box-remove-outline" size="30" /><strong>Шаблонов не найдено</strong><span>Создайте первый шаблон или измените фильтры.</span><v-btn size="x-small" variant="tonal" @click="startCreate">Создать</v-btn></div>
            </div>
        </template>

        <template v-else-if="view === 'use' && selectedTemplate">
            <header class="template-toolbar">
                <v-btn icon="mdi-arrow-left" size="x-small" variant="text" @click="view = 'list'" />
                <div><strong>{{ selectedTemplate.name }}</strong><span>{{ selectedTemplate.category_label }} · {{ selectedTemplate.usage_count }} отправок</span></div>
                <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" title="Редактировать" @click="startEdit(selectedTemplate)" />
            </header>
            <div class="template-context-form">
                <span class="template-caption">Контекст подстановки</span>
                <v-select v-if="chatOrders.length" v-model="context.order_id" :items="chatOrders" :item-title="item => `${item.number} · ${formatMoney(item.total_amount, item.currency_code)}`" item-value="id" label="Заказ" density="compact" variant="outlined" hide-details clearable />
                <v-autocomplete v-model="context.good_id" v-model:search="goodsSearch" :items="goods" item-title="name" item-value="id" label="Товар" density="compact" variant="outlined" hide-details clearable no-filter :loading="goodsLoading" />
                <div class="template-context-grid">
                    <v-select v-model="context.telephone_id" :items="entityPhones" item-title="number" item-value="id" label="Телефон" density="compact" variant="outlined" hide-details clearable :disabled="!entityPhones.length" />
                    <v-select v-model="context.building_id" :items="entityBuildings" item-title="label" item-value="id" label="Адрес" density="compact" variant="outlined" hide-details clearable :disabled="!entityBuildings.length" />
                </div>
            </div>
            <div class="template-preview" :class="{ 'is-loading': previewLoading }">
                <header><span>Предпросмотр</span><b :class="{ 'is-over': preview && !preview.within_limit }">{{ preview?.length || 0 }}/{{ meta.message_limit }}</b></header>
                <v-progress-linear v-if="previewLoading" indeterminate color="deep-purple-accent-1" height="2" />
                <pre>{{ preview?.text || 'Формируем сообщение…' }}</pre>
                <div v-if="unresolvedVariables.length" class="unresolved-box"><strong><v-icon icon="mdi-alert-circle-outline" size="13" />Нужен контекст</strong><span v-for="variable in unresolvedVariables" :key="variable.key" :title="`{{${variable.key}}}`">{{ variable.label }}</span><small>Можно вставить текст и заменить переменные вручную. Мгновенная отправка заблокирована.</small></div>
                <div v-if="preview && !preview.within_limit" class="limit-warning"><v-icon icon="mdi-text-long" size="13" />Сократите сообщение до {{ meta.message_limit }} символов.</div>
            </div>
            <div class="template-use-actions">
                <v-btn size="small" variant="tonal" prepend-icon="mdi-file-document-edit-outline" :disabled="!preview?.text || !selectedTemplate.is_active" @click="insertIntoComposer">В редактор</v-btn>
                <v-btn size="small" color="deep-purple-lighten-1" prepend-icon="mdi-send" :loading="sending" :disabled="!canSendPreview" @click="sendDirect">Отправить</v-btn>
            </div>
        </template>

        <template v-else-if="view === 'edit'">
            <header class="template-toolbar">
                <v-btn icon="mdi-arrow-left" size="x-small" variant="text" @click="view = editor.id && selectedTemplate && chat?.id ? 'use' : 'list'" />
                <div><strong>{{ editor.id ? 'Редактирование' : 'Новый шаблон' }}</strong><span>Локальная библиотека Avito CRM</span></div>
                <v-btn v-if="editor.id" icon="mdi-delete-outline" color="error" size="x-small" variant="text" title="Удалить" @click="deleteTemplate({ id: editor.id, name: editor.name })" />
            </header>
            <div class="template-editor">
                <v-text-field v-model="editor.name" label="Название" density="compact" variant="outlined" hide-details maxlength="160" />
                <div class="template-context-grid"><v-select v-model="editor.category" :items="meta.categories" item-title="label" item-value="value" label="Категория" density="compact" variant="outlined" hide-details /><v-text-field v-model="editor.sort_order" type="number" min="0" label="Порядок" density="compact" variant="outlined" hide-details /></div>
                <v-textarea v-model="editor.body" label="Текст сообщения" rows="7" auto-grow density="compact" variant="outlined" hide-details maxlength="1000" counter />
                <div class="editor-switches"><v-checkbox v-model="editor.is_active" label="Активен" density="compact" hide-details /><v-checkbox v-model="editor.is_favorite" label="Избранный" density="compact" hide-details /></div>
                <div class="variable-library">
                    <span class="template-caption">Переменные · нажмите для вставки</span>
                    <div v-for="group in variableGroups" :key="group.name"><strong>{{ group.name }}</strong><button v-for="variable in group.items" :key="variable.key" type="button" :title="variable.label" @click="appendVariable(variable.key)" v-text="placeholderText(variable.key)" /></div>
                </div>
                <div class="editor-hint"><v-icon icon="mdi-shield-check-outline" size="14" /><span>Подстановка выполняется на сервере. Неизвестные или пустые переменные будут показаны до отправки.</span></div>
                <v-btn block color="deep-purple-lighten-1" size="small" prepend-icon="mdi-content-save-outline" :loading="saving" :disabled="!editor.name.trim() || !editor.body.trim()" @click="saveTemplate">Сохранить шаблон</v-btn>
            </div>
        </template>
    </section>
</template>

<style scoped>
.template-panel { display: grid; min-height: 100%; align-content: start; gap: 7px; padding: 9px; color: #e9ebff; }
.template-toolbar { display: flex; min-height: 32px; align-items: center; gap: 5px; }.template-toolbar > div { min-width: 0; flex: 1; }.template-toolbar strong, .template-toolbar span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.template-toolbar strong { font-size: 11px; }.template-toolbar span { margin-top: 2px; color: #858baa; font-size: 8px; }
.template-filters { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px; }.template-panel :deep(.v-field) { font-size: 10px; }.template-panel :deep(.v-field__input) { min-height: 34px; padding-top: 4px; padding-bottom: 4px; }.template-panel :deep(.v-label) { font-size: 10px; }
.template-list { display: grid; align-content: start; gap: 5px; }.template-card { display: grid; grid-template-columns: minmax(0, 1fr) 28px; overflow: hidden; border: 1px solid #333750; border-radius: 8px; background: #1b1e35; }.template-card:hover { border-color: #5b5184; }.template-card.is-inactive { opacity: .62; }.template-card__body { min-width: 0; padding: 7px 8px; color: inherit; text-align: left; border: 0; background: transparent; cursor: pointer; }.template-card__body:disabled { cursor: default; }.template-card__body > span { display: flex; align-items: center; justify-content: space-between; gap: 5px; }.template-card__body b, .template-card__body i { color: #a895ef; font-size: 7px; font-style: normal; text-transform: uppercase; }.template-card__body i { color: #db9aa5; }.template-card__body strong { display: block; margin-top: 3px; font-size: 10px; }.template-card__body p { display: -webkit-box; overflow: hidden; margin: 3px 0; color: #9ca2c0; font-size: 8px; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }.template-card__body small { display: flex; align-items: center; gap: 3px; color: #6f7698; font-size: 7px; }.template-card > footer { display: grid; align-content: center; border-left: 1px solid #2e324a; background: #171a2f; }
.template-empty { display: grid; min-height: 180px; place-items: center; align-content: center; gap: 5px; color: #858baa; text-align: center; border: 1px dashed #3a3e56; border-radius: 8px; }.template-empty strong { color: #dfe2f8; font-size: 10px; }.template-empty span { font-size: 8px; }
.template-context-form, .template-editor { display: grid; gap: 6px; }.template-caption { color: #858baa; font-size: 7px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }.template-context-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
.template-preview { overflow: hidden; border: 1px solid #343850; border-radius: 8px; background: #101326; transition: opacity .15s; }.template-preview.is-loading { opacity: .72; }.template-preview > header { display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; color: #9281df; font-size: 7px; font-weight: 800; text-transform: uppercase; border-bottom: 1px solid #2d3148; }.template-preview header b { color: #7f86a6; }.template-preview header b.is-over { color: #e69aa8; }.template-preview pre { min-height: 100px; margin: 0; padding: 9px; color: #eef0ff; font: 10px/1.45 Inter, sans-serif; white-space: pre-wrap; word-break: break-word; }
.unresolved-box { display: flex; flex-wrap: wrap; gap: 3px; padding: 7px; border-top: 1px solid rgba(225, 157, 82, .25); background: rgba(112, 72, 29, .18); }.unresolved-box strong, .unresolved-box small { width: 100%; }.unresolved-box strong { display: flex; align-items: center; gap: 3px; color: #e9bd86; font-size: 8px; }.unresolved-box span { padding: 2px 4px; color: #f0cfaa; font-size: 7px; border-radius: 4px; background: rgba(126, 84, 36, .45); }.unresolved-box small { color: #aa9278; font-size: 7px; line-height: 1.35; }.limit-warning { display: flex; align-items: center; gap: 4px; padding: 6px 8px; color: #e3a2ad; font-size: 8px; border-top: 1px solid rgba(196, 83, 104, .25); background: rgba(108, 40, 54, .18); }.template-use-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
.editor-switches { display: grid; grid-template-columns: 1fr 1fr; }.editor-switches :deep(.v-selection-control) { min-height: 28px; }.variable-library { display: grid; gap: 5px; padding: 7px; border: 1px solid #343850; border-radius: 8px; background: #171a2e; }.variable-library > div { display: flex; flex-wrap: wrap; gap: 3px; }.variable-library strong { width: 100%; color: #858baa; font-size: 7px; }.variable-library button { padding: 2px 4px; color: #c7baf8; font: 6px/1.2 ui-monospace, monospace; border: 1px solid rgba(145, 119, 231, .25); border-radius: 4px; background: rgba(95, 72, 172, .16); cursor: pointer; }.variable-library button:hover { background: rgba(95, 72, 172, .32); }.editor-hint { display: grid; grid-template-columns: auto 1fr; gap: 5px; color: #8e95b4; font-size: 7px; line-height: 1.4; }.editor-hint .v-icon { color: #79c5a3; }
.template-panel.is-standalone { min-height: calc(100vh - 390px); padding: 16px; }.template-panel.is-standalone .template-list { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }.template-panel.is-standalone .template-editor, .template-panel.is-standalone .template-context-form, .template-panel.is-standalone .template-preview, .template-panel.is-standalone .template-use-actions { width: min(760px, 100%); }.template-panel.is-standalone .template-card__body p { -webkit-line-clamp: 3; }
</style>
