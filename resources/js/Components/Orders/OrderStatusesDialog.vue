<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
    modelValue: Boolean,
    permissions: { type: Object, default: () => ({ create: true, edit: true, delete: true }) },
})
const emit = defineEmits(['update:modelValue', 'changed'])

const statuses = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const listError = ref('')
const formError = ref('')
const deleteError = ref('')
const fieldErrors = ref({})
const editingStatus = ref(null)
const formVisible = ref(false)
const deleteTarget = ref(null)
const form = ref(emptyForm())
const mutating = computed(() => saving.value || deleting.value)
const isSystem = computed(() => Boolean(editingStatus.value?.is_system))
const closingFlagLocked = computed(() => isSystem.value || Number(editingStatus.value?.orders_count) > 0)
let controller = null
let requestId = 0
let disposed = false
let pendingChange = false

function emptyForm() {
    return { name: '', code: '', color: '#64748b', sort_order: 0, is_closed: false }
}

function cancelLoad() {
    requestId += 1
    controller?.abort()
    controller = null
    loading.value = false
}

function updateDialog(value) {
    if (!value && mutating.value) return
    if (!value) cancelLoad()
    emit('update:modelValue', value)
}

async function loadStatuses() {
    if (disposed || !props.modelValue) return
    cancelLoad()
    const currentRequest = requestId
    const requestController = new AbortController()
    controller = requestController
    loading.value = true
    listError.value = ''
    const isCurrent = () => !disposed && props.modelValue
        && currentRequest === requestId && !requestController.signal.aborted

    try {
        const { data } = await axios.get('/api/order-statuses', { signal: requestController.signal })
        if (!isCurrent()) return
        statuses.value = Array.isArray(data.data) ? data.data : []
        if (pendingChange) {
            pendingChange = false
            emit('changed', statuses.value)
        }
    } catch (failure) {
        if (!isCurrent() || axios.isCancel(failure)) return
        listError.value = pendingChange
            ? 'Изменения сохранены. Не удалось обновить список статусов — повторите загрузку.'
            : 'Не удалось загрузить статусы. Попробуйте ещё раз.'
    } finally {
        if (isCurrent()) {
            loading.value = false
            controller = null
        }
    }
}

function resetForm() {
    editingStatus.value = null
    formVisible.value = false
    form.value = emptyForm()
    fieldErrors.value = {}
    formError.value = ''
}

function createStatus() {
    if (!props.permissions.create || mutating.value || loading.value) return
    resetForm()
    form.value.sort_order = Math.min(65535, Math.max(0, ...statuses.value.map(status => Number(status.sort_order) || 0)) + 10)
    formVisible.value = true
}

function editStatus(status) {
    if (!props.permissions.edit || mutating.value || loading.value) return
    resetForm()
    editingStatus.value = status
    form.value = {
        name: status.name,
        code: status.code,
        color: status.color || '',
        sort_order: status.sort_order,
        is_closed: Boolean(status.is_closed),
    }
    formVisible.value = true
}

function validateForm() {
    const errors = {}
    if (!form.value.name.trim()) errors.name = ['Введите название статуса.']
    else if (form.value.name.trim().length > 64) errors.name = ['Не более 64 символов.']
    if (!isSystem.value && !/^[a-z][a-z0-9_-]{0,31}$/.test(form.value.code.trim())) {
        errors.code = ['До 32 латинских букв, цифр, _ или -. Начните с буквы.']
    }
    if (form.value.color.trim() && !/^#(?:[a-f\d]{3}|[a-f\d]{6})$/i.test(form.value.color.trim())) {
        errors.color = ['Укажите цвет в формате #RGB или #RRGGBB.']
    }
    const order = Number(form.value.sort_order)
    if (form.value.sort_order === '' || form.value.sort_order === null || !Number.isInteger(order) || order < 0 || order > 65535) {
        errors.sort_order = ['Введите целое число от 0 до 65535.']
    }
    fieldErrors.value = errors
    return Object.keys(errors).length === 0
}

async function saveStatus() {
    if (editingStatus.value ? !props.permissions.edit : !props.permissions.create) return
    if (mutating.value || loading.value || !formVisible.value || !validateForm()) return
    saving.value = true
    formError.value = ''
    const payload = {
        name: form.value.name.trim(),
        color: form.value.color.trim() || null,
        sort_order: Number(form.value.sort_order),
    }
    if (!isSystem.value) payload.code = form.value.code.trim()
    if (!closingFlagLocked.value) payload.is_closed = Boolean(form.value.is_closed)

    try {
        if (editingStatus.value) await axios.put(`/api/order-statuses/${editingStatus.value.id}`, payload)
        else await axios.post('/api/order-statuses', payload)
        if (disposed) return
        pendingChange = true
        resetForm()
        await loadStatuses()
    } catch (failure) {
        if (disposed) return
        fieldErrors.value = failure.response?.data?.errors || {}
        formError.value = failure.response?.status === 422
            ? 'Проверьте поля формы.'
            : failure.response?.data?.message || 'Не удалось сохранить статус. Попробуйте ещё раз.'
    } finally {
        if (!disposed) saving.value = false
    }
}

