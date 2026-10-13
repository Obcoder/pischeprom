<script setup>
import { computed, onScopeDispose, reactive, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({ unit: { type: Object, default: null }, profile: { type: Object, default: null }, options: { type: Object, default: () => ({}) }, creating: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'saved'])
const form = reactive({}), selectedUnit = ref(null), mode = ref('existing'), newName = ref(''), search = ref(''), units = ref([])
const busy = ref(false), searching = ref(false), error = ref(''), searchError = ref(''), confirmingClose = ref(false), baseline = ref(''), createKey = ref('')
let generation = 0, searchVersion = 0, searchTimer = null, searchController = null, disposed = false
const message = failure => Object.values(failure.response?.data?.errors || {}).flat().join(' ') || failure.response?.data?.message || 'Не удалось сохранить профиль. Повторите попытку.'
const snapshot = () => JSON.stringify({ form, unit_id: selectedUnit.value?.id, mode: mode.value, name: newName.value })
const dirty = computed(() => snapshot() !== baseline.value)
const isNewProfile = computed(() => props.creating || !props.profile?.exists)
const canSave = computed(() => !busy.value && (isNewProfile.value ? mode.value === 'new' ? Boolean(newName.value.trim()) : Boolean(selectedUnit.value?.id) : Boolean(props.unit?.id)))
function initialize() {
    generation++; createKey.value = crypto.randomUUID(); error.value = ''; confirmingClose.value = false; mode.value = 'existing'; newName.value = ''; selectedUnit.value = props.unit ? { ...props.unit } : null
    const profile = props.profile || {}, isNew = !profile.exists
    Object.assign(form, { revision: profile.revision || 0, status: profile.status || 'active', priority: profile.priority || 'normal', is_watched: isNew ? true : Boolean(profile.is_watched), owner_user_id: profile.owner_user_id ?? profile.owner?.id ?? null, review_interval_days: profile.review_interval_days ?? 30, next_review_on: isNew ? props.options.today || '' : profile.next_review_on || '', notes: profile.notes || '' })
    baseline.value = snapshot()
    if (isNewProfile.value && !selectedUnit.value) loadUnits()
}
async function loadUnits() {
    clearTimeout(searchTimer); searchController?.abort()
    const version = ++searchVersion, good = generation
    searchController = new AbortController(); searching.value = true; searchError.value = ''
    try {
        const response = await axios.get('/api/supplier-order-units', { signal: searchController.signal, params: { search: search.value.trim() || undefined } })
        if (!disposed && version === searchVersion && good === generation) units.value = response.data || []
    } catch (failure) { if (!disposed && version === searchVersion && failure.code !== 'ERR_CANCELED') searchError.value = message(failure) }
    finally { if (!disposed && version === searchVersion) searching.value = false }
}
function searchUnits() {
    searchController?.abort(); searchVersion++; clearTimeout(searchTimer); searching.value = true
    searchTimer = setTimeout(loadUnits, 250)
}
function chooseUnit(unit) { selectedUnit.value = { id: unit.id, name: unit.name }; error.value = '' }
function requestClose() { if (busy.value) return; if (dirty.value) confirmingClose.value = true; else emit('close') }
function payload() {
    return { revision: form.revision, status: form.status, priority: form.priority, is_watched: Boolean(form.is_watched), owner_user_id: form.owner_user_id ? Number(form.owner_user_id) : null, review_interval_days: Number(form.review_interval_days), next_review_on: form.next_review_on || null, notes: form.notes || null }
}
async function save() {
    if (!canSave.value) return
    const version = generation
    busy.value = true; error.value = ''
    try {
        const response = isNewProfile.value
            ? await axios.post('/api/suppliers', { ...payload(), create_key: createKey.value, ...(mode.value === 'new' ? { name: newName.value.trim() } : { unit_id: selectedUnit.value.id }) })
            : await axios.patch(`/api/suppliers/${props.unit.id}/profile`, payload())
        if (disposed || version !== generation) return
        const result = isNewProfile.value ? response.data : { unit: props.unit, profile: response.data }
        baseline.value = snapshot(); emit('saved', result)
    } catch (failure) { if (!disposed && version === generation) error.value = message(failure) }
    finally { if (!disposed && version === generation) busy.value = false }
}
watch(() => [props.unit?.id, props.profile?.revision, props.creating], initialize, { immediate: true })
watch(search, searchUnits)
onScopeDispose(() => { disposed = true; generation++; searchVersion++; searchController?.abort(); clearTimeout(searchTimer) })
</script>

<template>
    <section class="supplier-profile-editor" aria-label="Профиль поставщика">
        <header><div><h2>{{ isNewProfile ? 'Добавить поставщика' : 'Контроль поставщика' }}</h2><p>{{ isNewProfile ? 'Выберите существующую организацию или создайте новую.' : unit?.name }}</p></div><button :disabled="busy" class="close" aria-label="Закрыть профиль поставщика" @click="requestClose">×</button></header>
        <div v-if="confirmingClose" class="confirmation"><span>Есть несохранённые изменения.</span><button :disabled="busy" @click="confirmingClose = false">Продолжить</button><button class="danger" :disabled="busy" @click="emit('close')">Закрыть без сохранения</button></div>
        <form @submit.prevent="save">
            <p v-if="error" class="error" role="alert">{{ error }}</p>
            <fieldset :disabled="busy">
                <template v-if="isNewProfile">
                    <div class="segments" role="group" aria-label="Способ добавления поставщика"><button type="button" :aria-pressed="mode === 'existing'" @click="mode = 'existing'; !units.length && !selectedUnit && loadUnits()">Существующий Unit</button><button type="button" :aria-pressed="mode === 'new'" @click="mode = 'new'">Новый поставщик</button></div>
                    <label v-if="mode === 'new'" class="field">Название нового поставщика<input v-model="newName" required maxlength="255" placeholder="Наименование организации"></label>
                    <div v-else class="unit-picker">
                        <div v-if="selectedUnit" class="selected-unit"><strong>{{ selectedUnit.name }}</strong><span>Unit #{{ selectedUnit.id }}</span><button type="button" @click="selectedUnit = null; loadUnits()">Выбрать другого</button></div>
                        <template v-else><label class="field">Поиск Unit<input v-model="search" type="search" maxlength="150" placeholder="Название организации" @keydown.enter.prevent="loadUnits()"></label><p v-if="searchError" class="error" role="alert">{{ searchError }} <button type="button" @click="loadUnits">Повторить</button></p><p v-if="searching" class="muted" role="status">Поиск…</p><div v-else class="unit-results"><button v-for="item in units" :key="item.id" type="button" @click="chooseUnit(item)">{{ item.name }}<span>#{{ item.id }}</span></button><p v-if="!units.length" class="muted">Организации не найдены. Измените поиск или создайте нового поставщика.</p></div></template>
                    </div>
                </template>
                <div class="fields-grid">
                    <label class="field">Статус<select v-model="form.status"><option v-for="item in options.statuses || [{ value: 'active', label: 'Активный' }, { value: 'potential', label: 'Потенциальный' }, { value: 'paused', label: 'Приостановлен' }, { value: 'archived', label: 'Архив' }]" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                    <label class="field">Приоритет<select v-model="form.priority"><option v-for="item in options.priorities || [{ value: 'low', label: 'Низкий' }, { value: 'normal', label: 'Обычный' }, { value: 'high', label: 'Высокий' }, { value: 'critical', label: 'Критический' }]" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                    <label class="field full">Ответственный<select v-model="form.owner_user_id"><option :value="null">Не назначен</option><option v-for="owner in options.owners || []" :key="owner.id" :value="owner.id">{{ owner.name }}</option></select></label>
                    <label class="check full"><input v-model="form.is_watched" type="checkbox">На регулярном контроле</label>
                    <label class="field">Проверять каждые, дней<input v-model="form.review_interval_days" type="number" min="1" max="365" step="1" required></label>
                    <label class="field">Следующая проверка<input v-model="form.next_review_on" type="date"></label>
                    <p v-if="['paused', 'archived'].includes(form.status)" class="muted full">Для этого статуса напоминание о просроченной проверке приостановлено.</p>
                    <label class="field full">Рабочая заметка<textarea v-model="form.notes" rows="4" maxlength="10000" placeholder="Условия работы, договорённости, что проверить"></textarea></label>
                </div>
            </fieldset>
            <footer><span>{{ dirty ? 'Есть изменения' : 'Профиль поставщика' }}</span><button type="button" :disabled="busy" @click="requestClose">Отмена</button><button class="primary" :disabled="!canSave">{{ busy ? 'Сохраняем…' : isNewProfile ? 'Добавить поставщика' : 'Сохранить изменения' }}</button></footer>
        </form>
    </section>
</template>

<style scoped>
.supplier-profile-editor { background: #fff; color: #493f53; font: 12px/1.45 -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; border: 1px solid #d9d1e2; border-radius: 7px; max-height: 91vh; overflow: auto; }header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid #e1dbe8; background: #f8f6fb; }h2 { margin: 0 0 3px; font-size: 16px; font-weight: 600; }p { margin: 0; }header p { color: #8d7e9a; font-size: 12px; }.close { border: 0; background: none; font-size: 24px; min-height: 30px; padding: 0 8px; }form { padding: 12px 16px 0; }fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }.segments { display: flex; border: 1px solid #dbd3e5; border-radius: 5px; padding: 3px; background: #efeaf5; gap: 3px; margin-bottom: 12px; }.segments button { flex: 1; border: 0; background: transparent; }.segments button[aria-pressed=true] { background: #fff; box-shadow: 0 1px 3px #49315613; color: #5b436d; }.field { display: grid; gap: 5px; font-size: 12px; color: #81708f; }.fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 13px; }.full { grid-column: 1 / -1; }.check { display: flex; align-items: center; gap: 7px; }.selected-unit { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 9px; border: 1px solid #ded6e7; background: #faf8fd; border-radius: 4px; }.selected-unit strong { flex: 1; font-weight: 600; }.selected-unit span { color: #998ba6; }.unit-results { border: 1px solid #e4ddeb; border-radius: 4px; max-height: 185px; overflow: auto; margin-top: 7px; }.unit-results > button { display: flex; justify-content: space-between; align-items: center; gap: 8px; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #eee8f3; border-radius: 0; padding: 8px; white-space: normal; }.unit-results span { color: #a294ad; }.unit-results p { padding: 10px; }.muted { color: #9b8ba7; font-size: 12px; }.error { color: #a74759; background: #fff2f5; padding: 9px; border: 1px solid #efd4db; border-radius: 4px; margin-bottom: 9px; }.confirmation { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; padding: 10px 14px; color: #926b39; background: #fff8ed; }footer { display: flex; gap: 7px; align-items: center; justify-content: flex-end; padding: 12px 0; border-top: 1px solid #e5deec; margin-top: 13px; }footer span { flex: 1; color: #a091ac; font-size: 12px; }button, input, select, textarea { font: inherit; }button { cursor: pointer; min-height: 30px; padding: 5px 10px; border: 1px solid #d9d0e2; border-radius: 4px; background: #fff; color: #756181; white-space: nowrap; }button:hover:enabled { background: #f0ebf7; }button:disabled { opacity: .45; cursor: default; }.primary { background: #796591; border-color: #796591; color: white; }.primary:hover:enabled { background: #68577f; }.danger { color: #ab455b; }input:not([type=checkbox]), select, textarea { width: 100%; min-width: 0; border: 1px solid #dcd3e6; border-radius: 4px; min-height: 32px; padding: 5px 8px; background: #fff; color: #4e405a; box-sizing: border-box; }textarea { resize: vertical; }input[type=checkbox] { width: 14px; height: 14px; accent-color: #8d70aa; }button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 2px solid #b096ca; outline-offset: 2px; }@media(max-width:550px) { .fields-grid { grid-template-columns: 1fr; }form { padding-inline: 10px; }header { padding: 11px; }footer { flex-wrap: wrap; }footer span { flex-basis: 100%; } }
</style>
