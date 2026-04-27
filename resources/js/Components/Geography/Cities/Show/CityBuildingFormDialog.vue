<script setup>
import { computed, reactive, watch } from 'vue'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    mode: {
        type: String,
        default: 'create',
    },
    city: {
        type: Object,
        required: true,
    },
    building: {
        type: Object,
        default: null,
    },
    loading: Boolean,
})

const emit = defineEmits(['save'])

const form = reactive({
    address: null,
    postcode: null,
})

const title = computed(() => {
    return props.mode === 'create'
        ? `Добавить здание: ${props.city?.name ?? ''}`
        : `Редактировать здание`
})

function resetForm() {
    form.address = props.building?.address ?? null
    form.postcode = props.building?.postcode ?? null
}

function submit() {
    emit('save', {
        city_id: props.city.id,
        address: form.address,
        postcode: form.postcode,
    })
}

watch(
    () => model.value,
    value => {
        if (value) {
            resetForm()
        }
    }
)

watch(
    () => props.building,
    () => {
        if (model.value) {
            resetForm()
        }
    }
)
</script>

<template>
    <v-dialog
        v-model="model"
        width="720"
    >
        <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
                <span>{{ title }}</span>

                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="model = false"
                />
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-alert
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                >
                    Город будет подставлен автоматически:
                    <strong>{{ city.name }}</strong>
                </v-alert>

                <v-form @submit.prevent="submit">
                    <v-row>
                        <v-col>
                            <v-text-field
                                v-model="form.address"
                                label="Адрес здания"
                                placeholder="ул. Ленина, 10"
                                variant="outlined"
                                density="comfortable"
                                color="teal"
                                autofocus
                            />
                        </v-col>
                    </v-row>

                    <v-row>
                        <v-col cols="12" md="5">
                            <v-text-field
                                v-model="form.postcode"
                                label="Почтовый индекс"
                                placeholder="140000"
                                variant="solo"
                                density="comfortable"
                                color="blue-grey"
                            />
                        </v-col>

                        <v-col cols="12" md="7">
                            <v-text-field
                                :model-value="city.name"
                                label="Город"
                                variant="solo-filled"
                                density="comfortable"
                                readonly
                            />
                        </v-col>
                    </v-row>
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
                    :text="mode === 'create' ? 'Добавить' : 'Обновить'"
                    :loading="loading"
                    color="teal"
                    variant="elevated"
                    @click="submit"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
