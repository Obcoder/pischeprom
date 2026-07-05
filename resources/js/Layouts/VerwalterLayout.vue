<script setup>
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { route } from 'ziggy-js'

const workingLeads = ref([])
const workingLeadsTotal = ref(0)
const loadingWorkingLeads = ref(false)
const selectedLead = ref(null)
const selectedLeadLoading = ref(false)
const leadInfoDrawerOpen = ref(false)
const leadError = ref('')

const selectedLeadCalls = computed(() => selectedLead.value?.phone_calls || [])

async function fetchWorkingLeads() {
    loadingWorkingLeads.value = true
    leadError.value = ''

    try {
        const { data } = await axios.get('/api/leads', {
            params: {
                status: 'in_progress',
                per_page: 18,
            },
        })

        workingLeads.value = data.data || []
        workingLeadsTotal.value = data.total || workingLeads.value.length
    } catch (error) {
        console.error(error)
        leadError.value = 'Не удалось загрузить лиды в работе.'
    } finally {
        loadingWorkingLeads.value = false
    }
}

async function openLeadInfo(lead) {
    if (!lead?.id) {
        return
    }

    leadInfoDrawerOpen.value = true
    selectedLeadLoading.value = true
    selectedLead.value = lead
    leadError.value = ''

    try {
        const { data } = await axios.get(`/api/leads/${lead.id}`)
        selectedLead.value = data.data || data
    } catch (error) {
        console.error(error)
        leadError.value = 'Не удалось загрузить данные лида.'
    } finally {
        selectedLeadLoading.value = false
    }
}

function closeLeadInfo() {
    leadInfoDrawerOpen.value = false
}

function parseDate(value) {
    if (!value) {
        return null
    }

    const parsed = new Date(value)

    return Number.isNaN(parsed.getTime()) ? null : parsed
}

function formatDateTime(value) {
    const parsed = parseDate(value)

    if (!parsed) {
        return '-'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed)
}

function formatShortDate(value) {
    const parsed = parseDate(value)

    if (!parsed) {
        return '-'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed)
}

function formatPhone(value) {
    if (!value) {
        return '-'
    }

    return String(value).startsWith('+') ? value : `+${value}`
}

function formatSeconds(value) {
    if (value === null || value === undefined) {
        return '-'
    }

    const total = Number(value)
    const minutes = Math.floor(total / 60)
    const seconds = total % 60

    return `${minutes}:${String(seconds).padStart(2, '0')}`
}

function statusLabel(status) {
    return {
        open: 'Открыт',
        in_progress: 'В работе',
        won: 'Выигран',
        lost: 'Потерян',
        archived: 'Архив',
        success: 'Успешно',
        completed: 'Завершён',
        released: 'Завершён',
        missed: 'Пропущен',
        cancelled: 'Отменён',
        busy: 'Занято',
    }[status] || status || '-'
}

function statusColor(status) {
    return {
        open: 'blue',
        in_progress: 'red',
        won: 'green',
        lost: 'red',
        archived: 'grey',
        success: 'green',
        completed: 'green',
        released: 'green',
        missed: 'red',
        cancelled: 'orange',
        busy: 'orange',
    }[status] || 'grey'
}

function directionLabel(direction) {
    return {
        in: 'Входящий',
        out: 'Исходящий',
        missed: 'Пропущенный',
        unknown: 'Не определён',
    }[direction] || direction || '-'
}

function entityUrl(entityId) {
    try {
        return route('Ameise.entity.show', entityId)
    } catch (error) {
        return `/Ameise/entity/${entityId}`
    }
}

function commercialOffersUrl() {
    try {
        return route('admin.commercial-offers.index')
    } catch (error) {
        return '/Ameise/commercial-offers'
    }
}

function leadPrimaryContact(lead) {
    return lead.client_phone || lead.telephone?.number || ''
}

function leadRelationTitle(lead) {
    return lead.entity?.name || lead.unit?.name || lead.source || 'Без CRM-связи'
}

onMounted(fetchWorkingLeads)
</script>

