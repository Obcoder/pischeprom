<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { useHead } from "@vueuse/head";
import { route } from "ziggy-js";
import { useDate } from "vuetify";
import { Link } from "@inertiajs/vue3";

defineOptions({
    layout: VerwalterLayout,
});

const date = useDate();

const emails = ref([]);
const sendings = ref([]);
const phoneCalls = ref([]);
const leads = ref([]);
const phoneCallsTotal = ref(0);
const leadsTotal = ref(0);
const loadingCalls = ref(false);
const loadingLeads = ref(false);
const savingLeadId = ref(null);
const creatingEntityCallId = ref(null);
const tab = ref("calls");

const callFilters = ref({
    search: "",
    direction: null,
    status: null,
    per_page: 25,
});

const leadFilters = ref({
    search: "",
    status: null,
    per_page: 25,
});

const callHeaders = [
    { title: "Дата", key: "started_at", sortable: false },
    { title: "Тип", key: "direction", sortable: false },
    { title: "Статус", key: "status", sortable: false },
    { title: "Номер клиента", key: "client", sortable: false },
    { title: "Сотрудник", key: "employee", sortable: false },
    { title: "Длительность", key: "duration_seconds", sortable: false },
    { title: "Лид", key: "lead", sortable: false },
    { title: "CRM", key: "crm", sortable: false },
    { title: "", key: "actions", sortable: false },
];

const leadHeaders = [
    { title: "Активность", key: "last_activity_at", sortable: false },
    { title: "Лид", key: "title", sortable: false },
    { title: "Статус", key: "status", sortable: false },
    { title: "Клиент", key: "client", sortable: false },
    { title: "Звонков", key: "phone_calls_count", sortable: false },
    { title: "CRM", key: "crm", sortable: false },
    { title: "", key: "actions", sortable: false },
];

const headersEmails = [
    { title: "date", key: "created_at" },
    { title: "email", key: "address" },
    { title: "кол-во отправок", key: "sendings.length" },
];

const headersSendings = [
    { title: "Date", key: "created_at" },
    { title: "Subject", key: "subject" },
    { title: "Email", key: "email" },
];

const directionItems = [
    { title: "Все", value: null },
    { title: "Входящие", value: "in" },
    { title: "Исходящие", value: "out" },
    { title: "Пропущенные", value: "missed" },
];

const callStatusItems = [
    { title: "Все", value: null },
    { title: "Успешные", value: "success" },
    { title: "Завершённые", value: "completed" },
    { title: "Пропущенные", value: "missed" },
    { title: "Клики с сайта", value: "clicked" },
    { title: "Отменены", value: "cancelled" },
    { title: "Занято", value: "busy" },
];

const leadStatusItems = [
    { title: "Все", value: null },
    { title: "Открыт", value: "open" },
    { title: "В работе", value: "in_progress" },
    { title: "Выигран", value: "won" },
    { title: "Потерян", value: "lost" },
    { title: "Архив", value: "archived" },
];

const openLeadsCount = computed(() => leads.value.filter((lead) => ["open", "in_progress"].includes(lead.status)).length);
const missedCallsCount = computed(() => phoneCalls.value.filter((call) => call.status === "missed" || call.direction === "missed").length);

function indexEmails() {
    axios.get(route("emails.index")).then((response) => {
        emails.value = Array.isArray(response.data?.data) ? response.data.data : response.data;
    }).catch(console.log);
}

function indexSendings() {
    axios.get(route("sendings.index")).then((response) => {
        sendings.value = Array.isArray(response.data?.data) ? response.data.data : response.data;
    }).catch(console.log);
}

async function fetchPhoneCalls() {
    loadingCalls.value = true;

    try {
        const { data } = await axios.get("/api/phone-calls", {
            params: cleanParams(callFilters.value),
        });

        phoneCalls.value = data.data || [];
        phoneCallsTotal.value = data.total || 0;
    } catch (error) {
        console.error(error);
    } finally {
        loadingCalls.value = false;
    }
}

