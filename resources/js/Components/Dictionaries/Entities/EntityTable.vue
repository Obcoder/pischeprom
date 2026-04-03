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
            <div class="entity-filters">
                <!-- filters -->
            </div>

            <div class="entity-table-wrapper">
                <v-data-table-server
                    :headers="headers"
                    :items="items"
                    :items-length="totalItems"
                    :loading="loading"
                    :page="page"
                    :items-per-page="itemsPerPage"
                    :sort-by="sortBy"
                    fixed-header
                    hide-default-footer
                    item-value="id"
                    height="100%"
                    @update:options="onOptionsUpdate"
                >
                    ...
                </v-data-table-server>
            </div>

            <div class="entity-table-footer">
                <!-- page markers -->
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
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.entity-filters {
    flex: 0 0 auto;
    margin-bottom: 8px;
}

.entity-table-wrapper {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
}

.entity-table-footer {
    flex: 0 0 auto;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
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
