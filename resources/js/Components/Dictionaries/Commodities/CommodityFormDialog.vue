<script setup>
import { computed, ref, watch } from 'vue'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    commodity: {
        type: Object,
        default: null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits([
    'save',
])

const form = ref({
    name: '',
})

const isEdit = computed(() => Boolean(props.commodity?.id))

watch(
    () => props.commodity,
    (value) => {
        form.value = {
            name: value?.name || '',
        }
    },
    {
        immediate: true,
    }
)

function submit() {
    emit('save', {
        ...form.value,
    })
}
</script>

<template>
    <v-dialog v-model="model" width="720">
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>
                    {{ isEdit ? 'Редактировать Commodity' : 'Новая Commodity' }}
                </span>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="compact"
                    @click="model = false"
                />
            </v-card-title>

            <v-card-text>
                <v-form @submit.prevent="submit">
                    <v-row>
                        <v-col cols="12">
                            <v-text-field
                                v-model="form.name"
                                label="Наименование"
                                variant="outlined"
                                density="compact"
                                autofocus
                                hide-details
                            />
                        </v-col>
                    </v-row>

                    <v-alert
                        v-if="isEdit"
                        type="info"
                        variant="tonal"
                        density="compact"
                        class="mt-4"
                    >
                        Изображения и ava редактируются на отдельной странице Commodity.
                    </v-alert>
                </v-form>
            </v-card-text>

            <v-card-actions>
                <v-spacer />

                <v-btn
                    text="Отмена"
                    variant="text"
                    @click="model = false"
                />

                <v-btn
                    :text="isEdit ? 'Сохранить' : 'Создать'"
                    color="primary"
                    variant="elevated"
                    :loading="saving"
                    @click="submit"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
