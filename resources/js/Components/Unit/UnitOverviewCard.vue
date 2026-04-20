<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

const props = defineProps({
    unit: Object,
    files: Array,
    dict: Object,
    loading: Object,
})

const emit = defineEmits(['refresh'])

const activeTab = ref('info')

const dialogAttachEmail = ref(false)
const dialogAddEmail = ref(false)
const dialogAttachUri = ref(false)
const dialogAddUri = ref(false)

const formAttachEmail = useForm({
    email_id: null,
    unit_id: props.unit.id,
})

const formAddEmail = useForm({
    address: null,
})

const formAttachUri = useForm({
    uri_id: null,
    unit_id: props.unit.id,
})

const formAddUri = useForm({
    address: null,
})

function attachEmail() {
    formAttachEmail.post(route('emailgood.store'), {
        preserveState: true,
        onSuccess: async () => {
            formAttachEmail.reset()
            dialogAttachEmail.value = false
            emit('refresh')
        },
    })
}

function storeEmail() {
    formAddEmail.post(route('web.email.store'), {
        preserveState: true,
        onSuccess: async () => {
            formAddEmail.reset()
            dialogAddEmail.value = false
            emit('refresh')
        },
    })
}

function attachUri() {
    formAttachUri.post(route('web.unituri.store'), {
        preserveState: true,
        onSuccess: async () => {
            formAttachUri.reset()
            dialogAttachUri.value = false
            emit('refresh')
        },
    })
}

function storeUri() {
    formAddUri.post(route('web.uri.store'), {
        preserveState: true,
        onSuccess: async () => {
            formAddUri.reset()
            dialogAddUri.value = false
            emit('refresh')
        },
    })
}

const formatBuildingTitle = (building) => {
    if (!building) return ''
    return `${building.city?.name || '-'}: ${building.address}`
}
</script>

<template>
    <BaseSectionCard title="Unit Overview" icon="mdi-factory">
        <template #actions>
            <v-menu>
                <template #activator="{ props }">
                    <v-btn
                        v-bind="props"
                        icon="mdi-dots-vertical"
                        variant="text"
                        size="small"
                    />
                </template>

                <v-list density="compact">
                    <v-list-item title="Attach email" @click="dialogAttachEmail = true" />
                    <v-list-item title="Attach URI" @click="dialogAttachUri = true" />
                </v-list>
            </v-menu>
        </template>

        <div class="text-h6 font-weight-bold mb-3">
            {{ unit.name }}
        </div>

        <v-tabs v-model="activeTab" color="primary" density="comfortable">
            <v-tab value="info">Info</v-tab>
            <v-tab value="contacts">Contacts</v-tab>
            <v-tab value="files">Files</v-tab>
            <v-tab value="buildings">Buildings</v-tab>
        </v-tabs>

        <v-window v-model="activeTab" class="mt-4">
            <v-window-item value="info">
                <div class="mb-3">
                    <div class="text-caption text-medium-emphasis mb-2">URI</div>
                    <v-chip
                        v-for="uri in (unit.uris || [])"
                        :key="uri.id"
                        class="me-2 mb-2"
                        size="small"
                        variant="outlined"
                    >
                        <a :href="uri.address" target="_blank" class="text-decoration-none">
                            {{ uri.address }}
                        </a>
                    </v-chip>
                </div>

                <div>
                    <div class="text-caption text-medium-emphasis mb-2">Telephones</div>
                    <v-chip
                        v-for="telephone in (unit.telephones || [])"
                        :key="telephone.id"
                        class="me-2 mb-2"
                        size="small"
                        color="indigo"
                        variant="tonal"
                    >
                        {{ telephone.number }}
                    </v-chip>
                </div>
            </v-window-item>

            <v-window-item value="contacts">
                <v-list density="compact" lines="two">
                    <v-list-item
                        v-for="email in (unit.emails || [])"
                        :key="email.id"
                        :title="email.address"
                        subtitle="Email"
                    />
                </v-list>
            </v-window-item>

            <v-window-item value="files">
                <v-skeleton-loader
                    v-if="loading.files"
                    type="list-item-three-line"
                />
                <v-list v-else density="compact">
                    <v-list-item
                        v-for="file in files"
                        :key="file.name"
                    >
                        <a :href="file.url" target="_blank" class="text-decoration-none">
                            {{ file.name }}
                        </a>
                    </v-list-item>
                </v-list>
            </v-window-item>

            <v-window-item value="buildings">
                <v-list density="compact">
                    <v-list-item
                        v-for="building in (unit.buildings || [])"
                        :key="building.id"
                        :title="building.address"
                        :subtitle="building.city?.name"
                    />
                </v-list>
            </v-window-item>
        </v-window>

        <!-- Attach Email -->
        <v-dialog v-model="dialogAttachEmail" max-width="720">
            <v-card rounded="xl">
                <v-card-title>Attach Email</v-card-title>
                <v-card-text>
                    <v-autocomplete
                        v-model="formAttachEmail.email_id"
                        :items="dict.emails"
                        item-title="address"
                        item-value="id"
                        label="Emails"
                        variant="outlined"
                        density="comfortable"
                    />
                    <div class="mt-4">
                        <v-btn variant="text" @click="dialogAddEmail = true">
                            New email
                        </v-btn>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttachEmail = false">Cancel</v-btn>
                    <v-btn color="primary" @click="attachEmail" :loading="formAttachEmail.processing">
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Add Email -->
        <v-dialog v-model="dialogAddEmail" max-width="640">
            <v-card rounded="xl">
                <v-card-title>New Email</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="formAddEmail.address"
                        label="Email address"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAddEmail = false">Cancel</v-btn>
                    <v-btn color="primary" @click="storeEmail" :loading="formAddEmail.processing">
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Attach URI -->
        <v-dialog v-model="dialogAttachUri" max-width="720">
            <v-card rounded="xl">
                <v-card-title>Attach URI</v-card-title>
                <v-card-text>
                    <v-autocomplete
                        v-model="formAttachUri.uri_id"
                        :items="dict.uris"
                        item-title="address"
                        item-value="id"
                        label="URI"
                        variant="outlined"
                        density="comfortable"
                    />
                    <div class="mt-4">
                        <v-btn variant="text" @click="dialogAddUri = true">
                            New URI
                        </v-btn>
                    </div>
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAttachUri = false">Cancel</v-btn>
                    <v-btn color="primary" @click="attachUri" :loading="formAttachUri.processing">
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Add URI -->
        <v-dialog v-model="dialogAddUri" max-width="640">
            <v-card rounded="xl">
                <v-card-title>New URI</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="formAddUri.address"
                        label="URI address"
                        variant="outlined"
                        density="comfortable"
                    />
                </v-card-text>
                <v-card-actions class="justify-end">
                    <v-btn variant="text" @click="dialogAddUri = false">Cancel</v-btn>
                    <v-btn color="primary" @click="storeUri" :loading="formAddUri.processing">
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </BaseSectionCard>
</template>
