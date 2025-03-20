<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let messages = ref();
function indexMessages(){
    axios.get(route('api.messages')).then(function (response){
        messages.value = response.data;
    }).catch(function (error){
        console.log(error);
    })
}

onMounted(()=>{
    indexMessages();
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col>
                <v-data-table :items="messages"
                              density="comfortable"
                              hover="true"
                ></v-data-table>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
