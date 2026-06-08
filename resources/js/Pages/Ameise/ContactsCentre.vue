<script setup>
import VerwalterLayout from '@/Layouts/VerwalterLayout.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import { useHead } from '@vueuse/head'
import { route } from 'ziggy-js'
import { useDate } from 'vuetify'
import { Link } from '@inertiajs/vue3'

const MANAGER_PHONE = '79650160001'

defineOptions({
    layout: VerwalterLayout,
})

const date = useDate()

const emails = ref([])
const sendings = ref([])
const phoneCalls = ref([])
const leads = ref([])
const phoneCallsTotal = ref(0)
const leadsTotal = ref(0)
const loadingCalls = ref(false)
const loadingLeads = ref(false)
const savingLeadId = ref(null)
const savingLeadDrawer = ref(false)
const creatingEntityCallId = ref(null)
const dialingCallId = ref(null)
const syncingBeeline = ref(false)
const beelineSyncResult = ref(null)
const tab = ref('calls')
const callPage = ref(1)
const leadPage = ref(1)
const callsTableFrame = ref(null)
const callsTableHeight = ref(560)
const leadDrawerOpen = ref(false)
const selectedLead = ref(null)
const selectedLeadLoading = ref(false)

const snackbar = reactive({
    show: false,
    text: '',
    color: 'success',
})

const leadForm = reactive({
    title: '',
    status: 'open',
    description: '',
})

const callFilters = ref({
    search: '',
    direction: null,
    status: null,
    per_page: 100,
    hide_unresolved: true,
})

const leadFilters = ref({
    search: '',
    status: null,
    per_page: 25,
})

const callHeaders = [
    { title: 'Дата', key: 'started_at', sortable: false, width: 118 },
    { title: 'CRM', key: 'crm', sortable: false, width: 210 },
    { title: 'Номер', key: 'client', sortable: false, width: 150 },
    { title: 'Длительность', key: 'duration_seconds', sortable: false, width: 94, align: 'center' },
    { title: 'Лид', key: 'lead', sortable: false, width: 190 },
    { title: 'Тип', key: 'direction', sortable: false, width: 118 },
    { title: 'Статус', key: 'status', sortable: false, width: 128 },
    { title: 'Сотрудник', key: 'employee', sortable: false, width: 210 },
]

const leadHeaders = [
    { title: 'Активность', key: 'last_activity_at', sortable: false },
    { title: 'Лид', key: 'title', sortable: false },
    { title: 'Статус', key: 'status', sortable: false },
    { title: 'Клиент', key: 'client', sortable: false },
    { title: 'Звонков', key: 'phone_calls_count', sortable: false },
    { title: 'CRM', key: 'crm', sortable: false },
    { title: '', key: 'actions', sortable: false },
]

const headersEmails = [
    { title: 'date', key: 'created_at' },
    { title: 'email', key: 'address' },
    { title: 'кол-во отправок', key: 'sendings.length' },
]

const headersSendings = [
    { title: 'Date', key: 'created_at' },
    { title: 'Subject', key: 'subject' },
    { title: 'Email', key: 'email' },
]

const directionItems = [
    { title: 'Все', value: null },
    { title: 'Входящие', value: 'in' },
    { title: 'Исходящие', value: 'out' },
    { title: 'Пропущенные', value: 'missed' },
]

const callStatusItems = [
    { title: 'Все', value: null },
    { title: 'Успешные', value: 'success' },
    { title: 'Завершённые', value: 'completed' },
    { title: 'Пропущенные', value: 'missed' },
    { title: 'Клики с сайта', value: 'clicked' },
    { title: 'Отменены', value: 'cancelled' },
    { title: 'Занято', value: 'busy' },
]

const leadStatusItems = [
    { title: 'Открыт', value: 'open' },
    { title: 'В работе', value: 'in_progress' },
    { title: 'Выигран', value: 'won' },
    { title: 'Потерян', value: 'lost' },
    { title: 'Архив', value: 'archived' },
]

const leadStatusFilterItems = [
    { title: 'Все', value: null },
    ...leadStatusItems,
]

const openLeadsCount = computed(() => leads.value.filter((lead) => ['open', 'in_progress'].includes(lead.status)).length)
const missedCallsCount = computed(() => phoneCalls.value.filter((call) => call.status === 'missed' || call.direction === 'missed').length)
const callPageCount = computed(() => Math.max(Math.ceil(phoneCallsTotal.value / Math.max(callFilters.value.per_page, 1)), 1))
const selectedLeadCalls = computed(() => selectedLead.value?.phone_calls || [])

function notify(text, color = 'success') {
    snackbar.text = text
    snackbar.color = color
    snackbar.show = true
}

function indexEmails() {
    axios.get(route('emails.index')).then((response) => {
        emails.value = Array.isArray(response.data?.data) ? response.data.data : response.data
    }).catch(console.log)
}

