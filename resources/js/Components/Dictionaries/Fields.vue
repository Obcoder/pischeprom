<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

const fields = ref([])
const goods = ref([])
const total = ref(0)
const loading = ref(false)
const goodsLoading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialog = ref(false)
const deleteDialog = ref(false)
const selectedField = ref(null)
const errors = ref({})
const search = ref('')
const publishedFilter = ref('all')

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
})

const options = ref({
    page: 1,
    itemsPerPage: 50,
    sortBy: [{ key: 'sort_order', order: 'asc' }],
})

const form = reactive({
    id: null,
    title: '',
    slug: '',
    description: '',
    is_published: false,
    sort_order: 500,
    goods: [],
})

const headers = [
    { title: 'Field / подборка', key: 'title', sortable: true, minWidth: 260 },
    { title: 'Publish', key: 'is_published', sortable: true, width: 130 },
    { title: 'Order', key: 'sort_order', sortable: true, width: 90 },
    { title: 'Goods', key: 'goods_count', sortable: true, width: 90 },
    { title: 'Categories', key: 'categories_count', sortable: true, width: 110 },
    { title: 'Units', key: 'units_count', sortable: true, width: 80 },
    { title: 'Updated', key: 'updated_at', sortable: true, width: 135 },
    { title: '', key: 'actions', sortable: false, width: 130 },
]

const tableHeight = 'calc(100vh - 306px)'

const pageCount = computed(() => {
    const perPage = Number(options.value.itemsPerPage || 1)

    return Math.max(1, Math.ceil(Number(total.value || 0) / perPage))
})

const dialogTitle = computed(() => {
    return form.id ? 'Редактировать Field' : 'Новый Field'
})

const goodsById = computed(() => {
    return new Map(goods.value.map((good) => [Number(good.id), good]))
})

const selectedGoodsPreview = computed(() => {
    return form.goods
        .map((id) => goodsById.value.get(Number(id)))
        .filter(Boolean)
        .slice(0, 8)
})

function showMessage(text, color = 'success') {
    snackbar.text = text
    snackbar.color = color
    snackbar.show = true
}

function slugify(value) {
    const map = {
        а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k',
        л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'c',
        ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
    }

    return String(value || '')
        .trim()
        .toLowerCase()
        .split('')
        .map((char) => map[char] ?? char)
        .join('')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
}

function fieldParam(field) {
    return field?.slug || field?.id || ''
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
}

function resetForm() {
    Object.assign(form, {
        id: null,
        title: '',
        slug: '',
        description: '',
        is_published: false,
        sort_order: 500,
        goods: [],
    })

    selectedField.value = null
    errors.value = {}
}

function fillForm(field) {
    Object.assign(form, {
        id: field.id,
        title: field.title ?? '',
        slug: field.slug ?? '',
        description: field.description ?? '',
        is_published: Boolean(field.is_published),
        sort_order: field.sort_order ?? 500,
        goods: (field.goods || []).map((good) => good.id),
    })

    errors.value = {}
}

function resetDialogForm() {
    if (selectedField.value) {
        fillForm(selectedField.value)
        return
    }

    resetForm()
}

function payload(source = form) {
    return {
        title: source.title,
        slug: source.slug || null,
        description: source.description || null,
        is_published: Boolean(source.is_published),
        sort_order: Number(source.sort_order || 0),
        goods: source.goods || [],
    }
}

function replaceField(field) {
    const index = fields.value.findIndex((item) => item.id === field.id)

    if (index >= 0) {
        fields.value.splice(index, 1, field)
        return
    }

    fields.value.unshift(field)
}

async function fetchFields() {
    loading.value = true

    try {
        const sort = options.value.sortBy?.[0] || {}
        const response = await axios.get(route('fields.index'), {
            params: {
                page: options.value.page,
                per_page: options.value.itemsPerPage,
                search: search.value || null,
                published: publishedFilter.value === 'all' ? null : publishedFilter.value === 'published',
                sort_by: sort.key || 'sort_order',
                sort_desc: sort.order === 'desc',
            },
        })

        fields.value = response.data.data || []
        total.value = response.data.total || fields.value.length
    } catch (error) {
        console.error(error)
        showMessage('Не удалось загрузить Fields', 'error')
    } finally {
        loading.value = false
    }
}