<template>
    <v-layout class="rounded rounded-md verwalter-layout">
        <v-navigation-drawer width="312" class="ameise-leads-drawer">
            <div class="working-leads">
                <div class="working-leads__header">
                    <div>
                        <div class="working-leads__eyebrow">Ameise</div>
                        <h2>Лиды в работе</h2>
                    </div>
                    <button
                        type="button"
                        class="working-leads__refresh"
                        :disabled="loadingWorkingLeads"
                        @click="fetchWorkingLeads"
                    >
                        <v-icon icon="mdi-refresh" size="16" />
                    </button>
                </div>

                <div class="working-leads__meta">
                    <span>{{ workingLeadsTotal }}</span>
                    <span>активных задач</span>
                </div>

                <v-progress-linear
                    v-if="loadingWorkingLeads"
                    indeterminate
                    color="#8f1111"
                    height="2"
                    class="mb-3"
                />

                <v-alert
                    v-if="leadError"
                    type="error"
                    density="compact"
                    variant="tonal"
                    class="mb-3"
                >
                    {{ leadError }}
                </v-alert>

                <div v-if="workingLeads.length" class="working-leads__list">
                    <button
                        v-for="lead in workingLeads"
                        :key="lead.id"
                        type="button"
                        class="working-lead-card"
                        @click="openLeadInfo(lead)"
                    >
                        <span class="working-lead-card__top">
                            <span class="working-lead-card__title">{{ lead.title || 'Лид' }}</span>
                            <span class="working-lead-card__id">#{{ lead.id }}</span>
                        </span>
                        <span class="working-lead-card__relation">{{ leadRelationTitle(lead) }}</span>
                        <span class="working-lead-card__bottom">
                            <span class="working-lead-card__phone">{{ formatPhone(leadPrimaryContact(lead)) }}</span>
                            <span class="working-lead-card__date">{{ formatShortDate(lead.last_activity_at || lead.created_at) }}</span>
                        </span>
                    </button>
                </div>

                <div v-else-if="!loadingWorkingLeads" class="working-leads__empty">
                    <v-icon icon="mdi-check-circle-outline" size="22" />
                    <span>Нет лидов в работе</span>
                </div>
            </div>
        </v-navigation-drawer>

        <v-app-bar
            :elevation="2"
            rounded
            density="compact"
            scroll-behavior="fade-image elevate"
            image="https://picsum.photos/1920/1080?random"
        >
            <template v-slot:prepend>
                <v-app-bar-nav-icon>
                    <Link :href="route('Ameise')">
                        <v-icon icon="mdi-halloween" size="large" />
                    </Link>
                </v-app-bar-nav-icon>
            </template>
            <v-app-bar-title>
                <Link :href="route('Ameise.großbuch')">
                    <v-icon icon="mdi-access-point" size="x-small" />
                    <span class="ml-1 text-xs">Großbuch</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link
                    :href="route('Ameise.checks')"
                    title="Checks"
                    aria-label="Checks"
                >
                    <v-icon icon="mdi-receipt-text-outline" size="small" class="mx-1" />
                    <span>Checks</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link
                    :href="route('Ameise.warehouses')"
                    title="Склады"
                    aria-label="Склады"
                >
                    <v-icon icon="mdi-warehouse" size="small" class="mx-1" />
                    <span>Склады</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link
                    :href="route('Ameise.mail')"
                    class="ameise-mail-link"
                    title="Почта"
                    aria-label="Почта"
                >
                    <v-icon icon="mdi-email-fast-outline" size="18" />
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.fluxmonitor')">
                    <span>M</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link
                    :href="route('Ameise.fields')"
                    class="ameise-field-link"
                    title="Field matching"
                    aria-label="Field matching"
                >
                    <span>F</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link
                    :href="route('Ameise.home-banners')"
                    title="Баннеры главной"
                    aria-label="Баннеры главной"
                >
                    <v-icon icon="mdi-view-carousel-outline" size="small" class="mx-1" />
                    <span>Баннеры</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('ameise.workboard')">
                    <span>WorkBoard</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.botany')">
                    <span>Botany</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.perfume')">
                    <v-icon icon="mdi-scent" size="small" class="mx-2" />
                    <span>Perfume</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link :href="route('Ameise.marketing.yandex-direct')">
                    <v-icon icon="mdi-bullhorn-variant-outline" size="small" class="mx-1" />
                    <span>Маркетинг</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link
                    :href="route('Ameise.gis')"
                    class="ameise-gis-link"
                    title="GIS CRM"
                    aria-label="GIS CRM"
                >
                    <v-icon icon="mdi-map-marker-radius" size="small" class="mx-1" />
                    <span>GIS</span>
                </Link>
            </v-app-bar-title>
            <v-app-bar-title>
                <Link
                    :href="commercialOffersUrl()"
                    class="ameise-offers-link"
                    title="Коммерческие предложения"
                    aria-label="Коммерческие предложения"
                >
                    <v-icon icon="mdi-email-newsletter" size="small" class="mx-1" />
                    <span>КП</span>
                </Link>
            </v-app-bar-title>

            <template v-slot:append>
                <a
                    href="https://пищепром-сервер.рф/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ameise-header-icon"
                    title="Пищепром-Сервер"
                    aria-label="Пищепром-Сервер"
                >
                    <v-icon icon="mdi-server-network" size="21" />
                </a>

                <Link
                    :href="route('Ameise.contactsCentre')"
                    class="ameise-header-icon"
                    title="Contacts centre"
                    aria-label="Contacts centre"
                >
                    <v-icon icon="mdi-phone-in-talk" size="21" />
                </Link>

                <Link
                    :href="route('Ameise.settings')"
                    class="ameise-header-icon"
                    title="Настройки Ameise"
                    aria-label="Настройки Ameise"
                >
                    <v-icon icon="mdi-cog-outline" size="21" />
                </Link>

                <v-btn icon="mdi-heart" />

                <v-btn icon="mdi-magnify" />

                <v-btn icon="mdi-dots-vertical" />

                <v-icon icon="mdi-barn" size="x-small" />
            </template>
        </v-app-bar>

        <v-navigation-drawer
            v-model="leadInfoDrawerOpen"
            location="right"
            temporary
            width="520"
            class="lead-info-drawer"
        >
            <div class="lead-info">
                <div class="lead-info__header">
                    <div>
                        <div class="lead-info__eyebrow">Lead details</div>
                        <h2>{{ selectedLead?.title || 'Лид' }}</h2>
                        <div class="lead-info__id">#{{ selectedLead?.id }}</div>
                    </div>
                    <v-btn icon="mdi-close" size="small" variant="text" @click="closeLeadInfo" />
                </div>

                <v-progress-linear
                    v-if="selectedLeadLoading"
                    indeterminate
                    color="#8f1111"
                    height="2"
                    class="mb-4"
                />

                <div v-if="selectedLead" class="lead-info__body">
                    <section class="lead-info-section lead-info-section--accent">
                        <div class="lead-info-section__top">
                            <h3>Статус</h3>
                            <v-chip size="small" :color="statusColor(selectedLead.status)" variant="tonal">
                                {{ statusLabel(selectedLead.status) }}
                            </v-chip>
                        </div>
                        <p>{{ selectedLead.description || 'Описание пока не заполнено.' }}</p>
                    </section>

                    <section class="lead-info-section">
                        <h3>Контакт</h3>
                        <dl class="lead-info-grid">
                            <dt>Телефон</dt>
                            <dd>{{ formatPhone(selectedLead.client_phone || selectedLead.telephone?.number) }}</dd>
                            <dt>Источник</dt>
                            <dd>{{ selectedLead.source || '-' }}</dd>
                            <dt>Активность</dt>
                            <dd>{{ formatDateTime(selectedLead.last_activity_at || selectedLead.created_at) }}</dd>
                            <dt>Ответственный</dt>
                            <dd>{{ selectedLead.assigned_user?.name || '-' }}</dd>
                        </dl>
                    </section>

                    <section class="lead-info-section">
                        <h3>Связи</h3>
                        <div class="lead-info-relation">
                            <span>Entity</span>
                            <a v-if="selectedLead.entity" :href="entityUrl(selectedLead.entity.id)">
                                {{ selectedLead.entity.name }}
                            </a>
                            <strong v-else>-</strong>
                        </div>
                        <div class="lead-info-relation">
                            <span>Unit</span>
                            <Link v-if="selectedLead.unit" :href="route('web.unit.show', selectedLead.unit.id)">
                                {{ selectedLead.unit.name }}
                            </Link>
                            <strong v-else>-</strong>
                        </div>
                    </section>

                    <section class="lead-info-section">
                        <div class="lead-info-section__top">
                            <h3>Звонки</h3>
                            <span class="lead-info__calls-count">{{ selectedLeadCalls.length }}</span>
                        </div>
                        <div v-if="selectedLeadCalls.length" class="lead-info-calls">
                            <article v-for="call in selectedLeadCalls" :key="call.id" class="lead-info-call">
                                <div class="lead-info-call__top">
                                    <strong>{{ formatShortDate(call.started_at || call.created_at) }}</strong>
                                    <v-chip size="x-small" :color="statusColor(call.status)" variant="tonal">
                                        {{ statusLabel(call.status) }}
                                    </v-chip>
                                </div>
                                <div>{{ directionLabel(call.direction) }} · {{ formatSeconds(call.duration_seconds) }}</div>
                                <small>{{ formatPhone(call.client_phone) }}</small>
                            </article>
                        </div>
                        <div v-else class="lead-info__empty-calls">Связанных звонков нет.</div>
                    </section>
                </div>
            </div>
        </v-navigation-drawer>

        <v-main class="d-flex align-center justify-center" style="min-height: 300px;">
            <slot />
        </v-main>
    </v-layout>