function deletionReason(status) {
    if (!props.permissions.delete) return 'Нет права на удаление статусов'
    if (status.is_system) return 'Системный статус нельзя удалить'
    if (Number(status.orders_count) > 0) return 'Сначала переведите заказы в другой статус'
    return ''
}

function requestDelete(status) {
    if (mutating.value || loading.value || deletionReason(status)) return
    deleteError.value = ''
    deleteTarget.value = status
}

function cancelDelete() {
    if (deleting.value) return
    deleteTarget.value = null
    deleteError.value = ''
}

async function deleteStatus() {
    if (!deleteTarget.value || mutating.value || deletionReason(deleteTarget.value)) return
    deleting.value = true
    deleteError.value = ''
    const targetId = deleteTarget.value.id
    try {
        await axios.delete(`/api/order-statuses/${targetId}`)
        if (disposed) return
        pendingChange = true
        deleteTarget.value = null
        if (editingStatus.value?.id === targetId) resetForm()
        await loadStatuses()
    } catch (failure) {
        if (disposed) return
        const statusErrors = failure.response?.data?.errors?.status
        deleteError.value = (Array.isArray(statusErrors) ? statusErrors[0] : statusErrors)
            || failure.response?.data?.message || 'Не удалось удалить статус. Попробуйте ещё раз.'
    } finally {
        if (!disposed) deleting.value = false
    }
}

watch(() => props.modelValue, (open) => {
    cancelLoad()
    resetForm()
    deleteTarget.value = null
    deleteError.value = ''
    listError.value = ''
    statuses.value = []
    if (open) loadStatuses()
}, { immediate: true })

onBeforeUnmount(() => {
    disposed = true
    cancelLoad()
})
</script>

