<script setup>
import { computed } from 'vue'

defineProps({
    messages: {
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
    'read',
])

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
        title: 'От / Кому',
        key: 'contact',
        sortable: false,
        width: '260px',
    },
    {
        title: 'Тема',
        key: 'subject',
        sortable: false,
    },
    {
        title: 'Связи',
        key: 'relations',
        sortable: false,
        align: 'center',
        width: '150px',
    },
    {
        title: '',
        key: 'actions',
        sortable: false,
        align: 'end',
        width: '90px',
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
</script>

<template>
    <v-data-table-server
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
        height="720"
        hover
        class="rounded border border-blue-900 bg-slate-950"
        @update:options="emit('update:options', $event)"
        @click:row="(_, row) => emit('read', row.item)"
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

            <div class="text-[9px] text-grey">
                {{ item.folder }}
            </div>
        </template>

        <template #item.contact="{ item }">
            <div v-if="item.direction === 'incoming'">
                <div class="text-purple-lighten-3 text-xs">
                    {{ item.from_address || '—' }}
                </div>

                <div
                    v-if="item.from_name"
                    class="text-[10px] text-grey"
                >
                    {{ item.from_name }}
                </div>
            </div>

            <div
                v-else
                class="text-[10px] text-grey-lighten-1"
            >
                <div
                    v-for="line in recipients(item)"
                    :key="line"
                >
                    {{ line }}
                </div>
            </div>
        </template>

        <template #item.subject="{ item }">
            <div class="py-1 cursor-pointer">
                <div class="text-sm text-blue-lighten-4 hover:text-white">
                    {{ item.subject || 'Без темы' }}
                </div>

                <div
                    v-if="item.preview"
                    class="text-[10px] text-grey-lighten-1 line-clamp-2 mt-1"
                >
                    {{ item.preview }}
                </div>

                <div
                    v-if="item.body_loaded_at"
                    class="text-[9px] text-teal-lighten-3 mt-1"
                >
                    body cached
                </div>
            </div>
        </template>

        <template #item.relations="{ item }">
            <div class="d-flex justify-center ga-1 flex-wrap">
                <v-chip
                    v-for="email in item.emails"
                    :key="email.id"
                    size="x-small"
                    color="teal"
                    variant="tonal"
                >
                    {{ email.address }}
                </v-chip>
            </div>
        </template>

        <template #item.actions="{ item }">
            <v-btn
                icon="mdi-email-open-outline"
                size="x-small"
                variant="text"
                color="blue"
                @click.stop="emit('read', item)"
            />
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
