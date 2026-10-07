<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import CatalogToolbar from './CatalogToolbar.vue'

const components = ref([])
const search = ref('')
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const formLoading = ref(false)
const saving = ref(false)
const formError = ref('')
const errors = ref({})
const form = reactive({ id: null, name: '', products: [] })
const deleteTarget = ref(null)
const deleting = ref(false)
const deleteError = ref('')
const snackbar = reactive({ show: false, text: '' })

const headers = [
    { title: 'Компонент', key: 'name' },
    { title: 'Продукты', key: 'products_count', width: 130 },
    { title: 'Обновлён', key: 'updated_at', width: 160 },
    { title: '', key: 'actions', sortable: false, width: 100 },
]

const filteredComponents = computed(() => {
    const query = String(search.value || '').trim().toLocaleLowerCase('ru-RU')
    return components.value.filter(item => !query || item.name.toLocaleLowerCase('ru-RU').includes(query))
})

function notify(text) {
    snackbar.text = text
    snackbar.show = true
}

function message(cause, fallback) {
    return cause.response?.data?.message || fallback
}

function date(value) {
    if (!value) return '—'
    const parsed = new Date(value)
    return Number.isNaN(parsed.getTime()) ? '—' : parsed.toLocaleDateString('ru-RU')
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const { data } = await axios.get('/api/components')
        components.value = Array.isArray(data) ? data : (data.data || [])
    } catch (cause) {
        error.value = message(cause, 'Не удалось загрузить компоненты.')
    } finally {
        loading.value = false
    }
}

async function edit(item = null) {
    form.id = item?.id ?? null
    form.name = item?.name ?? ''
    form.products = []
    errors.value = {}
    formError.value = ''
    dialog.value = true
    if (!item) return

    formLoading.value = true
    try {
        const { data } = await axios.get(`/api/components/${item.id}`)
        form.name = data.name
        form.products = data.products || []
    } catch (cause) {
        formError.value = message(cause, 'Не удалось загрузить компонент.')
    } finally {
        formLoading.value = false
    }
}

async function save() {
    if (saving.value || formLoading.value) return
    errors.value = {}
    formError.value = ''
    saving.value = true
    try {
        const payload = { name: form.name.trim() }
        const { data } = form.id
            ? await axios.patch(`/api/components/${form.id}`, payload)
            : await axios.post('/api/components', payload)
        const index = components.value.findIndex(item => item.id === data.id)
        if (index === -1) components.value.push(data)
        else components.value.splice(index, 1, data)
        dialog.value = false
        notify(form.id ? 'Компонент обновлён.' : 'Компонент создан.')
    } catch (cause) {
        errors.value = cause.response?.data?.errors || {}
        formError.value = message(cause, 'Не удалось сохранить компонент.')
    } finally {
        saving.value = false
    }
}

function confirmDelete(item) {
    deleteError.value = ''
    deleteTarget.value = item
}

async function remove() {
    if (!deleteTarget.value || deleting.value) return
    deleting.value = true
    deleteError.value = ''
    try {
        await axios.delete(`/api/components/${deleteTarget.value.id}`)
        components.value = components.value.filter(item => item.id !== deleteTarget.value.id)
        deleteTarget.value = null
        notify('Компонент удалён.')
    } catch (cause) {
        deleteError.value = message(cause, 'Не удалось удалить компонент.')
    } finally {
        deleting.value = false
    }
}

onMounted(load)
</script>

