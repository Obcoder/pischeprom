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
    units: {
        type: Array,
        default: () => [],
    },
    entities: {
        type: Array,
        default: () => [],
    },
    saving: Boolean,
})

const emit = defineEmits([
    'save',
])

const form = reactive({
    unit_ids: [],
    entity_ids: [],
})

watch(() => props.email, (email) => {
    form.unit_ids = email?.units?.map((unit) => unit.id) ?? []
    form.entity_ids = email?.entities?.map((entity) => entity.id) ?? []
}, {
    immediate: true,
})

function submit() {
    emit('save', {
        unit_ids: form.unit_ids,
        entity_ids: form.entity_ids,
    })
}
</script>

<template>
    <v-dialog
        v-model="model"
        width="900"
    >
        <v-card class="rounded border border-teal-900 bg-slate-950">
            <v-card-title class="text-teal-lighten-3">
                Связи Email
            </v-card-title>

            <v-card-subtitle>
                {{ email?.address }}
            </v-card-subtitle>

            <v-card-text>
                <v-row>
                    <v-col cols="12" lg="6">
                        <v-autocomplete
                            v-model="form.unit_ids"
                            :items="units"
                            item-value="id"
                            item-title="name"
                            label="Units"
                            variant="outlined"
                            density="compact"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" lg="6">
                        <v-autocomplete
                            v-model="form.entity_ids"
                            :items="entities"
                            item-value="id"
                            item-title="name"
                            label="Entities"
                            variant="outlined"
                            density="compact"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>
                </v-row>
            </v-card-text>

            <v-card-actions>
                <v-spacer />

                <v-btn
                    text="Отмена"
                    variant="text"
                    @click="model = false"
                />

                <v-btn
                    text="Сохранить связи"
                    color="teal"
                    variant="elevated"
                    :loading="saving"
                    @click="submit"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
