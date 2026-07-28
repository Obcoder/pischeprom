<script setup>
import { computed, inject, onMounted, reactive, ref, watch } from 'vue'

const props = defineProps({ trip: { type: Object, required: true }, canManage: Boolean })
const emit = defineEmits(['changed'])
const api = inject('logisticsApi')
const expenses = ref([])
const metrics = ref(null)
const categories = ref([])
const checks = ref([])
const dialog = ref(false)
const deleting = ref(null)
const editing = ref(null)
const checkSearch = ref('')
const form = reactive(emptyForm())
let searchTimer = null

const selectedCheck = computed(() => checks.value.find((item) => item.id === form.check_id) || null)

function emptyForm() {
    return { check_id: null, expense_category_id: null, allocated_amount: null, currency_code: 'RUB', occurred_at: '', quantity: null, unit: '', unit_price: null, notes: '' }
}
function money(value, currency = 'RUB') { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency, maximumFractionDigits: 2 }).format(Number(value || 0)) }
function number(value, digits = 2) { return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: digits }).format(Number(value || 0)) }
function checkTitle(item) { return item ? `#${item.id} · ${item.date || 'без даты'} · ${item.entity?.name || 'без контрагента'} · доступно ${money(item.available_amount, item.currency_code)}` : '' }
function formatDate(value) { return value ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' }).format(new Date(value)) : '—' }

async function load() {
    try {
        const response = await api.request(`expenses-load-${props.trip.id}`, { method: 'get', url: `/api/logistics/trips/${props.trip.id}/expenses` }, { error: 'Не удалось загрузить расходы рейса.' })
        expenses.value = response?.data || []
        metrics.value = response?.metrics || null
    } catch { /* global snackbar */ }
}
async function loadCategories() {
    try {
        const response = await api.request('expense-categories', { method: 'get', url: '/api/logistics/expense-categories' }, { error: 'Не удалось загрузить категории расходов.' })
        categories.value = response?.data || []
    } catch { /* global snackbar */ }
}
async function searchChecks(search = '') {
    try {
        const response = await api.request(`checks-${search}`, { method: 'get', url: '/api/logistics/checks', params: { search, per_page: 30 } }, { error: 'Не удалось найти чеки.' })
        const byId = new Map(checks.value.map((item) => [item.id, item]))
        ;(response?.data || []).forEach((item) => byId.set(item.id, item))
        checks.value = [...byId.values()]
    } catch { /* global snackbar */ }
}
function onCheckSearch(value) {
    checkSearch.value = value || ''
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => searchChecks(checkSearch.value), 300)
}
function openCreate() { editing.value = null; Object.assign(form, emptyForm()); dialog.value = true; searchChecks() }
function openEdit(expense) {
    editing.value = expense
    Object.assign(form, emptyForm(), {
        check_id: expense.check_id,
        expense_category_id: expense.expense_category_id,
        allocated_amount: expense.allocated_amount,
        currency_code: expense.currency_code,
        occurred_at: expense.occurred_at?.slice(0, 10) || '',
        quantity: expense.quantity,
        unit: expense.unit || '',
        unit_price: expense.unit_price,
        notes: expense.notes || '',
    })
    if (expense.check) {
        checks.value = [...checks.value.filter((item) => item.id !== expense.check.id), { ...expense.check, available_amount: expense.allocated_amount }]
    }
    dialog.value = true
}

async function save() {
    try {
        const response = await api.request('expense-save', {
            method: editing.value ? 'put' : 'post',
            url: editing.value
                ? `/api/logistics/trips/${props.trip.id}/expenses/${editing.value.id}`
                : `/api/logistics/trips/${props.trip.id}/expenses`,
            data: { ...form, occurred_at: form.occurred_at || null, check_id: form.check_id || null },
        }, { success: editing.value ? 'Расход обновлён.' : 'Расход добавлен.' })
        if (!response) return
        dialog.value = false
        await load()
        emit('changed')
    } catch { /* field errors are shown below */ }
}

async function remove() {
    if (!deleting.value) return
    try {
        await api.request('expense-delete', { method: 'delete', url: `/api/logistics/trips/${props.trip.id}/expenses/${deleting.value.id}` }, { success: 'Расход отвязан; чек сохранён.' })
        deleting.value = null
        await load()
        emit('changed')
    } catch { /* global snackbar */ }
}

watch(() => form.check_id, () => {
    const check = selectedCheck.value
    if (!check || editing.value) return
    form.allocated_amount = check.available_amount
    form.currency_code = check.currency_code || 'RUB'
    form.occurred_at = check.date || ''
})
watch(() => props.trip.id, load)
onMounted(() => { load(); loadCategories() })
</script>

