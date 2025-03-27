<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let sales = ref();
function indexSales(){
    axios.get(route('api.sales')).then(function (response){
        sales.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

const headersSales = [
    {
        title: 'Created',
        key: 'created_at',
    },
    {
        title: 'Дата',
        key: 'date',
    },
    {
        title: 'Entity',
        key: 'entity_id',
    },
    {
        title: 'Сумма',
        key: 'total',
    },
]

let goods = ref();
function showGoods(sale_id){
    axios.get(route('api.sale.show', sale_id)).then(function (response){
        goods.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    indexSales();
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-data-table :items="sales"
                              :headers="headersSales"
                              items-per-page="250"
                              density="compact"
                              hover="true"
                >
                    <template v-slot:item.entity_id="{item}">
                        {{item.entity.name}}
                    </template>
                    <template v-slot:item.total="{item}">
                        <span @click="showGoods(item.id)">
                            {{item.total}}
                        </span>
                    </template>
                </v-data-table>
            </v-col>
            <v-col>
                {{goods}}
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
