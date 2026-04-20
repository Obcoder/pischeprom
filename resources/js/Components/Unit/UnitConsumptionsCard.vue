<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { useDate } from 'vuetify'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: Object,
    dict: Object,
})

const emit = defineEmits(['refresh'])

const date = useDate()
const showForm = ref(false)

const formConsumption = useForm({
    unit_id: props.unit.id,
    product_id: null,
    quantity: null,
    measure_id: null,
})

const headers = [
    { title: 'Created', key: 'created_at' },
    { title: 'Product', key: 'product_id' },
    { title: 'Quantity', key: 'quantity' },
    { title: 'Measure', key: 'measure_id' },
]

function storeConsumption() {
    formConsumption.post(route('api.consumption.store'), {
        preserveState: true,
        onSuccess: async () => {
            formConsumption.reset()
            showForm.value = false
            emit('refresh')
        },
    })
}
</script>

<template>
    <BaseSectionCard title="Consumptions" icon="mdi-chart-timeline-variant">
        <template #actions>
            <v-btn
                size="small"
                variant="text"
                @click="showForm = !showForm"
            >
                Add
            </v-btn>
        </template>

        <v-expand-transition>
            <div v-if="showForm" class="mb-4">
                <v-sheet rounded="lg" border class="pa-4 bg-grey-lighten-5">
                    <v-row>
                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="formConsumption.product_id"
                                :items="dict.products"
                                item-title="rus"
                                item-value="id"
                                label="Product"
                                variant="outlined"
                                density="comfortable"
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-text-field
                                v-model="formConsumption.quantity"
                                label="Quantity"
                                variant="outlined"
                                density="comfortable"
                            />
                        </v-col>

                        <v-col cols="12" md="3">
                            <v-select
                                v-model="formConsumption.measure_id"
                                :items="dict.measures"
                                item-title="name"
                                item-value="id"
                                label="Measure"
                                variant="outlined"
                                density="comfortable"
                            />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-end">
                        <v-btn
                            color="primary"
                            @click="storeConsumption"
                            :loading="formConsumption.processing"
                        >
                            Save
                        </v-btn>
                    </div>
                </v-sheet>
            </div>
        </v-expand-transition>

        <v-data-table
            :items="unit.consumptions || []"
            :headers="headers"
            density="compact"
            items-per-page="8"
            fixed-header
            height="320"
            class="border rounded-lg"
        >
            <template #item.product_id="{ item }">
                <Link :href="route('product.show', item.product_id)" class="text-decoration-none">
                    {{ item.product?.rus }}
                </Link>
            </template>

            <template #item.created_at="{ item }">
                {{ date.format(item.created_at, 'fullDate') }}
            </template>

            <template #item.measure_id="{ item }">
                {{ item.measure?.name }}
            </template>
        </v-data-table>
    </BaseSectionCard>
</template>