function indexSendings() {
    axios.get(route('sendings.index')).then((response) => {
        sendings.value = Array.isArray(response.data?.data) ? response.data.data : response.data
    }).catch(console.log)
}

async function fetchPhoneCalls() {
    loadingCalls.value = true

    try {
        const { data } = await axios.get('/api/phone-calls', {
            params: {
                ...cleanParams(callFilters.value),
                page: callPage.value,
            },
        })

        phoneCalls.value = data.data || []
        phoneCallsTotal.value = data.total || 0
    } catch (error) {
        console.error(error)
        notify('Не удалось загрузить звонки.', 'error')
    } finally {
        loadingCalls.value = false
    }
}

async function syncBeelineCalls() {
    syncingBeeline.value = true
    beelineSyncResult.value = null

    try {
        const { data } = await axios.post('/api/phone-calls/sync-beeline', {
            period: 'today',
            limit: 100,
        })

        beelineSyncResult.value = data
        callPage.value = 1
        await Promise.all([fetchPhoneCalls(), fetchLeads()])
    } catch (error) {
        console.error(error)
        notify('Не удалось синхронизировать Билайн.', 'error')
    } finally {
        syncingBeeline.value = false
    }
}

async function fetchLeads() {
    loadingLeads.value = true

    try {
        const { data } = await axios.get('/api/leads', {
            params: {
                ...cleanParams(leadFilters.value),
                page: leadPage.value,
            },
        })

        leads.value = data.data || []
        leadsTotal.value = data.total || 0
    } catch (error) {
        console.error(error)
        notify('Не удалось загрузить лиды.', 'error')
    } finally {
        loadingLeads.value = false
    }
}

async function updateLeadStatus(lead, status) {
    savingLeadId.value = lead.id

    try {
        await axios.patch(`/api/leads/${lead.id}`, { status })
        await Promise.all([fetchLeads(), fetchPhoneCalls()])
    } catch (error) {
        console.error(error)
        notify('Не удалось обновить статус лида.', 'error')
    } finally {
        savingLeadId.value = null
    }
}

async function createEntity(call) {
    creatingEntityCallId.value = call.id

    try {
        const response = await axios.post(`/api/phone-calls/${call.id}/create-entity`)
        await Promise.all([fetchPhoneCalls(), fetchLeads()])
        notify('Entity создана.')

        return response.data?.data || response.data
    } catch (error) {
        console.error(error)
        notify('Не удалось создать Entity.', 'error')
        return null
    } finally {
        creatingEntityCallId.value = null
    }
}

async function openOrCreateEntity(call) {
    if (call.entity?.id) {
        goToEntity(call.entity.id)
        return
    }

    const updatedCall = await createEntity(call)
    const entityId = updatedCall?.entity?.id

    if (entityId) {
        goToEntity(entityId)
    }
}

function goToEntity(entityId) {
    if (!entityId || typeof window === 'undefined') {
        return
    }

    window.location.assign(entityUrl(entityId))
}

async function dialClient(call) {
    if (!call.client_phone) {
        notify('У звонка нет номера клиента.', 'error')
        return
    }

    dialingCallId.value = call.id

    try {
        await axios.post('/api/phone-calls/dial', {
            client_phone: call.client_phone,
            employee_phone: MANAGER_PHONE,
        })
        notify(`Билайн: звонок на ${formatPhone(call.client_phone)} запущен через менеджера +${MANAGER_PHONE}.`)
    } catch (error) {
        console.error(error)
        notify(error.response?.data?.message || 'Не удалось запустить звонок через Билайн.', 'error')
    } finally {
        dialingCallId.value = null
    }
}

async function openLeadDrawer(lead) {
    if (!lead?.id) {
        return
    }

    leadDrawerOpen.value = true
    selectedLeadLoading.value = true

    try {
        const { data } = await axios.get(`/api/leads/${lead.id}`)
        selectedLead.value = data.data || data
        fillLeadForm(selectedLead.value)
    } catch (error) {
        console.error(error)
        notify('Не удалось загрузить лид.', 'error')
    } finally {
        selectedLeadLoading.value = false
    }
}

function closeLeadDrawer() {
    leadDrawerOpen.value = false
}

function fillLeadForm(lead) {
    leadForm.title = lead?.title || ''
    leadForm.status = lead?.status || 'open'
    leadForm.description = lead?.description || ''
}

async function saveLeadDrawer() {
    if (!selectedLead.value?.id) {
        return
    }

    savingLeadDrawer.value = true

    try {
        const { data } = await axios.patch(`/api/leads/${selectedLead.value.id}`, {
            title: leadForm.title,
            status: leadForm.status,
            description: leadForm.description,
        })

        selectedLead.value = data.data || data
        fillLeadForm(selectedLead.value)
        await Promise.all([fetchLeads(), fetchPhoneCalls()])
        notify('Лид обновлён.')
    } catch (error) {
        console.error(error)
        notify('Не удалось сохранить лид.', 'error')
    } finally {
        savingLeadDrawer.value = false
    }
}

