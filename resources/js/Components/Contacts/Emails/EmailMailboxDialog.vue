<script setup>
import { computed, watch } from 'vue'
import { useEmailMailboxMessages } from '@/Composables/useEmailMailboxMessages.js'

const model = defineModel({
    type: Boolean,
    default: false,
})

const props = defineProps({
    email: {
        type: Object,
        default: null,
    },
})

const {
    messages,
    totalItems,
    loading,
    search,
    direction,
    options,
    fetchMessages,
    resetMailbox,
} = useEmailMailboxMessages()

const headers = computed(() => [
    {
        title: 'Тип',
        key: 'direction',
        sortable: false,
        width: '90px',
    },
    {
        title: 'Дата',
        key: 'message_date',
        sortable: false,
        width: '150px',
    },
    {
        title: 'From',
        key: 'from_address',
        sortable: false,
        width: '220px',
    },
    {
        title: 'Subject',
        key: 'subject',
        sortable: false,
    },
    {
        title: 'To / CC',
        key: 'recipients',
        sortable: false,
        width: '260px',
    },
])

const directionOptions = [
    {
        title: 'Все',
        value: null,
    },
    {
        title: 'Входящие',
        value: 'incoming',
    },
    {
        title: 'Исходящие',
        value: 'outgoing',
    },
]

const itemsPerPageOptions = [
    {
        value: 25,
        title: '25',
    },
    {
        value: 50,
        title: '50',
    },
    {
        value: 100,
        title: '100',
    },
]

function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value))
}

function recipients(item) {
    const to = item.to?.map((recipient) => recipient.address).join(', ')
    const cc = item.cc?.map((recipient) => recipient.address).join(', ')

    return [
        to ? `To: ${to}` : null,
        cc ? `CC: ${cc}` : null,
    ].filter(Boolean)
}

let searchTimer = null

watch(model, async (opened) => {
    if (opened && props.email?.id) {
        await fetchMessages(props.email)
    }

    if (!opened) {
        resetMailbox()
    }
})

watch(search, () => {
    clearTimeout(searchTimer)

    searchTimer = setTimeout(() => {
        options.value.page = 1
        fetchMessages(props.email)
    }, 350)
})

watch(direction, () => {
    options.value.page = 1
    fetchMessages(props.email)
})

watch(options, () => {
    if (model.value) {
        fetchMessages(props.email)
    }
}, {
    deep: true,
})
</script>

<template>
    <v-dialog
        v-model="model"
        width="1200"
    >
        <v-card class="rounded border border-blue-900 bg-slate-950">
            <v-card-title class="d-flex justify-space-between align-center">
                <div>
                    <div class="text-blue-lighten-3">
                        Письма
                    </div>

                    <div class="text-[11px] text-grey">
                        {{ email?.address }}
                    </div>
                </div>

                <v-chip
                    size="small"
                    color="blue"
                    variant="tonal"
                >
                    {{ totalItems }}
                </v-chip>
            </v-card-title>

            <v-divider />

            <v-card-text>
                <v-row dense>
                    <v-col cols="12" lg="4">
                        <v-text-field
                            v-model="search"
                            label="Поиск по письмам"
                            prepend-inner-icon="mdi-magnify"
                            variant="solo"
                            density="compact"
                            clearable
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" lg="3">
                        <v-select
                            v-model="direction"
                            :items="directionOptions"
                            label="Тип"
                            variant="outlined"
                            density="compact"
                            hide-details
                        />
                    </v-col>

                    <v-col cols="12" lg="5">
                        <div class="d-flex justify-end">
                            <v-btn
                                prepend-icon="mdi-refresh"
                                text="Обновить"
                                color="blue"
                                variant="tonal"
                                density="compact"
                                :loading="loading"
                                @click="fetchMessages(email)"
                            />
                        </div>
                    </v-col>
                </v-row>

                <v-data-table-server
                    class="mt-3 rounded border border-blue-900 bg-slate-950"
                    :headers="headers"
                    :items="messages"
                    :items-length="totalItems"
                    :loading="loading"
                    :page="options.page"
                    :items-per-page="options.itemsPerPage"
                    :items-per-page-options="itemsPerPageOptions"
                    item-value="id"
                    density="compact"
                    fixed-header
                    height="600"
                    hover
                    @update:options="options = $event"
                >
                    <template #item.direction="{ item }">
                        <v-chip
                            size="x-small"
                            :color="item.direction === 'incoming' ? 'purple' : 'blue'"
                            variant="tonal"
                        >
                            {{ item.direction === 'incoming' ? '↓ in' : '↑ out' }}
                        </v-chip>
                    </template>

                    <template #item.message_date="{ item }">
                        <span class="text-[11px] font-mono">
                            {{ formatDate(item.message_date) }}
                        </span>
                    </template>

                    <template #item.from_address="{ item }">
                        <div>
                            <div class="text-blue-lighten-3 text-xs">
                                {{ item.from_address || '—' }}
                            </div>

                            <div
                                v-if="item.from_name"
                                class="text-[10px] text-grey"
                            >
                                {{ item.from_name }}
                            </div>
                        </div>
                    </template>

                    <template #item.subject="{ item }">
                        <div class="py-1">
                            <div class="text-sm text-blue-lighten-4">
                                {{ item.subject || 'Без темы' }}
                            </div>

                            <div
                                v-if="item.preview"
                                class="text-[10px] text-grey-lighten-1 line-clamp-2 mt-1"
                            >
                                {{ item.preview }}
                            </div>
                        </div>
                    </template>

                    <template #item.recipients="{ item }">
                        <div class="text-[10px] text-grey-lighten-1">
                            <div
                                v-for="line in recipients(item)"
                                :key="line"
                            >
                                {{ line }}
                            </div>
                        </div>
                    </template>
                </v-data-table-server>
            </v-card-text>

            <v-card-actions>
                <v-spacer />

                <v-btn
                    text="Закрыть"
                    variant="text"
                    @click="model = false"
                />
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
