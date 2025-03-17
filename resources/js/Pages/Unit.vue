<script setup>
import { useDate } from 'vuetify'
import {onMounted, ref} from "vue";
import axios from "axios";
import {useForm, Link} from "@inertiajs/vue3";
const date = useDate()
const props = defineProps({
    unit: Object,
    products: Object,
})
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})

let listProducts = ref();
function apiIndexProducts(){
    axios.get(route('api.products')).then(function (response){
        listProducts.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let listMeasures = ref();
function apiIndexMeasures(){
    axios.get(route('api.measures')).then(function (response){
        listMeasures.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
let showFormConsumption = ref(false)
const formConsumption = useForm({
    unit_id: props.unit.id,
    product_id: null,
    quantity: null,
    measure_id: null,
})
function storeConsumption(){
    formConsumption.post(route('api.consumption.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formConsumption.reset();
            showFormConsumption.value = false;
        },
    });
}

let showFormBuilding = ref();
let buildings = ref([]);
function apiIndexBuildings(like){
    axios.get(route('api.buildings'), {
        params: {
            search: like,
        }
    }).then(function (response){
        buildings.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}
const formatBuildingTitle = (building) => {
    if (!building) return '';
    return `${building.city?.name || ' - '}: ${building.address}`;
};
const formBuildingUnit = useForm({
    building_id: null,
    unit_id: props.unit.id,
    location_id: null,
})
function storeBuildingUnit(){
    formBuildingUnit.post(route('api.building_unit.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formBuildingUnit.reset();
        },
    })
}

let entities = ref();
function apiIndexEntities(like){
    axios.get(route('api.entities'), {
        params: {
            search: like,
        }
    }).then(function (response){
        entities.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}


const headersConsumptions = ref([
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
])
const headersEntities = ref([
    {
        title: 'Вид',
        key: 'classification.name',
    },
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'Телефоны',
        key: 'telephones',
    },
])


onMounted(()=> {
    apiIndexProducts();
    apiIndexMeasures();
    apiIndexBuildings();
    apiIndexEntities();
})

let da = new Date();
function timeDiff(time){
    let d = new Date();
    let diffMilliseconds = d - Date.parse(time);
    let denominator = 1000 * 3600 * 24;
    return Math.round(diffMilliseconds/denominator);
}
const sendEmail = async () => {
    try {
        const response = await axios.post(route('api.mail'));
        // console.log(response);
    } catch (error) {
        console.error('Ошибка при отправке:', error);
    }
};
</script>

<template>
    <Head>
        <title>Unit</title>
    </Head>

    <v-container>
        <v-row class="h-1/3"
        >
            <v-col cols="4">
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
                    <v-card-title>Entities</v-card-title>
                    <v-card-text>
                        <v-data-table :items="unit.entities"
                                      :headers="headersEntities"
                                      hover="true"
                        >
                            <template v-slot:item.telephones="{item}">
                                <v-sheet>
                                    <div v-for="telephone in item.telephones">
                                        {{telephone.number}}
                                    </div>
                                </v-sheet>
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-card-title>Labels</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="label in unit.labels">
                                <span>{{label.name}}</span>
                                <v-divider thickness="1"
                                           opacity="100"
                                ></v-divider>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn text="добавить"
                        ></v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="5">
                <v-card>
                    <v-card-title>
                        Consumptions
                    </v-card-title>
                    <v-card-subtitle>
                        <v-btn @click="showFormConsumption = !showFormConsumption"
                               text="добавить"
                               variant="text"
                        ></v-btn>
                    </v-card-subtitle>
                    <v-card-text>
                        <v-data-table :items="unit.consumptions"
                                      :headers="headersConsumptions"
                                      density="comfortable"
                                      hover="hover"
                                      class="text-sm"
                        >
                            <template v-slot:top>
                                <v-form @submit.prevent
                                        v-if="showFormConsumption"
                                        style="border: 2px solid red; padding: 20px;">
                                    <v-row>
                                        <v-col cols="7">
                                            <v-autocomplete :items="listProducts"
                                                            :item-title="'rus'"
                                                            :item-value="'id'"
                                                            v-model="formConsumption.product_id"
                                                            variant="outlined"
                                                            density="compact"
                                            ></v-autocomplete>
                                        </v-col>
                                        <v-col cols="3">
                                            <v-text-field v-model="formConsumption.quantity"
                                                          label="quantity"
                                                          variant="solo"
                                                          density="compact"
                                            ></v-text-field>
                                        </v-col>
                                        <v-col cols="2">
                                            <v-select :items="listMeasures"
                                                      :item-title="'name'"
                                                      :item-value="'id'"
                                                      v-model="formConsumption.measure_id"
                                                      variant="outlined"
                                                      density="compact"
                                            ></v-select>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col></v-col>
                                        <v-col></v-col>
                                        <v-col>
                                            <v-btn @click="storeConsumption"
                                                   text="store"
                                                   variant="outlined"
                                            ></v-btn>
                                        </v-col>
                                    </v-row>
                                </v-form>
                            </template>
                            <template v-slot:item.product_id="{item}">
                                <Link :href="route('product.show', item.product_id)">
                                    {{item.product.rus}}
                                </Link>
                            </template>
                            <template v-slot:item.created_at="{item}">
                                {{date.format(item.created_at, 'fullDate')}}
                            </template>
                            <template v-slot:item.measure_id="{item}">
                                {{item.measure.name}}
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col cols="4">
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
        </v-row>
        <v-row class="flex flex-row">
            <v-btn @click="sendEmail"
                   text="Отправляем email"
                   variant="elevated"
            ></v-btn>
            <v-btn @click="showFormBuilding = !showFormBuilding"
                   text="+ Building"
                   variant="elevated"
                   color="deep-orange"
            ></v-btn>
            <v-dialog v-model="showFormBuilding"
                      width="815"
            >
                <template v-slot:default="{isActive}">
                    <v-card color="yellow-lighten-3">
                        <v-card-title>Form Building</v-card-title>
                        <v-card-text>
                            <v-form @submit.prevent>
                                <v-row>
                                    <v-col>
                                        <v-autocomplete :items="buildings"
                                                        :item-title="formatBuildingTitle"
                                                        :item-value="'id'"
                                                        v-model="formBuildingUnit.building_id"
                                                        variant="outlined"
                                                        density="comfortable"
                                                        color="blue-grey"
                                        ></v-autocomplete>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col></v-col>
                                    <v-col></v-col>
                                    <v-col></v-col>
                                    <v-col>
                                        <v-btn @click="storeBuildingUnit"
                                               text="store"
                                               density="comfortable"
                                               variant="outlined"
                                               color="black"
                                        ></v-btn>
                                    </v-col>
                                </v-row>
                            </v-form>
                        </v-card-text>
                    </v-card>
                </template>
            </v-dialog>
        </v-row>
        <v-row>
            <v-col>
                <v-card color="puple">
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="building in unit.buildings">
                                {{building.address}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col>
                <v-card>
                    <v-card-title>Cities</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="building in unit.buildings"
                            >
                                {{building.city.name}}
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
