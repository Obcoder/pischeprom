<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm} from "@inertiajs/vue3";
defineOptions({
    layout: VerwalterLayout,
})

let countries = ref([]);
let regions = ref([]);
let cities = ref([]);
let buildings = ref([]);
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
        list.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

let tab = ref();

onMounted(()=> {
    arrayData.forEach((element) => {
        loadData(element.name + '.index', element.list);
        console.log(element.list);
    })
})

let searchBuildingsLike = ref();
function indexBuildings(like){
    axios.get(route('buildings.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        buildings.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let showFormBuilding = ref(false);
const formBuilding = useForm({
    address: null,
    city_id: null,
    postcode: null,
})
function storeBuilding(){
    formBuilding.post(route('buildings.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formBuilding.reset();
        },
        only: ['buildings'],
    })
}
</script>

<template>
    <v-container>
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
                                                <v-container>
                                                    <v-row>
                                                        <v-col cols="9">
                                                            <v-text-field v-model="searchBuildingsLike"
                                                                          @input="apiIndexBuildings(searchBuildingsLike)"
                                                                          variant="outlined"
                                                                          density="compact"
                                                                          class="text-sm"
                                                            ></v-text-field>
                                                        </v-col>
                                                        <v-col cols="3">
                                                            <v-btn @click="showFormBuilding = !showFormBuilding"
                                                                   text="+ building"
                                                                   variant="elevated"
                                                                   density="compact"
                                                                   color="yellow"
                                                            ></v-btn>
                                                            <v-dialog v-model="showFormBuilding"
                                                                      width="700"

                                                            >
                                                                <template v-slot:default="{isActive}">
                                                                    <v-card>
                                                                        <v-card-title>Form Building</v-card-title>
                                                                        <v-card-text>
                                                                            <v-form @submit.prevent>
                                                                                <v-row>
                                                                                    <v-col cols="7">
                                                                                        <v-text-field v-model="formBuilding.address"
                                                                                                      label="Address"
                                                                                                      variant="outlined"
                                                                                                      density="comfortable"
                                                                                        ></v-text-field>
                                                                                    </v-col>
                                                                                    <v-col cols="5">
                                                                                        <v-autocomplete :items="cities"
                                                                                                        :item-value="'id'"
                                                                                                        :item-title="'name'"
                                                                                                        v-model="formBuilding.city_id"
                                                                                                        label="Город"
                                                                                                        variant="outlined"
                                                                                                        density="comfortable"
                                                                                                        color="teal"
                                                                                                        chips
                                                                                        ></v-autocomplete>
                                                                                    </v-col>
                                                                                </v-row>
                                                                                <v-row>
                                                                                    <v-col>
                                                                                        <v-text-field v-model="formBuilding.postcode"
                                                                                                      label="Postcode"
                                                                                                      variant="outlined"
                                                                                                      density="comfortable"
                                                                                        ></v-text-field>
                                                                                    </v-col>
                                                                                    <v-col></v-col>
                                                                                    <v-col>
                                                                                        <v-btn @click="storeBuilding"
                                                                                               text="store"
                                                                                               variant="outlined"
                                                                                               density="comfortable"
                                                                                        ></v-btn>
                                                                                    </v-col>
                                                                                </v-row>
                                                                            </v-form>
                                                                        </v-card-text>
                                                                    </v-card>
                                                                </template>
                                                            </v-dialog>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col>
                                                            <v-list :lines="false"
                                                                    density="compact"
                                                            >
                                                                <v-list-item v-for="building in buildings" :key="building.id">
                                                                    <v-list-item-subtitle>
                                                                        {{building.city.name}}
                                                                    </v-list-item-subtitle>
                                                                    <v-list-item-subtitle>
                                                                        {{building.city.region.name}}
                                                                    </v-list-item-subtitle>
                                                                    {{building.address}}
                                                                </v-list-item>
                                                            </v-list>
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
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
