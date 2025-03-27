<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {ref} from "vue";
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
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-data-table :items="sales"
                              density="compact"
                              hover="true"
                ></v-data-table>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
