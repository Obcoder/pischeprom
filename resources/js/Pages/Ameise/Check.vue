<script setup>
import {useDate} from "vuetify";
import {Link, useForm} from "@inertiajs/vue3";
import {onMounted, ref} from "vue";
import {list} from "postcss";
import axios from "axios";

const date = useDate();
const props = defineProps({
    check: Object,
})

let showAddCommodity = ref(false);
let listCommoditiesInCheck = ref();
const headersCommodities = ref([
        {
            title: 'name',
            key: 'name',
            class: 'bg-red-100',
        },
        {
            title: 'quantity',
            key: 'pivot.quantity',
        },
        {
            title: 'measure',
            key: 'pivot.measure_id',
        },
        {
            title: 'price',
            key: 'pivot.price',
        },
        {
            title: 'total_price',
            key: 'pivot.total_price',
            class: 'bg-green-300',
        },
    ]
);

let listCommoditiesAll = ref();
function apiIndexCommodities(){
    axios.get(route('api.commodities')).then(function (response){
        listCommoditiesAll.value = response.data;
    }).catch(function (error){
        console.log(error);
    });
}

let measures = ref();
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

const formCommodityCheck = useForm({
    check_id: null,
    commodity_id: null,
    quantity: null,
    measure_id: null,
    price: null,
})
function storeCommodityToCheck(){
    formCommodityCheck.check_id = props.check.id;
    formCommodityCheck.post(route('api.checkcommodity.store'), {
        replace: true,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formCommodityCheck.reset();
        },
    })
}

onMounted(()=>{
    listCommoditiesInCheck.value = props.check.commodities;
    apiIndexCommodities();
    indexMeasures();
})
</script>

<template>
    <v-container class="border border-1 rounded">
        <v-row>
            <v-col></v-col>
            <v-col></v-col>
            <v-col cols="1">
                <v-btn text="+ commodity"
                       @click="showAddCommodity =! showAddCommodity"
                ></v-btn>
            </v-col>
        </v-row>
        <v-row class="rounded border border-black-1">
            <v-col>
                {{date.format(check.date, 'fullDate')}}
            </v-col>
            <v-col>
                {{check.entity.name}}
            </v-col>
            <v-col>
                {{check.amount}}
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="3"></v-col>
            <v-col cols="6">
                <v-data-table :items="listCommoditiesInCheck"
                              :headers="headersCommodities"
                              hover="hover"
                >
                    <template v-slot:top>
                        <v-sheet v-if="showAddCommodity">
                            <v-form @submit.prevent
                                    class="bg-emerald-600 p-1 border border-1 border-lime-950"
                            >
                                <v-row>
                                    <v-col>
                                        <v-autocomplete :items="listCommoditiesAll"
                                                        :item-value="'id'"
                                                        :item-title="'name'"
                                                        variant="solo"
                                                        density="compact"
                                                        v-model="formCommodityCheck.commodity_id"
                                        ></v-autocomplete>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col>
                                        <v-text-field v-model="formCommodityCheck.quantity"
                                                      label="Кол-во"
                                                      density="compact"
                                                      variant="outlined"
                                        ></v-text-field>
                                    </v-col>
                                    <v-col>
                                        <v-select :items="measures"
                                                  :item-value="'id'"
                                                  :item-title="'name'"
                                                  variant="solo"
                                                  density="compact"
                                                  v-model="formCommodityCheck.measure_id"
                                        ></v-select>
                                    </v-col>
                                    <v-col>
                                        <v-text-field v-model="formCommodityCheck.price"
                                                      label="Цена"
                                                      variant="filled"
                                                      density="compact"
                                                      class="bg-purple-900"
                                        ></v-text-field>
                                    </v-col>
                                </v-row>
                                <v-row>
                                    <v-col></v-col>
                                    <v-col></v-col>
                                    <v-col>
                                        <v-btn @click="storeCommodityToCheck"
                                               text="store"
                                               variant="outlined"
                                               density="compact"
                                        ></v-btn>
                                    </v-col>
                                </v-row>
                            </v-form>
                        </v-sheet>
                    </template>
                    <template v-slot:item.measure_id="{item}">
                        {{item.measure.name}}
                    </template>
                </v-data-table>
            </v-col>
            <v-col cols="3"></v-col>
        </v-row>
    </v-container>
</template>
