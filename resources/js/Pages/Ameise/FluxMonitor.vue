<script setup>
import {Head, useForm, Link} from "@inertiajs/vue3";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
import {onMounted, ref} from "vue";
import axios from "axios";
import {useHead} from "@vueuse/head";
defineOptions({
    layout: VerwalterLayout,
})

let stages = ref([])

function indexStages(){
    axios.get(route('stages.index')).then(function (response){
        stages.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}

onMounted(()=>{
    indexStages()
})

useHead({
    title: `Монитор текущей работы`,
    meta: [
        {
            name: 'description',
            content: `Монитор текущей работы`,
        }
    ]
})
</script>
<template>
    <v-container fluid>
        <v-row>
            <v-col>

            </v-col>
        </v-row>
        <v-row>
            <v-col cols="12"
                   class="flex flex-row"
            >
                <v-card v-for="stage in stages"
                        variant="outlined"
                        density="compact"
                >
                    <v-card-title>{{stage.name}}</v-card-title>
                    <v-card-text>
                        <v-list>
                            <v-list-item v-for="unit in stage.units"
                                         class="text-xs"
                            >
                                <Link :href="route('unit.show', unit.id)">
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
