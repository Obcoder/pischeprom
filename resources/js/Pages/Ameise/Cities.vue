<script setup>
import {Link, useForm} from "@inertiajs/vue3";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useHead} from "@vueuse/head";
defineOptions({
    layout: VerwalterLayout,
})

const headersCities = [
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'Регион',
        key: 'region_id',
    },
    {
        title: 'Широта',
        key: 'latitude',
    },
    {
        title: 'Долгота',
        key: 'longitude',
    },
    {
        title: 'population',
        key: 'population',
    },
    {
        title: 'wiki',
        key: 'wiki',
    },
    {
        title: 'Maps',
        key: 'yandexmapsgeo',
    },
]

const cities = ref()
const regions = ref()
let searchCitiesLike = ref();

function indexCities(like){
    axios.get(route('cities.index'), {
        params: {
            search: like,
        }
    }).then(function (response){
        cities.value = response.data;
    }).catch(function (error){
        console.log(error);
    });
}
function indexRegions(){
    axios.get(route('regions.index')).then(function (response){
        regions.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

const formCity = useForm({
    name: null,
    population: null,
    wiki: null,
    region_id: null,
    yandexmapsgeo: null,
    twogis: null,
    latitude: null,
    longitude: null,
})
function storeCity(){
    formCity.post(route('cities.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCity.reset();
            indexCities(searchCitiesLike.value)
        },
    })
}

onMounted(()=>{
    indexCities()
    indexRegions()
})

useHead({
    title: `Cities: Города, пгт, сёла, деревни, станицы`,
    meta: [
        {
            name: 'description',
            content: `Все cities in db`,
        }
    ]
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col></v-col>
            <v-col cols="11">
                <v-data-table :items="cities"
                              :headers="headersCities"
                              items-per-page="200"
                              density="compact"
                              hover="hover"
                >
                    <template v-slot:top>
                        <v-row>
                            <v-col>
                                <v-text-field v-model="searchCitiesLike"
                                              @input="indexCities(searchCitiesLike)"
                                              label="Поиск городов по like"
                                              variant="underlined"
                                              density="comfortable"
                                              color="blue"
                                              class="my-3"
                                              flat
                                ></v-text-field>
                            </v-col>
                            <v-col>
                                <v-dialog width="800"
                                >
                                    <template v-slot:activator="{props: activatorProps}">
                                        <v-btn v-bind="activatorProps"
                                               text="+ город"
                                               variant="elevated"
                                               density="comfortable"
                                               color="deep-purple"
                                        ></v-btn>
                                    </template>
                                    <template v-slot:default="{ isActive }">
                                        <v-card>
                                            <v-card-title>Form City</v-card-title>
                                            <v-card-text>
                                                <v-form @submit.prevent>
                                                    <v-row>
                                                        <v-text-field v-model="formCity.wiki"
                                                                      label="Wiki"
                                                                      variant="outlined"
                                                                      density="comfortable"
                                                        ></v-text-field>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col cols="8">
                                                            <v-text-field v-model="formCity.name"
                                                                          label="Название"
                                                                          variant="outlined"
                                                                          density="comfortable"
                                                            ></v-text-field>
                                                        </v-col>
                                                        <v-col cols="4">
                                                            <v-text-field v-model="formCity.population"
                                                                          label="Население"
                                                                          variant="outlined"
                                                                          density="comfortable"
                                                            ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col>
                                                            <v-autocomplete :items="regions"
                                                                            :item-title="'name'"
                                                                            :item-value="'id'"
                                                                            label="Регион"
                                                                            density="comfortable"
                                                                            color="purple"
                                                                            v-model="formCity.region_id"
                                                            ></v-autocomplete>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col>
                                                            <v-text-field v-model="formCity.yandexmapsgeo"
                                                                          label="yandex.ru/maps/geo/"
                                                                          variant="solo"
                                                                          density="comfortable"
                                                            ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col>
                                                            <v-text-field v-model="formCity.latitude"
                                                                          label="Широта"
                                                                          variant="outlined"
                                                                          density="comfortable"
                                                                          ></v-text-field>
                                                        </v-col>
                                                        <v-col>
                                                            <v-text-field v-model="formCity.longitude"
                                                                          label="Долгота"
                                                                          variant="outlined"
                                                                          density="comfortable"
                                                                          ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col>
                                                            <v-text-field v-model="formCity.twogis"
                                                                          label="2GIS"
                                                                          variant="solo"
                                                                          density="comfortable"
                                                            ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                </v-form>
                                            </v-card-text>
                                            <v-card-actions>
                                                <v-btn @click="storeCity"
                                                       text="сохранить"
                                                       variant="elevated"
                                                       color="green">
                                                </v-btn>
                                            </v-card-actions>
                                        </v-card>
                                    </template>
                                </v-dialog>
                            </v-col>
                        </v-row>
                    </template>
                    <template v-slot:item.name="{item}">
                        <Link :href="route('city.show', item.id)"
                        >
                            <span class="font-UnderdogRegular text-2xl"
                            >
                                {{item.name}}</span>
                        </Link>
                    </template>
                    <template v-slot:item.wiki="{item}">
                        <a :href="item.wiki" target="_blank"
                           class="text-xs text-zinc-800"
                        >
                            {{item.wiki}}
                        </a>
                    </template>
                    <template v-slot:item.yandexmapsgeo="{item}">
                        <a :href="item.yandexmapsgeo"
                           target="_blank"
                           class="mx-3"
                        >
                            yandex</a>
                        <a :href="item.twogis"
                           target="_blank"
                           class="mx-3"
                        >
                            2gis
                        </a>
                    </template>
                    <template v-slot:item.region_id="{item}">
                        <span class="text-sm font-mono">{{item.region.name}}</span>
                    </template>
                    <template v-slot:item.latitude="{item}">
                        <span class="text-sm">{{item.latitude}}</span>
                    </template>
                    <template v-slot:item.longitude="{item}">
                        <span class="text-xs">{{item.longitude}}</span>
                    </template>
                </v-data-table>
            </v-col>
        </v-row>
    </v-container>
</template>
