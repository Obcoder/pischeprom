<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { Link } from "@inertiajs/vue3";
import {route} from "ziggy-js";

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
    { title: 'ID', key: 'id', width: 80 },
    { title: 'Rus', key: 'rus' },
    { title: 'Category', key: 'category' },
    { title: 'Manufacturers', key: 'manufacturers' },
    { title: 'Created', key: 'created_at', width: 140 },
]

const form = reactive({
    rus: '',
    eng: '',
    zh: '',
    es: '',
    ar: '',
    po: '',
    de: '',
    fr: '',
    hi: '',
    category_id: null,
})

const rules = {
    required: v => !!v || 'Обязательное поле',
}

const categoryItems = computed(() =>
    categories.value.map(c => ({
        value: c.id,
        title: c.name
    }))
)

const filteredProducts = computed(() => {
    const q = (search.value || '').toLowerCase().trim()

    return products.value.filter(p => {
        // фильтр по категории
        if (categoryId.value && p.category_id !== categoryId.value) return false

        // текстовый поиск (по нескольким полям)
        if (!q) return true

        const hay = [
            p.id,
            p.rus,
            p.eng,
            p.de,
            p.fr,
            p?.category?.rus,
            p?.category?.name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return hay.includes(q)
    })
})

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
    form.rus = ''
    form.eng = ''
    form.zh = ''
    form.es = ''
    form.ar = ''
    form.po = ''
    form.de = ''
    form.fr = ''
    form.hi = ''
    form.category_id = null
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

    // валидация Vuetify form
    const formEl = formRef.value
    if (formEl?.validate) {
        const res = await formEl.validate()
        if (!res.valid) return
    } else {
        // на всякий случай
        if (!form.rus || !form.category_id) return
    }

    saving.value = true
    try {
        const payload = { ...form }
        const res = await axios.post('/api/products', payload)

        // оптимистично добавим, если API вернул объект
        const created = res.data && typeof res.data === 'object' ? res.data : null
        if (created?.id) {
            products.value.unshift(created)
        } else {
            // иначе просто перезагрузим список
            await loadAll()
        }

        createDialog.value = false
        snackbar.text = 'Продукт создан'
        snackbar.show = true
    } catch (e) {
        console.error(e)

        // если Laravel вернул ошибки валидации
        const msg =
            e?.response?.data?.message ||
            (e?.response?.data?.errors
                ? Object.values(e.response.data.errors).flat().join('\n')
                : null) ||
            'Ошибка создания продукта'

        error.value = msg
    } finally {
        saving.value = false
    }
}

onMounted(loadAll)
</script>

<template>
    <v-container fluid class="pa-4">
        <v-card>
            <v-card-title class="d-flex align-center ga-3 flex-wrap">
                <div class="text-h6">Products</div>

                <v-spacer />

                <!-- Текстовый поиск -->
                <v-text-field
                    v-model="search"
                    label="Поиск"
                    prepend-inner-icon="mdi-magnify"
                    variant="solo"
                    density="compact"
                    hide-details
                    clearable
                    style="max-width: 320px"
                />

                <!-- Фильтр по категориям -->
                <v-select
                    v-model="categoryId"
                    :items="categoryItems"
                    item-title="title"
                    item-value="value"
                    variant="solo"
                    label="Категория"
                    density="compact"
                    hide-details
                    clearable
                    style="max-width: 260px"
                />

                <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate()">
                    Новый продукт
                </v-btn>
            </v-card-title>

            <v-divider />

            <v-card-text class="pa-0">
                <v-data-table
                    :headers="headers"
                    :items="filteredProducts"
                    item-key="id"
                    items-per-page="50"
                    fixed-header
                    height="750px"
                    :loading="loading"
                    density="compact"
                    class="border rounded"
                    hover
                >

                    <template #item.category="{ item }">
                        <div class="text-body-2">
                            {{ item?.category?.rus ?? item?.category?.name ?? '—' }}
                        </div>
                    </template>

                    <template #item.manufacturers="{ item }">
                        <div class="text-body-2">
                            <template v-for="(m, index) in item.manufacturers" :key="m.id">
                                <Link
                                    :href="route('web.unit.show', m.id)"
                                    class="text-decoration-none"
                                >
                                    {{ m.rus ?? m.name ?? m.id }}
                                </Link>
                                <span v-if="index < item.manufacturers.length - 1">, </span>
                            </template>
                        </div>
                    </template>

                    <template #item.created_at="{ item }">
                        <div class="text-body-2">
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

        <!-- Диалог создания -->
        <v-dialog v-model="createDialog" max-width="720">
            <v-card>
                <v-card-title class="d-flex align-center">
                    <div class="text-h6">Создать продукт</div>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" @click="createDialog = false" />
                </v-card-title>

                <v-divider />

                <v-card-text>
                    <v-form ref="formRef" @submit.prevent="createProduct">
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model.trim="form.rus"
                                    label="Название (rus)"
                                    :rules="[rules.required]"
                                    required
                                />
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-select
                                    v-model="form.category_id"
                                    :items="categoryItems"
                                    item-title="title"
                                    item-value="value"
                                    label="Категория"
                                    :rules="[rules.required]"
                                    required
                                />
                            </v-col>

                            <!-- Доп. поля — по желанию -->
                            <v-col cols="12" md="4">
                                <v-text-field v-model.trim="form.eng" label="eng" />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field v-model.trim="form.de" label="de" />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field v-model.trim="form.fr" label="fr" />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-text-field v-model.trim="form.zh" label="zh" />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field v-model.trim="form.es" label="es" />
                            </v-col>
                            <v-col cols="12" md="4">
                                <v-text-field v-model.trim="form.ar" label="ar" />
                            </v-col>

                        </v-row>

                        <v-alert
                            v-if="error"
                            type="error"
                            variant="tonal"
                            class="mt-2"
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

        <!-- Снэк -->
        <v-snackbar v-model="snackbar.show" :timeout="2500">
            {{ snackbar.text }}
            <template #actions>
                <v-btn variant="text" @click="snackbar.show = false">OK</v-btn>
            </template>
        </v-snackbar>
    </v-container>
</template>
