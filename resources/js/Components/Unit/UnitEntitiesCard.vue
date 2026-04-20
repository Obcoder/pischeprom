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

const dialogAttachEntity = ref(false)
const dialogCreateEntity = ref(false)

const formAttachEntity = useForm({
    entity_id: null,
    unit_id: props.unit.id,
})

const formEntity = useForm({
    name: null,
    entity_classification_id: null,
    telephones: [],
})

const headersEntities = [
    { title: 'Вид', key: 'classification.name' },
    { title: 'Name', key: 'name' },
    { title: 'Телефоны', key: 'telephones' },
]

function attachEntity() {
    formAttachEntity.post(route('api.entity_unit.store'), {
        preserveState: true,
        onSuccess: async () => {
            formAttachEntity.reset()
            dialogAttachEntity.value = false
            emit('refresh')
        },
    })
}

function storeEntity() {
    formEntity.post(route('api.entity.store'), {
        preserveState: true,
        onSuccess: async () => {
            formEntity.reset()
            dialogCreateEntity.value = false
            emit('refresh')
        },
    })
}
</script>

<template>
    <BaseSectionCard title="Entities" icon="mdi-domain">
        <template #actions>
            <div class="d-flex ga-2">
                <v-btn
                    size="small"
                    variant="text"
                    @click="dialogAttachEntity = true"
                >
                    Attach
                </v-btn>
                <v-btn
                    size="small"
                    variant="text"
                    @click="dialogCreateEntity = true"
                >
                    Create
                </v-btn>
            </div>
        </template>

        <v-data-table
            :items="unit.entities || []"
            :headers="headersEntities"
            density="compact"
            class="border rounded-lg"
            items-per-page="5"
        >
            <template #item.name="{ item }">
                <div class="font-weight-medium">
                    {{ item.name }}
                </div>
            </template>

            <template #item.telephones="{ item }">
                <div class="d-flex flex-column">
          <span
              v-for="telephone in item.telephones"
              :key="telephone.id"
              class="text-caption"
          >
            {{ telephone.number }}
          </span>
                </div>
            </template>
        </v-data-table>

        <!-- Attach entity -->
        <v-dialog v-model="dialogAttachEntity" max-width="720">
            <v-card rounded="xl">
                <v-card-title>Attach Entity</v-card-title>
                <v-card-text>
                    <v-autocomplete
                        v-model="formAttachEntity.entity_id"
                        :items="dict.entities"
                        item-title="name"
                        item-value="id"
                        label="Entity"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttachEntity = false">Cancel</v-btn>
                    <v-btn color="primary" @click="attachEntity" :loading="formAttachEntity.processing">
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Create entity -->
        <v-dialog v-model="dialogCreateEntity" max-width="820">
            <v-card rounded="xl">
                <v-card-title>Create Entity</v-card-title>
                <v-card-text>
                    <v-row>
                        <v-col cols="12">
                            <v-select
                                v-model="formEntity.entity_classification_id"
                                :items="dict.entityClassifications"
                                item-title="name"
                                item-value="id"
                                label="Вид"
                                variant="outlined"
                                density="comfortable"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-text-field
                                v-model="formEntity.name"
                                label="Name"
                                variant="outlined"
                                density="comfortable"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-autocomplete
                                v-model="formEntity.telephones"
                                :items="dict.telephones"
                                item-title="number"
                                item-value="id"
                                label="Telephones"
                                variant="outlined"
                                density="comfortable"
                                multiple
                                chips
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogCreateEntity = false">Cancel</v-btn>
                    <v-btn color="primary" @click="storeEntity" :loading="formEntity.processing">
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </BaseSectionCard>
</template>
