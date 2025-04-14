<script setup>
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {Link} from "@inertiajs/vue3";
import {ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})
const props = defineProps({
    region: Object,
})

const regionShow = ref()

function fetchRegion(id){
    axios.get(route('regions.show', id)).then(function (response){
        regionShow.value = response.data
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
                <span>{{region.name}}</span>
            </v-col>
            <v-col></v-col>
        </v-row>
        <v-row>
            <v-col>
                <div v-for="city in region.cities"
                     class="p-2 my-1 font-UnderdogRegular text-sm text-fuchsia-900 border border-fuchsia-950 rounded"
                >
                    <Link :href="route('city.show', city.id)">
                        <span>{{city.name}}</span>
                    </Link>
                </div>
            </v-col>
            <v-col></v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>

</style>