function applyCallFilters() {
    callPage.value = 1
    fetchPhoneCalls()
}

function applyLeadFilters() {
    leadPage.value = 1
    fetchLeads()
}

function onCallOptionsUpdate(options) {
    const nextPage = options.page || 1
    const nextPerPage = options.itemsPerPage > 0 ? options.itemsPerPage : callFilters.value.per_page
    const shouldFetch = nextPage !== callPage.value || nextPerPage !== callFilters.value.per_page

    callPage.value = nextPage
    callFilters.value.per_page = nextPerPage

    if (shouldFetch) {
        fetchPhoneCalls()
    }
}

function cleanParams(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== '')
    )
}

function daysSinceSending(createdAt) {
    const sendingDate = new Date(createdAt)
    const today = new Date()
    const diffTime = today - sendingDate
    return Math.floor(diffTime / (1000 * 60 * 60 * 24))
}

function parseDate(value) {
    if (!value) {
        return null
    }

    const parsed = new Date(value)

    return Number.isNaN(parsed.getTime()) ? null : parsed
}

function formatDateTime(value) {
    if (!value) {
        return '-'
    }

    return date.format(value, 'fullDateTime')
}

function formatCallTime(value) {
    const parsed = parseDate(value)

    if (!parsed) {
        return '--:--'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsed)
}

function formatCompactDate(value) {
    const parsed = parseDate(value)

    if (!parsed) {
        return '-'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: '2-digit',
    }).format(parsed)
}

function formatCompactYear(value) {
    const parsed = parseDate(value)

    if (!parsed) {
        return ''
    }

    const currentYear = new Date().getFullYear()
    const year = parsed.getFullYear()

    return year === currentYear ? '' : String(year)
}

function clockStyle(value) {
    const parsed = parseDate(value)

    if (!parsed) {
        return {
            '--hour-angle': '0deg',
            '--minute-angle': '0deg',
        }
    }

    const minutes = parsed.getMinutes()
    const hours = parsed.getHours() % 12

    return {
        '--hour-angle': `${(hours * 30) + (minutes * 0.5)}deg`,
        '--minute-angle': `${minutes * 6}deg`,
    }
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

function formatPhone(value) {
    if (!value) {
        return 'Номер не найден'
    }

    return `+${value}`
}

function employeeTitle(call) {
    return call.employee_extension || call.employee_phone || call.employee_user || call.group_name || '-'
}

function employeeSubtitle(call) {
    const values = [call.employee_user, call.group_name]
        .filter(Boolean)
        .filter((value, index, list) => list.indexOf(value) === index && value !== employeeTitle(call))

    return values.join(' · ')
}

function directionLabel(direction) {
    return {
        in: 'Входящий',
        out: 'Исходящий',
        missed: 'Пропущенный',
        unknown: 'Не определён',
    }[direction] || direction || '-'
}

function statusLabel(status) {
    return {
        success: 'Успешно',
        completed: 'Завершён',
        released: 'Завершён',
        ringing: 'Звонит',
        missed: 'Пропущен',
        cancelled: 'Отменён',
        busy: 'Занято',
        clicked: 'Клик',
        not_available: 'Недоступен',
        not_allowed: 'Запрещён',
        not_found: 'Не найден',
        open: 'Открыт',
        in_progress: 'В работе',
        won: 'Выигран',
        lost: 'Потерян',
        archived: 'Архив',
    }[status] || status || '-'
}

function statusColor(status) {
    return {
        success: 'green',
        completed: 'green',
        released: 'green',
        ringing: 'blue',
        missed: 'red',
        cancelled: 'orange',
        busy: 'orange',
        clicked: 'blue',
        open: 'blue',
        in_progress: 'amber',
        won: 'green',
        lost: 'red',
        archived: 'grey',
    }[status] || 'grey'
}

function entityUrl(entityId) {
    try {
        return route('Ameise.entity.show', entityId)
    } catch (error) {
        return `/Ameise/entity/${entityId}`
    }
}

function portalInfo(call) {
    const info = call.portal || null

    if (info?.provider_call_id || info?.raw_portal_statistics_row) {
        return info
    }

    if (call.provider_call_id) {
        return {
            provider_call_id: call.provider_call_id,
            event_type: call.event_type,
            recording_url: call.recording_url,
        }
    }

    return null
}

function portalInfoText(call) {
    const info = portalInfo(call)

    if (!info) {
        return ''
    }

    return JSON.stringify(info, null, 2)
}

function recalculateCallsTable() {
    nextTick(() => {
        const element = callsTableFrame.value

        if (!element || typeof window === 'undefined') {
            return
        }

        const rect = element.getBoundingClientRect()
        const nextHeight = Math.max(360, Math.floor(window.innerHeight - rect.top - 18))

        callsTableHeight.value = nextHeight
    })
}

onMounted(async () => {
    indexEmails()
    indexSendings()
    await nextTick()
    recalculateCallsTable()
    await Promise.all([fetchPhoneCalls(), fetchLeads()])

    if (typeof window !== 'undefined') {
        window.addEventListener('resize', handleResize)
    }
})

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize)
    }
})

