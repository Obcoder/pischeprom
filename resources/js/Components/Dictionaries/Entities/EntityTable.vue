<script setup>
import { computed } from 'vue'
import { route } from 'ziggy-js'
import MaxContactButton from '@/Components/Max/MaxContactButton.vue'
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
    'open-avito',
    'open-sales',
    'open-orders',
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
    { title: 'Авито', key: 'avito_chats_count', sortable: true, width: 94 },
    { title: 'Продажи', key: 'sales_count', sortable: true, width: 88 },
    { title: 'Заказы', key: 'orders_count', sortable: true, width: 88 },
    { title: 'Purchase', key: 'purchases_max_date', sortable: true, width: 92 },
    { title: 'Создан', key: 'created_at', sortable: true, width: 82 },
    { title: 'Страна', key: 'country_name', sortable: true, width: '9%' },
    { title: '', key: 'actions', sortable: false, width: 136 },
]

const sortOptions = [
    { title: 'Дата создания', value: 'created_at' },
    { title: 'Количество продаж', value: 'sales_count' },
    { title: 'Последняя закупка', value: 'purchases_max_date' },
    { title: 'Последняя продажа', value: 'sales_max_date' },
    { title: 'Количество заказов', value: 'orders_count' },
    { title: 'Последний заказ', value: 'orders_max_submitted_at' },
    { title: 'Чаты Авито', value: 'avito_chats_count' },
    { title: 'Непрочитанные чаты Авито', value: 'avito_unread_chats_count' },
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

const presenceFilters = [
    { key: 'has_avito_chats', title: 'Чаты Авито' },
    { key: 'has_unread_avito', title: 'Непрочитанное в Авито' },
    { key: 'has_sales', title: 'Продажи' },
    { key: 'has_orders', title: 'Заказы (все статусы)' },
]

const presenceOptions = [
    { title: 'Все', value: null },
    { title: 'Есть', value: true },
    { title: 'Нет', value: false },
]

const filterMenu = computed({
    get: () => props.filtersOpened,
    set: value => emit('update:filtersOpened', !!value),
})

const currentSortKey = computed({
    get: () => props.sortBy?.[0]?.key || 'created_at',
    set: value => emit('update:sortBy', [{ key: value || 'created_at', order: currentSortOrder.value }]),
})

const currentSortOrder = computed({
    get: () => props.sortBy?.[0]?.order || 'desc',
    set: value => emit('update:sortBy', [{ key: currentSortKey.value, order: value === 'asc' ? 'asc' : 'desc' }]),
})

const currentSortTitle = computed(() => {
    return sortOptions.find(item => item.value === currentSortKey.value)?.title || 'Дата создания'
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
        ...(props.filters.entity_classification_ids || []),
        ...(props.filters.country_ids || []),
        ...(props.filters.city_ids || []),
        ...(props.filters.building_ids || []),
        ...(props.filters.email_ids || []),
        ...(props.filters.telephone_ids || []),
        ...(props.filters.unit_ids || []),
        ...(props.filters.chat_ids || []),
    ].filter(Boolean).length + presenceFilters.filter(({ key }) => props.filters[key] != null).length
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

const phoneNumber = (telephone) => {
    return telephone?.number ?? telephone?.telephone ?? telephone?.phone ?? ''
}

const chatTitle = (chat) => chat.peer_name || chat.title || `Чат #${chat.id}`
const openChat = (entity, chat) => emit('open-avito', { entity, chat })
</script>

<template>
    <v-card class="w-100 entities-table-card">
        <v-card-title class="entities-table-toolbar">
            <span>Entities</span>

            <v-chip size="small" variant="tonal" color="blue-grey-lighten-2">
                {{ totalItems }}
            </v-chip>

            <v-text-field
                v-model="filters.search"
                class="entities-table-search"
                label="Поиск"
                prepend-inner-icon="mdi-magnify"
                density="compact"
                clearable
                hide-details
                single-line
                variant="solo-filled"
            />

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
                        <v-row dense class="mb-2">
                            <v-col v-for="filter in presenceFilters" :key="filter.key" cols="12" sm="6" md="3">
                                <v-select
                                    v-model="filters[filter.key]"
                                    :items="presenceOptions"
                                    :label="filter.title"
                                    density="compact"
                                    clearable
                                    hide-details
                                    variant="solo-filled"
                                />
                            </v-col>
                        </v-row>
                        <v-divider class="mb-3" />
                        <v-row dense>
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
                    <div
                        v-if="item.telephones?.length"
                        class="entity-phone-list"
                    >
                        <span
                            v-for="telephone in item.telephones"
                            :key="telephone.id || phoneNumber(telephone)"
                            class="entity-phone-line"
                        >
                            <span>{{ phoneNumber(telephone) }}</span>
                            <MaxContactButton
                                :phone="phoneNumber(telephone)"
                                :entity-id="item.id"
                                :context-title="item.name"
                                size="x-small"
                                color="#fff7ed"
                            />
                        </span>
                    </div>
                    <span v-else>{{ item.telephones_display || formatPhones(item.telephones || []) || '—' }}</span>
                </template>

                <template #item.purchases_max_date="{ item }">
                    {{ item.purchases_max_date_display || '—' }}
                </template>

                <template #item.sales_count="{ item }">
                    <button
                        v-if="item.sales_count"
                        type="button"
                        class="entity-activity-button"
                        :title="`Открыть продажи: ${item.sales_count}${item.sales_max_date_display ? ` · последняя ${item.sales_max_date_display}` : ''}`"
                        @click="emit('open-sales', item)"
                    >
                        <strong>{{ item.sales_count }}</strong>
                        <small>{{ item.sales_max_date_display }}</small>
                    </button>
                    <span v-else class="text-disabled">—</span>
                </template>

                <template #item.orders_count="{ item }">
                    <button
                        v-if="item.orders_count"
                        type="button"
                        class="entity-activity-button"
                        :title="`Открыть заказы во всех статусах: ${item.orders_count}`"
                        @click="emit('open-orders', item)"
                    >
                        <strong>{{ item.orders_count }}</strong>
                        <small>{{ item.orders_max_submitted_at_display }}</small>
                    </button>
                    <span v-else class="text-disabled">—</span>
                </template>

                <template #item.avito_chats_count="{ item }">
                    <button
                        v-if="item.avito_chats?.length === 1"
                        type="button"
                        class="entity-avito-button"
                        :class="{ 'has-unread': item.avito_chats[0].is_unread }"
                        :title="`Открыть чат Авито: ${chatTitle(item.avito_chats[0])}`"
                        @click="openChat(item, item.avito_chats[0])"
                    >
                        <v-icon icon="mdi-message-text-outline" size="15" />
                        Чат
                        <span v-if="item.avito_chats[0].is_unread" class="entity-unread-count">{{ item.avito_chats[0].unread_count || 1 }}</span>
                    </button>
                    <v-menu v-else-if="item.avito_chats?.length > 1" location="bottom" max-height="360">
                        <template #activator="{ props: menuProps }">
                            <button
                                v-bind="menuProps"
                                type="button"
                                class="entity-avito-button"
                                :class="{ 'has-unread': item.avito_unread_chats_count > 0 }"
                                :title="`Чаты Авито: ${item.avito_chats_count} · непрочитанных: ${item.avito_unread_chats_count || 0}`"
                            >
                                <v-icon icon="mdi-message-text-outline" size="15" />
                                {{ item.avito_chats_count }}
                                <span v-if="item.avito_unread_chats_count" class="entity-unread-count">{{ item.avito_unread_chats_count }}</span>
                                <v-icon icon="mdi-chevron-down" size="13" />
                            </button>
                        </template>
                        <v-list density="compact" max-width="360" aria-label="Чаты Авито">
                            <v-list-item
                                v-for="chat in item.avito_chats"
                                :key="chat.id"
                                :title="chatTitle(chat)"
                                :subtitle="chat.peer_name && chat.title !== chat.peer_name ? chat.title : undefined"
                                @click="openChat(item, chat)"
                            >
                                <template #append>
                                    <span v-if="chat.is_unread" class="entity-unread-count ml-2">{{ chat.unread_count || 1 }}</span>
                                </template>
                            </v-list-item>
                        </v-list>
                    </v-menu>
                    <span v-else class="text-disabled">—</span>
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
    height: 100%;
    min-height: 0;
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.10);
}