<template>
    <v-dialog :model-value="modelValue" :persistent="mutating" max-width="880" scrollable @update:model-value="updateDialog">
        <v-card class="order-statuses" theme="light">
            <header class="order-statuses__header">
                <span class="order-statuses__symbol"><v-icon icon="mdi-tag-multiple-outline" size="20" /></span>
                <div class="order-statuses__heading">
                    <h2>Статусы заказов</h2>
                    <p>Название, цвет и порядок в списке</p>
                </div>
                <v-btn icon="mdi-close" size="small" variant="text" :disabled="mutating" aria-label="Закрыть статусы заказов" @click="updateDialog(false)" />
            </header>
            <v-progress-linear v-if="loading" indeterminate color="#7f1d1d" height="2" />

            <div class="order-statuses__body" :aria-busy="loading">
                <div v-if="listError" class="order-statuses__alert" role="alert">
                    <span>{{ listError }}</span>
                    <v-btn size="small" variant="text" color="#7f1d1d" :disabled="mutating" @click="loadStatuses">Повторить</v-btn>
                </div>
                <div class="order-statuses__workspace">
                    <section class="order-statuses__list" aria-label="Список статусов">
                        <div class="order-statuses__list-heading">
                            <span>Все статусы <span class="order-statuses__count">{{ statuses.length }}</span></span>
                            <v-btn v-if="permissions.create" size="small" variant="text" color="#7f1d1d" prepend-icon="mdi-plus" :disabled="loading || mutating" @click="createStatus">Добавить</v-btn>
                        </div>
                        <p v-if="loading && !statuses.length" class="order-statuses__empty" role="status">Загрузка статусов…</p>
                        <p v-else-if="!statuses.length && !listError" class="order-statuses__empty">Создайте первый статус.</p>
                        <div v-for="status in statuses" :key="status.id" class="order-statuses__row" :class="{ 'order-statuses__row--selected': editingStatus?.id === status.id }">
                            <button type="button" class="order-statuses__select" :disabled="!permissions.edit || loading || mutating" :aria-label="`Редактировать статус ${status.name}`" :aria-pressed="editingStatus?.id === status.id" @click="editStatus(status)">
                                <span class="order-statuses__dot" :style="{ backgroundColor: status.color || '#94a3b8' }" />
                                <span class="order-statuses__label">
                                    <strong>{{ status.name }}</strong>
                                    <span>{{ status.is_closed ? 'Завершает заказ' : 'В работе' }}<template v-if="status.is_system"> · системный</template></span>
                                </span>
                                <span class="order-statuses__usage" :title="`Заказов: ${status.orders_count || 0}`">{{ status.orders_count || 0 }}</span>
                                <v-icon v-if="permissions.edit" icon="mdi-pencil-outline" size="15" class="order-statuses__edit-icon" />
                            </button>
                            <span v-if="permissions.delete" :title="deletionReason(status) || 'Удалить статус'">
                                <v-btn icon="mdi-trash-can-outline" size="x-small" variant="text" color="#94a3b8" :disabled="loading || mutating || Boolean(deletionReason(status))" :aria-label="`Удалить статус ${status.name}`" @click="requestDelete(status)" />
                            </span>
                        </div>
                        <p v-if="statuses.some(status => status.is_system)" class="order-statuses__note">Системные статусы используются в работе заказов. Их можно переименовать и перекрасить.</p>
                    </section>

                    <form v-if="formVisible" class="order-statuses__form" @submit.prevent="saveStatus">
                        <div class="order-statuses__form-heading">
                            <h3>{{ editingStatus ? 'Редактировать статус' : 'Новый статус' }}</h3>
                            <span v-if="isSystem" class="order-statuses__system">Системный</span>
                        </div>
                        <p v-if="formError" class="order-statuses__form-error" role="alert">{{ formError }}</p>
                        <v-text-field v-model="form.name" label="Название" maxlength="64" variant="outlined" density="compact" hide-details="auto" :disabled="mutating" :error-messages="fieldErrors.name" />
                        <v-text-field v-model="form.code" label="Код статуса" maxlength="32" placeholder="awaiting_payment" variant="outlined" density="compact" hide-details="auto" :disabled="mutating || isSystem" :error-messages="fieldErrors.code" :hint="isSystem ? 'Код системного статуса не меняется' : 'Латинские буквы, цифры, _ или -'" />
                        <div class="order-statuses__form-row">
                            <div class="order-statuses__color">
                                <input type="color" :value="/^#[a-f\d]{6}$/i.test(form.color) ? form.color : '#64748b'" aria-label="Выбрать цвет статуса" :disabled="mutating" @input="form.color = $event.target.value" />
                                <v-text-field v-model="form.color" label="Цвет" placeholder="#64748b" variant="outlined" density="compact" hide-details="auto" :disabled="mutating" :error-messages="fieldErrors.color" />
                            </div>
                            <v-text-field v-model="form.sort_order" label="Порядок" type="number" min="0" max="65535" step="1" variant="outlined" density="compact" hide-details="auto" :disabled="mutating" :error-messages="fieldErrors.sort_order" class="order-statuses__sort" />
                        </div>
                        <div>
                            <v-switch v-model="form.is_closed" label="Завершает заказ" color="#7f1d1d" density="compact" hide-details="auto" :disabled="mutating || closingFlagLocked" :error-messages="fieldErrors.is_closed" />
                            <p class="order-statuses__hint">{{ isSystem ? 'Роль системного статуса не меняется.' : closingFlagLocked ? 'Для изменения переведите заказы в другой статус.' : 'При выборе этого статуса заказ считается завершённым.' }}</p>
                        </div>
                        <div class="order-statuses__form-actions">
                            <v-btn size="small" variant="text" :disabled="mutating" @click="resetForm">Отмена</v-btn>
                            <v-btn type="submit" size="small" color="#7f1d1d" variant="flat" :loading="saving" :disabled="loading || deleting">{{ editingStatus ? 'Сохранить' : 'Создать статус' }}</v-btn>
                        </div>
                    </form>
                    <div v-else class="order-statuses__placeholder">
                        <v-icon icon="mdi-tag-edit-outline" size="34" />
                        <p v-if="permissions.edit">Выберите статус для редактирования.</p>
                        <p v-else>Доступные статусы заказов</p>
                        <v-btn v-if="permissions.create" variant="tonal" color="#7f1d1d" size="small" prepend-icon="mdi-plus" :disabled="loading || mutating" @click="createStatus">Новый статус</v-btn>
                    </div>
                </div>
            </div>
        </v-card>
        <v-dialog :model-value="Boolean(deleteTarget)" :persistent="deleting" max-width="420" @update:model-value="value => !value && cancelDelete()">
            <v-card class="order-statuses__confirmation" theme="light">
                <h3>Удалить статус?</h3>
                <p>Статус «{{ deleteTarget?.name }}» будет удалён из списка.</p>
                <p v-if="deleteError" class="order-statuses__form-error" role="alert">{{ deleteError }}</p>
                <div class="order-statuses__form-actions">
                    <v-btn size="small" variant="text" :disabled="deleting" @click="cancelDelete">Отмена</v-btn>
                    <v-btn size="small" variant="flat" color="#7f1d1d" :loading="deleting" @click="deleteStatus">Удалить</v-btn>
                </div>
            </v-card>
        </v-dialog>
    </v-dialog>
