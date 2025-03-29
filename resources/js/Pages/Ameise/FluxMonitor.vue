<script setup>
import {Head, useForm, Link} from "@inertiajs/vue3";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
defineOptions({
    layout: VerwalterLayout,
})

let stages = ref();
function indexStages(){
    axios.get(route('api.stages.index')).then(function (response){
        stages.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    indexStages()
})
</script>
<template>
    <Head>
        <title>Flux Monitor</title>
    </Head>

    <v-container fluid>
        <v-row>
            <v-col v-for="stage in stages"
                   cols="3"
            >
                <v-card>
                    <v-card-title>{{stage.name}}</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="unit in stage.units"
                                         class="text-xs"
                            >
                                <Link :href="route('api.unit', unit.id)">
                                    {{unit.name}}
                                </Link>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>
            <v-col></v-col>
        </v-row>
    </v-container>
</template>
