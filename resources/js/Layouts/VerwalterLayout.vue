<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import { route } from 'ziggy-js'

const MANAGER_PHONE = '79650160001'

const page = usePage()
const canViewOrders = computed(() => Boolean(page.props.auth?.permissions?.orders?.view))
const workingLeads = ref([])
const workingLeadsTotal = ref(0)
const loadingWorkingLeads = ref(false)
const workingLeadsDrawerOpen = ref(false)
const selectedLead = ref(null)
const selectedLeadLoading = ref(false)
const leadInfoDrawerOpen = ref(false)
const leadError = ref('')
const savingLead = ref(false)
const savingLeadStatus = ref(null)
const dialingLeadId = ref(null)
const relationOptionsLoading = ref(false)
const entityOptions = ref([])
const unitOptions = ref([])

const leadForm = reactive({
    title: '',
    status: 'open',
    description: '',
    entity_id: null,
    unit_id: null,
})

const leadStatusItems = [
    { title: 'Открыт', value: 'open' },
    { title: 'В работе', value: 'in_progress' },
    { title: 'Выигран', value: 'won' },
    { title: 'Потерян', value: 'lost' },
    { title: 'Архив', value: 'archived' },
]

const leadStatusActionItems = [
    { title: 'Открыть', status: 'open', color: 'blue', icon: 'mdi-lock-open-outline' },
    { title: 'В работу', status: 'in_progress', color: 'amber', icon: 'mdi-progress-clock' },
    { title: 'Выигран', status: 'won', color: 'green', icon: 'mdi-check-circle-outline' },
    { title: 'Потерян', status: 'lost', color: 'red', icon: 'mdi-close-circle-outline' },
    { title: 'Архив', status: 'archived', color: 'grey', icon: 'mdi-archive-outline' },
]

