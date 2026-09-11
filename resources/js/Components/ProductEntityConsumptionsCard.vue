<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    productId: { type: [Number, String], required: true },
})

const consumptions = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')
let activeRequest = null

const statuses = {
    potential: { title: 'Потенциальная', color: 'primary' },
    confirmed: { title: 'Подтверждена', color: 'success' },
    closed: { title: 'Закрыта', color: 'grey' },
}

const headers = [
    { title: 'Entity', key: 'entity.name' },
    { title: 'Объём', key: 'quantity', sortable: false },
    { title: 'Статус', key: 'status', sortable: false },
    { title: 'Комментарий', key: 'comment', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' },
]

const filteredConsumptions = computed(() => {
    const query = String(search.value ?? '').trim().toLocaleLowerCase('ru-RU')
    if (!query) return consumptions.value

    return consumptions.value.filter(item => [
        item.entity?.name,
        item.entity?.INN,
        item.comment,
        statuses[item.status]?.title,
    ].some(value => String(value ?? '').toLocaleLowerCase('ru-RU').includes(query)))
})

function entityUrl(item) {
    return `/Ameise/entity/${item.entity_id}#entity-consumptions`
}

function formatQuantity(value) {
    if (value === null || value === undefined || value === '') return '—'

    const [whole, fraction = ''] = String(value).split('.')
    const decimal = fraction.replace(/0+$/, '')
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + (decimal ? `,${decimal}` : '')
}

async function loadConsumptions() {
    activeRequest?.abort()
    const request = new AbortController()
    activeRequest = request
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get(`/api/products/${props.productId}/entity-consumptions`, {
            signal: request.signal,
        })
        if (!request.signal.aborted) consumptions.value = data.data || []
    } catch (exception) {
        if (!request.signal.aborted) {
            error.value = exception?.response?.data?.message || 'Не удалось загрузить потребности Entities.'
        }
    } finally {
        if (!request.signal.aborted) loading.value = false
    }
}

watch(() => props.productId, () => {
    consumptions.value = []
    search.value = ''
    loadConsumptions()
}, { immediate: true })

onBeforeUnmount(() => activeRequest?.abort())
</script>

<template>
    <v-card rounded="lg" variant="flat" border class="entity-needs-card">
        <div class="entity-needs-header">
            <div class="d-flex align-center ga-2">
                <v-icon icon="mdi-domain" color="primary" size="21" />
                <h2 class="text-subtitle-1 font-weight-bold">Потребности Entities</h2>
                <v-chip v-if="!loading && !error" size="x-small" color="primary" variant="tonal">
                    {{ consumptions.length }}
                </v-chip>
            </div>
            <v-btn
                icon="mdi-refresh"
                size="small"
                variant="text"
                :loading="loading"
                aria-label="Обновить потребности Entities"
                title="Обновить"
                @click="loadConsumptions"
            />
        </div>

        <p class="text-body-2 text-medium-emphasis px-4 pb-3">
            Потребности компаний в этом продукте. Добавление и изменение — в карточке Entity.
        </p>

        <v-divider />

        <v-skeleton-loader v-if="loading" type="table-row@3" />

        <v-alert v-else-if="error" type="error" variant="tonal" density="compact" class="ma-3">
            {{ error }}
            <template #append>
                <v-btn size="small" variant="text" @click="loadConsumptions">Повторить</v-btn>
            </template>
        </v-alert>

        <div v-else-if="!consumptions.length" class="entity-needs-empty">
            <v-icon icon="mdi-clipboard-text-outline" color="primary" size="28" />
            <div>
                <div class="text-body-2 font-weight-medium">Потребностей пока нет</div>
                <div class="text-caption text-medium-emphasis">
                    Откройте нужную Entity и добавьте продукт в блоке «Потребности».
                </div>
            </div>
        </div>

        <template v-else>
            <div class="px-4 pt-3 pb-2">
                <v-text-field
                    v-model="search"
                    label="Поиск по названию, ИНН, статусу или комментарию"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="compact"
                    hide-details
                    clearable
                    @click:clear="search = ''"
                />
            </div>

            <v-data-table
                :headers="headers"
                :items="filteredConsumptions"
                :items-per-page="10"
                :items-per-page-options="[10, 25, 50]"
                density="compact"
                no-data-text="По вашему запросу ничего не найдено"
                items-per-page-text="На странице"
                page-text="{0}–{1} из {2}"
                class="entity-needs-table"
            >
                <template #item.entity.name="{ item }">
                    <div class="py-2">
                        <Link :href="entityUrl(item)" class="entity-needs-link">
                            {{ item.entity?.name || `Entity #${item.entity_id}` }}
                        </Link>
                        <div v-if="item.entity?.INN" class="text-caption text-medium-emphasis">
                            ИНН {{ item.entity.INN }}
                        </div>
                    </div>
                </template>

                <template #item.quantity="{ item }">
                    <span class="entity-needs-quantity">{{ formatQuantity(item.quantity) }}</span>
                    <span v-if="item.measure?.name" class="text-caption text-medium-emphasis ml-1">
                        {{ item.measure.name }}
                    </span>
                </template>

                <template #item.status="{ item }">
                    <v-chip :color="statuses[item.status]?.color || 'grey'" size="x-small" variant="tonal">
                        {{ statuses[item.status]?.title || item.status }}
                    </v-chip>
                </template>

                <template #item.comment="{ item }">
                    <span class="entity-needs-comment text-body-2">{{ item.comment || '—' }}</span>
                </template>

                <template #item.actions="{ item }">
                    <Link :href="entityUrl(item)" class="entity-needs-manage">
                        Управлять
                        <v-icon icon="mdi-arrow-top-right" size="14" />
                    </Link>
                </template>
            </v-data-table>
        </template>
    </v-card>
</template>

<style scoped>
.entity-needs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 12px 4px 16px;
}

.entity-needs-empty {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 24px 16px;
}

.entity-needs-link {
    color: rgb(var(--v-theme-primary));
    font-weight: 600;
    overflow-wrap: anywhere;
    text-decoration: none;
}

.entity-needs-link:hover,
.entity-needs-manage:hover {
    text-decoration: underline;
}

.entity-needs-quantity {
    font-weight: 600;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.entity-needs-comment {
    display: block;
    max-width: 420px;
    min-width: 120px;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.entity-needs-manage {
    color: rgb(var(--v-theme-primary));
    font-size: 0.8rem;
    white-space: nowrap;
    text-decoration: none;
}

.entity-needs-table :deep(th) {
    white-space: nowrap;
    background: rgba(var(--v-theme-primary), 0.035);
}
</style>
