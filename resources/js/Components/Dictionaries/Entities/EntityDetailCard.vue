<script setup>
import { usePhoneFormatter } from '@/Composables/entities/usePhoneFormatter'

defineProps({
    entity: {
        type: Object,
        default: null,
    },
})

const { formatPhones } = usePhoneFormatter()
</script>

<template>
    <v-card min-height="420" class="h-100">
        <v-card-title>Entity detail</v-card-title>

        <v-divider />

        <v-card-text v-if="entity">
            <div class="mb-2"><strong>ID:</strong> {{ entity.id }}</div>
            <div class="mb-2"><strong>Название:</strong> {{ entity.name }}</div>
            <div class="mb-2"><strong>INN:</strong> {{ entity.INN || '—' }}</div>
            <div class="mb-2"><strong>OGRN:</strong> {{ entity.OGRN || '—' }}</div>
            <div class="mb-2"><strong>Классификация:</strong> {{ entity.classification?.name || '—' }}</div>
            <div class="mb-2"><strong>Страна:</strong> {{ entity.country?.name || '—' }}</div>
            <div class="mb-2"><strong>Города:</strong> {{ entity.cities?.map(i => i.name).join(', ') || '—' }}</div>
            <div class="mb-2"><strong>Здания:</strong> {{ entity.buildings?.map(i => i.address).join(', ') || '—' }}</div>
            <div class="mb-2"><strong>Email:</strong> {{ entity.emails?.map(i => i.address).join(', ') || '—' }}</div>
            <div class="mb-2"><strong>Телефоны:</strong> {{ formatPhones(entity.telephones || []) || '—' }}</div>
            <div class="mb-2"><strong>Units:</strong> {{ entity.units?.map(i => i.name).join(', ') || '—' }}</div>
            <div class="mb-2"><strong>Chats:</strong> {{ entity.chats?.map(i => i.numbers).join(', ') || '—' }}</div>
            <div class="mb-2"><strong>Продаж:</strong> {{ entity.sales_count ?? 0 }}</div>
            <div class="mb-2">
                <strong>Последний purchase:</strong>
                {{ entity.last_purchase_date ? new Date(entity.last_purchase_date).toLocaleString() : '—' }}
            </div>
        </v-card-text>

        <v-card-text v-else class="text-medium-emphasis">
            Нажми на название Entity в таблице
        </v-card-text>
    </v-card>
</template>
