<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    entityId: { type: Number, required: true },
})
const emit = defineEmits(['changed'])

const rows = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const saving = ref(false)
const formError = ref('')
const errors = ref({})
const editingId = ref(null)
const deleteTarget = ref(null)
const deleting = ref(false)
const deleteError = ref('')
const products = ref([])
const measures = ref([])
const metaLoading = ref(false)
const metaLoaded = ref(false)
const metaError = ref('')
const form = reactive(emptyForm())
const statuses = [
    { value: 'potential', label: 'Потенциальная' },
    { value: 'confirmed', label: 'Подтверждённая' },
    { value: 'closed', label: 'Закрытая' },
]
const quantityFormat = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 3 })
let listRequest = 0
let metaRequest = 0

const activeCount = computed(() => rows.value.filter(row => row.status !== 'closed').length)
const productOptions = computed(() => {
    const usedIds = new Set(rows.value
        .filter(row => row.id !== editingId.value)
        .map(row => Number(row.product_id)))

    return products.value.map(product => ({
        ...product,
        title: productName(product),
        props: { disabled: usedIds.has(Number(product.id)) },
    }))
})
const formTitleId = computed(() => `entity-consumption-form-${props.entityId}`)
const deleteTitleId = computed(() => `entity-consumption-delete-${props.entityId}`)

function emptyForm() {
    return { product_id: null, quantity: '', measure_id: null, status: 'potential', comment: '' }
}

function endpoint(entityId = props.entityId) {
    return `/api/entities/${entityId}/consumptions`
}

function productName(product) {
    return product?.rus || product?.eng || (product?.id ? `Продукт #${product.id}` : 'Продукт')
}

function statusLabel(status) {
    return statuses.find(item => item.value === status)?.label || status
}

function amountLabel(row) {
    if (row.quantity === null || row.quantity === undefined || row.quantity === '') {
        return 'Объём не уточнён'
    }

    return `${quantityFormat.format(Number(row.quantity))} ${row.measure?.name || ''}`.trim()
}

function announceChange() {
    emit('changed', { count: rows.value.length, items: rows.value })
}

async function fetchRows() {
    const request = ++listRequest
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get(endpoint())
        if (request !== listRequest) return
        rows.value = data.data || []
        announceChange()
    } catch (err) {
        if (request === listRequest) {
            error.value = err.response?.data?.message || 'Не удалось загрузить потребности.'
        }
    } finally {
        if (request === listRequest) loading.value = false
    }
}

async function fetchMeta() {
    if (metaLoaded.value || metaLoading.value) return
    const request = ++metaRequest
    metaLoading.value = true
    metaError.value = ''

    try {
        const { data } = await axios.get(`${endpoint()}/meta`)
        if (request !== metaRequest) return
        products.value = data.products || []
        measures.value = data.measures || []
        metaLoaded.value = true
    } catch (err) {
        if (request === metaRequest) {
            metaError.value = err.response?.data?.message || 'Не удалось загрузить продукты и единицы измерения.'
        }
    } finally {
        if (request === metaRequest) metaLoading.value = false
    }
}

function openForm(row = null) {
    editingId.value = row?.id || null
    Object.assign(form, row ? {
        product_id: row.product_id,
        quantity: row.quantity ?? '',
        measure_id: row.measure_id,
        status: row.status,
        comment: row.comment || '',
    } : emptyForm())
    errors.value = {}
    formError.value = ''
    dialog.value = true
    fetchMeta()
}

function closeForm() {
    if (!saving.value) dialog.value = false
}

async function save() {
    if (saving.value || !metaLoaded.value || metaLoading.value) return
    errors.value = {}
    formError.value = ''
    const quantity = String(form.quantity ?? '').trim().replace(',', '.')

    if (!form.product_id) errors.value.product_id = ['Выберите продукт.']
    if (quantity && (!Number.isFinite(Number(quantity)) || Number(quantity) <= 0)) {
        errors.value.quantity = ['Укажите объём больше нуля.']
    }
    if (quantity && !form.measure_id) errors.value.measure_id = ['Выберите единицу измерения.']
    if (form.comment.length > 2000) errors.value.comment = ['Не более 2000 символов.']
    if (Object.keys(errors.value).length) return

    const entityId = props.entityId
    const id = editingId.value
    const payload = {
        product_id: form.product_id,
        quantity: quantity || null,
        measure_id: form.measure_id || null,
        status: form.status,
        comment: form.comment.trim() || null,
    }
    saving.value = true

    try {
        const { data } = id
            ? await axios.put(`${endpoint(entityId)}/${id}`, payload)
            : await axios.post(endpoint(entityId), payload)
        if (entityId !== props.entityId) return
        rows.value = id
            ? rows.value.map(row => row.id === id ? data.data : row)
            : [data.data, ...rows.value]
        dialog.value = false
        announceChange()
    } catch (err) {
        if (entityId !== props.entityId) return
        errors.value = err.response?.data?.errors || {}
        formError.value = err.response?.status === 422
            ? 'Проверьте отмеченные поля.'
            : err.response?.data?.message || 'Не удалось сохранить потребность. Попробуйте ещё раз.'
    } finally {
        saving.value = false
    }
}

