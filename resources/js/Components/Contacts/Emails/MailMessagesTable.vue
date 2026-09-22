<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'

const props = defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
    totalItems: {
        type: Number,
        default: 0,
    },
    loading: Boolean,
    markingReadIds: {
        type: Array,
        default: () => [],
    },
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
    'mark-read',
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
        width: '76px',
    },
    {
        title: 'Дата',
        key: 'message_date',
        sortable: false,
        width: '120px',
    },
    {
        title: 'Ящик',
        key: 'mailbox',
        sortable: false,
        width: '150px',
    },
    {
        title: 'Связи',
        key: 'relations',
        sortable: false,
        align: 'center',
        width: '142px',
    },
    {
        title: 'От / Кому',
        key: 'contact',
        sortable: false,
        width: '210px',
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
        width: '100px',
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
    const date = value ? new Date(value) : null

    if (!date || Number.isNaN(date.getTime())) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
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

function directUnitIds(item) {
    return new Set(relatedUnits(item).map((unit) => Number(unit.id)))
}

function entityUnits(entity, item) {
    const existingDirectUnits = directUnitIds(item)

    return uniqueById(entity?.units ?? [])
        .filter((unit) => !existingDirectUnits.has(Number(unit.id)))
}

function relationLabel(item, fallback) {
    return item?.name || `${fallback} #${item?.id}`
}

function relations(item) {
    const units = relatedUnits(item).map((unit) => ({
        key: `unit-${unit.id}`,
        label: relationLabel(unit, 'Unit'),
        title: relationLabel(unit, 'Unit'),
        href: unitHref(unit),
        kind: 'unit',
        icon: 'mdi-factory',
    }))
    const entities = relatedEntities(item).flatMap((entity) => [
        {
            key: `entity-${entity.id}`,
            label: relationLabel(entity, 'Entity'),
            title: relationLabel(entity, 'Entity'),
            href: entityHref(entity),
            kind: 'entity',
            icon: 'mdi-domain',
        },
        ...entityUnits(entity, item).map((unit) => ({
            key: `entity-${entity.id}-unit-${unit.id}`,
            label: relationLabel(unit, 'Unit'),
            title: `${relationLabel(entity, 'Entity')} → ${relationLabel(unit, 'Unit')}`,
            href: unitHref(unit),
            kind: 'unit',
            icon: 'mdi-factory',
        })),
    ])

    return [...units, ...entities]
}

function markingRead(item) {
    return props.markingReadIds.some((id) => String(id) === String(item.id))
}

function readStatusTitle(item) {
    if (item.is_seen === true) {
        return 'Прочитано на почтовом сервере'
    }

    if (!item.imap_uid) {
        return 'Отметка на сервере недоступна: письмо ещё не связано с IMAP'
    }

    return item.is_seen == null
        ? 'Статус на сервере ещё неизвестен. Отметить прочитанным'
        : 'Отметить прочитанным на почтовом сервере'
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
    const normalized = normalizeEmailAddress(address)
    const configuredIndex = props.mailboxes.findIndex((mailbox) => normalizeEmailAddress(mailbox.address) === normalized)

    if (configuredIndex >= 0) {
        return configuredIndex
    }

    return Math.abs([...normalized].reduce((sum, char) => sum + char.charCodeAt(0), 0))
}

function normalizeEmailAddress(value) {
    return String(value || '').trim().toLowerCase()
}

function configuredMailbox(address) {
    const normalized = normalizeEmailAddress(address)

    if (!normalized) {
        return null
    }

    return props.mailboxes.find((mailbox) => normalizeEmailAddress(mailbox.address) === normalized) || null
}

function configuredMailboxAddresses() {
    return new Set(
        props.mailboxes
            .map((mailbox) => normalizeEmailAddress(mailbox.address))
            .filter(Boolean)
    )
}

function recipientAddresses(recipients = []) {
    return recipients
        .map((recipient) => normalizeEmailAddress(recipient?.address))
        .filter(Boolean)
}

function mailboxAddress(item) {
    const mailbox = normalizeEmailAddress(item?.mailbox)

    if (mailbox) {
        return mailbox
    }

    const configured = configuredMailboxAddresses()
    const from = normalizeEmailAddress(item?.from_address)

    if (configured.has(from)) {
        return from
    }

    return [...recipientAddresses(item?.to), ...recipientAddresses(item?.cc)]
        .find((address) => configured.has(address)) || ''
}

function mailboxLabel(item) {
    const address = mailboxAddress(item)
    const mailbox = configuredMailbox(address)

    return mailbox?.address || address
}

function mailboxTitle(item) {
    const address = mailboxAddress(item)
    const mailbox = configuredMailbox(address)

    return mailbox?.label || address || 'Ящик не определён'
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

function attachmentCount(item) {
    return Number(item?.attachments_count || item?.attachments?.length || 0)
}

function hasAttachments(item) {
    return Boolean(item?.has_attachments || attachmentCount(item))
}

function attachmentLabel(item) {
    const count = attachmentCount(item)

    if (!count) {
        return 'Письмо с вложениями'
    }

    const remainder = count % 100
    const lastDigit = count % 10
    const noun = remainder >= 11 && remainder <= 14
        ? 'вложений'
        : lastDigit === 1
            ? 'вложение'
            : lastDigit >= 2 && lastDigit <= 4
                ? 'вложения'
                : 'вложений'

    return `${count} ${noun}`
}

function rowProps({ item }) {
    return {
        class: [
            'mail-message-row',
            `mail-message-row--${folderKind(item?.folder)}`,
            hasAttachments(item) ? 'mail-message-row--with-attachments' : 'mail-message-row--text-only',
            item?.is_seen === false ? 'mail-message-row--unread' : '',
        ].join(' '),
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
        :style="{ height: typeof height === 'number' ? `${height}px` : height }"
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
                density="compact"
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
            <span class="mail-date text-[10px] font-mono">
                {{ formatDate(item.message_date) }}
            </span>
        </template>

        <template #item.mailbox="{ item }">
            <span
                class="mailbox-pill"
                :style="mailboxStyle(mailboxAddress(item))"
                :title="`${mailboxTitle(item)} · ${mailboxLabel(item) || '—'}`"
            >
                {{ mailboxLabel(item) || '—' }}
            </span>
        </template>

        <template #item.contact="{ item }">
            <div v-if="item.direction === 'incoming'" class="mail-contact">
                <div class="mail-contact-line text-purple-lighten-3" :title="item.from_address">
                    {{ item.from_address || '—' }}
                </div>

                <div
                    v-if="item.from_name"
                    class="mail-contact-line text-[10px] text-grey"
                    :title="item.from_name"
                >
                    {{ item.from_name }}
                </div>
            </div>

            <div
                v-else
                class="mail-contact text-[10px] text-grey-lighten-1"
            >
                <div
                    v-for="line in recipients(item)"
                    :key="line"
                    class="mail-contact-line"
                    :title="line"
                >
                    {{ line }}
                </div>
            </div>
        </template>

        <template #item.subject="{ item }">
            <div class="mail-subject-cell">
                <div class="mail-subject-line">
                    <span
                        class="mail-subject-kind"
                        :class="hasAttachments(item) ? 'mail-subject-kind--attachment' : 'mail-subject-kind--text'"
                        :title="hasAttachments(item) ? attachmentLabel(item) : 'Письмо только с текстом'"
                    >
                        <v-icon
                            :icon="hasAttachments(item) ? 'mdi-paperclip' : 'mdi-text-long'"
                            size="15"
                        />
                    </span>

                    <span
                        class="mail-subject-title"
                        :title="item.subject || 'Без темы'"
                    >
                        {{ item.subject || 'Без темы' }}
                    </span>

                    <v-icon
                        v-if="item.body_loaded_at"
                        icon="mdi-check-circle-outline"
                        size="13"
                        color="teal-lighten-3"
                        title="Тело письма загружено"
                    />
                </div>

                <div
                    v-if="item.preview"
                    class="mail-subject-preview line-clamp-1"
                    :title="item.preview"
                >
                    {{ item.preview }}
                </div>
            </div>
        </template>

        <template #item.relations="{ item }">
            <div class="mail-relations">
                <div
                    v-if="relations(item).length"
                    class="mail-relations__links"
                >
                    <Link
                        v-for="relation in relations(item).slice(0, 2)"
                        :key="relation.key"
                        :href="relation.href"
                        class="mail-relation-link"
                        :class="`mail-relation-link--${relation.kind}`"
                        :title="relation.title"
                        @click.stop
                    >
                        <v-icon :icon="relation.icon" size="10" />
                        <span>{{ relation.label }}</span>
                    </Link>
                </div>

                <span v-else class="mail-relations__empty">—</span>

                <v-menu v-if="relations(item).length > 2" location="bottom">
                    <template #activator="{ props: menuProps }">
                        <v-btn
                            v-bind="menuProps"
                            class="mail-relations-more"
                            size="x-small"
                            variant="text"
                            :title="`Все связи: ${relations(item).length}`"
                            :aria-label="`Показать все связи: ${relations(item).length}`"
                            @click.stop
                        >
                            +{{ relations(item).length - 2 }}
                        </v-btn>
                    </template>
                    <div class="mail-relations-menu">
                        <Link
                            v-for="relation in relations(item)"
                            :key="relation.key"
                            :href="relation.href"
                            class="mail-relation-link"
                            :class="`mail-relation-link--${relation.kind}`"
                            :title="relation.title"
                            @click.stop
                        >
                            <v-icon :icon="relation.icon" size="12" />
                            <span>{{ relation.title }}</span>
                        </Link>
                    </div>
                </v-menu>
            </div>
        </template>

        <template #item.actions="{ item }">
            <div class="mail-actions">
                <v-btn
                    icon="mdi-email-open-outline"
                    size="x-small"
                    variant="text"
                    color="blue"
                    title="Открыть письмо"
                    aria-label="Открыть письмо"
                    @click.stop="emit('read', item)"
                />

                <span :title="readStatusTitle(item)" @click.stop>
                    <v-btn
                        :icon="item.is_seen === true ? 'mdi-email-check' : 'mdi-email-check-outline'"
                        size="x-small"
                        variant="text"
                        :color="item.is_seen === true ? 'teal-lighten-3' : 'blue-grey-lighten-2'"
                        :loading="markingRead(item)"
                        :disabled="item.is_seen === true || !item.imap_uid"
                        :aria-label="readStatusTitle(item)"
                        @click.stop="emit('mark-read', item)"
                    />
                </span>

                <v-btn
                    icon="mdi-delete-outline"
                    size="x-small"
                    variant="text"
                    color="red-lighten-2"
                    title="Удалить письмо из базы"
                    aria-label="Удалить письмо из базы"
                    @click.stop="emit('delete', item)"
                />
            </div>
        </template>
    </v-data-table-server>
</template>

<style scoped>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.mail-relations {
    align-items: center;
    display: flex;
    flex-wrap: nowrap;
    gap: 3px;
    justify-content: center;
}

.mail-messages-table {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    --v-table-header-height: 30px;
    --v-table-row-height: 42px;
}

.mail-messages-table :deep(.v-data-table__tr:hover) {
    background: rgba(59, 130, 246, 0.08) !important;
}

.mail-messages-table :deep(.v-table__wrapper) {
    flex: 1 1 auto;
    height: auto !important;
    min-height: 0;
    overflow: auto;
    overscroll-behavior: contain;
}

.mail-messages-table :deep(table) {
    min-width: 1040px;
    table-layout: fixed;
}

.mail-messages-table :deep(th) {
    font-size: 10px;
    height: 30px;
    padding: 0 8px !important;
    white-space: nowrap;
}

.mail-messages-table :deep(th),
.mail-messages-table :deep(td) {
    vertical-align: middle;
}

.mail-messages-table :deep(td) {
    height: 42px;
    padding: 3px 7px !important;
}

.mail-messages-table :deep(.v-data-table-footer) {
    flex: 0 0 auto;
    gap: 4px;
    min-height: 40px;
    padding: 2px 6px;
    border-top: 1px solid rgba(96, 165, 250, 0.15);
    font-size: 11px;
}

.mail-messages-table :deep(.v-data-table-footer__items-per-page) {
    gap: 6px;
}

.mail-messages-table :deep(.v-data-table-footer__items-per-page .v-field) {
    --v-input-control-height: 30px;
    --v-field-padding-top: 2px;
    --v-field-padding-bottom: 2px;
    font-size: 11px;
}

.mail-messages-table :deep(.v-data-table-footer__pagination .v-btn) {
    width: 28px;
    height: 28px;
}

.mail-messages-table :deep(.mail-message-row--unread .mail-subject-title) {
    color: #eff6ff;
    font-weight: 700;
}

.mail-date {
    white-space: nowrap;
}

.mail-contact {
    font-size: 11px;
    line-height: 1.35;
    min-width: 0;
}

.mail-contact-line {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 1px;
}

.mail-actions :deep(.v-btn) {
    flex: 0 0 auto;
    width: 26px;
    height: 26px;
}

.mail-messages-table :deep(.mail-message-row--with-attachments) {
    background: linear-gradient(90deg, rgba(245, 158, 11, 0.1), transparent 48%);
}

.mail-messages-table :deep(.mail-message-row--with-attachments > td:first-child) {
    box-shadow: inset 3px 0 0 #fbbf24;
}

.mail-messages-table :deep(.mail-message-row--with-attachments:hover) {
    background: linear-gradient(90deg, rgba(245, 158, 11, 0.18), rgba(59, 130, 246, 0.08) 58%) !important;
}

.mail-subject-cell {
    cursor: pointer;
    min-width: 0;
    padding: 1px 0;
}

.mail-subject-line {
    align-items: center;
    display: flex;
    gap: 5px;
    min-width: 0;
}

.mail-subject-kind {
    display: inline-flex;
    flex: 0 0 auto;
}

.mail-subject-kind--attachment {
    color: #fbbf24;
}

.mail-subject-kind--text {
    color: #64748b;
}

.mail-subject-title {
    color: #dbeafe;
    font-size: 12px;
    line-height: 1.3;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mail-subject-cell:hover .mail-subject-title {
    color: #fff;
}

.mail-subject-preview {
    color: #94a3b8;
    font-size: 10px;
    line-height: 1.2;
    margin-top: 1px;
}

.mailbox-pill {
    display: block;
    max-width: 100%;
    min-width: 0;
    padding: 2px 7px;
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
    margin-top: 2px;
    padding: 1px 5px;
    border-radius: 999px;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
    flex-direction: column;
    align-items: center;
    gap: 2px;
    justify-content: center;
    min-width: 0;
}

.mail-relations-more {
    flex: 0 0 auto;
    min-width: 20px;
    padding: 0 2px;
    height: 20px;
    font-size: 9px;
    color: #93c5fd;
}

.mail-relations-menu {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 180px;
    max-width: 360px;
    max-height: 280px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid rgba(96, 165, 250, 0.35);
    border-radius: 8px;
    background: #0f172a;
}

.mail-relations-menu .mail-relation-link {
    max-width: 100%;
    padding: 5px 7px;
    font-size: 11px;
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
    max-width: 100%;
    min-width: 0;
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
