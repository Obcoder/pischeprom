<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

const brands = ref([])

function indexBrands(){
    axios.get(route('brands.index')).then(function (response){
        brands.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    indexBrands()
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col></v-col>
            <v-col>
                <v-data-table :items="brands"></v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
