<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios'

import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'
import UnitFilesTab from '@/Components/Unit/UnitFilesTab.vue'
import UnitUrisCard from '@/Components/Unit/UnitUrisCard.vue'
import UnitRelationManagerDialog from '@/Components/Unit/UnitRelationManagerDialog.vue'

const props = defineProps({
    unit: {
        type: Object,
        required: true,
    },
    files: {
        type: Array,
        default: () => [],
    },
    dict: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['refresh'])

const activeTab = ref('info')

const dialogAttachEmail = ref(false)
const dialogAddEmail = ref(false)

const dialogLabels = ref(false)
const dialogFields = ref(false)
const dialogTelephones = ref(false)
const dialogCities = ref(false)

const savingLabels = ref(false)
const savingFields = ref(false)
const savingTelephones = ref(false)
const savingCities = ref(false)

const formAttachEmail = useForm({
    email_id: null,
    unit_id: props.unit.id,
})

const formAddEmail = useForm({
    address: null,
})

function getIds(items = []) {
    return items
        .filter(item => typeof item === 'object' && item?.id)
        .map(item => item.id)
}

function getStrings(items = []) {
    return items
        .filter(item => typeof item === 'string' && item.trim() !== '')
        .map(item => item.trim())
}

function attachEmail() {
    formAttachEmail.unit_id = props.unit.id

    formAttachEmail.post(route('emailgood.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            formAttachEmail.reset()
            dialogAttachEmail.value = false
            emit('refresh')
        },
    })
}

function storeEmail() {
    formAddEmail.post(route('web.email.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            formAddEmail.reset()
            dialogAddEmail.value = false
            emit('refresh')
        },
    })
}

async function syncLabels(payload) {
    savingLabels.value = true

    try {
        const currentIds = (props.unit.labels || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNames = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.labels.detach', {
                    unit: props.unit.id,
                    label: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.labels.attach', props.unit.id), {
                        label_id: id,
                    })
                )
        )

        await Promise.all(
            newNames.map(name =>
                axios.post(route('api.units.labels.attach', props.unit.id), {
                    name,
                })
            )
        )

        dialogLabels.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения labels:', error)
    } finally {
        savingLabels.value = false
    }
}

async function syncFields(payload) {
    savingFields.value = true

    try {
        const currentIds = (props.unit.fields || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNames = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.fields.detach', {
                    unit: props.unit.id,
                    field: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.fields.attach', props.unit.id), {
                        field_id: id,
                    })
                )
        )

        await Promise.all(
            newNames.map(name =>
                axios.post(route('api.units.fields.attach', props.unit.id), {
                    name,
                })
            )
        )

        dialogFields.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения fields:', error)
    } finally {
        savingFields.value = false
    }
}

async function syncTelephones(payload) {
    savingTelephones.value = true

    try {
        const currentIds = (props.unit.telephones || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNumbers = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.telephones.detach', {
                    unit: props.unit.id,
                    telephone: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.telephones.attach', props.unit.id), {
                        telephone_id: id,
                    })
                )
        )

        await Promise.all(
            newNumbers.map(number =>
                axios.post(route('api.units.telephones.attach', props.unit.id), {
                    number,
                })
            )
        )

        dialogTelephones.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения telephones:', error)
    } finally {
        savingTelephones.value = false
    }
}

async function syncCities(payload) {
    savingCities.value = true

    try {
        const currentIds = (props.unit.cities || []).map(item => item.id)
        const nextIds = getIds(payload)
        const newNames = getStrings(payload)

        const idsToDetach = currentIds.filter(id => !nextIds.includes(id))

        await Promise.all(
            idsToDetach.map(id =>
                axios.delete(route('api.units.cities.detach', {
                    unit: props.unit.id,
                    city: id,
                }))
            )
        )

        await Promise.all(
            nextIds
                .filter(id => !currentIds.includes(id))
                .map(id =>
                    axios.post(route('api.units.cities.attach', props.unit.id), {
                        city_id: id,
                    })
                )
        )

        await Promise.all(
            newNames.map(name =>
                axios.post(route('api.units.cities.attach', props.unit.id), {
                    name,
                })
            )
        )

        dialogCities.value = false
        emit('refresh')
    } catch (error) {
        console.error('Ошибка сохранения cities:', error)
    } finally {
        savingCities.value = false
    }
}
</script>

