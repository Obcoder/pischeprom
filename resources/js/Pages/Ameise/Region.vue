<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    region: Object,
})

const region = ref()

function fetchRegion(id){
    axios.get(route('regions.show', id)).then(function (response){
        region.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
</script>

<template>
    <v-container>
        <v-row>
            <v-col></v-col>
            <v-col>
                <span>{{props.region.name}}</span>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
