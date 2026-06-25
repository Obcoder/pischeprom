<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'

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
    mailboxes: {
        type: Array,
        default: () => [],
    },
    height: {
        type: [String, Number],
        default: 720,
    },
})

const emit = defineEmits([
    'update:options',
    'read',
    'delete',
])

const mailboxPalette = [
    {
        fg: '#67e8f9',
        bg: 'rgba(8, 145, 178, 0.18)',
        border: 'rgba(103, 232, 249, 0.45)',
    },
    {
        fg: '#86efac',
        bg: 'rgba(22, 163, 74, 0.18)',
        border: 'rgba(134, 239, 172, 0.45)',
    },
    {
        fg: '#fcd34d',
        bg: 'rgba(217, 119, 6, 0.18)',
        border: 'rgba(252, 211, 77, 0.45)',
    },
    {
        fg: '#fda4af',
        bg: 'rgba(225, 29, 72, 0.18)',
        border: 'rgba(253, 164, 175, 0.45)',
    },
    {
        fg: '#c4b5fd',
        bg: 'rgba(124, 58, 237, 0.18)',
        border: 'rgba(196, 181, 253, 0.45)',
    },
]

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
        title: 'Ящик',
        key: 'mailbox',
        sortable: false,
        width: '180px',
    },
    {
        title: 'Связи',
        key: 'relations',
        sortable: false,
        align: 'center',
        width: '180px',
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
        title: '',
        key: 'actions',
        sortable: false,
        align: 'end',
        width: '112px',
    },
])

