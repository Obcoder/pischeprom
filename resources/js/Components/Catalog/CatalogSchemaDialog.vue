<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'

const open = defineModel({ type: Boolean, default: false })
const props = defineProps({ levels: { type: Array, default: () => [] } })
const emit = defineEmits(['changed'])
const selectedLevelId = ref(null)
const saving = ref(false)
const error = ref('')
const errors = ref({})
const levelEditor = ref(false)
const fieldEditor = ref(false)
const levelForm = reactive({ id: null, name: '', sort_order: 0, display_mode: 'tree', is_domain: false })
const fieldForm = reactive({})
const baseline = ref('')
const discardTarget = ref(null)
const deleteTarget = ref(null)
const selectedLevel = computed(() => props.levels.find(level => level.id === selectedLevelId.value))
const displayModes = [
    { title: 'Ряд вкладок', value: 'tabs', icon: 'mdi-tab', description: 'Компактный ряд над деревом и таблицей. Удобно для основных разделов.' },
    { title: 'Иерархический список', value: 'tree', icon: 'mdi-file-tree-outline', description: 'Раскрывающиеся ветки в левой части. Подходит для подробной классификации.' },
    { title: 'Таблица объектов', value: 'list', icon: 'mdi-format-list-bulleted', description: 'Конечные записи справа. Если у записи есть подуровни, её ветка остаётся слева.' },
]
const fieldTypes = [
    { title: 'Текст', value: 'text' }, { title: 'Многострочный текст', value: 'textarea' },
    { title: 'Число', value: 'number' }, { title: 'Да / нет', value: 'boolean' },
    { title: 'Дата', value: 'date' }, { title: 'Ссылка', value: 'url' }, { title: 'Список вариантов', value: 'select' },
]
const displayFor = level => displayModes.find(mode => mode.value === (level.display_mode || (level.is_domain ? 'tabs' : 'tree')))
const fieldErrors = key => errors.value[key] || []
watch(open, value => {
    if (value && !selectedLevel.value) selectedLevelId.value = props.levels[0]?.id ?? null
    if (value) { error.value = ''; errors.value = {} }
})
function editLevel(level = null) {
    Object.assign(levelForm, {
        id: level?.id ?? null, name: level?.name || '', sort_order: level?.sort_order ?? 0,
        display_mode: level?.display_mode || (level?.is_domain ? 'tabs' : 'tree'), is_domain: level?.is_domain ?? false,
    })
    baseline.value = JSON.stringify(levelForm)
    levelEditor.value = true; error.value = ''; errors.value = {}
}
function editField(field = null) {
    Object.assign(fieldForm, {
        id: field?.id ?? null, level_id: selectedLevelId.value, key: field?.key || '', label: field?.label || '',
        type: field?.type || 'text', required: field?.required || false, is_public: field?.is_public || false,
        options_text: (field?.options || []).join('\n'), sort_order: field?.sort_order ?? 0,
    })
    baseline.value = JSON.stringify(fieldForm)
    fieldEditor.value = true; error.value = ''; errors.value = {}
}
function closeEditor(kind) {
    if (saving.value) return
    if (JSON.stringify(kind === 'level' ? levelForm : fieldForm) !== baseline.value) { discardTarget.value = kind; return }
    if (kind === 'level') levelEditor.value = false
    else fieldEditor.value = false
    error.value = ''; errors.value = {}
}
function discardEditor() {
    if (discardTarget.value === 'level') levelEditor.value = false
    else fieldEditor.value = false
    discardTarget.value = null; error.value = ''; errors.value = {}
}
async function mutate(action) {
    if (saving.value) return
    saving.value = true; error.value = ''; errors.value = {}
    try { await action(); emit('changed') }
    catch (failure) {
        errors.value = failure.response?.data?.errors || {}
        error.value = Object.values(errors.value).flat().join(' ') || failure.response?.data?.message || 'Не удалось сохранить изменения.'
    } finally { saving.value = false }
}
async function saveLevel() {
    await mutate(async () => {
        const { id, ...payload } = levelForm
        const { data } = id ? await axios.patch(`/api/catalog/levels/${id}`, payload) : await axios.post('/api/catalog/levels', payload)
        selectedLevelId.value = data.data.id
        levelEditor.value = false
    })
}
async function saveField() {
    await mutate(async () => {
        const { id, options_text, ...payload } = fieldForm
        payload.options = payload.type === 'select' ? [...new Set(options_text.split('\n').map(value => value.trim()).filter(Boolean))] : []
        if (id) await axios.patch(`/api/catalog/fields/${id}`, payload)
        else await axios.post('/api/catalog/fields', payload)
        fieldEditor.value = false
    })
}
async function remove() {
    const target = deleteTarget.value
    await mutate(async () => {
        await axios.delete(`/api/catalog/${target.kind}/${target.id}`)
        if (target.kind === 'levels') selectedLevelId.value = props.levels.find(level => level.id !== target.id)?.id ?? null
        deleteTarget.value = null
    })
}
function confirmDelete(target) { error.value = ''; errors.value = {}; deleteTarget.value = target }
</script>