<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-3">
            <div>
                <div class="text-subtitle-1 font-weight-bold">Расходы рейса</div>
                <div class="text-caption text-medium-emphasis">Ручной расход явно помечается отсутствием чека.</div>
            </div>
            <v-btn v-if="canManage" color="green-darken-2" variant="flat" size="small" prepend-icon="mdi-plus" @click="openCreate">Добавить</v-btn>
        </div>

        <v-row v-if="metrics" dense class="mb-3">
            <v-col cols="6" md="3"><v-card variant="tonal" color="green"><v-card-text><div class="text-caption">Всего</div><strong>{{ Object.entries(metrics.totals_by_currency || {}).map(([c,v]) => money(v,c)).join(' · ') || '—' }}</strong></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Стоимость / км</div><strong>{{ metrics.cost_per_km == null ? '—' : money(metrics.cost_per_km, metrics.primary_currency) }}</strong><div class="text-caption">{{ metrics.distance_basis === 'actual' ? 'факт' : 'план' }}</div></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Стоимость / кг</div><strong>{{ metrics.cost_per_kg == null ? '—' : money(metrics.cost_per_kg, metrics.primary_currency) }}</strong></v-card-text></v-card></v-col>
            <v-col cols="6" md="3"><v-card variant="tonal"><v-card-text><div class="text-caption">Топливо</div><strong>{{ number(metrics.fuel_liters, 3) }} л</strong><div class="text-caption">{{ metrics.actual_fuel_consumption_l_per_100km == null ? 'нет факта' : `${number(metrics.actual_fuel_consumption_l_per_100km, 2)} л/100 км` }}</div></v-card-text></v-card></v-col>
        </v-row>

        <v-table density="compact" class="border rounded">
            <thead><tr><th>Дата / категория</th><th>Чек</th><th>Количество</th><th class="text-right">Сумма</th><th></th></tr></thead>
            <tbody>
                <tr v-for="expense in expenses" :key="expense.id">
                    <td>{{ formatDate(expense.occurred_at) }}<div class="text-caption">{{ expense.category?.name }}</div></td>
                    <td><v-chip :color="expense.has_check ? 'green' : 'orange'" size="x-small" variant="tonal">{{ expense.has_check ? `Check #${expense.check_id}` : 'без чека' }}</v-chip></td>
                    <td>{{ expense.quantity == null ? '—' : `${number(expense.quantity, 3)} ${expense.unit || ''}` }}</td>
                    <td class="text-right font-weight-bold">{{ money(expense.allocated_amount, expense.currency_code) }}</td>
                    <td class="text-right"><v-btn v-if="canManage" icon="mdi-pencil-outline" size="x-small" variant="text" @click="openEdit(expense)" /><v-btn v-if="canManage" icon="mdi-link-off" color="red" size="x-small" variant="text" @click="deleting = expense" /></td>
                </tr>
                <tr v-if="!expenses.length"><td colspan="5" class="logistics-empty">Расходов пока нет.</td></tr>
            </tbody>
        </v-table>

        <v-dialog v-model="dialog" max-width="760" persistent>
            <v-card :title="editing ? 'Редактировать расход' : 'Новый расход'">
                <v-card-text>
                    <v-row dense>
                        <v-col cols="12"><v-autocomplete v-model="form.check_id" :items="checks" :item-title="checkTitle" item-value="id" label="Существующий чек (необязательно)" clearable no-filter @update:search="onCheckSearch" /></v-col>
                        <v-col cols="12" md="6"><v-select v-model="form.expense_category_id" :items="categories" item-title="name" item-value="id" label="Категория *" :error-messages="api.firstError('expense_category_id')" /></v-col>
                        <v-col cols="8" md="4"><v-text-field v-model.number="form.allocated_amount" label="Распределённая сумма *" type="number" min="0.01" step="0.01" :error-messages="api.firstError('allocated_amount')" /></v-col>
                        <v-col cols="4" md="2"><v-text-field v-model="form.currency_code" label="Валюта" maxlength="3" /></v-col>
                        <v-col cols="12" md="4"><v-text-field v-model="form.occurred_at" type="date" label="Дата расхода" /></v-col>
                        <v-col cols="4" md="3"><v-text-field v-model.number="form.quantity" label="Количество" type="number" min="0" step="0.001" /></v-col>
                        <v-col cols="3" md="2"><v-text-field v-model="form.unit" label="Ед. (l, night)" /></v-col>
                        <v-col cols="5" md="3"><v-text-field v-model.number="form.unit_price" label="Цена за единицу" type="number" min="0" step="0.01" /></v-col>
                        <v-col cols="12"><v-textarea v-model="form.notes" label="Примечание" rows="2" /></v-col>
                    </v-row>
                    <v-alert v-if="!form.check_id" type="warning" variant="tonal" density="compact">Расход будет сохранён без подтверждающего чека.</v-alert>
                </v-card-text>
                <v-card-actions class="justify-end"><v-btn variant="text" @click="dialog = false">Отмена</v-btn><v-btn color="green-darken-2" variant="flat" :loading="api.isPending('expense-save')" @click="save">Сохранить</v-btn></v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog :model-value="Boolean(deleting)" max-width="460" @update:model-value="!$event && (deleting = null)">
            <v-card title="Отвязать расход?"><v-card-text>Распределение будет удалено, исходный Check #{{ deleting?.check_id || '—' }} останется без изменений.</v-card-text><v-card-actions class="justify-end"><v-btn @click="deleting = null">Отмена</v-btn><v-btn color="red" variant="flat" :loading="api.isPending('expense-delete')" @click="remove">Отвязать</v-btn></v-card-actions></v-card>
        </v-dialog>
    </div>
</template>
