<script setup>
import { computed } from 'vue'

const search = defineModel('search', {
    type: String,
    default: '',
})

const filters = defineModel('filters', {
    type: Object,
    required: true,
})

const props = defineProps({
    loading: Boolean,
    mailboxes: {
        type: Array,
        default: () => [],
    },
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

const mailboxOptions = computed(() => [
    {
        title: 'Все ящики',
        value: null,
    },
    ...props.mailboxes.map((mailbox) => ({
        title: mailbox.label || mailbox.address,
        value: mailbox.address,
    })),
])
</script>

<template>
    <v-row dense>
        <v-col cols="12" lg="4">
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
                v-model="filters.mailbox"
                :items="mailboxOptions"
                label="Ящик"
                variant="outlined"
                density="compact"
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

        <v-col cols="12" lg="2">
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
