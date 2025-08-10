<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {route} from "ziggy-js";
defineOptions({
    layout: VerwalterLayout,
})

const notes = ref([])

//      N O T E S
function indexNotes(){
    axios.get(route('notes.index')).then(function (response){
        notes.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const headerNotes = ref([
    {
        key: 'name',
        title: 'Name',
        align: 'start',
        sortable: true,
    },
])

onMounted(()=>{
    indexNotes()
})
</script>

<template>
    <v-container fluid>
        <v-row>
            <v-col lg="2">
                <v-data-table :items="notes"
                              items-per-page="100"
                              :headers="headerNotes"
                              fixed-header
                              height="600px"
                              density="compact"
                              class="border rounded text-xs"
                              hover
                ></v-data-table>
            </v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
