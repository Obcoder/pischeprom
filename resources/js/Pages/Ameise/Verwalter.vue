<script setup>
import { Link } from '@inertiajs/vue3'
import { useHead } from '@vueuse/head'
import { route } from 'ziggy-js'
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'

defineOptions({
    layout: VerwalterLayout,
})

defineProps({
    activeLeads: {
        type: Array,
        default: () => [],
    },
})

function formatDateTime(value) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
}

function formatPhone(value) {
    if (!value) {
        return '—'
    }

    return String(value).startsWith('+') ? value : `+${value}`
}

function leadPhone(lead) {
    return lead.client_phone || lead.telephone?.number
}

function statusLabel(status) {
    return {
        open: 'Открыт',
        in_progress: 'В работе',
    }[status] || status || '—'
}

function entityUrl(entityId) {
    try {
        return route('Ameise.entity.show', entityId)
    } catch (error) {
        return `/Ameise/entity/${entityId}`
    }
}

useHead({
    title: 'Ameise — активные лиды',
    meta: [
        {
            name: 'description',
            content: 'Сводная страница Ameise',
        },
    ],
})
</script>

<template>
    <main class="ameise-dashboard">
        <section class="summary-block" aria-labelledby="active-leads-title">
            <header class="summary-block__header">
                <div>
                    <div class="summary-block__eyebrow">Сводная таблица</div>
                    <h1 id="active-leads-title">Активные лиды</h1>
                </div>
                <span class="summary-block__count" :aria-label="`Всего активных лидов: ${activeLeads.length}`">
                    {{ activeLeads.length }}
                </span>
            </header>

            <div class="lead-ledger">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Статус</th>
                            <th scope="col">Лид</th>
                            <th scope="col">Активность</th>
                        </tr>
                    </thead>
                    <tbody v-if="activeLeads.length">
                        <tr v-for="lead in activeLeads" :key="lead.id">
                            <td>
                                <span class="lead-status" :class="`lead-status--${lead.status}`">
                                    {{ statusLabel(lead.status) }}
                                </span>
                            </td>
                            <td>
                                <div class="lead-ledger__title" :title="lead.title || 'Лид'">
                                    {{ lead.title || 'Лид' }}
                                </div>
                                <div class="lead-ledger__meta">
                                    <span>#{{ lead.id }}</span>
                                    <Link
                                        v-if="lead.entity"
                                        :href="entityUrl(lead.entity.id)"
                                        class="lead-ledger__link"
                                    >
                                        {{ lead.entity.name }}
                                    </Link>
                                    <Link
                                        v-else-if="lead.unit"
                                        :href="route('web.unit.show', lead.unit.id)"
                                        class="lead-ledger__link"
                                    >
                                        {{ lead.unit.name }}
                                    </Link>
                                    <span v-else>{{ lead.source || 'Без CRM-связи' }}</span>
                                    <span v-if="leadPhone(lead)" class="lead-ledger__phone">
                                        {{ formatPhone(leadPhone(lead)) }}
                                    </span>
                                </div>
                            </td>
                            <td class="lead-ledger__date">
                                {{ formatDateTime(lead.last_activity_at || lead.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="3" class="lead-ledger__empty">Активных лидов нет</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>

<style scoped>
.ameise-dashboard {
    display: grid;
    align-self: stretch;
    align-content: start;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    min-height: calc(100vh - 48px);
    padding: 18px;
    background: #f6f7f9;
}

.summary-block {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    width: 100%;
    height: 50vh;
    border: 1px solid #d7dce2;
    border-radius: 8px;
    background: #ffffff;
}

.summary-block__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 58px;
    padding: 10px 14px;
    border-bottom: 1px solid #d7dce2;
}

.summary-block__eyebrow {
    margin-bottom: 2px;
    color: #7b8490;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.summary-block h1 {
    margin: 0;
    color: #20252b;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.2;
}

.summary-block__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 24px;
    padding: 0 8px;
    border: 1px solid #c8ced6;
    border-radius: 4px;
    background: #f5f6f8;
    color: #333941;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 11px;
    font-weight: 800;
}

.lead-ledger {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
}

.lead-ledger table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    color: #252a31;
    font-size: 12px;
}

.lead-ledger th:first-child {
    width: 78px;
}

.lead-ledger th:last-child {
    width: 82px;
}

.lead-ledger th,
.lead-ledger td {
    height: 34px;
    padding: 5px 10px;
    border-right: 1px solid #e6e9ed;
    border-bottom: 1px solid #e1e5e9;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
}

.lead-ledger th:last-child,
.lead-ledger td:last-child {
    border-right: 0;
}

.lead-ledger tbody tr:last-child td {
    border-bottom: 0;
}

.lead-ledger th {
    position: sticky;
    top: 0;
    z-index: 1;
    height: 30px;
    background: #f0f2f4;
    color: #626b76;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.lead-ledger tbody tr:hover {
    background: #faf4ee;
}

.lead-ledger__date,
.lead-ledger__phone {
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 11px;
}

.lead-ledger__date {
    color: #6f7781;
    white-space: normal !important;
}

.lead-ledger__title {
    overflow: hidden;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-ledger__meta {
    display: flex;
    gap: 5px;
    min-width: 0;
    overflow: hidden;
    margin-top: 2px;
    color: #8a929c;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-ledger__phone {
    color: #7f1d1d;
}

.lead-ledger__link {
    overflow: hidden;
    color: #7f1d1d;
    font-weight: 700;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-ledger__link:hover {
    text-decoration: underline;
}

.lead-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
}

.lead-status::before {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #64748b;
    content: "";
}

.lead-status--open::before {
    background: #2563eb;
}

.lead-status--in_progress::before {
    background: #d97706;
}

.lead-ledger__empty {
    height: 72px !important;
    color: #737b85;
    text-align: center !important;
}

@media (max-width: 700px) {
    .ameise-dashboard {
        grid-template-columns: minmax(0, 1fr);
        padding: 10px;
    }

    .summary-block__header {
        min-height: 54px;
        padding: 9px 10px;
    }
}
</style>