function confirmDelete(row) {
    deleteError.value = ''
    deleteTarget.value = row
}

function closeDelete() {
    if (!deleting.value) deleteTarget.value = null
}

async function remove() {
    if (deleting.value || !deleteTarget.value) return
    const entityId = props.entityId
    const id = deleteTarget.value.id
    deleting.value = true
    deleteError.value = ''

    try {
        await axios.delete(`${endpoint(entityId)}/${id}`)
        if (entityId !== props.entityId) return
        rows.value = rows.value.filter(row => row.id !== id)
        deleteTarget.value = null
        announceChange()
    } catch (err) {
        if (entityId === props.entityId) {
            deleteError.value = err.response?.data?.message || 'Не удалось удалить потребность. Попробуйте ещё раз.'
        }
    } finally {
        deleting.value = false
    }
}

watch(() => props.entityId, () => {
    ++metaRequest
    rows.value = []
    products.value = []
    measures.value = []
    metaLoaded.value = false
    metaLoading.value = false
    metaError.value = ''
    dialog.value = false
    deleteTarget.value = null
    fetchRows()
}, { immediate: true })

onBeforeUnmount(() => {
    ++listRequest
    ++metaRequest
})
</script>

<template>
    <section id="entity-consumptions" class="entity-needs" :aria-busy="loading" aria-label="Потребности в продуктах">
        <header class="entity-needs__header">
            <div class="entity-needs__heading">
                <span class="entity-needs__icon"><v-icon icon="mdi-package-variant-closed" size="19" /></span>
                <div>
                    <h2>Потребности <span v-if="!loading && !error" class="entity-needs__count">{{ rows.length }}</span></h2>
                    <p>Продукты для контрагента</p>
                </div>
            </div>
            <v-btn
                class="entity-needs__add"
                color="#7f1d1d"
                variant="tonal"
                size="small"
                prepend-icon="mdi-plus"
                :disabled="loading || !!error"
                @click="openForm()"
            >Добавить</v-btn>
        </header>

        <div v-if="loading" class="entity-needs__loading" role="status">
            <v-progress-circular indeterminate size="18" width="2" color="#7f1d1d" />
            <span>Загружаем потребности…</span>
        </div>
        <v-alert v-else-if="error" type="error" variant="tonal" density="compact" class="ma-3" role="alert">
            {{ error }}
            <template #append><v-btn variant="text" size="small" @click="fetchRows">Повторить</v-btn></template>
        </v-alert>
        <div v-else-if="!rows.length" class="entity-needs__empty">
            <strong>Какие продукты интересны контрагенту?</strong>
            <p>Добавьте потенциальную потребность. Объём можно уточнить позже.</p>
        </div>
        <template v-else>
            <div class="entity-needs__summary"><span>{{ activeCount }} в работе</span><span>{{ rows.length - activeCount }} закрыто</span></div>
            <ul class="entity-needs__list">
                <li v-for="row in rows" :key="row.id" class="entity-needs__item">
                    <div class="entity-needs__item-main">
                        <Link :href="route('product.show', row.product_id)" class="entity-needs__product">
                            {{ productName(row.product || { id: row.product_id }) }}
                            <v-icon icon="mdi-arrow-top-right" size="13" />
                        </Link>
                        <div class="entity-needs__details">
                            <span class="entity-needs__status" :class="`entity-needs__status--${row.status}`">{{ statusLabel(row.status) }}</span>
                            <span class="entity-needs__amount" :class="{ 'entity-needs__amount--unknown': !row.quantity }">{{ amountLabel(row) }}</span>
                        </div>
                        <p v-if="row.comment" class="entity-needs__comment">{{ row.comment }}</p>
                    </div>
                    <div class="entity-needs__actions">
                        <v-btn
                            icon="mdi-pencil-outline"
                            variant="text"
                            size="x-small"
                            :aria-label="`Изменить потребность: ${productName(row.product)}`"
                            title="Изменить потребность"
                            @click="openForm(row)"
                        />
                        <v-btn
                            icon="mdi-trash-can-outline"
                            variant="text"
                            size="x-small"
                            :aria-label="`Удалить потребность: ${productName(row.product)}`"
                            title="Удалить потребность"
                            @click="confirmDelete(row)"
                        />
                    </div>
                </li>
            </ul>
        </template>
    </section>

    <v-dialog
        v-model="dialog"
        max-width="560"
        :persistent="saving"
        :aria-labelledby="formTitleId"
    >
        <v-card class="entity-needs-dialog" rounded="lg">
            <header class="entity-needs-dialog__header">
                <div>
                    <h2 :id="formTitleId">{{ editingId ? 'Изменить потребность' : 'Новая потребность' }}</h2>
                    <p>Интерес контрагента к продукту</p>
                </div>
                <v-btn icon="mdi-close" size="small" variant="text" aria-label="Закрыть" :disabled="saving" @click="closeForm" />
            </header>
            <v-form :disabled="saving" @submit.prevent="save">
                <v-card-text class="entity-needs-dialog__body">
                    <v-alert v-if="metaError" type="error" variant="tonal" density="compact" class="mb-4" role="alert">
                        {{ metaError }}
                        <template #append><v-btn variant="text" size="small" @click="fetchMeta">Повторить</v-btn></template>
                    </v-alert>
                    <v-alert v-if="formError" type="error" variant="tonal" density="compact" class="mb-4" role="alert">{{ formError }}</v-alert>
                    <v-autocomplete
                        v-model="form.product_id"
                        :items="productOptions"
                        item-title="title"
                        item-value="id"
                        label="Продукт *"
                        :loading="metaLoading"
                        :disabled="saving || metaLoading || !metaLoaded"
                        :error-messages="errors.product_id"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                        no-data-text="Продукты не найдены"
                        auto-select-first
                    />
                    <div class="entity-needs-dialog__quantity">
                        <v-text-field
                            v-model="form.quantity"
                            label="Объём"
                            placeholder="Не уточнён"
                            inputmode="decimal"
                            :error-messages="errors.quantity"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                        />
                        <v-autocomplete
                            v-model="form.measure_id"
                            :items="measures"
                            item-title="name"
                            item-value="id"
                            :label="String(form.quantity ?? '').trim() ? 'Единица измерения *' : 'Единица измерения'"
                            :loading="metaLoading"
                            :disabled="saving || metaLoading || !metaLoaded"
                            :error-messages="errors.measure_id"
                            variant="outlined"
                            density="compact"
                            hide-details="auto"
                            no-data-text="Единицы не найдены"
                            clearable
                        />
                    </div>
                    <p class="entity-needs-dialog__hint">Если объём пока неизвестен, оставьте его пустым.</p>
                    <v-select
                        v-model="form.status"
                        :items="statuses"
                        item-title="label"
                        item-value="value"
                        label="Статус"
                        :error-messages="errors.status"
                        variant="outlined"
                        density="compact"
                        hide-details="auto"
                    />
                    <v-textarea
                        v-model="form.comment"
                        label="Комментарий"
                        placeholder="Сроки, условия, особенности потребности"
                        :error-messages="errors.comment"
                        variant="outlined"
                        density="compact"
                        rows="2"
                        auto-grow
                        counter="2000"
                        maxlength="2000"
                    />
                </v-card-text>
                <v-card-actions class="entity-needs-dialog__footer">
                    <v-btn variant="text" :disabled="saving" @click="closeForm">Отмена</v-btn>
                    <v-btn color="#7f1d1d" variant="flat" type="submit" :loading="saving" :disabled="!metaLoaded || metaLoading || saving">Сохранить</v-btn>
                </v-card-actions>
            </v-form>
        </v-card>
    </v-dialog>

    <v-dialog
        :model-value="!!deleteTarget"
        max-width="440"
        :persistent="deleting"
        :aria-labelledby="deleteTitleId"
        @update:model-value="value => { if (!value) closeDelete() }"
    >
        <v-card class="entity-needs-dialog" rounded="lg">
            <header class="entity-needs-dialog__header">
                <h2 :id="deleteTitleId">Удалить потребность?</h2>
                <v-btn icon="mdi-close" size="small" variant="text" aria-label="Закрыть" :disabled="deleting" @click="closeDelete" />
            </header>
            <v-card-text>
                <p>Потребность в продукте «{{ productName(deleteTarget?.product) }}» будет удалена у этого контрагента.</p>
                <v-alert v-if="deleteError" type="error" variant="tonal" density="compact" class="mt-3" role="alert">{{ deleteError }}</v-alert>
            </v-card-text>
            <v-card-actions class="entity-needs-dialog__footer">
                <v-btn variant="text" :disabled="deleting" @click="closeDelete">Отмена</v-btn>
                <v-btn color="#7f1d1d" variant="flat" :loading="deleting" :disabled="deleting" @click="remove">Удалить</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.entity-needs {
    scroll-margin-top: 20px;
    overflow: hidden;
    border: 1px solid #e8ddd2;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 4px 16px rgb(69 36 26 / 4%);
}
.entity-needs__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(110deg, #fff8f0, #fff);
}
.entity-needs__heading { display: flex; align-items: center; gap: 10px; min-width: 0; }
.entity-needs__icon { display: grid; place-items: center; width: 35px; height: 35px; flex-shrink: 0; color: #7f1d1d; background: #f5e9de; border-radius: 10px; }
.entity-needs__heading h2 { display: flex; align-items: center; gap: 7px; margin: 0; font-size: 15px; font-weight: 750; line-height: 1.4; color: #452820; }
.entity-needs__heading p { margin: 2px 0 0; font-size: 11px; color: #8d776b; }
.entity-needs__count { display: inline-grid; place-items: center; min-width: 21px; height: 21px; padding: 0 5px; background: #f1e3d8; color: #7f1d1d; border-radius: 6px; font-size: 11px; font-weight: 700; }
.entity-needs__add { flex-shrink: 0; text-transform: none; letter-spacing: 0; border-radius: 8px; }
.entity-needs__loading { display: flex; align-items: center; gap: 9px; padding: 20px 16px; color: #8d776b; font-size: 12px; }
.entity-needs__empty { padding: 3px 16px 17px; }
.entity-needs__empty strong { font-size: 12px; font-weight: 650; color: #675046; }
.entity-needs__empty p { margin: 4px 0 0; color: #8d776b; font-size: 12px; line-height: 1.5; }
.entity-needs__summary { display: flex; gap: 12px; padding: 0 16px 10px; color: #8d776b; font-size: 10px; }
.entity-needs__summary span:first-child { font-weight: 650; color: #7f1d1d; }
.entity-needs__list { list-style: none; padding: 0; margin: 0; }
.entity-needs__item { display: flex; align-items: flex-start; gap: 8px; padding: 11px 12px 11px 16px; border-top: 1px solid #f0e8e1; }
.entity-needs__item-main { min-width: 0; flex: 1; }
.entity-needs__product { color: #632b22; font-size: 13px; font-weight: 650; line-height: 1.45; text-decoration: none; overflow-wrap: anywhere; }
.entity-needs__product:hover { text-decoration: underline; }
.entity-needs__product:focus-visible { outline: 2px solid #7f1d1d; outline-offset: 3px; border-radius: 2px; }
.entity-needs__product .v-icon { opacity: .6; margin-left: 2px; }
.entity-needs__details { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 5px; }
.entity-needs__status { padding: 2px 6px; border-radius: 5px; font-size: 10px; font-weight: 600; line-height: 1.5; }
.entity-needs__status--potential { color: #895b16; background: #fff2d5; }
.entity-needs__status--confirmed { color: #2b684e; background: #eaf5ed; }
.entity-needs__status--closed { color: #746c66; background: #f0edeb; }
.entity-needs__amount { color: #574236; font-size: 11px; font-weight: 600; font-variant-numeric: tabular-nums; }
.entity-needs__amount--unknown { color: #917e72; font-weight: 400; }
.entity-needs__comment { margin: 6px 0 0; color: #867367; font-size: 11px; line-height: 1.5; white-space: pre-line; overflow-wrap: anywhere; }
.entity-needs__actions { display: flex; flex-shrink: 0; color: #9b8275; }
.entity-needs__actions .v-btn:hover { color: #7f1d1d; }
.entity-needs-dialog { color: #4b3329; }
.entity-needs-dialog__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px 12px; }
.entity-needs-dialog__header h2 { margin: 0; font-size: 18px; font-weight: 700; line-height: 1.4; }
.entity-needs-dialog__header p { margin: 3px 0 0; color: #8d776b; font-size: 12px; }
.entity-needs-dialog__body { display: flex; flex-direction: column; gap: 17px; padding-top: 12px !important; padding-bottom: 2px !important; }
.entity-needs-dialog__quantity { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.entity-needs-dialog__hint { margin: -9px 0 -1px; font-size: 11px; color: #8d776b; }
.entity-needs-dialog__footer { justify-content: flex-end; gap: 8px; padding: 12px 20px 16px; }
.entity-needs-dialog__footer .v-btn { text-transform: none; letter-spacing: 0; }
@media (max-width: 480px) {
    .entity-needs__header { padding: 12px; gap: 8px; }
    .entity-needs__heading { gap: 8px; }
    .entity-needs__icon { display: none; }
    .entity-needs__item { padding-left: 12px; padding-right: 8px; }
    .entity-needs-dialog__quantity { grid-template-columns: 1fr; gap: 17px; }
}
</style>
