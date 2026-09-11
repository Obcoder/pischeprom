<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter'

const props = defineProps({
    productId: { type: [Number, String], required: true },
})

const consumptions = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')
let activeRequest = null
const { formatPhone } = usePhoneFormatter()

const statuses = {
    potential: { title: 'Потенциальная', color: 'primary' },
    confirmed: { title: 'Подтверждена', color: 'success' },
    closed: { title: 'Закрыта', color: 'grey' },
}

const headers = [
    { title: 'Entity', key: 'entity.name' },
    { title: 'Объём', key: 'quantity', sortable: false, width: '100px' },
    { title: '', key: 'actions', sortable: false, align: 'end', width: '36px' },
]

const filteredConsumptions = computed(() => {
    const query = String(search.value ?? '').trim().toLocaleLowerCase('ru-RU')
    if (!query) return consumptions.value
    const phoneQuery = /^[+\d\s().-]+$/.test(query) ? query.replace(/\D/g, '') : ''

    return consumptions.value.filter(item => {
        const phones = entityPhones(item.entity)
        return [
            item.entity?.name,
            item.entity?.INN,
            item.comment,
            statuses[item.status]?.title,
            ...(item.entity?.cities || []).map(city => city.name),
            ...phones.map(phone => phone.number),
        ].some(value => String(value ?? '').toLocaleLowerCase('ru-RU').includes(query))
            || (phoneQuery && phones.some(phone => phone.number.replace(/\D/g, '').includes(phoneQuery)))
    })
})

function entityPhones(entity) {
    return (entity?.telephones || []).filter(phone => String(phone.number || '').trim())
        .map(phone => ({ ...phone, number: String(phone.number).trim() }))
}

function entityCities(entity) {
    return (entity?.cities || []).map(city => city.name).filter(Boolean).join(', ')
}

function telephoneUrl(number) {
    return `tel:${number.replace(/[^+\d]/g, '')}`
}

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
    <v-card rounded="lg" variant="flat" border class="entity-needs-card" aria-label="Потребители Entities">
        <div class="entity-needs-header">
            <div class="d-flex align-center ga-2">
                <v-icon icon="mdi-domain" color="primary" size="19" />
                <h2>Entities</h2>
                <v-chip v-if="!loading && !error" size="x-small" color="primary" variant="tonal">
                    {{ consumptions.length }}
                </v-chip>
            </div>
            <v-btn
                icon="mdi-refresh"
                size="x-small"
                variant="text"
                :loading="loading"
                aria-label="Обновить потребности Entities"
                title="Обновить"
                @click="loadConsumptions"
            />
        </div>

        <v-skeleton-loader v-if="loading" type="table-row@3" />

        <v-alert v-else-if="error" type="error" variant="tonal" density="compact" class="ma-3">
            {{ error }}
            <template #append>
                <v-btn size="small" variant="text" @click="loadConsumptions">Повторить</v-btn>
            </template>
        </v-alert>

        <div v-else-if="!consumptions.length" class="entity-needs-empty">
            <v-icon icon="mdi-clipboard-text-outline" color="primary" size="22" />
            <div>
                <div class="text-body-2 font-weight-medium">Потребностей пока нет</div>
                <div class="text-caption text-medium-emphasis">
                    Откройте нужную Entity и добавьте продукт в блоке «Потребности».
                </div>
            </div>
        </div>

        <template v-else>
            <div class="entity-needs-search">
                <v-text-field
                    v-model="search"
                    label="Поиск Entities"
                    placeholder="Название, ИНН, город, телефон"
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
                :hide-default-footer="filteredConsumptions.length <= 10"
                :mobile="false"
                density="compact"
                no-data-text="По вашему запросу ничего не найдено"
                items-per-page-text="На странице"
                page-text="{0}–{1} из {2}"
                class="entity-needs-table"
            >
                <template #item.entity.name="{ item }">
                    <div class="entity-needs-identity">
                        <Link :href="entityUrl(item)" class="entity-needs-link">
                            {{ item.entity?.name || `Entity #${item.entity_id}` }}
                        </Link>
                        <div class="entity-needs-meta">
                            <span v-if="item.entity?.INN" class="text-caption text-medium-emphasis">
                                ИНН {{ item.entity.INN }}
                            </span>
                            <v-chip :color="statuses[item.status]?.color || 'grey'" size="x-small" variant="tonal">
                                {{ statuses[item.status]?.title || item.status }}
                            </v-chip>
                        </div>
                        <div v-if="entityCities(item.entity) || entityPhones(item.entity).length" class="entity-needs-contacts">
                            <span v-if="entityCities(item.entity)" class="entity-needs-cities">
                                <v-icon icon="mdi-map-marker-outline" size="13" aria-hidden="true" />
                                <span>{{ entityCities(item.entity) }}</span>
                            </span>
                            <span v-if="entityPhones(item.entity).length" class="entity-needs-phones">
                                <v-icon icon="mdi-phone-outline" size="13" aria-hidden="true" />
                                <a
                                    v-for="phone in entityPhones(item.entity)"
                                    :key="phone.id || phone.number"
                                    :href="telephoneUrl(phone.number)"
                                    :aria-label="`Позвонить: ${formatPhone(phone.number)}`"
                                >{{ formatPhone(phone.number) }}</a>
                            </span>
                        </div>
                        <div v-if="item.comment" class="entity-needs-comment text-caption text-medium-emphasis">
                            {{ item.comment }}
                        </div>
                    </div>
                </template>

                <template #item.quantity="{ item }">
                    <span class="entity-needs-quantity">{{ formatQuantity(item.quantity) }}</span>
                    <span v-if="item.measure?.name" class="entity-needs-measure text-caption text-medium-emphasis">
                        {{ item.measure.name }}
                    </span>
                </template>

                <template #item.actions="{ item }">
                    <Link
                        :href="entityUrl(item)"
                        class="entity-needs-manage"
                        :aria-label="`Управлять потребностями ${item.entity?.name || `Entity #${item.entity_id}`}`"
                        title="Открыть потребности Entity"
                    >
                        <v-icon icon="mdi-arrow-top-right" size="16" />
                    </Link>
                </template>
            </v-data-table>
        </template>
    </v-card>
