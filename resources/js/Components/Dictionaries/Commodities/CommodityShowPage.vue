<script setup>
import { computed, onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { logo } from '@/Pages/Helpers/consts.js'
import { useCommodities } from '@/Composables/useCommodities.js'
import CommodityMediaManager from './CommodityMediaManager.vue'

const props = defineProps({
    commodityId: {
        type: Number,
        required: true,
    },
})

const {
    commodity,
    history,
    stats,
    loadingCommodities,
    savingCommodity,

    showCommodity,
    updateCommodity,
} = useCommodities()

const editName = ref('')
const nameEditing = ref(false)

const historyHeaders = [
    {
        title: 'Дата',
        key: 'check_date',
    },
    {
        title: 'Поставщик',
        key: 'entity_name',
    },
    {
        title: 'Кол-во',
        key: 'quantity',
    },
    {
        title: 'Ед.',
        key: 'measure_name',
    },
    {
        title: 'Цена',
        key: 'price',
    },
    {
        title: 'Сумма',
        key: 'total_price',
    },
    {
        title: 'Check',
        key: 'check_id',
    },
]

const priceHistoryAsc = computed(() => {
    return [...history.value]
        .filter((item) => item.price !== null && item.price !== undefined)
        .sort((a, b) => new Date(a.check_date) - new Date(b.check_date))
})

const maxPrice = computed(() => {
    const values = priceHistoryAsc.value.map((item) => Number(item.price || 0))

    return Math.max(...values, 1)
})

const latestPrice = computed(() => {
    return priceHistoryAsc.value.at(-1)?.price || null
})

const minPrice = computed(() => {
    const values = priceHistoryAsc.value.map((item) => Number(item.price || 0))

    if (!values.length) {
        return null
    }

    return Math.min(...values)
})

const maxMonthlyTotal = computed(() => {
    const values = stats.value.monthly.map((item) => Number(item.total || 0))

    return Math.max(...values, 1)
})

function money(value) {
    const number = Number(value || 0)

    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number)
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Intl.DateTimeFormat('ru-RU', {
        dateStyle: 'medium',
    }).format(new Date(value))
}

async function refresh() {
    const response = await showCommodity(props.commodityId)

    editName.value = response.data?.name || ''
}

function startEditName() {
    editName.value = commodity.value?.name || ''
    nameEditing.value = true
}

async function saveName() {
    await updateCommodity(props.commodityId, {
        name: editName.value,
    })

    nameEditing.value = false

    await refresh()
}

onMounted(refresh)
</script>

