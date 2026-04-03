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
    get: () => (props.filtersOpened ? 0 : null),
    set: value => emit('update:filtersOpened', value === 0),
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
    <v-card class="entity-table-card">
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

        <v-card-text class="entity-table-content">
            <v-expansion-panels
                v-model="localPanels"
                variant="accordion"
                class="mb-2"
            >
                <v-expansion-panel>
                    <v-expansion-panel-title>
                        Фильтры
                    </v-expansion-panel-title>

                    <v-expansion-panel-text>
                        <!-- фильтры -->
                    </v-expansion-panel-text>
                </v-expansion-panel>
            </v-expansion-panels>

            <div class="entity-table-wrapper">
                <v-data-table-server
                    class="entity-data-table"
                    :headers="headers"
                    :items="items"
                    :items-length="totalItems"
                    :loading="loading"
                    :page="page"
                    :items-per-page="itemsPerPage"
                    :sort-by="sortBy"
                    fixed-header
                    height="100%"
                    hide-default-footer
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
                </v-data-table-server>
            </div>

            <div class="entity-table-footer">
                <div class="d-flex flex-wrap ga-2 pa-2">
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
            </div>
        </v-card-text>
    </v-card>
</template>

<style scoped>
.entity-table-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.entity-table-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.entity-table-wrapper {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
}

.entity-data-table {
    height: 100%;
}

.entity-table-footer {
    flex: 0 0 auto;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    background: inherit;
}

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
