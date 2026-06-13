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
    { title: 'Города', key: 'city_names', sortable: false },
    { title: 'Дом', key: 'buildings', sortable: true },
    { title: 'Телефоны', key: 'telephones_display', sortable: false },
    { title: 'Продаж', key: 'sales_count', sortable: true },
    { title: 'Последний purchase', key: 'last_purchase_date', sortable: true },
    { title: 'Страна', key: 'country_name', sortable: false },
    { title: '', key: 'actions', sortable: false, width: 170 },
]

const filterMenu = computed({
    get: () => props.filtersOpened,
    set: value => emit('update:filtersOpened', !!value),
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
</script>

<template>
    <v-card class="w-100 entities-table-card">
        <v-card-title class="d-flex align-center flex-wrap ga-2">
            <span>Entities</span>

            <v-chip size="small" variant="tonal" color="blue-grey-lighten-2">
                {{ totalItems }}
            </v-chip>

            <v-spacer />

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

            <v-btn
                :variant="groupByCities ? 'flat' : 'tonal'"
                color="secondary"
                @click="emit('update:groupByCities', !groupByCities)"
            >
                {{ groupByCities ? 'Без группировки' : 'По городам' }}
            </v-btn>
        </v-card-title>

        <v-card-text>
            <v-data-table-server
                class="text-caption"
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
                height="770"
                hover
                @update:options="onOptionsUpdate"
            >
                <template #item.name="{ item }">
                    <a
                        href="#"
                        class="entity-name-link"
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

                <template #item.last_purchase_date="{ item }">
                    {{ item.last_purchase_date ? new Date(item.last_purchase_date).toLocaleDateString() : '—' }}
                </template>

                <template #item.actions="{ item }">
                    <div class="d-flex ga-1 justify-end">
                        <v-btn
                            icon="mdi-eye-outline"
                            size="small"
                            variant="text"
                            title="Открыть панель"
                            @click="emit('show', item)"
                        />

                        <v-btn
                            icon="mdi-open-in-new"
                            size="small"
                            variant="text"
                            title="Перейти в карточку"
                            :href="route('Ameise.entity.show', item.id)"
                        />

                        <v-btn
                            icon="mdi-pencil"
                            size="small"
                            variant="text"
                            title="Редактировать"
                            @click="emit('edit', item)"
                        />

                        <v-btn
                            icon="mdi-delete-outline"
                            size="small"
                            variant="text"
                            color="error"
                            title="Удалить"
                            @click="emit('delete', item)"
                        />
                    </div>
                </template>
            </v-data-table-server>

            <div class="d-flex flex-wrap ga-2 pa-2 mt-2 border-t">
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
    border: 1px solid rgba(255, 255, 255, 0.10);
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
    gap: 2px;
    max-width: 240px;
}

.entity-building-list span {
    color: rgba(255, 255, 255, 0.78);
    font-size: 10px;
    font-weight: 700;
    line-height: 1.2;
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
