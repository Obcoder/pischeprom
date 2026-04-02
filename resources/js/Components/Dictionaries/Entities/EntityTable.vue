<script setup>
import { computed } from 'vue'
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter'

const props = defineProps({
    items: { type: Array, default: () => [] },
    loading: Boolean,
    totalItems: Number,
    page: Number,
    itemsPerPage: Number,
    sortBy: { type: Array, default: () => [] },
    pageMarkers: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    meta: { type: Object, required: true },
    groupByCities: Boolean,
    filtersOpened: Boolean,
})

const emit = defineEmits([
    'update:page',
    'update:itemsPerPage',
    'update:sortBy',
    'update:groupByCities',
    'update:filtersOpened',
    'show',
    'create',
    'edit',
    'delete',
    'resetFilters',
    'reload',
])

const { formatPhones } = usePhoneFormatter()

const headers = [
    { title: 'Название', key: 'name', sortable: true },
    { title: 'Классификация', key: 'classification_name', sortable: false },
    { title: 'Страна', key: 'country_name', sortable: false },
    { title: 'Города', key: 'city_names', sortable: false },
    { title: 'Телефоны', key: 'telephones_display', sortable: false },
    { title: 'Продаж', key: 'sales_count', sortable: true },
    { title: 'Последний purchase', key: 'last_purchase_date', sortable: true },
    { title: '', key: 'actions', sortable: false, width: 120 },
]

const localPanels = computed({
    get: () => (props.filtersOpened ? [0] : []),
    set: (value) => emit('update:filtersOpened', value.includes(0)),
})

const onOptionsUpdate = (options) => {
    emit('update:page', options.page)
    emit('update:itemsPerPage', options.itemsPerPage)
    emit('update:sortBy', options.sortBy)
}

const goToPage = (p) => {
    emit('update:page', p)
    emit('reload')
}
</script>

<template>
    <v-card class="h-100">
        <v-card-title class="d-flex align-center flex-wrap ga-2">
            <span>Entities</span>
            <v-spacer />

            <v-btn color="primary" @click="emit('create')">
                Добавить
            </v-btn>

            <v-btn
                :variant="groupByCities ? 'flat' : 'outlined'"
                color="secondary"
                @click="emit('update:groupByCities', !groupByCities)"
            >
                {{ groupByCities ? 'Без группировки' : 'По городам' }}
            </v-btn>
        </v-card-title>

        <v-card-text>
            <v-expansion-panels
                v-model="localPanels"
                variant="accordion"
                class="mb-4"
            >
                <v-expansion-panel>
                    <v-expansion-panel-title>
                        Фильтры
                    </v-expansion-panel-title>

                    <v-expansion-panel-text>
                        <v-row dense>
                            <v-col cols="12" md="3">
                                <v-text-field
                                    v-model="filters.search"
                                    label="Поиск"
                                    density="compact"
                                    clearable
                                    hide-details
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.entity_classification_ids"
                                    :items="meta.classifications"
                                    item-title="name"
                                    item-value="id"
                                    label="Классификации"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.country_ids"
                                    :items="meta.countries"
                                    item-title="name"
                                    item-value="id"
                                    label="Страны"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.city_ids"
                                    :items="meta.cities"
                                    item-title="name"
                                    item-value="id"
                                    label="Города"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.building_ids"
                                    :items="meta.buildings"
                                    item-title="name"
                                    item-value="id"
                                    label="Здания"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.email_ids"
                                    :items="meta.emails"
                                    item-title="email"
                                    item-value="id"
                                    label="Emails"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.telephone_ids"
                                    :items="meta.telephones"
                                    item-title="number"
                                    item-value="id"
                                    label="Телефоны"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.unit_ids"
                                    :items="meta.units"
                                    item-title="name"
                                    item-value="id"
                                    label="Units"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" md="3">
                                <v-select
                                    v-model="filters.chat_ids"
                                    :items="meta.chats"
                                    item-title="name"
                                    item-value="id"
                                    label="Chats"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12" class="pt-1">
                                <div class="d-flex ga-2">
                                    <v-btn variant="text" @click="emit('resetFilters')">
                                        Сбросить
                                    </v-btn>
                                    <v-btn variant="text" @click="emit('reload')">
                                        Обновить
                                    </v-btn>
                                </div>
                            </v-col>
                        </v-row>
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>

            <v-data-table-server
                :headers="headers"
                :items="items"
                :items-length="totalItems"
                :loading="loading"
                :page="page"
                :items-per-page="itemsPerPage"
                :sort-by="sortBy"
                item-value="id"
                @update:options="onOptionsUpdate"
            >
                <template #item.name="{ item }">
                    <a
                        href="#"
                        class="text-decoration-none font-weight-medium"
                        @click.prevent="emit('show', item)"
                    >
                        {{ item.name }}
                    </a>
                </template>

                <template #item.city_names="{ item }">
                    <div v-if="groupByCities" class="d-flex flex-column">
                        <strong>{{ item.city_names || 'Без города' }}</strong>
                        <span class="text-medium-emphasis">{{ item.name }}</span>
                    </div>
                    <span v-else>{{ item.city_names || '—' }}</span>
                </template>

                <template #item.telephones_display="{ item }">
                    {{ item.telephones_display || formatPhones(item.telephones || []) || '—' }}
                </template>

                <template #item.last_purchase_date="{ item }">
                    {{ item.last_purchase_date ? new Date(item.last_purchase_date).toLocaleDateString() : '—' }}
                </template>

                <template #item.actions="{ item }">
                    <div class="d-flex ga-1 justify-end">
                        <v-btn size="small" variant="text" @click="emit('edit', item)">edit</v-btn>
                        <v-btn size="small" variant="text" color="error" @click="emit('delete', item)">del</v-btn>
                    </div>
                </template>

                <template #bottom>
                    <div class="d-flex flex-wrap ga-2 pa-4">
                        <v-btn
                            v-for="p in pageMarkers"
                            :key="p.page"
                            :variant="page === p.page ? 'flat' : 'outlined'"
                            size="small"
                            class="entity-page-chip"
                            @click="goToPage(p.page)"
                        >
                            <div class="d-flex flex-column align-center">
                                <span>{{ p.page }}</span>
                                <span class="entity-page-marker">{{ p.first_name }}</span>
                            </div>
                        </v-btn>
                    </div>
                </template>
            </v-data-table-server>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.entity-page-chip {
    min-width: 48px;
}

.entity-page-marker {
    font-size: 9px;
    line-height: 1;
    max-width: 70px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
