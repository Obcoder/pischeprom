<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'
import axios from 'axios'

const props = defineProps({
    unit: Object,
    dict: Object,
})

const emit = defineEmits(['refresh'])

const dialogAddQuotation = ref(false)

const headers = [
    { key: 'good.name', title: 'Good', sortable: true },
    { key: 'price', title: 'Price', sortable: true },
    { key: 'measure.name', title: 'Measure', sortable: true },
]

const formQuotation = useForm({
    unit_id: props.unit.id,
    good_id: null,
    price: null,
    measure_id: null,
})

async function searchGoods(search = '') {
    try {
        const { data } = await axios.get(route('goods.index'), {
            params: { search }
        })
        props.dict.goods = Array.isArray(data) ? data : (data.data || [])
    } catch (e) {
        console.error(e)
    }
}

function storeQuotation() {
    formQuotation.unit_id = props.unit.id

    formQuotation.post(route('web.quotation.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: async () => {
            formQuotation.reset()
            dialogAddQuotation.value = false
            emit('refresh')
        },
    })
}
</script>

<template>
    <BaseSectionCard title="Quotations" icon="mdi-cash-multiple">
        <template #actions>
            <v-btn
                size="small"
                color="deep-purple-darken-1"
                variant="tonal"
                @click="dialogAddQuotation = true"
            >
                + Quotation
            </v-btn>
        </template>

        <v-data-table
            :items="unit.quotations || []"
            :headers="headers"
            items-per-page="10"
            fixed-header
            height="340"
            density="compact"
            class="border rounded-lg"
        >
            <template #item.good.name="{ item }">
                <Link
                    :href="route('Ameise.good.show', item.good.id)"
                    class="text-primary font-weight-medium text-decoration-none"
                >
                    {{ item.good.name }}
                </Link>
            </template>

            <template #item.price="{ item }">
                {{ Number(item.price).toFixed(2) }}
            </template>
        </v-data-table>

        <v-dialog v-model="dialogAddQuotation" max-width="860">
            <v-card rounded="xl">
                <v-card-title>Новая цена (Quotation)</v-card-title>
                <v-card-text>
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="formQuotation.good_id"
                                :items="dict.goods"
                                item-title="name"
                                item-value="id"
                                label="Good"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                @update:search="searchGoods"
                            />
                            <div v-if="formQuotation.errors.good_id" class="text-red text-caption">
                                {{ formQuotation.errors.good_id }}
                            </div>
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="formQuotation.price"
                                label="Price"
                                type="number"
                                step="0.01"
                                variant="outlined"
                                density="comfortable"
                                clearable
                            />
                            <div v-if="formQuotation.errors.price" class="text-red text-caption">
                                {{ formQuotation.errors.price }}
                            </div>
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-select
                                v-model="formQuotation.measure_id"
                                :items="dict.measures"
                                item-title="name"
                                item-value="id"
                                label="Measure"
                                variant="outlined"
                                density="comfortable"
                                clearable
                            />
                            <div v-if="formQuotation.errors.measure_id" class="text-red text-caption">
                                {{ formQuotation.errors.measure_id }}
                            </div>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAddQuotation = false">
                        Cancel
                    </v-btn>
                    <v-btn
                        color="deep-purple-darken-1"
                        variant="tonal"
                        :loading="formQuotation.processing"
                        @click="storeQuotation"
                    >
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </BaseSectionCard>
</template>
