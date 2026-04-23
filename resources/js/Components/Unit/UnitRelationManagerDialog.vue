<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    payloadKey: {
        type: String,
        required: true,
    },
    routeName: {
        type: String,
        required: true,
    },
    unitId: {
        type: Number,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    dictItems: {
        type: Array,
        default: () => [],
    },
    itemTitle: {
        type: String,
        default: 'name',
    },
    itemValue: {
        type: String,
        default: 'id',
    },
    hint: {
        type: String,
        default: 'Можно выбрать из списка или ввести новое значение',
    },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const localValue = ref([])

const form = useForm({
    [props.payloadKey]: [],
})

watch(
    () => props.modelValue,
    (opened) => {
        if (opened) {
            localValue.value = [...(props.items || [])]
        }
    }
)

watch(
    () => props.items,
    (items) => {
        if (props.modelValue) {
            localValue.value = [...(items || [])]
        }
    },
    { deep: true }
)

function close() {
    emit('update:modelValue', false)
}

function normalizeItem(item) {
    if (typeof item === 'string') {
        return item.trim()
    }

    if (item?.[props.itemValue]) {
        return {
            id: item[props.itemValue],
            [props.itemTitle]: item[props.itemTitle],
        }
    }

    return item?.[props.itemTitle] ?? null
}

function save() {
    form[props.payloadKey] = (localValue.value || [])
        .map(normalizeItem)
        .filter(Boolean)

    form.post(route(props.routeName, props.unitId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            close()
            emit('saved')
        },
    })
}
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="760"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card rounded="xl">
            <v-card-title>{{ title }}</v-card-title>

            <v-card-text>
                <v-combobox
                    v-model="localValue"
                    :items="dictItems"
                    :item-title="itemTitle"
                    :item-value="itemValue"
                    :label="title"
                    :hint="hint"
                    multiple
                    chips
                    closable-chips
                    clearable
                    return-object
                    variant="outlined"
                    density="comfortable"
                    persistent-hint
                />
            </v-card-text>

            <v-card-actions class="justify-end">
                <v-btn variant="text" @click="close">
                    Cancel
                </v-btn>

                <v-btn
                    color="primary"
                    :loading="form.processing"
                    @click="save"
                >
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