<template>
    <v-container fluid>
        <v-row align="center" class="mb-4">
            <v-col>
                <Link
                    :href="route('Ameise.commodities')"
                    class="text-teal-lighten-2 text-caption"
                >
                    ← Commodities
                </Link>

                <div v-if="commodity" class="d-flex align-center ga-3 mt-2">
                    <v-avatar size="72" rounded="lg">
                        <v-img :src="commodity.ava_url || logo" cover />
                    </v-avatar>

                    <div class="flex-grow-1">
                        <div v-if="!nameEditing" class="d-flex align-center ga-2">
                            <h1 class="text-h5 mb-0">
                                {{ commodity.name }}
                            </h1>

                            <v-btn
                                icon="mdi-pencil"
                                variant="text"
                                density="compact"
                                @click="startEditName"
                            />
                        </div>

                        <div v-else class="d-flex align-center ga-2">
                            <v-text-field
                                v-model="editName"
                                variant="outlined"
                                density="compact"
                                hide-details
                                style="max-width: 520px"
                            />

                            <v-btn
                                icon="mdi-check"
                                color="success"
                                variant="tonal"
                                density="compact"
                                :loading="savingCommodity"
                                @click="saveName"
                            />

                            <v-btn
                                icon="mdi-close"
                                variant="text"
                                density="compact"
                                @click="nameEditing = false"
                            />
                        </div>

                        <div class="text-caption text-grey">
                            ID: {{ commodity.id }}
                            · Checks: {{ commodity.checks_count }}
                            · Media: {{ commodity.media_count }}
                        </div>
                    </div>
                </div>
            </v-col>

            <v-col cols="auto">
                <v-btn
                    icon="mdi-refresh"
                    variant="tonal"
                    :loading="loadingCommodities"
                    @click="refresh"
                />
            </v-col>
        </v-row>

        <v-row v-if="commodity">
            <v-col cols="12" lg="5">
                <CommodityMediaManager
                    :commodity-id="commodity.id"
                    :items="commodity.media || []"
                    @refresh="refresh"
                />
            </v-col>

            <v-col cols="12" lg="7">
                <v-row>
                    <v-col cols="12" md="4">
                        <v-card class="border rounded">
                            <v-card-text>
                                <div class="text-caption text-grey">
                                    Последняя цена
                                </div>

                                <div class="text-h5">
                                    {{ latestPrice ? money(latestPrice) : '—' }}
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-card class="border rounded">
                            <v-card-text>
                                <div class="text-caption text-grey">
                                    Минимальная цена
                                </div>

                                <div class="text-h5">
                                    {{ minPrice ? money(minPrice) : '—' }}
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col cols="12" md="4">
                        <v-card class="border rounded">
                            <v-card-text>
                                <div class="text-caption text-grey">
                                    Максимальная цена
                                </div>

                                <div class="text-h5">
                                    {{ maxPrice ? money(maxPrice) : '—' }}
                                </div>
                            </v-card-text>
                        </v-card>
                    </v-col>
                </v-row>

                <v-card class="border rounded mt-4">
                    <v-card-title>
                        График изменения цены
                    </v-card-title>

                    <v-card-text>
                        <div
                            v-if="priceHistoryAsc.length"
                            class="d-flex align-end ga-1 border rounded pa-3"
                            style="height: 180px; overflow-x: auto;"
                        >
                            <div
                                v-for="point in priceHistoryAsc"
                                :key="`${point.check_id}-${point.id}`"
                                class="d-flex flex-column align-center"
                                style="min-width: 24px; height: 100%;"
                            >
                                <div class="flex-grow-1 d-flex align-end">
                                    <div
                                        class="bg-primary rounded-t"
                                        :title="`${formatDate(point.check_date)} — ${money(point.price)}`"
                                        :style="{
                                            height: `${Math.max(6, Number(point.price || 0) / maxPrice * 130)}px`,
                                            width: '16px',
                                        }"
                                    />
                                </div>

                                <div class="text-caption text-grey mt-1" style="font-size: 9px;">
                                    {{ new Date(point.check_date).getFullYear() }}
                                </div>
                            </div>
                        </div>

                        <v-alert
                            v-else
                            type="info"
                            variant="tonal"
                            density="compact"
                        >
                            Истории цен пока нет.
                        </v-alert>
                    </v-card-text>
                </v-card>

                <v-card class="border rounded mt-4">
                    <v-card-title>
                        Потраченные суммы по месяцам
                    </v-card-title>

                    <v-card-text>
                        <div
                            v-if="stats.monthly.length"
                            class="d-flex align-end ga-2 border rounded pa-3"
                            style="height: 180px; overflow-x: auto;"
                        >
                            <div
                                v-for="item in stats.monthly"
                                :key="item.period"
                                class="d-flex flex-column align-center"
                                style="min-width: 52px; height: 100%;"
                            >
                                <div class="flex-grow-1 d-flex align-end">
                                    <div
                                        class="bg-deep-orange rounded-t"
                                        :title="`${item.period} — ${money(item.total)}`"
                                        :style="{
                                            height: `${Math.max(6, Number(item.total || 0) / maxMonthlyTotal * 130)}px`,
                                            width: '32px',
                                        }"
                                    />
                                </div>

                                <div class="text-caption text-grey mt-1" style="font-size: 10px;">
                                    {{ item.period }}
                                </div>
                            </div>
                        </div>

                        <v-alert
                            v-else
                            type="info"
                            variant="tonal"
                            density="compact"
                        >
                            Данных по суммам пока нет.
                        </v-alert>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>

        <v-row class="mt-4">
            <v-col cols="12">
                <v-card class="border rounded">
                    <v-card-title>
                        История закупок Commodity
                    </v-card-title>

                    <v-data-table
                        :headers="historyHeaders"
                        :items="history"
                        :items-per-page="100"
                        density="compact"
                        fixed-header
                        height="520"
                        hover
                    >
                        <template #item.check_date="{ item }">
                            {{ formatDate(item.check_date) }}
                        </template>

                        <template #item.quantity="{ item }">
                            {{ money(item.quantity) }}
                        </template>

                        <template #item.price="{ item }">
                            {{ money(item.price) }}
                        </template>

                        <template #item.total_price="{ item }">
                            <strong>{{ money(item.total_price) }}</strong>
                        </template>

                        <template #item.check_id="{ item }">
                            #{{ item.check_id }}
                        </template>
                    </v-data-table>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
