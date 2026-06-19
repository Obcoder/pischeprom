<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import {
    emptyProductTranslationForm,
    productLanguageFields,
    productTranslationFields,
} from '@/Pages/Helpers/productLanguages.js'

const loading = ref(false)
const saving = ref(false)

const products = ref([])
const categories = ref([])

const search = ref('')
const categoryId = ref(null)

const createDialog = ref(false)
const formRef = ref(null)

const snackbar = reactive({ show: false, text: '' })
const error = ref('')

const headers = [
    { title: 'ID', key: 'id', width: 72 },
    { title: 'Product', key: 'rus', minWidth: 260 },
    { title: 'Category', key: 'category', width: 220 },
    { title: 'Lang', key: 'translations', width: 96, sortable: false },
    { title: 'Manufacturers', key: 'manufacturers', sortable: false },
    { title: 'Created', key: 'created_at', width: 118 },
]

const form = reactive({
    ...emptyProductTranslationForm(),
    category_id: null,
    is_published: true,
})

const rules = {
    required: v => !!String(v ?? '').trim() || 'Обязательное поле',
}

const categoryItems = computed(() =>
    categories.value.map(c => ({
        value: c.id,
        title: c.name,
    }))
)

const filteredProducts = computed(() => {
    const q = (search.value || '').toLowerCase().trim()

    return products.value.filter(product => {
        if (categoryId.value && product.category_id !== categoryId.value) return false
        if (!q) return true

        const hay = [
            product.id,
            ...productTranslationFields.map(field => product[field.key]),
            product?.category?.rus,
            product?.category?.name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return hay.includes(q)
    })
})

const filledCreateFields = computed(() =>
    productTranslationFields.filter(field => String(form[field.key] ?? '').trim()).length
)

function filledTranslationCount(product) {
    return productTranslationFields.filter(field => String(product?.[field.key] ?? '').trim()).length
}

function formatDate(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return iso
    return d.toLocaleDateString('ru-RU')
}

function openCreate() {
    error.value = ''
    resetForm()
    createDialog.value = true
}

function resetForm() {
    productTranslationFields.forEach(field => {
        form[field.key] = ''
    })

    form.category_id = null
    form.is_published = true
}

function normalizePayload() {
    const payload = {
        category_id: form.category_id || null,
        is_published: Boolean(form.is_published),
    }

    productTranslationFields.forEach(field => {
        const value = String(form[field.key] ?? '').trim()
        payload[field.key] = value === '' ? null : value
    })

    return payload
}

async function loadAll() {
    loading.value = true
    try {
        const [pRes, cRes] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/categories'),
        ])

        products.value = Array.isArray(pRes.data)
            ? pRes.data
            : (pRes.data?.data ?? [])

        categories.value = Array.isArray(cRes.data)
            ? cRes.data
            : (cRes.data?.data ?? [])
    } catch (e) {
        console.error(e)
        snackbar.text = 'Ошибка загрузки данных'
        snackbar.show = true
    } finally {
        loading.value = false
    }
}

async function createProduct() {
    error.value = ''

    const formEl = formRef.value
    if (formEl?.validate) {
        const res = await formEl.validate()
        if (!res.valid) return
    } else if (!String(form.rus ?? '').trim()) {
        return
    }

    saving.value = true
    try {
        const res = await axios.post('/api/products', normalizePayload())
        const created = res.data && typeof res.data === 'object' ? res.data : null

        if (created?.id) {
            products.value.unshift(created)
        } else {
            await loadAll()
        }

        createDialog.value = false
        snackbar.text = 'Продукт создан'
        snackbar.show = true
    } catch (e) {
        console.error(e)

        error.value =
            e?.response?.data?.message ||
            (e?.response?.data?.errors
                ? Object.values(e.response.data.errors).flat().join('\n')
                : null) ||
            'Ошибка создания продукта'
    } finally {
        saving.value = false
    }
}

onMounted(loadAll)
</script>