</template>

<style scoped>
.entity-needs-card {
    min-width: 0;
}

.entity-needs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 48px;
    padding: 8px 12px;
}

.entity-needs-header h2 { margin: 0; font-size: 0.9rem; font-weight: 650; }
.entity-needs-search { padding: 0 12px 10px; }
.entity-needs-search :deep(.v-field__input) { font-size: 0.8rem; }
.entity-needs-table :deep(th) { height: 30px !important; }

.entity-needs-empty {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 12px;
}

.entity-needs-identity {
    min-width: 0;
    padding: 8px 0;
}

.entity-needs-contacts { display: flex; align-items: baseline; flex-wrap: wrap; gap: 3px 12px; margin-top: 4px; color: rgba(var(--v-theme-on-surface), 0.66); font-size: 0.7rem; line-height: 1.45; }
.entity-needs-cities { display: inline-flex; align-items: baseline; gap: 4px; min-width: 0; overflow-wrap: anywhere; }
.entity-needs-phones { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 3px 8px; min-width: 0; }
.entity-needs-contacts .v-icon { flex-shrink: 0; opacity: 0.75; }
.entity-needs-phones a { color: inherit; font-variant-numeric: tabular-nums; text-decoration: none; overflow-wrap: anywhere; }
.entity-needs-phones a:hover { color: rgb(var(--v-theme-primary)); text-decoration: underline; }
.entity-needs-phones a:focus-visible { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 2px; border-radius: 2px; }

.entity-needs-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px 8px;
    margin-top: 3px;
}

.entity-needs-link {
    color: rgb(var(--v-theme-primary));
    font-size: 0.8125rem;
    font-weight: 600;
    overflow-wrap: anywhere;
    text-decoration: none;
}

.entity-needs-link:hover,
.entity-needs-manage:hover {
    text-decoration: underline;
}

.entity-needs-quantity {
    font-size: 0.8125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}

.entity-needs-measure {
    display: block;
    overflow-wrap: anywhere;
}

.entity-needs-comment {
    display: block;
    margin-top: 4px;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.entity-needs-manage {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: rgb(var(--v-theme-primary));
    text-decoration: none;
}

.entity-needs-manage:hover {
    background: rgba(var(--v-theme-primary), 0.06);
}

.entity-needs-table :deep(table) {
    width: 100%;
    table-layout: fixed;
}

.entity-needs-table :deep(.v-data-table__td) {
    padding: 0 10px;
}

.entity-needs-table :deep(.v-data-table__td:last-child) {
    padding: 0 4px;
}

.entity-needs-table :deep(th) {
    font-size: 0.75rem;
    white-space: nowrap;
    background: rgba(var(--v-theme-primary), 0.035);
}

.entity-needs-table :deep(.v-data-table-footer) {
    justify-content: space-between;
    gap: 4px 8px;
    padding: 6px 8px;
    font-size: 0.75rem;
}

.entity-needs-table :deep(.v-data-table-footer__items-per-page),
.entity-needs-table :deep(.v-data-table-footer__pagination) {
    padding: 0;
}

.entity-needs-table :deep(.v-data-table-footer__info) {
    min-width: 0;
}

.entity-needs-table :deep(.v-data-table-footer .v-btn) {
    width: 28px;
    height: 28px;
}
</style>
