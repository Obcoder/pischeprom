<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: Object,
    dict: Object,
})

const emit = defineEmits(['refresh'])

const showAttachForm = ref(false)

const formAttachManufacturer = useForm({
    product_id: null,
    unit_id: props.unit.id,
})

const headers = [
    { key: 'rus', title: 'Title', align: 'start', sortable: true },
]

function attachManufacturer() {
    formAttachManufacturer.post(route('web.manufacturer.store'), {
        preserveState: true,
        onSuccess: async () => {
            formAttachManufacturer.reset()
            showAttachForm.value = false
            emit('refresh')
        },
    })
}
</script>

<template>
    <BaseSectionCard title="Manufactures" icon="mdi-package-variant-closed">
        <template #actions>
            <v-btn
                icon="mdi-plus"
                variant="text"
                size="small"
                @click="showAttachForm = !showAttachForm"
            />
        </template>

        <v-data-table
            :items="unit.manufactures || []"
            :headers="headers"
            items-per-page="8"
            fixed-header
            height="280"
            density="compact"
            class="border rounded-lg"
        >
            <template #item.rus="{ item }">
                <Link
                    :href="route('product.show', item.id)"
                    class="text-decoration-none font-weight-medium"
                >
                    {{ item.rus }}
                </Link>
            </template>
        </v-data-table>

        <v-expand-transition>
            <div v-if="showAttachForm" class="mt-4">
                <v-sheet rounded="lg" border class="pa-3 bg-grey-lighten-5">
                    <v-row>
                        <v-col cols="12">
                            <v-autocomplete
                                v-model="formAttachManufacturer.product_id"
                                :items="dict.products"
                                item-title="rus"
                                item-value="id"
                                label="Product"
                                variant="outlined"
                                density="comfortable"
                                hide-details
                            />
                        </v-col>
                    </v-row>

                    <div class="d-flex justify-end mt-3">
                        <v-btn
                            color="primary"
                            @click="attachManufacturer"
                            :loading="formAttachManufacturer.processing"
                        >
                            Attach
                        </v-btn>
                    </div>
                </v-sheet>
            </div>
        </v-expand-transition>
    </BaseSectionCard>
</template>
