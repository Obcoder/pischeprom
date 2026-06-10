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
    <BaseSectionCard title="Sales" icon="mdi-currency-usd" compact>
        <div class="unit-sales__list">
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

            <div
                v-if="!entities.some((entity) => entity.sales?.length)"
                class="unit-sales__empty"
            >
                Sales not found
            </div>
        </div>
    </BaseSectionCard>
</template>

<style scoped>
.unit-sales__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 360px;
    overflow: auto;
}

.unit-sales__empty {
    padding: 20px 12px;
    border: 1px dashed rgba(128, 0, 32, 0.18);
    border-radius: 12px;
    color: #887a80;
    font-size: 0.78rem;
    text-align: center;
}
</style>
