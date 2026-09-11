<script setup>
import { computed } from 'vue'

const props = defineProps({
    entity: { type: Object, default: null },
})

const cities = computed(() => {
    const items = [...(props.entity?.cities || []), ...(props.entity?.buildings || []).map(building => building.city)]
    return [...new Map(items.filter(city => city?.id).map(city => [city.id, city])).values()]
})
const buildings = computed(() => props.entity?.buildings || [])
</script>

<template>
    <section class="entity-geography" aria-label="География">
        <div class="entity-geography__heading">
            <v-icon icon="mdi-map-marker-radius-outline" size="17" />
            <h2>География</h2>
            <span v-if="entity?.country?.name" class="entity-geography__country">{{ entity.country.name }}</span>
        </div>
        <div v-if="cities.length" class="entity-geography__cities">
            <span v-for="city in cities" :key="city.id" class="entity-geography__city">
                {{ city.name }}<small v-if="city.region?.name"> · {{ city.region.name }}</small>
            </span>
        </div>
        <p v-else class="entity-geography__empty">Города не указаны</p>
        <div v-if="buildings.length" class="entity-geography__addresses">
            <div v-for="building in buildings" :key="building.id" class="entity-geography__address">
                <v-icon icon="mdi-office-building-outline" size="15" />
                <span>{{ [building.postcode, building.city?.name, building.address].filter(Boolean).join(', ') }}</span>
            </div>
        </div>
        <p v-else class="entity-geography__empty">Здания не указаны</p>
    </section>
</template>

<style scoped>
.entity-geography {
    display: grid;
    align-content: start;
    gap: 9px;
    min-width: 0;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.07);
}
.entity-geography__heading { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; }
.entity-geography h2 { font-size: 0.85rem; font-weight: 700; margin: 0; }
.entity-geography__country { margin-left: auto; font-size: 0.75rem; color: #efd9d7; }
.entity-geography__cities { display: flex; flex-wrap: wrap; gap: 6px; }
.entity-geography__city { padding: 3px 8px; border-radius: 6px; background: rgba(255,255,255,0.12); font-size: 0.8rem; }
.entity-geography__city small { color: #efd9d7; font-size: 0.72rem; }
.entity-geography__addresses { display: grid; gap: 6px; max-height: 160px; overflow-y: auto; }
.entity-geography__address { display: flex; align-items: baseline; gap: 7px; font-size: 0.78rem; line-height: 1.4; overflow-wrap: anywhere; }
.entity-geography__empty { margin: 0; color: #dfc2be; font-size: 0.78rem; }
</style>
