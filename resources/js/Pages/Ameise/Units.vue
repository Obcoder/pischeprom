<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let listUnits = ref();
function apiIndexUnits(like){
    axios.get(route('api.units'), {
        params: {
            search: like,
        }
    }).then(function (response){
        listUnits.value = response.data;
    }).catch(function (error){
        console.log();
    });
}

onMounted(()=>{
    apiIndexUnits('');
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-list>
                    <v-list-item v-for="unit in listUnits">
                        <span>{{unit.name}}</span>
                    </v-list-item>
                </v-list>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