</template>

<style scoped>
.ameise-leads-drawer :deep(a),
.lead-info-drawer :deep(a),
.verwalter-layout :deep(.v-app-bar a) {
    color: inherit;
    text-decoration: none;
}

.ameise-field-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 24px;
    border: 1px solid rgba(255, 255, 255, 0.32);
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.24);
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 13px;
    font-weight: 900;
    letter-spacing: 0.02em;
}

.ameise-field-link:hover {
    background: rgba(255, 255, 255, 0.18);
}

.ameise-mail-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 32px;
    border: 1px solid rgba(14, 165, 233, 0.42);
    border-radius: 11px;
    background:
        radial-gradient(circle at 70% 20%, rgba(255, 255, 255, 0.92), transparent 8%),
        linear-gradient(135deg, rgba(3, 105, 161, 0.88), rgba(15, 23, 42, 0.62));
    box-shadow: 0 8px 24px rgba(14, 165, 233, 0.28);
    color: #e0f2fe;
}

.ameise-mail-link::after {
    position: absolute;
    right: -4px;
    bottom: -4px;
    width: 12px;
    height: 12px;
    border: 2px solid rgba(255, 255, 255, 0.84);
    border-radius: 50%;
    background: #f97316;
    content: "";
}

.ameise-mail-link:hover {
    background:
        radial-gradient(circle at 70% 20%, rgba(255, 255, 255, 0.95), transparent 8%),
        linear-gradient(135deg, rgba(14, 165, 233, 0.96), rgba(29, 78, 216, 0.78));
    color: #ffffff;
    transform: translateY(-1px);
}

