<script setup>
import { computed } from 'vue'
import { route } from 'ziggy-js'
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
    groupByMode: { type: String, default: null },
    filtersOpened: Boolean,
})

const emit = defineEmits([
    'update:page',
    'update:itemsPerPage',
    'update:sortBy',
    'update:groupByMode',
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
    { title: 'Название', key: 'name', sortable: true, width: '18%' },
    { title: 'Класс', key: 'classification_name', sortable: true, width: '11%' },
    { title: 'Регион', key: 'region_names', sortable: true, width: '12%' },
    { title: 'Города', key: 'city_names', sortable: true, width: '11%' },
    { title: 'Дом', key: 'buildings', sortable: true, width: '13%' },
    { title: 'Тел.', key: 'telephones_display', sortable: true, width: '10%' },
    { title: 'Продаж', key: 'sales_count', sortable: true, width: 72 },
    { title: 'Purchase', key: 'purchases_max_date', sortable: true, width: 92 },
    { title: 'Sale', key: 'sales_max_date', sortable: true, width: 92 },
    { title: 'Создан', key: 'created_at', sortable: true, width: 82 },
    { title: 'Страна', key: 'country_name', sortable: true, width: '9%' },
    { title: '', key: 'actions', sortable: false, width: 136 },
]

const sortOptions = [
    { title: 'Количество продаж', value: 'sales_count' },
    { title: 'Дата создания', value: 'created_at' },
    { title: 'Последняя закупка', value: 'purchases_max_date' },
    { title: 'Последняя продажа', value: 'sales_max_date' },
    { title: 'Название', value: 'name' },
    { title: 'Классификация', value: 'classification_name' },
    { title: 'Регион', value: 'region_names' },
    { title: 'Город', value: 'city_names' },
    { title: 'Дом', value: 'buildings' },
    { title: 'Телефон', value: 'telephones_display' },
    { title: 'Страна', value: 'country_name' },
    { title: 'ИНН', value: 'INN' },
    { title: 'ОГРН', value: 'OGRN' },
]

const groupOptions = [
    { title: 'Нет', value: null },
    { title: 'Регионы', value: 'region' },
    { title: 'Города', value: 'city' },
]

const filterMenu = computed({
    get: () => props.filtersOpened,
    set: value => emit('update:filtersOpened', !!value),
})

const currentSortKey = computed({
    get: () => props.sortBy?.[0]?.key || 'sales_count',
    set: value => emit('update:sortBy', [{ key: value || 'sales_count', order: currentSortOrder.value }]),
})

const currentSortOrder = computed({
    get: () => props.sortBy?.[0]?.order || 'desc',
    set: value => emit('update:sortBy', [{ key: currentSortKey.value, order: value === 'asc' ? 'asc' : 'desc' }]),
})

const currentSortTitle = computed(() => {
    return sortOptions.find(item => item.value === currentSortKey.value)?.title || 'Количество продаж'
})

const tableGroupBy = computed(() => {
    if (props.groupByMode === 'region') {
        return [{ key: 'region_names', order: 'asc' }]
    }

    if (props.groupByMode === 'city') {
        return [{ key: 'city_names', order: 'asc' }]
    }

    return []
})

const activeFiltersCount = computed(() => {
    return [
        props.filters.search,
        ...(props.filters.entity_classification_ids || []),
        ...(props.filters.country_ids || []),
        ...(props.filters.city_ids || []),
        ...(props.filters.building_ids || []),
        ...(props.filters.email_ids || []),
        ...(props.filters.telephone_ids || []),
        ...(props.filters.unit_ids || []),
        ...(props.filters.chat_ids || []),
    ].filter(Boolean).length
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

const buildingTitle = (building) => {
    return [
        building.city?.name,
        building.address,
        building.postcode,
    ].filter(Boolean).join(' · ')
}

const groupLabel = (item) => {
    const value = item?.value ?? item?.title ?? item?.key ?? ''

    if (value) {
        return value
    }

    return props.groupByMode === 'region' ? 'Без региона' : 'Без города'
}
</script>

<template>
    <v-card class="w-100 entities-table-card">
        <v-card-title class="entities-table-toolbar">
            <span>Entities</span>

            <v-chip size="small" variant="tonal" color="blue-grey-lighten-2">
                {{ totalItems }}
            </v-chip>

            <v-spacer />

            <v-menu
                :close-on-content-click="false"
                location="bottom end"
                offset="12"
                content-class="entity-sort-menu"
            >
                <template #activator="{ props: menuProps }">
                    <v-btn
                        v-bind="menuProps"
                        color="blue-grey-lighten-2"
                        variant="tonal"
                        prepend-icon="mdi-sort"
                    >
                        {{ currentSortTitle }}
                        <v-icon
                            :icon="currentSortOrder === 'desc' ? 'mdi-sort-descending' : 'mdi-sort-ascending'"
                            end
                        />
                    </v-btn>
                </template>

                <v-card class="entity-sort-card">
                    <v-card-title class="entity-sort-card__title">
                        Сортировка
                    </v-card-title>

                    <v-card-text>
                        <v-select
                            v-model="currentSortKey"
                            :items="sortOptions"
                            item-title="title"
                            item-value="value"
                            label="Поле"
                            density="compact"
                            variant="solo-filled"
                            hide-details
                        />

                        <v-btn-toggle
                            v-model="currentSortOrder"
                            class="mt-3"
                            density="compact"
                            mandatory
                            divided
                        >
                            <v-btn value="desc" prepend-icon="mdi-sort-descending">
                                Убыв.
                            </v-btn>

                            <v-btn value="asc" prepend-icon="mdi-sort-ascending">
                                Возр.
                            </v-btn>
                        </v-btn-toggle>
                    </v-card-text>
                </v-card>
            </v-menu>

            <v-menu
                v-model="filterMenu"
                :close-on-content-click="false"
                location="bottom end"
                offset="12"
                content-class="entity-filter-menu"
            >
                <template #activator="{ props: menuProps }">
                    <v-btn
                        v-bind="menuProps"
                        :variant="activeFiltersCount ? 'flat' : 'tonal'"
                        color="blue-grey-lighten-2"
                        prepend-icon="mdi-filter-variant"
                    >
                        Фильтры

                        <v-chip
                            v-if="activeFiltersCount"
                            size="x-small"
                            color="#800000"
                            variant="flat"
                            class="ml-2"
                        >
                            {{ activeFiltersCount }}
                        </v-chip>
                    </v-btn>
                </template>

                <v-card class="entity-filter-card">
                    <v-card-title class="entity-filter-card__title">
                        <span>Фильтры Entities</span>

                        <v-btn
                            icon="mdi-close"
                            size="small"
                            variant="text"
                            @click="filterMenu = false"
                        />
                    </v-card-title>

                    <v-card-text>
                        <v-row dense>
                            <v-col cols="12" md="4">
                                <v-text-field
                                    v-model="filters.search"
                                    label="Поиск"
                                    density="compact"
                                    clearable
                                    hide-details
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
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
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
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
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
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
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.building_ids"
                                    :items="meta.buildings"
                                    :item-title="buildingTitle"
                                    item-value="id"
                                    label="Здания"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.email_ids"
                                    :items="meta.emails"
                                    item-title="address"
                                    item-value="id"
                                    label="Emails"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-autocomplete
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
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
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
                                    variant="solo-filled"
                                />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-select
                                    v-model="filters.chat_ids"
                                    :items="meta.chats"
                                    item-title="numbers"
                                    item-value="id"
                                    label="Chats"
                                    multiple
                                    chips
                                    closable-chips
                                    density="compact"
                                    clearable
                                    variant="solo-filled"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-btn variant="text" @click="emit('resetFilters')">
                            Сбросить
                        </v-btn>

                        <v-btn variant="text" @click="emit('reload')">
                            Обновить
                        </v-btn>

                        <v-spacer />

                        <v-btn color="blue-grey-darken-1" variant="flat" @click="filterMenu = false">
                            Закрыть
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-menu>

            <v-btn color="primary" @click="emit('create')">
                Добавить
            </v-btn>

            <v-btn-toggle
                :model-value="groupByMode"
                class="entity-group-toggle"
                density="compact"
                divided
                @update:model-value="emit('update:groupByMode', $event ?? null)"
            >
                <v-btn
                    v-for="option in groupOptions"
                    :key="option.value || 'none'"
                    :value="option.value"
                    size="small"
                >
                    {{ option.title }}
                </v-btn>
            </v-btn-toggle>
        </v-card-title>

        <v-card-text class="entities-table-body">
            <v-data-table-server
                class="entities-data-table text-caption"
                :headers="headers"
                :items="items"
                :items-length="totalItems"
                :loading="loading"
                :page="page"
                :items-per-page="itemsPerPage"
                :sort-by="sortBy"
                :group-by="tableGroupBy"
                fixed-header
                hide-default-footer
                item-value="id"
                height="100%"
                density="compact"
                hover
                @update:options="onOptionsUpdate"
            >
                <template #group-header="{ item, columns, toggleGroup, isGroupOpen }">
                    <tr class="entity-group-row">
                        <td :colspan="columns?.length || headers.length">
                            <v-btn
                                :icon="typeof isGroupOpen === 'function' && isGroupOpen(item) ? 'mdi-chevron-down' : 'mdi-chevron-right'"
                                size="x-small"
                                variant="text"
                                @click="typeof toggleGroup === 'function' && toggleGroup(item)"
                            />
                            <strong>{{ groupLabel(item) }}</strong>
                        </td>
                    </tr>
                </template>

                <template #item.name="{ item }">
                    <a
                        href="#"
                        class="entity-name-link"
                        @click.prevent="emit('show', item)"
                    >
                        {{ item.name }}
                    </a>
                </template>

                <template #item.region_names="{ item }">
                    <span>{{ item.region_names || '—' }}</span>
                </template>

                <template #item.city_names="{ item }">
                    <div v-if="groupByMode === 'city'" class="d-flex flex-column">
                        <strong>{{ item.city_names || 'Без города' }}</strong>
                        <span class="text-medium-emphasis">{{ item.name }}</span>
                    </div>
                    <span v-else>{{ item.city_names || '—' }}</span>
                </template>

                <template #item.buildings="{ item }">
                    <div class="entity-building-list">
                        <span
                            v-for="building in item.buildings"
                            :key="building.id"
                        >
                            {{ building.address }}
                        </span>
                    </div>
                </template>

                <template #item.telephones_display="{ item }">
                    {{ item.telephones_display || formatPhones(item.telephones || []) || '—' }}
                </template>

                <template #item.purchases_max_date="{ item }">
                    {{ item.purchases_max_date_display || '—' }}
                </template>

                <template #item.sales_max_date="{ item }">
                    {{ item.sales_max_date_display || '—' }}
                </template>

                <template #item.created_at="{ item }">
                    {{ item.created_at_display || '—' }}
                </template>

                <template #item.actions="{ item }">
                    <div class="entity-row-actions">
                        <v-btn
                            icon="mdi-eye-outline"
                            size="x-small"
                            variant="text"
                            title="Открыть панель"
                            @click="emit('show', item)"
                        />

                        <v-btn
                            icon="mdi-open-in-new"
                            size="x-small"
                            variant="text"
                            title="Перейти в карточку"
                            :href="route('Ameise.entity.show', item.id)"
                        />

                        <v-btn
                            icon="mdi-pencil"
                            size="x-small"
                            variant="text"
                            title="Редактировать"
                            @click="emit('edit', item)"
                        />

                        <v-btn
                            icon="mdi-delete-outline"
                            size="x-small"
                            variant="text"
                            color="error"
                            title="Удалить"
                            @click="emit('delete', item)"
                        />
                    </div>
                </template>
            </v-data-table-server>

            <div class="entity-page-strip">
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
        </v-card-text>
    </v-card>
</template>

<style scoped>
.entities-table-card {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 184px);
    min-height: 520px;
    border: 1px solid rgba(255, 255, 255, 0.10);
}

.entities-table-toolbar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 42px;
    padding: 6px 10px;
    font-size: 0.9rem;
}

.entities-table-body {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 6px;
}

.entities-data-table {
    flex: 1 1 auto;
    min-height: 0;
}

.entities-data-table :deep(.v-table__wrapper) {
    height: 100% !important;
}

.entities-data-table :deep(th),
.entities-data-table :deep(td) {
    height: 28px !important;
    padding: 0 6px !important;
    font-size: 0.7rem;
    line-height: 1.15;
    vertical-align: middle;
}

.entities-data-table :deep(th) {
    white-space: nowrap;
}

.entity-row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 2px;
}

