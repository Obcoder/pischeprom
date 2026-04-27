<script setup>
const props = defineProps({
    city: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <v-card class="rounded border border-teal-900 bg-slate-950 h-100">
        <v-card-title class="text-teal-lighten-3">
            Buildings
        </v-card-title>

        <v-card-text>
            <v-list
                v-if="city.buildings?.length"
                density="compact"
                class="bg-transparent"
            >
                <v-list-item
                    v-for="building in city.buildings"
                    :key="building.id"
                    class="rounded mb-1 hover:bg-slate-800"
                >
                    <template #title>
                        <div class="text-sm text-teal-lighten-3">
                            {{ building.address }}
                        </div>
                    </template>

                    <template #subtitle>
                        <div class="text-[11px] text-grey">
                            postcode: {{ building.postcode ?? '—' }}
                        </div>

                        <div
                            v-if="building.units?.length"
                            class="mt-1 d-flex flex-wrap ga-1"
                        >
                            <v-chip
                                v-for="unit in building.units"
                                :key="unit.id"
                                size="x-small"
                                color="teal"
                                variant="tonal"
                            >
                                {{ unit.name }}
                            </v-chip>
                        </div>
                    </template>
                </v-list-item>
            </v-list>

            <div
                v-else
                class="text-grey text-sm"
            >
                Здания пока не привязаны.
            </div>
        </v-card-text>
    </v-card>
</template>