<template>
    <section class="components-page">
        <CatalogToolbar :count="filteredComponents.length" :total="components.length">
            <v-text-field
                v-model="search"
                label="Поиск компонентов"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="catalog-toolbar__search"
                @click:clear="search = ''"
            />
            <template #actions>
                <v-btn icon="mdi-refresh" variant="text" :loading="loading" aria-label="Обновить компоненты" @click="load" />
                <v-btn color="#352345" prepend-icon="mdi-plus" @click="edit()">Компонент</v-btn>
            </template>
        </CatalogToolbar>

        <v-alert v-if="error" type="error" variant="tonal" class="ma-3">{{ error }}</v-alert>

        <v-data-table
            :headers="headers"
            :items="filteredComponents"
            :loading="loading"
            :items-per-page="25"
            :items-per-page-options="[25, 50, 100]"
            :sort-by="[{ key: 'name', order: 'asc' }]"
            item-value="id"
            density="compact"
            fixed-header
            items-per-page-text="На странице"
            no-data-text="Компоненты не найдены. Добавьте первый компонент."
            loading-text="Загрузка компонентов…"
            class="components-page__table"
        >
            <template #item.name="{ item }">
                <button type="button" class="components-page__name" @click="edit(item)">{{ item.name }}</button>
            </template>
            <template #item.updated_at="{ item }">{{ date(item.updated_at) }}</template>
            <template #item.actions="{ item }">
                <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" :aria-label="`Редактировать ${item.name}`" @click="edit(item)" />
                <v-btn icon="mdi-delete-outline" size="x-small" variant="text" color="error" :aria-label="`Удалить ${item.name}`" @click="confirmDelete(item)" />
            </template>
        </v-data-table>

        <v-dialog v-model="dialog" max-width="600" :persistent="saving || formLoading">
            <v-card>
                <v-card-title>{{ form.id ? 'Редактирование компонента' : 'Новый компонент' }}</v-card-title>
                <v-progress-linear v-if="formLoading" indeterminate color="#352345" />
                <form @submit.prevent="save">
                    <v-card-text>
                        <v-alert v-if="formError" type="error" variant="tonal" class="mb-4">{{ formError }}</v-alert>
                        <v-text-field
                            v-model="form.name"
                            label="Название компонента"
                            variant="outlined"
                            density="compact"
                            :error-messages="errors.name"
                            :disabled="saving || formLoading"
                            maxlength="255"
                            autofocus
                        />
                        <template v-if="form.id && !formLoading">
                            <p class="text-body-2 mb-2">Используется в продуктах: {{ form.products.length }}</p>
                            <div class="d-flex flex-wrap ga-2">
                                <v-chip
                                    v-for="product in form.products"
                                    :key="product.id"
                                    :href="`/Ameise/product/${product.id}`"
                                    size="small"
                                    variant="tonal"
                                    color="#352345"
                                >{{ product.rus || product.eng || `Продукт №${product.id}` }}</v-chip>
                            </div>
                        </template>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <v-btn :disabled="saving || formLoading" @click="dialog = false">Отмена</v-btn>
                        <v-btn type="submit" color="#352345" variant="flat" :loading="saving" :disabled="formLoading || !form.name.trim()">Сохранить</v-btn>
                    </v-card-actions>
                </form>
            </v-card>
        </v-dialog>

        <v-dialog :model-value="Boolean(deleteTarget)" max-width="480" :persistent="deleting" @update:model-value="value => { if (!value) deleteTarget = null }">
            <v-card title="Удалить компонент?">
                <v-card-text>
                    <p>«{{ deleteTarget?.name }}» будет удалён из справочника.</p>
                    <p v-if="deleteTarget?.products_count" class="mt-2">
                        Связи с продуктами ({{ deleteTarget.products_count }}) также будут удалены. Сами продукты сохранятся.
                    </p>
                    <v-alert v-if="deleteError" type="error" variant="tonal" class="mt-3">{{ deleteError }}</v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn :disabled="deleting" @click="deleteTarget = null">Отмена</v-btn>
                    <v-btn color="error" variant="flat" :loading="deleting" @click="remove">Удалить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" color="success" :timeout="3500">{{ snackbar.text }}</v-snackbar>
    </section>
</template>

<style scoped>
.components-page { display: flex; flex: 1 1 0; height: 100%; min-height: 0; flex-direction: column; background: #fff; }
.components-page__table { display: flex; flex: 1 1 0; min-height: 0; flex-direction: column; }
.components-page__table :deep(.v-table__wrapper) { flex: 1 1 auto; min-height: 0; }
.components-page__table :deep(th), .components-page__table :deep(td) { font-size: 12px; }
.components-page__name { color: #352345; text-align: left; font-weight: 600; }
.components-page__name:hover { text-decoration: underline; }
</style>
