<script setup>
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
    city: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <v-card class="rounded border border-blue-grey bg-slate-950 h-100">
        <v-card-title class="text-blue-grey-lighten-3">
            Units
        </v-card-title>

        <v-card-text>
            <v-list
                v-if="city.units?.length"
                density="compact"
                class="bg-transparent"
            >
                <v-list-item
                    v-for="unit in city.units"
                    :key="unit.id"
                    class="rounded mb-1 hover:bg-slate-800"
                >
                    <template #title>
                        <Link
                            :href="route('web.unit.show', unit.id)"
                            class="text-sm text-blue-grey-lighten-3 hover:text-white"
                        >
                            {{ unit.name }}
                        </Link>
                    </template>

                    <template #subtitle>
                        <div class="text-[11px] text-grey">
                            id: {{ unit.id }}
                        </div>
                    </template>
                </v-list-item>
            </v-list>

            <div
                v-else
                class="text-grey text-sm"
            >
                Units пока не привязаны.
            </div>
        </v-card-text>
    </v-card>
</template>
