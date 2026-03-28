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
                    density="comfortable"
                    clearable
                    @keyup.enter="loadPurchases"
                />

                <v-data-table
                    :headers="headers"
                    :items="items"
                    :loading="loading"
                    item-value="id"
                >
                    <template #item.entity="{ item }">
                        {{ item.entity?.name }}
                    </template>

                    <template #item.goods="{ item }">
                        <div class="d-flex flex-wrap ga-1">
                            <v-chip
                                v-for="good in item.goods"
                                :key="good.id"
                                size="small"
                            >
                                {{ good.name }}
                            </v-chip>
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

        <v-dialog v-model="dialog" max-width="900">
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
                            <v-select
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
                                v-model="form.amount"
                                type="number"
                                label="Сумма"
                                variant="outlined"
                                :error-messages="serverErrors.amount"
                            />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-space-between align-center mb-2">
                        <h3 class="text-h6">Товары</h3>
                        <v-btn size="small" @click="addGoodRow">Добавить товар</v-btn>
                    </div>

                    <v-row
                        v-for="(goodRow, index) in form.goods"
                        :key="index"
                        class="align-center"
                    >
                        <v-col cols="10">
                            <v-select
                                v-model="goodRow.id"
                                :items="goodsOptions"
                                item-title="name"
                                item-value="id"
                                label="Товар"
                                variant="outlined"
                            />
                        </v-col>
                        <v-col cols="2">
                            <v-btn color="error" variant="text" @click="removeGoodRow(index)">
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

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { usePurchases } from '@/composables/usePurchases'
import { usePurchaseForm } from '@/composables/usePurchaseForm'

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
} = usePurchaseForm()

const dialog = ref(false)
const saving = ref(false)
const search = ref('')
const entities = ref([])
const goodsOptions = ref([])
const serverErrors = ref({})

const headers = [
    { title: 'ID', key: 'id' },
    { title: 'Дата', key: 'date' },
    { title: 'Контрагент', key: 'entity' },
    { title: 'Сумма', key: 'amount' },
    { title: 'Товары', key: 'goods', sortable: false },
    { title: 'Действия', key: 'actions', sortable: false },
]

const loadPurchases = async () => {
    await fetchPurchases({ search: search.value })
}

const loadDictionaries = async () => {
    const [entitiesRes, goodsRes] = await Promise.all([
        axios.get('/api/entities', { params: { per_page: 1000 } }),
        axios.get('/api/goods', { params: { per_page: 1000 } }),
    ])

    entities.value = entitiesRes.data.data
    goodsOptions.value = goodsRes.data.data
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
    serverErrors.value = {}
    dialog.value = true
}

const closeDialog = () => {
    dialog.value = false
    resetForm()
    serverErrors.value = {}
}

const addGoodRow = () => {
    form.goods.push({
        id: null,
    })
}

const removeGoodRow = (index) => {
    form.goods.splice(index, 1)
}

const submit = async () => {
    saving.value = true
    serverErrors.value = {}

    try {
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