async function fetchLeads() {
    loadingLeads.value = true;

    try {
        const { data } = await axios.get("/api/leads", {
            params: cleanParams(leadFilters.value),
        });

        leads.value = data.data || [];
        leadsTotal.value = data.total || 0;
    } catch (error) {
        console.error(error);
    } finally {
        loadingLeads.value = false;
    }
}

async function updateLeadStatus(lead, status) {
    savingLeadId.value = lead.id;

    try {
        await axios.patch(`/api/leads/${lead.id}`, { status });
        await Promise.all([fetchLeads(), fetchPhoneCalls()]);
    } catch (error) {
        console.error(error);
    } finally {
        savingLeadId.value = null;
    }
}

async function createEntity(call) {
    creatingEntityCallId.value = call.id;

    try {
        await axios.post(`/api/phone-calls/${call.id}/create-entity`);
        await Promise.all([fetchPhoneCalls(), fetchLeads()]);
    } catch (error) {
        console.error(error);
    } finally {
        creatingEntityCallId.value = null;
    }
}

function cleanParams(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== "")
    );
}

function daysSinceSending(createdAt) {
    const sendingDate = new Date(createdAt);
    const today = new Date();
    const diffTime = today - sendingDate;
    return Math.floor(diffTime / (1000 * 60 * 60 * 24));
}

function formatDateTime(value) {
    if (!value) {
        return "-";
    }

    return date.format(value, "fullDateTime");
}

function formatSeconds(value) {
    if (value === null || value === undefined) {
        return "-";
    }

    const minutes = Math.floor(Number(value) / 60);
    const seconds = Number(value) % 60;

    return `${minutes}:${String(seconds).padStart(2, "0")}`;
}

function formatPhone(value) {
    if (!value) {
        return "Номер не найден";
    }

    return `+${value}`;
}

function employeeTitle(call) {
    return call.employee_extension || call.employee_phone || call.employee_user || call.group_name || "-";
}

function employeeSubtitle(call) {
    const values = [call.employee_user, call.group_name]
        .filter(Boolean)
        .filter((value, index, list) => list.indexOf(value) === index && value !== employeeTitle(call));

    return values.join(" · ");
}

function directionLabel(direction) {
    return {
        in: "Входящий",
        out: "Исходящий",
        missed: "Пропущенный",
        unknown: "Не определён",
    }[direction] || direction || "-";
}

function statusLabel(status) {
    return {
        success: "Успешно",
        completed: "Завершён",
        released: "Завершён",
        ringing: "Звонит",
        missed: "Пропущен",
        cancelled: "Отменён",
        busy: "Занято",
        clicked: "Клик",
        not_available: "Недоступен",
        not_allowed: "Запрещён",
        not_found: "Не найден",
        open: "Открыт",
        in_progress: "В работе",
        won: "Выигран",
        lost: "Потерян",
        archived: "Архив",
    }[status] || status || "-";
}

function statusColor(status) {
    return {
        success: "green",
        completed: "green",
        released: "green",
        ringing: "blue",
        missed: "red",
        cancelled: "orange",
        busy: "orange",
        clicked: "blue",
        open: "blue",
        in_progress: "amber",
        won: "green",
        lost: "red",
        archived: "grey",
    }[status] || "grey";
}

onMounted(() => {
    indexEmails();
    indexSendings();
    fetchPhoneCalls();
    fetchLeads();
});

useHead({
    title: "Contacts: calls, leads, emails",
    meta: [
        {
            name: "description",
            content: "Contacts centre: telephony, leads, emails",
        },
    ],
});
</script>

