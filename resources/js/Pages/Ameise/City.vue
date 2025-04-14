<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    city: Object,
})

</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <span class="text-xl"
                >
                    {{city.name}}</span>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
            <v-col>
                <v-card>
                    <v-card-title>Buldings</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="building in city.buildings">
                                {{building.address}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-card-title>Entities</v-card-title>
                    <v-card-text>
                        <div v-for="entity in city.entities"
                             class="p-2 my-1 mt-0 font-UnderdogRegular text-sm text-fuchsia-900 border border-fuchsia-950 rounded"
                             >
                            <span>{{entity.name}}</span>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
