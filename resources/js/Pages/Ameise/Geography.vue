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

function loadData(routeName, list, like){
    axios.get(route(routeName), {
        params: {
            search: like,
        }
    }).then(function (response){
        list.value.splice(0, list.value.length, ...response.data);
        console.log(buildings.value);
    }).catch(function (error){
        console.log(error);
    })
}

let tab = ref();

let searchBuildingsLike = ref();

onMounted(()=> {
    arrayData.forEach((element) => {
        loadData('api.' + element.name, element.list);
    })
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col>
                <v-card>
                    <v-tabs v-model="tab"
                            align-tabs="center"
                            color="deep-purple-accent-4"
                    >
                        <v-tab value="countries">Countries</v-tab>
                        <v-tab value="cities">Cities</v-tab>
                    </v-tabs>
                    <v-card-text>
                        <v-tabs-window v-model="tab">
                            <v-tabs-window-item value="countries">
                                <v-row>
                                    <v-col>
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
                                    <v-col>
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
                                </v-row>
                            </v-tabs-window-item>
                            <v-tabs-window-item value="cities"
                            >
                                <v-row>
                                    <v-col>
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
                                    <v-col>
                                        <v-card>
                                            <v-card-title>Buildings</v-card-title>
                                            <v-card-text>
                                                <v-text-field v-model="searchBuildingsLike"
                                                              @input="loadData('api.buildings', buildings, searchBuildingsLike)"
                                                              variant="outlined"
                                                              density="compact"
                                                              class="text-sm"
                                                ></v-text-field>
                                                <v-list>
                                                    <v-list-item v-for="building in buildings" :key="building.id">
                                                        {{building.address}}
                                                    </v-list-item>
                                                </v-list>
                                            </v-card-text>
                                        </v-card>
                                    </v-col>
                                </v-row>
                            </v-tabs-window-item>
                        </v-tabs-window>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>
