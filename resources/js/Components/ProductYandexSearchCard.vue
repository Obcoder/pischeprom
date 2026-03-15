<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    productId: {
        type: Number,
        required: true,
    },
    productName: {
        type: String,
        default: '',
    },
})

const headers = [
    { title: '#', key: 'position', width: 70 },
    { title: 'Заголовок', key: 'title' },
    { title: 'URL', key: 'url' },
    { title: 'Сниппет', key: 'snippet' },
]

const query = ref(props.productName ? `${props.productName} купить` : '')
const maxResults = ref(100)

const request = ref(null)
const results = ref([])

const isSubmitting = ref(false)
const isPolling = ref(false)
const isLoadingInitial = ref(false)

let pollTimer = null

watch(
    () => props.productName,
    (newValue) => {
        if ((!query.value || query.value === 'undefined купить') && newValue) {
            query.value = `${newValue} купить`
        }
    },
    { immediate: true }
)

function shortUrl(url) {
    if (!url) return '—'
    return url.length > 70 ? url.slice(0, 70) + '…' : url
}

async function loadLatest() {
    isLoadingInitial.value = true

    try {
        const { data } = await axios.get(`/api/products/${props.productId}/yandex-search/latest`)
        request.value = data.request
        results.value = data.results || []

        if (request.value && ['queued', 'processing'].includes(request.value.status)) {
            startPolling()
        }
    } finally {
        isLoadingInitial.value = false
    }
}

async function runSearch() {
    isSubmitting.value = true

    try {
        const { data } = await axios.post(`/api/products/${props.productId}/yandex-search`, {
            query: query.value,
            max_results: maxResults.value,
        })

        request.value = {
            id: data.request_id,
            status: data.status,
            query: data.query,
            results_count: 0,
            error_message: null,
        }

        results.value = []
        startPolling()
    } finally {
        isSubmitting.value = false
    }
}

async function pollLatest() {
    const { data } = await axios.get(`/api/products/${props.productId}/yandex-search/latest`)
    request.value = data.request
    results.value = data.results || []

    if (!request.value) {
        stopPolling()
        return
    }

    if (!['queued', 'processing'].includes(request.value.status)) {
        stopPolling()
    }
}

function startPolling() {
    if (pollTimer) return

    isPolling.value = true

    pollTimer = setInterval(async () => {
        try {
            await pollLatest()
        } catch (e) {
            console.error(e)
            stopPolling()
        }
    }, 3000)
}

function stopPolling() {
    isPolling.value = false

    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

onMounted(() => {
    loadLatest()
})

onBeforeUnmount(() => {
    stopPolling()
})
</script>

<template>
    <v-card class="mt-4">
        <v-card-title class="d-flex align-center justify-space-between">
            <span>Выдача Яндекса</span>

            <v-btn
                color="primary"
                :loading="isSubmitting"
                :disabled="isSubmitting || isPolling"
                @click="runSearch"
            >
                Получить выдачу Яндекса
            </v-btn>
        </v-card-title>

        <v-card-text>
            <v-row>
                <v-col cols="12" md="8">
                    <v-text-field
                        v-model="query"
                        label="Поисковый запрос"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                    />
                </v-col>

                <v-col cols="12" md="4">
                    <v-select
                        v-model="maxResults"
                        :items="[10, 20, 30, 50, 100]"
                        label="Количество результатов"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                    />
                </v-col>
            </v-row>

            <div class="mt-4" v-if="request">
                <v-alert
                    v-if="request.status === 'queued'"
                    type="info"
                    variant="tonal"
                >
                    Задача поставлена в очередь.
                </v-alert>

                <v-alert
                    v-else-if="request.status === 'processing'"
                    type="warning"
                    variant="tonal"
                >
                    Идёт сбор результатов...
                </v-alert>

                <v-alert
                    v-else-if="request.status === 'done'"
                    type="success"
                    variant="tonal"
                >
                    Готово. Получено результатов: {{ request.results_count }}
                </v-alert>

                <v-alert
                    v-else-if="request.status === 'failed'"
                    type="error"
                    variant="tonal"
                >
                    Ошибка: {{ request.error_message || 'Неизвестная ошибка' }}
                </v-alert>
            </div>

            <v-progress-linear
                v-if="isPolling"
                indeterminate
                class="mt-4"
            />

            <v-data-table
                class="mt-4"
                :headers="headers"
                :items="results"
                :loading="isLoadingInitial"
                :items-per-page="20"
                :items-per-page-options="[10, 20, 50, 100]"
            >
                <template #item.title="{ item }">
                    <div class="py-2">
                        <a
                            :href="item.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-decoration-none"
                        >
                            {{ item.title || item.url }}
                        </a>
                        <div class="text-caption text-medium-emphasis mt-1">
                            {{ item.domain }}
                        </div>
                    </div>
                </template>

                <template #item.url="{ item }">
                    <a
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ shortUrl(item.url) }}
                    </a>
                </template>

                <template #item.snippet="{ item }">
                    <div style="white-space: pre-line;">
                        {{ item.snippet || '—' }}
                    </div>
                </template>
            </v-data-table>
        </v-card-text>
    </v-card>
</template>
