<script setup>
import { useGoodPriceCalculations } from '@/Composables/useGoodPriceCalculations'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { usePriceTypes } from '@/Composables/usePriceTypes'
import { useGoodPriceTypeValues } from '@/Composables/useGoodPriceTypeValues'

const props = defineProps({
    goodId: {
        type: Number,
        required: true,
    },
    tableHeight: {
        type: [Number, String],
        default: 520,
    },
    showIntro: {
        type: Boolean,
        default: true,
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

const emit = defineEmits(['applied'])

const {
    priceTypes,
    fetchPriceTypes,
} = usePriceTypes()

const {
    storeValue,
    saving: applying,
} = useGoodPriceTypeValues(props.goodId)

const dialogApply = ref(false)
const applySource = ref(null)

const applyForm = reactive({
    price_type_id: null,
    price_net: null,
    price_gross: null,
    vat_rate: 20,
    is_manual: false,
    is_published: false,
    manual_comment: '',
})

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

const selectedPriceType = computed(() => {
    return priceTypes.value.find((item) => Number(item.id) === Number(applyForm.price_type_id)) || null
})

watch(
    () => applyForm.price_type_id,
    () => {
        if (selectedPriceType.value) {
            applyForm.is_published = !!selectedPriceType.value.is_public
        }
    }
)

function openApply(item) {
    applySource.value = item

    applyForm.price_type_id = null
    applyForm.price_net = item.sale_net_per_kg ?? null
    applyForm.price_gross = item.sale_gross_per_kg ?? null
    applyForm.vat_rate = item.result?.input?.vatRate ?? item.input?.vatRate ?? 20
    applyForm.is_manual = false
    applyForm.is_published = false
    applyForm.manual_comment = item.name || `Расчёт #${item.id}`

    dialogApply.value = true
}

async function applyToPriceType() {
    if (!applySource.value?.id || !applyForm.price_type_id) return

    await storeValue({
        calculation_id: applySource.value.id,
        price_type_id: applyForm.price_type_id,
        price_net: applyForm.price_net,
        price_gross: applyForm.price_gross,
        vat_rate: applyForm.vat_rate,
        is_manual: applyForm.is_manual,
        manual_comment: applyForm.manual_comment,
        is_published: applyForm.is_published,
    })

    dialogApply.value = false
    emit('applied')
}

onMounted(() => {
    fetchCalculations()
    fetchPriceTypes()
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
                v-if="showIntro"
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
                :height="tableHeight"
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
                        icon="mdi-tag-plus"
                        size="small"
                        variant="text"
                        color="deep-purple"
                        @click="openApply(item)"
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

                <v-dialog
                    v-model="dialogApply"
                    width="640"
                >
                    <v-card>
                        <v-card-title>Применить расчёт к виду цены</v-card-title>

                        <v-card-text>
                            <v-select
                                v-model="applyForm.price_type_id"
                                :items="priceTypes"
                                item-title="name"
                                item-value="id"
                                label="Вид цены"
                                variant="outlined"
                                density="compact"
                            >
                                <template #item="{ props, item }">
                                    <v-list-item
                                        v-bind="props"
                                        :title="item.raw.name"
                                        :subtitle="item.raw.code"
                                    />
                                </template>
                            </v-select>

                            <v-row>
                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="applyForm.price_gross"
                                        label="Цена с НДС / кг"
                                        type="number"
                                        step="0.01"
                                        variant="outlined"
                                        density="compact"
                                    />
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="applyForm.price_net"
                                        label="Цена без НДС / кг"
                                        type="number"
                                        step="0.01"
                                        variant="outlined"
                                        density="compact"
                                    />
                                </v-col>

                                <v-col cols="12" md="4">
                                    <v-text-field
                                        v-model="applyForm.vat_rate"
                                        label="НДС %"
                                        type="number"
                                        step="0.01"
                                        variant="outlined"
                                        density="compact"
                                    />
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-switch
                                        v-model="applyForm.is_manual"
                                        label="Считать ручной ценой"
                                        color="orange"
                                        inset
                                        hide-details
                                    />
                                </v-col>

                                <v-col cols="12" md="6">
                                    <v-switch
                                        v-model="applyForm.is_published"
                                        label="Публиковать"
                                        color="green"
                                        inset
                                        hide-details
                                    />
                                </v-col>

                                <v-col cols="12">
                                    <v-textarea
                                        v-model="applyForm.manual_comment"
                                        label="Комментарий"
                                        rows="3"
                                        variant="outlined"
                                        density="compact"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>

                        <v-card-actions>
                            <v-btn
                                variant="text"
                                @click="dialogApply = false"
                            >
                                Закрыть
                            </v-btn>

                            <v-btn
                                color="deep-purple-darken-1"
                                variant="tonal"
                                :loading="applying"
                                :disabled="!applyForm.price_type_id"
                                @click="applyToPriceType"
                            >
                                Применить
                            </v-btn>
                        </v-card-actions>
                    </v-card>
                </v-dialog>
            </v-card>
        </v-dialog>
    </v-card>
</template>
