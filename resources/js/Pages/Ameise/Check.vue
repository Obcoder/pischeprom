<script setup>
import {useDate} from "vuetify";
import {Link, useForm} from "@inertiajs/vue3";
import {onMounted, ref} from "vue";
import {list} from "postcss";
import axios from "axios";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    check: Object,
})
const date = useDate()

const commodities = ref([])
const commoditiesInCheck = ref([])

//      C O M M O D I T I E S
function indexCommodities(){
    axios.get(route('commodities.index')).then(function (response){
        commodities.value = response.data
    }).catch(function (error){
        console.log(error);
    });
}
let dialogAddCommodity = ref(false)
const headersCommodities = [
    {
        title: 'name',
        key: 'name',
        width: '60%',
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
const formCommodityCheck = useForm({
    check_id: null,
    commodity_id: null,
    quantity: null,
    measure_id: null,
    price: null,
})
function storeCommodityToCheck(){
    formCommodityCheck.check_id = props.check.id;
    formCommodityCheck.post(route('web.checkcommodity.store'), {
        replace: true,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formCommodityCheck.reset()
            commoditiesInCheck.value = props.check.commodities;
        },
    })
}

let measures = ref();
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=>{
    commoditiesInCheck.value = props.check.commodities;
    indexCommodities()
    indexMeasures()
})
</script>

<template>
    <v-container class="border border-1 rounded">
        <v-row class="rounded border border-black-1 mx-10">
            <v-col>
                {{date.format(check.date, 'fullDate')}}
            </v-col>
            <v-col>
                <Link :href="route('entities.show', check.entity.id)"
                      class="text-xl font-RubikMedium"
                >
                    {{check.entity.name}}
                </Link>
            </v-col>
            <v-col>
                {{check.amount}}
            </v-col>
            <v-col lg="1">
                <v-btn text="+ commodity"
                       @click="dialogAddCommodity =! dialogAddCommodity"
                ></v-btn>
            </v-col>
        </v-row>
        <v-row>
            <v-col></v-col>
            <v-col lg="6">
                <v-data-table :items="commoditiesInCheck"
                              :headers="headersCommodities"
                              density="comfortable"
                              hover
                >
                    <template v-slot:top>
                        <v-form v-if="dialogAddCommodity"
                                @submit.prevent
                                class="bg-slate-600 p-2 border border-1 border-lime-950 rounded"
                        >
                            <v-row>
                                <v-col>
                                    <v-autocomplete :items="commodities"
                                                    :item-value="'id'"
                                                    :item-title="'name'"
                                                    v-model="formCommodityCheck.commodity_id"
                                                    label="Commodities"
                                                    variant="solo"
                                                    density="comfortable"
                                                    hide-details
                                    ></v-autocomplete>
                                </v-col>
                            </v-row>
                            <v-row>
                                <v-col>
                                    <v-text-field v-model="formCommodityCheck.quantity"
                                                  label="Кол-во"
                                                  variant="solo-filled"
                                                  density="comfortable"
                                                  hide-details
                                    ></v-text-field>
                                </v-col>
                                <v-col>
                                    <v-select :items="measures"
                                              :item-value="'id'"
                                              :item-title="'name'"
                                              v-model="formCommodityCheck.measure_id"
                                              variant="solo"
                                              density="comfortable"
                                              hide-details
                                    ></v-select>
                                </v-col>
                                <v-col>
                                    <v-text-field v-model="formCommodityCheck.price"
                                                  label="Цена"
                                                  variant="solo-filled"
                                                  density="comfortable"
                                                  class="bg-cyan-700"
                                                  hide-details
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
                    <template v-slot:item.name="{item}">
                        <span class="text-sm font-sans text-zinc-800">{{item.name}}</span>
                    </template>
                </v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
