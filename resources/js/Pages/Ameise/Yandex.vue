<script setup>
import {onMounted, ref} from "vue";
import axios from "axios";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {useHead} from "@vueuse/head";
defineOptions({
    layout: VerwalterLayout,
})

const yandexRequests = ref([])

function indexYandexRequests(){
    axios.get(route('yandex-requests.index')).then(function (response){
        yandexRequests.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    indexYandexRequests()
})

useHead({
    title: `Работа с возможностями Yandex`,
    meta: [
        {
            name: 'description',
            content: `Работа с возможностями Yandex`,
        }
    ]
})
</script>

<template>
    <v-container>
        <v-row>
            <v-col></v-col>
            <v-col>
                <v-data-table :items="yandexRequests"></v-data-table>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
