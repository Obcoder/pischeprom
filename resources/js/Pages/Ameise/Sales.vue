<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useDate} from "vuetify";
defineOptions({
    layout: VerwalterLayout,
})

const date = useDate()

let sales = ref();
function indexSales(){
    axios.get(route('api.sales.index')).then(function (response){
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

let sale = ref();
function showGoods(id){
    axios.get(route('api.sales.show', id)).then(function (response){
        sale.value = response.data
    }).catch(function (error){
        console.log(error)
    });
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
                    <template v-slot:item.created_at="{item}">
                        <span>{{date.format(item.created_at, 'fullDateWithWeekday')}}</span>
                    </template>
                    <template v-slot:item.entity_id="{item}">
                        {{item.entity.name}}
                    </template>
                    <template v-slot:item.total="{item}">
                        <span @click="showGoods(item.id)"
                              class="cursor-pointer"
                        >
                            {{item.total}}
                        </span>
                    </template>
                </v-data-table>
            </v-col>
            <v-col>
                <v-list>
                    <v-list-item v-for="good in sale.goods">
                        <v-row>
                            <v-col>
                                <span class="cursor-pointer">
                                    {{good.name}}
                                </span>
                            </v-col>
                            <v-col>
                                <span>
                                    {{good.pivot.price}}
                                </span>
                            </v-col>
                            <v-col>
                                <span>
                                    {{good.pivot.quantity}}
                                </span>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
