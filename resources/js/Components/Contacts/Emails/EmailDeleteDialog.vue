<script setup>
const model = defineModel({
    type: Boolean,
    default: false,
})

defineProps({
    email: {
        type: Object,
        default: null,
    },
    deleting: Boolean,
})

const emit = defineEmits([
    'delete',
])
</script>

<template>
    <v-dialog
        v-model="model"
        width="520"
    >
        <v-card class="rounded border border-red-900 bg-slate-950">
            <v-card-title class="text-red-lighten-3">
                Удалить email?
            </v-card-title>

            <v-card-text>
                <div class="text-grey-lighten-1">
                    Запись будет удалена мягко через Soft Deletes.
                </div>

                <div class="text-teal-lighten-3 mt-3 font-mono">
                    {{ email?.address }}
                </div>
            </v-card-text>

            <v-card-actions>
                <v-spacer />

                <v-btn
                    text="Отмена"
                    variant="text"
                    @click="model = false"
                />

                <v-btn
                    text="Удалить"
                    color="red"
                    variant="elevated"
                    :loading="deleting"
                    @click="emit('delete')"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
