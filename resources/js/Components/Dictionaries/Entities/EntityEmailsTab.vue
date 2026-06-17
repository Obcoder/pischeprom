<script setup>
import { computed } from 'vue'

const props = defineProps({
    entity: {
        type: Object,
        default: null,
    },
})

const emails = computed(() => {
    return [...(props.entity?.emails || [])].sort((a, b) => {
        return String(a.address || '').localeCompare(String(b.address || ''), 'ru')
    })
})

function domain(email) {
    if (email?.domain) {
        return email.domain
    }

    return String(email?.address || '').split('@')[1] || '—'
}

function formatDate(value) {
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
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date)
}

function statusText(email) {
    return email?.is_active === false ? 'off' : 'active'
}
</script>

<template>
    <v-card class="entity-emails">
        <div class="entity-emails__head">
            <div>
                <span>Emails</span>
                <strong>{{ emails.length }}</strong>
            </div>

            <small>{{ entity?.name || 'Entity не выбрана' }}</small>
        </div>

        <div v-if="emails.length" class="entity-emails__sheet-wrap">
            <table class="entity-emails__sheet">
                <thead>
                    <tr>
                        <th class="entity-emails__rownum">#</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Domain</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>Last seen</th>
                        <th>Comment</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="(email, index) in emails" :key="email.id || email.address">
                        <td class="entity-emails__rownum">{{ index + 1 }}</td>
                        <td class="entity-emails__address">
                            <a :href="`mailto:${email.address}`">{{ email.address }}</a>
                        </td>
                        <td>{{ email.name || '—' }}</td>
                        <td>{{ domain(email) }}</td>
                        <td>{{ email.source || '—' }}</td>
                        <td>
                            <span
                                class="entity-emails__status"
                                :class="{ 'entity-emails__status--off': email.is_active === false }"
                            >
                                {{ statusText(email) }}
                            </span>
                        </td>
                        <td>{{ formatDate(email.verified_at) }}</td>
                        <td>{{ formatDate(email.last_seen_at) }}</td>
                        <td class="entity-emails__comment">{{ email.comment || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="entity-emails__empty">
            Emails для этой Entity не связаны.
        </div>
    </v-card>
</template>

<style scoped>
.entity-emails {
    overflow: hidden;
    border: 1px solid rgba(128, 0, 0, 0.18);
    border-radius: 18px;
    background: #fffdf8;
    box-shadow: 0 12px 30px rgba(48, 20, 10, 0.08);
}

.entity-emails__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(128, 0, 0, 0.18);
    background:
        linear-gradient(90deg, rgba(128, 0, 0, 0.14), rgba(128, 0, 0, 0.03)),
        #fff7ed;
}

.entity-emails__head div {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.entity-emails__head span {
    color: #3f1d1d;
    font-size: 0.78rem;
    font-weight: 950;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.entity-emails__head strong {
    color: #800000;
    font-size: 1rem;
    font-weight: 950;
}

.entity-emails__head small {
    overflow: hidden;
    color: #7c5148;
    font-size: 0.68rem;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-emails__sheet-wrap {
    max-height: 590px;
    overflow: auto;
    background: #fff;
}

.entity-emails__sheet {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
    color: #2d1a12;
    font-size: 11px;
    line-height: 1.18;
}

.entity-emails__sheet th,
.entity-emails__sheet td {
    max-width: 240px;
    padding: 3px 5px;
    border-right: 1px solid rgba(128, 0, 0, 0.14);
    border-bottom: 1px solid rgba(128, 0, 0, 0.12);
    overflow: hidden;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.entity-emails__sheet th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #800000;
    color: #fff7ed;
    font-size: 10px;
    font-weight: 950;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.entity-emails__sheet tr:nth-child(even) td {
    background: #fffaf4;
}

.entity-emails__sheet tr:hover td {
    background: #ffe8df;
}

.entity-emails__rownum {
    width: 42px;
    background: #f8eadf;
    color: #7c5148;
    font-weight: 900;
    text-align: right !important;
}

.entity-emails__sheet th.entity-emails__rownum {
    background: #5f0000;
    color: #fff;
}

.entity-emails__address a {
    color: #800000;
    font-weight: 900;
    text-decoration: none;
}

.entity-emails__address a:hover {
    text-decoration: underline;
}

.entity-emails__status {
    display: inline-flex;
    min-width: 42px;
    justify-content: center;
    padding: 1px 5px;
    border: 1px solid rgba(128, 0, 0, 0.22);
    border-radius: 2px;
    background: #f7d9ce;
    color: #800000;
    font-size: 10px;
    font-weight: 950;
}

.entity-emails__status--off {
    border-color: rgba(77, 63, 58, 0.28);
    background: #eee7e2;
    color: #6f625c;
}

.entity-emails__comment {
    max-width: 360px;
}

.entity-emails__empty {
    display: grid;
    min-height: 180px;
    place-items: center;
    color: #8b6b61;
    font-size: 0.82rem;
    font-weight: 800;
}

@media (max-width: 760px) {
    .entity-emails__head {
        align-items: flex-start;
        flex-direction: column;
    }

    .entity-emails__sheet-wrap {
        max-height: 520px;
    }
}
</style>
