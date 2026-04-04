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
                            v-model="localForm.number"
                            label="Номер"
                            variant="outlined"
                            density="comfortable"
                            :error-messages="errors.number || []"
                        />
                    </v-col>

                    <v-col cols="12">
                        <v-autocomplete
                            v-model="localForm.entity_ids"
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
                            v-model="localForm.unit_ids"
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
                    @click="onSubmit"
                >
                    Сохранить
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'

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

const emit = defineEmits([
    'update:modelValue',
    'update:form',
    'submit',
    'close',
])

const localForm = reactive({
    id: null,
    number: '',
    entity_ids: [],
    unit_ids: [],
})

watch(
    () => props.form,
    (value) => {
        localForm.id = value?.id ?? null
        localForm.number = value?.number ?? ''
        localForm.entity_ids = Array.isArray(value?.entity_ids) ? [...value.entity_ids] : []
        localForm.unit_ids = Array.isArray(value?.unit_ids) ? [...value.unit_ids] : []
    },
    { immediate: true, deep: true }
)

watch(
    localForm,
    (value) => {
        emit('update:form', {
            id: value.id,
            number: value.number,
            entity_ids: [...value.entity_ids],
            unit_ids: [...value.unit_ids],
        })
    },
    { deep: true }
)

const isEdit = computed(() => !!localForm.id)

const onSubmit = () => {
    emit('submit')
}
</script>