async function fetchGoods() {
    goodsLoading.value = true

    try {
        const response = await axios.get(route('goods.index'), {
            params: {
                per_page: 500,
                sort_by: 'name',
            },
        })

        goods.value = (Array.isArray(response.data) ? response.data : response.data.data || [])
            .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'ru'))
    } catch (error) {
        console.error(error)
        showMessage('Не удалось загрузить товары для связей', 'error')
    } finally {
        goodsLoading.value = false
    }
}

function openCreate() {
    resetForm()
    dialog.value = true
}

async function openEdit(field) {
    resetForm()
    dialog.value = true
    loading.value = true

    try {
        const response = await axios.get(route('fields.show', field.id))
        selectedField.value = response.data
        fillForm(response.data)
    } catch (error) {
        console.error(error)
        selectedField.value = field
        fillForm(field)
        showMessage('Field открыт из таблицы, детальная загрузка не удалась', 'warning')
    } finally {
        loading.value = false
    }
}

async function saveField() {
    saving.value = true
    errors.value = {}

    try {
        const response = form.id
            ? await axios.put(route('fields.update', form.id), payload())
            : await axios.post(route('fields.store'), payload())

        replaceField(response.data)
        selectedField.value = response.data
        dialog.value = false
        showMessage(form.id ? 'Field обновлён' : 'Field создан')
        await fetchFields()
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = error.response.data.errors || {}
            showMessage('Проверьте поля формы', 'error')
            return
        }

        console.error(error)
        showMessage('Не удалось сохранить Field', 'error')
    } finally {
        saving.value = false
    }
}

async function togglePublish(field, value) {
    const previous = field.is_published
    field.is_published = value

    try {
        const response = await axios.put(route('fields.update', field.id), {
            title: field.title,
            slug: field.slug,
            description: field.description,
            is_published: value,
            sort_order: field.sort_order ?? 500,
        })

        replaceField(response.data)
    } catch (error) {
        field.is_published = previous
        console.error(error)
        showMessage('Не удалось обновить публикацию', 'error')
    }
}

function askDelete(field) {
    selectedField.value = field
    deleteDialog.value = true
}

async function deleteField() {
    if (!selectedField.value?.id) {
        return
    }

    deleting.value = true

    try {
        await axios.delete(route('fields.destroy', selectedField.value.id))
        fields.value = fields.value.filter((item) => item.id !== selectedField.value.id)
        selectedField.value = null
        deleteDialog.value = false
        dialog.value = false
        showMessage('Field удалён')
        await fetchFields()
    } catch (error) {
        console.error(error)
        showMessage('Не удалось удалить Field', 'error')
    } finally {
        deleting.value = false
    }
}

function generateSlug() {
    form.slug = slugify(form.title)
}

function setPage(page) {
    if (page === options.value.page) {
        return
    }

    options.value.page = page
    fetchFields()
}

function setItemsPerPage(value) {
    const perPage = Number(value)

    if (!perPage || perPage === options.value.itemsPerPage) {
        return
    }

    options.value.itemsPerPage = perPage
    options.value.page = 1
    fetchFields()
}

function updateOptions(nextOptions) {
    options.value = {
        page: nextOptions.page,
        itemsPerPage: nextOptions.itemsPerPage,
        sortBy: nextOptions.sortBy,
    }

    fetchFields()
}

let searchTimer = null
watch([search, publishedFilter], () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        options.value.page = 1
        fetchFields()
    }, 250)
})

onMounted(() => {
    fetchGoods()
    fetchFields()
})
</script>