const itemsPerPageOptions = [
    {
        value: 100,
        title: '100',
    },
    {
        value: 25,
        title: '25',
    },
    {
        value: 50,
        title: '50',
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

function uniqueById(items) {
    return Array.from(
        new Map(
            items
                .filter((item) => item?.id)
                .map((item) => [Number(item.id), item])
        ).values()
    )
}

function relatedUnits(item) {
    return uniqueById(
        (item.emails ?? []).flatMap((email) => email.units ?? [])
    )
}

function relatedEntities(item) {
    return uniqueById(
        (item.emails ?? []).flatMap((email) => email.entities ?? [])
    )
}

function relationLabel(item, fallback) {
    return item?.name || `${fallback} #${item?.id}`
}

function unitHref(unit) {
    if (!unit?.id) {
        return null
    }

    try {
        return route('web.unit.show', unit.id)
    } catch (error) {
        return `/Ameise/unit/${unit.id}`
    }
}

function entityHref(entity) {
    if (!entity?.id) {
        return null
    }

    try {
        return route('Ameise.entity.show', entity.id)
    } catch (error) {
        return `/Ameise/entity/${entity.id}`
    }
}

function mailboxIndex(address) {
    const normalized = String(address || '').toLowerCase()
    const configuredIndex = props.mailboxes.findIndex((mailbox) => String(mailbox.address || '').toLowerCase() === normalized)

    if (configuredIndex >= 0) {
        return configuredIndex
    }

    return Math.abs([...normalized].reduce((sum, char) => sum + char.charCodeAt(0), 0))
}

function mailboxStyle(address) {
    const palette = mailboxPalette[mailboxIndex(address) % mailboxPalette.length]

    return {
        '--mailbox-fg': palette.fg,
        '--mailbox-bg': palette.bg,
        '--mailbox-border': palette.border,
    }
}

function folderKind(folder) {
    const normalized = String(folder || '').toLowerCase()

    if (normalized.includes('sent')) {
        return 'sent'
    }

    if (normalized.includes('inbox')) {
        return 'inbox'
    }

    return 'other'
}

function folderLabel(folder) {
    const kind = folderKind(folder)

    if (kind === 'sent') {
        return 'Sent'
    }

    if (kind === 'inbox') {
        return 'INBOX'
    }

    return folder || '—'
}

function rowProps({ item }) {
    return {
        class: `mail-message-row mail-message-row--${folderKind(item?.folder)}`,
    }
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
        :height="height"
        :row-props="rowProps"
        item-value="id"
        density="compact"
        fixed-header
        hover
        class="mail-messages-table rounded border border-blue-900 bg-slate-950"
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

            <div
                class="folder-badge"
                :class="`folder-badge--${folderKind(item.folder)}`"
            >
                {{ folderLabel(item.folder) }}
            </div>
        </template>

        <template #item.message_date="{ item }">
            <span class="text-[11px] font-mono">
                {{ formatDate(item.message_date) }}
            </span>
        </template>

        <template #item.mailbox="{ item }">
            <span
                class="mailbox-pill"
                :style="mailboxStyle(item.mailbox)"
            >
                {{ item.mailbox || '—' }}
            </span>
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
            <div class="mail-relations">
                <div
                    v-if="relatedUnits(item).length || relatedEntities(item).length"
                    class="mail-relations__links"
                >
                    <Link
                        v-for="unit in relatedUnits(item)"
                        :key="`unit-${unit.id}`"
                        :href="unitHref(unit)"
                        class="mail-relation-link mail-relation-link--unit"
                        :title="relationLabel(unit, 'Unit')"
                        @click.stop
                    >
                        <v-icon icon="mdi-factory" size="10" />
                        <span>{{ relationLabel(unit, 'Unit') }}</span>
                    </Link>

                    <Link
                        v-for="entity in relatedEntities(item)"
                        :key="`entity-${entity.id}`"
                        :href="entityHref(entity)"
                        class="mail-relation-link mail-relation-link--entity"
                        :title="relationLabel(entity, 'Entity')"
                        @click.stop
                    >
                        <v-icon icon="mdi-domain" size="10" />
                        <span>{{ relationLabel(entity, 'Entity') }}</span>
                    </Link>
                </div>

                <span v-else class="mail-relations__empty">—</span>
            </div>
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex justify-end ga-1">
                <v-btn
                    icon="mdi-email-open-outline"
                    size="x-small"
                    variant="text"
                    color="blue"
                    @click.stop="emit('read', item)"
                />

                <v-btn
                    icon="mdi-delete-outline"
                    size="x-small"
                    variant="text"
                    color="red-lighten-2"
                    @click.stop="emit('delete', item)"
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

.mail-relations {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    justify-content: center;
}

.mail-messages-table :deep(.v-data-table__tr:hover) {
    background: rgba(59, 130, 246, 0.08) !important;
}

.mailbox-pill {
    display: inline-flex;
    align-items: center;
    max-width: 166px;
    padding: 3px 9px;
    border: 1px solid var(--mailbox-border);
    border-radius: 999px;
    background: var(--mailbox-bg);
    color: var(--mailbox-fg);
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    font-weight: 800;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.folder-badge {
    width: fit-content;
    margin-top: 5px;
    padding: 1px 6px;
    border-radius: 999px;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.folder-badge--inbox {
    background: rgba(168, 85, 247, 0.17);
    color: #e879f9;
}

.folder-badge--sent {
    background: rgba(14, 165, 233, 0.18);
    color: #7dd3fc;
}

.folder-badge--other {
    background: rgba(148, 163, 184, 0.15);
    color: #cbd5e1;
}

.mail-relations__links {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    justify-content: center;
    width: 100%;
}

.mail-relations__empty {
    color: rgba(148, 163, 184, 0.45);
    font-size: 11px;
}

.mail-relation-link {
    align-items: center;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 999px;
    display: inline-flex;
    gap: 3px;
    font-size: 9px;
    line-height: 1.1;
    max-width: 138px;
    overflow: hidden;
    padding: 1px 5px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-relation-link span {
    overflow: hidden;
    text-overflow: ellipsis;
}

.mail-relation-link--unit {
    background: rgba(59, 130, 246, 0.12);
    color: #93c5fd;
}

.mail-relation-link--entity {
    background: rgba(168, 85, 247, 0.12);
    color: #d8b4fe;
}

.mail-relation-link:hover {
    border-color: rgba(255, 255, 255, 0.5);
    color: #fff;
}
</style>
