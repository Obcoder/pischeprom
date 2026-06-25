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
    lockedDirection: Boolean,
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

const todayActive = computed(() => Boolean(filters.value.today))

function toggleToday() {
    filters.value.today = !filters.value.today
}
</script>

<template>
    <v-row dense>
        <v-col cols="12" xl="4" lg="3">
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

        <v-col cols="12" md="4" lg="2">
            <v-select
                v-model="filters.mailbox"
                :items="mailboxOptions"
                label="Ящик"
                variant="outlined"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" md="4" lg="2">
            <v-select
                v-model="filters.direction"
                :items="directionOptions"
                label="Тип"
                variant="outlined"
                density="compact"
                :disabled="lockedDirection"
                hide-details
            />
        </v-col>

        <v-col cols="12" md="4" lg="2">
            <v-select
                v-model="filters.folder"
                :items="folderOptions"
                label="Папка"
                variant="outlined"
                density="compact"
                hide-details
            />
        </v-col>

        <v-col cols="12" xl="2" lg="3">
            <div class="d-flex justify-end ga-2 flex-wrap">
                <v-btn
                    prepend-icon="mdi-calendar-today-outline"
                    text="Почта сегодня"
                    :color="todayActive ? 'amber' : 'blue-grey'"
                    :variant="todayActive ? 'elevated' : 'tonal'"
                    density="compact"
                    @click="toggleToday"
                />

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
