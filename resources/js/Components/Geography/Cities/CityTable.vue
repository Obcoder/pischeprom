<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const props = defineProps({
    cities: {
        type: Array,
        default: () => [],
    },
    totalItems: {
        type: Number,
        default: 0,
    },
    loading: Boolean,
    options: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'update:options',
    'edit',
    'delete',
    'population',
])

const headers = computed(() => [
    {
        title: 'Wiki',
        key: 'wiki_thumbnail',
        sortable: false,
        width: '86px',
        align: 'center',
    },
    {
        title: 'City',
        key: 'name',
        sortable: true,
    },
    {
        title: 'Население',
        key: 'population',
        sortable: true,
        align: 'end',
    },
    {
        title: 'Region / Country',
        key: 'region.name',
        sortable: true,
    },
    {
        title: 'Latitude',
        key: 'latitude',
        sortable: true,
    },
    {
        title: 'Longitude',
        key: 'longitude',
        sortable: true,
    },
    {
        title: 'Связи',
        key: 'relations',
        sortable: false,
        align: 'center',
    },
    {
        title: '',
        key: 'actions',
        sortable: false,
        align: 'end',
        width: '150px',
    },
])

function formatNumber(value) {
    if (value === null || value === undefined || value === '') {
        return '—'
    }

    return new Intl.NumberFormat('ru-RU').format(value)
}

function latestPopulation(city) {
    return city.latest_population?.population
        ?? city.latestPopulation?.population
        ?? city.population
        ?? null
}

function latestPopulationYear(city) {
    return city.latest_population?.year
        ?? city.latestPopulation?.year
        ?? null
}
</script>

<template>
    <v-data-table-server
        :headers="headers"
        :items="cities"
        :items-length="totalItems"
        :loading="loading"
        :page="options.page"
        :items-per-page="options.itemsPerPage"
        :sort-by="options.sortBy"
        item-value="id"
        density="compact"
        fixed-header
        height="720"
        hover
        class="rounded border border-teal-900 bg-slate-950"
        @update:options="emit('update:options', $event)"
    >
        <template #item.wiki_thumbnail="{ item }">
            <div class="d-flex justify-center py-1">
                <a
                    v-if="item.wiki"
                    :href="item.wiki"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Открыть статью в Wikipedia"
                >
                    <v-avatar
                        size="52"
                        rounded="lg"
                        class="border border-teal-700 bg-slate-800 cursor-pointer hover:opacity-80"
                    >
                        <v-img
                            v-if="item.wiki_thumbnail"
                            :src="item.wiki_thumbnail"
                            cover
                        />

                        <span
                            v-else
                            class="text-teal-lighten-3 text-caption"
                        >
                    {{ item.name?.slice(0, 1) }}
                </span>
                    </v-avatar>
                </a>

                <v-avatar
                    v-else
                    size="52"
                    rounded="lg"
                    class="border border-teal-700 bg-slate-800"
                >
                    <v-img
                        v-if="item.wiki_thumbnail"
                        :src="item.wiki_thumbnail"
                        cover
                    />

                    <span
                        v-else
                        class="text-teal-lighten-3 text-caption"
                    >
                {{ item.name?.slice(0, 1) }}
            </span>
                </v-avatar>
            </div>
        </template>

        <template #item.name="{ item, index }">
            <div class="py-1">
                <div class="d-flex align-center">
                    <a
                        v-if="item.yandexmapsgeo"
                        :href="item.yandexmapsgeo"
                        target="_blank"
                        class="inline-flex items-center justify-center mr-1 bg-teal-500 text-white rounded-full text-[7px] font-bold w-4 h-4"
                    >
                        {{ index + 1 }}
                    </a>

                    <Link
                        :href="route('city.show', item.id)"
                        class="text-teal-lighten-3 hover:text-white font-ComfortaaVariableFont text-sm"
                    >
                        {{ item.name }}
                    </Link>
                </div>

                <div
                    v-if="item.wiki_title || item.wiki"
                    class="text-[10px] text-teal-darken-1 mt-1"
                >
                    <a
                        v-if="item.wiki"
                        :href="item.wiki"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="hover:text-teal-lighten-2"
                    >
                        {{ item.wiki_title || item.wiki }}
                    </a>

                    <span v-else>
                        {{ item.wiki_title }}
                    </span>
                </div>

                <div
                    v-if="item.wiki_summary"
                    class="text-[10px] text-grey-lighten-1 mt-1 line-clamp-2"
                >
                    {{ item.wiki_summary }}
                </div>
            </div>
        </template>

        <template #item.population="{ item }">
            <div class="text-right">
                <div class="font-RobotoRegular text-teal-lighten-3">
                    {{ formatNumber(latestPopulation(item)) }}
                </div>

                <div
                    v-if="latestPopulationYear(item)"
                    class="text-[10px] text-grey"
                >
                    {{ latestPopulationYear(item) }}
                </div>

                <v-btn
                    text="years"
                    size="x-small"
                    density="compact"
                    variant="text"
                    color="teal-lighten-3"
                    @click="emit('population', item)"
                />
            </div>
        </template>

        <template #item.region.name="{ item }">
            <div>
                <div class="text-sm text-teal-lighten-4">
                    {{ item.region?.name || '—' }}
                </div>

                <div class="text-[10px] text-teal-darken-1">
                    {{ item.region?.country?.name || '—' }}
                </div>
            </div>
        </template>

        <template #item.latitude="{ item }">
            <span class="text-xs font-mono">
                {{ item.latitude ?? '—' }}
            </span>
        </template>

        <template #item.longitude="{ item }">
            <span class="text-xs font-mono">
                {{ item.longitude ?? '—' }}
            </span>
        </template>

        <template #item.relations="{ item }">
            <div class="d-flex justify-center ga-1">
                <v-chip
                    size="x-small"
                    color="blue-grey"
                    variant="tonal"
                >
                    🏢 {{ item.buildings_count ?? 0 }}
                </v-chip>

                <v-chip
                    size="x-small"
                    color="purple"
                    variant="tonal"
                >
                    E {{ item.entities_count ?? 0 }}
                </v-chip>

                <v-chip
                    size="x-small"
                    color="teal"
                    variant="tonal"
                >
                    U {{ item.units_count ?? 0 }}
                </v-chip>
            </div>
        </template>

        <template #item.actions="{ item }">
            <div class="d-flex justify-end ga-1">
                <v-btn
                    icon="mdi-pencil"
                    size="x-small"
                    variant="text"
                    color="amber"
                    @click="emit('edit', item)"
                />

                <v-btn
                    icon="mdi-delete"
                    size="x-small"
                    variant="text"
                    color="red"
                    @click="emit('delete', item)"
                />
            </div>
        </template>
    </v-data-table-server>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
