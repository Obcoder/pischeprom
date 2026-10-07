<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'

const open = defineModel({ type: Boolean, default: false })
const props = defineProps({ levels: { type: Array, default: () => [] } })
const emit = defineEmits(['changed'])
const selectedLevelId = ref(null)
const saving = ref(false)
const error = ref('')
const levelEditor = ref(false)
const fieldEditor = ref(false)
const levelForm = reactive({ id: null, name: '', entity_type: 'custom', sort_order: 0 })
const fieldForm = reactive({})
const deleteTarget = ref(null)
const selectedLevel = computed(() => props.levels.find(level => level.id === selectedLevelId.value))
const kindTitles = { category: 'Категории', product: 'Продукты', good: 'Товары', custom: 'Свой раздел' }
const fieldTypes = [
    { title: 'Текст', value: 'text' }, { title: 'Многострочный текст', value: 'textarea' },
    { title: 'Число', value: 'number' }, { title: 'Да / нет', value: 'boolean' },
    { title: 'Дата', value: 'date' }, { title: 'Ссылка', value: 'url' }, { title: 'Список вариантов', value: 'select' },
]
watch(open, value => {
    if (value && !selectedLevel.value) selectedLevelId.value = props.levels[0]?.id ?? null
    if (value) error.value = ''
})
function editLevel(level = null) {
    Object.assign(levelForm, { id: level?.id || null, name: level?.name || '', entity_type: level?.entity_type || 'custom', sort_order: level?.sort_order || 0 })
    levelEditor.value = true; error.value = ''
}
function editField(field = null) {
    Object.assign(fieldForm, {
        id: field?.id || null, level_id: selectedLevelId.value, key: field?.key || '', label: field?.label || '',
        type: field?.type || 'text', required: field?.required || false, is_public: field?.is_public || false,
        options_text: (field?.options || []).join('\n'), sort_order: field?.sort_order || 0,
    })
    fieldEditor.value = true; error.value = ''
}
async function mutate(action) {
    if (saving.value) return
    saving.value = true; error.value = ''
    try { await action(); emit('changed') }
    catch (failure) { error.value = Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось сохранить изменения.' }
    finally { saving.value = false }
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
</script>

<template>
    <v-dialog v-model="open" max-width="1040" scrollable :persistent="saving">
        <v-card class="schema-dialog">
            <v-card-title class="schema-dialog__heading"><div><h2>Уровни и поля</h2><p>Создавайте классификации и задавайте свойства записей каждого уровня.</p></div><v-btn icon="mdi-close" variant="text" :disabled="saving" aria-label="Закрыть настройки" @click="open = false" /></v-card-title>
            <v-divider />
            <v-alert v-if="error && !levelEditor && !fieldEditor && !deleteTarget" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
            <v-card-text class="schema-dialog__body">
                <aside class="schema-dialog__levels">
                    <div class="schema-dialog__section-heading"><strong>Уровни классификации</strong><v-btn icon="mdi-plus" size="small" variant="text" aria-label="Добавить уровень" @click="editLevel()" /></div>
                    <v-list density="compact" class="pa-0">
                        <v-list-item v-for="level in levels" :key="level.id" :active="selectedLevelId === level.id" :title="level.name" :subtitle="kindTitles[level.entity_type]" @click="selectedLevelId = level.id" />
                    </v-list>
                    <p class="schema-dialog__hint">Уровень определяет набор свойств. Вложенность и порядок записей меняются в дереве каталога.</p>
                </aside>
                <div class="schema-dialog__fields">
                    <template v-if="selectedLevel">
                        <div class="schema-dialog__section-heading"><h3>{{ selectedLevel.name }}</h3><div><v-btn icon="mdi-pencil-outline" variant="text" size="small" aria-label="Изменить уровень" @click="editLevel(selectedLevel)" /><v-btn icon="mdi-delete-outline" variant="text" size="small" aria-label="Удалить уровень" @click="deleteTarget = { kind: 'levels', id: selectedLevel.id, name: selectedLevel.name }" /></div></div>
                        <div class="schema-dialog__base"><v-icon icon="mdi-card-text-outline" size="20" /><div><strong>Основные свойства</strong><p>Название, аватар, описание, SEO, публикация и витрина доступны у каждой записи.</p></div></div>
                        <div class="schema-dialog__section-heading"><strong>Дополнительные поля</strong><v-btn size="small" variant="tonal" prepend-icon="mdi-plus" @click="editField()">Добавить поле</v-btn></div>
                        <div v-for="field in selectedLevel.fields" :key="field.id" class="schema-dialog__field">
                            <div><strong>{{ field.label }}<span v-if="field.required"> *</span></strong><p>{{ fieldTypes.find(type => type.value === field.type)?.title }} · {{ field.key }}<span v-if="field.is_public"> · На сайте</span></p></div>
                            <div><v-btn icon="mdi-pencil-outline" variant="text" size="small" :aria-label="`Изменить поле ${field.label}`" @click="editField(field)" /><v-btn icon="mdi-delete-outline" variant="text" size="small" :aria-label="`Удалить поле ${field.label}`" @click="deleteTarget = { kind: 'fields', id: field.id, name: field.label }" /></div>
                        </div>
                        <p v-if="!selectedLevel.fields?.length" class="schema-dialog__empty">Дополнительных полей пока нет. Например, добавьте сорт, стандарт качества или срок хранения.</p>
                    </template>
                    <p v-else class="schema-dialog__empty">Создайте или выберите уровень классификации.</p>
                </div>
            </v-card-text>
            <v-divider /><v-card-actions><v-spacer /><v-btn :disabled="saving" @click="open = false">Готово</v-btn></v-card-actions>
        </v-card>
    </v-dialog>
    <v-dialog v-model="levelEditor" max-width="480" :persistent="saving"><v-card :title="levelForm.id ? 'Изменить уровень' : 'Новый уровень'">
        <v-card-text><v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</v-alert>
            <v-form id="catalog-level-form" @submit.prevent="saveLevel">
                <v-text-field v-model="levelForm.name" label="Название уровня *" variant="outlined" density="compact" required maxlength="255" :disabled="saving" />
                <v-select v-model="levelForm.entity_type" :items="Object.entries(kindTitles).map(([value, title]) => ({ value, title }))" label="Тип записей" variant="outlined" density="compact" :disabled="true" hint="Свой раздел — произвольный уровень. Остальные типы создают записи соответствующего справочника." persistent-hint />
                <v-text-field v-model.number="levelForm.sort_order" label="Порядок уровня в списке" type="number" variant="outlined" density="compact" class="mt-5" :disabled="saving" />
            </v-form>
        </v-card-text><v-card-actions><v-spacer /><v-btn :disabled="saving" @click="levelEditor = false">Отмена</v-btn><v-btn type="submit" form="catalog-level-form" :loading="saving">Сохранить</v-btn></v-card-actions>
    </v-card></v-dialog>
    <v-dialog v-model="fieldEditor" max-width="560" :persistent="saving" scrollable><v-card :title="fieldForm.id ? 'Изменить поле' : 'Новое поле'">
        <v-card-text><v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</v-alert>
            <v-form id="catalog-field-form" @submit.prevent="saveField">
                <v-text-field v-model="fieldForm.label" label="Название поля *" variant="outlined" density="compact" required :disabled="saving" />
                <v-text-field v-model="fieldForm.key" label="Код поля *" variant="outlined" density="compact" required :disabled="Boolean(fieldForm.id) || saving" hint="Латинские буквы, цифры и нижнее подчёркивание: shelf_life" persistent-hint class="mb-4" />
                <v-select v-model="fieldForm.type" :items="fieldTypes" label="Тип значения" variant="outlined" density="compact" :disabled="saving" />
                <v-textarea v-if="fieldForm.type === 'select'" v-model="fieldForm.options_text" label="Варианты · по одному на строке" variant="outlined" rows="4" :disabled="saving" />
                <v-text-field v-model.number="fieldForm.sort_order" label="Порядок поля" type="number" variant="outlined" density="compact" :disabled="saving" />
                <v-checkbox v-model="fieldForm.required" label="Обязательное поле" density="compact" hide-details :disabled="saving" />
                <v-checkbox v-model="fieldForm.is_public" label="Показывать значение на публичной странице" density="compact" hide-details :disabled="saving" />
            </v-form>
        </v-card-text><v-card-actions><v-spacer /><v-btn :disabled="saving" @click="fieldEditor = false">Отмена</v-btn><v-btn type="submit" form="catalog-field-form" :loading="saving">Сохранить</v-btn></v-card-actions>
    </v-card></v-dialog>
    <v-dialog :model-value="Boolean(deleteTarget)" max-width="460" :persistent="saving" @update:model-value="value => { if (!value) deleteTarget = null }"><v-card title="Удалить из структуры?">
        <v-card-text><v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</v-alert>«{{ deleteTarget?.name }}» будет удалено.<p class="mt-3">{{ deleteTarget?.kind === 'fields' ? 'Значения этого свойства будут удалены у всех записей уровня.' : 'Можно удалить только уровень без записей. Системные уровни защищены.' }}</p></v-card-text>
        <v-card-actions><v-spacer /><v-btn :disabled="saving" @click="deleteTarget = null">Отмена</v-btn><v-btn color="error" :loading="saving" @click="remove">Удалить</v-btn></v-card-actions>
    </v-card></v-dialog>
</template>

<style scoped>
.schema-dialog__heading { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; white-space: normal; }
.schema-dialog__heading h2 { font-size: 18px; }
.schema-dialog__heading p { font-size: 12px; color: #8a7f92; margin-top: 4px; }
.schema-dialog__body { display: grid; grid-template-columns: 260px minmax(0, 1fr); padding: 0 !important; min-height: 360px; }
.schema-dialog__levels { border-right: 1px solid #e7e1ed; background: #faf8fc; padding: 12px; }
.schema-dialog__fields { padding: 12px 22px 24px; }
.schema-dialog__section-heading { display: flex; justify-content: space-between; align-items: center; gap: 8px; min-height: 46px; font-size: 13px; }
.schema-dialog__section-heading h3 { font-size: 16px; }
.schema-dialog__hint, .schema-dialog__empty { color: #8a7f92; font-size: 12px; line-height: 1.8; padding: 14px 4px; }
.schema-dialog__base { display: flex; gap: 12px; background: #f7f4fa; border: 1px solid #e7e1ed; padding: 14px; margin: 8px 0 16px; font-size: 12px; }
.schema-dialog__base p { color: #8a7f92; margin-top: 4px; }
.schema-dialog__field { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eeeaf1; font-size: 13px; }
.schema-dialog__field p { color: #8a7f92; font-size: 11px; margin-top: 3px; }
@media (max-width: 680px) { .schema-dialog__body { grid-template-columns: 1fr; } .schema-dialog__levels { border-right: 0; border-bottom: 1px solid #e7e1ed; } }
</style>
