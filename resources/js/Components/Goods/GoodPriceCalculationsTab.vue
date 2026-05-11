<script setup>
import { onMounted, ref } from 'vue'
import { useGoodPriceCalculations } from '@/Composables/useGoodPriceCalculations'

const props = defineProps({
    goodId: {
        type: Number,
        required: true,
    },
})

const {
    calculations,
    loading,
    saving,
    deleting,
    fetchCalculations,
    updateCalculation,
    deleteCalculation,
} = useGoodPriceCalculations(props.goodId)

const dialogEdit = ref(false)
const edited = ref(null)

const headers = [
    { key: 'created_at', title: 'Дата', sortable: true, width: '150px' },
    { key: 'name', title: 'Название', sortable: true },
    { key: 'source', title: 'Источник', sortable: false },
    { key: 'sale_gross_per_kg', title: 'Продажа / кг с НДС', sortable: true },
    { key: 'sale_gross_per_box', title: 'Продажа / коробка с НДС', sortable: true },
    { key: 'margin_percent', title: 'Маржа %', sortable: true },
    { key: 'markup_percent', title: 'Наценка %', sortable: true },
    { key: 'actions', title: '', sortable: false, width: '120px' },
]

function formatMoney(value) {
    const number = Number(value || 0)

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number)
}

function formatDate(value) {
    if (!value) return '-'

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function sourceTitle(item) {
    if (item.quotation) {
        return `Quotation: ${item.quotation.unit?.name || '-'}`
    }

    if (item.purchase) {
        return `Закупка: ${item.purchase.entity?.name || '-'}`
    }

    return 'Ручной расчёт'
}

function openEdit(item) {
    edited.value = {
        id: item.id,
        name: item.name || '',
        comment: item.comment || '',
    }

    dialogEdit.value = true
}

async function saveEdit() {
    if (!edited.value?.id) return

    await updateCalculation(edited.value.id, {
        name: edited.value.name,
        comment: edited.value.comment,
    })

    dialogEdit.value = false
}

onMounted(() => {
    fetchCalculations()
})
</script>

<template>
    <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Сохранённые расчёты продажной цены</span>

            <v-btn
                icon="mdi-refresh"
                variant="text"
                :loading="loading"
                @click="fetchCalculations"
            />
        </v-card-title>

        <v-card-text>
            <v-alert
                type="info"
                variant="tonal"
                class="mb-4"
            >
                Здесь хранятся расчёты, которые были сохранены из калькулятора продажной цены.
            </v-alert>

            <v-data-table
                :items="calculations"
                :headers="headers"
                :loading="loading"
                items-per-page="50"
                fixed-header
                height="520"
                density="compact"
                class="border rounded"
                hover
            >
                <template #item.created_at="{ item }">
                    <span class="text-caption">
                        {{ formatDate(item.created_at) }}
                    </span>
                </template>

                <template #item.name="{ item }">
                    <div class="font-weight-medium">
                        {{ item.name || `Расчёт #${item.id}` }}
                    </div>

                    <div
                        v-if="item.comment"
                        class="text-caption text-medium-emphasis"
                    >
                        {{ item.comment }}
                    </div>
                </template>

                <template #item.source="{ item }">
                    <v-chip
                        size="small"
                        variant="tonal"
                    >
                        {{ sourceTitle(item) }}
                    </v-chip>
                </template>

                <template #item.sale_gross_per_kg="{ item }">
                    <strong>{{ formatMoney(item.sale_gross_per_kg) }}</strong>
                    <span class="text-caption ml-1">
                        {{ item.currency?.code || 'RUB' }}
                    </span>
                </template>

                <template #item.sale_gross_per_box="{ item }">
                    <strong>{{ formatMoney(item.sale_gross_per_box) }}</strong>
                    <span class="text-caption ml-1">
                        {{ item.currency?.code || 'RUB' }}
                    </span>
                </template>

                <template #item.margin_percent="{ item }">
                    {{ Number(item.margin_percent || 0).toFixed(2) }}%
                </template>

                <template #item.markup_percent="{ item }">
                    {{ Number(item.markup_percent || 0).toFixed(2) }}%
                </template>

                <template #item.actions="{ item }">
                    <v-btn
                        icon="mdi-pencil"
                        size="small"
                        variant="text"
                        @click="openEdit(item)"
                    />

                    <v-btn
                        icon="mdi-delete"
                        size="small"
                        variant="text"
                        color="red"
                        :loading="!!deleting[item.id]"
                        @click="deleteCalculation(item.id)"
                    />
                </template>

                <template #no-data>
                    <div class="pa-6 text-center text-medium-emphasis">
                        Сохранённых расчётов пока нет.
                    </div>
                </template>
            </v-data-table>
        </v-card-text>

        <v-dialog
            v-model="dialogEdit"
            width="560"
        >
            <v-card>
                <v-card-title>Редактировать расчёт</v-card-title>

                <v-card-text v-if="edited">
                    <v-text-field
                        v-model="edited.name"
                        label="Название"
                        variant="outlined"
                        density="compact"
                    />

                    <v-textarea
                        v-model="edited.comment"
                        label="Комментарий"
                        variant="outlined"
                        density="compact"
                        rows="4"
                    />
                </v-card-text>

                <v-card-actions>
                    <v-btn
                        variant="text"
                        @click="dialogEdit = false"
                    >
                        Закрыть
                    </v-btn>

                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="saving"
                        @click="saveEdit"
                    >
                        Сохранить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>
