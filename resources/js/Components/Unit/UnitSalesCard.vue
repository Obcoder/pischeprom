<script setup>
import { useDate } from 'vuetify'
import BaseSectionCard from '@/Components/Unit/BaseSectionCard.vue'

defineProps({
    entities: {
        type: Array,
        default: () => [],
    },
})

const date = useDate()
</script>

<template>
    <BaseSectionCard title="Sales" icon="mdi-currency-usd">
        <div class="d-flex flex-column ga-4">
            <template
                v-for="entity in entities"
                :key="entity.id"
            >
                <v-sheet
                    v-if="entity.sales?.length"
                    rounded="lg"
                    border
                    class="pa-3"
                >
                    <div class="font-weight-bold mb-3">
                        {{ entity.name }}
                    </div>

                    <v-list density="compact" class="pa-0">
                        <v-list-item
                            v-for="sale in entity.sales"
                            :key="sale.id"
                            class="px-0"
                        >
                            <template #title>
                                <div class="d-flex justify-space-between">
                                    <span>{{ date.format(sale.date, 'fullDate') }}</span>
                                    <span class="font-weight-medium">{{ sale.total }}</span>
                                </div>
                            </template>
                        </v-list-item>
                    </v-list>
                </v-sheet>
            </template>
        </div>
    </BaseSectionCard>
</template>
