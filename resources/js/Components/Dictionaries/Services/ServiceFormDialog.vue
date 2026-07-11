<script setup>
import { computed, ref, watch } from 'vue'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    service: {
        type: Object,
        default: null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    expenseArticles: {
        type: Array,
        default: () => [],
    },
    projects: {
        type: Array,
        default: () => [],
    },
    error: {
        type: String,
        default: '',
    },
})

const emit = defineEmits([
    'save',
])

const formRef = ref(null)
const form = ref(emptyForm())

const isEdit = computed(() => Boolean(props.service?.id))

const rules = {
    required: value => !!String(value ?? '').trim() || 'Обязательное поле',
}

watch(
    () => props.service,
    (value) => {
        fillForm(value)
    },
    {
        immediate: true,
    }
)

watch(
    () => model.value,
    (opened) => {
        if (opened && !props.service?.id) {
            fillForm(null)
        }
    }
)

function emptyForm() {
    return {
        name: '',
        code: '',
        expense_article_id: null,
        project_id: null,
        description: '',
        is_active: true,
    }
}

function fillForm(value) {
    form.value = {
        name: value?.name || '',
        code: value?.code || '',
        expense_article_id: value?.expense_article_id || null,
        project_id: value?.project_id || null,
        description: value?.description || '',
        is_active: value?.is_active ?? true,
    }
}

function normalizedPayload() {
    return {
        name: String(form.value.name ?? '').trim(),
        code: normalizeNullable(form.value.code),
        expense_article_id: form.value.expense_article_id || null,
        project_id: form.value.project_id || null,
        description: normalizeNullable(form.value.description),
        is_active: Boolean(form.value.is_active),
    }
}

function normalizeNullable(value) {
    const normalized = String(value ?? '').trim()

    return normalized === '' ? null : normalized
}

async function submit() {
    const validation = await formRef.value?.validate?.()

    if (validation && !validation.valid) {
        return
    }

    emit('save', normalizedPayload())
}
</script>

<template>
    <v-dialog v-model="model" width="820" scrollable>
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>
                    {{ isEdit ? 'Редактировать услугу' : 'Новая услуга' }}
                </span>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    density="compact"
                    @click="model = false"
                />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-form ref="formRef" @submit.prevent="submit">
                    <v-row dense>
                        <v-col cols="12" md="8">
                            <v-text-field
                                v-model="form.name"
                                label="Наименование"
                                variant="outlined"
                                density="compact"
                                autofocus
                                :rules="[rules.required]"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12" md="4">
                            <v-text-field
                                v-model="form.code"
                                label="Код"
                                variant="outlined"
                                density="compact"
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.expense_article_id"
                                :items="expenseArticles"
                                item-title="name"
                                item-value="id"
                                label="Статья расходов"
                                variant="outlined"
                                density="compact"
                                clearable
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12" md="6">
                            <v-autocomplete
                                v-model="form.project_id"
                                :items="projects"
                                item-title="name"
                                item-value="id"
                                label="Проект"
                                variant="outlined"
                                density="compact"
                                clearable
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-textarea
                                v-model="form.description"
                                label="Описание"
                                variant="outlined"
                                density="compact"
                                rows="4"
                                auto-grow
                                hide-details="auto"
                            />
                        </v-col>

                        <v-col cols="12">
                            <v-switch
                                v-model="form.is_active"
                                color="success"
                                density="compact"
                                inset
                                hide-details
                                label="Активна"
                            />
                        </v-col>
                    </v-row>

                    <v-alert
                        v-if="error"
                        type="error"
                        variant="tonal"
                        density="compact"
                        class="mt-3 service-form-error"
                        :text="error"
                    />
                </v-form>
            </v-card-text>

            <v-divider />

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

<style scoped>
.service-form-error {
    white-space: pre-line;
}
</style>
