<template>
    <v-dialog
        :model-value="modelValue"
        max-width="700"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <v-card>
            <v-card-title>
                {{ isEdit ? 'Редактирование телефона' : 'Новый телефон' }}
            </v-card-title>

            <v-card-text>
                <v-row>
                    <v-col cols="12">
                        <v-text-field
                            v-model="form.number"
                            label="Номер"
                            variant="outlined"
                            density="comfortable"
                            :error-messages="errors.number || []"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-autocomplete
                            v-model="form.entity_ids"
                            :items="entities"
                            item-title="name"
                            item-value="id"
                            label="Entities"
                            multiple
                            chips
                            closable-chips
                            clearable
                            variant="outlined"
                            density="comfortable"
                            :error-messages="errors.entity_ids || []"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-autocomplete
                            v-model="form.unit_ids"
                            :items="units"
                            item-title="name"
                            item-value="id"
                            label="Units"
                            multiple
                            chips
                            closable-chips
                            clearable
                            variant="outlined"
                            density="comfortable"
                            :error-messages="errors.unit_ids || []"
                        />
                    </v-col>
                </v-row>
            </v-card-text>

            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="$emit('close')">
                    Отмена
                </v-btn>

                <v-btn
                    color="primary"
                    :loading="saving"
                    @click="$emit('submit')"
                >
                    Сохранить
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    saving: {
        type: Boolean,
        default: false,
    },
    entities: {
        type: Array,
        default: () => [],
    },
    units: {
        type: Array,
        default: () => [],
    },
})

defineEmits([
    'update:modelValue',
    'submit',
    'close',
])

const isEdit = computed(() => !!props.form.id)
</script>