<template>
    <v-container fluid class="fields-admin pa-0">
        <v-card rounded="lg" elevation="1" class="fields-admin__card">
            <v-card-title class="fields-admin__toolbar">
                <div class="fields-admin__heading">
                    <div class="text-subtitle-1 font-weight-bold">Fields / подборки</div>
                    <div class="text-caption text-medium-emphasis">
                        CRUD, публикация на главной и связь подборка - товар.
                    </div>
                </div>

                <v-spacer />

                <v-text-field
                    v-model="search"
                    label="Поиск"
                    prepend-inner-icon="mdi-magnify"
                    variant="solo-inverted"
                    density="compact"
                    hide-details
                    clearable
                    class="fields-admin__search"
                />

                <v-select
                    v-model="publishedFilter"
                    :items="[
                        { title: 'Все', value: 'all' },
                        { title: 'Published', value: 'published' },
                        { title: 'Hidden', value: 'hidden' },
                    ]"
                    label="Publish"
                    variant="solo-inverted"
                    density="compact"
                    hide-details
                    class="fields-admin__filter"
                />

                <v-btn color="#47765a" prepend-icon="mdi-plus" density="comfortable" @click="openCreate">
                    Field
                </v-btn>
            </v-card-title>

            <v-data-table-server
                :headers="headers"
                :items="fields"
                :loading="loading"
                :items-length="total"
                :page="options.page"
                :items-per-page="options.itemsPerPage"
                :sort-by="options.sortBy"
                :height="tableHeight"
                fixed-header
                density="compact"
                hover
                item-value="id"
                class="fields-admin__table"
                hide-default-footer
                @update:options="updateOptions"
            >
                <template #item.title="{ item }">
                    <div class="fields-admin__title-cell">
                        <div class="d-flex align-center ga-2 min-width-0">
                            <a
                                v-if="item.public_url"
                                :href="item.public_url"
                                target="_blank"
                                rel="noopener"
                                class="fields-admin__title-link text-truncate"
                            >
                                {{ item.title }}
                            </a>
                            <span v-else class="font-weight-bold text-truncate">{{ item.title }}</span>

                            <v-chip size="x-small" variant="tonal" color="blue-grey">
                                #{{ item.id }}
                            </v-chip>
                        </div>

                        <div class="text-caption text-medium-emphasis text-truncate">
                            /подборки/{{ fieldParam(item) }}
                        </div>

                        <div v-if="item.description" class="fields-admin__description text-caption">
                            {{ item.description }}
                        </div>
                    </div>
                </template>

                <template #item.is_published="{ item }">
                    <div class="d-flex align-center ga-2">
                        <v-switch
                            :model-value="!!item.is_published"
                            color="green"
                            density="compact"
                            hide-details
                            inset
                            @update:model-value="(value) => togglePublish(item, value)"
                        />
                        <span class="text-caption">
                            {{ item.is_published ? 'on' : 'off' }}
                        </span>
                    </div>
                </template>

                <template #item.updated_at="{ item }">
                    <span class="text-caption">{{ formatDate(item.updated_at) }}</span>
                </template>

                <template #item.goods_count="{ item }">
                    <v-chip size="x-small" variant="tonal" color="teal">
                        {{ item.goods_count || 0 }}
                    </v-chip>
                </template>

                <template #item.categories_count="{ item }">
                    <v-chip size="x-small" variant="tonal" color="indigo">
                        {{ item.categories_count || 0 }}
                    </v-chip>
                </template>

                <template #item.units_count="{ item }">
                    <v-chip size="x-small" variant="tonal" color="brown">
                        {{ item.units_count || 0 }}
                    </v-chip>
                </template>

                <template #item.actions="{ item }">
                    <div class="d-flex justify-end ga-1">
                        <v-btn
                            v-if="item.public_url"
                            :href="item.public_url"
                            target="_blank"
                            rel="noopener"
                            icon="mdi-open-in-new"
                            size="small"
                            variant="text"
                        />
                        <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(item)" />
                        <v-btn icon="mdi-delete" size="small" variant="text" color="red" @click="askDelete(item)" />
                    </div>
                </template>

                <template #bottom>
                    <div class="fields-admin__footer">
                        <div class="d-flex align-center ga-2">
                            <span class="text-caption">Строк</span>
                            <v-select
                                :model-value="options.itemsPerPage"
                                :items="[25, 50, 100, 200]"
                                density="compact"
                                variant="plain"
                                hide-details
                                class="fields-admin__per-page"
                                @update:model-value="setItemsPerPage"
                            />
                            <span class="text-caption text-medium-emphasis">Всего: {{ total }}</span>
                        </div>

                        <v-pagination
                            :model-value="options.page"
                            :length="pageCount"
                            :total-visible="5"
                            density="compact"
                            @update:model-value="setPage"
                        />
                    </div>
                </template>
            </v-data-table-server>
        </v-card>

        <v-dialog v-model="dialog" max-width="980" persistent scrollable>
            <v-card rounded="lg">
                <v-card-title class="d-flex align-center ga-3">
                    <span>{{ dialogTitle }}</span>
                    <v-spacer />
                    <v-btn icon="mdi-close" variant="text" :disabled="saving" @click="dialog = false" />
                </v-card-title>

                <v-divider />

                <v-card-text>
                    <v-row dense>
                        <v-col cols="12" md="7">
                            <v-text-field
                                v-model="form.title"
                                label="Название подборки"
                                variant="outlined"
                                density="compact"
                                :error-messages="errors.title"
                                required
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model.number="form.sort_order"
                                type="number"
                                label="Order"
                                variant="outlined"
                                density="compact"
                                :error-messages="errors.sort_order"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-switch
                                v-model="form.is_published"
                                label="Publish"
                                color="green"
                                density="compact"
                                hide-details
                                inset
                            />
                        </v-col>

                        <v-col cols="12" md="8">
                            <v-text-field
                                v-model="form.slug"
                                label="Slug"
                                variant="outlined"
                                density="compact"
                                hint="Публичный URL: /подборки/slug"
                                persistent-hint
                                :error-messages="errors.slug"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-btn block variant="tonal" height="40" @click="generateSlug">
                                Сгенерировать slug
                            </v-btn>
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Описание для карточки и страницы подборки"
                                variant="outlined"
                                density="compact"
                                rows="3"
                                auto-grow
                                :error-messages="errors.description"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-autocomplete
                                v-model="form.goods"
                                :items="goods"
                                item-title="name"
                                item-value="id"
                                label="Товары подборки"
                                placeholder="Начните вводить товар"
                                variant="outlined"
                                density="compact"
                                multiple
                                chips
                                closable-chips
                                clearable
                                :loading="goodsLoading"
                                :error-messages="errors.goods"
                            />
                        </v-col>

                        <v-col cols="12">
                            <div class="fields-admin__preview-title">Первые выбранные товары</div>
                            <div v-if="selectedGoodsPreview.length" class="fields-admin__goods-preview">
                                <v-chip
                                    v-for="good in selectedGoodsPreview"
                                    :key="good.id"
                                    size="x-small"
                                    variant="tonal"
                                    color="teal"
                                >
                                    {{ good.name }}
                                </v-chip>
                                <v-chip v-if="form.goods.length > selectedGoodsPreview.length" size="x-small" variant="outlined">
                                    +{{ form.goods.length - selectedGoodsPreview.length }}
                                </v-chip>
                            </div>
                            <div v-else class="text-caption text-medium-emphasis">
                                Товары пока не выбраны.
                            </div>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        v-if="form.id"
                        color="red"
                        variant="text"
                        @click="askDelete({ id: form.id, title: form.title })"
                    >
                        Удалить
                    </v-btn>

                    <v-spacer />

                    <v-btn variant="text" :disabled="saving" @click="dialog = false">
                        Закрыть
                    </v-btn>

                    <v-btn variant="text" :disabled="saving" @click="resetDialogForm">
                        Сбросить
                    </v-btn>

                    <v-btn color="#47765a" variant="flat" :loading="saving" @click="saveField">
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" width="520">
            <v-card rounded="lg">
                <v-card-title>Удалить Field?</v-card-title>
                <v-card-text>
                    Удалить <strong>{{ selectedField?.title }}</strong>? Связи с товарами и units будут удалены через pivot, категории потеряют ссылку на Field.
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="deleting" @click="deleteDialog = false">Отмена</v-btn>
                    <v-btn color="red" variant="tonal" :loading="deleting" @click="deleteField">Удалить</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="2600">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<style scoped>
