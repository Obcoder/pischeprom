<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { usePurchases } from '@/Composables/usePurchases.js'
import { usePurchaseForm } from '@/Composables/usePurchaseForm.js'

const {
    items,
    loading,
    fetchPurchases,
    fetchPurchase,
    createPurchase,
    updatePurchase,
    deletePurchase,
} = usePurchases()

const {
    form,
    isEdit,
    resetForm,
    fillForm,
    payload,
    recalcItem,
    recalcAmount,
    emptyItem,
} = usePurchaseForm()

const dialog = ref(false)
const saving = ref(false)
const search = ref('')
const entities = ref([])
const goodsOptions = ref([])
const measures = ref([])
const currencies = ref([])
const serverErrors = ref({})

const headers = [
    { title: 'ID', key: 'id' },
    { title: 'Дата', key: 'date' },
    { title: 'Контрагент', key: 'entity' },
    { title: 'Сумма', key: 'amount' },
    { title: 'Позиции', key: 'items', sortable: false },
    { title: 'Действия', key: 'actions', sortable: false },
]

const extractItems = (response) => {
    if (Array.isArray(response?.data)) return response.data
    if (Array.isArray(response?.data?.data)) return response.data.data
    return []
}

const loadPurchases = async () => {
    await fetchPurchases({ search: search.value })
}

const loadDictionaries = async () => {
    const [entitiesRes, goodsRes, measuresRes, currenciesRes] = await Promise.all([
        axios.get('/api/entities'),
        axios.get('/api/goods'),
        axios.get('/api/measures'),
        axios.get('/api/currencies'),
    ])

    entities.value = extractItems(entitiesRes)
    goodsOptions.value = extractItems(goodsRes)
    measures.value = extractItems(measuresRes)
    currencies.value = extractItems(currenciesRes)
}

const openCreate = () => {
    resetForm()
    serverErrors.value = {}
    dialog.value = true
}

const openEdit = async (id) => {
    const purchase = await fetchPurchase(id)
    resetForm()
    fillForm(purchase)
    recalcAmount()
    serverErrors.value = {}
    dialog.value = true
}

const closeDialog = () => {
    dialog.value = false
    resetForm()
    serverErrors.value = {}
}

const addItemRow = () => {
    form.items.push(emptyItem())
}

const removeItemRow = (index) => {
    form.items.splice(index, 1)

    if (!form.items.length) {
        form.items.push(emptyItem())
    }

    recalcAmount()
}

const onItemChanged = (itemRow) => {
    recalcItem(itemRow)
    recalcAmount()
}

const submit = async () => {
    saving.value = true
    serverErrors.value = {}

    try {
        recalcAmount()

        if (isEdit.value) {
            await updatePurchase(form.id, payload.value)
        } else {
            await createPurchase(payload.value)
        }

        closeDialog()
        await loadPurchases()
    } catch (error) {
        if (error.response?.status === 422) {
            serverErrors.value = error.response.data.errors || {}
        } else {
            console.error(error)
        }
    } finally {
        saving.value = false
    }
}

const remove = async (id) => {
    await deletePurchase(id)
    await loadPurchases()
}

onMounted(async () => {
    await Promise.all([
        loadPurchases(),
        loadDictionaries(),
    ])
})
</script>

<template>
    <v-container fluid>
        <div class="d-flex justify-space-between align-center mb-4">
            <h1 class="text-h4">Закупки</h1>
            <v-btn color="primary" @click="openCreate">
                Создать закупку
            </v-btn>
        </div>

        <v-card>
            <v-card-text>
                <v-text-field
                    v-model="search"
                    label="Поиск по контрагенту"
                    variant="outlined"
                    clearable
                    @keyup.enter="loadPurchases"
                />

                <v-data-table
                    :headers="headers"
                    :items="items"
                    :loading="loading"
                    item-value="id"
                    hover
                >
                    <template #item.entity="{ item }">
                        {{ item.entity?.name }}
                    </template>

                    <template #item.items="{ item }">
                        <div class="d-flex flex-column ga-1">
                            <div
                                v-for="(row, idx) in item.items"
                                :key="idx"
                                class="text-body-2"
                            >
                                {{ row.good_name }} — {{ row.quantity }} × {{ row.price }} = {{ row.total }}
                            </div>
                        </div>
                    </template>

                    <template #item.actions="{ item }">
                        <div class="d-flex ga-2">
                            <v-btn size="small" variant="text" @click="openEdit(item.id)">
                                Редактировать
                            </v-btn>
                            <v-btn size="small" variant="text" color="error" @click="remove(item.id)">
                                Удалить
                            </v-btn>
                        </div>
                    </template>
                </v-data-table>
            </v-card-text>
        </v-card>

        <v-dialog v-model="dialog" max-width="1200">
            <v-card>
                <v-card-title>
                    {{ isEdit ? 'Редактирование закупки' : 'Создание закупки' }}
                </v-card-title>

                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.date"
                                type="date"
                                label="Дата"
                                variant="outlined"
                                :error-messages="serverErrors.date"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-autocomplete
                                v-model="form.entity_id"
                                :items="entities"
                                item-title="name"
                                item-value="id"
                                label="Контрагент"
                                variant="outlined"
                                :error-messages="serverErrors.entity_id"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                :model-value="form.amount"
                                label="Итоговая сумма"
                                variant="outlined"
                                readonly
                            />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-space-between align-center mb-4">
                        <h3 class="text-h6">Позиции закупки</h3>
                        <v-btn size="small" @click="addItemRow">Добавить позицию</v-btn>
                    </div>

                    <v-row
                        v-for="(itemRow, index) in form.items"
                        :key="index"
                        class="align-center mb-2"
                    >
                        <v-col cols="12" md="3">
                            <v-autocomplete
                                v-model="itemRow.good_id"
                                :items="goodsOptions"
                                item-title="name"
                                item-value="id"
                                label="Товар"
                                variant="outlined"
                                :error-messages="serverErrors[`items.${index}.good_id`]"
                            />
                        </v-col>

                        <v-col cols="12" md="1">
                            <v-text-field
                                v-model="itemRow.quantity"
                                type="number"
                                label="Кол-во"
                                variant="outlined"
                                @update:model-value="onItemChanged(itemRow)"
                                :error-messages="serverErrors[`items.${index}.quantity`]"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-select
                                v-model="itemRow.measure_id"
                                :items="measures"
                                item-title="name"
                                item-value="id"
                                label="Ед. изм."
                                variant="outlined"
                                :error-messages="serverErrors[`items.${index}.measure_id`]"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-text-field
                                v-model="itemRow.price"
                                type="number"
                                label="Цена"
                                variant="outlined"
                                @update:model-value="onItemChanged(itemRow)"
                                :error-messages="serverErrors[`items.${index}.price`]"
                            />
                        </v-col>

                        <v-col cols="12" md="2">
                            <v-select
                                v-model="itemRow.currency_id"
                                :items="currencies"
                                item-title="name"
                                item-value="id"
                                label="Валюта"
                                variant="outlined"
                                :error-messages="serverErrors[`items.${index}.currency_id`]"
                            />
                        </v-col>

                        <v-col cols="12" md="1">
                            <v-text-field
                                :model-value="itemRow.total"
                                label="Сумма"
                                variant="outlined"
                                readonly
                                :error-messages="serverErrors[`items.${index}.total`]"
                            />
                        </v-col>

                        <v-col cols="12" md="1">
                            <v-btn color="error" variant="text" @click="removeItemRow(index)">
                                Удалить
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" @click="closeDialog">Отмена</v-btn>
                    <v-btn color="primary" :loading="saving" @click="submit">
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>
