<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: Object,
    dict: Object,
})

const emit = defineEmits(['refresh'])

const dialogAttachLabel = ref(false)

const formAttachLabel = useForm({
    label_id: null,
    unit_id: props.unit.id,
})

function attachLabel() {
    formAttachLabel.post(route('web.labelunit.store'), {
        preserveState: true,
        onSuccess: async () => {
            formAttachLabel.reset()
            dialogAttachLabel.value = false
            emit('refresh')
        },
    })
}
</script>

<template>
    <BaseSectionCard title="Labels" icon="mdi-tag-multiple-outline">
        <template #actions>
            <v-btn
                icon="mdi-plus"
                variant="text"
                size="small"
                @click="dialogAttachLabel = true"
            />
        </template>

        <div class="d-flex flex-wrap ga-2">
            <v-chip
                v-for="label in (unit.labels || [])"
                :key="label.id"
                color="deep-purple"
                variant="tonal"
                size="small"
            >
                {{ label.name }}
            </v-chip>
        </div>

        <v-dialog v-model="dialogAttachLabel" max-width="640">
            <v-card rounded="xl">
                <v-card-title>Attach Label</v-card-title>
                <v-card-text>
                    <v-autocomplete
                        v-model="formAttachLabel.label_id"
                        :items="dict.labels"
                        item-title="name"
                        item-value="id"
                        label="Labels"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttachLabel = false">Cancel</v-btn>
                    <v-btn color="primary" @click="attachLabel" :loading="formAttachLabel.processing">
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </BaseSectionCard>
</template>
