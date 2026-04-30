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
    loading: Boolean,
})

const emit = defineEmits([
    'refresh',
])

const directionOptions = [
    {
        title: 'Все',
        value: null,
    },
    {
        title: 'Входящие',
        value: 'incoming',
    },
    {
        title: 'Исходящие',
        value: 'outgoing',
    },
]

const folderOptions = [
    {
        title: 'Все',
        value: null,
    },
    {
        title: 'INBOX',
        value: 'INBOX',
    },
    {
        title: 'Sent',
        value: 'Sent',
    },
]
</script>

<template>
    <v-row dense>
        <v-col cols="12" lg="5">
            <v-text-field
                v-model="search"
                label="Поиск по теме, адресу, тексту"
                prepend-inner-icon="mdi-magnify"
                variant="solo"
                density="compact"
                clearable
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="2">
            <v-select
                v-model="filters.direction"
                :items="directionOptions"
                label="Тип"
                variant="outlined"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="2">
            <v-select
                v-model="filters.folder"
                :items="folderOptions"
                label="Папка"
                variant="outlined"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" lg="3">
            <div class="d-flex justify-end">
                <v-btn
                    prepend-icon="mdi-refresh"
                    text="Обновить"
                    color="blue"
                    variant="tonal"
                    density="compact"
                    :loading="loading"
                    @click="emit('refresh')"
                />
            </div>
        </v-col>
    </v-row>
</template>
