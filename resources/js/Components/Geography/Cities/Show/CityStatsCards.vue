<script setup>
defineProps({
    city: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <v-row dense>
        <v-col cols="12" md="4">
            <v-card class="rounded border border-teal-900 bg-slate-950">
                <v-card-text>
                    <div class="text-caption text-teal-darken-1">Buildings</div>
                    <div class="text-h5 text-teal-lighten-3">
                        {{ city.buildings_count ?? city.buildings?.length ?? 0 }}
                    </div>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" md="4">
            <v-card class="rounded border border-purple-900 bg-slate-950">
                <v-card-text>
                    <div class="text-caption text-purple-lighten-2">Entities</div>
                    <div class="text-h5 text-purple-lighten-3">
                        {{ city.entities_count ?? city.entities?.length ?? 0 }}
                    </div>
                </v-card-text>
            </v-card>
        </v-col>

        <v-col cols="12" md="4">
            <v-card class="rounded border border-blue-grey bg-slate-950">
                <v-card-text>
                    <div class="text-caption text-blue-grey-lighten-2">Units</div>
                    <div class="text-h5 text-blue-grey-lighten-3">
                        {{ city.units_count ?? city.units?.length ?? 0 }}
                    </div>
                </v-card-text>
            </v-card>
        </v-col>
    </v-row>
</template>
