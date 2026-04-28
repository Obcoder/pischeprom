<script setup>
import { onMounted, ref } from 'vue'
import EmailsToolbar from './EmailsToolbar.vue'
import EmailsTable from './EmailsTable.vue'
import EmailFormDialog from './EmailFormDialog.vue'
import EmailDeleteDialog from './EmailDeleteDialog.vue'
import EmailRelationsDialog from './EmailRelationsDialog.vue'
import EmailMailboxDialog from './EmailMailboxDialog.vue'
import EmailMailboxStats from './EmailMailboxStats.vue'
import { useEmails } from '@/Composables/useEmails.js'
import { useEmailMeta } from '@/Composables/useEmailMeta.js'
import { useEmailMailboxSync } from '@/Composables/useEmailMailboxSync.js'

const {
    emails,
    totalItems,
    loading,
    saving,
    deleting,
    search,
    filters,
    options,
    fetchEmails,
    storeEmail,
    updateEmail,
    deleteEmail,
    syncUnits,
    syncEntities,
} = useEmails()

const {
    metaLoading,
    units,
    entities,
    domains,
    fetchMeta,
} = useEmailMeta()

const {
    syncing,
    syncYandex,
} = useEmailMailboxSync()

const formDialog = ref(false)
const deleteDialog = ref(false)
const relationsDialog = ref(false)
const mailboxDialog = ref(false)

const selectedEmail = ref(null)

const snackbar = ref({
    show: false,
    text: '',
    color: 'teal',
})

function openCreate() {
    selectedEmail.value = null
    formDialog.value = true
}

function openEdit(email) {
    selectedEmail.value = email
    formDialog.value = true
}

function openDelete(email) {
    selectedEmail.value = email
    deleteDialog.value = true
}

function openRelations(email) {
    selectedEmail.value = email
    relationsDialog.value = true
}

function openMailbox(email) {
    selectedEmail.value = email
    mailboxDialog.value = true
}

async function saveEmail(payload) {
    if (selectedEmail.value) {
        await updateEmail(selectedEmail.value, payload)

        snackbar.value = {
            show: true,
            text: 'Email обновлён',
            color: 'amber',
        }
    } else {
        await storeEmail(payload)

        snackbar.value = {
            show: true,
            text: 'Email создан',
            color: 'teal',
        }
    }

    formDialog.value = false
}

async function confirmDelete() {
    await deleteEmail(selectedEmail.value)

    deleteDialog.value = false
    selectedEmail.value = null

    snackbar.value = {
        show: true,
        text: 'Email удалён',
        color: 'red',
    }
}

async function saveRelations(payload) {
    await syncUnits(selectedEmail.value, payload.unit_ids)
    await syncEntities(selectedEmail.value, payload.entity_ids)

    relationsDialog.value = false

    snackbar.value = {
        show: true,
        text: 'Связи обновлены',
        color: 'purple',
    }
}

async function runSyncYandex() {
    await syncYandex(1000)

    snackbar.value = {
        show: true,
        text: 'Синхронизация Яндекс-почты поставлена в очередь',
        color: 'blue',
    }
}

onMounted(async () => {
    await Promise.all([
        fetchMeta(),
        fetchEmails(),
    ])
})
</script>

<template>
    <v-card class="rounded border border-teal-900 bg-slate-950">
        <v-card-title class="d-flex align-center justify-space-between py-2">
            <div>
                <div class="text-teal-lighten-3 font-ComfortaaVariableFont">
                    Emails
                </div>

                <div class="text-[10px] text-grey">
                    CRUD, связи с Units/Entities, входящие и исходящие письма office@180022.ru
                </div>
            </div>

            <EmailMailboxStats
                :total="totalItems"
                :loading="loading"
            />
        </v-card-title>

        <v-divider />

        <v-card-text>
            <EmailsToolbar
                v-model:search="search"
                v-model:filters="filters"
                :units="units"
                :entities="entities"
                :domains="domains"
                :loading="loading || metaLoading"
                :syncing="syncing"
                @create="openCreate"
                @refresh="fetchEmails"
                @sync-yandex="runSyncYandex"
            />

            <EmailsTable
                class="mt-3"
                :emails="emails"
                :total-items="totalItems"
                :loading="loading"
                :options="options"
                @update:options="options = $event"
                @edit="openEdit"
                @delete="openDelete"
                @relations="openRelations"
                @mailbox="openMailbox"
            />
        </v-card-text>
    </v-card>

    <EmailFormDialog
        v-model="formDialog"
        :email="selectedEmail"
        :saving="saving"
        @save="saveEmail"
    />

    <EmailDeleteDialog
        v-model="deleteDialog"
        :email="selectedEmail"
        :deleting="deleting"
        @delete="confirmDelete"
    />

    <EmailRelationsDialog
        v-model="relationsDialog"
        :email="selectedEmail"
        :units="units"
        :entities="entities"
        :saving="saving"
        @save="saveRelations"
    />

    <EmailMailboxDialog
        v-model="mailboxDialog"
        :email="selectedEmail"
    />

    <v-snackbar
        v-model="snackbar.show"
        :color="snackbar.color"
        :timeout="2500"
    >
        {{ snackbar.text }}
    </v-snackbar>
</template>
