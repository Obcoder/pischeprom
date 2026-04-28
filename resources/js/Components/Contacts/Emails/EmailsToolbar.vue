<script setup>
const search = defineModel('search', {
    type: String,
    default: '',
})

const filters = defineModel('filters', {
    type: Object,
    required: true,
})

defineProps({
    units: {
        type: Array,
        default: () => [],
    },
    entities: {
        type: Array,
        default: () => [],
    },
    domains: {
        type: Array,
        default: () => [],
    },
    loading: Boolean,
    syncing: Boolean,
})

const emit = defineEmits([
    'create',
    'refresh',
    'sync-yandex',
])

const activeOptions = [
    {
        title: 'Все',
        value: null,
    },
    {
        title: 'Активные',
        value: true,
    },
    {
        title: 'Отключённые',
        value: false,
    },
]
</script>

<template>
    <v-row dense>
        <v-col cols="12" lg="3">
            <v-text-field
                v-model="search"
                label="Поиск email / домен / entity / unit"
                prepend-inner-icon="mdi-magnify"
                variant="solo"
                density="compact"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="2">
            <v-select
                v-model="filters.is_active"
                :items="activeOptions"
                label="Статус"
                variant="outlined"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="2">
            <v-select
                v-model="filters.domain"
                :items="domains"
                label="Домен"
                variant="outlined"
                density="compact"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="2">
            <v-autocomplete
                v-model="filters.unit_ids"
                :items="units"
                item-value="id"
                item-title="name"
                label="Units"
                variant="outlined"
                density="compact"
                multiple
                chips
                closable-chips
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="2">
            <v-autocomplete
                v-model="filters.entity_ids"
                :items="entities"
                item-value="id"
                item-title="name"
                label="Entities"
                variant="outlined"
                density="compact"
                multiple
                chips
                closable-chips
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="1">
            <div class="d-flex justify-end ga-1">
                <v-btn
                    icon="mdi-refresh"
                    size="small"
                    variant="tonal"
                    color="teal"
                    :loading="loading"
                    @click="emit('refresh')"
                />

                <v-btn
                    icon="mdi-plus"
                    size="small"
                    variant="elevated"
                    color="teal"
                    @click="emit('create')"
                />
            </div>
        </v-col>
    </v-row>

    <v-row dense class="mt-1">
        <v-col cols="12" lg="3">
            <v-switch
                v-model="filters.has_incoming"
                label="Есть входящие"
                color="teal"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="3">
            <v-switch
                v-model="filters.has_outgoing"
                label="Есть исходящие"
                color="purple"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="6">
            <div class="d-flex justify-end">
                <v-btn
                    prepend-icon="mdi-email-sync"
                    text="Sync Yandex"
                    variant="tonal"
                    color="blue"
                    density="compact"
                    :loading="syncing"
                    @click="emit('sync-yandex')"
                />
            </div>
        </v-col>
    </v-row>
</template>
