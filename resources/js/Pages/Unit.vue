<script setup>
import { useDate } from 'vuetify'
import {onMounted, ref} from "vue";
const date = useDate()
const props = defineProps({
    unit: Object,
})
let headersConsumptions = ref();
let da = new Date();
function timeDiff(time){
    let d = new Date();
    let diffMilliseconds = d - Date.parse(time);
    let denominator = 1000 * 3600 * 24;
    return Math.round(diffMilliseconds/denominator);
}

onMounted(()=>{
    headersConsumptions.value = [
        {
            title: 'created',
            key: 'created_at',
        },
        {
            title: 'Product',
            key: 'product_id',
        },
        {
            title: 'Quantity',
            key: 'quantity',
        },
        {
            title: 'Measure',
            key: 'measure_id',
        },
    ]
})
</script>

<template>
    <Head>
        <title>Unit</title>
    </Head>

    <v-container>
        <v-row class="h-1/3"
        >
            <v-col cols="2">
                <v-card>
                    <v-card-title class="bg-orange-accent-3">
                        {{unit.name}}</v-card-title>
                    <v-card-subtitle class="d-flex">
                        <v-sheet>
                            <div v-for="uri in unit.uris"
                            >
                                <a :href="uri.address"
                                   target="_blank"
                                >
                                    {{uri.address}}
                                </a>
                            </div>
                        </v-sheet>
                    </v-card-subtitle>
                    <v-card-subtitle>
                        <v-sheet>
                            <div v-for="label in unit.labels">
                                {{label.name}}
                            </div>
                        </v-sheet>
                    </v-card-subtitle>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-card-title class="bg-gray-100">
                        Stages
                    </v-card-title>
                    <v-card-text class="text-amber">
                        <v-row v-for="stage in unit.stages">
                            <v-col>
                                <v-chip >
                                    {{stage.name}}
                                </v-chip>
                            </v-col>
                            <v-col>
                                <v-card title="Timing">
                                    <v-card-subtitle>
                                        <v-sheet class="font-sans text-sm">
                                            {{date.format(stage.pivot.startDate, 'normalDate')}}
                                        </v-sheet>
                                        <v-sheet>
                                            {{date.format(stage.pivot.startDate, 'year')}}
                                        </v-sheet>
                                    </v-card-subtitle>
                                    <v-card-text>
                                        Дней прошло:
                                        <v-chip>
                                            {{timeDiff(stage.pivot.startDate)}}
                                        </v-chip>
                                    </v-card-text>
                                </v-card>
                            </v-col>
                        </v-row>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
            <v-col cols="4">
                <v-card title="Consumptions">
                    <v-data-table :items="unit.consumptions"
                                  :headers="headersConsumptions"
                                  density="comfortable"
                                  hover="hover"
                                  class="text-sm"
                    >
                        <template v-slot:item.product="{item}">
                            {{item.product.name}}
                        </template>
                    </v-data-table>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
