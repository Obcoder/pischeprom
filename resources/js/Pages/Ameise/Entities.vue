<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let listEntities = ref();
function apiIndexEntities(){
    axios.get(route('api.entities')).then(function (response){
        listEntities.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=>{
    apiIndexEntities();
})
</script>

<template>
    {{listEntities}}
</template>
