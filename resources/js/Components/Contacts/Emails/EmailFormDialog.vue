<script setup>
import { reactive, watch } from 'vue'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    email: {
        type: Object,
        default: null,
    },
    saving: Boolean,
})

const emit = defineEmits([
    'save',
])

const form = reactive({
    address: '',
    name: '',
    comment: '',
    source: 'manual',
    is_active: true,
    verified_at: null,
    last_seen_at: null,
})

watch(() => props.email, (email) => {
    form.address = email?.address ?? ''
    form.name = email?.name ?? ''
    form.comment = email?.comment ?? ''
    form.source = email?.source ?? 'manual'
    form.is_active = email?.is_active ?? true
    form.verified_at = email?.verified_at ?? null
    form.last_seen_at = email?.last_seen_at ?? null
}, {
    immediate: true,
})

function submit() {
    emit('save', {
        ...form,
    })
}
</script>

<template>
    <v-dialog
        v-model="model"
        width="760"
    >
        <v-card class="rounded border border-teal-900 bg-slate-950">
            <v-card-title class="text-teal-lighten-3">
                {{ email ? 'Редактировать Email' : 'Добавить Email' }}
            </v-card-title>

            <v-card-text>
                <v-form @submit.prevent="submit">
                    <v-row>
                        <v-col cols="12" lg="7">
                            <v-text-field
                                v-model="form.address"
                                label="Email address"
                                type="email"
                                variant="outlined"
                                density="compact"
                                required
                            />
                        </v-col>

                        <v-col cols="12" lg="5">
                            <v-text-field
                                v-model="form.name"
                                label="Name / title"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col cols="12" lg="6">
                            <v-text-field
                                v-model="form.source"
                                label="Source"
                                variant="outlined"
                                density="compact"
                            />
                        </v-col>

                        <v-col cols="12" lg="6">
                            <v-switch
                                v-model="form.is_active"
                                label="Активен"
                                color="teal"
                                density="compact"
                                hide-details
                            />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col>
                            <v-textarea
                                v-model="form.comment"
                                label="Comment"
                                variant="outlined"
                                density="compact"
                                rows="4"
                            />
                        </v-col>
                    </v-row>
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
                    text="Сохранить"
                    color="teal"
                    variant="elevated"
                    :loading="saving"
                    @click="submit"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
