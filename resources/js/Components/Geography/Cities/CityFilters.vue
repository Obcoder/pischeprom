<script setup>
const filters = defineModel('filters', {
    type: Object,
    required: true,
})

defineProps({
    countries: {
        type: Array,
        default: () => [],
    },
    regions: {
        type: Array,
        default: () => [],
    },
    units: {
        type: Array,
        default: () => [],
    },
    entities: {
        type: Array,
        default: () => [],
    },
    loadingCountries: Boolean,
    loadingRegions: Boolean,
    loadingUnits: Boolean,
    loadingEntities: Boolean,
})

defineEmits(['reset'])
</script>

<template>
    <v-row dense class="mb-3">
        <v-col cols="12" lg="3">
            <v-text-field
                v-model="filters.search"
                label="Поиск: город, регион, страна, Wikipedia"
                variant="solo"
                density="compact"
                color="teal-lighten-3"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" md="6" lg="2">
            <v-autocomplete
                v-model="filters.country_id"
                :items="countries"
                :loading="loadingCountries"
                item-title="name"
                item-value="id"
                label="Страна"
                variant="outlined"
                density="compact"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" md="6" lg="2">
            <v-autocomplete
                v-model="filters.region_id"
                :items="regions"
                :loading="loadingRegions"
                item-title="name"
                item-value="id"
                label="Регион"
                variant="outlined"
                density="compact"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" md="6" lg="2">
            <v-autocomplete
                v-model="filters.unit_id"
                :items="units"
                :loading="loadingUnits"
                item-title="name"
                item-value="id"
                label="Unit"
                variant="outlined"
                density="compact"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" md="6" lg="2">
            <v-autocomplete
                v-model="filters.entity_id"
                :items="entities"
                :loading="loadingEntities"
                item-title="name"
                item-value="id"
                label="Entity"
                variant="outlined"
                density="compact"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="1">
            <v-select
                v-model="filters.has_buildings"
                :items="[
                    { title: 'Все', value: null },
                    { title: 'Есть здания', value: true },
                    { title: 'Нет зданий', value: false },
                ]"
                label="Buildings"
                variant="outlined"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" class="d-flex justify-end">
            <v-btn
                text="Сбросить фильтры"
                color="grey"
                variant="text"
                density="compact"
                @click="$emit('reset')"
            />
        </v-col>
    </v-row>
</template>