const selectedLeadCalls = computed(() => selectedLead.value?.phone_calls || [])
const selectedLeadPhone = computed(() => leadPrimaryContact(selectedLead.value))

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

    workingLeadsDrawerOpen.value = false
    leadInfoDrawerOpen.value = true
    selectedLeadLoading.value = true
    selectedLead.value = lead
    fillLeadForm(lead)
    leadError.value = ''

    try {
        const [{ data }] = await Promise.all([
            axios.get(`/api/leads/${lead.id}`),
            ensureRelationOptions(lead),
        ])
        selectedLead.value = data.data || data
        fillLeadForm(selectedLead.value)
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

function toggleWorkingLeads() {
    if (!workingLeadsDrawerOpen.value) {
        leadInfoDrawerOpen.value = false
    }

    workingLeadsDrawerOpen.value = !workingLeadsDrawerOpen.value
}

function fillLeadForm(lead) {
    leadForm.title = lead?.title || ''
    leadForm.status = lead?.status || 'open'
    leadForm.description = lead?.description || ''
    leadForm.entity_id = lead?.entity_id || lead?.entity?.id || null
    leadForm.unit_id = lead?.unit_id || lead?.unit?.id || null

    mergeRelationOptions(lead)
}

async function saveLead() {
    if (!selectedLead.value?.id) {
        return
    }

    savingLead.value = true
    leadError.value = ''

    try {
        const { data } = await axios.patch(`/api/leads/${selectedLead.value.id}`, {
            title: leadForm.title,
            status: leadForm.status,
            description: leadForm.description,
            entity_id: leadForm.entity_id || null,
            unit_id: leadForm.unit_id || null,
        })

        selectedLead.value = data.data || data
        fillLeadForm(selectedLead.value)
        await fetchWorkingLeads()
    } catch (error) {
        console.error(error)
        leadError.value = 'Не удалось сохранить лид.'
    } finally {
        savingLead.value = false
    }
}

async function updateLeadStatus(status) {
    if (!selectedLead.value?.id || selectedLead.value.status === status) {
        return
    }

    savingLeadStatus.value = status
    leadError.value = ''

    try {
        const { data } = await axios.patch(`/api/leads/${selectedLead.value.id}`, { status })
        selectedLead.value = data.data || data
        fillLeadForm(selectedLead.value)
        await fetchWorkingLeads()
    } catch (error) {
        console.error(error)
        leadError.value = 'Не удалось обновить статус лида.'
    } finally {
        savingLeadStatus.value = null
    }
}

async function dialLead() {
    const phone = selectedLeadPhone.value

    if (!selectedLead.value?.id || !phone) {
        leadError.value = 'У лида нет номера клиента.'
        return
    }

    dialingLeadId.value = selectedLead.value.id
    leadError.value = ''

    try {
        await axios.post('/api/phone-calls/dial', {
            client_phone: phone,
            employee_phone: MANAGER_PHONE,
        })
    } catch (error) {
        console.error(error)
        leadError.value = error.response?.data?.message || 'Не удалось запустить звонок через Билайн.'
    } finally {
        dialingLeadId.value = null
    }
}

async function ensureRelationOptions(lead = null) {
    const search = lead?.entity?.name || lead?.unit?.name || ''

    if (entityOptions.value.length && unitOptions.value.length) {
        mergeRelationOptions(lead)
        return
    }

    await Promise.all([
        fetchEntityOptions(search),
        fetchUnitOptions(search),
    ])
    mergeRelationOptions(lead)
}

async function fetchEntityOptions(search = '') {
    relationOptionsLoading.value = true

    try {
        const { data } = await axios.get('/api/entities', {
            params: {
                search,
                itemsPerPage: 25,
            },
        })

        entityOptions.value = normalizeOptions(data.data || data)
        mergeRelationOptions(selectedLead.value)
    } catch (error) {
        console.error(error)
        leadError.value = 'Не удалось загрузить список Entity.'
    } finally {
        relationOptionsLoading.value = false
    }
}

async function fetchUnitOptions(search = '') {
    relationOptionsLoading.value = true

    try {
        const { data } = await axios.get('/api/units', {
            params: { search },
        })

        unitOptions.value = normalizeOptions(data.data || data)
        mergeRelationOptions(selectedLead.value)
    } catch (error) {
        console.error(error)
        leadError.value = 'Не удалось загрузить список Unit.'
    } finally {
        relationOptionsLoading.value = false
    }
}

function normalizeOptions(items) {
    return (Array.isArray(items) ? items : [])
        .filter((item) => item?.id)
        .map((item) => ({
            id: item.id,
            name: item.name || `#${item.id}`,
        }))
}

function mergeRelationOptions(lead) {
    if (lead?.entity?.id) {
        entityOptions.value = mergeOptions(entityOptions.value, [lead.entity])
    }

    if (lead?.unit?.id) {
        unitOptions.value = mergeOptions(unitOptions.value, [lead.unit])
    }
}

function mergeOptions(current, additions) {
    const byId = new Map()

    ;[...current, ...normalizeOptions(additions)].forEach((item) => {
        byId.set(item.id, item)
    })

    return Array.from(byId.values()).sort((a, b) => String(a.name).localeCompare(String(b.name)))
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

function bankUrl() {
    try {
        return route('admin.bank.index')
    } catch (error) {
        return '/Ameise/bank'
    }
}

function normalizeRoutePath(value) {
    try {
        const path = new URL(String(value || '/'), 'http://localhost').pathname
        const normalized = path.replace(/\/+$/, '')

        return (normalized || '/').toLowerCase()
    } catch (error) {
        return '/'
    }
}

function isActiveUrl(url, exact = false) {
    const currentPath = normalizeRoutePath(page.url)
    const targetPath = normalizeRoutePath(url)

    if (exact || targetPath === '/') {
        return currentPath === targetPath
    }

    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`)
}

function mailMessageUrl(mailMessageId) {
    if (!mailMessageId) {
        return null
    }

    try {
        return `${route('Ameise.mail')}?mail_message_id=${mailMessageId}`
    } catch (error) {
        return `/Ameise/Mail?mail_message_id=${mailMessageId}`
    }
}

function leadPrimaryContact(lead) {
    return lead?.client_phone || lead?.telephone?.number || ''
}

function leadRelationTitle(lead) {
    return lead.entity?.name || lead.unit?.name || lead.source || 'Без CRM-связи'
}

onMounted(fetchWorkingLeads)
</script>

<template>
    <v-layout class="rounded rounded-md verwalter-layout">
        <v-navigation-drawer
            id="working-leads-drawer"
            v-model="workingLeadsDrawerOpen"
            location="left"
            temporary
            width="312"
            class="ameise-leads-drawer"
        >
            <div class="working-leads">
                <div class="working-leads__header">
                    <div>
                        <div class="working-leads__eyebrow">Ameise</div>
                        <h2>Лиды в работе</h2>
                    </div>
                    <div class="working-leads__actions">
                        <button
                            type="button"
                            class="working-leads__icon-button"
                            aria-label="Обновить лиды в работе"
                            title="Обновить"
                            :disabled="loadingWorkingLeads"
                            @click="fetchWorkingLeads"
                        >
                            <v-icon icon="mdi-refresh" size="16" />
                        </button>
                        <button
                            type="button"
                            class="working-leads__icon-button"
                            aria-label="Закрыть лиды в работе"
                            title="Закрыть"
                            @click="workingLeadsDrawerOpen = false"
                        >
                            <v-icon icon="mdi-close" size="17" />
                        </button>
                    </div>
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
            :elevation="0"
            height="58"
            class="ameise-app-bar"
        >
            <template #prepend>
                <div class="ameise-header-brand">
                    <button
                        type="button"
                        class="ameise-header-control ameise-header-control--lead working-leads-trigger"
                        :title="workingLeadsDrawerOpen ? 'Закрыть лиды в работе' : 'Открыть лиды в работе'"
                        :aria-label="workingLeadsDrawerOpen ? 'Закрыть лиды в работе' : 'Открыть лиды в работе'"
                        aria-controls="working-leads-drawer"
                        :aria-expanded="workingLeadsDrawerOpen"
                        @click="toggleWorkingLeads"
                    >
                        <v-badge
                            :content="workingLeadsTotal"
                            :model-value="workingLeadsTotal > 0"
                            color="#8f1111"
                            max="99"
                        >
                            <v-icon icon="mdi-account-clock-outline" size="20" />
                        </v-badge>
                    </button>

                    <Link
                        :href="route('Ameise')"
                        class="ameise-header-control ameise-header-home"
                        :class="{ 'is-active': isActiveUrl(route('Ameise'), true) }"
                        title="Ameise"
                        aria-label="Главная Ameise"
                    >
                        <v-icon icon="mdi-halloween" size="21" />
                        <span class="ameise-header-home__label">Ameise</span>
                    </Link>
                </div>
            </template>

            <nav class="ameise-header-nav" aria-label="Основная навигация Ameise">
                <Link
                    :href="route('Ameise.großbuch')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.großbuch')) }"
                    title="Großbuch"
                    aria-label="Großbuch"
                >
                    <v-icon icon="mdi-book-open-variant" size="19" />
                    <span class="ameise-nav-link__label">Großbuch</span>
                </Link>

                <Link
                    :href="route('Ameise.checks')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.checks')) }"
                    title="Checks"
                    aria-label="Checks"
                >
                    <v-icon icon="mdi-receipt-text-outline" size="19" />
                    <span class="ameise-nav-link__label">Checks</span>
                </Link>

                <Link
                    :href="route('Ameise.warehouses')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.warehouses')) }"
                    title="Склады"
                    aria-label="Склады"
                >
                    <v-icon icon="mdi-warehouse" size="19" />
                    <span class="ameise-nav-link__label">Склады</span>
                </Link>

                <Link
                    :href="route('Ameise.taxi-shifts')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.taxi-shifts')) }"
                    title="Такси"
                    aria-label="Такси"
                >
                    <v-icon icon="mdi-taxi" size="19" />
                    <span class="ameise-nav-link__label">Такси</span>
                </Link>

                <Link
                    :href="route('Ameise.logistics')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.logistics')) }"
                    title="Логистика"
                    aria-label="Логистика"
                >
                    <v-icon icon="mdi-truck-fast-outline" size="19" />
                    <span class="ameise-nav-link__label">Логистика</span>
                </Link>

                <Link
                    :href="route('Ameise.mail')"
                    class="ameise-header-control ameise-nav-link ameise-nav-link--mail"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.mail')) }"
                    title="Почта"
                    aria-label="Почта"
                >
                    <v-icon icon="mdi-email-fast-outline" size="19" />
                    <span class="ameise-nav-link__label">Почта</span>
                    <span class="ameise-mail-status" aria-hidden="true"></span>
                </Link>

                <Link
                    :href="route('Ameise.max')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.max')) }"
                    title="MAX"
                    aria-label="MAX"
                >
                    <v-icon icon="mdi-message-text-outline" size="19" />
                    <span class="ameise-nav-link__label">MAX</span>
                </Link>

                <Link
                    :href="route('Ameise.fluxmonitor')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.fluxmonitor')) }"
                    title="FluxMonitor"
                    aria-label="FluxMonitor"
                >
                    <span class="ameise-nav-monogram">M</span>
                    <span class="ameise-nav-link__label">Monitor</span>
                </Link>

                <Link
                    :href="route('Ameise.fields')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.fields')) }"
                    title="Field matching"
                    aria-label="Field matching"
                >
                    <span class="ameise-nav-monogram">F</span>
                    <span class="ameise-nav-link__label">Fields</span>
                </Link>

                <Link
                    :href="route('Ameise.home-banners')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.home-banners')) }"
                    title="Баннеры главной"
                    aria-label="Баннеры главной"
                >
                    <v-icon icon="mdi-view-carousel-outline" size="19" />
                    <span class="ameise-nav-link__label">Баннеры</span>
                </Link>

                <Link
                    :href="route('ameise.workboard')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('ameise.workboard')) }"
                    title="WorkBoard"
                    aria-label="WorkBoard"
                >
                    <v-icon icon="mdi-view-dashboard-outline" size="19" />
                    <span class="ameise-nav-link__label">WorkBoard</span>
                </Link>

                <Link
                    :href="route('Ameise.botany')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.botany')) }"
                    title="Botany"
                    aria-label="Botany"
                >
                    <v-icon icon="mdi-sprout" size="19" />
                    <span class="ameise-nav-link__label">Botany</span>
                </Link>

                <Link
                    :href="route('Ameise.perfume')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.perfume')) }"
                    title="Perfume"
                    aria-label="Perfume"
                >
                    <v-icon icon="mdi-scent" size="19" />
                    <span class="ameise-nav-link__label">Perfume</span>
                </Link>

                <Link
                    :href="route('Ameise.marketing.yandex-direct')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.marketing.yandex-direct')) }"
                    title="Маркетинг"
                    aria-label="Маркетинг"
                >
                    <v-icon icon="mdi-bullhorn-variant-outline" size="19" />
                    <span class="ameise-nav-link__label">Маркетинг</span>
                </Link>

                <Link
                    :href="route('Ameise.gis')"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(route('Ameise.gis')) }"
                    title="GIS CRM"
                    aria-label="GIS CRM"
                >
                    <v-icon icon="mdi-map-marker-radius" size="19" />
                    <span class="ameise-nav-link__label">GIS</span>
                </Link>

                <Link
                    :href="commercialOffersUrl()"
                    class="ameise-header-control ameise-nav-link"
                    :class="{ 'is-active': isActiveUrl(commercialOffersUrl()) }"
                    title="Коммерческие предложения"
                    aria-label="Коммерческие предложения"
                >
                    <v-icon icon="mdi-email-newsletter" size="19" />
                    <span class="ameise-nav-link__label">КП</span>
                </Link>
            </nav>

            <template #append>
                <div class="ameise-header-actions">
                    <Link
                        :href="route('Ameise.products')"
                        class="ameise-header-control ameise-header-icon ameise-products-icon"
                        :class="{ 'is-active': isActiveUrl(route('Ameise.products')) }"
                        title="Products"
                        aria-label="Products"
                    >
                        <v-icon icon="mdi-package-variant-closed" size="21" />
                    </Link>

                    <Link
                        v-if="canViewOrders"
                        :href="route('Ameise.orders.index')"
                        class="ameise-header-control ameise-header-icon ameise-orders-icon"
                        :class="{ 'is-active': isActiveUrl(route('Ameise.orders.index')) }"
                        title="Заказы"
                        aria-label="Панель заказов"
                    >
                        <v-icon icon="mdi-clipboard-text-clock-outline" size="21" />
                    </Link>

                    <Link
                        :href="bankUrl()"
                        class="ameise-header-control ameise-header-icon ameise-bank-icon"
                        :class="{ 'is-active': isActiveUrl(bankUrl()) }"
                        title="Банк"
                        aria-label="Банк"
                    >
                        <v-icon icon="mdi-bank-outline" size="21" />
                    </Link>

                    <a
                        href="https://пищепром-сервер.рф/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ameise-header-control ameise-header-icon ameise-server-icon"
                        title="Пищепром-Сервер"
                        aria-label="Пищепром-Сервер"
                    >
                        <v-icon icon="mdi-server-network" size="21" />
                    </a>

                    <Link
                        :href="route('Ameise.contactsCentre')"
                        class="ameise-header-control ameise-header-icon ameise-contacts-icon"
                        :class="{ 'is-active': isActiveUrl(route('Ameise.contactsCentre')) }"
                        title="Contacts centre"
                        aria-label="Contacts centre"
                    >
                        <v-icon icon="mdi-phone-in-talk" size="21" />
                    </Link>

                    <Link
                        :href="route('Ameise.settings')"
                        class="ameise-header-control ameise-header-icon ameise-settings-icon"
                        :class="{ 'is-active': isActiveUrl(route('Ameise.settings')) }"
                        title="Настройки Ameise"
                        aria-label="Настройки Ameise"
                    >
                        <v-icon icon="mdi-cog-outline" size="21" />
                    </Link>
                </div>
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

                <v-alert
                    v-if="leadError && !selectedLeadLoading"
                    type="error"
                    density="compact"
                    variant="tonal"
                    class="mb-3"
                >
                    {{ leadError }}
                </v-alert>

                <div v-if="selectedLead" class="lead-info__body">
                    <section class="lead-info-section lead-info-section--tools">
                        <div class="lead-info-section__top">
                            <h3>Инструменты</h3>
                            <v-chip size="small" :color="statusColor(selectedLead.status)" variant="tonal">
                                {{ statusLabel(selectedLead.status) }}
                            </v-chip>
                        </div>

                        <div class="lead-info-tools-grid">
                            <v-btn
                                v-for="action in leadStatusActionItems"
                                :key="action.status"
                                size="small"
                                :color="action.color"
                                :variant="selectedLead.status === action.status ? 'elevated' : 'tonal'"
                                :prepend-icon="action.icon"
                                :loading="savingLeadStatus === action.status"
                                :disabled="savingLead || selectedLead.status === action.status"
                                @click="updateLeadStatus(action.status)"
                            >
                                {{ action.title }}
                            </v-btn>
                        </div>

                        <div class="lead-info-tools-row">
                            <v-btn
                                size="small"
                                color="green"
                                variant="tonal"
                                prepend-icon="mdi-phone-outgoing"
                                :loading="dialingLeadId === selectedLead.id"
                                :disabled="!selectedLeadPhone"
                                @click="dialLead"
                            >
                                Позвонить
                            </v-btn>

                            <v-btn
                                v-if="mailMessageUrl(selectedLead.mail_message_id)"
                                size="small"
                                color="blue"
                                variant="tonal"
                                prepend-icon="mdi-email-open-outline"
                                :href="mailMessageUrl(selectedLead.mail_message_id)"
                            >
                                Письмо
                            </v-btn>

                            <v-btn
                                size="small"
                                color="#8f1111"
                                variant="text"
                                prepend-icon="mdi-refresh"
                                :loading="selectedLeadLoading"
                                @click="openLeadInfo(selectedLead)"
                            >
                                Обновить
                            </v-btn>
                        </div>
                    </section>

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
                        <h3>Редактирование</h3>
                        <v-text-field
                            v-model="leadForm.title"
                            label="Название"
                            density="compact"
                            variant="outlined"
                            hide-details
                        />
                        <v-select
                            v-model="leadForm.status"
                            :items="leadStatusItems"
                            label="Статус"
                            density="compact"
                            variant="outlined"
                            hide-details
                        />
                        <v-textarea
                            v-model="leadForm.description"
                            label="Описание / работа с лидом"
                            rows="4"
                            variant="outlined"
                            hide-details
                        />
                        <v-autocomplete
                            v-model="leadForm.entity_id"
                            :items="entityOptions"
                            item-title="name"
                            item-value="id"
                            label="Entity"
                            density="compact"
                            variant="outlined"
                            clearable
                            hide-details
                            :loading="relationOptionsLoading"
                            @update:search="fetchEntityOptions"
                        />
                        <v-autocomplete
                            v-model="leadForm.unit_id"
                            :items="unitOptions"
                            item-title="name"
                            item-value="id"
                            label="Unit"
                            density="compact"
                            variant="outlined"
                            clearable
                            hide-details
                            :loading="relationOptionsLoading"
                            @update:search="fetchUnitOptions"
                        />
                        <v-btn
                            color="#8f1111"
                            rounded="lg"
                            prepend-icon="mdi-content-save-outline"
                            :loading="savingLead"
                            @click="saveLead"
                        >
                            Сохранить
                        </v-btn>
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

.ameise-app-bar {
    overflow: visible !important;
    border-bottom: 1px solid rgba(96, 165, 250, 0.28) !important;
    background: linear-gradient(105deg, #040714 0%, #07122d 48%, #050817 100%) !important;
    box-shadow:
        0 1px 0 rgba(125, 211, 252, 0.08),
        0 12px 34px rgba(2, 6, 23, 0.58),
        0 10px 44px rgba(37, 99, 235, 0.16) !important;
    color: #dce9ff;
}

.ameise-app-bar :deep(.v-toolbar__content) {
    position: relative;
    isolation: isolate;
    overflow: visible;
    padding: 0 9px;
    background:
        radial-gradient(ellipse 210px 72px at 4% 50%, rgba(37, 99, 235, 0.28), transparent 72%),
        radial-gradient(ellipse 240px 82px at 92% 50%, rgba(29, 78, 216, 0.18), transparent 76%);
}

.ameise-app-bar :deep(.v-toolbar__content)::before {
    position: absolute;
    z-index: 0;
    inset: 0;
    background:
        linear-gradient(90deg, rgba(255, 255, 255, 0.025), transparent 28%, rgba(59, 130, 246, 0.025) 72%, transparent),
        repeating-linear-gradient(90deg, transparent 0 92px, rgba(148, 163, 184, 0.018) 93px);
    content: "";
    pointer-events: none;
}

.ameise-app-bar :deep(.v-toolbar__content)::after {
    position: absolute;
    z-index: 1;
    right: 18px;
    bottom: 0;
    left: 18px;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(59, 130, 246, 0.58) 12%,
        rgba(34, 211, 238, 0.82) 52%,
        rgba(59, 130, 246, 0.48) 86%,
        transparent 100%
    );
    box-shadow: 0 0 13px rgba(37, 99, 235, 0.95);
    content: "";
    pointer-events: none;
}

.ameise-app-bar :deep(.v-toolbar__prepend),
.ameise-app-bar :deep(.v-toolbar__append) {
    position: relative;
    z-index: 3;
    height: 100%;
    margin-inline: 0;
}

.ameise-header-brand,
.ameise-header-actions {
    display: flex;
    align-items: center;
    gap: 5px;
    height: 100%;
}

.ameise-header-brand {
    padding-right: 9px;
    border-right: 1px solid rgba(125, 211, 252, 0.1);
}

.ameise-header-actions {
    padding-left: 9px;
    border-left: 1px solid rgba(125, 211, 252, 0.1);
}

.ameise-header-nav {
    position: relative;
    z-index: 3;
    display: flex;
    align-items: center;
    flex: 1 1 auto;
    gap: 4px;
    min-width: 0;
    height: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 0 10px;
    scrollbar-width: none;
}

.ameise-header-nav::-webkit-scrollbar {
    display: none;
}

.ameise-header-control {
    --ameise-glow: 59 130 246;
    position: relative;
    isolation: isolate;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    min-width: 38px;
    height: 38px;
    padding: 0 9px;
    border: 0;
    border-radius: 12px;
    outline: none;
    background: transparent;
    color: #bfd0ee;
    cursor: pointer;
    font: inherit;
    text-decoration: none;
    transition: color 0.2s ease, transform 0.2s ease;
}

.ameise-header-control::before {
    position: absolute;
    z-index: 0;
    inset: -7px;
    border-radius: 17px;
    background: radial-gradient(
        circle at 50% 55%,
        rgb(var(--ameise-glow) / 0.88) 0%,
        rgb(var(--ameise-glow) / 0.48) 30%,
        rgb(var(--ameise-glow) / 0.13) 54%,
        transparent 72%
    );
    content: "";
    filter: blur(7px);
    opacity: 0.14;
    pointer-events: none;
    transform: scale(0.82);
    transition: opacity 0.22s ease, transform 0.22s ease;
}

.ameise-header-control::after {
    position: absolute;
    z-index: 1;
    inset: 0;
    border: 1px solid rgba(147, 197, 253, 0.08);
    border-radius: inherit;
    background: linear-gradient(145deg, rgba(30, 41, 79, 0.26), rgba(7, 12, 32, 0.2));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.025);
    content: "";
    pointer-events: none;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.ameise-header-control > * {
    position: relative;
    z-index: 2;
}

.ameise-header-control :deep(.v-icon) {
    filter: drop-shadow(0 0 5px rgb(var(--ameise-glow) / 0.28));
    transition: filter 0.2s ease, transform 0.2s ease;
}

.ameise-header-control:hover,
.ameise-header-control.is-active {
    color: #f4f8ff;
    transform: translateY(-1px);
}

.ameise-header-control:hover::before,
.ameise-header-control.is-active::before {
    opacity: 0.92;
    transform: scale(1);
}

.ameise-header-control:hover::after,
.ameise-header-control.is-active::after {
    border-color: rgb(var(--ameise-glow) / 0.55);
    background: linear-gradient(
        145deg,
        rgb(var(--ameise-glow) / 0.26),
        rgba(13, 22, 55, 0.74)
    );
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.14),
        inset 0 0 18px rgb(var(--ameise-glow) / 0.1),
        0 0 12px rgb(var(--ameise-glow) / 0.22);
}

.ameise-header-control:hover :deep(.v-icon),
.ameise-header-control.is-active :deep(.v-icon) {
    filter:
        drop-shadow(0 0 4px rgb(var(--ameise-glow) / 0.92))
        drop-shadow(0 0 10px rgb(var(--ameise-glow) / 0.46));
    transform: scale(1.04);
}

.ameise-header-control:focus-visible {
    outline: 2px solid #7dd3fc;
    outline-offset: 2px;
}

.ameise-header-control.is-active::before {
    animation: ameise-backlight 3.4s ease-in-out infinite;
}

.ameise-header-control--lead[aria-expanded="true"] {
    --ameise-glow: 239 68 68;
    color: #fff1f2;
}

.working-leads-trigger :deep(.v-badge__badge) {
    min-width: 15px;
    height: 15px;
    padding: 0 4px;
    border: 1px solid rgba(255, 255, 255, 0.75);
    box-shadow: 0 0 9px rgba(239, 68, 68, 0.85);
    font-size: 8px;
}

.ameise-header-home,
.ameise-nav-link {
    gap: 0;
}

.ameise-header-home__label,
.ameise-nav-link__label {
    display: block;
    max-width: 0;
    overflow: hidden;
    margin-left: 0;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: 0.015em;
    line-height: 1;
    opacity: 0;
    text-overflow: clip;
    white-space: nowrap;
    transform: translateX(-4px);
    transition:
        max-width 0.24s ease,
        margin-left 0.24s ease,
        opacity 0.18s ease,
        transform 0.24s ease;
}

.ameise-header-home.is-active .ameise-header-home__label,
.ameise-nav-link.is-active .ameise-nav-link__label {
    max-width: 104px;
    margin-left: 7px;
    opacity: 1;
    transform: translateX(0);
}

.ameise-nav-monogram {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 19px;
    height: 19px;
    font-family: "JetBrains Mono", "IBM Plex Mono", monospace;
    font-size: 13px;
    font-weight: 900;
    line-height: 1;
    text-shadow: 0 0 8px rgb(var(--ameise-glow) / 0.45);
}

.ameise-nav-link--mail {
    --ameise-glow: 6 182 212;
}

.ameise-mail-status {
    position: absolute !important;
    z-index: 3 !important;
    top: 6px;
    right: 6px;
    width: 6px;
    height: 6px;
    margin: 0 !important;
    border: 1px solid #07122d;
    border-radius: 50%;
    background: #fb923c;
    box-shadow: 0 0 7px rgba(251, 146, 60, 0.9);
}

.ameise-orders-icon {
    --ameise-glow: 249 115 22;
    color: #fed7aa;
}

.ameise-products-icon {
    --ameise-glow: 14 165 233;
    color: #bae6fd;
}

.ameise-bank-icon {
    --ameise-glow: 59 130 246;
    color: #dbeafe;
}

.ameise-server-icon {
    --ameise-glow: 168 85 247;
    color: #eadcff;
}

.ameise-contacts-icon {
    --ameise-glow: 34 197 94;
    color: #d1fae5;
}

.ameise-settings-icon {
    --ameise-glow: 96 165 250;
    color: #dbeafe;
}

.ameise-header-icon {
    width: 38px;
    padding: 0;
}

.ameise-header-icon::before {
    opacity: 0.22;
}

@keyframes ameise-backlight {
    0%,
    100% {
        opacity: 0.72;
        transform: scale(0.93);
    }

    50% {
        opacity: 1;
        transform: scale(1.04);
    }
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

.working-leads__actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.working-leads__icon-button {
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

.working-leads__icon-button:hover:not(:disabled) {
    background: #ffffff;
    transform: translateY(-1px);
}

.working-leads__icon-button:disabled {
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
    display: grid;
    gap: 9px;
    padding: 14px;
    border: 1px solid rgba(72, 42, 24, 0.12);
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 8px 22px rgba(44, 24, 13, 0.06);
}

.lead-info-section--accent {
    background: linear-gradient(135deg, #fff 0%, #fff3ea 100%);
}

.lead-info-section--tools {
    border-color: rgba(21, 128, 61, 0.16);
    background: linear-gradient(135deg, #ffffff 0%, #f4fff6 100%);
}

.lead-info-section__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.lead-info-tools-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.lead-info-tools-row {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.lead-info-tools-grid :deep(.v-btn__content),
.lead-info-tools-row :deep(.v-btn__content) {
    overflow: hidden;
    text-overflow: ellipsis;
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

@media (max-width: 1180px) {
    .ameise-app-bar :deep(.v-toolbar__content) {
        padding: 0 6px;
    }

    .ameise-header-brand,
    .ameise-header-actions {
        gap: 3px;
    }

    .ameise-header-brand {
        padding-right: 6px;
    }

    .ameise-header-actions {
        padding-left: 6px;
    }

    .ameise-header-nav {
        gap: 2px;
        padding: 0 6px;
    }

    .ameise-header-control {
        min-width: 36px;
        height: 36px;
        padding: 0 8px;
    }

    .ameise-header-icon {
        width: 36px;
        padding: 0;
    }
}

@media (max-width: 720px) {
    .ameise-app-bar :deep(.v-toolbar__content) {
        padding: 0 4px;
    }

    .ameise-header-brand,
    .ameise-header-actions {
        gap: 2px;
    }

    .ameise-header-brand {
        padding-right: 4px;
    }

    .ameise-header-actions {
        padding-left: 4px;
    }

    .ameise-header-nav {
        padding: 0 4px;
    }

    .ameise-header-control {
        min-width: 34px;
        height: 34px;
        padding: 0 7px;
        border-radius: 10px;
    }

    .ameise-header-control::before {
        inset: -5px;
    }

    .ameise-header-icon {
        width: 34px;
        padding: 0;
    }

    .ameise-header-home.is-active .ameise-header-home__label,
    .ameise-nav-link.is-active .ameise-nav-link__label {
        max-width: 76px;
        margin-left: 5px;
    }
}

@media (max-width: 960px) {
    .lead-info-drawer {
        width: min(92vw, 520px) !important;
    }
}

@media (prefers-reduced-motion: reduce) {
    .ameise-header-control,
    .ameise-header-control::before,
    .ameise-header-control::after,
    .ameise-header-control :deep(.v-icon),
    .ameise-header-home__label,
    .ameise-nav-link__label {
        transition: none;
    }

    .ameise-header-control.is-active::before {
        animation: none;
    }
}
</style>
