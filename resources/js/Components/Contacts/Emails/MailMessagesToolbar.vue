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
    hasFilters: Boolean,
    mailboxes: {
        type: Array,
        default: () => [],
    },
    lockedDirection: Boolean,
})

const emit = defineEmits([
    'reset',
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
    <div class="mail-filters">
        <v-text-field
            v-model="search"
            label="Поиск по теме, адресу, тексту"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            clearable
            hide-details
            class="mail-filters__search"
        />
        <v-select v-model="filters.mailbox" :items="mailboxOptions" label="Ящик" variant="outlined" density="compact" hide-details />
        <v-select v-model="filters.direction" :items="directionOptions" label="Тип" variant="outlined" density="compact" :disabled="lockedDirection" hide-details />
        <v-select v-model="filters.folder" :items="folderOptions" label="Папка" variant="outlined" density="compact" hide-details />
        <v-btn
            icon="mdi-filter-remove-outline"
            size="28"
            color="blue-grey-lighten-2"
            variant="text"
            title="Сбросить фильтры"
            aria-label="Сбросить фильтры"
            :disabled="!hasFilters"
            @click="emit('reset')"
        />
    </div>
</template>

<style scoped>
.mail-filters { display: grid; grid-template-columns: minmax(200px, 2fr) minmax(150px, 1fr) minmax(120px, .8fr) minmax(110px, .8fr) 28px; align-items: center; gap: 8px; flex: 0 0 auto; }
.mail-filters :deep(.v-field) { font-size: 12px; --v-input-control-height: 32px; --v-field-padding-top: 4px; --v-field-padding-bottom: 4px; }
.mail-filters :deep(.v-field__input) { min-height: 32px; padding-top: 4px; padding-bottom: 4px; }
.mail-filters :deep(.v-field__append-inner), .mail-filters :deep(.v-field__prepend-inner), .mail-filters :deep(.v-field__clearable) { padding-top: 4px; }
.mail-filters :deep(.v-label) { font-size: 12px; }
@media (max-width: 700px) {
    .mail-filters { grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1fr) 28px; gap: 6px; }
    .mail-filters__search { grid-column: 1 / -1; }
    .mail-filters > .v-btn { grid-column: 4; grid-row: 2; }
}
</style>