.entity-name-link {
    color: #eaf4ff;
    font-weight: 800;
    text-decoration: none;
}

.entity-name-link:hover {
    color: #ffffff;
    text-decoration: underline;
}

.entity-building-list {
    display: flex;
    flex-direction: column;
    gap: 1px;
    max-width: 210px;
    max-height: 32px;
    overflow: hidden;
}

.entity-building-list span {
    color: rgba(255, 255, 255, 0.78);
    font-size: 9px;
    font-weight: 700;
    line-height: 1.2;
}

.entity-sort-card {
    width: min(360px, calc(100vw - 32px));
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 14px;
    background: linear-gradient(135deg, #4d5354 0%, #626867 100%);
    color: #f5f1e9;
}

.entity-sort-card__title {
    min-height: 40px;
    padding: 8px 14px;
    font-size: 0.95rem;
}

.entity-group-toggle {
    max-width: 100%;
}

.entity-group-row td {
    background: rgba(128, 0, 0, 0.28) !important;
    color: #fff8e9;
}

.entity-filter-card {
    width: min(920px, calc(100vw - 32px));
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 20px;
    background:
        radial-gradient(circle at 0% 0%, rgba(255, 255, 255, 0.12), transparent 34%),
        linear-gradient(135deg, #4d5354 0%, #626867 100%);
    color: #f5f1e9;
    box-shadow: 0 26px 70px rgba(0, 0, 0, 0.38);
}

.entity-filter-card__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.entity-page-chip {
    min-width: 48px;
}

.entity-page-strip {
    flex: 0 0 auto;
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    max-height: 46px;
    padding: 6px 2px 0;
    overflow-x: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.entity-page-marker {
    font-size: 9px;
    line-height: 1;
    max-width: 70px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.w-100 {
    width: 100%;
    max-width: 100%;
}
</style>
