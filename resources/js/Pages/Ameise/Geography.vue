<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let countries = ref();
let regions = ref();
let cities = ref();
let buildings = ref();
let arrayData = [
    countries, regions, cities, buildings,
]

function loadData(routeName, list){
    axios.get(route(routeName)).then(function (response){
        list.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=> {
    arrayData.forEach((element) => {
        loadData('api.' + element, element);
    })
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-card>
                    <v-card-title>Countries</v-card-title>
                    <v-card-text>
                        <v-data-table :items="countries"
                                      density="compact"
                                      hover="true"
                        ></v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                {{regions}}
            </v-col>
        </v-row>
    </v-container>
</template>