<template>
    <v-dialog v-model="open" max-width="1380" scrollable :persistent="saving">
        <v-card class="schema-dialog">
            <v-card-title class="schema-dialog__heading"><div><h2>Уровни и поля классификации</h2><p>Задайте названия, способ отображения и свойства каждого уровня.</p></div><v-btn icon="mdi-close" variant="text" :disabled="saving" aria-label="Закрыть настройки" @click="open = false" /></v-card-title>
            <v-divider />
            <v-alert v-if="error && !levelEditor && !fieldEditor && !deleteTarget" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
            <v-card-text class="schema-dialog__body">
                <aside class="schema-dialog__levels">
                    <div class="schema-dialog__section-heading"><strong>Уровни <span class="schema-dialog__count">{{ levels.length }}</span></strong><v-btn icon="mdi-plus" size="small" variant="text" aria-label="Добавить уровень" @click="editLevel()" /></div>
                    <v-list density="comfortable" class="schema-dialog__level-list">
                        <v-list-item v-for="level in levels" :key="level.id" :active="selectedLevelId === level.id" :title="level.name" :subtitle="(level.is_domain ? 'Домены · ' : '') + displayFor(level)?.title" :prepend-icon="level.is_domain ? 'mdi-view-dashboard-outline' : displayFor(level)?.icon" rounded="lg" @click="selectedLevelId = level.id" />
                    </v-list>
                    <v-btn block variant="outlined" prepend-icon="mdi-plus" class="mt-3" @click="editLevel()">Создать уровень</v-btn>
                    <p class="schema-dialog__hint">Все уровни необязательны. Каждой записи можно назначить любой уровень или оставить её без уровня.</p>
                </aside>
                <div class="schema-dialog__fields">
                    <template v-if="selectedLevel">
                        <div class="schema-dialog__section-heading schema-dialog__selected-heading"><div><h3>{{ selectedLevel.name }}</h3><p>{{ selectedLevel.fields?.length || 0 }} дополнительных полей</p></div><div class="schema-dialog__heading-actions"><v-btn prepend-icon="mdi-pencil-outline" variant="tonal" size="small" @click="editLevel(selectedLevel)">Изменить уровень</v-btn><v-btn icon="mdi-delete-outline" variant="text" size="small" aria-label="Удалить уровень" @click="confirmDelete({ kind: 'levels', id: selectedLevel.id, name: selectedLevel.name })" /></div></div>
                        <div class="schema-dialog__overview">
                            <div class="schema-dialog__base"><v-icon :icon="displayFor(selectedLevel)?.icon" size="24" /><div><strong>{{ displayFor(selectedLevel)?.title }}</strong><p>{{ selectedLevel.is_domain ? 'Домены — высший уровень. По умолчанию показаны рядом вкладок.' : 'Способ представления этого уровня в каталоге.' }}</p></div></div>
                            <div class="schema-dialog__base"><v-icon icon="mdi-card-text-outline" size="24" /><div><strong>Общие свойства доступны всегда</strong><p>Название, аватар, описание, SEO, публикация и витрина.</p></div></div>
                        </div>
                        <div class="schema-dialog__section-heading"><strong>Дополнительные поля</strong><v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="editField()">Добавить поле</v-btn></div>
                        <div v-if="selectedLevel.fields?.length" class="schema-dialog__field-table">
                            <div class="schema-dialog__field schema-dialog__field--head"><span>Название и код</span><span>Тип значения</span><span>Настройки</span><span /></div>
                            <div v-for="field in selectedLevel.fields" :key="field.id" class="schema-dialog__field">
                                <div><strong>{{ field.label }}</strong><p>{{ field.key }}</p></div>
                                <span class="schema-dialog__field-type">{{ fieldTypes.find(type => type.value === field.type)?.title }}</span>
                                <div class="schema-dialog__badges"><v-chip v-if="field.required" size="x-small" variant="tonal">Обязательное</v-chip><v-chip v-if="field.is_public" size="x-small" variant="tonal" color="success">На сайте</v-chip><span v-if="!field.required && !field.is_public">—</span></div>
                                <div class="schema-dialog__field-actions"><v-btn icon="mdi-pencil-outline" variant="text" size="small" :aria-label="`Изменить поле ${field.label}`" @click="editField(field)" /><v-btn icon="mdi-delete-outline" variant="text" size="small" :aria-label="`Удалить поле ${field.label}`" @click="confirmDelete({ kind: 'fields', id: field.id, name: field.label })" /></div>
                            </div>
                        </div>
                        <div v-else class="schema-dialog__empty"><v-icon icon="mdi-form-textbox" size="32" /><strong>Добавьте свойства этого уровня</strong><p>Например: сорт, стандарт качества, страна происхождения или срок хранения.</p><v-btn variant="text" prepend-icon="mdi-plus" @click="editField()">Создать первое поле</v-btn></div>
                    </template>
                    <div v-else class="schema-dialog__empty"><v-icon icon="mdi-file-tree-outline" size="36" /><strong>Создайте первый уровень</strong><p>Глубина классификации и названия уровней зависят от вашей продукции.</p><v-btn variant="tonal" prepend-icon="mdi-plus" @click="editLevel()">Добавить уровень</v-btn></div>
                </div>
            </v-card-text>
            <v-divider /><v-card-actions class="schema-dialog__actions"><p>Конечные записи всегда показаны в таблице справа.</p><v-spacer /><v-btn variant="flat" color="#4d315e" :disabled="saving" @click="open = false">Готово</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog :model-value="levelEditor" max-width="1080" :persistent="saving" scrollable @update:model-value="value => { if (!value) closeEditor('level') }">
        <v-card class="schema-dialog">
            <v-card-title class="schema-dialog__heading"><div><h2>{{ levelForm.id ? 'Изменить уровень' : 'Новый уровень классификации' }}</h2><p>Выберите представление, удобное для этой части каталога.</p></div><v-btn icon="mdi-close" variant="text" :disabled="saving" aria-label="Закрыть редактор уровня" @click="closeEditor('level')" /></v-card-title>
            <v-divider />
            <v-card-text class="schema-dialog__editor-body">
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-5">{{ error }}</v-alert>
                <v-form id="catalog-level-form" :disabled="saving" @submit.prevent="saveLevel">
                    <div class="schema-dialog__editor-grid">
                        <section><h3>Основные настройки</h3>
                            <v-text-field v-model="levelForm.name" label="Название уровня *" variant="outlined" density="compact" required maxlength="255" :error-messages="fieldErrors('name')" />
                            <v-text-field v-model.number="levelForm.sort_order" label="Порядок отображения" type="number" min="0" max="1000000" variant="outlined" density="compact" :error-messages="fieldErrors('sort_order')" />
                            <div class="schema-dialog__domain"><v-switch v-model="levelForm.is_domain" label="Высший уровень — домены" color="#4d315e" density="compact" :error-messages="fieldErrors('is_domain')" @update:model-value="value => { if (value) levelForm.display_mode = 'tabs' }" /><p>Домены — высший уровень. По умолчанию показаны первым рядом вкладок. Записи этого уровня располагаются в корне каталога.</p></div>
                            <p class="schema-dialog__hint">Одна ветка может содержать больше уровней, чем другая. Назначайте уровень каждой записи независимо от соседних веток.</p>
                        </section>
                        <section><h3>Представление в интерфейсе</h3>
                            <v-radio-group v-model="levelForm.display_mode" :disabled="saving" :error-messages="fieldErrors('display_mode')" hide-details="auto">
                                <label v-for="mode in displayModes" :key="mode.value" class="schema-dialog__mode" :class="{ 'is-active': levelForm.display_mode === mode.value }">
                                    <v-radio :value="mode.value" :aria-label="mode.title" color="#4d315e" /><v-icon :icon="mode.icon" size="24" /><div><strong>{{ mode.title }}</strong><p>{{ mode.description }}</p></div>
                                </label>
                            </v-radio-group>
                        </section>
                    </div>
                </v-form>
            </v-card-text><v-divider /><v-card-actions class="schema-dialog__actions"><v-spacer /><v-btn :disabled="saving" @click="closeEditor('level')">Отмена</v-btn><v-btn type="submit" form="catalog-level-form" color="#4d315e" variant="flat" :loading="saving">Сохранить уровень</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog :model-value="fieldEditor" max-width="1080" :persistent="saving" scrollable @update:model-value="value => { if (!value) closeEditor('field') }">
        <v-card class="schema-dialog">
            <v-card-title class="schema-dialog__heading"><div><h2>{{ fieldForm.id ? 'Изменить поле' : 'Новое поле' }}</h2><p>Свойство уровня «{{ selectedLevel?.name }}»</p></div><v-btn icon="mdi-close" variant="text" :disabled="saving" aria-label="Закрыть редактор поля" @click="closeEditor('field')" /></v-card-title><v-divider />
            <v-card-text class="schema-dialog__editor-body">
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-5">{{ error }}</v-alert>
                <v-form id="catalog-field-form" :disabled="saving" @submit.prevent="saveField">
                    <div class="schema-dialog__editor-grid">
                        <section><h3>Название и значение</h3>
                            <v-text-field v-model="fieldForm.label" label="Название поля *" variant="outlined" density="compact" required maxlength="255" :error-messages="fieldErrors('label')" />
                            <v-text-field v-model="fieldForm.key" label="Код поля *" variant="outlined" density="compact" required maxlength="80" :disabled="Boolean(fieldForm.id) || saving" :error-messages="fieldErrors('key')" hint="Латинские буквы, цифры и подчёркивание: shelf_life" persistent-hint class="mb-4" />
                            <v-select v-model="fieldForm.type" :items="fieldTypes" label="Тип значения" variant="outlined" density="compact" :error-messages="fieldErrors('type')" />
                            <v-textarea v-if="fieldForm.type === 'select'" v-model="fieldForm.options_text" label="Варианты · по одному на строке" variant="outlined" density="compact" rows="5" :error-messages="fieldErrors('options')" />
                        </section>
                        <section><h3>Заполнение и отображение</h3>
                            <v-text-field v-model.number="fieldForm.sort_order" label="Порядок поля в карточке" type="number" min="0" max="1000000" variant="outlined" density="compact" :error-messages="fieldErrors('sort_order')" />
                            <div class="schema-dialog__field-settings"><v-switch v-model="fieldForm.required" label="Обязательное поле" color="#4d315e" density="compact" hide-details /><p>Значение нужно заполнить при сохранении записи этого уровня.</p></div>
                            <div class="schema-dialog__field-settings"><v-switch v-model="fieldForm.is_public" label="Показывать на сайте" color="#4d315e" density="compact" hide-details /><p>Значение доступно на публичной странице опубликованной записи.</p></div>
                        </section>
                    </div>
                </v-form>
            </v-card-text><v-divider /><v-card-actions class="schema-dialog__actions"><v-spacer /><v-btn :disabled="saving" @click="closeEditor('field')">Отмена</v-btn><v-btn type="submit" form="catalog-field-form" color="#4d315e" variant="flat" :loading="saving">Сохранить поле</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog :model-value="Boolean(deleteTarget)" max-width="520" :persistent="saving" @update:model-value="value => { if (!value) deleteTarget = null }"><v-card title="Удалить из структуры?">
        <v-card-text><v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</v-alert>«{{ deleteTarget?.name }}» будет удалено.<p class="mt-3">{{ deleteTarget?.kind === 'fields' ? 'Значения этого свойства будут удалены у всех записей уровня.' : 'Можно удалить только пустой уровень. Сначала назначьте его записям другой уровень или оставьте их без уровня.' }}</p></v-card-text>
        <v-card-actions><v-spacer /><v-btn :disabled="saving" @click="deleteTarget = null">Отмена</v-btn><v-btn color="error" :loading="saving" @click="remove">Удалить</v-btn></v-card-actions>
    </v-card></v-dialog>
    <v-dialog :model-value="Boolean(discardTarget)" max-width="480" @update:model-value="value => { if (!value) discardTarget = null }"><v-card title="Есть несохранённые изменения"><v-card-text>Закрыть редактор и отменить изменения?</v-card-text><v-card-actions><v-spacer /><v-btn @click="discardTarget = null">Продолжить</v-btn><v-btn color="error" @click="discardEditor">Отменить изменения</v-btn></v-card-actions></v-card></v-dialog>