.ameise-offers-link {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    color: #d9ffbf;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.ameise-offers-link:hover {
    color: #ffffff;
}

.ameise-gis-link {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    color: #e0f2fe;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 12px;
    font-weight: 950;
    letter-spacing: 0.04em;
}

.ameise-gis-link:hover {
    color: #ffffff;
}

.ameise-leads-drawer {
    background:
        radial-gradient(circle at 22% 0%, rgba(143, 17, 17, 0.16), transparent 34%),
        linear-gradient(180deg, #fff8ef 0%, #f3eadb 58%, #e7ddcc 100%);
    border-right: 1px solid rgba(72, 42, 24, 0.12);
}

.working-leads {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 14px 12px;
    color: #24180f;
}

.working-leads__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}

.working-leads__eyebrow,
.lead-info__eyebrow {
    color: #8f1111;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.working-leads h2,
.lead-info h2 {
    margin: 0;
    font-size: 21px;
    font-weight: 900;
    letter-spacing: -0.04em;
    line-height: 1.05;
}

.working-leads__refresh {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: 1px solid rgba(143, 17, 17, 0.22);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.58);
    color: #8f1111;
    transition: transform 0.16s ease, background 0.16s ease;
}

.working-leads__refresh:hover:not(:disabled) {
    background: #ffffff;
    transform: translateY(-1px);
}

.working-leads__refresh:disabled {
    opacity: 0.55;
}

