<script setup>
import { ref, watch } from 'vue'
import UnitLeadsPanel from '@/Components/Unit/UnitLeadsPanel.vue'
import UnitOrdersPanel from '@/Components/Unit/UnitOrdersPanel.vue'

const props = defineProps({
    unit: { type: Object, required: true },
    canViewOrders: { type: Boolean, default: false },
    canCreateOrders: { type: Boolean, default: false },
})
const emit = defineEmits(['refresh'])
const tab = ref('leads')

watch(() => props.canViewOrders, (allowed) => {
    if (!allowed && tab.value === 'orders') tab.value = 'leads'
})
</script>

<template>
    <section class="unit-activity" aria-label="Лиды и заказы Unit">
        <v-tabs v-model="tab" color="#352345" density="compact" height="38" class="unit-activity__tabs" show-arrows>
            <v-tab value="leads">Лиды</v-tab>
            <v-tab v-if="canViewOrders" value="orders">Заказы</v-tab>
        </v-tabs>
        <v-window v-model="tab" class="unit-activity__window" :touch="false">
            <v-window-item value="leads" :transition="false" :reverse-transition="false">
                <UnitLeadsPanel :unit="unit" @refresh="emit('refresh')" />
            </v-window-item>
            <v-window-item v-if="canViewOrders" value="orders" :transition="false" :reverse-transition="false">
                <UnitOrdersPanel :unit="unit" :can-view-orders="canViewOrders" :can-create-orders="canCreateOrders" />
            </v-window-item>
        </v-window>
    </section>
</template>

<style scoped>
.unit-activity { display: flex; flex-direction: column; height: 100%; min-height: 0; min-width: 0; overflow: hidden; border: 1px solid #d9d7dc; background: #fff; }
.unit-activity__tabs { flex-shrink: 0; border-bottom: 1px solid #d8d6db; }
.unit-activity__tabs :deep(.v-tab) { text-transform: none; letter-spacing: 0; font-size: 12px; padding: 0 14px; }
.unit-activity__window { flex: 1 1 auto; min-height: 0; min-width: 0; }
.unit-activity__window :deep(.v-window__container) { height: 100%; min-height: 0; min-width: 0; }
.unit-activity__window :deep(.v-window-item) { height: 100%; min-height: 0; min-width: 0; }
</style>
