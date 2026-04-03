<script setup>
const props = defineProps({
    modelValue: Boolean,
    loading: Boolean,
    isEdit: Boolean,
    form: { type: Object, required: true },
    meta: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const close = () => emit('update:modelValue', false)
const save = () => emit('submit')
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="950"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card>
            <v-card-title>
                {{ isEdit ? 'Редактирование Entity' : 'Новая Entity' }}
            </v-card-title>

            <v-card-text>
                <v-row dense>
                    <v-col cols="12" md="6">
                        <v-text-field v-model="form.name" label="Название" />
                    </v-col>

                    <v-col cols="12" md="3">
                        <v-text-field v-model="form.INN" label="INN" />
                    </v-col>

                    <v-col cols="12" md="3">
                        <v-text-field v-model="form.OGRN" label="OGRN" />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.entity_classification_id"
                            :items="meta.classifications"
                            item-title="name"
                            item-value="id"
                            label="Классификация"
                            clearable
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.country_id"
                            :items="meta.countries"
                            item-title="name"
                            item-value="id"
                            label="Страна"
                            clearable
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.cities"
                            :items="meta.cities"
                            item-title="name"
                            item-value="id"
                            label="Города"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.buildings"
                            :items="meta.buildings"
                            item-title="name"
                            item-value="id"
                            label="Здания"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.emails"
                            :items="meta.emails"
                            item-title="address"
                            item-value="id"
                            label="Emails"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.telephones"
                            :items="meta.telephones"
                            item-title="number"
                            item-value="id"
                            label="Телефоны"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.units"
                            :items="meta.units"
                            item-title="name"
                            item-value="id"
                            label="Units"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-select
                            v-model="form.chats"
                            :items="meta.chats"
                            item-title="name"
                            item-value="id"
                            label="Chats"
                            multiple
                            chips
                            closable-chips
                        />
                    </v-col>
                </v-row>
            </v-card-text>

            <v-card-actions>
                <v-spacer />
                <v-btn variant="text" @click="close">Отмена</v-btn>
                <v-btn color="primary" :loading="loading" @click="save">Сохранить</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
