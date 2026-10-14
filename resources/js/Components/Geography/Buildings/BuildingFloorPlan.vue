<script setup>
import { computed } from 'vue'
import { floorGroups, roomTitle } from '@/utils/buildingFloors'
const props = defineProps({ building: { type: Object, required: true } })
const emit = defineEmits(['open'])
const groups = computed(() => floorGroups(props.building))
</script>
<template>
    <div class="building-floor-plan" aria-label="Этажи и помещения">
        <div v-for="group in groups" :key="group.id ?? 'none'" class="floor-line">
            <button class="floor-number" :title="group.label" :aria-label="`Открыть ${group.label}`" @click="emit('open', { floorId: group.id })">{{ group.floor ? `${group.floor.number} эт.` : 'Без эт.' }}</button>
            <div class="floor-rooms"><button v-for="room in group.rooms" :key="room.id" :class="['room-chip', room.type, { linked: room.entities?.length }]" :title="roomTitle(room)" :aria-label="`Открыть ${roomTitle(room)}`" @click="emit('open', { roomId: room.id, floorId: room.building_floor_id })"><span v-if="room.type !== 'apartment'" class="room-kind">{{ room.type === 'office' ? 'оф.' : 'п.' }}</span>{{ room.number }}<sup v-if="room.entities?.length">{{ room.entities.length }}</sup></button><span v-if="!group.rooms.length" class="floor-empty">нет помещений</span></div>
        </div>
    </div>
</template>
<style scoped>
.building-floor-plan{max-height:160px;overflow:auto;min-width:240px;padding:2px 0;font:10px/1.3 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}.floor-line{display:grid;grid-template-columns:42px minmax(0,1fr);gap:5px;align-items:start;padding:3px 0;border-bottom:1px solid #edf0f1}.floor-line:last-child{border:0}.floor-number{font:inherit;color:#6c7780;text-align:right;padding:2px 4px;border:0;background:transparent;white-space:nowrap;cursor:pointer}.floor-rooms{display:flex;flex-wrap:wrap;gap:3px}.room-chip{font:inherit;border:1px solid #dce1e5;background:#fafbfc;color:#4e5d68;border-radius:3px;padding:1px 4px;min-height:18px;cursor:pointer;max-width:160px;overflow-wrap:anywhere}.room-chip.linked{background:#edf5f0;border-color:#bcd3c4;color:#355e43}.room-kind{font-size:8px;color:#8a959b;margin-right:2px}.room-chip sup{font-size:8px;position:static;margin-left:3px;color:#73947d}.floor-empty{color:#a0a7ac;font-size:9px;padding:2px}.room-chip:hover,.floor-number:hover{background:#e4edf5;color:#294e70}button:focus-visible{outline:2px solid #7aa3ca;outline-offset:1px}
</style>