</template>

<style scoped>
.order-statuses {
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 16px !important;
    background: #fff;
    color: #334155;
}

.order-statuses__header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
}

.order-statuses__symbol {
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    flex-shrink: 0;
    border-radius: 10px;
    background: #fef2f2;
    color: #7f1d1d;
}

.order-statuses__heading { flex: 1; }
.order-statuses__heading h2 { font-size: 15px; font-weight: 700; }
.order-statuses__heading p { margin-top: 1px; color: #64748b; font-size: 11px; }
.order-statuses__body { overflow-y: auto; }
.order-statuses__workspace { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); }
.order-statuses__list { min-width: 0; padding: 12px; border-right: 1px solid #e2e8f0; }
.order-statuses__list-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 9px; font-size: 12px; font-weight: 600; }
.order-statuses__count { margin-left: 3px; color: #94a3b8; font-variant-numeric: tabular-nums; }
.order-statuses__row { display: flex; align-items: center; gap: 2px; margin-bottom: 4px; border: 1px solid transparent; border-radius: 8px; }
.order-statuses__row:hover { background: #f8fafc; }
.order-statuses__row--selected, .order-statuses__row--selected:hover { border-color: #fecaca; background: #fff7f7; }
.order-statuses__select { display: flex; align-items: center; gap: 9px; width: 100%; min-width: 0; padding: 9px 3px 9px 9px; text-align: left; border-radius: 7px; }
.order-statuses__select:focus-visible { outline: 2px solid #7f1d1d; outline-offset: 1px; }
.order-statuses__select:disabled { opacity: 0.55; }
.order-statuses__dot { flex-shrink: 0; width: 9px; height: 9px; border: 1px solid rgb(15 23 42 / 12%); border-radius: 50%; }
.order-statuses__label { display: grid; min-width: 0; flex: 1; gap: 2px; }
.order-statuses__label strong { overflow-wrap: anywhere; font-size: 12px; font-weight: 600; }
.order-statuses__label > span { color: #64748b; font-size: 10px; }
.order-statuses__usage { padding: 2px 5px; border-radius: 5px; background: #f1f5f9; color: #64748b; font-size: 10px; font-variant-numeric: tabular-nums; }
.order-statuses__edit-icon { color: #94a3b8; }
.order-statuses__note { margin: 14px 4px 4px; color: #94a3b8; font-size: 10px; line-height: 1.5; }
.order-statuses__empty { padding: 24px 5px; color: #64748b; font-size: 12px; }
.order-statuses__form { display: flex; flex-direction: column; gap: 14px; padding: 18px; }
.order-statuses__form-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.order-statuses__form-heading h3, .order-statuses__confirmation h3 { font-size: 13px; font-weight: 700; }
.order-statuses__system { padding: 3px 6px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-size: 10px; }
.order-statuses__form-row, .order-statuses__color { display: flex; align-items: flex-start; gap: 8px; }
.order-statuses__color { flex: 1; min-width: 0; }
.order-statuses__color input { width: 32px; min-width: 32px; height: 40px; border-radius: 6px; cursor: pointer; }
.order-statuses__sort { flex: 0 0 104px; }
.order-statuses__hint { margin-top: -4px; color: #64748b; font-size: 10px; line-height: 1.5; }
.order-statuses__form-actions { display: flex; justify-content: flex-end; gap: 6px; padding-top: 4px; }
.order-statuses__placeholder { display: flex; min-height: 260px; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 24px; color: #94a3b8; text-align: center; font-size: 12px; line-height: 1.6; }
.order-statuses__alert { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 12px; padding: 8px 12px; border-radius: 8px; background: #fff1f2; color: #9f1239; font-size: 12px; }
.order-statuses__form-error { padding: 8px 10px; border-radius: 6px; background: #fff1f2; color: #9f1239; font-size: 12px; }
.order-statuses__confirmation { padding: 20px; border-radius: 12px !important; }
.order-statuses__confirmation > p { margin: 12px 0; color: #64748b; font-size: 13px; }
.order-statuses__form :deep(.v-field__input), .order-statuses__form :deep(.v-label) { font-size: 12px; }

@media (max-width: 650px) {
    .order-statuses__workspace { grid-template-columns: 1fr; }
    .order-statuses__list { border-right: 0; border-bottom: 1px solid #e2e8f0; }
    .order-statuses__placeholder { min-height: 160px; }
    .order-statuses__header { padding: 10px 12px; }
    .order-statuses__form { padding: 14px; }
}
</style>