<template>
    <v-container fluid class="products-shell pa-2 pa-md-3">
        <v-card rounded="xl" class="products-card">
            <div class="products-toolbar px-3 px-md-4 py-3">
                <div class="d-flex align-center ga-3 flex-wrap">
                    <div class="min-w-0">
                        <div class="text-h6 font-weight-black text-truncate">Products</div>
                        <div class="text-caption text-medium-emphasis">
                            {{ filteredProducts.length }} / {{ products.length }} записей
                        </div>
                    </div>

                    <v-spacer />

                    <v-text-field
                        v-model="search"
                        label="Поиск"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="compact"
                        hide-details
                        clearable
                        class="toolbar-field"
                    />

                    <v-autocomplete
                        v-model="categoryId"
                        :items="categoryItems"
                        item-title="title"
                        item-value="value"
                        variant="outlined"
                        label="Категория"
                        density="compact"
                        hide-details
                        clearable
                        class="toolbar-field toolbar-field--category"
                    />

                    <v-btn
                        color="primary"
                        prepend-icon="mdi-plus"
                        size="small"
                        class="font-weight-bold"
                        @click="openCreate()"
                    >
                        Новый продукт
                    </v-btn>
                </div>
            </div>

            <v-divider />

            <v-card-text class="pa-0">
                <v-data-table
                    :headers="headers"
                    :items="filteredProducts"
                    item-key="id"
                    items-per-page="50"
                    fixed-header
                    height="calc(100vh - 290px)"
                    :loading="loading"
                    density="compact"
                    class="products-table"
                    hover
                >
                    <template #item.category="{ item }">
                        <div class="text-body-2 text-truncate">
                            {{ item?.category?.rus ?? item?.category?.name ?? '—' }}
                        </div>
                    </template>

                    <template #item.rus="{ item }">
                        <Link
                            :href="route('product.show', item.id)"
                            class="product-link text-decoration-none"
                        >
                            <span class="font-weight-bold text-truncate">{{ item.rus || '—' }}</span>
                            <small v-if="item.eng" class="text-medium-emphasis text-truncate">{{ item.eng }}</small>
                        </Link>
                    </template>

                    <template #item.translations="{ item }">
                        <v-chip size="x-small" color="primary" variant="tonal">
                            {{ filledTranslationCount(item) }}/{{ productTranslationFields.length }}
                        </v-chip>
                    </template>

                    <template #item.manufacturers="{ item }">
                        <div class="manufacturers-cell text-body-2">
                            <template v-if="item.manufacturers?.length">
                                <template v-for="(m, index) in item.manufacturers" :key="m.id">
                                    <Link
                                        :href="route('web.unit.show', m.id)"
                                        class="text-decoration-none"
                                    >
                                        {{ m.rus ?? m.name ?? m.id }}
                                    </Link>
                                    <span v-if="index < item.manufacturers.length - 1">, </span>
                                </template>
                            </template>
                            <span v-else class="text-medium-emphasis">—</span>
                        </div>
                    </template>

                    <template #item.created_at="{ item }">
                        <div class="text-caption text-medium-emphasis">
                            {{ formatDate(item.created_at) }}
                        </div>
                    </template>

                    <template #no-data>
                        <div class="pa-6 text-medium-emphasis">
                            Ничего не найдено.
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <v-dialog v-model="createDialog" max-width="1080" scrollable>
            <v-card rounded="xl" class="create-card">
                <div class="create-hero px-4 py-3">
                    <div class="d-flex align-center justify-space-between ga-3">
                        <div class="min-w-0">
                            <div class="text-h6 font-weight-black text-truncate">Создать продукт</div>
                            <div class="text-caption text-medium-emphasis">
                                Заполнено переводов: {{ filledCreateFields }}/{{ productTranslationFields.length }}
                            </div>
                        </div>

                        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="createDialog = false" />
                    </div>
                </div>

                <v-divider />

                <v-card-text class="pa-3 pa-md-4">
                    <v-form ref="formRef" @submit.prevent="createProduct">
                        <v-row dense>
                            <v-col cols="12" md="5">
                                <v-text-field
                                    v-model.trim="form.rus"
                                    label="Русский / базовое название"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[rules.required]"
                                    required
                                    hide-details="auto"
                                >
                                    <template #prepend-inner>
                                        <v-chip size="x-small" color="primary" variant="flat">RU</v-chip>
                                    </template>
                                </v-text-field>
                            </v-col>

                            <v-col cols="12" md="5">
                                <v-autocomplete
                                    v-model="form.category_id"
                                    :items="categoryItems"
                                    item-title="title"
                                    item-value="value"
                                    label="Категория"
                                    variant="outlined"
                                    density="compact"
                                    clearable
                                    hide-details="auto"
                                />
                            </v-col>

                            <v-col cols="12" md="2">
                                <v-switch
                                    v-model="form.is_published"
                                    color="success"
                                    density="compact"
                                    inset
                                    hide-details
                                    label="Published"
                                />
                            </v-col>

                            <v-col
                                v-for="field in productLanguageFields"
                                :key="field.key"
                                cols="12"
                                sm="6"
                                md="4"
                                lg="3"
                            >
                                <v-text-field
                                    v-model.trim="form[field.key]"
                                    :label="`${field.label} (${field.key})`"
                                    :hint="field.native"
                                    variant="outlined"
                                    density="compact"
                                    hide-details="auto"
                                    persistent-hint
                                >
                                    <template #prepend-inner>
                                        <v-chip size="x-small" variant="tonal">{{ field.code }}</v-chip>
                                    </template>
                                </v-text-field>
                            </v-col>
                        </v-row>

                        <v-alert
                            v-if="error"
                            type="error"
                            variant="tonal"
                            class="mt-2 error-alert"
                            :text="error"
                        />
                    </v-form>
                </v-card-text>

                <v-divider />

                <v-card-actions class="px-4 py-3">
                    <v-spacer />
                    <v-btn variant="text" @click="createDialog = false">Отмена</v-btn>
                    <v-btn color="primary" :loading="saving" @click="createProduct">
                        Создать
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :timeout="2500">
            {{ snackbar.text }}
            <template #actions>
                <v-btn variant="text" @click="snackbar.show = false">OK</v-btn>
            </template>
        </v-snackbar>
    </v-container>
</template>

<style scoped>
.products-shell {
    min-height: calc(100vh - 120px);
}

.products-card,
.create-card {
    border: 1px solid rgba(var(--v-theme-primary), 0.16);
    overflow: hidden;
}

.products-toolbar,
.create-hero {
    background:
        radial-gradient(circle at 0 0, rgba(var(--v-theme-primary), 0.16), transparent 32%),
        linear-gradient(135deg, rgba(var(--v-theme-surface), 0.98), rgba(var(--v-theme-primary), 0.05));
}

.toolbar-field {
    max-width: 300px;
    min-width: 220px;
}

.toolbar-field--category {
    max-width: 250px;
}

.products-table :deep(thead th) {
    font-weight: 800;
    white-space: nowrap;
}

.product-link {
    display: grid;
    max-width: 360px;
    color: rgb(var(--v-theme-primary));
    line-height: 1.2;
}

.product-link small {
    margin-top: 2px;
}

.manufacturers-cell {
    max-width: 420px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.min-w-0 {
    min-width: 0;
}

.error-alert {
    white-space: pre-line;
}

@media (max-width: 960px) {
    .toolbar-field,
    .toolbar-field--category {
        max-width: none;
        width: 100%;
    }
}
</style>