let resizeTimer = null
function handleResize() {
    clearTimeout(resizeTimer)
    resizeTimer = setTimeout(recalculateCallsTable, 120)
}

useHead({
    title: 'Contacts: calls, leads, emails',
    meta: [
        {
            name: 'description',
            content: 'Contacts centre: telephony, leads, emails',
        },
    ],
})
</script>

<template>
    <v-container fluid class="contacts-centre pa-4">
        <v-row class="mb-4 contacts-centre__metrics-row">
            <v-col cols="12" md="4">
                <v-card rounded="xl" elevation="1" class="pa-4 h-100 contacts-centre__metric">
                    <div class="text-caption text-medium-emphasis">Звонки в журнале</div>
                    <div class="text-h4 font-weight-black">{{ phoneCallsTotal }}</div>
                    <div class="text-caption">Последние события Билайн АТС</div>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card rounded="xl" elevation="1" class="pa-4 h-100 contacts-centre__metric contacts-centre__metric--hot">
                    <div class="text-caption text-medium-emphasis">Открытые лиды</div>
                    <div class="text-h4 font-weight-black">{{ openLeadsCount }}</div>
                    <div class="text-caption">По текущей выборке</div>
                </v-card>
            </v-col>

            <v-col cols="12" md="4">
                <v-card rounded="xl" elevation="1" class="pa-4 h-100 contacts-centre__metric contacts-centre__metric--missed">
                    <div class="text-caption text-medium-emphasis">Пропущенные</div>
                    <div class="text-h4 font-weight-black">{{ missedCallsCount }}</div>
                    <div class="text-caption">Требуют контроля</div>
                </v-card>
            </v-col>
        </v-row>

        <v-card rounded="xl" elevation="1" class="contacts-centre__main-card">
            <v-tabs v-model="tab" color="#800000">
                <v-tab value="calls">Звонки</v-tab>
                <v-tab value="leads">Лиды</v-tab>
                <v-tab value="emails">Email активность</v-tab>
            </v-tabs>

            <v-divider />

            <v-window v-model="tab">
                <v-window-item value="calls">
                    <v-card-text class="contacts-centre__filters">
                        <v-row align="center" dense>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="callFilters.search"
                                    label="Поиск по номеру, сотруднику, Entity или Call ID"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    clearable
                                    prepend-inner-icon="mdi-magnify"
                                    @keyup.enter="applyCallFilters"
                                />
                            </v-col>

                            <v-col cols="12" md="2">
                                <v-select
                                    v-model="callFilters.direction"
                                    :items="directionItems"
                                    label="Тип"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    @update:model-value="applyCallFilters"
                                />
                            </v-col>

                            <v-col cols="12" md="2">
                                <v-select
                                    v-model="callFilters.status"
                                    :items="callStatusItems"
                                    label="Статус"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    @update:model-value="applyCallFilters"
                                />
                            </v-col>

                            <v-col cols="12" md="4" class="d-flex ga-2 justify-end flex-wrap">
                                <v-btn
                                    color="green"
                                    rounded="xl"
                                    variant="tonal"
                                    :loading="syncingBeeline"
                                    @click="syncBeelineCalls"
                                >
                                    Синхронизировать Билайн
                                </v-btn>
                                <v-btn color="#800000" rounded="xl" :loading="loadingCalls" @click="applyCallFilters">
                                    Обновить
                                </v-btn>
                            </v-col>
                        </v-row>

                        <v-alert
                            v-if="beelineSyncResult"
                            class="mt-4"
                            type="success"
                            variant="tonal"
                            density="compact"
                        >
                            Билайн: получено {{ beelineSyncResult.fetched }}, сохранено {{ beelineSyncResult.stored }}, пропущено {{ beelineSyncResult.skipped }}.
                        </v-alert>
                    </v-card-text>

                    <div ref="callsTableFrame" class="contacts-calls-shell">
                        <v-data-table-server
                            :headers="callHeaders"
                            :items="phoneCalls"
                            :items-length="phoneCallsTotal"
                            :items-per-page="callFilters.per_page"
                            :items-per-page-options="[callFilters.per_page]"
                            :loading="loadingCalls"
                            :page="callPage"
                            :height="callsTableHeight"
                            class="calls-table"
                            density="compact"
                            fixed-header
                            hover
                            item-value="id"
                            @update:options="onCallOptionsUpdate"
                        >
                            <template #item.started_at="{ item }">
                                <div class="call-date-cell">
                                    <div class="mini-clock" :style="clockStyle(item.started_at || item.created_at)">
                                        <span class="mini-clock__hour" />
                                        <span class="mini-clock__minute" />
                                        <span class="mini-clock__pin" />
                                    </div>
                                    <div class="call-date-cell__time">{{ formatCallTime(item.started_at || item.created_at) }}</div>
                                    <div class="call-date-cell__date">
                                        {{ formatCompactDate(item.started_at || item.created_at) }}
                                        <span v-if="formatCompactYear(item.started_at || item.created_at)">{{ formatCompactYear(item.started_at || item.created_at) }}</span>
                                    </div>
                                </div>
                            </template>

                            <template #item.crm="{ item }">
                                <button
                                    type="button"
                                    class="crm-link"
                                    :disabled="creatingEntityCallId === item.id"
                                    @click="openOrCreateEntity(item)"
                                >
                                    <span v-if="item.entity">{{ item.entity.name }}</span>
                                    <span v-else>{{ creatingEntityCallId === item.id ? 'Создаём...' : 'Создать Entity' }}</span>
                                </button>
                                <div v-if="item.unit" class="text-caption mt-1">
                                    <Link :href="route('web.unit.show', item.unit.id)">{{ item.unit.name }}</Link>
                                </div>
                            </template>

                            <template #item.client="{ item }">
                                <button
                                    type="button"
                                    class="phone-link"
                                    :disabled="!item.client_phone || dialingCallId === item.id"
                                    @click="dialClient(item)"
                                >
                                    <v-icon icon="mdi-phone-outgoing" size="14" />
                                    <span>{{ formatPhone(item.client_phone) }}</span>
                                </button>
                                <div v-if="!item.client_phone" class="text-caption text-error mt-1">
                                    Beeline не передал внешний номер
                                </div>
                            </template>

                            <template #item.duration_seconds="{ item }">
                                <span class="call-duration">{{ formatSeconds(item.duration_seconds) }}</span>
                            </template>

                            <template #item.lead="{ item }">
                                <button
                                    v-if="item.lead"
                                    type="button"
                                    class="lead-link"
                                    @click="openLeadDrawer(item.lead)"
                                >
                                    <span class="lead-link__title">#{{ item.lead.id }} {{ item.lead.title || 'Лид' }}</span>
                                    <v-chip size="x-small" :color="statusColor(item.lead.status)" variant="tonal">
                                        {{ statusLabel(item.lead.status) }}
                                    </v-chip>
                                </button>
                                <span v-else>-</span>
                            </template>

                            <template #item.direction="{ item }">
                                <v-chip size="small" variant="tonal">{{ directionLabel(item.direction) }}</v-chip>
                            </template>

                            <template #item.status="{ item }">
                                <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
                                    {{ statusLabel(item.status) }}
                                </v-chip>
                            </template>

                            <template #item.employee="{ item }">
                                <div class="employee-cell">
                                    <div class="employee-cell__text">
                                        <div>{{ employeeTitle(item) }}</div>
                                        <div class="text-caption text-medium-emphasis">{{ employeeSubtitle(item) }}</div>
                                    </div>

                                    <v-menu v-if="portalInfo(item)" location="bottom end" max-width="420">
                                        <template #activator="{ props: menuProps }">
                                            <v-btn
                                                v-bind="menuProps"
                                                icon="mdi-cloud-search-outline"
                                                size="x-small"
                                                variant="text"
                                                color="#800000"
                                            />
                                        </template>
                                        <v-card rounded="lg" class="portal-popover">
                                            <v-card-title class="text-subtitle-2">Portal</v-card-title>
                                            <v-card-text>
                                                <pre>{{ portalInfoText(item) }}</pre>
                                            </v-card-text>
                                        </v-card>
                                    </v-menu>
                                </div>
                            </template>
                        </v-data-table-server>

                        <transition name="lead-panel">
                            <aside v-if="leadDrawerOpen" class="lead-side-panel">
                                <div class="lead-side-panel__head">
                                    <div>
                                        <div class="text-caption text-medium-emphasis">Lead workspace</div>
                                        <h2>{{ selectedLead?.title || 'Лид' }}</h2>
                                    </div>
                                    <v-btn icon="mdi-close" size="small" variant="text" @click="closeLeadDrawer" />
                                </div>

                                <v-progress-linear v-if="selectedLeadLoading" indeterminate color="#800000" class="mb-3" />

                                <div v-if="selectedLead" class="lead-side-panel__body">
                                    <section class="lead-section">
                                        <h3>Update</h3>
                                        <v-text-field
                                            v-model="leadForm.title"
                                            label="Название"
                                            density="compact"
                                            variant="outlined"
                                        />
                                        <v-select
                                            v-model="leadForm.status"
                                            :items="leadStatusItems"
                                            label="Статус"
                                            density="compact"
                                            variant="outlined"
                                        />
                                        <v-textarea
                                            v-model="leadForm.description"
                                            label="Описание / работа с лидом"
                                            rows="5"
                                            variant="outlined"
                                        />
                                        <v-btn
                                            color="#800000"
                                            rounded="lg"
                                            :loading="savingLeadDrawer"
                                            @click="saveLeadDrawer"
                                        >
                                            Сохранить
                                        </v-btn>
                                    </section>

                                    <section class="lead-section">
                                        <h3>Данные</h3>
                                        <dl class="lead-data-grid">
                                            <dt>ID</dt><dd>#{{ selectedLead.id }}</dd>
                                            <dt>Источник</dt><dd>{{ selectedLead.source || '-' }}</dd>
                                            <dt>Номер</dt><dd>{{ formatPhone(selectedLead.client_phone || selectedLead.telephone?.number) }}</dd>
                                            <dt>Активность</dt><dd>{{ formatDateTime(selectedLead.last_activity_at || selectedLead.created_at) }}</dd>
                                            <dt>Закрыт</dt><dd>{{ selectedLead.closed_at ? formatDateTime(selectedLead.closed_at) : '-' }}</dd>
                                            <dt>Ответственный</dt><dd>{{ selectedLead.assigned_user?.name || '-' }}</dd>
                                        </dl>
                                    </section>

                                    <section class="lead-section">
                                        <h3>Связи</h3>
                                        <div class="lead-relation">
                                            <strong>Entity</strong>
                                            <button
                                                v-if="selectedLead.entity"
                                                type="button"
                                                class="crm-link"
                                                @click="goToEntity(selectedLead.entity.id)"
                                            >
                                                {{ selectedLead.entity.name }}
                                            </button>
                                            <span v-else>-</span>
                                        </div>
                                        <div class="lead-relation">
                                            <strong>Unit</strong>
                                            <Link v-if="selectedLead.unit" :href="route('web.unit.show', selectedLead.unit.id)">
                                                {{ selectedLead.unit.name }}
                                            </Link>
                                            <span v-else>-</span>
                                        </div>
                                        <div class="lead-relation">
                                            <strong>Telephone</strong>
                                            <span>{{ selectedLead.telephone?.number ? formatPhone(selectedLead.telephone.number) : '-' }}</span>
                                        </div>
                                    </section>

                                    <section class="lead-section">
                                        <h3>Связанные звонки ({{ selectedLeadCalls.length }})</h3>
                                        <div class="lead-calls-list">
                                            <div v-for="call in selectedLeadCalls" :key="call.id" class="lead-call-card">
                                                <div class="lead-call-card__top">
                                                    <strong>{{ formatCallTime(call.started_at || call.created_at) }}</strong>
                                                    <v-chip size="x-small" :color="statusColor(call.status)" variant="tonal">
                                                        {{ statusLabel(call.status) }}
                                                    </v-chip>
                                                </div>
                                                <div>{{ formatCompactDate(call.started_at || call.created_at) }} · {{ directionLabel(call.direction) }} · {{ formatSeconds(call.duration_seconds) }}</div>
                                                <div class="text-caption text-medium-emphasis">{{ formatPhone(call.client_phone) }}</div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </aside>
                        </transition>
                    </div>
                </v-window-item>

                <v-window-item value="leads">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="6">
                                <v-text-field
                                    v-model="leadFilters.search"
                                    label="Поиск по лиду, номеру или Entity"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    clearable
                                    prepend-inner-icon="mdi-magnify"
                                    @keyup.enter="applyLeadFilters"
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="leadFilters.status"
                                    :items="leadStatusFilterItems"
                                    label="Статус"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    @update:model-value="applyLeadFilters"
                                />
                            </v-col>

                            <v-col cols="12" md="3" class="d-flex ga-2 justify-end">
                                <v-btn color="#800000" rounded="xl" :loading="loadingLeads" @click="applyLeadFilters">
                                    Обновить
                                </v-btn>
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-data-table
                        :headers="leadHeaders"
                        :items="leads"
                        :loading="loadingLeads"
                        density="compact"
                        hover
                    >
                        <template #item.last_activity_at="{ item }">
                            <div class="text-caption">{{ formatDateTime(item.last_activity_at || item.created_at) }}</div>
                            <div class="text-caption text-medium-emphasis">{{ item.source }}</div>
                        </template>

                        <template #item.title="{ item }">
                            <button type="button" class="lead-table-title" @click="openLeadDrawer(item)">
                                {{ item.title }}
                            </button>
                            <div class="text-caption text-medium-emphasis">{{ item.description }}</div>
                        </template>

                        <template #item.status="{ item }">
                            <v-chip size="small" :color="statusColor(item.status)" variant="tonal">
                                {{ statusLabel(item.status) }}
                            </v-chip>
                        </template>

                        <template #item.client="{ item }">
                            {{ formatPhone(item.client_phone || item.telephone?.number) }}
                        </template>

                        <template #item.crm="{ item }">
                            <button
                                v-if="item.entity"
                                type="button"
                                class="crm-link"
                                @click="goToEntity(item.entity.id)"
                            >
                                {{ item.entity.name }}
                            </button>
                            <div v-if="item.unit">
                                <Link :href="route('web.unit.show', item.unit.id)">{{ item.unit.name }}</Link>
                            </div>
                            <span v-if="!item.entity && !item.unit">-</span>
                        </template>

                        <template #item.actions="{ item }">
                            <div class="d-flex ga-2 justify-end">
                                <v-btn
                                    v-if="item.status === 'open'"
                                    size="small"
                                    variant="tonal"
                                    color="amber"
                                    :loading="savingLeadId === item.id"
                                    @click="updateLeadStatus(item, 'in_progress')"
                                >
                                    В работу
                                </v-btn>
                                <v-btn
                                    v-if="['open', 'in_progress'].includes(item.status)"
                                    size="small"
                                    variant="tonal"
                                    color="green"
                                    :loading="savingLeadId === item.id"
                                    @click="updateLeadStatus(item, 'won')"
                                >
                                    Закрыть
                                </v-btn>
                                <v-btn
                                    v-if="['open', 'in_progress'].includes(item.status)"
                                    size="small"
                                    variant="text"
                                    color="red"
                                    :loading="savingLeadId === item.id"
                                    @click="updateLeadStatus(item, 'lost')"
                                >
                                    Потерян
                                </v-btn>
                            </div>
                        </template>
                    </v-data-table>
                </v-window-item>

                <v-window-item value="emails">
                    <v-row class="pa-4">
                        <v-col cols="12" lg="4">
                            <v-data-table
                                :items="emails"
                                :headers="headersEmails"
                                density="compact"
                                hover
                            >
                                <template #item.created_at="{ item }">
                                    <span class="text-xs">{{ date.format(item.created_at, 'fullDate') }}</span>
                                </template>
                            </v-data-table>
                        </v-col>

                        <v-col cols="12" lg="8">
                            <v-data-table
                                :items="sendings"
                                :headers="headersSendings"
                                items-per-page="36"
                                density="compact"
                                hover
                            >
                                <template #item.created_at="{ item }">
                                    <v-row>
                                        <v-col cols="9">
                                            <span>{{ date.format(item.created_at, 'fullDateTime') }}</span>
                                        </v-col>
                                        <v-col cols="3">
                                            <span>{{ daysSinceSending(item.created_at) }}</span>
                                        </v-col>
                                    </v-row>
                                </template>
                                <template #item.subject="{ item }">
                                    <span class="text-xs">{{ item.subject }}</span>
                                </template>
                                <template #item.email="{ item }">
                                    <div>{{ item.email?.address }}</div>
                                    <div>
                                        <Link
                                            v-for="unit in item.email?.units || []"
                                            :key="unit.id"
                                            :href="route('web.unit.show', unit.id)"
                                        >
                                            {{ unit.name }}
                                        </Link>
                                    </div>
                                </template>
                            </v-data-table>
                        </v-col>
                    </v-row>
                </v-window-item>
            </v-window>
        </v-card>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500">
            {{ snackbar.text }}
        </v-snackbar>
    </v-container>
