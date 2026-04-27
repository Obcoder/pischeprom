<script setup>
defineProps({
    city: {
        type: Object,
        required: true,
    },
})
</script>

<template>
    <v-card class="rounded border border-purple-900 bg-slate-950 h-100">
        <v-card-title class="text-purple-lighten-3">
            Entities
        </v-card-title>

        <v-card-text>
            <v-list
                v-if="city.entities?.length"
                density="compact"
                class="bg-transparent"
            >
                <v-list-item
                    v-for="entity in city.entities"
                    :key="entity.id"
                    class="rounded mb-1 hover:bg-slate-800"
                >
                    <template #title>
                        <div class="text-sm text-purple-lighten-3">
                            {{ entity.name }}
                        </div>
                    </template>

                    <template #subtitle>
                        <div class="text-[11px] text-grey">
                            {{ entity.classification?.name ?? 'classification: —' }}
                        </div>
                    </template>
                </v-list-item>
            </v-list>

            <div
                v-else
                class="text-grey text-sm"
            >
                Связанные сущности пока не указаны.
            </div>
        </v-card-text>
    </v-card>
</template>
