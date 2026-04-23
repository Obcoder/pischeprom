<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
    dict: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['refresh'])

const dialogAttach = ref(false)
const selectedUri = ref(null)
const uriSearch = ref('')
const saving = ref(false)
const deletingId = ref(null)

const uriItems = computed(() => props.dict?.uris || [])
const unitUris = computed(() => props.unit?.uris || [])

async function attachUri() {
    const selectedId =
        selectedUri.value && typeof selectedUri.value === 'object'
            ? (selectedUri.value.id ?? null)
            : null

    const typedValue = String(uriSearch.value || '').trim()
    const fallbackString = typeof selectedUri.value === 'string'
        ? selectedUri.value.trim()
        : ''

    const address = selectedId ? null : (typedValue || fallbackString || null)

    if (!selectedId && !address) {
        return
    }

    saving.value = true

    try {
        await axios.post(
            route('api.units.uris.attach', props.unit.id),
            selectedId
                ? { uri_id: selectedId }
                : { address }
        )

        selectedUri.value = null
        uriSearch.value = ''
        dialogAttach.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка привязки URI:', error)
    } finally {
        saving.value = false
    }
}

async function detachUri(uriId) {
    deletingId.value = uriId

    try {
        await axios.delete(
            route('api.units.uris.detach', {
                unit: props.unit.id,
                uri: uriId,
            })
        )

        emit('refresh')
    } catch (error) {
        console.error('Ошибка отвязки URI:', error)
    } finally {
        deletingId.value = null
    }
}
</script>

<template>
    <div>
        <div class="d-flex align-center justify-space-between mb-2">
            <div class="text-h6">Uris</div>

            <v-btn
                size="small"
                variant="text"
                @click="dialogAttach = true"
            >
                Attach
            </v-btn>
        </div>

        <div
            v-if="unitUris.length"
            class="d-flex flex-wrap ga-2"
        >
            <v-chip
                v-for="uri in unitUris"
                :key="uri.id"
                closable
                color="primary"
                variant="tonal"
                @click:close="detachUri(uri.id)"
            >
                {{ uri.address }}
            </v-chip>
        </div>

        <div v-else class="text-caption text-disabled">
            No uris
        </div>

        <v-dialog v-model="dialogAttach" max-width="720">
            <v-card rounded="xl">
                <v-card-title>Select URI</v-card-title>

                <v-card-text>
                    <v-combobox
                        v-model="selectedUri"
                        v-model:search="uriSearch"
                        :items="uriItems"
                        item-title="address"
                        item-value="id"
                        label="Uri"
                        hint="Выберите URI из базы или введите новый прямо в этом поле"
                        persistent-hint
                        clearable
                        return-object
                        variant="outlined"
                        density="comfortable"
                        @keydown.enter.prevent="attachUri"
                    />
                </v-card-text>

                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttach = false">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :loading="saving"
                        @click="attachUri"
                    >
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>
