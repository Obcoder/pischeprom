<script setup>
import { computed } from 'vue'

defineProps({
    emails: {
        type: Array,
        default: () => [],
    },
    totalItems: {
        type: Number,
        default: 0,
    },
    loading: Boolean,
    options: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'update:options',
    'edit',
    'delete',
    'relations',
    'mailbox',
])

const headers = computed(() => [
    {
        title: 'Email',
        key: 'address',
        sortable: true,
    },
    {
        title: 'Домен',
        key: 'domain',
        sortable: true,
        width: '170px',
    },
    {
        title: 'Почта',
        key: 'mail_stats',
        sortable: false,
        align: 'center',
        width: '200px',
    },
    {
        title: 'Последние',
        key: 'last_activity',
        sortable: false,
        width: '210px',
    },
    {
        title: 'Связи',
        key: 'relations',
        sortable: false,
        align: 'center',
    },
    {
        title: 'Статус',
        key: 'is_active',
        sortable: true,
        align: 'center',
        width: '100px',
    },
    {
        title: '',
        key: 'actions',
        sortable: false,
        align: 'end',
        width: '180px',
    },
])

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
    {
        value: 200,
        title: '200',
    },
    {
        value: -1,
        title: 'All',
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
</script>

<template>
    <v-data-table-server
        :headers="headers"
        :items="emails"
        :items-length="totalItems"
        :loading="loading"
        :page="options.page"
        :items-per-page="options.itemsPerPage"
        :items-per-page-options="itemsPerPageOptions"
        :sort-by="options.sortBy"
        item-value="id"
        density="compact"
        fixed-header
        height="720"
        hover
        class="rounded border border-teal-900 bg-slate-950"
        @update:options="emit('update:options', $event)"
    >
        <template #item.address="{ item, index }">
            <div class="py-1">
                <div class="d-flex align-center">
                    <span class="inline-flex items-center justify-center mr-2 bg-teal-500 text-white rounded-full text-[7px] font-bold w-4 h-4">
                        {{ index + 1 }}
                    </span>

                    <a
                        :href="`mailto:${item.address}`"
                        class="text-teal-lighten-3 hover:text-white font-ComfortaaVariableFont text-sm"
                    >
                        {{ item.address }}
                    </a>
                </div>

                <div
                    v-if="item.name"
                    class="text-[11px] text-teal-darken-1 mt-1"
                >
                    {{ item.name }}
                </div>

                <div
                    v-if="item.comment"
                    class="text-[10px] text-grey-lighten-1 mt-1 line-clamp-2"
                >
                    {{ item.comment }}
                </div>
            </div>
        </template>

        <template #item.domain="{ item }">
            <v-chip
                v-if="item.domain"
                size="x-small"
                color="teal"
                variant="tonal"
            >
                {{ item.domain }}
            </v-chip>

            <span
                v-else
                class="text-grey"
            >
                —
            </span>
        </template>

        <template #item.mail_stats="{ item }">
            <div class="d-flex justify-center ga-1">
                <v-chip
                    size="x-small"
                    color="blue"
                    variant="tonal"
                    title="Писем отправлено на этот email"
                >
                    ↑ {{ item.sent_count ?? 0 }}
                </v-chip>

                <v-chip
                    size="x-small"
                    color="purple"
                    variant="tonal"
                    title="Писем пришло с этого email"
                >
                    ↓ {{ item.received_count ?? 0 }}
                </v-chip>

                <v-chip
                    size="x-small"
                    color="amber"
                    variant="tonal"
                    title="Локальные sendings"
                >
                    S {{ item.sendings_count ?? 0 }}
                </v-chip>
            </div>
        </template>

        <template #item.last_activity="{ item }">
            <div class="text-[11px]">
                <div class="text-blue-lighten-3">
                    ↑ {{ formatDate(item.last_sent_at) }}
                </div>

                <div class="text-purple-lighten-3">
                    ↓ {{ formatDate(item.last_received_at) }}
                </div>
            </div>
        </template>

        <template #item.relations="{ item }">
            <div class="d-flex justify-center ga-1 flex-wrap">
                <v-chip
                    size="x-small"
                    color="teal"
                    variant="tonal"
                >
                    U {{ item.units_count ?? item.units?.length ?? 0 }}
                </v-chip>

                <v-chip
                    size="x-small"
                    color="purple"
                    variant="tonal"
                >
                    E {{ item.entities_count ?? item.entities?.length ?? 0 }}
                </v-chip>
            </div>
        </template>

        <template #item.is_active="{ item }">
            <v-chip
                size="x-small"
                :color="item.is_active ? 'green' : 'grey'"
                variant="tonal"
            >
                {{ item.is_active ? 'active' : 'off' }}
            </v-chip>
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex justify-end ga-1">
                <v-btn
                    icon="mdi-email-open-outline"
                    size="x-small"
                    variant="text"
                    color="blue"
                    title="Письма"
                    @click="emit('mailbox', item)"
                />

                <v-btn
                    icon="mdi-link-variant"
                    size="x-small"
                    variant="text"
                    color="teal"
                    title="Связи"
                    @click="emit('relations', item)"
                />

                <v-btn
                    icon="mdi-pencil"
                    size="x-small"
                    variant="text"
                    color="amber"
                    title="Редактировать"
                    @click="emit('edit', item)"
                />

                <v-btn
                    icon="mdi-delete"
                    size="x-small"
                    variant="text"
                    color="red"
                    title="Удалить"
                    @click="emit('delete', item)"
                />
            </div>
        </template>
    </v-data-table-server>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