.entities-table-toolbar {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 42px;
    padding: 6px 10px;
    font-size: 0.9rem;
}

.entities-table-search {
    flex: 0 1 260px;
    width: 260px;
    min-width: 180px;
    max-width: 260px;
    margin-left: 10px;
}

.entities-table-search :deep(.v-field) {
    min-height: 36px;
}

.entities-table-body {
    flex: 1 1 auto;
    min-height: 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    padding: 6px;
    overflow: hidden;
}

.entities-data-table {
    flex: 1 1 auto;
    min-height: 0;
    min-width: 0;
}

.entities-data-table :deep(.v-table__wrapper) {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
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

.entity-activity-button,
.entity-avito-button {
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    gap: 5px;
    min-height: 26px;
    max-width: 100%;
    color: #a9d5f8;
    white-space: nowrap;
    border-radius: 4px;
}

.entity-activity-button:hover,
.entity-avito-button:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
}

.entity-activity-button small {
    color: #a8b1bf;
    font-size: 9px;
}

.entity-avito-button.has-unread {
    color: #f2b8d3;
}

.entity-unread-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    padding: 1px 4px;
    border-radius: 8px;
    background: #a63868;
    color: #fff;
    font-size: 9px;
    line-height: 1.4;
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

.entity-phone-list {
    display: grid;
    gap: 1px;
    max-height: 42px;
    overflow: hidden;
}

.entity-phone-line {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
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