<template>
    <BaseSectionCard title="Unit Overview" icon="mdi-factory">
        <template #actions>
            <v-menu>
                <template #activator="{ props: menuProps }">
                    <v-btn
                        v-bind="menuProps"
                        icon="mdi-dots-vertical"
                        variant="text"
                        size="small"
                    />
                </template>

                <v-list density="compact">
                    <v-list-item title="Attach email" @click="dialogAttachEmail = true" />
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
                <div class="mb-4">
                    <div class="text-caption text-medium-emphasis mb-2">URI</div>
                    <UnitUrisCard
                        :unit="unit"
                        :dict="dict"
                        @refresh="emit('refresh')"
                    />
                </div>

                <div class="mb-4">
                    <div class="d-flex align-center justify-space-between mb-2">
                        <div class="text-caption text-medium-emphasis">Labels</div>
                        <v-btn size="x-small" variant="text" @click="dialogLabels = true">
                            Manage
                        </v-btn>
                    </div>

                    <div v-if="unit.labels?.length" class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="label in unit.labels"
                            :key="label.id"
                            size="small"
                            color="primary"
                            variant="tonal"
                        >
                            {{ label.name }}
                        </v-chip>
                    </div>

                    <div v-else class="text-caption text-disabled">
                        No labels
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex align-center justify-space-between mb-2">
                        <div class="text-caption text-medium-emphasis">Fields</div>
                        <v-btn size="x-small" variant="text" @click="dialogFields = true">
                            Manage
                        </v-btn>
                    </div>

                    <div v-if="unit.fields?.length" class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="field in unit.fields"
                            :key="field.id"
                            size="small"
                            color="deep-purple"
                            variant="tonal"
                        >
                            {{ field.name }}
                        </v-chip>
                    </div>

                    <div v-else class="text-caption text-disabled">
                        No fields
                    </div>
                </div>

                <div class="mb-2">
                    <div class="d-flex align-center justify-space-between mb-2">
                        <div class="text-caption text-medium-emphasis">
                            Cities for offices / warehouses / production / delivery
                        </div>
                        <v-btn size="x-small" variant="text" @click="dialogCities = true">
                            Manage
                        </v-btn>
                    </div>

                    <div v-if="unit.cities?.length" class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="city in unit.cities"
                            :key="city.id"
                            size="small"
                            color="teal"
                            variant="tonal"
                        >
                            {{ city.name }}
                        </v-chip>
                    </div>

                    <div v-else class="text-caption text-disabled">
                        No cities
                    </div>
                </div>
            </v-window-item>

            <v-window-item value="contacts">
                <div class="mb-4">
                    <div class="d-flex align-center justify-space-between mb-2">
                        <div class="text-caption text-medium-emphasis">Telephones</div>
                        <v-btn size="x-small" variant="text" @click="dialogTelephones = true">
                            Manage
                        </v-btn>
                    </div>

                    <div v-if="unit.telephones?.length" class="d-flex flex-wrap ga-2">
                        <v-chip
                            v-for="telephone in unit.telephones"
                            :key="telephone.id"
                            size="small"
                            color="indigo"
                            variant="tonal"
                        >
                            {{ telephone.number }}
                        </v-chip>
                    </div>

                    <div v-else class="text-caption text-disabled">
                        No telephones
                    </div>
                </div>

                <div>
                    <div class="text-caption text-medium-emphasis mb-2">Emails</div>

                    <v-list density="compact" lines="two">
                        <v-list-item
                            v-for="email in (unit.emails || [])"
                            :key="email.id"
                            :title="email.address"
                            subtitle="Email"
                        />
                    </v-list>
                </div>
            </v-window-item>

            <v-window-item value="files">
                <UnitFilesTab :unit-id="unit.id" />
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

        <v-dialog v-model="dialogAttachEmail" max-width="720">
            <v-card rounded="xl">
                <v-card-title>Attach Email</v-card-title>

                <v-card-text>
                    <v-autocomplete
                        v-model="formAttachEmail.email_id"
                        :items="dict.emails || []"
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
                    <v-btn variant="text" @click="dialogAttachEmail = false">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :loading="formAttachEmail.processing"
                        @click="attachEmail"
                    >
                        Attach
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

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
                    <v-btn variant="text" @click="dialogAddEmail = false">
                        Cancel
                    </v-btn>

                    <v-btn
                        color="primary"
                        :loading="formAddEmail.processing"
                        @click="storeEmail"
                    >
                        Save
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <UnitRelationManagerDialog
            v-model="dialogLabels"
            title="Manage Labels"
            :items="unit.labels || []"
            :dict-items="dict.labels || []"
            item-title="name"
            item-value="id"
            :loading="savingLabels"
            @save="syncLabels"
        />

        <UnitRelationManagerDialog
            v-model="dialogFields"
            title="Manage Fields"
            :items="unit.fields || []"
            :dict-items="dict.fields || []"
            item-title="name"
            item-value="id"
            :loading="savingFields"
            @save="syncFields"
        />

        <UnitRelationManagerDialog
            v-model="dialogTelephones"
            title="Manage Telephones"
            :items="unit.telephones || []"
            :dict-items="dict.telephones || []"
            item-title="number"
            item-value="id"
            hint="Можно выбрать номер из базы или ввести новый"
            :loading="savingTelephones"
            @save="syncTelephones"
        />

        <UnitRelationManagerDialog
            v-model="dialogCities"
            title="Manage Cities"
            :items="unit.cities || []"
            :dict-items="dict.cities || []"
            item-title="name"
            item-value="id"
            hint="Города офисов, складов, производств и доставки"
            :loading="savingCities"
            @save="syncCities"
        />
    </BaseSectionCard>
</template>
