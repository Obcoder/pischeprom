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
    { name: 'countries', list: countries },
    { name: 'regions', list: regions },
    { name: 'cities', list: cities },
    { name: 'buildings', list: buildings },
];

function loadData(routeName, list){
    axios.get(route(routeName)).then(function (response){
        list.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=> {
    arrayData.forEach((element) => {
        loadData('api.' + element.name, element.list);
    })
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col cols="1">
                <v-card>
                    <v-card-title>Countries</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="country in countries"
                                         class="text-[12px] font-sans"
                            >
                                {{country.name}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="3">
                <v-card>
                    <v-card-title>Cities</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="city in cities">
                                {{city.name}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="1">
                <v-card>
                    <v-card-title>Regions</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="region in regions"
                                         class="text-[12px] font-sans"
                            >
                                {{region.name}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="5">
                <v-card>
                    <v-card-text>Buildings</v-card-text>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="building in buildings">
                                {{building.address}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
