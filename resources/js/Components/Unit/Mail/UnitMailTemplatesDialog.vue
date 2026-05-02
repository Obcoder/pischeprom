<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const model = defineModel({
    type: Boolean,
    default: false,
})

const templates = ref([])
const loading = ref(false)
const saving = ref(false)
const editingId = ref(null)

const form = ref({
    name: '',
    subject: '',
    body: '',
    is_active: true,
})

async function fetchTemplates() {
    loading.value = true

    try {
        const { data } = await axios.get('/api/mail-templates')
        templates.value = data ?? []
    } catch (error) {
        console.error('Templates loading error:', error)
    } finally {
        loading.value = false
    }
}

function resetForm() {
    editingId.value = null

    form.value = {
        name: '',
        subject: '',
        body: '',
        is_active: true,
    }
}

function editTemplate(template) {
    editingId.value = template.id

    form.value = {
        name: template.name || '',
        subject: template.subject || '',
        body: template.body || '',
        is_active: Boolean(template.is_active),
    }
}

async function saveTemplate() {
    saving.value = true

    try {
        if (editingId.value) {
            await axios.put(`/api/mail-templates/${editingId.value}`, form.value)
        } else {
            await axios.post('/api/mail-templates', form.value)
        }

        resetForm()
        await fetchTemplates()
    } catch (error) {
        console.error('Template saving error:', error)
    } finally {
        saving.value = false
    }
}

async function deleteTemplate(template) {
    if (!confirm(`Удалить шаблон "${template.name}"?`)) return

    await axios.delete(`/api/mail-templates/${template.id}`)

    if (editingId.value === template.id) {
        resetForm()
    }

    await fetchTemplates()
}

watch(model, (value) => {
    if (value) {
        fetchTemplates()
    }
})
</script>

<template>
    <v-dialog v-model="model" max-width="1100" scrollable>
        <v-card class="rounded border border-blue-900 bg-slate-950">
            <v-card-title class="d-flex justify-space-between align-center">
                <div class="text-blue-lighten-3">
                    Шаблоны писем
                </div>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="model = false"
                />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row>
                    <v-col cols="12" lg="5">
                        <v-card variant="tonal" class="pa-3">
                            <div class="text-subtitle-2 mb-3">
                                {{ editingId ? 'Редактировать шаблон' : 'Новый шаблон' }}
                            </div>

                            <v-text-field
                                v-model="form.name"
                                label="Название"
                                variant="outlined"
                                density="compact"
                            />

                            <v-text-field
                                v-model="form.subject"
                                label="Тема"
                                variant="outlined"
                                density="compact"
                            />

                            <v-textarea
                                v-model="form.body"
                                label="Текст"
                                variant="outlined"
                                rows="12"
                                auto-grow
                                hint="Можно использовать: {{unit.name}}, {{unit.id}}, {{email.address}}"
                                persistent-hint
                            />

                            <v-switch
                                v-model="form.is_active"
                                label="Активен"
                                color="blue"
                                hide-details
                            />

                            <div class="d-flex justify-end ga-2 mt-3">
                                <v-btn
                                    variant="text"
                                    @click="resetForm"
                                >
                                    Сброс
                                </v-btn>

                                <v-btn
                                    color="blue"
                                    :loading="saving"
                                    :disabled="!form.name"
                                    @click="saveTemplate"
                                >
                                    Сохранить
                                </v-btn>
                            </div>
                        </v-card>
                    </v-col>

                    <v-col cols="12" lg="7">
                        <v-progress-linear
                            v-if="loading"
                            indeterminate
                            color="blue"
                        />

                        <v-list density="compact" class="rounded border border-blue-900 bg-slate-950">
                            <v-list-item
                                v-for="template in templates"
                                :key="template.id"
                            >
                                <template #title>
                                    <div class="text-blue-lighten-3">
                                        {{ template.name }}
                                    </div>
                                </template>

                                <template #subtitle>
                                    <div class="text-[11px] text-grey">
                                        {{ template.subject || 'Без темы' }}
                                    </div>
                                </template>

                                <template #append>
                                    <div class="d-flex ga-1">
                                        <v-btn
                                            icon="mdi-pencil"
                                            size="x-small"
                                            variant="text"
                                            color="amber"
                                            @click="editTemplate(template)"
                                        />

                                        <v-btn
                                            icon="mdi-delete"
                                            size="x-small"
                                            variant="text"
                                            color="red"
                                            @click="deleteTemplate(template)"
                                        />
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-col>
                </v-row>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>