.fields-admin {
    min-height: calc(100vh - 220px);
}

.fields-admin__card {
    overflow: hidden;
}

.fields-admin__toolbar {
    min-height: 58px;
    gap: 10px;
    padding: 8px 12px;
}

.fields-admin__heading {
    min-width: 220px;
}

.fields-admin__search {
    max-width: 280px;
}

.fields-admin__filter {
    max-width: 160px;
}

.fields-admin__table {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.fields-admin__title-cell {
    min-width: 0;
    padding: 5px 0;
}

.fields-admin__title-link {
    min-width: 0;
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
    text-decoration: none;
}

.fields-admin__description {
    display: -webkit-box;
    max-width: 520px;
    overflow: hidden;
    color: rgba(var(--v-theme-on-surface), 0.68);
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
}

.fields-admin__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 40px;
    padding: 4px 10px;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.fields-admin__per-page {
    max-width: 84px;
}

.fields-admin__preview-title {
    margin-bottom: 6px;
    color: rgba(var(--v-theme-on-surface), 0.64);
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.fields-admin__goods-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

:deep(.v-data-table-footer) {
    display: none;
}

:deep(.v-field__input) {
    min-height: 32px;
}

@media (max-width: 960px) {
    .fields-admin__toolbar {
        align-items: stretch;
    }

    .fields-admin__search,
    .fields-admin__filter {
        max-width: none;
        width: 100%;
    }

    .fields-admin__footer {
        align-items: stretch;
        flex-direction: column;
        gap: 4px;
    }
}
</style>
