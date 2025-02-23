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

let listMeasures = ref();
function apiIndexMeasures(){
    axios.get(route('api.measures')).then(function (response){
        listMeasures.value = response.data;
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
    apiIndexMeasures();
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
        <v-row>
            <v-col>
                {{date.format(check.date, 'fullDate')}}
            </v-col>
            <v-col>
                {{check.amount}}
            </v-col>
            <v-col>
                {{check.entity.name}}
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="1"></v-col>
            <v-col>
                <v-data-table :items="listCommodities"
                              :headers="headersCommodities"
                              hover="hover"
                >
                    <template v-slot:top>
                        <v-form @submit.prevent
                                v-if="showAddCommodity"
                        >
                            <v-row>
                                <v-col>
                                    <v-select :items="listCommoditiesAll"
                                              :item-value="'id'"
                                              :item-title="'name'"
                                              variant="solo"
                                              density="comfortable"
                                              v-model="formCommodityCheck.commodity_id"
                                    ></v-select>
                                </v-col>
                            </v-row>
                            <v-row>
                                <v-col>
                                    <v-text-field v-model="formCommodityCheck.quantity"
                                                  label="Кол-во"
                                                  density="comfortable"
                                    ></v-text-field>
                                </v-col>
                                <v-col>
                                    <v-select :items="listMeasures"
                                              :item-value="'id'"
                                              :item-title="'name'"
                                              variant="solo"
                                              density="comfortable"
                                              v-model="formCommodityCheck.measure_id"
                                    ></v-select>
                                </v-col>
                                <v-col>
                                    <v-text-field v-model="formCommodityCheck.price"
                                                  label="Цена"
                                                  density="comfortable"
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
                    </template>
                    <template v-slot:item.measure_id="{item}">
                        {{item.measure.name}}
                    </template>
                </v-data-table>
            </v-col>
            <v-col cols="1"></v-col>
        </v-row>
    </v-container>
</template>