<template>
    <v-container fluid class="contacts-centre pa-4">
        <v-row class="mb-4">
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

        <v-card rounded="xl" elevation="1" class="mb-5">
            <v-tabs v-model="tab" color="#800000">
                <v-tab value="calls">Звонки</v-tab>
                <v-tab value="leads">Лиды</v-tab>
                <v-tab value="emails">Email активность</v-tab>
            </v-tabs>

            <v-divider />

            <v-window v-model="tab">
                <v-window-item value="calls">
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="5">
                                <v-text-field
                                    v-model="callFilters.search"
                                    label="Поиск по номеру, сотруднику, Entity или Call ID"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                    clearable
                                    prepend-inner-icon="mdi-magnify"
                                    @keyup.enter="fetchPhoneCalls"
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
                                />
                            </v-col>

                            <v-col cols="12" md="3" class="d-flex ga-2 justify-end">
                                <v-btn color="#800000" rounded="xl" :loading="loadingCalls" @click="fetchPhoneCalls">
                                    Обновить
                                </v-btn>
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-data-table
                        :headers="callHeaders"
                        :items="phoneCalls"
                        :loading="loadingCalls"
                        density="compact"
                        hover
                    >
                        <template #item.started_at="{ item }">
                            <div class="text-caption">{{ formatDateTime(item.started_at || item.created_at) }}</div>
                            <div class="text-caption text-medium-emphasis">{{ item.provider_call_id }}</div>
                        </template>

                        <template #item.direction="{ item }">
                            <v-chip size="small" variant="tonal">{{ directionLabel(item.direction) }}</v-chip>
                        </template>

                        <template #item.status="{ item }">
                            <v-chip size="small" variant="tonal" :color="statusColor(item.status)">
                                {{ statusLabel(item.status) }}
                            </v-chip>
                        </template>

                        <template #item.client="{ item }">
                            <div class="font-weight-bold">{{ formatPhone(item.client_phone) }}</div>
                            <div v-if="item.entity" class="text-caption text-medium-emphasis">{{ item.entity.name }}</div>
                            <div v-else-if="!item.client_phone" class="text-caption text-error">Beeline не передал внешний номер в этом событии</div>
                        </template>

                        <template #item.employee="{ item }">
                            <div>{{ employeeTitle(item) }}</div>
                            <div class="text-caption text-medium-emphasis">{{ employeeSubtitle(item) }}</div>
                        </template>

                        <template #item.duration_seconds="{ item }">
                            {{ formatSeconds(item.duration_seconds) }}
                        </template>

                        <template #item.lead="{ item }">
                            <v-chip v-if="item.lead" size="small" :color="statusColor(item.lead.status)" variant="tonal">
                                #{{ item.lead.id }} {{ statusLabel(item.lead.status) }}
                            </v-chip>
                            <span v-else>-</span>
                        </template>

                        <template #item.crm="{ item }">
                            <div v-if="item.entity" class="font-weight-medium">{{ item.entity.name }}</div>
                            <div v-if="item.unit">
                                <Link :href="route('web.unit.show', item.unit.id)">{{ item.unit.name }}</Link>
                            </div>
                            <span v-if="!item.entity && !item.unit">-</span>
                        </template>

                        <template #item.actions="{ item }">
                            <v-btn
                                v-if="!item.entity"
                                size="small"
                                variant="tonal"
                                color="#800000"
                                :loading="creatingEntityCallId === item.id"
                                @click="createEntity(item)"
                            >
                                Создать Entity
                            </v-btn>
                        </template>
                    </v-data-table>
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
                                    @keyup.enter="fetchLeads"
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="leadFilters.status"
                                    :items="leadStatusItems"
                                    label="Статус"
                                    density="compact"
                                    variant="outlined"
                                    hide-details
                                />
                            </v-col>

                            <v-col cols="12" md="3" class="d-flex ga-2 justify-end">
                                <v-btn color="#800000" rounded="xl" :loading="loadingLeads" @click="fetchLeads">
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
                            <div class="font-weight-bold">{{ item.title }}</div>
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
                            <div v-if="item.entity" class="font-weight-medium">{{ item.entity.name }}</div>
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
    </v-container>
</template>

<style scoped>
.contacts-centre {
    background: linear-gradient(135deg, #fffaf4 0%, #f7f2eb 48%, #fff 100%);
    min-height: 100vh;
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
</style>