</template>

<style scoped>
.contacts-centre {
    min-height: 100vh;
    background: linear-gradient(135deg, #fffaf4 0%, #f7f2eb 48%, #fff 100%);
}

.contacts-centre__metric {
    border: 1px solid rgba(128, 0, 0, 0.08);
    background: #ffffff;
}

.contacts-centre__metric--hot {
    background: linear-gradient(135deg, #fff7e6, #ffffff);
}

.contacts-centre__metric--missed {
    background: linear-gradient(135deg, #fff1f1, #ffffff);
}

.contacts-centre__main-card {
    overflow: hidden;
}

.contacts-centre__filters {
    padding-bottom: 12px;
}

.contacts-calls-shell {
    position: relative;
    padding: 0 16px 16px;
    overflow: hidden;
}

.calls-table {
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 18px;
    overflow: hidden;
}

.calls-table :deep(tbody tr) {
    height: 54px;
}

.calls-table :deep(th) {
    white-space: nowrap;
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.02em;
    color: #5b2b1b;
    background: #fff8ef !important;
}

.calls-table :deep(td) {
    vertical-align: middle;
}

.call-date-cell {
    display: grid;
    justify-items: center;
    gap: 1px;
    min-width: 88px;
}

.call-date-cell__time {
    padding: 1px 6px;
    border-radius: 6px;
    color: #ff2f6d;
    background: #090909;
    font-family: 'Courier New', monospace;
    font-size: 1rem;
    font-weight: 900;
    letter-spacing: 0.03em;
    line-height: 1.2;
}

.call-date-cell__date {
    color: #735244;
    font-size: 0.68rem;
    font-weight: 800;
    line-height: 1;
}

.mini-clock {
    position: relative;
    width: 24px;
    height: 24px;
    border: 2px solid #e9edf7;
    border-radius: 50%;
    background: #263043;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
}

.mini-clock::before {
    content: '';
    position: absolute;
    inset: 3px;
    border-radius: 50%;
    background:
        linear-gradient(#fff 0 0) 50% 0 / 2px 4px no-repeat,
        linear-gradient(#fff 0 0) 50% 100% / 2px 4px no-repeat,
        linear-gradient(#fff 0 0) 0 50% / 4px 2px no-repeat,
        linear-gradient(#fff 0 0) 100% 50% / 4px 2px no-repeat;
    opacity: 0.9;
}

.mini-clock__hour,
.mini-clock__minute {
    position: absolute;
    left: 50%;
    bottom: 50%;
    width: 2px;
    border-radius: 99px;
    background: #fff;
    transform-origin: bottom center;
}

.mini-clock__hour {
    height: 7px;
    transform: translateX(-50%) rotate(var(--hour-angle));
}

.mini-clock__minute {
    height: 9px;
    background: #f8fafc;
    transform: translateX(-50%) rotate(var(--minute-angle));
}

.mini-clock__pin {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #ef233c;
    transform: translate(-50%, -50%);
}

.crm-link,
.phone-link,
.lead-link,
.lead-table-title {
    border: 0;
    background: transparent;
    cursor: pointer;
    font: inherit;
    text-align: left;
}

.crm-link {
    padding: 0;
    color: #800000;
    font-weight: 900;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.crm-link:disabled,
.phone-link:disabled {
    cursor: default;
    opacity: 0.55;
}

.phone-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border: 1px solid rgba(22, 101, 52, 0.18);
    border-radius: 999px;
    color: #166534;
    background: #f1fbf3;
    font-size: 0.78rem;
    font-weight: 900;
    white-space: nowrap;
}

.call-duration {
    color: #3f281d;
    font-size: 0.78rem;
    font-weight: 900;
}

.lead-link {
    display: grid;
    gap: 3px;
    max-width: 170px;
    padding: 0;
}

.lead-link__title {
    overflow: hidden;
    color: #6b2d09;
    font-size: 0.78rem;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lead-table-title {
    padding: 0;
    color: #6b2d09;
    font-weight: 900;
}

.employee-cell {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.employee-cell__text {
    min-width: 0;
}

.portal-popover pre {
    max-height: 260px;
    overflow: auto;
    margin: 0;
    font-size: 0.72rem;
    white-space: pre-wrap;
}

.lead-side-panel {
    position: absolute;
    top: 0;
    right: 16px;
    z-index: 8;
    width: min(40%, 620px);
    height: calc(100% - 16px);
    padding: 16px;
    border: 1px solid rgba(128, 0, 0, 0.14);
    border-radius: 22px;
    background: rgba(255, 253, 248, 0.98);
    box-shadow: -22px 0 54px rgba(58, 28, 12, 0.16);
    backdrop-filter: blur(12px);
}

.lead-panel-enter-active,
.lead-panel-leave-active {
    transition: transform 0.24s ease, opacity 0.24s ease;
}

.lead-panel-enter-from,
.lead-panel-leave-to {
    opacity: 0;
    transform: translateX(24px);
}

.lead-side-panel__head {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.lead-side-panel__head h2 {
    margin: 0;
    color: #35160d;
    font-size: 1.05rem;
    font-weight: 950;
    line-height: 1.15;
}

.lead-side-panel__body {
    display: grid;
    gap: 14px;
    max-height: calc(100% - 56px);
    overflow-y: auto;
    padding-right: 4px;
}

.lead-section {
    display: grid;
    gap: 8px;
    padding: 12px;
    border: 1px solid rgba(128, 0, 0, 0.08);
    border-radius: 16px;
    background: #fffaf2;
}

.lead-section h3 {
    margin: 0;
    color: #35160d;
    font-size: 0.86rem;
    font-weight: 950;
}

.lead-data-grid {
    display: grid;
    grid-template-columns: max-content minmax(0, 1fr);
    gap: 5px 10px;
    margin: 0;
    font-size: 0.78rem;
}

.lead-data-grid dt {
    color: #7c5b4b;
    font-weight: 900;
}

.lead-data-grid dd {
    min-width: 0;
    margin: 0;
    color: #2f160d;
}

.lead-relation {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 8px;
    font-size: 0.8rem;
}

.lead-calls-list {
    display: grid;
    gap: 8px;
}

.lead-call-card {
    padding: 9px;
    border-radius: 12px;
    background: #ffffff;
    font-size: 0.78rem;
}

.lead-call-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 2px;
}

@media (max-width: 960px) {
    .lead-side-panel {
        right: 8px;
        width: calc(100% - 16px);
    }
}
</style>
