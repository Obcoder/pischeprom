<script setup>
import { ref, computed } from 'vue'
import { useUnitUris } from '@/Composables/useUnitUris'

const props = defineProps({
    unit: Object,
    dict: Object,
})

const emit = defineEmits(['refresh'])

const {
    attachUri,
    detachUri,
    createUri,
    updateUri,
    deleteUri,
} = useUnitUris(async () => {
    await emit('refresh')
})

/* ---------- dialogs ---------- */

const attachDialog = ref(false)
const createDialog = ref(false)
const editDialog = ref(false)

/* ---------- forms ---------- */

const selectedUriId = ref(null)
const newUriAddress = ref('')
const editUriAddress = ref('')
const editingUri = ref(null)

/* ---------- computed ---------- */

const isCreateDisabled = computed(() => !newUriAddress.value.trim())
const isEditDisabled = computed(() => !editUriAddress.value.trim())

/* ---------- actions ---------- */

async function submitAttach() {
    if (!selectedUriId.value) return

    await attachUri(props.unit.id, selectedUriId.value)
    selectedUriId.value = null
    attachDialog.value = false
}

async function submitCreate() {
    if (isCreateDisabled.value) return

    const uri = await createUri(newUriAddress.value.trim())

    if (!uri?.id) return   // ✅ ВОТ СЮДА

    await attachUri(props.unit.id, uri.id)

    newUriAddress.value = ''
    createDialog.value = false
}

function openEdit(uri) {
    editingUri.value = uri
    editUriAddress.value = uri.address
    editDialog.value = true
}

async function submitEdit() {
    if (!editingUri.value || isEditDisabled.value) return

    await updateUri(editingUri.value.id, editUriAddress.value.trim())

    editDialog.value = false
    editingUri.value = null

    await emit('refresh')
}

async function submitDetach(uriId) {
    if (!uriId) return   // ✅ защита

    await detachUri(props.unit.id, uriId)
}

async function submitDelete(uriId) {
    if (!uriId) return   // ✅

    await deleteUri(uriId)
    await emit('refresh')
}
</script>

<template>
    <v-card>
        <v-card-title class="d-flex justify-space-between">
            <span>Uris</span>

            <div class="d-flex ga-2">
                <v-btn size="small" variant="text" @click="attachDialog = true">
                    Attach
                </v-btn>

                <v-btn size="small" variant="text" @click="createDialog = true">
                    New
                </v-btn>
            </div>
        </v-card-title>

        <v-card-text>
            <v-list density="compact">
                <v-list-item
                    v-for="uri in unit.uris"
                    :key="uri.id"
                >
                    <template #title>
                        <a :href="uri.address" target="_blank">
                            {{ uri.address }}
                        </a>
                    </template>

                    <template #append>
                        <div class="d-flex ga-1">
                            <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEdit(uri)" />

                            <v-btn icon="mdi-link-off" size="small" variant="text"
                                   color="warning"
                                   @click="submitDetach(uri.id)" />

                            <v-btn icon="mdi-delete" size="small" variant="text"
                                   color="error"
                                   @click="submitDelete(uri.id)" />
                        </div>
                    </template>
                </v-list-item>
            </v-list>
        </v-card-text>
    </v-card>

    <!-- Attach dialog -->
    <v-dialog v-model="attachDialog" max-width="500">
        <v-card>
            <v-card-title>Select URI</v-card-title>

            <v-card-text>
                <v-autocomplete
                    v-model="selectedUriId"
                    :items="dict.uris"
                    item-title="address"
                    item-value="id"
                    label="Uri"
                />
            </v-card-text>

            <v-card-actions>
                <v-btn text @click="attachDialog = false">Cancel</v-btn>
                <v-btn color="primary" @click="submitAttach">Attach</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- Create dialog -->
    <v-dialog v-model="createDialog" max-width="500">
        <v-card>
            <v-card-title>New URI</v-card-title>

            <v-card-text>
                <v-text-field
                    v-model="newUriAddress"
                    label="Address"
                />
            </v-card-text>

            <v-card-actions>
                <v-btn text @click="createDialog = false">Cancel</v-btn>
                <v-btn color="primary"
                       :disabled="isCreateDisabled"
                       @click="submitCreate">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>

    <!-- Edit dialog -->
    <v-dialog v-model="editDialog" max-width="500">
        <v-card>
            <v-card-title>Edit URI</v-card-title>

            <v-card-text>
                <v-text-field
                    v-model="editUriAddress"
                    label="Address"
                />
            </v-card-text>

            <v-card-actions>
                <v-btn text @click="editDialog = false">Cancel</v-btn>
                <v-btn color="primary"
                       :disabled="isEditDisabled"
                       @click="submitEdit">
                    Save
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