</template>

<style scoped>
.schema-dialog { border-radius: 14px !important; }
.schema-dialog__heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 24px; white-space: normal; }
.schema-dialog__heading h2 { font-size: 19px; }
.schema-dialog__heading p { font-size: 12px; color: #8a7f92; margin-top: 5px; }
.schema-dialog__body { display: grid; grid-template-columns: 290px minmax(0, 1fr); padding: 0 !important; min-height: 520px; }
.schema-dialog__levels { border-right: 1px solid #e7e1ed; background: #faf8fc; padding: 16px; }
.schema-dialog__level-list { padding: 0; background: transparent; }
.schema-dialog__level-list :deep(.v-list-item) { margin-bottom: 4px; }
.schema-dialog__level-list :deep(.v-list-item-title) { font-size: 13px; font-weight: 550; }
.schema-dialog__level-list :deep(.v-list-item-subtitle) { font-size: 11px; margin-top: 4px; }
.schema-dialog__level-list :deep(.v-list-item__prepend .v-icon) { margin-inline-end: 12px; font-size: 20px; }
.schema-dialog__fields { min-width: 0; padding: 18px 26px 26px; }
.schema-dialog__section-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; min-height: 46px; font-size: 13px; }
.schema-dialog__section-heading h3 { font-size: 20px; }
.schema-dialog__selected-heading { margin-bottom: 16px; }
.schema-dialog__selected-heading p { font-size: 11px; color: #8a7f92; margin-top: 4px; }
.schema-dialog__heading-actions { display: flex; align-items: center; gap: 6px; }
.schema-dialog__count { color: #8a7f92; margin-left: 4px; font-size: 11px; font-weight: 400; }
.schema-dialog__hint { color: #8a7f92; font-size: 12px; line-height: 1.8; padding: 16px 4px; }
.schema-dialog__overview { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 24px; }
.schema-dialog__base { display: flex; gap: 12px; align-items: flex-start; background: #f9f7fc; border: 1px solid #e7e1ed; border-radius: 10px; padding: 16px; font-size: 12px; }
.schema-dialog__base > .v-icon { color: #8e7c9b; }
.schema-dialog__base p { color: #8a7f92; margin-top: 6px; font-size: 11px; line-height: 1.6; }
.schema-dialog__field-table { margin-top: 10px; border: 1px solid #e9e3ee; border-radius: 10px; overflow: hidden; }
.schema-dialog__field { display: grid; grid-template-columns: minmax(140px, 1.5fr) minmax(100px, 1fr) minmax(90px, 1fr) 76px; gap: 12px; align-items: center; padding: 10px 14px; border-bottom: 1px solid #eeeaf1; font-size: 12px; }
.schema-dialog__field:last-child { border: 0; }
.schema-dialog__field--head { background: #faf8fc; color: #8a7f92; font-size: 11px; }
.schema-dialog__field p { color: #8a7f92; font-size: 11px; margin-top: 3px; }
.schema-dialog__field strong { overflow-wrap: anywhere; }
.schema-dialog__field-actions { display: flex; justify-content: flex-end; }
.schema-dialog__badges { display: flex; flex-wrap: wrap; gap: 4px; color: #b0a6b8; }
.schema-dialog__empty { display: flex; flex-direction: column; align-items: center; gap: 12px; color: #958a9f; font-size: 12px; line-height: 1.7; padding: 54px 28px; text-align: center; }
.schema-dialog__empty strong { color: #695875; font-size: 15px; font-weight: 550; }
.schema-dialog__actions { padding: 12px 24px; }
.schema-dialog__actions p { font-size: 11px; color: #8a7f92; }
.schema-dialog__editor-body { padding: 26px !important; }
.schema-dialog__editor-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 32px; }
.schema-dialog__editor-grid h3 { font-size: 13px; color: #665370; margin-bottom: 22px; font-weight: 650; }
.schema-dialog__domain { padding: 0 16px 16px; border: 1px solid #e7e1ed; border-radius: 10px; background: #faf8fc; }
.schema-dialog__domain p, .schema-dialog__field-settings p { font-size: 11px; line-height: 1.7; color: #8a7f92; }
.schema-dialog__field-settings { padding: 0 16px 16px; border: 1px solid #e7e1ed; border-radius: 10px; margin-bottom: 14px; }
.schema-dialog__mode { display: flex; align-items: center; gap: 12px; padding: 14px 16px 14px 8px; border: 1px solid #e7e1ed; border-radius: 10px; margin-bottom: 12px; cursor: pointer; }
.schema-dialog__mode.is-active { background: #f5eff9; border-color: #a48cb6; }
.schema-dialog__mode > .v-icon { color: #8e7c9b; }
.schema-dialog__mode > .v-radio { flex: 0 0 auto; }
.schema-dialog__mode strong { font-size: 13px; }
.schema-dialog__mode p { font-size: 11px; color: #8a7f92; line-height: 1.6; margin-top: 5px; }
@media (max-width: 1050px) { .schema-dialog__body { grid-template-columns: 250px minmax(0, 1fr); } .schema-dialog__overview { grid-template-columns: 1fr; } .schema-dialog__field { grid-template-columns: minmax(100px, 1fr) 110px 76px; } .schema-dialog__field > :nth-child(3) { display: none; } }
@media (max-width: 720px) { .schema-dialog__body, .schema-dialog__editor-grid { grid-template-columns: 1fr; } .schema-dialog__levels { border-right: 0; border-bottom: 1px solid #e7e1ed; } .schema-dialog__level-list { max-height: 210px; overflow: auto; } .schema-dialog__selected-heading { align-items: flex-start; flex-direction: column; } .schema-dialog__fields, .schema-dialog__editor-body { padding: 18px !important; } .schema-dialog__actions p { display: none; } .schema-dialog__field { grid-template-columns: minmax(100px, 1fr) 76px; } .schema-dialog__field > :nth-child(2) { display: none; } }
</style>