.working-leads__meta {
    display: inline-flex;
    align-items: baseline;
    gap: 7px;
    width: fit-content;
    margin-bottom: 12px;
    padding: 4px 9px;
    border-radius: 999px;
    background: rgba(143, 17, 17, 0.09);
    color: #6f1010;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    text-transform: uppercase;
}

.working-leads__meta span:first-child {
    font-size: 14px;
    font-weight: 900;
}

.working-leads__list {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 8px;
    min-height: 0;
    overflow-y: auto;
    padding-right: 2px;
}

.working-lead-card {
    display: block;
    width: 100%;
    padding: 10px;
    border: 1px solid rgba(72, 42, 24, 0.12);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.74);
    box-shadow: 0 8px 20px rgba(44, 24, 13, 0.06);
    color: #24180f;
    text-align: left;
    transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.working-lead-card:hover {
    border-color: rgba(143, 17, 17, 0.32);
    box-shadow: 0 12px 26px rgba(143, 17, 17, 0.12);
    transform: translateY(-1px);
}

.working-lead-card__top,
.working-lead-card__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.working-lead-card__title {
    overflow: hidden;
    font-size: 13px;
    font-weight: 900;
    letter-spacing: -0.02em;
    line-height: 1.18;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.working-lead-card__id,
.working-lead-card__date {
    flex: 0 0 auto;
    color: #8a8177;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
}

.working-lead-card__relation {
    display: block;
    overflow: hidden;
    margin: 5px 0 8px;
    color: #4c3b2e;
    font-size: 11px;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.working-lead-card__phone {
    color: #8f1111;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    font-weight: 800;
}

.working-leads__empty {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px;
    border: 1px dashed rgba(72, 42, 24, 0.24);
    border-radius: 16px;
    color: #655445;
    font-size: 13px;
}

.ameise-header-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    margin-right: 4px;
    border: 1px solid rgba(255, 255, 255, 0.36);
    border-radius: 13px;
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
    backdrop-filter: blur(5px);
    transition: background 0.16s ease, transform 0.16s ease;
}

.ameise-header-icon:hover {
    background: rgba(143, 17, 17, 0.74);
    transform: translateY(-1px);
}

.lead-info-drawer {
    background: #fffaf4;
}

.lead-info {
    height: 100%;
    overflow-y: auto;
    padding: 18px;
    color: #24180f;
}

.lead-info__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.lead-info__id,
.lead-info__calls-count {
    color: #817466;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 11px;
    font-weight: 800;
}

.lead-info__body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.lead-info-section {
    padding: 14px;
    border: 1px solid rgba(72, 42, 24, 0.12);
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 8px 22px rgba(44, 24, 13, 0.06);
}

.lead-info-section--accent {
    background: linear-gradient(135deg, #fff 0%, #fff3ea 100%);
}

.lead-info-section__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.lead-info-section h3 {
    margin: 0 0 9px;
    font-size: 13px;
    font-weight: 900;
    letter-spacing: -0.02em;
}

.lead-info-section__top h3 {
    margin-bottom: 0;
}

.lead-info-section p {
    margin: 10px 0 0;
    color: #4f4036;
    font-size: 13px;
    line-height: 1.45;
    white-space: pre-wrap;
}

.lead-info-grid {
    display: grid;
    grid-template-columns: 112px 1fr;
    gap: 7px 12px;
    margin: 0;
    font-size: 13px;
}

.lead-info-grid dt,
.lead-info-relation span {
    color: #817466;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.lead-info-grid dd {
    margin: 0;
    min-width: 0;
}

.lead-info-relation {
    display: grid;
    grid-template-columns: 72px 1fr;
    gap: 12px;
    padding: 7px 0;
    border-bottom: 1px solid rgba(72, 42, 24, 0.08);
    font-size: 13px;
}

.lead-info-relation:last-child {
    border-bottom: 0;
}

.lead-info-relation a {
    color: #8f1111;
    font-weight: 800;
}

.lead-info-calls {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
}

.lead-info-call {
    padding: 10px;
    border-radius: 14px;
    background: #f8f1e8;
    color: #3d3028;
    font-size: 12px;
}

.lead-info-call__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 4px;
}

.lead-info-call small,
.lead-info__empty-calls {
    color: #817466;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 10px;
}

@media (max-width: 960px) {
    .lead-info-drawer {
        width: min(92vw, 520px) !important;
    }
}
</style>
